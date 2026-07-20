---
date: "2026-07-20"
time: "1149"
topic: "US-GOV-052 — port hooks .ps1→.mjs (lotes 1-4) + aposenta Herd + adversário refuta 3 aposentadorias"
author: "[CC]"
related_adrs: [0062-separacao-runtime-hostinger-ct100, 0233-ativacao-memoria-momento-decisao, 0234-automation-registry-mcp]
---

# Handoff 2026-07-20 11:49 — US-GOV-052 port .ps1→.mjs + aposentadoria Herd + adversário

## O que foi feito (tudo mergeado salvo nota)

Port cross-plataforma dos hooks `.ps1`→`.mjs` (motivo: `.ps1` só roda no Windows do [W]; o time MCP Felipe/Maiara/Luiz entra em Mac/Linux e o `powershell -File` evapora em silêncio → hook morto). Padrão: classificador puro exportado + fail-open + selftest E2E + wiring `node .mjs` + registration test (correção ≠ invocação) + step no `governance-script-tests.yml` + `_HOOKS-INDEX.md` regenerado + `AUTOMATIONS.md` atualizado.

| PR | Conteúdo | Estado |
|---|---|---|
| [#4563](https://github.com/wagnerra23/oimpresso.com/pull/4563) | Lote 1 — 5 blockers (block-bom-encoding, block-serving-branch-switch, block-test-without-red, warn-red-first, commit-discipline-check) | ✅ merged |
| [#4568](https://github.com/wagnerra23/oimpresso.com/pull/4568) | Lote 2 — 5 nudges (memory-pending, nudge-recommend-not-menu, nudge-diagnosis-without-evidence, mcp-first-warning, nudge-test-contract-anchor) | ✅ merged |
| [#4570](https://github.com/wagnerra23/oimpresso.com/pull/4570) | Cleanup — deleta os 10 .ps1 dos lotes 1+2 + fix memory-health/AUTOMATIONS | ✅ merged |
| [#4577](https://github.com/wagnerra23/oimpresso.com/pull/4577) | Lote 3 — 4 SessionStart+preflight (check-skills-fresh, loop-fechar-check, licoes-code-two-strikes, modulo-preflight-warning) | ✅ merged |
| [#4578](https://github.com/wagnerra23/oimpresso.com/pull/4578) | Aposenta block-serving-branch-switch (Herd morto) | ✅ merged |
| [#4580](https://github.com/wagnerra23/oimpresso.com/pull/4580) | Lote 4 — porta charter-validate + preflight-new-capability | ✅ merged |

**Placar:** de 18 `.ps1` wired → **13 portados + 1 aposentado (Herd) mergeados**. Restam **3** (SessionStart) + 1 decisão ([W] sobre mcp-first-warning).

## Achados desta sessão (importantes)

1. **Herd morreu** ([W] 2026-07-20: "o hook do herd não é mais usado, testes estao no stage"). O `block-serving-branch-switch` só protegia o checkout que servia `oimpresso.test` via Herd local → premissa morta → aposentado (#4578). A R8/ADR 0233 fica sem objeto (append-only; formalizar a morte = ADR supersede é decisão [W]).

2. **Adversário refutou 3 aposentadorias.** [W] mandou aposentar mcp-first-warning + charter-validate + preflight-new-capability COM adversário. O adversário (read-only, verificou o repo vivo) provou que a "cobertura" que o triagem (2026-07-09) alegava cobre **vetor DIFERENTE** nos 3:
   - `charter-validate`: block-mwart cobre RUNBOOK, block-ancora cobre PNG — nenhum cobre "editou Page sem ler charter"; personas-resolve declara este hook como bind de enforcement. → KEEP+portado (#4580).
   - `preflight-new-capability`: reuse-check é dedup de SÍMBOLO, este é anti-reinvenção de FRAMEWORK (ADR 0216) — vetores diferentes. → KEEP+portado (#4580).
   - `mcp-first-warning`: BORDERLINE — skill cobre objetivo mas não o backstop determinístico por-Read; advisory + falso-positivo estrutural (worktree sem MCP). Dano de deletar = cost-hygiene, ZERO Tier-0. → KEEP, **decisão [W]** se aceita o residual (chip criado).
   - **Lição:** o triagem canônico ERRA análise de cobertura — sempre passar aposentadoria pelo adversário. **Registrar no §5 de proibicoes.md** (não feito ainda — pendente): "aposentar mcp-first/charter/preflight por cobertura-do-triagem = REFUTADO".

3. **Bug herdado corrigido:** `modulo-preflight-warning.projectKey` (o .ps1) só trocava `\` e `:`; a chave real do Claude Code é `D--oimpresso-com` (troca `\ : . /`) — o fallback de transcript NUNCA casava no Windows. Corrigido no port (#4577). O caminho primário agora é `payload.transcript_path` (mais robusto, que o .ps1 nem tinha).

4. **Funcionamento GARANTIDO** (smoke real pós-merge): 31/31 hook selftests passam; invocação exata `node .claude/hooks/X.mjs` com payload real — charter-validate dispara, preflight-new-capability dispara, block-bom-encoding strict → exit 2 (bloqueia), memory-pending exit 0. E o `block-destructive.mjs` bloqueou de verdade um `git push --force` do próprio smoke (prova viva). block-serving deletado + 0 refs no settings.json.

## Restante (2 chips criados)

- **Chip task_b1ad598f** — Portar cadeia SessionStart (`brief-fetch-curl.ps1` + `tier-a-banner.ps1` + handoff-inline-powershell) `.ps1`→`.mjs`. **RODAR ADVERSÁRIO PRIMEIRO** ([W] regra): o brief-fetch-curl faz HTTP autenticado ao MCP (curl→fetch tem risco de token/endpoint só-Windows + vazamento); o tier-a-banner acopla ao `skills-index-generate.mjs` (que verifica o banner — pode precisar derivar o número em vez de repetir). É o maior buraco Mac/Linux (o time abre sessão sem brief/handoff/banner).
- **Chip task_0e2e00c8** — Decisão [W]: aposentar `mcp-first-warning` (adversário: borderline, só se [W] aceitar o residual) ou manter.

## Pegadinhas catalogadas nesta sessão

- **Heredoc de Bash colapsa `\\`→`\`** (JSON→bash), quebra regex `.replace(/\\/g,...)` silenciosamente (node --check passa, comportamento erra). Usar **Write tool** (verbatim) para arquivos com `\\`, OU `String.fromCharCode(92)` no lugar do literal.
- **Classificador de segurança (Write/Bash/Edit) oscilou pesado** a sessão inteira — cada escrita levou 5-11 tentativas. Read/Grep/Glob nunca precisam dele.
- Merge de PRs que ambos regeneram `_HOOKS-INDEX.md` → conflito no gerado; resolver **regenerando** (`hooks-manifest-generate.mjs --write`), não hand-merge.

## Estado MCP no momento do fechamento

Não consultado via tools MCP nesta sessão (trabalho foi 100% em `.claude/hooks` + governança, fora do fluxo cycle/tasks). Sessão iniciou com brief #385 (cycle "—", HITL 2 pendentes Wagner FIN-004 + runbook on-prem). Nenhuma task MCP criada/fechada — o trabalho de hooks não tem US no backlog (é infra de agente, US-GOV-052 é o guarda-chuva). Chips CCD criados: task_b1ad598f + task_0e2e00c8.
