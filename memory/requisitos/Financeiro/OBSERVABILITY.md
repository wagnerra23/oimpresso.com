# OBSERVABILITY — Modules/Financeiro

> Declaração canônica de pontos de hook OTel (D9.a Observability v3 — 2026-05-16).
> Estratégia leve: documenta superfície de instrumentação SEM código novo. Quando SDK OTel full subir no CT 100 (`OTEL_FULL_SDK=true` — ver `config/otel.php`), services serão envolvidos via decorator/middleware sem mudar assinatura.

## Spans canônicos planejados

| Service / Método | Span name (OTel GenAI conv.) | Atributos obrigatórios | Trigger |
|---|---|---|---|
| `FluxoCaixaService::projetar()` | `financeiro.fluxo_caixa.projetar` | `business_id`, `dias`, `count.titulos_futuros`, `count.eventos`, `duration_ms` | Cada call (Inbox / Dashboard) |
| `TituloService::*` mutação | `financeiro.titulo.<action>` | `business_id`, `titulo_id`, `valor_centavos`, `status_anterior`, `status_novo` | Cada UPDATE |
| `TituloAutoService::gerarBoleto()` | `financeiro.boleto.gerar` | `business_id`, `provedor` (asaas/inter), `valor_centavos`, `http.status` | Cada call API externa |
| `UnificadoService::montarVisao()` | `financeiro.unificado.montar` | `business_id`, `range.inicio`, `range.fim`, `count.linhas` | Endpoint Inertia |

## Princípios Tier 0

- **Zero-cost quando driver=null** — wrapper checa `config('otel.enabled')` ANTES de criar span (curto-circuito)
- **business_id SEMPRE atributo** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) — qualquer span sem business_id é bug
- **PII redacted** — nomes/CPFs nunca em raw; usar hash sha256 quando preciso correlacionar
- **Performance** — span overhead alvo <1ms p99; spans síncronos OK, async batch exporter no SDK full

## Padrão de hook (quando ativar)

```php
use Modules\Jana\Services\Memoria\Telemetry\RetrievalSpan; // POPO leve já existente

$span = new RetrievalSpan('financeiro.fluxo_caixa.projetar', null, [
    'business_id' => $businessId,
    'dias' => $dias,
]);
try {
    $resultado = /* lógica existente intocada */;
    $span->setAttribute('count.eventos', $contadorEventos);
    $span->setStatus('ok');
    return $resultado;
} finally {
    $span->end();
    // LangfuseClient::recordSpan($span); // só quando config('otel.enabled') = true
}
```

## Refs
- [config/otel.php](../../../config/otel.php) — lightweight bridge fase atual
- [Modules/Jana/Services/Memoria/Telemetry/RetrievalSpan.php](../../../Modules/Jana/Services/Memoria/Telemetry/RetrievalSpan.php) — POPO reusável
- ADR canon: 0053 (MCP server), 0093 (multi-tenant Tier 0)
