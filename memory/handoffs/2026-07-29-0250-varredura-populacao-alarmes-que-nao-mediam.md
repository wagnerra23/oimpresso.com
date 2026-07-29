---
date: "2026-07-29"
time: "02:50 BRT"
slug: varredura-populacao-alarmes-que-nao-mediam
tldr: "Três rodadas com a regra 'só fecha com o problema resolvido'. Método validado: varrer a população e confrontar a SAÍDA do mecanismo com a FONTE. O cron-watchdog afirmava '✓ todos os 24 crons' tendo medido zero (gh caindo em catch), e o guard de selftest órfão via só 1 das 2 formas — 9 selftests não rodavam. Erro meu: contei CI por lista paginada (30 de 106) e quem me desmentiu foi a branch protection."
prs: [4997, 4999, 5000]
decided_by: [W]
next_steps:
  - "LC-13 bateu strike 3 e segue sem gate — medir FP do discriminador '0 assertions' contra as 9 lanes do pest-mysql-setup, depois estender junit-summary + o guard do ct100-fullsuite (nunca um 3º medidor)"
  - "LC-14 segue parada por two-strikes: eixo 1 do watchdog olhar `conclusion` além da recência, com FP medido antes"
  - "Compras: 2 falhas de contrato pré-existentes (UC-CMP-06 aba `abertas` → 302, UC-CMP-07 sort `location_name` → 302)"
  - "Re-land do fix do Produto (#4943, revertido) SÓ com aprovação [W] do diff do Blade + smoke em biz=1 antes do merge"
related_adrs:
  - 0317-observabilidade-como-produto
  - 0344-two-strikes-cobre-processo
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0275-calendario-promocao-gates-sdd
---

# Alarmes que afirmavam sem ter medido — 3 rodadas de varredura

## Estado MCP no momento do fechamento

⚠️ **MCP do oimpresso indisponível nesta sessão** — o hook `brief-fetch` caiu em fallback (`settings.local.json` não encontrado, token MCP indisponível). Portanto **não há** snapshot de `cycles-active` / `my-work` / `sessions-recent` / `decisions-search` medido agora. O último snapshot conhecido é herdado do [handoff das 22:38 de 28/07](2026-07-28-2238-revert-blade-producao-e-tenant-98.md), **não re-medido**: nenhum cycle ATIVO em COPI; 8 tasks em REVIEW.

Substituto **medido** nesta sessão (fonte alternativa, não equivalente): `mcp__github__list_pull_requests` state=open no início → 5 PRs abertos (#4997, #4990, #4988, #4982, #4953). Destes, o #4997 mergeou durante a sessão.

⚠️ **Clone raso** (`--is-shallow-repository` = `true`). **Nenhuma data de `git log` é citada como recibo neste handoff** — as datas e contagens de runs vieram da API do GitHub.

## O que entrou

| PR | Desfecho |
|---|---|
| [#4999](https://github.com/wagnerra23/oimpresso.com/pull/4999) | ✅ mergeado `6ebca42c` — `cron-watchdog`: `cego` ≠ `bootstrap` |
| [#5000](https://github.com/wagnerra23/oimpresso.com/pull/5000) | ✅ mergeado `21a06cb2` (auto-merge SQUASH) — registry enxerga selftest embutido |
| [#4997](https://github.com/wagnerra23/oimpresso.com/pull/4997) | ✅ mergeado `dbb07351` — Rodada 1, de sessão paralela |

## O método que foi validado (é o que importa pro próximo)

**Varrer a população inteira em vez de esperar o acaso**, e o predicado: **confrontar a SAÍDA do mecanismo com a FONTE que ele diz resolver**. Os 3 defeitos vieram disso; **nenhum** sairia de reler código — os três *parecem* corretos na leitura.

Corolário medido: **rodar os alarmes advisory** é barato e rende. Ninguém os lê, e é exatamente por isso que mentira mora ali.

## Rodada 2 — `cron-watchdog`

`gh()` engolia qualquer falha (`catch → ''`) e devolvia o mesmo `''` de *"perguntei e não há run"* → 🟡 bootstrap (benigno) → `exit 0` + **"✓ todos os 24 crons de governança com heartbeat < limite"**, tendo medido **zero**.

Recibo: **24 de 24** bootstrap no host sem `gh`; **`system-map.yml` tem 16 runs agendadas** (API do GitHub).

O fail-open era **deliberado** ([#3522](https://github.com/wagnerra23/oimpresso.com/pull/3522): *"fail-open em erro de API"*) e segue correto — o defeito é fail-open **+ afirmação de verde**.

Duas sub-populações varridas saíram **limpas** (registrar isso importa tanto quanto o defeito): guardas com `exit 0` por input ausente (5/5 presentes) e cobertura do `memory-schema-guard` (tipos canônicos 100% mapeados).

## Rodada 3 — `selftest-registry-check`

Via `*.test.mjs` e era cego ao modo `--selftest` embutido (**78** scripts o implementam). **A regra do irmão foi medida antes de armar**: sem ela, 46 acusados com **39 FP (85%)**; com ela, 7. Conservadora de propósito, com controle-negativo pra não virar perdão cego.

9 órfãos rodados antes de wirar (9 verdes). **2 eram órfãos POR DEPENDÊNCIA** — o `governance-script-tests` não instala nada de propósito, então a casa deles é o `memory-schema-gate`, que já paga o install. Só se descobre **rodando**.

Fila a **0** → `--check` cobre as duas formas **sem grandfathering**.

## Erro meu — LC-08 ocorrência 26

Contei CI por `list_workflow_runs` tratando os **30** da página como o todo; a API declarava **`total_count: 106`**. Afirmei *"30 de 30 verdes"* (#4999) e *"24 success, 0 falhas"* (#5000) enquanto um **required** (`PHP / Pest (Unit)`) nem aparecia na amostra. A lista muda enquanto se pagina (o `Memory schema gate` apareceu `queued` na página 1 e `success` na 2).

Quem desmentiu foi a **branch protection**, recusando o merge e **nomeando o check**. O #4999 não foi salvo pela minha contagem — foi pelos 34 required + `enforce_admins`.

**Lei:** verde de CI **não se conta por lista paginada**; a autoridade é a branch protection (tentar o merge) ou `get_check_runs` escopado ao head sha.

## Lições

- **Fail-open + afirmação de verde** é a combinação tóxica; fail-open sozinho pode ser desenho correto. Separar as duas coisas antes de "consertar".
- **Ausência de resposta não é estado do objeto medido.** Resposta ilegível idem — é falha do consultante.
- **Órfão por dependência** existe: um selftest pode não ser wirado porque o workflow-alvo não instala o que ele precisa. Rodar revela; ler não.
- **Medir FP ANTES** salvou dois gates: 85% (selftest órfão) e o critério ingênuo de presença. Medir depois não conta.
- **Sub-população limpa é resultado**, não silêncio — registrar evita que a próxima sessão re-varra.
- Hooks `block-destructive` morderam 2×, corretamente (`rm -f` e `--force-with-lease`). Nos dois casos havia caminho não-destrutivo, e no do force ele era **desnecessário**.

## Pointers

- Session log: [2026-07-29-varredura-de-populacao-tres-rodadas.md](../sessions/2026-07-29-varredura-de-populacao-tres-rodadas.md)
- Lápide da Rodada 2: `memory/proibicoes.md` §5 (2026-07-29, *"Instrumento AFIRMAR verde quando não conseguiu MEDIR"*)
- Ledger: `LC-13` 2 → 3 (generalizada de "suíte" para **instrumento**) · `LC-08` 25 → 26
