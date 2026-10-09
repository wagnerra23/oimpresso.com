---
module: NotificationTemplate
version: "1.0"
last_updated: "2026-10-09"
owner: wagner
---

# Modelos de notificação — migração MWART

Epic: Sistema/06, primeiro recorte (Modelos de notificação). Pedido [W] em 09/10: executar a sequência de threads. Reaproveita o pacote aprovado em 19/08 e o backend existente; Contas tem pré-flight separado.

## User stories

### US-NOTIF-001 · Plano F1
blocked_by: —
**Implementado em:** `memory/requisitos/NotificationTemplate/RUNBOOK-modelos-notificacao.md`, `memory/requisitos/NotificationTemplate/modelos-notificacao-parity.md` (#9105 mergeado em 09/10).
Plano preparado em `memory/requisitos/NotificationTemplate/RUNBOOK-modelos-notificacao.md` e `memory/requisitos/NotificationTemplate/modelos-notificacao-parity.md`.
F1 foi publicada pelo merge da #9105 em 09/10.

### US-NOTIF-002 · Baseline e ação dual F2
blocked_by: US-NOTIF-001
**Implementado em:** _pendente_.
Executar NotificationTemplateTest no CT 100, preservando os dois todo e acrescentando cobertura para bcc e modelos de módulos. Depois flag OFF e resposta Blade/Inertia com gates de permissão e isolamento. Não declarar done por presença de arquivo.

### US-NOTIF-003 · Seleção de modelos F3
blocked_by: US-NOTIF-002
**Implementado em:** _pendente_.
Page NotificationTemplate/Index no rail/painel do protótipo aprovado, mantendo grupos dinâmicos, rotas e nomes legíveis. Importar charter e casos com .tsx irmão e provas vinculadas.

### US-NOTIF-004 · Edição e prévia por canal F3
blocked_by: US-NOTIF-003
**Implementado em:** _pendente_.
Salvar em lote, canais e automáticos conforme o contrato; preservar tags e sanitizar prévia. Reusar a rota de teste de envio existente. Não ampliar destinatários ou canais.

### US-NOTIF-005 · QA F4
blocked_by: US-NOTIF-004
**Implementado em:** _pendente_.
Provar round-trip, acessibilidade, layout e isolamento. UC-NOT-18/25 continuam sem prova até criar fixtures reais de teste. Registrar smoke sem mensagens para contatos reais.

### US-NOTIF-006 · Cutover F5
blocked_by: US-NOTIF-005
**Implementado em:** _pendente_.
Ativar somente com QA e decisão de cutover registrados; canary e monitoramento conforme ADR 0104. Não confundir pedido da sequência com prova de QA.
