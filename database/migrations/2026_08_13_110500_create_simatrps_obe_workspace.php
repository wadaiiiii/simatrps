<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rps_cpmk_cpls', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rps_cpmk_id');
            $table->uuid('cpl_id');
            $table->string('source_type')->default('manual');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign('rps_cpmk_id')->references('id')->on('rps_cpmks')->cascadeOnDelete();
            $table->foreign('cpl_id')->references('id')->on('cpls')->cascadeOnDelete();
            $table->unique(['rps_cpmk_id', 'cpl_id']);
        });

        Schema::create('rps_sub_cpmks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rps_version_id');
            $table->string('code');
            $table->text('description');
            $table->string('bloom_level')->nullable();
            $table->unsignedSmallInteger('sequence_no');
            $table->string('source_type')->default('manual');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign('rps_version_id')->references('id')->on('rps_versions')->cascadeOnDelete();
            $table->unique(['rps_version_id', 'code']);
        });

        Schema::create('rps_cpmk_subcpmks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rps_cpmk_id');
            $table->uuid('rps_sub_cpmk_id');
            $table->timestamps();
            $table->foreign('rps_cpmk_id')->references('id')->on('rps_cpmks')->cascadeOnDelete();
            $table->foreign('rps_sub_cpmk_id')->references('id')->on('rps_sub_cpmks')->cascadeOnDelete();
            $table->unique(['rps_cpmk_id', 'rps_sub_cpmk_id']);
        });

        Schema::create('rps_materials', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rps_version_id');
            $table->uuid('rps_sub_cpmk_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sequence_no');
            $table->string('source_type')->default('manual');
            $table->timestamps();
            $table->foreign('rps_version_id')->references('id')->on('rps_versions')->cascadeOnDelete();
            $table->foreign('rps_sub_cpmk_id')->references('id')->on('rps_sub_cpmks')->nullOnDelete();
        });

        Schema::table('rps_weekly_plans', function (Blueprint $table): void {
            $table->uuid('rps_sub_cpmk_id')->nullable();
            $table->foreign('rps_sub_cpmk_id')->references('id')->on('rps_sub_cpmks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rps_weekly_plans', function (Blueprint $table): void {
            $table->dropForeign(['rps_sub_cpmk_id']);
            $table->dropColumn('rps_sub_cpmk_id');
        });
        Schema::dropIfExists('rps_materials');
        Schema::dropIfExists('rps_cpmk_subcpmks');
        Schema::dropIfExists('rps_sub_cpmks');
        Schema::dropIfExists('rps_cpmk_cpls');
    }
};
