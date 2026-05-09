---
slug: ui-kit-cowork-2026-05-09
title: "UI Kit — Snapshot Cowork 2026-05-09 (canon visual atualizado)"
type: ui_kit
status: canonical
date: 2026-05-09
imported: 2026-05-09
supersedes: ui-kit-cowork-2026-04-27
---

# UI Kit — Cowork "Oimpresso ERP Comunicação Visual" 2026-05-09

## Status

🎯 **Fonte da verdade visual canônica atualizada** — formalizado em [_DS UI-0012](../../adr/ui/0012-zip-cowork-2026-05-09-canon-visual.md).

Substitui [`cowork-2026-04-27/`](../cowork-2026-04-27/README.md) onde houver overlap. Telas novas (vendas, clientes, financeiro, produto, produção, inventário) **só existem aqui**.

## Origem

Snapshot do projeto Anthropic Cowork "Oimpresso ERP Comunicação Visual" exportado por Wagner em 2026-05-09 (zip `Oimpresso ERP Conunicação Visual. (1).zip`, 1.1MB / 156 arquivos). Designer: Claude (Anthropic) em sessão Cowork direta com Wagner.

Importação aconteceu **na mesma sessão** que rejeitou o batch F3 Financeiro do mesmo zip (ver [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md)). Os controllers/.tsx do `prototipo-ui-patch/` foram descartados; **só os assets visuais (.jsx referência + HTML + CSS + screenshots)** entram aqui.

## Como usar

Os arquivos abaixo são **referência visual imutável**. NÃO são consumidos pelo build do repo. Servem pra:

- **Comparar pixel-by-pixel** com a tela atual no repo quando portar
- **Calibrar tokens, espaçamentos, microinterações** (atalhos, focus rings, animações)
- **Identificar componentes faltantes** para portagens novas
- **Provar F2 visual approval** via screenshots (gate ADR 0107)

## Arquivos canônicos (atualizados)

### Padrões canônicos por tipo de tela (UI-0010 §2 + UI-0012 expandido)

| Arquivo | Tipo de tela | Aplicar quando refatorar |
|---|---|---|
| [`os-page.jsx`](os-page.jsx) (45.6 KB) ⭐ | **Lista + detalhe** (CRUD operacional) | Officeimpresso/OS, Repair, Project, Vendas, Clientes, Produtos |
| [`tasks.jsx`](tasks.jsx) | **Inbox unificada** master/detail | Pages/Tarefas/Index.tsx |
| [`viewers.jsx`](viewers.jsx) | **Viewers de tarefa** por tipo | Pages/Components/Viewers/* |
| [`chat.jsx`](chat.jsx) | **Conversação** (lista + thread + composer + tabs) | Pages/Jana/Chat.tsx (charter live em [`Chat.charter.md`](../../../../../resources/js/Pages/Jana/Chat.charter.md)) |
| [`sidebar.jsx`](sidebar.jsx) | **Sidebar dual Chat/Menu** | Components/cockpit/Sidebar/* |
| [`linked-apps.jsx`](linked-apps.jsx) | **Coluna direita Apps Vinculados** | Components/cockpit/LinkedApps |
| [`tweaks-panel.jsx`](tweaks-panel.jsx) | **Vibe/Densidade/Accent** | Components/cockpit/TweaksPanel |
| [`app.jsx`](app.jsx) | **Roteamento entre views** Cockpit | AppShellV2.tsx |
| [`laravel-panel.jsx`](laravel-panel.jsx) | **Painel info Laravel** dev/superadmin | TBD |

### Telas novas (não existiam em 2026-04-27)

| Arquivo | Tela alvo | Status no projeto |
|---|---|---|
| [`vendas-page.jsx`](vendas-page.jsx) (24.0 KB) + [`vendas-extras.jsx`](vendas-extras.jsx) (56.1 KB) | **Sells/Create + Sells/Index** | P0 da fila ([`TELAS_REVIEW_QUEUE.md`](../../../../../prototipo-ui/TELAS_REVIEW_QUEUE.md)) — pino F1 em [`sells-create/`](../../../../../prototipo-ui/prototipos/sells-create/) |
| [`clientes-page.jsx`](clientes-page.jsx) (10.3 KB) | **Cliente/Index** | P2 — sem charter ainda |
| [`producao-page.jsx`](producao-page.jsx) (14.8 KB) | **Repair/ProducaoOficina** | já em prod ([`Repair/ProducaoOficina/Index.tsx`](../../../../../resources/js/Pages/Repair/ProducaoOficina/Index.tsx)) — referência pra refator visual futuro |
| [`prod-page.jsx`](prod-page.jsx) (6.5 KB) + [`orc-page.jsx`](orc-page.jsx) (6.3 KB) | **Produto/Index + Orçamento** | P2-P3 — sem charter |
| [`produto-app.jsx`](produto-app.jsx) (60.9 KB) + [`produto-data.jsx`](produto-data.jsx) + [`produto-icons.jsx`](produto-icons.jsx) | **Produto/Unificado** (Catálogo) | P2 — controller candidato em `app/Http/Controllers/ProdutoUnificadoController.php` (do batch Cowork — usar como referência, não copiar literal) |
| [`financeiro-app.jsx`](financeiro-app.jsx) (48.6 KB) + [`financeiro-data.jsx`](financeiro-data.jsx) + [`financeiro-icons.jsx`](financeiro-icons.jsx) + [`financeiro-telas-extras.jsx`](financeiro-telas-extras.jsx) (35.7 KB) | **Financeiro/{Unificado, Fluxo, Conciliacao, DRE, PlanoContas}** | Unificado em prod; resto = pinos F1 em [`prototipo-ui/prototipos/financeiro-*/`](../../../../../prototipo-ui/prototipos/) — Cockpit V2 com decisões pendentes |

### Suporte (não-canônicos, só pra rodar o protótipo)

- [`data.jsx`](data.jsx) (13.6 KB) — MOCK data conversas/tarefas
- [`data-os.jsx`](data-os.jsx) (12.8 KB) — MOCK data de OS
- [`data-clientes.jsx`](data-clientes.jsx) (4.2 KB) — MOCK clientes
- [`data-vendas.jsx`](data-vendas.jsx) (3.9 KB) — MOCK vendas
- [`data-orc-prod.jsx`](data-orc-prod.jsx) (5.3 KB) — MOCK orçamento/produção
- [`icons.jsx`](icons.jsx) (6.6 KB) — Ícones inline (no repo: `lucide-react`, ADR ui/0003)

### CSS canônico

- [`styles.css`](styles.css) (120 KB) — CSS GLOBAL atualizado vs 2026-04-27 (90 KB, +30 KB de classes novas pras telas adicionais)

### HTMLs

| Arquivo | Função |
|---|---|
| [`Oimpresso ERP - Chat.html`](Oimpresso%20ERP%20-%20Chat.html) (2.2 KB) | Entry shell pra rodar protótipo offline |
| [`Financeiro Unificado.html`](Financeiro%20Unificado.html) (2.8 KB) | Shell financeiro carregando `financeiro-app.jsx` |
| [`Produto Unificado.html`](Produto%20Unificado.html) (2.9 KB) | Shell produto carregando `produto-app.jsx` |
| [`Inventario - Migracao Blade React.html`](Inventario%20-%20Migracao%20Blade%20React.html) (29.9 KB) ⭐ | **Standalone** — tela Inventário completa (sem .jsx externo). Pino F1 em [`inventario-migracao/`](../../../../../prototipo-ui/prototipos/inventario-migracao/) |
| [`Producao Oficina - Tela.html`](Producao%20Oficina%20-%20Tela.html) (46.4 KB) | Standalone Produção Oficina (já portada — `Repair/ProducaoOficina`) |

### Screenshots (F2 evidence)

5-6 PNGs em alta resolução cobrindo principais telas:

- [`screenshot-01-cockpit-overview.png`](screenshot-01-cockpit-overview.png) (50 KB)
- [`screenshot-02-cockpit-detail.png`](screenshot-02-cockpit-detail.png) (46 KB)
- [`screenshot-03-financeiro.png`](screenshot-03-financeiro.png) (170 KB)
- [`screenshot-04-producao.png`](screenshot-04-producao.png) (155 KB)
- [`screenshot-05-vendas.png`](screenshot-05-vendas.png) (127 KB)
- [`screenshot-06-produto.png`](screenshot-06-produto.png) (95 KB)

## Como rodar offline (smoke visual)

1. Servir a pasta com qualquer HTTP server (ex.: `python -m http.server 8080` ou Live Server)
2. Abrir [`Oimpresso ERP - Chat.html`](Oimpresso%20ERP%20-%20Chat.html) no navegador
3. Babel-standalone transpila `.jsx` em runtime — não precisa build

Cuidado: ainda precisa de internet pros CDNs (React 18, Babel, Google Fonts IBM Plex).

## Decisões POSTERIORES ao snapshot que sobrevivem (NÃO sobrescrever pelo zip)

Tabela herda + atualiza versão de [UI Kit 2026-04-27 §"Decisões posteriores"](../cowork-2026-04-27/README.md):

| Aspecto | Zip 2026-05-09 | Decisão posterior do repo | Status |
|---|---|---|---|
| Sidebar background | dark fixo | **light por padrão** (Wagner 2026-05-04: "branca é a correta muito mais linda") | [_DS UI-0009](../../adr/ui/0009-cockpit-sidebar-light-padrao.md) **VENCE** |
| AppShell legado | coexiste | removido em 2026-05-04 | Repo vence |
| CSS scope | global (`:root`, `body`) | escopado em `.cockpit{}` | Repo vence |
| Stack IA Vizra ADK | canônico | **rejeitado** | [ADR 0048](../../../../decisions/0048-vizra-adk-rejected.md) **vence** |
| Cliente "WR2" no CLAUDE.md | mencionado | sem persona-cliente em CLAUDE.md | Repo vence |
| **F3 Financeiro batch** (controllers + .tsx do `prototipo-ui-patch/`) | **WIP scaffold com 21 anti-padrões** | rejeitado pré-merge 2026-05-09 | [`LICOES_F3_FINANCEIRO_REJEITADO.md`](../../../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) **VENCE** |
| **`/financeiro/unificado`** (Controller + Page) | versão antiga (regrediria fixes) | em prod com fixes [#355](https://github.com/wagnerra23/oimpresso.com/pull/355) e [#358](https://github.com/wagnerra23/oimpresso.com/pull/358) | Repo **VENCE** (T-AP-4) |
| **`Modules\Produto`** assumption | assume namespace separado | UPOS canon = `App\Product`/`App\Variation` direto em `app/` | Repo vence |
| **Pages/Copiloto** namespace | usa "Copiloto" | atual = `Pages/Jana/*` (renomeado) | Repo vence |

Se outras divergências aparecerem ao portar tela X: criar ADR específica resolvendo o conflito ANTES de codar (regra ADR ui/0010 §3).

## Refs

- [_DS UI-0012 — Zip Cowork 2026-05-09 canon visual atualizado](../../adr/ui/0012-zip-cowork-2026-05-09-canon-visual.md) — esta importação foi formalizada lá
- [_DS UI-0010 — Zip Cowork 2026-04-27 canon visual](../../adr/ui/0010-zip-cowork-2026-04-27-canon-visual.md) — precedente
- [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) — 21 anti-padrões F3 catalogados
- [ADR 0114 (raiz) — Loop Cowork formalizado](../../../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- [ADR 0107 (raiz) — Visual gate F1.5](../../../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- [Cowork 2026-04-27 README](../cowork-2026-04-27/README.md) — versão anterior

---

**Importado em:** 2026-05-09
