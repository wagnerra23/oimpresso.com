---
date: "2026-08-11"
time: "12:00 BRT"
slug: gate-diff-silencioso-e-a-flag-que-truncava
tldr: "Fechado o estado 3 do memory-schema-gate: o --depth=50 truncava o repo que o fetch-depth:0 acabara de baixar inteiro, e o || true engolia o rc=128 resultante — 4 contexts required podiam sair verdes tendo validado ZERO arquivos. Bite-test provou handoff inválido passando. Consertado no #5569 (merge [W]) com 4 controles negativos."
prs: [5569]
decided_by: [W]
next_steps:
  - "Confirmar o ramo push (HEAD~1...HEAD) em produção: 0 runs concluíram após o merge (cancelados por concorrência). Próximo push tranquilo em main resolve."
  - "Opcional: varrer outros workflows que fazem diff contra a base assumindo o merge ref — não medido."
---

# Gate do schema saía verde sem ter validado nada — e a flag que causava isso

## Estado MCP no momento do fechamento

⚠️ **Sem MCP nesta sessão** — o `brief-fetch` do SessionStart caiu por **timeout** do servidor (fallback do hook ativou e serviu o índice de handoffs). Nenhuma tool `mcp__oimpresso__*` esteve disponível. Fallback filesystem usado, conforme [how-trabalhar §Fallback](../how-trabalhar.md):

| fonte | estado lido |
|---|---|
| `git` (origin/main) | `f2bb85351e0` no fechamento; PR #5569 mergeado por `wagnerra23` às 11:31:57Z (squash) |
| `memory/handoffs/2026-08-*` | 3 irmãos mais recentes, todos de 08-10 (retention/business_id · scorecard órfão · âncora de design) |
| `gh pr checks 5569` | **99 pass · 2 skipping · 0 fail** |
| ledger `LICOES_CODE.md` | LC-08 em 79 · LC-13 em 10 (incrementados neste PR) |

Não consultei `cycles-active`/`my-work`/`decisions-search` porque o servidor não respondia — declarado em vez de omitido.

## O que aconteceu

Sessão de **decisão + medição**, não de escopo novo. Chegou como pergunta pendente [W]: o `validate.mjs` tinha 4 estados no modo CI, e o **estado 3** (`changed-files.txt` vazio → `exit 0` com aviso) era decisão em aberto entre 3 caminhos.

A medição achou um **quarto caminho**, mais barato, que ninguém tinha visto: o `fetch-depth: 0` do checkout **era anulado pelo próprio step**, porque `git fetch --depth=50` TRUNCA repo completo (medido: 6425 → 50 commits). A defesa em profundidade que o YAML declara ter (`# precisa do main pra git diff`) não existia. A única barreira real era o merge ref do `actions/checkout` — propriedade não declarada e não testada.

[W] escolheu **(d) + (b)**: tirar a flag e parar de engolir o rc.

O que torna o caso instrutivo não é o defeito, é **por que ele sobreviveu**: nenhum dos dois pedaços, lido isolado, parece errado. `--depth=50` parece otimização; `|| true` parece robustez. Só a combinação produz "gate verde tendo validado zero arquivos", e só aparece confrontando a SAÍDA com a FONTE.

## Artefatos gerados

| arquivo | o quê |
|---|---|
| [`.github/workflows/memory-schema-gate.yml`](../../.github/workflows/memory-schema-gate.yml) | −`--depth=50`, −`2>/dev/null \|\| true` nos 2 ramos, `wc -l`→`grep -c .`; +25 linhas de comentário com a medição (33 ins / 4 del) |
| [`memory/proibicoes.md`](../proibicoes.md) §5 | lápide "6ª porta" — inclui o que a lápide **NÃO** autoriza (nada de gate sintático pra `\|\| true`) |
| [`memory/LICOES_CODE.md`](../LICOES_CODE.md) | LC-13 10→**11** (o defeito) · LC-08 79→**83** (4 erros meus de medição) |

## Persistência

1. **git** — #5569 mergeado (squash `a676c7cd7`); este handoff + canon em PR próprio.
2. **MCP** — webhook GitHub→MCP propaga `memory/**` em ~2min após o merge. Não validei (servidor fora).
3. **BRIEFING** — n/a: não é módulo de negócio, é infra de CI.

## Lições catalogadas

**Do mecanismo** (§5 + LC-13): fonte do universo que falha em silêncio vira carimbo. Quando um gate deriva seu universo de um comando que pode falhar, o silêncio do comando vira "nada a fazer".

**Minhas, 4 na mesma sessão** (LC-08) — todas do mesmo formato, *o instrumento respondia uma pergunta parecida com a feita*:
1. `printf` tratou `---` como flag; o redirect criou arquivo vazio e o `Falharam: 0` media esse vazio.
2. **Contaminei o cenário** escrevendo à mão no `changed-files.txt` do caso sob teste — simula um step que funcionou. Quase virou "o atual também morde".
3. Regex casou `--depth=50` dentro do **comentário que eu mesmo escrevi** → falso negativo que, aceito, me faria "consertar" código correto.
4. `git show origin/main:<path>` com MSYS manglando o `:` — pegadinha **já catalogada no meu próprio MEMORY.md** — e o `rc=0` ao lado era de outro comando.

**Método que pegou os 4:** contradição entre dois instrumentos, nunca releitura. E o padrão que se repete: *número plausível engana; número impossível denuncia*.

## Caveats

- **Ramo `push` não verificado em produção.** Provado por execução do step já mergeado contra clone real (`rc=0`), mas **0 runs de push concluíram** após o merge — todos cancelados por concorrência (6 pushes em 12min). Risco baixo, não zero.
- **`dup-detector`** apontou o #5567 (mesmo arquivo, sessão paralela). Não era duplicata: merge simulado com `merge-tree`, YAML válido, 9 labels intactos. `Dedup-ack` no corpo do PR.
- **`UI architecture`** falhou por infra (`curl error 60`, SSL self-signed no `Install PHP deps`) — meu diff é 1 YAML. Passou no rerun. Varri outros runs e **não achei** o mesmo erro: transitório pontual, não incidente.

## Próximos passos pra retomar

```bash
gh run list --workflow=memory-schema-gate.yml --limit 5 --json event,conclusion,createdAt
```

Se houver `push` com `success` após 2026-08-11T11:31Z, o residual do ramo `push` está fechado.

## Pointers detalhados

- PR + medições completas: [#5569](https://github.com/wagnerra23/oimpresso.com/pull/5569) (corpo tem tabelas de timing, bite-test e Dedup-ack)
- Antecedentes: PRs #5538 (estado 1) e #5558 (estado 2) — este fecha o 3
- Família do defeito: §5 [2026-07-29] watchdog · [2026-08-04] isenção que esvazia o conjunto
