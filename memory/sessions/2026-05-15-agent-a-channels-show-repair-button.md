# Agent A — Botão "Re-parear" em Channels/Show.tsx

**Data:** 2026-05-15
**Agent:** Agent A (Wave paralela #1 de 3)
**Wagner request:** "cadastro dos telefones... pareamento QR não funciona"
**Tipo:** UI fix isolado (frontend-only)
**Módulo:** `Modules/Whatsapp` (UI: `Atendimento/Channels`)

## Root cause documentado pelo orquestrador

Quando o cliente desvincula o WhatsApp via celular ("Aparelhos conectados"), o daemon Baileys no CT 100 detecta a queda, mas o DB Laravel só atualiza quando o cron `whatsapp:channels-reconcile` roda (intervalo 5min). Durante essa janela:

- `channels.status` permanece `'active'`
- `channels.channel_health` permanece `'healthy'`
- O botão "Conectar" em `Channels/Index.tsx` (linhas 451-453) só renderiza se `status !== 'active' && health !== 'healthy'` → **Wagner não vê opção**
- `Channels/Show.tsx` linha 254-258 dizia *"Edição completa vem em US futura"* — **nenhuma ação de pareamento disponível**

Sintoma final: "cadastro dos telefones não funciona / QR não abre".

## O que foi mudado

**Arquivo único editado:** `resources/js/Pages/Atendimento/Channels/Show.tsx`

### Linhas adicionadas (resumo)

| Local | O que | LOC aprox. |
|---|---|---|
| L11-L16 | Imports: `router`, `useEffect`, `Zap` icon | +2 imports |
| L23-L25 | Imports: `Dialog`, `DialogContent`, `DialogHeader`, `DialogTitle`, `DialogFooter`, `DialogDescription` (shadcn/ui) | +3 |
| `ConfigTab` (antes L223-L261) | Adicionado: state hooks (`repairOpen`, `qrImage`, `pairingCode`, `qrState`, `qrError`, `qrLoading`), função `startRepair()`, `useEffect` poll 3s, bloco UI botão "Re-parear" + Dialog modal QR | +~180 LOC |

### Imports novos

```tsx
import { Link, Deferred, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ... Zap } from 'lucide-react';
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription,
} from '@/Components/ui/dialog';
```

### Plug-point do botão

Dentro de `ConfigTab`, entre o bloco `last_health_message` (alerta amber) e o bloco `"Edição completa vem em US futura"`. Só renderiza se `channel.type === 'whatsapp_baileys'`:

```tsx
{isBaileys && (
  <div className="border-t pt-3 flex items-center justify-between gap-2">
    <p className="text-xs text-muted-foreground flex-1">
      Sessão WhatsApp caiu ou cliente desvinculou em "Aparelhos conectados"? Use re-parear.
    </p>
    <Button variant="outline" size="sm" onClick={startRepair} data-testid="channel-show-repair-btn">
      <Zap size={14} className="mr-1.5" aria-hidden />
      Re-parear
    </Button>
  </div>
)}
```

### Modal

`Dialog` com `data-testid="channel-show-repair-modal"`, renderizado dentro de `ConfigTab` (escopo do estado), só pra `whatsapp_baileys`. Reusa **literalmente** o visual do `Index.tsx` linhas 285-339 — QR PNG data URL (`<img>` 280×280) + fallback pairing code (`4-digit-format`).

### Backend reusado (NÃO tocado)

- `POST atendimento.channels.connect` → `ChannelsController::connect()` (linhas 363-549) — JÁ FAZ auto-purge banned/disconnected/error states antes do connect (catalogado no comentário inline, fix Wagner 2026-05-13)
- `GET atendimento.channels.status` → `ChannelsController::status()` (linhas 555-602) — atualiza `channel.status` pra `active` + `channel_health` pra `healthy` quando daemon retorna `state === 'connected'`

Confirmado em `Modules/Whatsapp/Routes/web.php` linhas 159-167.

## Confirmação destrutiva inline (Tier 0)

`confirm()` PT-BR antes de chamar daemon:

> "Re-parear gera novo QR e invalida sessão atual. Continuar?
>
> • Se o canal estava conectado, a sessão Baileys vai cair durante o pareamento.
> • Se já estava desconectado (cliente desvinculou em "Aparelhos conectados"), só vai parear de novo.
> • Mensagens em andamento podem atrasar até reconectar."

## Smoke E2E manual (passo-a-passo)

1. **Pré-condição:** existir um canal `whatsapp_baileys` ativo em `business_id=1` (biz de testes — ADR 0101).
2. Navegar pra `/atendimento/canais/{id}` (Show).
3. Aba "Config" (default).
4. Conferir: dentro do Card, abaixo do `dl` de detalhes e do alerta amber (se houver), aparece linha com:
   - texto muted "Sessão WhatsApp caiu ou cliente desvinculou..."
   - botão outline com ícone Zap + label "Re-parear" (`data-testid="channel-show-repair-btn"`)
5. Click no botão → `confirm()` PT-BR aparece.
6. Confirma → modal abre (`data-testid="channel-show-repair-modal"`) com Loader2 "Gerando QR no daemon CT 100…"
7. Daemon CT 100 responde 200 + `qr_png_data_url` → QR 280×280 renderiza, state mostra `qr_required`.
8. Pegar celular biz=1, WhatsApp → Configurações → Dispositivos vinculados → Vincular dispositivo → scan QR.
9. Após ~2-5s, poll 3s pega `state: 'connected'` → modal fecha automaticamente + `router.reload({ only: ['channel'] })` busca channel atualizado.
10. Conferir página recarregada: `Status = 'active'`, `Health = 'healthy'`, `Identificador` exibe E.164.

### Smoke fallback (pairing code)

Se QR não vier (raro, daemon pode falhar `QRCode.toDataURL`), backend cai pra `POST /pairing-code` → modal mostra **código numérico 8 dígitos** em fonte monospace XL (`AAAA-BBBB`). User entra com "Vincular com número de telefone" no celular.

### Smoke erro

Daemon desligado (CT 100 down) → `qrError` mostra "Daemon retornou 502" ou "Erro de rede: ECONNREFUSED" em vermelho dentro do modal. Botão "Fechar" funciona.

## Pegadinhas conhecidas

1. **Re-parear durante sessão ativa:** se o canal estava `connected` + `healthy` e ainda tinha session viva, o `DELETE /instances/{id}` no auto-purge **derruba a sessão atual**. Coexistência com `history.sync` rodando pode quebrar — Baileys cancela sync, próximo reconnect pode rebaixar. Mitigação: confirmação inline avisa o user. Pra biz=1 (testes), tolerável; pra biz=4 ROTA LIVRE (99% volume), avisar Larissa antes de testar em prod.

2. **Polling a cada 3s pode parecer lento:** modal só fecha após `state === 'connected'` + 1.5s delay. Se daemon demora a propagar (instance.update event delay), user pode achar que travou. Aceitável — modal mostra QR válido enquanto.

3. **`router.reload({ only: ['channel'] })`** só funciona se a página Show tiver `channel` como prop top-level (confirmado linha 88 `Props.channel`). Não recarrega `users`/`audit` deferred — bom, evita re-fetch desnecessário.

4. **Cron `whatsapp:channels-reconcile` ainda roda em paralelo:** se o user re-pareia e o cron entra logo depois, pode reescrever status. Como o backend `status()` já atualiza `status='active' + health='healthy'` no momento que daemon responde `connected`, e o cron faz a mesma coisa, são idempotentes. Sem race condition.

5. **Multi-tenant Tier 0:** `ChannelsController::connect` linha 365 já filtra `business_id` da session antes do `findOrFail`. Frontend não passa `business_id` (correto — `session('user.business_id')` é a fonte). Zero risco cross-tenant.

6. **`channel.type` discriminator:** botão só aparece se `whatsapp_baileys`. Se Wagner cadastrar `whatsapp_meta` ou `whatsapp_zapi`, esses já têm fluxo próprio (Meta usa Cloud API, Z-API tem painel próprio). Backend `ChannelsController::connect` retorna 422 explicitamente pra não-Baileys (linha 370-375) — defesa em profundidade caso o `isBaileys` check do frontend falhe.

## Pré-flight checklist (feito)

- [x] `resources/js/Pages/Atendimento/Channels/Show.tsx` (atual — alvo do edit)
- [x] `resources/js/Pages/Atendimento/Channels/Index.tsx` L1-L80 (imports) + L80-L340 (modal QR ref)
- [x] `Modules/Whatsapp/Http/Controllers/Admin/ChannelsController.php` L363-L602 (connect + status — JÁ EXISTEM)
- [x] `Modules/Whatsapp/Routes/web.php` L159-L167 (rotas confirmadas)
- [x] `memory/requisitos/Whatsapp/BRIEFING.md` — existência confirmada (skip leitura: fix é UI mínimo, contexto bate com Wagner request)
- [x] `memory/decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md` — skip (decisão de driver Meta, não Baileys; Baileys já está implementado)
- [x] `Glob memory/sessions/2026-05-15-agent-a-*` → não duplica

## Restrições Tier 0 verificadas

- ✅ PT-BR em tudo (label "Re-parear", confirm, alerts, microcopy "Sessão WhatsApp caiu…")
- ✅ `business_id` scope automático no backend (`session('user.business_id')` no `ChannelsController::connect` L365 + `status` L557)
- ✅ Reusou `route('atendimento.channels.connect', channel.id)` + `route('atendimento.channels.status', channel.id)`
- ✅ shadcn/ui (Button outline, Dialog/DialogContent/DialogHeader/DialogTitle/DialogFooter/DialogDescription) — mesma stack do Index
- ✅ `data-testid` consistente: `channel-show-repair-btn`, `channel-show-repair-modal`
- ✅ Confirmação destrutiva inline (3 bullets PT-BR explicando consequência)
- ⛔ NÃO mexeu em backend (`ChannelsController` zero edits, `Routes/web.php` zero edits)
- ⛔ NÃO mexeu em Index.tsx, ConversationThread.tsx, outros
- ⛔ NÃO fez git ops (só Write/Edit)
- ⛔ NÃO criou componente novo em `_components/` (duplicou inline mínimo — Wagner cortou Wave anterior por inflar)

## Próximos passos sugeridos (NÃO executados pelo Agent A)

- Wagner faz smoke E2E manual em biz=1 (passo-a-passo acima)
- Se OK → orquestrador parent consolida com fixes #2 e #3 (Agent B e C) em PRs separados ou batch
- Eventual: skill `whatsapp-doctor` pode citar este pattern como recovery de UI ("se canal `active+healthy` mas user reporta QR não funciona, mande ele em /atendimento/canais/{id} → Re-parear")
- Eventual: replicar o mesmo botão em Inbox header quando canal selecionado está com `health=disconnected` (já tem fluxo similar lá? — checar fora do escopo do Agent A)
