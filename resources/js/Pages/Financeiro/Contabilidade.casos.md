---
casos: Financeiro/Contabilidade — carimbado do Padrão de Tela
irmaos: Contabilidade.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-11-03"
---

# Casos de Uso & Aceite — Financeiro/Contabilidade

> Nascido de `criar-tela.mjs`. **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão. O stub `e2e/financeiro-contabilidade.spec.ts` já cita `UC-CONTAB-01`.

---

## UC-CONTAB-00 · Chego na tela pelo menu, sem digitar URL
- **Persona:** Larissa — abre o sistema e encontra a tela pelo sidebar.
- **Aceite:** Dado usuário com a permission `financeiro.relatorios.view` · Quando abre o Financeiro ·
  Então o item existe no sidebar e leva a `/financeiro/exportacao-contabil` (200, sem digitar URL).
- **Teste:** `e2e/financeiro-contabilidade.spec.ts` — stub `test.fixme` citando `UC-CONTAB-00` (ghost "Contabilidade" em `DataController::modifyAdminMenu`).
- **Regressão que defende:** a tela responder 200 e ninguém alcançar — `modifyAdminMenu()`
  no-op passa por todo gate de código, porque um método vazio é sintaticamente perfeito.
- **Status: ⬜**

---

## UC-CONTAB-01 · Baixo o arquivo do mês para o contador
- **Persona:** Eliana[E] (financeiro) — fecha o mês e manda os lançamentos ao contador.
- **Aceite:** Dado o de-para completo para as contas usadas na competência · Quando escolhe competência e sistema e clica "Gerar arquivo do Domínio" · Então o navegador baixa `lancamentos_dominio_AAAAMM.txt` e a tela diz onde importar no Domínio.
- **Teste:** `e2e/financeiro-contabilidade.spec.ts` — stub `test.fixme` citando `UC-CONTAB-01`. O arquivo em si está provado no backend por UC-EXPC-01..03.
- **Regressão que defende:** o botão não baixar nada, ou baixar o arquivo de outra competência.
- **Status: ⬜** — stub; vira 🧪/✅ quando o teste executar e passar.

---

## UC-EXPC-06 · A tela lista o de-para só da minha empresa
- **Persona:** Eliana[E].
- **Aceite:** Dado contas e plano de contas de duas empresas · Quando abre a tela · Então o de-para traz as origens da empresa da sessão (com o código já gravado, juros e descontos uma vez cada) e nenhuma da outra.
- **Teste:** `Modules/Financeiro/Tests/Feature/ExportacaoContabilContratoTest.php` — `UC-EXPC-06`.
- **Regressão que defende:** vazamento cross-tenant (Tier 0) no de-para.
- **Status: ⬜** — até o CI do PR rodar.

---

## UC-EXPC-07 · Salvar grava, troca e apaga o código
- **Persona:** Eliana[E].
- **Aceite:** Dado um código gravado · Quando salva com o campo vazio numa conta e preenchido em outras · Então a vazia sai da tabela e as preenchidas ficam com o código novo.
- **Teste:** `ExportacaoContabilContratoTest.php` — `UC-EXPC-07`.
- **Regressão que defende:** código antigo ficar depois de apagado (arquivo sai com conta errada).
- **Status: ⬜** — até o CI do PR rodar.

---

## UC-EXPC-08 · Origem de outra empresa é recusada
- **Persona:** quem forja o formulário.
- **Aceite:** Dado um plano de contas de outra empresa no envio · Quando salva · Então volta erro naquela linha e nada é gravado, nem as linhas válidas do mesmo envio.
- **Teste:** `ExportacaoContabilContratoTest.php` — `UC-EXPC-08`.
- **Regressão que defende:** gravar de-para apontando para conta de outra empresa (Tier 0).
- **Status: ⬜** — até o CI do PR rodar.

---

## UC-EXPC-09 · Sem permissão, sem tela
- **Persona:** usuário sem `financeiro.relatorios.view`.
- **Aceite:** Quando abre `/financeiro/exportacao-contabil` · Então 403.
- **Teste:** `ExportacaoContabilContratoTest.php` — `UC-EXPC-09`.
- **Regressão que defende:** tela de exportação aberta a quem não vê relatório.
- **Status: ⬜** — até o CI do PR rodar.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Resumo do mês (baixas, lançamentos, movimento no banco) e amostra das primeiras linhas — valor, exige prova dupla.
- **[BACKLOG]** Aba "Importar títulos" (US-FIN-034) — sem backend; decisão [W].

## Trilha do tempo
- 2026-11-03 · [CC] carimbado por criar-tela.mjs — trio nascido junto (charter + casos + teste). Refs: UI-0013 · ADR 0264 G-1/G-2.
