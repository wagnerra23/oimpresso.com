# Handoff 2026-07-28 12:30 — a medição do module-grades vira artefato, e o comando fantasma morre

> **Delta** do [handoff das 11:30](2026-07-28-1130-php-lint-no-write-tocontain-onda45.md) (Onda 4/5 do SDD).
> Um PR: [#4932](https://github.com/wagnerra23/oimpresso.com/pull/4932), **MERGED**.

## O que mudou pro próximo agente

**Precisa da nota de um módulo? Baixe o artefato, NÃO parseie o comentário do bot.**

```bash
gh run download <run-id> -n module-grades-current -D /tmp
jq -r '.[] | select(.module=="<Modulo>") | .score' /tmp/current.json
```

O artefato `module-grades-current` (retenção 30d, `if: always()`) passa a existir em todo
run do `module-grades-gate` — **inclusive quando ele reprova**, que é justamente quando
alguém precisa do número.

⚠️ **Use o campo `score`.** O JSON traz três: `score` · `score_v3_normalized` · `score_v3_raw`.
O gate compara `(int) $g["score"]`. No VozDoCliente: `score`=46, `raw`=**55**. Gravar o
`raw` criaria piso 9 pontos acima do mensurável — vermelho permanente em PR de terceiros,
que é o modo de falha dos rebaselines v3.5.1→v3.5.4.

## Os 2 defeitos que estavam na máquina

| # | Defeito | Consequência medida |
|---|---|---|
| 1 | `current.json` gerado em `/tmp` e **jogado fora** | a única forma de saber a nota era **parsear a tabela markdown** do comentário — prosa renderizada, que quebra com layout/emoji/truncagem |
| 2 | a instrução mandava rodar `composer module-grades-update-baseline`, que **não existe** | `grep` no `composer.json` volta vazio; o único lugar do repo que citava a string era a própria mensagem. Instrução inexecutável = passo que ninguém faz |

Os dois juntos explicam por que o **passo 3** ("após merge, registrar o módulo novo no
baseline") vivia pendente. O `#4917` (módulo Voz do Cliente, merge [W] 11:24) caiu nesse
buraco e o gate passou a acusar *"✨ 1 módulo novo sem aprovação"* em **todo PR que
trouxesse o main** — confirmado em `#4927` e `#4930`, que não tocam o módulo.

Não travava merge (advisory desde a [ADR 0314](../decisions/0314-poda-gates-onda-2-lei-fusoes.md) D-1 — verificado na branch protection
**VIVA**: 34 contexts required, nenhum com "grade"), mas vermelho crônico que ninguém pode
consertar é como um gate perde credibilidade.

## `VozDoCliente = 46` — quatro fontes

| fonte | valor |
|---|---:|
| **artefato `module-grades-current`** (primária, campo `score`) | **46** |
| comentário do bot em `#4927` | 46 |
| comentário do bot em `#4930` | 46 |
| `#4931` (sessão paralela, independente) | 46 |

`generated_at` do snapshot **não** foi tocado: os outros 36 módulos seguem medidos em
2026-07-16 (v3.6.0), e dizer o contrário seria mentir sobre eles. Baseline → **v3.6.1**.

## Armadilhas que custaram rodada (herde)

1. **`Dedup-ack` precisa começar a LINHA.** O detector usa `ACK_RE = /^Dedup-ack:\s*\S+/m`.
   Escrevi dentro de crases no meio da frase e declarei "ack registrado" — o detector não
   via. Fui ler a regex; aí passou.
2. **Node no Windows resolve `/tmp` como `D:\tmp`.** O script falhou, o `gh pr edit`
   regravou o corpo **inalterado**, e o passo pareceu concluído. Use o scratchpad com path
   absoluto.
3. **`module-grades-baseline.json` NÃO está na lista do `baseline-tamper-guard`** —
   o trailer `BASELINE-GROW` vale pros baselines que ele guarda (casos/anchor). Usar aqui
   seria marcador errado.
4. **`new Function` não valida script de `github-script`** — o runner embrulha o corpo em
   `async fn`, então `await` de topo é legal lá e ilegal no validador cru. Valide com o
   mesmo wrapper, senão o "JS QUEBRADO" é do seu medidor.

## Crédito ao `dup-detector`

Ele achou o `#4931` antes de mim. O que parecia retrabalho virou a **quarta confirmação**
do número — duas sessões independentes, sem se ver, gravando `46`.

## Estado MCP no fechamento

- `my-work` → 4 tasks, todas em REVIEW (`US-COPI-123` p0 · `US-TR-309` · `US-TR-310` · `US-PG-008`)
- `cycles-active` → nenhum cycle ativo em COPI (consultado às 11:30, sem mudança)

## Aberto — segue com [W]

Os dois 🔴 dos chips, sem alteração desde as 11:30: `toggleAutoEmission` liga emissão
automática de documento fiscal **sem gate nenhum**; assinatura com valor negociado **nunca
vira fatura** (match exato por `ciclo`+`valor` → `plan_id=null` → descarte com 1 linha de
log, sem alarme).
