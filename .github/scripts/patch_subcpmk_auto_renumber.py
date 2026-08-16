from pathlib import Path

p = Path('app/Http/Controllers/ObeWorkspaceController.php')
s = p.read_text(encoding='utf-8')

old = '''        DB::table('rps_sub_cpmks')->where('id', $subCpmk)->delete();

        return back()->with('success', 'Sub-CPMK dihapus. Nomor yang kosong akan dipakai kembali saat menambah Sub-CPMK baru.');
'''

new = '''        DB::transaction(function () use ($subCpmk, $version): void {
            DB::table('rps_sub_cpmks')
                ->where('id', $subCpmk)
                ->delete();

            $remaining = DB::table('rps_sub_cpmks')
                ->where('rps_version_id', $version->id)
                ->orderBy('sequence_no')
                ->orderBy('code')
                ->get(['id']);

            // Dua tahap untuk menghindari bentrok unique code saat, misalnya,
            // Sub-CPMK-8 harus berubah menjadi Sub-CPMK-6 sementara kode lama
            // masih ada pada baris berikutnya. Relasi lain tetap aman karena
            // seluruh mapping menggunakan UUID Sub-CPMK, bukan teks kodenya.
            foreach ($remaining as $index => $item) {
                DB::table('rps_sub_cpmks')
                    ->where('id', $item->id)
                    ->update([
                        'code' => '__renumber__'.$item->id,
                        'sequence_no' => 1000 + $index,
                        'updated_at' => now(),
                    ]);
            }

            foreach ($remaining as $index => $item) {
                $sequence = $index + 1;

                DB::table('rps_sub_cpmks')
                    ->where('id', $item->id)
                    ->update([
                        'code' => 'Sub-CPMK-'.$sequence,
                        'sequence_no' => $sequence,
                        'updated_at' => now(),
                    ]);
            }

            // Rekomendasi Sub-CPMK yang belum diterapkan menyimpan target_code.
            // Setelah renumber target tersebut dapat menjadi basi, jadi hapus
            // rekomendasi pending agar telaah berikutnya memakai struktur baru.
            DB::table('ai_suggestions')
                ->where('rps_version_id', $version->id)
                ->where('suggestion_type', 'sub_cpmk')
                ->where('status', 'pending')
                ->delete();
        });

        return back()->with(
            'success',
            'Sub-CPMK dihapus dan urutan Sub-CPMK dirapikan otomatis.'
        );
'''

if old not in s:
    raise SystemExit('destroySubCpmk target block not found')

s = s.replace(old, new, 1)
p.write_text(s, encoding='utf-8')
