<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VerifySimatRpsVercel extends Command
{
    protected $signature = 'simatrps:verify-vercel';
    protected $description = 'Cek kesiapan SiMatRPS untuk deployment Vercel';

    public function handle(): int
    {
        $root = base_path();

        $checks = [
            [
                'api/index.php tersedia',
                file_exists($root.'/api/index.php'),
                'entrypoint Laravel'
            ],
            [
                'vercel.json tersedia',
                file_exists($root.'/vercel.json'),
                'konfigurasi Vercel'
            ],
            [
                'APP_KEY terisi',
                filled(config('app.key')),
                'wajib untuk cookie/enkripsi'
            ],
            [
                'APP_DEBUG nonaktif',
                ! config('app.debug'),
                'production harus false'
            ],
            [
                'Database bukan SQLite',
                config('database.default') !== 'sqlite',
                (string) config('database.default')
            ],
            [
                'Session serverless-safe',
                in_array(config('session.driver'), ['cookie', 'database', 'redis'], true),
                (string) config('session.driver')
            ],
        ];

        $dbOk = false;
        $dbInfo = 'belum diuji';

        try {
            DB::connection()->getPdo();
            $dbOk = true;
            $dbInfo = (string) config('database.default');
        } catch (\Throwable $e) {
            $dbInfo = mb_substr($e->getMessage(), 0, 120);
        }

        $checks[] = ['Koneksi database online', $dbOk, $dbInfo];

        if ($dbOk) {
            $checks[] = [
                'Tabel migrations tersedia',
                Schema::hasTable('migrations'),
                'database sudah pernah dimigrate'
            ];
        }

        $this->table(
            ['Komponen', 'Status', 'Keterangan'],
            array_map(
                fn ($row) => [$row[0], $row[1] ? 'OK' : 'BELUM', $row[2]],
                $checks
            )
        );

        $requiredOk = collect($checks)
            ->every(fn ($row) => (bool) $row[1]);

        if ($requiredOk) {
            $this->info('SiMatRPS siap untuk deployment Vercel.');
            return self::SUCCESS;
        }

        $this->warn('Masih ada konfigurasi Vercel/database yang perlu dilengkapi.');
        return self::FAILURE;
    }
}
