# T1 · Mapa teste↔arquivo (`--coverage-php` per-test)

---
roadmap_item: T1
slug: mapa-teste-arquivo-per-test
onda: 7
status: proposed
depende_de: [P07]
destrava: [T2]
related_adrs: [0275, 0279, 0062, 0314]
esforco_estimado: "1-1.5d codável (IA-pair) + 7 nightlies de relógio (artefato estável) — DEPOIS de coverage_pct armado"
---

> **Contexto da decisão pendente:** a avaliação adversarial 2026-07-01 propôs **CORTE** de T1/T2
> ([_ROADMAP.md §Decisões pendentes #2](_ROADMAP.md)) por "blueprint puro, zero artefato,
> dependência em cadeia". A dependência em cadeia CAIU em 2026-07-02: pcov na imagem CT100 +
> 1º run instrumentado gerando clover (`[harness P07] pcov presente`). Este doc dá ao Wagner
> o escopo real pra decidir EXECUTAR vs demover pra feature-wish (ADR 0105) — com números,
> não com wishful thinking. **Status `proposed` = nada roda sem OK explícito.**

## Problema (2-3 frases)
Hoje não existe mapa "qual teste exercita qual arquivo-fonte". Sem ele, todo PR paga a suíte
inteira (nightly CT100 ~2h) ou o lane sqlite curado (subset fixo que não sabe o que o PR
impactou) — não há caminho pra rodar SÓ os testes relevantes ao diff. T2 (TDAD-lite,
impactados-no-PR) é estruturalmente impossível sem este artefato: T1 é o insumo, e a ADR
0275 §5 já fixa que T1 **não vira required sozinho** — é infra, não gate.

## Fonte canônica (ADR 0275 §5 linha 88)
`T1 | mapa teste↔arquivo (--coverage-php per-test) | artefato gerado em 7 nightlies
consecutivas sem erro; é insumo do T2, não vira required sozinho`.

## Como (desenho técnico honesto)
1. **Coleta**: o pcov já instrumenta a nightly (P07). Per-test exige exportar o objeto
   CodeCoverage completo: `--coverage-php /artifacts/coverage.cov` — o clover agrega por
   linha, mas o export PHP preserva `getTests()` (quais testes cobriram cada linha).
   Aditivo ao run atual: mesma filosofia P07, falha de coverage NÃO derruba o diagnóstico.
2. **Inversão**: script novo `scripts/tests/test-map-compute.php` (roda no container, PHP —
   precisa desserializar o `.cov` com as classes `SebastianBergmann\CodeCoverage` do vendor)
   inverte pra granularidade ARQUIVO (não linha): `{"app/Services/X.php": ["tests/Feature/XTest.php", ...]}`.
   Granularidade arquivo→arquivo-de-teste mantém o JSON pequeno (~centenas de KB vs GB por linha).
3. **Transporte**: mesmo trilho do floor/coverage — `governance/test-map.json` (gzip se >1MB:
   `test-map.json.gz`) no commit `[skip ci]` da branch órfã `governance/nightly-floor`.
   Schema versionado `test-map/v1` com `computed_at` + `sha` + `tests_total` + `files_mapped`.
4. **Read-side**: NENHUM neste item. T1 não entra no scorecard (não é métrica de qualidade,
   é artefato de infra). O consumidor é T2.

## DoD (objetivo, checável)
1. `git show origin/governance/nightly-floor:governance/test-map.json` retorna JSON válido
   `test-map/v1` com `files_mapped > 0`.
2. **7 nightlies consecutivas** publicam o artefato sem erro (critério ADR 0275 §5) —
   checável nos `computed_at` da órfã.
3. A nightly NÃO estoura o timeout (hoje 14400s): duração com per-test ≤ +30% vs run só-clover.
   Se estourar → kill-criteria (abaixo).

## Riscos / kill-criteria
- **Memória**: per-test coverage em suíte de ~11k testes infla o objeto CodeCoverage.
  Piloto obrigatório: 1 run manual com `--coverage-php` ANTES de commitar no script da
  nightly; se `memory_limit=2G` estourar, testar 4G; se ainda estourar → T1 degrada pra
  granularidade suite-por-módulo (mapa Modules/<X> → testes do módulo, via `--filter` runs
  separados) ou MORRE (aí sim o CORTE da avaliação vence, com evidência).
- **Timeout**: per-test adiciona overhead de escrita. Se a nightly passar de 4h, mover T1
  pra cadência semanal (dom 02:00) — o mapa não precisa ser diário pra servir o T2
  (staleness de 7d = FN marginal, medido no modo sombra do T2).
- **Tier 0**: NUNCA no Hostinger (ADR 0062). NUNCA rodar suite local. Tudo no CT100.
- **Não-promoção**: T1 nunca vira required (ADR 0275 §5 é explícito). Qualquer tentativa
  de promover T2 a required exige **reabrir a ADR 0314** (required = só Tier-0) — ver
  [proibições §ideias descartadas, entrada 2026-07-01 foundation-ratchet](../../../proibicoes.md).

## Dependências
- `depende_de: [P07]` — **duro**: só faz sentido depois de `coverage_pct` armado (3 nightlies
  válidas + baseline PR). Se o coverage simples ainda oscila, per-test herda a instabilidade.
- `destrava: [T2]` — T2 é o único consumidor.

## Esforço (recalibrado ADR 0106)
- **Codável**: ~1-1.5d IA-pair (flag no `ct100-fullsuite.sh` + `test-map-compute.php` +
  extensão do bloco `[floor]` + meta-teste de schema). Copy-adapt do trilho P07.
- **Relógio**: 1 piloto manual (Wagner dispara) + 7 nightlies = ~8-10d calendário.
