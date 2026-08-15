<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifySimatRpsPatch05C extends Command
{
    protected $signature = 'simatrps:verify-patch05c';
    protected $description = 'Memverifikasi optimasi token Groq Free pada Patch 05C';

    public function handle(): int
    {
        $context = file_get_contents(app_path('Services/Rps/RpsAiContextService.php'));
        $groq = file_get_contents(app_path('Services/Rps/GroqRpsService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/RpsAiController.php'));

        $checks = [
            ['Context per jenis AI', str_contains($context, 'compactForType')],
            ['Weekly tanpa full weekly_plan', str_contains($context, "'weekly_plan' => \$base +")],
            ['Dynamic output budget', str_contains($groq, 'maxCompletionTokens')],
            ['Weekly output 3200 token', str_contains($groq, "'weekly_plan' => 3200")],
            ['HTTP 413 friendly handling', str_contains($groq, '413 =>')],
            ['Controller kirim suggestion_type', str_contains($controller, "build(\$record, \$version, \$data['suggestion_type'])")],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn (array $row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn (array $row) => $row[1])) {
            $this->info('Patch 05C siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05C yang belum siap.');
        return self::FAILURE;
    }
}
