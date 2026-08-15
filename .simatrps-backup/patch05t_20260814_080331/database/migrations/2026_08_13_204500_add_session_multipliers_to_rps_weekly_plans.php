<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rps_weekly_plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('rps_weekly_plans', 'face_to_face_sessions')) {
                $table->unsignedTinyInteger('face_to_face_sessions')->default(1)->after('time_estimate');
            }

            if (! Schema::hasColumn('rps_weekly_plans', 'structured_task_sessions')) {
                $table->unsignedTinyInteger('structured_task_sessions')->default(1)->after('student_assignment');
            }

            if (! Schema::hasColumn('rps_weekly_plans', 'independent_study_sessions')) {
                $table->unsignedTinyInteger('independent_study_sessions')->default(1)->after('learning_activity');
            }
        });
    }

    public function down(): void
    {
        $columns = collect([
            'face_to_face_sessions',
            'structured_task_sessions',
            'independent_study_sessions',
        ])->filter(fn (string $column): bool => Schema::hasColumn('rps_weekly_plans', $column))->all();

        if ($columns !== []) {
            Schema::table('rps_weekly_plans', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
