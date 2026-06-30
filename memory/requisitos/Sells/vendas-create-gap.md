# Gap — Sells/Create (tela viva) vs vendas-create-page.jsx (mockup Cowork)

> Fase 1 da skill `aplicar-prototipo` (READ-ONLY). Mapeamento **semântico**, não diff textual.
> Tela viva: `resources/js/Pages/Sells/Create.tsx` (2004 linhas — em produção, ROTA LIVRE biz=4).
> Mockup: `_cowork-handoff-staging/oimpresso-erp-conunica-o-visual/project/vendas-create-page.jsx` (439 linhas).
> Bundle: **Comunicação Visual** (vertical em construção — CNAE 1813-0/01).
>
> ⚠️ **Aviso de leitura:** o mockup é MUITO menor que o vivo (439 vs 2004) e simplifica de propósito.
> A maioria das ausências no mockup é **stale** (vivo já tem mais), não gap. Os gaps reais são
> ideias de **fluxo vertical** (m²/acabamento ComVis, MO/peças+aprovação Oficina, fiscal inferido)
> que o vivo, focado em vestuário, não tem.

---

## Contexto-chave que muda a leitura

- **Vivo = vertical vestuário/genérico** (ROTA LIVRE). Tem gate `hasOficinaAuto` pra veículo, mas
  o resto é venda de produto simples (qty × preço).
- **Mockup = bi-vertical ComVis + Oficina** com **toggle de vertical no header** que troca o catálogo
  inteiro (produtos m² ↔ MO/peças). Esse é o eixo conceitual novo do mockup.
- Mockup trabalha em **centavos** (`unitCents`, `parseCents`); vivo trabalha em float pt-BR via
  `NumericInputPtBR`. **Isso é detalhe de protótipo, NÃO adotar** — o vivo já tem a defesa anti-`num_uf`
  (incidente 2026-06-05, arredonda 2 casas no submit). Centavos no mockup é só conveniência de demo.

---

## Parte 1 — Header

| | |
|---|---|
| **Mockup propõe** | (a) Botão "voltar pra Vendas" com `kbd esc` à esquerda do título. (b) **Toggle de vertical** (Comunicação visual ↔ Oficina) no header — troca catálogo. (c) Resumo "Itens / Total" inline no header. (d) Pills de seção (igual ao vivo). |
| **Vivo já tem** | Header sticky + KPI cards GIGANTES (Itens / Total / Pago / **Status pgto** com semântica falta/troco/exato) + pills com scroll-spy + IntersectionObserver. **Vivo é mais rico** que o resumo inline do mockup. |
| **Gap real** | Affordance "voltar com esc" explícito (vivo tem Cancelar no rodapé + Esc=blur, não um botão-voltar no topo). **P** · risco baixo. Toggle de vertical = ver Parte "Conceito transversal". |
| **Stale** | KPI 4-cards, scroll-spy, draft-recover — vivo tem, mockup não. |

## Parte 2 — Seleção de cliente

| | |
|---|---|
| **Mockup propõe** | Busca inline (≥2 char) → popover de matches + **"Cadastrar '\<query\>'" inline** (mini-form nome/telefone/CPF-CNPJ direto na tela, sem sair). Chip do cliente escolhido com CNPJ/contato + "Trocar". |
| **Vivo já tem** | `CustomerSearchAutocomplete` (componente dedicado) + cadastro de contato em **aba separada** via `postMessage` (`contact_created`). Auto-aplica grupo de preço, prazo, endereço de entrega ao selecionar (handler `handleCustomerSelect`, bug R8). |
| **Gap real** | **Cadastro rápido de cliente _inline_** (sem abrir outra aba) — o mockup resolve num mini-form na própria tela. Affordance melhor pra balcão. **M** · risco baixo (UI only; o backend de criar contato já existe). |
| **Stale** | Auto-pull de price-group/prazo/endereço ao trocar cliente — vivo tem, mockup não. |

## Parte 3 — Busca / adição de produtos

| | |
|---|---|
| **Mockup propõe** | (a) Busca com **navegação por teclado** (↑/↓ destaca, Enter adiciona). (b) **Bip de código de barras / EAN** — digitou ≥8 dígitos casa EAN exato e adiciona. (c) Catálogo muda por vertical. (d) Foco automático no campo de busca ao abrir. |
| **Vivo já tem** | `ProductSearchAutocomplete` dedicado + `handleAddProduct` (mesmo produto+variação incrementa qty; variação diferente = linha nova). Atalho `/` foca a busca. |
| **Gap real** | (1) **Navegação ↑/↓/Enter** no popover de produto — _pendente_ confirmar se `ProductSearchAutocomplete` já tem (não inspecionado aqui; provável que sim). (2) **Leitura de código de barras/EAN** — não evidente no vivo; é affordance forte pra balcão/PDV. **M** · risco baixo. |
| **Stale** | Dedupe de linha por produto+variação, toast de quantidade — vivo tem, mockup não. |

## Parte 4 — Grade de itens

| | |
|---|---|
| **Mockup propõe** | (a) **Campos m² por linha** (largura × altura → m² → R$/m²) pra ComVis. (b) Linha mostra **NCM / CFOP / cód.serviço** como metadados. (c) **Agrupamento MO / Peças** na Oficina. (d) Checkbox **"gera OS"** por linha (ComVis) e **toggle "aprovado/pendente"** por linha (Oficina — cobra só aprovado). |
| **Vivo já tem** | Tabela editável: Produto (+variação+SKU), Qtd, Preço unit., Desconto (R$/% por linha), Subtotal, remover. IMEI/serial por linha. Erro por item (estoque) contornando a linha. Footer com subtotal. |
| **Gap real** | (1) **m² por linha** (largura×altura) — central pra ComVis (banner/lona/fachada). ⚠️ **toca cálculo de valor — regra mestre exige dupla confirmação**: muda como `lineSubtotal` é computado (área × preço × qty). Só DESCREVER, não implementar lógica aqui. **G** · risco ALTO (Tier 0 valor). (2) Metadados fiscais por linha (NCM/CFOP) — **M** · risco baixo (display). (3) Agrupamento MO/Peças + toggle aprovado/pendente (Oficina) — fluxo de orçamento, **G** · risco médio. |
| **Stale** | Desconto R$/% por linha, IMEI, erro-por-item visual — vivo tem, mockup não. |

## Parte 5 — Totais / descontos / impostos ⚠️ (zona Tier 0)

| | |
|---|---|
| **Mockup propõe** | Desconto **único** (campo no rodapé) + frete. Base de cálculo na Oficina = **só itens aprovados** (`aprovadoCents`). Sem imposto explícito (delega ao bloco fiscal). |
| **Vivo já tem** | Desconto do pedido R$/% + **validação de desconto máximo** (`maxDiscount` por permissão) + despesas adicionais (4 linhas) + frete + imposto do pedido (`tax_rate_id`) + **card Resumo consolidado** (subtotal → desconto → frete → despesas → total). Anti-incidente `num_uf` no submit. |
| **Gap real** | Conceito "**cobrar só o aprovado**" (Oficina) — muda a base do total. ⚠️ **toca cálculo de valor — regra mestre exige dupla confirmação**. Vivo é mais completo em todo o resto; **não há gap de desconto/imposto a adotar** do mockup. **Apenas descrever**, não mexer em cálculo. |
| **Stale** | Desconto máx, despesas adicionais, imposto do pedido, card Resumo — vivo tem, mockup não. |

## Parte 6 — Pagamento

| | |
|---|---|
| **Mockup propõe** | (a) Pagamento como **cards clicáveis** (PIX/Boleto/Cartão/Faturado) com "clearing" (prazo de compensação). (b) **Parcelas** (select Nx de R$ Y) quando cartão/boleto/faturado. (c) Aviso "ao salvar, gera cobrança PIX/boleto no módulo Cobrança (idempotency sale:{id})". |
| **Vivo já tem** | `PaymentRow` (split de pagamento, múltiplas linhas, método+conta+data+nota) + indicador de saldo (falta/troco/exato) + venda a prazo permitida (payment_status=due). |
| **Gap real** | (1) **Cards de método** com "clearing"/prazo — UX mais visual que o select do `PaymentRow`. **M** · risco baixo. (2) **Parcelamento explícito** (Nx) — _pendente_ confirmar se `PaymentRow` cobre; não evidente. **M** · risco médio. (3) Aviso "gera cobrança/boleto ao salvar" — depende do módulo Cobrança/RecurringBilling estar ligado; **M** · risco médio. |
| **Stale** | Split de pagamento multi-linha, saldo semântico — vivo tem; mockup tem só 1 método. |

## Parte 7 — Fiscal (bloco NOVO do mockup — não existe no vivo)

| | |
|---|---|
| **Mockup propõe** | **Inferência automática de documentos fiscais**: a composição da venda (produto/serviço, CNPJ?, entrega fora do município?) liga/desliga **NFC-e / NF-e / NFS-e / MDF-e** com justificativa ("exige produto", "cliente sem CNPJ", "só p/ entrega fora do município"). Vendedor pode ajustar manualmente (override). |
| **Vivo já tem** | **Nada equivalente** na tela de criação. NFe vive no `Modules/NfeBrasil` (emissão pós-venda, fora desta tela). |
| **Gap real** | **Maior gap conceitual do mockup.** Painel "que documento vou emitir" inferido pela composição é uma affordance forte (reduz erro fiscal). ⚠️ NÃO toca valor, mas integra com NfeBrasil/FSM — **G** · risco médio-alto (integração multi-módulo, não é só UI). Adoção exige decisão de produto (ADR), não é cópia de layout. |
| **Stale** | — (vivo não tem, é gap puro). |

## Parte 8 — Frete / entrega

| | |
|---|---|
| **Mockup propõe** | (a) **Radio retirada ↔ entrega**. (b) Ao escolher entrega, **lista os endereços do cliente** (deriva de `client.addresses`) + opção "outro endereço" (form CEP/logradouro/…). (c) Detecta **município diferente** → habilita MDF-e. (d) Transportadora + valor de frete. |
| **Vivo já tem** | Bloco frete dentro de "Mais opções" (`<details>` colapsado): detalhes, endereço (texto livre), custo, status remessa, entregar-a. Auto-preenche endereço do cliente ao selecionar (1 endereço, texto). |
| **Gap real** | (1) **Picker de múltiplos endereços do cliente** (vivo só tem 1 campo de texto livre) — **M** · risco baixo. (2) **Radio retirada/entrega** como decisão de topo (vivo deixa frete sempre disponível colapsado) — **P** · risco baixo. (3) Detecção de município → MDF-e (ligada ao bloco fiscal) — ver Parte 7. ⚠️ custo de frete **toca valor** — entra no total; mexer no fluxo exige dupla confirmação. |
| **Stale** | Status de remessa, "entregar a", detalhes de envio — vivo tem, mockup não. |

## Parte 9 — Ações / rodapé

| | |
|---|---|
| **Mockup propõe** | Rodapé sticky com totais (Produtos/Aprovado, Pendente, Frete, **Desconto inline**, Total) + ações: Cancelar (`esc`) · Salvar e imprimir · **"Salvar e emitir" / "Salvar e gerar OS"** (label muda por vertical) com `kbd F2`. |
| **Vivo já tem** | Rodapé sticky com mensagem de validação inline (por que botão está desabilitado) + Cancelar (com AlertDialog se há trabalho) · Salvar e Imprimir · Salvar venda. Atalho **Ctrl/Cmd+Enter** (mockup usa F2). |
| **Gap real** | (1) **Desconto editável no próprio rodapé** (mockup) vs card Resumo (vivo) — preferência de layout; ⚠️ toca valor. **P** · risco baixo-médio. (2) Label de ação **contextual por vertical** ("Salvar e gerar OS") — depende do fluxo OS. **P** · risco baixo. (3) Atalho F2 vs Ctrl+Enter — divergência de convenção, **P**. |
| **Stale** | Mensagem "por que desabilitado", AlertDialog de cancelar, draft recover — vivo tem, mockup não. |

---

## Conceito transversal — Toggle de VERTICAL no header

O mockup gira em torno de um **seletor de vertical** (ComVis ↔ Oficina) que troca catálogo, campos
(veículo+mecânico na Oficina; frete na ComVis), agrupamento de itens e labels de ação. O vivo resolve
parte disso por **gate per-business** (`hasOficinaAuto` → seção veículo aparece/some), sem toggle manual.
Filosoficamente são abordagens diferentes: mockup = **operador escolhe o tipo**; vivo = **business define
o que existe** (alinhado ao Tier 0 multi-tenant: feature por business, não hardcode/toggle de UI).
**Não adotar o toggle como está** — colidiria com o princípio "habilitar feature é compra de pacote, não
toggle" (proibicoes.md). O que vale extrair é o **conteúdo vertical** (m², MO/peças, fiscal), gated por business.

---

## VEREDITO: ADOTAR-PARCIAL

O vivo está **à frente** do mockup em quase toda mecânica de venda genérica (KPIs, draft, split de
pagamento, desconto máx, despesas, IMEI, erro-por-item, anti-`num_uf`). O mockup **não é stale por
acidente** — ele é um protótipo de **outra vertical** (Comunicação Visual + Oficina) e traz fluxos que o
vivo, focado em vestuário, legitimamente não tem. Logo: **adotar seletivamente os conceitos verticais**,
gated por business, sem regredir nada do vivo e sem copiar a mecânica de centavos/toggle.

### Top 3 gaps reais (priorizados)
1. **Painel Fiscal inferido** (NFC-e/NF-e/NFS-e/MDF-e pela composição) — **G**, risco médio-alto, exige ADR + integração NfeBrasil. Maior diferencial conceitual.
2. **m² por linha (largura×altura)** pra ComVis — **G**, ⚠️ Tier 0 valor (dupla confirmação obrigatória). Central pra vertical Comunicação Visual.
3. **Cadastro rápido de cliente inline** (sem abrir outra aba) — **M**, risco baixo, ganho de UX de balcão imediato e barato.

### Não adotar (stale ou conflitante)
- Mecânica de centavos / `parseCents` (vivo já tem defesa pt-BR superior).
- Toggle manual de vertical no header (conflita com feature-por-business Tier 0).
- Pagamento de método único (vivo tem split multi-linha, é regressão).

### Pendências a confirmar antes de qualquer aplicação
- `ProductSearchAutocomplete` já tem navegação ↑/↓/Enter e leitura de EAN? (_pendente_)
- `PaymentRow` cobre parcelamento Nx? (_pendente_)
- Módulo Cobrança/RecurringBilling ligado pra "gera boleto ao salvar"? (_pendente_)
