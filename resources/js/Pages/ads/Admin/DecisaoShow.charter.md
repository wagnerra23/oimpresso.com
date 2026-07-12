---
page: /ads/admin/decisoes/{id}
component: resources/js/Pages/ads/Admin/DecisaoShow.tsx
related_prototype: n/a (tela de detalhe bespoke — não segue um dos 5 Padrões de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: ADS
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /ads/admin/decisoes/{id} (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/ADS/Http/Controllers/Admin/DecisoesController@show` + ações `approve`/`reject`/`dismiss` (rotas `ads.admin.decisoes.*`, middleware `auth` — V1 superadmin). Escopa `where('business_id', $businessId)` da sessão + `firstOrFail`. Tela de detalhe de uma decisão do ADS com drill-down da cadeia de raciocínio.
>
> Silêncio de PT é honesto: é detalhe bespoke — o único token estrutural presente é `grid-cols` (layout 2 colunas dos metadados), sem `FsmActionPanel`/`Timeline`/`<dl>`/`StatCard` que caracterizariam PT-03.

---

## Mission
Ser a página de detalhe onde Wagner audita e decide sobre UMA decisão automatizada do ADS (HiTL-2/HiTL-3, ARQ-0008). Mostra o raciocínio completo — instrução do Brain B, cadeia pai/filhas/skill/meta-skills, avaliação do ReviewerAgent (G-Eval) e metadados técnicos — e oferece aprovar/rejeitar (quando pendente) ou dispensar (quando bloqueada pelo firewall).

---

## Goals — Features (faz)
- Header com título da instrução (ou "Decisão #NNNN"), event_type · domínio · timestamp e ações contextuais.
- Ações: Aprovar / Rejeitar (quando `outcome=cancelled` e destino pending_wagner|brain_b e não bloqueada) ou Dispensar (quando destino blocked). Rejeição pede motivo via `window.prompt`.
- Badges de status: destino (StatusBadge), HiTL, risk_score, confidence_score, policy aplicada.
- Card "Instrução do Brain B": resumo, risco, rollback, estratégia de teste, arquivos, instrução pra Claude Code + JSON cru colapsável.
- Drill-down "Cadeia de raciocínio": decisão pai, subtarefas geradas (com review score), skill aprendida (mcp_decision_patterns) e meta-skills aplicáveis — todos com link.
- Review breakdown (G-Eval): score/100, confiança, tentativas, barras correctness/safety/quality/cost, issues e pontos positivos.
- Metadados técnicos (KV): ID, event_type, fonte, domínio, modelo, brain executor, tokens, latência, outcome, timestamps, arquivos afetados.

---

## Non-Goals — Features (NÃO faz)
- ❌ NÃO edita o texto da instrução do Brain B pela UI (aprova/rejeita como está; "modificar" é fluxo à parte não coberto aqui).
- ❌ NÃO executa a instrução — aprovar só libera pra execução (marca outcome=success), quem executa é o pipeline Brain A/Claude Code.
- ❌ NÃO acessa decisão de outro business — `show` escopa por business_id da sessão + firstOrFail (404 cross-tenant).
- ❌ NÃO reprocessa/re-roteia a decisão nem força novo review.

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 com breadcrumb ADS › Decisões › Detalhe ; largura max-w-5xl.

---

## Automation hooks (faz)
- Nenhuma automação dispara sozinha — approve/reject/dismiss são POSTs disparados por clique do usuário (`router.post`), com redirect back + flash status.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Sem polling / auto-refresh do detalhe.
- ❌ Rejeição nunca é silenciosa: exige motivo digitado (prompt) antes de postar.
- ❌ Aprovar não executa código automaticamente — só muda outcome; execução é etapa posterior fora da tela.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot) — incluir caso pendente (com botões) e caso bloqueado
- [ ] Confirmar se "aprovar com modificação" (wagner_modified) deveria ter UI aqui
