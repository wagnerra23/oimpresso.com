# Tela 22 · Protocolo de entrega (P3) ✅

> Parte do contrato da API do app ([API-CONTRATO-v1.md](../API-CONTRATO-v1.md): regras gerais §0, Pedidos §2, Início §6). Origem: handoff `propostas-app` do Design, conferido em 2026-10-05. Era ⬜ até o PR 11 do playbook (2026-10-15), que criou a rota. Controller: `app/Http/Controllers/Api/App/PedidoEntregaController.php`. Teste: `tests/Feature/Sells/AppPedidoEntregaContratoTest.php` (lane Sells Pest, MySQL).

## `GET /api/app/pedidos/{id}` → `entrega` e `itens_venda[].linha_id`
- `entrega`: `null` quando a etapa atual não tem a ação `entregar` da FSM; senão `{ "pode": bool }`. `pode` = o usuário passa no RBAC da FSM (`StageActionPolicy::canExecute`, o mesmo da web) e a ação não tem `side_effect_class` nem `event_class` no cadastro.
- `entregar` **não** entra em `acoes[]`: não sai pela rota de ação (tela 22 · avançar etapa), porque exige protocolo e assinatura.
- `itens_venda[]` ganhou `linha_id` (id da linha de topo da venda), que o app devolve na conferência.

## `POST /api/app/pedidos/{id}/entrega`
Header `Idempotency-Key` obrigatório (tabela `app_idempotencia`, rota `pedidos.entrega`). Throttle 20/min.

```json
{ "itens": [ { "linha_id": 5512, "conferido": true } ],
  "recebido_por": "Dra. Paula Reis",
  "documento": null,
  "assinatura_png": "data:image/png;base64,…",
  "sem_assinatura_motivo": null,
  "lat": -26.8977, "lng": -49.2316, "precisao_m": 12,
  "etapa_esperada": "paid" }
```

- `etapa_esperada` (obrigatório) = a `etapa.chave` que o app mostrava (no processo `venda_com_producao`, a etapa da qual sai `entregar` é `paid`; o grupo exibido é "Entrega"). Diferente da atual, ou etapa atual sem a ação `entregar` → `409 etapa_mudou`. A venda é travada (`lockForUpdate`) antes da conferência.
- Deve vir **ou** `assinatura_png` (data URL `data:image/png;base64,`, PNG válido de até 200 KB depois de decodificado) **ou** `sem_assinatura_motivo` (5–200 caracteres). Os dois vazios ou PNG inválido → `422 campos.assinatura_png`. Com assinatura, o motivo é descartado.
- Todas as linhas de topo do pedido em `itens` com `conferido: true`. Se faltar alguma, sobrar alguma ou uma vier `false` → `422 campos.itens`.
- `recebido_por` obrigatório (2–120). `documento` opcional (≤ 40).
- `lat`/`lng` opcionais e juntos (um sem o outro → 422 no que faltou). Sem permissão de localização, vão `null`. A localização é registro, não trava a entrega — salvo com o ajuste abaixo.
- Escopo: a mesma visibilidade do §2 (`direct_sell.view` / `view_own_sell_only` / `view_commission_agent_sell` + locais). Pedido de outra empresa ou fora do "só os próprios" → 404, sem dizer que existe.
- Numa transação só: reserva da `Idempotency-Key`, protocolo em `app_entregas` (`ENT-NNNN`, sequencial **por business**), assinatura em `arquivos` (dono = a venda, `arquivable_type` `App\Transaction`, pelo contrato `App\Contracts\Arquivos\AnexosDoApp` — o mesmo dos anexos da OS, tela 03) e a ação `entregar` pelo `ExecuteStageActionService` (o mesmo da web), com `origem: "app"` e o `protocolo` na trilha (`sale_stage_history.payload_snapshot`). Qualquer falha desfaz tudo; só o blob da assinatura já escrito no disco fica sem registro.

**201**:
```json
{ "protocolo": "ENT-0418", "registrado_em": "2026-10-05T14:22:00Z", "recebido_por": "Dra. Paula Reis",
  "com_assinatura": true, "pedido": { "…": "GET /pedidos/{id} já na etapa nova" } }
```
Repetir a mesma chave com o mesmo corpo devolve a resposta gravada (201), sem registrar de novo. A mesma chave com corpo diferente → `422 idempotencia_conflito`.

| Código | Quando | O app faz |
|---|---|---|
| `403 sem_permissao` | sem acesso a pedidos; RBAC da FSM recusou `entregar`; cadastro da ação com efeito colateral (fica só na web) | some o botão |
| `404 nao_encontrado` | outro business, ou fora do "só os próprios" | volta para a lista |
| `409 etapa_mudou` | `etapa_esperada` velha, ou o pedido não está na etapa de Entrega | recarrega o pedido |
| `409 em_andamento` | a mesma chave ainda está sendo registrada | espera e repete |
| `422 validacao` | `campos.{itens, recebido_por, documento, assinatura_png, sem_assinatura_motivo, lat, lng, precisao_m, idempotency_key, etapa_esperada}` | mostra no campo |
| `422 idempotencia_conflito` | chave reaproveitada com outro corpo | gera chave nova |
| `422 bloqueado` | a FSM recusou por regra de domínio | mostra a mensagem |
| `503 sem_configuracao` | o módulo de arquivos não está carregado e veio assinatura | tenta mais tarde |
| `429` | mais de 20 por minuto | espera |

## Ajustes da empresa (chaves `app_*` em `pos_settings`, PR 01 do playbook) — conferidos no servidor
- `app_entrega_exige_assinatura` (padrão `false`). Com `false`, vale "Recebido sem assinatura" com motivo. Com `true`, sem `assinatura_png` → `422 campos.assinatura_png`, e o app esconde a opção.
- `app_entrega_exige_localizacao` (padrão `false`). Com `true`, `lat`/`lng` nulos → `422 campos.lat`.

## LGPD
A assinatura é dado pessoal, mas **não é biométrica**: é imagem, sem dinâmica de traço. Base: execução de contrato (Art. 7º, V). Fica em `arquivos` com o business da venda (Tier 0) e o Curador decide o disco (cofre cifrado quando sensível).
