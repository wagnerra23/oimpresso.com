# Integração Vendas × Oficina Auto · entrega Cowork → Code

> **Status:** F1 aprovada pelo Wagner (2026-05-25) · pronta pra F3 (Claude Code)
> **Pré-requisito KB-9.75:** A1 (Integração cruzada) · Vendas 9,0 → 9,3
> **PR alvo:** novo branch `feat/integracao-vendas-oficina`

## O que muda

**Conceito:** OS do módulo Oficina entregue (`stage === "pronto"`) vira automaticamente uma venda do módulo Vendas com `source: "oficina"` + `osRef: "OS-NNNN"`. Módulos seguem separados (operacional respeita persona), costurados em 4 pontos.

**4 pontos de costura (todos implementados):**

1. **Lista de Vendas** ganha coluna **Origem** (Balcão · Oficina · Online) entre "Atendido por" e "Pipeline". Pills coloridas + link `↗ #OS-NNNN` clicável nas linhas de oficina. Linha de oficina ganha stripe azul sutil na borda esquerda.
2. **Saved tree "Por origem ▾"** no dropdown Visões — expansível com filhos Balcão/Oficina/Online + contadores.
3. **Hero KPI** quando `Foco = Faturamento` mostra "Faturado hoje · todas origens" + breakdown `● Balcão R$ X · ● Oficina R$ Y · ● Online R$ Z`.
4. **Drawer OS** (módulo Oficina) quando `stage === "pronto"` mostra card highlight verde **"Esta OS gerou a venda #V-NNNN"** com breakdown peças/serviço + fiscal + 3 atalhos. Botão "Abrir" dispara evento `oimpresso:open-venda` → drawer da venda abre cross-módulo.
5. **Caixa do dia** (`/vendas/caixa`) ganha seção **"Por origem"** ao lado de "Por forma de pagamento" com barras de progresso por source + refs `↗ #OS-NNNN` clicáveis.

## Arquivos modificados (7 · TODOS substituem 1:1 no repo)

| Path no repo | Mudança principal |
|---|---|
| `prototipo-ui/data-vendas.jsx` | +`VENDAS_SOURCE_META` · campos `source` + `osRef` derivados no map · saved view `origem` expansível |
| `prototipo-ui/vendas-page.jsx` | +`VdSource` component · coluna Origem na tabela · tree branch `origem` · listener `oimpresso:open-venda` · KPI hero breakdown |
| `prototipo-ui/vendas.css` | +tokens source (balcao/oficina/online) · `.vd-src-*` · `.vd-kpi-breakdown` · `.vd-row-oficina` stripe |
| `prototipo-ui/vendas-extras.jsx` | `VendasCaixaPage` · computa `bySource` + nova section "Por origem" com refs |
| `prototipo-ui/oficina-page.jsx` | `Drawer` · `vendaFromOs` lookup + card `.ofc-venda-card` quando `stage==="pronto"` · dispatch `oimpresso:open-venda` |
| `prototipo-ui/oficina-page.css` | +130 linhas · `.ofc-venda-card`, `.ofc-venda-grid`, `.ofc-fb-*`, `.ofc-venda-cta` |
| `prototipo-ui/styles.css` | grid fix `.vc-grid > .vc-card:nth-child(3),(4)` full-width + estilos `.vc-card-source` + `.vc-src-*` |

## Constituição respeitada (checklist)

- ✅ Single file (tudo dentro de `Oimpresso ERP - Chat.html`) — nenhum HTML novo criado
- ✅ Sem emoji em UI (só símbolos canônicos ✓ ⌛ × ↗ ●)
- ✅ Tokens oklch dentro da paleta — não inventei cor fora do sistema
- ✅ Sem rounded-xl+ (radius 4-10px)
- ✅ Drawer lateral mantido — nenhum modal full-screen pra detalhe
- ✅ UI 100% português ("Esta OS gerou a venda", "Por origem", "Abrir #V-")
- ✅ Cross-link bidirecional (Vendas → Oficina via `↗ #OS-` na coluna · Oficina → Vendas via card "Abrir #V-")
- ✅ Padrão Cockpit V2 (sidebar + header sticky + body cards + footer drawer)
- ✅ Sem CTA WhatsApp cliente-facing

## Decisões do Wagner (aprovadas 2026-05-25)

1. **Auto-faturar:** automático na transição `Pronto p/ retirar → Entregue`. Não requer click manual do mecânico.
2. **Split de comissão:** mecânico % do serviço + balcão % da peça quando houve intermediação. % a definir por filial.
3. **OS sem nota:** vira venda mesmo assim (`fiscal: {}`, sem badge NF-e). Não bloqueia fluxo informal.
4. **Default Felipe (mecânico):** vê tudo, filtro `Por origem · Oficina` pré-aplicado por default. Sem ACL hard.

## Pós-merge

- F1.5 (Claude Design crítica) — rodar `design-critique` no novo `prototipos/sells/page.tsx` quando Code traduzir
- F3.5 (Claude Accessibility) — `accessibility-review` no fluxo cross-módulo
- ADR — registrar decisão de auto-faturar OS→Venda como evento (`oimpresso:open-venda` é mock no protótipo; backend real usará observer no `OsObserver@updated`)

## Storyboard de referência

`STORYBOARD.html` neste mesmo diretório — 3 frames + diagrama de arquitetura + 4 decisões já validadas com Wagner.
