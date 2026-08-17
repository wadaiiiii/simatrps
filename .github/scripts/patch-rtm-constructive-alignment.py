from pathlib import Path

path = Path('app/Http/Controllers/RpsAiController.php')
text = path.read_text(encoding='utf-8')
original = text

old_prompt = '''5. `purpose` harus berupa uraian substantif 1-2 paragraf pendek: jelaskan konteks penugasan, kemampuan yang dilatih/diukur, dan pekerjaan intelektual atau keterampilan yang harus ditunjukkan mahasiswa. Jangan menggunakan kalimat generik seperti \"mengukur ketercapaian Sub-CPMK melalui tugas\" saja.\n6. `instructions` harus operasional dan siap dibaca mahasiswa.'''
new_prompt = '''5. `purpose` harus berupa uraian substantif 1-2 paragraf pendek: jelaskan konteks penugasan, kemampuan yang dilatih/diukur, dan pekerjaan intelektual atau keterampilan yang harus ditunjukkan mahasiswa. Jangan menggunakan kalimat generik seperti \"mengukur ketercapaian Sub-CPMK melalui tugas\" saja.\n\nCONSTRUCTIVE ALIGNMENT DESKRIPSI RTM:\n5a. Terapkan keselarasan konstruktif (constructive alignment): `purpose` WAJIB membawa kata/frasa kompetensi substantif dari SETIAP Sub-CPMK pada `tasks[*].sub_cpmk_codes`. Ambil terutama objek pengetahuan, algoritma, metode, teknik, perangkat konseptual, produk, atau keterampilan yang menjadi target kompetensi.\n5b. Jangan hanya menyalin KKO generik seperti memahami, menerapkan, mengimplementasikan, atau menganalisis. Sebutkan objek kompetensinya secara eksplisit. Contoh: bukan hanya \"menerapkan prosedur\", tetapi \"penerapan algoritma sorting (quicksort/mergesort)\"; bukan hanya \"menganalisis\", tetapi \"analisis kompleksitas waktu dan ruang pada stack, queue, linked list, dan hash table\" bila memang tertulis pada Sub-CPMK.\n5c. Pertahankan istilah teknis penting yang ada pada rumusan Sub-CPMK. Boleh menyintesis beberapa rumusan menjadi kalimat yang alami, tetapi jangan menghilangkan kata kunci utama hanya demi meringkas. Setiap Sub-CPMK yang dicakup RTM minimal harus dapat ditelusuri kembali melalui satu frasa substantif pada `purpose`.\n5d. Pola narasi yang diutamakan: tujuan umum penugasan → tuntutan aktivitas kognitif → cakupan kompetensi spesifik dari seluruh Sub-CPMK terkait. Hindari daftar mentah bila dapat dirangkai menjadi paragraf yang terbaca alami.\n6. `instructions` harus operasional dan siap dibaca mahasiswa.'''
if old_prompt not in text:
    raise SystemExit('prompt marker not found')
text = text.replace(old_prompt, new_prompt, 1)

text = text.replace("'rtm-integrative-v2'", "'rtm-integrative-v3-constructive-alignment'", 1)

old_chain = '''        } elseif ($data['suggestion_type'] === 'sub_cpmk') {\n            $result['payload'] = $this->sanitizeSubCpmkPayload(\n                $result['payload'] ?? [],\n                $version\n            );\n        }\n'''
new_chain = '''        } elseif ($data['suggestion_type'] === 'sub_cpmk') {\n            $result['payload'] = $this->sanitizeSubCpmkPayload(\n                $result['payload'] ?? [],\n                $version\n            );\n        } elseif ($data['suggestion_type'] === 'assessment_plan') {\n            $result['payload'] = $this->sanitizeAssessmentPlanPayload(\n                $result['payload'] ?? [],\n                $version\n            );\n        }\n'''
if old_chain not in text:
    raise SystemExit('sanitize chain marker not found')
text = text.replace(old_chain, new_chain, 1)

marker = '''    private function sanitizeCpmkReviewPayload(\n        array $payload,\n        object $version\n    ): array {\n'''
if marker not in text:
    raise SystemExit('sanitize method marker not found')

methods = r'''    private function sanitizeAssessmentPlanPayload(
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

        if ($adjusted > 0) {
            $summary = trim((string) ($payload['summary'] ?? ''));
            $note = $adjusted.' deskripsi RTM diperkuat dengan kata/frasa kompetensi Sub-CPMK untuk menjaga constructive alignment.';
            $payload['summary'] = $summary !== '' ? rtrim($summary, '.').' · '.$note : $note;
        }

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

'''
text = text.replace(marker, methods + marker, 1)

if text == original:
    raise SystemExit('no changes made')

path.write_text(text, encoding='utf-8')
