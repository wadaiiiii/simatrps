<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $ownerId = $request->user()->id;

        $rps = DB::table('rps')->where('owner_id', $ownerId);

        $recent = DB::table('rps')
            ->join('courses', 'courses.id', '=', 'rps.course_id')
            ->where('rps.owner_id', $ownerId)
            ->orderByDesc('rps.updated_at')
            ->limit(5)
            ->get([
                'rps.id',
                'rps.academic_year',
                'rps.academic_semester',
                'rps.status',
                'courses.name as course_name',
                'courses.system_code',
                'courses.official_code',
            ]);

        return Inertia::render('dashboard', [
            'stats' => [
                'rps' => (clone $rps)->count(),
                'draft' => (clone $rps)->where('status', 'draft')->count(),
                'valid_obe' => (clone $rps)->where('status', 'obe_valid')->count(),
                'curriculum_year' => DB::table('curriculums')->where('status', 'active')->max('year'),
            ],
            'recentRps' => $recent,
        ]);
    }
}
