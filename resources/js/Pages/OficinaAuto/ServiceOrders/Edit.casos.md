---
casos: Editar OS · /oficina-auto/ordens-servico/{id}/edit
irmaos: Edit.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-07-03"
---

# Casos de Uso & Aceite — Editar OS (Edit)

> **Contrato 🧪 (régua por tela · Onda 0b/ADR 0320).** Cada UC cita um teste que já existe (G-2).
> **Status 🧪** (em prova) porque o teste é Pest e ainda não está no manifesto de vereditos — subir
> pra ✅ exige um run coletado no CT100. Mesmo estado do golden `Sells/Create`.
>
> **Status:** ✅ passa (manifesto) · 🧪 em prova (teste cita o UC, sem veredito) · ⬜ não verificado · ❌ quebrou.

---

## UC-OED-01 · Editar e salvar retorna pro Show
- **Persona:** atendente.
- **Aceite:** Dado uma OS existente · Quando edita campos básicos (veículo, datas, notes) e salva · Então persiste e volta pro Show.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/ServiceOrderCrudTest.php`
- **Status: 🧪**

## UC-OED-02 · Adicionar/editar item recalcula o Total OS
- **Persona:** atendente — **Tier-0 valor**.
- **Aceite:** Dado a section inline "Itens da OS" · Quando adiciona/edita um item · Então o Total OS é recalculado server-side (Observer, ADR 0192), não no client.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/ServiceOrderItemTest.php`
- **Status: 🧪**

## UC-OED-03 · Adicionar peça baixa estoque
- **Persona:** atendente — **Tier-0 estoque**.
- **Aceite:** Dado um item do tipo peça com qty · Quando é adicionado · Então o estoque é baixado (peça×qty).
- **Teste:** `Modules/OficinaAuto/Tests/Feature/ServiceOrderItemStockBaixaTest.php`
- **Status: 🧪**

## UC-OED-05 · vehicle_id de outro business rejeitado (Tier 0)
- **Persona:** invariante de segurança.
- **Aceite:** Dado um vehicle do business B · Quando um usuário do business A tenta vincular na edição · Então rejeitado server-side (ADR 0093).
- **Teste:** `Modules/OficinaAuto/Tests/Feature/VehicleMultiTenantTest.php`
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG] Excluir item com rollback optimistic em erro HTTP** — coberto por `ServiceOrderItemHttpIntegrationTest`; contratar como UC quando a asserção do rollback citar o id.
- **[BACKLOG] window.confirm → AlertDialog do DS** — gap de UI (não comportamento); vive no scorecard como gap de UX.

## Como rodar a suíte
- **Pest (CT 100):** `docker exec oimpresso-staging php artisan test --filter=ServiceOrderItem` (Tier 0: CT 100, nunca local).
- **Cadência:** rodar ao fim de toda mexida na tela. ❌ = regressão → lição + conserto.

## Trilha do tempo
- 2026-07-03 · [CC] contrato inicial (4 UCs 🧪) a partir do charter + Pest existente (régua por tela Onda 0b). UC sobe pra ✅ quando um run CT100 alimentar o manifesto.
