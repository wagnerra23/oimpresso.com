---
id: resources-js-pages-oficina-auto-service-orders-show-casos
casos: Detalhe da OS · /oficina-auto/service-orders/{id}
irmaos: Show.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-07-30"
last_run_ci: "0 UC executado — UC-OSH-05..11 nascem neste PR; veredito pendente da lane PHP / Pest (OficinaAuto · MySQL)"
related_us: [US-OFICINA-001, US-OFICINA-003, US-OFICINA-035, US-OFICINA-038, US-OFICINA-039, US-OFICINA-040, US-OFICINA-041]
related_cu: [CU-OFI-04, CU-OFI-05, CU-OFI-07, CU-OFI-08, CU-OFI-09, CU-OFI-10, CU-OFI-11, CU-OFI-13, CU-OFI-15, CU-OFI-16]
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

## UC-OSH-05 · Registrar como o veículo entrou (avarias + combustível)
- **Persona:** atendente na recepção do pátio.
- **Aceite:** Dado a OS sendo aberta · Quando o atendente registra as avarias observadas e o nível de combustível · Então os dois ficam gravados na OS, e ambos são **opcionais** (entrada sem check-in é aceita).
- **Regressão que defende:** discussão com o cliente na entrega (*"esse amassado já estava?"*) sem prova do estado de entrada.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/ServiceOrderCheckinTest.php`
- **Status: 🧪**

## UC-OSH-06 · Vistoria mostra o que é crítico primeiro
- **Persona:** mecânico/atendente montando o orçamento.
- **Aceite:** Dado itens de vistoria com severidades diferentes · Quando a vistoria da OS é consultada · Então os **críticos vêm no topo**, e o total recomendado soma **só** o que precisa de ação — o que está em ordem não entra no orçamento.
- **Regressão que defende:** empurrar item que está OK pro orçamento (perda de confiança do cliente) ou esconder o crítico no fim da lista.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/DviInspectionItemTest.php`
- **Status: 🧪**

## UC-OSH-07 · O que a vistoria achou vira item da OS — uma vez só
- **Persona:** atendente convertendo vistoria em orçamento.
- **Aceite:** Dado um item de vistoria com valor sugerido · Quando é convertido · Então vira item da OS com aquele valor; **reconverter o mesmo item é recusado**, e item de outra OS não é alcançável.
- **Regressão que defende:** orçamento inflado por conversão dupla — dinheiro do cliente.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/ServiceOrderDviToOrcamentoTest.php`
- **Status: 🧪**

## UC-OSH-08 · O balcão vê em que pé está a aprovação
- **Persona:** atendente cobrando o cliente que não respondeu.
- **Aceite:** Dado uma OS com pedido de aprovação enviado · Quando o Show é aberto · Então o estado de aprovação aparece **derivado das colunas de decisão** (pendente/aprovado/recusado); reenviar redispara o aviso ao cliente.
- **Regressão que defende:** estado de aprovação guardado em duplicidade e divergindo do que o cliente respondeu.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/ServiceOrderApprovalGateTest.php`
- **Status: 🧪**

## UC-OSH-09 · Concluir a OS gera a venda com o total dos itens `[V0]`
- **Persona:** [W] / financeiro — **Tier-0 valor**.
- **Aceite:** Dado uma OS com itens lançados · Quando entra no estado final de sucesso · Então nasce a venda derivada com total igual à **soma dos itens**; OS sem itens gera venda com total zero (comportamento legado preservado — o valor é ajustado à mão depois).
- **Regressão que defende:** faturar valor diferente do que foi orçado e aprovado.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/ServiceOrderObserverItemsAndHttpTest.php`
- **Status: 🧪**

## UC-OSH-10 · O gate de etapa barra no servidor, não só na tela
- **Persona:** invariante de processo.
- **Aceite:** Dado uma transição com requisito bloqueante não cumprido · Quando é disparada · Então o **servidor** recusa (não basta a tela esconder o botão); transição **sem regra cadastrada** não é bloqueada.
- **Regressão que defende:** burlar o gate chamando a rota direto — o pátio avança OS sem vistoria/orçamento.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/ServiceOrderStageGateTest.php`
- **Status: 🧪**

## UC-OSH-11 · A venda derivada de outro negócio não é exibida `[T0]`
- **Persona:** invariante multi-tenant ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
- **Aceite:** Dado uma OS do negócio A apontando para uma venda do negócio B · Quando o Show monta o bloco de venda derivada · Então **nada** da venda de B é exposto (degrada para vazio, não vaza).
- **Regressão que defende:** número de nota e valor de outro cliente do ERP aparecendo na tela.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/ServiceOrderSheetVendaDerivadaTest.php`
- **Status: 🧪**

---

## Rastreabilidade (âncora no SDD §6 · [ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md))

| UC | Peso | Âncora (CU/US) |
|---|---|---|
| UC-OSH-01 | must | CU-OFI-04 · US-OFICINA-001 |
| UC-OSH-02 | must | CU-OFI-07 · US-OFICINA-003 |
| UC-OSH-03 | must `[T0]` | CU-OFI-15 · US-OFICINA-001 |
| UC-OSH-04 | must `[V0]` | CU-OFI-05 · US-OFICINA-001 |
| UC-OSH-05 | must | CU-OFI-11 · US-OFICINA-038 · US-OFICINA-039 |
| UC-OSH-06 | should | CU-OFI-09 · US-OFICINA-035 |
| UC-OSH-07 | should | CU-OFI-10 · US-OFICINA-040 |
| UC-OSH-08 | should | CU-OFI-13 · US-OFICINA-041 |
| UC-OSH-09 | must `[V0]` | CU-OFI-16 · US-OFICINA-001 |
| UC-OSH-10 | should | CU-OFI-08 · US-OFICINA-003 |
| UC-OSH-11 | must `[T0]` | CU-OFI-15 · US-OFICINA-001 |

> ⚖️ **Força do veredito:** lane `PHP / Pest (OficinaAuto · MySQL)`, criada em 2026-07-27 —
> **ADVISORY**: reprova fica visível e **não bloqueia merge** (não está em
> `governance/required-checks-baseline.json`). Os mesmos arquivos entram na suíte completa
> noturna (registrados em `phpunit.xml`).

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado sem teste citando o id = órfão. Ficam SEM token até existir teste real.

- **[BACKLOG] Painel fiscal da OS (`CU-OFI-17` · US-OFICINA-042/018)** — o card mostra o split entre peça e serviço, mas **não emite** documento fiscal, e **nenhum teste do repo o cobre** (varrido em 2026-07-27: 0 arquivos). Decisão de produto pendente: contratar ou virar Non-Goal explícito.

- **[BACKLOG] Botão FSM respeita RBAC** — botão de ação desabilitado quando a role não autoriza (`sale_stage_action_roles`). Coberto por `ServiceOrderStageGateTest` mas ainda não contratado como UC de tela.
- **[BACKLOG] Adicionar/excluir item via Sheet (optimistic)** — coberto por `ServiceOrderItemHttpIntegrationTest`.
- **[BACKLOG] Imprimir A4** — coberto por `ServiceOrderPrintTest`.

## Como rodar a suíte
- **Pest (CT 100):** `docker exec oimpresso-staging php artisan test --filter=ServiceOrder` (Tier 0: teste roda no CT 100, nunca local).
- **Cadência:** rodar ao fim de toda mexida na tela. UC que vira ❌ = regressão → lição + conserto antes de seguir.

## Trilha do tempo
- 2026-07-03 · [CC] contrato inicial (4 UCs 🧪) a partir do charter + Pest existente (régua por tela Onda 0b). UC sobe pra ✅ quando um run CT100 alimentar o manifesto (dente da régua).
