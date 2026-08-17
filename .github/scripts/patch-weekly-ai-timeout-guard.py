from pathlib import Path

provider = Path('app/Services/Rps/AiRpsProviderService.php')
text = provider.read_text()

old = """        return $this->generateAcrossProviders(\n            fn ($service) => $service->generateWeeklyBatch(\n                $context,\n                [$week],\n                $instruction\n            )\n        );"""
new = """        // Susun AI per pekan berjalan di Vercel Function dengan maxDuration 60 detik.\n        // Batasi dua provider per request agar fallback serial tidak melewati batas runtime.\n        // Provider yang gagal/rate-limited akan masuk cooldown sehingga percobaan berikutnya\n        // dapat bergerak ke provider sehat berikutnya.\n        return $this->generateAcrossProviders(\n            fn ($service) => $service->generateWeeklyBatch(\n                $context,\n                [$week],\n                $instruction\n            ),\n            2\n        );"""
if old not in text:
    raise SystemExit('generateWeek marker not found')
text = text.replace(old, new, 1)

old_sig = "private function generateAcrossProviders(callable $callback): array"
new_sig = "private function generateAcrossProviders(callable $callback, ?int $maxAttempts = null): array"
if old_sig not in text:
    raise SystemExit('generateAcrossProviders signature not found')
text = text.replace(old_sig, new_sig, 1)

old_loop = """        foreach ($available as $name => $service) {\n            $attempted[] = $name;"""
new_loop = """        foreach ($available as $name => $service) {\n            if ($maxAttempts !== null && count($attempted) >= $maxAttempts) {\n                break;\n            }\n\n            $attempted[] = $name;"""
if old_loop not in text:
    raise SystemExit('provider loop marker not found')
text = text.replace(old_loop, new_loop, 1)

old_throw = """        throw ValidationException::withMessages([\n            'ai' => 'Semua provider AI aktif gagal. Sudah mencoba/melewati: '\n                .collect(array_keys($allProblems))\n                    ->map(fn ($name) => strtoupper($name))\n                    ->implode(', ')\n                .'. '\n                .collect($allProblems)\n                    ->map(fn ($message, $name) =>\n                        strtoupper($name).': '.$message\n                    )\n                    ->implode(' | '),\n        ]);"""
new_throw = """        $safeBudgetNote = $maxAttempts !== null\n            ? ' Percobaan per request dibatasi '.count($attempted).' provider agar tidak melewati batas waktu server. Coba lagi; provider bermasalah akan dilewati selama masa cooldown.'\n            : '';\n\n        throw ValidationException::withMessages([\n            'ai' => 'Provider AI yang dicoba pada request ini belum berhasil. Sudah mencoba/melewati: '\n                .collect(array_keys($allProblems))\n                    ->map(fn ($name) => strtoupper($name))\n                    ->implode(', ')\n                .'. '\n                .collect($allProblems)\n                    ->map(fn ($message, $name) =>\n                        strtoupper($name).': '.$message\n                    )\n                    ->implode(' | ')\n                .$safeBudgetNote,\n        ]);"""
if old_throw not in text:
    raise SystemExit('provider final error marker not found')
text = text.replace(old_throw, new_throw, 1)

old_regex = """'/tokens per day|TPD|daily quota|rate limit|high demand|temporarily unavailable|timeout|timed out|denied access|access denied|service unavailable|payment method is required|payment required|HTTP 402|invalid api key|unauthorized|HTTP 401/i',"""
new_regex = """'/tokens per day|TPD|daily quota|rate limit|high demand|temporarily unavailable|timeout|timed out|denied access|access denied|service unavailable|payment method is required|payment required|HTTP 402|invalid api key|unauthorized|HTTP 401|output JSON|JSON.*(?:diproses|dipulihkan)|syntax error|invalid json/i',"""
if old_regex not in text:
    raise SystemExit('cooldown regex marker not found')
text = text.replace(old_regex, new_regex, 1)
provider.write_text(text)

controller = Path('app/Http/Controllers/RpsAiController.php')
text = controller.read_text()
old_candidate = """        $candidate = [\n            'rps_sub_cpmk_id' => $subId,"""
new_candidate = """        try {\n            $scannableLearningActivity = $this->formatScannableLearningActivity(\n                (string) ($item['learning_activity'] ?? '')\n            );\n        } catch (\\Throwable $error) {\n            // Formatter hanya untuk presentasi/scannability. Jangan biarkan output AI\n            // yang aneh menjatuhkan keseluruhan request menjadi HTTP 500.\n            report($error);\n            $rawLearningActivity = trim((string) ($item['learning_activity'] ?? ''));\n            $scannableLearningActivity = $rawLearningActivity !== ''\n                ? $rawLearningActivity\n                : null;\n        }\n\n        $candidate = [\n            'rps_sub_cpmk_id' => $subId,"""
if old_candidate not in text:
    raise SystemExit('candidate marker not found')
text = text.replace(old_candidate, new_candidate, 1)

old_activity = """            'learning_activity' => $this->formatScannableLearningActivity((string) ($item['learning_activity'] ?? '')),"""
new_activity = """            'learning_activity' => $scannableLearningActivity,"""
if old_activity not in text:
    raise SystemExit('learning_activity marker not found')
text = text.replace(old_activity, new_activity, 1)
controller.write_text(text)

# Trigger marker: weekly-ai-timeout-guard-v1
