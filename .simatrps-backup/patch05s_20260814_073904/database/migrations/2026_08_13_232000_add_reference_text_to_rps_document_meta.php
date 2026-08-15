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
            && ! Schema::hasColumn('rps_document_meta', 'reference_text')
        ) {
            Schema::table('rps_document_meta', function (Blueprint $table): void {
                $table->longText('reference_text')
                    ->nullable()
                    ->after('description_short');
            });
        }
    }

    public function down(): void
    {
        // Tidak dihapus untuk menghindari kehilangan pustaka RPS yang sudah diedit.
    }
};
