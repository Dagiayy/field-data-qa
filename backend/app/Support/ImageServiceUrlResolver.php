<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Resolves a Media/OutletBaseline `file_path`-style value (a local storage
 * disk path, or already a full URL) into a URL the image-service container
 * can actually fetch over HTTP. This is server-to-server traffic (backend
 * container -> image-service container), not browser-facing — if the
 * storage disk resolves to http://localhost/... (the host's own view of
 * itself), the image-service container needs host.docker.internal instead,
 * the same fix already used by ImageTestController for its manual test
 * bench. QaPresenter's browser-facing resolveUrl() deliberately does NOT do
 * this substitution, since the browser really is on localhost.
 */
class ImageServiceUrlResolver
{
    public static function resolve(?string $ref): ?string
    {
        if ($ref === null) {
            return null;
        }

        $url = Str::startsWith($ref, ['http://', 'https://'])
            ? $ref
            : Storage::disk('public')->url($ref);

        if (Str::startsWith($url, 'http://localhost')) {
            $url = str_replace('http://localhost', 'http://host.docker.internal', $url);
        }

        return $url;
    }
}
