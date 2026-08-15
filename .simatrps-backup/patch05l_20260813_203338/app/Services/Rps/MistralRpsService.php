<?php

namespace App\Services\Rps;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class MistralRpsService
{
    public function isConfigured(): bool
    {
        return filled(config('simatrps-ai.mistral.api_key'));
    }

    public function model(): string
    {
        return (string) config('simatrps-ai.mistral.model', 'mistral-small-latest');
    }

    public function testConnection(): array
    {
        $this->assertConfigured();

        $response = $this->client()->get(
            config('simatrps-ai.mistral.base_url').'/models/'.$this->model()
        );

        if (! $response->successful()) {
            $this->throwApiError($response->status(), $response->json());
        }

        return [
            'ok' => true,
            'provider' => 'mistral',
            'model' => $response->json('id') ?: $this->model(),
        ];
    }

    public function generate(string $type, array $context, ?string $instruction = null): array
    {
        $this->assertConfigured();

        $schema = $this->schema($type);
        $system = $this->systemPrompt($type)
            ."\n\nKeluarkan HANYA JSON valid yang mengikuti schema ini:\n".json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = $this->postWithRateLimitRetry(
            config('simatrps-ai.mistral.base_url').'/chat/completions',
            [
                'model' => $this->model(),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $system,
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode([
                            'instruction_from_lecturer' => $instruction,
                            'rps_context' => $context,
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                ],
                'temperature' => 0.2,
                'max_completion_tokens' => $this->maxCompletionTokens($type),
                'response_format' => [
                    'type' => 'json_object',
                ],
            ]
        );

        if (! $response->successful()) {
            $this->throwApiError($response->status(), $response->json());
        }

        $json = $response->json();
        $text = data_get($json, 'choices.0.message.content');

        if (! is_string($text) || trim($text) === '') {
            throw ValidationException::withMessages([
                'ai' => 'Mistral tidak mengembalikan output JSON yang dapat diproses.',
            ]);
        }

        try {
            $payload = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ValidationException::withMessages([
                'ai' => 'Output AI bukan JSON valid: '.$e->getMessage(),
            ]);
        }

        return [
            'payload' => $payload,
            'model' => $json['model'] ?? $this->model(),
            'response_id' => $json['id'] ?? null,
            'usage' => $json['usage'] ?? null,
        ];
    }

    public function generateWeeklyBatch(array $context, array $targetWeeks, ?string $instruction = null): array
    {
        $this->assertConfigured();

        $allowed = [1,2,3,4,5,6,7,9,10,11,12,13,14,15];
        $targetWeeks = array_values(array_filter(array_unique(array_map('intval', $targetWeeks)), fn (int $week): bool => in_array($week, $allowed, true)));

        if ($targetWeeks === []) {
            throw ValidationException::withMessages(['ai' => 'Target minggu AI tidak valid.']);
        }

        $context['constraints']['target_weeks'] = $targetWeeks;
        $schema = $this->weeklySchema($targetWeeks);
        $system = $this->systemPrompt('weekly_plan')
            ."\n\nWAJIB: keluarkan tepat ".count($targetWeeks)." item dan HANYA untuk minggu: ".implode(', ', $targetWeeks).". Jangan melewatkan satu pun minggu target."
            ."\n\nKeluarkan HANYA JSON valid yang mengikuti schema ini:\n".json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = $this->postWithRateLimitRetry(
            config('simatrps-ai.mistral.base_url').'/chat/completions',
            [
                'model' => $this->model(),
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => json_encode([
                        'instruction_from_lecturer' => $instruction,
                        'rps_context' => $context,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                ],
                'temperature' => 0.15,
                'max_completion_tokens' => min(1500, 500 + (count($targetWeeks) * 500)),
                'response_format' => [
                    'type' => 'json_object',
                ],
            ]
        );

        if (! $response->successful()) {
            $this->throwApiError($response->status(), $response->json());
        }

        $json = $response->json();
        $text = data_get($json, 'choices.0.message.content');

        if (! is_string($text) || trim($text) === '') {
            throw ValidationException::withMessages(['ai' => 'Mistral tidak mengembalikan batch rencana mingguan.']);
        }

        try {
            $payload = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ValidationException::withMessages(['ai' => 'Output batch Mistral bukan JSON valid: '.$e->getMessage()]);
        }

        $this->validateWeeklyBatch($payload, $targetWeeks, 'Mistral');

        return [
            'payload' => $payload,
            'provider' => 'mistral',
            'model' => $json['model'] ?? $this->model(),
            'response_id' => $json['id'] ?? null,
            'usage' => $json['usage'] ?? null,
        ];
    }

    private function validateWeeklyBatch(array $payload, array $targetWeeks, string $provider): void
    {
        $actual = collect($payload['weeks'] ?? [])
            ->pluck('week_number')
            ->map(fn ($week) => (int) $week)
            ->unique()
            ->sort()
            ->values()
            ->all();
        $expected = collect($targetWeeks)->map(fn ($week) => (int) $week)->unique()->sort()->values()->all();

        if ($actual !== $expected) {
            throw ValidationException::withMessages([
                'ai' => $provider.' menghasilkan minggu tidak lengkap ('.count($actual).'/'.count($expected).').',
            ]);
        }
    }

    private function maxCompletionTokens(string $type): int
    {
        return match ($type) {
            'cpmk_review' => 1400,
            'cpl_mapping' => 1400,
            'sub_cpmk' => 1800,
            'weekly_plan' => 3200,
            'assessment_plan' => 2200,
            default => 1800,
        };
    }

    private function postWithRateLimitRetry(string $url, array $payload)
    {
        $last = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $response = $this->client()->post($url, $payload);
            $last = $response;

            if ($response->status() !== 429) {
                return $response;
            }

            $wait = $this->retryDelaySeconds($response);
            usleep((int) (($wait + 0.25) * 1_000_000));
        }

        return $last;
    }

    private function retryDelaySeconds($response): float
    {
        $retryAfter = (float) ($response->header('retry-after') ?: 0);

        if ($retryAfter <= 0) {
            $message = (string) data_get($response->json(), 'error.message', '');

            if (preg_match('/try again in\s+([0-9.]+)ms/i', $message, $match)) {
                $retryAfter = ((float) $match[1]) / 1000;
            } elseif (preg_match('/try again in\s+([0-9.]+)s/i', $message, $match)) {
                $retryAfter = (float) $match[1];
            }
        }

        if ($retryAfter <= 0) {
            $retryAfter = $this->durationToSeconds(
                (string) ($response->header('x-ratelimit-reset-tokens') ?: '')
            );
        }

        return min(max($retryAfter, 0.8), 2.5);
    }

    private function pauseIfTokenBudgetLow($response): void
    {
        $remaining = (int) ($response->header('x-ratelimit-remaining-tokens') ?: 999999);

        // A batch RPS berikutnya biasanya membutuhkan sekitar 1.5K-2.5K token.
        if ($remaining >= 2600) {
            return;
        }

        $reset = $this->durationToSeconds(
            (string) ($response->header('x-ratelimit-reset-tokens') ?: '')
        );

        if ($reset <= 0) {
            $reset = 2.0;
        }

        usleep((int) ((min($reset + 0.35, 25.0)) * 1_000_000));
    }

    private function durationToSeconds(string $value): float
    {
        $value = trim($value);

        if ($value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $seconds = 0.0;

        if (preg_match('/([0-9.]+)m/i', $value, $m)) {
            $seconds += ((float) $m[1]) * 60;
        }

        if (preg_match('/([0-9.]+)s/i', $value, $s)) {
            $seconds += (float) $s[1];
        }

        if ($seconds <= 0 && preg_match('/([0-9.]+)ms/i', $value, $ms)) {
            $seconds += ((float) $ms[1]) / 1000;
        }

        return $seconds;
    }

    private function client(): PendingRequest
    {
        return Http::withToken((string) config('simatrps-ai.mistral.api_key'))
            ->acceptJson()
            ->asJson()
            ->timeout(min((int) config('simatrps-ai.mistral.timeout', 22), 22));
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw ValidationException::withMessages([
                'ai' => 'Mistral API belum dikonfigurasi. Jalankan: herd php artisan simatrps:ai-config',
            ]);
        }
    }

    private function throwApiError(int $status, mixed $body): never
    {
        $message = is_array($body)
            ? data_get($body, 'error.message')
            : null;

        $friendly = match ($status) {
            400 => 'Permintaan ke Mistral tidak dapat diproses.',
            413 => 'Konteks AI masih terlalu besar untuk batas token Groq Free. Patch 05C seharusnya sudah merampingkan konteks; coba ulangi sekali lagi.',
            401 => 'API key Mistral tidak valid.',
            403 => 'Akses Mistral ditolak untuk project/model ini.',
            404 => 'Model Mistral yang dipilih tidak ditemukan atau tidak tersedia.',
            429 => 'Batas penggunaan Mistral sedang tercapai. SiMatRPS sudah mencoba menunggu dan mengulang otomatis; coba lagi beberapa saat jika masih gagal.',
            default => 'Permintaan ke Mistral gagal (HTTP '.$status.').',
        };

        throw ValidationException::withMessages([
            'ai' => $message ? $friendly.' '.$message : $friendly,
        ]);
    }

    private function systemPrompt(string $type): string
    {
        $base = <<<'PROMPT'
Anda adalah asisten akademik SiMatRPS untuk penyusunan RPS berbasis Outcome-Based Education (OBE) pada Program Studi Matematika.

Aturan mutlak:
1. Gunakan HANYA konteks RPS yang diberikan. Jangan mengarang data kurikulum, CPL, kode mata kuliah, referensi, atau kebijakan yang tidak ada pada konteks.
2. CPL tidak boleh dibuat atau diubah. Untuk pemetaan, gunakan hanya `cpl_scope` yang tersedia.
3. CPMK boleh direkomendasikan untuk adaptasi atau penambahan pada level RPS, tetapi jangan mengubah master kurikulum.
4. UTS harus berada pada minggu 8 dan UAS pada minggu 16.
5. Gunakan bahasa Indonesia akademik yang ringkas, jelas, terukur, dan dapat dinilai.
6. Rekomendasi harus membantu dosen mengambil keputusan. AI tidak berwenang menetapkan keputusan akademik final.
7. Bila informasi sumber tidak cukup, jangan mengarang referensi; gunakan string kosong pada bagian yang tidak dapat didukung konteks.
8. Hindari duplikasi antar CPMK/Sub-CPMK. Gunakan KKO/Bloom secara konsisten.
PROMPT;

        $specific = match ($type) {
            'cpmk_review' => <<<'PROMPT'
Tugas: telaah kualitas rumusan CPMK kerja yang ada. Gunakan `adapt` HANYA bila rumusan baru atau level Bloom benar-benar lebih baik dan berbeda secara substantif dari CPMK saat ini. Perubahan kapitalisasi, tanda baca, atau pengulangan kalimat yang sama BUKAN adaptasi; untuk itu gunakan `keep`. Gunakan `add` hanya bila ada capaian penting yang benar-benar belum tercakup oleh CPMK yang ada. Jangan menduplikasi CPMK. Pemetaan CPMK ke CPL ditangani oleh fitur terpisah, jadi jangan mengubah keterkaitan CPL melalui telaah CPMK; pertahankan cpl_codes saat ini bila tersedia. Jangan menghapus CPMK secara otomatis.
PROMPT,
            'cpl_mapping' => <<<'PROMPT'
Tugas: rekomendasikan keterkaitan CPMK ke CPL secara akademik. Telaah makna setiap CPMK dan narasi setiap CPL pada `cpl_scope`. Untuk SETIAP CPMK, rekomendasikan satu atau lebih CPL yang paling benar-benar didukung oleh CPMK tersebut. Jangan memaksakan semua CPL ke semua CPMK, jangan membuat CPL baru, dan jangan memilih CPL hanya agar semua CPL terpakai. Sertakan alasan singkat dan tingkat keyakinan.
PROMPT,
            'sub_cpmk' => <<<'PROMPT'
Tugas: telaah Sub-CPMK yang sudah ada dan rekomendasikan `keep`, `adapt`, atau `add`. Gunakan `adapt` bila Sub-CPMK lama perlu diperbaiki dan isi `target_code` dengan kode Sub-CPMK yang ada. Gunakan `add` hanya jika benar-benar perlu capaian baru. Setiap Sub-CPMK harus mempunyai satu CPMK induk yang benar-benar ada pada konteks dan level Bloom C1-C6. Jangan menduplikasi rumusan yang sudah baik.
PROMPT,
            'weekly_plan' => <<<'PROMPT'
Tugas: susun draft 14 minggu pembelajaran (minggu 1-7 dan 9-15). Jangan membuat minggu 8/16 sebagai kuliah biasa. Kaitkan dengan Sub-CPMK yang benar-benar ada. Materi harus berasal/diturunkan dari bahan kajian dan silabus yang tersedia. Untuk setiap minggu, pisahkan dengan jelas BENTUK PEMBELAJARAN, METODE PEMBELAJARAN, PENUGASAN MAHASISWA, ESTIMASI WAKTU, dan aktivitas DARING. Bentuk pembelajaran dipilih sesuai karakter materi, misalnya: Kuliah tatap muka; Kuliah tatap muka (Lab); Praktikum/Laboratorium; Tutorial; Seminar/Diskusi; Daring sinkron; Daring asinkron; atau Blended Learning. Metode pembelajaran dipilih secara pedagogis dan tidak harus sama setiap minggu. Prioritaskan bila sesuai: Small Group Discussion, Problem-Based Learning, Project-Based Learning, Discovery Learning, Self-Directed Learning, Case Method/Case Study, demonstrasi, simulasi, live coding, praktik terbimbing, atau ceramah interaktif. Jangan memakai nama metode hanya sebagai hiasan; pilih berdasarkan Sub-CPMK, materi, tingkat Bloom, dan bentuk asesmennya. Untuk kuliah sinkron, estimasi waktu mengikuti jumlah SKS pada konteks (contoh 3 SKS = 3x50 menit) kecuali konteks praktikum membutuhkan bentuk lain. Penugasan mahasiswa harus konkret, misalnya latihan praktikum, tugas terstruktur, diskusi kelompok, studi kasus, proyek, presentasi, atau belajar mandiri. Jika aktivitas daring tidak diperlukan, isi '-' agar kolom Daring pada format RPS tetap eksplisit. Indikator, kriteria, teknik asesmen, materi, pustaka, bentuk, metode, dan penugasan harus saling selaras. Tulis setiap field secara ringkas agar mudah dicetak pada tabel RPS.
PROMPT,
            'assessment_plan' => <<<'PROMPT'
Tugas: telaah asesmen/RTM yang sudah ada lalu rekomendasikan SATU rencana asesmen lengkap sebagai pengganti bila dosen menyetujuinya. Total bobot harus tepat 100% dan seluruh Sub-CPMK harus terukur. UTS minggu 8 dan UAS minggu 16. Jika tugas/proyek/praktikum direkomendasikan, buat RTM yang relevan. Jangan mengarang referensi di luar konteks.
PROMPT,
            default => throw ValidationException::withMessages(['ai' => 'Jenis rekomendasi AI tidak didukung.']),
        };

        return $base."\n\n".$specific;
    }

    private function schema(string $type): array
    {
        return match ($type) {
            'cpmk_review' => $this->cpmkSchema(),
            'cpl_mapping' => $this->cplMappingSchema(),
            'sub_cpmk' => $this->subCpmkSchema(),
            'weekly_plan' => $this->weeklySchema(),
            'assessment_plan' => $this->assessmentSchema(),
            default => throw ValidationException::withMessages(['ai' => 'Jenis rekomendasi AI tidak didukung.']),
        };
    }

    private function cpmkSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'summary' => ['type' => 'string'],
                'recommendations' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'action' => ['type' => 'string', 'enum' => ['keep', 'adapt', 'add']],
                            'target_code' => ['type' => ['string', 'null']],
                            'description' => ['type' => 'string'],
                            'bloom_level' => ['type' => ['string', 'null'], 'enum' => ['C1','C2','C3','C4','C5','C6', null]],
                            'cpl_codes' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'rationale' => ['type' => 'string'],
                        ],
                        'required' => ['action','target_code','description','bloom_level','cpl_codes','rationale'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['summary','recommendations'],
            'additionalProperties' => false,
        ];
    }

    private function cplMappingSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'summary' => ['type' => 'string'],
                'mappings' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'cpmk_code' => ['type' => 'string'],
                            'cpl_codes' => [
                                'type' => 'array',
                                'minItems' => 1,
                                'items' => ['type' => 'string'],
                            ],
                            'confidence' => [
                                'type' => 'string',
                                'enum' => ['tinggi', 'sedang', 'rendah'],
                            ],
                            'rationale' => ['type' => 'string'],
                        ],
                        'required' => ['cpmk_code', 'cpl_codes', 'confidence', 'rationale'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['summary', 'mappings'],
            'additionalProperties' => false,
        ];
    }

    private function subCpmkSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'summary' => ['type' => 'string'],
                'items' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => 14,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'action' => ['type' => 'string', 'enum' => ['keep','adapt','add']],
                            'target_code' => ['type' => ['string', 'null']],
                            'parent_cpmk_code' => ['type' => 'string'],
                            'bloom_level' => ['type' => 'string', 'enum' => ['C1','C2','C3','C4','C5','C6']],
                            'description' => ['type' => 'string'],
                            'rationale' => ['type' => 'string'],
                        ],
                        'required' => ['action','target_code','parent_cpmk_code','bloom_level','description','rationale'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['summary','items'],
            'additionalProperties' => false,
        ];
    }

    private function weeklySchema(?array $targetWeeks = null): array
    {
        $allowedWeeks = [1,2,3,4,5,6,7,9,10,11,12,13,14,15];
        $weeks = $targetWeeks ?: $allowedWeeks;
        $weeks = array_values(array_filter(array_unique(array_map('intval', $weeks)), fn (int $week): bool => in_array($week, $allowedWeeks, true)));

        return [
            'type' => 'object',
            'properties' => [
                'summary' => ['type' => 'string'],
                'weeks' => [
                    'type' => 'array',
                    'minItems' => count($weeks),
                    'maxItems' => count($weeks),
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'week_number' => ['type' => 'integer', 'enum' => $weeks],
                            'sub_cpmk_code' => ['type' => 'string'],
                            'material' => ['type' => 'string'],
                            'learning_form' => ['type' => 'string'],
                            'learning_method' => ['type' => 'string'],
                            'time_estimate' => ['type' => 'string'],
                            'student_assignment' => ['type' => 'string'],
                            'online_activity' => ['type' => 'string'],
                            'learning_activity' => ['type' => 'string'],
                            'assessment_indicator' => ['type' => 'string'],
                            'assessment_criteria' => ['type' => 'string'],
                            'assessment_method' => ['type' => 'string'],
                            'references' => ['type' => 'string'],
                        ],
                        'required' => [
                            'week_number','sub_cpmk_code','material','learning_form','learning_method','time_estimate',
                            'student_assignment','online_activity','learning_activity',
                            'assessment_indicator','assessment_criteria','assessment_method','references'
                        ],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['summary','weeks'],
            'additionalProperties' => false,
        ];
    }

    private function assessmentSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'summary' => ['type' => 'string'],
                'assessments' => [
                    'type' => 'array',
                    'minItems' => 2,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'type' => ['type' => 'string', 'enum' => ['quiz','assignment','project','presentation','practicum','uts','uas','other']],
                            'week_number' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 16],
                            'weight' => ['type' => 'number', 'minimum' => 0, 'maximum' => 100],
                            'sub_cpmk_codes' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'description' => ['type' => 'string'],
                        ],
                        'required' => ['name','type','week_number','weight','sub_cpmk_codes','description'],
                        'additionalProperties' => false,
                    ],
                ],
                'tasks' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'type' => ['type' => 'string', 'enum' => ['assignment','project','practicum','presentation','other']],
                            'assessment_name' => ['type' => 'string'],
                            'due_week' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 16],
                            'purpose' => ['type' => 'string'],
                            'instructions' => ['type' => 'string'],
                            'expected_output' => ['type' => 'string'],
                            'sub_cpmk_codes' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'required' => ['title','type','assessment_name','due_week','purpose','instructions','expected_output','sub_cpmk_codes'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['summary','assessments','tasks'],
            'additionalProperties' => false,
        ];
    }

}
