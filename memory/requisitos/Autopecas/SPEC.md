---
module: Autopecas
version: "1.0"
last_updated: "2026-06-13"
status: rascunho
lifecycle: aguarda-sinal-qualificado
piloto: "Vargas (candidato — sinal qualificado real, contrato pendente)"
piloto_previsao: "depende de Vargas assinar pioneer Q4/26-Q1/27 (ADR 0105 enforcement)"
cnae_principal: "4530-7/03 (comércio a varejo de peças e acessórios novos para veículos automotores)"
cnae_secundarios: ["4530-7/01", "4530-7/02", "4530-7/04", "4530-7/05"]
related_adrs: [0125-modules-autopecas-feature-wish, 0121-oimpresso-modular-especializado-por-vertical, 0094-constituicao-v2-7-camadas-8-principios, 0093-multi-tenant-isolation-tier-0, 0105-cliente-como-sinal-guiar-sem-mandar, 0106-recalibracao-velocidade-fator-10x-ia-pair, 0035-stack-ai-canonica-wagner-2026-04-26, 0011-alinhamento-padrao-jana, 0089-capterra-driven-module-evolution, 0119-migration-factory-capacidade-institucional]
related_proposals: []
last_review: 2026-05-10
owner: [W]
---

# Especificação funcional — Autopecas (planejado — não existe)

> Convenção do ID: `US-AP-NNN` para user stories, `R-AP-NNN` para regras Gherkin.
> **Modulo NÃO existe em código.** Este SPEC é **antecipatório** — formaliza o contrato de construção SE/QUANDO Vargas (ou outro candidato autopeças saudável) assinar contrato pioneer ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) gatilho).
> Antes de scaffoldear (caso ativado), ler [Modules/Vestuario](../../../Modules/Vestuario) (módulo vertical live referência) + [Modules/Jana](../../../Modules/Jana) + [Modules/OficinaAuto SPEC](../OficinaAuto/SPEC.md) (catálogo peças shared infra) e imitar ([ADR 0011](../../decisions/0011-alinhamento-padrao-jana.md)).

## 1. Visão

ERP vertical brasileiro pra **autopeças balcão SMB** (1-15 balconistas, 200-3000 SKU, 30-300 vendas/dia) que substitui Auto Manager, Lokoz, FutureSysSoft, Mecanizou, Linx Microvix entregando: catálogo SKU + tabela aplicação por veículo (chassis/ano/modelo/montadora), venda balcão p95<1500ms, tabela preço por categoria/montadora, controle estoque mínimo + alertas, devolução com impacto estoque, garantia loja vs fabricante, NFC-e ágil + NFe-de-boleto-pago, e Jana IA conversacional pra "qual peça pra Civic 2015 freio dianteiro?".

**Tese de entrada:** quadrante "vertical autopeças balcão + tech moderno + IA conversacional + NFe automática" está vazio na BR (research pendente Q4/26). Concorrentes legacy desktop, sem IA, NFe semi-manual, mobile fraco.

**Status atual:** **NÃO em construção.** Sem Vargas (ou substituto qualificado) assinar contrato pioneer, **viola ADR 0105**. Modules/Vestuario (live ROTA LIVRE) cobre ~30-40% da base técnica (catálogo SKU multi-atributo, venda balcão, NFC-e). Gap: tabela aplicação veicular + devolução + garantia.

## 2. Audiência alvo

### Perfil-alvo: autopeças balcão BR de pequeno-médio porte

| Dimensão | Faixa |
|---|---|
| Funcionários | 2-15 (1 dono + 1-3 balconistas + 1-2 entregadores + 1 financeiro) |
| GMV anual | R$ [redacted Tier 0]k – R$ [redacted Tier 0]M (Vargas R$ [redacted Tier 0]M está no topo da faixa) |
| SKU ativo | 200 – 3.000 (peças únicas) — alta rotatividade |
| Vendas/dia | 30 – 300 (balcão presencial + telefone + WhatsApp) |
| Estado fiscal | Simples Nacional (maioria) ou Lucro Presumido |
| CNAE principal | **4530-7/03** (varejo peças novas) — secundários 4530-7/01 (peças usadas), 4530-7/02 (atacado), 4530-7/04 (motocicletas), 4530-7/05 (acessórios) |
| Sistema atual | Auto Manager / Lokoz / FutureSysSoft / Mecanizou / Linx Microvix / WR Sistemas Delphi (Vargas) / Excel+WhatsApp |
| Cliente final | Oficinas (B2B 60-70%), PF (30-40%), frota PJ pequena |
| Geografia | Brasil inteiro — Vargas localização a confirmar via banco Firebird |
| TAM | ~30-40k autopeças formais BR (Sindipeças); R$ [redacted Tier 0]bi/ano mercado reposição autopeças BR |

### Mecânicas operacionais típicas

1. Cliente chega no balcão (presencial) ou liga/WhatsApp pedindo peça
2. Balconista pergunta: marca, modelo, ano, sintoma → busca catálogo aplicação
3. Sistema lista peças compatíveis (original + similares + preço + estoque)
4. Cliente decide qualidade vs preço (original Bosch R$ [redacted Tier 0] vs similar Nakata R$ [redacted Tier 0])
5. Balconista lança venda (NFC-e instantânea); pagamento PIX/cartão/boleto/crediário
6. Entregador despacha (raio 5-15km) ou cliente leva
7. Eventual devolução D+7: cliente errou peça ou peça não bateu — registra motivo + estoque retorna
8. Garantia: peça defeituosa em 30-90d → loja substitui ou aciona fabricante via RMA
9. Reposição estoque: balconista vê alerta "estoque mínimo Bosch BB-318 cubo Gol" → comprador cota 3 fornecedores → compra

### Candidato piloto (Vargas — qualificado conforme ADR 0105)

- **Vargas** — saudável OfficeImpresso (Delphi versão 1468), R$ [redacted Tier 0]M GMV/ano, 26 anos relação Wagner-Vargas
- **Razão social provável:** "Vargas Acessorios" (CNAE 4530-X autopeças/comércio peças)
- **Sinal qualificado:** ✅ paga R$ [redacted Tier 0]-850/m WR Sistemas (estimativa) + ✅ Wagner reporta direto + 🟡 build desatualizado (sinal churn latente — janela ação)
- **Risco:** maior cliente legacy, perder = -R$ [redacted Tier 0]/m + racha narrativa "26 anos relação"
- **Estratégia:** outreach Wagner direto, presencial/Zoom 60min, pacote pioneer Enterprise R$ [redacted Tier 0]/m grandfathered + 50% off 6m + setup R$ [redacted Tier 0]

**Conclusão:** Vargas é piloto realista. Roadmap deste SPEC é **CONDICIONAL** ao gatilho descrito em §9 (Vargas assina contrato).

## 3. User Stories — Capacidades core

> Backlog antecipatório (US-AP-*) — só ativa SE/QUANDO Vargas (ou candidato qualificado) assinar contrato pioneer (ADR 0105). Módulo não existe em código.

Priorização: **P0** = bloqueia 1ª piloto Vargas (paridade competitiva mínima vs Auto Manager/Lokoz) · **P1** = competitivo vs líderes do nicho · **P2** = diferencial de longo prazo · **P3** = backlog/feature-wish.

### US-AP-001 · Catálogo SKU + tabela aplicação por veículo (chassis/ano/modelo/montadora) — **P0**

> **Área:** Catálogo
> **Rota:** `GET/POST /autopecas/produtos`
> **Controller:** `ProdutoCatalogoController`
> **Permissão Spatie:** `autopecas.produto.{view,create,update}`

**Implementado em:** _pendente_ — módulo wish aguarda-sinal-qualificado (Vargas), sem código do módulo no repo; catálogo veicular a construir

**Como** balconista
**Quero** buscar peça por marca+modelo+ano (ex: "Civic 2015 freio dianteiro") e ver SKUs compatíveis com aplicação detalhada (chassis_range, ano_min, ano_max, motor, observação)
**Para** atender cliente em <30s sem abrir 3 catálogos paralelos

**Definition of Done:**
- [ ] Tabela `autopecas_produtos` extends UltimatePOS `products` (codigo_oem, codigo_fabricante, marca_peca, categoria_peca, qualidade enum [original/oem/genuina/similar/recondicionada])
- [ ] Tabela `autopecas_aplicacoes` (id, business_id, produto_id FK, montadora, modelo, ano_min, ano_max, motor, chassis_inicial, chassis_final, observacao)
- [ ] Tabela `autopecas_montadoras` seed (Volkswagen, Fiat, Chevrolet, Ford, Honda, Toyota, Hyundai, Renault, Peugeot, Citroen, Nissan, BMW, Mercedes, Audi, Jeep, Mitsubishi, Kia, Suzuki, Volvo)
- [ ] Busca facetada: montadora → modelo → ano → categoria peça
- [ ] Multi-tenant `business_id` global scope (skill `multi-tenant-patterns` Tier A — ADR 0093)
- [ ] Pest Feature: cadastro válido + busca aplicação retorna SKUs corretos + isolation cross-biz
- [ ] **Reuso futuro:** quando Modules/OficinaAuto ativar, extrair tabelas `pecas` + `aplicacoes` como shared infra

**Concorrência:** todos verticais autopeças têm. Padrão esperado. **Diferencial via UX rápida + Jana IA (US-AP-013).**

---

### US-AP-002 · Venda balcão rápida (p95<1500ms, 1 tela 1 atalho) — **P0**

> **Área:** Vendas
> **Rota:** `GET /autopecas/balcao` + `POST /autopecas/balcao/finalizar`
> **Controller:** `BalcaoController`
> **Reusa:** UltimatePOS `transactions` + Modules/Vestuario padrão venda

**Implementado em:** _pendente_ — módulo wish aguarda-sinal-qualificado; venda balcão Autopecas a construir (reuso Vestuario é padrão a imitar, não implementação da US)

**Como** balconista em pico
**Quero** vender 5 peças + cliente + pagamento + NFC-e em <60 segundos sem trocar de tela
**Para** atender 30-50 clientes/h em pico (Vargas opera 8-18h, sazonalidade fim de semana)

**DoD:**
- [ ] Page `Autopecas/Balcao.tsx` 1-tela com busca produto + listagem item + cliente + pagamento + finalizar
- [ ] Atalhos teclado: F2 busca produto, F4 cliente, F8 pagamento, F12 finalizar (padrão balconista decora)
- [ ] Performance budget: p95<1500ms `POST /balcao/finalizar` (NFC-e síncrona) — gate Pest browser-test
- [ ] Suporte código de barras (USB scanner emul teclado)
- [ ] Cliente não cadastrado: "Consumidor Final CPF opcional" sem trava
- [ ] Multi-tenant scope + audit log toda venda

**Concorrência:** Auto Manager 🟡 (multi-tela), Lokoz 🟡, Linx Microvix ✅ (PDV unificado). **oimpresso ✅ planejado** — UX rápida é diferencial.

---

### US-AP-003 · Tabela preço por categoria/montadora (markup configurável) — **P0**

> **Área:** Pricing
> **Rota:** `GET/POST /autopecas/tabelas-preco`
> **Controller:** `TabelaPrecoController`

**Implementado em:** _pendente_ — módulo wish aguarda-sinal-qualificado, nada construído

**Como** dono/comprador
**Quero** definir markup % por (categoria_peca × montadora × qualidade) — ex: freio Volkswagen original = 40%, similar = 25%; suspensão Fiat = 35%
**Para** preço sair calculado automaticamente sem digitar a cada compra

**DoD:**
- [ ] Tabela `autopecas_tabelas_preco` (id, business_id, categoria_peca, montadora, qualidade, markup_pct, valor_min, valor_max, ativo)
- [ ] Cálculo: `preco_venda = max(valor_min, custo × (1 + markup/100))` clipped at `valor_max`
- [ ] Override por SKU (peça específica fora regra)
- [ ] Histórico mudanças (audit log)
- [ ] Multi-tenant scope

**Concorrência:** Auto Manager ✅, Lokoz ✅, Linx Microvix ✅. Esperado.

---

### US-AP-004 · Controle estoque mínimo + alertas reposição — **P0**

> **Área:** Estoque
> **Rota:** `GET /autopecas/estoque/alertas`
> **Reusa:** UltimatePOS `variation_location_details` + Job daily

**Implementado em:** _pendente_ — módulo wish aguarda-sinal-qualificado, nada construído

**Como** comprador/dono
**Quero** ver dashboard "peças em estoque mínimo" com sugestão de cotação automática (3 fornecedores cadastrados)
**Para** não rodar fora-de-estoque e perder venda balcão (dor #1 setor: stockout fideliza concorrente)

**DoD:**
- [ ] Campo `produtos.estoque_minimo` por SKU + variation_location
- [ ] Job daily `autopecas:check-stock-min` compara `qty_available < estoque_minimo` → cria alerta
- [ ] Page `Estoque/Alertas.tsx` com lista + ação "cotar fornecedores" (US-AP-008)
- [ ] WhatsApp/email comprador opt-in
- [ ] Pest: estoque cai abaixo mínimo → alerta criado

**Concorrência:** Auto Manager ✅, Lokoz ✅, FutureSysSoft ✅. Esperado.

---

### US-AP-005 · Devolução com motivo + impacto em estoque — **P0**

> **Área:** Pos-venda
> **Rota:** `GET/POST /autopecas/devolucoes`
> **Controller:** `DevolucaoController`

**Implementado em:** _pendente_ — módulo wish aguarda-sinal-qualificado, nada construído

**Como** balconista
**Quero** registrar devolução D+0 a D+7 com motivo enum (cliente_errou_peca / nao_serviu / defeito / arrependimento / troca_marca) + retornar peça ao estoque automaticamente
**Para** controle preciso (devolução é 5-12% do faturamento autopeças BR — não é exceção)

**DoD:**
- [ ] Tabela `autopecas_devolucoes` (id, business_id, transaction_id_origem, transaction_id_devolucao, motivo, valor_devolvido, retorno_estoque enum [sim_revenda/sim_baixa/nao_descarte], anexo_foto_path, observacao)
- [ ] Estado machine: pendente → aprovada / rejeitada
- [ ] Trigger: aprovada + retorno_estoque=sim_revenda → ajuste estoque +N + emite NF devolução
- [ ] Limite D+7 configurável por business (Vargas pode definir D+15)
- [ ] Audit log + relatório mensal % devolução por motivo
- [ ] Pest: devolução aprovada com retorno → estoque sobe + NF gerada

**Concorrência:** Auto Manager ✅, Lokoz 🟡 (sem motivo enum), FutureSysSoft 🟡. **Diferencial médio** se enum + analytics motivo.

---

### US-AP-006 · Garantia loja vs fabricante (registro + lookup + RMA) — **P0**

> **Área:** Pos-venda
> **Rota:** `GET/POST /autopecas/garantias`
> **Controller:** `GarantiaController`

**Implementado em:** _pendente_ — módulo wish aguarda-sinal-qualificado, nada construído

**Como** balconista/dono
**Quero** ao vender peça registrar garantia (loja 30d default + fabricante 90-365d conforme tipo) e quando cliente reclamar D+15 saber: "está em garantia loja → trocar agora" ou "garantia fabricante → abrir RMA Bosch"
**Para** atender cliente sem disputa "tinha garantia ou não?" + processo RMA padronizado

**DoD:**
- [ ] Tabela `autopecas_garantias` (id, business_id, transaction_id, produto_id, tipo enum [loja/fabricante], prazo_dias, vence_em, status, fabricante_nome, fabricante_protocolo_rma, custo_loja_substituicao_pago)
- [ ] Lookup por NF + SKU + cliente: retorna estado garantia atual
- [ ] Job daily `garantia_vence_em - 7d` → notifica balconista (anti-fraude cliente último-dia)
- [ ] Workflow RMA: status pendente → enviado_fabricante → aprovado / rejeitado
- [ ] Custo loja: se troca imediata + fabricante demora → registra `custo_loja_substituicao_pago` pra reconciliar com RMA aprovado
- [ ] Relatório custo garantia % faturamento

**Concorrência:** Auto Manager 🟡 (só prazo simples), Lokoz ❌, FutureSysSoft 🟡. **Diferencial alto** loja vs fabricante separado.

---

### US-AP-007 · NFC-e ágil (modelo 65) + NFe-de-boleto-pago automática — **P1**

> **Área:** Fiscal
> **Reusa:** [Modules/NfeBrasil](../NfeBrasil/SPEC.md) US-NFE-002 (NFC-e ✅ pronta) + [Modules/RecurringBilling](../RecurringBilling/SPEC.md) US-RB-044 (boleto pago→NFe ✅ entregue)

**Implementado em:** _pendente_ — o adapter Autopecas (split NFC-e peça, CFOP 5102/5949, envio cliente) não existe; as deps NfeBrasil US-NFE-002 e RecurringBilling US-RB-044 são prontas mas são outras US, não esta

**Como** balconista
**Quero** que NFC-e dispare em <500ms ao finalizar venda (sem cliente esperar) E quando boleto crediário cair pago, NFe automática emitida sem clique humano
**Para** zero atrito balcão + zero esquecimento NF crediário

**DoD:**
- [x] US-RB-044 (boleto pago→NFe) ✅ entregue
- [x] US-NFE-002 (NFC-e ✅ pronta SC homologação biz=1)
- [ ] Adapter Autopecas: split NF se venda mista peças (NFC-e modelo 65) sem serviço
- [ ] CFOP 5102 (peça nova) ou 5949 (acessório); CSOSN 102 Simples
- [ ] NFC-e enviada por email/WhatsApp cliente (opt-in)
- [ ] Fallback gracioso: SEFAZ down → contingência offline + retry
- [ ] Pest: venda balcão → NFC-e emitida cstat 100 em <500ms

**Concorrência:** Auto Manager ✅, Lokoz ✅, Linx Microvix ✅. Padrão. **Diferencial:** Vargas hoje no Delphi WR não tem NFC-e ágil — migração entrega isso.

---

### US-AP-008 · Cotação fornecedores (RFQ multi-fornecedor) — **P1**

> **Área:** Compras
> **Reusa:** UltimatePOS `contacts.type=supplier`

**Implementado em:** _pendente_ — módulo wish aguarda-sinal-qualificado, nada construído

**Como** comprador
**Quero** enviar pedido de cotação simultânea pra 3-5 fornecedores (peça X qty Y) e comparar respostas + escolher melhor preço/prazo
**Para** garantir margem peça + documentar cotação (ISO 9001 friendly se Vargas certificado)

**DoD:**
- [ ] Tabela `autopecas_cotacoes` + `autopecas_cotacao_respostas` (fornecedor_id, preco_unit, prazo_entrega_dias, validade_resposta, observacao)
- [ ] Envio email/WhatsApp fornecedor com link público resposta `/cotacao/{token}`
- [ ] UI compare lado-a-lado 3-5 respostas
- [ ] Trigger compra direta (gera Purchase Transaction UltimatePOS)
- [ ] Histórico cotações por fornecedor (rating delivery + preço)

**Concorrência:** Auto Manager 🟡, Lokoz 🟡, FutureSysSoft 🟡 (manual). **Diferencial mid-tier.**

---

### US-AP-009 · Multi-depósito (loja matriz + filial + estoque consignado) — **P1**

> **Área:** Estoque
> **Reusa:** UltimatePOS `business_locations` + `variation_location_details`

**Implementado em:** _pendente_ — módulo wish aguarda-sinal-qualificado; UI dedicada Autopecas de transferência a construir (base UltimatePOS existe, mas a US é a camada Autopecas)

**Como** dono multi-loja
**Quero** ver estoque agregado de todas localizações + transferir peça entre depósitos com nota fiscal interna (NFe transferência CFOP 5409/6409)
**Para** atender cliente que está na matriz mas peça está na filial sem perder venda

**DoD:**
- [ ] Já suportado UltimatePOS (validar)
- [ ] UI dedicada Autopecas: "transferir peça" 1-clique
- [ ] NFe transferência automática (CFOP 5409 same UF / 6409 outra UF)
- [ ] Multi-tenant scope inviolável (transferência só entre locations do mesmo business_id)
- [ ] Pest: transferência matriz→filial gera NFe + estoque ajusta correto

**Concorrência:** Auto Manager ✅, Lokoz ✅, Linx Microvix ✅. Esperado mid-tier.

---

### US-AP-010 · Crediário interno (limite cliente + parcelamento + boleto) — **P1**

> **Área:** Financeiro
> **Reusa:** [Modules/Financeiro](../Financeiro/) AR + Modules/RecurringBilling boleto

**Implementado em:** _pendente_ — módulo wish aguarda-sinal-qualificado, nada construído

**Como** financeiro
**Quero** vender peça em crediário interno (3-12x sem cartão) com limite por cliente + score interno + boleto/PIX nas datas
**Para** B2B oficinas pagam 30/60/90d (padrão setor); sem crediário, perde 30-50% do volume

**DoD:**
- [ ] Tabela `autopecas_crediario_clientes` (contact_id, limite_total, limite_disponivel, score_interno, status enum [ativo/suspenso])
- [ ] Trigger venda crediário: cria N parcelas em `transaction_payments` com data vencimento
- [ ] Boleto/PIX automático cada parcela (Modules/RecurringBilling reuse)
- [ ] Inadimplência: bloqueia novo crediário se 1+ parcela atraso 5d
- [ ] Audit log mudanças limite
- [ ] LGPD: score interno declarado + opt-in cliente

**Concorrência:** Auto Manager ✅, Lokoz ✅, FutureSysSoft ✅. Esperado.

---

### US-AP-011 · WhatsApp consulta peça (Jana bot consulta catálogo) — **P1**

> **Área:** Comercial
> **Reusa:** [Modules/Whatsapp](../Whatsapp/) + [Modules/Jana](../../../Modules/Jana)

**Implementado em:** _pendente_ — módulo wish aguarda-sinal-qualificado; Jana tool `autopecas.consultar_peca` a construir

**Como** cliente oficina parceira
**Quero** mandar foto da peça quebrada + "Vargas Civic 2015" pelo WhatsApp e receber em 30s lista compatível com preço e disponibilidade
**Para** atender cliente fora horário balcão (Vargas fecha 18h) + fidelizar oficinas (atendimento exclusivo)

**DoD:**
- [ ] Webhook WhatsApp Cloud API (token Meta — Modules/Whatsapp)
- [ ] Jana tool `autopecas.consultar_peca` (input: marca, modelo, ano, descricao_texto, foto_opcional)
- [ ] Output: lista SKUs compatíveis + preço + estoque + link reserva
- [ ] Reserva temporária 2h se cliente clica "reservar" (estoque diminui mas não vende)
- [ ] Audit log conversas (LGPD: opt-in obrigatório)

**Concorrência:** **NENHUM concorrente entrega Jana-style.** **Diferencial #1 oimpresso.**

---

### US-AP-012 · App balconista mobile (PWA — busca peça + reserva) — **P1**

> **Área:** UX
> **Reusa:** Inertia/React responsive + PWA manifest

**Implementado em:** _pendente_ — módulo wish aguarda-sinal-qualificado, nada construído

**Como** balconista atendendo cliente no estoque (não no balcão)
**Quero** abrir lista de peças no celular, fotografar SKU, ver estoque, reservar pra venda
**Para** atender cliente que está no estoque vendo peça presencialmente sem voltar ao computador

**DoD:**
- [ ] PWA manifest + service worker offline-first
- [ ] Page `/autopecas/mobile` mobile-first
- [ ] Câmera nativa pra ler código de barras
- [ ] Reserva temporária (mesmo padrão US-AP-011)
- [ ] Push notification (Centrifugo CT 100) ao receber pedido WhatsApp
- [ ] Funciona em 4G + offline graceful (queue sync)

**Concorrência:** Auto Manager 🟡, Lokoz 🟡, Linx Microvix ✅. Esperado mid-tier.

---

### US-AP-013 · Diagnóstico assistido Jana IA ("qual peça pra Civic 2015 freio dianteiro?") — **P1**

> **Área:** IA
> **Reusa:** [Modules/Jana](../../../Modules/Jana) tools + ContextSnapshotService

**Implementado em:** _pendente_ — módulo wish aguarda-sinal-qualificado; Jana tool `autopecas.sugerir_peca` a construir

**Como** balconista iniciante
**Quero** descrever (marca, modelo, ano, sintoma) e receber SKUs ranqueados + alternativas + tempário sugerido SE peça exigir aplicação (link Modules/OficinaAuto futuro)
**Para** acelerar atendimento + reduzir dependência de balconista sênior

**DoD:**
- [ ] Jana tool `autopecas.sugerir_peca` (input: marca, modelo, ano, sintoma_texto)
- [ ] Output: SKUs[] (descricao, score_compatibilidade, preco, estoque, qualidade, alternativas[])
- [ ] PolicyEngine: `REQUIRE_HUMAN_REVIEW` (balconista confirma antes de vender)
- [ ] Aprendizado: cada venda fechada vira fact `autopecas.peca_vendida_para` em `MemoriaContrato` (ADR 0035)
- [ ] Disclaimer: "sugestão IA — confirmar compatibilidade aplicação"
- [ ] LGPD: sem PII real cliente em prompt

**Concorrência:** **NENHUM concorrente entrega.** **Diferencial #2.**

---

### US-AP-014 · Cupom fiscal SAT (SP) — **P2**

> **Área:** Fiscal
> **Reusa:** [Modules/NfeBrasil](../NfeBrasil/SPEC.md) (SAT a adicionar)

**Implementado em:** _pendente_ — módulo wish aguarda-sinal-qualificado; SAT SP nem no NfeBrasil existe ainda (P2, condicional a Vargas ser SP)

**Como** dono autopeças SP
**Quero** emitir cupom fiscal via SAT (alternativa NFC-e em SP até transição completa)
**Para** atender exigência SEFAZ-SP + clientes que preferem cupom

**Status:** **proposta P2** — depende vertical Vargas se for SP. Validar localização Vargas via banco Firebird antes priorizar.

---

### US-AP-015 · E-commerce mini-loja (catálogo digital + pedido online) — **P2**

> **Área:** Comercial
> **Reusa:** [Modules/Cms](../Cms/) landing + Modules/RecurringBilling

**Implementado em:** _pendente_ — módulo wish aguarda-sinal-qualificado, nada construído (P2, fase 3)

**Como** dono autopeças
**Quero** subdomínio `loja.vargas-acessorios.com.br` com catálogo público + pedido online (não auto-checkout — solicita orçamento)
**Para** captar leads digital + diferenciar oficinas que pesquisam Mercado Livre Auto

**DoD:**
- [ ] Subdomínio configurável business (já suportado infra)
- [ ] Page `cms_pages` com catálogo facetado público
- [ ] Carrinho → pedido orçamento (não check-out direto — peça depende aplicação)
- [ ] Email/WhatsApp ao balconista interno + Page `Pedidos/Online.tsx` priorizar
- [ ] LGPD: opt-in cookies/marketing

**Concorrência:** Linx Microvix ✅, Auto Manager 🟡, Lokoz ❌. Diferencial mid-tier.

---

## 4. Concorrentes verticais (research pendente Q4/26)

> **Status research:** SPEC criada antes de research formal autopeças BR. Lista preliminar baseada em conhecimento setor — confirmar e aprofundar via [research/2026-Q4-prospeccao-autopecas/](../../research/2026-Q4-prospeccao-autopecas/) quando ativar.

### 4.1 Auto Manager (a confirmar)
- Pricing público desconhecido
- Forte: balcão + financeiro + integração NF
- Calcanhar provável: stack legacy desktop ou web tradicional sem IA

### 4.2 Lokoz
- Pricing R$ [redacted Tier 0]-499/m (estimativa)
- Forte: cloud-native, multi-segmento auto (oficina + autopeças + locadora)
- Calcanhar: multi-segmento sem profundidade vertical real

### 4.3 FutureSysSoft
- Forte: tradicional autopeças, base instalada
- Calcanhar: stack envelhecida

### 4.4 Mecanizou
- Forte: marca consolidada
- Calcanhar: foco oficina, autopeças secundário

### 4.5 Linx Microvix
- Forte: enterprise B2B grande, integração ERP corporativo
- Calcanhar: caro, complexo, overkill SMB Vargas-size

### 4.6 AutoForce
- Forte: digital marketing automotive
- Calcanhar: foco lead gen, não ERP completo

### 4.7 WR Sistemas Delphi (legacy Vargas atual)
- Forte: 26 anos relação Wagner-Vargas, OfficeImpresso completo
- Calcanhar: build 1468 desatualizado, stack Delphi, sem IA, NFC-e semi-manual, sem multi-tenant moderno

> **TODO research Q4/26:** confirmar pricing exato + features + RA reclamações de cada concorrente. Criar `memory/research/2026-Q4-prospeccao-autopecas/02-concorrentes-erp-autopecas-br.md` no padrão Modules/OficinaAuto research.

## 5. Diferenciais oimpresso (vs concorrentes preliminares)

| Diferencial | oimpresso | Auto Manager | Lokoz | FutureSysSoft | Linx Microvix | WR Delphi |
|---|---|---|---|---|---|---|
| **Jana IA conversacional + memória** ([ADR 0035](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md)) | ✅ planejado | ❌ | ❌ | ❌ | ❌ | ❌ |
| **NFC-e + NFe-de-boleto auto** (US-RB-044 ✅) | ✅ | 🟡 | 🟡 | 🟡 | ✅ | ❌ |
| **Multi-tenant Tier 0** (ADR 0093) | ✅ | 🟡 | 🟡 | ❌ | ✅ | ❌ |
| **Stack moderna** (Laravel 13.6 + Inertia v3 + React 19) | ✅ | ❌ | 🟡 | ❌ | 🟡 | ❌ |
| **WhatsApp consulta peça por foto** (US-AP-011) | ✅ planejado | ❌ | ❌ | ❌ | 🟡 | ❌ |
| **Diagnóstico assistido IA** (US-AP-013) | ✅ planejado | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Constituição v2 ADRs públicas** (ADR 0094) | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Migration Factory** (ADR 0119 — Strangler Fig + parallel run 30d) | ✅ | n/a | n/a | n/a | n/a | n/a |

**Wedge primário (3 frases):**
> *"O ERP de autopeças que entrega NFC-e em 500ms no balcão, responde 'qual peça pra Civic 2015 freio dianteiro' direto pelo WhatsApp da oficina, e migra seu Delphi WR Sistemas em 30d sem perder histórico — preservando 26 anos de relação."*

## 6. Arquitetura técnica

### 6.1 Estrutura de diretórios (a criar SE/QUANDO ativado)

```
Autopecas/                   ← a criar (planejado — não existe; status: feature-wish até gatilho)
├── Config/
│   ├── config.php
│   └── permissions.php       ← Spatie: autopecas.produto.*, autopecas.balcao.*, autopecas.devolucao.*, autopecas.garantia.*, autopecas.cotacao.*
├── Database/
│   ├── Migrations/
│   │   ├── create_autopecas_produtos_extends_table.php (extends UltimatePOS products)
│   │   ├── create_autopecas_aplicacoes_table.php
│   │   ├── create_autopecas_montadoras_table.php
│   │   ├── create_autopecas_tabelas_preco_table.php
│   │   ├── create_autopecas_devolucoes_table.php
│   │   ├── create_autopecas_garantias_table.php
│   │   ├── create_autopecas_cotacoes_table.php
│   │   ├── create_autopecas_cotacao_respostas_table.php
│   │   └── create_autopecas_crediario_clientes_table.php
│   └── Seeders/
│       ├── MontadorasSeeder.php (19 montadoras BR)
│       └── PecasOemBaseSeeder.php (Bosch/Nakata/Fras-le open data — shared com Modules/OficinaAuto)
├── Entities/                ← Eloquent Models (BusinessIdScope global)
│   ├── ProdutoAutopecas.php (extends Product UltimatePOS)
│   ├── Aplicacao.php
│   ├── Montadora.php
│   ├── TabelaPreco.php
│   ├── Devolucao.php
│   ├── Garantia.php
│   ├── Cotacao.php
│   ├── CotacaoResposta.php
│   └── CrediarioCliente.php
├── Http/
│   ├── Controllers/
│   │   ├── DataController.php       ← UltimatePOS hooks
│   │   ├── InstallController.php    ← 3 rotas (status, install, uninstall)
│   │   ├── ProdutoCatalogoController.php
│   │   ├── BalcaoController.php
│   │   ├── TabelaPrecoController.php
│   │   ├── DevolucaoController.php
│   │   ├── GarantiaController.php
│   │   ├── CotacaoController.php
│   │   ├── CrediarioController.php
│   │   ├── EstoqueAlertaController.php
│   │   └── CotacaoPublicaController.php  ← rota pública /cotacao/{token}
│   └── Requests/
├── Jobs/
│   ├── CheckEstoqueMinimoJob.php (cron daily)
│   ├── GarantiaVenceLembreteJob.php (cron daily)
│   └── ConsultaWhatsappPecaJob.php
├── Listeners/
│   ├── BoletoPagoEmiteNfe.php (split NFC-e peça)
│   └── VendaConcluidaRegistraGarantia.php
├── Services/
│   ├── BalcaoVendaService.php
│   ├── CalculadoraPrecoService.php
│   ├── DevolucaoService.php
│   ├── GarantiaService.php
│   └── ConsultaPecaJanaService.php (wrapper Jana tool)
├── Resources/
│   ├── views/  (mínimo Blade — 99% Inertia)
│   └── lang/
├── Routes/
│   ├── web.php
│   └── api.php
├── Tests/
│   ├── Feature/
│   └── Unit/
├── module.json
└── composer.json
```

Frontend Inertia em `resources/js/Pages/Autopecas/` seguindo Cockpit Pattern V2 (ADR 0110) com `.charter.md` ao lado de cada Page (S4+).

### 6.2 Reusa Modules existentes

- **Modules/Vestuario** — padrão venda balcão + variação SKU multi-atributo (~30-40% reuso UI/Controller patterns)
- **Modules/NfeBrasil** US-NFE-002 (NFC-e ✅) + futuro CT-e quando frota
- **Modules/RecurringBilling** US-RB-044 (boleto pago→NFe ✅)
- **Modules/Financeiro** AR/AP/extrato/crediário base
- **Modules/Jana** ContextSnapshotService + tools (US-AP-011, US-AP-013)
- **Modules/Whatsapp** webhook Meta (US-AP-011)

### 6.3 Reuso futuro Modules/OficinaAuto (catálogo peças shared)

Quando OficinaAuto ativar (sinal qualificado pendente), extrair:
- `pecas` + `peca_aplicacoes` (chassis_ranges, ano, modelo, motor, montadora) → pacote shared `OimpressoCatalogPecas`
- Ambos módulos consomem o pacote sem duplicação

Por enquanto, Autopecas (planejado — não existe) implementa standalone com tabelas `autopecas_produtos`, `autopecas_aplicacoes` — refactor pra shared quando OficinaAuto sinalizar piloto.

### 6.4 Schema essencial (resumo)

```sql
-- autopecas_produtos (extends products UltimatePOS via 1-1)
id, business_id, product_id FK, codigo_oem, codigo_fabricante,
marca_peca, categoria_peca, qualidade enum, observacao_tecnica

-- autopecas_aplicacoes (1 produto → N aplicações)
id, business_id, produto_id FK, montadora_id FK, modelo, ano_min, ano_max,
motor, chassis_inicial, chassis_final, observacao

-- autopecas_montadoras (seed shared catalog)
id, nome, codigo, ativo

-- autopecas_tabelas_preco
id, business_id, categoria_peca, montadora_id, qualidade,
markup_pct, valor_min, valor_max, ativo

-- autopecas_devolucoes
id, business_id, transaction_id_origem, transaction_id_devolucao FK,
motivo enum, valor_devolvido, retorno_estoque enum, anexo_path, observacao,
status enum, created_by_user_id

-- autopecas_garantias
id, business_id, transaction_id, produto_id, tipo enum [loja|fabricante],
prazo_dias, vence_em, status, fabricante_nome, fabricante_protocolo_rma,
custo_loja_substituicao_pago

-- autopecas_cotacoes
id, business_id, descricao, qtd, deadline_resposta, status, criado_por_user_id

-- autopecas_cotacao_respostas
id, cotacao_id, fornecedor_contact_id, preco_unit, prazo_entrega_dias,
validade_resposta, observacao, ranking_calculado

-- autopecas_crediario_clientes
id, business_id, contact_id, limite_total, limite_disponivel,
score_interno, status enum [ativo|suspenso], opt_in_lgpd_at
```

Todos com `business_id` indexado + FK + global scope (Tier 0 IRREVOGÁVEL — ADR 0093).

## 7. Roadmap CONDICIONAL (só ativa se Vargas assinar)

> ⚠️ **NÃO IMPLEMENTAR.** Roadmap abaixo é antecipatório — só vira backlog ativo quando gatilho §9 for satisfeito (Vargas contrato pioneer assinado). Sem cliente piloto pagante, **viola ADR 0105**.

### Fase 0 — Scaffold (1 semana IA-pair)
Module skeleton + DataController + InstallController + 3 migrations core (autopecas_produtos, autopecas_aplicacoes, autopecas_montadoras + seed) + Charter inicial. **0 features visíveis.**

### Fase 1 — MVP-6 capacidades core (3-4 semanas IA-pair, fator 10x ADR 0106)
- US-AP-001 (catálogo + aplicação)
- US-AP-002 (venda balcão rápida)
- US-AP-003 (tabela preço)
- US-AP-004 (estoque mínimo)
- US-AP-005 (devolução)
- US-AP-006 (garantia loja vs fabricante)
- US-AP-007 (NFC-e ágil + NFe-boleto auto — adapter sobre US-RB-044)

**Esforço estimado IA-pair (ADR 0106):** ~80h codáveis × 2x margem = ~10-12 dias úteis Felipe (vs ~50 dias humano sem IA-pair).

### Fase 2 — Diferenciais (4-6 semanas + Migration Factory Vargas)
- US-AP-008 (cotação RFQ)
- US-AP-009 (multi-depósito)
- US-AP-010 (crediário interno)
- US-AP-011 (WhatsApp consulta peça — diferencial #1)
- US-AP-012 (PWA balconista mobile)
- US-AP-013 (Jana diagnóstico — diferencial #2)
- **Migration Factory Vargas:** banco Firebird → oimpresso, dry-run + parallel run 30d (humano-limitado: ~30-60d wallclock cliente concreto)

### Fase 3 — Escala (6+ meses pós-Vargas)
- US-AP-014 (SAT SP), US-AP-015 (e-commerce)
- 2º cliente autopeças (não-Vargas) via Migration Factory
- Refactor catálogo peças shared com Modules/OficinaAuto se OficinaAuto ativar

**Total MVP→produção piloto Vargas: ~10-14 semanas IA-pair + 30-60d Migration Factory wallclock = ~4-5 meses corridos.** Sem IA-pair seria ~12 semanas Felipe + 60d = ~6 meses.

## 8. Pricing tier sugerido (calibração pendente Q4/26)

> Pricing **antecipatório** — research formal pendente. Calibrar contra Auto Manager / Lokoz / Linx Microvix antes outreach Vargas Q4/26.

| Tier | Preço/m | Inclui | Posição vs mercado (preliminar) |
|---|---|---|---|
| **Auto Starter** | **R$ [redacted Tier 0]/m** | 1 loja, 1-2 balconistas, 100 NFC-e/m, balcão básico, NFe-boleto auto, Jana 200 perguntas/m | A confirmar contra entry tiers |
| **Auto Pro** | **R$ [redacted Tier 0]/m** | 1 loja, 3-6 balconistas, NFC-e ilim, 5 users, crediário, devolução+garantia, Jana 1000 p/m, WhatsApp Cloud API básico | A confirmar contra mid tiers |
| **Auto Enterprise** | **R$ [redacted Tier 0]/m** | Multi-loja (até 5), users ilim, todas features, Jana ilim+memória dedicada, SLA telefônico, success dedicado, Migration Factory | **Vargas pioneer grandfathered 24m + 50% off 6m → R$ [redacted Tier 0]/m × 6m → R$ [redacted Tier 0]/m × 18m** |
| **Setup** | **R$ [redacted Tier 0] default** (pioneer) / **R$ [redacted Tier 0]-5.000** (Migration Factory regular) | — | Pioneer setup R$ [redacted Tier 0] só primeiros 2 clientes |
| **Trial** | **14 dias** sem cartão | — | Padrão |
| **Anual** | **12 paga 10** | — | Padrão |

**Calibração necessária:** research Q4/26 confirma se R$ [redacted Tier 0] starter / R$ [redacted Tier 0] pro / R$ [redacted Tier 0] enterprise estão alinhados Auto Manager / Lokoz / Linx Microvix mid-market. Provável ajuste pra R$ [redacted Tier 0]/399/999 se concorrentes mais agressivos.

## 9. Pré-requisitos pra ATIVAR (mudar status pra `em_construcao`)

> **Esta seção é a fronteira ADR 0105.** Sem TODOS os pré-requisitos abaixo, módulo permanece `feature-wish`. Não scaffoldear, não criar tasks ativas, não codar.

### 9.1 Sinal qualificado de mercado (gatilho cliente — ADR 0105)

**Pelo menos 1 dos 2 cenários:**

1. **Vargas assina contrato pioneer** (Cenário A — preferido):
   - Contrato Enterprise R$ [redacted Tier 0]/m grandfathered 24m + 50% off 6m + setup R$ [redacted Tier 0] escrito assinado
   - Compromisso reportar bugs/features semanal por 6 meses
   - Autoriza migração full Migration Factory Strangler Fig + parallel run 30d
   - Geografia a confirmar via banco Firebird (suporte presencial possível se SP/SC)
   - Snapshot financeiro pré-migração executado (skill `officeimpresso-financial-snapshot`)

2. **2º cliente autopeças saudável** (Cenário B — backup se Vargas recusar):
   - 1 dos outros 6 saudáveis OfficeImpresso (a identificar — possivelmente Mhundo ou Fixar) confirma vertical autopeças
   - Mesmo pacote pioneer Enterprise
   - Geografia BR qualquer

### 9.2 6 features mínimas validadas (paridade competitiva)

Antes de cobrar 1º cliente, **TODAS** essas 6 capacidades core funcionam end-to-end em homologação:

1. **US-AP-001** — catálogo SKU + tabela aplicação por veículo
2. **US-AP-002** — venda balcão p95<1500ms
3. **US-AP-003** — tabela preço por categoria/montadora
4. **US-AP-004** — controle estoque mínimo + alertas
5. **US-AP-005** — devolução com motivo
6. **US-AP-006** — garantia loja vs fabricante
7. **US-AP-007** — NFC-e ágil + NFe-boleto auto (já entregue núcleo, só adapter)

**Não inclui** US-AP-008 (cotação), US-AP-010 (crediário), US-AP-011 (WhatsApp), US-AP-013 (Jana diag) — fase 2.

### 9.3 Capacidade time

- **WIP atual:** 5 pessoas com Modules/Vestuario live, Modules/ComunicacaoVisual em construção (Sprint 1 Q3/26), MWART Financeiro batch
- **Recomendação:** ativar Autopecas **só após** Modules/ComunicacaoVisual ter 1ª piloto comvisual estabilizado (Q1/27 estimado). Antes, oportunidade-custo é negativa
- **Gate Wagner:** Wagner aprova ativação baseado em (a) Vargas contrato + (b) ComunicacaoVisual piloto verde + (c) Sprint capacity

### 9.4 ADR de ativação

Quando os pré-requisitos forem satisfeitos, **abrir ADR canon** "Autopecas-ativacao-vertical" com:
- evidência sinal qualificado (contrato assinado Vargas ou 2º cliente)
- evidência 6 features mínimas verde (Pest + smoke real)
- snapshot financeiro Vargas confirmado
- aprovação Wagner [W] + revisão Felipe [F]
- mudança SPEC `status: feature-wish` → `status: em_construcao`
- criação batch tasks no MCP via `tasks-create` (não markdown — ADR 0070)

## 10. Métricas de sucesso (12m após ativação, NÃO antes)

| Métrica | Baseline (M0 ativação) | M6 | M12 | Crítica |
|---|---|---|---|---|
| Clientes pagantes Autopecas (planejado — não existe) | 1 (Vargas) | 2-3 | **5-10** | <3 = re-avaliar tese |
| ARR módulo (R$/ano) | R$ [redacted Tier 0]k (Vargas Enterprise) | R$ [redacted Tier 0]-54k | **R$ [redacted Tier 0]-180k** | <R$ [redacted Tier 0]k = pivotar |
| US entregues (de 15 totais) | 7 (mínimo P0) | 10 (P0+P1) | **13** | <10 = stack mal calibrado |
| Cases públicos clicáveis | 0 | 1 (Vargas) | **2** | (transparência radical) |
| Bug crítico produção | n/a | <1/mês | <1/trimestre | (Pest gate ADR 0094) |
| Churn módulo | n/a | <5%/m | <8%/ano | (review trigger ADR 0121) |
| NFC-e ágil p95 | <1500ms | <1000ms | <800ms | (ux competitivo balcão) |

**Convergência [ADR 0022](../../decisions/0022-meta-5mi-ano-financeira.md):** Autopecas (planejado — não existe) contribui R$ [redacted Tier 0]-180k ARR de R$ [redacted Tier 0]M total (1.8-3.6% no M12 pós-ativação). Multi-vertical é tese — autopeças é diversificação Vargas-driven, não substituição.

## 11. Anti-padrões — o que NÃO fazer

1. ❌ **Construir SEM Vargas assinatura (ou 2º cliente qualificado)** — viola ADR 0105 explicitamente. Status `feature-wish` é proteção
2. ❌ **Forçar Vargas a migrar** — princípio ADR 0105 "guiar sem mandar". Plano B mantém Vargas no OfficeImpresso se recusar
3. ❌ **Copiar feature-set Auto Manager e cobrar 30% menos** — sem diferencial Jana + NFe-boleto + multi-tenant Tier 0, perde por base instalada
4. ❌ **Hard-code vocabulário autopeças no núcleo UltimatePOS** — quebra ADR 0121 §P1. Tudo "aplicação/montadora/qualidade-peça" vai em `Autopecas` (planejado — não existe)
5. ❌ **Esquecer `business_id` global scope em qualquer Model nova** — Tier 0 IRREVOGÁVEL (ADR 0093)
6. ❌ **Implementar US-AP-011 (WhatsApp consulta) sem opt-in LGPD explícito** — mensagem comercial sem opt-in = TIM-style risco multa Anatel + LGPD Art. 7º
7. ❌ **PII real (CPF/CNPJ cliente, placa) em PR/commit/log** — skill `commit-discipline` Tier A. `[REDACTED]` ou `PiiRedactor`
8. ❌ **Fundir Autopecas (planejado — não existe) com Modules/OficinaAuto** — persona/workflow/concorrência distintos. ADR 0125 §Alternativa B rejeitada
9. ❌ **Cobrar setup R$ [redacted Tier 0] de Vargas** — pioneer R$ [redacted Tier 0] Setup regular só pra clientes 3+ pós-piloto
10. ❌ **Smoke test com `business_id=1`** (Wagner WR2) — ADR 0101 manda biz piloto Vargas
11. ❌ **Migrar Vargas sem dry-run + Pattern 07** — banco Firebird Vargas pode ter quirks não-mapeados (triggers, procedures, customizações)
12. ❌ **Embutir API DETRAN/CRLV no Autopecas** — não é fluxo balcão (autopeças não consulta CRLV). Isso é Modules/OficinaAuto US-AUTO-002
13. ❌ **Ativar Autopecas antes de Modules/ComunicacaoVisual ter 1ª piloto verde** — viola WIP (ADR 0094 §5 SoC brutal)
14. ❌ **NFe transferência multi-depósito sem CFOP correto** (5409 same UF / 6409 outra UF) — multa SEFAZ por código errado

## 12. Decisões pendentes (resolver SE/QUANDO ativar)

- [ ] Pricing tier final — research Q4/26 confirma R$ [redacted Tier 0]/599/1499 ou ajusta R$ [redacted Tier 0]/399/999
- [ ] Catálogo peças OEM seed: parceria Bosch/Nakata/Fras-le (cobrável) vs scraping fair-use vs base própria
- [ ] Reuso shared infra com Modules/OficinaAuto: extrair `pecas` + `aplicacoes` agora ou só quando OficinaAuto ativar
- [ ] SAT SP (US-AP-014): só ativa se Vargas for SP. Confirmar localização via banco Firebird antes priorizar
- [ ] E-commerce mini-loja (US-AP-015): só fase 3, validar se Vargas usa Mercado Livre Auto hoje
- [ ] WhatsApp Cloud API: usar token Meta atual oimpresso ou Vargas tem token próprio?

## 13. Referências

- ADR 0125 — Autopecas (planejado — não existe) como feature-wish (mãe deste módulo)
- ADR 0121 — Modular especializado por vertical
- ADR 0094 — Constituição v2 (princípios duros)
- ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0105 — Cliente como sinal qualificado (gatilho de ativação)
- ADR 0106 — Recalibração velocidade fator 10x IA-pair
- ADR 0089 — Capterra-driven evolution
- ADR 0119 — Migration Factory (aplicada a Vargas)
- [SPEC Modules/OficinaAuto](../OficinaAuto/SPEC.md) — vertical próximo, template imitado, catálogo peças shared infra futura
- [SPEC Modules/Vestuario](../Vestuario/SPEC.md) — modelo SPEC live em produção (~30-40% reuso UI/Controller)
- [SPEC Modules/ComunicacaoVisual](../ComunicacaoVisual/SPEC.md) — 3º vertical, mesma arquitetura
- [SPEC Modules/NfeBrasil](../NfeBrasil/SPEC.md) — reuso US-NFE-002 NFC-e
- [SPEC Modules/RecurringBilling](../RecurringBilling/SPEC.md) — reuso US-RB-044 boleto-pago→NFe
- [Modules/Jana](../../../Modules/Jana) — reuso IA US-AP-011, US-AP-013
- [PLANO-MIGRACAO-VARGAS.md](PLANO-MIGRACAO-VARGAS.md) — plano operacional Vargas (irmão deste SPEC)
- [Autopecas.charter.md](Autopecas.charter.md) — charter v1 antecipatório
- [RUNBOOK criar módulo](../Infra/RUNBOOK-criar-modulo.md)
- Sindipeças — ABIPEÇAS Anuário 2024 (research pendente Q4/26)

---

**Última atualização:** 2026-05-10 — SPEC criada **antecipatória** sem cliente piloto assinado. Status `feature-wish` lifecycle `aguarda-sinal-qualificado`. Não codar até gatilho §9 satisfeito (Vargas contrato pioneer ou 2º cliente qualificado). Revisar trimestralmente — se 12 meses sem sinal, considerar arquivar como `historical` (ADR 0095 lifecycle).
