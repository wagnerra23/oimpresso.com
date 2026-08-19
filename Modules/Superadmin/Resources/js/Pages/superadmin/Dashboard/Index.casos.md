---
id: modules-superadmin-pages-superadmin-dashboard-index-casos
casos: Superadmin · Visão geral · /superadmin
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a única tela do ERP cujas queries são cross-tenant POR DESENHO (ADR 0093 §exceções). Sem casos, a próxima sessão "conserta" isso aplicando escopo de tenant e quebra o produto — ou renderiza número inventado onde não há query.
owner: wagner
last_run: "2026-08-19"
last_run_ci: "_pendente_ — o trio nasce nesta onda (SA-O1). O veredito por UC entra no manifesto quando a lane rodar no CI; até lá o Status é 🧪, nunca ✅."
---

# Casos de Uso & Aceite — Superadmin · Visão geral (`/superadmin`)

> **Âncora:** UC-SA-001 e UC-SA-016 do F1 do Cowork
> (`cowork-inbox/SUPERADMIN-F1-2026-08-18.md` §2), cruzados com as invariantes do
> [RUNBOOK-dashboard](../../../../../../../memory/requisitos/Superadmin/RUNBOOK-dashboard.md) §5
> e com a exceção de multi-tenant da [ADR 0093](../../../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
> Os UCs derivam do **contrato**, nunca do `Index.tsx` nem do controller — teste derivado do
> código é tautológico (`proibicoes.md` §5).

---

## UC-SADASH-01 · A tela responde em Inertia, não em Blade · `must`

**Dado** que sou superadmin autenticado
**Quando** abro `/superadmin`
**Então** recebo uma resposta Inertia cujo componente é `superadmin/Dashboard/Index`
— não o `view('superadmin::superadmin.index')` do AdminLTE.

Status: 🧪

---

## UC-SADASH-02 · Os KPIs vêm do service, não de query inline · `must`

**Dado** que o `SuperadminDashboardService` é o dono das leituras da home
**Quando** o controller monta a página
**Então** as props `semAssinatura`, `statsPeriodo` e `tendencia` carregam os valores do
service — e o controller **não** refaz a query de negócios-sem-assinatura por conta própria.

> Por quê: até 2026-08-19 o `index()` refazia `Business::leftjoin('subscriptions'...)` inline
> enquanto `countNotSubscribedBusinesses()` já existia. Duas fontes para o mesmo número drifam.

Status: 🧪

---

## UC-SADASH-03 · Admin de negócio não entra · `must` `[T0]`

**Dado** que sou admin de um negócio (sem a permissão `superadmin`)
**Quando** acesso `/superadmin`
**Então** recebo 403 — e o item não aparece na sidebar.

Status: 🧪

---

## UC-SADASH-04 · As queries enxergam TODOS os negócios · `must` `[T0]`

**Dado** que existem negócios de mais de um `business_id`
**Quando** a visão geral conta cadastros e negócios sem assinatura
**Então** o número cobre **todos** os negócios da plataforma, não só o do usuário logado.

> Este caso é o inverso do resto do ERP e existe para **impedir** que alguém "conserte" a
> tela aplicando escopo de tenant. Cross-tenant aqui é intencional (ADR 0093 §exceções
> Superadmin) — é o produto, não um vazamento.

Status: 🧪

---

## UC-SADASH-05 · Nenhum bloco é renderizado com número inventado · `must`

**Dado** que MRR, funil trial→pago, churn e receita-por-pacote ainda não têm query
**Quando** a visão geral é montada
**Então** esses blocos **não** chegam nas props — a tela mostra só o que o banco sustenta.

> O protótipo desenha os 9 blocos com mock. Renderizar mock em produção seria fabricar
> número — pior que não mostrar. Entram na SA-O1b, com query real.

Status: 🧪

---

## Testes mínimos

- DQE: 1 negócio sem assinatura, 1 com assinatura ativa, 1 negócio em outro `business_id`.
- Borda: período sem nenhum cadastro (KPI zero, não em branco); `end_date` nula.
- Permissão: admin de negócio 403; superadmin 200.
- Plural PT-BR: 1 negócio / 2 negócios · 1 pagante / 2 pagantes.
