<?php

namespace App\Services\Rps;

final class ComputerAssistedTechniqueGuard
{
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
        $current = trim((string) ($week['assessment_method'] ?? ''));
        $normalized = mb_strtolower($current);

        if (! in_array($normalized, ['tes tertulis', 'penilaian produk', 'observasi'], true)) {
            return $current;
        }

        $evidence = mb_strtolower($this->evidenceText($week, $context));

        if ($this->hasExplicitWrittenEvidence($evidence)) {
            return $current;
        }

        if ($this->hasComputerAssistedPerformanceEvidence($evidence)) {
            return 'Penilaian kinerja';
        }

        return $current;
    }

    private function hasComputerAssistedPerformanceEvidence(string $evidence): bool
    {
        $hasExecutionAction = preg_match(
            '/\b(?:mengimplementasikan|diimplementasikan|implementasi|menerapkan|diterapkan|menjalankan|dijalankan|mengeksekusi|dieksekusi|eksekusi|menggunakan|digunakan|penggunaan|menguji|diuji|pengujian|mengukur|diukur|pengukuran|benchmark|benchmarking|men-debug|debug|debugging|mensimulasikan|disimulasikan|simulasi|mengolah|diolah|pengolahan|memproses|diproses|pemrosesan|mengoperasikan|dioperasikan|konfigurasi|mengonfigurasi)\b/u',
            $evidence
        ) === 1;

        $hasComputerEnvironment = preg_match(
            '/\b(?:komputer|komputasi|aplikasi\s+komputasi|software|perangkat\s+lunak|sql|database|basis\s+data|query|kueri|python|\br\b|matlab|arcgis|qgis|gis|spreadsheet|excel|notebook|jupyter|ide|terminal|server|source\s+code|kode\s+program|program|runtime|bfs|dfs|algoritma\s+pencarian)\b/u',
            $evidence
        ) === 1;

        return $hasExecutionAction && $hasComputerEnvironment;
    }

    private function hasExplicitWrittenEvidence(string $evidence): bool
    {
        return preg_match(
            '/tes\s+tertulis|ujian\s+tertulis|kuis\s+tertulis|soal\s+tertulis|jawaban\s+tertulis|lembar\s+jawaban|esai\s+tertulis|pembuktian\s+tertulis/u',
            $evidence
        ) === 1;
    }

    /**
     * @param  array<string, mixed>  $week
     * @param  array<string, mixed>  $context
     */
    private function evidenceText(array $week, array $context): string
    {
        $parts = [];

        foreach ([
            'assessment_indicator',
            'assessment_criteria',
            'student_assignment',
            'learning_activity',
            'learning_method',
            'material',
        ] as $field) {
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
