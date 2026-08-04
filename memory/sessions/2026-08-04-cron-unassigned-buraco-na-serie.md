---
date: 2026-08-04
topic: "Buraco na série do cron mcp:tasks:unassigned — onFailure é cego a 'nunca começou'"
participants: [W, C]
outcomes:
  - "Série reproduzida em prod: 2 buracos em 8 dias (07-29, 08-04)"
  - "onFailure já existia e disparou 0× — cobre exit != 0, cego a 'nunca começou'"
  - "Premissa corrigida: o Daily Brief NÃO depende deste cron (consulta o banco ao vivo)"
  - "Meu diagnóstico de causa-raiz foi REFUTADO por adversário — LC-08, retratado"
  - "Fix: remove ->onOneServer() (mitigação que discrimina, causa NÃO estabelecida)"
---

# Buraco na série do `mcp:tasks:unassigned`

## O pedido

[W] reportou que o cron (06:45 BRT, US-INFRA-043, ligado em 2026-07-27 no
[#4862](https://github.com/wagnerra23/oimpresso.com/pull/4862)) falhou em silêncio em 2026-08-04 e
2026-07-29, com ~25% de buracos na primeira semana de vida. Pediu: reproduzir, achar a causa, e
tornar o fracasso visível.

## 1 · Reprodução — confirmada

```bash
cd domains/oimpresso.com/public_html/storage/logs && \
  grep -h "mcp:tasks:unassigned" laravel*.log | grep -o "2026-[0-9][0-9]-[0-9][0-9] [0-9:]*" | sort | uniq -c
```

Presente 07-28, 07-30, 07-31, 08-01, 08-02, 08-03 (sempre ~06:45:05); **ausente 07-29 e 08-04**.
Mais 2 corridas manuais em 07-27 18:11 (dia do deploy). **2 de 8 = 25%.**

Não rodei o comando à mão para medir — isso injetaria um ponto na própria série sob análise.

## 2 · O que está PROVADO

| Fato | Recibo (prod Hostinger, 2026-08-04) |
|---|---|
| O comando loga incondicionalmente | `Log::channel('single')->info(...)` no fim do `handle()`, `McpTasksUnassignedCommand.php` |
| Não foi exit != 0 | `grep -c "mcp:tasks:unassigned FALHOU"` → **0** |
| Deploy chegou e está registrado | `php artisan schedule:list` → `45 6 * * * mcp:tasks:unassigned` |
| Não houve deploy na janela | `gh run list --workflow=deploy.yml` → 0 runs na janela dos 2 dias |
| Não foi disco | `df -h` → 75% usado, 5,4 T livres |
| Não foi OOM (implausível) | 0 fatal/memory em 06:40–06:55 nos 2 dias; `memory_limit` CLI = 3072M p/ ~672 linhas |
| Vizinho sem `onOneServer` não teve buraco | `mcp:tasks:health-check` (06:20, log incondicional) → **12/12 dias** |

**A conclusão estrutural que sobrevive:** `onFailure` cobre **exit != 0** e é **cego a "nunca
começou"** — evento pulado por filtro, ou `schedule:run` que morre antes (comandos rodam
in-process, sem `runInBackground`). Adicionar mais `onFailure` seria teatro.

## 3 · Duas premissas do enunciado, corrigidas

**a) O Daily Brief não depende deste cron.** `TasksSemDonoBriefLineService::itens()` chama
`app(McpTasksUnassignedCommand::class)->detectarNaoAtribuidas()` — consulta o **banco ao vivo** na
geração do brief, 6×/dia. Não lê a saída nem o log do cron. Em 07-29 e 08-04 a flag continuou
aparecendo normalmente. O que se perde num buraco é só o ponto da série — que sustenta a decisão
futura de promover a `--strict` com mordida provada ([ADR 0336](../decisions/0336-gates-design-promocao-por-mordida-provada-emenda-0314.md)).

**b) Os números.** 519 = `todo AND owner IS NULL` (correto p/ "sem dono"); o critério do comando
(`cycle_id IS NULL OR owner IS NULL`) dá **672**. Denominadores diferentes, sem contradição.

## 4 · O erro que eu cometi (LC-08) — retratado

Afirmei como achado que *"a Hostinger perde slots de MINUTO EXATO de forma esparsa"*, apoiado numa
tabela de atividade nos 5 slots `onOneServer` (06:15/06:35/06:45/07:00/07:10). O adversário
derrubou, e **verifiquei por conta própria**:

```
ScorecardSnapshotCommand    : Log:: = 0
SddScorecardSnapshotCommand : Log:: = 0
DetectDriftCommand          : Log:: = 4   (só se houver drift)
GovernanceAuditCommand      : Log:: = 1   (só dentro de catch)
```

Os comandos de 07:00 e 07:10 **nunca escrevem** em `laravel.log`. Meus "07:00=OK(8)" e
"07:10=OK(11)" mediam **tráfego ambiente**; "07:00=VAZIO" em 08-03 era o estado **saudável**, não
sinal. E "06:45=VAZIO" é o próprio dado a explicar — usá-lo como um dos 5 slots é circular.

**A amostra útil nunca foi 5 slots: era n=2 comandos** (os dois com log incondicional).

Mesma forma do `crontab -l || echo` já lapidado (§5 2026-07-27): **silêncio de instrumento lido
como evidência negativa**. Classe **LC-08** — e cometida no mesmo texto em que eu citava a classe.

Caiu junto a exclusão do `onOneServer`: ela era non-sequitur. `CacheSchedulingMutex::create()` usa
`mutexName() + Hi` — a chave é **por-evento e por-minuto**, então outro evento ter perdido o slot
não carrega informação sobre este.

## 5 · Fix aplicado — remove `->onOneServer()`

**Causa NÃO estabelecida.** O que se sabe: entre os dois únicos comandos com oráculo válido, o
12/12 e o 6/8 diferiam **só** pelo `onOneServer()`. Não é prova (Fisher 1-cauda = 0,1474, n=8).

Removê-lo é a mudança mais barata, mais direcionada e a **única que discrimina**:

- Ele dedupa entre **hosts**, e só um host roda `schedule:run` com `APP_ENV=live`.
- Num host único não dedupa nada e **adiciona** uma via de skip silencioso dependente de cache
  (`CACHE_DRIVER=file`): `add()` false → evento pulado, sem `onFailure`, sem log.
- Mesmo se surgisse um 2º host, rodar 2× é inócuo: o comando é um SELECT + log.
- Se os buracos **persistirem** sem ele, a causa está noutro lugar e o dado passa a valer.

Descartei o meu primeiro fix (2 slots, `cron('45 6,7 * * *')`): mitiga por palpite, não discrimina,
e mudaria a semântica da série que [W] vai usar na promoção a `--strict`.

## 6 · O que este PR NÃO fecha

**Visibilidade.** Nada hoje **alarma** quando o cron falta. `onFailure` é cego por construção, e o
[`cron-watchdog.mjs`](../../scripts/governance/cron-watchdog.mjs) declara no cabeçalho que os ~76
schedules Laravel estão **fora do eixo 1** (não há API de liveness — o scheduler roda no servidor,
não no GitHub). Fechar isso é **heartbeat em DB** (o comando grava "rodei", e algo alarma na
ausência) — máquina nova, que exige FP medido antes e decisão [W]. Fica registrado, não construído.

**Log de 967 MB.** `storage/logs/laravel.log` está com 967 MB (`storage/logs` = 1,1 G), canal
`single` sem rotação. Torna a própria série lenta de consultar. Achado operacional, não tratado aqui.

## Arquivos tocados

- `app/Console/Kernel.php` — remove `->onOneServer()`; comentário com a medição e o desvio declarado
- `memory/governance/AUTOMATIONS.md` — registry do cron
- `memory/reference/feedback-cron-slot-06h15-brt-disputado.md` — o passo 2 prescrevia "sempre
  `onOneServer()`"; desvio declarado lá, no mesmo PR (regra de precedência)
- `memory/requisitos/Forja/SPEC.md` + `CAPTERRA-FICHA.md` — ref de linha `L622`/`:622` (o bloco foi
  pra L658); trocada por âncora durável (símbolo + grep), não pelo número novo
