# Sessão 2026-06-09 — Oficina vira realidade: erradicar locação + polish (chrome, WhatsApp, impressão)

## Pedido [W]
"Quero fazer a oficina se tornar realidade, o que falta? ainda tem informação de locação presente." Depois, em lotes:
- **A1** apagar aluguel de caçamba + fundamentar p/ não voltar ("eu não uso, é alucinação").
- **A2** cor da sidebar está preta, devia seguir as regras do projeto.
- **A3** "quero reparo, faça a ADR."
- **A4** a linha Foco/Densidade/Pressão devia ser menu discreto ao lado do kanban.
- **B1** botão do WhatsApp grandão na Fila.
- **B2** impressão da Fila. **B3** impressão da OS.

## O que foi feito (protótipo `oimpresso.com.html`)
- **B1 + A1(emoji):** botão WhatsApp da Fila era CTA verde full-width (`.ofc-rail-btn.wa`, `--pos`) → virou secundário neutro **"Abrir conversa"** (ícone lucide). Removido `📲` em `oficina-forms.jsx`/`oficina-fila.jsx`/`oficina-page.jsx` (canon sem emoji). CSS verde morto removido.
- **A2 chrome:** `styles.css` (o REAL carregado — NÃO o `cowork-financeiro-bundle.css`, que é espelho não-linkado) `--sb-*` de `oklch(0.21 0 0)` preto-neutro → `oklch(0.21 0.025 295)` tint roxo canon (hue 295, ADR 0235). `?v=tok1→tok2`. Confirmado computed ao vivo.
- **B2/B3 impressão:** criados `oficina-print.js` (`window.OficinaPrint.{printOS,printFila}`) + `oficina-print.css` — folhas **A4 isoladas via `@media print`** (padrão Vendas `vd-orc`): OS = marca+grid veículo/cliente+sintoma+DVI+peças/MO+total+assinaturas; Fila = tabela de OS abertas (urgentes marcadas) + total em carteira. Ligados os 3 botões (Imprimir fila / Imprimir OS no drawer / Imprimir no detalhe da Fila — este estava **sem onClick**). Registrados no HTML; testados ao vivo (folhas renderizam corretas).
- **A4:** já era popover "Visão" discreto no protótipo (Foco/Densidade/Pressão não é mais linha) — nada a refazer; produção (git) é que ainda tem a linha → coberto no handoff/Fase 2.

## Decisões
- **ADR (proposta):** `memory/decisions/_PROPOSTA-ADR-oficina-reparo-erradica-locacao.md` — Reparo é o ÚNICO domínio; erradicar `order_type=locacao` + KPIs de locação; "Caçambas" sobrevive só como nome comercial do cliente Martinho. Fecha o resíduo que a ADR 0194 deixou. Tier 0 autorizado por [W].
- **Handoff:** `prototipo-ui-patch/PROMPT_PARA_CODE_OFICINA-REPARO-ERRADICA-LOCACAO.md` (P0 erradicação+ADR+proibições · P1 polish visível como referência).

## Erros + correção
- **[CC] enquadrou locação como "legado vivo intencional, Tier 0 a preservar"** na resposta anterior. [W] corrigiu: "eu não uso, é alucinação, apaga". → nova lição em `LICOES_CC.md` (**L-domínio-soberano**: fonte de verdade do domínio é [W], não o código legado; código rodando ≠ processo querido; perguntar antes de presumir intenção). STATUS realinhado.
- **Editei o CSS de chrome errado primeiro** (`cowork-financeiro-bundle.css`, não-linkado) — computed continuou preto. Reli os `<link>` do HTML, achei `styles.css` como o real, reapliquei. (REGRA 6 aplicada a "qual arquivo está REALMENTE no ar".)

## Residual
- Transporte zero-toque ainda **não** disparado ([W] estava em review). Quando ele disser "comita/transporta": gerar URLs públicas dos arquivos de referência e entregar o prompt P0.
- Produção (git `ServiceOrders`/`ProducaoOficina`) ainda tem `order_type=locacao` + menu "Caçambas" + linha Foco/Densidade/Pressão → resolve no PR do handoff.

## Refs
- Arquivos: `styles.css · oficina-print.{js,css} · oficina-fila.{jsx,css} · oficina-forms.jsx · oficina-page.jsx · oimpresso.com.html`.
- Git lido @main: `Modules/OficinaAuto/{CHANGELOG.md,Routes/web.php,Resources/menus/topnav.php}` · `CLAUDE_DESIGN_BRIEFING.md` (roxo 295 canon) · ADR 0194 (reclassificação domínio).

## Lote C (m0093) — dúvidas de arquitetura + item 4
**Dúvidas respondidas grounded (@main):**
1. **FSM existe** — ADR 0143, pipeline ServiceOrder LIVE em prod desde 2026-05-12 (`orcamento→aprovada→em_servico→concluida[+cancelada]`). As etapas da tela (recepção→…→pronto) são a apresentação por cima.
2. **Estoque** — `oficina_service_order_items` tem 3 tipos: `peca` (com `product_id` nullable → catálogo `products` UltimatePOS legacy), `mao_obra`, `servico_terceiro`. OS liga ao núcleo de venda via `service_orders.transaction_sell_line_id` (migration 2026_05_12_230001). Integração = peça consome estoque do core UltimatePOS na venda; MO/terceiro não tocam estoque. Sem módulo "Estoque" dedicado — é o inventário do core.
3. **Faturamento/NF** — venda = `transactions`/`transaction_sell_lines` (core). Emissão: **NfeBrasil** (NF-e 55 / NFC-e 65 = produto) + **NFSe** (NFS-e modelo 56 nacional = serviço). **Fiscal** = cockpit agregador thin (7 sub-páginas; Config = regime+tributação por business). Split OS: peça→NF-e, mão de obra→NFS-e. **Pequena×grande:** o regime per-business no Fiscal/Config dirige; sistema cobre do balcão simples (NFC-e/Simples) ao completo (NF-e+NFS-e+SPED EFD-ICMS/IPI Waves 8-9). Mesma planta, subconjunto por porte.

**Item 4 (feito):** `ItemsEditor` (oficina-forms.jsx) já tinha "Mão de obra", mas o dropdown estoque/encomend./ag.aprov é só de peça. Contextualizei por tipo: peça = qtd/R$ unit + status de estoque; mão de obra = horas/R$h + rótulo "serviço" (sem estoque); terceiro = rótulo "terceiro". CSS `.ofc-items-stat-na`. Print `printOS` corrigido pra usar qty×unit (antes lia valor/preco inexistentes) + label de tipo. Verificado: MO 0.5h×R$120=R$60 na folha.

## Próximo passo
[W] decide: disparar o transporte do handoff P0 (erradicação no git), ou continuar o review visual do protótipo. Estoque↔OS (consumo na conclusão) e split fiscal peça→NFe/MO→NFSe são candidatos a próximos handoffs se [W] quiser fechar o ciclo "vira realidade".
