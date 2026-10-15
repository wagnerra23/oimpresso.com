# Tela 22 · Protocolo de entrega (P3) ⬜

> ⬜ **Proposta, rota ainda não existe no ERP.** Parte do contrato da API do app ([API-CONTRATO-v1.md](../API-CONTRATO-v1.md): regras gerais §0, Início §6). Origem: handoff `propostas-app` do Design, conferido em 2026-10-05. Vira ✅ no PR 11 do playbook.

## Novo ⬜ — `POST /api/app/pedidos/{id}/entrega`
Header `Idempotency-Key` obrigatório.

```json
{ "itens": [ { "linha_id": 5512, "conferido": true } ],
  "recebido_por": "Dra. Paula Reis",
  "documento": null,
  "assinatura_png": "data:image/png;base64,…",
  "sem_assinatura_motivo": null,
  "lat": -26.8977, "lng": -49.2316, "precisao_m": 12,
  "etapa_esperada": "delivery" }
```

- Deve vir **ou** `assinatura_png` (≤ 200 KB, PNG) **ou** `sem_assinatura_motivo` (5–200 caracteres). Os dois vazios → `422 campos.assinatura_png`.
- Todos os `itens` do pedido com `conferido: true`. Se faltar algum → `422 campos.itens`.
- `lat`/`lng` são opcionais. Sem permissão de localização, vão `null`. A localização é registro, não trava a entrega.
- O ERP grava o protocolo (`ENT-NNNN` por business), anexa a assinatura em `arquivos` e executa a ação da FSM que sai de "Entrega". Tudo numa transação só.

**201**:
```json
{ "protocolo": "ENT-0418", "registrado_em": "2026-10-05T14:22:00Z", "recebido_por": "Dra. Paula Reis",
  "com_assinatura": true, "pedido": { "…": "GET /pedidos/{id} já na etapa nova" } }
```

| Código | Quando |
|---|---|
| `403 sem_permissao` | sem a ação de entrega em `acoes[]` |
| `409 etapa_mudou` | o pedido não está em Entrega |
| `422 validacao` | itens, recebido_por, assinatura/motivo |
| `422 bloqueado` | a FSM recusou |

## Ajustes da empresa (chaves `app_*` em `pos_settings`, PR 01 do playbook)
- `app_entrega_exige_assinatura` (padrão `false`). Com `false`, vale "Recebido sem assinatura" com motivo. Com `true`, sem `assinatura_png` → `422 campos.assinatura_png`, e o app esconde a opção.
- `app_entrega_exige_localizacao` (padrão `false`). Com `true`, `lat`/`lng` nulos → `422 campos.lat`.

## LGPD
A assinatura é dado pessoal, mas **não é biométrica**: é imagem, sem dinâmica de traço. Base: execução de contrato (Art. 7º, V).
