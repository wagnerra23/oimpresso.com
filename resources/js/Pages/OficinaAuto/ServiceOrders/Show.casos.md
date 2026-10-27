---
id: resources-js-pages-oficina-auto-service-orders-show-casos
casos: Detalhe da OS · /oficina-auto/service-orders/{id}
irmaos: Show.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-09-09"
last_run_ci: "2026-09-09 CT100 (oimpresso-staging, main 755f6de79, MySQL oimpresso_staging): 49 passed / 10 failed (146 assertions) nos 12 arquivos citados pelos UCs. As 10 falhas sao PRE-EXISTENTES e nao vem desta tela — o container roda o main SEM a mudanca do PR. Causa medida: 3 do ServiceOrderStagePipelineTest esperam os stages disponivel/locada/recolhida, dominio LOCACAO erradicado (proibicoes.md 2026-06-09 + RUNBOOK-erradicacao-locacao.md, que nomeia este teste entre os 7 pendentes); as demais sao comparacao estrita int x float (145 vs 145.0). Status dos UC segue 🧪 — nenhum sobe a ✅ com este run."
related_us: [US-OFICINA-001, US-OFICINA-003, US-OFICINA-035, US-OFICINA-038, US-OFICINA-039, US-OFICINA-040, US-OFICINA-041, US-OFICINA-047]
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

## Entrega com foto do serviço (US-OFICINA-047 · UC-FLX-22) — parcial: foto exigida, assinatura falta

> **Âncora:** catálogo de fluxos, modelo "Oficina mecânica" (`CatalogoModelos`), processo de venda,
> etapa **"Entregue · Fotos do serviço"**, e o UC-FLX-22 (*"só conclui com foto do serviço e
> assinatura do cliente"*). Esta entrega cobre a **foto**. A **assinatura do cliente não existe na
> Oficina** e fica fora: o único padrão de assinatura do repo é o da entrega do pedido no app
> (`PedidoEntregaController`, ajuste `app_entrega_exige_assinatura`). Estado do caso: **parcial**.
>
> **Onde a regra mora:** `StageGateEvaluator` (mesmo gate do UC-OSH-10), regra `servico_foto` na ação
> `entregar` (Pronto p/ retirar → Entregue). Não está no `concluir_servico` porque a entrega é o último
> ponto antes de o veículo sair e é a etapa que o modelo do catálogo marca.
>
> **O que é "foto do serviço":** imagem anexada à OS (laudo) ou a um item da vistoria, enviada **a
> partir** da entrada da OS em `em_execucao` na trilha FSM. O anexo não tem marcação própria de "foto
> do serviço" — o laudo e a vistoria usam o mesmo anexo —, então a data de envio contra a entrada na
> execução é o que separa o antes do depois. **Limite:** OS que chegou a Pronto p/ retirar sem passar
> por `em_execucao` na trilha (importada ou repontuada) não tem referência; aí qualquer foto da OS
> conta. PDF não conta (o app aceita PDF como anexo).
>
> Prova: `Modules/OficinaAuto/Tests/Feature/ServiceOrderEntregaFotoTest.php` (lane `PHP / Pest (Sells · MySQL)`).

## UC-OSH-12 · Entregar sem foto do serviço é recusado
- **Persona:** mecânico/atendente no balcão.
- **Aceite:** Dado uma OS em Pronto p/ retirar que entrou em execução há 1 h, com só a foto do diagnóstico (de antes) e um PDF · Quando pede "Entregar ao cliente" · Então o servidor responde **422** com o requisito `servico_foto` pendente e a OS continua em Pronto p/ retirar.
- **Regressão que defende:** veículo sai sem registro do serviço feito.
- **Status: 🧪**

## UC-OSH-13 · Com foto do serviço, a entrega passa
- **Aceite:** Dado a mesma OS com uma imagem enviada depois de entrar em execução · Quando pede "Entregar ao cliente" · Então a OS vai para **Entregue** e a trilha não marca liberação.
- **Status: 🧪**

## UC-OSH-14 · Gerente libera sem foto, e a liberação fica na trilha
- **Aceite:** Dado um usuário com papel gerente da empresa · Quando entrega sem foto **sem** pedir liberação · Então é barrado e vê que pode liberar · Quando pede com liberação · Então entrega, e a trilha guarda `gate_override` e `gate_unmet = [servico_foto]` com o usuário.
- **Status: 🧪**

## UC-OSH-15 · Foto de outra empresa não conta `[T0]`
- **Aceite:** Dado uma imagem da empresa 99 apontando para a OS da 98, e nenhuma sessão (como no app) · Quando o gate avalia a entrega · Então a foto **não** conta; a mesma foto na empresa 98 conta (controle positivo).
- **Regressão que defende:** sem sessão, o escopo global do anexo não filtra — a foto de outro cliente liberaria a entrega.
- **Status: 🧪**

## UC-OSH-16 · No app a regra é a mesma
- **Aceite:** Dado a OS sem foto do serviço · Quando o app lista as ações · Então "entregar" vem com o bloqueio dizendo o que falta · Quando o app executa · Então volta `bloqueado` (o app não libera) · Com foto, `ok` e a OS fica Entregue · Outra empresa → `nao_encontrado`.
- **Status: 🧪**

---

## Assinatura do cliente na entrega (US-OFICINA-048 · UC-FLX-22) — completa o UC-FLX-22

> **Âncora:** UC-FLX-22 (*"só conclui com foto do serviço e assinatura do cliente"*). A foto é a
> US-OFICINA-047 acima; aqui entra a **assinatura**, no mesmo gate (`StageGateEvaluator`, ação
> `entregar`), requisito `assinatura`.
>
> **Por empresa, desligada por padrão:** `business.common_settings[oficina_entrega_exige_assinatura]`
> (liga/desliga com `php artisan oficina:entrega-assinatura <biz> --ligar|--desligar`). Desligada,
> o checklist da entrega é exatamente o de antes (só a foto) — o piloto ao vivo não muda nada.
>
> **Onde se colhe:** quadro de assinatura dentro do checklist da entrega (`ServiceOrderSignaturePad`
> no `ServiceOrderStageGate`), que aparece enquanto o requisito está pendente. O PNG segue o contrato
> da entrega do pedido no app (data URL, ≤ 200 KB) e fica no módulo Arquivos anexado à linha de
> `oficina_os_assinaturas` — **não à OS**, cujo anexo é foto do laudo e contaria como foto do serviço.
>
> **Limite declarado:** o app não tem quadro de assinatura da OS; com o ajuste ligado, a entrega pelo
> app volta `bloqueado` até a assinatura ser colhida na web.
>
> Prova: `Modules/OficinaAuto/Tests/Feature/ServiceOrderEntregaAssinaturaTest.php` (lane `PHP / Pest (Sells · MySQL)`).

## UC-OSH-17 · Desligada por padrão, a entrega não pede assinatura
- **Aceite:** Dado uma empresa sem o ajuste e uma OS com a foto do serviço · Quando o gate avalia a entrega · Então o checklist é só `servico_foto` e a OS é entregue.
- **Regressão que defende:** ligar a assinatura em todas as empresas sem pedido (o piloto parar de entregar).
- **Status: 🧪**

## UC-OSH-18 · Ligada e sem assinatura, a entrega é recusada
- **Aceite:** Dado o ajuste ligado e a OS com foto mas sem assinatura · Quando pede "Entregar ao cliente" · Então **422** com o único pendente `assinatura`, e a OS continua em Pronto p/ retirar.
- **Status: 🧪**

## UC-OSH-19 · Com a assinatura salva no quadro, a entrega passa
- **Aceite:** Dado o ajuste ligado · Quando salva a assinatura (PNG) com o nome de quem recebe · Então **201**, o anexo fica na assinatura (business da OS), a galeria da OS não ganha foto, e "Entregar ao cliente" leva a OS para **Entregue**.
- **Status: 🧪**

## UC-OSH-20 · Imagem que não é PNG é recusada
- **Aceite:** Quando envia data URL que não é PNG · Então **422** em `assinatura_png` · Quando envia vazio · Então erro de validação · Em ambos nada é gravado e o requisito segue pendente.
- **Status: 🧪**

## UC-OSH-21 · Assinatura de outra empresa não conta `[T0]`
- **Aceite:** Dado uma linha de assinatura (com anexo) da empresa 99 apontando para a OS da 98 · Quando o gate avalia a entrega · Então o requisito `assinatura` segue pendente e a entrega é recusada.
- **Regressão que defende:** a assinatura de outro cliente liberar a entrega (o gate também roda pelo app, sem sessão).
- **Status: 🧪**

## UC-OSH-22 · O ajuste liga e desliga por empresa
- **Aceite:** Quando roda `oficina:entrega-assinatura <biz> --ligar` · Então a empresa passa a exigir e a outra não muda · Com `--desligar` volta a não exigir.
- **Status: 🧪**


## Peças compatíveis com o veículo (US-AUTO-008) — backend, atrás de flag

> **Âncora:** decisão [W] 2026-10-26 no SPEC — *"catálogo de peças com marca e modelo em texto; a
> peça compatível aparece na OS daquele veículo"*. O veículo ganha `brand`/`model` (texto) e a
> empresa marca "esta peça serve em <marca> <modelo> de <ano> a <ano>" em `oficina_pecas_compativeis`.
> Modelo vazio = toda a marca. Comparação sem caixa e sem espaço nas pontas.
>
> **Desligado por padrão:** `OFICINA_CATALOGO_PECAS_ENABLED=false` → as rotas
> `ordens-servico/{order}/pecas-compativeis` devolvem 404. A tela (sugestão no formulário de item)
> vem em PR próprio, depois do protótipo aprovado.
>
> Prova: `Modules/OficinaAuto/Tests/Feature/PecasCompativeisContratoTest.php` (lane `PHP / Pest (Sells · MySQL)`).

## UC-OSPECA-01 · Na OS aparecem só as peças daquela marca, modelo e ano
- **Persona:** atendente da oficina lançando peça na OS de um veículo.
- **Aceite:** Dado peças marcadas para VW Gol 2010–2020, VW (toda a marca), VW Saveiro e VW Gol 1995–2005 · Quando a OS é de um Gol 2016 · Então aparecem só a do Gol 2010–2020 e a da marca.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/PecasCompativeisContratoTest.php` — `UC-OSPECA-01 · na OS do veículo aparecem só as peças daquela marca/modelo e ano`.
- **Regressão que defende:** decisão [W] 2026-10-26 (marca e modelo em texto; a compatível aparece na OS daquele veículo).
- **Status: ⬜** — não verificado: CT 100 fora do ar, aguarda a lane `sells-pest` do CI.

## UC-OSPECA-02 · Sem marca não há sugestão, e marcar de novo não duplica
- **Persona:** atendente marcando uma peça como compatível.
- **Aceite:** Dado um veículo sem marca · Então a lista vem vazia · Quando a mesma peça é marcada duas vezes para Fiat Strada · Então fica uma linha só (a segunda atualiza o ano).
- **Teste:** `Modules/OficinaAuto/Tests/Feature/PecasCompativeisContratoTest.php` — `UC-OSPECA-02 · veículo sem marca não recebe sugestão, e marcar de novo não duplica`.
- **Regressão que defende:** sugestão errada em veículo sem marca e catálogo com linhas repetidas.
- **Status: ⬜** — não verificado: CT 100 fora do ar, aguarda a lane `sells-pest` do CI.

## UC-OSPECA-03 · Peça e veículo de outra empresa não entram `[T0]`
- **Persona:** qualquer usuário da empresa 98; a 99 é a adversária.
- **Aceite:** Marcar peça da empresa 99 pela 98 é recusado · Compatibilidade gravada na 99 não aparece na OS da 98 · Pedir a lista de um veículo da 99 pela 98 é recusado.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/PecasCompativeisContratoTest.php` — `UC-OSPECA-03 · peça e veículo de outra empresa não entram (Tier 0)`.
- **Regressão que defende:** ADR 0093: peça ou compatibilidade de outra empresa aparecendo na OS.
- **Status: ⬜** — não verificado: CT 100 fora do ar, aguarda a lane `sells-pest` do CI.

## UC-OSPECA-04 · O catálogo nasce desligado
- **Persona:** dono da oficina antes de ligar o recurso.
- **Aceite:** Sem a variável ligada, o serviço diz desligado e as rotas devolvem 404.
- **Teste:** `Modules/OficinaAuto/Tests/Feature/PecasCompativeisContratoTest.php` — `UC-OSPECA-04 · o catálogo nasce desligado`.
- **Regressão que defende:** feature nova ligada sem decisão [W] no piloto ao vivo.
- **Status: ⬜** — não verificado: CT 100 fora do ar, aguarda a lane `sells-pest` do CI.
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
| UC-OSH-12 | must | CU-OFI-08 · US-OFICINA-047 · UC-FLX-22 |
| UC-OSH-13 | must | CU-OFI-08 · US-OFICINA-047 · UC-FLX-22 |
| UC-OSH-14 | should | CU-OFI-08 · US-OFICINA-047 · UC-FLX-22 |
| UC-OSH-15 | must `[T0]` | CU-OFI-15 · US-OFICINA-047 |
| UC-OSH-16 | must | CU-OFI-08 · US-OFICINA-047 · UC-FLX-22 |
| UC-OSH-17 | must | CU-OFI-08 · US-OFICINA-048 · UC-FLX-22 |
| UC-OSH-18 | must | CU-OFI-08 · US-OFICINA-048 · UC-FLX-22 |
| UC-OSH-19 | must | CU-OFI-08 · US-OFICINA-048 · UC-FLX-22 |
| UC-OSH-20 | should | CU-OFI-08 · US-OFICINA-048 |
| UC-OSH-21 | must `[T0]` | CU-OFI-15 · US-OFICINA-048 |
| UC-OSH-22 | should | US-OFICINA-048 |
| UC-OSPECA-01 | must | US-AUTO-008 |
| UC-OSPECA-02 | should | US-AUTO-008 |
| UC-OSPECA-03 | must `[T0]` | US-AUTO-008 |
| UC-OSPECA-04 | must | US-AUTO-008 |

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
- 2026-10-21 · [CC] UC-OSH-17..22 — assinatura do cliente na entrega (US-OFICINA-048 · UC-FLX-22), por empresa e desligada por padrão. Fecha o que o UC-OSH-12..16 deixou parcial.
- 2026-10-20 · [CC] UC-OSH-12..16 — entrega exige foto do serviço (US-OFICINA-047 · UC-FLX-22). Parcial: assinatura do cliente fica fora (não existe na Oficina).
- 2026-07-03 · [CC] contrato inicial (4 UCs 🧪) a partir do charter + Pest existente (régua por tela Onda 0b). UC sobe pra ✅ quando um run CT100 alimentar o manifesto (dente da régua).
