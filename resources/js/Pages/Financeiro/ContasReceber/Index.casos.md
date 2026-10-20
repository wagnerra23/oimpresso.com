---
id: resources-js-pages-financeiro-contas-receber-index-casos
casos: Contas a Receber · /financeiro/contas-receber
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-10-20"
---

# Casos de Uso & Aceite — Contas a Receber

> Tela Tier-0 (dinheiro a entrar). Charter retroativo criado na **Onda de correção Financeiro**
> ([ADR 0320], régua por tela). Persona: **Eliana [E]**.
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.
>
> ⚠️ **Régua estendida (Onda 0b), retrato de 2026-07-03:** esta tela tinha **0 UC com teste que o defenda** (os UC-CRAB-* de 2026-10-20 são o primeiro bloco com teste) — a
> cobertura de comportamento é o **débito** que o scorecard expõe ao lado da nota de UX (70).
> Os casos abaixo estão no **backlog sem id** de propósito: pela G-2 ([ADR 0264]), um `UC-*`
> declarado sem teste é órfão e **quebra o `casos-gate`**. Cada um só vira `UC-CR-NN` **no mesmo
> PR** que trouxer o teste (Pest/MySQL real no CT100 — [ADR 0062]). Declarar UC + teste = 1 PR.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado sem teste citando o id = órfão. Itens SEM token de UC até existir teste real.

- **[BACKLOG] Emitir boleto de título aberto gera remessa** — Dado título `receber` `aberto` sem
  boleto · Quando clico **Emitir boleto** · Então nasce `BoletoRemessa` (via `TituloService` →
  `CnabDirectStrategy`) com `nosso_numero`, o botão some e a coluna Boleto mostra o número. Candidato
  a UC + Pest (`ContaReceberController::emitirBoleto`).
- **[BACKLOG] Título já-com-boleto não re-emite** — Quando o título já tem `BoletoRemessa` não
  cancelado · Então o botão Emitir não renderiza e um novo POST é no-op (toast "já tem boleto").
- **[BACKLOG] Boleto sem conta bancária configurada falha com aviso** — Sem `fin_contas_bancarias`
  no business · Então a emissão devolve `DomainException` → flash `error` (não 500), e o rodapé
  linka `/financeiro/contas-bancarias`.
- **[BACKLOG] Filtro atrasado/semana/hoje não vaza título de outro business (Tier 0)** — Dado
  títulos de biz=1 e biz=99 · Quando filtro por vencimento · Então só aparecem os do business da
  session (ADR 0093). Parcialmente tocado por `MultiTenantIsolationTest` (rota responde + scope de
  planos), mas **sem UC-id citado** ainda.
- **[BACKLOG] Boleto é fluxo crítico observado** — a emissão registra span OTel
  `financeiro.boleto.emitir` com biz + titulo_id + falha de gateway (Wave 17 D9).

## Aberto do título parcial (US-FIN-073)

> **Âncora:** SPEC US-FIN-073 (achado do PR #9638, decisão [W] 2026-10-20). O saldo que esta tela
> mostra na coluna "Em aberto" é o `valor_aberto` do título; ele não pode voltar ao bruto quando o core
> regrava o `payment_status` da venda. Prova: `Modules/Financeiro/Tests/Feature/AbertoVendaParcialContratoTest.php`.
> VALOR: regra mestre — cada número confere por dois caminhos (Σ baixas do título e
> `final_total − Σ transaction_payments`).

## UC-CRAB-01 · Venda parcial mantém o que já foi pago · `must` valor

**Dado** uma venda de 1.000,00 com um pagamento de 400,00 **Quando** o core grava `payment_status = partial`
(e qualquer re-sincronização depois, como mudar o vencimento) **Então** o título fica com 600,00 em aberto e
status "parcial", nunca de volta a 1.000,00 "aberto".

Status: 🧪

## UC-CRAB-02 · Compra parcial idem · `must` valor

**Dado** uma compra de 500,00 com 200,00 pago **Quando** o core grava `partial` **Então** o título a pagar fica
com 300,00 em aberto, status "parcial".

Status: 🧪

## UC-CRAB-03 · Relatório dos títulos já gravados é só leitura · `must`

**Dado** um título parcial gravado antes do conserto (aberto de volta no bruto) **Quando** rodo
`financeiro:resync-from-core --business=N --aberto` **Então** ele aparece com aberto atual → correto e o status
atual → correto, e nada é gravado; com `--apply` o comando recusa — corrigir os existentes é decisão [W].

Status: 🧪

## UC-CRAB-04 · Relatório não mostra título de outro business · `must` Tier 0

**Dado** um título parcial divergente no business B **Quando** rodo o relatório para o business A **Então** ele
não aparece; rodando para B, aparece.

Status: 🧪

## Como rodar a suíte
1. **Pest (MySQL real):** lane do Financeiro no CT100 — quando os UCs acima ganharem teste.
2. **Cadência:** rodar ao fim de toda mexida na tela. UC ❌ = regressão → lição + conserto.

## Trilha do tempo
- 2026-10-20 · [CC] US-FIN-073: UC-CRAB-01..04 (aberto do título parcial + relatório `--aberto` só leitura),
  provados por `AbertoVendaParcialContratoTest` na lane financeiro-pest. Nenhum título existente muda no deploy.
- 2026-07-03 · [CC] criado na Onda de correção Financeiro (régua por tela, [ADR 0320]): charter +
  casos retroativos. Cobertura de comportamento nasce em **débito visível** (0 UC com teste) — a
  contradição UX-alto (70 Advanced) / comportamento-indefeso que o programa existe pra expor.
- 2026-07-06 · [CC] sweep D-14 (partial reload `only:` no filtro — PR #3889 pattern). Só mudou
  COMO as props carregam; comportamento/cálculo intocados. `last_run` bumpado (G-6) — UCs em
  débito seguem sem teste (sem `Status: ✅`, nada a revalidar via manifesto).

- 2026-08-08 · [CC] revalidado (bump `last_run`): migração do primary "Novo recebimento" do shim
  DEPRECATED `FinanceiroPrimaryButton` pro canon `<PageHeaderPrimary>` (ADR 0190). O shim emitia
  `.os-btn primary`, cuja única família de regras no CSS servido é escopada `.sells-cowork` → nenhuma
  casava e o botão rendia nu (medido em prod: padding 0, radius 0, texto em 2 linhas). Só o chrome do
  header mudou — `label` e `onClick` idênticos; comportamento/cálculo intocados. UCs em débito seguem
  sem teste (sem `Status: ✅`, nada a revalidar via manifesto).
[ADR 0320]: ../../../../../memory/decisions/0320-programa-ondas-regua-correcao.md
[ADR 0264]: ../../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0062]: ../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md
