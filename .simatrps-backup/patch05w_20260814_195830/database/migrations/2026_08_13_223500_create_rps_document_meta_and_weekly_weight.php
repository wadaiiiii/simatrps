<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rps_document_meta')) {
            Schema::create('rps_document_meta', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('rps_version_id')->unique();
                $table->string('course_cluster', 255)->nullable();
                $table->date('prepared_date')->nullable();
                $table->string('developer_name', 500)->nullable();
                $table->string('coordinator_name', 500)->nullable();
                $table->string('head_program_name', 500)->nullable();
                $table->text('lecturer_names')->nullable();
                $table->text('software_media')->nullable();
                $table->text('hardware_media')->nullable();
                $table->text('prerequisite_text')->nullable();
                $table->text('description_short')->nullable();
                $table->timestamps();

                $table->foreign('rps_version_id')
                    ->references('id')
                    ->on('rps_versions')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasColumn('rps_weekly_plans', 'assessment_weight')) {
            Schema::table('rps_weekly_plans', function (Blueprint $table): void {
                $table->decimal('assessment_weight', 5, 2)
                    ->nullable()
                    ->after('reference_text');
            });
        }

        // Backfill bobot cetak dari asesmen lama agar data existing tetap konsisten.
        if (
            Schema::hasTable('assessments')
            && Schema::hasColumn('assessments', 'weight')
            && Schema::hasColumn('assessments', 'week_number')
        ) {
            $weeks = DB::table('rps_weekly_plans')
                ->whereNull('assessment_weight')
                ->get(['id', 'rps_version_id', 'week_number']);

            foreach ($weeks as $week) {
                $sum = (float) DB::table('assessments')
                    ->where('rps_version_id', $week->rps_version_id)
                    ->where('week_number', $week->week_number)
                    ->sum('weight');

                if ($sum > 0) {
                    DB::table('rps_weekly_plans')
                        ->where('id', $week->id)
                        ->update([
                            'assessment_weight' => round($sum, 2),
                            'updated_at' => now(),
                        ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('rps_document_meta')) {
            Schema::drop('rps_document_meta');
        }

        // assessment_weight tidak dihapus karena bisa saja sudah merupakan
        // kolom inti pada instalasi SiMatRPS yang lebih lama.
    }
};
