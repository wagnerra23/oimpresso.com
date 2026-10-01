---
id: resources-js-pages-whatsapp-templates-index-casos
casos: Lista de templates de mensagem WhatsApp · /whatsapp/templates
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do admin + critério de aceite verificável (Dado/Quando/Então)
por_que: a lista não tem `where business_id` explícito — o isolamento depende do global scope + auth, e isso precisa de prova.
owner: wagner
last_run: "2026-09-30"
---

# Casos de Uso & Aceite — Templates WhatsApp

> US-WA-013 · charter `Index.charter.md` (draft). UCs derivados do charter (Goals, Non-Goals,
> Anti-hooks) e do DoD da US-WA-013, não do `.tsx`. Teste:
> `tests/Feature/Whatsapp/AtendimentoAdminContratoTest.php` (DB-less, lane sqlite).
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e passa no CI (manifesto não regravado) · ⬜ não verificado · ❌ quebrou.

---

## UC-WTPL-01 · O admin só vê os templates da própria empresa
- **Persona:** admin autenticado do business 98.
- **Aceite:** Dado a sessão do business 98 com usuário autenticado · Quando a lista resolve · Então a consulta a `whatsapp_templates` carrega `business_id = 98` · E a rota exige `auth` (sem usuário o global scope não age).
- **Teste:** `UC-WTPL-01 · lista filtra o business da sessão via global scope e a rota exige auth`.
- **Regressão que defende:** Non-Goal "NÃO cruza tenants — `business_id` scope" (Tier 0, ADR 0093). O controller confia no `HasBusinessScope`; o teste é o que impede alguém de trocar o model ou tirar o scope sem perceber.
- **Status: 🧪** — passou no CI do PR #8330 (run 36805852386, `PHP / Pest (Unit)`); ✅ quando `casos:results` regravar o manifesto.

---

## UC-WTPL-02 · Filtrar por provedor e status
- **Persona:** admin quer ver só os HSM Meta aprovados.
- **Aceite:** Dado `?provider=meta_cloud&status=APPROVED` · Então a consulta filtra os dois · Dado `all` nos dois · Então não filtra nenhum.
- **Teste:** `UC-WTPL-02 · filtro de provedor e status chega na consulta, all não filtra`.
- **Regressão que defende:** DoD da US-WA-013 "filtro por status" + Goal "distinção entre HSM Meta e locais".
- **Status: 🧪** — passou no CI do PR #8330 (run 36805852386, `PHP / Pest (Unit)`); ✅ quando `casos:results` regravar o manifesto.

---

## UC-WTPL-03 · Abrir a lista não grava, não sincroniza e só quem gerencia templates abre
- **Persona:** admin abre a tela.
- **Aceite:** Dado a abertura · Então `templates` é prop adiada e só lê (`select`) · E a sincronização Meta é rota **POST** separada · E as rotas exigem `can:whatsapp.templates.manage`.
- **Teste:** `UC-WTPL-03 · abrir só lê, sync é POST separado e a rota exige whatsapp.templates.manage`.
- **Regressão que defende:** Anti-hooks "não dispara sync Meta automático ao abrir" e "não grava nada em GET".
- **Status: 🧪** — passou no CI do PR #8330 (run 36805852386, `PHP / Pest (Unit)`); ✅ quando `casos:results` regravar o manifesto.

---

## Backlog (prosa honesta — sem UC até ganhar teste que o cite)
- [BACKLOG] Alerta de template local sem contraparte Meta aprovada (`has_meta_counterpart`) — sem teste.
- [BACKLOG] Empty state sem templates — comportamento de tela.
- ⚠️ O charter (draft) diz "NÃO faz sync Meta nesta versão"; o backend já tem `POST /whatsapp/templates/sync-meta` e o SPEC US-WA-013 pede o botão. Charter desatualizado — Non-Goal é campo [W], não editado aqui; registrado no `_saida-04`.
