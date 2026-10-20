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

## ISS retido ajusta o título (US-FIN-072 · UC-FLX-30)

> **Âncora:** [US-FIN-072](../../../../../memory/requisitos/Financeiro/SPEC.md) e o UC-FLX-30 do
> catálogo de fluxos (estágio "NFS-e Autorizada"). Quando a NFS-e da venda é autorizada com retenção,
> o título a receber passa a cobrar o líquido: bruto − ISS retido − PIS − COFINS − CSLL retidos.
> ⚠️ REGRA MESTRE de valor: chave **por business, desligada por padrão**
> (`business.common_settings['financeiro_iss_retido_ajuste']`); só age na mudança de status da nota
> (nota autorizada antes do deploy não é tocada); título com baixa não é reescrito. Para as notas já
> autorizadas existe só a prévia `financeiro:iss-retido-ajuste --dry-run --business=N`, que não grava.
> Nesta entrega a tela ainda não mostra bruto/retenções/líquido; IR/INSS retidos e o "aplicar" nas notas
> antigas são follow-up da US. Prova: `Modules/Financeiro/Tests/Feature/IssRetidoAjusteTituloContratoTest.php`.
> Caso-base: serviço 1.000,00 · ISS 5% retido 50,00 · PIS/COFINS/CSLL 4,65% = 46,50 → título 903,50.

## UC-CRISS-01 · Chave desligada não muda o título · `must`

**Dado** um business sem a chave (o padrão) e um título a receber aberto de 1.000,00 da venda V
**Quando** a NFS-e de V com ISS retido é autorizada **Então** o título continua 1.000,00 em total e
em aberto, sem registro de ajuste.

Status: 🧪

## UC-CRISS-02 · Chave ligada: título cobra o líquido, uma vez só · `must` valor

**Dado** a chave ligada e um título aberto de 1.000,00 da venda V **Quando** a NFS-e de V é autorizada
com ISS 5% retido e PIS/COFINS/CSLL de 6,50/30,00/10,00 **Então** o título passa a 903,50 (total e em
aberto), o bruto 1.000,00 e cada retenção ficam no registro do ajuste, e reprocessar a autorização não
desconta de novo.

Status: 🧪

## UC-CRISS-03 · Título com baixa fica com ajuste pendente · `must` valor

**Dado** a chave ligada e o título da venda V já com recebimento parcial (aberto 400,00 de 1.000,00)
**Quando** a NFS-e de V é autorizada **Então** os valores do título não mudam e o ajuste fica
`pendente` (motivo `pendente_baixa`) com o retido de 96,50 registrado para decisão humana.

Status: 🧪

## UC-CRISS-04 · Cancelar a NFS-e devolve o título ao bruto · `must` valor

**Dado** o título da venda V ajustado para 903,50 **Quando** a NFS-e é cancelada **Então** o título
volta a 1.000,00 (903,50 + 96,50) e o ajuste fica `estornado`.

Status: 🧪

## UC-CRISS-05 · Nota de um business não toca título de outro · `must` Tier 0

**Dado** os businesses A (98) e B (99) com a chave ligada e um título de B com o mesmo
`transaction_id` da nota de A **Quando** a nota de A é autorizada **Então** o título de B continua
1.000,00, sem registro de ajuste.

Status: 🧪

## UC-CRISS-06 · Prévia das notas já autorizadas não grava nada · `must` valor

**Dado** uma NFS-e autorizada com a chave desligada e o título ainda em 1.000,00 **Quando** rodo
`financeiro:iss-retido-ajuste --dry-run --business=A` **Então** a saída mostra o título indo para
903,50 com ação `aplicar`, a linha do título fica idêntica, e sem `--dry-run` o comando recusa.

Status: 🧪

## UC-CRISS-07 · Re-sincronizar a venda mantém o líquido · `must` valor

**Dado** o título da venda V ajustado para 903,50 **Quando** a venda é re-sincronizada a partir do
core (total 1.000,00) **Então** o título segue 903,50 em total e em aberto e o ajuste segue `aplicado`.

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
[ADR 0320]: ../../../../../memory/decisions/0320-programa-ondas-regua-correcao.md
[ADR 0264]: ../../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0062]: ../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md
