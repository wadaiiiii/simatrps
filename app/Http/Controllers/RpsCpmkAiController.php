<?php

namespace App\Http\Controllers;

use App\Services\Rps\AiRpsProviderService;
use App\Services\Rps\RpsAiContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        $suggestionRow = $rpsRow
            ? DB::table('ai_suggestions')
                ->where('id', $suggestion)
                ->where('rps_version_id', $rpsRow->current_version_id)
                ->first(['suggestion_type', 'suggestion_payload'])
            : null;

        $changedTargets = [];
        $changesCpmkStructure = false;

        if ($suggestionRow?->suggestion_type === 'cpmk_review') {
            $payload = json_decode((string) $suggestionRow->suggestion_payload, true) ?: [];
            $selected = collect($request->input('selected_indices', []))
                ->map(fn ($index) => (int) $index)
                ->unique()
                ->values();

            foreach ($selected as $index) {
                $item = $payload['recommendations'][$index] ?? null;
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

        if ($changesCpmkStructure && $rpsRow) {
            if ($changedTargets !== []) {
                DB::table('rps_cpmks')
                    ->where('rps_version_id', $rpsRow->current_version_id)
                    ->whereIn('code', array_values(array_unique($changedTargets)))
                    ->update([
                        'bloom_level' => null,
                        'updated_at' => now(),
                    ]);
            }

            $this->invalidateDownstreamAi(
                (string) $rpsRow->current_version_id,
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
        $record = DB::table('rps')
            ->where('id', $rps)
            ->first(['id', 'course_id', 'current_version_id']);

        if (! $record) {
            return;
        }

        $row = DB::table('ai_suggestions')
            ->where('rps_version_id', $record->current_version_id)
            ->where('suggestion_type', 'cpmk_review')
            ->where('requested_by', $request->user()->id)
            ->whereIn('status', ['pending', 'accepted'])
            ->orderByDesc('created_at')
            ->first();

        if (! $row) {
            return;
        }

        $officialCpls = DB::table('course_cpls')
            ->join('cpls', 'cpls.id', '=', 'course_cpls.cpl_id')
            ->where('course_cpls.course_id', $record->course_id)
            ->orderBy('cpls.sequence_no')
            ->get(['cpls.id', 'cpls.code', 'cpls.description']);

        $officialByCode = $officialCpls->keyBy(
            fn ($cpl) => strtoupper(trim((string) $cpl->code))
        );

        $current = DB::table('rps_cpmks')
            ->where('rps_version_id', $record->current_version_id)
            ->orderBy('sequence_no')
            ->get(['id', 'code', 'description', 'bloom_level'])
            ->keyBy(fn ($cpmk) => $this->normalizeCpmkCode((string) $cpmk->code));

        $payload = json_decode((string) $row->suggestion_payload, true) ?: [];
        $providerItems = collect($payload['recommendations'] ?? [])
            ->filter(fn ($item) => is_array($item));

        $byTarget = $providerItems
            ->filter(fn (array $item) => filled($item['target_code'] ?? null))
            ->keyBy(fn (array $item) => $this->normalizeCpmkCode((string) $item['target_code']));

        $normalized = [];

        foreach ($current as $code => $existing) {
            $item = $byTarget->get($code);
            if (! is_array($item)) {
                $item = [
                    'action' => 'keep',
                    'target_code' => $existing->code,
                    'description' => $existing->description,
                    'bloom_level' => $existing->bloom_level,
                    'cpl_codes' => [],
                    'rationale' => 'Tidak ada perubahan substantif yang diusulkan untuk CPMK ini.',
                ];
            }

            $action = strtolower(trim((string) ($item['action'] ?? 'keep')));
            if (! in_array($action, ['keep', 'adapt'], true)) {
                $action = 'keep';
            }

            $item['action'] = $action;
            $item['target_code'] = (string) $existing->code;
            $item['description'] = $action === 'keep'
                ? (string) $existing->description
                : trim((string) ($item['description'] ?? $existing->description));
            $item['bloom_level'] = $existing->bloom_level;
            $item = $this->attachOfficialCplEvidence(
                $item,
                $existing,
                $officialByCode,
                $officialCpls
            );

            $normalized[] = $item;
        }

        foreach ($providerItems as $item) {
            if (! is_array($item) || strtolower(trim((string) ($item['action'] ?? 'keep'))) !== 'add') {
                continue;
            }

            $item['action'] = 'add';
            $item['target_code'] = null;
            $item['bloom_level'] = null;
            $item = $this->attachOfficialCplEvidence(
                $item,
                null,
                $officialByCode,
                $officialCpls
            );
            $normalized[] = $item;
        }

        $counts = collect($normalized)->countBy(
            fn (array $item) => strtolower((string) ($item['action'] ?? 'keep'))
        );

        $payload['summary'] = sprintf(
            'Telaah CPMK terhadap %d CPL master: %d dipertahankan, %d perlu perbaikan, %d usulan tambahan. Telaah ini tidak mengubah pemetaan CPMK-CPL maupun level Bloom.',
            $officialCpls->count(),
            (int) $counts->get('keep', 0),
            (int) $counts->get('adapt', 0),
            (int) $counts->get('add', 0),
        );
        $payload['recommendations'] = $normalized;
        $payload['_review_basis'] = [
            'policy_version' => self::POLICY_VERSION,
            'curriculum_cpl_codes' => $officialCpls->pluck('code')->values()->all(),
            'curriculum_cpl_count' => $officialCpls->count(),
        ];

        $context = json_decode((string) $row->input_context, true) ?: [];
        $context['policy_version'] = self::POLICY_VERSION;
        $context['curriculum_cpl_codes'] = $officialCpls->pluck('code')->values()->all();

        $updates = [
            'input_context' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'suggestion_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ];

        // Keep-only review tetap harus terlihat pada klik pertama. Status pending
        // berarti dosen dapat membaca hasilnya; tidak berarti data harus diubah.
        if ($row->status === 'accepted') {
            $updates += [
                'status' => 'pending',
                'accepted_payload' => null,
                'decided_by' => null,
                'decided_at' => null,
            ];
        }

        DB::table('ai_suggestions')->where('id', $row->id)->update($updates);
    }

    private function attachOfficialCplEvidence(
        array $item,
        ?object $existing,
        Collection $officialByCode,
        Collection $officialCpls
    ): array {
        $codes = collect($item['cpl_codes'] ?? [])
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter(fn (string $code) => $officialByCode->has($code))
            ->unique()
            ->values();

        if ($codes->isEmpty() && $existing) {
            $codes = DB::table('rps_cpmk_cpls')
                ->join('cpls', 'cpls.id', '=', 'rps_cpmk_cpls.cpl_id')
                ->where('rps_cpmk_cpls.rps_cpmk_id', $existing->id)
                ->whereIn('cpls.id', $officialCpls->pluck('id')->all())
                ->orderBy('cpls.sequence_no')
                ->pluck('cpls.code')
                ->map(fn ($code) => strtoupper(trim((string) $code)))
                ->unique()
                ->values();
        }

        if ($codes->isEmpty()) {
            $description = trim((string) ($item['description'] ?? ($existing->description ?? '')));
            $inferred = $this->inferClosestOfficialCpl($description, $officialCpls);
            if ($inferred !== null) {
                $codes = collect([$inferred]);
            }
        }

        $item['cpl_codes'] = $codes->all();
        $rationale = trim((string) ($item['rationale'] ?? ''));

        if ($codes->isNotEmpty()) {
            $basis = 'Acuan CPL master: '.$codes->implode(', ').'.';
            if (! str_contains(mb_strtolower($rationale), 'acuan cpl master')) {
                $rationale = trim($basis.' '.$rationale);
            }
        }

        $item['rationale'] = $rationale !== ''
            ? $rationale
            : 'Telaah dilakukan terhadap CPL resmi mata kuliah dari master kurikulum.';

        return $item;
    }

    private function inferClosestOfficialCpl(string $description, Collection $officialCpls): ?string
    {
        $target = $this->semanticTokens($description);
        if ($target === [] || $officialCpls->isEmpty()) {
            return null;
        }

        $ranked = $officialCpls
            ->map(function ($cpl) use ($target): array {
                $tokens = $this->semanticTokens((string) $cpl->description);

                return [
                    'code' => strtoupper(trim((string) $cpl->code)),
                    'score' => count(array_intersect($target, $tokens)),
                ];
            })
            ->sortByDesc('score')
            ->values();

        $best = $ranked->first();

        return is_array($best) && (int) ($best['score'] ?? 0) > 0
            ? (string) $best['code']
            : null;
    }

    private function semanticTokens(string $value): array
    {
        $stop = [
            'yang', 'dan', 'atau', 'dengan', 'dalam', 'pada', 'untuk', 'dari',
            'secara', 'mampu', 'mahasiswa', 'konsep', 'teori', 'serta', 'melalui',
            'dapat', 'berdasarkan', 'terhadap', 'suatu', 'bidang', 'keilmuan',
        ];

        $value = mb_strtolower($value);
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;

        return collect(preg_split('/\s+/u', trim($value)) ?: [])
            ->filter(fn ($token) => mb_strlen((string) $token) >= 4)
            ->reject(fn ($token) => in_array((string) $token, $stop, true))
            ->unique()
            ->values()
            ->all();
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
