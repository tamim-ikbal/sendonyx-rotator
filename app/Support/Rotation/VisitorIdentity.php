<?php

namespace App\Support\Rotation;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie as CookieFactory;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The pseudonymous identity a click is attributed to.
 *
 * Both values this produces are keyed HMACs rather than the underlying request
 * data: the raw IP address is never written to the database or to a queue
 * payload, and the visitor id is opaque to anyone without the application key.
 */
final readonly class VisitorIdentity
{
    /**
     * The length, in hex characters, of a visitor id.
     */
    private const LENGTH = 32;

    public function __construct(
        private string $key,
        private string $cookieName,
        private int $cookieDays,
    ) {}

    /**
     * Resolve the visitor id for a request.
     *
     * A returning visitor is recognised by their cookie. Everyone else gets a
     * value derived from their IP, user agent and the current date, which keeps
     * a cookie blocking visitor stable for the length of a day without ever
     * being stable enough to track them across days. There is no third case:
     * from the server a blocked cookie and a first visit look identical.
     */
    public function resolve(Request $request): string
    {
        $cookie = $request->cookie($this->cookieName);

        if (is_string($cookie) && $this->isWellFormed($cookie)) {
            return $cookie;
        }

        return $this->fingerprint($request);
    }

    /**
     * Hash the request's IP address for storage.
     */
    public function hashIp(Request $request): string
    {
        return hash_hmac('sha256', (string) $request->ip(), $this->key);
    }

    /**
     * Build the cookie that pins the visitor id for future visits.
     */
    public function cookie(string $visitorId): Cookie
    {
        return CookieFactory::make(
            $this->cookieName,
            $visitorId,
            $this->cookieDays * 24 * 60,
            httpOnly: true,
        );
    }

    /**
     * Derive a stable, per day visitor id from the request itself.
     */
    private function fingerprint(Request $request): string
    {
        $material = implode('|', [
            (string) $request->ip(),
            (string) $request->userAgent(),
            now()->toDateString(),
        ]);

        return substr(hash_hmac('sha256', $material, $this->key), 0, self::LENGTH);
    }

    /**
     * Determine whether a cookie value is one this application issued.
     *
     * The cookie is not encrypted, so a visitor can put anything in it. The
     * column is a varchar(100) and the value reaches a GROUP BY in the
     * statistics queries, so it is checked before it is trusted.
     */
    private function isWellFormed(string $value): bool
    {
        return strlen($value) === self::LENGTH && ctype_xdigit($value);
    }
}
