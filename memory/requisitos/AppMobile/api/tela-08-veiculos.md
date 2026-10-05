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
