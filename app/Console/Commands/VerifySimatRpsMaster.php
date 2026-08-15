<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VerifySimatRpsMaster extends Command
{
    protected $signature = 'simatrps:verify-master';
    protected $description = 'Memverifikasi master Kurikulum Matematika 2025 SiMatRPS';

    public function handle(): int
    {
        if (! Schema::hasTable('curriculums')) {
            $this->error('Tabel akademik belum tersedia. Jalankan migrate terlebih dahulu.');
            return self::FAILURE;
        }

        $curriculum = DB::table('curriculums')->where('code', 'KUR-MAT-2025')->first();

        if (! $curriculum) {
            $this->error('KUR-MAT-2025 belum tersedia. Jalankan seeder Patch 02.');
            return self::FAILURE;
        }

        $id = $curriculum->id;

        $rows = [
            ['Kurikulum', 1, 1],
            ['CPL', DB::table('cpls')->where('curriculum_id', $id)->count(), 8],
            ['KBK', DB::table('kbks')->where('curriculum_id', $id)->count(), 3],
            ['Mata Kuliah', DB::table('courses')->where('curriculum_id', $id)->count(), 63],
            ['Prasyarat', DB::table('course_prerequisites')->join('courses', 'courses.id', '=', 'course_prerequisites.course_id')->where('courses.curriculum_id', $id)->count(), 35],
            ['MK-CPL', DB::table('course_cpls')->join('courses', 'courses.id', '=', 'course_cpls.course_id')->where('courses.curriculum_id', $id)->count(), 255],
            ['Silabus', DB::table('course_syllabi')->join('courses', 'courses.id', '=', 'course_syllabi.course_id')->where('courses.curriculum_id', $id)->count(), 62],
            ['CPMK', DB::table('curriculum_cpmks')->join('courses', 'courses.id', '=', 'curriculum_cpmks.course_id')->where('courses.curriculum_id', $id)->count(), 290],
            ['Item Silabus', DB::table('course_syllabus_items')->join('courses', 'courses.id', '=', 'course_syllabus_items.course_id')->where('courses.curriculum_id', $id)->count(), 259],
            ['Isu Data', DB::table('curriculum_data_issues')->where('curriculum_id', $id)->count(), 21],
        ];

        $this->table(['Data', 'Jumlah', 'Target', 'Status'], array_map(
            fn (array $row) => [$row[0], $row[1], $row[2], $row[1] === $row[2] ? 'OK' : 'CEK'],
            $rows
        ));

        $missing = DB::table('courses')
            ->leftJoin('curriculum_cpmks', 'curriculum_cpmks.course_id', '=', 'courses.id')
            ->where('courses.curriculum_id', $id)
            ->groupBy('courses.id', 'courses.system_code', 'courses.name', 'courses.verification_status')
            ->havingRaw('COUNT(curriculum_cpmks.id) = 0')
            ->orderBy('courses.semester_recommended')
            ->get([
                'courses.system_code',
                'courses.name',
                'courses.verification_status',
            ]);

        $this->newLine();
        $this->info('Mata kuliah tanpa CPMK master:');
        $this->table(['Kode', 'Mata Kuliah', 'Verifikasi'], $missing->map(
            fn ($row) => [(string) $row->system_code, (string) $row->name, (string) $row->verification_status]
        )->all());

        $allOk = collect($rows)->every(fn (array $row) => $row[1] === $row[2]);

        if ($allOk) {
            $this->newLine();
            $this->info('Master Kurikulum 2025 siap digunakan SiMatRPS.');
            return self::SUCCESS;
        }

        $this->warn('Ada jumlah data yang perlu diperiksa.');
        return self::FAILURE;
    }
}
