---
slug: 0107-emendation-0104-visual-comparison-gate-f3
number: 107
title: "Emendation ADR 0104 — Visual comparison gate obrigatório em F3 (loop design supervisionado)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by:
  - W
decided_at: '2026-05-08'
quarter: 2026-Q2
related:
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0104-processo-mwart-canonico-unico-caminho
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0106-recalibracao-velocidade-fator-10x-ia-pair
emends:
  - '0104'
pii: false
---

# ADR 0107 — Emendation ADR 0104: Visual comparison gate em F3 (loop design supervisionado)

**Status:** ✅ Aceita
**Data:** 2026-05-08
**Decisão por:** Wagner Rocha
**Emenda:** ADR 0104 §F3 FRONTEND INCREMENTAL (não supersede — adiciona artefato + gate)

---

## Contexto

ADR 0104 formalizou o processo MWART canônico em 5 fases (PLAN → BACKEND → FRONTEND → QA → CUTOVER) com 3 camadas de enforcement (skill Tier A + hook + CI). A primeira aplicação real foi `/sells/create` (PRs #240..#248 em 2026-05-08) — entregou tela funcional em prod com 92/100 audit técnico.

Wagner reportou em 2026-05-08 (após o deploy):

> *"Na versão de Skin anterior o nível era o estado da arte de uma venda e depois ele considerava os requisitos que eu tinha no blade, aplicando o design no cockpit e várias etapas de supervisão ensinada pelo design. Por que mudou? Lá sim era muito superior ao feito aqui."*

Investigação git ([sessão 2026-05-08 tarde](../../sessions/2026-05-08-mwart-comparative-cycle.md)) identificou 3 práticas que sumiram da era pré-Constituição V2 (Repair S2.5, PRs #138-145):

1. **Sessão de design Cowork síncrona** — protótipo HTML+React iterado AO VIVO entre Wagner e Claude antes de codar (gerou canon `os-page.jsx` 1021 LOC em 2026-04-27)
2. **Loop "tela branca em prod → fix" como mecanismo de aprendizado** — 3 PRs corretivos sobre 4 telas (75% retrabalho), mas cada fix virou regra permanente de `mwart-quality` (Checks 1-9)
3. **Tabela comparativa Blade vs Cockpit vs Canon** — sempre IMPLÍCITA na cabeça do Wagner ("Blade feio, Cockpit bonito mas sem topnav") — nunca formalizada em arquivo

ADR 0104 codificou **gates técnicos** mas não **o loop visual humano**. Sells passou todos os gates técnicos (audit modo B 92, Pest 31 testes, build OK) mas perdeu o componente "design supervisionado" que era o coração da qualidade Repair.

## Decisão

Emenda ao ADR 0104 §F3 FRONTEND INCREMENTAL: **antes** de codar Page Inertia (US `<MOD>-003` skeleton), gerar artefato obrigatório `<tela>-visual-comparison.md` que captura **3 fontes lado-a-lado**:

| Coluna | Conteúdo |
|---|---|
| **Blade legacy** | Estrutura visual atual (header, campos, hierarquia) |
| **Canon Cockpit** | Como `os-page.jsx` (ou `tasks.jsx`/`chat.jsx`) trata mesmo padrão |
| **Decisão MWART** | O que vamos adotar — paridade, melhoria, ou exceção justificada |

8 dimensões obrigatórias (vide [TEMPLATE.md](../../.claude/skills/mwart-comparative/TEMPLATE.md)):

1. Layout (header, sidebar, topnav, footer, sticky elements)
2. Hierarquia visual (1 ação primária, 2 secundárias, hierarquia tipográfica)
3. Densidade (espaçamento, line-height, card-pad)
4. Iconografia (lucide vs emoji vs SVG vs ausente)
5. Estados (hover, focus, loading, empty, error)
6. Atalhos (J/K/E/A, /, Esc, ⌘+Enter — quais aplicáveis)
7. Persistência (localStorage prefixo `oimpresso.<mod>.<tela>.*`)
8. Componentes shared usados (PageHeader, EmptyState, KpiCard, DataTable)

### Loop síncrono Wagner-Claude (parte humana)

1. Skill `mwart-comparative` (Tier A — criada por este ADR) gera draft do `<tela>-visual-comparison.md`
2. **Skill PARA** e aguarda Wagner revisar (~5min síncrono)
3. Wagner aprova / ajusta dimensões / adiciona requisitos
4. Skill marca `status: approved` no frontmatter
5. SOMENTE ENTÃO Claude codifica a Page Inertia (US `<MOD>-003`)

Esse loop substitui a "sessão Cowork síncrona" que existia em Repair S2.5 — agora formalizado como artefato + gate.

### Gate CI (parte automática)

Workflow `.github/workflows/mwart-gate.yml` (já existe — emendado por este ADR) verifica em **toda PR que toca `resources/js/Pages/<Mod>/<Tela>.tsx`**:

1. ✅ `memory/requisitos/<Mod>/<tela>-visual-comparison.md` existe
2. ✅ Frontmatter `status: approved`
3. ✅ ≥6 das 8 dimensões preenchidas (≥6 linhas com Blade+Canon+Decisão)
4. ✅ Coluna "Decisão MWART" não tem `TODO` ou `???`

CI bloqueia merge se qualquer falha. Override via comentário PR `/mwart-override <razão>` registra exceção em ADR per-tela.

## Consequências

### Boas

- **Resultado visual previsível** — Wagner sabe exatamente o que vai sair antes de codar (não pós-facto)
- **Aprendizado preservado** — `<tela>-visual-comparison.md` vira ativo permanente do projeto. Próxima migração lê os anteriores como referência
- **Reduz retrabalho** — gaps visuais identificados ANTES de codar, não em PR corretivo
- **Onboarding novo dev** — lê 1-2 visual-comparison.md prévios, entende padrão sem precisar perguntar
- **Wagner volta ao loop** — explicita o ponto onde sua revisão é insubstituível (5min síncrono em F1.5)

### Ruins / mitigações

- **Adiciona ~30min ao processo F1→F3** (skill gera + Wagner revisa). **Mitigação:** evita ~2-4h de PRs corretivos pós-deploy (caso Sells: 5 PRs em 30min)
- **Pode virar burocracia se Wagner não reservar 5min** — fica gate vazio. **Mitigação:** template forçado tem ≥6 dimensões obrigatórias; vagas geram `TODO` que CI bloqueia
- **Skill inicial em 1 dimensão (form/list/master-detail)** — não cobre tudo. **Mitigação:** TEMPLATE.md evolui append-only conforme novos tipos aparecem

## Plano de aplicação

1. **Hoje (este PR):**
   - [x] ADR 0107 criado
   - [x] Skill `mwart-comparative/SKILL.md` Tier A
   - [x] TEMPLATE.md com 8 dimensões
   - [x] `.github/workflows/mwart-gate.yml` emendado com check
   - [x] CLAUDE.md adiciona `mwart-comparative` aos Tier A

2. **Próxima migração (US-SELL-007 ou outra):**
   - Skill ativa automático
   - Gera draft visual-comparison
   - Wagner aprova
   - CI gate verifica antes de mergear

3. **Refator retroativo Sells/create (opcional, follow-up):**
   - Gerar `Sells/sells-create-visual-comparison.md` retroativo
   - Wagner revisa
   - Identifica gaps (ex: topnav horizontal módulo, KPI cards top, sticky cart)
   - Refactor PR aplica mudanças

## Refs

- [ADR 0094 — Constituição V2](0094-constituicao-v2-7-camadas-8-principios.md) — §Loop fechado por métrica
- [ADR 0104 — Processo MWART canônico](0104-processo-mwart-canonico-unico-caminho.md) — emendado
- [ADR 0105 — Cliente como sinal + 3 graus regulação](0105-cliente-como-sinal-guiar-sem-mandar.md) — Wagner como ponto de supervisão
- [Skill `mwart-comparative`](../../.claude/skills/mwart-comparative/SKILL.md) — implementação
- [Skill `mwart-quality` Check 10](../../.claude/skills/mwart-quality/SKILL.md) — antecessor (vago)
- [Canon `os-page.jsx`](../requisitos/_DesignSystem/ui_kits/cowork-2026-04-27/os-page.jsx) — referência visual
- [Sessão 2026-04-27 Chat Cockpit prototype](../sessions/2026-04-27-prototipo-chat-cockpit-adr-0039.md) — origem do canon

## Designer

**Decisão por Wagner** em sessão 2026-05-08 após observar gap visual em Sells/create vs lembrança do processo Repair S2.5. Quote-chave gravado:

> *"Como deveria ser? Antes na versão Skin anterior tinha estado da arte + requisitos do Blade + design Cockpit + supervisão ensinada pelo design. Era superior."*

Esta emenda restaura o loop ausente sem destruir o formalismo do ADR 0104.

---

**Última atualização:** 2026-05-08
