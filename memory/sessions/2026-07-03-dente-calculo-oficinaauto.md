---
date: '2026-07-03'
topic: 'Dente de cálculo da ordem de serviço (OficinaAuto) — Onda 1.4, TEST-ONLY'
authors: [C]
outcomes:
  - 'PR #3762 MERGED — tests/Feature/Calculo/CalculoValorOficinaAutoTest.php (20 passed/76 asserts CT100 MySQL biz=1)'
  - 'Cobertura verificada: existentes provam "ele soma" com exemplos redondos; indefeso era property+golden de precisão de centavo'
  - 'Zero mudança de cálculo — canary Martinho biz=164 LIVE intacto; Module Grades OficinaAuto 79→80 (+1)'
  - 'Seção "Onda 1.4 — Dente de cálculo (OS)" registrada no ROADMAP.md (encaixe T6, não doc paralelo)'
prs: [3762]
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0101-tests-business-id-1-nunca-cliente
  - 0171-oficinaauto-ativacao-piloto-martinho-faseada
  - 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
  - 0265-oficina-reparo-erradica-locacao
---

# TL;DR

Wagner "destrave OficinaAuto" → implementei o **dente de cálculo** (Onda 1.4 do Programa de Ondas) no cálculo **próprio da ordem de serviço** de reparo, **TEST-ONLY** (canary Martinho biz=164 LIVE). Converte o controle manual da REGRA MESTRE em automático: property de conservação + partição + filtro-severidade DVI + golden de centavo + discriminador RED. PR [#3762](https://github.com/wagnerra23/oimpresso.com/pull/3762) MERGED, CI 70 verde, `20 passed (76 assertions)` no CT100.

# O que foi feito

## Verificação de cobertura (compare-não-duplica)

Lida em `origin/main`. Os testes existentes cobrem o funcional com **exemplos redondos**:

- `ServiceOrderItemTest`: `addItem` auto-cálculo (180,00), `recalcularTotal` soma, `breakdownPorTipo`.
- `DviInspectionItemTest`: `totalRecomendado` (350,50), `breakdownPorSeverity`.
- `ServiceOrderIndexItemsTotalTest`: `withSum items_total`.

Provam **"ele soma"**. Nenhum exerce **precisão de centavo numa faixa** nem a invariante de conservação. Esse era o indefeso — o ângulo property+golden que a Onda 1.4 pede. Não dupliquei os dentes de venda (#3695), financeiro (#3710) nem fiscal (#3735): mirei o cálculo **próprio da OS**.

## Alvos e contratos defendidos

| Método | Contrato (âncora externa, não o código) |
|---|---|
| `ServiceOrderItemService::recalcularTotal` | conservação: `total == round(Σ valor_total, 2)` (fuzzed seed fixa) |
| `ServiceOrderItemService::breakdownPorTipo` | partição: peça+mão+terceiro == total, sem migrar dinheiro |
| `ServiceOrderItemService::addItem` | `round(qty×unit, 2)` + override de desconto que não infla |
| `DviInspectionService::totalRecomendado` | orçamento recomendado = Σ só de `{atenção,crítico}`, ignora `ok` |
| discriminação RED | mutantes `floor`/`cast-int` que perdem centavo divergem do real |
| Tier 0 | total não soma item cross-tenant (ADR 0093) |

## Honestidade técnica registrada

- A OS **não** roteia por `num_uf` (Service casta `(float)` direto) → o golden de "não inflar" é **sentinela de regressão futura**, não reprodução de bug atual.
- A OS **não tem campo de desconto** (ADR 0194/0265) → desconto entra como override de `valor_total` por item, depois somado.
- Contrato ancorado FORA do código (conservação de dinheiro / filtro DVI) — anti-tautologia (`proibicoes.md §"Teste que deriva do CÓDIGO"`).

## Evidência RED/GREEN

CT100 staging (MySQL real, `docker cp` do arquivo untracked, removido depois; `DatabaseTransactions` faz rollback — canary não tocado): `20 passed (76 assertions)`. O próprio dente pegou **2 bugs de float na minha 1ª versão** do teste discriminador (`floor(6.96×100)` → 6,95; `abs(6.96−6.95) >= 0.01` falso em float) — prova de que a asserção morde.

# Lições

- **CT100-only sem poluir canary:** staging ≠ prod Martinho + rollback transacional + arquivo untracked removido = seguro.
- **Base stale:** memória escrita em worktree fresco de `origin/main` (o de trabalho estava −4713; editar `08-handoff.md` lá reverteria o índice).
