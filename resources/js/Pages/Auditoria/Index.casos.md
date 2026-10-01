---
id: resources-js-pages-auditoria-index-casos
casos: Auditoria Index · /auditoria
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa + critério de aceite verificável (Dado/Quando/Então), provado por teste citando o id.
por_que: a trilha de auditoria do business (listar, filtrar, não ver a de outro) é durável — não muda no refactor da tela.
owner: wagner
last_run: "2026-09-30"
---

# Casos de Uso & Aceite — Auditoria / Index

> Listagem de governança sobre `activity_log`. UCs derivados do
> [SDD §6](../../../../memory/requisitos/Auditoria/SDD-auditoria-v1.0.md) (CU-AUD-01), do
> [charter](Index.charter.md) (Goal 1 — filtros) e do
> [SPEC](../../../../memory/requisitos/Auditoria/SPEC.md) US-AUDIT-010 (redirect legado).
> Código conferido (não usado para derivar): `AuditoriaController@index` → `AuditEntryService::list/normalizeFilters`.
> Provados por `tests/Feature/Auditoria/AuditoriaTelasContratoTest.php` (tenants fictícios 98 × 99, transação revertida).
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.

---

## UC-AUDI-01 · Listo só as alterações do meu business, paginadas, mais recente primeiro
- **Persona:** admin do business / auditor — investiga o que mudou no próprio negócio (CU-AUD-01).
- **Aceite:** Dado alterações em `activity_log` do business 98 e do business 99 · Quando a listagem é montada para o business 98 · Então vêm **só** as do 98, num paginador, ordenadas da mais nova para a mais antiga.
- **Teste:** `AuditoriaTelasContratoTest` ("UC-AUDI-01 · lista só o próprio business").
- **Status: 🧪**

## UC-AUDI-02 · Filtro por origem/evento/entidade restringe a lista; chave fora da whitelist é descartada
- **Persona:** auditor — chega na entrada certa em ≤2 cliques (charter, Goal 1).
- **Aceite:** Dado alterações `created` e `updated` do mesmo business · Quando filtro `event=updated` · Então só as `updated` aparecem. E dado um pedido com chave fora de `causer_kind|event|subject_type` (ex.: `business_id`) · Então ela é descartada antes da consulta — o filtro nunca troca o tenant.
- **Teste:** `AuditoriaTelasContratoTest` ("UC-AUDI-02 · filtro restringe e whitelist descarta").
- **Status: 🧪**

## UC-AUDI-03 · A URL antiga `/reports/activity-log` leva à tela nova, preservando os filtros
- **Persona:** usuário com favorito/link legado (SPEC US-AUDIT-010).
- **Aceite:** Dado `GET /reports/activity-log?event=updated` · Então **301** para `/auditoria?event=updated`.
- **Teste:** `AuditoriaTelasContratoTest` ("UC-AUDI-03 · redirect 301 legado").
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste)

- Reverter a partir da tela: o charter promete (Goal 3, bulk panel, export), mas a tela **não tem botão** e `AuditoriaController@revert` responde **501** (placeholder). Decisão [W] pendente — ver `_saida-01.md` do playbook `lote-trio-medida`.
- Exportar CSV/JSON (`ExportAuditEntriesRequest`) — prometido no charter Wave 25; sem rota exposta medida.
- Badge UNREVERTIBLE por linha — depende do revert existir na tela.
