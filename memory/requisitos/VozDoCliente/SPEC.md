---
id: requisitos-voz-do-cliente-spec
module: VozDoCliente
version: "1.0"
last_updated: "2026-07-30"
anchor_format: "v1"
status: em-construcao
owner: wagner
source_us: US-INFRA-002
---

# SPEC — VozDoCliente

## Autoridade e limite

Este documento registra o contrato funcional do módulo. A história original e seu
histórico de escopo continuam canônicos em
[US-INFRA-002](../Infra/SPEC.md#us-infra-002--client-signal--entidade--canal-estruturado).
O estado operacional e o que ainda não havia sido construído em 2026-07-28 estão no
[BRIEFING](BRIEFING.md). O inventário de arquivos é derivado pela máquina em
[SUPERFICIE.md](SUPERFICIE.md).

Em 2026-07-28, a fatia entregue de US-INFRA-002 foi fechada como **entidade + canal
autenticado**. Roteamento, conversão em US, contagem no brief, MCP e captura automática
de erro permaneceram fora deste contrato.

## Contrato funcional entregue

### US-INFRA-002 — capturar sinal autenticado no ERP

Uma pessoa autenticada e autorizada pôde registrar um relato dentro do ERP. O sinal
herdou o `business_id` da sessão, preservou o texto e a URL de origem e apareceu na
caixa de triagem do mesmo tenant.

Critérios de aceite:

- [x] `vozdocliente.reportar` ou `superadmin` autorizou o envio.
- [x] O relato aceitou texto entre 5 e 2.000 caracteres, severidade opcional de 0 a 4
  e URL opcional de até 500 caracteres.
- [x] O `business_id` foi obtido da sessão, nunca do request.
- [x] A ausência de um business válido na sessão bloqueou a gravação.
- [x] Texto normalizado idêntico no mesmo business foi deduplicado.
- [x] Texto idêntico em businesses diferentes permaneceu independente.
- [x] `vozdocliente.triar` ou `superadmin` protegeu a caixa de triagem.
- [x] A caixa exibiu somente sinais do business da sessão, com pendentes primeiro.

Implementação:

- [`StoreSinalRequest.php`](../../../Modules/VozDoCliente/Http/Requests/StoreSinalRequest.php)
- [`SinalController.php`](../../../Modules/VozDoCliente/Http/Controllers/SinalController.php)
- [`Sinal.php`](../../../Modules/VozDoCliente/Entities/Sinal.php)
- [`web.php`](../../../Modules/VozDoCliente/Routes/web.php)
- [`create_voz_sinais_table.php`](../../../Modules/VozDoCliente/Database/Migrations/2026_07_28_100000_create_voz_sinais_table.php)

Prova automatizada:

- [`SinalCrossTenantTest.php`](../../../Modules/VozDoCliente/Tests/Feature/SinalCrossTenantTest.php)
  cobriu isolamento biz=1 × biz=99, busca por ID, contagem por tenant e deduplicação.

## Invariantes

| ID | Regra | Prova |
|---|---|---|
| R-VOZ-001 | Nenhum sinal de um business pode aparecer em outro. | `SinalCrossTenantTest.php` + global scope de `Sinal` |
| R-VOZ-002 | O cliente não escolhe `business_id`; a sessão é a autoridade. | `SinalController::store` |
| R-VOZ-003 | O mesmo relato normalizado não cria duplicata dentro do tenant. | unique `(business_id, hash_origem)` + teste |
| R-VOZ-004 | O mesmo texto em tenants diferentes representa sinais diferentes. | hash inclui `business_id` + teste |
| R-VOZ-005 | Relatar e triar exigem permissões distintas. | `StoreSinalRequest` + `SinalController::index` |

## Fora deste contrato

Os itens abaixo permaneceram backlog em 2026-07-28 e não podem ser inferidos como
entregues pela existência deste módulo:

- botão global de relato no shell;
- roteamento automático para o módulo dono;
- ação de triagem que converte sinal em US;
- contagem no brief diário;
- tools MCP;
- captura automática de erro do browser;
- migração da caixa Blade para Inertia.

## Referências

- [SCOPE do módulo](../../../Modules/VozDoCliente/SCOPE.md)
- [ADR 0105 — cliente como sinal](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)
- [ADR 0093 — isolamento multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [RUNBOOK — criar módulo](../Infra/RUNBOOK-criar-modulo.md)
