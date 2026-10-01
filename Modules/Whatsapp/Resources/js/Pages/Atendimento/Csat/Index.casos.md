---
id: modules-whatsapp-pages-atendimento-csat-index-casos
casos: Dashboard CSAT pós-atendimento · /atendimento/csat
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do gestor + critério de aceite verificável (Dado/Quando/Então)
por_que: é leitura de satisfação de clientes de UM business — o filtro de tenant e o caráter somente-leitura são o que não pode regredir.
owner: wagner
last_run: "2026-09-30"
---

# Casos de Uso & Aceite — Dashboard CSAT

> US-WA-CSAT · charter `Index.charter.md` (draft). UCs derivados do charter (Goals, Non-Goals,
> Automation hooks, Anti-hooks), não do `.tsx`. Teste: `tests/Feature/Whatsapp/AtendimentoAdminContratoTest.php`
> (DB-less, lane sqlite `.github/ci-sqlite-pest.list`).
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e passa no CI (manifesto não regravado) · ⬜ não verificado · ❌ quebrou.

---

## UC-ACSAT-01 · O gestor só vê a satisfação do próprio negócio
- **Persona:** gestor de atendimento do business 98.
- **Aceite:** Dado a sessão do business 98 · Quando a tela calcula KPIs, distribuição e últimas respostas · Então **toda** consulta a `whatsapp_csat_responses` carrega `business_id = 98`.
- **Teste:** `UC-ACSAT-01 · KPIs, distribuição e últimas respostas filtram o business da sessão`.
- **Regressão que defende:** Non-Goal do charter "não mostra CSAT de outro `business_id`" (Tier 0, ADR 0093). Sem usuário autenticado o global scope é no-op, então o `where` explícito do controller é a defesa.
- **Status: 🧪** — passou no CI do PR #8330 (run 36805852386, `PHP / Pest (Unit)`); ✅ quando `casos:results` regravar o manifesto.

---

## UC-ACSAT-02 · Período só aceita 7, 30 ou 90 dias
- **Persona:** gestor troca o período no seletor (ou alguém edita `?range=` na URL).
- **Aceite:** Dado `?range=7` · Então o período é 7 · Dado `?range=999` ou ausente · Então o período é 30.
- **Teste:** `UC-ACSAT-02 · range fora da whitelist cai no default 30`.
- **Regressão que defende:** Automation hook do charter "Range whitelisted no backend (7/30/90, default 30)".
- **Status: 🧪** — passou no CI do PR #8330 (run 36805852386, `PHP / Pest (Unit)`); ✅ quando `casos:results` regravar o manifesto.

---

## UC-ACSAT-03 · Abrir a tela não grava nada e não roda as consultas pesadas no 1º paint
- **Persona:** gestor abre o dashboard.
- **Aceite:** Dado a abertura da tela · Então `kpis`, `distribution` e `recent` são props adiadas (`Inertia::defer`), o render inicial não consulta o banco, e as consultas adiadas são **só leitura** (`select`).
- **Teste:** `UC-ACSAT-03 · render inicial não consulta e as props pesadas são adiadas e só leitura`.
- **Regressão que defende:** Anti-hooks "não muta dados" e "não dispara pesquisa CSAT ao abrir"; hook "kpis/distribution/recent via `Inertia::defer`".
- **Status: 🧪** — passou no CI do PR #8330 (run 36805852386, `PHP / Pest (Unit)`); ✅ quando `casos:results` regravar o manifesto.

---

## Backlog (prosa honesta — sem UC até ganhar teste que o cite)
- [BACKLOG] Link do cliente abre o thread no Inbox (`/atendimento/inbox?thread=`) — o próprio charter pede confirmar se é o destino canônico (render `Atendimento/Inbox/Index` está órfão, thread 01 deste playbook).
- [BACKLOG] Empty state quando não há respostas — comportamento de tela, sem teste de render.
