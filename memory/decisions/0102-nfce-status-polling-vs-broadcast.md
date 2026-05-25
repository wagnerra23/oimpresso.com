---
slug: 0102-nfce-status-polling-vs-broadcast
number: 102
title: "US-NFE-002 fase 2C — UI status NFC-e via polling JSON (broadcast adiado)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-07"
module: nfebrasil
quarter: 2026-Q2
tags: [nfe, ui, polling, broadcast, centrifugo, runtime-split, hostinger, ct100]
supersedes: []
supersedes_partially: []
superseded_by: []
related: ["0058-reverb-substituido-por-centrifugo-frankenphp", "0062-separacao-runtime-hostinger-ct100", "0093-multi-tenant-isolation-tier-0"]
pii: false
review_triggers: ["Quando Centrifugo HTTP bridge Hostinger→CT 100 for desbloqueado, reavaliar substituir polling por broadcast (sem mudar componentes consumidores)", "Se polling 2s × 30 max não cobrir p99 SEFAZ em prod, ajustar maxPolls antes de migrar pra broadcast"]
---

# ADR 0102 — UI status NFC-e via polling JSON (broadcast adiado pendente HTTP bridge)

## Contexto

US-NFE-002 (Emitir NFC-e a partir de venda finalizada) tem AC #5: "toast/badge sucesso pós-emissão". Wagner pediu UX onde após finalizar venda, usuário vê em tempo-real o status da NFC-e (autorizando → autorizada / rejeitada).

**Caminhos possíveis avaliados:**

| Caminho | Onde | Custo | Bloqueador |
|---|---|---|---|
| A) Reverb (WebSocket) no Hostinger | Hostinger | viola [ADR 0062](0062-separacao-runtime-hostinger-ct100.md) — Hostinger não roda daemons | ❌ |
| B) Centrifugo (CT 100) + bridge HTTP do Hostinger | CT 100 + bridge | precisa driver Laravel custom OU wrapper HTTP. ~3-5h. Decisão arquitetural separada (Wagner) | ⏸ aguardando |
| C) Polling JSON 2s no front | Hostinger | não viola ADRs, funciona hoje, hook abstrai transport | ✅ |

**Estado encontrado durante análise:**

- `BROADCAST_DRIVER=null` no `.env` Hostinger (broadcasting desligado)
- `config/broadcasting.php` só tem reverb/ably/redis/log/null — **sem driver Centrifugo registrado**
- [ADR 0058](0058-reverb-substituido-por-centrifugo-frankenphp.md) define Centrifugo como canon mas só no CT 100 Proxmox (FrankenPHP daemon)
- [ADR 0062](0062-separacao-runtime-hostinger-ct100.md) é IRREVOGÁVEL: Hostinger ≠ CT 100 runtime

**Caminho B (broadcast Centrifugo via HTTP bridge)** é o destino arquitetural mas exige:
1. Driver Laravel custom mapeando `broadcast()` → POST HTTP para Centrifugo API no CT 100
2. Túnel HTTP cross-runtime (Hostinger → CT 100 via Tailscale ou public TLS)
3. Front instancia client Centrifugo apontando pra `mcp.oimpresso.com` (CT 100)
4. ADR mãe nova decidindo trade-offs (latência cross-runtime, falha graceful, etc)

Bloquear US-NFE-002 fase 2C esperando todo esse caminho B seria erro — pipeline server-side já está fechado (PRs #198, #200, #201) e UX é última peça.

## Decisão

**Implementar UI status NFC-e via polling JSON (caminho C) como solução IMEDIATA.** Hook React abstrai o transport — quando broadcast Centrifugo HTTP bridge for desbloqueado, troca-se o transport interno do hook sem refazer componentes consumidores.

**Implementação (PR #203):**

```
GET /nfe-brasil/api/transactions/{tx}/nfe-status        (endpoint JSON)
```

**Frontend:**

- `resources/js/Hooks/useNfceStatus.ts` — polling 2s × 30 max (cobre p99 SEFAZ ~30s)
- `resources/js/Components/NfeBrasil/NfceStatusBadge.tsx` — banner reativo 4 estados (info/ok/warn/error)
- `resources/js/Pages/NfeBrasil/Transactions/NfceStatus.tsx` — Page Inertia demo

**Comportamento do hook:**

- Inicia poll quando `transactionId` muda
- Para automaticamente em estado **terminal** (`autorizada`/`rejeitada`/`denegada`)
- Para também após `maxPolls` (default 30 = 1 min) se nunca atingir terminal — flag `hasGivenUp`
- `useRef` pra evitar fetch concorrente + cleanup no unmount
- Callback `onTerminal(payload)` pra hook consumer triggerar toast

**Endpoint backend:**

- Cross-tenant guard: filtra `business_id` da `session('business.id')` automaticamente
- Modelo 65-only (NFC-e). Modelo 55 ignorado nesse endpoint
- Múltiplas emissões pra mesma tx (retentativas) → retorna mais recente (`orderByDesc('id')`)
- Flag `is_terminal` no response — UI usa pra parar polling

## Trade-offs

### Por que não broadcast desde o início

- Hostinger não roda daemons (ADR 0062 IRREVOGÁVEL)
- Bridge HTTP Hostinger → CT 100 precisa ADR arquitetural separada
- Centrifugo no CT 100 + Hostinger sem broadcasting = 0 driver registrado pra `broadcast()` Laravel
- Bloquear shipping de US-NFE-002 esperando isso seria erro de priorização

### Por que polling vs server-sent events ou long-polling

- Polling 2s simples, debugável (DevTools Network tab mostra cada fetch)
- Server-sent events precisa rota persistente (Hostinger PHP-FPM tem timeout)
- Long-polling tem mesmo problema (PHP-FPM keep-alive limitado)
- Poll 2s × 30 max = 60s total worst-case, custo trivial em CPU/banda
- **Hook abstrai transport** — quando trocar pra broadcast, componentes não mudam

### Por que `maxPolls = 30` (1 min)

- p99 SEFAZ homologação: ~30s. p99 SEFAZ prod: ~10-15s (estimativa baseada em sped-nfe historical)
- 60s cobre 99% dos casos com folga
- Após 60s sem terminal, exibir botão "Atualizar manualmente" (refetch) é melhor UX que poll infinito

## Plano de migração futura (caminho B)

Quando broadcast Centrifugo HTTP bridge for desbloqueado:

1. ADR nova decidindo: que driver Laravel custom (ou usar `pusher-php-server` apontando pra Centrifugo API)
2. Adicionar driver em `config/broadcasting.php`
3. Criar event `NFCeStatusUpdated` que `ShouldBroadcastNow` em channel `business.{id}.nfe-status`
4. Disparar event dentro do `NfeService::processarRetorno()` quando cstat 100/215/etc
5. **Alterar APENAS** `useNfceStatus.ts` interno: trocar fetch poll por subscribe Centrifugo client
6. Componentes (`NfceStatusBadge`, Page demo, qualquer Page consumidora) **não mudam**

Esse plano de migração é o motivo de o hook ser o ponto de abstração. Fora isso, polling é trivial de manter e debugar.

## Consequências

### Positivo

- UX completa hoje (não bloqueia shipping fase 2C)
- Não viola ADRs 0058/0062
- Hook reutilizável em qualquer Page Inertia (já está em demo `/nfe-brasil/transactions/{tx}/status`)
- Custo trivial: poll 2s × 30 = 30 fetches × ~5ms cada = ~150ms total CPU
- DevTools-friendly: cada fetch visível, fácil de debugar

### Negativo

- Latência mínima 2s (vs ~50ms broadcast tempo real)
- Custo de banda 30 fetches × ~500B = 15KB por venda (trivial mas não-zero)
- Caso edge: usuário deixa Page aberta com tx pendente → consome 30 polls e para. Refresh manual reset
- Quando migrar pra broadcast, código de polling fica como "dead code" — ou apaga, ou mantém como fallback graceful (decisão na hora)

## Refs

- Auto-mem: `project_nfebrasil_estado_2026_05_07.md`, `runbook_smoke_sefaz_biz1.md`
- [ADR 0058](0058-reverb-substituido-por-centrifugo-frankenphp.md) — Centrifugo > Reverb
- [ADR 0062](0062-separacao-runtime-hostinger-ct100.md) — Hostinger ≠ CT 100 runtime
- US-NFE-002 SPEC: `memory/requisitos/NfeBrasil/SPEC.md`
- PR [#203](https://github.com/wagnerra23/oimpresso.com/pull/203) — implementação fase 2C
- Pipeline ponta-a-ponta: PRs #193, #198, #200, #201, #203
