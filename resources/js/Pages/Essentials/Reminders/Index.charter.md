---
page: /essentials/reminder
component: resources/js/Pages/Essentials/Reminders/Index.tsx
related_prototype: prototipo-ui/cowork/essenciais-page.jsx#Lembretes (:444-537)
bundle_source: essenciais-page.jsx
owner: wagner
status: draft
last_validated: "2026-05-17"
parent_module: Essentials
related_adrs: [93, 94, 101, 104]
tier: B
charter_version: 1
---

# Page Charter — /essentials/reminder (Lembretes pessoais)

> Migração Blade T1 Wave D — `Modules/Essentials/Resources/views/reminder/index.blade.php` (FullCalendar + jQuery + Bootstrap modals) → React/Inertia com **grade do mês** + Dialog inline. Cada usuário só vê os seus lembretes.
>
> ---
>
> ## ⚠️ Emenda 2026-09-09 — o calendário VOLTOU, e este charter estava errado
>
> Este charter, escrito em 2026-05-17 no lote de migração T1 (commit `e442757c1b`,
> **gerado pelo agente, não por [W]**), trazia dois textos que a tela agora contradiz:
>
> | Onde | Dizia | Estado |
> |---|---|---|
> | Mission | *"Substitui o calendário FullCalendar legado por listagem ordenada cronologicamente"* | **revogado** |
> | Non-Goals | *"❌ NÃO renderiza calendário full-month grid (decisão UX: listagem é mais prática diário)"* | **revogado** |
>
> **Por que o charter perde.** No eixo FORMA a cadeia é `protótipo Cowork > teste > casos >
> charter > SPEC` ([UI-0029](../../../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md),
> citada em [proibicoes.md §Precedência](../../../../../memory/proibicoes.md)), e o protótipo
> desenha a grade do mês (`essenciais-page.jsx:498-511`, `role="grid"`). O perdedor se corrige
> no MESMO PR — é o que esta emenda faz.
>
> **Por que este Non-Goal não é soberania de [W].** Non-Goal escrito por [W] é intocável; este
> **não foi escrito por ele**. Nasceu num lote automatizado de charters de migração, e a skill
> `charter-write` é justamente **proibida de inferir Non-Goal** — só [W] preenche. Um
> anti-padrão inferido pelo agente *parece canon* e vira instrução ativa pra regressão: a
> próxima sessão leria "não faça calendário" como lei. O `owner: wagner` do frontmatter é
> boilerplate legado, não autoria ([TEAM.md §3.1](../../../../../TEAM.md)).
>
> Inventário da tela: [`reminders-index-gap.md`](../../../../../memory/requisitos/Essentials/reminders-index-gap.md)
> — que já registrava as duas decisões de forma opostas e escritas, e mandava [W] decidir.
> [W] decidiu em 2026-09-08: *"quero fazer igual ao protótipo"*.

---

## Mission

Permitir que cada usuário cadastre **avisos pessoais** (data + hora + repetição) sem poluir agenda de terceiros. Mostra o mês inteiro numa grade, como o protótipo (`essenciais-page.jsx#Lembretes`): a pergunta que a tela responde é *"o que tem no dia X?"*, e uma lista cronológica não responde isso sem o usuário contar linhas.

---

## Goals (faz)

- **Grade do mês** (`role="grid"`, 7 colunas) com os lembretes dentro do dia, até 3 por célula + `+N`
- Navegação de mês (anterior · `{mês} de {ano}` · próximo) + atalho "Voltar pra hoje"
- Navegação por teclado: setas percorrem os dias, Enter abre o primeiro lembrete do dia
- Recorrência expandida na grade (`every_day` / `every_week` / `every_month`), a partir da data de início
- Quick actions: **Edit** (Dialog inline) / **Delete** (AlertDialog confirmação) / **Novo lembrete**
- Form campos: `name` (obrigatório), `date` (date picker HTML5), `time` (time picker HTML5), `end_time` (opcional), `repeat` (Select: `one_time` / `every_day` / `every_week` / `every_month`)
- Badge visual de repetição em cada linha (ícone `Repeat`)
- Empty state graceful quando lista vazia (ícone `Bell` + mensagem)
- Scope DB enforced no Controller: `business_id` + `user_id` (cada user só vê os seus)

---

## Non-Goals (NÃO faz)

- ❌ NÃO mostra lembretes de outros usuários (privacidade por design — diferente de Todo compartilhado)
- ❌ NÃO mostra lembrete de OUTRA origem (Financeiro, Ponto) — o protótipo desenha filtro,
  legenda e "Abrir no módulo" por origem (`:449`, `:515-516`, `:521-526`), mas isso exige
  que o lembrete carregue procedência, e a tabela `essentials_reminders` não tem essa coluna.
  É modelo novo, decisão de [W] — não foi inventado aqui
- ❌ NÃO envia notificação por email/whatsapp (Sprint futuro — hoje apenas registro)
- ❌ NÃO sincroniza com Google Calendar / Outlook (out-of-scope T1 migration)
- ❌ NÃO permite anexos / links (form mínimo)
- ❌ NÃO mostra lembretes históricos por padrão (todos ordem ASC; usuário rola se quiser)

---

## UX targets

- Render <300ms (lista local, sem paginate — usuário típico tem <50 lembretes)
- Form Dialog abre instantâneo (state local)
- Validação inline em erros (`form.errors.<field>`)
- Toast Sonner pra feedback success/error (canon)
- Mobile responsive (max-w-4xl + grid 3-col colapsa)

---

## Backend contract

- `ReminderController@index` retorna `{ reminders: Reminder[], repeats: Option[] }`
- `Reminder` shape: `{ id, name, date, time, end_time, repeat }`
- POST `/essentials/reminder` (StoreReminderRequest)
- PUT `/essentials/reminder/{id}` (UpdateReminderRequest)
- DELETE `/essentials/reminder/{id}`
- Multi-tenant Tier 0: `Reminder` Entity tem `HasBusinessScope` ([ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))

---

## Métricas de sucesso

- ✅ User biz=1 NÃO enxerga lembretes user biz=99 (cross-tenant Pest)
- ✅ User A biz=1 NÃO enxerga lembretes user B biz=1 (user_id scope Pest)
- ✅ Form valida `name` required + `date` required + `time` required (StoreReminderRequest)
- ✅ Smoke route `/essentials/reminder` retorna 200 autenticado biz=1
- ✅ Charter + RUNBOOK presentes (gate MWART)
