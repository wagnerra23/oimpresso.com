---
name: Discovery cliente-sinal Larissa @ ROTA LIVRE — Compras
description: Script de call discovery pra Wagner validar dor real de compras com Larissa (biz=4 vestuário) antes de ativar Onda 1 do roadmap Compras. Aplica ADR 0105 (cliente-como-sinal).
type: discovery
status: draft
related:
  - memory/decisions/0105-cliente-como-sinal-guiar-sem-mandar.md
  - memory/reference/cliente-rotalivre.md
  - memory/requisitos/Compras/CAPTERRA-DESIGN-FICHA.md
  - memory/requisitos/Compras/AUDITORIA-COMPRAS-2026-05-21.md
created: 2026-05-21
---

# Discovery — Larissa Fernandes @ ROTA LIVRE — Compras

**Objetivo da call:** validar se Compras é dor real e priorizada antes de gastar 10-15dd na Onda 1 (Foundation). Sem isso a Onda fica órfã ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md): "se sistema não tiver e cliente paga bem + reporta, é desenvolvido").

**Duração-alvo:** 15-20 min. Aberta, não enviesada. Wagner conduz, anota literal.

**O que NÃO fazer na call:**

- ❌ Não descrever o protótipo (vicia resposta dela)
- ❌ Não falar "XML NF-e", "FSM", "3-way match" (jargão técnico fora do mundo dela)
- ❌ Não prometer prazo
- ❌ Não perguntar se "ela quer feature X" (resposta sempre sim) — perguntar **o que dói**

**Premissa a derrubar logo:** protótipo Cowork tem persona gráfica (lonas/vinis/tintas/ilhós) mas Larissa é **vestuário/moda** (CNPJ LARISSA COMERCIO DE ARTIGOS DO VESTUARIO). Fornecedor dela provavelmente é representante de moda viajante, **não** atacado B2B com portal XML. Validar antes de pressupor importação XML NF-e como ação primária.

**Sinal confirmado Wagner 2026-05-21:** *"ela compra e [tem] entrada por grade"* — Larissa opera **compra+entrada por grade** (matriz tam×cor). Isso REDEFINE P0 da Onda 1 (de "XML NF-e auto-rascunho" pra "componente `GradeMatrixInput`"). Backend já cobre (`Variation`/`VariationTemplate`/`purchase_lines.variation_id`); falta UI matricial. Detalhe completo no Bloco 4.5 abaixo.

---

## Bloco 1 — Mapear processo atual (5 min · aberto)

> **W:** *"Larissa, queria entender como você compra mercadoria hoje, do início ao fim. Me conta um caso recente — uma compra que você fez essa semana."*

Escutar sem interromper. Anotar literal:

- **Q1.1** Quem ela contata pra comprar? (representante / atacadista / WhatsApp / showroom / e-commerce B2B)
- **Q1.2** Como o pedido é feito? (telefone / WhatsApp / planilha / e-mail / app fornecedor)
- **Q1.3** Frequência típica? (semanal / quinzenal / sazonal coleção)
- **Q1.4** Quantos fornecedores ativos? (1-3 / 4-10 / >10)
- **Q1.5** Forma de pagamento usual? (boleto 30/60/90 / cartão CNPJ / dinheiro / PIX)

## Bloco 2 — Detectar a dor (5 min · só perguntar SE ela não trouxe espontaneamente)

> **W:** *"E quando dá ruim numa compra, o que costuma dar errado?"*

- **Q2.1** Já recebeu mercadoria diferente do pedido? Com que frequência?
- **Q2.2** Já pagou nota e depois descobriu erro de quantidade/preço? Como descobriu?
- **Q2.3** Como ela sabe HOJE quanto está devendo pra cada fornecedor? (cabeça / caderno / planilha / oimpresso já mostra?)
- **Q2.4** O que ela mais gostaria que o sistema "olhasse" pra ela em compras?

## Bloco 3 — Quantificar tempo & custo (3 min)

- **Q3.1** Quanto tempo POR DIA ela gasta hoje organizando compra (pedindo / conferindo / lançando)?
- **Q3.2** Última vez que perdeu dinheiro em compra (preço errado, mercadoria parada, prazo perdido): quanto foi? Quando?
- **Q3.3** Tem caso de fornecedor que ela parou de usar por erro recorrente? Conta a história.

## Bloco 4 — Entender o fluxo XML NF-e (2 min · só se ela mencionou "nota")

> **W:** *"Quando chega a nota fiscal do fornecedor, o que você faz com ela hoje?"*

- **Q4.1** Recebe XML no e-mail ou só DANFE em papel?
- **Q4.2** Algum sistema "puxa" essa nota automaticamente? (contador / oimpresso / nada)
- **Q4.3** Já aconteceu de não dar entrada na nota e depois faltar estoque "fantasma"?

## Bloco 4.5 — Entrada por grade (CONFIRMADO Wagner 2026-05-21 · 2 min validação)

> **W:** *"Quando chega a mercadoria, você dá entrada por modelo inteiro ou tem que digitar tamanho por tamanho?"*

Wagner já confirmou: **Larissa compra e dá entrada por grade** (matriz tam × cor). Esse bloco é só pra quantificar.

- **Q4.5.1** Quantos modelos diferentes ela recebe numa entrega típica? (5 / 20 / 50+)
- **Q4.5.2** Grade típica: quantos tamanhos × quantas cores por modelo? (ex: PMGG × 3 cores = 12 SKUs filhos)
- **Q4.5.3** Hoje no oimpresso, ela dá entrada UMA LINHA POR SKU FILHO ou tem algum atalho? (provavelmente linha-a-linha — é o que o `purchase/create.blade.php` permite)
- **Q4.5.4** Quanto tempo gasta digitando entrada de UMA entrega típica? (10 min / 1h / meio dia)

**Achado técnico paralelo (já validado no código 2026-05-21):**

UltimatePOS core já tem `app/VariationTemplate.php` + `app/Variation.php` + `purchase_lines.variation_id` (JOIN em [PurchaseController.php:645](../../../app/Http/Controllers/PurchaseController.php#L645)) — **backend cobre grade**. O que falta é UI matricial (modelo pai → tabela tam×cor → 1 célula = 1 qty → save grava N linhas).

Bling e Tiny ERP fazem assim. Protótipo Cowork atual (`compras-page.jsx`) **NÃO** desenha entrada matricial — mock data é gráfica linear (`qty: 200, unit: "m²"`).

**Implicação imediata se SINAL FORTE:**

| Posição original Onda 1 (auditoria genérica) | Onda 1 ajustada Larissa vestuário |
|---|---|
| P0a: Bridge XML NF-e → Purchase auto-rascunho | P0a: **Componente `GradeMatrixInput`** (modelo pai → tabela tam×cor) |
| P0b: 3-way match (PO/Recv/NF-e) | P0b: Lista de compras + drawer detalhe (sem FSM 6 estágios complexa — só rascunho/recebido/pago) |
| P0c: FSM 6 estágios | P0c: Importação XML NF-e — DEPOIS, se representante mandar XML |

Redesenhar Bloco 1 mock do protótipo Cowork pra vestuário (Bom Retiro/Brás atacado, modelos camiseta/calça/vestido, grade real) ANTES de qualquer F2 screenshot pra Wagner aprovar.

## Bloco 5 — Critério de pronto pelo lado dela (2 min)

> **W:** *"Se a gente fizesse uma tela de Compras nova no oimpresso, o que seria 'mágica' pra você? O que ela teria que fazer pra você dizer 'agora sim'?"*

Anotar literal. Não enviesar.

---

## Saída esperada da call → decisão Wagner

Após a call, classificar em **um** dos 4 buckets:

| Bucket | Significado | Próxima ação |
|---|---|---|
| **SINAL FORTE** | Larissa cita 2+ dores quantificadas (R$ ou horas/dia) E processo dela bate com features P0 da auditoria | Ativar **Onda 1 (Foundation 10-15dd)** — criar SPEC + charter + F1 pino visual |
| **SINAL MORNO** | Dor existe mas é #4-#5 na lista dela (NF-e fiscal / Financeiro / Estoque vêm antes) | Congelar Compras · priorizar o que ela citou #1-#2 |
| **SINAL FRACO** | Processo dela é via WhatsApp/papel e ela não reporta dor | Compras vira backlog teórico (ADR de feature wish, sem US ativa — [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) |
| **PIVOT** | Dor real é OUTRA coisa que apareceu na conversa (ex: controle de estoque por coleção, etiqueta de preço, devolução cliente) | Documentar pivot · nova auditoria do tema certo |

## Pegadinhas pra Wagner ficar atento durante a call

1. **Decoração de fluxo** ([cliente-rotalivre.md:30](../../reference/cliente-rotalivre.md#L30)) — Larissa decorou comportamento errado de `format_date`. Mesma coisa pode acontecer com compras: ela pode ter aceito processo manual como "normal" e não saber que pode melhorar. Perguntar "como deveria ser" pode dar branco — perguntar "o que dói" funciona melhor.

2. **Monitor 1280px** — qualquer tela nova precisa caber. Não prometer "drawer expansível" sem testar viewport dela primeiro.

3. **Vestuário ≠ Gráfica** — protótipo atual ficcionalizou persona gráfica. Os SUPPLIERS/PRODUCTS mock no `compras-page.jsx` vão precisar virar mocks de vestuário (atacado de moda BR: Bom Retiro/Brás SP, fornecedores comuns: Hering atacado, ZB Confeções, etc) antes de qualquer screenshot F2.

4. **Representante de moda viajante é canal típico PME vestuário BR** — pode ser que XML NF-e import nem seja P0 pra ela (representante mostra catálogo presencial, pedido vai por WhatsApp, nota chega DEPOIS por e-mail). Se for assim, P0 vira "registrar pedido pelo celular durante visita do representante" — feature completamente diferente.

5. **Volume real** = 17.251+ vendas em 5 anos ([cliente-rotalivre.md:14](../../reference/cliente-rotalivre.md#L14)) — assumir compras correspondente: ~50-200 compras/mês? Validar Q1.3.

---

## Pós-call — onde anotar resultado

Wagner cria `memory/sessions/YYYY-MM-DD-larissa-call-compras.md` com:

1. Respostas literais Q1-Q5 (não parafrasear)
2. Bucket escolhido (SINAL FORTE/MORNO/FRACO/PIVOT)
3. Próxima ação concreta (não vago)
4. Citações diretas que viram critério de pronto pra qualquer SPEC futura

Se SINAL FORTE → criar `memory/requisitos/Compras/SPEC.md` referenciando a call como justificativa cada US.
