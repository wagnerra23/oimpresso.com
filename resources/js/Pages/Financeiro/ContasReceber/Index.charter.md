---
id: resources-js-pages-financeiro-contas-receber-index-charter
page: /financeiro/contas-receber
component: resources/js/Pages/Financeiro/ContasReceber/Index.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
owner: wagner
status: deprecated
last_validated: "2026-07-03"
supersedes_note: "Superseção funcional pelo Unificado (/financeiro/unificado) — decisão [W] 2026-07-03."
parent_module: Financeiro
parent_capterra: memory/requisitos/Financeiro/CAPTERRA-INVENTARIO.md
related_adrs: [93, 94, 320]
related_us: [US-FIN-013, US-FIN-020]
runbook: memory/requisitos/Financeiro/RUNBOOK-cobranca.md
tier: A
charter_version: 2
---

# Page Charter — /financeiro/contas-receber

> **Status:** `deprecated` — **superada pelo [Financeiro/Unificado](../Unificado/Index.charter.md)** (decisão [W] 2026-07-03).
> Smoke em prod 2026-07-03 (biz=1 "Oimpresso Matriz", Wagner·Admin) confirmou que a tela **renderiza
> viva** — mas o Unificado cobre **100%** do que ela faz, incl. **emitir boleto** (lá via Banco Inter
> LIVE, mais novo que o `CnabDirectStrategy` daqui) + baixa + lente "A receber". Era o único
> diferencial e não é mais. Os testes já a chamavam de `legacy` (`test_contas_receber_legacy_*`).
> **Plano:** redirecionar `/financeiro/contas-receber` → `/financeiro/unificado?lente=receber` +
> ajustar o SubNav; quando o redirect landar, o trio (`.tsx`+`.charter`+`.casos`) é removido junto.
> Charter retroativo nasceu na Onda de correção Financeiro (régua por tela, [ADR 0320]) — ver
> [_Roadmap_Faturamento §Camada de correção].
> _v1 (2026-07-03) nasceu `draft`; v2 (mesmo dia) → `deprecated` após smoke + decisão [W]._
> Persona: **Eliana [E]** — financeiro do escritório, densidade alta. Secundária Larissa [L]
> (dona, quer saber "quem está me devendo?").
>
> **Relação com o Unificado:** esta é a **lente A receber** isolada (só `tipo='receber'`); a
> tela [Financeiro/Unificado](../Unificado/Index.charter.md) é a visão combinada (Pagar/Receber
> numa view só). Aqui o foco é **cobrança**: emitir boleto do título aberto.

---

## Mission (1 frase)

Responder **"quem está me devendo e o que já virou boleto?"** — lista os títulos a receber do
business (gerados de venda a prazo ou lançados à mão) e permite **emitir o boleto** do título
aberto, sem sair da tela.

---

## Goals — Features (faz)

- **Lista de títulos a receber** (`Titulo` `tipo='receber'`, `business_id` da session, `deleted_at`
  nulo), ordenada por vencimento, **até 100** linhas. Colunas: Nº · Cliente (+ origem: `Venda #id`
  ou origem manual) · Vencimento · **Valor aberto** · Status · Boleto · Ações.
- **Filtros** (recarga Inertia `preserveState`): por **status** (Todos/Abertos/Parciais/Quitados)
  e por **vencimento** (Qualquer/Hoje/Próx. 7 dias/Atrasados — `atrasado` = vencimento < hoje e
  status ≠ quitado).
- **Emitir boleto** (só em título `status='aberto'` sem boleto): `POST /contas-receber/{id}/boleto`
  → `TituloService` → `CnabDirectStrategy`. Requer conta bancária configurada (rodapé linka
  `/financeiro/contas-bancarias`). Span OTel `financeiro.boleto.emitir` (latência gateway + erro).
  Título que já tem boleto mostra `nosso_numero` + status e o botão some.
- **Novo recebimento**: botão primary redireciona pra `/financeiro/unificado/novo?kind=receivable`
  (o insert manual vive no Unificado — não é reimplementado aqui).
- **Chrome canon**: `<PageHeader>` v3.8 + `FinanceiroSubNav active="contas-receber"` (ADR 0180).
- **Multi-tenant Tier 0** (ADR 0093): toda query filtra `business_id` da session; `findOrFail`
  do título no boleto também é scopado ao business.

---

## Non-Goals (não faz — de propósito)

- **Não dá baixa / registra recebimento aqui** — isso é do Unificado (`FinBaixaSheet`) ou do
  Observer da venda→pagamento. Esta tela emite cobrança, não quita.
- **Não calcula total/desconto/imposto** — só exibe `valor_aberto`/`valor_total` que já vêm
  prontos do backend (ver D1: n/a). O valor do boleto = valor do título, montado server-side.
- **Não cria título inline** — redireciona pro `/unificado/novo`.
- **Não pagina além de 100** — corte explícito no controller; refino por filtro (débito de UX
  registrado no scorecard: sem defer/skeleton).

---

## UX targets

- Persona Eliana: achar "quem está atrasado" em ≤2 cliques (filtro Atrasados) e emitir boleto
  na mesma linha.
- Nota screen-grade atual: **70 · Advanced** (`financeiro-contasreceber-index.yaml`). Débitos
  abertos: `bg-blue-100` cru (viola zero-blue), sem `Inertia::defer`/skeleton, sem atalhos.

## Anti-hooks (o que denuncia regressão)

- Botão **Emitir boleto** aparecer em título quitado/cancelado/já-com-boleto.
- Lista mostrar título de **outro business** (Tier 0) — nunca.
- `emitirForm.processing` desabilitar **todos** os botões Emitir de uma vez (form global) em vez
  de só a linha clicada (débito catalogado no scorecard, dim Error-recovery).

---

## Casos de comportamento

Contrato de comportamento (UCs + status) vive ao lado em [Index.casos.md](Index.casos.md) e é
cruzado pelo `casos-coverage-guard.mjs` (G-2). Foto UX+comportamento no scorecard
`memory/governance/scorecards/screens/financeiro-contasreceber-index.yaml`.

[ADR 0320]: ../../../../../memory/decisions/0320-programa-ondas-regua-correcao.md
[_Roadmap_Faturamento §Camada de correção]: ../../../../../memory/requisitos/_Roadmap_Faturamento.md
