---
id: resources-js-pages-financeiro-contas-receber-index-casos
casos: Contas a Receber · /financeiro/contas-receber
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-10-26"
---

# Casos de Uso & Aceite — Contas a Receber

> Tela Tier-0 (dinheiro a entrar). Charter retroativo criado na **Onda de correção Financeiro**
> ([ADR 0320], régua por tela). Persona: **Eliana [E]**.
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.
>
> ⚠️ **Régua estendida (Onda 0b), retrato de 2026-07-03:** esta tela tinha **0 UC com teste que o defenda** (os UC-CRREG-* e UC-CRAB-* de 2026-10-20 são os primeiros com teste) — a
> cobertura de comportamento é o **débito** que o scorecard expõe ao lado da nota de UX (70).
> Os casos abaixo estão no **backlog sem id** de propósito: pela G-2 ([ADR 0264]), um `UC-*`
> declarado sem teste é órfão e **quebra o `casos-gate`**. Cada um só vira `UC-CR-NN` **no mesmo
> PR** que trouxer o teste (Pest/MySQL real no CT100 — [ADR 0062]). Declarar UC + teste = 1 PR.

---

## Retenção da NFS-e entra como baixa (US-FIN-072 · UC-FLX-30)

> **Âncora:** [US-FIN-072](../../../../../memory/requisitos/Financeiro/SPEC.md) e o UC-FLX-30 do
> catálogo de fluxos (estágio "NFS-e Autorizada"). **Decisão [W] 2026-10-20:** "venda parcial não
> deveria existir" — a retenção é **baixa**, não redução do título. Quando a NFS-e da venda é
> autorizada com retenção, a venda recebe um pagamento `retencao` no valor retido (ISS retido + PIS +
> COFINS + CSLL) e o título, que mantém o bruto, recebe a baixa `retencao` (sem movimento de caixa).
> ⚠️ REGRA MESTRE de valor: chave **por business, desligada por padrão**
> (`business.common_settings['financeiro_iss_retido_ajuste']`); só age na mudança de status da nota;
> a baixa vai só até o que falta pagar, a diferença fica como pendência. Para notas já autorizadas, só
> a prévia `financeiro:iss-retido-ajuste --dry-run --business=N`, que não grava. Desde 2026-10-21 a
> lista mostra Bruto · Retido · Líquido (UC-CRISS-08); IR/INSS e o "aplicar" nas notas antigas seguem
> como follow-up da US.
> Prova: `Modules/Financeiro/Tests/Feature/IssRetidoAjusteTituloContratoTest.php`.
> Caso-base: venda 1.000,00 · ISS 5% retido 50,00 · PIS/COFINS/CSLL 46,50 → baixa 96,50 · aberto 903,50.

## UC-CRISS-01 · Chave desligada não lança retenção · `must`

**Dado** um business sem a chave (o padrão) e a venda V de 1.000,00 a receber **Quando** a NFS-e de V
com ISS retido é autorizada **Então** o título segue 1.000,00 em aberto, sem baixa nem pagamento de
retenção.

Status: 🧪

## UC-CRISS-02 · Chave ligada: baixa de retenção 96,50, uma vez só · `must` valor

**Dado** a chave ligada e a venda V de 1.000,00 com título aberto **Quando** a NFS-e de V é autorizada
com ISS 5% retido e PIS/COFINS/CSLL de 6,50/30,00/10,00 **Então** o título mantém 1.000,00 de total,
recebe uma baixa `retencao` de 96,50 (sem movimento de caixa) e fica com 903,50 em aberto; reprocessar
a autorização não lança outra.

Status: 🧪

## UC-CRISS-03 · Cliente paga o líquido e tudo fica pago · `must` valor

**Dado** o título de V com a baixa de retenção de 96,50 **Quando** o cliente paga 903,50 **Então** a
venda fica `paid` e o título `quitado` com 0,00 em aberto — nunca parcial.

Status: 🧪

## UC-CRISS-04 · Aberto menor que o retido: baixa até o aberto, diferença pendente · `must` valor

**Dado** a venda V já com 950,00 pagos (falta 50,00) **Quando** a NFS-e com 96,50 retidos é autorizada
**Então** a baixa de retenção é de 50,00, os 46,50 restantes ficam como pendência no registro do título,
a venda fica `paid` e o título quitado — nunca saldo negativo.

Status: 🧪

## UC-CRISS-05 · Cancelar a NFS-e estorna a baixa de retenção · `must` valor

**Dado** o título de V com a baixa de retenção de 96,50 **Quando** a NFS-e é cancelada **Então** o
pagamento de retenção sai da venda, o título ganha a baixa de estorno (−96,50, append-only), volta a
1.000,00 em aberto, a venda volta a `due` e o registro fica `estornado`.

Status: 🧪

## UC-CRISS-06 · Nota de um business não toca venda de outro · `must` Tier 0

**Dado** os businesses A (98) e B (99) com a chave ligada e uma nota de A apontando para a venda de B
**Quando** a nota de A é autorizada **Então** a venda e o título de B ficam sem pagamento e sem baixa de
retenção.

Status: 🧪

## UC-CRISS-07 · Prévia das notas já autorizadas não grava nada · `must` valor

**Dado** uma NFS-e autorizada com a chave desligada e a venda ainda com 1.000,00 em aberto **Quando**
rodo `financeiro:iss-retido-ajuste --dry-run --business=A` **Então** a saída mostra a baixa de 96,50 e o
aberto indo a 903,50 com ação `baixa`, nada é gravado, e sem `--dry-run` o comando recusa.

Status: 🧪

## UC-CRISS-08 · A lista mostra bruto, retido e líquido · `must` valor

**Dado** o título de V com a retenção de 96,50 lançada **Quando** abro Contas a Receber **Então** a linha
mostra Bruto 1.000,00 · Retido 96,50 · Líquido 903,50 (conta no servidor, em centavos, a partir de
`metadata.retencao_nfse.retido_total`). Sem retenção, ou com a chave desligada, Retido mostra "—" e o
Líquido é o bruto; cancelada a nota (retenção estornada), Retido volta a 0 e o Líquido a 1.000,00. Só
exibe: nada é gravado.

Status: 🧪

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

---

## Régua de cobrança do título vencido (US-FIN-071 · UC-FLX-29)

> **Âncora:** [US-FIN-071](../../../../../memory/requisitos/Financeiro/SPEC.md) e o UC-FLX-29 do
> catálogo de fluxos (transição "Vencido → Em cobrança": lembretes D+1, D+5 e D+15 e tarefa ao
> vendedor). Nesta entrega é o job diário por business (`ReguaCobrancaJob`, agendado via
> `financeiro:regua-cobranca`). O histórico aparece no painel "Lembretes" desta tela (UC-CRREG-08) e a
> régua liga na aba Financeiro de `/business/settings`; WhatsApp e estado "em cobrança" no título são
> follow-up da US. Régua **desligada
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
um vencido há 5 dias **Quando** o job roda **Então** o da venda gera tarefa no CRM (pelo contrato
`AgendaDeTarefas`) atribuída a V e ligada à venda, o manual gera tarefa para o dono do business, e o de 5 dias não gera tarefa.

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

## UC-CRREG-07 · Sem o módulo Crm, o lembrete sai e não há tarefa · `must`

**Dado** a agenda de tarefas (contrato `App\Contracts\Crm\AgendaDeTarefas`) não ligada e um título
vencido há 15 dias **Quando** o job roda **Então** o e-mail sai, o lembrete da etapa 15 fica `enviado`
sem tarefa e nenhuma linha nova aparece em `crm_schedules`.

Status: 🧪

## UC-CRREG-08 · Painel "Lembretes" mostra o que saiu e o que não saiu · `must` Tier 0

**Dado** a régua ligada em A (98) e em B (99), um título de A vencido há 1 dia com cliente que tem
e-mail, outro de A vencido há 5 dias com cliente que recusou e-mail, e um título de B vencido há 1 dia,
e o job do dia rodou nos dois **Quando** abro a tela de A **Então** a carga normal não traz os
lembretes (prop `Inertia::optional`) **E quando** clico em "Lembretes" (reload parcial `only:
['lembretes']`) **Então** vejo a régua ligada com D+1, D+5 e D+15, o título de D+1 como `enviado`, o
de D+5 como `nao_enviado` com motivo `opt_out_email`, e nada de B. A régua liga na aba Financeiro de
`/business/settings` (UC-EMP-15). A régua chega com `dias_antes` (vazio por padrão, US-FIN-033) e cada item com
`fase` (`antes`|`apos`); o painel mostra `D-3` para aviso antes do vencimento e `D+3` para o lembrete depois.

Status: 🧪

## UC-CRREG-09 · Aviso antes do vencimento só com `dias_antes`, sem tarefa · `must`

Fonte: SPEC US-FIN-033 (avisar o cliente X dias antes do vencimento).
**Dado** régua ligada sem `dias_antes` (o padrão) e um título que vence em 3 dias **Quando** o job roda
**Então** nada é registrado. **Dado** `dias_antes = [3]`, um título que vence em 3 dias e outro em 2
**Quando** o job roda **Então** o de 3 dias recebe 1 lembrete `fase = antes`, etapa 3, `enviado`, com
assunto "vence em 3 dia(s)" e **sem** tarefa no CRM; o de 2 dias não recebe nada.

Status: 🧪

## UC-CRREG-10 · Aviso D-3 e lembrete D+3 do mesmo título convivem · `must`

Fonte: SPEC US-FIN-033 (idempotência por título, tipo e etapa).
**Dado** `dias = [3]` e `dias_antes = [3]` e um título que vence em 3 dias **Quando** o job roda hoje e
de novo (duas vezes) 6 dias depois **Então** o título tem 2 lembretes — `antes:3` e `apos:3` — e saem 2
e-mails: rodar duas vezes no mesmo dia não duplica.

Status: 🧪

## UC-CRREG-11 · A mensagem leva o meio de pagar da cobrança aberta · `must` Tier 0

Fonte: lacuna P0 "régua com link de pagamento" (levantamento 2026-10-25) + SPEC US-FIN-033.
**Dado** um título com cobrança `emitida` dele (`origem_type = fin_titulo`) com Pix copia e cola, outro cuja única
cobrança está `paga` (e uma cobrança de OUTRO business aponta para o mesmo id), e um título de venda cuja venda tem
cobrança `vencida` com linha digitável **Quando** o job roda **Então** o primeiro e-mail traz o Pix copia e cola, o
segundo não traz meio de pagar nenhum, e o terceiro traz a linha digitável da cobrança da venda.

Status: 🧪

## Casos pedidos em 2026-10-23 — mudaram para o Financeiro unificado

Esta tela foi aposentada pelo [W] (charter: `deprecated`, superada pelo Financeiro/Unificado, decisão
de 2026-07-03). Os casos de total do serviço, retido e recebido, de título de venda agrupada e de título
de cada parte faturada, pedidos em 2026-10-23, estão em `../Unificado/Index.casos.md`. Verificado contra as regras do [W] em 2026-10-23.

1. **Pest (MySQL real):** lane do Financeiro no CT100 — quando os UCs acima ganharem teste.
2. **Cadência:** rodar ao fim de toda mexida na tela. UC ❌ = regressão → lição + conserto.

## Trilha do tempo
- 2026-10-23 · [M+C] Backlog de ISS retido (total, retido e recebido) e de título de venda agrupada, sem id.
- 2026-10-23 · [M+C] Backlog: título de cada parte faturada, da comparação com concorrentes (Omie), sem id.
- 2026-10-23 · [M+C] Os três casos de 2026-10-23 mudaram para `Financeiro/Unificado/Index.casos.md`: esta tela está aposentada.
- 2026-10-21 · [CC] US-FIN-071: botão "Lembretes" no cabeçalho + painel lateral (UC-CRREG-08). As colunas
  Bruto/Retido/Líquido do #9698 seguem intactas; revalidado após juntar os dois.
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
- 2026-10-21 · [CC] UC-CRISS-08 (US-FIN-072): colunas Bruto · Retido · Líquido na lista. Só leitura;
  valores calculados em `RetencaoNfseTituloService::valoresDoTitulo` e provados por 3 caminhos em
  `IssRetidoAjusteTituloContratoTest`.
- 2026-10-20 · [CC] UC-CRREG-01..07 (US-FIN-071 · UC-FLX-29): primeira cobertura com UC-id desta
  tela — a régua de cobrança é job diário, sem mudança no `.tsx`; `last_run` intocado (nenhum UC
  antigo reexecutado). Prova: `ReguaCobrancaContratoTest`.

[ADR 0320]: ../../../../../memory/decisions/0320-programa-ondas-regua-correcao.md
[ADR 0264]: ../../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0062]: ../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md
