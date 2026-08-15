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
            && ! Schema::hasColumn('rps_document_meta', 'supporting_reference_text')
        ) {
            Schema::table('rps_document_meta', function (Blueprint $table): void {
                $table->longText('supporting_reference_text')
                    ->nullable()
                    ->after('reference_text');
            });
        }
    }

    public function down(): void
    {
        // Disengaja tidak dihapus agar pustaka pendukung RPS tidak hilang.
    }
};
