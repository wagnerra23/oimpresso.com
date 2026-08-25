# _arquivo/INDEX.md — Manifesto do Arquivo de Design (Cowork)

> **Versão:** v1.0 · **Data:** 2026-05-30 · **Autor:** [CC]
> **Indexado sob a constituição:** ADR 0094 (Oimpresso V2) + ADR UI-0013 (UI v2) + soberania-[W] (autorizada 2026-05-31, aguarda nº/versão pelo Code).
> **Regra que rege este arquivo:** **append-only (ADR 0003 · L-07).** Nada foi apagado — tudo foi **movido** da raiz pra cá e registrado abaixo. Mover ≠ deletar. Pra reverter, é só mover de volta usando a coluna "Origem".
>
> **Pra que serve:** a raiz do Cowork agora é **base limpa** — só o app vivo (`Oimpresso ERP - Chat.html` + módulos) + espinha + ponte viva. Todo o histórico de exploração ficou aqui, organizado por tema. Este índice diz **o que cada arquivo era e o que o cobre hoje**, pro [CL] (Code) e pro [W] não se perderem.
>
> 📌 **Lente de REAPROVEITAMENTO (companheira deste manifesto):** `_arquivo/INVENTARIO-REAPROVEITAMENTO.md` (2026-06-07) — classifica cada item por 🟢 aproveitável / 🟡 parcial / 🔴 só histórico + **o que se colheria** dele. Comece por lá quando for compor/refinar tela.

---

## 0 · Por que esta faxina (contexto)

`PLANO_ORGANIZACAO_CASA.md` (18/mai) rodou quase todo: os HTMLs/JSX duplicados antigos já tinham sumido e o `styles.css` já foi quebrado por módulo. Mas a casa **sujou de novo** com ~16 HTMLs soltos (a maioria rascunhos da sessão de 30/mai) + 3 Design Systems + ~13 prompts de ponte já processados. Esta rodada (2026-05-30, pedido [W]) move essa camada nova pro arquivo, **sem apagar**, e deixa o índice + a base limpa.

**Regra do CLAUDE.md reforçada:** existe **UM** arquivo principal — `Oimpresso ERP - Chat.html` — e toda tela é rota dentro dele. Proibido `.html` novo por módulo. Por isso telas como `Clientes.html` viraram histórico: já vivem como rota no Chat.

---

## 1 · Base limpa que ficou na raiz (NÃO mexer aqui sem motivo)

| Grupo | Arquivos |
|---|---|
| **App vivo (a entrega)** | `Oimpresso ERP - Chat.html` + todos os `*-page.jsx`, `data-*.jsx`, `*.css`, `app.jsx`, `sidebar.jsx`, `icons.jsx`, `tweaks-panel.jsx`, `fsm-stepper.jsx`, `ds-behavior.js`, `mockup-bodies.js` que ele importa |
| **DS canônico (espelho do git)** | `tokens.css`, `design-system.css` |
| **Playbook do método** | `Método KB-9.75.html` |
| **Espinha (lida 1º)** | `STATUS.md`, `MEMORY_INDEX.md`, `CARTA_DESIGN_CC.md`, `CLAUDE.md`, `README.md`, `ARQUITETURA.md` |
| **Ponte viva [CC]↔[CL]** | `COWORK_NOTES.md`, `CODE_NOTES.md`, `SYNC_LOG.md`, `CLAUDE_CODE_BRIEFING.md`, `CODE_DESIGN_CONTRACT.md`, `LARAVEL_REPO_CONTEXT.md`, `prototipo-ui-patch/` |
| **Memória de contexto viva** | `MEMORIA_VENDAS_CREATE_LARISSA.md`, `MEMORIA_F3_ZEROTOUCH.md`, `AUDITORIA_MODULOS.md`, `AUDITORIA_MODULOS.md`, `HANDOFF_*.md`, `PLANO_ORGANIZACAO_CASA.md` |
| **Lápide (append-only)** | `CONSTITUICAO.md` (superseded → `CARTA_DESIGN_CC.md`, ADR 0201/L-07) · `CLAUDE.md.proposto` (proposta [W]) |

---

## 2 · `_arquivo/telas/` — telas de exploração (substituídas por rotas vivas)

| Arquivo (destino) | Origem | O que era | Coberto hoje por |
|---|---|---|---|
| `Clientes.html` | raiz | exploração da lista de clientes | rota Clientes (`clientes-page.jsx`) no Chat |
| `Oimpresso ERP - Clientes.html` | raiz | shell antigo só de Clientes | idem — Chat unificado |
| `Shell Real.html` | raiz | teste de paridade com AppShellV2 do repo | shell vivo do Chat (Cockpit V2) |
| `Cadastro Cliente - Pagina Inteira DS 4.2.html` | raiz | molde PT-03 (cadastro = página inteira) | **PROPOSTA F0** — toca proibição "detalhe usa Sheet". Não é lei. |
| `Frescor - Clientes vs Financeiro.html` | raiz | comparativo de frescor entre telas | diagnóstico pontual — consumido |
| `Integração Vendas × Oficina - Storyboard.html` | raiz | storyboard do fluxo Vendas→Oficina | fluxo vive em `vendas-flow.jsx` / `oficina-page.jsx` |

## 3 · `_arquivo/referencia/` — diagnósticos/método (consulta, não app)

| Arquivo | Origem | O que é |
|---|---|---|
| `Diagnóstico Vendas KB-9.75.html` | raiz | bench de Vendas pelo método KB-9.75 (referência) |
| `Cadastro de Contacts - Diagnóstico KB-9.75.html` | raiz | diagnóstico do cadastro de cliente |
| `Metodo 9.75 - OS.html` | raiz | aplicação do método na tela de OS |

> Playbook-mestre do método continua **na raiz** (`Método KB-9.75.html`). Aqui ficam as aplicações por tela.

## 4 · `_arquivo/sessao-2026-05-30/` — rascunhos de decisão (F0/F1, não entrega canônica)

| Arquivo | Origem | O que era |
|---|---|---|
| `Painel Cowork - Estado Atual.html` | raiz | espelho visual da espinha (STATUS.md é a fonte-texto) |
| `Piloto Vendas - Antes Depois.html` | raiz | proposta de harmonização (verde sobre DS) — **PROPOSTA** |
| `Auditoria - O Melhor de Cada Tela.html` | raiz | lista de proteção por tela |
| `Auditoria Sidebar + PageHeader.html` | raiz | auditoria de sidebar/header |

## 5 · `_arquivo/ds/` — Design Systems passados/propostas + `ds-historico/` v1–v2

> **Regra ADR 0239 R4:** exatamente **1 spec vigente fica na raiz** (hoje `Design System v4.html`, ao lado de `design-system.css`/`tokens.css`). Passados + propostas vivem aqui. Lista de links no fim deste arquivo (§DS — versões antigas).

| Arquivo | Origem | Status |
|---|---|---|
| `ds/Design System v4.2 - Evolucao.html` | raiz | spec v4.2 (cockpit/fiscal/sla/readiness) — **PROPOSTA**, não canon |
| `ds/Design System v3.html` | raiz | spec v3 — **passado** (superseded por v4) |
| `ds-historico/Design System.html`, `v1.1`, `v1.2`, `v2`, `DS v2 - Plano/Reavaliacao` | `_arquivo-ds/` | linhagem antiga do DS |

> ⚠️ **Cor canônica = roxo `primary` `oklch(0.55 0.15 295)` (ADR 0235).** Os specs HTML aqui podem mostrar accents antigos (azul 220, identidade-por-tela) — são **histórico**, não a lei. A lei de cor mora no git (`tokens.css` / `design-system.css`).

## 6 · `_arquivo/bridge-processados/` — prompts/gaps já entregues ao Code

| Arquivo | Origem | Status |
|---|---|---|
| `PROMPT_PARA_CLAUDE_CODE.md`, `PROMPT_PARA_CODE_MEMORIA.md`, `PROMPT_PARA_CODE_VENDAS_FINANCEIRO.md`, `PROMPT_LICOES_PARA_CLAUDE_CODE.md`, `PROMPT_OS_V4_ROXO.md`, `PROMPT_v3_ATOMICO_PARA_CODE.md`, `PROMPT_v4_CASA_ORGANIZADA.md` | raiz | prompts zero-toque one-shot — já processados |
| `FORCE_OVERWRITE_V3_PARA_CODE.md`, `COWORK_RESPONSE_PR295.md` | raiz | syncs específicos — processados (PR #295 mergeado) |
| `GAPS_FINANCEIRO_PRA_CODE.md`, `GAPS_v2/v3/v4_FINANCEIRO_PRA_CODE.md` | raiz | 4 iterações de gaps do Financeiro — consolidadas |

> A ponte **viva** (não processada) fica na raiz: `COWORK_NOTES.md`, `CODE_NOTES.md`, `SYNC_LOG.md` + `prototipo-ui-patch/`.

## 7 · `_arquivo/auditoria/` — screenshots e .bak

68 PNGs de auditoria/bench + `vendas.css.bak` (vindos de `_audit/`). Histórico visual; não consumido pelo app.

## 8 · `_arquivo/legado/` — pastas pesadas arquivadas (v1.1 · dedup profundo D4)

| Pasta (destino) | Origem | O que era |
|---|---|---|
| `legado/uploads/` | `uploads/` | 358 arquivos — uploads antigos + **handoff recursivo aninhado** (`Oimpresso-handoff(1)/…`) que inflava o export pra ~14 MB |
| `legado/backups/` | `backups/` | 112 arquivos — backups datados (2026-05-14*) |
| `legado/scraps/` | `scraps/` | 8 arquivos — rascunhos/thumbnails |
| `legado/memory-para-github/` | `memory-para-github/` | staging antigo de sync (substituído por `prototipo-ui-patch/`) |

> Movido pra desinflar o export. Nada referenciado pelo app vivo (grep confirmou zero `src/href/url` apontando pra essas pastas).

---

## 9 · Changelog (append-only)

| Versão | Data | Autor | Mudança |
|---|---|---|---|
| v1.0 | 2026-05-30 | [CC] | Criação do `_arquivo/`. Movidos 16 HTMLs + 13 mds de bridge + unificados `_arquivo-ds`→`ds-historico/` e `_audit`→`auditoria/`. Base da raiz enxugada. Nada apagado. |
| v1.1 | 2026-05-30 | [CC] | **Reconciliação pós-auditoria 55/100 (grade D2–D8).** D4 dedup profundo: `uploads/backups/scraps/memory-para-github` (+479 arq.) → `legado/`. D2: ADR 0200/0201 cunhados despromovidos a `_PROPOSTA`. D5: navy→STALE/ADR 0235. D6: hierarquia fonte-única no MEMORY_INDEX. Soberania = ADR 0238. |
| v1.2 | 2026-05-30 | [CC] | **Conformidade ADR 0239 R4** (lida no git nesta sessão). Corrigido over-archive: `Design System v4.html` (vigente) **devolvido à raiz**; só v3 + v4.2 ficam arquivados. Adicionada seção "DS — versões antigas (links)". |

---

## DS — versões antigas (links · ADR 0239 R4)

> **Vigente (NÃO está aqui — fica na raiz):** `Design System v4.html` (roxo 295, ADR 0235).

| Versão | Local | Status |
|---|---|---|
| v4.2 (Evolução) | `_arquivo/ds/Design System v4.2 - Evolucao.html` | proposta (não ratificada) |
| v3 | `_arquivo/ds/Design System v3.html` | passado (superseded por v4) |
| v2 · DS v2 Plano · DS v2 Reavaliação | `_arquivo/ds-historico/` | histórico |
| v1.2 · v1.1 · v1 | `_arquivo/ds-historico/` | histórico |
