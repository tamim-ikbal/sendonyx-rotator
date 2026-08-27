<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('api-tokens.edit'))->assertRedirect(route('login'));
});

test('lists the tokens belonging to the user', function () {
    $user = User::factory()->create();
    $user->createToken('Onyx dashboard');
    User::factory()->create()->createToken('Someone else');

    $this->actingAs($user)
        ->get(route('api-tokens.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/tokens')
            ->has('tokens', 1)
            ->where('tokens.0.name', 'Onyx dashboard')
            ->where('tokens.0.last_used_at', null)
            ->where('createdToken', null),
        );
});

test('creates a token and hands back the plain text value once', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from(route('api-tokens.edit'))
        ->post(route('api-tokens.store'), ['name' => 'Onyx dashboard']);

    $response->assertSessionHasNoErrors()->assertRedirect(route('api-tokens.edit'));
    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'name' => 'Onyx dashboard',
    ]);

    $plainTextToken = session('createdToken');

    expect($plainTextToken)->toBeString()
        ->and($user->tokens()->sole()->token)->toBe(hash('sha256', explode('|', $plainTextToken)[1]));
});

test('shows the plain text token on the redirect and never again', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('api-tokens.store'), ['name' => 'Onyx dashboard']);

    $this->get(route('api-tokens.edit'))
        ->assertInertia(fn (Assert $page) => $page->whereNot('createdToken', null));

    $this->get(route('api-tokens.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('createdToken', null));
});

test('rejects a token with no name', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from(route('api-tokens.edit'))
        ->post(route('api-tokens.store'), []);

    $response->assertSessionHasErrors('name');
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('revokes a token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Onyx dashboard')->accessToken;

    $response = $this->actingAs($user)
        ->from(route('api-tokens.edit'))
        ->delete(route('api-tokens.destroy', $token->id));

    $response->assertRedirect(route('api-tokens.edit'));
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('returns 404 revoking another user token', function () {
    $token = User::factory()->create()->createToken('Someone else')->accessToken;

    $response = $this->actingAs(User::factory()->create())
        ->delete(route('api-tokens.destroy', $token->id));

    $response->assertNotFound();
    $this->assertDatabaseCount('personal_access_tokens', 1);
});

test('stops authenticating the API once the token is revoked', function () {
    $user = User::factory()->create();
    $newToken = $user->createToken('Onyx dashboard');
    $this->withToken($newToken->plainTextToken)->getJson(route('rotators.index'))->assertOk();

    $this->actingAs($user)->delete(route('api-tokens.destroy', $newToken->accessToken->id));

    // Sanctum accepts the web guard as well as a bearer token, so the session
    // left behind by actingAs would authenticate the next request on its own
    // and hide whether the token still works.
    $this->flushSession();
    Auth::forgetGuards();

    $this->withToken($newToken->plainTextToken)->getJson(route('rotators.index'))->assertUnauthorized();
});
