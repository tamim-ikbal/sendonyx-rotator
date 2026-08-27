<?php

namespace App\Http\Controllers\Docs;

use App\Http\Controllers\Controller;
use App\Support\ApiDocs\ApiEndpoint;
use App\Support\ApiDocs\ApiReference;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The API reference, with a console for calling it.
 *
 * The page never sees a token: Sanctum stores only a hash, so the reader pastes
 * one in and it stays in the browser tab. The console sends the request from
 * the reader's own browser rather than proxying it through the server, which is
 * what makes the curl snippet beside it an honest description of the call.
 */
final class ApiDocsController extends Controller
{
    /**
     * Show the API reference.
     */
    public function __invoke(ApiReference $reference): Response
    {
        return Inertia::render('docs/index', [
            // The host the reader is actually on, so a copied snippet works
            // against this environment rather than against production.
            'baseUrl' => rtrim(url('/'), '/'),
            'groups' => array_map(
                fn (array $group): array => [
                    'name' => $group['name'],
                    'description' => $group['description'],
                    'endpoints' => array_map(
                        static fn (ApiEndpoint $endpoint): array => $endpoint->toArray(),
                        $group['endpoints'],
                    ),
                ],
                $reference->groups(),
            ),
        ]);
    }
}
