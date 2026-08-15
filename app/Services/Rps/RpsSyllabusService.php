<?php

namespace App\Services\Rps;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RpsSyllabusService
{
    public function topics(string $courseId): array
    {
        $syllabus = DB::table('course_syllabi')
            ->where('course_id', $courseId)
            ->orderBy('source_entry_no')
            ->first();

        if (! $syllabus || ! filled($syllabus->syllabus_text)) {
            return [];
        }

        $text = trim((string) $syllabus->syllabus_text);

        $parts = preg_split('/\bPustaka\s*:?\s*/iu', $text, 2);
        $topicText = trim($parts[0] ?? $text);

        $topicText = preg_replace('/^\s*Silabus\s*:?\s*/iu', '', $topicText);
        $topicText = preg_replace('/\s+/u', ' ', $topicText);

        $topics = $this->splitTopLevel($topicText);

        return array_values(array_filter(array_map(function (string $topic): string {
            $topic = trim($topic, " \t\n\r\0\x0B,.;");
            return $topic;
        }, $topics), fn (string $topic) => mb_strlen($topic) >= 3));
    }

    public function references(string $courseId): ?string
    {
        $syllabus = DB::table('course_syllabi')
            ->where('course_id', $courseId)
            ->orderBy('source_entry_no')
            ->first();

        if (! $syllabus) {
            return null;
        }

        if (filled($syllabus->reference_text)) {
            return trim((string) $syllabus->reference_text);
        }

        $text = (string) ($syllabus->syllabus_text ?? '');

        if (preg_match('/\bPustaka\s*:?\s*(.+)$/isu', $text, $match)) {
            return trim(preg_replace('/\s+/u', ' ', $match[1]));
        }

        return null;
    }

    public function syncMaterials(
        string $courseId,
        string $versionId,
        bool $replaceImported = true
    ): int {
        $topics = $this->topics($courseId);

        if ($topics === []) {
            return 0;
        }

        if ($replaceImported) {
            DB::table('rps_materials')
                ->where('rps_version_id', $versionId)
                ->where('source_type', 'curriculum_syllabus')
                ->delete();
        }

        $existing = DB::table('rps_materials')
            ->where('rps_version_id', $versionId)
            ->pluck('title')
            ->map(fn ($title) => mb_strtolower(trim((string) $title)))
            ->filter()
            ->flip();

        $rows = [];
        $seen = [];

        foreach ($topics as $index => $topic) {
            $normalized = mb_strtolower(trim($topic));

            if ($normalized === '' || isset($seen[$normalized]) || $existing->has($normalized)) {
                continue;
            }

            $seen[$normalized] = true;
            $rows[] = [
                'id' => (string) Str::uuid(),
                'rps_version_id' => $versionId,
                'rps_sub_cpmk_id' => null,
                'title' => $topic,
                'description' => null,
                'sequence_no' => $index + 1,
                'source_type' => 'curriculum_syllabus',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            DB::table('rps_materials')->insert($rows);
        }

        return count($rows);
    }

    public function importedMaterialsLookLikeReferences(string $versionId): bool
    {
        $items = DB::table('rps_materials')
            ->where('rps_version_id', $versionId)
            ->where('source_type', 'curriculum_syllabus')
            ->pluck('title');

        if ($items->isEmpty()) {
            return false;
        }

        $referenceLike = $items->filter(function (string $title): bool {
            return preg_match('/\b(19|20)\d{2}\b/u', $title) === 1
                || str_contains(mb_strtolower($title), 'deepublish')
                || str_contains(mb_strtolower($title), 'informatika bandung')
                || str_contains(mb_strtolower($title), 'programming: an introduction');
        })->count();

        return $referenceLike >= max(1, (int) ceil($items->count() * 0.6));
    }

    private function splitTopLevel(string $text): array
    {
        $items = [];
        $buffer = '';
        $depth = 0;
        $length = mb_strlen($text);

        for ($i = 0; $i < $length; $i++) {
            $char = mb_substr($text, $i, 1);

            if ($char === '(' || $char === '[') {
                $depth++;
            } elseif ($char === ')' || $char === ']') {
                $depth = max(0, $depth - 1);
            }

            $isSeparator = ($char === ',' || $char === ';') && $depth === 0;

            if ($isSeparator) {
                if (trim($buffer) !== '') {
                    $items[] = trim($buffer);
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $items[] = trim($buffer);
        }

        return $items;
    }
}
