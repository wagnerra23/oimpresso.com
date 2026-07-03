---
date: "2026-07-03"
time: "15:04 BRT"
slug: dente-calculo-oficinaauto
tldr: "Dente de cálculo da ordem de serviço OficinaAuto (TEST-ONLY, PR #3762 MERGED). Cobre o cálculo PRÓPRIO da OS — recalcularTotal/breakdownPorTipo/addItem/DviInspectionService::totalRecomendado — que estava defendido só por exemplos redondos, sem property/golden de precisão de centavo. 20 tests/76 asserts GREEN no CT100. Canary Martinho biz=164 LIVE intacto."
prs: [3762]
decided_by: [W]
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0101-tests-business-id-1-nunca-cliente
  - 0106-recalibracao-velocidade-fator-10x-ia-pair
  - 0171-oficinaauto-ativacao-piloto-martinho-faseada
  - 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
  - 0265-oficina-reparo-erradica-locacao
  - 0320-programa-ondas-regua-correcao
next_steps:
  - "Se algum somador da OS precisar mudar (ex US-OFICINA-027 recalc peça×qty + hora×horas), abrir US separada sob REGRA MESTRE — nunca no PR do teste."
---

# Handoff — Dente de cálculo da OS (OficinaAuto)

## Estado MCP no momento do fechamento

- `cycles-active`: **nenhum cycle ATIVO** em COPI (off-cycle).
- `my-work`: 30 tasks (@wagner) — 8 REVIEW, 8 BLOCKED, 14 TODO. Nenhuma era o dente (item do programa-ondas, `parent_plan`).
- Handoffs irmãos hoje (mesmo programa de dentes): [Fiscal](2026-07-03-1730-dente-calculo-fiscal-motor-tributario.md), [Produto](2026-07-03-1703-dente-calculo-produto.md), [RecurringBilling US-RB-056](2026-07-03-1245-us-rb-056-materializada.md).

## O que aconteceu

Wagner "destrave OficinaAuto" → executei o **dente de cálculo** (Onda 1.4 do Programa de Ondas) aplicado ao coração da **ordem de serviço** de reparo. Regra-mestre da sessão: **TEST-ONLY** — o canary Martinho (biz=164) está LIVE em prod ([ADR 0171](../decisions/0171-oficinaauto-ativacao-piloto-martinho-faseada.md)), então nada de cálculo foi tocado.

**Verificação de cobertura REAL (compare-não-duplica), lida em `origin/main`:** os testes existentes (`ServiceOrderItemTest`, `DviInspectionItemTest`, `ServiceOrderIndexItemsTotalTest`) provam "ele soma" com **exemplos redondos** (180,00 · 350,50 · 480,00) — que nunca exercem precisão de centavo. O indefeso era o ângulo **property + golden**. Não dupliquei os dentes de venda (#3695), financeiro (#3710) nem o fiscal (#3735): mirei o cálculo **próprio da OS**.

**Nota de honestidade:** a OS **não** roteia por `num_uf` (o Service casta `(float)` direto) — então o golden de "não inflar" é **sentinela de regressão futura** (pega o dia em que alguém rotear por parser pt-BR ou trocar `round` por strip), não a reprodução de um bug atual. Documentado no arquivo e no PR. Também: a OS **não tem campo de desconto** ([ADR 0194](../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md)/[0265](../decisions/0265-oficina-reparo-erradica-locacao.md)) — desconto entra como override de `valor_total` por item, depois somado.

## Artefatos gerados

- `tests/Feature/Calculo/CalculoValorOficinaAutoTest.php` (~489 linhas) — property conservação (`recalcularTotal == round(Σ valor_total,2)`, fuzzed seed fixa) + partição (`breakdownPorTipo`) + golden (0,10×3==0,30; `round(qty×unit,2)`; override que não infla) + property DVI (`totalRecomendado` filtra `{atenção,crítico}`) + discriminação RED (mutantes `floor`/`cast-int` que perdem centavo divergem do real) + guard Tier 0 cross-tenant.
- `memory/requisitos/OficinaAuto/ROADMAP.md` (+30 linhas) — nova seção "Onda 1.4 — Dente de cálculo (OS)" (encaixe T6: evolui o que existe, não doc paralelo, não interrompe o canary).

## Persistência

- **git:** PR [#3762](https://github.com/wagnerra23/oimpresso.com/pull/3762) squash-MERGED por [W] (sha `8d08c4a`), branch remoto deletado, worktree removido.
- **CI:** 70 checks verdes / 0 falha. Module Grades: OficinaAuto **79→80** (+1), zero regressão.
- **Evidência RED/GREEN:** CT100 staging (MySQL real) `20 passed (76 assertions)`. O próprio dente pegou **2 bugs de float na minha 1ª versão** do teste de discriminação (`floor(6.96×100)`→6,95; `abs(6.96−6.95) >= 0.01` falso em float) — prova de que a asserção morde.

## Próximos passos pra retomar

Nada aberto neste dente. Se surgir mudança de somador da OS (ex US-OFICINA-027 `peça×qty + hora×horas`): **US separada sob REGRA MESTRE** (dupla confirmação + antes→depois + OK [W]).

## Lições catalogadas

- **CT100-only sem poluir o canary:** rodei o teste via `docker cp` do arquivo pro `oimpresso-staging` (untracked, removido depois) — staging ≠ prod Martinho, e o teste usa `DatabaseTransactions` (rollback). Nunca toquei o cálculo.
- **Teste com âncora externa (anti-tautologia):** contrato = conservação de dinheiro / filtro DVI (domínio), não o que a classe faz hoje — respeita `proibicoes.md §"Teste que deriva do CÓDIGO"`.
- **Base stale:** os writes de memória (handoff + índice) foram feitos em worktree fresco de `origin/main` — editar `08-handoff.md` no worktree −4713 teria revertido o índice.

## Pointers detalhados

- Programa de Ondas: [1.4-dente-calculo.md](../requisitos/_Governanca/programa-ondas/onda-1-sells/1.4-dente-calculo.md)
- ROADMAP OficinaAuto §"Onda 1.4": [ROADMAP.md](../requisitos/OficinaAuto/ROADMAP.md)
- Alvos: `Modules/OficinaAuto/Services/ServiceOrderItemService.php`, `DviInspectionService.php`
