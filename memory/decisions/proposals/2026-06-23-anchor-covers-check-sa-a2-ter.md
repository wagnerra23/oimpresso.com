---
title: "Anchor covers-check (SA-A2-ter) — `**Testado em:**` deve declarar a US que cobre, não só existir (estende ADR 0303)"
status: proposed
date: "2026-06-23"
decisores: [Wagner (aprova), Claude Code (autor)]
related_adrs:
  - 0303-anchor-lint-wired-testado-sa-a2-bis
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0302-fonte-unica-doneness-anchor-aposenta-status-spec
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
origem: "Wagner: 'a âncora é o passo mais importante — boas âncoras, bom sistema'. Workflow de design (14 agentes, 6 designs adversariais) elegeu o G1 (prova-comportamento) como maior alavancagem. Gap real medido: `Testado em:` prova que o teste EXISTE, não que COBRE a US — a brecha do `SpatiePermissionsTest` (teste genérico, só existe em Modules/Ponto, passa o existence-check sem provar nada sobre a US fiscal)."
prs: [3310]
---

# Anchor covers-check (SA-A2-ter): `**Testado em:**` declara a US que cobre

> **Estende** o [ADR 0303](../0303-anchor-lint-wired-testado-sa-a2-bis.md) (dono do testado-check), que estende o [ADR 0273](../0273-anchor-spec-codigo-formato-canonico-fluxo-novo.md). NÃO supersede nenhum.
>
> **✅ Código já landou e PROVOU que arma ANTES da ratificação** ([#3310](https://github.com/wagnerra23/oimpresso.com/pull/3310)) — doutrina Onda 0 / ADR 0303 ("cada brick prova que armou"): fixture good/bad + catraca no `gate-selftest.mjs` (GT-G6), gate-selftest **24/24**. Esta proposta formaliza a regra pra ratificação.

## Contexto

O testado-check do ADR 0303 (`dead_tests`) pega ref de teste que **não existe**. Mas deixa passar a mentira mais sutil: teste que **existe** e está sendo apontado por uma US **sem provar nada sobre ela**. Caso real (medido em `origin/main`): `NfeBrasil/SPEC.md:281` cita `**Testado em:** \`SpatiePermissionsTest\`` — esse teste só existe em `Modules/Ponto`, passa o existence-check global (não é `dead_test`), e não tem relação nenhuma com a US de NF-e. Em domínio fiscal, isso é grave: uma US de isolamento multi-tenant (R-NFE-001, Tier 0) pode ficar "testada" verde apontando um teste genérico que nunca exercita o isolamento.

A rubrica de âncora (8 propriedades) deu **prova-comportamento = 4/12.5** ao oimpresso — o menor de todos. É o gap que mais separa a âncora do topo mundial.

## Decisão

### 1. `**Testado em:**` ganha o marcador `covers`

Cada ref de teste numa linha `**Testado em:**` que **exista no disco** deve declarar, dentro do `.php`, `// @covers-us <US-ID>` casando a US do bloco-pai. `covers` = **marcador grep**, NÃO atributo PHP — os testes do repo são closures Pest (`uses(Tests\TestCase::class)` + `it()`), e atributo PHP não anexa a closure (refutado contra o código real pelo adversário do workflow de design). Forma canônica:

```
**Testado em:** `Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php` (covers US-NFE-010)
```
e no teste:
```php
// @covers-us US-NFE-010
it('prova o comportamento de US-NFE-010', function () { ... });
```

### 2. Estado novo `testado_sem_covers` (advisory)

`anchor-lint.mjs` (`testadoCoversMissing()`): teste que existe mas não declara `@covers-us` da US-pai → `testado_sem_covers`. **ADVISORY**: reportado sempre; exit 1 **só com `--check-covers`** (flag opt-in). Em produção o `anchor-drift.yml` roda `--check` normal → não morde. Nasce advisory (ADR 0271/0275).

### 3. Ratchet advisory → required (ADR 0275 §5)

| Fase | Critério |
|---|---|
| **F1 ADVISORY** (agora) | `--check-covers` existe + provado por fixture; `anchor-drift` reporta, não bloqueia |
| **F2 diff-aware no-new-lie** | pré-requisito: reconciliar os 13 `dead_tests` do NfeBrasil (não dá pra plantar `covers` em teste-fantasma); 14d advisory FP<5%; `--check-covers` diff-aware só no SPEC tocado |
| **F3 required** | flip do Wagner por calendário (ADR 0275), 3 medições/armamento, ≤1/semana |

## Pré-requisito declarado

Plantar `covers` exige primeiro **reconciliar os `dead_tests`** (refs de teste inexistentes) por módulo (1 PR/módulo, como o Financeiro — ADR 0303). NfeBrasil tem 13 dead_tests hoje. Sem isso, `covers` apontaria pra fantasma.

## Consequências

- ✅ Fecha a brecha "teste genérico apontado sem cobrir a US" — sobe prova-comportamento de 4 → ~6 (entregue) / ~8 (com G1b verde-por-arquivo).
- ✅ Tudo fs-puro (sem PHP/DB/git no lint), estende `anchor-lint`+`gate-selftest` existentes (zero workflow novo — teto ADR 0298).
- ⚠️ Authoring: +`covers()`/`// @covers-us` por teste citado (2-3 linhas). Custo modesto, grão segue 1 US/1 linha.
- ⚠️ NÃO prova teste **verde** (só que declara cobertura) — isso é o G1b (emenda separada, lê o JUnit que o CI já produz).

## Alternativas consideradas

1. **`#[CoversUS]` atributo PHP** — rejeitado: testes são closures Pest, atributo não anexa. Marcador-comentário grep é a mecânica real.
2. **Cruzar JUnit por método/US já** — adiado (G1b): `junit-summary.mjs` agrega por-arquivo, não por-método; verde-por-arquivo é a granularidade honesta.
3. **Nascer required** — viola ADR 0271/0275 e quebraria todo SPEC legado (nenhum tem `covers` hoje).

## Referências

- [#3310](https://github.com/wagnerra23/oimpresso.com/pull/3310) — implementação + fixture + gate-selftest 24/24
- `memory/sessions/2026-06-23-ancora-improvada-design-final.md` — design (workflow 14 agentes)
- `memory/sessions/2026-06-23-arte-ancora-changelog-notafiscal.md` — rubrica + benchmark (oimpresso 67, G1 maior alavancagem)
- ADR 0303 (testado-check) · 0273 (gramática) · 0275 (calendário advisory→required) · 0256 (gate-selftest GT-G6)
