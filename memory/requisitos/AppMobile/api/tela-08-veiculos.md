# App — Veículos (tela 08) — só leitura ✅

> Parte do contrato da API do app ([API-CONTRATO-v1.md](../API-CONTRATO-v1.md): regras gerais §0, Início §6). Regra de acesso da área Oficina: [tela-07-ordens-de-servico.md](tela-07-ordens-de-servico.md).

`GET /api/app/veiculos?q=&pagina=N` (20 por página, ordem por placa)

```json
{ "itens": [ { "id": 7, "placa": "RLV2E48", "placa_secundaria": "REB1A23", "descricao": "Caminhão",
    "ano": "2019/2020", "cliente": "Transportes Vale Norte", "km": 48312, "cor": "Branco" } ],
  "total": 91, "pagina": 1, "tem_mais": true }
```

- Veículos de cliente do business (`vehicles`, Modules/OficinaAuto). Permissão da tela web de
  veículos: `oficinaauto.vehicle.view` + pacote da Oficina; sem ela → `403 sem_permissao`.
- O cadastro não tem marca/modelo: `descricao` = rótulo do tipo (como a web). `ano` =
  fabricação/modelo. `cliente` = dono do veículo. `cor`/`placa_secundaria` `null` quando vazias.
- `km` = maior km conhecido (cadastro do veículo ou km de entrada das OS dele); `null` sem registro.
- Busca `q`: placa (principal e do reboque), rótulo do tipo e nome do dono.
- Sem padrão de placa: o app deduz pelo formato.
