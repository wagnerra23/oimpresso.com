# Contrafactual de corpus — scaffold de experimento

> Origem: chip C1 da grade 2026-07-17 (fraqueza F1 "corpus de contexto nunca medido, 3/10").
> [W] escolheu **"gastar"** (rodar o experimento, não só a aritmética). Este diretório é a
> camada de EXECUÇÃO — reconstruída depois que a versão enxuta do `agent-corpus-counterfactual.mjs`
> podou o harness de 3 arms por zero-invocador. Agora tem invocador.

## O que mede

Se o **corpus de contexto** (74 skills + 11 rules + 64 hooks, quase tudo escrito por agente)
ajuda o agente a resolver tarefas — contra a régua publicada ([arXiv 2602.11988](https://arxiv.org/abs/2602.11988):
LLM-generated derruba 0,5–2pp e custa +20%). Arm **atribuído** (não observacional — skills
disparam por path, então "carregou a skill" = "é tarefa daquele tipo", confundido; §5 2026-07-15).

## Peças

| arquivo | o quê |
|---|---|
| `harness.mjs` | camada de execução: `armStats` + `classificarContraste` (veredito só com IC≠0) + `buildReport` + render. Importa TODAS as primitivas do que já está commitado (`wilsonCI`/`newcombeDiffCI` do agente-corpus, `custoUSD` do agent-cost-per-pr, `median` do agent-pr-outcomes) — não duplica (§5 2026-07-09). |
| `grader-multitenant.mjs` | oráculo estático de exemplo: dado o código retornado, pass = aplicou o padrão Tier-0 (global scope + business_id indexado + FK). Determinístico (não o olho — §5 2026-07-16). |

Mecanismo dos arms: injeta o `SKILL.md` no prompt (arm `atual`) ou não (arm `sem`), com o
`CLAUDE.md` **constante de fundo** nos dois (não dá pra desligar por prompt — é o mesmo fundo).

## SMOKE rodado (2026-07-17 · n=1/braço · skill `multi-tenant-patterns`)

Prova de máquina + primeiro achado real. 2 agentes reais na tarefa "crie um Model
business-scoped + migration"; grader estático; harness.

```
BRAÇO   n   first-pass   IC95 da diferença      custo (tokens)
sem     1   100%         ⚪ INDISTINGUÍVEL       113.128
atual   1   100%         Δ 0pp · [-79, 79]pp     138.407  (+22%)
```

**Três achados, todos honestos:**
1. **A máquina não mente** — com n=1, veredito = `indistinguível` + MDE ≥100pp. Recusa declarar
   vencedor no ruído (o pecado que o chip existe pra não cometer).
2. **Confound estrutural, ao vivo:** o arm `sem` aplicou o padrão multi-tenant **mesmo sem a
   skill** — o agente disse *"the Tier 0 IRREVOGÁVEL rule (ADR 0093) can't be waived by a task
   sub-prompt"*. O `CLAUDE.md`/proibicoes de fundo **já carrega** a lição ⇒ a skill é
   **redundante** com o fundo constante. Efeito marginal ~0 **por construção**.
3. **Custo bate no paper:** o arm com a skill custou **+22%** (paper: +20%) pra **zero**
   benefício marginal — o achado do paper reproduzido no nosso corpus, num run real.

## Consequência para o gasto (decisão técnica, não pergunta)

**Não vale queimar ~297 runs em `multi-tenant-patterns`** (nem em qualquer skill cuja lição já
esteja no `CLAUDE.md`): mediria ~zero-por-construção. O smoke reenquadra o gasto:

- **A pergunta barata que o smoke levantou** é REDUNDÂNCIA: quanto do corpus só **restateia** o
  `CLAUDE.md`? Skill redundante = +custo/~0-benefício = candidata a poda **sem runs**. Essa é a
  medição de maior valor-por-token agora (estática, não precisa de 297 agentes).
- **A pergunta cara (297 runs) só vale** num alvo cuja lição NÃO esteja no `CLAUDE.md` — e mesmo
  aí, a sobreposição corpus↔CLAUDE.md é pervasiva (o projeto front-loada Tier 0 no CLAUDE.md),
  então a pergunta "o corpus inteiro ajuda?" na verdade exige o arm **sem CLAUDE.md** (strip do
  ambiente — o confound que eu tinha sinalizado), não só sem-a-skill.

## Como escalar (quando o alvo for válido)

1. Alvo = skill CLAUDE.md-disjunta + tarefa onde ela plausivelmente muda o desfecho.
2. Oráculo forte = Pest de isolamento no CT100 (troca o grader estático; mesmo formato de `runs.json`).
3. `--power` (no `agent-corpus-counterfactual.mjs`) dá o n: ≥20pp = 99/braço = 297 runs.
4. `node harness.mjs runs.json` → veredito. Só declara vencedor se o IC≠0.

## Honestidade (o que o smoke NÃO provou)

- n=1: MDE ≥100pp — nada sobre a magnitude do efeito, só que a máquina funciona.
- Oráculo estático é **proxy** (não Pest real). Pilot troca por teste rodado.
- Custo em tokens brutos do Agent tool (sem breakdown input/output/cache pra USD) — o join fino
  é a máquina do `agent-cost-per-pr` (fonte JSONL), não ligada neste smoke.
- O confound CLAUDE.md-de-fundo é REAL e afeta todo alvo redundante — daí a recomendação acima.
