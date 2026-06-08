import { NodeSDK, logs } from '@opentelemetry/sdk-node';
import { getNodeAutoInstrumentations } from '@opentelemetry/auto-instrumentations-node';
import { OTLPTraceExporter } from '@opentelemetry/exporter-trace-otlp-grpc';
import { OTLPMetricExporter } from '@opentelemetry/exporter-metrics-otlp-grpc';
import { OTLPLogExporter } from '@opentelemetry/exporter-logs-otlp-grpc';
import { PeriodicExportingMetricReader } from '@opentelemetry/sdk-metrics';

const endpoint = process.env['OTEL_EXPORTER_OTLP_ENDPOINT'] ?? 'http://localhost:4317';

export const sdk = new NodeSDK({
  traceExporter: new OTLPTraceExporter({ url: endpoint }),
  metricReader: new PeriodicExportingMetricReader({
    exporter: new OTLPMetricExporter({ url: endpoint }),
    exportIntervalMillis: 30_000,
  }),
  logRecordProcessor: new logs.BatchLogRecordProcessor(
    new OTLPLogExporter({ url: endpoint }),
  ),
  instrumentations: [
    getNodeAutoInstrumentations({
      '@opentelemetry/instrumentation-fs': { enabled: false },
    }),
  ],
});

(sdk as unknown as { start: () => Promise<void> }).start().catch((err) => { console.error('Telemetry SDK failed to start', err); });

process.on('SIGTERM', () => {
  (sdk as unknown as { shutdown: () => Promise<void> }).shutdown()
    .then(() => process.exit(0))
    .catch((err) => {
      console.error('Telemetry SDK shutdown failed', err);
      process.exit(1);
    });
});
