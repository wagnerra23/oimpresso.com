# Tela 22 · Avançar etapa do pedido (P1) ⬜

> Proposta. Vira `memory/requisitos/AppMobile/api/tela-22-avancar-etapa.md` no ERP. Regras gerais: §0 do API-CONTRATO-v1.

## Já existe ✅
- `GET /api/app/pedidos/{id}` devolve `acoes[{chave, rotulo, pode}]`, que vêm de `SaleFsmActionController::actions` (§2). Na v1 elas são só leitura.
- Na OS, o mesmo padrão já roda: `POST /api/app/os/{id}/acoes/{chave}`, via `AcoesOs`.

## Novo ⬜ — `POST /api/app/pedidos/{id}/acao`
O nome segue o §2 (`/acao {acao}` via `ExecuteStageActionService`). Não usar `/acoes/{chave}`.

Header `Idempotency-Key` obrigatório (tabela `app_idempotencia`, a mesma da venda §2.2).

```json
{ "acao": "send_to_production", "motivo": null, "etapa_esperada": "quote_approved" }
```

- `etapa_esperada` = a `etapa.chave` que o app mostrava. Se for diferente da atual → `409 etapa_mudou`. Isso protege contra o duplo toque e contra dois usuários mexendo ao mesmo tempo.
- `motivo` ≤ 500 caracteres, vai para a trilha da FSM. Só é obrigatório nas ações que a web já exige.
- Escopo: a mesma visibilidade do §2 (`view_own_sell_only` etc.). Pedido de outra empresa → 404.

**200** → o mesmo JSON de `GET /api/app/pedidos/{id}`, já na etapa nova.

| Código | Quando | O app faz |
|---|---|---|
| `403 sem_permissao` | ação com `pode: false` | some o botão |
| `404 nao_encontrado` | outro business, ou fora do "só os próprios" | volta para a lista |
| `409 etapa_mudou` | `etapa_esperada` ≠ atual | recarrega o pedido e avisa |
| `409 em_andamento` | mesma `Idempotency-Key` ainda rodando | espera |
| `422 bloqueado` | a FSM recusou (falta item, pagamento…) | mostra a `mensagem` |
| `422 validacao` | `motivo` faltando ou longo | erro no campo |
| `429` | throttle 30/min | "Tente de novo em instantes" |

## Regra mestre ⚠️
Se a ação **reserva ou baixa estoque, ou cancela cobrança** (o §2 avisa que várias fazem isso), ela não entra na 1ª entrega. A primeira versão libera **só as ações sem efeito em valor ou estoque**. A lista dessas ações sai do `ExecuteStageActionService` e precisa do ok do [W].

## Teste de contrato
- Cross-tenant: tenant 98 × 2 (ADR 0358).
- 409 com `etapa_esperada` velha.
- Idempotência: mesma chave e mesmo corpo → mesma resposta.

## Ajuste da empresa
`app_acoes_fsm_liberadas` (padrão `[]` = todas as seguras). A empresa só escolhe dentro da lista segura. Ação fora da lista → não vem em `acoes[]` e o POST → `403 sem_permissao`.
