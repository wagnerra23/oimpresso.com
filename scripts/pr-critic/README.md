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

O passe tem duas etapas: um **FINDER** contexto-zero propõe candidatos, e cada
candidato citação-válido é submetido a **VERIFICAÇÃO POR LENTES DIVERSAS** —
sobrevive só o achado que a maioria confirma (§Lentes abaixo). A **precisão do
próprio critic** é medida à parte (`precisao.mjs`), fechando o loop.

| É | NÃO é |
|---|---|
| Crítica de **coerência** diff × contrato citado | Validação de **cobertura** de casos (isso é o `casos-gate`, ADR 0264) |
| Contexto **ZERO** — finder e lentes recebem SÓ o diff + artefatos; não herdam a sessão que produziu o PR (Cognition) | Judge de Constituição UI (isso é o `pr-ui-judge-manual`, `php artisan ui:judge-pr`) |
| **Advisory por lei** (ADR 0314: required só Tier-0) — achados nunca bloqueiam | Gate de merge, opinião estética, ou sugestão sem âncora |

## As 5 defesas contra teatro/alucinação

1. **Trava de citação (mecânica):** achado cuja `citacao_contrato` não existe
   LITERALMENTE (módulo whitespace) no artefato citado é **descartado em código**
   e contado no rodapé do comentário. A âncora é verificável, não confiada.
2. **Verificação por lentes diversas (perspective-diverse verify):** o finder só
   PROPÕE; cada candidato passa por **3 lentes cegas entre si e contexto-zero**
   (`contradição literal` · `regressão vs adição` · `advogado do diff`), e só
   sobrevive quem a **maioria (≥2)** confirma. Pega o falso-positivo que a trava
   de citação NÃO pega: citação REAL, mas contradição INFERIDA errada. Voto ausente
   NÃO conta como confirma (fail-safe). Padrão do Workflow tool. _(motivação: um
   verificador ruidoso perde a confiança e vira ignorado — lição #4038.)_
3. **Sem kill-switch dormente** (a lição do pr-ui-judge morto): a chave é a
   presença do secret `ANTHROPIC_API_KEY`/`OPENAI_API_KEY`. Sem ele, coleta+selftest
   rodam grátis e o passe agente é pulado com `::notice` explícito — nunca verde-teatro.
4. **No silent caps:** teto de 8 grupos/PR + teto de 8 achados-verificados/grupo;
   grupos cortados, achados refutados por voto, achados não-verificados pelo teto,
   grupos sem contrato e truncamentos aparecem nominalmente no comentário.
5. **Selftest hard no início do job** (`coleta.test.mjs` + `critica.test.mjs` —
   incl. agregação de votos bite/release — + `precisao.test.mjs`, fixtures-armadilha)
   — o critic não roda com roteamento/voto quebrado. Também rodam no
   `governance-script-tests.yml`.

## Lentes (perspective-diverse verify)

Cada achado candidato é julgado por 3 verificadores independentes, cada um com um
ângulo distinto de refutação e **cego aos outros** (só vê citação + hunk + alegação):

| Lente | Pergunta (confirma só se…) |
|---|---|
| `contradição literal` | o hunk MUDA/REMOVE literalmente o que a citação descreve |
| `regressão vs adição` | é regressão de um invariante/fluxo declarado (não adição neutra) |
| `advogado do diff` | mesmo tentando DEFENDER o diff, ele claramente contradiz a citação |

Maioria (`MIN_CONFIRMA=2` de 3) mantém; senão descarta (contado no rodapé). A
agregação (`agregarVotos`) é **pura e testada**; as chamadas das lentes são LLM.

## Precisão do próprio critic (loop fechado — grade v3)

O critic embute em cada comentário um bloco `<!-- pr-critic-data: {...} -->` com
os achados sobreviventes (id/arquivo/severidade). O medidor `precisao.mjs` lê
esse bloco (o comentário persiste no gh pra sempre) e, por PR, checa se o arquivo
apontado mudou em commits POSTERIORES ao comentário → **taxa-de-AÇÃO** (agiu vs
ignorado) + first-pass + por severidade. É **taxa-de-ação, não taxa-de-verdade**
(o critic é advisory — ignorar achado correto é decisão válida; ver `--json.gaps`).
Relatório semanal via `pr-critic-precisao.yml`. **Deconflito (1 fato = 1 lugar):**
NÃO é `outcome-metrics` (retrabalho de design) nem `agent-pr-outcomes` (DORA de PR)
nem `casos-gate` (cobertura) — mede se o CRÍTICO acerta.

## Peças

| Arquivo | Papel | Determinístico? |
|---|---|---|
| `coleta.mjs` | diff → manifesto (grupos × contratos); roteamento de gap/map **por conteúdo** (o gap referencia o path do `.tsx`), não por nome de arquivo | ✅ (testado) |
| `critica.mjs` | FINDER (1 chamada/grupo) + LENTES (voto majoritário) via fetch puro · `json_schema` · trava de citação · agregação de votos (pura, testada) · monta comentário + bloco machine-readable | parcial (partes puras testadas) |
| `precisao.mjs` | mede a precisão do critic (taxa-de-ação) a partir do comentário + commits; `--json`/`--brief`/`--fixture`/`--selftest`/`--ledger` | parcial (funções puras testadas) |
| `comentar.mjs` | upsert de 1 comentário/PR via marcador `<!-- pr-critic-contrato -->` | ✅ |
| `../../.github/workflows/pr-critic.yml` | orquestra o critic em CI (advisory) | — |
| `../../.github/workflows/pr-critic-precisao.yml` | mede a precisão semanalmente (advisory) | — |

## Provider e custo

Auto-resolução: **Anthropic** se `ANTHROPIC_API_KEY` existir (default
`claude-opus-4-8`); senão **OpenAI** via `OPENAI_API_KEY` (default `gpt-4o` —
já existe nos secrets do repo; decisão Wagner 2026-07-09 "dá pra colocar o
OpenAI, já tem lá"). Overrides: repo vars `PR_CRITIC_PROVIDER` /
`PR_CRITIC_MODEL`. Só arquivos do diff (nunca a árvore); contratos truncados a
15KB, diff/arquivo a 20KB, ≤8 grupos/PR. As lentes só rodam quando HÁ achado
(silêncio é comum) e reusam o hunk+citação já em contexto — 3 chamadas curtas por
achado candidato, teto de 8/grupo. Custo típico ~US$0,10-0,50/PR.

## Ativação

Ativo desde o merge via `OPENAI_API_KEY` (fallback). Se Wagner adicionar
`ANTHROPIC_API_KEY` nos secrets, o provider preferido assume sozinho — sem
mudança de código. Registrado no censo de gates
(`scripts/governance/gates-registry.json`, terminal `advisory`,
`promote_by` 2026-07-23 — o zelador cobra a decisão de manter/promover).

## Rodar local

```bash
node scripts/pr-critic/coleta.test.mjs && node scripts/pr-critic/critica.test.mjs && node scripts/pr-critic/precisao.test.mjs
git diff origin/main...HEAD > /tmp/pr.diff
node scripts/pr-critic/coleta.mjs --out /tmp/manifesto.json
ANTHROPIC_API_KEY=... node scripts/pr-critic/critica.mjs \
  --manifesto /tmp/manifesto.json --diff /tmp/pr.diff --out-dir storage/pr-critic
# precisão do critic (ao vivo via gh, ou hermético via --fixture):
node scripts/pr-critic/precisao.mjs --days 60            # ao vivo (precisa de gh)
node scripts/pr-critic/precisao.mjs --fixture <prs.json> # offline
```

Refs: arte 2026-06-22 §3 gap #5 · grade v3 (critic no loop de PR; lentes + precisão) ·
ADR 0314 (advisory) · ADR 0264 (casos-gate ≠ este) · ADR 0271 (morte do pr-ui-judge.yml
+ subtração > adição fútil) · #4038 (verificador ruidoso perde a confiança) ·
grade-das-réguas 2026-07-09 ataque ①.
