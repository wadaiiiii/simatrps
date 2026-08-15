<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('rps_document_meta')
            && ! Schema::hasColumn('rps_document_meta', 'published_date')
        ) {
            Schema::table('rps_document_meta', function (Blueprint $table): void {
                $table->date('published_date')
                    ->nullable()
                    ->after('prepared_date');
            });
        }

        if (! Schema::hasTable('rps_weekly_simulations')) {
            Schema::create('rps_weekly_simulations', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('rps_version_id');
                $table->unsignedTinyInteger('week_number');
                $table->decimal('score', 5, 2)->nullable();
                $table->timestamps();

                $table->unique(
                    ['rps_version_id', 'week_number'],
                    'rps_weekly_simulations_version_week_unique'
                );

                $table->foreign('rps_version_id')
                    ->references('id')
                    ->on('rps_versions')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Data simulasi dan tanggal terbit tidak dihapus agar tidak kehilangan
        // konfigurasi dokumen RPS yang sudah dibuat.
    }
};
