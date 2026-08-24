# [CC]→[CL] · Caixa Unificada — Saúde de canal: fechar Onda 2 + Onda 3

> **Auto-contido, sem URL.** Cole UMA vez no Claude Code. Regra do loop (§10.4): **valide vs `main` antes de codar**;
> **1 onda = 1 PR**; não cunhar ADR (Tier 0 = [W]); reportar cada PR em `CODE_NOTES.md`.

## Estado real (✓lido @main `e2752b3b8ddb`, 2026-06-18, [CC] — arquivos abertos)

**O banner JÁ LANDOU** — e além do que o handoff anterior propunha:
- `_components/ChannelHealthBanner.tsx` **existe**, alimentado pela prop **eager `unhealthyChannels`** (`helpers.ts UnhealthyChannel`), convergida pelo cron **`whatsmeow:health-probe`** (incidente **US-WA-308**). Ou seja, o agregado de backend (a "Onda 4 opcional" do handoff velho) também está feito.
- Estados REAIS de health: **`disconnected`** / **`banned`** (fora do ar → `err`) e **`degraded`** (instável → `warn`). `healthy`/`never_checked` = ok. **Não existe estado `down`** (o handoff antigo usava `down` — descartar).

**O que falta** (medido agora):
1. **Onda 2** — `ComposerV4.tsx` bloqueia em `isPreview`/`isBlocked` mas **ignora `channel_health`**: dá pra digitar e "enviar" num canal caído (a msg não sai). `ConversationThreadV4.tsx` também não mostra saúde no header.
2. **Onda 3** — `ChannelsDrawer.tsx` tem só engrenagem ⚙️ → config (sem "Reconectar" explícito) **e** seu `HEALTH_LABELS` está **stale**: mapeia `healthy/degraded/down/never_checked` — não conhece `disconnected`/`banned`, então a linha cai pro texto cru.

**Tokens:** usar os semânticos já no repo: `text-warning-fg` / `text-destructive-fg` / `bg-warning-soft` / `bg-destructive-soft` / `border-*/40`. **Não** inventar oklch — flipam no dark (ADR 0281, R1).

> ⚠️ **NÃO reinventar QR (✓lido @main `Channels/Show.tsx`):** o re-pareamento via **QR real** já existe — `ConfigTab` tem o botão "Re-parear" (Baileys) que chama `atendimento.channels.connect` (QR `qr_png_data_url` + pairing-code + polling `channels.status` 3s, D-15). Reconectar destas ondas deve **navegar pra `route('atendimento.channels.show', id)`** (cai nesse fluxo), **não** construir um modal de QR novo na Caixa. O modal QR do protótipo Cowork é só exploração visual.

---

## Onda 0 — inventário (sem PR)
```
grep -rn "channel_health\|UnhealthyChannel\|HEALTH_LABELS" resources/js/Pages/Atendimento/CaixaUnificada
php artisan route:list | grep -i "atendimento.channels.show"
```
A rota de reconectar é a mesma que o `ChannelHealthBanner`/`ChannelsDrawer` já usam: `route('atendimento.channels.show', id)`. **Não inventar endpoint.**

---

## Onda 2 — PR-1 · Thread sabe da saúde + composer pausa no canal caído. Zero backend.

### a) `_components/helpers.ts` — helpers de saúde PARTILHADOS (estender, não reinventar)
Hoje os estados estão inline no banner e stale no drawer. Centralizar (banner pode migrar depois; aqui é só adição):
```ts
/** US-WA-308 — estados de saúde de canal partilhados (thread/composer/drawer/banner). */
export const CHANNEL_DOWN_STATES = new Set(['disconnected', 'banned']);
export function isChannelDown(health: string | null | undefined): boolean {
  return !!health && CHANNEL_DOWN_STATES.has(health);
}
export function isChannelUnhealthy(health: string | null | undefined): boolean {
  return !!health && health !== 'healthy' && health !== 'never_checked';
}
export const CHANNEL_HEALTH_LABEL: Record<string, string> = {
  healthy: 'saudável',
  degraded: 'instável',
  disconnected: 'fora do ar',
  banned: 'bloqueado pela Meta',
  never_checked: 'sem verificação',
};
```

### b) `_components/ConversationThreadV4.tsx` — marcador no header + passar pro composer
Import:
```ts
import { /* …já tem… */ isChannelDown, isChannelUnhealthy, CHANNEL_HEALTH_LABEL } from './helpers';
```
No bloco `<div className="flex items-center gap-1.5 text-[11px] text-muted-foreground mt-0.5">` do header, **logo após** o `{thread.channel_handle && (…)}`:
```tsx
{isChannelUnhealthy(thread.channel_health) && (
  <>
    <span className="text-border">·</span>
    <span
      className={cn('font-semibold', isChannelDown(thread.channel_health) ? 'text-destructive-fg' : 'text-warning-fg')}
      title={isChannelDown(thread.channel_health)
        ? 'Canal fora do ar — envio pausado'
        : 'Canal instável — sincronização lenta'}
      data-testid="caixa-unif-thread-health"
    >
      ● {CHANNEL_HEALTH_LABEL[thread.channel_health] ?? 'fora do ar'}
    </span>
  </>
)}
```
No `<ComposerV4 … />` do rodapé, adicionar a prop:
```tsx
channelHealth={thread.channel_health}
```

### c) `_components/ComposerV4.tsx` — bloqueio honesto SÓ quando caído
Import: `import { isChannelDown } from './helpers';`
Na interface `Props`, adicionar: `channelHealth?: string;`
Na assinatura/destructuring do componente, receber `channelHealth`.
Logo antes de `const canType = …`:
```ts
const channelDown = isChannelDown(channelHealth);
```
Dobrar no gate existente (degradado segue enviando — é só lento; nota interna sempre permitida):
```ts
const canType = !(isPreview && !internalMode) && !isBlocked && !(channelDown && !internalMode);
```
No `placeholder`, adicionar o ramo de canal caído (antes do ramo `isPreview`):
```ts
const placeholder = isBlocked
  ? 'Contato bloqueado — envio desabilitado'
  : internalMode
    ? 'Nota interna · só pra equipe (⌘⇧N pra voltar)'
    : channelDown
      ? 'Canal fora do ar — reconecte pra enviar (rascunho salvo)'
      : isPreview
        ? `${channelShort} em homologação — envio bloqueado`
        : `Responder via ${channelShort}${channelLabel ? ` · ${channelLabel}` : ''}`;
```
Aviso acima da barra do composer (espelha o de `mediaError`), **só** quando `channelDown && !internalMode` — colocar junto dos outros banners condicionais do topo do JSX:
```tsx
{channelDown && !internalMode && (
  <div
    className="border-t border-destructive/30 bg-destructive-soft text-destructive-fg px-3.5 py-2 text-[11.5px]"
    role="status"
    data-testid="caixa-unif-composer-channel-down"
  >
    <b className="font-semibold">Envio pausado — canal fora do ar.</b>{' '}
    Reconecte o canal pra enviar; troque pra <b>Nota</b> (⌘⇧N) se quiser registrar algo interno.
  </div>
)}
```
**Pronto quando:** thread de canal `disconnected`/`banned` mostra `● fora do ar` no header + composer bloqueado com aviso (nota interna ainda permitida); `degraded` mostra `● instável` mas envia normal; `healthy`/`never_checked` não mostram nada. `tsc --noEmit` limpo, dark-mode legível. Sem regressão em R-WA-CAIXA-UNIF-001/002.

---

## Onda 3 — PR-2 · Drawer "Canais": corrigir labels + Reconectar explícito. Zero backend.
**Depende do helper da Onda 2-a.** Se a Onda 2 ainda não landou, traga o bloco `helpers.ts` junto.

`_components/ChannelsDrawer.tsx`:
- **Apagar** o `const HEALTH_LABELS = { … }` local (stale, sem `disconnected`/`banned`) e importar `CHANNEL_HEALTH_LABEL` + `isChannelUnhealthy` de `./helpers`. Trocar os usos de `HEALTH_LABELS` por `CHANNEL_HEALTH_LABEL`.
- Na linha da conta (`caixa-unif-drawer-acc-{id}`), quando `acc.status === 'ativo' && isChannelUnhealthy(acc.channel_health)`, **somar** ao `Inline` de ações um botão Reconectar antes da engrenagem (a engrenagem fica):
```tsx
{acc.status === 'ativo' && isChannelUnhealthy(acc.channel_health) && (
  <button
    type="button"
    onClick={() => router.visit(route('atendimento.channels.show', acc.id))}
    className="inline-flex items-center gap-1 rounded-md border border-warning/40 bg-warning/15 px-2 py-0.5 text-[10.5px] font-semibold text-warning-fg hover:bg-warning/25"
    title="Reconectar canal"
    data-testid={`caixa-unif-drawer-reconnect-${acc.id}`}
  >
    <RefreshCw size={11} aria-hidden /> Reconectar
  </button>
)}
```
Imports a somar: `import { router } from '@inertiajs/react';` e `RefreshCw` no `lucide-react`.
**Pronto quando:** abrir o drawer com conta `disconnected`/`banned`/`degraded` mostra o label PT certo + botão Reconectar que leva pra `channels.show`. `tsc` limpo.

---

Comece pela Onda 2. Reporte cada PR em `CODE_NOTES.md`. **Não** cunhar ADR; **não** mexer no `ChannelHealthBanner.tsx`/backend (já live).
