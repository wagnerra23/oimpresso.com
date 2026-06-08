# ARQUITETURA.md
# Mapa único Cowork ↔ Repo · 2026-05-18
# Os arquivos têm OS MESMOS NOMES nos dois lados.

## CSS · 12 arquivos escopados (3 KB-9.75 + 9 módulos)

| Arquivo            | Escopo                                | Quando carrega |
|--------------------|---------------------------------------|----------------|
| `styles.css`       | tokens · shell · sidebar · drawer base| sempre · core  |
| `vendas.css`       | `.vd-*` `.vendas-aplus` FSM venda     | sempre         |
| `financeiro.css`   | `.fin-*` FSM fin · drawer abas        | sempre         |
| `compras-page.css` | `.compras-*`                          | sempre         |
| `crm-page.css`     | `.crm-*`                              | sempre         |
| `inbox-page.css`   | `.inbox-*`                            | sempre         |
| `kb-page.css`      | `.kb-*`                               | sempre         |
| `oficina-page.css` | `.of-*`                               | sempre         |
| `equipe-page.css`  | `.eq-*`                               | sempre         |
| `mockup-pages.css` | `.mockup-*`                           | sempre         |
| `chat-jana.css`    | `.cj-*`                               | sempre         |
| `prod-mec.css`     | `.prod-*` `prod-page-extras.css` mesclado | sempre     |
| `fin-boletos.css`  | `.bol-*` interno                      | sempre         |

## JSX · 6 módulos KB-9.75 aplicado + shell

### Shell / núcleo
- `app.jsx` — router principal
- `sidebar.jsx`
- `tweaks-panel.jsx`
- `icons.jsx`
- `data.jsx` · `data-os.jsx` · `data-clientes.jsx` · `data-orc-prod.jsx` · `data-vendas.jsx`

### Vendas · 9,75/10
- `vendas-page.jsx`        Lista + Drawer principal
- `vendas-extras.jsx`      Sub-rotas Caixa · Devoluções · Comissões · Relatórios · PDV
- `vendas-shortcuts.jsx`   J/K/R/F/B/E/X/? cheat-sheet
- `vendas-ai.jsx`          IA · resumir/histórico/sugerir
- `vendas-curation.jsx`    Comentários inline · audit · troubleshooter · VdLinkify
- `vendas-output.jsx`      Transcript PDF · apresentação · mensagem · arte
- `vendas-tweaks.jsx`      TweaksPanel densidade/drawer/SLA/paleta

### Financeiro · 9,75/10
- `financeiro-app.jsx`
- `financeiro-data.jsx`    FIN_ROWS + cross-link #V- #PC- #BL-
- `financeiro-icons.jsx`
- `financeiro-telas-extras.jsx`  Fluxo · Conciliação · DRE · Plano de contas
- `financeiro-curation.jsx`     useFinComments · useFinConferido · useFinEdits + FinEditPanel · finAuditTrail · FinPillFrescor
- `financeiro-ai.jsx`           finAiPartyHistory · FinAiAnomaliaBanner · FinAiPanel · FinAiMonthDigest
- `financeiro-output.jsx`       FIN_TROUBLES · FinFechamentoTrilha · FinPresentationMode · useFinFavs

### Compartilhado cross-módulo
- `fsm-stepper.jsx` — FSM canônica · domains: venda_cv · venda_mec · os · financeiro · boleto · recurring

### Módulos · sem refino KB-9.75 ainda
- `boleto-contas-app.jsx`
- `compras-page.jsx`
- `oficina-page.jsx`
- `crm-page.jsx`
- `inbox-page.jsx` + `inbox-v2-*.jsx` (4 arquivos)
- `equipe-page.jsx`
- `kb-page.jsx` + `kb-extras.jsx` + `kb-paths.jsx` + `kb-trouble-lib.jsx` + `kb-trouble-editor.jsx` + `kb-images-print.jsx`
- `mockup-pages.jsx` (+ `mockup-bodies.js`)
- `producao-page.jsx`
- `prod-page.jsx`
- `chat-jana.jsx`
- `chat.jsx`
- `tasks.jsx`
- `clientes-page.jsx`
- `os-page.jsx`
- `orc-page.jsx`
- `viewers.jsx`
- `linked-apps.jsx`
- `laravel-panel.jsx`

## HTML · 3 arquivos

- `Oimpresso ERP - Chat.html` — entrada do shell (link rel + script tags)
- `Método KB-9.75.html` — playbook do método (referência)
- `Diagnóstico Vendas KB-9.75.html` — bench inicial (referência)

## Convenção de nomes (NÃO QUEBRAR)

1. **CSS por módulo**: `<modulo>.css` ou `<modulo>-page.css`
2. **JSX por módulo**: `<modulo>-page.jsx` (entry) + `<modulo>-<feature>.jsx` (refinos)
3. **Compartilhados**: nomes curtos sem prefixo (`fsm-stepper.jsx`, `sidebar.jsx`)
4. **Data**: `data-<dominio>.jsx`
5. **Nomes batem 1:1 entre Cowork e prototipo-ui/ do repo**

## Pré-requisitos arquiteturais (A1, A2)

- A1 Integração cruzada · `VdLinkify` em `vendas-curation.jsx`
  Aceita `#V-` `#OS-` `#CLI-` `#orc-` `#BL-` `#PC-` `#R-` `#P-`
- A2 Multi-tenant Tier 0 · backend (Code resolve)
