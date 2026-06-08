# Runbook · Repair

## Problema: Cliente não consegue consultar status no portal

**Sintoma**: `/repair-status` diz "não encontrado" com dados corretos.

**Causa possível**: Rate-limit ativado por muitas tentativas do mesmo IP.

**Correção**:
- Limpar cache `cache:forget repair.status.attempts.{IP}`.
- Verificar se número do reparo e telefone/CPF batem com registro original.

## Problema: SMS não é enviado ao mudar status

**Sintoma**: Técnico marca "ready" mas cliente não recebe SMS.

**Causa possível #1**: Queue worker parou.

**Correção**: `php artisan queue:restart` + verificar supervisord em produção.

**Causa possível #2**: Saldo zerado na operadora SMS.

**Correção**: verificar painel do provider (Twilio/Zenvia/etc).

## Problema: Status pula etapa direto pra "ready"

**Sintoma**: Técnico muda de "received" direto pra "ready" sem passar por diagnosis.

**Causa**: Business desligou state machine estrita.

**Correção**: é feature, mas pode ligar via config `repair.enforce_state_transitions = true`.

## Problema: Peça aguardada há 30+ dias

**Sintoma**: Reparo travado em "waiting_parts".

**Correção**: processo — contatar fornecedor, ou trocar peça por equivalente, ou retornar ao cliente.

## Comandos úteis

```bash
# Lista reparos com SLA estourado
php artisan repair:sla-breach

# Audit Repair
php artisan docvault:audit-module Repair --save
```
