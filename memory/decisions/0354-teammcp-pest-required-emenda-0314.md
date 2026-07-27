---
slug: 0354-teammcp-pest-required-emenda-0314
number: 354
title: "Emenda à 0314 — teammcp-pest promovido a REQUIRED (as rotas /forja executam de verdade, não pulam)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-27"
module: governance
quarter: 2026-Q3
tags: [governance, gates, ci, required, pest, mysql, teammcp, forja, branch-protection, falsa-cobertura]
supersedes: []
superseded_by: []
related: [0314-poda-gates-onda-2-lei-fusoes, 0327-anchor-content-required-emenda-0314, 0336-gates-design-promocao-por-mordida-provada-emenda-0314, 0347-deadlink-gate-required-emenda-0314, 0264-governanca-executavel-trio-dominio-e2e, 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura, 0093-multi-tenant-isolation-tier-0, 0101-tests-business-id-1-nunca-cliente]
---

# ADR 0354 — Emenda à 0314: `teammcp-pest` promovido a REQUIRED

## Contexto

A [ADR 0314](0314-poda-gates-onda-2-lei-fusoes.md) fixou **"required = só Tier-0"**. A [0327](0327-anchor-content-required-emenda-0314.md) abriu a 1ª exceção formal, a [0347](0347-deadlink-gate-required-emenda-0314.md) a 2ª — ambas pelo mesmo processo: emenda ADR reabrindo a 0314 pro item + flip [W]. Esta é a **3ª**.

**Sinal de custo (medido em 2026-07-27, [PR #4887](https://github.com/wagnerra23/oimpresso.com/pull/4887)):** `Modules/TeamMcp/Tests/Feature/ForjaRoutesSmokeTest.php` existia desde a Onda Forja e **nunca executou uma vez**. Contado: `git ls-files "Modules/TeamMcp/Tests/**Test.php"` = **26** arquivos; na lane sqlite = **7**; `grep -c TeamMcp .github/workflows/modules-pest.yml` = **0** (o módulo não estava no matrix, e aquela lane nem emite JUnit). Rodado à mão no CT 100 (`DB_CONNECTION=mysql`): **7 failed / 5 passed** — rota `forja.saude` inexistente + dataset associativo contra `it()` de 2 argumentos. O happy-path (`assertStatus(200)` + `assertInertia`) **jamais rodou**, e `UC-FORJA-01`/`UC-FORJA-07` eram órfãos no G-2 ([ADR 0264](0264-governanca-executavel-trio-dominio-e2e.md)).

O mecanismo do apodrecimento é específico e reincidente na casa: **em sqlite a stack UltimatePOS só `markTestSkipped`** (os middlewares exigem schema MySQL com `business`/`users`/`permissions`). Skip vira veredito `skip` no manifesto por-UC — nunca `pass`. Ou seja: sem lane MySQL, o teste de rota é **decorativo por construção**, e o painel do G-7 fica mudo pro módulo. É a mesma doença que a [lane do Arquivos](../../.github/workflows/arquivos-pest.yml) documenta ("skip no sqlite = verde mente") e que a US-COPI-138 nomeou ("guard que nenhum workflow executa = mentira").

**Precedente de classe:** `PHP / Pest (Financeiro · MySQL)` e `PHP / Pest (NfeBrasil · MySQL)` **já são required**. Lane pest MySQL como required é prática estabelecida, não invenção.

## Decisão

`teammcp-pest` (context de CI **`PHP / Pest (TeamMcp · MySQL)`**) passa a **required** na branch protection de `main`, como exceção consciente à 0314.

**Justificativa que a régua Tier-0 não pesa:** o que a lane defende não é dinheiro/PII/fiscal — é a **existência de execução**. Advisory aqui recria exatamente a condição que produziu o defeito: um teste que ninguém roda envelhece quebrado sem sinal. A política vigente segue **"required = Tier-0 + exceções explicitamente autorizadas via emenda + flip [W]"**.

**Override de cadência (consciente, [W]):** a [0336](0336-gates-design-promocao-por-mordida-provada-emenda-0314.md) DR-2 pede **≥2 mordidas reais** antes do flip. Esta lane tem **ZERO** — nasceu em 2026-07-27 com uma única corrida verde (15 passed / 75 assertions). [W] autorizou promoção imediata ("Promova", 2026-07-27), mesmo padrão de override do [ADR 0347](0347-deadlink-gate-required-emenda-0314.md) ("promova", 2026-07-23) e do #3444. **Isto fica escrito porque é o risco residual real, não um detalhe:** promove-se por argumento estrutural, não por evidência de mordida.

Mitigações do residual:
- **(a) Blast radius contido** — `skip-as-pass` via `dorny/paths-filter`: só paga MySQL + migrate + Pest quando algo de `Modules/TeamMcp/**` muda; nos demais PRs conclui verde em segundos.
- **(b) Catraca de allowlist** — a lane roda **UM** arquivo (`ForjaRoutesSmokeTest.php`), o único com verde provado. Teste novo entra de um em um, depois de rodar verde (ratchet up), nunca em lote. Isso limita o que pode avermelhar.
- **(c) Gate de reversão** abaixo.

## Require-safe (pré-condições do flip)

1. **Sem paths-filter no `pull_request`** — o workflow roda em TODO PR; um required com paths-filter travaria em "Expected — waiting". Já satisfeito por construção ([ADR 0271](0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md) onda 2).
2. **Nome não mente** — o job chama `PHP / Pest (TeamMcp · MySQL)`; nenhum texto do workflow afirma "advisory" (lição P14 / §5 2026-07-16: artefato não declara o próprio enforcement).
3. **Contexto com `·` U+00B7** — 13 dos 34 required já carregam esse caractere. O flip usa o procedimento anti-mojibake obrigatório: arquivo UTF-8 **sem BOM** + `gh api --input`, **nunca** payload inline no shell Windows; validação pós-PUT com `node scripts/governance/protection-drift.mjs` (contagem via GET não prova nada — mojibake mantém a contagem). Ver [`RUNBOOK-branch-protection.md`](../requisitos/Infra/RUNBOOK-branch-protection.md).
4. **Flip do vivo = ato [W]** (R10 / [ADR 0275](0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md) §5). **NÃO é feito por agente.** A entrada em `governance/required-checks-baseline.json` vem em PR **pós-flip** (precedente: #4700 = ADR+gate, flip [W], #4710 = baseline). Registrar o baseline antes do flip criaria o drift que o `protection-drift.mjs` existe pra pegar.

## Gate de reversão

Se a lane produzir **falso-positivo** que trave PR legítimo, a demoção a advisory é imediata via PR removendo o context de `required-checks-baseline.json` (+ flip [W] no vivo) + nota nesta ADR — sem ADR-mãe nova (a 0314 permanece a lei).

Os dois modos de falha plausíveis, nomeados pra não virarem surpresa:
- **Drift do seed** — o happy-path depende de `.github/actions/pest-mysql-setup` semear um usuário **não-admin** em biz=1. O `Gate::before` (`AuthServiceProvider`) libera qualquer ability pra quem tem `Admin#{business_id}`; se o seed passar a criar admin, o caso 403 do `UC-FORJA-07` quebra — e quebra **certo** (o teste está medindo o que promete).
- **Infra do service MySQL** — flake do container. Mitigado pelo `--health-retries=20` da action.

## Consequências

- O teste de rota do Forja deixa de poder envelhecer quebrado em silêncio: PR que quebrar `/forja` reprova merge.
- O JUnit do TeamMcp chega ao `casos-results-publish` (elo do G-7): o Status de `UC-FORJA-01`/`07` passa a vir do veredito real, não de prosa.
- `promote_by: 2026-08-10` sai do `gates-registry.json` — a lane deixa de ser advisory com vencimento e vira required com gate de reversão. O teto da [ADR 0298](0298-teto-de-governanca-anti-proliferacao-gates.md) fica satisfeito pelo caminho previsto ("gate nasce required-ou-cron, nunca advisory eterno").
- **Não muda** a lane sqlite (`.github/ci-sqlite-pest.list`, +18 alvos do TeamMcp no #4887): lá o arquivo segue skip-as-pass, e o valor é outro — pegar erro de **binding** (o `DatasetArgumentsMismatch` que manteve o teste quebrado é lançado antes do guard de driver).
