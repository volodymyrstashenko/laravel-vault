<?php

namespace Thevps\Vault\Support;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A host that stores media on a private (non public-read) `s3` disk gets a 403 from a plain
 * `Media::getUrl()`/`getFirstMediaUrl()` — this app's own `App\Support\MediaLibrary\MediaUrl`
 * works around that with a signed, temporary URL for the `s3` disk, falling back to the plain
 * URL everywhere else (local `public` disk, tests, etc.). Mirrored here so the package doesn't
 * depend on the host's class (a package must not reference `App\*`) while still working
 * correctly for hosts whose bucket isn't public-read — found live (2026-09-13): a credential
 * icon (`icon_url`) 403'd once the host's default media disk became a private `s3` bucket,
 * because `Credential::iconUrl()`/`attachmentsList()` still called the plain, unsigned
 * accessors. See CLAUDE.md § "S3 bucket private" for the same fix already applied host-side.
 */
class MediaUrl
{
    private const DEFAULT_MINUTES = 60;

    public static function for(?Media $media, int $minutes = self::DEFAULT_MINUTES): ?string
    {
        if (! $media) {
            return null;
        }

        if ($media->disk !== 's3') {
            return $media->getUrl();
        }

        try {
            return $media->getTemporaryUrl(now()->addMinutes($minutes));
        } catch (\Throwable) {
            return $media->getUrl();
        }
    }
}
