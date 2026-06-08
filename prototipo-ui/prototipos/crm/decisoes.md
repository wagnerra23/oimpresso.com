---
casos: CRM · window.CrmPage (crm-page.jsx)
irmaos: CRM.charter.md · crm-page.css
tecnica: Caso de uso = narrativa do cliente + aceite verificável (Dado/Quando/Então)
nota_tela: 8.6
owner: wagner · last_run: 2026-06-02
---

# Casos de Uso & Aceite — CRM

> Derivados do código real (`crm-page.jsx`). Funil kanban Lead→Qualificado→Proposta→Negociação→Ganho. ⚠️ tela do repo ainda é Blade legado (L-26) — estes casos guiam a migração Inertia.

## UC-C01 · Ver o funil num relance
- **Persona:** comercial/Wagner. **Como usa:** bate o olho no kanban — 5 colunas, cada deal um card, total e contador por coluna.
- **Aceite:** Então 5 colunas (`STAGES`) com cards por `stage`, total em R$ e nº por coluna.
- **Check:** static `STAGES` + `byCol` + `crm-col-n`. · **Status: ✅ static · live ⬜**

## UC-C02 · Avançar arrastando
- **Persona:** comercial. **Como usa:** arrasta um card pra outra coluna pra mudar o estágio.
- **Aceite:** Quando solta numa coluna · Então `stage` muda + toast "Cliente → Estágio".
- **Check:** static `moveDeal` + `handleDrop` + `crm-toast`. · **Status: ✅ static · live ⬜**

## UC-C03 · Os números do pipeline
- **Persona:** Wagner. **Como usa:** lê Pipeline ativo, Ganhas no mês (+ticket), Conversão, Ciclo médio.
- **Aceite:** Então KPI strip com `stats.pipeline/wonValue/conv` + ciclo médio.
- **Check:** static `stats` useMemo + `.crm-stats`. · **Status: ✅ static**

## UC-C04 · Focar nos quentes
- **Persona:** comercial. **Como usa:** botão "Só quentes" filtra os deals em risco (warn/bad).
- **Aceite:** Quando ativa · Então só os `warn`/`bad` aparecem no kanban.
- **Check:** static `filter==="hot"` + `byCol`. · **Status: ✅ static**

## UC-C05 · Abrir a oportunidade (drawer)
- **Persona:** comercial. **Como usa:** clica o card → drawer com valor, flow de estágio clicável (move por aqui também), ações (Ligar/WhatsApp/Orçamento/Próxima ação) e histórico.
- **Aceite:** Quando abre · Então `crm-drawer` com `crm-flow` (estágios clicáveis) + ações + timeline.
- **Check:** static `drawerDeal` + `crm-flow-step` + `crm-drawer-actions`. · **Status: ✅ static · live ⬜**

## UC-C06 · Novo lead
- **Persona:** comercial. **Como usa:** "+ Novo lead" abre drawer de criação → cria em Leads.
- **Aceite:** Quando cria · Então o card entra na coluna Lead + toast.
- **Check:** static `newOpen` + `setDeals`. · **Status: ✅ static · live ⬜**

## Evolução
- 2026-06-02 · [CC] criou a suíte (6 UCs) grounded em `crm-page.jsx`. ⚠️ Tela do repo é Blade legado (sem Inertia page) — `live ⬜` até a migração F3. Ajustes travados no charter: emoji→lucide, tokenizar hues de estágio.
