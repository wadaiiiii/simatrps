<?php

namespace App\Services\Rps;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class GeminiRpsService
{
    public function isConfigured(): bool
    {
        return filled(config('simatrps-ai.gemini.api_key'));
    }

    public function model(): string
    {
        return (string) config('simatrps-ai.gemini.model', 'gemini-3.6-flash');
    }

    public function testConnection(): array
    {
        $this->assertConfigured();

        $response = $this->client()->get(
            config('simatrps-ai.gemini.base_url').'/models/'.$this->model()
        );

        if (! $response->successful()) {
            $this->throwApiError($response->status(), $response->json());
        }

        return [
            'ok' => true,
            'provider' => 'gemini',
            'model' => data_get($response->json(), 'name')
                ? str_replace('models/', '', (string) data_get($response->json(), 'name'))
                : $this->model(),
        ];
    }

    public function generate(string $type, array $context, ?string $instruction = null): array
    {
        $this->assertConfigured();

        $schema = $this->schema($type);
        $system = $this->systemPrompt($type);
        $prompt = json_encode([
            'instruction_from_lecturer' => $instruction,
            'rps_context' => $context,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = $this->client()->post(
            config('simatrps-ai.gemini.base_url').'/models/'.$this->model().':generateContent',
            [
                'systemInstruction' => [
                    'parts' => [[
                        'text' => $system,
                    ]],
                ],
                'contents' => [[
                    'role' => 'user',
                    'parts' => [[
                        'text' => $prompt,
                    ]],
                ]],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'maxOutputTokens' => $this->maxOutputTokens($type),
                    'responseMimeType' => 'application/json',
                    'responseJsonSchema' => $schema,
                    'thinkingConfig' => [
                        'thinkingBudget' => $this->thinkingBudget($type),
                    ],
                ],
            ]
        );

        if (! $response->successful()) {
            $this->throwApiError($response->status(), $response->json());
        }

        $json = $response->json();
        $parts = data_get($json, 'candidates.0.content.parts', []);
        $text = collect(is_array($parts) ? $parts : [])
            ->pluck('text')
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->implode('');

        if (trim($text) === '') {
            $finish = data_get($json, 'candidates.0.finishReason');
            $block = data_get($json, 'promptFeedback.blockReason');

            throw ValidationException::withMessages([
                'ai' => $block
                    ? 'Gemini memblokir permintaan: '.$block
                    : 'Gemini tidak mengembalikan output JSON yang dapat diproses'.($finish ? ' ('.$finish.').' : '.'),
            ]);
        }

        try {
            $payload = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ValidationException::withMessages([
                'ai' => 'Output Gemini bukan JSON valid: '.$e->getMessage(),
            ]);
        }

        return [
            'payload' => $payload,
            'provider' => 'gemini',
            'model' => $this->model(),
            'response_id' => data_get($json, 'responseId'),
            'usage' => data_get($json, 'usageMetadata'),
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
            ."\n\nWAJIB: keluarkan tepat ".count($targetWeeks)." item dan HANYA untuk minggu: ".implode(', ', $targetWeeks).". Jangan melewatkan satu pun minggu target.";
        $prompt = json_encode([
            'instruction_from_lecturer' => $instruction,
            'rps_context' => $context,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = $this->client()->post(
            config('simatrps-ai.gemini.base_url').'/models/'.$this->model().':generateContent',
            [
                'systemInstruction' => ['parts' => [['text' => $system]]],
                'contents' => [[
                    'role' => 'user',
                    'parts' => [['text' => $prompt]],
                ]],
                'generationConfig' => [
                    'temperature' => 0.15,
                    'maxOutputTokens' => 3600,
                    'responseMimeType' => 'application/json',
                    'responseJsonSchema' => $schema,
                    'thinkingConfig' => [
                        'thinkingBudget' => $this->thinkingBudget('weekly_plan'),
                    ],
                ],
            ]
        );

        if (! $response->successful()) {
            $this->throwApiError($response->status(), $response->json());
        }

        $json = $response->json();
        $parts = data_get($json, 'candidates.0.content.parts', []);
        $text = collect(is_array($parts) ? $parts : [])
            ->pluck('text')
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->implode('');

        if (trim($text) === '') {
            throw ValidationException::withMessages(['ai' => 'Gemini tidak mengembalikan batch rencana mingguan.']);
        }

        try {
            $payload = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ValidationException::withMessages(['ai' => 'Output batch Gemini bukan JSON valid: '.$e->getMessage()]);
        }

        $this->validateWeeklyBatch($payload, $targetWeeks, 'Gemini');

        return [
            'payload' => $payload,
            'provider' => 'gemini',
            'model' => $this->model(),
            'response_id' => data_get($json, 'responseId'),
            'usage' => data_get($json, 'usageMetadata'),
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

    private function maxOutputTokens(string $type): int
    {
        return match ($type) {
            'cpmk_review' => 1800,
            'sub_cpmk' => 2400,
            'weekly_plan' => 6000,
            'assessment_plan' => 3200,
            default => 2400,
        };
    }

    private function thinkingBudget(string $type): int
    {
        if ($this->model() === 'gemini-2.5-flash-lite') {
            return 0;
        }

        return match ($type) {
            'cpmk_review' => 256,
            'sub_cpmk' => 384,
            'weekly_plan' => 512,
            'assessment_plan' => 384,
            default => 256,
        };
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders([
                'x-goog-api-key' => (string) config('simatrps-ai.gemini.api_key'),
            ])
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('simatrps-ai.gemini.timeout', 120))
            ->retry(1, 700, throw: false);
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw ValidationException::withMessages([
                'ai' => 'Gemini API belum dikonfigurasi. Jalankan: herd php artisan simatrps:ai-config',
            ]);
        }
    }

    private function throwApiError(int $status, mixed $body): never
    {
        $message = is_array($body)
            ? data_get($body, 'error.message')
            : null;

        $friendly = match ($status) {
            400 => 'Permintaan ke Gemini tidak dapat diproses.',
            401 => 'API key Gemini tidak valid.',
            403 => 'Akses Gemini ditolak untuk project/model ini.',
            404 => 'Model Gemini yang dipilih tidak ditemukan atau tidak tersedia.',
            429 => 'Kuota/rate limit Gemini Free sedang tercapai. Tunggu lalu coba lagi atau biarkan SiMatRPS memakai fallback Groq.',
            500, 502, 503, 504 => 'Layanan Gemini sedang mengalami gangguan sementara.',
            default => 'Permintaan ke Gemini gagal (HTTP '.$status.').',
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
Tugas: telaah CPMK kerja yang ada. Berikan rekomendasi `keep`, `adapt`, atau `add` secara selektif. Pemetaan CPL harus hanya menggunakan kode CPL pada `cpl_scope`. Jangan menghapus CPMK secara otomatis.
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
