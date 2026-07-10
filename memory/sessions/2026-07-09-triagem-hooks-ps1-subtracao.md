---
date: "2026-07-09"
topic: "Triagem com subtração dos 24 hooks .ps1 restantes — porta Tier-0, lote útil-ativo, aposenta nudge redundante, deleta morto"
authors: [C]
related_adrs: [0271-revisao-gates-ci-estado-real-required-e-subtracao-segura, 0314-poda-gates-onda-2-lei-fusoes, 0233-ativacao-memoria-momento-decisao]
---

# Sessão 2026-07-09 — Triagem dos hooks .ps1 (grade v3, fraqueza "cross-platform" 7/10)

**Contexto:** os 9 blockers Tier-0 já são `.mjs` (PRs [#4025](https://github.com/wagnerra23/oimpresso.com/pull/4025), [#4028](https://github.com/wagnerra23/oimpresso.com/pull/4028), [#4035](https://github.com/wagnerra23/oimpresso.com/pull/4035) hoje + os nativos block-brl/figma/design-sync/ancora/askq). O adversário da grade avisou: portar os ~34 `.ps1` restantes cegamente = "polir commodity". Resposta: **triagem com subtração** (espírito ADR 0271 — cortar exige a mesma coragem que criar).

**Inventário real (main fresco `e4c0eb5b8c`):** 36 arquivos `.ps1` em `.claude/hooks/` = **24 hooks + 12 arquivos de teste** (9 `*.test.ps1` pareados + 3 harnesses `test-*.ps1`). 23 dos 24 registrados no `settings.json`; 1 órfão.

## Método (evidência, não opinião)

- **Registro:** `grep settings.json` — hook registrado ≠ arquivo existente.
- **Mordida:** grep em `memory/sessions/` + `memory/handoffs/` por menções pós-criação; ausência = suspeito. Ressalva honesta: hooks advisory que "mordem" via stderr raramente geram doc — a ausência é sinal fraco, cruzei com incidente-origem e canon-âncora.
- **Substituto:** existe gate CI/rule/skill/hook `.mjs` cobrindo o mesmo vetor?
- **Refs externas:** grep em `.github/`, `scripts/`, `.claude/workflows/` antes de qualquer deleção (um `.ps1` chamado por outro mecanismo não pode sumir no calado).

## Tabela da triagem (24 hooks)

| # | Hook | Protege (âncora) | Mordeu? (evidência) | Classe | Ação |
|---|---|---|---|---|---|
| 1 | `pii-redactor.ps1` | LGPD Art. 7º — bloqueia `git commit` com CPF/CNPJ/cartão real (US-COPI-086) | SIM — 5 docs; redesign opção B commit-only 2026-06-13 = uso real calibrado | **Tier-0-esquecido** | **Portar JÁ** (PR 2) |
| 2 | `block-destructive.ps1` | Irreversibilidade — rm -rf, force-push main, DROP TABLE, DELETE sem WHERE, migrate:fresh prod (US-COPI-085) | Origem em guardrails Cycle 01; runtime-only, nenhum CI substitui | **Tier-0-esquecido** | **Portar JÁ** (PR 2) |
| 3 | `block-memory-drift.ps1` | ADRs CANON append-only + canon só via PR (ADR 0094/0130 + proibições Tier 0) | 4 docs; CI `governance-gate.yml` cobre só o MERGE — o hook cobre o edit local runtime (5 drifts catalogados maratona WhatsApp) | **Tier-0-esquecido** | **Portar JÁ** (PR 3) |
| 4 | `block-module-drift.ps1` | Controllers fora do SCOPE.md `contains[]` (ENFORCEMENT.md mecanismo #3, ADR 0080) | **NUNCA** — `git log -S` no settings.json: nunca foi registrado. Morto de nascença. Área hoje coberta por `casos-gate`+`dominio-gate` required (ADR 0264) | **Morto** | **Deletar** (PR 4) + `.test.ps1` junto. Zero mudança de enforcement (nunca rodou) |
| 5 | `block-bom-encoding.ps1` | UTF-8 BOM crasha PHP prod (incidente #984, site inteiro fora) | Incidente-origem grave; sem menção pós-criação, mas vetor (writes via Bash/PowerShell) segue vivo | útil-ativo | Portar — lote B |
| 6 | `block-merge-markers.ps1` | Markers de conflito em prod (PRs #1000/#1001, parse error) | Incidente-origem; **CI `merge-marker-scan.sh` já cobre o merge** — hook é defesa runtime | útil-ativo | Portar — lote B. Nota 0314: candidato a fusão futura se o scan CI virar required |
| 7 | `block-routes-string-legacy.ps1` | `Controller@method` string quebra `route:cache` (incidente #843, 404 silencioso) | Incidente-origem; rule `routes.md` é passiva, hook é o único enforcement | útil-ativo | Portar — lote B |
| 8 | `block-test-without-red.ps1` | Teste novo sem evidência de red = tautológico (SDD FV-T0, proibições §5 2026-06-05) | 1 doc (semana-0 SDD); bloqueador armável | útil-ativo | Portar — lote B (par do #9) |
| 9 | `warn-red-first.ps1` | Código de produção sem teste na sessão (advisory do par) | 1 doc; refs em `sdd-semana-0.js` workflow | útil-ativo | Portar — lote B (atualizar refs do workflow) |
| 10 | `nudge-test-contract-anchor.ps1` | Teste ancorado em CONTRATO, não no código (proibições §5) | refs em `sdd-semana-0.js` + 2 hooks irmãos | útil-ativo | Portar — lote B |
| 11 | `brief-fetch-curl.ps1` | Injeta o brief no SessionStart de worktree sem MCP (skill brief-first Tier A) | SIM — roda TODA sessão (132 disparos/7d; rodou nesta) | útil-ativo | Portar — lote A (curl→fetch nativo node) |
| 12 | `tier-a-banner.ps1` | Banner skills Tier A no SessionStart (ADR 0225) | Roda toda sessão; **alvo do gerador** `skills-index-generate.mjs` (P31, #4032) | útil-ativo | Portar — lote A (atualizar o gerador junto — ele verifica o banner) |
| 13 | `licoes-code-two-strikes.ps1` | Alarme classe de erro 2× sem gate (loop de aprendizado LICOES_CODE.md) | Roda toda sessão (nesta: 2 classes WATCH) | útil-ativo | Portar — lote A |
| 14 | `loop-fechar-check.ps1` | Rotina "fechar o loop IA-OS" (manifesto JSON, pendentes #3/#4) | Roda toda sessão; aponta pendência real | útil-ativo | Portar — lote A. Aposentar quando manifesto zerar (subtração agendada) |
| 15 | `check-skills-fresh.ps1` | Skill nova mergeada entre sessões → avisa /sync-skills | 4 docs; valor sobe com time MCP multi-dev | útil-ativo | Portar — lote A |
| 16 | `memory-pending.ps1` | Stop: memory/ dirty sem push → time não vê via MCP (webhook só pós-push) | 3 docs; vetor real (bug recorrente catalogado MANUAL §5) | útil-ativo | Portar — lote C |
| 17 | `modulo-preflight-warning.ps1` | FASE 1 PRÉ-FLIGHT da Regra Primária Tier 0 (Wagner 2026-05-15, warn) | 3 docs; par da skill `preflight-modulo` (ADR 0225) | útil-ativo | Portar — lote C |
| 18 | `commit-discipline-check.ps1` | Warns ≤300 linhas + force-push sem lease + PII no staged (skill Tier A) | 1 doc; warn barato, parcialmente redundante com pii-redactor (que bloqueia) | útil-ativo | Portar — lote C (fundir o warn de PII no port? avaliar no PR) |
| 19 | `nudge-diagnosis-without-evidence.ps1` | R1 estendida — diagnóstico sem grep/log/curl (ADR 0233) | ADR 0233 **ratificada 2026-07-02** (dossiê onda 4: "núcleo real") | útil-ativo | Portar — lote C |
| 20 | `nudge-recommend-not-menu.ps1` | R13 — resposta termina em menu sem recomendação (ADR 0233) | Idem #19; par do `block-askq-execution-menu.mjs` (que cobre AskUserQuestion; este cobre texto) | útil-ativo | Portar — lote C |
| 21 | `block-serving-branch-switch.ps1` | R8 (ADR 0233) — troca de branch no checkout MAIN que o Herd serve (`D:\oimpresso.com`) | ADR ratificada 2026-07-02 | **Manter .ps1 (exceção justificada)** | NÃO portar: o objeto protegido é a máquina Windows do Wagner (Herd local). Cross-platform não agrega — em Mac/Linux não existe o checkout servido. Portar = polir commodity |
| 22 | `mcp-first-warning.ps1` | Warn Read/Glob em memory/* → "use MCP" (2026-04-30) | 2 docs (só arquitetura, nenhuma mordida); **falso-positivo estrutural**: em worktree sem MCP conectado o fallback filesystem é LEGÍTIMO (how-trabalhar §Fallback) e o hook briga com ele | nudge-cosmético | **Propor aposentar** — coberto por skill `mcp-first` (Tier B) + brief injetado pelo #11. Decisão Wagner no PR de deleção |
| 23 | `preflight-new-capability.ps1` | Avisa ao criar capability nova sem checar existente (anti-reinvenção 2026-05-29) | **ZERO menções** em sessions/handoffs desde criação | nudge-cosmético | **Propor aposentar** — coberto por rule path-scoped `reuse-check.md` (`reuse:check`) + skill `como-integrar`. Decisão Wagner |
| 24 | `charter-validate.ps1` | Warn charter-fetch antes de Edit em Pages (C1 P0 Onda 4, 2026-05-13) | 6 docs mas todos de arquitetura; a promoção a bloqueador ("quando ROI provado") NUNCA aconteceu em ~2 meses | nudge-cosmético | **Propor aposentar** — vetor coberto por `block-mwart-violation.mjs` (bloqueador) + `block-ancora-no-olho.mjs` + skill `charter-first`. Decisão Wagner |

**Harnesses de teste:** `test-pii-redactor.ps1` e `test-block-destructive.ps1` morrem nos ports (substituídos por `.test.mjs` que RODAM no CI — os `.ps1` nunca rodaram no gate-selftest Linux). `test-all-hooks-smoke.ps1` encolhe a cada lote e morre no último. Os 9 `*.test.ps1` pareados seguem seus hooks.

## Plano de PRs (nenhum mergeado — Wagner decide)

| PR | Conteúdo | Linhas est. |
|---|---|---|
| 1 | Este doc (tabela da triagem) | ~120 |
| 2 | Port `pii-redactor` + `block-destructive` → `.mjs` (par US-COPI-085/086, mesmo bloco do settings.json; precedente 2-hooks/PR = #4028/#4035). Atualiza `grade.mjs` (referencia block-destructive.ps1) + CI + deleta harnesses .ps1 | ~550 |
| 3 | Port `block-memory-drift` → `.mjs` (o maior, 273 linhas — PR dedicado) | ~400 |
| 4 | Deleta `block-module-drift.ps1` + `.test.ps1` (morto de nascença — nunca registrado) | −250 |
| 5+ | Lotes A (SessionStart ×5), B (guardas de código ×6), C (comportamentais ×5) — 1 lote/PR, ≤300 linhas quando possível; cada lote atualiza settings.json + `test-all-hooks-smoke.ps1` + refs | fila |
| — | Aposentadorias #22/#23/#24: 1 PR por hook, justificativa individual, **só após Wagner aprovar** (deleção = mudança de enforcement) | fila |

## Ressalvas cumpridas

- settings.json atualizado no MESMO PR de cada port (hook registrado ≠ arquivo).
- Tests novos em `.test.mjs` → `gate-selftest` (`node --test .claude/hooks/`) pega automático; entrada explícita em `governance-script-tests.yml` segue o padrão #4035.
- Nenhuma deleção em lote cego: o único delete imediato (#4) nunca esteve registrado (provado por `git log -S`); os 3 nudges aguardam Wagner.
- Refs externas checadas: `warn-red-first`/`nudge-test-contract-anchor` são citados por `sdd-semana-0.js` (atualizar no lote B); `tier-a-banner` é verificado por `skills-index-generate.mjs` (atualizar no lote A); `block-merge-markers` tem irmão CI `merge-marker-scan.sh` (mantém).
