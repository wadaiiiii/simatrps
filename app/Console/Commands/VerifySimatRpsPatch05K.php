<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class VerifySimatRpsPatch05K extends Command
{
    protected $signature = 'simatrps:verify-patch05k';
    protected $description = 'Verifikasi AI kontekstual per pekan dan CPMK no-op guard';

    public function handle(): int
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/RpsAiController.php')
        );
        $provider = file_get_contents(
            app_path('Services/Rps/AiRpsProviderService.php')
        );
        $ui = file_get_contents(
            resource_path('js/pages/rps/show.tsx')
        );
        $groq = file_get_contents(
            app_path('Services/Rps/GroqRpsService.php')
        );

        $routes = collect(Route::getRoutes()->getRoutes());
        $weekRoute = $routes->contains(fn ($route) =>
            $route->uri() === 'rps/{rps}/ai/weeks/{week}'
            && in_array('POST', $route->methods(), true)
        );

        $checks = [
            ['POST AI per pekan', $weekRoute],
            ['Provider generateWeek()', str_contains($provider, 'public function generateWeek')],
            ['Direct apply pekan', str_contains($controller, 'public function generateWeek')],
            ['Audit weekly_week accepted', str_contains($controller, "'weekly_week'")],
            ['CPMK no-op sanitizer', str_contains($controller, 'sanitizeCpmkReviewPayload')],
            ['Prompt ADAPT substantif', str_contains($groq, 'berbeda secara substantif')],
            ['UI AI per baris pekan', str_contains($ui, 'Lengkapi AI')],
            ['UI AI di WeekEditor', str_contains($ui, 'Susun Ulang dengan AI')],
            ['UI Telaah CPMK kontekstual', str_contains($ui, 'Telaah CPMK AI')],
            ['UI Sub-CPMK kontekstual', str_contains($ui, 'Telaah Sub-CPMK AI')],
            ['UI Asesmen kontekstual', str_contains($ui, 'Telaah Asesmen + RTM AI')],
            ['Global 14 pekan dihapus', ! str_contains($ui, 'Susun 14 Pekan dengan AI')],
            ['Retry provider singkat', str_contains($groq, 'attempt <= 2')],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(
                fn (array $row) => [$row[0], $row[1] ? 'OK' : 'BELUM'],
                $checks
            )
        );

        if (collect($checks)->every(fn (array $row) => $row[1])) {
            $this->info('Patch 05K siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05K yang belum siap.');
        return self::FAILURE;
    }
}
