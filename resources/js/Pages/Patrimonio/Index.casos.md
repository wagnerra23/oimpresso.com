---
casos: Patrimonio/Index — Painel do Patrimônio
irmaos: Index.charter.md (lei) · memory/requisitos/AssetManagement/RUNBOOK-patrimonio-index.md
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-09-08"
---

# Casos de Uso & Aceite — Patrimonio/Index

> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão.
>
> Os ids nascem `UC-PAT-*`, não `UC-INDEX-*` como o `criar-tela.mjs` carimba: `INDEX` colidiria
> com toda outra tela chamada `Index.tsx`, e id de UC é global no `casos-gate`.

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

## UC-PAT-02 · Toda aba da barra leva a uma rota que existe
- **Persona:** qualquer usuário do módulo — clica numa aba e chega em algum lugar.
- **Aceite:** Dado a barra de abas do Patrimônio · Quando qualquer aba navegável é renderizada ·
  Então o `href` dela está entre as rotas reais de `Modules/AssetManagement/Routes/web.php`, e as
  5 primeiras são as do protótipo, nesta ordem: Painel · Bens · Alocações · Manutenções · Configurações.
- **Teste:** `tests/patrimonioSubNav.spec.ts` — `describe('UC-PAT-02 …')`, 4 casos.
- **Regressão que defende:** inventar `href` plausível pra uma aba do protótipo que o backend não
  tem — daria 404 e pareceria bug da tela. **Bite-test:** adicionar
  `{ key: 'garantias', href: '/asset/garantias' }` derruba 2 casos (`href inexistente`).
- **Status: 🧪** — o teste cita o UC e passa localmente (9/9). Vira ✅ quando o
  manifesto `scripts/casos-test-results.json` receber o veredito, e ele só é publicado por run
  verde de `main` (`casos-results-publish.yml`) — não por afirmação minha (G-7).

---

## UC-PAT-03 · Quem não tem o módulo não vê a barra de abas
- **Persona:** usuário de um business que não assinou o Patrimônio.
- **Aceite:** Dado um `shell.menu` sem a entry do módulo (pacote não assinado **ou** usuário sem
  nenhuma permission `asset.*` — é o `DataController:109` que decide) · Quando a barra é montada ·
  Então ela não renderiza nada. E um ghost `dashboard` de **outro** módulo não a faz renderizar.
- **Teste:** `tests/patrimonioSubNav.spec.ts` — `describe('UC-PAT-03 …')`, 3 casos.
- **Regressão que defende:** vazamento de navegação cross-módulo/cross-tenant (ADR 0093 Tier 0).
  **Bite-test:** trocar o predicado por `g.key === 'dashboard'` (sem o `/asset/`) derruba o caso
  do outro módulo.
- **Status: 🧪** — o teste cita o UC e passa localmente (9/9). Vira ✅ quando o
  manifesto `scripts/casos-test-results.json` receber o veredito, e ele só é publicado por run
  verde de `main` (`casos-results-publish.yml`) — não por afirmação minha (G-7).

---

## UC-PAT-04 · Garantias e Auditoria aparecem, mas não prometem o que não existe
- **Persona:** Wagner — vê no protótipo 7 abas e quer saber por que duas não abrem.
- **Aceite:** Dado que não há rota de Garantias nem de Auditoria no backend · Quando a barra é
  montada · Então as duas aparecem no `⋯ Mais` **inertes** (sem `href`), cada uma com um `title`
  dizendo qual decisão falta (`D-GARANTIAS` / `D-AUDITORIA`) — e **não** entram entre as abas
  navegáveis.
- **Teste:** `tests/patrimonioSubNav.spec.ts` — `describe('UC-PAT-04 …')`, 2 casos.
- **Regressão que defende:** alguém "completar" a barra criando as rotas, ou apagar as duas e a
  próxima sessão achar que o protótipo tinha 5 abas.
- **Status: 🧪** — o teste cita o UC e passa localmente (9/9). Vira ✅ quando o
  manifesto `scripts/casos-test-results.json` receber o veredito, e ele só é publicado por run
  verde de `main` (`casos-results-publish.yml`) — não por afirmação minha (G-7).

---

## UC-PAT-05 · Número sem fonte mostra `—`, nunca zero e nunca inventado
- **Persona:** Eliana (financeiro) — precisa saber a diferença entre "não gastei" e "não sei".
- **Aceite:** Dado que o sistema não calcula depreciação (`assets.depreciation` é gravada e nunca
  lida para conta) e não tem coluna de custo em `asset_maintenances` · Quando o painel renderiza ·
  Então o KPI "Valor residual" e o total "custo de manutenção no ano" mostram `—`, e o backend
  devolve `null` (não `0`) nesses dois campos.
- **Regressão que defende:** preencher o buraco com `0`, com `rand()` ou com uma fórmula de
  depreciação escolhida pelo agente. A regra (linear ou SAC) é decisão [W] — RESÍDUO 6, dono
  `SPEC.md:96 US-ASSET-W01`. Ver charter §Non-Goals.
- **Status: ⬜** — hoje defendido só pela leitura do código (`painelKpis()` devolve
  `'valorResidual' => null` literal; `painelManutencoes()` devolve `'custo' => null`). Um teste
  que o defenda exige exercitar o controller, e `Modules/AssetManagement/Tests/` está fora do
  prefixo desta thread. Declarado como resíduo no `_saida-07.md`, não como coberto.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Chego na tela pelo menu, sem digitar URL: a camada de ALCANCE
  (rota → permission → menu → pacote) é a única do ciclo que não é código React, e nenhum
  gate a cobre. A entry existe no `DataController:109` desde antes desta tela; verificar é
  smoke em runtime. Sem id até ter teste — id sem teste é órfão (G-2).

- **[BACKLOG]** Bem sem registro de garantia entra em "Sem garantia", nunca em "Vencida"
  (charter R3) — o `CASE` de `painelGarantia()` já separa os 4 baldes; falta teste de banco.
- **[BACKLOG]** Bem de outro business não entra no patrimônio bruto (ADR 0093) — as 6 consultas
  filtram `business_id`, e a de garantia entra por `join` com `assets` porque
  `asset_warranties` não tem a coluna; falta o teste cross-tenant (biz 98 × 99).
- **[BACKLOG]** As 5 props caras são `Inertia::defer` — o primeiro paint não espera agregação.

## Trilha do tempo
- 2026-09-08 · [CC] carimbado por `criar-tela.mjs` — trio nascido junto. Refs: UI-0013 · ADR 0264 G-1/G-2.
- 2026-09-08 · [CL] thread 07 do playbook: UCs reais escritos, ids `UC-INDEX-*` → `UC-PAT-*`,
  3 UCs cobertos por `tests/patrimonioSubNav.spec.ts` (9 casos, bite-test em 2 mutações).
