---
module: NotificationTemplate
status: em-construcao
status_nota: "Tela React no ar para todas as empresas desde 13/10 (#9115 seleção, #9159 editor, #9175 flag); faltam modo Visual, prévia, restaurar padrão, QA F4 e retirada do Blade."
updated_at: "2026-10-13"
owner: W
lifecycle: ativo
---

# BRIEFING — Modelos de notificação

Dossiê da tela core /notification-templates, não módulo nWidart novo. Origem: thread Sistema/06 e pacote aprovado em 19/08. A Blade e os patches de segurança já existiam no main e5ec05dda251; não foram duplicados neste F1.

- Domínio e sequência: [SPEC](SPEC.md).
- Caminho de execução: [RUNBOOK](RUNBOOK-modelos-notificacao.md).
- Contrato de preservação: [Paridade](modelos-notificacao-parity.md).
- Backend: app/Http/Controllers/NotificationTemplateController.php e app/NotificationTemplate.php.
- Teste: tests/Feature/NotificationTemplateTest.php. UC-NOT-18/25 ficaram todo; baseline e JUnit são tratados no próximo recorte, sem declarar a migração concluída.
- Fonte visual: prototipo-ui/cowork/Wagner/notificacoes-page.jsx e pacote cowork-inbox/notificacoes. Charter/casos entram com a Page, sem criar trio órfão.

Contas é o segundo recorte da Sistema/06 e exige pré-flight separado. Não houve alteração de flag, valores, estoque ou envio ao cliente nesta F1.
