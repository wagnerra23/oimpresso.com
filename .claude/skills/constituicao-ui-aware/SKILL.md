---
name: constituicao-ui-aware
description: Use SEMPRE antes de Edit/Write em qualquer `resources/js/Pages/<X>/*.tsx`, `resources/js/Components/shared/**/*.tsx`, `resources/css/cockpit.css`, `resources/css/inertia.css`, ou ao criar tela nova no projeto. Carrega no contexto antes de codar — (1) Constituição UI v2 — hierarquia 4 camadas Fundações→Shell→PT→Módulo + regra-mestre "pedido vago, pergunta antes" ([ADR UI-0013](../../memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)), (2) Padrão de Tela aplicável (PT-01 Lista pros Index.tsx · drawer 760 pros Edit cadastrais), (3) anti-padrões AP1-AP8 do [PRE-MERGE-UI](../../memory/requisitos/_DesignSystem/PRE-MERGE-UI.md). Substitui leitura repetida desses 3 docs a cada sessão. ATIVA também quando user pede "criar tela X", "tela de Y", "Index do módulo Z", "PT-01 no módulo W", "drawer pra cadastro X", "tocar UI", "mudar visual de Y".
tier: B
auto_trigger: path
resumo: Constituição UI v2 + PT aplicável + PRE-MERGE-UI antes de Edit em Pages/Components/css ([ADR UI-0013](memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md))
recalibracao_nota: tier A->B 2026-07-09 (US-GOV-052 P31) — critério ADR 0225 (dispara por path Pages/Components/css, não é núcleo segurança/LGPD); banner e CLAUDE.md pós-0225 já não a listavam no núcleo.
status: active
version: 1.0
authority: canonical
---

# Skill: constituicao-ui-aware — Constituição UI v2 sempre-presente (Tier A always-on)

> **Documento mãe:** [ADR UI-0013 Constituição UI v2](../../memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) (aceita 2026-05-24 · canônica).
> Esta skill é gancho de atenção. **Não substitui a leitura completa** dos docs — força agente a NÃO esquecer o framework.

## Quando ativa

Description-match em:

- `Edit` ou `Write` em `resources/js/Pages/<Modulo>/*.tsx` (toda tela Inertia)
- `Edit` ou `Write` em `resources/js/Components/shared/**/*.tsx` (componente compartilhado)
- `Edit` ou `Write` em `resources/css/cockpit.css` ou `resources/css/inertia.css` (Fundações)
- Pedido contendo "criar tela", "Index do módulo", "PT-01 / PT-XX", "drawer pra cadastro", "mudar visual", "novo Page Inertia"
- Pedido contendo "tocar UI", "mexer na sidebar", "alterar header", "novo componente compartilhado"

## Hierarquia 4 camadas (lembrete cirúrgico)

```
┌─────────────────────────────────────────────────────┐
│  4 · MÓDULO          Pages/<X>/, Modules/<X>/       │  ← varia
├─────────────────────────────────────────────────────┤
│  3 · PADRÃO DE TELA  PT-01 Lista · (PT-02..05 TBD)  │  ← templates fixos
├─────────────────────────────────────────────────────┤
│  2 · SHELL           AppShellV2 · PageHeader        │  ← 1× pro app
├─────────────────────────────────────────────────────┤
│  1 · FUNDAÇÕES       tokens cor · tipo · espaço     │  ← imutável via ADR
└─────────────────────────────────────────────────────┘
```

**Princípio:** camada superior **herda** das inferiores e **nunca contradiz**. Conflito? Camada inferior vence.

| Você tocando… | Camada | Não pode mudar |
|---|---|---|
| `Pages/<X>/Index.tsx` | 4-Módulo | Tokens · shell · slots do PT aplicável |
| `Components/shared/PageHeader.tsx` | 2-Shell | Tokens |
| `cockpit.css` `:root { --tokens }` | 1-Fundações | **NADA sem ADR aprovada** |
| Nova ADR UI-NNNN | 6-Decisões | Frontmatter Nygard + lifecycle |

## Regra-mestre · pedido vago

Antes de codar, verifica se pedido aponta:

- ✅ **Camada** (Fundações / Shell / PT / Módulo)
- ✅ **Artefato** canônico aplicar
- ✅ **Mudança específica**

Se vago → **PARA e PERGUNTA**. Exemplos:

| Pedido vago ❌ | Resposta · pergunta ✅ |
|---|---|
| "faz uma tela de cobranças" | "Qual camada? PT-01 Lista? Quais colunas?" |
| "muda a cor pra ficar igual fiscal" | "Token Fundações específico? `--origin-FIN-bg`?" |
| "deixa bonito" | "Que regra do KB-9.75? Score atual + alvo?" |
| "moderniza" | "Camada + mudança específica · referência canônica?" |

Operacionalizada por skill [`wagner-request-refiner`](../wagner-request-refiner/SKILL.md) (Tier B reactive) + agente [`wagner-understand`](../../.claude/agents/) — esta skill cita a regra mas o refiner aplica.

## PT aplicável por tipo de arquivo

| Você criando/editando | PT aplicável | Lê **antes** de codar |
|---|---|---|
| `Pages/<X>/Index.tsx` | **PT-01 Lista** | [PT-01-Lista.md](../../memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md) — 6 slots: PageHeader · ModuleTopNav · Toolbar · BulkBar · DataTable · Drawer |
| `Pages/<X>/Edit.tsx` / `Create.tsx` cadastral | PT-02 (TBD) → usa drawer 760 [ADR 0185](../../memory/decisions/0185-drawer-760-canon-entidades-cadastrais.md) | ADR 0185 · padrão drawer existente em `Pages/Cliente/_drawer/` |
| `Pages/<X>/Show.tsx` | PT-03 Detalhe (TBD) | Justificar caso a caso · drawer 760 substitui Show em entidades cadastrais [ADR 0179](../../memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md) |
| `Pages/Home/*` ou dashboards | PT-04 Dashboard (TBD) | Sem template canônico ainda · cuidado pra não inventar 6º slot |
| `Pages/Settings/*` | PT-05 Config (TBD) | Idem |

**Se PT-XX não existe ainda** → use drawer 760 / patterns vigentes em módulo similar · documente desvio · proponha ADR se 2+ módulos pedirem mesmo template.

## Anti-padrões PRE-MERGE-UI (camada 4 · Módulo)

Lê completo em [PRE-MERGE-UI.md](../../memory/requisitos/_DesignSystem/PRE-MERGE-UI.md). Resumo:

| ID | Anti-padrão | Como detecta hoje |
|---|---|---|
| **AP1** | Cor hardcoded (`#hex`, `bg-blue-500`) em arquivo Page | `php artisan ui:lint` Onda 1.2 + manual rg |
| **AP2** | Componente reinventado · sem importar do shared (`PageHeader`, `DataTable`, `BulkActionBar`, `EmptyState`, `StatusBadge`) | Manual review · grep imports |
| **AP3** | `localStorage` sem prefixo `oimpresso.<modulo>.*` ([ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)) | rg `localStorage\.(get/set)Item\(['"](?!oimpresso\.)/` |
| **AP4** | Ícone fora `lucide-react` ([UI-0003](../../memory/requisitos/_DesignSystem/adr/ui/0003-lucide-react-como-unica-iconografia.md)) | `ui:lint` regra R2 (FontAwesome) |
| **AP5** | Gradient decorativo 135deg (bluish-purple) | Manual review · sem grep ainda |
| **AP6** | Emoji em UI de produto (lucide icon, não emoji) | `ui:lint` regra R3 |
| **AP7** | Status badge com `bg-fill` — Stripe-style usa dot + texto colorido | Manual review |
| **AP8** | Copy não-PT-BR em label/erro/mensagem | Manual review · sem grep ainda |

## Sidebar permanece light · NÃO mudar pra dark

[ADR UI-0009](../../memory/requisitos/_DesignSystem/adr/ui/0009-cockpit-sidebar-light-padrao.md) + [ADR UI-0014](../../memory/requisitos/_DesignSystem/adr/ui/0014-sidebar-light-mantida-v2-parcial.md) — Wagner explícito 2026-05-24. Constituição UI v2 externa propõe sidebar dark sempre · **NÃO aplicar** no oimpresso. Próxima Claude Design lendo v2 vai propor dark de novo · esta skill é a resposta canônica.

## Workflow obrigatório (resumido)

```
1. Identifica camada que vai tocar
2. Se vago → PERGUNTA antes de codar (regra-mestre)
3. Lê PT aplicável (PT-01 pros Index, drawer 760 pros Edit cadastral)
4. Edit/Write seguindo slots + tokens semânticos + componentes shared
5. Antes do PR: roda mental PRE-MERGE-UI checklist camada 4 (AP1-AP8)
6. Se baseline `php artisan ui:lint` introduz violações → fix antes do commit
```

## Pegadinhas conhecidas

- **Tokens semânticos** sempre — `bg-accent`, `text-foreground`, `border-border` · NÃO `bg-blue-500`
- **`localStorage` prefixo `oimpresso.<modulo>.*`** — multi-tenant Tier 0 ([ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))
- **`Inertia::defer` em props pesadas** — ([skill `inertia-defer-default`](../inertia-defer-default/SKILL.md)) SPA-feel real
- **Charter `.charter.md` ao lado do `.tsx`** — ([skill `charter-first`](../charter-first/SKILL.md)) Tier A dormente S4
- **Sub-tabs como ghost** (sem cor primária no action button) — [ADR 0040](../../memory/decisions/0040-modulos-densos-sub-tabs.md)

## O que esta skill NÃO faz

- ❌ Não substitui leitura completa de UI-0013 (linka, não copia)
- ❌ Não substitui PT-01-Lista.md (linka, não copia)
- ❌ Não trava Edit/Write (gancho de atenção, não bloqueador — pra isso ver `preflight-modulo`)
- ❌ Não roda `ui:lint` automático (esse é Onda 1.2 do roadmap, sob demanda)
- ❌ Não decide pedido vago (apenas alerta · regra-mestre força agente perguntar)

## Refs

- **ADR-mãe UI-0013:** [`memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md`](../../memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)
- **ADR UI-0014 sidebar:** [`memory/requisitos/_DesignSystem/adr/ui/0014-sidebar-light-mantida-v2-parcial.md`](../../memory/requisitos/_DesignSystem/adr/ui/0014-sidebar-light-mantida-v2-parcial.md)
- **PT-01 Lista canon:** [`memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md`](../../memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md)
- **PRE-MERGE-UI checklist:** [`memory/requisitos/_DesignSystem/PRE-MERGE-UI.md`](../../memory/requisitos/_DesignSystem/PRE-MERGE-UI.md)
- **Ponteiro MCP indexável:** [ADR 0187](../../memory/decisions/0187-constituicao-ui-v2-ponteiro-canon.md)
- **AUTOMATION-ROADMAP:** [`memory/requisitos/_DesignSystem/AUTOMATION-ROADMAP.md`](../../memory/requisitos/_DesignSystem/AUTOMATION-ROADMAP.md) — esta skill é Onda 1 Item 1.1
- **Skills correlatas Tier A:** `mwart-process` v1.2 (cita PT-01) · `multi-tenant-patterns` · `commit-discipline` · `preflight-modulo`
- **Skills correlatas reactive:** `wagner-request-refiner` (regra-mestre) · `pageheader-canon` (auto-validate POST)

## Versão

**v1.0** · 2026-05-24 · primeira versão · Onda 1 Item 1.1 do [AUTOMATION-ROADMAP](../../memory/requisitos/_DesignSystem/AUTOMATION-ROADMAP.md).

**Bump v1.1** quando PT-02..PT-05 forem documentados — atualizar tabela "PT aplicável por tipo de arquivo".
**Bump v2.0** se UI-0013 for superseded (breaking).
