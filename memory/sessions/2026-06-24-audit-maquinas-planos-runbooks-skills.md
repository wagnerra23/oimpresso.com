# Auditoria das máquinas, planos, runbooks e skills — "juntar os planos"

**Data:** 2026-06-24 · **Origem:** Wagner — "auditar todas as máquinas e planos runbooks skills já está na hora" + 3 perguntas (PHP de correspondência? SPEC-viva precisa de índice de arquivos? como verificar saúde do sistema).
**Método:** workflow de 10 auditores read-only paralelos → síntese → adversário de completude (12 agentes, ~2.1M tokens). Output bruto em `tasks/w331ezhfz.output`.

> ⚠️ O adversário catalogou os **furos da própria auditoria** (ver §6) — esta é a versão honesta, não a triunfante.

## 1. O que foi FEITO nesta sessão (o fio que originou a auditoria)
1. **`prototipo-ui/detectar-telas.mjs`** criado — gate da Fase 0/0.5 do protocolo `aplicar-prototipo`: lê um bundle Cowork em staging e mapeia cada `arquivo→tela→alvo-no-repo` por 6 estratégias (path-espelhado → charter-irmão → repo-suffix → charter.component → ALIAS → órfão), classifica IDENTICO/ALTERADO/SEMANTICO/ALVO-PENDENTE/ORFAO/AMBIGUO, e **falha (exit 1) se sobrar mockup órfão** ("0 telas perdidas em silêncio"). Fixture hermético + `--selftest` travam o P0 (a tela Venda que duas versões em prosa perdiam).
2. **Armado no CI** — step advisory `detectar-telas --selftest` em [`design-memory-gates.yml`](../../.github/workflows/design-memory-gates.yml) + scripts `telas:detect`/`telas:selftest` no `package.json`. (Antes era gate-as-script fora de pipeline.)
3. **Duplicata REAL deletada** — `scripts/governance/plans-index-generate.mjs` (órfão de CI, gravava `_PLANS-INDEX-GENERATED.md` com underscore) era 100% duplicata de `plans-index.mjs` (vivo, na umbrella). Ambos "GERADOR determinístico do Índice de Planos Vivos (ADR 0294+0256)". Nascidos no PR #3092 ("sessão duplicada"). Sobrevivente: `plans-index.mjs`.

## 2. Resposta às 3 perguntas (com evidência)
**Q1 — existe PHP que lê arquivo e define correspondências?** SIM: `Modules/Jana/Services/CharterHealthChecker.php` lê o filesystem e estabelece **tela↔charter** (`charter_missing`: toda `.tsx` roteada exige `.charter.md` ao lado; `charter_refs_broken`: refs do charter existem). Roda no `jana:health-check`; espelhado 1:1 no node por `charter-refs.mjs` (gate **required**). **Mas opera só sobre o que já está commitado** — não faz `bundle→repo`. Logo o eixo de import (bundle Cowork→repo) **não tinha dono** → `detectar-telas` não duplica.

**Q2 — a SPEC-viva precisa de máquina sabendo onde está cada arquivo?** SIM, e o índice **já existe, FRAGMENTADO por eixo**. `detectar-telas` não duplica nenhum — é o passo ANTES (descobre o mapeamento de import que os outros pressupõem pronto).

**Q3 — verificar saúde + juntar os planos?** O esqueleto já existe: **`scripts/governance/governance-audit.mjs`** é o agregador "1 botão → 1 scorecard" (roda memory-health + gate-selftest + integrity-check + knowledge-drift + ds-guard + plan-health + jana:* e devolve `{ok, results[]}`). "Juntar os planos" = **consumir** ele numa view + plugar scorecards soltos. Não é construir; é consolidar.

## 3. Mapa das máquinas (camadas)
| Camada | Exemplos | Mede / faz |
|---|---|---|
| CI-gates required | ci, financeiro-pest, phpstan-gate, governance-gate(-umbrella), multi-tenant-gate, dominio-gate, casos-gate, charter-refs-gate, conformance-gate, a11y-axe-gate, e2e-gate, adr-lint | bloqueiam merge |
| CI-gates advisory/ratchet | screen-coverage, reconcile-triplet, contrato-de-tela, anchor-drift, design-memory-gates, dup-detector, plan-health-gate | reportam/ratcheteiam |
| scripts-índice (geram fonte-única) | adr-index-generate, **plans-index**, tasks-index-generate, shipped-log-generate | `--check` falha se commitado≠gerado |
| **scripts-correspondência** | anchor-lint (US↔código), charter-us-lint (charter↔US), screen-coverage-map (.tsx↔QA), charter-refs (.tsx↔charter), reconcile-triplet, **detectar-telas (bundle→repo · ÚNICO import-time)** | mapeiam onde-está-o-quê |
| artisan-health (PHP) | jana:health-check (5 SQL) + **CharterHealthChecker.php** + module:grade + governance:audit | saúde runtime |
| sentinelas (drift) | memory-health, knowledge-drift, mcp-drift-sentinel, protection-drift, plan-health | apodrecimento visível |
| **agregador-saúde** | **governance-audit.mjs** | ponto de entrada do painel único |
| SPEC-viva / âncora | SPEC `**Implementado em:**` (ADR 0273) + anchor-lint + doneness-lint + charter-live-signal | a spec aponta pra código real e vivo |

Números: **91 workflows CI · ~106 scripts .mjs · 222 comandos artisan · ~71 skills · 100+ runbooks.** Overlap_matrix completa (9 famílias) no output do workflow.

## 4. Os EIXOS DE "ÓRFÃO" (proposta de ADR — Wagner cunha o número)
Hoje "tela/ponteiro órfão" é detectado em **≥3 lugares com inputs diferentes, sem mapa** → risco de alguém reinventar um 4º/5º. Nomear num ADR curto:
| Eixo | Máquina | Pega |
|---|---|---|
| `Inertia::render → page viva` | `OrphanRenderGateTest` (runtime) | render aponta pra page que não existe |
| `charter → protótipo` | `charter-blueprint-pointers.mjs` | charter aponta pra protótipo inexistente |
| `bundle → alvo` (import) | **`detectar-telas.mjs`** | mockup do bundle não acha alvo no repo |
| `charter ↔ .tsx` | `CharterHealthChecker.php` / `charter-refs.mjs` | `.tsx` sem charter / ref quebrada |
| `US ↔ código` | `anchor-lint.mjs` | spec aponta pra código morto/zombie |

## 5. Recomendações restantes (ranqueadas)
- **[P1]** Mitigar 2ª fonte do `ALIAS` map do detectar-telas — derivar dos charters/contracts OU teste cruzando ALIAS × charter (risco de drift: mesmo par mockup→alvo pode divergir).
- **[P1]** ADR nomeando os eixos-de-órfão (§4).
- **[P1]** Painel único: consumir `governance-audit.mjs` numa view (Daily Brief / `/copiloto/admin/saude`) + plugar scorecards soltos (screen-coverage, design-identity, module-grade, sdd, anchor-coverage). `protection-drift.mjs` já é o watchdog do enforcement.
- **[P1]** Armar `charter-us-lint.mjs` num `.yml` (tem `--check` diff-aware, mas não está em workflow nenhum — correspondência charter→US não é gate hoje).
- **[P2]** Integração profunda do `detectar-telas` no `gate-selftest.mjs` (fixtures good/bad provando que o gate MORDE) — hoje só o `--selftest` interno roda no CI.
- **[P2]** Consolidar família governança/meta (governance-script-tests + guards-meta-gate + gate-selftest + steps em adr-index/umbrella) num `meta-gate` matrix.
- **[P2]** Fundir `reincidencia-guard.mjs` → `handoff-integrity-guard.mjs` (o próprio header admite a dup C3/C4).

## 6. Furos da PRÓPRIA auditoria (o adversário de completude)
- **Faltou cobrir:** `component-registry-check.mjs` (vizinho Cowork→componente mais próximo, gate ATIVO), `cowork-ssot-guard.mjs` (vive no mesmo workflow onde armamos o detectar-telas), `reuse-index.mjs` (índice símbolo↔arquivo — relevante ao "índice único"), `uc-derive.mjs` (UC↔teste), e **218 dos 222 comandos artisan** (lado PHP raso — só 4 vistos).
- **Erros factuais:** **91** workflows (não 92); a justificativa "a dup plans-index é o que o `dup-detector` previne" é **FALSA** (`dup-detector` = cross-PR hot-path; `jscpd` só `resources/js/**`; **nada** cobre dup em `scripts/**` → é GAP, não teatro); "detectar-telas órfão de pipeline" impreciso — é órfão de CI-gate mas já referenciado em skill + RUNBOOK (agora armado).
- **Follow-up honesto:** um 2º passo focado só no eixo **artisan (222 comandos PHP)** é o maior buraco em aberto desta auditoria.

## Refs
- [`detectar-telas.mjs`](../../prototipo-ui/detectar-telas.mjs) · [`RUNBOOK-aplicar-prototipo-orquestracao.md`](../../prototipo-ui/RUNBOOK-aplicar-prototipo-orquestracao.md) · skill `aplicar-prototipo`
- ADRs centrais: 0273 (âncora Implementado em) · 0297 (anchor-lint) · 0294 (planos vivos) · 0256 (knowledge survival) · 0264 (trio casos/dominio/e2e) · 0271/0275 (gates nascem advisory) · 0298 (anti-proliferação de workflow)
