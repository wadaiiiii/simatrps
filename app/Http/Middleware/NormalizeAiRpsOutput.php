<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class NormalizeAiRpsOutput
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = (string) ($request->route()?->getName() ?? '');
        $rpsId = (string) ($request->route('rps') ?? '');
        $versionId = $rpsId !== ''
            ? DB::table('rps')->where('id', $rpsId)->value('current_version_id')
            : null;

        // Bersihkan juga data lama sebelum halaman RPS dibangun, sehingga
        // hasil AI terdahulu seperti `Ubah menjadi: "..."` langsung tampil final.
        if ($routeName === 'rps.show' && $versionId) {
            $this->normalizeStoredCpmks((string) $versionId);
            $this->normalizePendingSuggestions((string) $versionId);
        }

        $response = $next($request);

        // Setelah provider AI selesai, normalisasi payload sebelum redirect GET.
        if ($versionId && in_array($routeName, ['rps.ai.generate', 'rps.ai.apply'], true)) {
            $this->normalizeStoredCpmks((string) $versionId);
            $this->normalizePendingSuggestions((string) $versionId);
        }

        return $response;
    }

    private function normalizeStoredCpmks(string $versionId): void
    {
        DB::table('rps_cpmks')
            ->where('rps_version_id', $versionId)
            ->get(['id', 'description'])
            ->each(function ($row): void {
                $clean = $this->cleanFinalStatement((string) ($row->description ?? ''));

                if ($clean !== (string) ($row->description ?? '')) {
                    DB::table('rps_cpmks')
                        ->where('id', $row->id)
                        ->update([
                            'description' => $clean,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    private function normalizePendingSuggestions(string $versionId): void
    {
        DB::table('ai_suggestions')
            ->where('rps_version_id', $versionId)
            ->where('status', 'pending')
            ->get(['id', 'suggestion_type', 'suggestion_payload'])
            ->each(function ($row): void {
                $payload = json_decode((string) $row->suggestion_payload, true);

                if (! is_array($payload)) {
                    return;
                }

                $payload = $this->normalizePayload((string) $row->suggestion_type, $payload);

                DB::table('ai_suggestions')
                    ->where('id', $row->id)
                    ->update([
                        'suggestion_payload' => json_encode(
                            $payload,
                            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                        ),
                        'updated_at' => now(),
                    ]);
            });
    }

    private function normalizePayload(string $type, array $payload): array
    {
        $payload['summary'] = $this->indonesianSummary($type, $payload);

        if ($type === 'cpmk_review') {
            $payload['recommendations'] = collect($payload['recommendations'] ?? [])
                ->map(function ($item): array {
                    if (! is_array($item)) {
                        return [];
                    }

                    $item['description'] = $this->cleanFinalStatement(
                        (string) ($item['description'] ?? '')
                    );
                    $item['rationale'] = $this->normalizeRationale(
                        'cpmk_review',
                        (string) ($item['rationale'] ?? ''),
                        (string) ($item['action'] ?? 'keep')
                    );

                    return $item;
                })
                ->filter(fn (array $item): bool => $item !== [])
                ->values()
                ->all();
        }

        if ($type === 'cpl_mapping') {
            $payload['mappings'] = collect($payload['mappings'] ?? [])
                ->map(function ($item): array {
                    if (! is_array($item)) {
                        return [];
                    }

                    $item['rationale'] = $this->normalizeRationale(
                        'cpl_mapping',
                        (string) ($item['rationale'] ?? '')
                    );

                    return $item;
                })
                ->filter(fn (array $item): bool => $item !== [])
                ->values()
                ->all();
        }

        if ($type === 'material_plan' || $type === 'sub_cpmk') {
            $payload['items'] = collect($payload['items'] ?? [])
                ->map(function ($item) use ($type): array {
                    if (! is_array($item)) {
                        return [];
                    }

                    if ($type === 'sub_cpmk') {
                        $item['description'] = $this->cleanFinalStatement(
                            (string) ($item['description'] ?? '')
                        );
                    }

                    $item['rationale'] = $this->normalizeRationale(
                        $type,
                        (string) ($item['rationale'] ?? ''),
                        (string) ($item['action'] ?? '')
                    );

                    return $item;
                })
                ->filter(fn (array $item): bool => $item !== [])
                ->values()
                ->all();
        }

        return $payload;
    }

    private function cleanFinalStatement(string $value): string
    {
        $clean = trim($value);

        // Provider kadang menulis instruksi perubahan sebagai bagian isi final.
        $clean = preg_replace(
            '/^(?:ubah\s+menjadi|diubah\s+menjadi|revisi(?:\s+menjadi)?|rumusan\s+baru|ganti\s+menjadi)\s*:\s*/iu',
            '',
            $clean
        ) ?? $clean;

        $clean = trim($clean);

        // Lepas tanda kutip pembungkus, tetapi jangan mengubah kutip di tengah teks.
        $pairs = [
            ['"', '"'],
            ["'", "'"],
            ['“', '”'],
            ['‘', '’'],
        ];

        foreach ($pairs as [$open, $close]) {
            if (
                mb_strlen($clean) >= 2
                && str_starts_with($clean, $open)
                && str_ends_with($clean, $close)
            ) {
                $clean = trim(mb_substr(
                    $clean,
                    mb_strlen($open),
                    mb_strlen($clean) - mb_strlen($open) - mb_strlen($close)
                ));
                break;
            }
        }

        return $clean;
    }

    private function indonesianSummary(string $type, array $payload): string
    {
        return match ($type) {
            'cpmk_review' => $this->cpmkSummary($payload),
            'cpl_mapping' => 'AI menyiapkan '.count($payload['mappings'] ?? [])
                .' rekomendasi pemetaan CPMK ke CPL. Periksa kecocokan setiap pemetaan sebelum diterapkan.',
            'material_plan' => 'AI menyiapkan '.count($payload['items'] ?? [])
                .' rekomendasi Bahan Kajian berdasarkan CPMK, Sub-CPMK, dan konteks mata kuliah.',
            'sub_cpmk' => 'AI menyiapkan '.count($payload['items'] ?? [])
                .' rekomendasi Sub-CPMK. Periksa CPMK induk, rumusan, dan level Bloom sebelum diterapkan.',
            'weekly_plan' => 'AI menyiapkan rencana pembelajaran untuk '.count($payload['weeks'] ?? [])
                .' pekan. Periksa kesesuaian Sub-CPMK, materi, metode, dan asesmen sebelum diterapkan.',
            'assessment_plan' => 'AI menyiapkan '.count($payload['assessments'] ?? [])
                .' rekomendasi asesmen dan '.count($payload['tasks'] ?? [])
                .' RTM. Periksa cakupan Sub-CPMK dan total bobot sebelum diterapkan.',
            default => 'Rekomendasi AI siap ditelaah. Periksa isi sebelum menerapkannya ke RPS.',
        };
    }

    private function cpmkSummary(array $payload): string
    {
        $items = collect($payload['recommendations'] ?? []);
        $changed = $items->filter(
            fn ($item): bool => is_array($item)
                && strtolower((string) ($item['action'] ?? 'keep')) !== 'keep'
        )->count();

        if ($changed === 0) {
            return 'Telaah AI menilai rumusan CPMK saat ini sudah memadai. Tidak ada perubahan substantif yang perlu diterapkan.';
        }

        return 'Telaah AI menghasilkan '.$items->count().' rekomendasi CPMK; '
            .$changed.' di antaranya memerlukan penyesuaian. Periksa rumusan dan level Bloom sebelum menerapkan.';
    }

    private function normalizeRationale(
        string $type,
        string $value,
        string $action = ''
    ): string {
        $clean = trim($value);

        if ($clean !== '' && ! $this->looksEnglish($clean)) {
            return $clean;
        }

        $action = strtolower($action);

        return match ($type) {
            'cpmk_review' => match ($action) {
                'adapt' => 'Rumusan CPMK direkomendasikan untuk diperjelas agar lebih terukur dan konsisten dengan level Bloom.',
                'add' => 'CPMK tambahan direkomendasikan untuk melengkapi capaian pembelajaran yang belum tercakup.',
                default => 'Rumusan CPMK dipertahankan karena sudah cukup jelas, terukur, dan relevan.',
            },
            'cpl_mapping' => 'Pemetaan direkomendasikan berdasarkan kesesuaian makna CPMK dengan CPL yang dipilih.',
            'material_plan' => 'Rekomendasi ini dipilih agar Bahan Kajian lebih selaras dengan capaian pembelajaran mata kuliah.',
            'sub_cpmk' => match ($action) {
                'adapt' => 'Sub-CPMK direkomendasikan untuk diperjelas agar lebih terukur dan konsisten dengan CPMK induknya.',
                'add' => 'Sub-CPMK tambahan direkomendasikan untuk melengkapi tahapan pencapaian CPMK.',
                default => 'Sub-CPMK dipertahankan karena sudah relevan dengan CPMK induknya.',
            },
            default => $clean !== '' ? $clean : 'Rekomendasi disusun berdasarkan konteks RPS yang aktif.',
        };
    }

    private function looksEnglish(string $value): bool
    {
        preg_match_all(
            '/\b(?:the|and|with|for|course|review|statement|statements|based|used|recommend|recommended|improve|clarity|measurability|assigned|new|added|kept|mapping|assessment|learning|existing|current|no)\b/iu',
            $value,
            $matches
        );

        return count($matches[0] ?? []) >= 2;
    }
}
