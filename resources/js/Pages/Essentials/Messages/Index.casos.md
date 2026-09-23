---
id: resources-js-pages-essentials-messages-index-casos
casos: Essentials · Mural de mensagens · /essentials/messages
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável (Dado/Quando/Então)
owner: wagner
last_run: "2026-09-23"
---

# Casos de uso — /essentials/messages · Mural de mensagens

> **Status:** ✅ passa (provado no manifesto G-7) · 🧪 teste cita o UC, sem veredito ainda ·
> ⬜ não verificado · ❌ quebrou.

> Os UC derivam do **contrato** — [`Index.charter.md`](Index.charter.md) (lei) + o
> `EssentialsMessageController` real (`index` → `Inertia::render('Essentials/Messages/Index')`,
> `store`, `getNewMessages`) — **nunca** do `.tsx` nem do protótipo (§5 2026-06-05). A tela é
> mural bespoke, fora dos 5 Padrões de Tela (o próprio charter diz); os UC descrevem o
> comportamento que existe, sem reclassificar o padrão.

> ⚖️ **Onde roda.** Teste: [`tests/Feature/Essentials/MessagesIndexContratoTest.php`](../../../../../tests/Feature/Essentials/MessagesIndexContratoTest.php),
> MySQL-only (pula no SQLite). Medido 2026-09-23: o arquivo **não está** na allowlist de
> nenhuma lane de PR com MySQL — a lane natural é `PHP / Pest (Essentials · MySQL)`
> (`.github/workflows/essentials-pest.yml`), que **não** é required. Até ser listado lá, o UC
> não tem veredito de CI. Pest local é proibido ([ADR 0062](../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).

---

## UC-EMSG-01 · Enviar mensagem grava no tenant da sessão e ela entra no mural `[must]`
Status: 🧪 sem veredito
- **Persona:** operador — escreve um aviso pra equipe e o vê no mural.
- **Aceite:** Dado a tela · Quando envio `POST /essentials/messages` com `message` · Então responde redirect sem erro de validação, existe **uma** linha em `essentials_messages` com `business_id` da sessão e `user_id` meu, e a prop deferida `messages` passa a trazê-la com o meu `user_id` (o que alinha a bolha à direita).
- **Teste:** `tests/Feature/Essentials/MessagesIndexContratoTest.php` — `UC-EMSG-01 · enviar mensagem …`
- **Regressão que defende:** mensagem gravada sem tenant, ou gravada e ausente do mural.

## UC-EMSG-02 · O mural não mostra mensagem de outro business `[must]` `[T0]`
Status: 🧪 sem veredito
- **Persona:** operador — nunca lê recado interno de outra empresa.
- **Aceite:** Dado uma mensagem minha no meu tenant e outra mensagem **minha** em outro `business_id` · Quando a tela pede `messages` · Então chega a primeira e **não** chega a segunda.
- **Teste:** `tests/Feature/Essentials/MessagesIndexContratoTest.php` — `UC-EMSG-02 · o mural não mostra …`
- **Regressão que defende:** charter Non-Goal *"NÃO mostra mensagens de outro business — `scopedMessagesQuery($businessId)`"* (ADR 0093). A alheia é de minha autoria de propósito: só o filtro de `business_id` a segura.

## UC-EMSG-03 · O polling traz só mensagens de outros, mais novas que o último visto `[must]`
Status: 🧪 sem veredito
- **Persona:** operador com o mural aberto — vê chegar o que a equipe escreveu, sem recarregar e sem duplicar o que ele mesmo enviou.
- **Aceite:** Dado, no meu tenant, uma mensagem de colega **depois** do último visto, uma de colega **antes**, uma minha **depois**, e uma de colega de **outro** tenant depois · Quando o cliente chama `GET /essentials/get-new-messages?last_chat_time=<último visto>` · Então o JSON `messages` traz **só** a primeira.
- **Teste:** `tests/Feature/Essentials/MessagesIndexContratoTest.php` — `UC-EMSG-03 · o polling …`
- **Regressão que defende:** charter §Goals *"Polling de novas mensagens … deduplicando"*. Polling que devolvesse a própria mensagem ou as antigas a duplicaria na tela; sem o filtro de tenant, vazaria.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] Remover mensagem de terceiro não apaga (o `destroy` filtra por `user_id`) — charter Non-Goal; sem teste que cite.
- [BACKLOG] Sem `essentials.view_message` nem `essentials.create_message` a tela responde 403 — gating do charter; exige montar usuário sem o papel Admin.
- [BACKLOG] Anti-spam de notificação (DB só se passou >10 min da última mensagem da mesma localidade) — charter Automation hook; exige inspecionar `Notification::fake()` por canal.
