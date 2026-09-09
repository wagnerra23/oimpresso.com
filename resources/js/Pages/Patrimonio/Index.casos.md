---
casos: Patrimonio/Index — Painel do Patrimônio
irmaos: Index.charter.md (lei) · memory/requisitos/AssetManagement/RUNBOOK-patrimonio-index.md
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-09-09"
---

# Casos de Uso & Aceite — Patrimonio/Index

> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão.
>
> Os ids nascem `UC-PAT-*`, não `UC-INDEX-*` como o `criar-tela.mjs` carimba: `INDEX` colidiria
> com toda outra tela chamada `Index.tsx`, e id de UC é global no `casos-gate`.
>
> **A sub-navegação não tem UC aqui.** O `_shared/PatrimonioSubNav.tsx` é da tela de Bens
> ([#7035](https://github.com/wagnerra23/oimpresso.com/pull/7035)), que a fundou primeiro — os
> casos dele moram em `Bens.casos.md`. Este painel apenas a consome com `active="dashboard"`.

---

## UC-PAT-01 · Vejo o patrimônio da minha empresa em um relance
- **Persona:** Wagner (gestor) — quer saber quanto a empresa tem parado e o que está sem cobertura.
- **Aceite:** Dado um business com bens cadastrados · Quando abre `/asset/dashboard` · Então vê
  os 4 KPIs (patrimônio bruto · valor residual · alocados X de Y · garantia vencida ou vencendo),
  o "Resumo de hoje" e os 3 blocos de análise, com os números do **próprio** business.
- **Teste:** `e2e/patrimonio-index.spec.ts` — stub `test.fixme` citando `UC-PAT-01`.
- **Regressão que defende:** a tela migrar pra Inertia e perder um bloco no caminho.
- **Status: ⬜** — stub. Vira 🧪 quando a lane E2E executar (11 dos 15 specs do repo são `fixme`
  hoje; não afirmo cobertura que não tenho).

---

## UC-PAT-02 · Valor residual sem fonte mostra `—`, nunca `R$ 0,00`
- **Persona:** Eliana (financeiro) — precisa saber a diferença entre "não sobrou valor" e "não sei".
- **Aceite:** Dado que o sistema não calcula depreciação (`assets.depreciation` é gravada como
  texto livre e relida só pelo `edit.blade.php:71`) · Quando o painel renderiza com
  `valorResidual: null` · Então o card "Valor residual" mostra `—` e **nenhum valor em reais**, e
  a prosa do "Resumo de hoje" também não o inventa.
- **Teste:** `tests/js/patrimonio-painel-sem-fonte.test.tsx` — `describe('UC-PAT-02 …')`, 3 casos
  (o 1º é controle positivo do harness).
- **Regressão que defende:** trocar o traço por `0` "pra não ficar feio". `R$ 0,00` **afirma** que
  não há valor residual — e isso não é o que se sabe. A regra (linear ou SAC) é decisão [W]:
  RESÍDUO 6, dono `SPEC.md:96 US-ASSET-W01`.
  **Bite-test:** trocar por `brl(kpis.valorResidual ?? 0)` derruba este caso.
- **Status: 🧪** — o teste cita o UC e passa (6/6 no arquivo). Vira ✅ quando o manifesto
  `scripts/casos-test-results.json` receber o veredito, e ele só é publicado por run verde de
  `main` (`casos-results-publish.yml`) — não por afirmação minha (G-7).

---

## UC-PAT-03 · Custo de manutenção sem coluna mostra `—`, nunca zero
- **Persona:** Eliana — confere quanto se gastou mantendo o parque.
- **Aceite:** Dado que `asset_maintenances` não tem coluna de valor (o `additional_cost` mora em
  `asset_warranties` e é o custo da garantia) · Quando o painel lista manutenções em aberto ·
  Então cada linha e o total do ano mostram `—`, e o backend devolve `custo: null` (não `0`).
- **Teste:** `tests/js/patrimonio-painel-sem-fonte.test.tsx` — `describe('UC-PAT-03 …')`, 2 casos.
- **Regressão que defende:** somar `0` e apresentar como "custo de manutenção no ano" — que
  afirmaria que não se gastou nada. RESÍDUO 3, decisão [W].
  **Bite-test:** trocar por `brl(m.custo ?? 0)` derruba este caso.
- **Status: 🧪** — idem UC-PAT-02.

---

## UC-PAT-04 · Quantidade é decimal e o painel não arredonda
- **Persona:** Wagner — cadastra bem com quantidade fracionária (bobina, chapa, litro).
- **Aceite:** Dado `assets.quantity` `decimal(22,4)` · Quando o card "Alocados" renderiza
  `4,5 de 13,25` · Então os dois números aparecem com a fração intacta, em pt-BR.
- **Teste:** `tests/js/patrimonio-painel-sem-fonte.test.tsx` — `describe('UC-PAT-04 …')`, 1 caso.
- **Regressão que defende:** "consertar" os cards `0,00` do Blade truncando pra inteiro. Os cards
  somam **quantidade**, não contam registros — truncar perde dado real.
- **Status: 🧪** — idem UC-PAT-02.

---

## UC-PAT-05 · O selo da análise nomeia a categoria de MAIOR valor, não a primeira da lista
- **Persona:** Wagner (gestor) — quer ler de relance onde o patrimônio está concentrado, sem
  somar as barras de cabeça.
- **Aceite:** Dado um business cujo bem de maior valor **não** é o primeiro item de
  `porCategoria` · Quando o card "Patrimônio por categoria" renderiza · Então o selo mostra o
  percentual **da categoria de maior valor** sobre o patrimônio bruto, e nada aparece quando o
  denominador ainda não chegou (`kpis` é prop deferida).
- **Teste:** `tests/js/patrimonio-painel-forma.test.tsx` — `describe('UC-PAT-05 …')`, 4 casos
  (o 1º é controle positivo do harness).
- **Regressão que defende:** ler `porCategoria[0]` em vez de derivar o máximo. Hoje o servidor
  ordena por valor desc (`AssetController::painelPorCategoria`, `orderByDesc('valor')`), então
  os dois coincidem — e é exatamente por isso que o defeito passaria despercebido: o selo
  continua **aparecendo**, com a categoria errada, no dia em que a ordenação mudar. Percentual
  errado é pior que percentual nenhum, porque tem cara de apurado.
  **Bite-test (provado 2026-09-09):** trocar o `reduce` por `porCategoria[0]` derruba o caso 2;
  remover a guarda `&& kpis?.bruto` derruba o caso 3 (o selo sai `Infinity% em …`).
- **Status: 🧪** — idem UC-PAT-02.

---

## UC-PAT-06 · O ícone do "Resumo de hoje" resolve no lucide, nunca no fallback
- **Persona:** Larissa (opera a tela) — o painel abre com o mesmo vocabulário visual das outras
  seções, não com uma bola vazia onde deveria haver um ícone.
- **Aceite:** Dado que `Components/Icon` resolve o ícone **por string** · Quando o bloco
  `data-contract="resumo"` renderiza · Então o `<svg>` sai como `lucide-calendar` e **não** como
  `lucide-circle`.
- **Teste:** `tests/js/patrimonio-painel-forma.test.tsx` — `describe('UC-PAT-06 …')`, 1 caso.
- **Regressão que defende:** o nome do ícone errar e ninguém ver. O `Icon` aceita qualquer
  string e cai em `Circle` quando o nome não existe no lucide (`Components/Icon.tsx:29`) — o TS
  **não** valida, o componente faz cast. tsc verde, eslint verde, build verde, e a tela mostra
  uma bola. Foi o bug de 2026-05-07 ([#184](https://github.com/wagnerra23/oimpresso.com/pull/184)),
  que atingiu todas as telas Inertia de uma vez.
  **Bite-test (provado 2026-09-09):** trocar `name="calendar"` por um nome inexistente derruba
  o caso — `lucide-circle` no lugar de `lucide-calendar`.
- **Status: 🧪** — idem UC-PAT-02.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Chego na tela pelo menu, sem digitar URL: a camada de ALCANCE
  (rota → permission → menu → pacote) é a única do ciclo que não é código React, e nenhum gate a
  cobre. A entry existe no `DataController:109` desde antes desta tela; verificar é smoke em
  runtime. Sem id até ter teste — id sem teste é órfão (G-2).
- **[BACKLOG]** Bem sem registro de garantia entra em "Sem garantia", nunca em "Vencida"
  (charter R3) — o `CASE` de `painelGarantia()` já separa os 4 baldes; falta teste de banco.
- **[BACKLOG]** Bem de outro business não entra no patrimônio bruto (ADR 0093) — as 6 consultas
  filtram `business_id`, e a de garantia entra por `join` com `assets` porque `asset_warranties`
  não tem a coluna; falta o teste cross-tenant (biz 98 × 99).
- **[BACKLOG]** `painelKpis()` devolve `null` (não `0`) em `valorResidual` — hoje o `null` é
  fixture do teste de tela; provar o contrato do controller exige
  `Modules/AssetManagement/Tests/`, fora do prefixo desta thread.
- **[BACKLOG]** As 5 props caras são `Inertia::defer` — o primeiro paint não espera agregação.

## Trilha do tempo
- 2026-09-08 · [CC] carimbado por `criar-tela.mjs` — trio nascido junto. Refs: UI-0013 · ADR 0264 G-1/G-2.
- 2026-09-08 · [CL] thread 07 do playbook: UCs reais escritos, ids `UC-INDEX-*` → `UC-PAT-*`.
- 2026-09-08 · [CL] pós-merge do [#7035](https://github.com/wagnerra23/oimpresso.com/pull/7035): os
  3 UCs que cobriam a sub-navegação **saíram** — ela é da tela de Bens, que a fundou primeiro, e
  manter caso aqui criaria um segundo dono do mesmo contrato. Entraram os 3 que são desta tela
  (número sem fonte × 2, decimal × 1), cobertos por
  `tests/js/patrimonio-painel-sem-fonte.test.tsx` (6 casos, bite-test em 2 mutações).
- 2026-09-09 · [CL] os dois detalhes de FORMA que faltavam pro painel espelhar o protótipo
  (ícone do resumo · selo da categoria dominante) entraram com UC próprio — **UC-PAT-05** e
  **UC-PAT-06**, em `tests/js/patrimonio-painel-forma.test.tsx` (5 casos, bite-test em 3
  mutações). Arquivo separado do irmão de propósito: aquele defende contrato de **dado**
  (número sem fonte mostra `—`), estes defendem **forma**. O contador da lane subiu de
  60 arquivos/702 testes para 61/707 — a prova de que o arquivo novo entrou não é o nome
  no log, é o delta (§5 2026-08-02).
