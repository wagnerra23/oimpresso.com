# App — Agenda de revisão da Oficina (aba da Oficina) — escrita sem valor nem estoque

> Parte do contrato da API do app ([API-CONTRATO-v1.md](../API-CONTRATO-v1.md): regras gerais §0).
> O protótipo não tem tela de agenda, por isso não há número: é uma aba da Oficina, ao lado de OS
> ([tela 07](tela-07-ordens-de-servico.md)) e Veículos ([tela 08](tela-08-veiculos.md)).

**Decisão [W] 2026-10-06:** o agendamento guarda veículo + cliente (sugerido pelo dono do veículo) +
dia e hora + observação curta. Na chegada, "Abrir OS" abre a Nova OS preenchida, e o agendamento fica
`atendido` e ligado à OS. Na web vem depois, em outro PR.

**Padrões do gerente da fila** (o [W] pode mudar): dois agendamentos no mesmo horário são permitidos (a
oficina tem mais de um box); início em dia passado é recusado (o próprio dia vale); as permissões são as
de criar OS.

**Acesso:** listar = a mesma regra da tela 07 (módulo `oficina_auto_module` no pacote + permissão
`oficinaauto.service_order.view`). Agendar e cancelar = também `oficinaauto.service_order.create` e o
processo da oficina cadastrado (o mesmo `pode_criar` da tela 07). Sem acesso → `403 sem_permissao`.

**Tabela:** `oficina_agendamentos` (própria da Oficina; o `bookings` do core é reserva de restaurante e
não é usado). `business_id` indexado + FK; tudo escopado pelo business do token (Tier 0, ADR 0093).

## Listar

`GET /api/app/agendamentos?de=AAAA-MM-DD&ate=AAAA-MM-DD`

```json
{ "itens": [ {
    "id": 31, "inicio": "2026-10-08T09:30",
    "veiculo": { "id": 7, "placa": "RLV2E48", "descricao": "Caminhão" },
    "cliente": { "id": 12, "nome": "Transportes Vale Norte" },
    "observacao": "Revisão 50 mil", "status": "agendado", "os_id": null } ],
  "pode_criar": true }
```

- Ordem: `inicio` crescente (desempate pelo id). Traz `agendado`, `atendido` e `cancelado`; o app filtra.
- `inicio` no fuso da empresa (como a `data` das OS), sem segundos.
- `veiculo.descricao` = a mesma string do `GET /api/app/veiculos` (rótulo do tipo; o cadastro não tem
  marca/modelo); `null` se o tipo não tem rótulo. `cliente` = `null` se não informado.
- Sem `de`: hoje. Sem `ate`: `de` + 30 dias. Ambos inclusivos. Janela máxima 92 dias.
- `422 { erro:"validacao", campos }`: data fora do formato, `ate` antes de `de`, janela acima de 92 dias.

## Agendar

`POST /api/app/agendamentos` (throttle 30/min)

```json
{ "vehicle_id": 7, "contact_id": 12, "inicio": "2026-10-08T09:30", "observacao": "Revisão 50 mil" }
```

- `vehicle_id` e `inicio` (`AAAA-MM-DDTHH:MM`) obrigatórios; `contact_id` e `observacao` (≤ 500) opcionais.
  O app pré-preenche o cliente com o `cliente_id` do veículo (tela 08); a API não força.
- Veículo e cliente têm de ser do business do token: outra empresa ou inexistente → "Veículo não
  encontrado." / "Cliente não encontrado.".
- Dia passado → `campos.inicio` "O agendamento não pode ser num dia que já passou." (hora já passada de
  hoje é aceita). Mesmo horário de outro agendamento é aceito.
- `201` → o item (formato da lista) · `422 { erro:"validacao", campos }` · `403 sem_permissao`.

## Cancelar

`POST /api/app/agendamentos/{id}/cancelar` (throttle 30/min) `{ "motivo": "Cliente remarcou" }`

- `motivo` opcional (≤ 500), guardado em `motivo_cancelamento`.
- `200` → o item com `status: "cancelado"` · `404 nao_encontrado` (outra empresa ou inexistente) ·
  `422 { erro:"estado_invalido", mensagem }` se já está `atendido` ("Este agendamento já virou OS.") ou
  `cancelado` ("Este agendamento já foi cancelado.").

## Abrir OS

O app abre a Nova OS (tela 07) preenchida com o veículo e o cliente do agendamento e envia
`POST /api/app/os` com `agendamento_id`. Na mesma transação a OS é criada e o agendamento vira
`atendido` com `os_id`. Regras e erros em [tela-07 §Nova OS](tela-07-ordens-de-servico.md).

## Contrato

`tests/Feature/Sells/AppOficinaAgendaApiContratoTest.php` (lane Sells, tenants 98 × 99): listar, agendar,
validação, cancelar, Abrir OS que atende, e vazamento entre empresas com controle positivo em par.
