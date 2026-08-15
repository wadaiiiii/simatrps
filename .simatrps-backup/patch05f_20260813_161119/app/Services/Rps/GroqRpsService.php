<?php

namespace App\Services\Rps;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class GroqRpsService
{
    public function isConfigured(): bool
    {
        return filled(config('simatrps-ai.groq.api_key'));
    }

    public function model(): string
    {
        return (string) config('simatrps-ai.groq.model', 'openai/gpt-oss-120b');
    }

    public function testConnection(): array
    {
        $this->assertConfigured();

        $response = $this->client()->get(
            config('simatrps-ai.groq.base_url').'/models/'.$this->model()
        );

        if (! $response->successful()) {
            $this->throwApiError($response->status(), $response->json());
        }

        return [
            'ok' => true,
            'provider' => 'groq',
            'model' => $response->json('id') ?: $this->model(),
        ];
    }

    public function generate(string $type, array $context, ?string $instruction = null): array
    {
        $this->assertConfigured();

        $schema = $this->schema($type);
        $system = $this->systemPrompt($type);

        $response = $this->client()->post(
            config('simatrps-ai.groq.base_url').'/chat/completions',
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
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'simatrps_'.$type,
                        'strict' => false,
                        'schema' => $schema,
                    ],
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
                'ai' => 'Groq tidak mengembalikan output JSON yang dapat diproses.',
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

    private function maxCompletionTokens(string $type): int
    {
        return match ($type) {
            'cpmk_review' => 1400,
            'sub_cpmk' => 1800,
            'weekly_plan' => 3200,
            'assessment_plan' => 2200,
            default => 1800,
        };
    }

    private function client(): PendingRequest
    {
        return Http::withToken((string) config('simatrps-ai.groq.api_key'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('simatrps-ai.groq.timeout', 120))
            ->retry(1, 700, throw: false);
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw ValidationException::withMessages([
                'ai' => 'Groq API belum dikonfigurasi. Jalankan: herd php artisan simatrps:ai-config',
            ]);
        }
    }

    private function throwApiError(int $status, mixed $body): never
    {
        $message = is_array($body)
            ? data_get($body, 'error.message')
            : null;

        $friendly = match ($status) {
            400 => 'Permintaan ke Groq tidak dapat diproses.',
            413 => 'Konteks AI masih terlalu besar untuk batas token Groq Free. Patch 05C seharusnya sudah merampingkan konteks; coba ulangi sekali lagi.',
            401 => 'API key Groq tidak valid.',
            403 => 'Akses Groq ditolak untuk project/model ini.',
            404 => 'Model Groq yang dipilih tidak ditemukan atau tidak tersedia.',
            429 => 'Batas gratis/rate limit Groq sedang tercapai. Tunggu lalu coba lagi.',
            default => 'Permintaan ke Groq gagal (HTTP '.$status.').',
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
Tugas: rekomendasikan Sub-CPMK yang logis dari CPMK kerja, bahan kajian, dan silabus. Setiap Sub-CPMK harus mempunyai satu CPMK induk yang benar-benar ada pada konteks dan level Bloom C1-C6.
PROMPT,
            'weekly_plan' => <<<'PROMPT'
Tugas: susun draft 14 minggu pembelajaran (minggu 1-7 dan 9-15). Jangan membuat minggu 8/16 sebagai kuliah biasa. Kaitkan dengan Sub-CPMK yang benar-benar ada. Materi harus berasal/diturunkan dari bahan kajian dan silabus yang tersedia. Metode, aktivitas, indikator, kriteria, dan asesmen harus selaras dengan Sub-CPMK. Tulis setiap field secara ringkas (idealnya satu frasa atau satu kalimat pendek) agar seluruh 14 minggu muat dalam batas token Free Plan.
PROMPT,
            'assessment_plan' => <<<'PROMPT'
Tugas: rekomendasikan struktur asesmen yang total bobotnya tepat 100% dan mengukur seluruh Sub-CPMK. UTS minggu 8 dan UAS minggu 16. Jika tugas/proyek/praktikum direkomendasikan, buat RTM yang relevan. Jangan mengarang referensi di luar konteks.
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
                            'parent_cpmk_code' => ['type' => 'string'],
                            'bloom_level' => ['type' => 'string', 'enum' => ['C1','C2','C3','C4','C5','C6']],
                            'description' => ['type' => 'string'],
                            'rationale' => ['type' => 'string'],
                        ],
                        'required' => ['parent_cpmk_code','bloom_level','description','rationale'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['summary','items'],
            'additionalProperties' => false,
        ];
    }

    private function weeklySchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'summary' => ['type' => 'string'],
                'weeks' => [
                    'type' => 'array',
                    'minItems' => 14,
                    'maxItems' => 14,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'week_number' => ['type' => 'integer', 'enum' => [1,2,3,4,5,6,7,9,10,11,12,13,14,15]],
                            'sub_cpmk_code' => ['type' => 'string'],
                            'material' => ['type' => 'string'],
                            'learning_method' => ['type' => 'string'],
                            'learning_activity' => ['type' => 'string'],
                            'assessment_indicator' => ['type' => 'string'],
                            'assessment_criteria' => ['type' => 'string'],
                            'assessment_method' => ['type' => 'string'],
                            'references' => ['type' => 'string'],
                        ],
                        'required' => [
                            'week_number','sub_cpmk_code','material','learning_method','learning_activity',
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
