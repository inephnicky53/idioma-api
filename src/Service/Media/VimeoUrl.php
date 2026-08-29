<?php

namespace App\Service\Media;

/**
 * Parse public Vimeo URLs into a player embed (no API token required).
 */
final class VimeoUrl
{
    public static function videoId(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        if (preg_match('#(?:player\.)?vimeo\.com/(?:video/)?(\d+)#i', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    public static function privacyHash(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        if (preg_match('#vimeo\.com/(?:video/)?\d+/([a-z0-9]+)#i', $url, $m)) {
            return $m[1];
        }

        if (preg_match('#[?&]h=([a-z0-9]+)#i', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    public static function toEmbed(?string $url): ?string
    {
        $id = self::videoId($url);
        if (!$id) {
            return null;
        }

        $embed = 'https://player.vimeo.com/video/' . $id;
        $hash = self::privacyHash($url);
        if ($hash) {
            $embed .= '?h=' . rawurlencode($hash);
        }

        return $embed;
    }
}
