# Sessão 2026-06-11 — Vendas/Create re-skin na linguagem do show

**Pedido ([W], comentário inline no header do Create):** "cada botão que volta? a venda tá uma coisa feia, teria que refazer por completo? o show está bonito."

**Resposta de escopo:** NÃO precisou refazer por completo. O domínio do Create (m² automático, bip EAN, frete por endereço do cliente, fiscal inferido, F2, evento `oimpresso:venda-created`) é sólido — feia era a CASCA. Re-skin total da camada visual emprestando a gramática do show (`VendaDetailDrawer`) + método 9.75.

## O que foi feito
- **vendas-create-page.css reescrito (v2 → host `?v=cr3`):**
  - Header: botão **← Vendas (esc)** novo (era a falta que o [W] apontou — não existia volta fora do Cancelar no rodapé), h1 fs-6, total hero **mono fs-7 tabular** (linguagem do `vd-drawer-total`).
  - Pills de scroll-spy → **tabs sublinhadas** (= `vd-drawer-tab` do show: border-bottom 2px accent, count pill accent-soft).
  - Seções: **ícone-quadradinho accent-soft** (= lentes do drawer financeiro 9.75) + sh-1, h2 fs-5.
  - Tempero ds-v6: `--sh-1/--sh-2`, `--t-1/--ease`, ramp `--fs-1..7` em todo o arquivo; números mono.
  - **FIX overflow-x da fila 06-10**: `.vc-body{overflow-x:hidden}` + `min-width:0` em grids/fields + `.input/.select{width:100%}` + savebar `flex-wrap` (o "Salvar e emitir" não corta mais).
  - kbds visíveis (esc no voltar/Cancelar, F2 no primário) — padrão drawer 9.75.
- **vendas-create-page.jsx (edits cirúrgicos, lógica intocada):** botão `.vc-back` (chevR rotacionado, onDone), `vc-sec-ic` nas 5 seções (doc/archive/truck/receipt/check), kbd esc no Cancelar.
- **G8 snap:** 8.5/9/9.5/10px de font → `var(--fs-1)`; probe re-rodado = **0 tamanhos fora do ramp**.

## Prova
- Probe G1–G13: **0 🔴** (na rota vc-form). G5 lista 7 cruas do SHELL (.sb-body scrollbar, .av-* avatar) — pré-existentes, fora do escopo vendas-create, baseline da rota nunca calibrada.
- Screenshots light+dark, item adicionado, seções de baixo (pagamento/fiscal), savebar com item (primário roxo habilitado).

## Erros/quirks de ambiente (não repetir)
- `__vendasSubSetter('index')` NÃO existe — sub é **'lista'** (setei 'index' por engano → tela branca + persistiu em localStorage; corrigido pra 'lista' no mesmo turno).
- Captura html-to-image **perde scrollTop de container interno** e drawers com entrance-animation ficam `opacity:0` no iframe de captura — inspecionar fundo de página via `translateY` no inner, não via scroll.

## Residual
- Busca de cliente fraca (fila 06-10) — não tocada (proporção).
- G5 baseline da rota Vendas/Create nunca calibrada (cruas são do shell).
- Port F3: o `Sells/Create.tsx@main` é alto-craft (✓lido 06-08); este re-skin é do MOCK Cowork — se [W] aprovar F2, o handoff leva só os conceitos (voltar, tabs, ícone-seção, ramp), não copy-paste de CSS.

**Próximo passo:** F2 [W] (screenshot ✓/✗). Sem handoff enfileirado ainda.

## Adendo (mesmo dia) — sub-nav do módulo Vendas ([W] "quais telas vinculadas à venda?" → "ok" pra expor)
- Respondido do código: 6 sub-rotas (lista/create/caixa/devoluções/comissões/relatórios) + overlays (show, edit, PDV, recibo/orçamento, NF-e, ⌘K) + costura venda-created → Produção/Financeiro.
- **Executado:** `.vd-modnav` no wrapper `VendasModule` (vendas-extras.jsx ?v=modnav1 + vendas.css ?v=modnav1) — tabs sublinhadas (mesma gramática show/Create) com as 5 sub-telas + PDV balcão à direita; **esconde no create** (página focada, já tem ← Vendas). Classe nova de propósito: NÃO reutilizei `.vm-subnav` legado do styles.css (cruas #fff/oklch). Reverte a decisão antiga "só no Visões ▾" COM ok explícito do [W].
- Prova: navegação por clique funciona (lista→caixa→devoluções), dark ok, create sem subnav. **Validado no view AO VIVO do usuário** (eval no pane real): active = roxo canon, classes corretas.
- **Quirk de ambiente CONFIRMADO E GRAVE pra próximas sessões:** o pipeline de captura do iframe (html-to-image) reportou classes/computed/pixels MUTUAMENTE inconsistentes pra atualizações de classe in-place (active "preso" na tab errada) — gastei ~8 rounds caçando bug inexistente. Regra: **estado visual pós-interação só confiável via eval no view do usuário ou reload limpo**; não depurar paint pelo capture.

## Adendo 2 (mesmo dia) — reposicionamento + faxina do toolbar ([W] "page header fica abaixo do header" · "visões sai fora" · "Imprimir caixa vai pra dentro do caixa")
- **Tabs reposicionadas:** saíram do topo do wrapper → componente `VdModNav({here})` (window.VdModNav) renderizado **ABAIXO do page header** de cada tela: lista (vendas-page.jsx, antes do vd-toolbar) + caixa/devoluções/comissões/relatórios (vendas-extras.jsx). Active por prop `here`, não global (evita stale no commit). CSS .vd-modnav virou in-page (transparent, sem padding lateral, margin-bottom s-5).
- **VdSubBreadcrumb removido das 4 sub-páginas** (redundante — tab "Vendas" volta pra lista; função mantida no arquivo por histórico).
- **"Visões ▾" REMOVIDO do vd-toolbar** (substituído pela sub-nav — ordem explícita [W]); **"Imprimir caixa" saiu da lista** → Caixa do dia, header: "Imprimir Z"→"Imprimir caixa (Z)". Estados visoesOpen/visoesRef ficaram órfãos no arquivo (inócuos).
- Versões: vendas-page.jsx?v=modnav2 · vendas-extras.jsx?v=modnav2 · vendas.css?v=modnav2.
- Prova: lista = h1→tabs→FOCO toolbar limpo; caixa = h1+ações→tabs ativos roxo; console limpo.

## Adendo 3 (mesmo dia) — toolbar FOCO desceu pra cima da lista ([W] "essa linha vai ficar acima da lista")
- Bloco vd-toolbar (FOCO Caixa/Faturamento/Comissão + vd-views "Hoje") movido na VendasListPage: era header→tabs→**toolbar**→KPIs→status-tabs→tabela; virou header→tabs→KPIs→**toolbar**→status-tabs→tabela (?v=modnav3, cirurgia por índice via run_script, 101 linhas).
- Caveat declarado: o FOCO troca o 4º KPI/ranking que agora ficam ACIMA dele — [W] mandou explícito, executado.
