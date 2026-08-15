<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_programs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('faculty_name');
            $table->string('university_name');
            $table->string('degree_level')->default('S1');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('curriculums', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('study_program_id');
            $table->string('code');
            $table->string('name');
            $table->unsignedSmallInteger('year');
            $table->string('effective_academic_year')->nullable();
            $table->string('end_academic_year')->nullable();
            $table->string('status')->default('draft')->index();
            $table->string('source_type')->default('official_curriculum');
            $table->text('source_reference')->nullable();
            $table->text('source_file_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('study_program_id')->references('id')->on('study_programs')->cascadeOnDelete();
            $table->unique(['study_program_id', 'code'], 'curriculum_code_unique');
        });

        Schema::create('cpls', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('curriculum_id');
            $table->string('code');
            $table->text('description');
            $table->string('domain')->nullable();
            $table->unsignedSmallInteger('sequence_no');
            $table->boolean('is_active')->default(true);
            $table->text('source_reference')->nullable();
            $table->timestamps();

            $table->foreign('curriculum_id')->references('id')->on('curriculums')->cascadeOnDelete();
            $table->unique(['curriculum_id', 'code'], 'cpl_code_unique');
        });

        Schema::create('kbks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('curriculum_id');
            $table->string('code');
            $table->string('name');
            $table->unsignedSmallInteger('sequence_no');
            $table->boolean('is_active')->default(true);
            $table->text('source_reference')->nullable();
            $table->timestamps();

            $table->foreign('curriculum_id')->references('id')->on('curriculums')->cascadeOnDelete();
            $table->unique(['curriculum_id', 'code'], 'kbk_code_unique');
        });

        Schema::create('course_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedSmallInteger('sequence_no')->default(1);
            $table->boolean('is_active')->default(true);
        });

        Schema::create('courses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('curriculum_id');
            $table->string('system_code');
            $table->string('official_code')->nullable()->index();
            $table->string('name');
            $table->decimal('credits', 4, 1);
            $table->unsignedSmallInteger('semester_recommended')->nullable()->index();
            $table->boolean('is_mandatory')->default(true);
            $table->unsignedBigInteger('category_id')->nullable();
            $table->uuid('kbk_id')->nullable();
            $table->string('course_type')->default('mandatory');
            $table->boolean('has_practicum')->default(false);
            $table->boolean('is_recognition_course')->default(false);
            $table->boolean('is_course_group')->default(false);
            $table->string('code_status')->default('official');
            $table->text('prerequisite_note')->nullable();
            $table->string('verification_status')->default('source_verified');
            $table->text('source_reference')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('curriculum_id')->references('id')->on('curriculums')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('course_categories')->nullOnDelete();
            $table->foreign('kbk_id')->references('id')->on('kbks')->nullOnDelete();
            $table->unique(['curriculum_id', 'system_code'], 'course_system_code_unique');
        });

        Schema::create('course_variants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('parent_course_id');
            $table->string('variant_code');
            $table->string('variant_name');
            $table->decimal('credits', 4, 1);
            $table->boolean('is_active')->default(true);
            $table->text('source_reference')->nullable();
            $table->timestamps();

            $table->foreign('parent_course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->unique(['parent_course_id', 'variant_code'], 'course_variant_unique');
        });

        Schema::create('course_cpls', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->uuid('cpl_id');
            $table->string('contribution_level')->default('supporting');
            $table->decimal('planned_weight', 5, 2)->nullable();
            $table->text('source_reference')->nullable();
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->foreign('cpl_id')->references('id')->on('cpls')->cascadeOnDelete();
            $table->unique(['course_id', 'cpl_id'], 'course_cpl_unique');
        });

        Schema::create('course_prerequisites', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->uuid('prerequisite_course_id');
            $table->string('prerequisite_type')->default('required');
            $table->string('minimum_grade')->nullable();
            $table->text('source_reference')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->foreign('prerequisite_course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->unique(
                ['course_id', 'prerequisite_course_id', 'prerequisite_type'],
                'course_prerequisite_unique'
            );
        });

        Schema::create('course_syllabi', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->unsignedSmallInteger('source_entry_no')->nullable();
            $table->string('source_variant_code')->nullable();
            $table->string('source_course_code')->nullable();
            $table->text('source_course_header')->nullable();
            $table->decimal('source_credits', 4, 1)->nullable();
            $table->text('source_prerequisite_text')->nullable();
            $table->text('description')->nullable();
            $table->text('syllabus_text')->nullable();
            $table->text('reference_text')->nullable();
            $table->string('verification_status')->default('source_verified');
            $table->text('source_reference')->nullable();
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->unique(['course_id', 'source_entry_no'], 'course_syllabus_source_unique');
        });

        Schema::create('course_syllabus_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->unsignedSmallInteger('source_entry_no')->nullable();
            $table->unsignedSmallInteger('sequence_no');
            $table->text('title');
            $table->text('description')->nullable();
            $table->text('source_reference')->nullable();
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->unique(['course_id', 'source_entry_no', 'sequence_no'], 'syllabus_item_unique');
        });

        Schema::create('curriculum_cpmks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->string('code');
            $table->text('description');
            $table->unsignedSmallInteger('sequence_no');
            $table->string('verification_status')->default('source_verified');
            $table->text('source_reference')->nullable();
            $table->unsignedSmallInteger('source_entry_no')->nullable();
            $table->string('source_course_code')->nullable();
            $table->text('source_course_header')->nullable();
            $table->string('source_variant_code')->nullable();
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->unique(['course_id', 'code'], 'curriculum_cpmk_unique');
        });

        Schema::create('curriculum_data_issues', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('curriculum_id');
            $table->string('issue_code');
            $table->string('entity_type');
            $table->text('entity_key')->nullable();
            $table->text('issue_description');
            $table->text('selected_value')->nullable();
            $table->text('selection_basis')->nullable();
            $table->string('severity')->default('warning');
            $table->string('status')->default('open');
            $table->text('source_reference')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('curriculum_id')->references('id')->on('curriculums')->cascadeOnDelete();
            $table->unique(['curriculum_id', 'issue_code'], 'curriculum_issue_unique');
        });

        Schema::create('rps_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('study_program_id');
            $table->string('code');
            $table->string('name');
            $table->unsignedSmallInteger('version_no')->default(1);
            $table->unsignedSmallInteger('effective_year')->nullable();
            $table->string('status')->default('draft');
            $table->text('description')->nullable();
            $table->text('source_reference')->nullable();
            $table->timestamps();

            $table->foreign('study_program_id')->references('id')->on('study_programs')->cascadeOnDelete();
            $table->unique(['study_program_id', 'code', 'version_no'], 'rps_template_unique');
        });

        Schema::create('rps_template_sections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('template_id');
            $table->string('section_key');
            $table->string('section_name');
            $table->unsignedSmallInteger('sequence_no');
            $table->boolean('is_required')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->foreign('template_id')->references('id')->on('rps_templates')->cascadeOnDelete();
            $table->unique(['template_id', 'section_key'], 'rps_template_section_unique');
        });

        Schema::create('obe_rules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description');
            $table->string('severity')->default('warning');
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('rps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('curriculum_id');
            $table->uuid('course_id');
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('academic_year');
            $table->string('academic_semester');
            $table->string('status')->default('draft')->index();
            $table->uuid('current_version_id')->nullable();
            $table->timestamps();

            $table->foreign('curriculum_id')->references('id')->on('curriculums')->restrictOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->restrictOnDelete();
            $table->unique(
                ['course_id', 'owner_id', 'academic_year', 'academic_semester'],
                'rps_owner_period_unique'
            );
        });

        Schema::create('rps_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rps_id');
            $table->decimal('version_no', 6, 2)->default(1);
            $table->uuid('template_id')->nullable();
            $table->string('status')->default('draft');
            $table->text('description_short')->nullable();
            $table->text('change_summary')->nullable();
            $table->json('ai_generation_meta')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->foreign('rps_id')->references('id')->on('rps')->cascadeOnDelete();
            $table->foreign('template_id')->references('id')->on('rps_templates')->nullOnDelete();
            $table->unique(['rps_id', 'version_no'], 'rps_version_unique');
        });

        Schema::create('rps_cpmks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rps_version_id');
            $table->string('code');
            $table->text('description');
            $table->string('bloom_level')->nullable();
            $table->string('source_type')->default('curriculum');
            $table->uuid('source_cpmk_id')->nullable();
            $table->unsignedSmallInteger('sequence_no');
            $table->timestamps();

            $table->foreign('rps_version_id')->references('id')->on('rps_versions')->cascadeOnDelete();
            $table->foreign('source_cpmk_id')->references('id')->on('curriculum_cpmks')->nullOnDelete();
            $table->unique(['rps_version_id', 'code'], 'rps_cpmk_unique');
        });

        Schema::create('rps_weekly_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rps_version_id');
            $table->unsignedSmallInteger('week_number');
            $table->text('learning_outcome')->nullable();
            $table->text('assessment_indicator')->nullable();
            $table->text('assessment_criteria')->nullable();
            $table->text('assessment_method')->nullable();
            $table->text('learning_method')->nullable();
            $table->text('learning_activity')->nullable();
            $table->text('material_text')->nullable();
            $table->text('reference_text')->nullable();
            $table->decimal('assessment_weight', 5, 2)->nullable();
            $table->boolean('is_exam')->default(false);
            $table->string('exam_type')->nullable();
            $table->string('source_type')->default('system');
            $table->timestamps();

            $table->foreign('rps_version_id')->references('id')->on('rps_versions')->cascadeOnDelete();
            $table->unique(['rps_version_id', 'week_number'], 'rps_week_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rps_weekly_plans');
        Schema::dropIfExists('rps_cpmks');
        Schema::dropIfExists('rps_versions');
        Schema::dropIfExists('rps');
        Schema::dropIfExists('obe_rules');
        Schema::dropIfExists('rps_template_sections');
        Schema::dropIfExists('rps_templates');
        Schema::dropIfExists('curriculum_data_issues');
        Schema::dropIfExists('curriculum_cpmks');
        Schema::dropIfExists('course_syllabus_items');
        Schema::dropIfExists('course_syllabi');
        Schema::dropIfExists('course_prerequisites');
        Schema::dropIfExists('course_cpls');
        Schema::dropIfExists('course_variants');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('course_categories');
        Schema::dropIfExists('kbks');
        Schema::dropIfExists('cpls');
        Schema::dropIfExists('curriculums');
        Schema::dropIfExists('study_programs');
    }
};
