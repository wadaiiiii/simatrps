<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RpsDeleteController extends Controller
{
    public function destroy(Request $request, string $rps): RedirectResponse
    {
        $record = DB::table('rps')
            ->join('courses', 'courses.id', '=', 'rps.course_id')
            ->where('rps.id', $rps)
            ->first([
                'rps.id',
                'rps.owner_id',
                'rps.status',
                'courses.name as course_name',
            ]);

        abort_unless($record, 404);

        $isAdmin = $request->user()->role === 'admin';
        abort_unless($record->owner_id === $request->user()->id || $isAdmin, 403);

        if (in_array($record->status, ['final', 'finalized'], true) && ! $isAdmin) {
            throw ValidationException::withMessages([
                'rps' => 'RPS yang sudah final hanya dapat dihapus oleh Admin.',
            ]);
        }

        DB::table('rps')->where('id', $record->id)->delete();

        return redirect()
            ->route('rps.index')
            ->with('success', "RPS {$record->course_name} berhasil dihapus.");
    }
}
