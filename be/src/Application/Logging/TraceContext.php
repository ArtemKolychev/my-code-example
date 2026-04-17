<?php

declare(strict_types=1);

namespace App\Application\Logging;

final class TraceContext
{
    private static ?string $traceId = null;
    private static ?string $jobId = null;

    public static function setTraceId(string $traceId): void
    {
        self::$traceId = $traceId;
    }

    public static function setJobId(string $jobId): void
    {
        self::$jobId = $jobId;
    }

    public static function getTraceId(): ?string
    {
        return self::$traceId;
    }

    public static function getJobId(): ?string
    {
        return self::$jobId;
    }

    public static function clear(): void
    {
        self::$traceId = null;
        self::$jobId = null;
    }

    public static function generateTraceId(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0x0FFF) | 0x4000,
            mt_rand(0, 0x3FFF) | 0x8000,
            mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF), mt_rand(0, 0xFFFF),
        );
    }
}
