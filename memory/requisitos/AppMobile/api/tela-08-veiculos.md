# App — Veículos (tela 08) — só leitura ✅

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
