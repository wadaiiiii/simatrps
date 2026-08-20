<?php

namespace App\Http\Controllers;

use App\Services\Rps\AiRpsProviderService;
use App\Services\Rps\RpsAiContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RpsCpmkAiController extends RpsAiController
{
    private const POLICY_VERSION = 'cpmk-master-cpl-v1';

    public function generate(
        Request $request,
        string $rps,
        AiRpsProviderService $aiProvider,
        RpsAiContextService $contextService
    ): RedirectResponse {
        if ((string) $request->input('suggestion_type') !== 'cpmk_review') {
            return parent::generate($request, $rps, $aiProvider, $contextService);
        }

        $lecturerInstruction = trim((string) $request->input('instruction', ''));
        $policyInstruction = <<<'PROMPT'
[POLICY_VERSION: cpmk-master-cpl-v1]
Telaah CPMK WAJIB menjadikan CPL resmi dari master kurikulum sebagai acuan substantif utama. Pada `cpl_scope`, item dengan `source = curriculum` adalah CPL resmi mata kuliah; item tambahan dosen boleh dibaca sebagai konteks sekunder tetapi tidak boleh menggantikan CPL resmi.

Untuk SETIAP CPMK yang sudah ada:
1. Baca rumusan CPL resmi, konteks mata kuliah, rumusan CPMK, dan pemetaan CPL yang saat ini tersimpan bila ada.
2. Nilai apakah CPMK benar-benar menurunkan kemampuan yang relevan dari CPL resmi dan apakah rumusannya terukur pada level mata kuliah.
3. Gunakan `keep` bila substansi dan rumusannya sudah layak.
4. Gunakan `adapt` HANYA bila perlu penulisan ulang substantif agar lebih terukur/tepat tetapi tetap berada dalam ruang lingkup CPL resmi yang sama.
5. Gunakan `add` HANYA bila ada kompetensi penting yang jelas dituntut CPL resmi dan relevan dengan mata kuliah tetapi sungguh belum tercakup CPMK yang ada. Jangan menambah CPMK hanya untuk membuat jumlahnya lebih banyak.
6. `cpl_codes` WAJIB berisi satu atau lebih kode CPL RESMI yang benar-benar menjadi acuan telaah item tersebut. Jangan membuat kode baru dan jangan memasukkan CPL di luar CPL resmi mata kuliah.
7. Keluarkan tepat satu item review untuk setiap CPMK existing. Item `add` hanya boleh ditambahkan setelah seluruh CPMK existing ditelaah.
8. Telaah ini TIDAK mengubah pemetaan CPMK-CPL dan TIDAK menetapkan Bloom. Pemetaan formal dan Bloom dikerjakan pada langkah terpisah setelah rumusan CPMK final.
9. Alasan (`rationale`) harus menjelaskan keterkaitan substansi CPMK dengan CPL resmi yang disebut pada `cpl_codes`, bukan sekadar menyatakan "sesuai".
PROMPT;

        $request->merge([
            'instruction' => $lecturerInstruction !== ''
                ? $lecturerInstruction."\n\n".$policyInstruction
                : $policyInstruction,
        ]);

        $this->limitCpmkReviewTimeouts();

        $response = parent::generate($request, $rps, $aiProvider, $contextService);

        $this->normalizeLatestCpmkReview($request, $rps);

        return $response;
    }

    public function apply(Request $request, string $rps, string $suggestion): RedirectResponse
    {
        $rpsRow = DB::table('rps')
            ->where('id', $rps)
            ->first(['current_version_id']);

        $rpsData = $rpsRow ? get_object_vars($rpsRow) : [];
        $versionId = filled($rpsData['current_version_id'] ?? null)
            ? (string) $rpsData['current_version_id']
            : null;

        $suggestionRow = $versionId
            ? DB::table('ai_suggestions')
                ->where('id', $suggestion)
                ->where('rps_version_id', $versionId)
                ->first(['suggestion_type', 'suggestion_payload'])
            : null;

        $changedTargets = [];
        $changesCpmkStructure = false;
        $suggestionData = $suggestionRow ? get_object_vars($suggestionRow) : [];

        if (($suggestionData['suggestion_type'] ?? null) === 'cpmk_review') {
            $decoded = json_decode((string) ($suggestionData['suggestion_payload'] ?? ''), true);
            $payload = is_array($decoded) ? $decoded : [];
            $rawSelected = $request->input('selected_indices', []);
            $selected = [];

            if (is_array($rawSelected)) {
                foreach ($rawSelected as $index) {
                    $selected[] = (int) $index;
                }
                $selected = array_values(array_unique($selected));
            }

            $recommendations = $payload['recommendations'] ?? [];
            $recommendations = is_array($recommendations) ? $recommendations : [];

            foreach ($selected as $index) {
                $item = $recommendations[$index] ?? null;
                if (! is_array($item)) {
                    continue;
                }

                $action = strtolower(trim((string) ($item['action'] ?? 'keep')));
                if (! in_array($action, ['adapt', 'add'], true)) {
                    continue;
                }

                $changesCpmkStructure = true;

                if ($action === 'adapt') {
                    $target = $this->normalizeCpmkCode((string) ($item['target_code'] ?? ''));
                    if ($target !== '') {
                        $changedTargets[] = $target;
                    }
                }
            }
        }

        $response = parent::apply($request, $rps, $suggestion);

        if ($changesCpmkStructure && $versionId !== null) {
            if ($changedTargets !== []) {
                DB::table('rps_cpmks')
                    ->where('rps_version_id', $versionId)
                    ->whereIn('code', array_values(array_unique($changedTargets)))
                    ->update([
                        'bloom_level' => null,
                        'updated_at' => now(),
                    ]);
            }

            $this->invalidateDownstreamAi(
                $versionId,
                (string) $request->user()->id
            );

            $response->with(
                'success',
                'Rekomendasi CPMK diterapkan. Karena rumusan/struktur CPMK berubah, level Bloom dikosongkan pada CPMK yang diperbaiki dan Pemetaan Bloom AI serta Pemetaan CPMK → CPL AI perlu dijalankan ulang.'
            );
        }

        return $response;
    }

    private function normalizeLatestCpmkReview(Request $request, string $rps): void
    {
        $recordRow = DB::table('rps')
            ->where('id', $rps)
            ->first(['id', 'course_id', 'current_version_id']);

        if (! $recordRow) {
            return;
        }

        $record = get_object_vars($recordRow);
        $versionId = (string) ($record['current_version_id'] ?? '');
        $courseId = (string) ($record['course_id'] ?? '');

        if ($versionId === '' || $courseId === '') {
            return;
        }

        $rowObject = DB::table('ai_suggestions')
            ->where('rps_version_id', $versionId)
            ->where('suggestion_type', 'cpmk_review')
            ->where('requested_by', $request->user()->id)
            ->whereIn('status', ['pending', 'accepted'])
            ->orderByDesc('created_at')
            ->first();

        if (! $rowObject) {
            return;
        }

        $row = get_object_vars($rowObject);

        /** @var list<array{id:string, code:string, description:string}> $officialCpls */
        $officialCpls = DB::table('course_cpls')
            ->join('cpls', 'cpls.id', '=', 'course_cpls.cpl_id')
            ->where('course_cpls.course_id', $courseId)
            ->orderBy('cpls.sequence_no')
            ->get(['cpls.id', 'cpls.code', 'cpls.description'])
            ->map(static function (object $cpl): array {
                $data = get_object_vars($cpl);

                return [
                    'id' => (string) ($data['id'] ?? ''),
                    'code' => (string) ($data['code'] ?? ''),
                    'description' => (string) ($data['description'] ?? ''),
                ];
            })
            ->values()
            ->all();

        /** @var array<string, array{id:string, code:string, description:string}> $officialByCode */
        $officialByCode = [];
        foreach ($officialCpls as $cpl) {
            $officialByCode[strtoupper(trim($cpl['code']))] = $cpl;
        }

        /** @var array<string, array{id:string, code:string, description:string, bloom_level:?string}> $current */
        $current = [];
        $currentRows = DB::table('rps_cpmks')
            ->where('rps_version_id', $versionId)
            ->orderBy('sequence_no')
            ->get(['id', 'code', 'description', 'bloom_level']);

        foreach ($currentRows as $currentRow) {
            $data = get_object_vars($currentRow);
            $code = $this->normalizeCpmkCode((string) ($data['code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $current[$code] = [
                'id' => (string) ($data['id'] ?? ''),
                'code' => (string) ($data['code'] ?? $code),
                'description' => (string) ($data['description'] ?? ''),
                'bloom_level' => filled($data['bloom_level'] ?? null)
                    ? (string) $data['bloom_level']
                    : null,
            ];
        }

        $decodedPayload = json_decode((string) ($row['suggestion_payload'] ?? ''), true);
        /** @var array<string, mixed> $payload */
        $payload = is_array($decodedPayload) ? $decodedPayload : [];

        /** @var list<array<string, mixed>> $providerItems */
        $providerItems = [];
        $rawRecommendations = $payload['recommendations'] ?? [];
        if (is_array($rawRecommendations)) {
            foreach ($rawRecommendations as $candidate) {
                if (is_array($candidate)) {
                    $providerItems[] = $candidate;
                }
            }
        }

        /** @var array<string, array<string, mixed>> $byTarget */
        $byTarget = [];
        foreach ($providerItems as $item) {
            $targetCode = $this->normalizeCpmkCode((string) ($item['target_code'] ?? ''));
            if ($targetCode !== '') {
                $byTarget[$targetCode] = $item;
            }
        }

        /** @var list<array<string, mixed>> $normalized */
        $normalized = [];

        foreach ($current as $code => $existing) {
            $item = $byTarget[$code] ?? [
                'action' => 'keep',
                'target_code' => $existing['code'],
                'description' => $existing['description'],
                'bloom_level' => $existing['bloom_level'],
                'cpl_codes' => [],
                'rationale' => 'Tidak ada perubahan substantif yang diusulkan untuk CPMK ini.',
            ];

            $action = strtolower(trim((string) ($item['action'] ?? 'keep')));
            if (! in_array($action, ['keep', 'adapt'], true)) {
                $action = 'keep';
            }

            $item['action'] = $action;
            $item['target_code'] = $existing['code'];
            $item['description'] = $action === 'keep'
                ? $existing['description']
                : trim((string) ($item['description'] ?? $existing['description']));
            $item['bloom_level'] = $existing['bloom_level'];
            $normalized[] = $this->attachOfficialCplEvidence(
                $item,
                $existing,
                $officialByCode,
                $officialCpls
            );
        }

        foreach ($providerItems as $item) {
            if (strtolower(trim((string) ($item['action'] ?? 'keep'))) !== 'add') {
                continue;
            }

            $item['action'] = 'add';
            $item['target_code'] = null;
            $item['bloom_level'] = null;
            $normalized[] = $this->attachOfficialCplEvidence(
                $item,
                null,
                $officialByCode,
                $officialCpls
            );
        }

        $counts = ['keep' => 0, 'adapt' => 0, 'add' => 0];
        foreach ($normalized as $item) {
            $action = strtolower((string) ($item['action'] ?? 'keep'));
            if (array_key_exists($action, $counts)) {
                $counts[$action]++;
            }
        }

        $payload['summary'] = sprintf(
            'Telaah CPMK terhadap %d CPL master: %d dipertahankan, %d perlu perbaikan, %d usulan tambahan. Telaah ini tidak mengubah pemetaan CPMK-CPL maupun level Bloom.',
            count($officialCpls),
            $counts['keep'],
            $counts['adapt'],
            $counts['add'],
        );
        $payload['recommendations'] = $normalized;
        $payload['_review_basis'] = [
            'policy_version' => self::POLICY_VERSION,
            'curriculum_cpl_codes' => array_values(array_map(
                static fn (array $cpl): string => $cpl['code'],
                $officialCpls
            )),
            'curriculum_cpl_count' => count($officialCpls),
        ];

        $decodedContext = json_decode((string) ($row['input_context'] ?? ''), true);
        /** @var array<string, mixed> $context */
        $context = is_array($decodedContext) ? $decodedContext : [];
        $context['policy_version'] = self::POLICY_VERSION;
        $context['curriculum_cpl_codes'] = array_values(array_map(
            static fn (array $cpl): string => $cpl['code'],
            $officialCpls
        ));

        $updates = [
            'input_context' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'suggestion_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ];

        // Keep-only review tetap harus terlihat pada klik pertama. Status pending
        // berarti dosen dapat membaca hasilnya; tidak berarti data harus diubah.
        if (($row['status'] ?? null) === 'accepted') {
            $updates += [
                'status' => 'pending',
                'accepted_payload' => null,
                'decided_by' => null,
                'decided_at' => null,
            ];
        }

        DB::table('ai_suggestions')->where('id', (string) ($row['id'] ?? ''))->update($updates);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array{id:string, code:string, description:string, bloom_level:?string}|null  $existing
     * @param  array<string, array{id:string, code:string, description:string}>  $officialByCode
     * @param  list<array{id:string, code:string, description:string}>  $officialCpls
     * @return array<string, mixed>
     */
    private function attachOfficialCplEvidence(
        array $item,
        ?array $existing,
        array $officialByCode,
        array $officialCpls
    ): array {
        $codes = [];
        $rawCodes = $item['cpl_codes'] ?? [];

        if (is_array($rawCodes)) {
            foreach ($rawCodes as $code) {
                $normalizedCode = strtoupper(trim((string) $code));
                if ($normalizedCode !== '' && isset($officialByCode[$normalizedCode])) {
                    $codes[] = $normalizedCode;
                }
            }
        }

        $codes = array_values(array_unique($codes));
        $officialIds = array_values(array_filter(array_map(
            static fn (array $cpl): string => $cpl['id'],
            $officialCpls
        )));

        if ($codes === [] && $existing !== null && $existing['id'] !== '') {
            $mapped = DB::table('rps_cpmk_cpls')
                ->join('cpls', 'cpls.id', '=', 'rps_cpmk_cpls.cpl_id')
                ->where('rps_cpmk_cpls.rps_cpmk_id', $existing['id'])
                ->whereIn('cpls.id', $officialIds)
                ->orderBy('cpls.sequence_no')
                ->pluck('cpls.code')
                ->all();

            foreach ($mapped as $code) {
                $normalizedCode = strtoupper(trim((string) $code));
                if ($normalizedCode !== '' && isset($officialByCode[$normalizedCode])) {
                    $codes[] = $normalizedCode;
                }
            }
            $codes = array_values(array_unique($codes));
        }

        if ($codes === []) {
            $description = trim((string) ($item['description'] ?? ($existing['description'] ?? '')));
            $inferred = $this->inferClosestOfficialCpl($description, $officialCpls);
            if ($inferred !== null) {
                $codes = [$inferred];
            }
        }

        $item['cpl_codes'] = $codes;
        $rationale = trim((string) ($item['rationale'] ?? ''));

        if ($codes !== []) {
            $basis = 'Acuan CPL master: '.implode(', ', $codes).'.';
            if (! str_contains(mb_strtolower($rationale), 'acuan cpl master')) {
                $rationale = trim($basis.' '.$rationale);
            }
        }

        $item['rationale'] = $rationale !== ''
            ? $rationale
            : 'Telaah dilakukan terhadap CPL resmi mata kuliah dari master kurikulum.';

        return $item;
    }

    /**
     * @param  list<array{id:string, code:string, description:string}>  $officialCpls
     */
    private function inferClosestOfficialCpl(string $description, array $officialCpls): ?string
    {
        $target = $this->semanticTokens($description);
        if ($target === [] || $officialCpls === []) {
            return null;
        }

        $bestCode = null;
        $bestScore = 0;

        foreach ($officialCpls as $cpl) {
            $tokens = $this->semanticTokens($cpl['description']);
            $score = count(array_intersect($target, $tokens));

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestCode = strtoupper(trim($cpl['code']));
            }
        }

        return $bestScore > 0 && $bestCode !== '' ? $bestCode : null;
    }

    /** @return list<string> */
    private function semanticTokens(string $value): array
    {
        $stop = [
            'yang', 'dan', 'atau', 'dengan', 'dalam', 'pada', 'untuk', 'dari',
            'secara', 'mampu', 'mahasiswa', 'konsep', 'teori', 'serta', 'melalui',
            'dapat', 'berdasarkan', 'terhadap', 'suatu', 'bidang', 'keilmuan',
        ];

        $value = mb_strtolower($value);
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;
        $parts = preg_split('/\s+/u', trim($value)) ?: [];
        $tokens = [];

        foreach ($parts as $token) {
            $token = (string) $token;
            if (mb_strlen($token) < 4 || in_array($token, $stop, true)) {
                continue;
            }
            $tokens[$token] = true;
        }

        return array_values(array_keys($tokens));
    }

    private function normalizeCpmkCode(string $value): string
    {
        $value = strtoupper(trim($value));
        if ($value === '') {
            return '';
        }

        if (preg_match('/(\d+)/', $value, $match) === 1) {
            return 'CPMK-'.str_pad((string) ((int) $match[1]), 2, '0', STR_PAD_LEFT);
        }

        return $value;
    }

    private function invalidateDownstreamAi(string $versionId, string $userId): void
    {
        DB::table('ai_suggestions')
            ->where('rps_version_id', $versionId)
            ->whereIn('suggestion_type', ['bloom_mapping', 'cpl_mapping', 'sub_cpmk'])
            ->where('status', 'pending')
            ->update([
                'status' => 'rejected',
                'decided_by' => $userId,
                'decided_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function limitCpmkReviewTimeouts(): void
    {
        foreach (['groq', 'mistral', 'sambanova', 'openrouter', 'huggingface', 'cohere'] as $provider) {
            $key = 'simatrps-ai.'.$provider.'.timeout';
            $current = (int) config($key, 22);
            config([$key => max(4, min($current, 6))]);
        }
    }
}
