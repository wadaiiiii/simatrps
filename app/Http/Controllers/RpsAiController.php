<?php

namespace App\Http\Controllers;

use App\Services\Rps\RpsAssessmentSyncService;
use App\Services\Rps\AiRpsProviderService;
use App\Services\Rps\RpsAiContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RpsAiController extends Controller
{
    public function generate(
        Request $request,
        string $rps,
        AiRpsProviderService $aiProvider,
        RpsAiContextService $contextService
    ): RedirectResponse {
        $this->extendAiExecutionTime();

        [$record, $version] = $this->context($request, $rps);

        $data = $request->validate([
            'suggestion_type' => ['required', Rule::in([
                'cpmk_review',
                'bloom_mapping',
                'cpl_mapping',
                'material_plan',
                'sub_cpmk',
                'weekly_plan',
                'assessment_plan',
            ])],
            'instruction' => ['nullable', 'string', 'max:3000'],
        ]);

        $context = $contextService->build($record, $version, $data['suggestion_type']);

        $providerType = $data['suggestion_type'] === 'bloom_mapping'
            ? 'cpmk_review'
            : $data['suggestion_type'];

        $effectiveInstruction = $data['instruction'] ?? null;
        if ($data['suggestion_type'] === 'bloom_mapping') {
            $bloomInstruction = 'Fokus HANYA pada klasifikasi Taksonomi Bloom untuk setiap CPMK yang sudah ada. '
                .'Jangan mengubah rumusan CPMK, jangan menambah atau menghapus CPMK, dan jangan memetakan CPL. '
                .'Kembalikan tepat satu rekomendasi untuk SETIAP CPMK dengan target_code yang sama, description yang sama persis, '
                .'dan bloom_level C1-C6 yang paling sesuai dengan kata kerja operasional serta tuntutan kognitif rumusannya. '
                .'Gunakan action adapt bila level Bloom perlu diisi atau diubah, dan keep bila level saat ini sudah tepat. '
                .'Jangan menaikkan level hanya agar terlihat progresif: pahami/menjelaskan umumnya C2; menerapkan/menggunakan/menyelesaikan C3; '
                .'menganalisis/membandingkan C4; mengevaluasi/menilai C5; merancang/menciptakan/mengembangkan C6, dengan tetap membaca konteks rumusan.';
            $effectiveInstruction = filled($effectiveInstruction)
                ? trim($effectiveInstruction)."\n\n".$bloomInstruction
                : $bloomInstruction;
        } elseif ($data['suggestion_type'] === 'sub_cpmk') {
            $subBloomInstruction = 'Klasifikasikan Bloom setiap Sub-CPMK secara INDIVIDUAL berdasarkan kata kerja operasional dan tuntutan kognitif rumusannya. '
                .'JANGAN menyeragamkan semua Sub-CPMK pada C3 atau level lain hanya karena berada dalam satu mata kuliah. '
                .'Gunakan pola umum: mengingat/menyebutkan C1; memahami/menjelaskan/mengidentifikasi C2; menerapkan/menggunakan/menghitung/menyelesaikan C3; '
                .'menganalisis/membandingkan/membedakan C4; mengevaluasi/menilai/memvalidasi C5; merancang/menciptakan/mengembangkan C6. '
                .'Jika satu rumusan memuat beberapa KKO, pilih tuntutan kognitif tertinggi yang benar-benar menjadi hasil belajar utama. '
                .'Sub-CPMK boleh bertahap dari level lebih rendah menuju level CPMK induk, tetapi TIDAK BOLEH melebihi tuntutan Bloom CPMK induknya. '
                .'Gunakan variasi level hanya jika didukung rumusan, bukan untuk sekadar membuat distribusi berbeda.';
            $effectiveInstruction = filled($effectiveInstruction)
                ? trim($effectiveInstruction)."\n\n".$subBloomInstruction
                : $subBloomInstruction;
        }

        $contextHash = hash(
            'sha256',
            json_encode(
                [
                    'type' => $data['suggestion_type'],
                    'instruction' => trim((string) ($data['instruction'] ?? '')),
                    'ai_policy_version' => 'bloom-guard-v2',
                    'context' => $context,
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        $latestPending = DB::table('ai_suggestions')
            ->where('rps_version_id', $version->id)
            ->where('suggestion_type', $data['suggestion_type'])
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->first(['input_context']);

        if ($latestPending) {
            $latestInput = json_decode(
                (string) $latestPending->input_context,
                true
            );

            if (($latestInput['context_hash'] ?? null) === $contextHash) {
                return back()->with(
                    'success',
                    'Rekomendasi AI terbaru untuk konteks dan Preferensi AI yang sama masih tersedia. SiMatRPS memakai hasil yang sudah ada tanpa memanggil provider lagi.'
                );
            }
        }

        try {
            $result = $aiProvider->generate(
                $providerType,
                $context,
                $effectiveInstruction
            );
        } catch (ValidationException $error) {
            throw $error;
        } catch (\Throwable $error) {
            report($error);

            throw ValidationException::withMessages([
                'ai' => 'Layanan AI tidak dapat menyelesaikan permintaan. '
                    .'Coba kembali beberapa saat lagi atau aktifkan provider backup. '
                    .'Detail teknis: '.$error->getMessage(),
            ]);
        }

        if ($data['suggestion_type'] === 'cpmk_review') {
            $result['payload'] = $this->sanitizeCpmkReviewPayload(
                $result['payload'] ?? [],
                $version
            );
        } elseif ($data['suggestion_type'] === 'bloom_mapping') {
            $result['payload'] = $this->sanitizeBloomMappingPayload(
                $result['payload'] ?? [],
                $version
            );
        } elseif ($data['suggestion_type'] === 'sub_cpmk') {
            $result['payload'] = $this->sanitizeSubCpmkPayload(
                $result['payload'] ?? [],
                $version
            );
        }

        // Satu tipe hanya mempunyai satu rekomendasi pending aktif.
        // Ini mencegah UI membaca rekomendasi lama dan memberi kesan
        // "berhasil" padahal hasil terbaru tidak menghasilkan perubahan.
        DB::table('ai_suggestions')
            ->where('rps_version_id', $version->id)
            ->where('suggestion_type', $data['suggestion_type'])
            ->where('status', 'pending')
            ->update([
                'status' => 'rejected',
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
                'updated_at' => now(),
            ]);

        if (in_array($data['suggestion_type'], ['cpmk_review', 'bloom_mapping'], true)) {
            $actionable = collect($result['payload']['recommendations'] ?? [])
                ->contains(fn ($item) =>
                    is_array($item)
                    && in_array(strtolower((string) ($item['action'] ?? 'keep')), ['adapt', 'add'], true)
                );

            if (! $actionable) {
                DB::table('ai_suggestions')->insert([
                    'id' => (string) Str::uuid(),
                    'rps_version_id' => $version->id,
                    'suggestion_type' => $data['suggestion_type'],
                    'status' => 'accepted',
                    'input_context' => json_encode([
                        'provider' => $result['provider'] ?? null,
                        'model' => $result['model'] ?? null,
                        'instruction' => $data['instruction'] ?? null,
                        'context_hash' => $contextHash,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'suggestion_payload' => json_encode($result['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'accepted_payload' => json_encode($result['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'requested_by' => $request->user()->id,
                    'decided_by' => $request->user()->id,
                    'decided_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return back()->with(
                    'success',
                    $data['suggestion_type'] === 'bloom_mapping'
                        ? 'Pemetaan Bloom AI selesai: level Bloom CPMK yang dianalisis sudah sesuai; tidak ada perubahan yang perlu diterapkan.'
                        : 'Telaah AI selesai: CPMK sudah memadai dan tidak ada perubahan substantif yang perlu diterapkan.'
                );
            }
        }

        DB::table('ai_suggestions')->insert([
            'id' => (string) Str::uuid(),
            'rps_version_id' => $version->id,
            'suggestion_type' => $data['suggestion_type'],
            'status' => 'pending',
            'input_context' => json_encode([
                'provider' => $result['provider'] ?? null,
                'model' => $result['model'],
                'fallback_used' => (bool) ($result['fallback_used'] ?? false),
                'primary_error' => $result['primary_error'] ?? null,
                'response_id' => $result['response_id'],
                'usage' => $result['usage'],
                'instruction' => $data['instruction'] ?? null,
                'context_hash' => $contextHash,
                'context' => $context,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'suggestion_payload' => json_encode(
                $result['payload'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            'requested_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $providerUsed = strtoupper((string) ($result['provider'] ?? 'AI'));
        $fallbackNote = (bool) ($result['fallback_used'] ?? false)
            ? " Provider utama gagal/dilewati; rekomendasi dibuat dengan {$providerUsed}."
            : " Provider: {$providerUsed}.";

        return back()->with(
            'success',
            'Rekomendasi AI berhasil dibuat. Review hasilnya sebelum diterapkan.'
                .$fallbackNote
        );
    }

    public function generateWeek(
        Request $request,
        string $rps,
        int $week,
        AiRpsProviderService $aiProvider,
        RpsAiContextService $contextService
    ): RedirectResponse {
        // One week = one AI request. Keep the request below common nginx/Herd
        // gateway timeouts instead of processing 14 weeks sequentially.
        if (function_exists('set_time_limit')) {
            @set_time_limit(55);
        }
        @ini_set('max_execution_time', '55');

        [$record, $version] = $this->context($request, $rps);

        $data = $request->validate([
            'instruction' => ['nullable', 'string', 'max:3000'],
            'overwrite' => ['nullable', 'boolean'],
        ]);

        $weekly = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $version->id)
            ->where('week_number', $week)
            ->first();

        abort_unless($weekly, 404);

        if ((bool) $weekly->is_exam || in_array($week, [8, 16], true)) {
            throw ValidationException::withMessages([
                'ai' => 'Minggu UTS/UAS tidak disusun dengan AI pertemuan.',
            ]);
        }

        $targetSub = null;

        if (filled($weekly->rps_sub_cpmk_id ?? null)) {
            $targetSub = DB::table('rps_sub_cpmks')
                ->where('id', $weekly->rps_sub_cpmk_id)
                ->where('rps_version_id', $version->id)
                ->first([
                    'id',
                    'code',
                    'sequence_no',
                    'description',
                    'bloom_level',
                ]);
        }

        $targetSub ??= $this->targetSubCpmkForWeek($version->id, $week);

        if (! $targetSub) {
            throw ValidationException::withMessages([
                'ai' => 'Belum ada Sub-CPMK. Susun Sub-CPMK terlebih dahulu sebelum menggunakan AI per minggu.',
            ]);
        }

        $context = $contextService->buildWeekContext(
            $record,
            $version,
            $week,
            $targetSub->code
        );

        $indicatorInstruction = <<<'PROMPT'
Untuk minggu ini, jangan menyalin, memendekkan, atau sekadar memparafrase rumusan `target_sub_cpmk` pada `assessment_indicator`.
Turunkan indikator penilaian BARU sebagai bukti ketercapaian yang dapat diamati dan dinilai. Gunakan konteks `parent_cpmk`, `target_materials`, `target_assessments`, `current_week`, dan level Bloom untuk membuat indikator lebih spesifik terhadap materi minggu tersebut.
Indikator ideal memuat 2-3 tindakan/bukti operasional, misalnya mengidentifikasi unsur pada contoh, menjelaskan hubungan/argumen, menerapkan prosedur pada kasus, membandingkan hasil, menganalisis kesalahan, atau menghasilkan produk yang relevan—sesuaikan dengan level Bloom dan bidang ilmu pada konteks.
JANGAN menyebut kode Sub-CPMK, frasa "sesuai rumusan", "menunjukkan ketercapaian", atau membuka kalimat dengan "Mahasiswa mampu/dapat". Mulai langsung dengan kata kerja operasional.
Boleh menggunakan pengetahuan keilmuan dan pedagogis umum untuk menurunkan contoh bukti belajar yang wajar, tetapi jangan mengubah atau mengarang CPL/CPMK/Sub-CPMK resmi, bobot, referensi, atau kebijakan kurikulum. Jangan membuat ambang angka/nilai baru jika tidak tersedia pada konteks.
Pastikan `assessment_criteria` menilai kualitas bukti tersebut dan `assessment_method` konsisten dengan asesmen yang tersedia.
Materi minggu WAJIB selaras dengan `target_sub_cpmk`. Prioritaskan `target_materials` bila tersedia. Jangan memilih bahan kajian hanya karena urutannya berdekatan, dan jangan mengulang bahan kajian yang tidak relevan dengan Sub-CPMK target. Jika perlu pengulangan untuk penguatan, nyatakan eksplisit sebagai pendalaman/latihan.
PROMPT;

        $effectiveInstruction = filled($data['instruction'] ?? null)
            ? trim((string) $data['instruction'])."

".$indicatorInstruction
            : $indicatorInstruction;

        try {
            $result = $aiProvider->generateWeek(
                $context,
                $week,
                $effectiveInstruction
            );
        } catch (ValidationException $error) {
            throw $error;
        } catch (\Throwable $error) {
            report($error);

            throw ValidationException::withMessages([
                'ai' => 'AI minggu '.$week.' gagal sebelum batas waktu. '
                    .'Coba lagi atau aktifkan provider backup. Detail: '.$error->getMessage(),
            ]);
        }

        $item = collect($result['payload']['weeks'] ?? [])
            ->first(fn ($candidate) =>
                (int) ($candidate['week_number'] ?? 0) === $week
            );

        if (! is_array($item)) {
            throw ValidationException::withMessages([
                'ai' => 'AI tidak mengembalikan data yang valid untuk minggu '.$week.'.',
            ]);
        }

        $subId = $targetSub->id;

        $allowedMaterials = ! empty($context['target_materials'] ?? [])
            ? $context['target_materials']
            : ($context['materials'] ?? []);

        $resolvedMaterial = $this->resolveWeekMaterial(
            (string) ($item['material'] ?? ''),
            $allowedMaterials,
            (string) ($context['target_sub_cpmk']['description'] ?? '')
        );

        $resolvedReferences = $this->resolveWeekReferenceCodes(
            (string) ($item['references'] ?? ''),
            $context['bibliography'] ?? [],
            (string) $resolvedMaterial,
            (string) ($context['target_sub_cpmk']['description'] ?? ''),
            $week
        );

        $candidate = [
            'rps_sub_cpmk_id' => $subId,
            'material_text' => $resolvedMaterial,
            'learning_form' => $item['learning_form'] ?? null,
            'learning_method' => $item['learning_method'] ?? null,
            'time_estimate' => $this->defaultTimeEstimate((int) ($context['course']['credits'] ?? 1)),
            'face_to_face_sessions' => max(1, (int) ($weekly->face_to_face_sessions ?? 1)),
            'student_assignment' => $item['student_assignment'] ?? null,
            'structured_task_sessions' => max(1, (int) ($weekly->structured_task_sessions ?? 1)),
            'online_activity' => $item['online_activity'] ?? null,
            // learning_activity adalah rincian aktivitas dalam Metode Pembelajaran.
            // Belajar Mandiri hanya disimpan sebagai frekuensi/waktu.
            'learning_activity' => $item['learning_activity'] ?? null,
            'independent_study_sessions' => max(1, (int) ($weekly->independent_study_sessions ?? 1)),
            'assessment_indicator' => $item['assessment_indicator'] ?? null,
            'assessment_criteria' => $item['assessment_criteria'] ?? null,
            'assessment_method' => $item['assessment_method'] ?? null,
            'reference_text' => $resolvedReferences,
        ];

        $overwrite = (bool) ($data['overwrite'] ?? false);
        $updates = [];

        foreach ($candidate as $key => $value) {
            if (! filled($value)) {
                continue;
            }

            $sessionField = in_array($key, [
                'face_to_face_sessions',
                'structured_task_sessions',
                'independent_study_sessions',
            ], true);
            $invalidLegacyTime = $key === 'time_estimate'
                && is_string($weekly->{$key} ?? null)
                && preg_match('/(?:^|[;\s])0\s*[×x]\s*\(/u', (string) $weekly->{$key}) === 1;

            if (
                $overwrite
                || ! filled($weekly->{$key} ?? null)
                || ($sessionField && (int) ($weekly->{$key} ?? 0) < 1)
                || $invalidLegacyTime
            ) {
                $updates[$key] = $value;
            }
        }

        if ($updates === []) {
            return back()->with(
                'success',
                'Minggu '.$week.' sudah lengkap. Tidak ada field kosong yang perlu diisi AI.'
            );
        }

        $updates['source_type'] = str_starts_with((string) ($weekly->source_type ?? ''), 'manual_allocation')
            ? 'manual_allocation_ai'
            : 'ai_accepted';
        $updates['updated_at'] = now();

        DB::table('rps_weekly_plans')
            ->where('id', $weekly->id)
            ->update($updates);

        // Store as accepted audit history, but do not keep it in the active
        // recommendation panel because the lecturer explicitly requested it
        // from the week row.
        $auditId = (string) Str::uuid();
        DB::table('ai_suggestions')->insert([
            'id' => $auditId,
            'rps_version_id' => $version->id,
            'suggestion_type' => 'weekly_week',
            'status' => 'accepted',
            'input_context' => json_encode([
                'provider' => $result['provider'] ?? null,
                'model' => $result['model'] ?? null,
                'week_number' => $week,
                'instruction' => $data['instruction'] ?? null,
                'overwrite' => $overwrite,
                'response_id' => $result['response_id'] ?? null,
                'usage' => $result['usage'] ?? null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'suggestion_payload' => json_encode(
                $result['payload'] ?? [],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            'accepted_payload' => json_encode(
                $item,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            'requested_by' => $request->user()->id,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with(
            'success',
            'AI berhasil '.($overwrite ? 'menyusun ulang' : 'melengkapi')
                .' minggu '.$week.' menggunakan '
                .strtoupper((string) ($result['provider'] ?? 'AI')).'.'
        );
    }

    public function apply(Request $request, string $rps, string $suggestion): RedirectResponse
    {
        [$record, $version] = $this->context($request, $rps);
        $row = $this->suggestion($version->id, $suggestion);

        if ($row->status !== 'pending') {
            throw ValidationException::withMessages([
                'ai' => 'Rekomendasi AI ini sudah pernah diputuskan.',
            ]);
        }

        $data = $request->validate([
            'selected_indices' => ['nullable', 'array'],
            'selected_indices.*' => ['integer', 'min:0', 'max:100'],
            'selected_assessment_indices' => ['nullable', 'array'],
            'selected_assessment_indices.*' => ['integer', 'min:0', 'max:100'],
            'selected_task_indices' => ['nullable', 'array'],
            'selected_task_indices.*' => ['integer', 'min:0', 'max:100'],
        ]);

        $payload = json_decode($row->suggestion_payload, true, 512, JSON_THROW_ON_ERROR);
        $selectedIndices = array_values(array_unique(array_map('intval', $data['selected_indices'] ?? [])));
        $selectedAssessmentIndices = array_values(array_unique(array_map('intval', $data['selected_assessment_indices'] ?? [])));
        $selectedTaskIndices = array_values(array_unique(array_map('intval', $data['selected_task_indices'] ?? [])));

        if (in_array($row->suggestion_type, ['cpmk_review', 'bloom_mapping', 'cpl_mapping', 'material_plan', 'sub_cpmk'], true) && $selectedIndices === []) {
            throw ValidationException::withMessages([
                'ai' => 'Pilih minimal satu rekomendasi yang akan diterapkan.',
            ]);
        }

        if (
            $row->suggestion_type === 'assessment_plan'
            && $selectedAssessmentIndices === []
            && $selectedTaskIndices === []
        ) {
            throw ValidationException::withMessages([
                'ai' => 'Pilih minimal satu asesmen atau RTM yang akan diterapkan.',
            ]);
        }

        $result = DB::transaction(function () use (
            $row,
            $payload,
            $selectedIndices,
            $selectedAssessmentIndices,
            $selectedTaskIndices,
            $record,
            $version,
            $request
        ): array {
            $result = match ($row->suggestion_type) {
                'cpmk_review' => $this->applyCpmkReview($payload, $selectedIndices, $record, $version, $request->user()->id),
                'bloom_mapping' => $this->applyBloomMapping($payload, $selectedIndices, $version),
                'cpl_mapping' => $this->applyCplMapping($payload, $selectedIndices, $record, $version),
                'material_plan' => $this->applyMaterialPlan($payload, $selectedIndices, $version),
                'sub_cpmk' => $this->applySubCpmk($payload, $selectedIndices, $version, $request->user()->id),
                'weekly_plan' => $this->applyWeeklyPlan($payload, $version),
                'assessment_plan' => $this->applyAssessmentPlanSelective(
                    $payload,
                    $selectedAssessmentIndices,
                    $selectedTaskIndices,
                    $version,
                    $request->user()->id
                ),
                default => throw ValidationException::withMessages(['ai' => 'Jenis rekomendasi AI tidak didukung.']),
            };

            if (($result['changed'] ?? 0) < 1 && in_array($row->suggestion_type, ['cpmk_review', 'bloom_mapping', 'cpl_mapping', 'material_plan', 'sub_cpmk', 'assessment_plan'], true)) {
                throw ValidationException::withMessages([
                    'ai' => 'Tidak ada perubahan yang diterapkan. Pilih rekomendasi ADAPT atau ADD yang valid.',
                ]);
            }

            $acceptedPayload = $payload;
            if ($selectedIndices !== []) {
                $acceptedPayload['_selected_indices'] = $selectedIndices;
            }
            if ($selectedAssessmentIndices !== []) {
                $acceptedPayload['_selected_assessment_indices'] = $selectedAssessmentIndices;
            }
            if ($selectedTaskIndices !== []) {
                $acceptedPayload['_selected_task_indices'] = $selectedTaskIndices;
            }

            DB::table('ai_suggestions')->where('id', $row->id)->update([
                'status' => 'accepted',
                'accepted_payload' => json_encode($acceptedPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
                'updated_at' => now(),
            ]);

            return $result;
        });

        return back()->with(
            'success',
            ($result['message'] ?? 'Rekomendasi AI diterapkan ke RPS.').' Rekomendasi ini sudah dikeluarkan dari daftar aktif.'
        );
    }

    public function reject(Request $request, string $rps, string $suggestion): RedirectResponse
    {
        [, $version] = $this->context($request, $rps);
        $row = $this->suggestion($version->id, $suggestion);

        DB::table('ai_suggestions')->where('id', $row->id)->update([
            'status' => 'rejected',
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Rekomendasi AI ditolak. Data RPS tidak diubah.');
    }

    private function sanitizeCpmkReviewPayload(
        array $payload,
        object $version
    ): array {
        $current = DB::table('rps_cpmks')
            ->where('rps_version_id', $version->id)
            ->get()
            ->keyBy('code');

        $items = $payload['recommendations'] ?? [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $action = strtolower((string) ($item['action'] ?? 'keep'));
            $target = $this->normalizeCpmkCode((string) ($item['target_code'] ?? ''));

            if ($action === 'adapt' && $current->has($target)) {
                $existing = $current->get($target);
                $sameDescription = $this->comparableText((string) ($item['description'] ?? ''))
                    === $this->comparableText((string) ($existing->description ?? ''));

                if ($sameDescription) {
                    $items[$index]['action'] = 'keep';
                    $items[$index]['target_code'] = $target;
                    $items[$index]['description'] = $existing->description;
                    $items[$index]['bloom_level'] = $existing->bloom_level;
                    $items[$index]['cpl_codes'] = [];
                    $items[$index]['rationale'] = 'Rumusan CPMK sudah memadai; Bloom dan CPL dipetakan pada tahap terpisah.';
                } else {
                    $items[$index]['target_code'] = $target;
                    $items[$index]['bloom_level'] = $existing->bloom_level;
                    $items[$index]['cpl_codes'] = [];
                }
            }

            if ($action === 'add') {
                $newText = $this->comparableText((string) ($item['description'] ?? ''));
                $duplicate = $current->first(fn ($row) =>
                    $newText !== ''
                    && $this->comparableText((string) ($row->description ?? '')) === $newText
                );

                if ($duplicate) {
                    $items[$index]['action'] = 'keep';
                    $items[$index]['target_code'] = $duplicate->code;
                    $items[$index]['description'] = $duplicate->description;
                    $items[$index]['bloom_level'] = $duplicate->bloom_level;
                    $items[$index]['cpl_codes'] = [];
                    $items[$index]['rationale'] = 'Usulan identik dengan CPMK yang sudah ada.';
                } else {
                    $items[$index]['bloom_level'] = null;
                    $items[$index]['cpl_codes'] = [];
                }
            }
        }

        $payload['summary'] = 'Telaah rumusan CPMK tanpa mengubah level Bloom maupun pemetaan CPL.';
        $payload['recommendations'] = $items;
        return $payload;
    }

    private function sanitizeBloomMappingPayload(array $payload, object $version): array
    {
        $current = DB::table('rps_cpmks')
            ->where('rps_version_id', $version->id)
            ->orderBy('sequence_no')
            ->get(['id', 'code', 'description', 'bloom_level'])
            ->keyBy('code');

        $byTarget = collect($payload['recommendations'] ?? [])
            ->filter(fn ($item) => is_array($item))
            ->keyBy(fn ($item) => $this->normalizeCpmkCode((string) ($item['target_code'] ?? '')));

        $items = [];
        foreach ($current as $code => $existing) {
            $candidate = $byTarget->get($code);
            if (! is_array($candidate)) {
                continue;
            }

            $providerBloom = strtoupper(trim((string) ($candidate['bloom_level'] ?? '')));
            if (! in_array($providerBloom, ['C1','C2','C3','C4','C5','C6'], true)) {
                continue;
            }

            $inferredBloom = $this->inferBloomLevel((string) $existing->description);
            $newBloom = $inferredBloom ?: $providerBloom;
            $guardAdjusted = $inferredBloom !== null && $inferredBloom !== $providerBloom;

            $oldBloom = strtoupper(trim((string) ($existing->bloom_level ?? '')));
            $same = $oldBloom === $newBloom;

            $rationale = trim((string) ($candidate['rationale'] ?? ''));
            if ($guardAdjusted) {
                $rationale = 'Guard Bloom SiMatRPS menyesuaikan hasil provider dari '.$providerBloom.' menjadi '.$newBloom
                    .' berdasarkan kata kerja operasional eksplisit pada rumusan CPMK.';
            } elseif ($rationale === '') {
                $rationale = $same
                    ? 'Level Bloom saat ini sudah sesuai dengan tuntutan kognitif CPMK.'
                    : 'Level Bloom disesuaikan dengan kata kerja operasional dan tuntutan kognitif CPMK.';
            }

            $items[] = [
                'action' => $same ? 'keep' : 'adapt',
                'target_code' => $existing->code,
                'description' => $existing->description,
                'bloom_level' => $newBloom,
                'cpl_codes' => [],
                'rationale' => $rationale,
            ];
        }

        if ($items === []) {
            throw ValidationException::withMessages([
                'ai' => 'AI belum menghasilkan pemetaan Bloom C1-C6 yang valid. Coba kembali.',
            ]);
        }

        $payload['summary'] = 'Pemetaan Taksonomi Bloom untuk CPMK yang sudah final. Rumusan CPMK dan pemetaan CPL tidak diubah.';
        $payload['recommendations'] = $items;
        return $payload;
    }

    private function sanitizeSubCpmkPayload(array $payload, object $version): array
    {
        $existingSubs = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $version->id)
            ->get(['id', 'code', 'description', 'bloom_level'])
            ->keyBy(fn ($row) => mb_strtolower(trim((string) $row->code)));

        $items = $payload['items'] ?? [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $action = strtolower((string) ($item['action'] ?? 'keep'));
            $targetCode = trim((string) ($item['target_code'] ?? ''));
            $existing = $targetCode !== ''
                ? $existingSubs->get(mb_strtolower($targetCode))
                : null;

            $description = trim((string) ($item['description'] ?? ($existing->description ?? '')));
            if ($description === '' && $existing) {
                $description = (string) $existing->description;
            }

            $parent = $this->resolveAiParentCpmk(
                $version->id,
                (string) ($item['parent_cpmk_code'] ?? ''),
                $existing ? (string) $existing->code : null,
                $description
            );

            $providerBloom = strtoupper(trim((string) ($item['bloom_level'] ?? '')));
            if (! in_array($providerBloom, ['C1','C2','C3','C4','C5','C6'], true)) {
                $providerBloom = '';
            }

            $inferredBloom = $this->inferBloomLevel($description);
            $newBloom = $inferredBloom ?: ($providerBloom !== '' ? $providerBloom : 'C2');
            $adjustments = [];

            if ($inferredBloom !== null && $providerBloom !== '' && $inferredBloom !== $providerBloom) {
                $adjustments[] = 'hasil provider '.$providerBloom.' disesuaikan menjadi '.$inferredBloom.' berdasarkan KKO rumusan';
            }

            if ($parent) {
                $parentBloom = $this->inferBloomLevel((string) $parent->description)
                    ?: strtoupper(trim((string) ($parent->bloom_level ?? '')));

                if (
                    in_array($parentBloom, ['C1','C2','C3','C4','C5','C6'], true)
                    && $this->bloomRank($newBloom) > $this->bloomRank($parentBloom)
                ) {
                    $adjustments[] = $newBloom.' diturunkan ke '.$parentBloom.' agar tidak melampaui CPMK induk '.$parent->code;
                    $newBloom = $parentBloom;
                }

                $items[$index]['parent_cpmk_code'] = $parent->code;
            }

            $oldBloom = $existing
                ? strtoupper(trim((string) ($existing->bloom_level ?? '')))
                : '';

            if ($action === 'keep' && $existing && $oldBloom !== $newBloom) {
                $items[$index]['action'] = 'adapt';
            }

            $items[$index]['description'] = $description;
            $items[$index]['bloom_level'] = $newBloom;

            if ($adjustments !== []) {
                $items[$index]['rationale'] = 'Guard Bloom SiMatRPS: '.implode('; ', $adjustments).'.';
            } elseif (! filled($items[$index]['rationale'] ?? null)) {
                $items[$index]['rationale'] = 'Level Bloom ditetapkan berdasarkan kata kerja operasional, tuntutan kognitif, dan hierarki CPMK induk.';
            }
        }

        $payload['summary'] = 'Telaah Sub-CPMK dengan pemeriksaan KKO Bloom per rumusan dan batas hierarki terhadap CPMK induk.';
        $payload['items'] = $items;

        return $payload;
    }

    private function inferBloomLevel(string $description): ?string
    {
        $text = mb_strtolower($description);

        // Urutkan dari tuntutan kognitif tertinggi. Jika satu rumusan memuat
        // beberapa KKO, level tertinggi yang benar-benar tertulis menjadi guard.
        $patterns = [
            'C6' => [
                'merancang', 'menciptakan', 'mengembangkan', 'membangun',
                'memformulasikan', 'merumuskan', 'mengonstruksi', 'menghasilkan',
            ],
            'C5' => [
                'mengevaluasi', 'menilai', 'memvalidasi', 'mengkritik',
                'mengkritisi', 'mempertimbangkan',
            ],
            'C4' => [
                'menganalisis', 'membandingkan', 'membedakan', 'menelaah',
                'menginvestigasi', 'mengategorikan',
            ],
            'C3' => [
                'menerapkan', 'menggunakan', 'menghitung', 'menyelesaikan',
                'mengimplementasikan', 'mendemonstrasikan', 'menentukan',
                'memecahkan',
            ],
            'C2' => [
                'memahami', 'menjelaskan', 'menginterpretasikan', 'menafsirkan',
                'mengklasifikasikan', 'merangkum', 'menggambarkan',
                'mengidentifikasi', 'menguraikan',
            ],
            'C1' => [
                'mengingat', 'menyebutkan', 'mendefinisikan', 'mengenali',
                'mendaftar',
            ],
        ];

        foreach ($patterns as $level => $verbs) {
            foreach ($verbs as $verb) {
                if (preg_match('/(?<![\\pL])'.preg_quote($verb, '/').'(?![\\pL])/u', $text) === 1) {
                    return $level;
                }
            }
        }

        return null;
    }

    private function bloomRank(string $level): int
    {
        return match (strtoupper(trim($level))) {
            'C1' => 1,
            'C2' => 2,
            'C3' => 3,
            'C4' => 4,
            'C5' => 5,
            'C6' => 6,
            default => 0,
        };
    }

    private function resolveWeekMaterial(
        string $value,
        array $materials,
        string $subDescription
    ): ?string {
        $titles = collect($materials)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->unique(fn ($item) => $this->comparableText($item))
            ->values();

        if ($titles->isEmpty()) {
            return filled($value) ? trim($value) : null;
        }

        $candidate = $this->comparableText($value);

        $explicit = $titles
            ->filter(function (string $title) use ($candidate): bool {
                $normalized = $this->comparableText($title);

                return $normalized !== ''
                    && (
                        $candidate === $normalized
                        || str_contains($candidate, $normalized)
                        || ($candidate !== '' && str_contains($normalized, $candidate))
                    );
            })
            ->take(2)
            ->values();

        if ($explicit->isNotEmpty()) {
            return $explicit->implode('; ');
        }

        $contextTokens = $this->semanticTokens(
            trim($value.' '.$subDescription)
        );

        $best = $titles
            ->map(function (string $title) use ($contextTokens): array {
                return [
                    'title' => $title,
                    'score' => count(array_intersect(
                        $contextTokens,
                        $this->semanticTokens($title)
                    )),
                ];
            })
            ->sortByDesc('score')
            ->first();

        return $best
            ? (string) $best['title']
            : (string) $titles->first();
    }

    private function resolveWeekReferenceCodes(
        string $value,
        array $bibliography,
        string $material,
        string $subDescription,
        int $week
    ): ?string {
        $entries = collect($bibliography)
            ->filter(fn ($item) =>
                is_array($item)
                && filled($item['code'] ?? null)
                && filled($item['text'] ?? null)
            )
            ->values();

        if ($entries->isEmpty()) {
            return null;
        }

        $allowed = $entries->pluck('code')->all();

        preg_match_all('/\[\s*(\d+)\s*\]/', $value, $matches);

        $aiCodes = collect($matches[1] ?? [])
            ->map(fn ($number) => '['.(int) $number.']')
            ->filter(fn ($code) => in_array($code, $allowed, true))
            ->unique()
            ->values();

        $contextTokens = $this->semanticTokens(
            trim($material.' '.$subDescription)
        );

        $relevant = $entries
            ->map(function (array $entry) use ($contextTokens): array {
                return [
                    'code' => (string) $entry['code'],
                    'score' => count(array_intersect(
                        $contextTokens,
                        $this->semanticTokens((string) $entry['text'])
                    )),
                ];
            })
            ->sortByDesc('score')
            ->filter(fn ($entry) => ($entry['score'] ?? 0) > 0)
            ->pluck('code')
            ->take(2);

        $codes = $aiCodes
            ->concat($relevant)
            ->unique()
            ->take(3)
            ->values();

        if ($codes->isEmpty()) {
            // Fallback terkontrol: gunakan pustaka RPS aktif dan rotasi
            // antar minggu agar tidak semua pekan selalu [1].
            $offset = max(0, ($week - 1) % $entries->count());
            $entry = $entries->get($offset) ?? $entries->first();
            $codes = collect([(string) $entry['code']]);
        }

        return $codes->implode(', ');
    }

    private function semanticTokens(string $value): array
    {
        $stopwords = [
            'yang','dan','atau','untuk','dengan','dalam','pada','dari','ke',
            'the','and','for','with','using','serta','melalui','mahasiswa',
            'mampu','dapat','konsep','materi','pembelajaran',
        ];

        return collect(
            preg_split('/\s+/u', $this->comparableText($value)) ?: []
        )
            ->filter(fn ($token) =>
                mb_strlen($token) >= 3
                && ! in_array($token, $stopwords, true)
            )
            ->unique()
            ->values()
            ->all();
    }

    private function targetSubCpmkForWeek(
        string $versionId,
        int $week
    ): ?object {
        $teachingWeeks = [1,2,3,4,5,6,7,9,10,11,12,13,14,15];
        $position = array_search($week, $teachingWeeks, true);

        if ($position === false) {
            return null;
        }


        $manualSubId = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->where('week_number', $week)
            ->where('source_type', 'manual_allocation')
            ->value('rps_sub_cpmk_id');

        if ($manualSubId) {
            $manualSub = DB::table('rps_sub_cpmks')
                ->where('rps_version_id', $versionId)
                ->where('id', $manualSubId)
                ->first(['id', 'code', 'sequence_no', 'description', 'bloom_level']);

            if ($manualSub) {
                return $manualSub;
            }
        }

        $subs = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $versionId)
            ->orderBy('sequence_no')
            ->orderBy('code')
            ->get(['id', 'code', 'sequence_no', 'description', 'bloom_level']);

        if ($subs->isEmpty()) {
            return null;
        }

        if ($subs->count() > count($teachingWeeks)) {
            throw ValidationException::withMessages([
                'ai' => 'Jumlah Sub-CPMK melebihi 14 pertemuan efektif. Rapikan Sub-CPMK terlebih dahulu sebelum menggunakan AI Pekan.',
            ]);
        }

        $materials = DB::table('rps_materials')
            ->where('rps_version_id', $versionId)
            ->orderBy('sequence_no')
            ->get(['id', 'rps_sub_cpmk_id', 'title']);

        $subIndexById = [];
        foreach ($subs as $index => $sub) {
            $subIndexById[(string) $sub->id] = (int) $index;
        }

        $pivotLinks = collect();
        $materialIds = $materials->pluck('id')->filter()->map(fn ($id) => (string) $id)->all();
        if ($materialIds !== [] && Schema::hasTable('rps_material_subcpmks')) {
            $pivotLinks = DB::table('rps_material_subcpmks')
                ->whereIn('rps_material_id', $materialIds)
                ->get(['rps_material_id', 'rps_sub_cpmk_id'])
                ->groupBy('rps_material_id');
        }

        $materialLoads = array_fill(0, $subs->count(), 0);
        $materialCount = max(1, $materials->count());

        foreach ($materials as $materialIndex => $material) {
            $explicit = [];
            if (filled($material->rps_sub_cpmk_id ?? null)) {
                $index = $subIndexById[(string) $material->rps_sub_cpmk_id] ?? null;
                if ($index !== null) $explicit[] = $index;
            }
            if ($pivotLinks->has((string) $material->id)) {
                foreach ($pivotLinks->get((string) $material->id) as $link) {
                    $index = $subIndexById[(string) $link->rps_sub_cpmk_id] ?? null;
                    if ($index !== null) $explicit[] = $index;
                }
            }
            $explicit = array_values(array_unique($explicit));
            if ($explicit !== []) {
                foreach ($explicit as $index) $materialLoads[$index]++;
                continue;
            }

            $titleTokens = $this->semanticTokens((string) ($material->title ?? ''));
            $scores = [];
            foreach ($subs as $subIndex => $sub) {
                $scores[$subIndex] = count(array_intersect(
                    $titleTokens,
                    $this->semanticTokens((string) $sub->description)
                ));
            }
            $bestScore = $scores === [] ? 0 : max($scores);
            $expected = $materials->count() <= 1
                ? 0.0
                : ((float) $materialIndex / (float) ($materialCount - 1)) * max(0, $subs->count() - 1);
            $candidates = array_keys(array_filter($scores, fn ($score) => $score === $bestScore));
            if ($candidates === []) $candidates = range(0, $subs->count() - 1);
            usort($candidates, fn ($a, $b) => abs($a - $expected) <=> abs($b - $expected) ?: $a <=> $b);
            $materialLoads[(int) $candidates[0]]++;
        }

        $counts = array_fill(0, $subs->count(), 1);
        $demand = [];
        foreach ($subs as $subIndex => $sub) {
            $load = $materialLoads[$subIndex];
            $materialFactor = $load === 0 ? 0.35 : min(3.25, $load * 0.65);
            $bloomWeight = match ($this->bloomRank((string) ($sub->bloom_level ?? ''))) {
                1 => 0.00,
                2 => 0.15,
                3 => 0.35,
                4 => 0.65,
                5 => 0.90,
                6 => 1.15,
                default => 0.25,
            };
            $demand[$subIndex] = 1.0 + $materialFactor + $bloomWeight;
        }

        $remaining = count($teachingWeeks) - $subs->count();
        while ($remaining > 0) {
            $bestIndex = 0;
            $bestPriority = -INF;
            foreach ($subs as $subIndex => $_sub) {
                $priority = $demand[$subIndex] / max(1, $counts[$subIndex]);
                if ($priority > $bestPriority) {
                    $bestPriority = $priority;
                    $bestIndex = (int) $subIndex;
                }
            }
            $counts[$bestIndex]++;
            $remaining--;
        }

        $sequence = [];
        foreach ($subs as $subIndex => $sub) {
            for ($i = 0; $i < $counts[$subIndex]; $i++) {
                $sequence[] = $sub;
            }
        }

        return $sequence[$position] ?? $subs->last();
    }

    private function defaultTimeEstimate(int $credits): string
    {
        $credits = max(1, $credits);

        return "Tatap muka: 1 × ({$credits} × 50 menit); "
            ."Tugas terstruktur: 1 × ({$credits} × 60 menit); "
            ."Belajar mandiri: 1 × ({$credits} × 60 menit)";
    }

    private function comparableText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function extendAiExecutionTime(): void
    {
        // Local PHP/Herd commonly defaults to 30 seconds. AI requests,
        // especially when a provider is rate-limited, can legitimately
        // take longer. This prevents the Guzzle CurlFactory fatal shown
        // by the development error page.
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        @ini_set('max_execution_time', '180');
    }

    private function applyCplMapping(
        array $payload,
        array $selectedIndices,
        object $rps,
        object $version
    ): array {
        $scopeCpls = DB::table('cpls')
            ->whereIn('id', array_values(array_unique([
                ...DB::table('course_cpls')
                    ->where('course_id', $rps->course_id)
                    ->pluck('cpl_id')
                    ->all(),
                ...DB::table('rps_additional_cpls')
                    ->where('rps_version_id', $version->id)
                    ->pluck('cpl_id')
                    ->all(),
            ])))
            ->get(['id', 'code'])
            ->keyBy('code');

        $mappings = $payload['mappings'] ?? [];
        $changed = 0;

        foreach ($selectedIndices as $index) {
            $item = $mappings[$index] ?? null;

            if (! is_array($item)) {
                throw ValidationException::withMessages([
                    'ai' => 'Pilihan pemetaan CPMK-CPL AI tidak valid.',
                ]);
            }

            $cpmkCode = $this->normalizeCpmkCode(
                (string) ($item['cpmk_code'] ?? '')
            );

            $cpmk = DB::table('rps_cpmks')
                ->where('rps_version_id', $version->id)
                ->where('code', $cpmkCode)
                ->first();

            if (! $cpmk) {
                throw ValidationException::withMessages([
                    'ai' => "CPMK {$cpmkCode} tidak ditemukan pada RPS.",
                ]);
            }

            $validCodes = collect($item['cpl_codes'] ?? [])
                ->map(fn ($code) => strtoupper(trim((string) $code)))
                ->filter(fn ($code) => $scopeCpls->has($code))
                ->unique()
                ->values()
                ->all();

            if ($validCodes === []) {
                throw ValidationException::withMessages([
                    'ai' => "Rekomendasi {$cpmkCode} tidak memiliki CPL yang valid dalam Scope CPL RPS.",
                ]);
            }

            $newIds = collect($validCodes)
                ->map(fn ($code) => $scopeCpls->get($code)?->id)
                ->filter()
                ->sort()
                ->values()
                ->all();

            $oldIds = DB::table('rps_cpmk_cpls')
                ->where('rps_cpmk_id', $cpmk->id)
                ->pluck('cpl_id')
                ->sort()
                ->values()
                ->all();

            if ($oldIds === $newIds) {
                continue;
            }

            $this->replaceCpmkMappings(
                $cpmk->id,
                $validCodes,
                $scopeCpls
            );

            $changed++;
        }

        return [
            'changed' => $changed,
            'message' => $changed > 0
                ? "{$changed} pemetaan CPMK-CPL berubah dan sudah disimpan."
                : 'Pemetaan yang dipilih sama dengan pemetaan saat ini; tidak ada data yang diubah.',
        ];
    }

    private function applyBloomMapping(array $payload, array $selectedIndices, object $version): array
    {
        $items = $payload['recommendations'] ?? [];
        $changed = 0;

        foreach ($selectedIndices as $index) {
            $item = $items[$index] ?? null;
            if (! is_array($item)) {
                throw ValidationException::withMessages(['ai' => 'Pilihan pemetaan Bloom AI tidak valid.']);
            }
            if (strtolower((string) ($item['action'] ?? 'keep')) === 'keep') {
                continue;
            }

            $target = $this->normalizeCpmkCode((string) ($item['target_code'] ?? ''));
            $bloom = strtoupper(trim((string) ($item['bloom_level'] ?? '')));
            if (! in_array($bloom, ['C1','C2','C3','C4','C5','C6'], true)) {
                throw ValidationException::withMessages(['ai' => 'Level Bloom '.$target.' tidak valid.']);
            }

            $cpmk = DB::table('rps_cpmks')
                ->where('rps_version_id', $version->id)
                ->where('code', $target)
                ->first();
            if (! $cpmk) {
                throw ValidationException::withMessages(['ai' => 'CPMK target '.$target.' tidak ditemukan.']);
            }

            if (strtoupper(trim((string) ($cpmk->bloom_level ?? ''))) === $bloom) {
                continue;
            }

            DB::table('rps_cpmks')->where('id', $cpmk->id)->update([
                'bloom_level' => $bloom,
                'updated_at' => now(),
            ]);
            $changed++;
        }

        return [
            'changed' => $changed,
            'message' => "{$changed} level Bloom CPMK berhasil dipetakan.",
        ];
    }

    private function applyCpmkReview(
        array $payload,
        array $selectedIndices,
        object $rps,
        object $version,
        int $userId
    ): array {
        $scopeCpls = DB::table('cpls')
            ->whereIn('id', array_values(array_unique([
                ...DB::table('course_cpls')->where('course_id', $rps->course_id)->pluck('cpl_id')->all(),
                ...DB::table('rps_additional_cpls')->where('rps_version_id', $version->id)->pluck('cpl_id')->all(),
            ])))
            ->get(['id', 'code'])
            ->keyBy('code');

        $recommendations = $payload['recommendations'] ?? [];
        $changed = 0;
        $adapted = 0;
        $added = 0;

        foreach ($selectedIndices as $index) {
            $item = $recommendations[$index] ?? null;
            if (! is_array($item)) {
                throw ValidationException::withMessages(['ai' => 'Pilihan CPMK AI tidak valid.']);
            }

            $action = strtolower((string) ($item['action'] ?? 'keep'));

            if ($action === 'keep') {
                continue;
            }

            if ($action === 'adapt') {
                $target = $this->normalizeCpmkCode((string) ($item['target_code'] ?? ''));
                $cpmk = DB::table('rps_cpmks')
                    ->where('rps_version_id', $version->id)
                    ->where('code', $target)
                    ->first();

                if (! $cpmk) {
                    throw ValidationException::withMessages([
                        'ai' => 'CPMK target '.$target.' tidak ditemukan. Buat rekomendasi AI baru agar sesuai data RPS terbaru.',
                    ]);
                }

                DB::table('rps_cpmks')->where('id', $cpmk->id)->update([
                    'description' => trim((string) ($item['description'] ?? $cpmk->description)),
                    'source_type' => 'ai_adapted',
                    'updated_at' => now(),
                ]);

                $changed++;
                $adapted++;
                continue;
            }

            if ($action === 'add') {
                $description = trim((string) ($item['description'] ?? ''));
                if ($description === '') {
                    throw ValidationException::withMessages(['ai' => 'Rumusan CPMK AI kosong dan tidak dapat ditambahkan.']);
                }

                $duplicate = DB::table('rps_cpmks')
                    ->where('rps_version_id', $version->id)
                    ->whereRaw('LOWER(description) = ?', [mb_strtolower($description)])
                    ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages(['ai' => 'CPMK yang dipilih sudah ada pada RPS.']);
                }

                $code = $this->nextCpmkCode($version->id);
                $sequence = ((int) DB::table('rps_cpmks')->where('rps_version_id', $version->id)->max('sequence_no')) + 1;
                $id = (string) Str::uuid();

                DB::table('rps_cpmks')->insert([
                    'id' => $id,
                    'rps_version_id' => $version->id,
                    'code' => $code,
                    'description' => $description,
                    'bloom_level' => null,
                    'source_type' => 'ai_added',
                    'source_cpmk_id' => null,
                    'sequence_no' => $sequence,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $changed++;
                $added++;
                continue;
            }

            throw ValidationException::withMessages(['ai' => 'Aksi CPMK AI tidak dikenali: '.$action]);
        }

        return [
            'changed' => $changed,
            'message' => "{$changed} rekomendasi CPMK diterapkan ({$adapted} adaptasi, {$added} tambahan).",
        ];
    }

    private function normalizeCpmkCode(string $code): string
    {
        $code = strtoupper(trim($code));

        // Toleran terhadap keluaran model seperti:
        // CPMK 1, CPMK-1, CPMK_01, "CPMK-01 (utama)".
        if (preg_match('/CPMK[^0-9]*0*(\d+)/', $code, $match)) {
            return 'CPMK-'.str_pad((string) ((int) $match[1]), 2, '0', STR_PAD_LEFT);
        }

        return $code;
    }

    private function resolveAiParentCpmk(
        string $versionId,
        string $candidate,
        ?string $targetSubCode = null,
        ?string $description = null
    ): ?object {
        $normalized = $this->normalizeCpmkCode($candidate);

        $cpmks = DB::table('rps_cpmks')
            ->where('rps_version_id', $versionId)
            ->orderBy('sequence_no')
            ->orderBy('code')
            ->get();

        $exact = $cpmks->first(
            fn ($row) => $this->normalizeCpmkCode((string) $row->code) === $normalized
        );

        if ($exact) {
            return $exact;
        }

        // Untuk ADAPT, CPMK induk lama adalah fallback paling aman.
        if (filled($targetSubCode)) {
            $sub = DB::table('rps_sub_cpmks')
                ->where('rps_version_id', $versionId)
                ->whereRaw('LOWER(code) = ?', [mb_strtolower(trim((string) $targetSubCode))])
                ->first();

            if ($sub) {
                $parentId = DB::table('rps_cpmk_subcpmks')
                    ->where('rps_sub_cpmk_id', $sub->id)
                    ->value('rps_cpmk_id');

                $parent = $cpmks->firstWhere('id', $parentId);
                if ($parent) {
                    return $parent;
                }
            }
        }

        // Jika provider menulis kode induk tidak valid pada item ADD,
        // pilih CPMK dengan kemiripan kata paling tinggi terhadap rumusan.
        // Ini hanya fallback setelah exact-code dan current-parent gagal.
        $needle = $this->comparableText((string) $description);
        $needleTokens = collect(preg_split('/\s+/', $needle) ?: [])
            ->filter(fn ($token) => mb_strlen($token) >= 4)
            ->unique()
            ->values();

        if ($needleTokens->isNotEmpty()) {
            $ranked = $cpmks->map(function ($row) use ($needleTokens) {
                $text = $this->comparableText((string) $row->description);
                $score = $needleTokens
                    ->filter(fn ($token) => str_contains($text, $token))
                    ->count();

                return ['row' => $row, 'score' => $score];
            })->sortByDesc('score')->values();

            if (($ranked->first()['score'] ?? 0) > 0) {
                return $ranked->first()['row'];
            }
        }

        return $cpmks->count() === 1 ? $cpmks->first() : null;
    }

    private function replaceCpmkMappings(string $cpmkId, array $codes, $scopeCpls): void
    {
        $ids = collect($codes)
            ->unique()
            ->map(fn ($code) => $scopeCpls->get($code)?->id)
            ->filter()
            ->values();

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('rps_cpmk_cpls')->where('rps_cpmk_id', $cpmkId)->delete();

        foreach ($ids as $cplId) {
            DB::table('rps_cpmk_cpls')->insert([
                'id' => (string) Str::uuid(),
                'rps_cpmk_id' => $cpmkId,
                'cpl_id' => $cplId,
                'source_type' => 'ai_accepted',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function applyMaterialPlan(
        array $payload,
        array $selectedIndices,
        object $version
    ): array {
        $items = $payload['items'] ?? [];
        $selected = [];

        foreach ($selectedIndices as $index) {
            $item = $items[$index] ?? null;

            if (! is_array($item)) {
                throw ValidationException::withMessages([
                    'ai' => 'Pilihan Bahan Kajian AI tidak valid.',
                ]);
            }

            $title = trim((string) ($item['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            // Satu rekomendasi = satu judul Bahan Kajian final.
            $selected[$this->comparableText($title)] = $item;
        }

        if ($selected === []) {
            throw ValidationException::withMessages([
                'ai' => 'Tidak ada judul Bahan Kajian valid yang dipilih.',
            ]);
        }

        $applied = 0;

        foreach ($selected as $item) {
            $action = strtolower((string) ($item['action'] ?? 'add'));
            $title = trim((string) ($item['title'] ?? ''));
            $targetTitle = trim((string) ($item['target_title'] ?? ''));

            if ($action === 'adapt' && $targetTitle !== '') {
                $target = DB::table('rps_materials')
                    ->where('rps_version_id', $version->id)
                    ->whereRaw('LOWER(title) = ?', [mb_strtolower($targetTitle)])
                    ->first();

                if ($target) {
                    DB::table('rps_materials')
                        ->where('id', $target->id)
                        ->update([
                            'title' => $title,
                            'rps_sub_cpmk_id' => null,
                            'source_type' => 'ai_adapted',
                            'updated_at' => now(),
                        ]);

                    if (Schema::hasTable('rps_material_subcpmks')) {
                        DB::table('rps_material_subcpmks')
                            ->where('rps_material_id', $target->id)
                            ->delete();
                    }

                    $applied++;
                    continue;
                }
            }

            // Untuk rekomendasi lama beraksi LINK:
            // bukan lagi membuat relasi, tetapi memastikan judulnya nyata
            // pada daftar Bahan Kajian.
            $existing = DB::table('rps_materials')
                ->where('rps_version_id', $version->id)
                ->whereRaw('LOWER(title) = ?', [mb_strtolower($title)])
                ->first();

            if ($existing) {
                DB::table('rps_materials')
                    ->where('id', $existing->id)
                    ->update([
                        'rps_sub_cpmk_id' => null,
                        'updated_at' => now(),
                    ]);

                if (Schema::hasTable('rps_material_subcpmks')) {
                    DB::table('rps_material_subcpmks')
                        ->where('rps_material_id', $existing->id)
                        ->delete();
                }

                $applied++;
                continue;
            }

            $next = ((int) DB::table('rps_materials')
                ->where('rps_version_id', $version->id)
                ->max('sequence_no')) + 1;

            DB::table('rps_materials')->insert([
                'id' => (string) Str::uuid(),
                'rps_version_id' => $version->id,
                'rps_sub_cpmk_id' => null,
                'title' => $title,
                'description' => null,
                'sequence_no' => $next,
                'source_type' => 'ai_added',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $applied++;
        }

        return [
            'changed' => $applied,
            'message' => "{$applied} Bahan Kajian terpilih diterapkan dan tersedia pada daftar materi.",
        ];
    }

    private function applySubCpmk(
        array $payload,
        array $selectedIndices,
        object $version,
        int $userId
    ): array {
        $items = $payload['items'] ?? [];
        $changed = 0;
        $adapted = 0;
        $added = 0;

        foreach ($selectedIndices as $index) {
            $item = $items[$index] ?? null;
            if (! is_array($item)) {
                throw ValidationException::withMessages(['ai' => 'Pilihan Sub-CPMK AI tidak valid.']);
            }

            $action = strtolower((string) ($item['action'] ?? 'add'));
            if ($action === 'keep') {
                continue;
            }

            $target = trim((string) ($item['target_code'] ?? ''));
            $parent = $this->resolveAiParentCpmk(
                $version->id,
                (string) ($item['parent_cpmk_code'] ?? ''),
                $action === 'adapt' ? $target : null,
                (string) ($item['description'] ?? '')
            );

            if (! $parent) {
                throw ValidationException::withMessages([
                    'ai' => 'CPMK induk rekomendasi AI tidak dapat dipastikan. Buat rekomendasi baru atau pilih CPMK induk secara manual.',
                ]);
            }

            if ($action === 'adapt') {
                $sub = DB::table('rps_sub_cpmks')
                    ->where('rps_version_id', $version->id)
                    ->whereRaw('LOWER(code) = ?', [mb_strtolower($target)])
                    ->first();

                if (! $sub) {
                    throw ValidationException::withMessages([
                        'ai' => 'Sub-CPMK target '.$target.' tidak ditemukan. Buat rekomendasi AI baru.',
                    ]);
                }

                DB::table('rps_sub_cpmks')->where('id', $sub->id)->update([
                    'description' => trim((string) ($item['description'] ?? $sub->description)),
                    'bloom_level' => $item['bloom_level'],
                    'source_type' => 'ai_adapted',
                    'updated_at' => now(),
                ]);

                DB::table('rps_cpmk_subcpmks')->where('rps_sub_cpmk_id', $sub->id)->delete();
                DB::table('rps_cpmk_subcpmks')->insert([
                    'id' => (string) Str::uuid(),
                    'rps_cpmk_id' => $parent->id,
                    'rps_sub_cpmk_id' => $sub->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $changed++;
                $adapted++;
                continue;
            }

            if ($action === 'add') {
                $description = trim((string) ($item['description'] ?? ''));
                if ($description === '') {
                    throw ValidationException::withMessages(['ai' => 'Rumusan Sub-CPMK AI kosong.']);
                }

                $duplicate = DB::table('rps_sub_cpmks')
                    ->where('rps_version_id', $version->id)
                    ->whereRaw('LOWER(description) = ?', [mb_strtolower($description)])
                    ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages(['ai' => 'Sub-CPMK yang dipilih sudah ada pada RPS.']);
                }

                $id = (string) Str::uuid();
                $sequence = ((int) DB::table('rps_sub_cpmks')->where('rps_version_id', $version->id)->max('sequence_no')) + 1;

                DB::table('rps_sub_cpmks')->insert([
                    'id' => $id,
                    'rps_version_id' => $version->id,
                    'code' => $this->nextSubCpmkCode($version->id),
                    'description' => $description,
                    'bloom_level' => $item['bloom_level'],
                    'sequence_no' => $sequence,
                    'source_type' => 'ai_accepted',
                    'created_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('rps_cpmk_subcpmks')->insert([
                    'id' => (string) Str::uuid(),
                    'rps_cpmk_id' => $parent->id,
                    'rps_sub_cpmk_id' => $id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $changed++;
                $added++;
                continue;
            }

            throw ValidationException::withMessages(['ai' => 'Aksi Sub-CPMK AI tidak dikenali: '.$action]);
        }

        return [
            'changed' => $changed,
            'message' => "{$changed} rekomendasi Sub-CPMK diterapkan ({$adapted} adaptasi, {$added} tambahan).",
        ];
    }

    private function applyWeeklyPlan(array $payload, object $version): array
    {
        $expectedWeeks = [1,2,3,4,5,6,7,9,10,11,12,13,14,15];
        $actualWeeks = collect($payload['weeks'] ?? [])
            ->pluck('week_number')
            ->map(fn ($week) => (int) $week)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($actualWeeks !== $expectedWeeks) {
            throw ValidationException::withMessages([
                'ai' => 'Rencana mingguan AI tidak lengkap. Harus tepat 14 minggu pembelajaran (1-7 dan 9-15). Buat rekomendasi baru.',
            ]);
        }

        foreach (($payload['weeks'] ?? []) as $item) {
            $weekNumber = (int) ($item['week_number'] ?? 0);

            if (! in_array($weekNumber, [1,2,3,4,5,6,7,9,10,11,12,13,14,15], true)) {
                continue;
            }

            $week = DB::table('rps_weekly_plans')
                ->where('rps_version_id', $version->id)
                ->where('week_number', $weekNumber)
                ->first();

            if (! $week) {
                continue;
            }

            $subId = DB::table('rps_sub_cpmks')
                ->where('rps_version_id', $version->id)
                ->where('code', $item['sub_cpmk_code'])
                ->value('id');

            $updates = ['updated_at' => now()];

            $candidate = [
                'rps_sub_cpmk_id' => $subId,
                'material_text' => $item['material'] ?? null,
                'learning_form' => $item['learning_form'] ?? null,
                'learning_method' => $item['learning_method'] ?? null,
                'time_estimate' => $item['time_estimate'] ?? null,
                'student_assignment' => $item['student_assignment'] ?? null,
                'online_activity' => $item['online_activity'] ?? null,
                'learning_activity' => $item['learning_activity'] ?? ($item['student_assignment'] ?? null),
                'assessment_indicator' => $item['assessment_indicator'] ?? null,
                'assessment_criteria' => $item['assessment_criteria'] ?? null,
                'assessment_method' => $item['assessment_method'] ?? null,
                'reference_text' => $item['references'] ?? null,
                'source_type' => 'ai_accepted',
            ];

            foreach ($candidate as $key => $value) {
                if ($key === 'source_type') {
                    continue;
                }

                $currentValue = $week->{$key} ?? null;
                $formatField = in_array($key, [
                    'learning_form',
                    'learning_method',
                    'time_estimate',
                    'student_assignment',
                    'online_activity',
                ], true);

                $canRefreshGenerated = $formatField
                    && ($week->source_type ?? null) !== 'manual';

                if (filled($value) && (! filled($currentValue) || $canRefreshGenerated)) {
                    $updates[$key] = $value;
                }
            }

            if (count($updates) > 1) {
                $updates['source_type'] = 'ai_accepted';
                DB::table('rps_weekly_plans')->where('id', $week->id)->update($updates);
            }
        }

        app(RpsAssessmentSyncService::class)->syncVersion($version->id);

        return [
            'changed' => 14,
            'message' => 'Rencana 14 minggu AI diterapkan ke workspace dan rantai asesmen disinkronkan.',
        ];
    }

    private function applyAssessmentPlanSelective(
        array $payload,
        array $selectedAssessmentIndices,
        array $selectedTaskIndices,
        object $version,
        int $userId
    ): array {
        $recommendations = $payload['assessments'] ?? [];
        $tasks = $payload['tasks'] ?? [];
        $changedAssessments = 0;
        $changedTasks = 0;
        $affectedWeeks = [];

        foreach ($selectedAssessmentIndices as $index) {
            $item = $recommendations[$index] ?? null;

            if (! is_array($item)) {
                throw ValidationException::withMessages([
                    'ai' => 'Pilihan asesmen AI tidak valid.',
                ]);
            }

            $type = (string) ($item['type'] ?? 'other');
            $week = (int) ($item['week_number'] ?? 1);

            if ($type === 'uts') {
                $week = 8;
            } elseif ($type === 'uas') {
                $week = 16;
            }

            $query = DB::table('assessments')
                ->where('rps_version_id', $version->id);

            if (in_array($type, ['uts', 'uas'], true)) {
                $existing = $query->where('type', $type)->first();
            } else {
                $name = mb_strtolower(trim((string) ($item['name'] ?? '')));

                $existing = DB::table('assessments')
                    ->where('rps_version_id', $version->id)
                    ->whereRaw('LOWER(name) = ?', [$name])
                    ->first();

                if (! $existing) {
                    $existing = DB::table('assessments')
                        ->where('rps_version_id', $version->id)
                        ->where('type', $type)
                        ->where('week_number', $week)
                        ->first();
                }
            }

            $assessmentId = $existing?->id ?: (string) Str::uuid();

            $values = [
                'name' => trim((string) ($item['name'] ?? 'Asesmen AI')),
                'type' => $type,
                'week_number' => $week,
                'description' => (string) ($item['description'] ?? ''),
                'weight' => (float) ($item['weight'] ?? 0),
                'source_type' => 'ai_accepted',
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('assessments')
                    ->where('id', $assessmentId)
                    ->update($values);
            } else {
                DB::table('assessments')->insert($values + [
                    'id' => $assessmentId,
                    'rps_version_id' => $version->id,
                    'code' => $this->nextAssessmentCode($version->id),
                    'created_by' => $userId,
                    'created_at' => now(),
                ]);
            }

            DB::table('assessment_subcpmks')
                ->where('assessment_id', $assessmentId)
                ->delete();

            foreach (array_unique($item['sub_cpmk_codes'] ?? []) as $code) {
                $subId = DB::table('rps_sub_cpmks')
                    ->where('rps_version_id', $version->id)
                    ->where('code', $code)
                    ->value('id');

                if ($subId) {
                    DB::table('assessment_subcpmks')->insert([
                        'id' => (string) Str::uuid(),
                        'assessment_id' => $assessmentId,
                        'rps_sub_cpmk_id' => $subId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $changedAssessments++;
            if (in_array($type, ['uts', 'uas'], true)) {
                $affectedWeeks[] = $week;
            }
        }

        foreach ($selectedTaskIndices as $index) {
            $task = $tasks[$index] ?? null;

            if (! is_array($task)) {
                throw ValidationException::withMessages([
                    'ai' => 'Pilihan RTM AI tidak valid.',
                ]);
            }

            $title = trim((string) ($task['title'] ?? 'RTM AI'));

            $existing = DB::table('rps_tasks')
                ->where('rps_version_id', $version->id)
                ->whereRaw('LOWER(title) = ?', [mb_strtolower($title)])
                ->first();

            $assessmentId = null;
            $assessmentName = trim((string) ($task['assessment_name'] ?? ''));

            if ($assessmentName !== '') {
                $assessmentId = DB::table('assessments')
                    ->where('rps_version_id', $version->id)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($assessmentName)])
                    ->value('id');
            }

            $taskId = $existing?->id ?: (string) Str::uuid();

            $values = [
                'assessment_id' => $assessmentId,
                'title' => $title,
                'type' => (string) ($task['type'] ?? 'assignment'),
                'purpose' => (string) ($task['purpose'] ?? ''),
                'instructions' => (string) ($task['instructions'] ?? ''),
                'expected_output' => (string) ($task['expected_output'] ?? ''),
                'due_week' => (int) ($task['due_week'] ?? 1),
                'source_type' => 'ai_accepted',
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('rps_tasks')
                    ->where('id', $taskId)
                    ->update($values);
            } else {
                DB::table('rps_tasks')->insert($values + [
                    'id' => $taskId,
                    'rps_version_id' => $version->id,
                    'code' => $this->nextTaskCode($version->id),
                    'created_by' => $userId,
                    'created_at' => now(),
                ]);
            }

            DB::table('rps_task_subcpmks')
                ->where('rps_task_id', $taskId)
                ->delete();

            foreach (array_unique($task['sub_cpmk_codes'] ?? []) as $code) {
                $subId = DB::table('rps_sub_cpmks')
                    ->where('rps_version_id', $version->id)
                    ->where('code', $code)
                    ->value('id');

                if ($subId) {
                    DB::table('rps_task_subcpmks')->insert([
                        'id' => (string) Str::uuid(),
                        'rps_task_id' => $taskId,
                        'rps_sub_cpmk_id' => $subId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $changedTasks++;
        }

        // RTM yang terhubung ke asesmen harus mengikuti cakupan asesmennya;
        // jangan menambahkan Sub-CPMK lain hanya demi mengejar cakupan global.
        $autoCoveredTaskSubs = 0;

        foreach (array_unique($affectedWeeks) as $affectedWeek) {
            // Asesmen non-UTS/UAS adalah rekap/agregat. Jangan pernah menulis
            // langsung ke satu pekan karena bobotnya harus didistribusikan
            // melalui Sub-CPMK oleh Isi Bagian Kosong.
            if (! in_array((int) $affectedWeek, [8, 16], true)) {
                continue;
            }

            $weekWeight = round(
                (float) DB::table('assessments')
                    ->where('rps_version_id', $version->id)
                    ->where('week_number', $affectedWeek)
                    ->whereIn('type', ['uts', 'uas'])
                    ->sum('weight'),
                2
            );

            DB::table('rps_weekly_plans')
                ->where('rps_version_id', $version->id)
                ->where('week_number', $affectedWeek)
                ->update([
                    'assessment_weight' => $weekWeight,
                    'updated_at' => now(),
                ]);
        }

        app(RpsAssessmentSyncService::class)->syncVersion($version->id);

        $totalWeight = round(
            (float) DB::table('assessments')
                ->where('rps_version_id', $version->id)
                ->sum('weight'),
            2
        );

        $message = "{$changedAssessments} asesmen dan {$changedTasks} RTM terpilih diterapkan.";

        if ($autoCoveredTaskSubs > 0) {
            $message .= " {$autoCoveredTaskSubs} Sub-CPMK yang belum tercakup otomatis dialokasikan ke RTM agar seluruh Sub-CPMK terakomodir.";
        }

        if ($changedAssessments > 0 && $totalWeight > 100.0) {
            $message .= " PERINGATAN: total bobot asesmen agregat saat ini {$totalWeight}% (>100%). Validator OBE akan menandainya sampai total tepat 100%.";
        } elseif ($changedAssessments > 0 && abs($totalWeight - 100.0) >= 0.01) {
            $message .= " Total bobot asesmen agregat saat ini {$totalWeight}%; sesuaikan hingga tepat 100%.";
        } elseif ($changedAssessments > 0) {
            $message .= ' Total bobot asesmen agregat 100%. Distribusi bobot pekan, RTM, matriks, dan simulasi langsung disinkronkan.';
        }

        return [
            'changed' => $changedAssessments + $changedTasks,
            'message' => $message,
        ];
    }

    private function ensureAllSubCpmksCoveredByTasks(
        string $versionId
    ): int {
        $subs = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $versionId)
            ->orderBy('sequence_no')
            ->orderBy('code')
            ->get(['id', 'code', 'sequence_no'])
            ->values();

        $tasks = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->orderBy('due_week')
            ->orderBy('code')
            ->get(['id', 'code', 'due_week'])
            ->values();

        if ($subs->isEmpty() || $tasks->isEmpty()) {
            return 0;
        }

        $covered = DB::table('rps_task_subcpmks')
            ->whereIn('rps_sub_cpmk_id', $subs->pluck('id'))
            ->pluck('rps_sub_cpmk_id')
            ->unique()
            ->values();

        $uncovered = $subs
            ->reject(fn ($sub) => $covered->contains($sub->id))
            ->values();

        $added = 0;

        foreach ($uncovered as $index => $sub) {
            $taskIndex = min(
                $tasks->count() - 1,
                (int) floor(
                    ($index * $tasks->count())
                    / max(1, $uncovered->count())
                )
            );

            $task = $tasks->get($taskIndex) ?? $tasks->last();

            $exists = DB::table('rps_task_subcpmks')
                ->where('rps_task_id', $task->id)
                ->where('rps_sub_cpmk_id', $sub->id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('rps_task_subcpmks')->insert([
                'id' => (string) Str::uuid(),
                'rps_task_id' => $task->id,
                'rps_sub_cpmk_id' => $sub->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $added++;
        }

        return $added;
    }

    private function nextAssessmentCode(string $versionId): string
    {
        $numbers = DB::table('assessments')
            ->where('rps_version_id', $versionId)
            ->pluck('code')
            ->map(function ($code): int {
                preg_match('/(\d+)$/', (string) $code, $match);
                return (int) ($match[1] ?? 0);
            });

        return 'ASM-'.str_pad(
            (string) (($numbers->max() ?? 0) + 1),
            2,
            '0',
            STR_PAD_LEFT
        );
    }

    private function nextTaskCode(string $versionId): string
    {
        $numbers = DB::table('rps_tasks')
            ->where('rps_version_id', $versionId)
            ->pluck('code')
            ->map(function ($code): int {
                preg_match('/(\d+)$/', (string) $code, $match);
                return (int) ($match[1] ?? 0);
            });

        return 'RTM-'.str_pad(
            (string) (($numbers->max() ?? 0) + 1),
            2,
            '0',
            STR_PAD_LEFT
        );
    }

    private function nextCpmkCode(string $versionId): string
    {
        $used = DB::table('rps_cpmks')
            ->where('rps_version_id', $versionId)
            ->pluck('code')
            ->all();

        for ($i = 1; $i <= 99; $i++) {
            $code = 'CPMK-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            if (! in_array($code, $used, true)) {
                return $code;
            }
        }

        return 'CPMK-'.str_pad((string) (count($used) + 1), 2, '0', STR_PAD_LEFT);
    }

    private function nextSubCpmkCode(string $versionId): string
    {
        $used = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $versionId)
            ->pluck('code')
            ->all();

        for ($i = 1; $i <= 99; $i++) {
            $code = 'Sub-CPMK-'.$i;
            if (! in_array($code, $used, true)) {
                return $code;
            }
        }

        return 'Sub-CPMK-'.(count($used) + 1);
    }

    private function suggestion(string $versionId, string $id): object
    {
        $row = DB::table('ai_suggestions')
            ->where('id', $id)
            ->where('rps_version_id', $versionId)
            ->first();

        abort_unless($row, 404);

        return $row;
    }

    private function context(Request $request, string $rps): array
    {
        $record = DB::table('rps')->where('id', $rps)->first();
        abort_unless($record, 404);

        abort_unless(
            $record->owner_id === $request->user()->id
                || $request->user()->role === 'admin',
            403
        );

        $version = DB::table('rps_versions')
            ->where('id', $record->current_version_id)
            ->first();

        abort_unless($version, 404);

        return [$record, $version];
    }
}
