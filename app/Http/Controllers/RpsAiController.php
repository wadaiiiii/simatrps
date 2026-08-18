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

        if (in_array($data['suggestion_type'], ['cpmk_review', 'bloom_mapping', 'cpl_mapping'], true)) {
            $this->assertDocumentInfoReady($version->id);
        }

        if ($data['suggestion_type'] === 'weekly_plan') {
            $this->assertMeetingAllocationConfigured($version->id);
        }

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

        if ($data['suggestion_type'] === 'assessment_plan') {
            $rtmInstruction = <<<'PROMPT'
Untuk Telaah Asesmen + RTM, perlakukan ASESMEN sebagai rencana pengukuran agregat dan RTM sebagai lembar instruksi tugas konkret bagi mahasiswa.

MODE TELAAH / MERGE AMAN:
- `current_assessments` dan `current_tasks` adalah kondisi RPS dosen SAAT INI. Telaah dan manfaatkan data itu; jangan berasumsi RPS kosong.
- `assessment_budget.existing_weight_total` adalah total bobot ASESMEN YANG SUDAH TERSIMPAN. Semua asesmen existing dengan bobot > 0 adalah BASELINE TERKUNCI: pertahankan bobot dan identitasnya, jangan keluarkan ulang sebagai rekomendasi asesmen baru/perbaikan hanya untuk membentuk paket 100%.
- `assessment_budget.remaining_weight` adalah SATU-SATUNYA anggaran bobot untuk rekomendasi asesmen baru atau asesmen existing berbobot 0 yang memang perlu dilengkapi. Jumlah bobot seluruh rekomendasi asesmen yang benar-benar baru/dilengkapi WAJIB tepat sebesar `remaining_weight`, sehingga existing + rekomendasi = tepat 100%.
- Jika `remaining_weight` = 0, jangan rekomendasikan asesmen baru. Telaah hanya RTM/keterkaitan yang masih perlu diperbaiki tanpa mengubah bobot asesmen yang sudah tersimpan.
- Pertahankan asesmen/RTM lama yang sudah selaras. Jangan menduplikasi item yang secara akademik sudah mewakili fungsi yang sama.
- Jika item lama perlu diperbaiki, rekomendasikan bentuk target-state yang masih dapat dikenali dari tipe, cakupan Sub-CPMK, jadwal, dan konteks tugasnya. Sistem akan menandainya sebagai PERBAIKI dan hanya mengubahnya bila dosen memilih rekomendasi tersebut.
- Tambahkan item baru hanya untuk celah asesmen/RTM yang benar-benar belum tercakup.
- Jangan menghapus asesmen/RTM lama secara implisit. Penghapusan tetap keputusan eksplisit dosen di editor.
- Target-state asesmen setelah mempertahankan/perbaiki/menambah harus tepat 100%, bukan 100% baru yang ditumpuk di atas bobot lama.
- Pastikan seluruh Sub-CPMK aktif memiliki bukti asesmen dan RTM yang relevan; gunakan keterkaitan Sub-CPMK sebagai dasar utama constructive alignment.
- SETIAP Sub-CPMK aktif WAJIB tercakup minimal satu asesmen NON-UTS/UAS dengan bobot positif. UTS/UAS boleh mengukur Sub-CPMK yang sama sebagai asesmen sumatif, tetapi UTS/UAS tidak boleh menjadi satu-satunya asesmen untuk suatu Sub-CPMK.
- Detail Asesmen adalah sumber kebenaran bentuk dan bobot penilaian pekanan. Jangan membuat bentuk penilaian pekanan yang berdiri sendiri di luar asesmen agregat.

ATURAN CAKUPAN RTM:
1. Satu RTM BOLEH mengukur tepat satu Sub-CPMK ATAU beberapa Sub-CPMK sekaligus jika tugasnya integratif (proyek, praktikum, presentasi, tugas kasus, atau produk yang memang memerlukan beberapa capaian).
2. `tasks[*].sub_cpmk_codes` tidak boleh dipaksa sama dengan Sub-CPMK pada pekan pengumpulan. `due_week` hanya menunjukkan jadwal/pengumpulan; cakupan akademik RTM ditentukan oleh kemampuan yang benar-benar diukur tugas tersebut. Untuk RTM multi-Sub-CPMK, `due_week` WAJIB berada pada atau setelah PEKAN TERAKHIR di `weekly_plan` yang memuat salah satu Sub-CPMK RTM; jangan mengumpulkan tugas sebelum seluruh capaian yang diukur selesai dipelajari.
3. Seluruh `sub_cpmk_codes` sebuah RTM harus merupakan bagian dari `sub_cpmk_codes` asesmen induknya. RTM boleh mengukur sebagian atau seluruh cakupan asesmen induk.
4. Jangan membuat banyak RTM hanya untuk memaksa pola satu RTM = satu Sub-CPMK. Jika satu tugas secara alami mengintegrasikan 2-4 Sub-CPMK, gunakan satu RTM integratif.

KEDALAMAN ISI RTM:
5. `purpose` harus berupa uraian substantif 1-2 paragraf pendek: jelaskan konteks penugasan, kemampuan yang dilatih/diukur, dan pekerjaan intelektual atau keterampilan yang harus ditunjukkan mahasiswa. Jangan menggunakan kalimat generik seperti "mengukur ketercapaian Sub-CPMK melalui tugas" saja.

CONSTRUCTIVE ALIGNMENT DESKRIPSI RTM:
5a. Terapkan keselarasan konstruktif (constructive alignment): `purpose` WAJIB membawa kata/frasa kompetensi substantif dari SETIAP Sub-CPMK pada `tasks[*].sub_cpmk_codes`. Ambil terutama objek pengetahuan, algoritma, metode, teknik, perangkat konseptual, produk, atau keterampilan yang menjadi target kompetensi.
5b. Jangan hanya menyalin KKO generik seperti memahami, menerapkan, mengimplementasikan, atau menganalisis. Sebutkan objek kompetensinya secara eksplisit. Contoh: bukan hanya "menerapkan prosedur", tetapi "penerapan algoritma sorting (quicksort/mergesort)"; bukan hanya "menganalisis", tetapi "analisis kompleksitas waktu dan ruang pada stack, queue, linked list, dan hash table" bila memang tertulis pada Sub-CPMK.
5c. Pertahankan istilah teknis penting yang ada pada rumusan Sub-CPMK. Boleh menyintesis beberapa rumusan menjadi kalimat yang alami, tetapi jangan menghilangkan kata kunci utama hanya demi meringkas. Setiap Sub-CPMK yang dicakup RTM minimal harus dapat ditelusuri kembali melalui satu frasa substantif pada `purpose`.
5d. Pola narasi yang diutamakan: tujuan umum penugasan → tuntutan aktivitas kognitif → cakupan kompetensi spesifik dari seluruh Sub-CPMK terkait. Hindari daftar mentah bila dapat dirangkai menjadi paragraf yang terbaca alami.
6. `instructions` harus operasional dan siap dibaca mahasiswa. Susun sedikitnya 5 langkah bernomor yang logis: persiapan/identifikasi masalah, pengumpulan atau pemilihan data/informasi bila relevan, proses analisis/perhitungan/perancangan/implementasi, pemeriksaan atau interpretasi hasil, dokumentasi, dan pengumpulan/presentasi. Sesuaikan dengan jenis mata kuliah dan Bahan Kajian aktif; jangan mengarang perangkat, data, ukuran kelompok, atau aplikasi yang tidak didukung konteks.
7. Untuk proyek/tugas integratif yang berlangsung lintas pekan atau mengukur beberapa Sub-CPMK, masukkan bagian "Tahap/Milestone" di dalam `instructions`. Gunakan pekan yang masuk akal dari `weekly_plan` dan pastikan tahap akhir tidak melewati `due_week`. Jangan membuat jadwal di luar semester.
8. `expected_output` harus menjelaskan luaran konkret dalam 3-5 butir, misalnya laporan, perhitungan/analisis, diagram/model, source code/notebook, peta, produk, atau bahan presentasi sesuai karakter mata kuliah. Jangan mengarang jumlah halaman, format file khusus, atau standar teknis yang tidak ada di konteks/instruksi dosen.
9. `assessments[*].description` harus berisi indikator/kriteria penilaian yang spesifik terhadap tugas, bukan kalimat umum. Boleh merinci aspek ketepatan konsep/metode, kualitas proses, kualitas hasil/interpretasi, dan komunikasi/dokumentasi sepanjang sesuai konteks.
10. Gunakan bahasa Indonesia akademik yang jelas, instruktif, dan cukup rinci seperti lembar Rencana Tugas Mahasiswa resmi. Hindari pengulangan kalimat template antar-RTM.
PROMPT;

            $effectiveInstruction = filled($effectiveInstruction)
                ? trim((string) $effectiveInstruction)."\n\n".$rtmInstruction
                : $rtmInstruction;
        }

        $contextHash = hash(
            'sha256',
            json_encode(
                [
                    'type' => $data['suggestion_type'],
                    'instruction' => trim((string) ($data['instruction'] ?? '')),
                    'ai_policy_version' => $data['suggestion_type'] === 'assessment_plan' ? 'rtm-integrative-v6-remaining-budget' : 'bloom-guard-v2',
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
        } elseif ($data['suggestion_type'] === 'assessment_plan') {
            $result['payload'] = $this->sanitizeAssessmentPlanPayload(
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
        RpsAiContextService $contextService,
        RpsAssessmentSyncService $assessmentSync
    ): RedirectResponse {
        try {
            return $this->generateWeekInternal(
                $request,
                $rps,
                $week,
                $aiProvider,
                $contextService,
                $assessmentSync
            );
        } catch (ValidationException $error) {
            throw $error;
        } catch (\Throwable $error) {
            report($error);

            $diagnostic = strtoupper(substr(hash(
                'sha256',
                get_class($error).'|'.$error->getMessage().'|'.$error->getFile().'|'.$error->getLine()
            ), 0, 8));

            throw ValidationException::withMessages([
                'ai' => 'Susun AI Pekan '.$week.' belum berhasil diproses. '
                    .'Request dihentikan dengan aman agar tidak menjadi Server Error 500. '
                    .'Kode diagnostik: '.$diagnostic.'. Muat ulang lalu coba kembali.',
            ]);
        }
    }

    private function generateWeekInternal(
        Request $request,
        string $rps,
        int $week,
        AiRpsProviderService $aiProvider,
        RpsAiContextService $contextService,
        RpsAssessmentSyncService $assessmentSync
    ): RedirectResponse {
        // One week = one AI request. Keep the request below common nginx/Herd
        // gateway timeouts instead of processing 14 weeks sequentially.
        if (function_exists('set_time_limit')) {
            @set_time_limit(55);
        }
        @ini_set('max_execution_time', '55');

        [$record, $version] = $this->context($request, $rps);
        $this->assertMeetingAllocationConfigured($version->id);

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
                'ai' => 'Pekan UTS/UAS tidak disusun dengan AI pertemuan.',
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
                'ai' => 'Belum ada Sub-CPMK. Susun Sub-CPMK terlebih dahulu sebelum menggunakan AI per pekan.',
            ]);
        }

        $context = $contextService->buildWeekContext(
            $record,
            $version,
            $week,
            $targetSub->code
        );
        $assessmentSnapshot = $assessmentSync->snapshot($version->id);
        $assessmentOwnerName = trim((string) ($assessmentSnapshot['assessment_owner_name_by_week'][$week] ?? ''));

        $indicatorInstruction = <<<'PROMPT'
Untuk pekan ini, jangan menyalin, memendekkan, atau sekadar memparafrase rumusan `target_sub_cpmk` pada `assessment_indicator`.
Turunkan indikator penilaian BARU sebagai bukti ketercapaian yang dapat diamati dan dinilai. Gunakan konteks `parent_cpmk`, `target_materials`, `target_assessments`, `current_week`, dan level Bloom untuk membuat indikator lebih spesifik terhadap materi pekan tersebut.
Indikator ideal memuat 2-3 tindakan/bukti operasional, misalnya mengidentifikasi unsur pada contoh, menjelaskan hubungan/argumen, menerapkan prosedur pada kasus, membandingkan hasil, menganalisis kesalahan, atau menghasilkan produk yang relevan—sesuaikan dengan level Bloom dan bidang ilmu pada konteks.
JANGAN menyebut kode Sub-CPMK, frasa "sesuai rumusan", "menunjukkan ketercapaian", atau membuka kalimat dengan "Mahasiswa mampu/dapat". Mulai langsung dengan kata kerja operasional.
Boleh menggunakan pengetahuan keilmuan dan pedagogis umum untuk menurunkan contoh bukti belajar yang wajar, tetapi jangan mengubah atau mengarang CPL/CPMK/Sub-CPMK resmi, bobot, referensi, atau kebijakan kurikulum. Jangan membuat ambang angka/nilai baru jika tidak tersedia pada konteks.
Pastikan `assessment_criteria` menilai kualitas bukti tersebut. `assessment_method` TIDAK boleh menciptakan bentuk penilaian baru: bentuk resmi selalu berasal dari Detail Asesmen. Jika pekan belum mempunyai asesmen induk pada `target_assessments`, kosongkan `assessment_method`; sistem akan meminta dosen melengkapi Detail Asesmen.
Materi pekan WAJIB selaras dengan `target_sub_cpmk`. Prioritaskan `target_materials` bila tersedia. Jangan memilih bahan kajian hanya karena urutannya berdekatan, dan jangan mengulang bahan kajian yang tidak relevan dengan Sub-CPMK target. Jika perlu pengulangan untuk penguatan, nyatakan eksplisit sebagai pendalaman/latihan.

FORMAT SCANNABLE METODE DAN AKTIVITAS PEMBELAJARAN:
- `learning_method` hanya berisi nama metode/model pembelajaran yang ringkas, misalnya "Problem-Based Learning", "Case Method", "Project-Based Learning", "Small Group Discussion", atau kombinasi singkat yang benar-benar relevan. Jangan menulis uraian aktivitas di field ini.
- `learning_activity` WAJIB berupa 3-5 fase aktivitas kelas dalam daftar bernomor, SATU aktivitas per baris. Gunakan frasa ringkas sekitar 4-12 kata per poin, bukan paragraf atau kalimat naratif panjang.
- Gunakan pola: kata/frasa aktivitas + objek belajar yang konkret. Hindari pembuka berulang "Dosen..." atau "Mahasiswa..." dan hindari penjelasan prosedural panjang.
- Contoh format yang diutamakan:
  1. Penjelasan konsep quicksort dan mergesort.
  2. Diskusi kelompok komparasi algoritma.
  3. Latihan implementasi kode di IDE.
- Setiap fase harus selaras dengan `target_sub_cpmk`, materi pekan, level Bloom, dan `learning_method`. Jangan mengarang perangkat lunak tertentu bila tidak tersedia pada konteks.
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
                'ai' => 'AI pekan '.$week.' gagal sebelum batas waktu. '
                    .'Coba lagi atau aktifkan provider backup. Detail: '.$error->getMessage(),
            ]);
        }

        $item = collect($result['payload']['weeks'] ?? [])
            ->first(fn ($candidate) =>
                (int) ($candidate['week_number'] ?? 0) === $week
            );

        if (! is_array($item)) {
            throw ValidationException::withMessages([
                'ai' => 'AI tidak mengembalikan data yang valid untuk pekan '.$week.'.',
            ]);
        }

        // Provider tertentu kadang mengembalikan field teks sebagai array/list
        // walaupun schema meminta string. Normalisasi dahulu agar tidak terjadi
        // `Array to string conversion` yang sebelumnya dapat berujung HTTP 500.
        $item = $this->normalizeWeeklyAiItem($item);

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

        try {
            $scannableLearningActivity = $this->formatScannableLearningActivity(
                (string) ($item['learning_activity'] ?? '')
            );
        } catch (\Throwable $error) {
            // Formatter hanya untuk presentasi/scannability. Jangan biarkan output AI
            // yang aneh menjatuhkan keseluruhan request menjadi HTTP 500.
            report($error);
            $scannableLearningActivity = null;
        }

        // Output provider seperti 1E-16/0.0001 bukan aktivitas pembelajaran.
        // Jika aktivitas tidak bermakna, bentuk fallback scannable yang tetap
        // diturunkan dari metode, materi, indikator, dan Sub-CPMK aktif.
        if (! $this->isMeaningfulLearningActivity($scannableLearningActivity)) {
            $scannableLearningActivity = $this->fallbackScannableLearningActivity(
                (string) ($item['learning_method'] ?? ''),
                (string) ($resolvedMaterial ?? ''),
                (string) ($item['assessment_indicator'] ?? ''),
                (string) ($context['target_sub_cpmk']['description'] ?? '')
            );
        }

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
            'learning_activity' => $scannableLearningActivity,
            'independent_study_sessions' => max(1, (int) ($weekly->independent_study_sessions ?? 1)),
            'assessment_indicator' => $item['assessment_indicator'] ?? null,
            'assessment_criteria' => $item['assessment_criteria'] ?? null,
            'assessment_method' => $assessmentOwnerName !== '' ? $assessmentOwnerName : null,
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

        $currentAssessmentMethod = trim((string) ($weekly->assessment_method ?? ''));
        if ($currentAssessmentMethod !== $assessmentOwnerName) {
            $updates['assessment_method'] = $assessmentOwnerName !== '' ? $assessmentOwnerName : null;
        }

        if ($updates === []) {
            return back()->with(
                'success',
                'Pekan '.$week.' sudah lengkap. Tidak ada field kosong yang perlu diisi AI.'
            );
        }

        $updates['source_type'] = str_starts_with((string) ($weekly->source_type ?? ''), 'manual_allocation')
            ? 'manual_allocation_ai'
            : 'ai_accepted';
        $updates['updated_at'] = now();

        DB::table('rps_weekly_plans')
            ->where('id', $weekly->id)
            ->update($updates);

        $assessmentSync->syncVersion($version->id);

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

        $assessmentNote = $assessmentOwnerName !== ''
            ? ' Bentuk dan bobot penilaian mengikuti Detail Asesmen “'.$assessmentOwnerName.'”.'
            : ' Pekan ini belum memiliki asesmen induk; bentuk dan bobot penilaian tidak dibuat oleh AI pekanan. Lengkapi Detail Asesmen untuk menyinkronkannya.';

        return back()->with(
            'success',
            'AI berhasil '.($overwrite ? 'menyusun ulang' : 'melengkapi').' pekan '.$week.'.'.$assessmentNote
        );
    }


    private function normalizeWeeklyAiItem(array $item): array
    {
        $textFields = [
            'material',
            'learning_form',
            'learning_method',
            'student_assignment',
            'online_activity',
            'assessment_indicator',
            'assessment_criteria',
            'assessment_method',
        ];

        foreach ($textFields as $field) {
            $item[$field] = $this->normalizeWeeklyAiText($item[$field] ?? null, '; ');
        }

        // Aktivitas kelas lebih baik mempertahankan satu item per baris agar
        // formatter scannable dapat membuat 3-5 fase yang rapi.
        $item['learning_activity'] = $this->normalizeWeeklyAiText(
            $item['learning_activity'] ?? null,
            "\n"
        );

        // Referensi array seperti ["[1]", "[2]"] tetap menjadi kode yang
        // dapat dibaca resolver pustaka.
        $item['references'] = $this->normalizeWeeklyAiText(
            $item['references'] ?? null,
            ', '
        );

        return $item;
    }

    private function normalizeWeeklyAiText(mixed $value, string $separator = '; '): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value) || is_numeric($value) || is_bool($value)) {
            $text = trim((string) $value);

            // Angka murni/scientific notation yang muncul pada field naratif
            // adalah artefak provider, bukan isi akademik yang layak disimpan.
            if (preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[+-]?\d+)?$/i', $text) === 1) {
                return '';
            }

            return $text;
        }

        if (! is_array($value)) {
            return '';
        }

        $flatten = function (mixed $item) use (&$flatten): array {
            if ($item === null) {
                return [];
            }

            if (is_string($item) || is_numeric($item) || is_bool($item)) {
                $text = trim((string) $item);
                if ($text === '' || preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[+-]?\d+)?$/i', $text) === 1) {
                    return [];
                }
                return [$text];
            }

            if (! is_array($item)) {
                return [];
            }

            $result = [];
            foreach ($item as $key => $child) {
                // Untuk object-like array, pertahankan value substantif;
                // key teknis tidak perlu ikut masuk ke dokumen RPS.
                foreach ($flatten($child) as $part) {
                    $result[] = $part;
                }
            }

            return $result;
        };

        return collect($flatten($value))
            ->filter(fn ($part) => trim((string) $part) !== '')
            ->unique(fn ($part) => mb_strtolower(trim((string) $part)))
            ->implode($separator);
    }

    private function isMeaningfulLearningActivity(?string $value): bool
    {
        $value = trim((string) $value);
        if ($value === '') {
            return false;
        }

        $plain = preg_replace('/(?:^|\n)\s*\d{1,2}[.)]\s*/u', ' ', $value) ?? $value;
        $plain = trim(preg_replace('/\s+/u', ' ', $plain) ?? $plain);

        if ($plain === '' || preg_match('/^[+\-]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[+\-]?\d+)?$/i', $plain) === 1) {
            return false;
        }

        // Minimal mengandung beberapa huruf agar bukan simbol/kode numerik.
        preg_match_all('/\pL/u', $plain, $letters);
        return count($letters[0] ?? []) >= 8;
    }

    private function fallbackScannableLearningActivity(
        string $method,
        string $material,
        string $indicator,
        string $subCpmk
    ): string {
        $methodKey = mb_strtolower(trim($method));
        $topic = $this->shortLearningTopic($material !== '' ? $material : $subCpmk);
        $evidence = $this->shortLearningTopic($indicator);

        if (str_contains($methodKey, 'problem-based') || str_contains($methodKey, 'problem based')) {
            $items = [
                'Orientasi masalah terkait '.$topic,
                'Identifikasi konsep dan informasi yang diperlukan',
                'Diskusi kelompok analisis alternatif penyelesaian',
                'Penyelesaian kasus atau latihan terarah',
                'Presentasi dan refleksi hasil analisis',
            ];
        } elseif (str_contains($methodKey, 'case')) {
            $items = [
                'Pengenalan kasus terkait '.$topic,
                'Identifikasi fakta dan konsep utama',
                'Diskusi kelompok analisis kasus',
                'Perumusan alternatif solusi atau keputusan',
                'Presentasi dan refleksi hasil',
            ];
        } elseif (str_contains($methodKey, 'project')) {
            $items = [
                'Penetapan tujuan proyek terkait '.$topic,
                'Perencanaan langkah dan pembagian pekerjaan',
                'Pelaksanaan analisis atau pengembangan produk',
                'Pemeriksaan hasil terhadap kriteria tugas',
                'Presentasi dan refleksi hasil proyek',
            ];
        } elseif (str_contains($methodKey, 'small group') || str_contains($methodKey, 'discussion')) {
            $items = [
                'Penjelasan ringkas konsep '.$topic,
                'Identifikasi unsur atau operasi utama',
                'Diskusi kelompok komparasi dan analisis',
                'Latihan penerapan pada contoh terarah',
                'Presentasi dan refleksi hasil kelompok',
            ];
        } else {
            $items = [
                'Penjelasan konsep utama '.$topic,
                'Identifikasi unsur atau prosedur penting',
                'Diskusi dan latihan penerapan terarah',
                'Analisis hasil berdasarkan konsep yang dipelajari',
                'Refleksi dan simpulan pembelajaran',
            ];
        }

        if ($evidence !== '' && $evidence !== $topic) {
            $items[3] = 'Latihan bukti belajar: '.$evidence;
        }

        return collect($items)
            ->map(fn ($item, $index) => ($index + 1).'. '.rtrim(trim($item), '.').'.')
            ->implode("\n");
    }

    private function shortLearningTopic(string $value): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = preg_replace('/^(?:menerapkan|mengimplementasikan|menganalisis|menjelaskan|mengidentifikasi|membandingkan|merancang|menyusun|mengevaluasi)\s+/iu', '', $value) ?? $value;

        if ($value === '') {
            return 'materi pekan';
        }

        return rtrim(Str::words($value, 6, ''), ' .;,');
    }

    private function formatScannableLearningActivity(string $value): ?string
    {
        $value = trim($value);
        if (
            $value === ''
            || preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:e[+-]?\d+)?$/i', $value) === 1
        ) {
            return null;
        }

        $value = preg_replace(
            '/^\s*(?:fase[-\s]*fase\s+aktivitas\s+pembelajaran|aktivitas\s+kelas)\s*:?\s*/iu',
            '',
            $value
        ) ?? $value;
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = preg_replace('/\s+(?=\d{1,2}[.)]\s+)/u', "\n", $value) ?? $value;
        $value = preg_replace(
            '/\s+(?=(?:menganalisis|mendiskusikan|mengidentifikasi|membandingkan|menghitung|menyusun|mempresentasikan|menjelaskan|menerapkan|mengimplementasikan|merancang|mengevaluasi|mempraktikkan|menguji|merefleksikan|menginterpretasikan|mengamati|menelusuri|memecahkan|menentukan|mengembangkan)\b)/iu',
            "\n",
            $value
        ) ?? $value;

        $parts = preg_split(
            '/(?:^|\n)\s*(?:\d{1,2}[.)]|[-•])\s*/u',
            "\n".$value,
            -1,
            PREG_SPLIT_NO_EMPTY
        ) ?: [];

        if (count($parts) < 2) {
            $parts = preg_split('/(?<=[.!?])\s+(?=[\p{Lu}\d])/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [$value];
        }

        $items = collect($parts)
            ->map(function ($part) {
                $item = trim(strip_tags((string) $part));
                $item = preg_replace('/\s+/u', ' ', $item) ?? $item;
                $item = preg_replace('/^(?:dosen|mahasiswa)\s+/iu', '', $item) ?? $item;
                $item = trim($item, " \t\n\r\0\x0B-•;.");

                // Bila provider masih membuat kalimat sangat panjang, pertahankan
                // inti aktivitas agar tabel RPS tetap mudah dipindai.
                $clauses = preg_split('/\s*;\s*/u', $item, 2);
                $item = trim((string) ($clauses[0] ?? $item));
                if (str_word_count($item, 0, 'À-ÿ') > 10) {
                    $item = Str::words($item, 10, '');
                }

                $item = preg_replace(
                    '/\s+(?:dan|atau|dengan|untuk|pada|ke|dari|dalam|yang|serta|sebagai|melalui)$/iu',
                    '',
                    $item
                ) ?? $item;

                return trim($item);
            })
            ->filter()
            ->unique(fn ($item) => mb_strtolower($item))
            ->take(5)
            ->values();

        if ($items->isEmpty()) {
            return null;
        }

        return $items
            ->map(fn ($item, $index) => ($index + 1).'. '.rtrim($item, '.').'.')
            ->implode("\n");
    }

    public function apply(Request $request, string $rps, string $suggestion): RedirectResponse
    {
        [$record, $version] = $this->context($request, $rps);
        $row = $this->suggestion($version->id, $suggestion);

        if (in_array($row->suggestion_type, ['cpmk_review', 'bloom_mapping', 'cpl_mapping'], true)) {
            $this->assertDocumentInfoReady($version->id);
        }

        if ($row->suggestion_type === 'weekly_plan') {
            $this->assertMeetingAllocationConfigured($version->id);
        }

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

    private function sanitizeAssessmentPlanPayload(
        array $payload,
        object $version
    ): array {
        $subByCode = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $version->id)
            ->orderBy('sequence_no')
            ->get(['code', 'description'])
            ->keyBy(fn ($row) => $this->normalizeSubCpmkLookupCode((string) $row->code));

        $tasks = $payload['tasks'] ?? [];
        $adjusted = 0;

        foreach ($tasks as $index => $task) {
            if (! is_array($task)) {
                continue;
            }

            $codes = collect($task['sub_cpmk_codes'] ?? [])
                ->map(fn ($code) => $this->normalizeSubCpmkLookupCode((string) $code))
                ->filter()
                ->unique()
                ->values();

            if ($codes->isEmpty()) {
                continue;
            }

            $phrases = $codes
                ->map(fn ($code) => $subByCode->get($code))
                ->filter()
                ->map(fn ($sub) => $this->rtmCompetencyPhrase((string) $sub->description))
                ->filter()
                ->unique(fn ($phrase) => $this->comparableText((string) $phrase))
                ->values();

            if ($phrases->isEmpty()) {
                continue;
            }

            $purpose = trim((string) ($task['purpose'] ?? ''));
            if ($purpose === '') {
                $purpose = 'Penugasan ini digunakan untuk memperoleh bukti penguasaan capaian Sub-CPMK melalui pekerjaan yang menuntut mahasiswa menunjukkan proses dan hasil belajar secara runtut serta dapat ditelusuri.';
            }

            $missing = $phrases
                ->reject(fn ($phrase) => $this->rtmPurposeCoversPhrase($purpose, (string) $phrase))
                ->values();

            if ($missing->isEmpty()) {
                $tasks[$index]['purpose'] = $purpose;
                continue;
            }

            $alignmentClause = $this->joinAcademicPhrases($missing->all());
            $purpose = rtrim($purpose, " \t\n\r\0\x0B.")
                .'. Secara khusus, tugas ini menilai kemampuan mahasiswa terkait '
                .$alignmentClause.'.';

            $tasks[$index]['purpose'] = $purpose;
            $adjusted++;
        }

        $payload['tasks'] = $tasks;
        $payload = $this->constrainAssessmentPlanToRemainingBudget($payload, $version);
        $payload = $this->assertNonExamAssessmentCoverage($payload, $version);
        $payload = $this->annotateAssessmentMergeActions($payload, $version);

        if ($adjusted > 0) {
            $summary = trim((string) ($payload['summary'] ?? ''));
            $note = $adjusted.' deskripsi RTM diperkuat dengan kata/frasa kompetensi Sub-CPMK untuk menjaga constructive alignment.';
            $payload['summary'] = $summary !== '' ? rtrim($summary, '.').' · '.$note : $note;
        }

        return $payload;
    }



    private function constrainAssessmentPlanToRemainingBudget(array $payload, object $version): array
    {
        $existing = DB::table('assessments')
            ->where('rps_version_id', $version->id)
            ->orderByRaw('COALESCE(week_number, 99)')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'week_number', 'description', 'weight', 'source_type']);

        $existingTotal = round((float) $existing->sum(fn ($row) => (float) ($row->weight ?? 0)), 2);
        if ($existingTotal > 100.001) {
            throw ValidationException::withMessages([
                'ai' => 'Total bobot asesmen yang sudah tersimpan '.$existingTotal.'%. Rapikan bobot manual hingga maksimal 100% sebelum menjalankan Telaah Asesmen + RTM AI.',
            ]);
        }

        $remaining = round(max(0, 100 - $existingTotal), 2);
        $positive = $existing->filter(fn ($row) => (float) ($row->weight ?? 0) > 0)->values();
        $positiveIds = $positive->pluck('id')->map('strval')->all();
        $links = $positiveIds === []
            ? collect()
            : DB::table('assessment_subcpmks')
                ->join('rps_sub_cpmks', 'rps_sub_cpmks.id', '=', 'assessment_subcpmks.rps_sub_cpmk_id')
                ->whereIn('assessment_subcpmks.assessment_id', $positiveIds)
                ->get(['assessment_subcpmks.assessment_id', 'rps_sub_cpmks.code'])
                ->groupBy('assessment_id');

        $nameRemap = [];
        $candidates = [];

        foreach (($payload['assessments'] ?? []) as $item) {
            if (! is_array($item)) continue;

            $name = trim((string) ($item['name'] ?? ''));
            $type = strtolower(trim((string) ($item['type'] ?? 'other')));
            $week = $type === 'uts' ? 8 : ($type === 'uas' ? 16 : (int) ($item['week_number'] ?? 0));
            $targetCode = trim((string) ($item['target_code'] ?? ''));
            $wantedSubs = collect($item['sub_cpmk_codes'] ?? [])
                ->map(fn ($code) => $this->normalizeSubCpmkLookupCode((string) $code))
                ->filter()->unique()->sort()->values()->all();

            $match = null;
            if ($targetCode !== '') {
                $match = $positive->first(fn ($row) => strcasecmp((string) $row->code, $targetCode) === 0);
            }
            if (! $match && in_array($type, ['uts', 'uas'], true)) {
                $match = $positive->first(fn ($row) => strtolower((string) $row->type) === $type);
            }
            if (! $match && $name !== '') {
                $needle = $this->comparableText($name);
                $match = $positive->first(fn ($row) => $this->comparableText((string) $row->name) === $needle);
            }
            if (! $match && ! in_array($type, ['uts', 'uas'], true) && $wantedSubs !== []) {
                $match = $positive->first(function ($row) use ($links, $type, $week, $wantedSubs): bool {
                    if (strtolower((string) $row->type) !== $type) return false;
                    if ($week > 0 && (int) ($row->week_number ?? 0) !== $week) return false;
                    $currentSubs = collect($links->get($row->id, []))
                        ->pluck('code')
                        ->map(fn ($code) => $this->normalizeSubCpmkLookupCode((string) $code))
                        ->filter()->unique()->sort()->values()->all();
                    return $currentSubs === $wantedSubs;
                });
            }

            if ($match) {
                if ($name !== '') {
                    $nameRemap[$this->comparableText($name)] = trim((string) $match->name);
                }
                continue;
            }

            $candidates[] = $item;
        }

        $tasks = $payload['tasks'] ?? [];
        foreach ($tasks as $index => $task) {
            if (! is_array($task)) continue;
            $assessmentName = trim((string) ($task['assessment_name'] ?? ''));
            $key = $assessmentName !== '' ? $this->comparableText($assessmentName) : '';
            if ($key !== '' && isset($nameRemap[$key])) {
                $tasks[$index]['assessment_name'] = $nameRemap[$key];
            }
        }
        $payload['tasks'] = $tasks;

        if ($remaining <= 0.001) {
            $candidates = [];
        } elseif ($candidates === []) {
            throw ValidationException::withMessages([
                'ai' => 'AI belum menghasilkan asesmen tambahan untuk bobot sisa '.$remaining.'%. Jalankan Telaah Asesmen + RTM AI kembali; asesmen yang sudah berbobot tetap dipertahankan.',
            ]);
        } else {
            $requestedTotal = collect($candidates)->sum(fn ($item) => max(0, (float) ($item['weight'] ?? 0)));
            $allocated = 0.0;
            $lastIndex = count($candidates) - 1;

            foreach ($candidates as $index => $item) {
                if ($index === $lastIndex) {
                    $weight = round(max(0, $remaining - $allocated), 2);
                } elseif ($requestedTotal > 0.001) {
                    $weight = round($remaining * (max(0, (float) ($item['weight'] ?? 0)) / $requestedTotal), 2);
                    $allocated = round($allocated + $weight, 2);
                } else {
                    $weight = round($remaining / count($candidates), 2);
                    $allocated = round($allocated + $weight, 2);
                }
                $candidates[$index]['weight'] = $weight;
            }
        }

        $payload['assessments'] = array_values($candidates);
        $payload['_assessment_budget'] = [
            'existing_weight_total' => $existingTotal,
            'remaining_weight' => $remaining,
            'recommended_new_weight_total' => round((float) collect($candidates)->sum(fn ($item) => (float) ($item['weight'] ?? 0)), 2),
            'target_total' => 100.0,
        ];

        $summary = trim((string) ($payload['summary'] ?? ''));
        $budgetNote = $remaining > 0.001
            ? 'Bobot tersimpan '.$existingTotal.'% dipertahankan; rekomendasi asesmen baru disaring menjadi '.$remaining.'% agar total akhir tepat 100%.'
            : 'Bobot tersimpan sudah 100%; AI tidak menambahkan asesmen baru dan hanya menelaah RTM/keterkaitan.';
        $payload['summary'] = $summary !== '' ? rtrim($summary, '.').' · '.$budgetNote : $budgetNote;

        return $payload;
    }

    private function assertNonExamAssessmentCoverage(array $payload, object $version): array
    {
        $activeCodes = DB::table('rps_sub_cpmks')
            ->where('rps_version_id', $version->id)
            ->orderBy('sequence_no')
            ->pluck('code')
            ->map(fn ($code) => $this->normalizeSubCpmkLookupCode((string) $code))
            ->filter()->unique()->values();

        if ($activeCodes->isEmpty()) return $payload;

        $existingCoveredCodes = DB::table('assessment_subcpmks')
            ->join('assessments', 'assessments.id', '=', 'assessment_subcpmks.assessment_id')
            ->join('rps_sub_cpmks', 'rps_sub_cpmks.id', '=', 'assessment_subcpmks.rps_sub_cpmk_id')
            ->where('assessments.rps_version_id', $version->id)
            ->whereNotIn('assessments.type', ['uts', 'uas'])
            ->whereRaw('COALESCE(assessments.weight, 0) > 0')
            ->pluck('rps_sub_cpmks.code')
            ->map(fn ($code) => $this->normalizeSubCpmkLookupCode((string) $code))
            ->filter()->unique()->values();

        $coveredCodes = $existingCoveredCodes->merge(
            collect($payload['assessments'] ?? [])
                ->filter(fn ($item) => is_array($item))
                ->reject(fn ($item) => in_array(strtolower(trim((string) ($item['type'] ?? 'other'))), ['uts', 'uas'], true))
                ->filter(fn ($item) => (float) ($item['weight'] ?? 0) > 0)
                ->flatMap(fn ($item) => $item['sub_cpmk_codes'] ?? [])
                ->map(fn ($code) => $this->normalizeSubCpmkLookupCode((string) $code))
                ->filter()
        )->unique()->values();

        $missing = $activeCodes->diff($coveredCodes)->values();
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'ai' => 'Telaah Asesmen + RTM AI belum memenuhi constructive alignment. Sub-CPMK berikut belum memiliki asesmen non-UTS/UAS berbobot: '
                    .$missing->implode(', ').'. Jalankan Telaah Asesmen + RTM AI kembali; rekomendasi yang tidak menutup seluruh Sub-CPMK tidak akan diterapkan.',
            ]);
        }

        return $payload;
    }

    private function annotateAssessmentMergeActions(array $payload, object $version): array
    {
        $existingAssessments = DB::table('assessments')
            ->where('rps_version_id', $version->id)
            ->orderByRaw('COALESCE(week_number, 99)')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'week_number', 'description', 'weight', 'source_type']);

        $assessmentIds = $existingAssessments->pluck('id')->all();
        $assessmentLinks = $assessmentIds === []
            ? collect()
            : DB::table('assessment_subcpmks')
                ->join('rps_sub_cpmks', 'rps_sub_cpmks.id', '=', 'assessment_subcpmks.rps_sub_cpmk_id')
                ->whereIn('assessment_subcpmks.assessment_id', $assessmentIds)
                ->get(['assessment_subcpmks.assessment_id', 'rps_sub_cpmks.code'])
                ->groupBy('assessment_id');

        $claimedAssessmentIds = [];
        $assessmentItems = $payload['assessments'] ?? [];

        foreach ($assessmentItems as $index => $item) {
            if (! is_array($item)) continue;

            $type = strtolower(trim((string) ($item['type'] ?? 'other')));
            $week = $type === 'uts' ? 8 : ($type === 'uas' ? 16 : (int) ($item['week_number'] ?? 0));
            $name = trim((string) ($item['name'] ?? ''));
            $wantedSubs = collect($item['sub_cpmk_codes'] ?? [])
                ->map(fn ($code) => $this->normalizeSubCpmkLookupCode((string) $code))
                ->filter()->unique()->values();

            $available = $existingAssessments
                ->reject(fn ($row) => in_array((string) $row->id, $claimedAssessmentIds, true))
                ->values();

            $match = null;
            if (in_array($type, ['uts', 'uas'], true)) {
                $match = $available->first(fn ($row) => strtolower((string) $row->type) === $type);
            }

            if (! $match && $name !== '') {
                $needle = $this->comparableText($name);
                $match = $available->first(fn ($row) => $this->comparableText((string) $row->name) === $needle);
            }

            if (! $match && ! in_array($type, ['uts', 'uas'], true) && $wantedSubs->isNotEmpty()) {
                // Constructive alignment is the primary identity of a non-exam
                // assessment. A lecturer may rename or change the form while it
                // still measures the same Sub-CPMK. Rank all non-exam items by
                // Sub-CPMK coverage first; type/name/week are tie-breakers.
                $ranked = $available
                    ->reject(fn ($row) => in_array(strtolower((string) $row->type), ['uts', 'uas'], true))
                    ->map(function ($row) use ($assessmentLinks, $wantedSubs, $week, $name, $type): array {
                        $currentSubs = collect($assessmentLinks->get($row->id, []))
                            ->pluck('code')
                            ->map(fn ($code) => $this->normalizeSubCpmkLookupCode((string) $code))
                            ->filter()->unique()->values();
                        $overlap = $wantedSubs->intersect($currentSubs)->count();
                        $sameCoverage = $wantedSubs->sort()->values()->all()
                            === $currentSubs->sort()->values()->all();
                        $sameType = strtolower((string) $row->type) === $type;
                        $sameWeek = $week > 0 && (int) ($row->week_number ?? 0) === $week;
                        $nameOverlap = count(array_intersect(
                            $this->semanticTokens($name),
                            $this->semanticTokens((string) $row->name)
                        ));
                        $score = ($overlap * 10)
                            + ($sameCoverage ? 20 : 0)
                            + ($sameType ? 3 : 0)
                            + ($sameWeek ? 2 : 0)
                            + min(3, $nameOverlap);
                        return [
                            'row' => $row,
                            'score' => $score,
                            'overlap' => $overlap,
                            'same_coverage' => $sameCoverage,
                        ];
                    })
                    ->sortByDesc('score')
                    ->values();

                $best = $ranked->first();
                // Never adapt solely because type/week/name happen to match.
                // At least one shared Sub-CPMK is required for non-exam items.
                if ($best && ($best['overlap'] ?? 0) > 0) {
                    $match = $best['row'];
                }
            }

            if ($match) {
                $claimedAssessmentIds[] = (string) $match->id;
                $currentSubs = collect($assessmentLinks->get($match->id, []))
                    ->pluck('code')
                    ->map(fn ($code) => $this->normalizeSubCpmkLookupCode((string) $code))
                    ->filter()->unique()->sort()->values()->all();
                $newSubs = $wantedSubs->sort()->values()->all();
                $same = $this->comparableText((string) $match->name) === $this->comparableText($name)
                    && strtolower((string) $match->type) === $type
                    && (int) ($match->week_number ?? 0) === $week
                    && abs((float) $match->weight - (float) ($item['weight'] ?? 0)) < 0.01
                    && $currentSubs === $newSubs
                    && $this->comparableText((string) ($match->description ?? '')) === $this->comparableText((string) ($item['description'] ?? ''));

                $targetSourceType = strtolower(trim((string) ($match->source_type ?? 'manual')));
                $lecturerOwnedTarget = ! in_array($targetSourceType, ['ai_accepted', 'ai_adapted', 'ai_generated', 'automation', 'assessment_sync'], true);
                $assessmentItems[$index]['action'] = ($same || $lecturerOwnedTarget) ? 'keep' : 'adapt';
                $assessmentItems[$index]['target_code'] = (string) $match->code;
                $assessmentItems[$index]['target_source_type'] = (string) ($match->source_type ?? 'manual');
                $assessmentItems[$index]['rationale'] = $same
                    ? 'Asesmen yang sudah ada telah selaras dengan target-state AI; pertahankan tanpa perubahan.'
                    : 'Asesmen yang sudah ada dikenali sebagai target perbaikan terutama dari kesamaan cakupan Sub-CPMK; tipe, jadwal, dan nama menjadi penguat.';
            } else {
                $assessmentItems[$index]['action'] = 'add';
                $assessmentItems[$index]['target_code'] = null;
                $assessmentItems[$index]['target_source_type'] = null;
                $assessmentItems[$index]['rationale'] = 'Belum ada asesmen aktif yang cukup setara; rekomendasi ini merupakan tambahan.';
            }
        }

        $payload['assessments'] = $assessmentItems;

        $existingTasks = DB::table('rps_tasks')
            ->where('rps_version_id', $version->id)
            ->orderBy('due_week')
            ->orderBy('code')
            ->get(['id', 'code', 'title', 'type', 'assessment_id', 'due_week', 'purpose', 'instructions', 'expected_output', 'source_type']);
        $taskIds = $existingTasks->pluck('id')->all();
        $taskLinks = $taskIds === [] ? collect() : DB::table('rps_task_subcpmks')
            ->join('rps_sub_cpmks', 'rps_sub_cpmks.id', '=', 'rps_task_subcpmks.rps_sub_cpmk_id')
            ->whereIn('rps_task_subcpmks.rps_task_id', $taskIds)
            ->get(['rps_task_subcpmks.rps_task_id', 'rps_sub_cpmks.code'])
            ->groupBy('rps_task_id');
        $assessmentById = $existingAssessments->keyBy(fn ($row) => (string) $row->id);
        $claimedTaskIds = [];
        $taskItems = $payload['tasks'] ?? [];

        foreach ($taskItems as $index => $item) {
            if (! is_array($item)) continue;

            $title = trim((string) ($item['title'] ?? ''));
            $type = strtolower(trim((string) ($item['type'] ?? 'assignment')));
            $dueWeek = (int) ($item['due_week'] ?? 0);
            $assessmentName = trim((string) ($item['assessment_name'] ?? ''));
            $wantedSubs = collect($item['sub_cpmk_codes'] ?? [])
                ->map(fn ($code) => $this->normalizeSubCpmkLookupCode((string) $code))
                ->filter()->unique()->values();
            $available = $existingTasks
                ->reject(fn ($row) => in_array((string) $row->id, $claimedTaskIds, true))
                ->values();

            $match = null;
            if ($title !== '') {
                $needle = $this->comparableText($title);
                $match = $available->first(fn ($row) => $this->comparableText((string) $row->title) === $needle);
            }

            if (! $match) {
                $ranked = $available
                    ->filter(fn ($row) => strtolower((string) $row->type) === $type)
                    ->map(function ($row) use ($taskLinks, $assessmentById, $wantedSubs, $dueWeek, $assessmentName): array {
                        $currentSubs = collect($taskLinks->get($row->id, []))
                            ->pluck('code')
                            ->map(fn ($code) => $this->normalizeSubCpmkLookupCode((string) $code))
                            ->filter()->unique()->values();
                        $overlap = $wantedSubs->intersect($currentSubs)->count();
                        $sameWeek = $dueWeek > 0 && (int) ($row->due_week ?? 0) === $dueWeek;
                        $parent = filled($row->assessment_id ?? null)
                            ? $assessmentById->get((string) $row->assessment_id)
                            : null;
                        $sameAssessment = $parent && $assessmentName !== ''
                            && $this->comparableText((string) $parent->name) === $this->comparableText($assessmentName);
                        $score = ($overlap * 6) + ($sameWeek ? 2 : 0) + ($sameAssessment ? 4 : 0);
                        return ['row' => $row, 'score' => $score, 'overlap' => $overlap];
                    })
                    ->sortByDesc('score')->values();
                $best = $ranked->first();
                if ($best && (($best['overlap'] ?? 0) > 0 || ($best['score'] ?? 0) >= 6)) {
                    $match = $best['row'];
                }
            }

            if ($match) {
                $claimedTaskIds[] = (string) $match->id;
                $currentSubs = collect($taskLinks->get($match->id, []))
                    ->pluck('code')
                    ->map(fn ($code) => $this->normalizeSubCpmkLookupCode((string) $code))
                    ->filter()->unique()->sort()->values()->all();
                $newSubs = $wantedSubs->sort()->values()->all();
                $parent = filled($match->assessment_id ?? null)
                    ? $assessmentById->get((string) $match->assessment_id)
                    : null;
                $same = $this->comparableText((string) $match->title) === $this->comparableText($title)
                    && strtolower((string) $match->type) === $type
                    && (int) ($match->due_week ?? 0) === $dueWeek
                    && $currentSubs === $newSubs
                    && $this->comparableText((string) ($parent->name ?? '')) === $this->comparableText($assessmentName)
                    && $this->comparableText((string) ($match->purpose ?? '')) === $this->comparableText((string) ($item['purpose'] ?? ''));

                $targetSourceType = strtolower(trim((string) ($match->source_type ?? 'manual')));
                $lecturerOwnedTarget = ! in_array($targetSourceType, ['ai_accepted', 'ai_adapted', 'ai_generated', 'automation', 'assessment_sync'], true);
                $taskItems[$index]['action'] = ($same || $lecturerOwnedTarget) ? 'keep' : 'adapt';
                $taskItems[$index]['target_code'] = (string) $match->code;
                $taskItems[$index]['target_source_type'] = (string) ($match->source_type ?? 'manual');
                $taskItems[$index]['rationale'] = $same
                    ? 'RTM yang sudah ada telah selaras dengan target-state AI; pertahankan tanpa perubahan.'
                    : 'RTM yang sudah ada dikenali sebagai target perbaikan berdasarkan asesmen induk, jadwal, dan cakupan Sub-CPMK.';
            } else {
                $taskItems[$index]['action'] = 'add';
                $taskItems[$index]['target_code'] = null;
                $taskItems[$index]['target_source_type'] = null;
                $taskItems[$index]['rationale'] = 'Belum ada RTM aktif yang cukup setara; rekomendasi ini merupakan tambahan.';
            }
        }

        $payload['tasks'] = $taskItems;
        $payload['_merge_mode'] = 'safe_review';
        $summary = trim((string) ($payload['summary'] ?? ''));
        $note = 'Telaah bersifat non-destruktif: item lama dipertahankan, diperbaiki hanya bila dipilih, dan tidak dihapus otomatis.';
        $payload['summary'] = $summary !== '' ? rtrim($summary, '.').' · '.$note : $note;

        return $payload;
    }

    private function normalizeSubCpmkLookupCode(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[\s_‐‑‒–—]+/u', '-', $value) ?? $value;
        return trim($value, '-');
    }

    private function rtmCompetencyPhrase(string $description): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $description) ?? $description);
        $value = preg_replace('/^(mahasiswa\s+)?(mampu\s+|dapat\s+)?/iu', '', $value) ?? $value;

        $nominalizations = [
            'mengimplementasikan' => 'implementasi',
            'menerapkan' => 'penerapan',
            'menganalisis' => 'analisis',
            'mengevaluasi' => 'evaluasi',
            'merancang' => 'perancangan',
            'mengembangkan' => 'pengembangan',
            'menjelaskan' => 'penjelasan',
            'mengidentifikasi' => 'identifikasi',
            'menggunakan' => 'penggunaan',
            'menghitung' => 'perhitungan',
            'menyelesaikan' => 'penyelesaian',
            'membandingkan' => 'perbandingan',
            'memvalidasi' => 'validasi',
            'menguji' => 'pengujian',
            'membuat' => 'pembuatan',
            'menyusun' => 'penyusunan',
            'memodelkan' => 'pemodelan',
            'menentukan' => 'penentuan',
        ];

        foreach ($nominalizations as $verb => $noun) {
            $pattern = '/^'.preg_quote($verb, '/').'\s+/iu';
            if (preg_match($pattern, $value) === 1) {
                $value = preg_replace($pattern, $noun.' ', $value, 1) ?? $value;
                break;
            }
        }

        return rtrim(trim($value), '.;');
    }

    private function rtmPurposeCoversPhrase(string $purpose, string $phrase): bool
    {
        $purposeComparable = $this->comparableText($purpose);
        $phraseComparable = $this->comparableText($phrase);

        if ($phraseComparable !== '' && str_contains($purposeComparable, $phraseComparable)) {
            return true;
        }

        $stopwords = [
            'mahasiswa','kemampuan','melalui','dalam','dengan','untuk','yang','pada',
            'serta','secara','terkait','penerapan','implementasi','analisis','evaluasi',
            'penggunaan','penjelasan','penyelesaian','perancangan','pengembangan',
        ];

        $tokens = collect(preg_split('/\s+/u', $phraseComparable) ?: [])
            ->map(fn ($token) => trim((string) $token))
            ->filter(fn ($token) => mb_strlen($token) >= 3)
            ->reject(fn ($token) => in_array($token, $stopwords, true))
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            return false;
        }

        $matched = $tokens->filter(fn ($token) => str_contains($purposeComparable, $token))->count();
        $required = min(3, max(1, (int) ceil($tokens->count() * 0.5)));

        return $matched >= $required;
    }

    private function joinAcademicPhrases(array $phrases): string
    {
        $phrases = array_values(array_filter(array_map(
            fn ($phrase) => trim((string) $phrase),
            $phrases
        )));

        if (count($phrases) === 0) return '';
        if (count($phrases) === 1) return $phrases[0];
        if (count($phrases) === 2) return $phrases[0].', serta '.$phrases[1];

        $last = array_pop($phrases);
        return implode('; ', $phrases).'; serta '.$last;
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
            // antar pekan agar tidak semua pekan selalu [1].
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
                'ai' => 'Rencana pekanan AI tidak lengkap. Harus tepat 14 pekan pembelajaran (1-7 dan 9-15). Buat rekomendasi baru.',
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
            'message' => 'Rencana 14 pekan AI diterapkan ke workspace dan rantai asesmen disinkronkan.',
        ];
    }

    private function applyAssessmentPlanSelective(
        array $payload,
        array $selectedAssessmentIndices,
        array $selectedTaskIndices,
        object $version,
        int $userId
    ): array {
        // Re-evaluate coverage and merge actions at APPLY time against the latest RPS state.
        // A remaining-budget recommendation is valid only while the stored baseline
        // weight is unchanged; otherwise the lecturer must review the new remainder.
        $budgetAtReview = $payload['_assessment_budget'] ?? null;
        if (! is_array($budgetAtReview)) {
            throw ValidationException::withMessages([
                'ai' => 'Rekomendasi ini dibuat dengan kebijakan bobot lama. Jalankan Telaah Asesmen + RTM AI kembali agar rekomendasi menyesuaikan bobot sisa terbaru.',
            ]);
        }
        $currentStoredTotal = round((float) DB::table('assessments')
            ->where('rps_version_id', $version->id)
            ->sum('weight'), 2);
        $reviewStoredTotal = round((float) ($budgetAtReview['existing_weight_total'] ?? 0), 2);
        if (abs($currentStoredTotal - $reviewStoredTotal) > 0.01) {
            throw ValidationException::withMessages([
                'ai' => 'Bobot asesmen berubah sejak rekomendasi dibuat (saat telaah '.$reviewStoredTotal.'%, sekarang '.$currentStoredTotal.'%). Jalankan Telaah Asesmen + RTM AI kembali agar bobot rekomendasi dihitung dari sisa terbaru.',
            ]);
        }

        $payload = $this->assertNonExamAssessmentCoverage($payload, $version);
        $payload = $this->annotateAssessmentMergeActions($payload, $version);
        $recommendations = $payload['assessments'] ?? [];
        $tasks = $payload['tasks'] ?? [];
        $changedAssessments = 0;
        $changedTasks = 0;
        $affectedWeeks = [];

        // Hitung target bobot sebagai MERGE: ADAPT mengganti bobot lama,
        // ADD menambah, KEEP tidak mengubah. Ini mencegah kasus 10% lama +
        // rencana AI 100% dibaca keliru sebagai 110% padahal salah satu item
        // seharusnya merupakan perbaikan asesmen lama.
        $projectedTotal = (float) DB::table('assessments')
            ->where('rps_version_id', $version->id)
            ->sum('weight');

        foreach ($selectedAssessmentIndices as $index) {
            $item = $recommendations[$index] ?? null;
            if (! is_array($item)) continue;
            $action = strtolower((string) ($item['action'] ?? 'add'));
            if ($action === 'keep') continue;

            $newWeight = (float) ($item['weight'] ?? 0);
            if ($action === 'adapt') {
                $targetCode = trim((string) ($item['target_code'] ?? ''));
                $target = $targetCode !== ''
                    ? DB::table('assessments')
                        ->where('rps_version_id', $version->id)
                        ->where('code', $targetCode)
                        ->first(['id', 'weight'])
                    : null;
                if (! $target) {
                    throw ValidationException::withMessages([
                        'ai' => 'Target asesmen yang akan diperbaiki sudah berubah atau tidak ditemukan. Jalankan Telaah Asesmen + RTM AI kembali.',
                    ]);
                }
                $projectedTotal -= (float) $target->weight;
            }
            $projectedTotal += $newWeight;
        }

        $projectedTotal = round($projectedTotal, 2);
        if ($projectedTotal > 100.001) {
            throw ValidationException::withMessages([
                'ai' => "Pilihan rekomendasi akan membuat total bobot asesmen {$projectedTotal}%. Telaah ulang atau pilih hanya rekomendasi PERBAIKI/TAMBAH yang diperlukan. Asesmen lama tidak dihapus otomatis.",
            ]);
        }

        foreach ($selectedAssessmentIndices as $index) {
            $item = $recommendations[$index] ?? null;
            if (! is_array($item)) {
                throw ValidationException::withMessages(['ai' => 'Pilihan asesmen AI tidak valid.']);
            }

            $action = strtolower((string) ($item['action'] ?? 'add'));
            if ($action === 'keep') continue;
            if (! in_array($action, ['adapt', 'add'], true)) {
                throw ValidationException::withMessages(['ai' => 'Aksi asesmen AI tidak dikenali. Buat telaah baru.']);
            }

            $type = strtolower((string) ($item['type'] ?? 'other'));
            $week = (int) ($item['week_number'] ?? 1);
            if ($type === 'uts') $week = 8;
            if ($type === 'uas') $week = 16;

            $existing = null;
            if ($action === 'adapt') {
                $targetCode = trim((string) ($item['target_code'] ?? ''));
                $existing = $targetCode !== ''
                    ? DB::table('assessments')
                        ->where('rps_version_id', $version->id)
                        ->where('code', $targetCode)
                        ->first()
                    : null;
                if (! $existing) {
                    throw ValidationException::withMessages([
                        'ai' => 'Asesmen target perbaikan tidak ditemukan. Jalankan Telaah Asesmen + RTM AI kembali agar konteks diperbarui.',
                    ]);
                }
                $existingSourceType = strtolower(trim((string) ($existing->source_type ?? 'manual')));
                if (! in_array($existingSourceType, ['ai_accepted', 'ai_adapted', 'ai_generated', 'automation', 'assessment_sync'], true)) {
                    throw ValidationException::withMessages([
                        'ai' => 'Asesmen manual/dosen tidak boleh ditimpa oleh AI. Item tetap dipertahankan; ubah dari Edit Detail Asesmen bila diperlukan.',
                    ]);
                }
            } else {
                $name = trim((string) ($item['name'] ?? ''));
                $duplicate = $name !== '' && DB::table('assessments')
                    ->where('rps_version_id', $version->id)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'ai' => 'Asesmen yang akan ditambahkan ternyata sudah ada. Jalankan telaah ulang agar AI menandainya sebagai PERBAIKI/PERTAHANKAN.',
                    ]);
                }
            }

            $assessmentId = $existing?->id ?: (string) Str::uuid();
            $values = [
                'name' => trim((string) ($item['name'] ?? 'Asesmen AI')),
                'type' => $type,
                'week_number' => $week,
                'description' => (string) ($item['description'] ?? ''),
                'weight' => (float) ($item['weight'] ?? 0),
                'source_type' => $action === 'adapt' ? 'ai_adapted' : 'ai_accepted',
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('assessments')->where('id', $assessmentId)->update($values);
            } else {
                DB::table('assessments')->insert($values + [
                    'id' => $assessmentId,
                    'rps_version_id' => $version->id,
                    'code' => $this->nextAssessmentCode($version->id),
                    'created_by' => $userId,
                    'created_at' => now(),
                ]);
            }

            DB::table('assessment_subcpmks')->where('assessment_id', $assessmentId)->delete();
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
            if (in_array($type, ['uts', 'uas'], true)) $affectedWeeks[] = $week;
        }

        foreach ($selectedTaskIndices as $index) {
            $task = $tasks[$index] ?? null;
            if (! is_array($task)) {
                throw ValidationException::withMessages(['ai' => 'Pilihan RTM AI tidak valid.']);
            }

            $action = strtolower((string) ($task['action'] ?? 'add'));
            if ($action === 'keep') continue;
            if (! in_array($action, ['adapt', 'add'], true)) {
                throw ValidationException::withMessages(['ai' => 'Aksi RTM AI tidak dikenali. Buat telaah baru.']);
            }

            $title = trim((string) ($task['title'] ?? 'RTM AI'));
            $existing = null;
            if ($action === 'adapt') {
                $targetCode = trim((string) ($task['target_code'] ?? ''));
                $existing = $targetCode !== ''
                    ? DB::table('rps_tasks')
                        ->where('rps_version_id', $version->id)
                        ->where('code', $targetCode)
                        ->first()
                    : null;
                if (! $existing) {
                    throw ValidationException::withMessages([
                        'ai' => 'RTM target perbaikan tidak ditemukan. Jalankan Telaah Asesmen + RTM AI kembali.',
                    ]);
                }
                $existingSourceType = strtolower(trim((string) ($existing->source_type ?? 'manual')));
                if (! in_array($existingSourceType, ['ai_accepted', 'ai_adapted', 'ai_generated', 'automation', 'assessment_sync'], true)) {
                    throw ValidationException::withMessages([
                        'ai' => 'RTM manual/dosen tidak boleh ditimpa oleh AI. Item tetap dipertahankan; ubah dari editor RTM bila diperlukan.',
                    ]);
                }
            } else {
                $duplicate = DB::table('rps_tasks')
                    ->where('rps_version_id', $version->id)
                    ->whereRaw('LOWER(title) = ?', [mb_strtolower($title)])
                    ->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'ai' => 'RTM yang akan ditambahkan ternyata sudah ada. Jalankan telaah ulang agar AI menandainya sebagai PERBAIKI/PERTAHANKAN.',
                    ]);
                }
            }

            $assessmentId = null;
            $assessmentName = trim((string) ($task['assessment_name'] ?? ''));
            if ($assessmentName !== '') {
                $assessmentId = DB::table('assessments')
                    ->where('rps_version_id', $version->id)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($assessmentName)])
                    ->value('id');
            }
            if (! $assessmentId && $existing && filled($existing->assessment_id ?? null)) {
                $assessmentId = $existing->assessment_id;
            }
            if (! $assessmentId) {
                throw ValidationException::withMessages([
                    'ai' => 'Asesmen induk untuk RTM belum tersedia. Pilih juga rekomendasi asesmen terkait atau jalankan telaah ulang.',
                ]);
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
                'source_type' => $action === 'adapt' ? 'ai_adapted' : 'ai_accepted',
                'updated_at' => now(),
            ];

            if ($existing) {
                DB::table('rps_tasks')->where('id', $taskId)->update($values);
            } else {
                DB::table('rps_tasks')->insert($values + [
                    'id' => $taskId,
                    'rps_version_id' => $version->id,
                    'code' => $this->nextTaskCode($version->id),
                    'created_by' => $userId,
                    'created_at' => now(),
                ]);
            }

            DB::table('rps_task_subcpmks')->where('rps_task_id', $taskId)->delete();
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

        foreach (array_unique($affectedWeeks) as $affectedWeek) {
            if (! in_array((int) $affectedWeek, [8, 16], true)) continue;
            $weekWeight = round((float) DB::table('assessments')
                ->where('rps_version_id', $version->id)
                ->where('week_number', $affectedWeek)
                ->whereIn('type', ['uts', 'uas'])
                ->sum('weight'), 2);
            DB::table('rps_weekly_plans')
                ->where('rps_version_id', $version->id)
                ->where('week_number', $affectedWeek)
                ->update(['assessment_weight' => $weekWeight, 'updated_at' => now()]);
        }

        app(RpsAssessmentSyncService::class)->syncVersion($version->id);

        $totalWeight = round((float) DB::table('assessments')
            ->where('rps_version_id', $version->id)->sum('weight'), 2);
        $message = "{$changedAssessments} asesmen dan {$changedTasks} RTM terpilih diterapkan dengan mode merge aman.";
        if ($changedAssessments > 0 && abs($totalWeight - 100.0) >= 0.01) {
            $message .= " Total bobot asesmen saat ini {$totalWeight}%; sesuaikan hingga tepat 100%.";
        } elseif ($changedAssessments > 0) {
            $message .= ' Total bobot asesmen 100%. Distribusi bobot pekan, RTM, matriks, dan simulasi disinkronkan.';
        }

        return ['changed' => $changedAssessments + $changedTasks, 'message' => $message];
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

    private function assertDocumentInfoReady(string $versionId): void
    {
        $meta = DB::table('rps_document_meta')
            ->where('rps_version_id', $versionId)
            ->first();

        $required = [
            'course_cluster',
            'prepared_date',
            'published_date',
            'developer_name',
            'coordinator_name',
            'head_program_name',
            'lecturer_names',
            'software_media',
            'hardware_media',
            'prerequisite_text',
            'description_short',
        ];
        $missing = collect($required)
            ->filter(fn (string $field) => ! filled($meta->{$field} ?? null))
            ->values();

        if (! $meta || $missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'document_info' => 'Lengkapi dan simpan Edit Informasi RPS terlebih dahulu sebelum mengatur Scope CPL atau menyusun CPMK.',
            ]);
        }
    }

    private function assertMeetingAllocationConfigured(string $versionId): void
    {
        $teachingWeeks = [1,2,3,4,5,6,7,9,10,11,12,13,14,15];

        $configured = DB::table('rps_weekly_plans')
            ->where('rps_version_id', $versionId)
            ->whereIn('week_number', $teachingWeeks)
            ->whereNotNull('rps_sub_cpmk_id')
            ->where('source_type', 'like', 'manual_allocation%')
            ->count();

        if ($configured !== count($teachingWeeks)) {
            throw ValidationException::withMessages([
                'ai' => 'Atur jumlah pertemuan setiap Sub-CPMK terlebih dahulu. Setelah total 14/14 disimpan, Susun AI per pekan akan aktif.',
            ]);
        }
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
