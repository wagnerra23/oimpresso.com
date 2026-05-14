---
name: WhatsApp re-pairing monitor — RUNBOOK
description: Validar fix PR #828 (async queue history sync) após re-parear canal Baileys. Script PowerShell polling Hostinger (logs + count DB conversations/messages/jobs).
type: runbook
since: 2026-05-14
---

# Monitor re-pairing canal WhatsApp Baileys

Receita pra validar fix async queue PR #828 quando re-parear canal Suporte (ou qualquer outro canal Baileys após reset).

## Quando rodar

Sempre que:
- Re-parear canal Baileys fresh no celular (QR scan)
- Suspeitar que history sync travou (msgs históricas não populam Inbox)
- Auditar jobs pendentes/falhos na queue `whatsapp-history`

## Como rodar

```powershell
# Default (channel=6 Suporte biz=1, refresh 15s)
pwsh ./scripts/whatsapp-monitor-pairing.ps1

# Customizado
pwsh ./scripts/whatsapp-monitor-pairing.ps1 -ChannelId 5 -BusinessId 4 -IntervalSeconds 10
```

Ctrl+C pra parar.

## O que o script mostra

```
═══ WhatsApp re-pairing monitor — iter #1 ═══
Channel: 6 (biz=1)  ·  Refresh: 15s  ·  Ctrl+C pra parar

Channel "Suporte" status: active
Conversations:     12   Messages:     2350
Jobs pending:       3   Failed:         0

[OK] FIX VALIDADO — conversations > 0 pra channel 6

── Últimas 8 linhas log (filtro history-sync) ──
[2026-05-14 16:21:33] live.INFO: history-sync-job: chunk processed channel=6 msgs=50
[2026-05-14 16:21:35] live.INFO: history-sync-job: chunk processed channel=6 msgs=50
...
```

## Critério de sucesso

| Sinal | Interpretação |
|---|---|
| `channel_status: active` | Pairing OK, daemon conectado |
| `conversations > 0` crescendo | Async queue funcionando — fix PR #828 validado |
| `jobs_pending` cresce inicialmente e zera em ~5-10min | Comportamento esperado pós-pairing (~90d msgs em chunks de 50) |
| `jobs_failed > 0` crescendo | ⚠️ Bug — investigar `storage/logs/laravel.log` ALERT entries |
| Logs `history-sync-job: chunk processed` aparecendo | Cron `queue:work` everyMinute funcionando |

## Troubleshoot

| Sintoma | Causa provável | Ação |
|---|---|---|
| Script trava em "Warm-up SSH" | Hostinger SSH IPv4 timeout (flaky) | Retry — sem warm-up SSH quase sempre dá Connection timed out |
| `conversations: 0` por >5min após pairing | Daemon não está mandando msgs OR Hostinger queue parada | SSH manual: `ssh hostinger 'php artisan queue:work database --queue=whatsapp-history --once'` força 1 batch |
| `jobs_failed` cresce | Job lança exception | `ssh hostinger 'tail -50 storage/logs/laravel.log \| grep -A 5 PersistHistorySyncBatchJob'` |
| `channel_status: setup` permanente | QR não scaneado OR daemon offline | Abrir `/atendimento/canais`, gerar QR novo |

## Referências

- PR fix: [#828](https://github.com/wagnerra23/oimpresso.com/pull/828) async via `dispatchAfterResponse()`
- Handoff: [memory/handoffs/2026-05-14-0300-whatsapp-async-queue-final-fix.md](../../handoffs/2026-05-14-0300-whatsapp-async-queue-final-fix.md)
- Cron: [app/Console/Kernel.php:394](../../../app/Console/Kernel.php) `queue:work everyMinute whatsapp-history`
- SSH receita: [memory/reference/hostinger.md](../../reference/hostinger.md)
