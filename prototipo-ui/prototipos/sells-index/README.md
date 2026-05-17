# Prototype: Sells / Index (lista de vendas)

> **Origem:** handoff bundle Claude Design (claude.ai/design) — sessão **chat10 "KB-9.75 Vendas"** _2026-05-16_.
> **Score do design:** **9,75/10** (KB-9.75 method aplicado em 4 refinos sequenciais, com polish final).
> **Tela alvo no repo:** [`resources/js/Pages/Sells/Index.tsx`](../../../resources/js/Pages/Sells/Index.tsx) (charter live, Cockpit V2 ADR 0110).
> **Cadeia de ADRs:** 0104 MWART · 0107 visual-comparison F3 · 0109 Claude Design plugin · 0114 Cowork loop · 0141 migracao Blade React.

---

## Como abrir

Abra `Oimpresso ERP - Chat.html` em um browser local (Chrome/Edge). Na sidebar esquerda, clique em **$ Vendas** — a lista carrega com mock data de [`data-vendas.jsx`](data-vendas.jsx).

Cabeçalho da tela `/vendas` tem:
- Dropdown **📂 Visões ▾** (canto direito) — alterna sub-rotas (Lista / Caixa / Devoluções / Comissões / Relatórios / PDV)
- ⌘K / `/` — abre palette de busca (prefixos `#ID @vendedor $valor /ação`)
- `?` — abre cheat-sheet de atalhos
- `J / K` — navega linhas
- `B` — favorita linha focada (★ persiste em `localStorage`)
- `R / F / X / E` — recibo / faturar / bulk / editar

---

## Arquivos por refino (KB-9.75)

| Refino | Score Δ | Arquivos | Função |
|---|---|---|---|
| **Baseline A+** | 5.6 | `vendas-aplus.jsx`, `vendas-page.jsx` (parte), `data-vendas.jsx`, `vendas-extras.jsx`, `vendas-create-completo.jsx` | Lista + drawer + 3 verticais (ComVis/Vest/OS) |
| **R1 Fundação** | +1.2 → 6.8 | `vendas-shortcuts.jsx` (novo), `vendas-page.jsx` (deltas), `styles.css` (+210 linhas) | SLA pill · J/K · tree-view · responsive ≤1100px · ⌘K v2 prefixos |
| **R2 IA** | +1.4 → 8.2 | `vendas-ai.jsx` (novo), `vendas-page.jsx` (drawer tab ✦), `styles.css` (+250 linhas) | Resumir pedido · histórico cliente · sugerir próxima · palette IA |
| **R3 Curadoria + Guia** | +1.0 → 9.2 | `vendas-curation.jsx` (novo), `styles.css` (+260 linhas) | Comentários por item · audit trail · troubleshooter · linkify `#V- / #OS- / #CLI- / #orc-` |
| **R4 Distribuição** | +0.55 → 9.75 | `vendas-output.jsx` (novo), `styles.css` (+470 linhas) | Transcript A4 · apresentação fullscreen · WhatsApp preview · drag-drop arte |
| **Polish** | +ε | `vendas-tweaks.jsx` (novo) | TweaksPanel (densidade · drawer width · SLA visual · paleta) |

---

## Plano de implementação (F0 → F5)

Atualmente: **F0 concluída** (bundle copiado).

Próximo: **F1** — gerar [`memory/requisitos/Sells/index-r1-visual-comparison.md`](../../../memory/requisitos/Sells/index-r1-visual-comparison.md) com 15 dimensões + plug-points (Cowork JSX ↔ funções reais do `SellController` + `/sells-list-json`). Wagner aprova **screenshot da F1** (não tabela) antes de F2.

Esquema previsto:

| PR | Refino | Linhas est. | Bloqueia? |
|---|---|---|---|
| PR1 | R1 Fundação | ~300 | — |
| PR2 | R2 IA | ~350 | depende R1 |
| PR3 | R3 Curadoria | ~400 | depende R1 |
| PR4 | R4 Distribuição | ~500 | depende R1 |

Cada PR passa em `commit-discipline` (1 PR = 1 intent, ≤300 linhas — R4 talvez quebre em 2).

---

## Plug-points (preview — detalhe na F1 visual-comparison.md)

| Mock no prototype | Função real no repo |
|---|---|
| `VENDAS_LIST` (`data-vendas.jsx`) | `GET /sells-list-json?payment_status=&limit=50` (já existe — `SellPosController@getListJson`) |
| `payTerm × fsm` (SLA computado) | `transaction_date + pay_term_number_days × current_stage_id` (FSM ADR 0143 live biz=1) |
| `window.VdAiPanel` (askAi) | Jana Copiloto MCP — `Modules/Copiloto/Services/Ai/...` (precisa endpoint dedicado pra venda) |
| `oimpresso.vendas.itemComments` localStorage | Tabela `sell_item_comments` (a criar) ou reusar `transaction_sell_lines.note` |
| `oimpresso.vendas.itemArt` localStorage | `media` polymorphic (`spatie/laravel-medialibrary` já no projeto) |
| Linkify `#V-NNNN` `#OS-NNNN` `#CLI-Nome` `#orc-NNNN` | Resolve via `route('sells.show', ...)` / `route('repair.show', ...)` etc |
| FSM stepper no drawer | `app/Domain/Fsm/ExecuteStageActionService` + UI `FsmActionPanel.tsx` (já existe) |

---

## Tier 0 — restrições obrigatórias na adaptação

- **`business_id` global scope** (ADR 0093) — TODOS os queries reais filtrados por tenant. Pest cross-tenant biz=1 vs biz=99 obrigatório.
- **Smoke em biz=1** (ADR 0101) — Wagner/WR2 SC, nunca ROTA LIVRE (biz=4 cliente).
- **Cabe em monitor 1280px** (cliente piloto Larissa, biz=4) — `@media ≤1100px` do R1 já endereça tablet, mas validar wide.
- **Drawer abre <300ms** (charter atual).
- **Sem cor crua** (`bg-gray-N` / `bg-red-N`) — só semântica Cockpit V2 (rose/emerald/amber/blue).
- **PT-BR em tudo**.

---

## Sync log

| Data | Quem | O que |
|---|---|---|
| 2026-05-17 | [CL] Claude Code | F0 — bundle copiado do design handoff (`Kf6GHQu6fkwlh0vnL30Oog`). Sessão worktree `stupefied-noether-89f83d`. |

---

## Refs

- Charter atual: [`Sells/Index.charter.md`](../../../resources/js/Pages/Sells/Index.charter.md)
- SPEC: [`memory/requisitos/Sells/SPEC.md`](../../../memory/requisitos/Sells/SPEC.md)
- Design Capterra: [`memory/requisitos/Sells/CAPTERRA-DESIGN-FICHA.md`](../../../memory/requisitos/Sells/CAPTERRA-DESIGN-FICHA.md)
- Cowork PROTOCOL: [`prototipo-ui/PROTOCOL.md`](../../PROTOCOL.md)
- Lições F3 anti-padrões: [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../LICOES_F3_FINANCEIRO_REJEITADO.md)
