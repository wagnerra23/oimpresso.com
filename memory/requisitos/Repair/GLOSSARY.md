# Glossário · Repair

## Diagnostic fee
Taxa cobrada antes do reparo começar. Cobre análise do equipamento. Abatida no total se cliente aprovar orçamento.

## Repair Job
Ordem de reparo. Vive em `repair_jobs`. Tem FK pra POS transaction e state machine.

## Repair Status
Estágio atual do reparo: `received`, `in_diagnosis`, `waiting_parts`, `in_progress`, `ready`, `delivered`, `canceled`.

## SLA (Service Level Agreement)
Tempo máximo acordado por estágio. Se ultrapassa, dispara alerta pro gerente.

## Technician
Usuário com role `repair.technician`. Pode avançar status e atribuir peças.

## Warranty
Garantia do serviço. Período em dias após entrega. Reparo dentro da garantia é gratuito (sem cobrar nova diagnostic fee).
