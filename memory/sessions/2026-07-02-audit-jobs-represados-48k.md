# 2026-07-02 — Audit: 48k jobs represados na `jobs` table (filas sem worker)

**Sessão:** exciting-euler-f23684 · **Owner:** [CC] · **Escopo:** investigação read-only prod + PR de correção (worker gated + comando purge)

## Problema

`QUEUE_CONNECTION=database` no Hostinger, mas o Kernel só agenda `queue:work` pras filas `whatsapp` e `whatsapp-history`. Resultado: **48.194 jobs** represados desde 2026-05-14, `attempts=0` em todos (nenhum worker jamais pegou).

## Inventário (prod, read-only via SSH, 2026-07-02)

| Fila | Job | Qtde | Período | Disposição recomendada |
|---|---|---|---|---|
| default | `Whatsapp\DownloadMediaJob` | 20.282 | 14→28/mai | **PURGAR** — órfãos já catalogados no próprio código (job migrou pra fila `whatsapp` em 28/mai); URLs de mídia Meta expiram em dias |
| customer-memory | `RebuildCustomerMemoryJob` | 15.259 | 16/mai→02/jul | **PURGAR** — rebuild idempotente "latest-wins"; cron diário re-dispatcha fresco |
| default | `KB\KbBridgeFromMcpJob` | 8.062 | 16/mai→02/jul | **PURGAR** — sync incremental com cursor (`KbBridgeState`); próximo tick (15min) recupera tudo |
| default | `Jana\NarrarSaudeEcosistemaJob` | 1.074 | contínuo | **PURGAR** — recorrente, latest-wins |
| default | `RetryFailedMediaDownloadsJob` | 1.057 | contínuo | **PURGAR** — scanner recorrente |
| default | `RecurringBilling\SyncBankBalancesJob` | 1.057 | contínuo | **PURGAR** — rodar ×1057 = rate-limit nas APIs bancárias; latest-wins |
| default | `Whatsapp\TranscribeAudioJob` | 646 | 28/mai→01/jul | **PURGAR** — transcrever áudios de semanas atrás = custo LLM sem valor; futuro flui com worker |
| **nfe** | `EmitirNfceAoFinalizarVenda` (listener) | **384** | 14/mai→01/jul | **PURGAR com decisão Wagner** — ver seção NFC-e abaixo |
| employee-performance | `RebuildEmployeePerformanceJob` | 176 | diário | **PURGAR** — idem customer-memory |
| jana-index | `ReindexarDocumentoJob` | 45 | 10/jun→02/jul | **PURGAR** — staleness detector re-detecta e re-dispatcha (self-healing) |
| default | `SyncBankStatementsJob` / `InboxAutoCleanupJob` / `BuscarDfesRecebidosJob` | 44+44+42 | diários | **PURGAR** — latest-wins |
| copiloto-memoria | `ExtrairFatosDaConversaJob` | 10 | 14→22/mai | **PURGAR** (ou rodar — só 10, conversas de mai; valor marginal) |
| default | `whatsapp:import-history` | 7 | 14-15/mai | **PURGAR** — stale |
| default | `OficinaAuto\EnviarLinkAprovacaoWhatsappJob` | **5** | 10-11/jun | **PURGAR — NUNCA rodar**: enviaria WhatsApp de aprovação de 3 semanas atrás pra clientes reais do Martinho (biz=164) |

`failed_jobs`: 16 (todos `ProcessIncomingWebhookJob` fila whatsapp — fora do escopo).

## Achado Tier 0 — fila `nfe` (fiscal)

- Flag global `NFEBRASIL_AUTO_EMISSION_NFCE=true` **LIGADA** na prod; `nfe_business_configs` tem só biz=1 com `auto_emission_enabled=1`.
- 384 jobs → 314 transactions distintas: **279 biz=4 (ROTA LIVRE)** + 5 biz=1 + 30 já deletadas (jobs falhariam, `deleteWhenMissingModels=false`).
- Elegíveis (sell/final/paid|partial): 224. **Zero** têm `nfe_emissoes`.
- Gate per-business: os 221 pendentes biz=4 fariam **no-op** (sem config). Só **3 vendas biz=1** emitiriam NFC-e **retroativa** (vendas de mai-jun emitidas com data de hoje) se o worker ligasse sem purge.
- **Pergunta aberta pro Wagner:** biz=4 (Larissa) deveria estar emitindo NFC-e automática? Se sim, falta a linha em `nfe_business_configs` — e há 221 vendas desde 03/mai sem emissão. Se não, tudo certo (no-op).

## Correção entregue (PR)

1. **`jobs:purge-represados`** ([app/Console/Commands/PurgeJobsRepresadosCommand.php](../../app/Console/Commands/PurgeJobsRepresadosCommand.php)) — dry-run DEFAULT (REGRA MESTRE), `--execute` explícito, recusa filas `whatsapp*` (têm worker + cleanup próprio), tabela de impacto por fila+classe.
2. **Worker agendado** no Kernel pras 6 filas órfãs (`nfe,default,customer-memory,employee-performance,jana-index,copiloto-memoria`), mesmo pattern whatsapp (`--max-time=55`, everyMinute, withoutOverlapping) — **gated por `QUEUE_BACKLOG_WORKER_ENABLED` default false** (config/queue.php).
3. Teste Pest [tests/Feature/Console/PurgeJobsRepresadosCommandTest.php](../../tests/Feature/Console/PurgeJobsRepresadosCommandTest.php) (CI roda; local proibido).

## Sequência de ativação (pós-merge, Wagner)

1. Deploy (git pull Hostinger).
2. `php artisan jobs:purge-represados` (dry-run) → conferir tabela.
3. Wagner aprova → `php artisan jobs:purge-represados --execute`.
4. Decidir as 3 NFC-e biz=1 pendentes (emitir manual ou ignorar) + decidir biz=4 (`nfe_business_configs`).
5. `QUEUE_BACKLOG_WORKER_ENABLED=true` no `.env` + `php artisan config:cache`.
6. Smoke: `SELECT queue, COUNT(*) FROM jobs GROUP BY queue` deve ficar ~0 fora de picos.

## Risco se ligar worker SEM purge (por que o gate existe)

NFC-e retroativa (3 vendas biz=1) + 5 WhatsApps stale pra clientes Martinho + 1.057 syncs bancários em rajada (rate limit) + 646 transcrições LLM inúteis + ~18h de churn de CPU no shared hosting.

## EXECUTADO 2026-07-02 (Wagner aprovou — "ok pode fazer")

- PR [#3609](https://github.com/wagnerra23/oimpresso.com/pull/3609) mergeado (64 checks verdes) + deploy automático OK.
- Dry-run na prod: 48.198 jobs (audit da manhã + 4 novos dos crons) — tabela bateu com o inventário aprovado.
- `jobs:purge-represados --execute`: **48.198 deletados, tabela `jobs` zerada** (log estruturado `[jobs.purge-represados]` no laravel.log).
- `QUEUE_BACKLOG_WORKER_ENABLED=true` no `.env` + `config:cache`; `schedule:list` confirma o 3º worker (`nfe,default,customer-memory,employee-performance,jana-index,copiloto-memoria`) everyMinute.
- Smoke pós-ativação: filas drenando (ver relatório da sessão).

**Pendência aberta (decisão Wagner, fiscal Tier 0):** biz=4 (ROTA LIVRE) deveria emitir NFC-e automática? 221 vendas desde 03/mai ficaram sem emissão (gate per-business fez no-op). Se sim → criar linha `nfe_business_configs` biz=4 + decidir retroativas com contador. As 3 NFC-e biz=1 pendentes foram purgadas junto (emitir manual se necessário).
