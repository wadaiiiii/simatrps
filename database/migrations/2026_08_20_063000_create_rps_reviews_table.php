<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rps_reviews')) {
            return;
        }

        Schema::create('rps_reviews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('rps_version_id');
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 40)->index();
            $table->text('note')->nullable();
            $table->timestamp('reviewed_at')->index();
            $table->timestamps();

            $table->foreign('rps_version_id')
                ->references('id')
                ->on('rps_versions')
                ->cascadeOnDelete();
            $table->index(['rps_version_id', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rps_reviews');
    }
};
