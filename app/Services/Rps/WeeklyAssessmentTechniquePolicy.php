<?php

namespace App\Services\Rps;

final class WeeklyAssessmentTechniquePolicy
{
    /**
     * Append a strict semantic policy so providers distinguish an assessment
     * technique from an instrument/rubric used to score the evidence.
     */
    public function appendInstruction(?string $instruction): string
    {
        $policy = <<<'PROMPT'
KEBIJAKAN WAJIB UNTUK `assessment_method` / TEKNIK PENILAIAN:
- `assessment_method` adalah TEKNIK memperoleh bukti/data ketercapaian mahasiswa, BUKAN instrumen atau pedoman penskoran.
- JANGAN isi field ini dengan "Rubrik analitik", "Rubrik holistik", "rubrik", "checklist/daftar cek", "rating scale/skala penilaian", "lembar observasi", "pedoman penskoran", atau "soal tes" sebagai nama teknik. Itu adalah instrumen yang dapat digunakan di Detail Asesmen/Rubrik, bukan Teknik pada tabel RPS.
- Jika instruksi sebelumnya memberi "Rubrik analitik" sebagai contoh teknik, ABAIKAN contoh tersebut; aturan ini menggantikannya.
- Pilih teknik berdasarkan JENIS BUKTI yang benar-benar diminta oleh `assessment_indicator`, `assessment_criteria`, tugas/aktivitas pekan, lingkungan kerja mahasiswa, dan Detail Asesmen yang tersedia.
- PRIORITAS PENALARAN: (1) apa yang benar-benar dilakukan mahasiswa; (2) media/lingkungan kerja yang digunakan; (3) bukti yang dihasilkan; baru (4) kata kerja operasional pada indikator. Jangan menentukan teknik hanya dari KKO seperti menganalisis, menilai, atau membuktikan.
- Jika mahasiswa harus MENJALANKAN, MENGIMPLEMENTASIKAN, MENGUJI, MENGUKUR, MENDEBUG, MENSIMULASIKAN, atau MENGOLAH sesuatu menggunakan komputer/perangkat lunak, proses tersebut merupakan bukti kinerja. Contoh konteks: SQL/database/query, Python, R, MATLAB, ArcGIS/QGIS/GIS, spreadsheet, IDE/notebook, aplikasi komputasi, simulasi, atau perangkat lunak lain. Dalam kondisi ini utamakan "Penilaian kinerja", walaupun indikator juga memakai kata "membuktikan", "menganalisis", atau "menilai".
- "Membuktikan/pembuktian" HANYA mengarah ke "Tes tertulis" bila bukti yang diminta memang berupa soal, lembar jawaban, esai, atau pembuktian tertulis. Pembuktian melalui eksekusi program, eksperimen, benchmark, pengukuran waktu respons, simulasi, atau penggunaan perangkat lunak adalah "Penilaian kinerja".
- Jika fokus utama adalah HASIL/ARTEFAK akhir (program, model, laporan, peta, dokumen, produk) dan proses penggunaannya tidak menjadi bukti utama, gunakan "Penilaian produk". Jika proses praktik sekaligus hasil diamati tetapi hanya satu teknik dapat dipilih, pilih teknik yang paling langsung merepresentasikan indikator utama.
- Gunakan pemetaan berikut sebagai panduan keputusan:
  * soal/jawaban/esai/perhitungan/pembuktian yang secara eksplisit tertulis -> "Tes tertulis";
  * jawaban/verifikasi verbal atau wawancara -> "Tes lisan";
  * demonstrasi, praktik/praktikum, prosedur, coding, penggunaan software, eksekusi/benchmark/simulasi yang proses kerjanya menjadi bukti -> "Penilaian kinerja";
  * artefak/produk akhir seperti program, model, laporan, peta, dokumen, atau produk lain -> "Penilaian produk";
  * pekerjaan integratif bertahap/milestone -> "Penilaian proyek";
  * pemaparan hasil secara lisan/visual -> "Penilaian presentasi";
  * kumpulan karya/perkembangan hasil kerja -> "Penilaian portofolio";
  * partisipasi, proses, sikap, atau kolaborasi -> "Observasi";
  * evaluasi oleh sesama mahasiswa -> "Penilaian teman sejawat";
  * refleksi/evaluasi oleh mahasiswa atas dirinya -> "Penilaian diri".
- Pilih SATU teknik utama yang paling merepresentasikan bukti. Maksimal dua teknik hanya bila indikator memang menuntut dua bukti yang berbeda secara nyata.
- Jangan memvariasikan teknik hanya agar setiap pekan terlihat berbeda. Teknik boleh sama antarpekan jika bukti yang diukur memang sama, tetapi jangan memakai satu teknik generik untuk semua pekan tanpa membaca indikatornya.
PROMPT;

        $instruction = trim((string) $instruction);

        return $instruction !== ''
            ? $instruction."\n\n".$policy
            : $policy;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function normalizeResult(array $result, array $context = []): array
    {
        $weeks = $result['payload']['weeks'] ?? null;

        if (! is_array($weeks)) {
            return $result;
        }

        foreach ($weeks as $index => $week) {
            if (! is_array($week)) {
                continue;
            }

            $weeks[$index]['assessment_method'] = $this->resolveTechnique($week, $context);
        }

        $result['payload']['weeks'] = $weeks;

        return $result;
    }

    /**
     * @param  array<string, mixed>  $week
     * @param  array<string, mixed>  $context
     */
    public function resolveTechnique(array $week, array $context = []): string
    {
        $raw = trim((string) ($week['assessment_method'] ?? ''));
        $evidence = mb_strtolower($this->evidenceText($week, $context));
        $practicalTechnique = $this->inferPracticalComputerTechnique($evidence);
        $canonical = $this->canonicalTechnique($raw);

        // Provider dapat memilih "Tes tertulis" hanya karena KKO seperti
        // membuktikan/menganalisis. Bila bukti sebenarnya berupa eksekusi,
        // benchmark, coding, GIS, SQL, simulasi, atau praktik komputer, konteks
        // aktivitas nyata harus mengalahkan klasifikasi KKO tersebut.
        if (
            $canonical === 'Tes tertulis'
            && $practicalTechnique !== null
            && ! $this->hasExplicitWrittenEvidence($evidence)
        ) {
            return $practicalTechnique;
        }

        if ($canonical !== null) {
            return $canonical;
        }

        if ($practicalTechnique !== null) {
            return $practicalTechnique;
        }

        $inferred = $this->inferFromEvidence($evidence);

        if ($inferred !== null) {
            return $inferred;
        }

        // Provider output that is only an instrument must never leak into the
        // official RPS Technique field. In an ambiguous case, observation is a
        // safer evidence-collection technique than inventing a rubric name.
        if ($raw === '' || $this->looksLikeInstrument($raw)) {
            return 'Observasi';
        }

        return $raw;
    }

    private function canonicalTechnique(string $value): ?string
    {
        $value = mb_strtolower(trim($value));

        if ($value === '') {
            return null;
        }

        $patterns = [
            'Penilaian teman sejawat' => '/(?:penilaian\s+teman\s+sejawat|peer\s+(?:assessment|review))/u',
            'Penilaian diri' => '/(?:penilaian\s+diri|self\s+assessment)/u',
            'Penilaian portofolio' => '/(?:penilaian\s+)?portofolio/u',
            'Penilaian presentasi' => '/(?:penilaian\s+)?presentasi/u',
            'Penilaian proyek' => '/(?:penilaian\s+)?proyek|project\s+assessment/u',
            'Penilaian produk' => '/penilaian\s+produk/u',
            'Penilaian kinerja' => '/(?:penilaian\s+kinerja|observasi\s+kinerja|unjuk\s+kerja)/u',
            'Tes tertulis' => '/(?:tes|ujian)\s+tertulis/u',
            'Tes lisan' => '/(?:tes|ujian)\s+lisan/u',
            'Observasi' => '/\bobservasi\b/u',
        ];

        foreach ($patterns as $technique => $pattern) {
            if (preg_match($pattern, $value) === 1) {
                return $technique;
            }
        }

        return null;
    }

    private function inferFromEvidence(string $evidence): ?string
    {
        $rules = [
            'Penilaian teman sejawat' => '/teman\s+sejawat|peer\s+(?:review|assessment)/u',
            'Penilaian diri' => '/refleksi\s+diri|penilaian\s+diri|self\s+assessment/u',
            'Penilaian portofolio' => '/portofolio|kumpulan\s+karya|rekam\s+jejak\s+karya/u',
            'Penilaian presentasi' => '/presentasi|mempresentasikan|pemaparan|memaparkan/u',
            'Penilaian proyek' => '/\bproyek\b|\bproject\b|milestone|lintas\s+pekan|integratif\s+bertahap/u',
            'Tes lisan' => '/\blisan\b|wawancara|tanya\s+jawab\s+oral|verbal/u',
            'Penilaian kinerja' => '/praktik|praktikum|demonstrasi|mendemonstrasikan|unjuk\s+kerja|prosedur|coding|pemrograman|mengimplementasikan|implementasi|menjalankan|eksekusi|debug|simulasi|percobaan/u',
            'Penilaian produk' => '/\bproduk\b|artefak|program|aplikasi|model|laporan|peta|poster|prototipe|prototype|dokumen\s+hasil/u',
            'Tes tertulis' => '/tes\s+tertulis|ujian\s+tertulis|kuis\s+tertulis|soal\s+tertulis|jawaban\s+tertulis|lembar\s+jawaban|esai\s+tertulis|pembuktian\s+tertulis/u',
            'Observasi' => '/partisipasi|keaktifan|diskusi|kolaborasi|sikap|proses\s+kerja/u',
        ];

        foreach ($rules as $technique => $pattern) {
            if (preg_match($pattern, $evidence) === 1) {
                return $technique;
            }
        }

        return null;
    }

    private function inferPracticalComputerTechnique(string $evidence): ?string
    {
        // Konteks praktik eksplisit sudah cukup kuat walau perangkat tidak
        // disebutkan secara spesifik.
        if (
            preg_match(
                '/\b(?:praktik|praktikum|coding|pemrograman|debugging|demonstrasi|unjuk\s+kerja)\b/u',
                $evidence
            ) === 1
        ) {
            return 'Penilaian kinerja';
        }

        $hasExecutionAction = preg_match(
            '/\b(?:menjalankan|mengeksekusi|eksekusi|mengimplementasikan|implementasi|menggunakan|penggunaan|menguji|pengujian|mengukur|pengukuran|benchmark|benchmarking|men-debug|debug|debugging|mensimulasikan|simulasi|mengolah|pengolahan|memproses|pemrosesan|mengoperasikan|konfigurasi|mengonfigurasi)\b/u',
            $evidence
        ) === 1;

        $hasComputerEnvironment = preg_match(
            '/\b(?:komputer|komputasi|software|perangkat\s+lunak|sql|database|basis\s+data|query|kueri|python|matlab|arcgis|qgis|gis|spreadsheet|excel|notebook|jupyter|ide|terminal|server|source\s+code|kode\s+program|program|runtime|waktu\s+respon|waktu\s+respons)\b/u',
            $evidence
        ) === 1;

        return $hasExecutionAction && $hasComputerEnvironment
            ? 'Penilaian kinerja'
            : null;
    }

    private function hasExplicitWrittenEvidence(string $evidence): bool
    {
        return preg_match(
            '/tes\s+tertulis|ujian\s+tertulis|kuis\s+tertulis|soal\s+tertulis|jawaban\s+tertulis|lembar\s+jawaban|esai\s+tertulis|pembuktian\s+tertulis/u',
            $evidence
        ) === 1;
    }

    private function looksLikeInstrument(string $value): bool
    {
        return preg_match(
            '/rubrik|check\s*list|checklist|daftar\s+cek|rating\s+scale|skala\s+penilaian|lembar\s+observasi|pedoman\s+penskoran|soal\s+tes/u',
            mb_strtolower($value)
        ) === 1;
    }

    /**
     * @param  array<string, mixed>  $week
     * @param  array<string, mixed>  $context
     */
    private function evidenceText(array $week, array $context): string
    {
        $weekFields = [
            'assessment_indicator',
            'assessment_criteria',
            'student_assignment',
            'learning_activity',
            'learning_method',
            'material',
        ];

        $parts = [];

        foreach ($weekFields as $field) {
            if (filled($week[$field] ?? null)) {
                $parts[] = $this->stringify($week[$field]);
            }
        }

        foreach (['target_assessments', 'current_assessments', 'target_sub_cpmk'] as $field) {
            if (isset($context[$field])) {
                $parts[] = $this->stringify($context[$field]);
            }
        }

        return trim(implode(' ', array_filter($parts)));
    }

    private function stringify(mixed $value): string
    {
        if (is_string($value) || is_numeric($value) || is_bool($value)) {
            return trim((string) $value);
        }

        if (! is_array($value)) {
            return '';
        }

        $parts = [];

        array_walk_recursive($value, function ($item) use (&$parts): void {
            if (is_string($item) || is_numeric($item)) {
                $text = trim((string) $item);
                if ($text !== '') {
                    $parts[] = $text;
                }
            }
        });

        return implode(' ', $parts);
    }
}
