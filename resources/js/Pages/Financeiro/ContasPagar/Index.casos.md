---
id: resources-js-pages-financeiro-contas-pagar-index-casos
casos: Contas a Pagar · /financeiro/contas-pagar
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-07-06"
---

# Casos de Uso & Aceite — Contas a Pagar

> Tela Tier-0 (dinheiro a sair + **baixa que mexe em valor**). Charter retroativo criado na
> **Onda de correção Financeiro** ([ADR 0320], régua por tela). Persona: **Eliana [E]**.
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.
>
> ⚠️ **Régua estendida (Onda 0b) + dente D1:** a tela tem **0 UC com teste** E o cálculo da baixa
> parcial (`valor_aberto − valor_baixa`) está **🔴 indefeso** (nenhuma prova). Os casos ficam no
> **backlog sem id** de propósito (G-2 · [ADR 0264]): `UC-*` sem teste = órfão que quebra o
> `casos-gate`. Cada um vira `UC-CP-NN` **no mesmo PR** que trouxer o teste (Pest/MySQL real no
> CT100 — [ADR 0062]). O teste de cálculo (D1) é o **outro chip** (onda de cálculo).

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado sem teste citando o id = órfão. Itens SEM token de UC até existir teste real.

- **[BACKLOG] Baixa total quita o título** — Dado título `pagar` `aberto` de R$ X · Quando registro
  baixa de X · Então nasce `TituloBaixa` e o título vira `quitado` com `valor_aberto = 0`.
- **[BACKLOG] Baixa parcial calcula o aberto ao centavo (D1)** — Dado título de R$ 100 · Quando
  baixo R$ 30 · Então o valor **se conserva**: nasce um título FILHO quitado de 30 (`titulo_pai_id`)
  e o pai reduz para 70, seguindo **aberto** — `Σ(filhos) + pai.valor_aberto == 100`.
  **É o caso do dente D1** (property + golden + cross-check); classe do `num_uf`.
  > ⚠️ **Correção factual 2026-07-27 (onda `sdd-from-source`).** Este bullet dizia
  > *"`valor_aberto = 70,00` e `status = parcial`"*. O código **não usa mais** `status='parcial'`
  > desde a decisão [W] de 2026-06-04 (*baixa parcial vira SPLIT* — comentário literal em
  > `UnificadoController@baixar`): o contrato descrito aqui estava **errado sobre o sistema**, e um
  > teste escrito a partir dele nasceria vermelho por motivo errado. Corrigido o **perdedor**
  > (precedência: código provado > casos), sem mexer na intenção. O dente D1 deixou de ser
  > "🔴 indefeso": passou a ser defendido — **na tela onde a baixa realmente mora**, a Visão
  > Unificada — por `UC-FUNI-01` (`BaixaConservacaoValorContratoTest`, lane `financeiro-pest`).
- **[BACKLOG] Baixa acima do aberto é recusada** — Quando `valor_baixa > valor_aberto` · Então flash
  `error` "Valor da baixa excede o aberto" e nada é gravado.
- **[BACKLOG] Título quitado/cancelado não aceita baixa** — Quando o título já está `quitado`/`cancelado`
  · Então recusa com aviso (não cria `TituloBaixa`).
- **[BACKLOG] Conta bancária de outro business é rejeitada (Tier 0)** — Quando `conta_bancaria_id`
  aponta pra conta de outro business · Então 422 (a validação `exists` hoje **não** scopa business —
  gap Tier-0 catalogado no charter · Anti-hooks). Parcialmente tocado por `MultiTenantIsolationTest`
  (isolamento de planos + hard-delete de baixa bloqueado), **sem UC-id citado** ainda.
- **[BACKLOG] Baixa é idempotente** — cada `TituloBaixa` nasce com `idempotency_key` uuid; re-submit
  não deve duplicar a baixa.

## Como rodar a suíte
1. **Pest (MySQL real):** lane do Financeiro no CT100 — quando os UCs acima ganharem teste. O UC de
   baixa parcial exige o dente D1 (property/golden/cross-check).
2. **Cadência:** rodar ao fim de toda mexida na tela. UC ❌ = regressão → lição + conserto.

## Trilha do tempo
- 2026-07-27 · [CC] onda `sdd-from-source` (passo 5 · Onda 2) — **nenhum UC novo aqui, de propósito**:
  esta tela está `deprecated` (charter, #3718) e a `US-FIN-064` (`todo`) prevê o redirect pro
  Unificado; investir contrato numa tela que vai morrer é dívida. O que foi feito: **correção
  factual** do bullet D1 (o `status='parcial'` descrito não existe desde 2026-06-04) e ponteiro pro
  UC que passa a defender o cálculo na tela onde a baixa mora (`UC-FUNI-01`, Visão Unificada).
  Contrato do módulo agora vive em `memory/requisitos/Financeiro/SDD-tela-financeiro-v1.0.md`.
- 2026-07-03 · [CC] criado na Onda de correção Financeiro (régua por tela, [ADR 0320]): charter +
  casos retroativos. Cobertura de comportamento e D1 nascem em **débito visível** — a contradição
  UX-alto (70 Advanced) / baixa-de-valor-indefesa que o programa existe pra expor.
- 2026-07-06 · [CC] sweep D-14 (partial reload `only:` no filtro — PR #3889 pattern). Só mudou
  COMO as props carregam; comportamento/cálculo/baixa intocados. `last_run` bumpado (G-6) — os UCs
  em débito seguem sem teste (sem `Status: ✅`, nada a revalidar via manifesto).

[ADR 0320]: ../../../../../memory/decisions/0320-programa-ondas-regua-correcao.md
[ADR 0264]: ../../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0062]: ../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md
