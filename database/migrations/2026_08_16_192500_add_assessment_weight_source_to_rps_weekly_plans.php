<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('rps_weekly_plans', 'assessment_weight_source')) {
            Schema::table('rps_weekly_plans', function (Blueprint $table): void {
                $table->string('assessment_weight_source', 32)
                    ->nullable()
                    ->after('assessment_weight');
            });
        }

        DB::table('rps_weekly_plans')
            ->whereIn('week_number', [8, 16])
            ->update(['assessment_weight_source' => 'exam']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('rps_weekly_plans', 'assessment_weight_source')) {
            Schema::table('rps_weekly_plans', function (Blueprint $table): void {
                $table->dropColumn('assessment_weight_source');
            });
        }
    }
};
