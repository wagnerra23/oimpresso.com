---
title: "1 componente de tab-nav canônico + padrão de criação de componentes POR PAPEL"
status: proposed
date: "2026-07-15"
decisores: [Wagner (aprova), Claude Code (autor)]
related_adrs:
  - 0338-ds-lint-eixo-valor-token-fecha-por-forma
  - 0209-eslint-9-flat-config
  - 0258-processo-adr-estado-arte-indice-gerado-supersede-atomico
  - 0182-pageheadertabs-canon-pattern-telas
  - 0190-primary-button-roxo-universal-295
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
related_proposals:
  - 2026-06-11-arvore-componentes-canonica
origem: "Wagner 2026-07-15: 8 barras de abas de topo divergentes + dark quebrado por cor hardcoded em style inline que NENHUM gate pegava. 'fechar a CAUSA... o padrão de criação de componentes.'"
prs: ["este (registry + ADR + 4 máquinas)"]
---

# 1 componente de tab-nav canônico + padrão de criação de componentes POR PAPEL

> **Status: PROPOSTA.** Não é lei, não é ADR numerado. [CC] rascunha; **[W] decide, numera e aprova.** Soberania [W] (ADR 0238). Não mergeia sem Wagner.

## Contexto

O sistema tinha **8 componentes de "barra de abas de topo" divergentes** (a fita horizontal `Unificado · Pagar · Receber · ⋯` abaixo do título). Cada um hand-rolou o mesmo papel de um jeito ligeiramente diferente. Dois sintomas concretos:

1. **Radius errado** — alguém pôs `rounded-md` numa aba; o protótipo (`.cli-moduletopnav-tab`) é RETO (`border-radius: 0`, underline reto). Wagner pegou **no olho**.
2. **Dark quebrado** — cores/bordas hardcoded em `style={{}}` **inline** no TSX (ex: `borderBottomColor: 'oklch(0.93 0.004 90)'` — um tom claro). Isso **não é pego por nenhum gate**: o `prototipo-ui/cowork/conformance-gate.mjs` (cor-crua) e o stylelint `color-no-hex` só olham arquivos **`.css`**, nunca `style` inline de JSX/TSX; e as regras `ds/*` de className ([ADR 0338](../0338-ds-lint-eixo-valor-token-fecha-por-forma.md)) não olham o objeto `style`. Sombras (`box-shadow`) não tinham gate nenhum. Resultado: hardcode de tom claro num inline **quebra o modo escuro sem nenhum alarme**.

Consolidou-se num único componente — `resources/js/Components/shared/PageHeaderTabs.tsx`, fiel ao protótipo `prototipo-ui/cowork/clientes-page.css` `.cli-moduletopnav`. Mas **consolidar os casos não fecha a CAUSA**: a próxima feature hand-rola a 9ª barra e reintroduz o mesmo bug. A CAUSA é a **ausência de um padrão de criação de componentes por papel** + a **ausência de máquina** que veja cor inline.

Este é o mesmo diagnóstico do [ADR 0338](../0338-ds-lint-eixo-valor-token-fecha-por-forma.md) ("virar máquina quando 'sempre vai faltar isso'") e da [proposta árvore-componentes-canônica](2026-06-11-arvore-componentes-canonica.md) — esta proposta **estende** essa linhagem pro eixo "1 papel = 1 componente", não abre governança paralela.

## Decisão proposta

### D-1 · 1 componente de tab-nav canônico

O papel **"barra de abas de topo"** canoniza em **`PageHeaderTabs`** (`@/Components/shared`), consumido via o `*SubNav` do módulo (`FinanceiroSubNav`/`JanaSubNav`/`PontoSubNav`), que resolve ghosts + active a partir do `shell.menu`. Anti-pattern (não se escreve mais): `ModuleTopNav` · `PageHeaderModuleNav` · `FiscalModuleTopNav` · `role="tablist"` hand-rolado na tela. Registrado em `prototipo-ui/REGISTRY_DS_COMPONENTES.md` §"Navegação de página".

> **Errata [W] 2026-07-15** — `SubNav` (`@/Components/shared/SubNav`) **saiu** da lista anti-pattern acima. Revisão DS constatou que é **papel DISTINTO** (sub-navegação contextual in-page: `value`/`onChange`, sem `shell.menu`/`href`), não um hand-roll do tab-nav. Registrado como papel próprio (`sub-navegacao-contextual`) no REGISTRY §"Sub-navegação contextual" e em `ROLE_SIGNATURES`. O detector `--roles` deixou de marcá-lo como drift (era falso-positivo por proximidade de nome).

### D-2 · Padrão de criação de componentes POR PAPEL (a CAUSA)

Generaliza o "tripé" do [ADR 0338 §5](../0338-ds-lint-eixo-valor-token-fecha-por-forma.md) e da árvore-componentes. **Antes de criar um componente, pergunte qual PAPEL ele cumpre:**

1. **O papel já está no REGISTRY?** → **consuma o canônico. Não hand-role.** (Toda exceção é dívida que o `ds/*` cobra no PR.)
2. **Não está?** → é **buraco do DS**. Abre Onda e entrega o **tripé**: (a) impl em `@/Components/{ui,shared}` + (b) regra `ds/no-*` que substitui o hand-roll + (c) teste de **fidelidade** ao protótipo (+ story em `_Showcase`). Sem os três, não é "pronto" — é CSS órfão que a tela vai hand-rolar em volta.

**Corolário de cor (fecha o buraco do dark):** valor de cor mora em **token dark-aware** (`var(--accent)`, `var(--border)`, `var(--text)`, `var(--surface)`…), nunca hardcoded em `style` inline de tela. O componente canônico (camada DS) pode carregar o valor do token; a tela, não.

### D-3 · As 4 máquinas (enforcement — "virar máquina se sempre falta")

| # | Máquina | Onde | Tipo |
|---|---|---|---|
| a | **Detector de papel-duplicado** — agrupa componentes por papel e particiona canon / consumer (importa o canon) / independente (hand-roll); sinaliza clusters com independente>0 | `scripts/governance/component-registry-check.mjs --roles` (+ self-test) | advisory (exit 0; `--strict` p/ exit 1) |
| b | **`ds/no-inline-tablist`** — barra `role="tablist"` hand-rolada na tela → `<PageHeaderTabs>` | `eslint.config.js` (ratchet 0209) | component-substitute (lista curada, tipo 2 do 0338) |
| c | **`ds/no-inline-raw-color`** — cor/**borda**/**sombra** crua em `style={{}}` inline (rgb/rgba/hsl/hsla/oklch/oklab/lab/lch/color/hex). `var(--x)` não casa → saída correta. **É o buraco do dark** que faltava | `eslint.config.js` (ratchet 0209) | eixo valor-vs-token (tipo 1 do 0338), **surface novo (inline)** |
| d | **Spec de fidelidade protótipo↔componente** — renderiza `PageHeaderTabs`, assere as 4 propriedades-chave do `.cli-moduletopnav-tab.active` (radius 0 · `border-bottom-color: var(--accent)` · `background: accent-soft` · `font-weight: 600`). **Quebra se puser `rounded-md`** (provado por injeção → falha → revert) | `tests/pageHeaderTabsFidelity.spec.tsx` (vitest/jsdom) | fidelidade (controle-negativo ADR 0258) |

Máquinas (b) e (c) entram no **mesmo ratchet** do 0209 (`config/eslint-baseline.json`; `npm run lint:baseline:write` absorve a dívida atual; só o delta novo quebra `eslint-gate.yml`). Nenhuma nasce required — advisory por [ADR 0271/0314](../0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md) (required = só Tier-0 dinheiro/PII/multi-tenant/fiscal; DS é higiene/qualidade).

## Justificativa

- **(b)/(c) fecham por FORMA, não por enumeração** (doutrina 0338): (c) casa a **forma do valor** (qualquer função de cor ou hex) num surface novo (o `style` inline) — completo por construção pra esse surface, sem "sempre um leak atrás". (b) é component-substitute curado (o papel tem 1 substituto).
- **Ratchet = completude sem flag day**: as regras acendem no legado, o baseline perdoa a dívida, o gap só desce.
- **(d) trava o que o gate sintático não vê**: `rounded-md` é className (não pego por gate de cor); o render-test é o único que prova a fidelidade computada. Controle-negativo real (injeção provada) satisfaz o [ADR 0258](../0258-processo-adr-estado-arte-indice-gerado-supersede-atomico.md).
- **(a) surface o drift residual** honestamente: a consolidação NÃO migrou tudo — hoje (pós-remoção de `ModuleTopNav`/`FiscalModuleTopNav` e reclassificação do `SubNav` como papel próprio) o detector lista **1 independente vivo** (`Financeiro/Unificado/Index`) pra migração incremental, sem mentir "está tudo consolidado".

## Consequências

- Papel "barra de abas de topo" deixa de ter hand-roll novo (b) e cor inline nova (c) sem alarme; a fidelidade da aba ativa não regride (d); o drift residual fica visível (a).
- **Migração incremental** do independente restante (`Financeiro/Unificado/Index`) → `PageHeaderTabs`, por gate visual (fora do escopo desta proposta; catalogado pelo detector).
- **Ondas futuras (catalogadas, NÃO implementadas agora):** o detector de papel já rodou outros papéis suspeitos — **`status-badge`** (11 componentes hand-rolam o pill de status) e **`combobox`** (5 hand-rolam o dropdown de busca). Cada um vira Onda própria com o mesmo tripé (canon + regra `ds/no-*` + fidelidade). Entram como novas entradas em `ROLE_SIGNATURES` e no REGISTRY quando abrirem.
- **Residual honesto (fica humano/charter, não vira máquina):** nome de cor nu (`'white'`/`'red'`) em style inline não casa (ambíguo vs `transparent`/`inherit`/`currentColor`); template literal de cor (`` `oklch(${x})` ``) não casa (não é Literal); distinguir "aba de topo" de "aba dentro de painel" é heurística sintática, por isso (a) é report-only e (b) é ratchet — nenhum é gate cego. A confiança termina onde o AST/regex termina.

## Referências

- [ADR 0338](../0338-ds-lint-eixo-valor-token-fecha-por-forma.md) — DS lint fecha por forma (base emendada; (c) é o surface inline dela)
- [ADR 0209](../0209-eslint-9-flat-config.md) — ratchet ESLint 9 (baseline que absorve (b)/(c))
- [ADR 0258](../0258-processo-adr-estado-arte-indice-gerado-supersede-atomico.md) — todo ✅ tem que ter sido visto falhar (controle-negativo de (a) e (d))
- [proposta árvore-componentes-canônica](2026-06-11-arvore-componentes-canonica.md) — linhagem "camada UI vira pasta/enforcement"
- `prototipo-ui/REGISTRY_DS_COMPONENTES.md` §"Navegação de página" · `prototipo-ui/REGRAS_DS_LINT.md`
- `prototipo-ui/cowork/clientes-page.css` §Slot 2 `.cli-moduletopnav` — a fonte de fidelidade
