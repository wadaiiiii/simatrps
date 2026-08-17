from pathlib import Path
import re

SERVICE = Path('app/Services/Rps/RpsSmartDraftService.php')
CSS = Path('resources/css/app.css')


def replace_once(text: str, old: str, new: str, label: str) -> str:
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{label}: expected exactly 1 match, found {count}')
    return text.replace(old, new, 1)


service = SERVICE.read_text(encoding='utf-8')

# 1) Do not repeat the full material title/list inside the learning activity.
service = replace_once(
    service,
    """            $activity = 'Mahasiswa mempelajari '\n                .($materialText ?: 'bahan kajian yang ditetapkan')\n                .', mendiskusikan contoh, dan menyelesaikan latihan yang mendukung '\n                .$sub->code.'.';\n""",
    """            $activity = $this->learningActivityForWeek(\n                $sub,\n                $hasPracticum\n            );\n""",
    'compact weekly learning activity',
)

# 2) For manually allocated weeks, explicit Material <-> Sub-CPMK links are exclusive.
#    If there is no explicit link, use only the strongest small candidate set.
pattern = re.compile(
    r"        \$titles = \$materials\n"
    r"            ->values\(\)\n"
    r"            ->map\(function \(\$material, \$index\) use \(\$sub, \$linkedMaterialIds\): array \{.*?"
    r"            ->unique\(\)\n"
    r"            ->values\(\)\n"
    r"            ->all\(\);\n\n"
    r"        if \(\$titles === \[\]\) \{\n"
    r"            return null;\n"
    r"        \}\n",
    re.S,
)

replacement = r'''        $materialRows = $materials
            ->values()
            ->map(function ($material, $index) use ($sub, $linkedMaterialIds): array {
                $title = trim((string) ($material->title ?? ''));
                $direct = filled($material->rps_sub_cpmk_id ?? null)
                    && (string) $material->rps_sub_cpmk_id === (string) $sub->id;
                $pivot = filled($material->id ?? null)
                    && in_array((string) $material->id, $linkedMaterialIds, true);

                return [
                    'title' => $title,
                    'index' => (int) $index,
                    'linked' => $direct || $pivot,
                    'score' => $this->materialRelevanceScore(
                        (string) ($sub->description ?? ''),
                        $title
                    ),
                ];
            })
            ->filter(fn (array $item) => $item['title'] !== '')
            ->values();

        $linkedTitles = $materialRows
            ->filter(fn (array $item) => $item['linked'])
            ->sortBy('index')
            ->pluck('title')
            ->unique()
            ->values()
            ->all();

        if ($linkedTitles !== []) {
            // Relasi eksplisit adalah keputusan akademik dosen/AI material plan.
            // Jangan campurkan materi lain hanya karena memiliki satu kata yang sama.
            $titles = $linkedTitles;
        } else {
            $scored = $materialRows
                ->filter(fn (array $item) => $item['score'] > 0)
                ->sort(function (array $a, array $b): int {
                    if ($a['score'] !== $b['score']) {
                        return $b['score'] <=> $a['score'];
                    }
                    return $a['index'] <=> $b['index'];
                })
                ->values();

            $bestScore = $scored->isEmpty() ? 0 : (int) $scored->max('score');
            $candidateLimit = max(1, $weekCount * 2);

            // Satu kecocokan kata pada banyak materi adalah sinyal ambigu.
            // Gunakan urutan kurikulum sebagai fallback, bukan memasukkan semuanya.
            $ambiguous = $bestScore <= 1 && $scored->count() > $candidateLimit;

            if (! $ambiguous && $bestScore > 0) {
                $minimumScore = max(1, $bestScore - 1);
                $titles = $scored
                    ->filter(fn (array $item) => $item['score'] >= $minimumScore)
                    ->take($candidateLimit)
                    ->sortBy('index')
                    ->pluck('title')
                    ->unique()
                    ->values()
                    ->all();
            } else {
                $allTitles = $materialRows->sortBy('index')->pluck('title')->values();
                $totalMaterials = $allTitles->count();
                $subTotal = max(1, DB::table('rps_sub_cpmks')
                    ->where('rps_version_id', $sub->rps_version_id)
                    ->count());
                $subIndex = max(0, min($subTotal - 1, ((int) ($sub->sequence_no ?? 1)) - 1));
                $start = (int) floor(($subIndex * $totalMaterials) / $subTotal);
                $end = (int) floor((($subIndex + 1) * $totalMaterials) / $subTotal);
                $length = max(1, $end - $start);

                $titles = $allTitles
                    ->slice($start, $length)
                    ->take($candidateLimit)
                    ->values()
                    ->all();
            }
        }

        if ($titles === []) {
            return null;
        }
'''

service, count = pattern.subn(replacement, service, count=1)
if count != 1:
    raise SystemExit(f'allocated material selection: expected 1 match, found {count}')

# 3) Never put an unlimited number of material titles into one weekly cell.
service = replace_once(
    service,
    """        if ($titles === []) {\n            return array_fill(0, $weekCount, null);\n        }\n\n        $materialCount = count($titles);\n""",
    """        if ($titles === []) {\n            return array_fill(0, $weekCount, null);\n        }\n\n        // Maksimal dua topik inti per pekan. Daftar Bahan Kajian asli tetap utuh;\n        // pembatasan ini hanya untuk penyajian pada tabel rencana pertemuan.\n        $maxTitles = max(1, $weekCount * 2);\n        if (count($titles) > $maxTitles) {\n            $titles = array_slice($titles, 0, $maxTitles);\n        }\n\n        $materialCount = count($titles);\n""",
    'cap weekly material titles',
)

# 4) Add concise, Bloom-aware activity helper.
helper = r'''    private function learningActivityForWeek(object $sub, bool $hasPracticum): string
    {
        $code = trim((string) ($sub->code ?? 'Sub-CPMK')) ?: 'Sub-CPMK';
        $level = strtoupper(trim((string) ($sub->bloom_level ?? ''));

        $activity = match ($level) {
            'C1', 'C2' => 'Mengidentifikasi konsep utama, mendiskusikan contoh, dan melakukan latihan pemahaman.',
            'C3' => 'Menerapkan konsep melalui contoh dan latihan terarah, kemudian membahas hasilnya.',
            'C4' => 'Menganalisis kasus/contoh, membandingkan hasil, dan menyusun alasan atas temuan.',
            'C5' => 'Mengevaluasi kasus atau hasil kerja menggunakan kriteria yang ditetapkan dan memberikan argumentasi.',
            'C6' => 'Merancang atau mengembangkan solusi, menguji hasil, dan melakukan perbaikan berdasarkan umpan balik.',
            default => 'Membahas konsep, menganalisis contoh, dan menyelesaikan latihan terarah.',
        };

        if ($hasPracticum) {
            $activity = rtrim($activity, '.').' melalui diskusi dan/atau praktikum.';
        }

        return $activity.' Aktivitas diarahkan untuk mencapai '.$code.'.';
    }

'''

service = replace_once(
    service,
    '    private function indicatorFromSubCpmk(string $description): string\n    {',
    helper + '    private function indicatorFromSubCpmk(string $description): string\n    {',
    'learning activity helper insertion',
)

SERVICE.write_text(service, encoding='utf-8')


# 5) Print: table starts close to prerequisite and is allowed to flow continuously.
css = CSS.read_text(encoding='utf-8-sig')
marker = 'Patch: compact Smart Draft + continuous weekly print flow'
if marker not in css:
    css = css.rstrip() + r'''

/* Patch: compact Smart Draft + continuous weekly print flow */
@media print {
    html.rps-print-mode .rps-print-week-gap {
        display: block !important;
        height: 10px !important;
        min-height: 10px !important;
        max-height: 10px !important;
        break-inside: auto !important;
        page-break-inside: auto !important;
    }

    /* Pekan boleh terbelah pada pergantian halaman. Header tabel tetap diulang,
       sehingga tabel terlihat sebagai satu rangkaian kontinu dan tidak menyisakan
       ruang kosong besar hanya untuk memindahkan satu pekan secara utuh. */
    html.rps-print-mode .rps-print-weekly,
    html.rps-print-mode .rps-print-weekly tbody,
    html.rps-print-mode .rps-print-weekly .rps-print-week-block,
    html.rps-print-mode .rps-print-weekly .rps-print-week-block > tr,
    html.rps-print-mode .rps-print-weekly .rps-print-week-block > tr > td {
        break-inside: auto !important;
        page-break-inside: auto !important;
    }
}
''' + '\n'

CSS.write_text(css, encoding='utf-8')
print('Compact Smart Draft and continuous weekly print flow patch applied.')
