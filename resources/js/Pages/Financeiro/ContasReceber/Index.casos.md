---
id: resources-js-pages-financeiro-contas-receber-index-casos
casos: Contas a Receber · /financeiro/contas-receber
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-08-08"
---

# Casos de Uso & Aceite — Contas a Receber

> Tela Tier-0 (dinheiro a entrar). Charter retroativo criado na **Onda de correção Financeiro**
> ([ADR 0320], régua por tela). Persona: **Eliana [E]**.
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.
>
> ⚠️ **Régua estendida (Onda 0b):** hoje esta tela tem **0 UC com teste que o defenda** — a
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

---

## Régua de cobrança do título vencido (US-FIN-071 · UC-FLX-29)

> **Âncora:** [US-FIN-071](../../../../../memory/requisitos/Financeiro/SPEC.md) e o UC-FLX-29 do
> catálogo de fluxos (transição "Vencido → Em cobrança": lembretes D+1, D+5 e D+15 e tarefa ao
> vendedor). Nesta entrega é o job diário por business (`ReguaCobrancaJob`, agendado via
> `financeiro:regua-cobranca`) — a tela ainda não mostra o histórico nem configura a régua; WhatsApp,
> estado "em cobrança" no título e a configuração com permissão são follow-up da US. Régua **desligada
> por padrão** (`business.common_settings['financeiro_regua_cobranca']`). Prova:
> `Modules/Financeiro/Tests/Feature/ReguaCobrancaContratoTest.php`. Não mexe em valor de título.

## UC-CRREG-01 · Lembrete em D+1, D+5 e D+15, não em D+2 · `must`

**Dado** régua ligada com os dias padrão e hoje = 20/03, quatro títulos a receber em aberto vencidos
em 19/03 (D+1), 18/03 (D+2), 15/03 (D+5) e 05/03 (D+15), cada um com cliente que tem e-mail
**Quando** o job do dia roda **Então** saem 3 e-mails e ficam 3 lembretes `enviado` (etapas 1, 5 e
15); o título de D+2 não recebe nada.

Status: 🧪

## UC-CRREG-02 · Rodar duas vezes no mesmo dia não duplica · `must`

**Dado** um título vencido há 5 dias **Quando** o job roda duas vezes no mesmo dia **Então** existe
1 lembrete para a etapa 5 e sai 1 e-mail só.

Status: 🧪

## UC-CRREG-03 · Opt-out de e-mail não recebe, e o motivo fica registrado · `must` LGPD

**Dado** um título vencido há 1 dia cujo cliente tem `email_consent = false` **Quando** o job roda
**Então** nenhum e-mail sai e o lembrete da etapa 1 fica `nao_enviado` com motivo `opt_out_email`.

Status: 🧪

## UC-CRREG-04 · Última etapa vira tarefa para o vendedor · `must`

**Dado** dois títulos vencidos há 15 dias — um de venda com vendedor comissionado V, outro manual — e
um vencido há 5 dias **Quando** o job roda **Então** o da venda gera tarefa (`crm_schedules`)
atribuída a V, o manual gera tarefa para o dono do business, e o de 5 dias não gera tarefa.

Status: 🧪

## UC-CRREG-05 · Régua desligada não faz nada · `must`

**Dado** um business sem a chave da régua (o padrão) e um título vencido há 1 dia **Quando** o job
roda **Então** nenhum lembrete é registrado e nenhum e-mail sai.

Status: 🧪

## UC-CRREG-06 · Um business não toca títulos de outro · `must` Tier 0

**Dado** os businesses A (98) e B (99) com a régua ligada, cada um com 1 título vencido há 1 dia
**Quando** roda o job de A **Então** só o título de A tem lembrete e sai 1 e-mail; o de B fica sem
nada.

Status: 🧪

## Como rodar a suíte
1. **Pest (MySQL real):** lane do Financeiro no CT100 — quando os UCs acima ganharem teste.
2. **Cadência:** rodar ao fim de toda mexida na tela. UC ❌ = regressão → lição + conserto.

## Trilha do tempo
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
- 2026-10-20 · [CC] UC-CRREG-01..06 (US-FIN-071 · UC-FLX-29): primeira cobertura com UC-id desta
  tela — a régua de cobrança é job diário, sem mudança no `.tsx`; `last_run` intocado (nenhum UC
  antigo reexecutado). Prova: `ReguaCobrancaContratoTest`.

[ADR 0320]: ../../../../../memory/decisions/0320-programa-ondas-regua-correcao.md
[ADR 0264]: ../../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0062]: ../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md
