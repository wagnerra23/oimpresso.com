# Tela 22 · Avançar etapa do pedido (P1) ✅

> Parte do contrato da API do app ([API-CONTRATO-v1.md](../API-CONTRATO-v1.md): regras gerais §0, Pedidos §2, Início §6). Origem: handoff `propostas-app` do Design, conferido em 2026-10-05. Era ⬜ até o PR 04 do playbook (2026-10-15), que criou a rota.

## `GET /api/app/pedidos/{id}` → `acoes[{chave, rotulo, pode}]`
Só as ações da etapa atual que o app pode executar: as da **lista segura** (abaixo) liberadas pelo ajuste `app_acoes_fsm_liberadas`, e que no banco não tenham `side_effect_class` nem `event_class`. As demais ficam só na web e não vêm aqui. `pode` = o usuário passa no RBAC da FSM (`StageActionPolicy::canExecute`, o mesmo da web).

## `POST /api/app/pedidos/{id}/acao`
Header `Idempotency-Key` obrigatório (tabela `app_idempotencia`, rota `pedidos.acao`, a mesma tabela da venda §2.2). Throttle 30/min.

```json
{ "acao": "iniciar_producao", "motivo": null, "etapa_esperada": "quote_approved" }
```

- `etapa_esperada` (obrigatório) = a `etapa.chave` que o app mostrava. Diferente da atual → `409 etapa_mudou`. A venda é travada (`lockForUpdate`) antes da conferência, então duas requisições com chaves diferentes não avançam duas vezes.
- `motivo` ≤ 500 caracteres, opcional, vai para a trilha (`sale_stage_history.payload_snapshot`, com `origem: "app"`). Nenhuma ação da lista segura o exige hoje (a web também não exige).
- Escopo: a mesma visibilidade do §2 (`direct_sell.view` / `view_own_sell_only` / `view_commission_agent_sell` + locais). Pedido de outra empresa ou fora do "só os próprios" → 404, sem dizer que existe.
- Execução: `ExecuteStageActionService::execute` (o mesmo da web), dentro da transação que reserva a `Idempotency-Key`. Se a ação falha, a reserva some junto.

**200** → o mesmo JSON de `GET /api/app/pedidos/{id}`, já na etapa nova. Repetir a mesma chave com o mesmo corpo devolve a resposta gravada, sem executar de novo.

| Código | Quando | O app faz |
|---|---|---|
| `403 sem_permissao` | sem acesso a pedidos; ação fora da lista liberada; cadastro da ação com efeito colateral; RBAC da FSM recusou | some o botão |
| `404 nao_encontrado` | outro business, ou fora do "só os próprios" | volta para a lista |
| `409 etapa_mudou` | `etapa_esperada` ≠ atual, ou a ação não sai da etapa atual | recarrega o pedido e avisa |
| `409 em_andamento` | mesma `Idempotency-Key` ainda rodando | espera |
| `422 validacao` | `Idempotency-Key`, `acao` ou `etapa_esperada` faltando; `motivo` > 500 (`campos{}`) | erro no campo |
| `422 idempotencia_conflito` | a chave já foi usada com outro corpo | gera chave nova |
| `429` | throttle 30/min | "Tente de novo em instantes" |
| `500 falha` | erro inesperado (registrado no log) | "Não foi possível avançar o pedido" |

`422 bloqueado` (gate da FSM) **não ocorre hoje**: a venda não tem gate como a OS, e nenhuma ação da lista segura tem efeito colateral que possa recusar. Fica reservado para quando entrar ação com regra.

## Lista segura (regra mestre ⚠️) — `App\Services\AppLojas\AcoesDoPedido::SEGURAS`
Tirada ação por ação do processo `venda_com_producao` (`FsmProcessoVendaComProducaoSeeder`). **Precisa do ok do [W]** (o contrato já pedia).

| Ação | De → para | Por que entra |
|---|---|---|
| `iniciar_producao` | Aprovado → Em produção | sem side effect |
| `pausar_producao` | Em produção → Em espera | sem side effect |
| `concluir` | Entregue → Concluída | sem side effect |

Ficam fora (só na web): `cliente_aprovou` (ReservarEstoque), `reabrir_para_revisao` (LiberarReserva), `concluir_producao` (ConsumirEstoque), `cancelar_venda` (CancelarVendaCascade), `faturar`, `emitir_nfe`, `marcar_pago` (valor/fiscal), `cliente_rejeitou` (cancela sem a cascata), `entregar` (é do PR da entrega, P3, que exige protocolo e assinatura conforme o ajuste) e `enviar_orcamento` (orçamento em rascunho não aparece em Pedidos). Não há ação de retomar a partir de "Em espera" no seed: um pedido pausado pelo app só sai de lá pela web (cancelar).

## Ajuste da empresa
`app_acoes_fsm_liberadas` (`AjustesDaEmpresa`, padrão `[]` = todas as seguras), conferido no servidor em `AcoesDoPedido::liberadas`. Com itens, vale a interseção com a lista segura: chave fora dela nunca é liberada. Ação fora → não vem em `acoes[]` e o POST → `403 sem_permissao`. A aba "App das lojas" ainda não edita esta chave (`na_tela: false` no mapa central); a tela de escolha fica para depois.

## Teste de contrato
`tests/Feature/Sells/AppPedidoAcaoContratoTest.php` (lane `sells-pest`):
- `acoes[]` só com as seguras; POST avança pela FSM e grava a trilha com origem e motivo.
- Idempotência: mesma chave e mesmo corpo → mesma resposta, uma linha de trilha; corpo diferente → 422.
- 409 com `etapa_esperada` velha; ação fora da lista → 403; ajuste estreita a lista.
- Cross-tenant: tenant 98 × 2 (ADR 0358) → 404 e a etapa do outro não muda.
- 422 validacao; sem permissão de venda → 403.
