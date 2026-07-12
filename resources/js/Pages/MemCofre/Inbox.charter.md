---
page: /memcofre/inbox
component: resources/js/Pages/MemCofre/Inbox.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela de lista paginada com abas)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: MemCofre
related_us: [US-DOCVAULT-003]
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /memcofre/inbox (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/SRS/Http/Controllers/InboxController@index` (rota `memcofre.inbox`, prefixo `/memcofre`, stack admin UltimatePOS + `throttle:60,1`). Módulo `Modules/SRS` ("Cofre de Memórias") — ferramenta interna Wagner de uso raro, em deprecação segundo o BRIEFING. Tela implementada de verdade (lê `DocEvidence` real).

---

## Mission
Fila de triagem: evidências brutas coletadas (bugs, regras, fluxos, citações, prints, decisões) aguardam classificação humana antes de virarem requisito rastreável. O operador filtra por status (pendente/triada/aplicada/rejeitada), busca no conteúdo, edita a classificação e marca como aplicada — validação humana no meio do caminho entre coleta e SPEC.

---

## Goals — Features (faz)
- Lista paginada (25/pág) de `DocEvidence` escopada por `business_id`, com fonte relacionada (`DocSource`) hidratada.
- Abas de status (pendentes/triadas/aplicadas/rejeitadas) com contadores por business (prop `counts` deferida em partial reload — D-14).
- Busca por texto com debounce 300ms via Scout (driver `database` agora, `meilisearch` quando ativar — partial reload `only:['evidences','filtros']`).
- Dialog de triagem (`useForm`): edita status, tipo (kind), módulo-alvo, sugestão de story/regra, notas.
- Ação "Aplicar" (marca `applied`) e remoção da linha de evidência (a fonte original é preservada).
- Mostra badge de IA + confiança quando a evidência foi extraída por IA; links pra arquivo/URL da fonte.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não cria evidência aqui — a origem é a tela Ingest (`/memcofre/ingest`). _Inferência pendente de Wagner._
- ❌ Ao "remover", não apaga a `DocSource` original — só a linha de evidência (declarado no próprio diálogo de confirmação).
- ❌ Não escreve automaticamente a US/regra no SPEC do módulo — "aplicar" é marcação de status, não geração de artefato. _Inferência pendente de Wagner._
- ❌ Não lista evidências de outros businesses (escopo `business_id` em todas as queries).

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 (breadcrumb "Cofre › Inbox").

---

## Automation hooks (faz)
- Busca full-text delegada ao Scout no backend (não filtra no cliente).
- Partial reloads Inertia (`only:[...]`) em troca de aba/página/busca — evita recarregar `counts`.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não faz auto-apply nem auto-triagem em massa — cada evidência é decidida manualmente.
- ❌ Não faz polling; recarrega só na interação do usuário.
- ❌ Não remove a fonte original ao deletar a evidência.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] **Bug latente:** os endpoints de mutação usam prefixo stale `/docs/inbox/{id}/triage`, `/docs/inbox/{id}/apply` e `DELETE /docs/inbox/{id}`, mas as rotas registradas são `/memcofre/inbox/{evidence}/...` (nenhum prefixo `/docs` existe em `routes.php`). Alinhar frontend ↔ rota antes de live.
