---
date: "2026-06-10"
hour: "15:00 BRT"
topic: "Board Oficina cortado sob a sidebar em produção — diagnóstico Cowork + patch -m-6 órfão"
authors: [W, C]
outcomes:
  - "Causa raiz: -m-6 no root assumindo p-6 que .main-body não tem + min-h topbar extinta + grid sem minmax/scroll interno"
  - "Patch aplicado em Board.tsx (flex-1 + overflow-x-auto + repeat(n, minmax(228px,1fr)))"
  - "Sweep do anti-pattern -m-6 em 11 telas restantes"
  - "Lição L-26 registrada em memory/LICOES_CC.md"
prs: [2508]
us: []
related_adrs: []
---

# Sessão 2026-06-10 — Board Oficina cortado em produção (diagnóstico + patch)

## Pedido
[W] mandou screenshot de oimpresso.com/oficina-auto/ordens-servico/board: tela cortada
embaixo da sidebar, "não encaixa", e a sensação de que o design da Oficina Auto
"não foi aplicado". Pediu comparação com o protótipo Cowork.

## O que foi feito
- Comparei oimpresso.com.html dos dois projetos Cowork (atual × 019dcfd3…): **idênticos**
  (135 linhas, 7512 chars). O problema NÃO era o protótipo — era a tela real em produção.
- Li `resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx`, `ServiceOrderKanbanColumn.tsx`,
  `boardTone.ts`, `AppShellV2.tsx` e `cockpit.css` no repo.

## Diagnóstico (causa raiz do corte)
1. **`-m-6` no root do Board.tsx**: assume que o AppShellV2 envolve a página com p-6.
   Mas `.main-body` (cockpit.css ~l.180) é flex column **sem padding**. Margem negativa
   num scroll container cria overflow **inalcançável** à esquerda/topo → header
   ("…cina · Quadro de OS"), KPIs e 1ª coluna ficam permanentemente cortados.
2. **`min-h-[calc(100vh-3rem)]`**: assume topbar 3rem, mas hideTopbar=true é default
   desde 2026-05-17.
3. **`grid grid-cols-6`** sem minmax nem scroll interno: min-content dos cards estoura
   a viewport → scrollbar horizontal no main-body, quadro espremido. Canon do protótipo
   (.prod-kanban, oficina-page.css): `repeat(n, minmax(228px, 1fr))` + `overflow: auto`
   — o quadro rola POR DENTRO, o shell nunca estoura.

## Divergência visual registrada (decisão de [W], não corrigi)
`boardTone.ts` usa paleta Tailwind crua multi-hue (amber/violet/emerald/rose/etc.) nos
KPIs e colunas — diverge dos tokens do CLAUDE_DESIGN_BRIEFING (warn/pos/neg/accent-soft).
O arquivo afirma que as cores foram [W]-aprovadas ("a cor exata aprovada") — então só
registro: se [W] quiser o board com a cara do protótipo warm, o ponto único de mudança
é boardTone.ts.

## Entrega
Patch zero-toque: `prototipo-ui-patch/resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx`
(diff mínimo: root `-m-6 … min-h-[calc(100vh-3rem)]` → `flex-1`; quadro com wrapper
`overflow-x-auto` + gridTemplateColumns inline `repeat(n, minmax(228px,1fr))`;
`gridColsClass` removido). URL pública + 1 prompt pro Claude Code no chat.

## Erros + correção
Nenhum erro novo de [CC] nesta sessão.

## Residual
- [CL] precisa commitar o patch (eu não escrevo no git).
- Verificar depois do deploy se outras telas usam o mesmo anti-pattern `-m-6`
  (grep por `-m-6` em resources/js/Pages — provável em ProducaoOficina).

## Refs
- Board.tsx @main (commit 3b560b86) · cockpit.css l.145-190 · oficina-page.css l.7-18
- Protótipo canon: oficina-page.jsx / .prod-kanban (styles.css l.3798)

## Faxina anti-token-burn ([W] autorizou: "tem que apagar os errados")
115 arquivos mortos removidos: `_cowork-bundle/` (11), `_scrap/` (12),
`prototipo-ui-patch/_processados/` (22) e ~70 pontes/patches/espelhos antigos no
`prototipo-ui-patch/` (PROMPT_PARA_CODE_* de ondas mergeadas, vendas-*.jsx de maio,
cópias locais de PROTOCOL/BRIEFING/GLOSSARY/COWORK_NOTES). Mantidos: pontes dos
últimos ~3 dias (QUALIDADE-9, CAIXA-UNIFICADA, ERRADICA-LOCACAO-ACTIONS,
PACOTE-FINANCEIRO-F2, PRIMITIVOS-LAYOUT-V2, W28, UC-GUARDS, CICLO-DIARIO,
GOVERNANCA-FECHAR-G3-G7), charters/registros vivos (REGRAS_*, REGISTRY_*, MATRIZ_*,
Oficina.charter, CobrancaRecorrente.charter), `resources/` (patches F1 recentes),
`memory/`, `prototipos/` e pastas de estrutura. Regra nova: **L-39** em LICOES_CC.md.

## Próximo passo
Wagner cola o prompt no Claude Code → commit + push → conferir a tela no navegador.
