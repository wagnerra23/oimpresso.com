---
page: /financeiro/contas-pagar
component: resources/js/Pages/Financeiro/ContasPagar/Index.tsx
owner: wagner
status: draft
last_validated: "2026-07-03"
parent_module: Financeiro
parent_capterra: memory/requisitos/Financeiro/CAPTERRA-INVENTARIO.md
related_adrs: [93, 94, 320]
related_us: [US-FIN-013, US-FIN-020]
runbook: memory/requisitos/Financeiro/RUNBOOK-unificado.md
tier: A
charter_version: 1
---

# Page Charter — /financeiro/contas-pagar

> **Status:** `draft` — charter retroativo (Onda de correção Financeiro — régua por tela, [ADR 0320]).
> A tela **aparenta viva** (o `ContaPagarController` renderiza Inertia 200, está no sidebar via
> `DataController`), mas o charter **não foi validado contra prod** nesta sessão — nasce `draft`,
> não `live` (R1: `live` = evidência datada). Promover exige `smoke:` datado **ou** `prod-flags.json`.
> Era uma das 7 telas de dinheiro sem contrato catalogadas em [_Roadmap_Faturamento §Camada de correção].
> Persona: **Eliana [E]** — financeiro do escritório. Gêmea de [Contas a Receber](../ContasReceber/Index.charter.md),
> com a diferença crítica: aqui existe **baixa (pagamento) inline que mexe em valor** — logo cai
> na [regra-mestre de VALOR/ESTOQUE](../../../../../memory/proibicoes.md) e no dente D1 (ver abaixo).
>
> **Relação com o Unificado:** é a **lente A pagar** isolada (só `tipo='pagar'`); o
> [Unificado](../Unificado/Index.charter.md) é a visão combinada.

---

## Mission (1 frase)

Responder **"o que eu tenho pra pagar e quando vence?"** e **registrar a baixa** (pagamento
total ou parcial) do título — escolhendo conta, valor, data e meio de pagamento.

---

## Goals — Features (faz)

- **Lista de títulos a pagar** (`Titulo` `tipo='pagar'`, `business_id` da session, `deleted_at`
  nulo), ordenada por vencimento, até 100 linhas. Colunas: Nº · Fornecedor (+ origem) · Vencimento
  · **Valor aberto** · Status · Ações.
- **Filtros** (recarga Inertia `preserveState`): por **status** (Todos/Abertos/Parciais/Quitados)
  e por **vencimento** (Qualquer/Hoje/Próx. 7 dias/Atrasados).
- **Registrar baixa / Pagar** (`Sheet` shadcn, título `aberto`/`parcial`): campos **valor da baixa**
  (default = `valor_aberto`, aceita **parcial**), **data**, **conta bancária**, **meio de pagamento**
  (dinheiro/pix/boleto/cartão/transferência/cheque/compensação), observações. `POST /contas-pagar/{id}/pagar`.
- **Backend `pagar()`** cria `TituloBaixa` (idempotência por `idempotency_key` uuid) e **recalcula**
  `valor_aberto = max(0, valor_aberto − valor_baixa)`, definindo `status` = `quitado` (aberto ≤ 0) ou
  `parcial`. Guardas: título `quitado`/`cancelado` recusa baixa; `valor_baixa > valor_aberto` recusa.
- **Novo pagamento**: botão primary → `/financeiro/unificado/novo?kind=payable`.
- **Chrome canon**: `<PageHeader>` v3.8 + `FinanceiroSubNav active="contas-pagar"` (ADR 0180).
- **Multi-tenant Tier 0** (ADR 0093): queries + `findOrFail` scopados ao `business_id` da session;
  `conta_bancaria_id` validado por `exists` (defesa cross-tenant a reforçar — ver Anti-hooks).

---

## Non-Goals (não faz — de propósito)

- **Não calcula total/desconto/imposto do título** — o valor do título já vem pronto; o que a tela
  faz de aritmética é a **baixa** (subtração `valor_aberto − valor_baixa`) → é isso que o D1 cobre.
- **Não cria título inline** — redireciona pro `/unificado/novo`.
- **Não emite boleto** (isso é a receber). Aqui só se **paga**.
- **Não pagina além de 100**.

---

## UX targets

- Persona Eliana: filtrar Atrasados e dar baixa na linha em ≤3 cliques.
- Nota screen-grade atual: **70 · Advanced** (`financeiro-contaspagar-index.yaml`). Débitos abertos:
  `bg-blue-100` cru, sem `Inertia::defer`/skeleton, zero atalhos de teclado.

## Anti-hooks (o que denuncia regressão)

- **Baixa parcial calcular `valor_aberto` errado** — o total pago em N baixas parciais tem que
  fechar no valor do título ao centavo (é a classe do incidente `num_uf`). Hoje **sem teste** (D1 🔴).
- Aceitar `valor_baixa > valor_aberto` (deveria recusar) ou baixar título `quitado`/`cancelado`.
- `conta_bancaria_id` de **outro business** passar no `exists` (a validação hoje não scopa business —
  risco Tier-0 a fechar; catalogado, **não** consertar neste PR de charter).
- Lista mostrar título de outro business (Tier 0).

---

## D1 — cálculo de valor (dente, plugar não fundir · [ADR 0320] §3-bis)

Esta tela **toca valor**: a baixa registra `valor_baixa` e recalcula `valor_aberto`/`status` no
`pagar()`. Logo **D1 aplica** e hoje está **🔴 indefeso** — não há property test do round-trip de
número nem golden fixture da baixa parcial nem cross-check dos 2 caminhos. O dente real (o teste que
fecha D1) é trabalho da onda de **cálculo** (o "outro chip"), **não** deste PR de contrato. Aqui o
D1 entra **visível** no scorecard como débito.

---

## Casos de comportamento

UCs + status em [Index.casos.md](Index.casos.md), cruzado pelo `casos-coverage-guard.mjs` (G-2).
Foto UX+comportamento+D1 no scorecard `memory/governance/scorecards/screens/financeiro-contaspagar-index.yaml`.

[ADR 0320]: ../../../../../memory/decisions/proposals/0320-programa-ondas-regua-correcao.md
[_Roadmap_Faturamento §Camada de correção]: ../../../../../memory/requisitos/_Roadmap_Faturamento.md
