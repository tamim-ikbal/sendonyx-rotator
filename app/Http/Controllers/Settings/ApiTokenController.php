<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ApiTokenStoreRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The one piece of UI this application owns for the rotator dashboard.
 *
 * The dashboard is a separate application that authenticates against the API
 * with a Sanctum token; this page is where a user issues and revokes one.
 */
class ApiTokenController extends Controller
{
    /**
     * Show the user's API tokens.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/tokens', [
            'tokens' => $request->user()->tokens()
                ->latest('id')
                ->get()
                ->map(fn (PersonalAccessToken $token): array => [
                    'id' => $token->id,
                    'name' => $token->name,
                    'last_used_at' => $token->last_used_at?->toIso8601String(),
                    'created_at' => $token->created_at?->toIso8601String(),
                ]),
            // Flashed by store(), and readable only on the redirect that
            // follows it. Sanctum keeps a hash, so this is the one moment the
            // plain text value exists anywhere it can still be shown.
            'createdToken' => $request->session()->get('createdToken'),
        ]);
    }

    /**
     * Issue a new API token.
     */
    public function store(ApiTokenStoreRequest $request): RedirectResponse
    {
        $token = $request->user()->createToken($request->string('name')->value());

        $request->session()->flash('createdToken', $token->plainTextToken);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('API token created.')]);

        return to_route('api-tokens.edit');
    }

    /**
     * Revoke an API token.
     *
     * The lookup starts from the user's own tokens, so another account's token
     * id is a 404 rather than a deletion.
     */
    public function destroy(Request $request, string $token): RedirectResponse
    {
        $request->user()->tokens()->findOrFail($token)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('API token revoked.')]);

        return to_route('api-tokens.edit');
    }
}
