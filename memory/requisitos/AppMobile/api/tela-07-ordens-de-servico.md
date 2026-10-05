# App — Ordens de serviço (tela 07) — só leitura ✅

> Parte do contrato da API do app ([API-CONTRATO-v1.md](../API-CONTRATO-v1.md): regras gerais §0, Início §6).

**Área (11. Oficina — Onda D (Modules/OficinaAuto)):** Área `oficina` em `areas` (§6): módulo `oficina_auto_module` no pacote do business (Camada 1;
superadmin: módulo instalado) **e** permissão `oficinaauto.service_order.view` — a mesma regra do
menu web da Oficina. Sem acesso → `403 sem_permissao`. Vocabulário de reparo: `order_type` só
`manutencao`/`mecanica` (ADR 0265). Sem câmera (ADR 0383).

`GET /api/app/os?etapa=<chave|todas>&pagina=N` (20 por página)

```json
{ "itens": [ {
    "id": 42, "numero": "OS-00042", "placa": "RLV2E48", "veiculo": "Caminhão",
    "cliente": "Transportes Vale Norte", "valor": 750.00,
    "etapa": { "chave": "aguardando_pecas", "rotulo": "Aguardando peças", "indice": 4, "total_etapas": 6 },
    "travada": true } ],
  "etapas": [ { "chave": "recepcao", "rotulo": "Recepção", "total": 3 } ],
  "total": 6, "travadas": 2, "pagina": 1, "tem_mais": false }
```

- Mesmo universo do quadro web `/oficina-auto/ordens-servico`: OS no processo FSM
  `oficina_mecanica_os` em etapa **não-terminal**, ou OS de mecânica ainda sem pipeline (conta na
  etapa inicial). Terminais (entregue, cancelado, garantia acionada) ficam fora. `total` = ativas.
- `etapas` = as não-terminais do processo do business, na ordem do ERP (`sort_order`), sempre todas,
  com `total` (pode ser 0). `indice` é 1-based nessa lista.
- `travada` = etapa `aguardando_aprovacao` ou `aguardando_pecas`.
- `numero` = `OS-` + id com 5 dígitos (como a web). `veiculo` = rótulo do tipo de veículo da web
  (o cadastro não tem marca/modelo/ano); `null` se o tipo não tem rótulo.
- `valor` = soma dos itens da OS (peças + mão de obra), como o card web; `null` sem item.
  `cliente` = cliente da OS; `null` se a OS não tem cliente.
- Ordem: etapa mais avançada primeiro; desempate pela OS mais recente.
- O filtro `etapa` só filtra `itens` (e `tem_mais`). `total`, `travadas` e `etapas[].total` contam
  sempre TODAS as OS ativas, com ou sem filtro.

## Nova OS — escrita (sem valor nem estoque)

`GET /api/app/os` traz também `pode_criar: bool`: o usuário tem `oficinaauto.service_order.create` (ou é
superadmin) **e** o business tem o processo da oficina (sem ele a OS nasceria fora do quadro). O app só
mostra "+ Nova OS" quando é `true`.

`POST /api/app/os` (throttle 30/min)

```json
{ "vehicle_id": 7, "contact_id": 12, "mileage_at_service": 48312, "box_label": "Elevador 1", "notes": "Barulho na suspensão" }
```

- `vehicle_id` obrigatório; `contact_id`, `mileage_at_service` (inteiro ≥ 0), `box_label` (≤ 60) e
  `notes` (≤ 2000) opcionais. Veículo e cliente têm de ser do business do token. `contact_id` vazio fica
  vazio, como na web; o app pode sugerir o dono do veículo (`cliente_id` da lista de veículos, tela 08).
- O ERP fixa `order_type = mecanica`, `status = aberta`, `entered_at = agora` e o business do token, põe a
  OS no pipeline da oficina (Recepção, para aparecer nesta lista) e a liga ao veículo se ele estiver livre.
  Mesmo create da web (`ServiceOrderController@store`): não gera item, valor, venda nem WhatsApp.
- `201` → o mesmo JSON do `GET /api/app/os/{id}` (tela 03). `422 { erro:"validacao", campos }` (inclui
  veículo/cliente de outra empresa: "Veículo não encontrado.") · `403 sem_permissao` (sem permissão de ver
  ou de criar OS) · `503 sem_configuracao` (módulo da oficina indisponível).
- Fica na web, por ora: nível de combustível, avarias na entrada, mecânico responsável e criar veículo junto.
