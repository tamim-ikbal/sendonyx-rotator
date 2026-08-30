<?php

use App\Enums\DeviceType;
use App\Jobs\RecordRotatorClick;
use App\Models\TrafficRotator;
use App\Models\TrafficRotatorClick;
use App\Models\TrafficRotatorDestination;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

/**
 * Hit the rotator once, optionally as a specific visitor.
 */
function hitRotator(array $headers = [], array $cookies = []): TestResponse
{
    return test()->withUnencryptedCookies($cookies)->get(route('rotator.redirect'), $headers);
}

/**
 * Hit the rotator a number of times and return the destination ids logged, in order.
 *
 * @return array<int, int|null>
 */
function rotatorSequence(int $hits): array
{
    for ($hit = 0; $hit < $hits; $hit++) {
        hitRotator()->assertRedirect();
    }

    return TrafficRotatorClick::query()->orderBy('id')->pluck('destination_id')->all();
}

test('redirects to the only active destination', function () {
    $rotator = TrafficRotator::factory()->create();
    $destination = TrafficRotatorDestination::factory()->forRotator($rotator)
        ->create(['url' => 'https://offers.example.com/one']);

    $response = hitRotator();

    $response->assertFound()->assertRedirect('https://offers.example.com/one');
    expect(TrafficRotatorClick::query()->sole()->destination_id)->toBe($destination->id);
});

test('distributes hits by weight in the smooth round robin order', function () {
    $rotator = TrafficRotator::factory()->create();
    $heavy = TrafficRotatorDestination::factory()->forRotator($rotator)->weight(3)->create();
    $first = TrafficRotatorDestination::factory()->forRotator($rotator)->weight(1)->create();
    $second = TrafficRotatorDestination::factory()->forRotator($rotator)->weight(1)->create();

    $sequence = rotatorSequence(5);

    expect($sequence)->toBe([$heavy->id, $first->id, $heavy->id, $second->id, $heavy->id]);
});

test('repeats the same cycle once the weights are exhausted', function () {
    $rotator = TrafficRotator::factory()->create();
    $heavy = TrafficRotatorDestination::factory()->forRotator($rotator)->weight(3)->create();
    $first = TrafficRotatorDestination::factory()->forRotator($rotator)->weight(1)->create();
    $second = TrafficRotatorDestination::factory()->forRotator($rotator)->weight(1)->create();

    $cycle = [$heavy->id, $first->id, $heavy->id, $second->id, $heavy->id];

    expect(rotatorSequence(10))->toBe([...$cycle, ...$cycle]);
});

test('never sends a visitor to a paused destination', function () {
    $rotator = TrafficRotator::factory()->create();
    $active = TrafficRotatorDestination::factory()->forRotator($rotator)->create();
    TrafficRotatorDestination::factory()->forRotator($rotator)->paused()->create();

    expect(rotatorSequence(4))->toBe(array_fill(0, 4, $active->id));
});

test('rotates a destination added after the first visit', function () {
    $rotator = TrafficRotator::factory()->create();
    $first = TrafficRotatorDestination::factory()->forRotator($rotator)->create();

    hitRotator()->assertRedirect($first->url);

    $second = TrafficRotatorDestination::factory()->forRotator($rotator)->create();

    expect(rotatorSequence(2))->toBe([$first->id, $first->id, $second->id]);
});

test('restarts the rotation cycle when a weight changes', function () {
    $rotator = TrafficRotator::factory()->create();
    $heavy = TrafficRotatorDestination::factory()->forRotator($rotator)->weight(3)->create();
    $light = TrafficRotatorDestination::factory()->forRotator($rotator)->weight(1)->create();

    hitRotator();
    hitRotator();

    $heavy->update(['weight' => 1]);

    expect(rotatorSequence(2))->toBe([$heavy->id, $heavy->id, $heavy->id, $light->id]);
});

test('falls back to the default destination url when nothing is active', function () {
    TrafficRotator::factory()->withDefaultUrl('https://sendonyx.com/fallback')->create();

    hitRotator()->assertRedirect('https://sendonyx.com/fallback');
});

test('logs a fallback hit against the rotator with no destination', function () {
    $rotator = TrafficRotator::factory()->withDefaultUrl()->create();
    TrafficRotatorDestination::factory()->forRotator($rotator)->paused()->create();

    hitRotator();

    $click = TrafficRotatorClick::query()->sole();

    expect($click->rotator_id)->toBe($rotator->id)
        ->and($click->destination_id)->toBeNull();
});

test('returns 404 when the rotator has neither a destination nor a default url', function () {
    TrafficRotator::factory()->create();

    hitRotator()->assertNotFound();

    expect(TrafficRotatorClick::query()->count())->toBe(0);
});

test('returns 404 when no rotator exists at all', function () {
    hitRotator()->assertNotFound();
});

test('forbids caching the redirect', function () {
    $rotator = TrafficRotator::factory()->create();
    TrafficRotatorDestination::factory()->forRotator($rotator)->create();

    $response = hitRotator();

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Pragma'))->toBe('no-cache');
});

test('dispatches the click to the queue rather than writing it in the request', function () {
    $rotator = TrafficRotator::factory()->create();
    $destination = TrafficRotatorDestination::factory()->forRotator($rotator)->create();

    Queue::fake([RecordRotatorClick::class]);

    hitRotator();

    Queue::assertPushed(
        RecordRotatorClick::class,
        fn (RecordRotatorClick $job): bool => $job->rotatorId === $rotator->id
            && $job->destinationId === $destination->id,
    );
});

test('issues a visitor cookie on the first hit and reuses it on the next', function () {
    $rotator = TrafficRotator::factory()->create();
    TrafficRotatorDestination::factory()->forRotator($rotator)->create();

    $response = hitRotator();

    $issued = $response->getCookie('rotator_vid', decrypt: false)?->getValue();

    expect($issued)->toBeString()->toHaveLength(32);

    hitRotator(cookies: ['rotator_vid' => $issued]);

    expect(TrafficRotatorClick::query()->pluck('visitor_id')->all())->toBe([$issued, $issued]);
});

test('ignores a visitor cookie the application did not issue', function () {
    $rotator = TrafficRotator::factory()->create();
    TrafficRotatorDestination::factory()->forRotator($rotator)->create();

    hitRotator(cookies: ['rotator_vid' => "not-hex'; DROP TABLE traffic_rotator_clicks; --"]);

    $visitorId = TrafficRotatorClick::query()->sole()->visitor_id;

    expect($visitorId)->toHaveLength(32)->toMatch('/^[0-9a-f]{32}$/');
});

test('gives two cookieless hits from one visitor the same identity for a day', function () {
    $rotator = TrafficRotator::factory()->create();
    TrafficRotatorDestination::factory()->forRotator($rotator)->create();

    $agent = ['HTTP_USER_AGENT' => 'Mozilla/5.0 (X11; Linux x86_64) Firefox/121.0'];

    $this->travelTo('2026-08-27 09:00:00');
    hitRotator($agent);

    $this->travelTo('2026-08-27 21:30:00');
    hitRotator($agent);

    $this->travelTo('2026-08-28 09:00:00');
    hitRotator($agent);

    $identities = TrafficRotatorClick::query()->orderBy('id')->pluck('visitor_id')->all();

    expect($identities[0])->toBe($identities[1])
        ->and($identities[2])->not->toBe($identities[0]);
});

test('stores the address the visitor connected from', function () {
    $rotator = TrafficRotator::factory()->create();
    TrafficRotatorDestination::factory()->forRotator($rotator)->create();

    hitRotator(['REMOTE_ADDR' => '203.0.113.9']);
    hitRotator(['REMOTE_ADDR' => '198.51.100.4']);

    $addresses = TrafficRotatorClick::query()->orderBy('id')->pluck('ip_address')->all();

    expect($addresses)->toBe(['203.0.113.9', '198.51.100.4']);
});

test('records the address a trusted cdn forwarded rather than one the visitor forged', function () {
    $rotator = TrafficRotator::factory()->create();
    TrafficRotatorDestination::factory()->forRotator($rotator)->create();

    // Cloudflare appends the connecting address to whatever the visitor sent,
    // so a forged entry arrives ahead of the real one. Trusting the edge range
    // and nothing wider is what makes the real one win: with every proxy
    // trusted there is no untrusted hop to stop at and the forged address is
    // what gets recorded. Do not relax this to '*'.
    config(['trustedproxy.proxies' => '162.158.0.0/15']);

    hitRotator([
        'REMOTE_ADDR' => '162.158.10.5',
        'X-Forwarded-For' => '1.2.3.4, 203.0.113.77',
    ]);

    expect(TrafficRotatorClick::query()->sole()->ip_address)->toBe('203.0.113.77');
});

test('records the country the cdn resolved on the edge', function () {
    $rotator = TrafficRotator::factory()->create();
    TrafficRotatorDestination::factory()->forRotator($rotator)->create();

    hitRotator(['REMOTE_ADDR' => '203.0.113.9', 'CF-IPCountry' => 'de']);

    expect(TrafficRotatorClick::query()->sole()->visitor_country)->toBe('DE');
});

test('records no country for a visitor nothing places', function () {
    $rotator = TrafficRotator::factory()->create();
    TrafficRotatorDestination::factory()->forRotator($rotator)->create();

    // A reserved range, so this stays null whether or not the machine running
    // the suite has a country database fetched.
    hitRotator(['REMOTE_ADDR' => '203.0.113.9']);

    expect(TrafficRotatorClick::query()->sole()->visitor_country)->toBeNull();
});

test('ignores a country header a visitor forged for themselves', function () {
    $rotator = TrafficRotator::factory()->create();
    TrafficRotatorDestination::factory()->forRotator($rotator)->create();

    hitRotator(['REMOTE_ADDR' => '203.0.113.9', 'CF-IPCountry' => "Germany'--"]);

    expect(TrafficRotatorClick::query()->sole()->visitor_country)->toBeNull();
});

test('rotates a bot and records it as one', function () {
    $rotator = TrafficRotator::factory()->create();
    $destination = TrafficRotatorDestination::factory()->forRotator($rotator)->create();

    hitRotator(['HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])
        ->assertRedirect($destination->url);

    $click = TrafficRotatorClick::query()->sole();

    expect($click->device_type)->toBe(DeviceType::BOT)
        ->and(TrafficRotatorClick::query()->excludingBots()->count())->toBe(0);
});

test('records the referring page', function () {
    $rotator = TrafficRotator::factory()->create();
    TrafficRotatorDestination::factory()->forRotator($rotator)->create();

    hitRotator(['HTTP_REFERER' => 'https://forum.example.com/thread/1']);

    expect(TrafficRotatorClick::query()->sole()->referrer)->toBe('https://forum.example.com/thread/1');
});
