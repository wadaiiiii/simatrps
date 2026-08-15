<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rps_additional_cpls', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rps_version_id');
            $table->uuid('cpl_id');
            $table->string('source_type')->default('lecturer');
            $table->text('rationale')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('rps_version_id')
                ->references('id')
                ->on('rps_versions')
                ->cascadeOnDelete();

            $table->foreign('cpl_id')
                ->references('id')
                ->on('cpls')
                ->cascadeOnDelete();

            $table->unique(
                ['rps_version_id', 'cpl_id'],
                'rps_additional_cpl_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rps_additional_cpls');
    }
};
