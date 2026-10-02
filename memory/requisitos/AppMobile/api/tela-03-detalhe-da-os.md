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
