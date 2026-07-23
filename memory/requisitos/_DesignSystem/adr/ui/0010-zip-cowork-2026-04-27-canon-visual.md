---
id: requisitos-design-system-adr-ui-0010-zip-cowork-2026-04-27-canon-visual
---

> ⚰️ **SUPERSEDED em 2026-06-06 por [UI-0018](0018-canon-visual-vivo-ds-v6-manual-identidade.md).**
> Este zip-snapshot de 2026-04-27 **deixou de ser canon visual** — a verdade viva é DS v6 + primitivos
> (ADR 0253) + Manual de Identidade. Preservado como **histórico** (append-only); NÃO copiar.

# ADR UI-0010 · Zip Cowork 2026-04-27 é canon visual; `os-page.jsx` é padrão canônico de tela list+detail

- **Status**: superseded · lifecycle: substituido
- **Substituído por**: [UI-0018 — Canon visual vivo DS v6 + Manual](0018-canon-visual-vivo-ds-v6-manual-identidade.md)
- **Data**: 2026-05-05
- **Decisores**: Wagner, Claude
- **Categoria**: ui · estruturante
- **Refs**: [UI Kit Cowork 2026-04-27](../../ui_kits/cowork-2026-04-27/README.md), [ADR raiz 0039](../../../../decisions/0039-ui-chat-cockpit-padrao.md), [UI-0008](0008-cockpit-layout-mae-do-erp.md), [UI-0009](0009-cockpit-sidebar-light-padrao.md), [ADR 0011 raiz](../../../../decisions/0011-alinhamento-padrao-jana.md)
- **Substitui parcialmente**: [UI-0006 — Padrão tela operacional](0006-padrao-tela-operacional.md) (template Jana-like) onde houver conflito visual com `os-page.jsx`
- **Convive com**: [UI-0009](0009-cockpit-sidebar-light-padrao.md) — sidebar light sobrevive (decisão Wagner explícita 2026-05-05; ver §Conflitos resolvidos)

## Contexto

Em 2026-04-27 Wagner abriu sessão de design no projeto Anthropic Cowork "Oimpresso ERP Comunicação Visual" e Claude produziu um protótipo HTML+React+CSS completo do Chat Cockpit (`Oimpresso ERP - Chat.html` + 12 `.jsx` + `styles.css` 3.308 linhas). Parte foi portada pro repo nas Fases 1-2 (`AppShellV2.tsx`, `Components/cockpit/*`, `cockpit.css` 1.348 linhas escopado em `.cockpit{}`), formalizada em [ADR raiz 0039](../../../../decisions/0039-ui-chat-cockpit-padrao.md) + [_DS UI-0008](0008-cockpit-layout-mae-do-erp.md).

Em **2026-05-05** Wagner reabriu o zip ("(2).zip", reexport do mesmo projeto Cowork) e declarou em sessão direta:

> "Eu prefiro o Zip, ele está correto, gostaria de aplicar esse layout nas outras telas. `os-page.jsx` (OS lista+detalhe) sendo o padrão canônico."

Essa declaração formaliza o zip como **fonte da verdade visual** pra portagens futuras e adota `os-page.jsx` como **padrão canônico de tela list+detail** (lista CRUD operacional com detalhe lateral, não master/detail tipo inbox).

## Decisão

### 1. Zip Cowork 2026-04-27 é canon visual

Snapshot importado integral em [`memory/requisitos/_DesignSystem/ui_kits/cowork-2026-04-27/`](../../ui_kits/cowork-2026-04-27/) (14 arquivos: 12 `.jsx` + `styles.css` + HTML entry + README). Todas as portagens visuais futuras devem comparar pixel-by-pixel com o snapshot ANTES de divergir. Divergir requer ADR específica.

### 2. Padrões canônicos por tipo de tela

| Arquivo do UI Kit | Padrão canônico de | Substitui (onde aplicável) |
|---|---|---|
| `os-page.jsx` ⭐ | **Tela list+detail** (CRUD operacional) | Pattern Jana ([ADR 0011](../../../../decisions/0011-alinhamento-padrao-jana.md)) **só na parte visual** — ADR 0011 sobrevive como pattern de **estrutura de módulo** (DataController hooks, modules_statuses.json, etc.) |
| `tasks.jsx` | **Inbox unificada master/detail** | UI-0006 (template tela operacional) onde for inbox de pendências cross-módulo |
| `viewers.jsx` | **Viewer de tarefa por tipo** | — (padrão novo, não havia equivalente) |
| `chat.jsx` | **Conversação** (lista + thread + composer + tabs) | Pages/Copiloto/Chat.tsx legado |
| `sidebar.jsx` | **Sidebar dual Chat/Menu** | Components/cockpit/Sidebar/* (já portado) |
| `linked-apps.jsx` | **Coluna direita Apps Vinculados** | Components/cockpit/LinkedApps (já portado) |
| `tweaks-panel.jsx` | **Vibe/Densidade/Accent** | Components/cockpit/TweaksPanel (já portado) |

### 3. Conflitos resolvidos

Várias decisões do repo são POSTERIORES ao snapshot 2026-04-27. **Sobreviventes (zip NÃO sobrescreve)**:

| Aspecto | Decisão posterior | Justificativa |
|---|---|---|
| **Sidebar light por padrão** | [UI-0009](0009-cockpit-sidebar-light-padrao.md) | Wagner em 2026-05-04: "branca é a correta muito mais linda"; ratificado em 2026-05-05: "manter sidebar". Sobrevive. |
| **CSS escopado em `.cockpit{}`** | `cockpit.css` no repo | Necessário pra não vazar pro Site/Cms/AppShell legado. |
| **AppShell legado removido** | session 2026-05-04 | Cockpit é shell único do ERP em React. |
| **Stack IA Vizra ADK rejeitada** | [ADR 0048](../../../../decisions/0048-vizra-adk-rejected.md) | Decisão técnica independente da UI. |
| **Sem "WR2 Sistemas" como persona em CLAUDE.md** | auto-mem `feedback_nao_anotar_clientes_em_claude_md` | CLAUDE.md é primer técnico, não CRM. |

### 4. Plano de portagem

**Não-meta deste ADR**: refazer todas as telas de uma vez. Cada portagem é um PR separado com seu próprio runbook (skill `cockpit-runbook` cobre o template).

Telas-alvo iniciais ranqueadas por prioridade:

1. **Pages/Officeimpresso/OS/* (a criar — ainda Blade)** — tela-piloto literal do `os-page.jsx`. Fase 2 do plano de migração ADR 0039.
2. **Pages/Tarefas/Index.tsx (a criar)** — usa `tasks.jsx` + `viewers.jsx`. Fase 4 do plano.
3. **Pages/Copiloto/Admin/{Custos,Governanca,Qualidade}/Index.tsx** — alinhar visual com `os-page.jsx`.
4. **Pages/Financeiro/*** — alinhar com `os-page.jsx`.
5. **Pages/Copiloto/Memoria.tsx** — possivelmente alinhar com `os-page.jsx`.
6. **Pages/Copiloto/Cockpit.tsx** — já segue `chat.jsx`; verificar pixel-fidelity em refresh futuro.
7. **Pages/Copiloto/Dashboard.tsx** — não é list+detail; manter padrão atual de cards-grid OU criar ADR específica se quiser refazer.
8. **Pages/Copiloto/Chat.tsx** — legado pré-Cockpit; possível candidato a deletar em favor de Cockpit.tsx.

Cada portagem cria um **session log** + **ADR de divergência** se necessário + **RUNBOOK** via skill `cockpit-runbook`.

## Consequências

### Positivas

- **Fonte visual única e imutável**: agora qualquer Claude ou dev sabe exatamente onde está o canon.
- **`os-page.jsx` desbloqueia portagens** que estavam paradas por falta de padrão claro (Officeimpresso, Project, Repair).
- **Conflitos com decisões posteriores ficam explícitos** na tabela §3 — sem ambiguidade.
- **Skill `cockpit-runbook` ganha referência**: gerador de RUNBOOKs já citava UI Kit; agora aponta pra arquivo concreto.

### Negativas / mitigações

- **2.000 linhas de CSS faltantes** (gap zip 3.308 vs repo 1.348) precisam ser portadas conforme cada tela for migrada. **Mitigação:** portar CSS por escopo de tela (não dump único), com escopo `.cockpit` mantido (UI-0008).
- **Snapshot envelhece**: zip é 2026-04-27, decisões posteriores criam exceções. **Mitigação:** tabela §3 deste ADR + tabela equivalente no [UI Kit README](../../ui_kits/cowork-2026-04-27/README.md). Quando snapshot for substituído, criar `ui_kits/cowork-YYYY-MM-DD/` novo + ADR substituindo este.
- **Risco de regressão visual** ao alinhar telas em prod: **Mitigação:** cada portagem em PR separado com smoke visual + screenshot comparativo zip ↔ repo.

## Alternativas consideradas

- **Manter status quo** (cada tela com seu padrão herdado) — rejeitada: caos visual ao escalar; cliente final reaprendendo a cada tela nova.
- **Refazer todo o CSS importando os 90 KB do zip** — rejeitada: vazaria pro Site/Cms/AppShell legado, viola escopo `.cockpit`. Proporcional à portagem mantém isolamento.
- **Reverter UI-0009 (sidebar light)** — rejeitada explicitamente por Wagner em 2026-05-05 ("manter sidebar").

## Validação pendente

- [ ] Portagem da 1ª tela usando `os-page.jsx` como canon (provável piloto: `Pages/Officeimpresso/OS/Index.tsx` ou `Pages/Copiloto/Admin/Custos/Index.tsx`) com screenshot comparativo.
- [ ] CHANGELOG do `_DesignSystem` atualizado.
- [ ] DESIGN.md raiz aponta pra UI Kit + este ADR.
