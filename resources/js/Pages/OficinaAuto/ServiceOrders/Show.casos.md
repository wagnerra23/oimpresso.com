---
id: resources-js-pages-oficina-auto-service-orders-show-casos
casos: Detalhe da OS · /oficina-auto/service-orders/{id}
irmaos: Show.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-07-03"
---

# Casos de Uso & Aceite — Detalhe da OS (Show)

> **Contrato 🧪 (régua por tela · Onda 0b/ADR 0320).** Estas telas de OS (Show/Edit/Create) tinham
> Pest de sobra mas **nenhum `.casos.md`** — comportamento sem contrato que a régua pudesse citar.
> Este doc estabelece o contrato + rastreabilidade (G-2): cada UC cita um teste que já existe e o
> defende. **Status 🧪** (em prova) porque o teste é Pest e ainda **não** está no manifesto de vereditos
> (`casos-test-results.json`, alimentado por e2e/JUnit) — subir pra ✅ exige um run coletado no CT100
> (o "dente" da régua). Mesmo estado do golden `Sells/Create` (também 🧪/0%).
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 em prova (teste cita o UC, sem veredito no manifesto) · ⬜ não verificado · ❌ quebrou.

---

## UC-OSH-01 · Ver a OS como fonte-da-verdade
- **Persona:** mecânico/atendente (Martinho biz=164).
- **Como usa:** abre a OS e vê veículo (placa/tipo), cliente, `order_type`, datas e os itens (peças+serviços) com subtotal.
- **Aceite:** Dado uma OS do business atual · Quando abre `/oficina-auto/service-orders/{id}` · Então vê o resumo + a lista de itens + o total.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/ServiceOrderCrudTest.php`
- **Status: 🧪**

## UC-OSH-02 · Timeline append-only de transições FSM
- **Persona:** governança / atendente.
- **Aceite:** Dado uma OS que avançou de etapa · Quando abre o Show · Então a timeline mostra as transições (ator, timestamp) em ordem, sem editar/apagar (append-only, ADR 0143).
- **Teste:** `Modules/OficinaAuto/Tests/Feature/FsmTransitionTest.php`
- **Status: 🧪**

## UC-OSH-03 · OS de outro business não vaza (Tier 0)
- **Persona:** qualquer — invariante de segurança.
- **Aceite:** Dado uma OS do business B · Quando um usuário do business A tenta abrir · Então 404 (global scope `business_id`, ADR 0093).
- **Teste:** `Modules/OficinaAuto/Tests/Feature/VehicleMultiTenantTest.php`
- **Status: 🧪**

## UC-OSH-04 · Total/itens exibidos batem com peça×qty + hora
- **Persona:** atendente (cobrança) — **Tier-0 valor**.
- **Aceite:** Dado itens (peça×qty + hora×horas) · Quando abre o Show · Então o total exibido é a soma dos itens. ⚠️ Hoje OS mecânica retorna R$ 0 (buraco US-OFICINA-027) — o dente de cálculo é outro chip.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/ServiceOrderItemTest.php`
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado sem teste citando o id = órfão. Ficam SEM token até existir teste real.

- **[BACKLOG] Botão FSM respeita RBAC** — botão de ação desabilitado quando a role não autoriza (`sale_stage_action_roles`). Coberto por `ServiceOrderStageGateTest` mas ainda não contratado como UC de tela.
- **[BACKLOG] Adicionar/excluir item via Sheet (optimistic)** — coberto por `ServiceOrderItemHttpIntegrationTest`.
- **[BACKLOG] Imprimir A4** — coberto por `ServiceOrderPrintTest`.

## Como rodar a suíte
- **Pest (CT 100):** `docker exec oimpresso-staging php artisan test --filter=ServiceOrder` (Tier 0: teste roda no CT 100, nunca local).
- **Cadência:** rodar ao fim de toda mexida na tela. UC que vira ❌ = regressão → lição + conserto antes de seguir.

## Trilha do tempo
- 2026-07-03 · [CC] contrato inicial (4 UCs 🧪) a partir do charter + Pest existente (régua por tela Onda 0b). UC sobe pra ✅ quando um run CT100 alimentar o manifesto (dente da régua).
