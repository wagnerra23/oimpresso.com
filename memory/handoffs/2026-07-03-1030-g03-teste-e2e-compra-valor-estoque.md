---
date: "2026-07-03"
time: "10:30 BRT"
slug: g03-teste-e2e-compra-valor-estoque
tldr: "Fechado o G-03/C04 da CAPTERRA Compras: teste E2E REAL (não source-grep) que prova valor+linhas+estoque numa compra grade tam×cor via POST /purchases. Validado verde no CT 100 (48 asserts), PR #3722 merged. Follow-ups (lane CI MySQL + BRIEFING/CAPTERRA) já em sessão paralela Onda 2.1 — NÃO duplicar."
prs: [3722]
related_adrs: [0093-multi-tenant-isolation-tier-0, 0101-tests-business-id-1-nunca-cliente, 0264-governanca-executavel-trio-dominio-e2e]
next_steps:
  - "Deixar lane CI MySQL do Compras (PR #3723) e refresh CAPTERRA/BRIEFING (branches Onda 2.1) com a sessão paralela — não tocar os mesmos arquivos"
  - "Se a lane #3723 ficar vermelha no meu teste: conferir subscription do biz=1 no seed pest-mysql-setup (store() bail expiredResponse)"
---

# G-03 — Teste E2E cálculo valor+estoque no fluxo de compra

## Estado MCP no momento do fechamento
- **cycle:** nenhum ATIVO em COPI (cycles-active vazio)
- **my-work @wagner:** 30 tasks (8 review, 8 blocked, 14 todo) — nenhuma era este G-03 (trabalho novo, veio da CAPTERRA-FICHA)
- **ADRs relevantes:** [0264](../decisions/0264-governanca-executavel-trio-dominio-e2e.md) (trio+E2E viram gates), [0318](../decisions/0318-ragas-eval-real-mata-tautologia-ct100-staging.md) (mata answer=ground_truth) — mesma família anti-tautologia deste teste
- **Sessões paralelas confirmadas (whats-active via branches):** `ci-compras-mysql-lane` (#3723), `compras-briefing-refresh`, `compras-backlog-onda-2.1` (#3717), `handoff-onda-2.1-compras` (#3719) — outra sessão é dona da Onda 2.1 Compras

## O que aconteceu
A CAPTERRA-FICHA Compras (gap **G-03 / cap C04**) descobriu que NENHUM teste provava que uma compra persiste custo/total/estoque corretos — os "hardening tests" (`GapsHardeningTest`/`GapsP1HardeningTest` + parte `store()` do `PurchaseGradeMatrixTest`) são `file_get_contents`+`str_contains` no source: tautológicos, o mesmo anti-padrão de [proibicoes §5](../proibicoes.md) que mordeu o Sells (incidente `num_uf` R$ ×100k).

Criei um teste **comportamental** que submete uma compra REAL (produto `variable` grade tam×cor 2×2 + frete + desconto% + imposto) via `POST /purchases` → `PurchaseController::store` → `ProductUtil::createOrUpdatePurchaseLines`+`updateProductQuantity`, ancorado em CONTRATO (SPEC US-COM-005 + regra-mestre valor/estoque), não na implementação (esperados computados à mão). Assere: (a) `transactions.{final_total,total_before_tax,tax,discount,shipping}` por 2 caminhos independentes + guard anti-`×100`; (b) cada `purchase_line` `qty×purchase_price` por `variation_id`; (c) `variation_location_details.qty_available` += qty por variação/local; + grade endpoint `GET /purchases/grade-matrix` mapeando célula→`variation_id`.

**Smoke real no CT 100** (`oimpresso-staging`, MySQL, biz=1 dogfood): `2 passed (48 assertions)`. Não foi "skipa e mente".

## Artefatos gerados
- `Modules/Compras/Tests/Feature/PurchaseCalculoValorEstoqueE2ETest.php` (+394 linhas, 1 arquivo) — **PR #3722 MERGED** (57 checks pass, squash → main `0f826c5fda`)

## Persistência (3 canais)
- **git:** PR #3722 merged em main ✅
- **MCP:** este handoff propaga via webhook GitHub→MCP (~2min pós-push)
- **BRIEFING/CAPTERRA:** NÃO tocados aqui — sessão paralela Onda 2.1 é dona (evitar colisão)

## Próximos passos pra retomar
Nada a fazer neste escopo — está fechado e merged. Os follow-ups (lane CI + docs) são da sessão paralela. Se retomar: `gh pr checks 3723` pra ver se meu teste passa na lane MySQL nova.

## Lições catalogadas
- **5 problemas que só o smoke real pegou** (prova de por que source-grep mente): middleware `CheckUserLogin` exige `user_type='user'`+`allow_login=1`; controller exige role `Admin#{biz}` (Gate::before do UPos, `AuthServiceProvider`); `createOrUpdatePaymentLines(null)` estoura `foreach` (TransactionUtil:731) → mandar `payment: []`; `roles.business_id` é NOT NULL+FK; e Pest `toHaveKey($k,$msg)` — o 2º arg é VALOR esperado, não mensagem.
- **`num_uf` hoje é currency-agnostic** (heurística pt-BR pós-incidente Sells) — qty inteiro pequeno + preço 2-casas-ponto parseiam determinístico independente da config de moeda.
- **Sessões paralelas na mesma base:** antes de "melhorar" um módulo, `git for-each-ref` + `gh pr list` por keyword — a Onda 2.1 Compras já tinha lane CI (#3723) e refresh de docs em voo. Não duplicar.
- **Pegadinha MSYS:** `MSYS_NO_PATHCONV=1` (usado pra revspec `origin/main:path`) faz `/d/...` NÃO virar `D:/` → `git worktree add` criou pasta fantasma `D:\d\...`. Usar sem essa flag pra paths normais.

## Pointers detalhados
- CAPTERRA-FICHA G-03/C04: `memory/requisitos/Compras/CAPTERRA-FICHA.md` §6 + §8
- SPEC US-COM-005: `memory/requisitos/Compras/SPEC.md`
- Regra-mestre valor/estoque: `memory/proibicoes.md` §CÁLCULO DE VALOR ou ESTOQUE
