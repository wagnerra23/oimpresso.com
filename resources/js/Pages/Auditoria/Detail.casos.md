---
id: resources-js-pages-auditoria-detail-casos
casos: Auditoria Detail · /auditoria/{id}
irmaos: Detail.charter.md (lei)
tecnica: Caso de uso = narrativa + critério de aceite verificável (Dado/Quando/Então), provado por teste citando o id.
por_que: ver o diff de UMA alteração do próprio business — e nunca a de outro — é durável, não muda no refactor.
owner: wagner
last_run: "2026-09-30"
---

# Casos de Uso & Aceite — Auditoria / Detail

> Detalhe read-only de uma entrada de `activity_log`. UCs derivados do
> [SDD §6](../../../../memory/requisitos/Auditoria/SDD-auditoria-v1.0.md) (CU-AUD-02) e do
> [charter](Detail.charter.md) (Mission + Non-Goal "NÃO cruza tenants").
> Código conferido (não usado para derivar): `AuditoriaController@show` → `AuditEntryService::find` (`firstOrFail` escopado).
> Provados por `tests/Feature/Auditoria/AuditoriaTelasContratoTest.php` (tenants fictícios 98 × 99, transação revertida).
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.

---

## UC-AUDD-01 · Abro uma alteração do meu business e recebo o "antes" e o "depois"
- **Persona:** auditor — investiga uma mudança específica sem abrir o banco (CU-AUD-02).
- **Aceite:** Dado uma alteração do business 98 com `properties.old` e `properties.attributes` · Quando o detalhe é carregado para o business 98 · Então vem essa entrada com os dois lados intactos (é deles que a tela monta Campo · Antes · Depois).
- **Teste:** `AuditoriaTelasContratoTest` ("UC-AUDD-01 · detalhe traz old e attributes").
- **Status: 🧪**

## UC-AUDD-02 · Alteração de outro business não abre
- **Persona:** admin do business 99 — não pode ler a trilha do 98, nem chutando o id (Tier 0, ADR 0093).
- **Aceite:** Dado uma alteração do business 98 · Quando o detalhe é pedido com o id dela para o business 99 · Então **não é encontrada** (`ModelNotFoundException` → 404), sem devolver dado nenhum.
- **Teste:** `AuditoriaTelasContratoTest` ("UC-AUDD-02 · cross-tenant não abre").
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste)

- Fallback key/value quando só há um lado (`old` ou `attributes`) — é renderização; precisa teste de UI.
- Link "ver alterações desta entidade" (`/auditoria?subject_type=…`) — navegação; precisa teste de UI.
- Reverter a partir do detalhe: o **Index** charter promete, o **Detail** charter lista como Non-Goal. Charters divergem — decisão [W] (ver `_saida-01.md` do playbook `lote-trio-medida`).
