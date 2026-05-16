# Vestuario — Observability (OTel / logs / métricas)

> Documento canônico de observabilidade do **Modules/Vestuario**. Atualizado 2026-05-16. D9 dim v3.

## Decisão arquitetural

Vestuario **NÃO instrumenta OTel próprio**. Herda spans/traces do `OtelServiceProvider` do core (registra HTTP middleware + Eloquent listeners globalmente). Justificativa:

1. **SoC brutal** (Constituição v2 §5) — telemetry central no core garante consistência cross-módulo
2. **Vertical-thin** ([SPEC](SPEC.md) Sprint 1) — única operação atual é `VestuarioSetting::get/set` (settings JSON) e comando `vestuario:settings` artisan
3. **Custo de manutenção** — duplicar OTel exporter/sampler por módulo vertical multiplica config drift

## Spans que Vestuario gera automaticamente (via core)

| Operação | Span auto-gerado | Onde aparece |
|---|---|---|
| Request HTTP `/vestuario/install*` | `http.server` span (laravel middleware) | OTel collector / traces backend |
| `VestuarioSetting::query()->...` | `db.query` span (Eloquent listener) | OTel collector + logs estruturados |
| `php artisan vestuario:settings` | `cli.command` span (Console Kernel listener) | OTel collector |
| `LogsActivity` trait (Spatie) | `activity_log` row em DB (audit) | tabela `activity_log` + `/copiloto/admin/audit` |

## Logs estruturados

Logs herdam `App\Logging\CustomizeFormatter` do core. Padrão:

```php
\Log::info('Vestuario settings updated', [
    'business_id' => $businessId,  // já scopado pelo global scope
    'keys_changed' => array_keys($changes),
]);
```

⛔ **PII Tier 0** ([proibicoes.md](../../proibicoes.md)) — nunca logar conteúdo de `settings` se contiver dado de cliente. Usar `[REDACTED]` ou `array_keys()` apenas (ver [PII-LGPD.md](PII-LGPD.md)).

## Métricas Prometheus / health-check

Vestuario aparece indiretamente em:

- `php artisan jana:health-check` — check `multi_tenant_isolation` valida `VestuarioSetting` respeita global scope (cross-business=0)
- Logs estruturados Hostinger → ingestão Grafana via core (não há exporter custom)

## Quando criar OTel custom

Quando Vestuario evoluir Sprint 2+ e ganhar:

- Service classe com lógica de negócio crítica (precificação, validação fiscal vestuário) → instrumentar com `Tracer::startSpan()` per-method
- Job assíncrono com SLA (envio etiqueta, sync ERP externo) → span custom + métrica `vestuario_job_duration_seconds`
- Integração HTTP externa (marketplaces, transportadoras) → `OtelHttpClient` wrapper

Até lá, herança do core é suficiente e correta.

## Referências

- ADR 0035 (stack IA canônica) §"OTel GenAI"
- ADR 0094 (Constituição v2) §7 Transparência
- `App\Providers\OtelServiceProvider` (core)
