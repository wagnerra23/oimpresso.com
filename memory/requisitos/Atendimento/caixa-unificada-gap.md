---
tela: /atendimento/caixa-unificada
prototipo: prototipo-ui/prototipos/caixa-unificada/ (inbox-page.jsx 1141 ln + inbox-page.css 2336 ln + inbox-ai/cur/extras/out.jsx)
tela_viva: resources/js/Pages/Atendimento/CaixaUnificada/Index.tsx (+ _components ComposerV4/ConversationListV4/ConversationThreadV4/ContextSidebarV4/ChannelsDrawer/QueuesSheet/BroadcastSheet/ChannelHealthBanner/ReconnectModal/...)
paridade_atual: "100%+ (tela viva ULTRAPASSOU o protótipo)"
gerado_em: "2026-06-23"
governanca:
  - "LEI 2026-06-18 (charter): A Caixa é o OURO — NÃO repintar. Mudança visual exige prova diff de computed-style = 0. 'Aplicar o design' = extrair o DS DELA pras OUTRAS telas, NUNCA mexer aqui."
  - "LEI 2026-06-18 (charter): O verde do WhatsApp fica (token de canal --ch-wa)."
  - "Tier 0 multi-tenant ADR 0093 IRREVOGÁVEL — qualquer payload novo escopa por business_id."
  - "ADR 0114 (Cowork loop) · 0107 (visual gate F3) · 0135 (omnichannel) · 0267 (filas DB) · 0268 (broadcast)."
  - "anti M-AP-2 (LICOES_F3): não dar tom de completude em WIP; placeholders honestos."
related_charter: resources/js/Pages/Atendimento/CaixaUnificada/Index.charter.md (v19, status live, cutover 2026-05-15)
---

# GAP-SPEC — Caixa Unificada (protótipo Cowork vs tela viva V4)

> **Mapeamento READ-ONLY (Fase 1 `aplicar-prototipo`).** Não aplica nada.
> **Conclusão de cabeçalho:** a tela viva **passou o protótipo**. O protótipo
> data de 2026-05-15 (último sync, PR-D @88% — comentário no topo de
> `inbox-page.jsx`). Desde então a tela recebeu Ondas 1–4, Waves 1–5 e PR-1..PR-10
> (charter v2→v19) que **realizam tudo que o protótipo tem e MUITO mais**.
> Logo, o protótipo NÃO é fonte — é, no máximo, backlog de *catch-up* de 1 item visual.
> **NÃO PROPOR REGRESSÃO** (ex.: ressuscitar a faixa de chips removida na Onda 1).

---

## Comparação por PARTE

| Parte | O que mudou/falta (protótipo vs vivo) | Por quê | Esforço | Risco | Ação |
|---|---|---|---|---|---|
| **Header da página** | Protótipo: título "Caixa unificada". Vivo: "Atendimento" + mesmo subtítulo dinâmico (`stats`). Topnav: protótipo tem Templates(dd)+Filas+Canais+Broadcast+Troubleshooters+Trilhas+Nova; vivo tem Templates(dd Jana/HSM)+Filas+Canais+Broadcast+**Guia**(unifica Troubleshooters+Trilhas)+Nova. | Vivo **consolidou** Troubleshooters+Trilhas num só botão "Guia" (InboxGuiaDialog) e Templates já aponta rotas reais. Diferença de título é decisão [W]. | — | só-visual | **Nada** (vivo à frente; consolidação é melhoria). |
| **Faixa de chips de canais** | Protótipo TEM faixa horizontal (`om-filter` role=tablist) + sub-row de contas acima da shell. Vivo **REMOVEU** (Onda 1, 2026-06-16) e moveu Canal/Conta pro popover **Filtros** da lista. | Faixa comprimia 1280px (monitor ROTA LIVRE). Direção [W] explícita. `ChannelChipsRow.tsx` deletado (dead-code). | — | governança | **NÃO RESSUSCITAR.** Regressão proibida (decisão [W] + charter Goals). |
| **Lista de conversas** | Protótipo: dropdown 4-status + busca + linha avatar/nome/preview/assignee/unread + border-left fila + SLA pill simples. Vivo: **7 tabs canônicas** (all/unread/assigned/bot/awaiting_human/resolved/archived) em DropdownMenu + **popover Filtros** (Canal/Conta/Fila/Tags/Ordenar/Esperando-há/Sem-CRM/Janela-24h/Mídia-24h) + **SLA pill 4-estados** animado + **favoritos** (★ ordena topo) + **ChannelHealthBanner** no topo. | Wave 2/5 + Polish V2 + US-WA-308 adicionaram poder muito além do protótipo. | — | — (vivo à frente) | **Nada.** Protótipo é subconjunto. |
| **Thread (mensagens)** | Protótipo: bubbles por dia + nota interna + **cross-link `#os4821`/`#q-vendas`/`#c1`/`#a3`** (`linkifyMessage` em inbox-cur.jsx) + comentários por-msg (`MsgCommentWrap`). Vivo: bubbles+dia+nota+**MsgComments** (port 2026-06-18, localStorage v1)+lightbox+transcript+presenter+SLA header. **Falta no vivo:** o linkify de cross-refs `#xxx` dentro do texto da bubble. | MsgComments foi portado; o `linkifyMessage` (refs clicáveis no corpo da msg) é o **único** detalhe do protótipo ainda não presente na thread viva (grep confirma: só InboxGuiaDialog usa, não a renderização de bubble). | **P** | só-visual | **Catch-up opcional** (ver Ordem). Avaliar utilidade real antes — pode ser low-signal. |
| **Composer** | Protótipo: input + toggle Resp/Nota(⌘⇧N) + ⌘T templates + `/` macros + `{}` variáveis (mock client-side). Vivo: tudo isso **real** (TemplatePicker por provider, macros via `atendimento.macros.list`, vars `{{nome/telefone/operador}}` com preview verde/vermelho) **+ upload mídia** (16MB cap) **+ MicRecorder voz + InteractiveMessage (List/Button Meta)**. | US-WA-303 + Wave 4 backend real; protótipo era só visual mock. | — | — (vivo à frente) | **Nada.** |
| **Sidebar de Contexto** | Protótipo: 8 sections (Fila/Atribuído/Canal/Tags/OS/Saldo/Histórico/Último/Ações), Fila e Atribuído como `<select>` mock. Vivo: mesmas sections com **Fila override real** (US-WA-305, queue_override) + **Assignee picker real** (US-WA-302, operadores do business Tier 0) + **Saldo/Histórico reais** (US-WA-308, transactions/sells por business_id) + **CustomerMemoryBlock** (Customer 360) + IA dialog. Coluna virou **drawer lateral** (decisão [W] 2026-06-19) vs coluna fixa do protótipo. | Onda 3 deu dados reais; OS/Ações continuam placeholder honesto nos 2. | — | — (vivo à frente) | **Nada.** Drawer é decisão [W] posterior ao protótipo. |
| **Drawer Canais e contas** | Protótipo: drawer local agrupado por canal, contas ativo/em-breve, "+ Adicionar conta". Vivo: **ChannelsDrawer** (Sheet) agrupado por type + status/health + link Gerenciar + **ReconnectModal com QR REAL** (port + catraca Contrato de Tela). | US-WA-304 + reconectar in-place (2026-06-18) reusam backend real. | — | — (vivo à frente) | **Nada.** |
| **Drawer Filas** | Protótipo: drawer read-only (label/SLA/dist/members/count) com array `QUEUES` hardcoded. Vivo: **QueuesSheet CRUD** sobre tabela `whatsapp_queues` (ADR 0267) + seed lazy + tags-gatilho + default protegida. | US-WA-301 persistiu em DB. | — | — (vivo à frente) | **Nada.** |
| **Broadcast** | Protótipo: drawer mock que "dispara" sem backend. Vivo: **BroadcastSheet fase 1** real (whatsapp_broadcasts + opt-in LGPD + pre-flight janela-24h/só-HSM + draft auditável); disparo em massa = fase 2 com gate [W] (botão disabled, anti M-AP-2). | US-WA-306 / ADR 0268. Protótipo "dispara" fake = anti-pattern que o vivo corretamente NÃO copiou. | — | backend (fase 2 futura) | **Nada agora.** Fase 2 é US futura, não gap de protótipo. |
| **+ Nova conversa** | Protótipo: botão sem ação (placeholder). Vivo: **NewConversationDialog** real (find-or-create Tier 0, reabre thread, reusa pipeline send). | US-WA-307. | — | — (vivo à frente) | **Nada.** |
| **IA na thread** | Protótipo: Summarize/Ask/Suggest mock (inbox-ai.jsx). Vivo: **InboxAiController + InboxAssistAgent reais** (summarize/ask/suggest-reply, PiiRedactor LGPD, dry_run gateando custo). | US-WA-309/PR-9 com infra Jana real. | — | — (vivo à frente) | **Nada.** |
| **Atalhos / cheat-sheet / mobile / favoritos / palette** | Protótipo: J/K/E/A, ⌘K palette LOCAL, cheat `?`, mobile 3-tabs, favoritos LS, transcript, presenter, lightbox. Vivo: J/K/E/A/`/`/`?`/⌘⇧N reais; **⌘K = TODO honesto** (usa palette global PMG-002 do AppShellV2 em vez de duplicar — anti-duplicação MANUAL #5); mobile tabs, favoritos LS, transcript, presenter, lightbox (MediaFullscreenModal) todos presentes. | Polish V2 / PR-8. ⌘K do protótipo seria duplicação; vivo fez a escolha correta. | — | — (vivo à frente) | **Nada.** Não duplicar palette. |
| **Real-time / perf / Tier 0** | Protótipo: estado mock client-side (sem rede). Vivo: **Centrifugo WSS + polling 5s defensivo + preserveScroll/State + Inertia::defer + ACL canal=fila + business_id scope** (13 testes Pest R-WA-CAIXA-UNIF-001..015). | Paridade de capacidade era meta da Mission; vivo entregou. | — | — (vivo à frente) | **Nada.** |

---

## Ordem de aplicação sugerida

Como a tela viva **ultrapassou** o protótipo, NÃO há fluxo de "aplicar protótipo → tela".
A única ação real possível é 1 catch-up cosmético, e ainda assim opcional:

1. **(Opcional · P · só-visual)** Avaliar portar `linkifyMessage` (cross-refs `#os4821`/`#q-vendas`/`#c1`/`#a3` clicáveis no corpo da bubble) pra `ConversationThreadV4`. **Antes de codar**, validar utilidade real (ROTA LIVRE/Larissa usa refs em msg?) — pode ser low-signal (ADR 0105 cliente-como-sinal). Se entrar: respeitar a LEI "não repintar" (zero mudança de cor/layout; só transforma `#xxx` em botão). Reusar handlers que já existem (mover-fila, abrir-conv).
2. **Inverter o fluxo (recomendado):** o trabalho de verdade é o da LEI 2026-06-18 — **EXTRAIR o DS desta Caixa (o "ouro") pras OUTRAS telas**, com prova `diff de computed-style = 0` aqui. Isso é escopo da skill `aplicar-prototipo` em OUTRAS telas, não nesta.
3. **Re-sincronizar o protótipo (higiene de memória):** `inbox-page.jsx` ainda traz `TODO US-WA-301..307` que já foram **ENTREGUES** no vivo (charter §1–§5). O protótipo está stale como fonte. Sugestão: marcar o protótipo como "absorvido/atrás do vivo" no `prototipo-ui/SYNC_LOG.md` pra ninguém tratá-lo como fonte por engano.

---

## Veredito

**À FRENTE (tela viva V4 > protótipo).** Paridade **100%+**.

A tela viva implementa 100% das capacidades do protótipo e adiciona camadas inteiras
que o protótipo não tem (7 tabs, popover Filtros com 9 grupos, SLA 4-estados,
ChannelHealthBanner + ReconnectModal QR real, QueuesSheet CRUD em DB, BroadcastSheet
fase 1 LGPD, NewConversation find-or-create, IA real com PII redaction, upload/voz/
interactive no composer, Customer 360, Centrifugo+polling, 13 testes Pest Tier 0).

O protótipo virou **backlog de catch-up de UM item visual opcional** (linkify de
cross-refs) — e nem isso é fonte canônica de mudança, já que a **LEI "A Caixa é o
OURO — não repintar"** governa esta tela. O valor a extrair daqui é o **inverso**:
usar esta Caixa como referência de DS pras outras telas.

**Riscos Tier0/backend:** nenhum gap introduz risco. O único cuidado é **governança**
— não ressuscitar a faixa de chips removida (regressão proibida por decisão [W]) e
não repintar a tela (LEI). Broadcast fase 2 é US futura com gate [W], fora do escopo
deste mapeamento.
