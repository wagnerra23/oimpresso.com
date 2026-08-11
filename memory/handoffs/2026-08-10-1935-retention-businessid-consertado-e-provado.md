---
date: "2026-08-10"
time: "19:35 BRT"
slug: retention-businessid-consertado-e-provado
tldr: "Fecha o next_step que o handoff das 17:40 deixou aberto: o $businessId do arquivos:retention-cleanup foi consertado com aval [W] e está PROVADO pela lane (5 falhas → 1 → 0). Eram DUAS causas independentes atrás do mesmo skip — bug de produção + Artisan::output() consumindo o buffer 2×. --mudos 28 → 27."
prs: [5546]
decided_by: [W]
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0123-modules-arquivos-backbone
next_steps:
  - "ExportZipCommandTest segue mudo: 3 das 4 falhas são o MESMO double-fetch de Artisan::output() (conserto conhecido, test-only); a 4ª NÃO foi diagnosticada — audit log action='exported' ausente, possível gap de rastreabilidade LGPD Art. 18"
  - "[W] decidir os 13 sem lane MySQL (ComVis 7 · Repair 3 · Vestuario 3) — exige workflow novo = gates-registry Check G + teto ADR 0298"
  - "O comando arquivos:retention-cleanup NÃO está agendado no Kernel (só arquivos:health-check). Se um dia for agendado, reavaliar: sem transação no chunk, uma falha no meio deixa parte apagada sem rollback"
---

# Handoff — o `$businessId` consertado, e as duas causas atrás do mesmo `skip`

Continuação curta do [handoff das 17:40](2026-08-10-1740-mudos-eixo2-12-ligados-28-classificados.md),
que deixou este item como `next_step` **aberto**. Está fechado.

## Estado MCP no momento do fechamento

⚠️ **MCP INDISPONÍVEL.** As tools `mcp__Oimpresso_MCP___Wagner__*` estão **registradas**
nesta sessão (diferente do fechamento das 17:40, quando nem apareciam), mas as **3**
chamadas retornaram `Server Oimpresso MCP — Wagner unavailable`: `cycles-active`,
`my-work`, `decisions-search`.

**Declarado, não inventado.** Fallback: `git ls-tree origin/main` + `git log` + `gh`.

## O que foi feito

[W] autorizou (*"pode arrumar"*) o fix que o handoff anterior tinha deixado pendente
justamente por ser **mudança de comportamento destrutivo**.

`Modules/Arquivos/Console/Commands/RetentionCleanupCommand.php` — a closure do `chunk`
abria com `use ($dryRun, $retentionDays, &$stats)` e consumia `$businessId` no `Log::info`
do batch. A variável existe no escopo externo e nunca fora capturada.

**Uma linha**, zero lógica de deleção tocada. `$businessId` entra **por valor** porque é
`null` de propósito no MODO ADMIN (sem `--business`) — o log registra o *filtro usado*.

## A prova, e ela é o ponto do handoff

| run | resultado | causa removida |
|---|---|---|
| [31411208410](https://github.com/wagnerra23/oimpresso.com/actions/runs/31411208410) | **5 failed** | teste ligado pela 1ª vez (#5526) — `Undefined variable $businessId` ×5 |
| [31421776552](https://github.com/wagnerra23/oimpresso.com/actions/runs/31421776552) | **1 failed** | captura no `use` → 4 somem |
| [31422722920](https://github.com/wagnerra23/oimpresso.com/actions/runs/31422722920) | **0 failed** | `Artisan::output()` capturado 1× → a última cai |

Delta da lane vs `main`: **34 → 42 passed**, **143 → 179 assertions**.

## O achado que vale carregar: eram DUAS causas, de naturezas diferentes

Atrás do mesmo `skip` (que saía `exit 0`) havia:

1. **Bug de produção** — o comando crashava com dado real, **sem rollback**, depois de já
   ter apagado um lote e removido os arquivos do disco.
2. **Defeito de asserção** — `Artisan::output()` é `BufferedOutput::fetch()`, que retorna
   **e esvazia** o buffer; chamado 2× no mesmo teste, a 2ª asserção recebia string vazia.

O perigo da segunda é o **sintoma enganoso**: o erro sai como `Expected: <vazio> To contain:
Hard-deleted: 1`, apontando para o **comando**. Quem não medir vai investigar o lugar
errado. Medido antes de mexer: era o **único** teste daquele arquivo com o padrão (os
outros 5 chamam 1× cada) — grep contado, não amostrado.

**Método que evitou o erro:** quando a lane falhou DEPOIS do meu fix, não presumi que a
culpa fosse dele. Medi, e a falha restante era de outra família — a mesma que eu já tinha
diagnosticado no `ExportZipCommandTest`.

## O teste entrou junto, de propósito

`RetentionCleanupCommandTest` saiu da dívida declarada do #5526 e entrou na allowlist do
`arquivos-pest.yml`. Se o `$businessId` sair do `use` outra vez, a lane avermelha — é a
diferença entre **consertar** e **travar**. Deixar o fix sem rede seria a doença que este
eixo inteiro combate.

## Comportamento que mudou (registrado para quem for agendar isto um dia)

Não há transação envolvendo o `chunk`. **Antes**: apagava até 100 linhas, removia os
arquivos, gravava audit log e crashava — sem rollback. **Depois**: percorre todos os chunks
até `--limit`, ou seja **apaga mais por invocação**. É o comportamento pretendido, e as
travas seguem de pé (`--dry-run`, `--limit`, o warning de `HARD DELETE`, e o comando **não
estar agendado** no `Kernel.php`).

## Armadilha de instrumento nesta sessão

Ao confirmar o merge, `git show origin/main:.github/workflows/...` foi **mangleado pelo
MSYS** (o `:` virou `;`, o `/` virou `\`) e o `grep -c` seguinte devolveu **0** — que teria
virado *"o teste não entrou na allowlist"*, falso. Refeito com `MSYS_NO_PATHCONV=1` **e
controle positivo** antes de afirmar. Mesma família do `cmd || echo` já catalogado.
