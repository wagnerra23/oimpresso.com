# [CC]→[CL] · CAIXA UNIFICADA — Filtros em 2 botões + remover faixa de canais (EM ONDAS, por setor)

> **Origem:** comentários [W] 2026-06-16 na tela (Cowork `inbox-page.jsx`): "quero ícone de filtro,
> não gostei dos filtros abertos na tela" + "troque a linha pro filtro, do lado da conversa".
> **verificado vs main:** `Index.tsx` lido nesta sessão (2026-06-16). Estrutura real confirmada abaixo.
> **AUTO-CONTIDO** — referência visual = `prototipo-ui/prototipos/caixa-unificada/inbox-page.jsx`
> (om-list-h-tools · om-pop-float · om-flt-pill · om-funnel). §10.4: valida vs main, repo vence.

## Mapa por setor — o que muda e o que NÃO muda (blast radius honesto)

| Setor | Arquivo @main | Muda? | O quê |
|---|---|---|---|
| **Header** | `Index.tsx` (bloco header) | ❌ não | Título + subtítulo + topnav (Templates/Filas/Canais/Broadcast/+Nova) ficam. Não tocar. |
| **Filtros** | `Index.tsx` + `ConversationListV4.tsx` | ✅ **sim** | Núcleo da entrega — ver Ondas 1–2. |
| **Conversas** | `ConversationListV4.tsx` (lista) | ⚠ só o header | Os 2 botões entram no header da coluna. **Itens da lista não mudam** (avatar, glyph de canal, SLA, unread, assignee). |
| **Thread** | `ConversationThreadV4.tsx` | ❌ não | Dark-mode já landou (ADR 0281). Validar vs main, não tocar. |
| **Contexto** | `ContextSidebarV4.tsx` | ❌ não | Sem mudança nesta leva (assignee picker US-WA-302 já tem `availableAssignees`). Não tocar. |

---

## ONDA 1 — Header/Index: remover a faixa horizontal de canais (1 PR)
**Arquivo:** `resources/js/Pages/Atendimento/CaixaUnificada/Index.tsx`
- Remover o bloco `<Deferred ...><ChannelChipsRow .../></Deferred>` que renderiza a faixa de canais
  ACIMA da shell 3-col (entre o header e `<InboxMobileTabs>`).
- **Manter o fetch** de `availableChannels`/`availableAccounts` (a lista e o popover de Filtros usam).
- `ChannelChipsRow.tsx` provavelmente vira dead-code → se nada mais importa, remover no mesmo PR (rodar grep).
- **Pronto quando:** topo da tela sem a faixa; `channelTypeFilter`/`accountFilter` continuam vindo da URL (não quebrar o contrato — só não renderizar a faixa).

## ONDA 2 — Filtros/Conversas: header da lista vira 2 controles (1 PR) — APÓS decisão [W] abaixo
**Arquivo:** `resources/js/Pages/Atendimento/CaixaUnificada/_components/ConversationListV4.tsx`
Substituir o cluster de filtros do header da lista por **2 controles** (espelhar `inbox-page.jsx`):
1. **Status** — `DropdownMenu` (shadcn, já importado no Index) com o `tab`/`statusFilter`
   (7-valor: Todas/Não lidas/Minhas/Bot/Aguardando/… ou os 4 canônicos). Label = status atual + chevron.
2. **Filtros** — botão com **ícone de funil** (`lucide-react` `Filter`) + **badge** de filtros ativos →
   abre **`Popover` shadcn FLUTUANTE** (não empurra a lista; fecha clicando fora). Grupos dentro:
   - **Canal** (`channelTypeFilter`) — chips com glyph + contagem (migrado da faixa removida na Onda 1)
   - **Conta** (`accountFilter`) — só quando o canal tem 2+ contas
   - **Fila** (`queueFilter`) — chips com dot de hue
   - **Atribuição** (assignee: Todas/Atribuídas/Sem dono)
   - rodapé **"Limpar filtros"**

### ✅ DECISÃO [W] 2026-06-16 — Onda 2 DESBLOQUEADA: opção (a) ABSORVER TODOS no popover Filtros
Wagner escolheu **(a)**: os power-filters do `@main` entram **no mesmo popover Filtros**, como grupos.
Ordem dos grupos no popover (espelhar `inbox-page.jsx`, que já demonstra Canal/Conta/Fila/Atribuição/Tags/Ordenar):
1. **Canal** (`channelTypeFilter`) · 2. **Conta** (`accountFilter`, quando 2+) · 3. **Fila** (`queueFilter`)
4. **Atribuição** (assignee) · 5. **Tags** (`activeTagIds`) · 6. **Ordenar por** (`orderBy`: Última msg / inbound)
7. **Janela 24h** (`within24h`) · 8. **Sem CRM** (`unlinked`) · 9. **Mídia 24h** (`mediaInbound24h`)
10. **Esperando há** (`inboundAging`: 6h/12h/24h/48h/7d)
Toggles booleanos (Janela 24h / Sem CRM / Mídia 24h) = pills on/off; `inboundAging` = pills de faixa; `orderBy` = pills.
**Protótipo cobre 1–6 funcionalmente** (referência visual do padrão). **7–10 o Code mapeia** dos params que já existem
no `@main` (mock do Cowork não tem timestamp/mídia). Rodapé "Limpar filtros" zera todos. Badge conta todos os ativos.

**Contrato backend intacto:** `channel`, `account_id`, `tab`, `q` (+ os power-filters) continuam querystring.
O popover só muda a APRESENTAÇÃO; `router.get` do `selectThread` já relê esses params. Não mexer no backend.

**Pronto quando:** header da lista com 2 controles; popover flutua (não empurra); faixa de canais ausente;
filtros ativos refletidos no badge; URL-sync preservado; screenshots @1280/@1440; sem regressão de teclado (J/K/E/A/"/").

---

## Resumo de ondas
1. **Onda 1** (`Index.tsx`) — remover faixa ChannelChipsRow. Autônoma, mergeável.
2. **Onda 2** (`ConversationListV4.tsx`) — 2 controles + popover flutuante. **DESBLOQUEADA — [W] escolheu (a):** absorver TODOS os power-filters no popover (grupos: Canal/Conta/Fila/Atribuição/Tags/Ordenar/Janela 24h/Sem CRM/Mídia 24h/Esperando há).
3. Header / Thread / Contexto — **não tocar** nesta leva.

Não cunhar ADR (Tier 0 = [W]). Ao terminar cada onda: `[PROCESSADO AAAA-MM-DD]` aqui + retorno em `CODE_NOTES.md`. Cowork read-only no git.
