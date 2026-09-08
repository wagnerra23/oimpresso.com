---
date: "2026-09-06"
time: "2330 BRT"
slug: "redestilacao-13-portas-floor-distiller-13-0"
tldr: "Re-destilação REAL das 13 portas BRIEFING stale via jana:distill-module-truth num clone fresco do CT 100 (checkout sujo do oimpresso-staging intocado) + refutação GT-G5 em rodadas (12 portas: 10 rodadas, 31,8% → 1,58%; Governance: 3 rodadas, 59,1% → 1,8%). distiller_freshness medido 13 → 0 e floor re-apertado 13→0 no baseline. PR #6932 (draft) com 2 entries no ledger."
decided_by: ["W"]
cycle: null
prs: [6932]
us: []
next_steps:
  - "[W] revisar e mergear o #6932 — o GT-G3 required deve ficar verde (ratchet local: nenhuma regressão); se main ganhar outra porta stale antes do merge, re-medir e absorver/re-destilar no mesmo PR"
  - "Chip: DistillerModuloVerdade derruba `id:` e `related_adrs` do frontmatter — consertar no distiller (o pós-processo aqui foi mecânico e declarado)"
  - "Chip: gatherer do distiller parafraseia slug de arquivo como 'última mudança' quando há poucos eventos (Governance: 3 de 48) e aceita session de refutação de outro lote como fonte (RecurringBilling) — filtrar sessions de processo e ler git log do módulo"
  - "SPEC `_pendente_` atrás do código: US-NFE-006, US-REPA-002, US-CRM-091; US-GOV-049/050 `todo` com trabalho entregue; CT-e rotulado modelo 67 no código (norma: 57)"
  - "Limpar /root/distill-fresh no CT 100 (clone + .env copiado do staging + raw-12 backup) quando o PR mergear"
---

# Handoff — re-destilação das 13 portas · floor `distiller_freshness` 13→0

> Continuação da cadeia de `nota_absorcao_*` do baseline (0→5→6→7→8→9→11→12→13). O que cada nota pedia — *"rodar `jana:distill-module-truth` num checkout atualizado e re-apertar o floor em PR próprio"* — foi feito, na forma do precedente #4042→#4061.

## O que foi feito

- **Checkout:** o container `oimpresso-staging` (`c1abe9548`, 2026-08-26, arquivos sujos de outra sessão) **não foi tocado**. Clone novo em `/root/distill-fresh/oimpresso` (host CT 100; `445efc7bb`, depois `e2bb4fdc1a` para o Governance), `vendor/` copiado do staging (`composer.lock` idêntico), `.env` do staging (fica no host), container descartável da imagem `oimpresso/mcp:latest` na rede `docker-host_default`, `--entrypoint php`, um `--module=<X>` por vez.
- **Distiller:** 13/13 `porta reescrita`, 0 `refused_pii`. `distilled_at`/`distilled_by` intocados. Pós-processo mecânico declarado: `id:` restaurado (o distiller emite só 5 campos), `related_adrs` no OficinaAuto, H1 duplicada da LLM removida.
- **Governance entrou no meio:** o #6907 tocou `PROTOCOLO-REFUTADOR-BACKFILL.md` enquanto a sessão rodava e main passou de 12 para 13 stale — re-destilado no mesmo clone (atualizado) e refutado como lote próprio.
- **Refutação GT-G5:** saída bruta da LLM reprovou (31,8% na r1: número derivado, "última mudança" atrás dos eventos, recibo trocado por prosa, gap já entregue como gap). Corrigida entre rodadas pelo gerador; cada rodada re-verificou o lote inteiro com refutadores opus novos em sessão fresca. Aprovação na r10 (1,58%) e r3 do Governance (1,8%). Log completo: [`memory/sessions/2026-09-06-refutacao-gt-g5-distill-12-portas.md`](../sessions/2026-09-06-refutacao-gt-g5-distill-12-portas.md).
- **Medição** (repo desrasado nos dois lados): `origin/main` puro = **13** (portas 80, carimbadas 14, oldest 2026-07-17); mesma árvore + os 13 BRIEFINGs = **0** (oldest 2026-09-05). Ratchet local após o baseline: nenhuma regressão.

## Pegadinhas que custaram tempo (para quem repetir)

- `git fetch origin governance/nightly-floor --depth 1` (sugerido pelo próprio ratchet) deixa o repo shallow e a métrica cai em `not_yet_measured` — desfazer com `fetch --unshallow`.
- **Nota de regressão de refutador não é fonte** — restaurar da porta HEAD sem re-medir reintroduziu fatos caducos (r5 subiu de 2,43% para 4,5%).
- **Recibo de comando tem que ser a forma literal rodada** — o refutador roda e compara (Governance r2).
- `knowledge-drift` lê "não existe `Modules/Cliente`" como citação de módulo fantasma → `ghost_count` 12→13 no ratchet; reescrito sem a forma de path.
- Rate limit da API (HTTP 429) matou refutadores em duas rodadas; relançados sem perda.

## Estado MCP no momento do fechamento

Brief do início da sessão (#615): cycle ativo —, HITL pendente [W] 5, SDD composta 42,5. `whats-active` não consultado nesta sessão (worktree filha, MCP não conectado — fallback filesystem, how-trabalhar §Fallback). Nenhuma task MCP criada/alterada.
