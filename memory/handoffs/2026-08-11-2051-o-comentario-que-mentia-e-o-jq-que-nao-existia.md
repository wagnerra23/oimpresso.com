---
slug: o-comentario-que-mentia-e-o-jq-que-nao-existia
date: "2026-08-11"
tldr: "Comentário da lane Compras afirmava ser ADVISORY 6 dias depois de virar required (#5313) — travou o #5568. Corrigido pra apontar pro dono (#5610) + lápide LC-10 4→5 (#5615), ambos mergeados [W]. A varredura do meu próprio erro (monitor com jq, que não existe no Windows) achou 1 site vivo no repo: a receita de bucket da skill governance-pr-summary, cujo fallback // \"unknown\" nunca rodava — #5633, aberto, 0 falhas, fila."
autor: "[CC] Claude Code"
sessao: cool-swartz-c3390e
prs: [5610, 5615, 5633]
related_adrs:
  - 0369-tres-lanes-pest-valor-estoque-lei-required-emenda-0314
  - 0358-doutrina-de-teste-tenant-98-supersede-0101
  - 0346-promove-topico-gate-required-override-soberano-emenda-0314
  - 0314-poda-gates-onda-2-lei-fusoes
---

# Handoff — o comentário que mentia, e o `jq` que não existia

Entrada de [W]: o comentário do step `Run Pest` em `.github/workflows/compras-pest.yml` afirmava, **em presente**, que a lane era ADVISORY. Medido nos dois oráculos — `governance/required-checks-baseline.json` (`classic_protection.contexts`, 42) **e** a proteção viva (42 contexts, pos. 35) — o job `PHP / Pest (Compras · MySQL)` é **required**.

## A cronologia é o achado

| data | evento |
|---|---|
| 2026-07-16 | lápide-mãe LC-10 varre **8 sites vivos em 5 arquivos** |
| **2026-07-27** (#4864) | comentário escrito — **11 dias DEPOIS**, num arquivo que a varredura não podia alcançar. Nasceu **verdadeiro** |
| 2026-08-05 (#5313, ADR 0369) | job promovido a required → **a frase vira falsa** |
| 2026-08-11 | #5568 (só docs) trava; a frase é notada |

Sobreviveu 6 dias. E o custo foi concreto: o próprio `compras-pest.yml` está no `paths-filter` da lane, então **até o PR que corrige o comentário dispara a lane**.

**O que isso mede, e é desconfortável:** as 2 máquinas possíveis já foram medidas e reprovadas na própria mãe — gate de vocabulário (**130 FP** em árvore limpa) e label derivado do baseline (mente por lag: 4 dias no flip do `anchor-content-check`). Logo a defesa desta classe é **cultural — e a cultura falhou em 11 dias, em artefato NOVO**. Isso não reabre o gate; é dado sobre o custo. _(A [ADR 0346](../decisions/0346-promove-topico-gate-required-override-soberano-emenda-0314.md) já tinha aplicado a mesma lição no rename do label do gate Tópico.)_

## As 3 opções que apresentei caducaram no meio da sessão

Apresentei a [W] o fork consertar / rebaixar / manter. Entre a apresentação e o commit, **outra sessão consertou os testes em main**:

- o fatal `Trait::CONST` do `ComprasListagemNPlusUmTest` (que fazia 2 testes **nem executarem** — vermelho por não-execução) foi corrigido com Reflection. **Meu fix virou redundante; conflito resolvido a favor de main.**
- o failing-first declarado do `ComprasContratoFiltrosTest` foi pro regime *ratchet up*.
- run `31517070253` (17:20Z): **`45 passed (125 assertions)`**. O #5568 **mergeou**.

Sobrou só o comentário — que é o que o #5610 toca.

## O segundo achado veio do meu próprio erro

Armei um monitor de CI cujas 4 pernas passavam por `jq`. **`jq` não existe no Git Bash do agente desktop.** Rodou 60min emitindo nada; o silêncio era indistinguível de "ainda rodando", e eu tinha prometido *"te aviso quando fechar"*.

A varredura (`git grep` em `scripts/**` + `.claude/**`, descartando `gh --jq`) achou **1 site vivo**: `.claude/skills/governance-pr-summary/SKILL.md`, receita que roda **local** antes de `gh pr create`:

```bash
bucket=$(jq -r '.governance.bucket // "unknown"' "$p/module.json" 2>/dev/null)
```

O `2>/dev/null` engole o `command not found`; o `// "unknown"` — que existe pra garantir um valor — **nunca executa porque o jq nem inicia**; `bucket` sai **vazio**, e vazio **não casa** com a condição 2 linhas abaixo (*"Se bucket = unknown → escalar pro Wagner"*). **O fallback que deveria disparar o escalonamento é o que a ausência do binário desliga.**

Bite-test: `Modules/Compras` → jq `''` · node `process_horizontal` · inexistente → `unknown` (casa, escala). Fix com `node -e` + try/catch.

## Ledger

- **LC-10 4→5** — lápide §5 com a cronologia + o corolário novo (o limite vale pro comentário afirmar o **estado do job**, não só o enforcement).
- **LC-13 11→12** — lápide §5; corolário que generaliza: **fallback embutido na sintaxe da ferramenta só protege se a ferramenta INICIAR**.
- Nenhum gate novo. O predicado do jq (*"este binário existe no ambiente ONDE roda?"*) não é derivável do texto, e os 9 workflows que usam `jq` rodam em `ubuntu-latest`, onde ele existe.

## Erros meus, registrados nas lápides

1. **A 1ª redação do fix trocou a frase falsa por uma errata datada com snapshot** (`"5/5 failure"`). Escrita ~15h; às 17h20 a lane voltou a verde. **Apodreceu em 2 horas, dentro do PR que consertava o apodrecimento.** Reescrita pra ponteiro-pro-comando antes do commit.
2. Troquei `"PHP 8.3+"` por `"8.2+"` inferindo da RFC, sem medir. Removi a alegação e ancorei no bite-test que rodei no CT 100 (8.4.22).
3. O monitor cego acima.

## ⚠️ Aberto / não medido

- **#5633 aberto**: 100 pass, 0 fail, **1 queued** (`DS gate`). Não é defeito — é fila: **98 de 100** runs recentes do repo estão `queued`. Conclui sozinho quando drenar; merge é [W].
- **`jq` no CT 100 ficou POR MEDIR** — host em `502 Bad Gateway`. `scripts/infra/get-secret.sh` usa jq em 2 linhas e roda lá: a `:138` tem `|| bw status`, **a `:156` não tem**. Não concluir seguro nem quebrado.
- Divergência campo `Ocorrências` × contador do hook segue como decisão [W] pendente desde 08-08 — **não mexi**.

## Estado MCP no momento do fechamento

| tool | resultado |
|---|---|
| `cycles-active` | **Nenhum cycle ATIVO em COPI** |
| `my-work` | 10 tasks, **todas em REVIEW** — US-TR-309/310/311, US-PROD-025/027, US-INFRA-023/048, US-TR-305/306, US-KB-002 |
| `decisions-search "required checks baseline enforcement gate promoção"` | 0261 · 0336 · **0346** · 0298 |
| `sessions-recent` | fallback filesystem — últimos handoffs de 2026-08-11: 1514, 1537, 1745, 1750, 1810 (este é 2051) |

Nenhuma task foi criada ou movida nesta sessão: o trabalho foi conserto de artefato + ledger, sem US associada.
