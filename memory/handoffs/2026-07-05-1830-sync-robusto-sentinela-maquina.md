---
date: "2026-07-05"
time: "18:30 BRT"
slug: sync-robusto-sentinela-maquina
tldr: "Executa next_steps do handoff 0130: #3821 sync robusto (lock+retry+--only+anti-OOM) PROVADO em prod — cron reconciliou os 73 BRIEFINGs sozinho (76 vivos, full sync 1610 docs sem deadlock/OOM). #3823 sentinela mcp_index_sync_gap LIVE verde (0 ausentes de 1620, estendida com heartbeat pela sessão canary). Residuais #2/#3 + Ondas 1-4 viraram 6 chips paralelos; Ondas 2/4 e hybrid já fecharam."
prs: [3821, 3823, 3820]
decided_by: [W]
related_adrs:
  - 0053-mcp-server-governanca-como-produto
  - 0312-decisions-search-fulltext-hybrid-docs-off
  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
next_steps:
  - "Wagner: fechar PR #3818 (baseline duplicado do #3820 — 1 tema = 1 doc) e triar #3665/#3570 (handoff-PRs antigos abertos)"
  - "Consolidar resultados das 6 sessões-chip quando fecharem (Ondas 1/3 ainda rodando ao encerrar)"
  - "Sentinela se auto-prova no cron 06:00 BRT — se alarmar, seguir mensagem do check (mcp:sync-memory + investigar)"
---

# Sync robusto + sentinela — a máquina substitui o conserto na mão

## Estado MCP no momento

- **Cycle ativo:** nenhum em COPI. `my-work`: 8 REVIEW + 2 BLOCKED (inalterado por esta sessão).
- **PRs desta sessão:** #3821 MERGED (sync robusto) · #3822 CLOSED (superseded pós-squash) · #3823 MERGED (sentinela, rebase limpo do #3822) · #3820 MERGED (plano+baseline, autoria sessão irmã, merge autorizado Wagner).
- **Handoffs irmãos de hoje:** 0130 (origem do plano) · 1500 (Onda 2 fechada) · 1720 (Onda 4 fechada) — sessões-chip aterrissando em paralelo.

## O que aconteceu

Wagner pediu "executa o plano do último PR" (handoff 0130) + "aprimore em chips" + **"a máquina é melhor do que arrumar na mão"** — que virou o princípio da sessão:

1. **Next_step #1 (sync robusto) → #3821:** `Cache::lock('mcp:sync-memory')` (mata deadlock webhook+cron), retry 3× só pra errno 1213/1205 (não-deadlock propaga — lição hotfix 1062), `--only=<type>` (parcial barato que **pula soft-delete** — sem isso o whereNotIn apagaria 1400+ docs), disableQueryLog + gc a cada 200 docs (OOM). 7 Pest no CT 100.
2. **Next_step #3 (sentinela) → #3823:** check DURO `mcp_index_sync_gap` no `jana:health-check` — compara `IndexarMemoryGitParaDb::slugsEsperados()` (MESMA coleta do sync, fonte única) vs slugs vivos. Doc canônico fora do índice = ALERT 06:00. 4 Pest (bite-test incluso).
3. **Next_step #2 (hybrid 0312) + canary + Ondas 1-4 do plano → 6 chips**, todos iniciados por Wagner em sessões paralelas. Coordenei anti-duplicação via send_message (sessão canary estendeu meu check com heartbeat em vez de recriar — funcionou).

## Prova real (R1 — evidência literal de prod)

```
briefings_vivos=76        (eram 3 à mão + 73 pendentes — o CRON reconciliou sozinho)
mcp-cron.log: "Concluído: 1610 indexados ... 0 removidos" ×2 runs full consecutivos
jana:health-check: mcp_index_sync_gap ok=true value=0 "Todos os 1620 docs ... heartbeat ≤7d"
```

Full sync que antes morria (deadlock+OOM) agora termina; sentinela LIVE verde e verdadeira (esperados == vivos).

## Lições catalogadas

- **Empilhar PR sobre branch que vai levar squash** quebra o merge do filho (histórico diverge) — rebase via cherry-pick em branch novo (`-v2`) resolve sem force-push. Considerar merge-commit pra stacks futuras.
- **Arrow fn PHP captura por VALOR**: `fn () =>` + closure interno `use (&$x)` referencia a CÓPIA — contador de retry ficou 0 no teste. Closure explícito com `use (&$x)` nas duas camadas.
- **Paralelismo de chips funciona** com 1 aviso de escopo no send_message — a sessão canary estendeu em vez de duplicar.

## Pointers detalhados

- Origem/contrato: [`2026-07-05-0130-rag-investigacao-profunda-sync-fix.md`](2026-07-05-0130-rag-investigacao-profunda-sync-fix.md)
- Plano das Ondas: [`memory/requisitos/_Governanca/PLANO-APROFUNDAMENTO-AVALIACOES.md`](../requisitos/_Governanca/PLANO-APROFUNDAMENTO-AVALIACOES.md) + baseline [`memory/governance/BASELINE-QUALIDADE-2026-07.md`](../governance/BASELINE-QUALIDADE-2026-07.md)
- Código: `Modules/Jana/Services/Mcp/IndexarMemoryGitParaDb.php` · `Modules/Jana/Console/Commands/{McpSyncMemoryCommand,HealthCheckCommand}.php` · testes em `Modules/Jana/Tests/Feature/Mcp/`
