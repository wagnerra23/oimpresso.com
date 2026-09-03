---
slug: 0189-pageheader-canon-v3-1-cadastro-roxo
number: 189
title: "PageHeader canon v3.1 — bloco fechado + KPI strip separado + ⋮ overflow + roxo médio cadastro"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-24"
module: _DesignSystem
quarter: 2026-Q2
tags: [design-system, page-header, ui-canon, modern-saas, kpi-strip, overflow-menu]
supersedes: []
supersedes_partially: [0180-drift-numero-adr-0178-conflito-paralelo, 0182-pageheadertabs-canon-pattern-telas]
superseded_by: []
amended_by: [0190-primary-button-roxo-universal-295]
related:
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0104-processo-mwart-canonico-unico-caminho
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0180-drift-numero-adr-0178-conflito-paralelo
  - 0182-pageheadertabs-canon-pattern-telas
  - 0185-drawer-760-canon-entidades-cadastrais
  - 0187-constituicao-ui-v2-ponteiro-canon
  - 0190-primary-button-roxo-universal-295
pii: false
review_triggers:
  - Larissa biz=4 testar a v3.1 em prod e dar feedback negativo
  - 3+ outros pilotos pedirem variação visual incompatível
  - Migration Wave 2+ revelar problema arquitetural
---

# ADR 0189 — PageHeader canon v3.1 — bloco fechado + KPI strip separado + ⋮ overflow + roxo médio cadastro

> **AMENDED 2026-05-25 por [ADR 0190](0190-primary-button-roxo-universal-295.md):**
> Esta ADR introduziu primary roxo médio `oklch(0.55 0.15 295)` **só no escopo Cadastro**.
> ADR 0190 generalizou — roxo 295 vira PRIMARY UNIVERSAL pra TODOS os módulos. Hue per grupo
> (SIDEBAR_GROUP_HUE) continua existindo APENAS pra agrupamento visual no sidebar (não mais pro
> primary das telas).

> **ERRATA 2026-08-31** ([ADR 0377](0377-append-only-adr-excecao-por-label-emenda-0094.md) — cadeia de supersede quebrada): a [ADR 0190](0190-primary-button-roxo-universal-295.md) citada logo acima está **`superseded`** desde 2026-05-29, substituída pela [ADR 0235 DS v4](0235-ds-v4-accent-roxo-universal.md). A 0235 declara `supersedes: [0190]` mas **não menciona esta ADR nem a [0182](0182-pageheadertabs-canon-pattern-telas.md)** — então o efeito da morte da 0190 sobre as emendas que ela fazia nas duas nunca foi declarado, e esta ADR ficou emendada por uma ADR morta.
>
> **O que a errata resolve (e o que NÃO muda):**
>
> | | Estado |
> |---|---|
> | Primary roxo `oklch(0.55 0.15 295)` universal | ✅ **continua valendo** — a 0235 reafirma o mesmo valor; a morte da 0190 não o reverte |
> | Hue-per-grupo no primary das telas | ❌ **não volta** — foi o que a 0190 removeu e a 0235 manteve removido (é o que a errata [0260](0260-errata-0182-pageheader-cor-roxo-universal.md) já dizia pra 0182) |
> | Hue-per-grupo no sidebar (`SIDEBAR_GROUP_HUE`) | ⚠️ **autoridade morta, código vivo** — ver nota abaixo |
>
> ⚠️ **A linha do `SIDEBAR_GROUP_HUE` merece precisão, porque as duas leituras erradas são fáceis.** A [ADR 0235](0235-ds-v4-accent-roxo-universal.md) diz as duas coisas, e elas não se contradizem: o **item 2** declara que a autoridade da 0190 — *"primary roxo **e** a regra de hue-per-grupo da sidebar"* — deixa de valer; o **item 4** mantém o código *"por ora"* e delega a decisão de unificar ao Claude Design, **"sem mudança de código neste momento"**. Autoridade morta + implementação pendente, declarado honestamente.
>
> **Medido em 2026-08-31** contra `origin/main` @ `8796f3b431`: o `SIDEBAR_GROUP_HUE` **segue vivo** — definido em `resources/js/Components/cockpit/shared.ts:183` e consumido por `cockpit/Sidebar.tsx` (3 sites) e `shared/PageHeaderTabs.tsx`. E a [UI-0028](../requisitos/_DesignSystem/adr/ui/0028-sidebar-segue-o-prototipo-hue-295-supersede-parcial-0027.md) (accepted 2026-08-28) **não fecha esse adiamento**: ela fixa os tokens de superfície/texto do sidebar (`--sb-bg`/`--sb-text`/`--sb-border`/…) em hue 295, e não menciona `SIDEBAR_GROUP_HUE` nem hue-per-grupo — são eixos diferentes. **O "por ora" do item 4 segue aberto**, e fechá-lo é decisão [W] + Claude Design, não conserto de errata.
> | Layout v3.1 desta ADR (bloco fechado · KPI strip separado · ⋮ overflow · tabs abreviadas) | ✅ intacto — a emenda da 0190 era **só de cor** |

## Contexto

Sessão 2026-05-24 longa de iteração visual no header de `/contacts?type=customer` (Cliente/Index). Trabalho começou
descrevendo o header de `/financeiro/cobranca` (PR #1453 corrigiu border magenta) → aplicou padrão em Cliente
(PR #1454 primary ciano cadastro) → reorganizou tabs inline (PR #1455). Apesar de 3 PRs mergeados, Wagner reportou:
"olha que guerra para fazer esse topo, e na prática tu esquece coisas simples e estraga o layout. para padronizar
teria que realmente acertar o padrão. esquece os detalhes que deixa lindo."

Resposta foi escrever spec v3 inicial (cobertura 17 dimensões, 30 seções) + protótipo HTML standalone + diagrama SVG
em `prototipo-ui/prototipos/pageheader-canon-v3/`. Wagner validou o método ("entregar protótipo antes de codar"),
mas rejeitou todas 5 variantes do botão "Filtros avançados" propostas. Pedido: comparar com o REAL.

Inspeção do real (cowork-canon-financeiro-bundle.css medido ao vivo em
`https://oimpresso.com/cowork-preview/erp-shell-v2/Venda por Estagio FSM.html`) revelou que spec v3 estava
desalinhado com canon Cowork em **5 dimensões inteiras**:

1. Palette warm (hue 80) vs cool (hue 220) — temperatura oposta
2. Density compact (32px / 12.5 / 400) vs cozy (36px / 13 / 500) — escala diferente
3. System fonts vs IBM Plex — família tipográfica diferente
4. Primary azul-marinho `rgb(31,58,95)` vs ciano `oklch(0.55 0.15 202)` — peso muito diferente
5. Ghost sem ícone vs ghost-com-lupa — affordance diferente

Wagner escolheu família **B Modern SaaS** (não C Cowork puro, não A Warm corporate v3 refinada) com customizações:
- **Roxo médio `oklch(0.55 0.15 295)` como primary** (substitui ciano cadastro hue 202)
- **KPI strip em 4 cards branco frio** como bloco SEPARADO abaixo do header
- **Overflow `⋮` mantido** (contra meu palpite inicial) com Filtros + Importar + Exportar + Grupos dentro
- **"Filtros avançados" sai da Zona R** — vira item do menu overflow
- **Header bloco FECHADO** com border completa + radius 8px, NÃO grudado no KPI
- **Tabs com nomes ABREVIADOS** — "Fornecedores" → "Fornec.", "Funcionários" → "Equipe", "Representantes" → "Repr."

## Decisão

Adotar **PageHeader canon v3.1** como template oficial pra TODAS as Index Inertia/React do oimpresso (~80 telas),
com escopo de rollout faseado começando por Cliente/Index (biz=4 Larissa, validação 7 dias) e Financeiro/Cobranca
(biz=1 Wagner daily).

Especificação completa em **`memory/requisitos/_DesignSystem/templates/PageHeader-canon-v3-1.md`**.

Resumo da spec:

**Estrutura 3 blocos verticais separados** (gap 12px entre eles):
1. Header card (border-radius 8 + border completa)
2. KPI strip card (4 cards branco frio em grid 4 cols)
3. Conteúdo card (lista, tabela, etc)

**Header — geometria 3 zonas:**
- Zona L (`flex-1 min-w-0`): title 16px/600 + subtitle 12px tabular-nums
- Zona C (`shrink-0 self-stretch`): 5 tabs ghost com counter, ordem `[Todos, Clientes, Fornec., Equipe, Repr.]`
- Zona R (`ml-auto shrink-0`): `⋮` (overflow menu com Filtros + Dados + Configuração) + `+ Novo cliente` (primary roxo)

**Tokens canon (família B Modern SaaS):**
- Background: `#f8fafc` (slate-50) cool
- Card: `#ffffff`
- Text: `#0f172a` strong, `#334155` base, `#64748b` dim, `#94a3b8` faint
- Border: `#e2e8f0` soft, `#cbd5e1` mid
- **Primary: `oklch(0.55 0.15 295)` roxo médio** (família "pessoas" mental do Wagner)
- Primary-dark (border): `oklch(0.45 0.15 295)`
- Primary-soft (hover/bg active): `oklch(0.96 0.03 295)`
- Font: `ui-sans-serif, system-ui, -apple-system, "Segoe UI"`
- Tamanho/peso base: 12.5px / 400
- Height botões: 32px

> **ERRATA 2026-08-31** ([ADR 0377](0377-append-only-adr-excecao-por-label-emenda-0094.md) — ponteiros podres): o bloco de tokens acima é **retrato de 2026-05-24** e está preservado como história. **Dois itens dele não valem mais como instrução**, e implementar por eles hoje quebra:
>
> 1. **Os hex crus** (`#f8fafc` · `#ffffff` · `#0f172a` · `#334155` · `#64748b` · `#94a3b8` · `#e2e8f0` · `#cbd5e1`) descrevem os valores da paleta, **não a forma de escrevê-los**. Desde a [ADR 0263](0263-identidade-cor-gate-bloqueante.md) (2026-06-08) cor crua em CSS de tela é **catraca só-desce** e hex/família `-NNN` em `.tsx/.jsx` é barrado pelo `ui:lint` — ambos sob o job `DS gate`, **required**. Tela nova **herda token** (`var(--surface)`, `var(--foreground)`, `var(--border)`), nunca declara cor própria.
> 2. **A fonte.** A linha `Font: ui-sans-serif, system-ui, …` registra a escolha "família B Modern SaaS" desta sessão, que preteriu o IBM Plex (§Contexto item 3). Como **canon de tela**, foi revertida: o vivo é `--font-sans: "IBM Plex Sans", ui-sans-serif, system-ui, …` — 5 arquivos de `resources/css/` em `origin/main` @ `8796f3b431` —, e a [ADR 0244](0244-ds-v5-canon-oficina-padrao.md) (2026-06-02) registra que *"o roxo canon e o **IBM Plex** foram preservados"*.
>
>    ⚠️ **Mas a stack system sobrevive como exceção DECLARADA, e não é drift:** `resources/js/Components/PageHeader/PageHeaderPrimary.tsx:74` força `fontFamily: 'ui-sans-serif, system-ui, …'` inline no botão primary, com razão registrada no docblock (*"forçado inline (AP16 LEARNINGS)"*). Medido em 2026-08-31: é o **único** arquivo de `resources/js/` que faz isso. Quem "consertar" aquela linha achando que é resíduo desta ADR quebra o AP16 — o certo é reabrir o AP16, não trocar a fonte no site.
>
> **Fonte da verdade dos dois:** `resources/css/` (tokens gerados em `resources/css/tokens/`), não este bloco.

- Border-radius: 5px (botões) / 8px (cards)

**Hue per grupo: REVOGADO** (parcial — só pro Cadastro)
- ADR 0182 estabelecia hue per grupo (cadastro=202 ciano, financas=145 verde, etc).
- Roxo 295 vira primary do escopo `cadastro`, mas a decisão de "universal vs per grupo" fica em ABERTO até feedback de Larissa.
- Trigger pra revisar: depois de 7d de teste em prod, se Wagner gostar visualmente — promover roxo 295 como universal e revogar 0182 inteiro.

## Justificativa

**Por que família B (Modern SaaS) em vez de C (Cowork puro):**
- C era "zero ousadia" mas mantinha visual datado (warm bege + serif vibe corporativo dos anos 2010)
- B traz coerência com tendências 2026 (Linear, Stripe, Notion, Vercel) que clientes esperam ver
- C continuaria sendo legado bonito mas envelhecido; B sinaliza modernidade

**Por que roxo médio 295 em vez de ciano cadastro 202 ou azul-marinho cowork:**
- Wagner escolheu visualmente entre 4 calibres (médio, escuro saturado, vivo, pastel) — médio bateu
- Roxo é menos comum em ERP BR (Bling = azul, Tiny = azul, Omie = azul) — diferenciação visual de marca
- Hue 295 conecta com "pessoas/equipe" no modelo mental do Wagner (mesmo SIDEBAR_GROUP_HUE definindo `pessoas=88` verde-limão — Wagner sobrescreve com modelo mental próprio)

**Por que overflow ⋮ mantido (contra meu palpite inicial):**
- Inicialmente propus split-button "+ Novo cliente ▾" agregando ações secundárias
- Wagner preferiu manter `⋮` como container de ações de baixa frequência (Filtros + Importar + Exportar + Grupos)
- Razão implícita: usuário já está familiarizado com `⋮` em Gmail/Drive/Linear; mudar pra split-button confunde

**Por que blocos visuais SEPARADOS (3 cards) em vez de 1 card grande:**
- Header + KPI + lista no mesmo card = visual sobrecarregado, KPI parece "rodapé do header"
- 3 cards separados respiram visualmente, cada um tem identidade própria
- Permite reordenação futura (ex: KPI antes do header em telas de dashboard)

**Por que tabs ABREVIADAS:**
- Em viewport 1280px (Larissa) os nomes completos + counter não cabiam — quebra de linha indesejada
- `Fornec.` é abreviação canônica BR-ERP, `Equipe` é palavra completa (evita `Func.` ambíguo)
- `title="Fornecedores"` preserva acessibilidade (screen reader + hover tooltip)

## Consequências

**Positivas:**
- Template canon ÚNICO pras 80 telas Index — fim do "cada tela inventa o próprio header"
- Validação visual via protótipo standalone ANTES de codar (método novo, evita 3 PRs de retrabalho como aconteceu hoje)
- Tokens organizados em `:root` permitem mudança em 1 lugar afetar todo o app
- Hue roxo dá identidade visual diferenciada vs concorrentes BR
- Documentação em 5 níveis (ADR + SPEC + Charter + LEARNINGS + Protótipo) — cada audiência tem o doc certo

**Negativas / Trade-offs:**
- Migração de ~80 telas é trabalho (estimativa: 4 waves, ~3 semanas com IA-pair)
- Família B incompatível visualmente com `.cockpit` + `.fin-cowork` + `.cadastro-scope` existentes — precisa override
- Roxo 295 quebra ADR 0182 (hue per grupo) — coexistência temporária até decisão "universal vs per grupo"
- `cowork-canon-financeiro-bundle.css` continua válido pras telas que ainda não migraram — coexistência por algumas semanas

**Riscos mitigados:**
- Wave 1 piloto (2 telas, 2 dias) ANTES de migração em massa
- Feedback Larissa biz=4 antes de promover canon universal
- Protótipo standalone evita 3+ PRs de retrabalho como esta sessão
- LEARNINGS.md captura iterações sem ADR formal — permite "ir aprendendo" sem inflar memory/decisions

## Referências

- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (8 princípios duros)
- [ADR 0104](0104-processo-mwart-canonico-unico-caminho.md) — processo MWART (Blade→Inertia)
- [ADR 0114](0114-prototipo-ui-cowork-loop-formalizado.md) — loop Cowork ↔ Claude Code (origem do bundle Cowork canônico)
- [ADR 0180](0180-pageheader-canon-3-zonas.md) — PageHeader v1 (3 zonas) — agora superseded parcialmente
- [ADR 0182](0182-pageheader-canon-hue-per-grupo.md) — hue per grupo — superseded parcialmente (roxo 295 ignora hue per grupo no Cadastro)
- [ADR 0185](0185-drawer-760-canon-entidades-cadastrais.md) — drawer 760 entidades cadastrais (irmão deste canon)
- [ADR 0187](0187-constituicao-ui-v2-ponteiro-canon.md) — Constituição UI v2 ponteiro canon
- [memory/requisitos/_DesignSystem/templates/PageHeader-canon-v3-1.md](../requisitos/_DesignSystem/templates/PageHeader-canon-v3-1.md) — SPEC completa do template
- [memory/requisitos/_DesignSystem/templates/PageHeader-LEARNINGS.md](../requisitos/_DesignSystem/templates/PageHeader-LEARNINGS.md) — diário evolutivo (sessão 2026-05-24 + futuras)
- [prototipo-ui/prototipos/pageheader-canon-v3/](../../prototipo-ui/prototipos/pageheader-canon-v3/) — bundle de protótipos visuais (SPEC inicial v3 + 3-familias + b-v2-roxo-kpis)
- PRs desta sessão: [#1453](https://github.com/wagnerra23/oimpresso.com/pull/1453) (border magenta fix) · [#1454](https://github.com/wagnerra23/oimpresso.com/pull/1454) (primary ciano cadastro) · [#1455](https://github.com/wagnerra23/oimpresso.com/pull/1455) (tabs inline)
