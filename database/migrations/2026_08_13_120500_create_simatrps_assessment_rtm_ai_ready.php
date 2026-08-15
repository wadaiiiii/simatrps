<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rps_version_id');
            $table->string('code');
            $table->string('name');
            $table->string('type')->index();
            $table->unsignedSmallInteger('week_number')->nullable();
            $table->text('description')->nullable();
            $table->decimal('weight', 5, 2)->nullable();
            $table->string('source_type')->default('manual');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('rps_version_id')->references('id')->on('rps_versions')->cascadeOnDelete();
            $table->unique(['rps_version_id', 'code'], 'assessment_code_unique');
        });

        Schema::create('assessment_subcpmks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('assessment_id');
            $table->uuid('rps_sub_cpmk_id');
            $table->timestamps();

            $table->foreign('assessment_id')->references('id')->on('assessments')->cascadeOnDelete();
            $table->foreign('rps_sub_cpmk_id')->references('id')->on('rps_sub_cpmks')->cascadeOnDelete();
            $table->unique(['assessment_id', 'rps_sub_cpmk_id'], 'assessment_subcpmk_unique');
        });

        Schema::create('rps_tasks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rps_version_id');
            $table->uuid('assessment_id')->nullable();
            $table->string('code');
            $table->string('title');
            $table->string('type')->default('assignment');
            $table->text('purpose')->nullable();
            $table->text('instructions')->nullable();
            $table->text('expected_output')->nullable();
            $table->unsignedSmallInteger('due_week')->nullable();
            $table->string('source_type')->default('manual');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('rps_version_id')->references('id')->on('rps_versions')->cascadeOnDelete();
            $table->foreign('assessment_id')->references('id')->on('assessments')->nullOnDelete();
            $table->unique(['rps_version_id', 'code'], 'rps_task_code_unique');
        });

        Schema::create('rps_task_subcpmks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rps_task_id');
            $table->uuid('rps_sub_cpmk_id');
            $table->timestamps();

            $table->foreign('rps_task_id')->references('id')->on('rps_tasks')->cascadeOnDelete();
            $table->foreign('rps_sub_cpmk_id')->references('id')->on('rps_sub_cpmks')->cascadeOnDelete();
            $table->unique(['rps_task_id', 'rps_sub_cpmk_id'], 'rps_task_subcpmk_unique');
        });

        Schema::create('obe_validation_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rps_version_id');
            $table->string('rule_code');
            $table->string('severity')->default('warning');
            $table->boolean('is_passed');
            $table->text('message');
            $table->json('details')->nullable();
            $table->timestamp('validated_at');
            $table->timestamps();

            $table->foreign('rps_version_id')->references('id')->on('rps_versions')->cascadeOnDelete();
            $table->index(['rps_version_id', 'validated_at']);
        });

        Schema::create('ai_suggestions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rps_version_id');
            $table->string('suggestion_type')->index();
            $table->string('status')->default('pending')->index();
            $table->json('input_context')->nullable();
            $table->json('suggestion_payload');
            $table->json('accepted_payload')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->foreign('rps_version_id')->references('id')->on('rps_versions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_suggestions');
        Schema::dropIfExists('obe_validation_results');
        Schema::dropIfExists('rps_task_subcpmks');
        Schema::dropIfExists('rps_tasks');
        Schema::dropIfExists('assessment_subcpmks');
        Schema::dropIfExists('assessments');
    }
};
