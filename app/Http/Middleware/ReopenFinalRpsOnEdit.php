<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ReopenFinalRpsOnEdit
{
    private const NON_EDIT_ROUTES = [
        'rps.validate-obe',
        'rps.finalize',
        'rps.reopen',
        'rps.ai.generate',
        'rps.ai.reject',
        'rps.simulation.update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $routeName = (string) ($request->route()?->getName() ?? '');
        if (in_array($routeName, self::NON_EDIT_ROUTES, true)) {
            return $next($request);
        }

        $rpsId = $request->route('rps');
        if (! is_string($rpsId) || $rpsId === '') {
            return $next($request);
        }

        $record = DB::table('rps')
            ->where('id', $rpsId)
            ->first(['id', 'status', 'current_version_id']);

        if (! $record || strtolower((string) $record->status) !== 'final') {
            return $next($request);
        }

        DB::transaction(function () use ($record): void {
            DB::table('rps')
                ->where('id', $record->id)
                ->update([
                    'status' => 'draft',
                    'updated_at' => now(),
                ]);

            if (filled($record->current_version_id ?? null)) {
                DB::table('rps_versions')
                    ->where('id', $record->current_version_id)
                    ->update([
                        'status' => 'draft',
                        'finalized_at' => null,
                        'updated_at' => now(),
                    ]);
            }
        });

        return $next($request);
    }
}
