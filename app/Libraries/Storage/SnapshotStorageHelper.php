<?php

namespace App\Libraries\Storage;

class SnapshotStorageHelper
{
    const COMPRESSED_PREFIX = '__GZ64__:';

    /**
     * Compress raw JSON string using gzip and base64 encoding.
     */
    public static function compress(string $rawJson): string
    {
        if (empty($rawJson) || strlen($rawJson) < 256) {
            return $rawJson;
        }

        if (function_exists('gzencode')) {
            $compressed = gzencode($rawJson, 9);
            if ($compressed !== false) {
                return self::COMPRESSED_PREFIX . base64_encode($compressed);
            }
        }

        return $rawJson;
    }

    /**
     * Decompress string if it was compressed, or return as-is for legacy plain JSON.
     */
    public static function decompress(?string $content): string
    {
        if (empty($content)) {
            return '';
        }

        if (str_starts_with($content, self::COMPRESSED_PREFIX)) {
            $b64 = substr($content, strlen(self::COMPRESSED_PREFIX));
            $decoded = base64_decode($b64);
            if ($decoded !== false && function_exists('gzdecode')) {
                $unzipped = @gzdecode($decoded);
                if ($unzipped !== false) {
                    return $unzipped;
                }
            }
        }

        // Return original content if not compressed or fallback
        return $content;
    }

    /**
     * Check if a content string is compressed with the storage prefix.
     */
    public static function isCompressed(?string $content): bool
    {
        return !empty($content) && str_starts_with($content, self::COMPRESSED_PREFIX);
    }
}
