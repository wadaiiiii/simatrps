<?php

use App\Models\User;

const SSO_SIPANDU_VERCEL_CALLBACK = 'https://sipandumath.vercel.app/sso/callback';
const SSO_SIPANDU_CAMPUS_CALLBACK = 'https://matematika.unsulbar.ac.id/akademik/sipandu/sso/callback';

beforeEach(function () {
    config()->set('services.sso.clients.sipandu', [
        'redirect_uris' => [
            SSO_SIPANDU_VERCEL_CALLBACK,
            SSO_SIPANDU_CAMPUS_CALLBACK,
        ],
    ]);
});

function ssoAuthorizeQuery(string $redirectUri, string $verifier = ''): array
{
    $verifier = $verifier !== '' ? $verifier : str_repeat('v', 64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

    return [
        'client_id' => 'sipandu',
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
        'state' => 'state-regression-test',
    ];
}

test('callback Vercel SiPANDU tetap diterima selama masa migrasi', function () {
    $user = User::factory()->create([
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('sso.authorize', ssoAuthorizeQuery(SSO_SIPANDU_VERCEL_CALLBACK)));

    $response->assertRedirect();
    expect((string) $response->headers->get('Location'))
        ->toStartWith(SSO_SIPANDU_VERCEL_CALLBACK.'?')
        ->toContain('code=')
        ->toContain('state=state-regression-test');
});

test('callback UNSULBAR SiPANDU diterima dan dapat menukar kode PKCE', function () {
    $user = User::factory()->create([
        'is_active' => true,
        'email_verified_at' => now(),
    ]);
    $verifier = str_repeat('c', 64);

    $authorize = $this->actingAs($user)->get(route('sso.authorize', ssoAuthorizeQuery(SSO_SIPANDU_CAMPUS_CALLBACK, $verifier)));
    $authorize->assertRedirect();

    $location = (string) $authorize->headers->get('Location');
    expect($location)->toStartWith(SSO_SIPANDU_CAMPUS_CALLBACK.'?');

    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    expect($query['code'] ?? null)->not->toBeNull();

    $token = $this->postJson(route('sso.token'), [
        'grant_type' => 'authorization_code',
        'client_id' => 'sipandu',
        'redirect_uri' => SSO_SIPANDU_CAMPUS_CALLBACK,
        'code' => $query['code'],
        'code_verifier' => $verifier,
    ]);

    $token->assertOk()
        ->assertJsonPath('token_type', 'sso_identity')
        ->assertJsonPath('user.email', strtolower($user->email))
        ->assertJsonPath('user.is_active', true);
});

test('callback yang tidak ada dalam allowlist ditolak', function () {
    $user = User::factory()->create([
        'is_active' => true,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('sso.authorize', ssoAuthorizeQuery('https://attacker.example/sso/callback')))
        ->assertStatus(400);
});
