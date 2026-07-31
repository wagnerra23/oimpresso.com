---
id: resources-js-pages-oficina-auto-service-orders-edit-casos
casos: Editar OS · /oficina-auto/ordens-servico/{id}/edit
irmaos: Edit.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-07-30"
last_run_ci: "0 UC executado — UC-OED-06/07/08 nascem neste PR; veredito pendente da lane PHP / Pest (OficinaAuto · MySQL)"
related_us: [US-OFICINA-001]
related_cu: [CU-OFI-05, CU-OFI-06, CU-OFI-15]
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
- **Aceite (corrigido 2026-07-27):** Dado um item do tipo peça com `product_id` de produto com controle de estoque · **Quando a OS é CONCLUÍDA** · Então o estoque é baixado (peça×qty) pelo caminho auditável do núcleo.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/ServiceOrderItemStockBaixaTest.php`
- **Status: 🧪**

> ⚠️ **Correção factual (2026-07-27 · chip SDD).** Este UC dizia *"Quando é **adicionado** · Então o
> estoque é baixado"*. **O código nunca fez isso** — `ServiceOrderItemService::baixarEstoqueConclusao`
> roda **na conclusão** da OS, e o teste aqui citado prova exatamente a versão corrigida
> (*"…baixa estoque pela quantidade **ao concluir OS**"*). Corrigido o **artefato**, não o código
> (precedência: teste verde > casos · [proibicoes](../../../../memory/proibicoes.md) §Precedência).
> Nenhuma mudança de comportamento — piloto LIVE.

## UC-OED-05 · vehicle_id de outro business rejeitado (Tier 0)
- **Persona:** invariante de segurança.
- **Aceite:** Dado um vehicle do business B · Quando um usuário do business A tenta vincular na edição · Então rejeitado server-side (ADR 0093).
- **Teste:** `Modules/OficinaAuto/Tests/Feature/VehicleMultiTenantTest.php`
- **Status: 🧪**

## UC-OED-06 · Concluir a mesma OS duas vezes não baixa estoque em dobro `[V0]`
- **Persona:** invariante de estoque (REGRA MESTRE valor/estoque).
- **Aceite:** Dado uma OS já concluída cujas peças já baixaram · Quando a conclusão é reprocessada · Então o saldo **não** é decrementado de novo (idempotência).
- **Regressão que defende:** estoque negativo por reprocessamento — dano direto em dado de produção do piloto.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/ServiceOrderItemStockBaixaTest.php`
- **Status: 🧪**

## UC-OED-07 · Mão-de-obra não mexe em estoque `[V0]`
- **Persona:** invariante de estoque.
- **Aceite:** Dado um item sem produto de catálogo (mão-de-obra ou serviço de terceiro) · Quando a OS é concluída · Então **nenhum** saldo de estoque é alterado por ele.
- **Regressão que defende:** hora trabalhada virar baixa de peça e furar o inventário.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/ServiceOrderItemStockBaixaTest.php`
- **Status: 🧪**

## UC-OED-08 · Peça de catálogo de outro negócio é recusada no item `[T0]`
- **Persona:** invariante multi-tenant ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
- **Aceite:** Dado um produto do negócio B · Quando é lançado como peça numa OS do negócio A · Então o item **não é persistido**.
- **Regressão que defende:** baixar estoque do catálogo de outro cliente do ERP — vazamento com consequência financeira.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/ServiceOrderItemStockBaixaTest.php`
- **Status: 🧪**

---

## Rastreabilidade (âncora no SDD §6 · [ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md))

| UC | Peso | Âncora (CU/US) |
|---|---|---|
| UC-OED-01 | must | CU-OFI-04 · US-OFICINA-001 |
| UC-OED-02 | must `[V0]` | CU-OFI-05 · US-OFICINA-001 |
| UC-OED-03 | must `[V0]` | CU-OFI-06 · US-OFICINA-001 |
| UC-OED-05 | must `[T0]` | CU-OFI-15 · US-OFICINA-001 |
| UC-OED-06 | must `[V0]` | CU-OFI-06 · US-OFICINA-001 |
| UC-OED-07 | must `[V0]` | CU-OFI-06 · US-OFICINA-001 |
| UC-OED-08 | must `[T0]` | CU-OFI-15 · US-OFICINA-001 |

> O **quarto id desta série** (entre `UC-OED-03` e `UC-OED-05`) está **reservado**: foi proposto
> e nunca ratificado, e id proposto não se reusa. Ele **não é escrito por extenso aqui de
> propósito** — a porta `requisitos-status.mjs` extrai UC do corpo inteiro do arquivo, então
> soletrá-lo o transformaria num UC **declarado e órfão** (exatamente a dívida que este doc evita).
>
> ⚖️ **Força do veredito:** lane `PHP / Pest (OficinaAuto · MySQL)`, criada em 2026-07-27 —
> **ADVISORY**: reprova fica visível e **não bloqueia merge** (não está em
> `governance/required-checks-baseline.json`).

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG] Excluir item com rollback optimistic em erro HTTP** — coberto por `ServiceOrderItemHttpIntegrationTest`; contratar como UC quando a asserção do rollback citar o id.
- **[BACKLOG] window.confirm → AlertDialog do DS** — gap de UI (não comportamento); vive no scorecard como gap de UX.

## Como rodar a suíte
- **Pest (CT 100):** `docker exec oimpresso-staging php artisan test --filter=ServiceOrderItem` (Tier 0: CT 100, nunca local).
- **Cadência:** rodar ao fim de toda mexida na tela. ❌ = regressão → lição + conserto.

## Trilha do tempo
- 2026-07-03 · [CC] contrato inicial (4 UCs 🧪) a partir do charter + Pest existente (régua por tela Onda 0b). UC sobe pra ✅ quando um run CT100 alimentar o manifesto.
