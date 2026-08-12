---
date: "2026-08-12"
slug: sec5-derivado-e-o-indice-que-conflitava
tldr: "O §5 virou DERIVADO: corpos íntegros em memory/licoes-rejeitadas.md (fonte, append-only), só os limites no proibicoes.md (−61%, zero perda). Lápide nova nasce na FONTE — se escrever no §5, rode --check → --absorver → --write, senão o --write apaga a lápide alheia (aconteceu 2× nesta sessão)."
hour: "07:40 BRT"
topic: "§5 derivado (−61% no proibicoes.md, zero perda) + merge=union no índice de handoff"
authors: [C]
prs: [5616, 5635]
related_adrs:
  - 0376-sec5-derivado-limite-no-contexto-arqueologia-na-fonte
---

# Handoff 2026-08-12 07:40 BRT — o §5 virou derivado, e o índice parou de conflitar

## O que mergeou

**[#5616](https://github.com/wagnerra23/oimpresso.com/pull/5616) MERGED** — triagem das 3 lápides §5 marcadas `revisar`. As três: **premissa intacta**, nenhuma classe 3, **zero emenda**. Uma era só rename (`taskBadges.tsx` → `Components/shared/TaskBadges.tsx`, e o rename É o conserto que a lápide prescreve); duas citavam âncoras num parágrafo de *pendência*, não de premissa.

**[#5635](https://github.com/wagnerra23/oimpresso.com/pull/5635) MERGED** — o §5 virou **derivado**. `proibicoes.md` de **413.339 → ~161k chars (−61%)**, com **zero perda**:

- **FONTE** `memory/licoes-rejeitadas.md` — lápides íntegras, append-only Tier 0
- **DERIVADO** §5 de `proibicoes.md` — cabeçalhos + só o bloco "O limite", gerado

A linha de corte foi **medida**, não escolhida: o limite (o que previne) é **22,5%** dos corpos; a arqueologia (tentativa/por que caiu/evidência) é **77,5%**.

## O que o próximo precisa saber (e é operacional)

⚠️ **Lápide nova nasce na FONTE, não no §5.** Quem escrever no §5 direto vai ser pego pelo `--check`. Aconteceu **duas vezes só nesta sessão** (o #5615 e a lápide do `jq`): o merge passa **sem conflito** e o `--write` seguinte **apagaria** a lápide alheia. A receita:

```bash
node scripts/governance/sec5-derive.mjs --check      # acusa e NOMEIA a órfã
node scripts/governance/sec5-derive.mjs --absorver   # move pra fonte, preservando posição
node scripts/governance/sec5-derive.mjs --write      # só então regenera
```

O `--check` tem **duas pernas independentes**: não-perda (todo `- **O limite` da fonte chegou ao derivado) e sincronia (byte-idêntico ao gerado). Sem a primeira ele seria tautológico.

## O índice de handoff parou de conflitar

Pergunta do [W]: *"tem maneira de não conflitar o handoff?"* — **o arquivo de handoff nunca conflitou** (nome único por sessão). O que conflita é o **índice** `08-handoff.md`, onde toda sessão insere uma linha **no mesmo lugar**, com conteúdo disjunto: não há o que resolver, as duas devem entrar.

`memory/08-handoff.md merge=union` no `.gitattributes` — técnica que o repo **já usava** em 3 `.list` do `.github/`. Custo declarado no próprio arquivo: ordem arbitrária entre as duas linhas concorrentes, lista podendo passar de 5. **Não resolve** duas sessões editando as *seções de instrução* (aí union duplica em vez de conflitar).

## Erros meus desta sessão (registrados, não apagados)

1. **O invariante nasceu cego** — o extrator pegava 1 limite por lápide, e a `2026-08-03` tem **dois eixos**. O segundo era comido, e o `conferirNaoPerda` não via porque conferia só a 1ª linha. Achado pelo `--audit`. Virou bite-test.
2. **LC-19**: fui editar o hook `block-memory-drift` para ensinar o label `adr-body-edit-W` a ele. O [W] disse *"já deve ter uma sessão fazendo isso"* — e o [#5624](https://github.com/wagnerra23/oimpresso.com/pull/5624) já tinha medido que **é impossível**: PreToolUse roda **antes de existir PR**. Meu patch funcionaria por acidente. O classificador me barrou 2×, e foi acerto.
3. Medi linhas com `Measure-Object -Line` e vi "122 faltando" — eram as 122 linhas **vazias** que ele não conta. Instrumento errado, peguei antes de publicar.

## Pendências declaradas

- **ADR 0376 sem a seção do `--absorver`.** [W] autorizou editar ADR aceita (*"não é mais somente leitura"*), mas o hook bloqueia e o override é `OIMPRESSO_MEMORY_OVERRIDE=1` no ambiente — **decisão [W]**. O conteúdo vive no docblock do script.
- **A política em geral continua em aberto**: "ADR aceita é append-only" está escrito em **4** lugares (`CLAUDE.md`, `proibicoes.md`, o hook, Job 1 do `governance-gate`). O CI já libera **por PR** via label `adr-body-edit-W`; o hook não. Mudar a política é ADR própria.
- **`governance/adr-alias-map.json` parado há 61d** (limite 60) — derruba o `crons de governança vivos?` (**advisory**, não required). **Não é meu**: não toquei o arquivo, última escrita 2026-06-21 (#3134). O watchdog mede **idade, não autoria**, e manda varrer "quem escreve neste path" para separar cron-que-parou × curadoria-envelhecida × mecanismo-morto. Pelo histórico cheira a curadoria (caso *(b)*), que é decisão [W] — precedente #4822.

## Estado MCP no momento do fechamento

Consultado agora (não de memória):

- **`cycles-active`** → *"Nenhum cycle ATIVO em COPI"*
- **`my-work`** → **5 tasks**, todas em **REVIEW**, todas `p1`: `US-TR-309` (lista de tasks órfãs) · `US-TR-310` (owner+prioridade inline) · `US-PROD-027` ([V0] preço zero em tabela) · `US-INFRA-023` (Zod em endpoints não-Inertia) · `US-TR-305` (Inbox marcar lido)
- **`sessions-recent limit:3`** → `session-2026-08-04-cron-unassigned-buraco-na-serie` · `session-2026-08-05-ancora-medivel-funil-e-teste-que-nao-provava` · `session-2026-08-05-maquinas-que-existiam-e-nao-avisavam` (os três indexados em 2026-08-12)
- **brief #503** (gerado há 35 min): HITL pending [W] = 5 · US não atribuída **671** (520 sem dono) · SDD composta **55,2** (Δ−0,2)

Session log completo: [`2026-08-12-sec5-derivado-e-o-indice-que-conflita.md`](../sessions/2026-08-12-sec5-derivado-e-o-indice-que-conflita.md).
