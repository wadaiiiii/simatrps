<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rps_material_subcpmks')) {
            Schema::create('rps_material_subcpmks', function (Blueprint $table): void {
                $table->uuid('rps_material_id');
                $table->uuid('rps_sub_cpmk_id');
                $table->timestamps();

                $table->primary(
                    ['rps_material_id', 'rps_sub_cpmk_id'],
                    'rps_material_subcpmks_pk'
                );

                $table->foreign('rps_material_id')
                    ->references('id')
                    ->on('rps_materials')
                    ->cascadeOnDelete();

                $table->foreign('rps_sub_cpmk_id')
                    ->references('id')
                    ->on('rps_sub_cpmks')
                    ->cascadeOnDelete();
            });
        }

        if (
            Schema::hasTable('rps_materials')
            && Schema::hasColumn('rps_materials', 'rps_sub_cpmk_id')
        ) {
            DB::table('rps_materials')
                ->whereNotNull('rps_sub_cpmk_id')
                ->orderBy('sequence_no')
                ->get(['id', 'rps_sub_cpmk_id'])
                ->each(function ($row): void {
                    DB::table('rps_material_subcpmks')->updateOrInsert(
                        [
                            'rps_material_id' => $row->id,
                            'rps_sub_cpmk_id' => $row->rps_sub_cpmk_id,
                        ],
                        [
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                });
        }
    }

    public function down(): void
    {
        // Disengaja tidak menghapus data korelasi akademik.
    }
};
