<?php

use App\Enums\DeviceType;
use App\Support\UserAgent\DeviceTypeResolver;

test('classifies a user agent into the reported device buckets', function (?string $userAgent, DeviceType $expected) {
    $resolver = new DeviceTypeResolver;

    expect($resolver->resolve($userAgent))->toBe($expected);
})->with([
    'desktop browser' => [
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        DeviceType::DESKTOP,
    ],
    'phone' => [
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        DeviceType::MOBILE,
    ],
    'tablet' => [
        'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        DeviceType::TABLET,
    ],
    'crawler' => [
        'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        DeviceType::BOT,
    ],
    'another crawler' => [
        'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
        DeviceType::BOT,
    ],
    'absent user agent' => [null, DeviceType::DESKTOP],
    'empty user agent' => ['', DeviceType::DESKTOP],
]);
