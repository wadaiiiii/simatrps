<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class VerifySimatRpsPatch05P extends Command
{
    protected $signature = 'simatrps:verify-patch05p';
    protected $description = 'Verifikasi Workspace Polish & Manual Controls Patch 05P';

    public function handle(): int
    {
        $ui = file_get_contents(resource_path('js/pages/rps/show.tsx'));
        $rps = file_get_contents(app_path('Http/Controllers/RpsController.php'));
        $ai = file_get_contents(app_path('Http/Controllers/RpsAiController.php'));
        $obe = file_get_contents(app_path('Http/Controllers/ObeWorkspaceController.php'));
        $ctx = file_get_contents(app_path('Services/Rps/RpsAiContextService.php'));
        $routes = collect(Route::getRoutes()->getRoutes());

        $checks = [
            ['Pustaka RPS editable', Schema::hasColumn('rps_document_meta', 'reference_text')],
            ['Deskripsi fallback master', str_contains($rps, '$masterDescription')],
            ['Master syllabus prop', str_contains($rps, "'masterSyllabus' => \$masterSyllabus")],
            ['Pustaka a/b/c jadi 1/2/3', str_contains($rps, "(?:\\d+|[a-z])")],
            ['Sidebar trigger', str_contains($ui, 'SidebarTrigger')],
            ['AI suggestion inline', str_contains($ui, 'Lihat rekomendasi AI') && ! str_contains($ui, 'absolute right-0 top-8')],
            ['CPL hover detail', str_contains($ui, 'group-hover:max-h-32')],
            ['Tambah CPMK manual', str_contains($ui, 'function DocumentCpmkAdd')],
            ['Tambah Sub-CPMK manual', str_contains($ui, 'function DocumentSubCpmkAdd')],
            ['Kode Bloom terlihat', str_contains($ui, "Bloom —")],
            ['Pemetaan CPL terlihat', str_contains($ui, 'CPL terkait:')],
            ['Edit Bahan Kajian', str_contains($ui, 'function DocumentMaterialsManager')],
            ['Route edit material', $routes->contains(fn ($route) => $route->uri() === 'rps/{rps}/materials/{material}' && in_array('PUT', $route->methods(), true))],
            ['Tambah asesmen manual', str_contains($ui, 'function AssessmentQuickAdd')],
            ['Tambah RTM manual', str_contains($ui, 'function TaskQuickAdd')],
            ['AI stale suggestion dibersihkan', str_contains($ai, 'Satu tipe hanya mempunyai satu rekomendasi pending aktif')],
            ['Mapping AI hanya hitung perubahan', str_contains($ai, '$oldIds === $newIds')],
            ['AI memakai pustaka RPS', str_contains($ctx, "rps_document_meta") && str_contains($ctx, "reference_text")],
        ];

        $this->table(
            ['Komponen', 'Status'],
            array_map(fn (array $row) => [$row[0], $row[1] ? 'OK' : 'BELUM'], $checks)
        );

        if (collect($checks)->every(fn (array $row) => $row[1])) {
            $this->info('Patch 05P siap digunakan.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada komponen Patch 05P yang belum siap.');
        return self::FAILURE;
    }
}
