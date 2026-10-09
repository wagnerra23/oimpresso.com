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
**Implementado em:** _pendente_ (história documental; F1 foi publicada pela #9105, sem implementação de runtime atribuída a esta US).
Plano preparado em `memory/requisitos/NotificationTemplate/RUNBOOK-modelos-notificacao.md` e `memory/requisitos/NotificationTemplate/modelos-notificacao-parity.md`.
F1 foi publicada pelo merge da #9105 em 09/10.

### US-NOTIF-002 · Baseline e ação dual F2
blocked_by: US-NOTIF-001
**Implementado em:** `app/Http/Controllers/NotificationTemplateController.php`.
**Aceite:** flag ausente/OFF preserva Blade; X-Inertia + flag ON responde com três grupos adiados; partial reload preserva os nove campos, módulos e tenant 98/99; sem permissão recebe 403 antes da flag.
**Testado em:** `tests/Feature/NotificationTemplateDualTest.php` (CT 100, commit 04fd4aadd0, sete passes/31 assertions).
Baseline publicado pela #9107. Em 09/10, CT 100: baseline + primeiro dual tiveram 25 passes/78 assertions e dois todo; o dual final teve sete passes/31 assertions (commit 04fd4aadd0). X-Inertia e flag useV2NotificationTemplates habilitam três grupos adiados; flag ausente ficou OFF no serviço real. Permissão e tenant foram provados. CI e publicação do dual ainda pendentes nesta entrega; F3/F4/F5 não foram concluídas.

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
