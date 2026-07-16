---
page_id: settings-payment-gateways-cnab-retorno
status: draft
owner: wagner
created: 2026-05-31
route: /settings/payment-gateways/{credentialId}/cnab-retorno
controller: Modules/PaymentGateway/Http/Controllers/Settings/PaymentGatewaysCnabRetornoController
page: /settings/payment-gateways/{id}/cnab-retorno
component: resources/js/Pages/Settings/PaymentGateways/CnabRetorno.tsx
related_prototype: n/a (herda PT-02 Form-Drawer; segue o Padrão de Tela)
last_validated: "2026-05-31"
parent_module: PaymentGateway
tier: B
charter_version: 1
---

# Charter · Retorno CNAB (Payment Gateways)

## Mission
Permitir que o operador financeiro envie arquivos de retorno bancário (CNAB .ret/.txt)
de um gateway e acompanhe a conciliação — registros baixados, valor total e erros por arquivo.

## Goals
- G1 — Upload de 1 arquivo CNAB por vez (drag&drop ou seleção), com preview de nome/tamanho antes de enviar.
- G2 — Validação de formato/limite no front (extensões + tamanho de `limites`) espelhando o `validate()` do Controller.
- G3 — Histórico de uploads (deferred `uploads`) com tamanho, datas, contadores qtd_paga/cancelada/vencida/registrada e status.
- G4 — Erros de processamento (`erros[]`) visíveis e expansíveis por linha do histórico.

## Non-Goals
- NG1 — (revisar Wagner) edição/reprocessamento de arquivo já enviado?
- NG2 — (revisar Wagner) download do arquivo original ou do relatório de baixa?
- NG3 — (revisar Wagner) upload em lote / múltiplos arquivos simultâneos?

## UX targets
- Dropzone como ação primária; histórico secundário abaixo.
- EmptyState canon quando sem histórico; skeleton durante o deferred.
- Paleta 100% tokens DS v4 (accent/danger/success/warning); zero cor crua.

## Automation hooks / Anti-hooks
- (revisar Wagner) notificar/registrar em AuditLog cada processamento?
- (revisar Wagner) Anti-hook: nunca auto-reprocessar nem auto-baixar cobrança sem ação humana?
