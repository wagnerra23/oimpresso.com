<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * OtelServiceProvider — boot do OTel SDK (Wave 26).
 *
 * Inicializa TracerProvider global apenas quando:
 *   1. config('otel.enabled') = true (default false)
 *   2. config('otel.sdk_disabled') = false (kill-switch emergencial)
 *   3. Classes OpenTelemetry presentes (composer require open-telemetry/sdk)
 *
 * Em caso de qualquer falha (collector down, lib missing, etc.), faz fallback
 * silencioso pra zero-cost path — NUNCA quebra request real (Princípio 8
 * Constituição v2: confiabilidade com fallback).
 *
 * Multi-tenant Tier 0 ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * NUNCA colocar PII (CPF/CNPJ/email cliente) em ResourceAttributes globais.
 * `business_id` por span via OtelHelper::spanBiz().
 *
 * @see ADR 0162 (proposta — Wave 26 OTel Collector CT 100)
 * @see app/Util/OtelHelper.php
 * @see config/otel.php
 */
class OtelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Gate 1: feature flag default false (Wagner ativa via env quando CT 100 collector pronto).
        if (! config('otel.enabled', false)) {
            return;
        }

        // Gate 2: kill-switch emergencial (OTEL_SDK_DISABLED=true desliga sem mexer enabled).
        if (config('otel.sdk_disabled', false)) {
            return;
        }

        // Gate 3: classes SDK presentes (composer install ainda não rodado em todo ambiente).
        if (! class_exists(\OpenTelemetry\API\Globals::class)) {
            return;
        }

        // Fallback silencioso em qualquer erro (Princípio 8 — confiabilidade com fallback).
        try {
            $this->bootTracerProvider();
        } catch (\Throwable $e) {
            // Log estruturado mas NUNCA throw — request real prossegue.
            \Log::warning('[OTEL] boot falhou, seguindo em zero-cost path', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);
        }
    }

    private function bootTracerProvider(): void
    {
        $tracerProvider = $this->buildTracerProvider();

        // `registerInitializer` mudou de assinatura: recebe um Configurator e
        // devolve o Configurator com o TracerProvider setado (não mais `fn () => $tp`).
        // É ISTO que liga o tracer GLOBAL que o OtelHelper::span consome — o boot
        // antigo registrava um closure inválido e o global nunca era configurado.
        \OpenTelemetry\API\Globals::registerInitializer(
            fn (\OpenTelemetry\API\Instrumentation\Configurator $configurator) => $configurator->withTracerProvider($tracerProvider)
        );
    }

    /**
     * Constrói o TracerProvider SDK (resource + processor + sampler + exporter).
     *
     * Exporter injetável (default OTLP HTTP pro endpoint configurado) pra que o
     * Pest possa provar o pipeline com InMemoryExporter sem rede.
     *
     * Compat de API do SDK (2026-06-01 T1.b — o boot quebrava silenciosamente):
     *   - `ResourceAttributes::DEPLOYMENT_ENVIRONMENT` foi REMOVIDA no sem-conv
     *     >=1.27 (renomeada p/ deployment.environment.name). Usar a chave literal
     *     estável `deployment.environment` evita o "Undefined constant".
     *   - `BatchSpanProcessor::__construct` passou a exigir um clock (2º arg).
     *     `::builder($exporter)->build()` encapsula o default — version-stable.
     */
    public function buildTracerProvider(
        ?\OpenTelemetry\SDK\Trace\SpanExporterInterface $exporter = null
    ): \OpenTelemetry\SDK\Trace\TracerProviderInterface {
        // Endpoint compat: nova chave `otel.endpoint` + legacy US-WA-083 `otel.exporter.endpoint`.
        if ($exporter === null) {
            $endpoint = (string) (config('otel.endpoint')
                ?? config('otel.exporter.endpoint', 'http://mcp.oimpresso.com:4318/v1/traces'));

            $transport = (new \OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory())
                ->create($endpoint, 'application/x-protobuf');

            $exporter = new \OpenTelemetry\Contrib\Otlp\SpanExporter($transport);
        }

        // Sample rate compat: novo `otel.sample_rate` + legacy `otel.sampling.local_sampler_ratio`.
        $sampleRate = (float) (config('otel.sample_rate')
            ?? config('otel.sampling.local_sampler_ratio', 0.05));

        // Service name compat: novo `otel.service_name` + legacy `otel.service.name`.
        $serviceName = (string) (config('otel.service_name')
            ?? config('otel.service.name', 'oimpresso'));

        $resource = \OpenTelemetry\SDK\Resource\ResourceInfo::create(
            \OpenTelemetry\SDK\Common\Attribute\Attributes::create([
                \OpenTelemetry\SemConv\ResourceAttributes::SERVICE_NAME => $serviceName,
                \OpenTelemetry\SemConv\ResourceAttributes::SERVICE_VERSION => (string) (config('otel.service.version') ?? '1.0.0'),
                'deployment.environment' => (string) config('app.env', 'production'),
                'oimpresso.runtime' => $this->detectRuntime(),
            ])
        );

        return \OpenTelemetry\SDK\Trace\TracerProvider::builder()
            ->addSpanProcessor(
                \OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor::builder($exporter)->build()
            )
            ->setSampler(
                new \OpenTelemetry\SDK\Trace\Sampler\TraceIdRatioBasedSampler($sampleRate)
            )
            ->setResource($resource)
            ->build();
    }

    /**
     * Detecta runtime (CT 100 Proxmox vs Hostinger shared) via marker file.
     *
     * Marker `/etc/oimpresso-ct100-marker` é criado no deploy CT 100 (ADR 0062).
     * Hostinger nunca tem esse marker — separação runtime Tier 0 IRREVOGÁVEL.
     */
    private function detectRuntime(): string
    {
        if (@file_exists('/etc/oimpresso-ct100-marker')) {
            return 'ct100';
        }

        return 'hostinger';
    }
}
