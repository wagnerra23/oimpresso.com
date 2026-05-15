import { diag, DiagConsoleLogger, DiagLogLevel } from '@opentelemetry/api';
import { OTLPTraceExporter } from '@opentelemetry/exporter-trace-otlp-http';
import { OTLPMetricExporter } from '@opentelemetry/exporter-metrics-otlp-http';
import { Resource } from '@opentelemetry/resources';
import { PeriodicExportingMetricReader } from '@opentelemetry/sdk-metrics';
import { NodeSDK } from '@opentelemetry/sdk-node';
import { getNodeAutoInstrumentations } from '@opentelemetry/auto-instrumentations-node';
import { ATTR_SERVICE_NAME, ATTR_SERVICE_VERSION } from '@opentelemetry/semantic-conventions';
import type { Env } from '../config/env.js';

let sdk: NodeSDK | undefined;

export function startOtel(env: Env): void {
  if (!env.OTEL_ENABLED || !env.OTEL_EXPORTER_OTLP_ENDPOINT) {
    return;
  }

  if (env.LOG_LEVEL === 'debug' || env.LOG_LEVEL === 'trace') {
    diag.setLogger(new DiagConsoleLogger(), DiagLogLevel.WARN);
  }

  const resource = new Resource({
    [ATTR_SERVICE_NAME]: env.OTEL_SERVICE_NAME,
    [ATTR_SERVICE_VERSION]: process.env.npm_package_version ?? '0.0.0',
    'deployment.environment': env.NODE_ENV,
  });

  const traceExporter = new OTLPTraceExporter({
    url: `${env.OTEL_EXPORTER_OTLP_ENDPOINT}/v1/traces`,
  });

  const metricReader = new PeriodicExportingMetricReader({
    exporter: new OTLPMetricExporter({
      url: `${env.OTEL_EXPORTER_OTLP_ENDPOINT}/v1/metrics`,
    }),
    exportIntervalMillis: 30_000,
  });

  sdk = new NodeSDK({
    resource,
    traceExporter,
    metricReader,
    instrumentations: [
      getNodeAutoInstrumentations({
        '@opentelemetry/instrumentation-fs': { enabled: false },
        '@opentelemetry/instrumentation-net': { enabled: false },
      }),
    ],
  });

  sdk.start();
}

export async function shutdownOtel(): Promise<void> {
  if (!sdk) return;
  try {
    await sdk.shutdown();
  } catch {
    // já encerrado ou exporter offline — segue
  }
}
