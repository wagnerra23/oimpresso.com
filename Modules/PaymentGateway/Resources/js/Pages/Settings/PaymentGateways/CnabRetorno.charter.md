---
id: resources-js-pages-settings-payment-gateways-cnab-retorno-charter
page_id: settings-payment-gateways-cnab-retorno
status: draft
owner: wagner
created: 2026-05-31
route: /settings/payment-gateways/{credentialId}/cnab-retorno
controller: Modules/PaymentGateway/Http/Controllers/Settings/PaymentGatewaysCnabRetornoController
page: /settings/payment-gateways/{id}/cnab-retorno
component: Modules/PaymentGateway/Resources/js/Pages/Settings/PaymentGateways/CnabRetorno.tsx
related_prototype: n/a (herda PT-02 Form-Drawer; segue o Padrão de Tela)  # 2026-09-09 [C]: declaração de PT herdado. ⚠️ ERRATA do recibo herdado: buscar `cnab-retorno`/`CnabRetorno` dá 0, mas isso é grep de string literal (§5 2026-08-18) — o termo de domínio `cnab` dá 15 hits em 5 arquivos do espelho, e existem DOIS `SheetRemessaRetorno` (`boletos-page.jsx:509` e `pg-cobranca-page.jsx:863`), sheet de 560px que lista arquivos REM/RET dentro da Cobrança. Logo: vocabulário visual PARCIAL existe; a TELA (dropzone + validação + contadores por arquivo, G1-G4 deste charter) não. Não promover: sheet de lista ≠ tela de importação. medido 2026-09-09 pelas 3 pernas (repo inteiro com --hidden, projeto Cowork por ID via DesignSync.list_files, espelho). Ver memory/requisitos/_DesignSystem/INVENTARIO-ANCORAS-2026-09-09.md §5.3 + §5.7.
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
