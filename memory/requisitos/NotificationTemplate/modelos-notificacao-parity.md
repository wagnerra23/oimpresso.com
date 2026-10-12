# Paridade — Modelos de notificação

Mapa F1, main e5ec05dda251 (09/10). Origem: Blade notification_template/index e partials, controller e charter do pacote Cowork de 19/08. A coluna de prova indica teste existente, não resultado reexecutado nesta sessão.

| Contrato preservado | Fonte | Severidade | Prova existente / próxima prova |
|---|---|---|---|
| GET e POST exigem send_notification | Controller index/store | alta | UC-NOT-01; repetir com flag OFF/ON |
| Três grupos e modelos injetados por módulos | __grupos + índice Blade | alta | UC-NOT-02; testar extra de módulo na ação dual |
| Registro por business_id/template_for, lote sem duplicação | store | alta | UC-NOT-04/05/26 |
| Checkbox ausente zera os três auto_send | store | alta | UC-NOT-17; repetir round-trip da Page |
| Whitelist usa as mesmas chaves do índice | __grupos/store | alta | UC-NOT-27 |
| cc/bcc são um endereço por campo | partial tabs + store | alta | UC-NOT-24 cobre cc; acrescentar caso bcc |
| Tags desconhecidas preservadas e avisadas | charter R7 + persistência | média | UC-NOT-10 cobre round-trip; aviso da UI ainda precisa teste |
| send_ledger só oferece e-mail | partial tabs, charter R5 | alta | Teste UI por criar; regra backend D5 pendente no pacote |
| Automático apenas new_sale/payment_reminder na UI | partial tabs | alta | UC-NOT-16/17 cobrem persistência; restrição UI por provar |
| Agenda depende do módulo core habilitado por empresa | __grupos | alta | UC-NOT-31 |
| Teste de envio ignora destino forjado | rota test existente | alta | UC-NOT-22 |
| Seed PT-BR preserva modelos editados | model + migration | alta | UC-NOT-28/29 |
| Corpo do e-mail não executa script | caminho de envio + prévia | alta | UC-NOT-30 cobre envio; prévia React por provar |
| Canal vazio não envia e lembrete desligado não dispara | charter R9/R10 | alta | UC-NOT-18/25 são todo; faltam fixtures |

A Page não foi criada neste F1. As provas específicas da UI, pixels e cutover permaneceram pendentes. Decisões do pacote ainda não ratificadas devem ser lidas no original, sem transformar recomendação em aceite.
