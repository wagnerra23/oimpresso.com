---
date: "2026-06-23"
topic: "Âncora improvada do oimpresso — design final (workflow 14 agentes: 6 designs → adversários → síntese → adversário final), nota honesta 67→~71, plano faseado fs-puro"
authors: [C]
type: session
module: governance
pii: false
related_adrs:
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0303-anchor-lint-wired-testado-sa-a2-bis
  - 0302-fonte-unica-doneness-anchor-aposenta-status-spec
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0298-teto-de-governanca-anti-proliferacao-gates
---

# Âncora improvada do oimpresso — design final

## TL;DR

Workflow ultracode (14 agentes: 6 designs por filosofias opostas → 6 adversários → síntese → adversário final), tudo grounded em `origin/main`. Veredito: **dá pra melhorar de 67 → ~71 (honesto, onda 1 entregue)**, e o design é **disciplinado** (adversário final: *"honesta e bem-aterrada — raro"*). O ganho é real mas **modesto**, e — crucialmente — o workflow é honesto que os ~13 pontos que faltam pro topo mundial (DO-178C 84) são **over-engineering** pro perfil de ERP brownfield de 5 devs + cliente único; ficam como **dívida rastreável** (que é o ponto inteiro de uma boa âncora). Tudo **fs-puro** (sem PHP/DB/git dentro do lint), estende gates existentes (ADR 0298), nasce advisory (ADR 0275).

## 0. O que os adversários MATARAM (não ressuscitar)

Quatro mecanismos apareceram em 4-5 designs mas foram refutados contra o código real:

1. **`#[CoversUS]` como atributo PHP** — inviável: os testes de NfeBrasil são **Pest closures** (`uses(Tests\TestCase::class)` + `it()`), `grep "extends TestCase"` = vazio. Atributo PHP não anexa a `it()`. → usar **`covers()` nativo do Pest + marcador grep `// @covers-us US-X`**.
2. **DoD `- [x] … → \`Class::metodo\``** — formato não existe em nenhum SPEC (DoD é prosa pura). → cortado.
3. **JUnit cruzado por método/US** — `junit-summary.mjs` corta o `::` e agrega **por arquivo**. → verde é provado **por-arquivo**, e só isso se promete.
4. **`modulos[]` derivado dos paths** — a convenção `Pages/<X>=Modules/<X>` quebra em ~1/3 do repo (`Pages/Cliente`≠`Modules/Crm`). → `modulos:` vira **array DECLARADO** validado por `existsSync`, não derivado. *(Corrige a hipótese anterior de "derivado".)*

Também cortado: git dentro do `anchor-lint` (viola o invariante fs-puro que matou `route:list`, ADR 0303); re-stamp que reescreve o SPEC.

## 1. A âncora improvada — formato final (3 camadas, fs-puras)

**1a. `**Implementado em:**` — inalterado.** `existsSync` + wired-check anti-zumbi (`anchor-lint.mjs:119-126`) já são teto mundial em prova-existência/prova-vivo. Não toca.

**1b. `**Testado em:**` — ganha 2 provas baratas:**
- **`(covers US-X)`** grep-checável: o teste escreve `covers(...)` + `// @covers-us US-NFE-010`; o `anchor-lint` estende `deadTestRefs` (`:144-162`) pra exigir que a US-ID case a do bloco-pai. Fecha a brecha real do `**Testado em:** \`SpatiePermissionsTest\`` (teste genérico que só existe em `Modules/Ponto` e não prova nada sobre NFe).
- **`verde@<job>`** (onda 2): só com `--junit <summary.json>`, lê o `junit-summary.json` que o CI **já produz** e marca o arquivo-de-teste como passed/failed. **`skipped != passed`.** Sem o flag → `behavior_unknown` advisory, nunca avermelha legado.

**1c. 6 campos do changelog — VIEW gerada (`changelog-gen.mjs`), zero dual-source:** quem-alterou DERIVADO do git · objetivo/caso-uso/teste/aceite no bloco SPEC · cliente via `> **Sinal:** CS-NNN` resolvido no MCP **só na view** (nunca no gate fs-puro). SoC dura: o gate que morde é fs-puro; rede só na view.

**1d. `modulos:`** — array DECLARADO no frontmatter, validado `existsSync(Modules/<X>)`. Resolve o multi-módulo do Wagner honestamente.

### Exemplo-ALVO — US-NFE-010 (multi-tenant Tier 0) — *estado-alvo, não atual*
```yaml
anchor_format: "v2"
modulos: [NfeBrasil, Fiscal, Financeiro]
```
```markdown
> **Objetivo:** automatizar cálculo ICMS/ST/IPI/PIS/COFINS/CBS/IBS na emissão     ← campo PROPOSTO
> **Sinal:** CS-LARISSA-FISCAL                                                       ← campo PROPOSTO
**Implementado em:** `Modules/NfeBrasil/Services/MotorTributarioService.php` · … · verificado@<sha7> (2026-06-23)
**Testado em:** `Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php` (covers US-NFE-010) · verde@modules-pest-nfe
```
> ⚠️ Pré-requisito real: hoje `SPEC.md:270` cita `MultiTenantIsolationTest` (sem prefixo) que o anchor-lint **já flagra como dead_test** (NfeBrasil tem **13 dead_tests** ao vivo). O arquivo real é `NfeBrasilMultiTenantIsolationTest.php`. **Reconciliar os dead_tests é pré-requisito de G1 — não dá pra plantar `covers` em teste-fantasma.**

## 2. Nota honesta (do adversário final, não a auto-nota inflada)

| # | Propriedade | Hoje | Final | Por quê |
|---|---|---:|---:|---|
| 1 | Verificável-máquina | 11 | 11 | grep + JSON.parse, git fora do lint, fs-puro preservado |
| 2 | Prova-existência | 12 | 12 | inalterado |
| 3 | Prova-está-vivo | 12.5 | 12.5 | wired anti-zumbi intacto — único no mundo |
| 4 | Prova-comportamento | 4 | **6** | `covers` (entregue, +2) fecha SpatiePermissionsTest; verde-por-arquivo (+2) é **contingente** da onda 2 |
| 5 | Bidirecional | 6 | **7.5** | `orfao_codigo` MVP (advisory, reusa renderGraph); req-sem-filho cortado (P2 brownfield) |
| 6 | Sobrevive-rename | 4 | 4 | **corte consciente** — v2/AST não vale 1-2d pro perfil |
| 7 | Não-gameável | 6 | **6.5** | `covers` mata teste-genérico; +1 extra é contingente do verde |
| 8 | Barata + grão | 11 | 10 | +covers + modulos/Objetivo/Sinal 2-3 linhas/US — custo modesto |
| | **TOTAL** | **67** | **~71** | onda 1 entregue ~70-71; ~72.5 com verde (onda 2); ~74 com sentinela SEFAZ (bloqueada) |

**Não alcança o DO-178C 84** — e o workflow argumenta que não deve (ver §4).

## 3. Plano faseado

- **Pré-requisito:** reconciliar os 13 dead_tests do NfeBrasil (1 módulo/PR, como foi o Financeiro — ADR 0303). Sem isso, `covers` planta em fantasma.
- **G1a — `covers`+`@covers-us` no anchor-lint** (estende `deadTestRefs:144-162`): ~1 dev-day. **Maior ROI, primeiro.**
- **G1b — verde-por-arquivo via JUnit** (onda 2): ~1-1.5d. Reusa `ci.yml:120`; precisa **adicionar `--log-junit` ao `modules-pest.yml`** (hoje não emite) + cruzar com a **nightly CT100 MySQL** (não o lane sqlite, que mascara bug MySQL-only).
- **`orfao_codigo` MVP** (bidirecional): ~0.5-1d.
- **`modulos:`/`Objetivo:`/`Sinal:` + `changelog-gen.mjs`**: ~1-1.5d.
- **Fixtures GT-G6** good/bad (`gate-selftest.mjs`): ~0.5d.
- **Sentinela SEFAZ** (onda fiscal própria, não bloqueia G1): padrão 2-tempos (FETCH rede→WARN / CHECK fs-puro), copiado de `mcp-drift-sentinel.mjs`. **Bloqueada:** não há API SEFAZ oficial de "NT vigente" — fonte = scrape frágil. Sem crédito de nota até existir.

**Tudo:** emenda ao **ADR 0303** (não 0273 — 0303 é dono do testado-check); estende `anchor-lint`+`anchor-drift.yml`+`modules-pest.yml` (zero workflow novo, ADR 0298); nasce advisory, no-new-lie diff-aware (grandfather legado); promoção a required = flip do Wagner por calendário (ADR 0275).

## 4. Onde ainda perde pro topo — e por que NÃO fechar agora

| Gap vs. 84 | Custo | Vale? |
|---|---|---|
| P6 rename-proof (path quebra no rename) | v2/AST PHP+TSX, 1-2d | **Não** — refactor de pasta é raro num ERP de 5 devs; `anchored_dead` é ruído visível, não mentira silenciosa |
| P4 8→12.5 (verde por-arquivo, não por-método) | reexecutar suite no gate (proíbe fs-puro) | **Parcial** — só cruzar com nightly CT100 vale (onda 2); reexecução não |
| P5 bidirecional cheio | inverter grafo full | **Não** — `req_sem_filho` avermelharia US legadas com teste informal |
| Quebra 3 SEFAZ | sentinela + fonte-de-verdade | **Eventual, isolado** — é a menos frequente das 4 quebras fiscais; teste-vermelho (a cada PR) e Tier-0-vazando (catástrofe) são piores e G1 os pega |

**Veredito de 1 linha:** o caminho que paga é **reconciliar dead_tests → G1a (`covers`) → G1b (verde via JUnit/CT100) → orfao_codigo MVP**, estendendo o que já existe, nascendo advisory. Leva 67 → ~71-74 honestos. Os 10 pontos pro 84 ficam como dívida rastreável documentada.

## 5. Veredito adversarial final

`grounded: true · honest_total: 71 · beats_current_67: true · beats_winner_84: false`. **Nada viola** fs-puro/Tier0/0298/0275/legado (cada um confirmado no código). Ressalvas: (a) não ler 72.5 como "entregue" — onda 1 = ~70-71, o resto é contingente; (b) o exemplo da US-NFE-010 é **estado-alvo** (`Objetivo:`/`Sinal:` são campos propostos, não existem hoje), não "real preenchido"; (c) over-engineering menor a vigiar: `Objetivo:` declarado tem prova fraca (prosa livre) e `modulos:` é metadado, não âncora de done-ness.
