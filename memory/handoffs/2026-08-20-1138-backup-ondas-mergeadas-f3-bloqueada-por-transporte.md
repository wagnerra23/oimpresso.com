---
date: "2026-08-20"
time: "11:38"
slug: backup-ondas-mergeadas-f3-bloqueada-por-transporte
tldr: "Ondas 0, 1, 2 e a F1 da 3 do Backup estão no main (5 PRs). A F3 — a tela — está bloqueada por TRANSPORTE, não por decisão: o payload de design tem ~3,5 MB e o get_file corta em 256 KiB. Destrava com um payload só do backup."
decided_by: [W]
prs: [5979, 5980, 5999, 6000, 6003]
next_steps:
  - "Design emitir sync/payload.backup.json com backup-page.jsx + backup-page.css + acessos-ds.jsx (cabe no transporte)"
  - "Ou emitir sync/payload.part*.json de até 256 KiB — o applier já junta lotes"
  - "Com qualquer um dos dois: aplicar-payload --dry --require-complete-shell, revisar o grafo, aplicar, e então a F3"
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
  - 0062-separacao-runtime-hostinger-ct100
---

# Handoff — Backup: 4 ondas no `main`, F3 bloqueada por transporte

## Estado no fechamento

| Onda | PR | Estado |
|---|---|---|
| 0 — decisões [W] + medições | #5979 | mergeada |
| 1 — segurança do legado | #5980 | mergeada |
| 2 — job + worker da fila `backups` | #5999 | mergeada |
| 3 (F1) — RUNBOOK + BRIEFING | #6000 | mergeada |
| — conserto do protocolo de design | #6003 | mergeada |

## Estado MCP no momento do fechamento

⚠️ **O servidor MCP não respondeu nesta sessão.** O hook de `SessionStart` registrou
`[brief-fetch hook] FALLBACK ATIVADO — motivo: servidor MCP não respondeu no tempo (timeout)`,
e a sessão inteira rodou pelo **fallback filesystem**, que é o caminho legítimo previsto em
[`how-trabalhar.md`](../how-trabalhar.md) §Fallback.

Consequência honesta: **não há snapshot de `cycles-active` / `my-work` / `sessions-recent` aqui**,
porque nenhuma dessas tools respondeu. Registrar um snapshot inventado seria pior que registrar a
ausência. Quem retomar deve rodar `brief-fetch` antes de assumir qualquer estado de cycle/task.

O que substituiu o MCP como fonte de estado nesta sessão: `gh pr view/list` para o estado real dos
PRs, `git log origin/main` para o que entrou, e os gates do CI como veredito.

## O que precisa acontecer para a F3 sair

O protótipo **existe** no Cowork (medido por ID, não por suposição):

| Arquivo | Papel | No espelho? |
|---|---|---|
| `backup-page.jsx` | a tela | não |
| `backup-page.css` | estilo | não |
| `acessos-ds.jsx` | provê `window.AcessosDS` | não |

O terceiro é o que engana: `backup-page.jsx` faz `const { Kpis, Kpi, Nota, Vazio, Confirm, Meta } =
window.AcessosDS;` no topo do módulo. Sem ele a tela **não degrada — quebra**. Baixar só o par
`backup-page.*`, que parecia o óbvio, entregaria tela morta.

**O bloqueio é de transporte:** `sync/payload.json` tem ~3,5 MB; `DesignSync.get_file` corta em
256 KiB (`"truncated": true`, corte medido em 256.567 chars). Verificado 3× ao longo do dia — o
payload voltou **byte-idêntico** (mesmo sha256) nas três.

**Não existe rota fiel alternativa:** escrever o espelho a partir do que o `get_file` entrega no
contexto do agente é transcrição — a classe que causou o STALE de 2026-08-11, e o motivo de o
`aplicar-payload.mjs` existir.

## Armadilhas catalogadas nesta sessão (não repetir)

1. **`public $queue` redeclarada em job** = fatal na carga; `php -l` não pega. Use `$this->onQueue()`
   no constructor. Ainda dormente em `Modules/PaymentGateway/Jobs/ProcessarWebhookPixInterJob.php:55`.
2. **Fila `default` não é drenada** sem `queue.backlog_worker_enabled` (default `false`). Job novo
   precisa de fila com worker declarado.
3. **`gh run rerun` + label ao mesmo tempo** no `visual-regression` = os dois caem no grupo
   `visual-regression-<ref>` e viram `cancelled`, que a branch protection lê como bloqueio. A saída
   está escrita nas linhas 33-46 do próprio workflow: SHA novo.
4. **Porta de entrada de `memory/requisitos/<X>/` é `BRIEFING.md`**, não README — `knowledge-drift.mjs`
   L539. O `TRUTH_RE` da L102 conta docs *concorrentes*, não a porta.

## Onde ler o resto

- Session log: [`sessions/2026-08-20-backup-migracao-ondas-0-a-3.md`](../sessions/2026-08-20-backup-migracao-ondas-0-a-3.md)
- Contrato da tela: [`requisitos/Backup/RUNBOOK-index.md`](../requisitos/Backup/RUNBOOK-index.md)
- Decisões + o que foi refutado: [`requisitos/Backup/DECISOES-ONDA-0.md`](../requisitos/Backup/DECISOES-ONDA-0.md)
- Índice do módulo: [`requisitos/Backup/BRIEFING.md`](../requisitos/Backup/BRIEFING.md)
