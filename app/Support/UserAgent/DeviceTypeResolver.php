<?php

namespace App\Support\UserAgent;

use App\Enums\DeviceType;
use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\Device\AbstractDeviceParser;

/**
 * Classifies a user agent into the four device buckets the dashboard reports.
 *
 * Bots are classified rather than dropped: they are still rotated and still
 * logged, and the statistics exclude them with a query filter, so a bot hit
 * stays visible as leaked traffic instead of vanishing from the record.
 */
final class DeviceTypeResolver
{
    /**
     * Resolve the device type behind a user agent string.
     *
     * Anything the detector cannot place, an empty user agent included, is
     * reported as desktop rather than left unresolved. A null device type means
     * "not classified yet" to the statistics layer, and a click written by the
     * job has been classified.
     */
    public function resolve(?string $userAgent): DeviceType
    {
        $detector = new DeviceDetector($userAgent ?? '');
        $detector->parse();

        if ($detector->isBot()) {
            return DeviceType::BOT;
        }

        return match ($detector->getDevice()) {
            AbstractDeviceParser::DEVICE_TYPE_SMARTPHONE,
            AbstractDeviceParser::DEVICE_TYPE_PHABLET,
            AbstractDeviceParser::DEVICE_TYPE_FEATURE_PHONE => DeviceType::MOBILE,
            AbstractDeviceParser::DEVICE_TYPE_TABLET => DeviceType::TABLET,
            default => DeviceType::DESKTOP,
        };
    }
}
