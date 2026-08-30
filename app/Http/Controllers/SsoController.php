<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SsoController extends Controller
{
    public function authorize(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'string', 'max:80'],
            'redirect_uri' => ['required', 'url', 'max:2048'],
            'response_type' => ['required', 'in:code'],
            'code_challenge' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{43,128}$/'],
            'code_challenge_method' => ['required', 'in:S256'],
            'state' => ['required', 'string', 'max:180'],
        ]);

        $client = config('services.sso.clients.'.$validated['client_id']);

        abort_unless(
            is_array($client)
            && isset($client['redirect_uri'])
            && hash_equals((string) $client['redirect_uri'], (string) $validated['redirect_uri']),
            400,
            'SSO client atau redirect URI tidak valid.',
        );

        $user = $request->user();
        abort_unless($user instanceof User && (bool) $user->is_active, 403, 'Akun tidak aktif.');

        $code = Str::random(64);
        $cacheKey = 'sso:authorization-code:'.hash('sha256', $code);

        Cache::put($cacheKey, [
            'user_id' => $user->id,
            'client_id' => $validated['client_id'],
            'redirect_uri' => $validated['redirect_uri'],
            'code_challenge' => $validated['code_challenge'],
            'issued_at' => now()->timestamp,
        ], now()->addSeconds(90));

        $query = http_build_query([
            'code' => $code,
            'state' => $validated['state'],
        ], '', '&', PHP_QUERY_RFC3986);

        return redirect()->away($validated['redirect_uri'].'?'.$query);
    }

    public function token(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'grant_type' => ['required', 'in:authorization_code'],
            'client_id' => ['required', 'string', 'max:80'],
            'redirect_uri' => ['required', 'url', 'max:2048'],
            'code' => ['required', 'string', 'size:64'],
            'code_verifier' => ['required', 'string', 'regex:/^[A-Za-z0-9.\-_~]{43,128}$/'],
        ]);

        $cacheKey = 'sso:authorization-code:'.hash('sha256', $validated['code']);
        $authorization = Cache::get($cacheKey);

        if (! is_array($authorization)) {
            return response()->json([
                'message' => 'Kode otorisasi tidak valid atau sudah kedaluwarsa.',
            ], 400);
        }

        if (
            ! hash_equals((string) $authorization['client_id'], (string) $validated['client_id'])
            || ! hash_equals((string) $authorization['redirect_uri'], (string) $validated['redirect_uri'])
        ) {
            return response()->json(['message' => 'SSO client tidak sesuai.'], 400);
        }

        $challenge = rtrim(strtr(base64_encode(hash('sha256', $validated['code_verifier'], true)), '+/', '-_'), '=');

        if (! hash_equals((string) $authorization['code_challenge'], $challenge)) {
            return response()->json(['message' => 'PKCE verifier tidak valid.'], 400);
        }

        $user = User::query()->find($authorization['user_id']);

        if (! $user || ! (bool) $user->is_active) {
            Cache::forget($cacheKey);

            return response()->json(['message' => 'Akun tidak aktif atau tidak ditemukan.'], 403);
        }

        Cache::forget($cacheKey);

        return response()->json([
            'token_type' => 'sso_identity',
            'expires_in' => 0,
            'issuer' => config('services.sso.issuer'),
            'user' => [
                'sub' => 'simatrps:'.$user->id,
                'name' => $user->name,
                'email' => strtolower($user->email),
                'role' => $user->role,
                'identity_number' => $user->nidn,
                'email_verified' => $user->email_verified_at !== null,
                'is_active' => (bool) $user->is_active,
            ],
        ]);
    }
}
