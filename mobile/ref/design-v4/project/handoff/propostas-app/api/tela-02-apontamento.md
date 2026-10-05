# Tela 02 · Apontamento por QR (P8) ⬜

> Proposta. Vira `api/tela-02-apontamento.md` no ERP.

## Já existe ✅ (web, sessão — `Modules/ComunicacaoVisual`, US-COMVIS-004)
- Rotas em `/comunicacao-visual/api/apontamentos`: `em-andamento`, `index`, `iniciar`, `{apontamento}/finalizar` e `{apontamento}/cancelar` (throttle 60/min).
- **Não há pausa.** A US aponta por spool de impressão.

## Novo ⬜ — mesmas funções do `ApontamentoController`, com token
`GET /api/app/producao/apontamentos/em-andamento` → `{ item: null | { id, pedido: { id, numero, resumo, cliente }, etapa, iniciado_em } }`

`GET /api/app/producao/etiqueta/{codigo}` → resolve o QR ou o número digitado:
```json
{ "pedido": { "id": 4812, "numero": "4812", "resumo": "Fachada ACM + letra caixa", "cliente": "Ótica Visão Clara" },
  "etapa": { "chave": "in_production", "rotulo": "Produção · Impressão" }, "pode_apontar": true }
```
- O QR leva `oimpresso:p:<business_hash>:<pedido_id>`. Se o `business_hash` for de outra empresa → 404, sem dizer que o pedido existe.

`POST /api/app/producao/apontamentos/iniciar` `{ pedido_id, etapa }` → `201 { id, iniciado_em }`
- Se já houver um em andamento do mesmo operador → `409 em_andamento` com o item. O app oferece finalizar o anterior.

`POST /api/app/producao/apontamentos/{id}/finalizar` → `200 { id, minutos }`
`POST /api/app/producao/apontamentos/{id}/cancelar` → `200` (não conta tempo)

| Código | Quando |
|---|---|
| `403 sem_permissao` | sem `comvis.os.view` ou permissão de apontar |
| `404 nao_encontrado` | etiqueta de outro business ou inexistente |
| `409 em_andamento` | já há um apontamento aberto |

## ERP — etiqueta
O PDF da OS/pedido ganha o QR com o código acima. É tarefa separada, no módulo de impressão.

## App
Câmera para ler o QR, pedida no primeiro uso. Número digitado como alternativa. O cronômetro é só exibição: o tempo que vale é o do servidor (`iniciado_em`).

## Ajuste da empresa
`app_apontamento_exige_qr` (padrão `false`). Com `true`, o app esconde "Digitar número", e `GET /producao/etiqueta/{codigo}` só aceita o formato do QR → `422 campos.codigo`.
