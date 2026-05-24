---
slug: 0187-constituicao-ui-v2-ponteiro-canon
number: 187
title: "Constituição UI v2 — ponteiro canon (hierarquia 4 camadas + regra-mestre + PT-01 + PRE-MERGE-UI)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-24"
accepted_at: "2026-05-24"
accepted_via: "Wagner aprovou explícito em sessão `frosty-greider-83ab2f` 2026-05-24 — comando exato: 'eu aporvo' (UI-0013) + 'eu realmente gosto como esta hoje. não gostaria de mudar' (sidebar opção A → UI-0014)"
module: design-system
quarter: 2026-Q2
tags: [constituicao-ui, design-system, governança, hierarquia-camadas, ui-0013, ui-0014, mcp-discoverable, ponteiro-adr, multi-agent]
supersedes: []
supersedes_partially: []
amends: []
superseded_by: []
related:
  - "0093-multi-tenant-isolation-tier-0"
  - "0094-constituicao-v2-7-camadas-8-principios"
  - "0104-processo-mwart-canonico-unico-caminho"
  - "0107-emendation-0104-visual-comparison-gate-f3"
  - "0114-prototipo-ui-cowork-loop-formalizado"
  - "0149-mwart-screen-pattern-reuse-cowork"
  - "0179-cliente-drawer-760px-substitui-show-fullpage"
  - "0185-drawer-760-canon-entidades-cadastrais"
charter_impact:
  - "CLAUDE.md raiz (Constituição UI v2 ganha seção Hierarquia UI + passo 4 protocolo)"
  - "Skill mwart-process v1.1 → v1.2 (Regra de ouro cita PT-01 + PRE-MERGE-UI)"
links_externos:
  ui_0013: "memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md"
  ui_0014: "memory/requisitos/_DesignSystem/adr/ui/0014-sidebar-light-mantida-v2-parcial.md"
  pt_01: "memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md"
  pre_merge_ui: "memory/requisitos/_DesignSystem/PRE-MERGE-UI.md"
  proposal_sidebar: "memory/decisions/proposals/2026-05-24-sidebar-dark-vs-light.md"
  ds_changelog: "memory/requisitos/_DesignSystem/CHANGELOG.md"
---

# ADR 0187 — Constituição UI v2 (ponteiro canon)

## Contexto

ADRs do design system vivem em `memory/requisitos/_DesignSystem/adr/ui/` (12 ADRs UI hoje, UI-0001 a UI-0014). A tool MCP `decisions-search` indexa `memory/decisions/*.md` raiz. ADRs UI ficavam **invisíveis** ao `decisions-search` mesmo sendo canônicas.

Esta ADR existe **só pra dar visibilidade MCP** à Constituição UI v2 — sem ela, o time MCP (Felipe, Maiara, Eliana, Luiz) e qualquer agente novo não descobriria UI-0013/0014 via `decisions-search "constituição"` ou `decisions-search "ui"`.

A Constituição UI v2 foi aprovada por Wagner em 2026-05-24 (sessão `frosty-greider-83ab2f`) após análise de handoff externo Claude Design v2:
- **ADR UI-0013** (aceita) — hierarquia 4 camadas Fundações→Shell→PT→Módulo + regra-mestre "pedido vago, pergunta antes" + vocabulário canônico
- **ADR UI-0014** (aceita) — confirmação opção A: sidebar permanece light (UI-0009 vence v2 ADR 0041 externa)
- **PT-01 Lista** — primeiro Padrão de Tela formalizado (12 telas-lista já aplicam)
- **PRE-MERGE-UI checklist** — anti-regressão por camada (AP1-AP8)

## Decisão

Adotar **Constituição UI v2** como mental model canônico do design system oimpresso, com este ADR servindo de **ponteiro indexável pelo MCP**.

### Hierarquia adotada (referência rápida)

```
┌─────────────────────────────────────────────────────┐
│  4 · MÓDULO          Modules/<X> · Pages/<X>        │  ← varia
├─────────────────────────────────────────────────────┤
│  3 · PADRÃO DE TELA  PT-01 Lista · (PT-02..05 TBD)  │  ← templates fixos
├─────────────────────────────────────────────────────┤
│  2 · SHELL           AppShellV2 · PageHeader        │  ← 1× pro app
├─────────────────────────────────────────────────────┤
│  1 · FUNDAÇÕES       tokens cor · tipo · espaço     │  ← imutável via ADR
└─────────────────────────────────────────────────────┘
```

**Princípio:** camada superior **herda** das inferiores e **nunca contradiz**. Conflito? Camada inferior vence (Fundações > Shell > PT > Módulo).

### Regra-mestre · pedido vago

Antes de tocar UI, agente verifica se pedido aponta:
- ✅ Camada (1-Fundações / 2-Shell / 3-PT / 4-Módulo)
- ✅ Artefato canônico aplicar
- ✅ Mudança específica

Se vago → **pergunta** antes de implementar (operacionalizado por skill `wagner-request-refiner` + agente `wagner-understand`).

### Sidebar permanece light

Conflito v2 (sidebar dark sempre) vs UI-0009 (sidebar light padrão) **resolvido por Wagner explícito 2026-05-24**: opção A — manter UI-0009. Formalizado em [UI-0014](memory/requisitos/_DesignSystem/adr/ui/0014-sidebar-light-mantida-v2-parcial.md).

## Docs operacionais (onde o time MCP busca)

| Doc | Path | Quando ler |
|---|---|---|
| **ADR-mãe UI-0013** | `memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md` | Antes de tocar qualquer arquivo UI |
| **ADR UI-0014 sidebar** | `memory/requisitos/_DesignSystem/adr/ui/0014-sidebar-light-mantida-v2-parcial.md` | Se for mexer em sidebar dark/light |
| **PT-01 Lista** | `memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md` | Antes de criar/editar tela-lista (Index.tsx) |
| **PRE-MERGE-UI** | `memory/requisitos/_DesignSystem/PRE-MERGE-UI.md` | Antes de abrir PR que toca UI |
| **README DS** | `memory/requisitos/_DesignSystem/README.md` | Visão geral + mapa 4 camadas |
| **Proposal sidebar** | `memory/decisions/proposals/2026-05-24-sidebar-dark-vs-light.md` | Histórico desempate |

## Enforcement — automatizado vs disciplina

Esta ADR não pretende ser CI lint. Enforça via combinação:

### Automatizado (CI / hooks / skills always-on)

- **CLAUDE.md raiz** (`@imports` no SessionStart) — passo 4 do protocolo aponta esta ADR
- **Skill `mwart-process` Tier A v1.2** — Regra de ouro cita PT-01 + PRE-MERGE-UI
- **Skill `preflight-modulo`** (hook bloqueador) — Edit em `Modules/<X>/` exige leitura prévia
- **Skill `multi-tenant-patterns` Tier A** — `business_id` scope (ADR 0093)
- **Module Grades Gate CI** — bloqueia PR se nota módulo baixar

### Disciplina humana / agente (não automatizado ainda)

- Aplicar PRE-MERGE-UI mentalmente antes do PR
- Reconhecer pedido vago e perguntar (regra-mestre)
- Não adicionar 6ª origin badge sem ADR
- Não tocar tokens Fundações em arquivo de módulo

### Próximos passos pra subir automação (lista priorizada)

| # | Mecanismo | Esforço | Quando |
|---|---|---|---|
| 1 | Skill `constituicao-ui-aware` Tier A description-match em Edit/Write em Pages/ | 30min | quando >2 agentes errarem na ordem |
| 2 | CI lint `php artisan ui:lint` — grep cor crua, ícone fora lucide, emoji em UI | 2h | quando primeira regressão real aparecer |
| 3 | Hook pre-commit local em Pages/ | 1h | opcional |
| 4 | Webhook GitHub → Slack/MCP-notif quando UI canônica muda | 4h | quando time MCP estiver maior |

## Consequências

### Positivas

- **`decisions-search` MCP** agora retorna esta ADR pra queries "constituição", "ui", "camadas", "design system" — time MCP descobre
- **`brief-fetch`** lista esta ADR em "decisões 24h" no dia da aprovação
- **Hierarquia explícita** acaba com pergunta recorrente "qual cor aplicar / qual padrão de tela / onde mexe"
- **Append-only mantido** — UI-0001..UI-0012 + UI-0013 + UI-0014 coexistem
- **Lacunas declaradas** (PT-02..PT-05, 11 hues v2, voice&tone) — agente sabe o que NÃO inventar

### Negativas

- ADR ponteiro duplica informação que já vive em UI-0013/0014 — risco de divergência se um for atualizado e outro não. Mitigação: linkam-se mutuamente, atualização sempre toca os 2 em mesmo PR.
- Convenção "ADRs UI em `_DesignSystem/adr/ui/` + ponteiro em `decisions/`" não está documentada em nenhum lugar — depende de Wagner+Claude lembrarem. Mitigação: linha em CLAUDE.md futura, ou ADR sobre "convenção indexação MCP".

### Neutras / a observar

- Time MCP descobrir esta ADR depende de chamarem `brief-fetch` ou `decisions-search` numa sessão — não tem notificação push hoje
- Próxima Claude Design vai propor sidebar dark de novo provavelmente — UI-0014 é a resposta canônica indexada
- Se `decisions-search` MCP for atualizado pra indexar `_DesignSystem/adr/ui/` direto, esta ADR vira redundante mas continua válida

## Pegadinhas conhecidas

- **Não atualizar UI-0013 sem atualizar esta ADR também** (ou vice-versa). Append-only — mudança vira nova ADR `supersedes: [0187]`.
- **PT-02 Form/Drawer** ainda não existe — quando criar, esta ADR ganha referência via amendment ou link em "Docs operacionais".
- **Constituição v2 backend (ADR 0094)** é coisa diferente da Constituição UI v2 (UI-0013). Nomes parecidos, escopos disjuntos. Esta ADR é só sobre UI v2.

## Referências

- ADR UI-0013: [`memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md`](../requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)
- ADR UI-0014: [`memory/requisitos/_DesignSystem/adr/ui/0014-sidebar-light-mantida-v2-parcial.md`](../requisitos/_DesignSystem/adr/ui/0014-sidebar-light-mantida-v2-parcial.md)
- PT-01 Lista: [`memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md`](../requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md)
- PRE-MERGE-UI: [`memory/requisitos/_DesignSystem/PRE-MERGE-UI.md`](../requisitos/_DesignSystem/PRE-MERGE-UI.md)
- README DS: [`memory/requisitos/_DesignSystem/README.md`](../requisitos/_DesignSystem/README.md)
- Proposal sidebar (decided): [`memory/decisions/proposals/2026-05-24-sidebar-dark-vs-light.md`](./proposals/2026-05-24-sidebar-dark-vs-light.md)
- Handoff externo Claude Design v2: 2026-05-24 (sessão chat8 projeto Cowork "Constituição UI v2")
- Sessão de aprovação: worktree `frosty-greider-83ab2f` 2026-05-24
