---
id: resources-js-pages-whatsapp-settings-casos
casos: Conexão WhatsApp Business via Meta Embedded Signup · /whatsapp/settings
irmaos: Settings.charter.md (lei)
tecnica: Caso de uso = narrativa do admin + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela lida com credencial Meta de um business — isolamento de tenant, segredo fora do front e CSRF de uso único são o contrato.
owner: wagner
last_run: "2026-09-30"
---

# Casos de Uso & Aceite — Conexão WhatsApp (Meta Cloud)

> US-WA-001 · US-WA-067 · ADR 0202 · charter `Settings.charter.md` (draft v2). UCs derivados do
> charter (Goals, Non-Goals, Anti-hooks, Multi-tenant), não do `.tsx`. Teste:
> `tests/Feature/Whatsapp/AtendimentoAdminContratoTest.php` (lane sqlite). O fluxo do callback
> OAuth já tem contrato próprio em `Modules/Whatsapp/Tests/Feature/EmbeddedSignupFlowTest.php`.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e passa no CI (manifesto não regravado) · ⬜ não verificado · ❌ quebrou.

---

## UC-WSET-01 · O admin vê a conexão do próprio negócio, e o token nunca chega à tela
- **Persona:** admin do business 98; existe também a conexão do business 99.
- **Aceite:** Dado as duas conexões gravadas · Quando abro `/whatsapp/settings` no business 98 · Então `currentConfig` traz o telefone do 98 (não o do 99) e nenhuma prop contém o `meta_access_token`.
- **Teste:** `UC-WSET-01 · mostra a conexão do business da sessão e nunca o token`.
- **Regressão que defende:** Anti-hooks "mutar/ler config alheio" (Tier 0, ADR 0093) e "expor access_token em props/state/DOM".
- **Status: ⬜** — aguarda o CI deste PR.

---

## UC-WSET-02 · Sem Meta App configurado, a tela é honesta e não abre o popup
- **Persona:** admin num servidor sem `META_APP_ID`.
- **Aceite:** Dado `whatsapp.meta.app_id` vazio · Então a prop `metaAppId` chega vazia (empty state) · E pedir o início do OAuth devolve **503** `meta_app_not_configured` sem gravar state na sessão.
- **Teste:** `UC-WSET-02 · sem Meta App a tela recebe vazio e o init devolve 503`.
- **Regressão que defende:** Goal "empty state honesto se `META_APP_ID` ausente" e Anti-hook "hardcode META_APP_ID no frontend — vem via props".
- **Status: ⬜** — aguarda o CI deste PR.

---

## UC-WSET-03 · O início do OAuth gera um state CSRF novo e o amarra ao popup
- **Persona:** admin clica "Conectar com Meta".
- **Aceite:** Dado o Meta App configurado · Quando o init roda · Então a sessão guarda um state de 64 hex · E a URL do popup leva o mesmo state e o `client_id` do servidor.
- **Teste:** `UC-WSET-03 · init grava state de 64 hex e o popup carrega o mesmo state`.
- **Regressão que defende:** Automation hook "CSRF state stored em `session('whatsapp_oauth_state')`" — o callback faz `pull` (1-shot) e só funciona se o init gravou.
- **Status: ⬜** — aguarda o CI deste PR.

---

## UC-WSET-04 · Só quem gerencia a conexão abre a tela
- **Persona:** usuário do business sem a permissão `whatsapp.settings.manage`.
- **Aceite:** Dado as rotas da tela (`show`, `meta.oauth_init`, `meta.embedded_callback`) · Então todas exigem `auth` e `can:whatsapp.settings.manage`, e o callback só aceita POST.
- **Teste:** `UC-WSET-04 · rotas exigem auth + can:whatsapp.settings.manage e o callback é POST`.
- **Regressão que defende:** Non-Goal "mutar config alheio" e o 1-shot do callback (GET não pode disparar provisionamento).
- **Status: ⬜** — aguarda o CI deste PR.

---

## Backlog (prosa honesta — sem UC até ganhar teste que o cite)
- [BACKLOG] `postMessage` só aceito de `https://www.facebook.com` (defesa XSS) — vive no `.tsx`, sem teste de front.
- [BACKLOG] Esc fecha o popup e limpa o estado `connecting` — comportamento de tela.
