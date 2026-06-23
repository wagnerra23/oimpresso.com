# Sessão 2026-06-03 — Dark padrão · DS v6 (sistema) · Norte · Ficha da Frota

> Resumo canônico (Pedido · Feito · Decisões · Erros+correção · Residual · Refs · Próximo).

## Pedido (W, zero-toque, ao longo do dia)
1. Aproveitar o cockpit do "Luiz" → trazer **dark como padrão** do projeto.
2. Harmonizar cores ao DS (tirar oklch cru). 3. Temperar o DS. 4. Pensar o CRM/ERP.
5. Evoluir pra DS v6 + receita. 6. Gabarito Vendas. 7. Pontes pro Code.

## O que foi feito (Cowork)
- **Dark = padrão** + toggle Claro/Escuro nos Tweaks (`app.jsx` liga `data-theme`; ds-v5 já tinha o bloco dark, faltava acionar).
- **Oficina harmonizada → tokens:** sweep **64→~6** oklch cru (6 intencionais: placa/sombras); bloco dark redundante removido (−8KB, single source). KPI Urgentes theme-aware via `color-mix`.
- **DS temperado (Tier 0, W autorizou):** paleta `--stage-{slate,indigo,rose,emerald,green}` (claro+dark) + chroma sutil nos `--*-soft`. Roxo canon (ADR 0235) intocado.
- **View "Fila"** master-detail na Oficina (lista + detalhe inline + rail Apps Vinculados OS+CRM+WhatsApp), 4º modo do toggle, reusa `DviEditor/ItemsEditor/StageGate` (drawer travado intocado), responsivo <1180.
- **Ficha da Frota (CRM 360):** `crm-ficha.{jsx,css}`, sub-item "Frotas 360" (não toca o funil). Grafo frota→veículos→contatos + timeline unificada + próxima-melhor-ação (Jana). Branco e preto via token.
- **Norte — Fluxo do Caminhão:** peça de visão (7 cenas, costuras como herói). **Deployada no staging real** (`/showcase/norte`, PR do Code, atrás do superadmin, tokens escopados `.nx-root`, `--stage-*` em fallback).
- **DS v6 (sistema):** `ds-v6/showcase.html` (11 componentes, 2 temas) + `ds-v6/receita.html` (método 6 passos) + `ds-v6/gabarito-vendas.html` (**grounded no `Sells/Index.charter` v6** real). **Landed no repo PR #2165** (referência, aditivo).
- **Pontes pro Code (COWORK_NOTES):** `OFICINA-DARK-STAGE-DS` · `DS-V6` · `DS-V6-TOKEN-DELTA` (single-intent). README HANDOFF-ENTRY atualizado (snapshot stale 06-01 → pendentes da sessão).

## Decisões (propostas — W bate o martelo Tier 0)
- Dark = padrão do projeto. · DS v6 = soma aditiva ao v5 (11 componentes + receita), roxo canon intocado. [W] "já está aprovado".
- Gabarito = **referência grounded no main**, não port. Port do `Sells/Index.tsx` real = Tier-0/MWART futuro (gate + screenshot W).
- Ordem de transporte: **tokens primeiro** (delta) → harmoniza Norte → depois telas.

## Erros + correção (lição)
- **L-NN (grounding):** construí o 1º gabarito Vendas **inventado** (narrativa oficina), não grounded no `Sells/Index` real. [W] pegou ("comparou com a tela real no git?"). **Correção:** li `Sells/Index.charter.md` @main → reconciliei (subnav FOCO/Caixa/Faturamento/Comissão · status canon · KPIs+ageing · drawer 480px no lugar do rail · 10 colunas FSM/fiscal). **Regra:** gabarito/régua se faz **grounded no `main`**, nunca inventado (L-26/L-27 aplicado a design).
- **Loop validado:** "monto tela → sobra cor crua → é buraco do DS → vira token → harmonizo" (foi assim que `--stage-*` nasceu). Vira a Receita.

## Residual (W / Code)
- Processar pendentes na ordem: **TOKEN-DELTA** (desbloqueia `--stage-*` no main + de-TODO do Norte) → DS v6 componentes → port Sells (Tier-0, gate).
- Chroma dos `-soft` no repo: gated (repo usa `.vd-*/.os-*` escopadas? confirmar antes).
- ADR DS v6: número = W.

## Refs
- Arquivos: `app.jsx · styles.css · oficina-page.{jsx,css} · oficina-fila.{jsx,css} · crm-ficha.{jsx,css} · ds-v5/tokens.css · Norte - Fluxo do Caminhão.html (+norte-data/app) · ds-v6/{showcase,receita,gabarito-vendas}.html`
- Repo: PR #2165 (DS v6 reference) · Norte staging `/showcase/norte`.
- Pontes: `prototipo-ui-patch/PROMPT_PARA_CODE_{OFICINA-DARK-STAGE-DS,DS-V6,DS-V6-TOKEN-DELTA}.md`.

## Próximo passo
Token delta (opção 2) → Norte 100% harmonizado → port Sells quando W quiser.
