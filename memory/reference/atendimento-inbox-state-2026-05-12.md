---
name: Estado funcional Atendimento Whatsapp 2026-05-12
description: O que faz e o que não faz pós-CYCLE-05 (11 PRs + #685/#686/#687/#688/#692 PR-rush 2026-05-12); auto-link Contact CRM = MANUAL (32 convs biz=1 com linked=0); ordenação por last_message_at mistura inbound+outbound; backlog WA-041/047/048/049/050/051/052/058/059
type: reference
---
# Atendimento Whatsapp — estado funcional 2026-05-12

## Funciona

- **Inbox `/atendimento/inbox`** — schema omnichannel polimórfico (Channel + Conversation + Message — ADR 0135)
- **Canais `/atendimento/canais`** + Show.tsx com tabs Config/Usuários/Histórico (US-WA-068)
- **Templates Jana `/atendimento/canais/jana-templates`** — toggle bot + 4 templates HSM
- **Notas internas** estilo Chatwoot — toggle Reply/Note + render amarela centralizada (US-WA-071, ADR 0142)
- **Slash commands em notas**: `/lembrar` `/corrigir` `/lembrete` `/config bot=off` (US-WA-074..077)
- **Tab Usuários canal** — ACL via `channel_user_access` (US-WA-068) + filtragem inbox (US-WA-069)
- **Gate Tier 0** — notas internas NUNCA vazam pro driver
- **Permissions Spatie** registradas + atribuídas a Admin#{biz} (PR #665)
- **Mídia inbound webhook** detecta tipo + extrai `mimetype`/`fileLength`/`seconds` do payload aninhado (PR #664)
- **Mic recorder** no composer (MediaRecorder API → blob OGG → POST /send-media — PR #664)
- **Daemon CT 100** `/media/decrypt-url` endpoint deployado (PR #669)

## Funciona PARCIAL

- **Mídia inbound visual** — UI mostra "Áudio · 27s · aguardando download" em vez de `[mídia]` genérico (B2 PR #664). Mas áudio NÃO toca ainda — `media_url` continua null pois nenhum decrypt pipeline foi rodado. Vai funcionar quando guardião 6 camadas + backfill rodar.
- **Mídia outbound** — `SendMediaJob` chama daemon mas envia chave `mime` (schema antigo). Daemon Zod espera `mimetype`. Fix 1 linha pendente.

## NÃO funciona

- **Auto-link Contact CRM por phone** — botão "Vincular contato" só MANUAL (US-WA-064). 32 conversas biz=1 estão `linked=0`. Falta:
  - Webhook auto-link: ao receber, `Contact::where('mobile|landline','LIKE','%phone%')->where('business_id', $biz)->first()` → linka
  - Botão "Cadastrar como contato" inline na sidebar direita
- **Ordenação inbox** — hoje `orderByDesc('last_message_at')` (qualquer direção). Se Wagner preferir "última mensagem do CLIENTE", trocar pra `last_inbound_at` (campo existe).
- **Outbound drivers polimórfico** (Z-API/Meta) — US-WA-058/059 pendentes. Hoje só Baileys outbound.
- **Métricas custo/deflection/tempo** — US-WA-041 backlog.
- **Tags/quick-replies/botões interativos** — US-WA-045..048 backlog.

## Conversations em prod biz=1 (2026-05-12)

- Total: 32
- Linked Contact: 0
- 89 messages sem body (mídia) — todas com `media_url=null` (aguardando decrypt pipeline)

## Estado daemon CT 100

- Container `whatsapp-baileys` Up healthy
- Erros em log: `MessageCounterError` (signal desync — chip canal #3 "Suporte" precisa reconectar QR?)
- Endpoints prontos: `/text`, `/media`, `/media/decrypt-url`, `/instances/:id/connect|status`
