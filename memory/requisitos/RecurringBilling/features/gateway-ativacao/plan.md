---
feature: gateway-ativacao
module: RecurringBilling
---

# Plan — Ativar gateway nas assinaturas dormentes

## Decisões técnicas

| # | Decisão | Por quê (1 linha) | Âncora |
|---|---|---|---|
| D1 | Comando artisan idempotente `rb:gateway-backfill`, NUNCA SQL/tinker direto | caminho canônico "INSERT/UPDATE direto → comando artisan idempotente + commit" | [proibicoes.md](../../../../proibicoes.md) §Mexeu-REGISTRA |
| D2 | Dry-run é o modo **padrão**; escrita só com `--apply` explícito | mexe em COBRANÇA (valor) → REGRA MESTRE: apresentar impacto antes de aplicar | proibicoes §REGRA MESTRE |
| D3 | Atribuição via `rb_subscriptions.conta_bancaria_id` (coluna override JÁ existe) — não criar coluna `gateway` nova na assinatura | o schema já prevê o override por contrato; `InvoiceGeneratorService` já propaga pro invoice | migration `2026_05_06_001001` L29-30 · `InvoiceGeneratorService.php` L162 |
| D4 | Resolução de provider: conta bancária ATIVA do business no banco preferencial (Inter 077 · C6 336 · Cora 403); ambiguidade (2+ contas no mesmo banco) = não-resolvível (AC-4) | determinístico e auditável; palpite de gateway é dinheiro no lugar errado | handoff 2026-06-07 (códigos de banco) |
| D5 | Audit por escrita reusando o pattern de audit do módulo (SubscriptionEvent) | trilha já existente por assinatura; não inventar tabela nova | `Models/SubscriptionEvent.php` |

## Plug-points (comparar e NÃO duplicar)

| Onde | O que já existe | Como esta feature encaixa |
|---|---|---|
| `Modules/RecurringBilling/Console/Commands/` | `BackfillCachedFieldsCommand.php` (backfill idempotente de referência) | IMITAR: novo `GatewayBackfillCommand.php` no mesmo idioma (chunk, relatório, `--detail`) |
| `Modules/RecurringBilling/Models/Subscription.php` | scope business + `conta_bancaria_id` override | só UPDATE via Model (global scope Tier 0 ativo) |
| `Modules/RecurringBilling/Services/InvoiceGeneratorService.php` | L162 já copia `conta_bancaria_id` da subscription pro invoice | ZERO mudança esperada aqui — é o motivo do D3 |
| `Modules/RecurringBilling/Models/SubscriptionEvent.php` | trilha de eventos por assinatura | evento `gateway_atribuido` (de→pra, ator, origem=backfill) |
| `Modules/RecurringBilling/Tests/` | suite Pest existente do módulo | novos testes AO LADO, registrados como a suite atual (pegadinha phpunit.xml) |

## Design (opcionais — do design.md do Kiro)

- **(a) Dados tocados:** `rb_subscriptions.conta_bancaria_id` (write, override de gateway) · leitura de `contas_bancarias` ativas do business · `rb_subscription_events` (write, audit).
- **(b) Contratos:** nenhuma rota HTTP nova (comando artisan `rb:gateway-backfill`); evento interno `gateway_atribuido` no `SubscriptionEvent`; a régua reativada volta a disparar o fluxo de `GenerateInvoicesCommand`.
- **(c) Interação novo↔existente:** `GatewayBackfillCommand → Subscription (global scope biz) → grava conta_bancaria_id + SubscriptionEvent` → no próximo ciclo `InvoiceGeneratorService` L162 já copia `conta_bancaria_id` pro invoice (por isso zero mudança lá, D3).

## Riscos Tier-0 (checklist)

- [x] **Multi-tenant (ADR 0093):** toda resolução conta↔assinatura DENTRO do mesmo `business_id`; Pest cross-tenant biz=1 vs biz=99 (AC-5). Comando itera por business, nunca `withoutGlobalScopes` sem `// SUPERADMIN:`.
- [x] **REGRA MESTRE valor/estoque:** APLICA EM CHEIO (reativa cobrança real). Dupla confirmação = (a) dry-run antes→depois das 109 linhas aprovado por Wagner + (b) smoke canário de 1 assinatura ANTES de destravar o lote. Sem aprovação humana do dry-run, `--apply` não roda (T-05 é gate humano).
- [x] **PII/LGPD:** relatório/log usa contato `[REDACTED]`; nunca CPF/CNPJ em PR/commit (nem valores BRL em git — regra 2026-06-08).
- [x] **Tela (ADR 0264):** N/A — sem mudança em `resources/js/Pages/**` nesta feature (UI é fora-de-escopo).
- [x] **Runtime (ADR 0062):** Pest SEMPRE no CT 100 (`oimpresso-staging`); nenhum daemon novo.

## Alternativas descartadas

- **UPDATE via tinker/phpMyAdmin nas 109 linhas** — viola "mexeu, registra"; sem idempotência nem audit; variante "só dessa vez" também proibida.
- **Coluna `gateway` string nova em `rb_subscriptions`** — duplicaria a semântica de `conta_bancaria_id` (dual-source, a doença do ADR 0302); o invoice já deriva gateway da conta.
- **Backfill embutido no `GenerateInvoicesCommand`** — mistura régua recorrente com migração one-shot; re-execução diária re-avaliaria atribuição (surface de bug em dinheiro).
