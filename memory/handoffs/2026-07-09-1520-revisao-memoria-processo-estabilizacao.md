# Handoff 2026-07-09 15:20 — Revisão da memória do processo + doutrina 0329 + estabilização em ondas

**Sessão:** `wonderful-moser-71ba82` (+ worktrees `test-protocolo/test-fonte-unica/test-vcstale/lei-passo2/test-estabiliza`, todos limpos ou em uso pelos chips). **Off-cycle.** **Base:** sempre `origin/main` fresco (a worktree da sessão nasceu −4947 stale — guard avisou, todo trabalho partiu de worktrees frescas).

## TL;DR

Arco do dia (pedido [W]: "testar todo o protocolo de aplicação de protótipo" → "auto-crítica e aprender" → "o que mais fica procurando?" → "revisão profunda da memória do processo" → "faça a lei" → "estabilize, sempre fica algo pendente"):

1. **Protocolo testado ponta-a-ponta** (fases −1..5, ondas): pull DesignSync real (ADR 0325, `vendas-page.jsx` 92KB sem browser), detectar-telas 0 órfãos c/ 2 telas de venda separadas, âncora+fingerprint medindo DOM vivo do `/financeiro/unificado` (primary = 295 ✅ DS).
2. **3 furos fechados+MERGED:** #3999 `style-fingerprint --selftest` órfão → CI · #4001 `protocolo.config.mjs` fonte-única (IDs Cowork/DS + staging + mapa FASES) · #4002 sentinela `visual-comparison-staleness` (achou 14 comparativos stale 51-53d).
3. **Auditoria adversarial 57 agentes** sobre a memória do processo → meta-padrão: *"correção-do-mecanismo ≠ invocação"* — enforcement desacoplado do merge (advisory/opt-in/nunca-construído/Windows-only/desarmado). 13 categorias · 36 propostas · 26 sobrevivem · 10 rejeitadas (candidatas a proibições §5). Artifact "revisao-memoria-processo".
4. **Passo 1 "parar de mentir" #4003 MERGED** — pageheader-canon C4 aprovava hue morto (145; agora exige 295 da ADR 0190). Verificar-antes-de-editar pegou **3 falsos-positivos do próprio relatório adversarial** (ragas-gate existe como `jana-ragas-gate.yml`; "46" inexistente; mwart-gate em ADR aceita = tombstone, não edição).
5. **5 chips paralelos** (worktrees isoladas, ressalvas do adversário embutidas): #4004 hook BRL **MERGED** · #4005 IT2/§14 charter **MERGED** · #4006 knowledge-drift ghost **MERGED** · #4007 SafeSelectItem **MERGED** · #4009 tombstones (ABERTO, verde) · #4010 ref-integrity (ABERTO).
6. **A lei: ADR 0329 (doutrina de documentação de processo) #4008 MERGED como `proposto`** — 5 propriedades (executável/fonte-única/ligada-ao-gate/cross-plataforma/auto-fresca). Formato ESTUDADO antes (workflow 4 leitores): ADR numerado TOP-LEVEL (o sync MCP faz glob não-recursivo → `proposals/` é invisível a `decisions-search`; prova viva: 0320 `aceito` presa lá).
7. **Estabilização em ondas** ([W] "sempre fica algo pendente"): bateria **22/22 verde** no main pós-9-merges (1 falso-alarme: `node --test` dir-mode Node24 local vs Node20 CI) · worktrees limpos · **4 tasks MCP criadas US-GOV-049..052** (as pendências agora existem no sistema que o brief lê) · **sentinela `adr-proposto-parado` #4011 (ABERTO)** — scan real: **A:7 decididos invisíveis em proposals/ · B:3 numerados presos · C:30 propostos >14d** (feature-wish excluído, ADR 0105).

## Pendências ABERTAS (o motivo deste handoff — todas registradas como US)

| O quê | Task MCP | Dono |
|---|---|---|
| Ratificar ADR 0329 (flip `proposto→aceito` in-place + `adr-index --write`) | **US-GOV-049** | [W] |
| Ratificar 0314 por-item + 0299; **mover 0320 aceita de proposals/ → top-level** (bug vivo) | **US-GOV-050** | [W] |
| Review+merge **#4009** (tombstones) e **#4010** (ref-integrity) | **US-GOV-051** | [W] |
| **#4011** sentinela adr-proposto-parado — CI rodando ao fechar este handoff | — | [W] merge |
| Backlog M/G da revisão (P24 hooks .ps1→.mjs ANTES do time MCP; P31/32/33; P6/13/35; P11-A) | **US-GOV-052** | — |

## Estado MCP no momento do fechamento (prova, não promessa)

- `cycles-active`: **nenhum cycle ativo** em COPI.
- `my-work` (wagner): 20 tasks (8 review · 8 blocked · 4 todo) — **zero desta sessão antes do registro acima** (o furo que motivou a Onda 3).
- `sessions-recent` (fs): hoje `2026-07-09-ds-loop-sync-git-espelho` (sessão paralela, tema disjunto) + handoff 11:18; 2026-07-08 ×2 (fidelidade fingerprint).
- decisions desde o último handoff (11:18): **0329 proposto** (#4008) · 0328 aceita ontem (#3998, sessão paralela).
- PRs abertos meus/chips: #4009, #4010, #4011. Pré-existentes de outras sessões (não tocados): #3994/#3987/#3986/#3916/#3914/#3906.

## Lições da sessão

- **"Isso vai acabar não sendo feito" ([W]) é um mecanismo, não um humor:** pendência sem máquina que a surfe apodrece — virou a sentinela #4011 + as US-GOV. É a Propriedade 5 da 0329 aplicada à própria governança.
- **Nenhum achado é lei até verificado contra o repo vivo:** o relatório adversarial (57 agentes) errou 3/7 detalhes checados. Verificar-antes-de-editar salvou o canon 5 vezes.
- **`proposals/` é invisível ao MCP** (glob não-recursivo) — ADR que precisa ser encontrável nasce top-level numerado; `proposal_id` é deprecado ([W] pegou o agente prestes a copiar o formato legado: "estude melhor o assunto").
- **O "processo melhor" era o do CLAUDE.md** que a sessão não fechava: tasks MCP + handoff + session log. PRs sem registro no MCP = pendência invisível ao brief.
- MSYS colon-mangling / worktree-órfã pós-`gh pr merge --delete-branch` / Node24 vs Node20 dir-mode: 3 tropeços de toolchain diagnosticados como falso-alarme antes de "consertar" o que não estava quebrado.
