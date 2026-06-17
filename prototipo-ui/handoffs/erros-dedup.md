<!-- cowork: target: prototipo-ui/handoffs/erros-dedup.md -->
---
handoff_id: erros-dedup
tela: Plataforma/ErrorHandling
files: [app/Support/Errors/ErrorGrouper.php, database/migrations/xxxx_create_error_groups_table.php, app/Exceptions/Handler.php]
created_by: CC
audited_against: 0f98814eb4f8
---
## Onda E-2 (Fase 2 · Absorver) — Deduplicação + rate-limit de alertas

**Depende de:** E-1 (classificação na origem já carimba `dedupKey`). **Objetivo:** 1000 ocorrências do
mesmo erro = **1 sinal com contador**, nunca 1000 pings. Sem isto, qualquer canal se mata sozinho.

**§10.4:** validar contra o `main`; main vence. Reusar `Cache` + DB; NÃO `Cache::flush`.

### Design
- Tabela `error_groups`: `dedup_key (unique) · severity · audience · first_seen · last_seen · count ·
  status (open/muted/resolved) · sample_payload (sem PII)`. Tier-0: repo-wide, sem business_id no scope
  de leitura de governança (mas `dedup_key` inclui o business afetado).
- `ErrorGrouper::record(Classification $c)`: upsert por `dedup_key` (incrementa `count`, atualiza
  `last_seen`). Janela de decaimento: grupo sem ocorrência há N dias → arquiva.
- **Rate-limit do alerta** (no Handler, S0): dispara `S0Alert` no máx **1×/grupo/janela** (ex. 15min)
  via `Cache::add(dedup_key, ...)`. Reincidência dentro da janela só incrementa o contador.
- O alerta carrega o `count` ("3ª vez em 10min") e link pro grupo.

### NÃO FAZER
- ❌ Alertar por ocorrência. ❌ `Cache::flush`. ❌ PII no `sample_payload`. ❌ tocar a régua da E-1.

### PRONTO QUANDO (Pest)
- 1000 exceções iguais → 1 linha em `error_groups` com `count=1000`.
- `S0Alert` dispara 1× na janela por `dedup_key`; reincidência só incrementa.
- Grupo sem ocorrência há N dias → arquivado.

> Cowork read-only no git — DESIGN; código é PR revisado do [CL].
