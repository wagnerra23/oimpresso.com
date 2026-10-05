# App — Detalhe da OS (tela 03) — só leitura ✅

> Parte do contrato da API do app ([API-CONTRATO-v1.md](../API-CONTRATO-v1.md): regras gerais §0, Início §6).

`GET /api/app/os/{id}` — qualquer OS do business (inclusive terminal e fora do pipeline, abertas
pelo histórico do veículo). Outra empresa ou inexistente → `404 nao_encontrado`. Mesma permissão da 07.

```json
{ "id": 42, "numero": "OS-00042", "local": "Elevador 1",
  "etapa": { "chave": "em_execucao", "rotulo": "Em execução", "indice": 5, "total_etapas": 6, "terminal": false },
  "travada": false,
  "veiculo": { "placa": "RLV2E48", "descricao": "Caminhão", "km": 48312 },
  "cliente": { "id": 7, "nome": "Transportes Vale Norte" },
  "observacoes": "Barulho na suspensão",
  "vistoria": { "ok": 8, "atencao": 2, "critico": 1 },
  "itens": [ { "tipo": "peca", "descricao": "Bieleta", "quantidade": 2, "valor_unitario": 210.00, "valor": 420.00 } ],
  "totais": { "pecas": 420.00, "mao_de_obra": 330.00, "terceiros": 80.00, "total": 830.00 },
  "fotos_laudo": 3 }
```

- `local` = box da OS (texto livre); não existe cadastro de box/elevador.
- `etapa`: terminal → `indice: null, terminal: true`; OS de mecânica sem pipeline → etapa inicial;
  OS fora do processo da oficina (ex.: importadas sem pipeline) → `etapa: null`.
- `veiculo.km` = km na entrada da OS. `cliente` = cliente da OS (pode diferir do dono do veículo).
- O ERP não tem queixa/diagnóstico: vêm `observacoes` (Observações da OS) e `vistoria` (contagem
  dos itens da vistoria digital por severidade).
- `itens.tipo` ∈ `peca · mao_obra · servico_terceiro`; o ERP não guarda unidade nem horas.
- `totais` = soma dos itens por tipo; `total` = soma de todos (o mesmo número da 07 e da web).
- `fotos_laudo` = quantas fotos do laudo a OS tem (só contagem; o app não exibe nem tira foto, ADR 0383).

## Avançar etapa — escrita (sem valor nem estoque)

O `GET /api/app/os/{id}` traz também:

```json
"acoes": [ { "chave": "concluir_servico", "rotulo": "Concluir serviço", "critica": true, "pode": true,
             "bloqueio": "Falta: Orçamento com ≥ 1 item lançado." } ]
```

- Só as ações que **avançam** a OS na linha principal e saem da etapa atual, nesta ordem:
  `iniciar_diagnostico`, `enviar_orcamento`, `aprovar_pedir_pecas`, `aprovar_executar`, `pecas_chegaram`,
  `concluir_servico`, `entregar`. Cancelar, recusar orçamento, acionar garantia e o override do gate
  ficam só na web. OS fora do pipeline ou em etapa terminal → `[]`.
- `pode` = o usuário pode executar (permissão `oficinaauto.service_order.update` ou superadmin, e a
  regra de papel da ação, a mesma da web). `critica` = a ação é crítica ou pede confirmação (o app
  confirma antes). `bloqueio` = os requisitos do gate da etapa que faltam; `null` quando passa.

`POST /api/app/os/{id}/acoes/{chave}` (corpo vazio, sem override; throttle 30/min):

- `200` → o mesmo JSON do `GET /api/app/os/{id}`, já na etapa nova.
- `422 { erro:"bloqueado", mensagem }` quando o gate barra (a OS não muda).
- `409 { erro:"etapa_mudou", mensagem }` quando a ação não sai da etapa atual (outro usuário moveu antes).
- `422 { erro:"nao_suportada", mensagem }` para ação fora da lista acima.
- `403 sem_permissao` (sem permissão de ver ou de alterar OS) · `404 nao_encontrado` (inexistente ou de outra empresa).
- A transição passa pela FSM canônica (a mesma da web), com trilha em `sale_stage_history`. As ações do
  processo da oficina não têm efeito colateral; se no banco alguma tiver (`side_effect_class` ou
  `event_class`), o app não a mostra nem a executa — valor e estoque nunca mudam por aqui.
