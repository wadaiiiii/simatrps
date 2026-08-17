<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rps_validator_decisions')) {
            return;
        }

        Schema::create('rps_validator_decisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rps_version_id');
            $table->string('check_key', 80);
            $table->string('subject_key', 500);
            $table->string('decision', 30)->default('keep');
            $table->uuid('decided_by')->nullable();
            $table->timestamps();

            $table->unique(
                ['rps_version_id', 'check_key', 'subject_key'],
                'rps_validator_decision_unique'
            );
            $table->index(['rps_version_id', 'check_key']);
            $table->foreign('rps_version_id')
                ->references('id')
                ->on('rps_versions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rps_validator_decisions');
    }
};
