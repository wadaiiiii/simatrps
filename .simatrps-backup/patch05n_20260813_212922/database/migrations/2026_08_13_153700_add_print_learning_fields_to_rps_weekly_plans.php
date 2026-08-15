<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('rps_weekly_plans', 'learning_form')) {
            Schema::table('rps_weekly_plans', function (Blueprint $table): void {
                $table->text('learning_form')->nullable()->after('material_text');
            });
        }

        if (! Schema::hasColumn('rps_weekly_plans', 'time_estimate')) {
            Schema::table('rps_weekly_plans', function (Blueprint $table): void {
                $table->string('time_estimate', 120)->nullable()->after('learning_method');
            });
        }

        if (! Schema::hasColumn('rps_weekly_plans', 'student_assignment')) {
            Schema::table('rps_weekly_plans', function (Blueprint $table): void {
                $table->text('student_assignment')->nullable()->after('time_estimate');
            });
        }

        if (! Schema::hasColumn('rps_weekly_plans', 'online_activity')) {
            Schema::table('rps_weekly_plans', function (Blueprint $table): void {
                $table->text('online_activity')->nullable()->after('student_assignment');
            });
        }
    }

    public function down(): void
    {
        $columns = collect([
            'learning_form',
            'time_estimate',
            'student_assignment',
            'online_activity',
        ])->filter(fn (string $column): bool => Schema::hasColumn('rps_weekly_plans', $column))->all();

        if ($columns !== []) {
            Schema::table('rps_weekly_plans', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
