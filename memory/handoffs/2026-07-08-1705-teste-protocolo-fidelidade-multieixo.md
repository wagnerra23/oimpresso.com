---
date: "2026-07-08"
time: "17:05 BRT"
slug: teste-protocolo-fidelidade-multieixo
tldr: "Teste do protocolo aplicar-prototipo (ADR 0325 −1-PULL + fidelidade fingerprint) na Financeiro/Unificado: transporte novo + detecção + gate de fidelidade provados ponta-a-ponta; achado = drift sistemático de token dark + fonte mono (não copiar o protótipo, direção não é uniforme). Aprendizados landados em canon (PR #3979); 2 tasks de fidelidade rodando."
prs: [3979]
related_adrs: [0325-import-prototipo-designsync-pull-direto, 0190-pageheader-primary-roxo-295]
next_steps: ["Acompanhar task_52981b56 (fix drift token dark, dono do gap-spec) + task_f707fee6 (medição light/1280/estados/região)", "Ao retomar: worktree fresco off origin/main; NÃO reusar branch stale"]
---

# Teste do protocolo `aplicar-prototipo` na Financeiro/Unificado

## Estado MCP no momento do fechamento
- Snapshot via **brief #321** (SessionStart) — MCP oimpresso tools deferred + classifier degradado esta sessão, não rodei `cycles-active`/`my-work` frescos.
- **Cycle:** off-cycle. **HITL pending Wagner:** 2 (runbook on-prem Gold, FIN-004 cobrança ROTA LIVRE). Brain B 0%.
- Handoff irmão mais recente: [2026-07-08 14:31 borda-dark-token-ui0022](2026-07-08-1431-financeiro-borda-dark-token-ui0022.md) (mesma área, sessão paralela).

## O que aconteceu
Wagner: *"o protocolo mudou e quero testar no financeiro aplique do protótipo"* → dogfood do protocolo `aplicar-prototipo` na tela **Financeiro/Unificado**, do zero, em **base fresca origin/main** (a desta sessão estava −4931 → worktree novo `fin-proto-test`).

Provado ponta-a-ponta:
1. **−1-PULL DesignSync (ADR 0325)** — `financeiro-page.jsx` puxado direto do projeto Cowork `019dcfd3`, **sem browser/ZIP**. Pull === staging (dado atual).
2. **detectar-telas (Fase 0/0.5)** — 0 órfãos; line-diff diz vivo **à frente** do protótipo (Unificado 3057 ln × 2002 ln).
3. **Gate de fidelidade** (Wagner escolheu esse caminho) — `ancora.mjs` → `--snippet` → render proto (harness Cowork servido local :8799) + prod (`oimpresso.com/financeiro/unificado`, dark, biz=1) → `--compare` **fail-closed passou** (ancora ok, conteudo ok 19/82). Resultado: **67 DIVERGE / 30 IDENTICO**; dominante SISTEMATICO = `lineHeight` 65/67, `bgEfetivo` 64/67, `borderColor` 58/67, `fontSize` 47/67, + fonte mono `IBM Plex Mono`->`ui-monospace`.

**Leitura honesta:** ~540 "miss" eram RUIDO (sidebar real + dados mock, captura de página inteira); e **direção não é uniforme** (botão primário: prod `oklch 0.55` = canon ADR 0190, proto `0.7` = atrás → aplicar cego REGRIDE). Logo: nada a aplicar cegamente; o valor é a medição.

## Artefatos gerados
- **PR #3979 MERGED** (canon): `prototipo-ui/RUNBOOK-fidelidade-fingerprint.md` +11/-1 — cobertura obrigatória multi-eixo (`{light,dark}x{1280,1440}xestados`, região) + direção-não-uniforme + map cego a CSS + variante receiver-POST. CI 100% verde.
- **task_52981b56** (rodando) — fix do drift token dark, dono do gap-spec.
- **task_f707fee6** (rodando) — medição só, eixos light/1280/estados/região.
- Efêmeros (scratchpad, não-canon): `proto.json`/`prod.json` (fingerprints), `fp-receiver.mjs`, `snippet.js`.

## Persistência
- **git:** PR #3979 em `main` (`1da2d29216`) — webhook->MCP propaga em ~2min.
- **MCP/tasks:** 2 chips de fidelidade tracked.
- **BRIEFING:** não aplicável (sessão de teste de processo, não de capacidade de módulo).

## Próximos passos pra retomar
Acompanhar os 2 tasks de fidelidade (o `52981b56` decide o canon dos tokens dark + abre gap-spec; o `f707fee6` alimenta com light/1280/estados). Ao retomar código: **worktree fresco off origin/main** — não reusar branch stale.

## Lições catalogadas
- **Classifier degradado** (opus-4-8 stage-1/2) intermitente a sessão inteira — cada git op levou ~5-10 retries; read-only não sofre. Não é bug meu; retry resolve.
- **`javascript_tool` trunca retorno grande** → handoff de fingerprint via **receiver POST em `127.0.0.1`** (Chrome trata como trustworthy, aba https POSTa sem mixed-content). Validado.
- **Prova de base:** worktree estava −4931 de origin/main; rodar o protocolo dali daria falso-negativo silencioso (guard SessionStart estava certo).
- **map ≠ fidelidade:** o map (detectar-telas) é cego a CSS; só o fingerprint renderizado vê token/superfície/borda. Duas camadas complementares (agora em canon).

## Pointers detalhados
- Protocolo: [`prototipo-ui/RUNBOOK-fidelidade-fingerprint.md`](../../prototipo-ui/RUNBOOK-fidelidade-fingerprint.md) (editado) + [`RUNBOOK-aplicar-prototipo-orquestracao.md`](../../prototipo-ui/RUNBOOK-aplicar-prototipo-orquestracao.md) + skill `aplicar-prototipo`.
- Transporte: [ADR 0325](../decisions/0325-import-prototipo-designsync-pull-direto.md).
- Sessão paralela mesma área: handoff [2026-07-08 14:31](2026-07-08-1431-financeiro-borda-dark-token-ui0022.md) (borda dark = TOKEN, ADR UI-0022) — o `52981b56` deve reconciliar com esse achado.
