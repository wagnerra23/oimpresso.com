# [CC]→[CL] · CAIXA UNIFICADA — Foto WhatsApp · verde→roxo · Status no filtro · Contexto vira drawer (4 ondas)

> **Origem:** comentários [W] 2026-06-19 na tela (Cowork `inbox-page.jsx`):
> 1. *"dá pra pegar do ícone do whatzap? acho que a foto mesmo"* (avatar da conversa)
> 2. *"a cor verde copiar da cor do git roxinha"* (accent verde do canal)
> 3. *"pode colocar no filtro, fica mais limpo o layout"* (botão Status)
> 4. *"pode sumir por completo o contexto e colocar um botão para abrir na thread"*
>
> **verificado vs main @55e06f28:** estrutura confirmada nesta sessão (helpers.ts, árvore CaixaUnificada).
> **§10.4:** valida vs main, **repo vence**. Não cunhar ADR (Tier 0 = [W]).

## Referência visual (curl — fonte canônica do protótipo, Cowork)
URLs efêmeras (~1h). Baixe os 3 e use como espelho do comportamento:
```bash
curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/prototipos/caixa-unificada/inbox-page.jsx?t=89046ccebb357daa8b4260ec129ccd7b0eb9d4334a026bcef6e497aae6684bcf.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781911098.fp&direct=1" -o /tmp/inbox-page.jsx
curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/prototipos/caixa-unificada/inbox-page.css?t=fa7d1580cb1152be2c232c71b4787ec60b2e804020cb2200b54fd5e1e77896e2.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781911099.fp&direct=1" -o /tmp/inbox-page.css
curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/prototipos/caixa-unificada/inbox-extras.jsx?t=e7762f25343e50c506d4bb375da54ba068f1cd4a73b3fdc020d27f6d314c391e.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781911099.fp&direct=1" -o /tmp/inbox-extras.jsx
```

## Blast radius (o que muda · o que NÃO muda)

| Setor | Arquivo @main | Muda? | Onda |
|---|---|---|---|
| **Catálogo de canais** | `Http/Controllers/.../CaixaUnificadaController` (array de canais) | ✅ 1 campo | B |
| **Conversas (lista)** | `_components/ConversationListV4.tsx` | ✅ avatar + header | A, C |
| **Thread** | `_components/ConversationThreadV4.tsx` | ✅ avatar + botão Contexto | A, D |
| **Contexto** | `_components/ContextSidebarV4.tsx` | ✅ vira conteúdo de Sheet | D |
| **Shell/Index** | `Index.tsx` | ✅ remove 3ª coluna + estado do Sheet | D |
| **Mobile tabs** | `_components/InboxMobileTabs.tsx` | ✅ remove aba Contexto | D |
| **Payload** | `CaixaUnificadaController` + Contact (avatar_url) | ✅ novo campo nullable | A |
| **Composer / Broadcast / Filas / Reconnect** | — | ❌ não tocar | — |

---

## ONDA A — Avatar com foto de perfil do WhatsApp (comments 1)
Hoje o avatar usa `initials(name)` sobre `avatarHue(seed)` (helpers.ts). Wagner quer a **foto real** do contato.

**Backend (US — nullable, degrada pra iniciais):**
- Expor `avatar_url: string | null` em `CaixaUnifConversation` **e** `CaixaUnifThread` (helpers.ts) — vindo da
  **profile picture** do contato WhatsApp. whatsmeow/Baileys expõe `GetProfilePictureInfo`/`profilePicUrl`;
  persistir em coluna nova (ex. `contacts.profile_pic_url`, `whatsapp_contacts.avatar_url` — onde casar com o schema)
  e popular no ingest/health-probe. Sem foto = `null`.

**Frontend (`ConversationListV4.tsx` + `ConversationThreadV4.tsx`):**
- No avatar circular: se `avatar_url` → `<img src={avatar_url} className="h-full w-full rounded-full object-cover" alt="" />`;
  senão mantém `initials()` sobre o bg `avatarHue`. **Manter** o badge de glyph de canal e o dot de presença por cima.
- Espelho visual: `.om-av-img` no `inbox-page.css` (width/height 100%, `border-radius:50%`, `object-fit:cover`).

> **NÃO** subir as PNGs do Cowork (`inbox-photo-c*.png`) — são mock de protótipo. A fonte real é a profile pic.
> **Pronto quando:** conversas com foto mostram a imagem (crop circular), sem foto caem nas iniciais; glyph+presença intactos.

## ONDA B — Verde → roxo canon (comment 2)
O verde que [W] aponta é o **hue do canal WhatsApp** (`ChannelCatalogItem.hue = 145`, definido no Controller
"mesmo array do Controller pra paridade"). Roxo canon = `oklch(0.55 0.15 295)` (`--accent` universal, §10.4).
- No catálogo de canais do `CaixaUnificadaController`: **WhatsApp `hue: 145 → 295`** (Baileys/Meta/Z-API — todos os `wa_*`).
  Isso repinta glyph/badge/chips do canal pro roxo via as fórmulas oklch existentes.
- Varrer os componentes V4: qualquer `oklch(... 145)` **cru** de accent → `295`. **Preservar** os verdes semânticos:
  `SLA fresh` (success), **presença online** e **sim/não** do troubleshooter — esses são estado, não marca.
- O chrome da tela (seleção/unread/pills) no @main já usa token `--accent` (roxo) — confirmar que não há 145 cru sobrando.
- **Dark mode:** garantir identidade dark em **roxo canon** (sem tint verde). No protótipo o tema escuro tinha hue
  cru `150/152` (verde) → agora `295`; no @main confirmar que os tokens dark (ADR 0281) não introduzem verde. SLA-fresh
  e presença **continuam verdes** também no dark.

> **Pronto quando:** badge/glyph WhatsApp roxo; nenhum accent verde cru (claro **nem** escuro); SLA-fresh e presença seguem verdes.

## ONDA C — Status entra no popover Filtros (comment 3)
Hoje o header da lista tem **2 controles** (Status + Filtros) — ver `PROMPT_PARA_CODE_CAIXA-FILTROS-2BOTOES.md`.
[W] quer **1 só botão** (Filtros) pra limpar o layout.
- Em `ConversationListV4.tsx`: remover o controle **Status** standalone; mover o status/tab pra **primeiro grupo dentro
  do Popover Filtros** (chips, espelhando os outros grupos). Badge do funil já conta status (`countActiveFilters`
  inclui `status !== 'abertas'`). "Limpar filtros" zera status junto.
- Contrato backend intacto (`tab`/`status` continua querystring; só muda a apresentação).

> **Pronto quando:** header da lista com **1 botão (funil + badge)**; Status é grupo no popover; URL-sync preservado.

## ONDA D — Contexto vira drawer (comment 4)
Remover a **coluna fixa** `ContextSidebarV4` e abrir o contexto por **botão na thread**.
- `Index.tsx`: shell passa de 3 → **2 colunas** (lista + thread). Remover a 3ª coluna e a lógica de
  colapsar/expandir (`CU_LS.SIDEBAR_COLLAPSED` vira dead-code → remover).
- `ConversationThreadV4.tsx`: no header da thread, botão **"Contexto"** (ícone info `lucide` `Info`) que abre um
  **`Sheet` shadcn (lado direito)** com o conteúdo atual do `ContextSidebarV4` (Fila/Atribuído/Canal/Saldo/Histórico/
  Ações + IA). `ContextSidebarV4` permanece como componente, agora renderizado **dentro do Sheet**, não como coluna.
- `InboxMobileTabs.tsx`: **remover a aba "Contexto"** (fica só Conversas/Thread); no mobile o Sheet abre full-width.
- Espelho: `.om-ctx-open-btn` (botão na thread) + drawer reusa o padrão `om-drawer`/`Sheet`.

> **Pronto quando:** sem coluna de contexto; botão "Contexto" na thread abre Sheet à direita; mobile sem aba Contexto;
> nada do conteúdo de contexto perdido.

---

## Resumo de ondas (todas autônomas/mergeáveis)
- **A** — avatar com foto (backend `avatar_url` + `<img>` fallback em List/Thread).
- **B** — WhatsApp `hue 145→295` no Controller + varrer accent verde cru (preservar SLA/presença/sim-não).
- **C** — Status some como botão; vira grupo no Popover Filtros (1 botão no header).
- **D** — Contexto deixa de ser coluna; botão na thread abre Sheet; mobile sem aba.

Ao terminar cada onda: marcar `[PROCESSADO AAAA-MM-DD]` aqui + retorno em `CODE_NOTES.md`. Cowork é read-only no git.
