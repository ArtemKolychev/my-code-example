<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use App\Application\Logging\TraceContext;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final class TraceIdProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = $record->extra;

        $traceId = TraceContext::getTraceId();
        if (null !== $traceId) {
            $extra['traceId'] = $traceId;
        }

        $jobId = TraceContext::getJobId();
        if (null !== $jobId) {
            $extra['jobId'] = $jobId;
        }

        return $record->with(extra: $extra);
    }
}
