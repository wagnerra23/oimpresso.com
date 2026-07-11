---
page: /atendimento/canais/{id}
component: resources/js/Pages/Atendimento/Channels/Show.tsx
related_prototype: n/a (herda PT-03 Detalhe; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Whatsapp
related_us: [US-WA-068]
related_adrs: [114, 101, 93, 135]
tier: B
charter_version: 1
---

# Page Charter — /atendimento/canais/{id} (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Whatsapp/Http/Controllers/Admin/ChannelsController@show` (rota `atendimento.channels.show`, permissão `whatsapp.settings.manage`). Detalhe de um canal omnichannel (WhatsApp Baileys/Z-API/Meta ou outro plug) com abas Config, Usuários e Histórico.

---

## Mission
Dar ao gestor do atendimento a visão completa de um canal — configuração, saúde da conexão, quem tem acesso e o histórico de grants — a partir da lista de canais. É onde se diagnostica uma queda de sessão e se re-pareia um WhatsApp Baileys sem depender do daemon manual, além de auditar concessões de acesso ao canal.

---

## Goals — Features (faz)
- Mostra os detalhes do canal em `<dl>` (apelido, tipo, status, health, identificador, UUID, LGPD aceito, bot habilitado) na aba Config.
- Exibe a última mensagem de health check quando presente (alerta visual).
- Aba Usuários (deferred): lista quem tem acesso ativo ao canal e usuários disponíveis pra conceder acesso, via `ChannelUsersTab`.
- Aba Histórico (deferred): tabela append-style de grants/revokes (quem concedeu/revogou e quando), com badge ativo/revogado.
- Para canais `whatsapp_baileys`, botão "Re-parear" sempre visível — gera novo QR/pairing code chamando `atendimento.channels.connect` e faz poll de status via `atendimento.channels.status` (3s) até `connected`, então recarrega só `channel`.
- Botão "Voltar" pra lista de canais (`atendimento.channels.index`).

---

## Non-Goals — Features (NÃO faz)
- ❌ Edição inline completa do canal (o próprio código diz "Edição completa do canal vem em US futura") — inferência pendente de Wagner.
- ❌ Remoção do canal por esta tela (delega pra lista) — inferência pendente de Wagner.
- ❌ Não expõe canais de outro `business_id` — `findOrFail` scopado retorna 404 cross-tenant (Tier 0 ADR 0093).
- ❌ Não gerencia templates/macros/mensagens do canal aqui.

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 quando aplicável

---

## Automation hooks (faz)
- `channel`, `users`, `availableUsers` e `audit` carregados via `Inertia::defer` no controller — listas pesadas com joins só executam quando a aba correspondente pede (skill `inertia-defer-default`, D-14).
- Re-parear dispara o daemon Baileys (CT 100) via endpoint `connect`, com auto-purge de sessão banida.
- Poll de status a cada 3s enquanto o modal de re-pareamento está aberto; encerra e recarrega ao detectar `connected`.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não faz poll de status fora do modal de re-pareamento aberto.
- ❌ Re-parear exige confirmação explícita (`confirm`) — não invalida a sessão atual sem o usuário aceitar o aviso.
- ❌ Não concede/revoga acesso de usuário automaticamente — sempre ação manual na aba Usuários.
- ❌ Não muta dados em GET; as abas só leem (grants/revokes têm endpoints próprios).

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Confirmar comportamento de re-parear em canal já conectado vs desvinculado (aviso do `confirm`)
