# App — Veículos (tela 08) — leitura ✅ · novo veículo

> Parte do contrato da API do app ([API-CONTRATO-v1.md](../API-CONTRATO-v1.md): regras gerais §0, Início §6). Regra de acesso da área Oficina: [tela-07-ordens-de-servico.md](tela-07-ordens-de-servico.md).

`GET /api/app/veiculos?q=&pagina=N` (20 por página, ordem por placa)

```json
{ "itens": [ { "id": 7, "placa": "RLV2E48", "placa_secundaria": "REB1A23", "descricao": "Caminhão",
    "ano": "2019/2020", "cliente": "Transportes Vale Norte", "cliente_id": 12, "km": 48312, "cor": "Branco" } ],
  "total": 91, "pagina": 1, "tem_mais": true }
```

- `cliente_id` = id do dono (`null` sem dono): o app usa para sugerir o cliente da nova OS (tela 07).
- Veículos de cliente do business (`vehicles`, Modules/OficinaAuto). Permissão da tela web de
  veículos: `oficinaauto.vehicle.view` + pacote da Oficina; sem ela → `403 sem_permissao`.
- O cadastro não tem marca/modelo: `descricao` = rótulo do tipo (como a web). `ano` =
  fabricação/modelo. `cliente` = dono do veículo. `cor`/`placa_secundaria` `null` quando vazias.
- `km` = maior km conhecido (cadastro do veículo ou km de entrada das OS dele); `null` sem registro.
- Busca `q`: placa (principal e do reboque), rótulo do tipo e nome do dono.
- Sem padrão de placa: o app deduz pelo formato.

## Histórico do veículo — só leitura ✅

`GET /api/app/veiculos/{id}/os` →

```json
{ "itens": [ { "os_id": 998, "numero": "OS-00998", "data": "2026-06-12",
    "etapa_rotulo": "Entregue", "cliente": "Transportes Vale Norte", "valor": 750.0 } ] }
```

- Todas as OS do veículo, inclusive as encerradas e as fora do fluxo da oficina, da entrada mais nova
  para a mais antiga (`entered_at`; sem ela, a data de criação). Sem paginação; no máximo as 200 mais recentes.
- `etapa_rotulo` = nome da etapa no processo da oficina, terminais incluídas ("Entregue"); OS de
  mecânica ainda sem pipeline = etapa inicial (como no quadro web); OS fora do processo → `null`.
- `valor` = soma dos itens da OS, o mesmo número da 07; `null` sem item. `cliente` = cliente da OS
  (pode não ser o dono do veículo); `null` se a OS não tem cliente. `data` = `AAAA-MM-DD`.
- Mesma permissão da lista (`oficinaauto.vehicle.view` + pacote da Oficina) → `403 sem_permissao`.
  Veículo de outra empresa ou inexistente → `404 nao_encontrado`. OS de outra empresa nunca entra.

## Novo veículo — escrita (sem valor nem estoque)

Pedido [W] 2026-10-05. A lista `GET /api/app/veiculos` traz também `"pode_criar": bool` (permissão
`oficinaauto.vehicle.create` ou superadmin): o app só mostra "+ Veículo" com ela.

`GET /api/app/veiculos/opcoes` → `{ "tipos": [ { "chave": "caminhao", "rotulo": "Caminhão" } ] }`, na ordem
do ERP. Mesma permissão da lista.

`POST /api/app/veiculos` (throttle 30/min):

```json
{ "placa": "RBA2H78", "tipo": "caminhao", "placa_secundaria": null, "ano_fabricacao": 2019,
  "ano_modelo": 2020, "cor": null, "km": 48312, "chassi": null, "renavam": null, "contact_id": 12 }
```

- Obrigatórios: `placa` (≤ 10) e `tipo` (uma `chave` de `opcoes`). O ERP grava a placa em maiúsculas, só
  letras e números (`rba-2h78` → `RBA2H78`); vale também para `placa_secundaria`. Anos 1900–2100, `km`
  inteiro ≥ 0 (km de entrada do cadastro), `cor`/`chassi` ≤ 30, `renavam` ≤ 11.
- `contact_id` = dono, só da própria empresa (outra empresa ou inexistente → `422 campos.contact_id`
  "Cliente não encontrado.").
- **Placa repetida** (decisão [W] 2026-10-05, "o erp deve recusar duas placa ativas"): placa já em
  outro veículo ativo (não excluído) da empresa, como principal ou de reboque →
  `422 { erro:"validacao", campos:{ placa | placa_secundaria: "Esta placa já está em outro veículo ativo." },
  veiculo_existente_id }` — o app oferece abrir o veículo existente. A comparação ignora hífen, espaço,
  ponto e maiúscula/minúscula. Veículo excluído e placa de outra empresa não bloqueiam.
- `201` → o item no mesmo formato da lista (`id`, `placa`, `placa_secundaria`, `descricao`, `ano`, `cliente`,
  `cliente_id`, `km`, `cor`), para o app seguir direto para a nova OS.
- `422 { erro:"validacao", campos }` com as mensagens da web · `403 sem_permissao` sem
  `oficinaauto.vehicle.view` ou `oficinaauto.vehicle.create`.
- Só insere em `vehicles`: sem OS, valor, estoque, venda ou cobrança. Fora por ora: consulta de placa
  externa, motor, combustível, chassi secundário e observações.

## Consulta de placa — leitura (pedido [W] 2026-10-05)

`GET /api/app/veiculos/opcoes` traz também `"consulta_placa": bool` — o app só mostra "Buscar" pela placa
quando é `true`. Em produção ele fica `false` enquanto não houver fornecedor contratado (o driver de
teste inventa dados e não responde em produção).

`GET /api/app/veiculos/consulta-placa/{placa}` (mesma permissão do cadastro; throttle 10/min — a consulta
pode ser paga). A mesma consulta da web: só dados técnicos, nunca proprietário; cache 24h por empresa+placa.

- `200 { "encontrado": true, "dados": { "placa", "ano_fabricacao", "ano_modelo", "cor", "chassi", "renavam",
  "marca_modelo" } }` — `marca_modelo` só para mostrar (o veículo não guarda marca/modelo).
- `200 { "encontrado": false, "mensagem": "Nenhum dado encontrado para esta placa." }`.
- Placa já em veículo ativo da empresa → `200 { "encontrado": false, "mensagem": "Esta placa já está em
  outro veículo ativo.", "veiculo_existente_id" }`, **sem** consultar o fornecedor.
- `422 campos.placa` placa fora do formato ABC1234/ABC1D23 · `502 indisponivel` fornecedor fora ·
  `503 sem_configuracao` "Consulta de placa não configurada." · `403 sem_permissao`.

## Editar veículo — escrita (pedido [W] 2026-10-05)

A lista `GET /api/app/veiculos` traz também `"pode_editar": bool` (`oficinaauto.vehicle.update` ou superadmin).

`GET /api/app/veiculos/{id}` → `{ id, placa, placa_secundaria, tipo, ano_fabricacao, ano_modelo, cor, km, chassi,
renavam, contact_id, cliente, pode_editar }` — `tipo` é a chave de `opcoes`; `km` é o km do **cadastro**
(o campo que o PUT grava; a lista mostra o maior km conhecido, cadastro ou OS). Outra empresa ou
inexistente → `404 nao_encontrado`. Mesma permissão da lista.

`PUT /api/app/veiculos/{id}` (throttle 30/min) com o mesmo corpo e as mesmas regras do POST:

- `200` → o item no formato da lista.
- Placa (principal ou reboque) **trocada** para uma de outro veículo ativo da empresa → `422` com
  `campos.placa | placa_secundaria` "Esta placa já está em outro veículo ativo." e `veiculo_existente_id`.
  Mantida a própria placa, passa (inclusive nos veículos que já estavam duplicados antes da regra) — igual à web.
- km menor que o atual é **aceito** (decisão [W] 2026-10-05: como a web).
- `contact_id` só da própria empresa · `403 sem_permissao` sem `vehicle.view` ou `vehicle.update` ·
  `404` outra empresa · `503 sem_configuracao`.
- Só atualiza `vehicles`: sem valor, estoque ou cobrança. As OS já existentes guardam o próprio cliente e
  km; **placa e tipo mostrados na OS vêm do veículo**, então refletem a edição.

## Excluir veículo — escrita (pedido [W] 2026-10-05)

`GET /api/app/veiculos/{id}` traz também `"pode_excluir": bool` (`oficinaauto.vehicle.delete`, a mesma da
policy da web).

`DELETE /api/app/veiculos/{id}` (throttle 30/min), como o destroy da web: **soft delete** — o veículo some
das listas e a placa fica livre para outro cadastro; a web **não** tem como restaurar (para o usuário é
definitivo). Sem valor, estoque ou cobrança.

- `200 { "ok": true }`.
- **OS em andamento** (decisão [W] 2026-10-05) → `409 { erro:"em_uso", mensagem, os_abertas }`, ex.
  "Este veículo tem 2 OS em andamento. Encerre as OS antes de excluir." Em andamento = etapa não terminal,
  ou OS de mecânica ainda sem pipeline (conta na Recepção, como na 07). OS encerrada ou fora do processo
  da oficina não impede.
- `404 nao_encontrado` outra empresa ou inexistente · `403 sem_permissao` sem `vehicle.view` ou
  `vehicle.delete` · `503 sem_configuracao`.

## Histórico de km — leitura (pedido [W] 2026-10-05)

Não há tabela de leituras de km. O histórico vem do `GET /api/app/veiculos/{id}/os`, só com campos a mais
(quem já consome a rota não muda):

- cada item ganha `"km": int | null` — o km anotado **na entrada daquela OS** (`mileage_at_service`); a `data`
  do item é a da entrada (`entered_at`; sem ela, a de criação).
- a raiz ganha `"km_cadastro": int | null` (km do cadastro do veículo) e `"cadastrado_em": "AAAA-MM-DD" | null`.
- Vale o mesmo limite de OS do histórico (as 200 mais recentes).

## Lembrete de revisão por km (decisão [W] 2026-10-06)

Parte interna da US-AUTO-014: **só a oficina** é avisada, dentro do app, pelo **km real anotado** (o maior
km conhecido: cadastro ou entrada de OS). Sem WhatsApp ao cliente, sem estimativa por média.

- Coluna nova `vehicles.next_service_km` (inteiro ≥ 0, nula), preenchida à mão. No app: `proxima_revisao_km`.
- `POST` e `PUT /api/app/veiculos` aceitam `"proxima_revisao_km": int | null` (≥ 0; pode estar abaixo do km
  atual — revisão atrasada). O `PUT` substitui o cadastro inteiro: omitir o campo apaga a próxima revisão.
- `GET /api/app/veiculos/{id}` e cada item da lista devolvem `proxima_revisao_km`. O app calcula
  `faltam = proxima_revisao_km − km`, com o `km` que a lista já manda (o maior conhecido).
- `GET /api/app/veiculos?revisao=1` → só os veículos com a próxima revisão marcada e o km real a até
  `revisao_aviso_km` dela, ou já passado; ordem do mais atrasado ao que falta mais.
- A lista traz sempre `"revisao_proxima": N` (contagem com o mesmo critério, independente do filtro e da
  busca) e `"revisao_aviso_km": 1000` (decisão [W]; o app só exibe).
