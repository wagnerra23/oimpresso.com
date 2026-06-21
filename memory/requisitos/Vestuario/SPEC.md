---
module: Vestuario
version: "1.0"
last_updated: "2026-06-13"
owner: wagner
status: ativo
status_detalhe: "live em produção via ROTA LIVRE biz=4 desde 2024-Q1"
piloto: ROTA LIVRE — LARISSA COMERCIO DE ARTIGOS DO VESTUARIO LTDA - ME
piloto_inicio: 2024-Q1
cnae_principal: "4781-4/00"
related_adrs:
  - 0121-oimpresso-modular-especializado-por-vertical
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0093-multi-tenant-isolation-tier-0
  - 0035-stack-ai-canonica-wagner-2026-04-26
  - 0066-format-date-shift-3h-preservado-legacy-clientes
  - 0089-capterra-driven-module-evolution
  - 0105-cliente-como-sinal-guiar-sem-mandar
---
<!-- schema-allowlist: US-VEST-* vivem sob "## 3. Capacidades em produção" e "## 4. Capacidades faltantes" (separação prod-vs-backlog deste módulo); headings não casam o gate "User stories" mas o conteúdo US existe e é canônico -->
<!-- pii-allowlist: o único CNPJ literal do arquivo (seção 2 cliente piloto) foi trocado pelo CNPJ fake canônico 11.222.333/0001-81 -->

# Especificação funcional — Modules/Vestuario

> Convenção do ID: `US-VEST-NNN` para user stories, `R-VEST-NNN` para regras Gherkin.
> Campo `Implementado em` linka com Page React (`resources/js/Pages/...`) ou Controller core UltimatePOS quando capacidade já existe no núcleo.

> ⚠️ **Estado especial deste módulo:** o código vertical "Modules/Vestuario" **não existe como pasta física** ainda. ROTA LIVRE usa o **núcleo UltimatePOS + Modules/{Financeiro, NfeBrasil, Copiloto}** com customizações pontuais há 2+ anos. Esta SPEC formaliza o que **está em produção** (validado) + identifica o que **falta encapsular** num `Modules/Vestuario/` próprio pra atender ROTA LIVRE de forma estado-da-arte e habilitar revenda do módulo. Ver ADR 0121 §P7.

## 1. Visão

Módulo vertical pra **lojas de vestuário/moda brasileiras** (CNAE 4781-4/00). Cobre o ciclo completo balcão+ecommerce+estoque-por-tamanho-cor-estação+troca-CDC+comissão-vendedor+fidelidade+sazonalidade.

## 2. Cliente piloto — ROTA LIVRE

| Campo | Valor |
|-------|-------|
| Razão social | LARISSA COMERCIO DE ARTIGOS DO VESTUARIO LTDA - ME |
| CNPJ | 11.222.333/0001-81 <!-- pii-allowlist: CNPJ fake canônico (era PII real do cliente piloto ROTA LIVRE) --> |
| Endereço único | BL0001 "ROTA LIVRE" — Termas do Gravatal, Gravatal/SC, CEP 88735-0 |
| Telefone | (48) 3626-4806 |
| Timezone | `America/Sao_Paulo` |
| `business_id` | 4 |
| Volume operacional | 17.251+ vendas (~99% do sistema novo Laravel) |
| Primeira venda | 2021-05-13 |
| Cadastro | 2021-02-01 |
| Operadora principal | Larissa Fernandes (`larissa-04`, role `Admin#4`) |
| Auxiliares | `rota.vendas-04` (Vendas#4), `caixa-04` (Caixa#4) |

**Sensibilidades operacionais documentadas** (auto-mem `cliente_rotalivre.md`):

1. **Horários decorados com shift +3h** ([ADR 0066](../../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md)) — qualquer correção visual de datetime quebra percepção. NÃO mexer.
2. **`transaction_date` retroativo é fluxo normal** — Larissa registra balcão em lote no fim do dia. Não tentar "corrigir" por algoritmo.
3. **Monitor 1280px** — telas com 21+ colunas inutilizam. DataTables sempre com `columnDefs` escondendo colunas opcionais.
4. **Role custom precisa `location.4` explícita** — `default_location` é pré-requisito da busca de produto em `/sells/create`.
5. **Operação de vestuário real**: ticket médio R$ [redacted Tier 0]-500, pico 14h-17h SP, "Cliente Balcão" + nomes PF (Jociane, Edna, Guilherme, etc.), 2 esquemas de invoice convivendo (`2026/NNNN` + `17NNN`).

## 3. Capacidades em produção (validadas em ROTA LIVRE 2 anos)

> Estas US descrevem o que **já funciona em prod**. Marcadas `live`. Implementação atual é via núcleo UltimatePOS + módulos compartilhados.

### US-VEST-001 · Cadastro de produto com variações tamanho + cor `live`

> **Área:** Catalog
> **Implementado em:** [`app/Variation.php`](../../../app/Variation.php) · [`app/ProductVariation.php`](../../../app/ProductVariation.php) · [`app/VariationTemplate.php`](../../../app/VariationTemplate.php) · [`app/Http/Controllers/VariationTemplateController.php`](../../../app/Http/Controllers/VariationTemplateController.php) · UI legacy `/products`

**Como** Larissa-Admin
**Quero** cadastrar SKU "Camiseta Básica Algodão" com matriz tamanho (PP/P/M/G/GG) × cor (preta/branca/azul) gerando 15 variations
**Para** controlar estoque por combinação tamanho+cor sem criar 15 produtos separados

**Definition of Done (em prod):**
- [x] Modelo `Variation` filho de `ProductVariation` (parent) com SKU+sub_sku independentes
- [x] `VariationTemplate` reutilizável (ex: "tamanhos PP-GG", "cores básicas") por business
- [x] `VariationLocationDetails` mantém estoque por (variation_id, location_id) — multi-location
- [x] DataTables produtos com filtro por variação, cor, tamanho — locale pt-BR
- [x] Multi-tenant: scope `business_id` em `Product`, `Variation`, `VariationTemplate`

### US-VEST-002 · Venda balcão (PDV) com leitor de código de barras `live`

> **Área:** POS
> **Rota:** `GET /pos/create`
> **Implementado em:** [`app/Http/Controllers/SellPosController.php`](../../../app/Http/Controllers/SellPosController.php) · UI legacy Blade `/pos/create`

**Como** `rota.vendas-04` (operador balcão)
**Quero** abrir POS, bipar código de barras da etiqueta, escolher tamanho+cor da peça, finalizar com cartão/dinheiro/pix
**Para** atender cliente balcão em ≤30 segundos sem digitação

**Definition of Done (em prod):**
- [x] `default_location` da role pré-seleciona BL0001
- [x] Busca produto por `sub_sku` (código de barras) ou nome
- [x] Múltiplos meios de pagamento na mesma venda (split tender)
- [x] Estoque deduzido em tempo real por `(variation_id, location_id)`
- [x] Impressão recibo direto sem preview

### US-VEST-003 · Emissão de NFC-e modelo 65 a partir do POS `live (parcial — biz=4 não usa hoje, mas infra existe)`

> **Área:** Fiscal
> **Implementado em:** [`Modules/NfeBrasil/`](../../../Modules/NfeBrasil/) — ver [SPEC NfeBrasil §US-NFE-002](../NfeBrasil/SPEC.md)

**Status real ROTA LIVRE 2026-05:** infra pronta, ROTA LIVRE ainda **não emite NFC-e regularmente** (operação caixa em SC tem regime particular). Pendente discovery: regime tributário de Larissa (MEI? Simples? estoque equivalente a CMV declarado?). Bloqueador comercial, não técnico.

### US-VEST-004 · Histórico de vendas com filtros (cliente, período, vendedor) `live`

> **Área:** Reports
> **Rota:** `GET /sells`
> **Implementado em:** [`app/Http/Controllers/SellController.php`](../../../app/Http/Controllers/SellController.php) — UI Blade legacy

**Como** Larissa-Admin
**Quero** listar vendas filtrando por data, cliente, status pagamento, vendedor
**Para** auditar fechamento mensal e responder dúvida de cliente (ex: "comprei dia 15, qual valor?")

**Definition of Done (em prod):**
- [x] DataTables 21 colunas com `columnDefs` escondendo 5 default (monitor 1280px Larissa)
- [x] Locale pt-BR DataTables (`public/locale/datatables/pt-BR.json`)
- [x] Coluna `transaction_date` aceita retroativo (Larissa registra em lote)
- [x] Export CSV com escape correto de PT-BR
- [x] Multi-tenant scopado por `business_id`

### US-VEST-005 · Estoque por localização (matriz tamanho × cor × loja) `live`

> **Área:** Stock
> **Implementado em:** [`app/VariationLocationDetails.php`](../../../app/VariationLocationDetails.php) · UI legacy `/products` aba estoque

**Como** Larissa-Admin
**Quero** ver tabela "Camiseta Básica" com 15 linhas (5 tamanhos × 3 cores) mostrando qty disponível em BL0001
**Para** decidir o que repor antes da próxima compra

**Definition of Done (em prod):**
- [x] Tabela `variation_location_details` com qty_available indexed por (variation_id, location_id)
- [x] Movimentações via `Transaction::sell|purchase|opening_stock` ajustam automaticamente
- [x] `OpeningStock` permite carga inicial sem afetar `purchase`/`sell` históricos
- [x] Trigger MySQL imutabilidade movimentações financeiras (não rollback silencioso)

### US-VEST-006 · Compra (purchase) com fornecedor + recebimento `live`

> **Área:** Purchases
> **Implementado em:** [`app/Http/Controllers/PurchaseController.php`](../../../app/Http/Controllers/PurchaseController.php)

**Como** Larissa-Admin
**Quero** registrar compra de fornecedor "Confecção XYZ" com 50 peças (5 tam × 2 cor × 5un cada) entrando em BL0001
**Para** ter estoque atualizado e rastrear contas a pagar

**Definition of Done (em prod):**
- [x] FormRequest valida fornecedor pertence ao business
- [x] Linha de purchase grava por (variation_id, qty, unit_cost)
- [x] Atualiza estoque + cria payable em `Modules/Financeiro` (via Observer Transaction)
- [x] Custo médio ponderado calculado em `Variation::default_purchase_price`

### US-VEST-007 · Conta a receber (boleto Asaas) e a pagar `live`

> **Área:** Financeiro
> **Implementado em:** [`Modules/Financeiro/`](../../../Modules/Financeiro/) · [`Modules/RecurringBilling/`](../../../Modules/RecurringBilling/) — ver [SPEC RecurringBilling §US-RB-044](../RecurringBilling/SPEC.md)

ROTA LIVRE usa Asaas como adapter de boleto + extrato (Inter PJ planejado em US-RB-045..047). NFe automática boleto-pago é diferencial cross-vertical (US-RB-044, hoje pronta server-side).

### US-VEST-008 · Múltiplos schemes de invoice convivendo (`2026/NNNN` + `17NNN`) `live`

> **Área:** Invoice numbering
> **Implementado em:** [`app/Http/Controllers/InvoiceLayoutController.php`](../../../app/Http/Controllers/InvoiceLayoutController.php) · `business.invoice_schemes`

**Como** Larissa-Admin
**Quero** rodar 2 schemes em paralelo (legacy `17NNN` antigo + `2026/NNNN` novo)
**Para** não quebrar referência cruzada com ERPs/contadores antigos

**Definition of Done (em prod):**
- [x] Vários `invoice_schemes` por business (UltimatePOS core)
- [x] Default scheme por location/role configurável
- [x] Rebuild de número em transferência interna funciona

### US-VEST-009 · Sidebar/topnav adaptado por monitor 1280px `live`

> **Área:** UX
> **Implementado em:** [`Modules/Vestuario/Http/Controllers/DataController.php`](../../../Modules/Vestuario/Http/Controllers/DataController.php) · verificado@08c4a8f (2026-06-21)

**Definition of Done (em prod, em forma genérica):**
- [x] Locale pt-BR DataTables global
- [x] CSS responsivo monitor 1280px (testado pela operadora)
- [x] Customização `format_date` shift +3h preservada (ADR 0066)

## 4. Capacidades faltantes (gaps específicos vestuário) — backlog priorizado

> Tudo aqui é **gap real** identificado vs concorrentes verticais (Linx Microvix, ProMoz, Vendizap, Bling Loja). Origem: análise comparativa estado-da-arte 2026-Q2.

### US-VEST-020 · Etiqueta/código de barras com tamanho+cor (impressão térmica) `p0`

> owner: — · priority: p0 · estimate: 12h · status: todo · type: story
> blocked_by: —

**Contexto.** Hoje ROTA LIVRE imprime etiqueta padrão UltimatePOS (apenas SKU+nome+preço). Concorrentes verticais (Linx Microvix, ProMoz) imprimem etiqueta com **TAM-COR-COLEÇÃO** legível humano + código barras + QR pra consulta estoque. Sem isso, balcão precisa ler barcode tiny e perde 5-10s por peça.

**Acceptance criteria:**
- [ ] Layout etiqueta térmica (Argox/Zebra padrão) com campos: nome, tamanho, cor, valor, código barras, QR
- [ ] Geração lote: selecionar produto + variação → imprime N etiquetas
- [ ] Configurável por business (largura/altura/margem)
- [ ] Impressão direta via `escpos-php` ou navegador (PDF + autoprint)
- [ ] Test Pest: gera PDF com 10 etiquetas, valida campos presentes

### US-VEST-021 · Devolução/troca com prazo CDC + crédito em conta-cliente `p0`

> owner: — · priority: p0 · estimate: 16h · status: todo · type: story
> blocked_by: —

**Contexto.** CDC (Lei 8.078/90 art. 49) garante 7 dias devolução compras online + prazo legal 30d defeitos. Vestuário é setor com **alta taxa de troca por tamanho** (~15-25% segundo Linx Retail Insights). Hoje ROTA LIVRE faz troca via "criar venda nova + cancelar antiga" — perde rastreabilidade e quebra DRE. Concorrentes (Linx, ProMoz) têm fluxo dedicado **Devolução** que gera crédito automático na ficha do cliente, usável próxima compra.

**Acceptance criteria:**
- [ ] Tabela `vest_devolucoes` (id, business_id, transaction_id_origem, motivo enum [tamanho, cor, defeito, arrependimento], dias_desde_venda, valor_credito)
- [ ] Validação automática: dias_desde_venda ≤ 7 (online) ou ≤ 30 (defeito) — fora disso, gestor sobrepõe com motivo
- [ ] Crédito vai pra `vest_creditos_cliente` (saldo positivo do contact)
- [ ] Próxima venda do contact pode usar crédito (linha negativa antes de pagamento)
- [ ] Estoque retorna pra location origem
- [ ] Audit log Spatie por mutação
- [ ] Teste Pest: 3 cenários (devolução prazo OK, fora prazo com override, defeito 30d)

### US-VEST-022 · Comissão de vendedor (% sobre venda + meta) `p1`

> owner: — · priority: p1 · estimate: 16h · status: todo · type: story
> blocked_by: US-VEST-029 (estação)

**Contexto.** UltimatePOS tem `SalesCommissionAgentController` mas **calcula só por percentual fixo plano**. Vestuário típico tem: comissão escalonada (3% até meta, 5% acima), bônus por categoria/marca específica, comissão diferente PDV vs ecommerce. ROTA LIVRE hoje não usa comissão (operação familiar) mas habilita revenda módulo a outras lojas.

**Acceptance criteria:**
- [ ] Tabela `vest_comissao_regras` (id, business_id, vendedor_user_id, tipo enum [linear, escalonada, por_categoria], pct_base, meta_valor, pct_bonus)
- [ ] Job mensal `CalcularComissoesJob` agrupa vendas do vendedor no período
- [ ] Tela `/vestuario/comissoes` lista vendedor × mês × valor + status (calculada/paga/disputada)
- [ ] Permissão `vestuario.comissoes.manage`
- [ ] Audit log + reapuração permitida (com motivo)
- [ ] Test Pest: 3 cenários (linear, escalonada batida, com bônus categoria)

### US-VEST-023 · Liquidação por categoria/marca/estação (desconto em massa) `p1`

> owner: — · priority: p1 · estimate: 10h · status: todo · type: story
> blocked_by: —

**Contexto.** Hoje ROTA LIVRE precisa editar peça-a-peça pra aplicar desconto de troca-de-estação. Linx Microvix tem **"Campanha de Liquidação"** — define categoria/marca/estação + % desconto + período → aplica em todas variações automaticamente.

**Acceptance criteria:**
- [ ] Tabela `vest_liquidacoes` (id, business_id, nome, escopo_tipo enum [categoria, marca, estacao, manual], escopo_ids JSON, pct_desconto, data_inicio, data_fim, ativa)
- [ ] Aplica em runtime no carrinho POS — preço efetivo = preço × (1 - pct_desconto)
- [ ] Etiqueta etiqueta vermelha "LIQUIDAÇÃO -30%" automática
- [ ] Conflito 2 liquidações → maior desconto vence (configurável)
- [ ] Relatório fim de campanha: peças vendidas × desconto efetivo × margem real

### US-VEST-024 · Programa fidelidade (R$ [redacted Tier 0] = 1 ponto) com resgate em desconto `p1`

> owner: — · priority: p1 · estimate: 18h · status: todo · type: story
> blocked_by: —

**Contexto.** UltimatePOS core **não tem** programa fidelidade nativo. Vestuário usa fidelidade pra reter cliente (~70% das compradoras retornam, segundo SPC Brasil). Linx, ProMoz, Vendizap todos oferecem.

**Acceptance criteria:**
- [ ] Tabela `vest_fidelidade_regras` (id, business_id, pts_por_real, valor_minimo_resgate, validade_pontos_dias)
- [ ] Tabela `vest_fidelidade_movimentos` (id, business_id, contact_id, transaction_id, pontos, tipo [credito/debito], data_expiracao)
- [ ] Listener em `TransactionPaid` (event existente) credita pontos
- [ ] No POS: tela checkout mostra saldo cliente + opção "usar X pontos = R$ Y desconto"
- [ ] Cron diário expira pontos vencidos
- [ ] LGPD: opt-in explícito do cliente armazenado (Art. 7 LGPD)

### US-VEST-025 · Vale-presente / cartão presente (gift card) `p2`

> owner: — · priority: p2 · estimate: 12h · status: todo · type: story
> blocked_by: —

**Contexto.** Diferencial sazonal (Dia das Mães, Natal). Cliente compra "vale R$ [redacted Tier 0]" → recebedor usa em qualquer compra futura. UltimatePOS não tem nativo.

**Acceptance criteria:**
- [ ] Tabela `vest_gift_cards` (id, business_id, codigo único, valor_emitido, valor_disponivel, comprador_contact_id, beneficiario_contact_id?, validade_at, status)
- [ ] Emissão: gera código aleatório 8 chars + QR + PDF impressão
- [ ] Resgate no POS: bipa código → valida saldo → debita parcial/total
- [ ] Anti-fraude: rate-limit tentativas por IP/business
- [ ] Audit log toda movimentação

### US-VEST-026 · Crediário próprio (layaway / parcelado loja) `p2`

> owner: — · priority: p2 · estimate: 24h · status: todo · type: story
> blocked_by: US-VEST-021 (devolução)

**Contexto.** Loja-de-bairro brasileira oferece "fiado" / parcelado próprio (sem cartão). Cliente paga sinal + N parcelas mensais direto na loja. ROTA LIVRE faz informalmente hoje. Concorrentes (ProMoz) têm módulo formal com análise de crédito + carnê.

**Acceptance criteria:**
- [ ] Tabela `vest_crediarios` (id, business_id, contact_id, transaction_id_origem, valor_total, n_parcelas, parcela_valor, dia_vencimento_mes, status)
- [ ] Tabela `vest_crediario_parcelas` (id, crediario_id, n_parcela, vencimento, valor, pago_em?, status)
- [ ] Score interno simples: histórico inadimplência do contact (% atraso) → bloqueia score < 50%
- [ ] Carnê PDF imprimível com N folhas
- [ ] Cron diário marca atrasadas + dispara WhatsApp/SMS lembrete (via `Modules/Whatsapp` futuro)
- [ ] Integra com Asaas: pode emitir boleto da parcela individual

### US-VEST-027 · Provador / fila / pré-venda (separação reserva 24h) `p3`

> owner: — · priority: p3 · estimate: 14h · status: todo · type: story
> blocked_by: —

**Contexto.** Grandes lojas de vestuário (>50m²) têm provador com fila. Cliente leva 5 peças, prova, decide 2 — outras 3 voltam. Hoje em ROTA LIVRE (operação pequena) não é dor; vira diferencial pra revenda futura em lojas maiores. Linx tem.

**Acceptance criteria:**
- [ ] Tabela `vest_reservas_provador` (id, business_id, contact_id?, peças JSON [variation_id, qty], status [provando, decidindo, finalizada, devolvida], expira_em)
- [ ] App tablet operadora: criar reserva, scan barcode, expira 30min
- [ ] Estoque temporariamente subtraído (não vende paralelo)
- [ ] Pós-prova: cria venda só do que ficou + libera estoque do que voltou

### US-VEST-028 · Vendas externas (sacoleira / venda direta) `p3`

> owner: — · priority: p3 · estimate: 16h · status: todo · type: story
> blocked_by: US-VEST-022 (comissão)

**Contexto.** Modelo comum em vestuário interior BR: sacoleira retira N peças, vende em rede pessoal, devolve não-vendido + paga vendido. UltimatePOS não modela.

**Acceptance criteria:**
- [ ] Tabela `vest_sacoleiras` (id, business_id, contact_id, limite_credito, ativa)
- [ ] Tabela `vest_consignacoes` (id, sacoleira_id, retirada_at, devolucao_at, peças JSON, status)
- [ ] Conferência devolução: 3 buckets (vendida, devolvida, perdida-cobrar)
- [ ] Comissão sacoleira % sobre vendido (reusa US-VEST-022)
- [ ] Estoque sai pra location virtual "Sacoleira-X" e volta na conferência

### US-VEST-029 · Atributo "estação" (verão/inverno/meia-estação/atemporal) `p1`

> owner: — · priority: p1 · estimate: 6h · status: todo · type: story
> blocked_by: —

**Contexto.** Hoje "estação" é prefixo no nome ("Verão24-Camiseta-..."). Quebra busca, relatório por coleção, liquidação automática.

**Acceptance criteria:**
- [ ] Migration adiciona `products.estacao_id` (FK pra `vest_estacoes`)
- [ ] Tabela `vest_estacoes` (id, business_id, nome, ano, ativa, data_inicio, data_fim)
- [ ] Filtro `/products?estacao=Verao24`
- [ ] Relatório "rotação por estação" (sell-through %)
- [ ] Pré-requisito de US-VEST-022 (comissão por estação) e US-VEST-023 (liquidação)

### US-VEST-030 · Ecommerce (loja virtual / WhatsApp catálogo) `p3`

> owner: — · priority: p3 · estimate: 60h+ · status: todo · type: epic
> blocked_by: US-VEST-020, US-VEST-029

**Contexto.** Vendizap captura mercado de "loja sem ecommerce que quer começar simples". Catálogo público + WhatsApp Business como canal. ROTA LIVRE não pediu hoje — fica como **ADR feature-wish** até sinal qualificado (ADR 0105).

**Status:** **proposta**, sem código. Aguarda 3+ signals de cliente real (tarefa MCP `decisions-search` quando vier).

## 5. Concorrentes verticais

| Concorrente | Foco | Pricing | Pontos fortes | Lacunas |
|------|------|---------|---------------|---------|
| **Linx Microvix Vestuário** | grandes redes (>5 lojas) | R$ [redacted Tier 0]-2500/m | profundidade SKU, multi-loja, PCP roupa, BI Linx | preço alto, lock-in caro, suporte demorado |
| **ProMoz** | médio-pequeno (1-3 lojas) | R$ [redacted Tier 0]-700/m | UI simples, treinamento bom | falta NFe-de-boleto-pago, BI fraco |
| **Vendizap** | micro (catálogo WhatsApp) | R$ [redacted Tier 0]-150/m | onboarding instant (5min) | sem PDV físico, sem fiscal robusto |
| **Bling Loja** | horizontal raso | R$ [redacted Tier 0]-400/m | integração marketplace | nada vertical (sem matriz tam×cor profunda) |
| **F360** | nicho moda regional | R$ [redacted Tier 0]-800/m | foco regional sul | UI legacy |

## 6. Diferenciais oimpresso vs concorrentes

1. **Jana IA com memória persistente** (ADR 0035-0053) — única vertical moda com IA conversacional contextual. Larissa pergunta "quanto vendi de Verão24 esta semana?" e recebe resposta com dados reais.
2. **NFe-de-boleto-pago automática** (US-RB-044, ADR 0089) — cross-vertical do núcleo. Concorrente nenhum tem.
3. **Multi-tenant Tier 0 IRREVOGÁVEL** (ADR 0093) — isolation por design, não retrofit. Linx Microvix multi-loja mistura schema.
4. **Stack moderna** (Laravel 13.6 + React 19 + Inertia v3 + Pest 4) — concorrentes em PHP 7.x + jQuery legacy.
5. **Governança formal** (Constituição v2 — ADR 0094) — 36% das enterprises não têm; lojas pequenas zero.
6. **Suporte direto via WhatsApp** com pessoa real (Wagner) — concorrentes têm chamado-em-fila.
7. **Customizações preservadas como first-class** (ex: shift +3h ROTA LIVRE — ADR 0066) — concorrente "atualiza e quebra".
8. **Sinal qualificado pra evolução** (ADR 0105) — backlog só recebe item se cliente paga e reporta. Ninguém faz no setor.

## 7. Arquitetura técnica

### Modelos UltimatePOS reaproveitados (núcleo)

| Modelo | Vestuário usa pra | Customização |
|--------|-------------------|--------------|
| `App\Product` | peça (camiseta, calça) | + `estacao_id`, `genero` (M/F/Infantil/Unissex) |
| `App\Variation` | combinação tam+cor | sub_sku como código de barras impresso |
| `App\VariationTemplate` | template "tamanhos PP-GG", "cores básicas" | reutilizável |
| `App\VariationLocationDetails` | estoque por loja | reutilizar |
| `App\Brands` | marca (Hering, Riachuelo, marca própria) | reutilizar |
| `App\Category`, `App\SubCategory` | feminino/masculino/infantil > calça/blusa/vestido | reutilizar |
| `App\Transaction` (`type=sell\|purchase`) | venda + compra | reutilizar |
| `App\Contact` | cliente final + fornecedor | reutilizar |
| `Modules\Financeiro` | AR/AP, extrato bancário | reutilizar |
| `Modules\NfeBrasil` | NFC-e modelo 65 PDV | reutilizar (US-NFE-002) |
| `Modules\RecurringBilling` | boleto avulso Asaas | reutilizar (US-RB-044) |

### Tabelas novas Modules/Vestuario (a criar quando US-VEST-020+ entrarem)

- `vest_estacoes` — coleção/estação por business
- `vest_devolucoes` + `vest_creditos_cliente` — fluxo CDC
- `vest_comissao_regras` — comissão vendedor escalonada
- `vest_liquidacoes` — campanhas desconto em massa
- `vest_fidelidade_regras` + `vest_fidelidade_movimentos` — programa pontos
- `vest_gift_cards` — vale-presente
- `vest_crediarios` + `vest_crediario_parcelas` — layaway
- `vest_reservas_provador` — fila provador
- `vest_sacoleiras` + `vest_consignacoes` — venda externa

Todas com `business_id` indexed + FK + global scope (skill `multi-tenant-patterns` Tier A — ADR 0093).

### Padrões obrigatórios

- **Cada controller** scopa por `business_id` (Trait `BusinessScope` no Model + `where business_id=session('business.id')` no Controller defesa em profundidade)
- **Audit log Spatie** em todas mutações financeiras (devolução, comissão, gift_card, crediário)
- **DataController** por sub-feature (sidebar entry, perms Spatie, hooks UltimatePOS)
- **Pest Feature** com 1 happy + 1 isolamento + 1 edge case mínimo por US

## 8. Roadmap próximos 12 meses

| Quarter | US prioridade | Marco |
|---------|---------------|-------|
| **2026-Q2 (atual)** | US-VEST-029 (estação) + US-VEST-020 (etiqueta) | fundação pra liquidação/comissão |
| **2026-Q3** | US-VEST-021 (devolução CDC) + US-VEST-023 (liquidação) + US-VEST-022 (comissão) | paridade Linx Microvix em ROTA LIVRE |
| **2026-Q4** | US-VEST-024 (fidelidade) + US-VEST-025 (gift card) | sazonalidade Black Friday + Natal |
| **2027-Q1** | US-VEST-026 (crediário) + US-VEST-027 (provador) | revenda pra 2º cliente Vestuario |
| **2027-Q2** | US-VEST-028 (sacoleira) + revisão US-VEST-030 (ecommerce — só com sinal) | network effect |

> Cadência ajustada por **ADR 0106** (recalibração 10x IA-pair) e **ADR 0105** (cliente como sinal). Tasks via `tasks-create` no MCP, não TODO inline.

## 9. Anti-padrões (o que NÃO fazer)

- ⛔ **Mexer no `format_date` shift +3h** — ADR 0066, Larissa decorou. Quebrar = regressão percebida.
- ⛔ **Adicionar coluna default em DataTables `/sells`** sem checar largura 1280px (ADR 0094 §1).
- ⛔ **Criar role custom sem `location.4` explícita** — trava busca produto em `/sells/create` (incidente 2026-04-24).
- ⛔ **Usar `withoutGlobalScopes` em Model com `business_id`** sem comment `// SUPERADMIN: <razão>` (Tier 0 ADR 0093).
- ⛔ **Hard-deletar `Variation` com sell_lines históricos** — quebra rastreabilidade contábil. Usar soft delete.
- ⛔ **Criar tabela `vest_*` sem `business_id` indexed + FK** — Tier 0 IRREVOGÁVEL.
- ⛔ **Subir feature de fidelidade sem opt-in LGPD** — Art. 7 LGPD (consentimento explícito).
- ⛔ **Implementar US-VEST-030 (ecommerce) sem 3+ sinais qualificados** — ADR 0105.
- ⛔ **Smoke test com `business_id=1`** (Wagner WR2, prod) — ADR 0101 manda biz=4 (cliente piloto).
- ⛔ **PII de Larissa ou clientes finais em PR/log/commit** — usar `[REDACTED]` ou `PiiRedactor` (ADR 0094 §6).

## 10. Métricas de sucesso

### Operacional ROTA LIVRE (validar manutenção)

- **Disponibilidade**: ≥99.5% mês (Hostinger SLA)
- **Tempo médio venda balcão**: ≤30s do scan ao recibo (US-VEST-002)
- **Erro de estoque (físico vs sistema)**: ≤0.5% mês
- **Reclamação Larissa via WhatsApp**: ≤1 por semana sobre bug oimpresso

### Negócio (revenda módulo Vestuario)

- **Q3-2026**: 2º cliente Vestuario assinando (sinal de revendabilidade)
- **Q4-2026**: 5 clientes Vestuario MRR somado ≥ R$ [redacted Tier 0]k (validação P5 ADR 0121)
- **2027-Q4**: 15 clientes Vestuario, MRR ≥ R$ [redacted Tier 0]k (lifecycle = "ativo" ADR 0121)

### Técnico (qualidade)

- **Cobertura Pest módulo**: ≥70% linhas (target Sprint S5+)
- **Multi-tenant isolation tests**: 100% das US-VEST-* (regra Tier 0)
- **Pest verde local antes de PR**: 100% (auto-mem `feedback_tenancy_changes_require_pest_local.md`)
- **PII em log/commit**: 0 ocorrências (skill `commit-discipline` Tier A enforce)

## 11. Decisões pendentes

- [ ] Regime tributário ROTA LIVRE — discovery com Larissa (MEI? Simples? bloqueador US-VEST-003 NFC-e regular)
- [ ] Modules/Vestuario fica hoje **virtual** (núcleo + customizações em DataController genérico) ou criar pasta `Modules/Vestuario/` formal? — depende do 2º cliente Vestuario chegar (ADR 0105)
- [ ] Comissão vendedor: começar pelo escalonado simples (US-VEST-022) ou fechar discovery com 1 loja real antes de codar?
- [ ] Etiqueta térmica: padrão Argox/Zebra é universal SC ou tem variação regional?
- [ ] Programa fidelidade: pontos não-transferíveis (LGPD-easy) ou transferíveis (Linx tem)?
- [ ] Crediário: integrar com Asaas (boleto por parcela) ou ficar 100% offline (carnê PDF)?
- [ ] Ecommerce US-VEST-030: aguardar sinal qualificado real (ADR 0105) — não codar antes.

## 12. Referências

- ADR 0121 — Modular especializado por vertical (mãe deste módulo)
- ADR 0094 — Constituição v2 (princípios duros)
- ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0066 — `format_date` shift +3h preservado
- ADR 0089 — Capterra-driven evolution
- ADR 0105 — Cliente como sinal qualificado
- ADR 0106 — Recalibração velocidade fator 10x IA-pair
- Auto-mem `cliente_rotalivre.md` — perfil + sensibilidades + histórico incidentes
- Auto-mem `reference_clientes_ativos.md` — 56 businesses, ROTA LIVRE concentra 99% volume
- Auto-mem `reference_db_schema.md` — schema UltimatePOS multi-tenant
- Auto-mem `reference_ultimatepos_integracao.md` — hooks DataController + Observer Transaction
- SPEC `Modules/RecurringBilling` — fluxo boleto-pago + NFe automática (US-RB-044)
- SPEC `Modules/NfeBrasil` — NFC-e modelo 65 (US-NFE-002)
- SPEC `Modules/Financeiro` — extrato bancário + AR/AP

---

**Última atualização:** 2026-05-10 — SPEC criada formalizando o que ROTA LIVRE usa em prod há 2 anos + identificando gaps vertical vs Linx Microvix/ProMoz/Vendizap. Próximo passo: tasks-create no MCP pras US-VEST-029 (P1) + US-VEST-020 (P0) que destravam o resto do roadmap Q3-2026.
