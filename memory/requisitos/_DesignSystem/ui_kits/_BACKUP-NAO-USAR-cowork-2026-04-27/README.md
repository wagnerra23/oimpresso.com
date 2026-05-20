---
slug: ui-kit-cowork-2026-04-27
title: "UI Kit — Snapshot Cowork 2026-04-27 (canon visual)"
type: ui_kit
status: canonical
date: 2026-04-27
imported: 2026-05-05
---

# UI Kit — Cowork "Oimpresso ERP Comunicação Visual" 2026-04-27

## Status

🎯 **Fonte da verdade visual canônica** — formalizado em [_DS UI-0010](../../adr/ui/0010-zip-cowork-2026-04-27-canon-visual.md).

Ratificado por Wagner em 2026-05-05 sessão (após comparação zip vs repo): "*prefiro o Zip, ele está correto, gostaria de aplicar esse layout nas outras telas. `os-page.jsx` (OS lista+detalhe) sendo o padrão canônico*".

## Origem

Snapshot do projeto Anthropic Cowork "Oimpresso ERP Comunicação Visual" exportado por Wagner em 2026-04-27 (zip `Oimpresso ERP Conunicação Visual. (2).zip`). Designer: Claude (Anthropic) em sessão Cowork direta com Wagner.

## Como usar

Os arquivos abaixo são **referência visual imutável**. NÃO são consumidos pelo build do repo. Servem pra:

- **Comparar pixel-by-pixel** com a tela atual no repo quando portar
- **Calibrar tokens, espaçamentos, microinterações** (atalhos, focus rings, animações)
- **Identificar componentes faltantes** (ex.: Pages/Tarefas/Index.tsx, Pages/Officeimpresso/OS/Index.tsx ainda em Blade)

## Arquivos canônicos

### Padrões canônicos por tipo de tela (UI-0010 §2)

| Arquivo | Tipo de tela | Aplicar quando refatorar |
|---|---|---|
| [`os-page.jsx`](os-page.jsx) (45 KB) ⭐ | **Lista + detalhe** (CRUD operacional) | Officeimpresso/OS, Repair, Project, Officeimpresso/Clientes/Produtos/Orçamentos/Vendas |
| [`tasks.jsx`](tasks.jsx) | **Inbox unificada** master/detail | Pages/Tarefas/Index.tsx (a criar — Fase 4 plano migração) |
| [`viewers.jsx`](viewers.jsx) | **Viewers de tarefa** por tipo | Pages/Components/Viewers/* (a criar) |
| [`chat.jsx`](chat.jsx) | **Conversação** (lista + thread + composer + tabs) | Pages/Copiloto/Cockpit.tsx (já portado parcialmente) |
| [`sidebar.jsx`](sidebar.jsx) | **Sidebar dual Chat/Menu** | Components/cockpit/Sidebar/* (já portado) |
| [`linked-apps.jsx`](linked-apps.jsx) | **Coluna direita Apps Vinculados** | Components/cockpit/LinkedApps (já portado) |
| [`tweaks-panel.jsx`](tweaks-panel.jsx) | **Vibe/Densidade/Accent** | Components/cockpit/TweaksPanel (já portado) |
| [`app.jsx`](app.jsx) | **Roteamento entre views** Cockpit | AppShellV2.tsx (já portado) |
| [`laravel-panel.jsx`](laravel-panel.jsx) | **Painel info Laravel** dev/superadmin | TBD |

### Suporte (não-canônicos, só pra rodar o protótipo)

- [`data.jsx`](data.jsx) (13.6 KB) — MOCK data conversas/tarefas
- [`data-os.jsx`](data-os.jsx) (12.7 KB) — MOCK data de OS
- [`icons.jsx`](icons.jsx) (5.5 KB) — Ícones inline (no repo: `lucide-react`, R-DS-003)
- [`Oimpresso ERP - Chat.html`](Oimpresso%20ERP%20-%20Chat.html) — Entry HTML pra rodar offline

### CSS canônico

- [`styles.css`](styles.css) (90 KB / 3.308 linhas) — CSS GLOBAL (selectors em `:root`, `*`, `body`)
   - Repo já portou ~1.348 linhas pra `resources/css/cockpit.css` com escopo `.cockpit{}`
   - **Gap:** ~2.000 linhas das classes específicas (`.os-row`, `.list-h`, `.viewer-*`, etc.) — proporcional às telas que ainda não foram portadas

## Como rodar offline (smoke visual)

1. Servir a pasta com qualquer HTTP server (ex.: `python -m http.server 8080` ou Live Server)
2. Abrir [`Oimpresso ERP - Chat.html`](Oimpresso%20ERP%20-%20Chat.html) no navegador
3. Babel-standalone transpila `.jsx` em runtime — não precisa build

Cuidado: ainda precisa de internet pros CDNs (React 18, Babel, Google Fonts IBM Plex).

## Decisões POSTERIORES ao snapshot que sobrevivem (NÃO sobrescrever pelo zip)

Tabela de exceções — onde o repo decidiu DEPOIS de 2026-04-27 e essa decisão sobrevive:

| Aspecto | Zip 2026-04-27 | Decisão posterior do repo | Status |
|---|---|---|---|
| Sidebar background | dark fixo (`oklch(0.21 0 0)`) | **light por padrão** (Wagner: "branca é a correta muito mais linda") | [_DS UI-0009](../../adr/ui/0009-cockpit-sidebar-light-padrao.md) **VENCE** |
| AppShell legado | coexiste com Cockpit | removido em 2026-05-04 | Repo vence |
| CSS scope | global (`:root`, `body`) | escopado em `.cockpit{}` pra não vazar pro Site/Cms | Repo vence |
| Stack IA Vizra ADK | canônico | **rejeitado** | [ADR 0048](../../../../decisions/0048-vizra-adk-rejected.md) **vence** |
| Cliente "WR2 Sistemas" no CLAUDE.md | mencionado | sem persona-cliente em CLAUDE.md | Repo vence (auto-mem `feedback_nao_anotar_clientes_em_claude_md`) |

Se outras divergências aparecerem ao portar tela X: criar ADR específica resolvendo o conflito ANTES de codar.

## Refs

- [_DS UI-0010 — Zip Cowork canon visual](../../adr/ui/0010-zip-cowork-2026-04-27-canon-visual.md) (esta importação foi formalizada lá)
- [ADR 0039 (raiz) — Chat Cockpit](../../../../decisions/0039-ui-chat-cockpit-padrao.md)
- [_DS UI-0008 — Cockpit layout-mãe](../../adr/ui/0008-cockpit-layout-mae-do-erp.md)
- [_DS UI-0009 — Sidebar light](../../adr/ui/0009-cockpit-sidebar-light-padrao.md)
- [Session 2026-04-27 — Protótipo Chat Cockpit](../../../../sessions/2026-04-27-prototipo-chat-cockpit.md)
- [Session 2026-04-28 — Design prototype Chat ERP](../../../../sessions/2026-04-28-design-prototype-chat-erp.md) — apendido do `memory-para-github/` do zip nesta importação

---

**Importado em:** 2026-05-05
