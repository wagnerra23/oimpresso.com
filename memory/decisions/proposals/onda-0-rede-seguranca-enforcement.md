---
proposal_id: onda-0-rede-seguranca-enforcement
status: proposed
created: 2026-06-21
proposed_by: claude-code
decided_by: wagner
decided_at:
parent_adr: 0094 (Constituição v2)
related_adrs: [0093, 0066, 0175, 0212, 0273, 0275, 0279]
type: plano-execucao-enforcement
blueprint: memory/sessions/2026-06-21-blueprint-sdd-vertical-viva.md
verificacao: memory/sessions/2026-06-21-verificacao-rede-onda0-estado-real.md
---

# Proposta · Onda 0 — a rede de segurança (parar de quebrar antes de mudar)

> **Status:** 🟡 **PROPOSED 2026-06-21** — aguarda decisão do Wagner.
> Origem: Wagner, ao ver a deriva spec↔módulo, concluiu *"o sistema fica quebrando; primeiro garantir que não quebre de novo com uma estrutura melhor"*. Isto é a **Onda 0** do [blueprint](../../sessions/2026-06-21-blueprint-sdd-vertical-viva.md) aprovado. Estado real de cada peça verificado contra o código vivo em [verificação 2026-06-21](../../sessions/2026-06-21-verificacao-rede-onda0-estado-real.md).

## Contexto

O sistema "quebra de novo" (num_uf ×100k, regressões entrando no `main` sem barrar, specs derivando em pastas-fantasma) por **uma causa só**: as proteções existem mas são todas *advisory* — **medem e não governam**. **0 dos 17 gates required são SDD**; PRs vermelhos já entraram no `main` (#2761/#2848). Mexer na estrutura (limpar specs, religar módulos, vertical viva) antes de armar a rede é trocar o telhado com o detector de fumaça desligado. **Primeiro a rede.**

A verificação corrigiu o blueprint pra melhor: a camada de **medição já está feita** (floor 274 transportado CT100→main, [ADR 0279](../0279-sdd-medir-governar-floor-nightly.md); JUnit wired; `baseline-tamper-guard` mergeado #3128; `gate-selftest` com 5 catracas). Sobram **4 bricks pequenos** — sendo um a peça-chave que converte "mede" em "governa".

## O que "garantir que não quebra" significa (honesto)

Nenhuma estrutura dá 100%. O que esta dá é **monotônico**: *tudo que for travado por teste+catraca não regride mais em silêncio, e a área travada só cresce* (regra de armamento, [ADR 0275](../0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md) §3 — 3 medições reais antes de punir). Garantia que **acumula** em vez de **decair**.

## Decisão proposta

Executar a **Onda 0** como 4 bricks, cada um um PR pequeno que *prova* que armou uma peça (counterfactual), na ordem abaixo. Promover **`multi-tenant-gate` (Tier 0, [ADR 0093](../0093-multi-tenant-isolation-tier-0.md))** como o **1º gate SDD `required`** — a maior alavanca e a já-pronta. Nenhuma outra onda (limpeza spec↔módulo, vertical viva) começa antes da rede verde.

## Os 4 bricks

| # | Brick | O que falta (verificado) | Arquivos reais | Prova / counterfactual | Tamanho | Risco | Quem |
|---|---|---|---|---|---|---|---|
| **A** | **Oráculo do caminho do dinheiro** | `num_uf` consertado mas **sem teste**; +3h ([ADR 0066](../0066-format-date-shift-3h-preservado-legacy-clientes.md)) sem teste dedicado | novo `tests/Unit/Utils/NumUfTest.php` (trava `app/Utils/Util.php:31-104`); novo `tests/Unit/Helpers/FormatDateShiftTest.php` (trava `format_date` +3h vs `format_date_no_shift`/`format_now_local`) | reintroduzir o `str_replace` legacy → teste **falha**; remover o +3h → teste **falha** (força migration+aviso, não regressão muda) | ~200 LOC | nulo (só testes) | C |
| **B** | **Ligar pcov (coverage_pct real)** | `coverage: none`; sem pcov; sem gate de cobertura | `ci.yml:31-32,110` (add `pcov`, `coverage: pcov`, `--coverage-clover`); `phpunit.xml` (`<coverage>`); wire em `scripts/governance/sdd-scorecard.mjs` (tira `coverage_pct` de `not_yet_measured`) | scorecard passa a publicar nº real; arma após 3 medições (ADR 0275 §3) | médio | CI mais lento → job de coverage separado do job rápido | C |
| **C** | **Confirmar migration em prod** | máquina pronta (migration + snapshot 07:10 BRT live), aplicação em prod **não confirmada** | `Modules/Governance/.../create_mcp_sdd_scorecard_history_table.php`; `app/Console/Kernel.php:327` | `SELECT COUNT(*) FROM mcp_sdd_scorecard_history` ≥ 1 | trivial | SSH Hostinger = Tier 0 | **W** (Claude entrega o comando) |
| **D** | **1º gate SDD `required` + counterfactual** ⭐ | 0 required são SDD; `multi-tenant-gate` skip-as-pass pronto; `NoMissingTenantScopeRule` 117 no baseline | `multi-tenant-gate.yml`; `app/PhpStan/Rules/NoMissingTenantScopeRule.php`; `governance/required-checks-baseline.json` (+ branch protection) | PR-isca: query de Module sem `business_id` → gate **exit 1 / bloqueia**. 117 existentes ficam grandfathered no baseline; `baseline-tamper-guard` impede afrouxar no mesmo PR | pequeno (config) | **flip de branch protection = Wagner** (ADR 0275 §5: máx 1/semana) | C prepara + **W** flipa |

## Sequência recomendada

1. **A** (agora — seguro, trava o dinheiro, fundação dos outros).
2. **D** (em paralelo — *não depende de A/B/C*; o gate de tenant é determinístico e já tem baseline). É o keystone: a primeira vez que um PR-quebrado é **barrado**. Claude prepara o counterfactual + o diff de `required-checks-baseline.json`; **Wagner faz o flip** (1 vaga/semana).
3. **B** (pcov) — fecha a última medição que falta.
4. **C** (Wagner confirma a migration em prod) — a qualquer momento.

> Insight da verificação: como a medição já está feita, **a rota mais rápida pra "governa" é o brick D agora** (não esperar floor/pcov). Promover o gate de Tier 0 torna o **pior tipo de quebra (vazamento cross-tenant) impossível de mergear**.

## Kill-criteria

1. Counterfactual de D **não** dá exit 1 (gate não morde) → não promover; consertar o gate primeiro.
2. pcov estourar o tempo de CI a ponto de travar o fluxo → rodar coverage só no nightly, não no PR.
3. Promover D quebrar PRs legítimos por falso-positivo do `NoMissingTenantScopeRule` → voltar a advisory, ajustar a regra, re-tentar (demoção-via-decisão, ADR 0275 §5).
4. Qualquer brick exigir tocar Tier 0 (`business_id` scope no ORM) → pare (Tier 0 vence).

## Reversibilidade

Alta. A/B são aditivos (testes + config de CI). D é um flip de branch protection — reversível em 1 clique (demoção-via-ADR, ADR 0275 §5). C é leitura. Nada cria schema irreversível.

## Decisão a tomar pelo Wagner

- [ ] Aprovar a Onda 0 e a ordem (A → D → B → C), promovendo a ADR canon (Nygard, próximo nº livre), OU
- [ ] Ajustar: outro gate como 1º `required` (alternativas: `NoSilentFallbackRule` 104, ou um gate de scorecard), ou outra ordem, OU
- [ ] Rejeitar / pedir mais investigação.

> Recomendação: aprovar com **D = `multi-tenant-gate`** como 1º required — é Tier 0 (a quebra mais cara), já está pronto (skip-as-pass), e o baseline grandfathera a dívida existente sem travar o time.

## Reconciliação (2026-06-22) — de-confliction com programa paralelo

Reconferência pós-execução revelou que esta Onda 0 **sobrepôs um programa "armamento SDD" ativo e mais adiantado** (sessões paralelas Claude). Estado real consolidado:

| Brick | Resultado real |
|---|---|
| **A** · oráculo `format_date` +3h | ✅ genuíno — merged [#3178](https://github.com/wagnerra23/oimpresso.com/pull/3178) |
| **B** · pcov/coverage | ❌ **REVERTIDO** ([#3184](https://github.com/wagnerra23/oimpresso.com/pull/3184)) — duplicava [#3150](https://github.com/wagnerra23/oimpresso.com/pull/3150) (pcov CT100 + `measureCoverage` full-suite, fonte única) |
| **C** · migration scorecard em prod | ✅ feito — tabela `mcp_sdd_scorecard_history` aplicada + 1 row (snapshot 2026-06-21, composta 50) |
| **D** · ratchet → required | ⏭️ **não executado aqui** — [#3181](https://github.com/wagnerra23/oimpresso.com/pull/3181) (required-candidate) + [#3143](https://github.com/wagnerra23/oimpresso.com/pull/3143) (foundation-ratchet→required, DRAFT pré-14d) já cobrem. Agendamento de 29/jun **cancelado**. |

**Lição estrutural:** a checagem "tem outro fazendo isso?" tem que rodar **antes** de construir, mecanicamente — não por disciplina (o handoff [#3092](../../handoffs/2026-06-20-2115-sessao-duplicada-armamento-sdd.md) já avisava e a duplicação reincidiu). Proposta do mecanismo preventivo: [anti-duplicacao-work-claim-gate.md](anti-duplicacao-work-claim-gate.md).
