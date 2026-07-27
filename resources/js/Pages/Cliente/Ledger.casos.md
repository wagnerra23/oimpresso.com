---
id: resources-js-pages-cliente-ledger-casos
casos: Extrato financeiro do cliente · /contacts/ledger
irmaos: Ledger.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o SALDO do cliente ("quem me deve quanto") não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-07-08"
---

# Casos de Uso & Aceite — Extrato financeiro do cliente (Ledger)

> Primeiro trio aceso do módulo Cliente (casos_coverage era 0%). Tela que toca **VALOR** (saldo débito/crédito) — cada UC é ancorado no dente de cálculo `CalculoValorClienteTest`, que caracteriza e trava o saldo ATUAL sob a REGRA MESTRE (dupla confirmação por dois caminhos). Derivam do SDD [§6.2 CU-CLI-08/09](../../../../memory/requisitos/Cliente/SDD-cadastro-cliente-v1.0.md).
>
> ⚖️ **Onde estes UC rodam, e com que força** (medido 2026-07-27): lane `PHP / Pest (Cliente · MySQL)` — [`cliente-pest.yml`](../../../../.github/workflows/cliente-pest.yml), criada 2026-07-27, **advisory** (não está em [`required-checks-baseline.json`](../../../../governance/required-checks-baseline.json): reprova visível, **não bloqueia merge**). **Antes dela** o `CalculoValorClienteTest` rodava só no full-suite nightly do CT 100 e em **nenhuma** lane de PR — apesar de ser o dente `[V0]` do saldo do cliente. Onde as linhas abaixo dizem "passa no CI", leia-se **passava no nightly**. A frase "primeira lane acesa" da redação original era figurada; **lane de CI mesmo, só agora**.
>
> **Status:** ✅ passa (com prova no manifesto G-7) · 🧪 em teste/prova (teste cita o UC mas manifesto não regravado) · ⬜ não verificado · ❌ quebrou.

---

## UC-CLED-01 · Ver o saldo devedor do cliente já contando a devolução
- **Persona:** Larissa / Kamila — "quanto esse cliente me deve?" olhando o extrato, sem montar planilha.
- **Aceite:** Dado um cliente com venda finalizada + uma devolução (`is_return=1`) · Quando o saldo é computado · Então o número reflete a devolução com o **sinal certo** (a devolução SOBE o saldo devedor), sem dupla contagem — e uma implementação que ignorasse `is_return` daria um número diferente (red).
- **Teste:** `tests/Feature/Calculo/CalculoValorClienteTest.php` — `get_contact_due_golden_com_devolucao` + `discriminacao_versao_que_ignora_is_return_seria_red` (Pest, CT100, harness G-2).
- **Regressão que defende:** o incidente `num_uf` (2026-06-05, saldo inflado ~×100k) tornou concreto o risco de o saldo do cliente sair errado. Este UC trava o número golden.
- **Status: 🧪** — teste de cálculo escrito e passa no CI; volta a ✅ quando `casos:results` regravar o manifesto por-UC (G-7, sem fingir prova).

---

## UC-CLED-02 · A devolução sobe o saldo pelo valor exato, nunca em dobro
- **Persona:** Larissa — devolveu uma peça de R$ X; o saldo tem que subir exatamente X, não 2X nem descer.
- **Aceite:** Dado um saldo inicial · Quando entra uma devolução de valor X · Então o saldo devedor sobe em **exatamente +X** (property, para qualquer X).
- **Teste:** `tests/Feature/Calculo/CalculoValorClienteTest.php` — `property_devolucao_sobe_saldo_em_exatamente_o_valor_devolvido` (Pest property, CT100, harness G-2).
- **Status: 🧪** — property escrita e passa; volta a ✅ com o manifesto regravado.

---

## UC-CLED-03 · O saldo do resumo bate com o saldo do extrato (dupla confirmação)
- **Persona:** Larissa / Guilherme — o número que aparece na listagem (coluna "devendo") tem que ser o MESMO que fecha no rodapé do extrato; senão não dá pra confiar.
- **Aceite:** Dado o mesmo cliente · Quando comparo `Util::getContactDue` (resumo da listagem) com `TransactionUtil::getLedgerDetails(...)['all_balance_due']` (rodapé do extrato) · Então os **dois caminhos independentes convergem** para o mesmo valor (REGRA MESTRE — dupla confirmação).
- **Teste:** `tests/Feature/Calculo/CalculoValorClienteTest.php` — `dupla_confirmacao_contact_due_bate_com_ledger_all_balance_due` (Pest, CT100, harness G-2).
- **Regressão que defende:** dois caminhos de cálculo (resumo vs extrato) com SQL próprio podem divergir silenciosamente; este UC torna a divergência uma falha de CI.
- **Status: 🧪** — teste escrito e passa; volta a ✅ com o manifesto regravado.

---

## UC-CLED-04 · O saldo de um cliente de outro tenant nunca entra no meu
- **Persona:** operador de qualquer negócio — o extrato só pode somar lançamentos do próprio `business_id` (Cliente é PII-heavy, Tier 0).
- **Aceite:** Dado um cliente com lançamentos e um cliente homônimo em OUTRO `business_id` · Quando computo o saldo do tenant corrente · Então o lançamento estrangeiro **nunca** entra na conta (isolamento multi-tenant é a única catraca de vazamento).
- **Teste:** `tests/Feature/Calculo/CalculoValorClienteTest.php` — `regua_multi_tenant_business_id_estrangeiro_zera_o_saldo` (Pest, CT100, harness G-2).
- **Regressão que defende:** vazamento cross-tenant no cálculo de saldo (ADR 0093 Tier 0 IRREVOGÁVEL).
- **Status: 🧪** — régua multi-tenant escrita e passa; volta a ✅ com o manifesto regravado.

---

## UC-CLED-05 · Parcelar um pagamento não muda o saldo (aditividade)
- **Persona:** Larissa — receber R$ X de uma vez ou em duas parcelas de X/2 tem que dar o mesmo saldo final.
- **Aceite:** Dado um pagamento de valor X · Quando ele é dividido em parcelas que somam X · Então o saldo do cliente é **idêntico** ao do pagamento único (property de aditividade).
- **Teste:** `tests/Feature/Calculo/CalculoValorClienteTest.php` — `property_saldo_e_aditivo_no_split_de_pagamentos` (Pest property, CT100, harness G-2).
- **Status: 🧪** — property escrita e passa; volta a ✅ com o manifesto regravado.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado sem teste citando o id = órfão. Itens SEM token de UC até existir teste real.

- **[BACKLOG] Filtros de data/formato aplicam via full-page reload preservando o deep-link** — exige spec Playwright (harness e2e-gate) que carregue `/contacts/ledger?...` e assere URL + linhas.
- **[BACKLOG] Export PDF abre em nova aba / Excel baixa** — exige stub de download no harness (mesmo padrão do caso de export da OficinaAuto). ⚠️ O id daquele UC foi **removido desta linha em 2026-07-27**: o `requisitos-status.mjs` extrai UC varrendo o TEXTO INTEIRO do `casos.md`, então citar o id de um UC de **outro módulo** em prosa o fazia entrar na contagem do Cliente (22 → 23) e ser dado como "coberto por teste" porque algum teste da Oficina contém a string. Falso-positivo do gerador, reportado ao dono do script; aqui só tirei o gatilho.
- **[BACKLOG] Os 3 KPI cards (débitos, créditos, saldo) somam a tabela** — render test da `Ledger.tsx` ancorado no payload de `getLedgerDetails`.

## Rastreabilidade (UC → CU do SDD → US do SPEC)

| UC | CU (SDD §6) | US (SPEC) |
|---|---|---|
| UC-CLED-01 | CU-CLI-08 | — |
| UC-CLED-02 | CU-CLI-08 | — |
| UC-CLED-03 | CU-CLI-08 | — |
| UC-CLED-04 | CU-CLI-09 · CU-CLI-11 | — |
| UC-CLED-05 | CU-CLI-08 | — |

> Coluna US vazia de propósito: o saldo do cliente (`getContactDue`/`getLedgerDetails`) **não tem US no SPEC** — o dente nasceu como onda de cálculo, não como US. É registro honesto de que a régua de valor mais crítica do módulo está fora do escopo declarado.

## Como rodar a suíte
1. **Pest (cálculo):** `docker exec oimpresso-staging php artisan test --filter=CalculoValorClienteTest` no CT100 (nunca local/Hostinger — proibições Tier 0).
2. **Manifesto:** `npm run casos:results` regrava `scripts/casos-test-results.json` (merge per-UC) → 🧪 vira ✅ quando o veredito real bate.
3. **Cadência:** rodar ao fim de toda mexida em `Ledger.tsx` ou nos métodos de saldo (`getContactDue`/`getLedgerDetails`). UC ❌ = regressão → lição + conserto.

## Trilha do tempo
- 2026-07-27 · [CC] chip S-Cliente do passo 5 (agent `sdd-from-source`). **Nenhum UC reescrito** — o dente `[V0]` segue intacto (mexer em `getContactDue`/`getLedgerDetails` é US separada sob REGRA MESTRE). Correção factual: o dente de valor do saldo do cliente rodava **só no nightly**, em nenhuma lane de PR; lane criada agora, **advisory**, com a força declarada. + ponteiro pro SDD §6.2. Refs: [SDD Cliente](../../../../memory/requisitos/Cliente/SDD-cadastro-cliente-v1.0.md) · [ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md).
- 2026-07-08 · [CC] criado — 1ª lane do módulo Cliente (casos_coverage 0% → trio Ledger fechado). 5 UCs ancorados no dente `CalculoValorClienteTest` (getContactDue + getLedgerDetails all_balance_due + dupla confirmação + multi-tenant), parte de "ligar a máquina do protocolo" (SDD anti-fachada). Statuses 🧪 (teste cita o UC e passa; manifesto per-UC regrava depois). Refs: [ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md) G-1/G-2 · REGRA MESTRE cálculo de valor (proibicoes.md).
