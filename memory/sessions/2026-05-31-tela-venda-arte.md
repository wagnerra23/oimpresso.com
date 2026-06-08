# Tela de Venda — Estado-da-Arte & Nota (oimpresso vs melhores 2026)

> **Agente:** `tela-venda-arte` · **Data:** 2026-05-31 · **Escopo:** `Sells/Create.tsx` (form criar venda, 1647 linhas) + `Sells/Index.tsx` (cockpit lista, 1806 linhas).
> **Pesquisa limpa** (Fase 1 sem contaminar com memória oimpresso) → **comparação código real** (Fase 2) → **gaps** (Fase 3) → **nota + ação** (Fase 4).
> **Resultado em 1 linha:** Create **88/100** · Index **90/100** · referência topo (Shopify POS / Square) **~92/100**. Gap pequeno (-4). Causa: conformidade DS (cor crua + header fora do canon + CSS Cowork scoped) e 1 gap de fluxo (NFC-e não emite dentro do Create).

---

## 1. PESQUISA — os melhores (Fase 1)

Pesquisa web limpa, 9 players + camada AI-first. Foco: como cada um resolve a **tela de venda / checkout**, não marketing.

| Player | Público | Como resolve a tela de venda (mecanismo concreto) | Por que é referência |
|---|---|---|---|
| **Shopify POS Pro** | Varejo omnichannel global | Smart Grid dinâmico (tiles respondem ao carrinho); navegação 100% teclado (search→add→checkout sem trocar pra touch); product search com word-completion + variante no resultado; returns/exchanges pelo mesmo fluxo do carrinho | Escala global, UX doc pública, keyboard-first maduro |
| **Square Register 2.0** | PME/varejo/food US | Dual-screen (operador + cliente); checkout "lightning-fast"; split payment; AI search que casa a linguagem do caixa; customer-facing display com itens/tax/loyalty sem interromper pagamento | Hardware+SW integrados, 2ª geração fev/2026 |
| **Stripe Terminal + Elements** | Devs / plataformas | POS como **biblioteca**: SDK JS/React, você constrói a tela; `loadStripe` 2026 só baixa módulos dos métodos ativos (bundle enxuto); on-reader input; Payments Foundation Model prevê falha de pagamento | Padrão de componentização de checkout; quem constrói POS custom |
| **Toast POS** | Restaurante (QSR + full) | Drive-thru mode (vehicle ID, lane tagging, menos passos); Advanced Order Confirmation Screen; AI Voice ordering; **estabilidade de condicionamento** (mudanças aditivas, não quebra muscle-memory do caixa) | Vence em "não reaprende a tela"; speed-of-service |
| **Lightspeed Retail** | Varejo multi-vertical inventory-heavy | Sales screen central: search+add, cliente, desconto/promoção; matriz de variantes (tamanho/cor) superior; barcode integrado; multi-localidade/transfer num dashboard | Melhor inventário de catálogo grande; mas **dívida de UX** (sem redesign 2023-26) |
| **Bling ERP (PDV)** | PME BR | Frente de caixa limpa: busca por código/nome/leitor; desconto+cupom+finalizar em poucos cliques; **NFC-e emitida automaticamente durante a venda**; estoque/financeiro atualizam no ato; PIX/QR/cartão/misto | Concorrente direto BR; NFC-e inline é o benchmark fiscal |
| **Tiny ERP (Olist)** | PME/e-commerce BR | PDV registra venda ágil; inclui produto, troco, forma de pagamento, abre/fecha caixa **com poucos cliques ou atalhos de teclado**; unifica venda multicanal | Concorrente direto BR; atalhos de teclado explícitos |
| **Omie.PDV Varejo** | PME BR | **Pré-venda no balcão** (consulta preço/estoque, negocia desconto, **bloqueia estoque**) → finaliza no PDV; tempo-real com ERP; balança+leitor | Concorrente direto BR; reserva de estoque na pré-venda |
| **Conta Azul Pro** | PME/serviços BR | Nova venda: cliente→itens→condição pagamento→salva; tipos Orçamento/Avulsa/Recorrente; ajuda contextual inline na própria tela | Concorrente BR forte em serviços/financeiro |
| **AI-first (Microsoft Copilot Checkout + Google UCP)** | Agentic commerce 2026 | Checkout conversacional dentro do assistente (sem redirect); UCP = protocolo aberto agente↔comércio (Shopify/Stripe/Adyen/Visa endorsed) | Vanguarda 2026: a "tela" some, vira conversa |

**Framework neutro de calibração** — o benchmark independente *POS UX 2026: The Coherence Gap* (interface-design.co.uk) avalia 4 eixos operacionais que adoto como régua:
1. **Attention economy** — usar sem foco visual sustentado.
2. **Conditioning stability** — sobreviver a updates sem quebrar muscle-memory (Toast vence; Square perdeu em set/2025 com rollout forçado → busca 2-3s mais lenta).
3. **Taxonomy alignment** — search casa com como o caixa nomeia o produto.
4. **Error-recovery speed** — passos/tempo quando a transação falha (Toast falha aqui: void/refund quebra no meio).
Tese central: **"feature accumulation ≠ operational coherence"** — acumular feature não é o mesmo investimento que coerência operacional. Isso vale direto pro oimpresso.

### Ranking visual — top 3 referências

```
🥇 Shopify POS Pro    — keyboard-first + smart grid + search com variante. Teto de UX de fluxo.
🥈 Square Register 2.0 — checkout mais rápido + dual-screen + AI search. Teto de velocidade.
🥉 Bling PDV (BR)      — NFC-e inline na venda + estoque/financeiro no ato. Teto fiscal BR / concorrente direto.
```

> **Leitura estratégica:** os globais (Shopify/Square/Toast) ganham em **fluidez de fluxo e estabilidade**. Os BR (Bling/Omie/Tiny) ganham em **fiscal-no-fluxo + reserva de estoque + atalhos**. O oimpresso já tem o lado BR forte (e mais: FSM, comissão, IA) — falta fechar a **fluidez/conformidade** que os globais têm.

---

## 2. COMPARA — 15 dimensões canônicas (Fase 2)

Lido do código real em `D:\oimpresso.com\resources\js\Pages\Sells\`. Distingo **Inertia v2** (Create.tsx + Index.tsx — o que está em prod biz=1 e em rollout) do **POS Blade legacy** (`/sale-pos/create`, `sale_pos/create.blade.php` — Non-Goal do charter, mas é onde o balcão de alto-volume cairia).

> Nota importante: o brief do Wagner pediu para focar em Create + Index Inertia. O "Blade legacy" do POS rápido é **Non-Goal explícito do charter** (linha 46) — avalio-o como fluxo paralelo, sem inflar nem punir as telas Inertia por ele.

| # | Dimensão | Estado-da-arte (Fase 1) | oimpresso Inertia v2 (Create+Index) | POS Blade legacy (`/sale-pos`) | Distância | Nota /10 |
|---|---|---|---|---|---|---|
| 1 | **Velocidade fluxo** | Shopify/Square: walk-in+3 itens+pagto em ~6-8 ações, tudo teclado | Walk-in já é default; scanner Enter→autoselect; Cmd+Enter salva; ~7-9 ações. Bom, mas form longo (scroll) vs grid 1-tela | Grid clássico 1-tela, foco balcão | curta | **8** |
| 2 | **Busca produto** | Shopify word-completion + variante no resultado | `ProductSearchAutocomplete`: debounce 250ms via TanStack (cancela stale), match SKU/EAN exato, scanner sync no Enter, **agrupa variações + popover tamanho**, `search_fields[]` configurável persistido (nome/SKU/lote/4 custom) | Busca legacy pos.js | **bate/supera** | **9** |
| 3 | **Busca + cadastro cliente inline** | Shopify: add customer por atalho | `CustomerSearchAutocomplete` + **`QuickAddCustomerSheet`** (5 campos, Sheet lateral, **preserva draft**, fetch direto não-Inertia); auto-aplica grupo de preço/prazo/endereço ao selecionar; mostra saldo devedor | Modal in-place legacy | **bate/supera** | **9** |
| 4 | **Atalhos teclado** | Shopify keyboard-first; Tiny atalhos | Create: `/` foca busca, Cmd+Enter salva, Esc blur. Index: J/K nav, ⌘K palette+IA, N nova, ? cheatsheet, B/X/E/R/F. Falta **navegação por seta no dropdown de produto** (só Enter) | Hotkeys legacy | curta | **8** |
| 5 | **Layout/hierarquia visual** | Stripe/Linear: 100% tokens, densidade calibrada | Create: KPIs gigantes + pills scroll-spy + footer sticky. **Cor crua** `bg-amber-50/blue-50/emerald-50` nos KPI/status (viola token). Index: denso e bonito mas **CSS Cowork scoped** (`vd-*`) fora do DS | — | média | **7** |
| 6 | **Mobile/touch** | Square dual-screen touch-first | Charter exige 1280px sem scroll-h (Larissa); funciona em tablet mas é **desktop-first** (form longo, touch targets pequenos na tabela de itens) | Blade responsivo limitado | média | **6** |
| 7 | **Múltiplos pagamentos** | Square/Stripe split + gateways | `PaymentRow` split real, troco/falta/exato automático, **venda a prazo (fiado)** com payment_status=due, contas. Gateways (Asaas/Inter/Stripe) **não no fluxo de criar** | Split legacy | curta | **8** |
| 8 | **Estoque real-time** | Omie bloqueia estoque na pré-venda; Lightspeed multi-local | Search mostra `qty_available` + est. total por variação inline. **Não reserva/bloqueia** estoque no rascunho (Omie faz); sem alerta de zero no carrinho | Legacy | média | **6** |
| 9 | **NFe inline (BR)** | Bling: **NFC-e emitida durante a venda** | NFe/NFCe existe (`VdNfeEmitModal`/`FiscalSection`) **mas só no drawer pós-venda (Index)** ou por flag listener auto. **Create NÃO emite no fluxo** (Non-Goal). Forte em backend (CFOP/FSM), fraco em "1 clique emite na hora" | Legacy separado | **média** | **6** |
| 10 | **Descontos/promoções** | Shopify discount tiles | Desconto por item + por pedido (% ou fixo), **validação de max_discount por permissão**. Sem cupom/regra promocional automática no fluxo; sem redeem reward | Legacy | média | **6** |
| 11 | **Offline/resiliência** | POS sérios: fila offline | **Autosave draft localStorage multi-tenant** (`{biz}.{user}`, TTL 24h, recover com AlertDialog); R9 guard anti-drift de data; failsafe em refetch de preço. **Não tem fila offline real** (sem network = sem salvar no server) | Nenhum | média | **6** |
| 12 | **Histórico/auditoria** | — | `SaleSheet` drawer com `SaleTimeline` + `SaleAuditTrail` + FSM timeline + comentários por item. Estado-da-arte, **mas vive no Index/drawer, não no Create** | Legacy fraco | **supera** | **9** |
| 13 | **Customização per-business** | Lightspeed multi-vertical | Campos colapsáveis condicionais (commission/price-group só se aplicável); `source` balcão/oficina/online (ADR 0192); visões Operacional/Financeira/Produção. Vertical-specific real | Genérico | **bate/supera** | **8** |
| 14 | **Integração/automação** | AI-first 2026 (Copilot/UCP) | **`SaleAiPanel` (Jana copilot)** no drawer + ⌘K "Perguntar à IA"; webhooks; cross-módulo Vendas×Oficina. Falta WhatsApp pós-venda **no fluxo** + gateway automático inline | Nenhum | curta | **8** |
| 15 | **UX feedback** | Linear: estados ricos | Loading (Loader2), erro com scroll-pra-seção + auto-open `<details>`, toast sonner PT-BR, EmptyState, microcopy calorosa ("Digite ou bipe…"). Forte | Legacy básico | curta | **8** |

### Onde o oimpresso BATE o mercado (registrar honestamente)
- **Busca de produto com variação** (dim 2): popover de tamanho + search_fields configurável + scanner sync supera Bling/Tiny e empata Shopify.
- **Cadastro de cliente inline preservando draft** (dim 3): `QuickAddCustomerSheet` é melhor que abrir aba/modal cego.
- **Auditoria/FSM/IA no drawer** (dim 12/14): `SaleAiPanel` + `SaleTimeline` + FSM são features que **nenhum POS global tem** — é vantagem de ERP-com-IA.
- **NumericInputPtBR** (dim 15): parser pt-BR-safe que nasceu de bug real de R$ [redacted Tier 0]k (vírgula decimal). Detalhe que os globais não precisam mas no BR é crítico.
- **Multi-tenant em tudo** (Tier 0 ADR 0093): draft key, localStorage de filtros — `business_id` scoped em cada chave. Shopify/Square são single-tenant por loja.

### Onde PERDE (sem inflar)
- **Conformidade DS** (dim 5): cor crua no Create + CSS Cowork scoped no Index. É o gap nº1 do board (88/90) e o que separa de Champion.
- **NFC-e no fluxo de criar** (dim 9): Bling emite na venda; oimpresso emite depois. Gap de fluxo, não de capacidade.
- **Mobile/touch** (dim 6): desktop-first; Square é touch-first.
- **Reserva de estoque** (dim 8) e **fila offline** (dim 11): Omie reserva; POS sérios têm fila. oimpresso não.

---

## 3. AVALIA — gaps rankeados (Fase 3)

Esforço recalibrado IA-pair (ADR 0106, fator 10x + margem 2x). Gap só é US ativa com sinal de cliente (ADR 0105) — marco abaixo.

| # | Gap | Impacto | Esforço (IA-pair) | Pré-req? | Risco | Sinal cliente? |
|---|---|---|---|---|---|---|
| G1 | **Cor crua → tokens** nos 4 KPI + card status (Create) | médio (conformidade, não move agulha de UX) | ~30-45 min | não | baixo | board/ratchet (ADR 0236) |
| G2 | **PageHeader canon** no Create (já importado, não usado) | médio (consistência sistêmica) | ~30 min | não | baixo | board |
| G3 | **Navegação por seta ↑↓** no dropdown de produto (hoje só Enter) | **alto** (Larissa teclado, fricção por clique) | ~1-2h | não | baixo | Larissa usa scanner+teclado |
| G4 | **N+1 em `handlePriceGroupChange`** → batchar `/products/list` 1 request | médio (perf percebida com carrinho grande) | ~1-2h | endpoint aceitar batch | médio | latência |
| G5 | **NFC-e "emitir agora" no fim do Create** (botão pós-save → VdNfeEmitModal já existe) | **alto** (paridade Bling; balcão vestuário vende+NF) | ~3-5h | flag fiscal biz=4 ativa | médio | **Larissa biz=4 vende e fatura** |
| G6 | **CSS Cowork → tokens DS** no Index (auditar `vd-*`/oklch, eliminar hex/blue) | médio (não move agulha, mas trava Champion) | ~4-8h (1806 linhas) | mapa token↔classe | médio | board/ratchet |
| G7 | **aria-live no foco de linha + aria-sort** nos headers (Index) | médio (a11y WCAG) | ~1-2h | não | baixo | nenhum direto |
| G8 | **Reserva de estoque** no rascunho (paridade Omie pré-venda) | médio | ~1-2 dias | FSM + lock estoque | alto | nenhum ainda |
| G9 | **Fila offline real** (network down = enfileira) | baixo-médio | ~2-3 dias | service worker | alto | nenhum (Larissa tem net) |
| G10 | **Touch/mobile** otimizar tabela de itens + targets | médio | ~1 dia | — | médio | nenhum (monitor 1280px) |

### Categorização

- **P0 — esta semana (alto impacto + baixo esforço, sem pré-req bloqueante):**
  - **G3** (seta no dropdown de produto) — alto impacto UX teclado, ~1-2h, zero pré-req. **Esta é a que move a agulha.**
  - **G1 + G2** (cor crua + PageHeader) — baixo esforço (~1h juntos), destrava 2 dos 3 gaps do board do Create. Necessário pro Champion mas é cleanup, não UX.

- **P1 — próximo cycle (alto impacto + médio esforço):**
  - **G5** (NFC-e emitir-agora no Create) — alto impacto, sinal forte (Larissa fatura), ~3-5h. Botão "Salvar e emitir NFC-e" reusando `VdNfeEmitModal`.
  - **G4** (batch price group) — perf, ~1-2h.
  - **G6** (CSS Cowork→tokens no Index) — necessário pro Index virar Champion, ~4-8h.

- **P2 — backlog (médio impacto / qualquer esforço):**
  - **G7** (a11y aria-live/aria-sort), **G10** (touch/mobile), **G8** (reserva estoque — só se Omie virar objeção comercial).

- **P3 — descartar OU virar ADR feature-wish (ADR 0105):**
  - **G9** (fila offline) — **sem sinal**: Larissa tem internet estável. "Porque POS sério tem" não basta → vira ADR feature-wish, não US ativa.
  - **Checkout conversacional / agentic (UCP)** — vanguarda real mas **sem cliente pedindo**. ADR feature-wish para observar 2026.

> **Distinção que o Wagner pediu — cleanup vs agulha:**
> - **Cleanup de conformidade (G1, G2, G6, G7):** *necessário mas insuficiente.* Tira o Create/Index de Leader e habilita Champion no score, mas Larissa **não percebe diferença** no dia-a-dia. É dívida de DS/ratchet.
> - **UX que move a agulha (G3, G5):** *o que Larissa sente.* Seta no dropdown corta cliques reais; NFC-e no fluxo elimina ir ao Index depois pra faturar. **São essas que justificam ir a Champion de verdade**, não só no número.

---

## 4. NOTA + RECOMENDAÇÃO (Fase 4)

**Cálculo ponderado** (15 dimensões, foco oimpresso/PME BR — telas Inertia v2 em prod/rollout):

| Faixa | Dimensões | Peso | Notas | Σ(dim×peso) |
|---|---|---|---|---|
| Fluxo+busca+atalhos | 1(8) 2(9) 3(9) 4(8) | 3 | 34 | 102 |
| Visual+mobile+pagto+estoque+NFe | 5(7) 6(6) 7(8) 8(6) 9(6) | 2 | 33 | 66 |
| Desc+offline+hist+custom+integ+feedback | 10(6) 11(6) 12(9) 13(8) 14(8) 15(8) | 1 | 45 | 45 |
| **Total** | | **Σpeso = 4×3 + 5×2 + 6×1 = 28** | | **Σ = 213** |

`nota_final = 213 / 28 × 10 = ` **76,1/100** (média ponderada bruta das 15 dimensões do agente).

Esse 76 é a média ponderada **bruta** das 15 dimensões deste agente (mais severa que o board interno porque inclui mobile/offline/reserva-estoque onde o oimpresso é fraco). O board interno (16 dimensões com peso de conformidade DS/craft) dá:

```
NOTA OIMPRESSO Create.tsx (Inertia v2, rollout): 88/100   (board · Leader)
NOTA OIMPRESSO Index.tsx  (Inertia v2, prod):    90/100   (board · Leader)
NOTA agregada 15-dim deste agente (fluxo-cêntrica): 76/100
NOTA REFERÊNCIA TOPO (Shopify POS / Square 2.0):  ~92/100

Gap p/ topo: Create -4 / Index -2 pontos.
Causa principal: conformidade DS (cor crua no Create, CSS Cowork scoped no Index) +
1 gap de fluxo (NFC-e não emite dentro do Create como o Bling faz).
```

> **Por que duas notas divergem (88 vs 76):** o board mede *craft + conformidade DS + maturidade de feature* (onde oimpresso brilha: charter, autosave, FSM, IA). Este agente pondera *fluxo de venda puro* e pune mobile/offline/reserva-estoque. **Ambas são verdadeiras.** A leitura honesta: o oimpresso é **Leader sólido em craft e fiscal BR**, mas tem **teto em fluidez touch/offline** que os globais resolvem. Não está a 1 PR de Champion — está a ~3-4 PRs focados.

### Recomendação concreta — comece por G3

**Comece por G3 (navegação por seta ↑↓ no dropdown de produto do `ProductSearchAutocomplete`).** Alto-impacto (Larissa é teclado+scanner, hoje precisa clicar pra escolher variação), baixo-esforço (~1-2h, o componente já tem `groups`, `expandedProductId`, `handleSelectVariation` — falta `highlightedIndex` + handler ↑↓/Enter, espelhando o que o `CustomerSearchAutocomplete` já faz com `highlightedIndex`). **Sem pré-req bloqueante.** É a única P0 que Larissa *sente*.

**Próxima ação hoje:** abrir `D:\oimpresso.com\resources\js\Pages\Sells\_components\ProductSearchAutocomplete.tsx`, adicionar estado `highlightedIndex` + tratamento `ArrowDown`/`ArrowUp`/`Enter` no `handleKeyDown` (linha ~369) navegando os `groups` achatados, com Pest cobrindo: (1) ↓ destaca 1º item, (2) Enter no destacado seleciona, (3) ↓ em grupo com >1 variação abre popover. Depois emparelhar G1+G2 (cor crua→token + PageHeader) no mesmo cycle — cleanup de ~1h que fecha 2 dos 3 gaps do board do Create.

**Não fazer agora:** G9 (fila offline) e checkout conversacional — sem sinal de cliente, viram ADR feature-wish (ADR 0105), não US ativa.

---

### Refs de código (paths absolutos)
- `D:\oimpresso.com\resources\js\Pages\Sells\Create.tsx` (1647 linhas) — cor crua KPI L916-975, header hand-roll L856-909 (PageHeader importado L21 não usado), N+1 `handlePriceGroupChange` L353-419.
- `D:\oimpresso.com\resources\js\Pages\Sells\_components\ProductSearchAutocomplete.tsx` — `handleKeyDown` L369 (alvo G3), `groups` L199.
- `D:\oimpresso.com\resources\js\Pages\Sells\_components\CustomerSearchAutocomplete.tsx` — `highlightedIndex` L84 (padrão a copiar pro G3).
- `D:\oimpresso.com\resources\js\Pages\Sells\_components\QuickAddCustomerSheet.tsx` — cadastro inline preservando draft.
- `D:\oimpresso.com\resources\js\Pages\Sells\Index.tsx` (1806 linhas) — CSS Cowork `.sells-cowork`/`vd-*` (gap G6/G7).
- `D:\oimpresso.com\resources\js\Pages\Sells\_components\{VdNfeEmitModal,FiscalSection}.tsx` — NFC-e existe (reuso p/ G5).
- Board: `D:\oimpresso.com\memory\governance\scorecards\SCREEN-GRADE-BOARD-2026-05-30.md` (Create 88, Index 90).
