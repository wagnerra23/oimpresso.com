# pr-critic — critic adversarial de PR ancorado em contrato (advisory)

> Fecha o **ataque ① da grade-das-réguas 2026-07-09** (única fraqueza confirmada 3/10
> na re-verificação): não existia critic adversarial automático em PR de agente —
> o `pr-ui-judge.yml` foi deletado na ADR 0271 onda 2 (dormente/teatro) e o
> `ultrareview` é manual. Implementa a proposta **#5 da arte
> [`memory/sessions/2026-06-22-arte-design-to-code-sdd.md`](../../memory/sessions/2026-06-22-arte-design-to-code-sdd.md)**
> (critic read-only GAP-SPEC × diff), na variante CI.
> Régua externa: Google Jules (critic no loop → 64% merge-ready) · Cognition
> (review agent com contexto ZERO acha mais fundo, ~2 bugs/PR).

## O que é (e o que NÃO é)

Em PRs que tocam `resources/js/Pages/**` ou `Modules/**`, um passe crítico
**read-only** pergunta uma única coisa: **o diff contradiz o CONTRATO da
tela/módulo?** Contrato = `<Tela>.charter.md` + `<Tela>.casos.md` (ao lado do
`.tsx`) + `<tela>-gap.md` + `<tela>.map.json` (`memory/requisitos/<Mod>/`) +
charter de módulo.

| É | NÃO é |
|---|---|
| Crítica de **coerência** diff × contrato citado | Validação de **cobertura** de casos (isso é o `casos-gate`, ADR 0264) |
| Contexto **ZERO** — o critic recebe SÓ o diff + artefatos; não herda a sessão que produziu o PR (Cognition) | Judge de Constituição UI (isso é o `pr-ui-judge-manual`, `php artisan ui:judge-pr`) |
| **Advisory por lei** (ADR 0314: required só Tier-0) — achados nunca bloqueiam | Gate de merge, opinião estética, ou sugestão sem âncora |

## As 4 defesas contra teatro/alucinação

1. **Trava de citação (mecânica):** achado cuja `citacao_contrato` não existe
   LITERALMENTE (módulo whitespace) no artefato citado é **descartado em código**
   e contado no rodapé do comentário. A âncora é verificável, não confiada.
2. **Sem kill-switch dormente** (a lição do pr-ui-judge morto): a chave é a
   presença do secret `ANTHROPIC_API_KEY`. Sem ele, coleta+selftest rodam grátis
   e o passe agente é pulado com `::notice` explícito — nunca verde-teatro.
3. **No silent caps:** teto de 8 grupos/PR; grupos cortados, grupos sem contrato
   e truncamentos aparecem nominalmente no comentário.
4. **Selftest hard no início do job** (`coleta.test.mjs` + `critica.test.mjs`,
   fixtures-armadilha) — o critic não roda com roteamento quebrado. Também
   rodam no `governance-script-tests.yml`.

## Peças

| Arquivo | Papel | Determinístico? |
|---|---|---|
| `coleta.mjs` | diff → manifesto (grupos × contratos); roteamento de gap/map **por conteúdo** (o gap referencia o path do `.tsx`), não por nome de arquivo | ✅ (testado) |
| `critica.mjs` | 1 chamada Anthropic por grupo (fetch puro, sem SDK) · `output_config.format` json_schema · trava de citação · monta comentário | parcial (partes puras testadas) |
| `comentar.mjs` | upsert de 1 comentário/PR via marcador `<!-- pr-critic-contrato -->` | ✅ |
| `../../.github/workflows/pr-critic.yml` | orquestra em CI (advisory) | — |

## Custo

Só arquivos do diff (nunca a árvore); contratos truncados a 15KB, diff/arquivo a
20KB, ≤8 chamadas/PR. Default `claude-opus-4-8` (~US$0,20-0,40 num PR típico de
1-2 telas). Wagner pode baratear via repo variable `PR_CRITIC_MODEL`
(ex.: `claude-sonnet-5`).

## Ativação (pendente Wagner — R10)

O repo **não tem** o secret `ANTHROPIC_API_KEY` (verificado 2026-07-09; só
OPENAI_API_KEY existe). Até Wagner adicionar em *Settings → Secrets and
variables → Actions*, o job roda só a parte determinística e avisa. Nenhuma
variable é necessária (sem kill-switch).

## Rodar local

```bash
node scripts/pr-critic/coleta.test.mjs && node scripts/pr-critic/critica.test.mjs
git diff origin/main...HEAD > /tmp/pr.diff
node scripts/pr-critic/coleta.mjs --out /tmp/manifesto.json
ANTHROPIC_API_KEY=... node scripts/pr-critic/critica.mjs \
  --manifesto /tmp/manifesto.json --diff /tmp/pr.diff --out-dir storage/pr-critic
```

Refs: arte 2026-06-22 §3 gap #5 · ADR 0314 (advisory) · ADR 0264 (casos-gate ≠ este) ·
ADR 0271 (morte do pr-ui-judge.yml) · grade-das-réguas 2026-07-09 ataque ①.
