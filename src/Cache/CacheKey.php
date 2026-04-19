<?php

namespace App\Cache;

final class CacheKey
{
    public const TRANSFER_STATUS = 'transfer_status_%s';
    public const TRANSFER_LOCK = 'transfer_lock_%s';

    public static function transferStatus(string $referenceId): string
    {
        return sprintf(self::TRANSFER_STATUS, self::normalize($referenceId));
    }

    public static function transferLock(string $referenceId, ?int $fromId = null, ?int $toId = null): string
    {
        // normalize inputs
        $ref = self::normalize($referenceId);
        $from = $fromId !== null ? (string)$fromId : '';
        $to = $toId !== null ? (string)$toId : '';

        // include ids if provided, then hash whole payload to keep key length bounded
        $raw = $ref . '|' . $from . '|' . $to;
        return sprintf(self::TRANSFER_LOCK, sha1($raw));
    }

    private static function normalize(string $s): string
    {
        return strtolower(trim($s));
    }
}