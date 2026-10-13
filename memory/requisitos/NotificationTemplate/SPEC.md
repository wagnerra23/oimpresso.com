---
module: NotificationTemplate
version: "1.0"
last_updated: "2026-10-13"
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
**Testado em:** `tests/Feature/NotificationTemplate/DualTest.php` (CT 100, commit 04fd4aadd0, sete passes/31 assertions).
Baseline publicado pela #9107. Em 09/10, CT 100: baseline + primeiro dual tiveram 25 passes/78 assertions e dois todo; o dual final teve sete passes/31 assertions (commit 04fd4aadd0). X-Inertia e flag useV2NotificationTemplates habilitam três grupos adiados; flag ausente ficou OFF no serviço real. Permissão e tenant foram provados. CI e publicação do dual ainda pendentes nesta entrega; F3/F4/F5 não foram concluídas.

### US-NOTIF-003 · Seleção de modelos F3
blocked_by: US-NOTIF-002
**Implementado em:** `resources/js/Pages/NotificationTemplate/Index.tsx` · `app/Http/Controllers/NotificationTemplateController.php` · verificado@07e8891 (2026-10-13) — entregue pela #9115 (mergeada em 10/10) e no ar desde 13/10, com a flag ligada pela #9175; [W] confirmou a tela nova em produção.
**Aceite:** a Page abre com os grupos dinâmicos de modelos do próprio tenant, busca, atalhos e canais; "Editar modelos" continua abrindo o editor clássico em `?legacy=1`.
**Testado em:** `tests/js/notificacoes-selecao.test.tsx`.
Page NotificationTemplate/Index no rail/painel do protótipo aprovado, mantendo grupos dinâmicos, rotas e nomes legíveis. Primeiro recorte de seleção/consulta e trio foi preparado em `resources/js/Pages/NotificationTemplate/Index.tsx`; cinco testes de interação passaram em `tests/js/notificacoes-selecao.test.tsx`. Critérios completos do editor e QA ficaram pendentes; não foi declarada a US inteira implementada.

### US-NOTIF-004 · Edição e prévia por canal F3
blocked_by: US-NOTIF-003
**Implementado em:** _parcial_ · `resources/js/Pages/NotificationTemplate/Index.tsx` · `resources/js/Pages/NotificationTemplate/_components/editor-modelo.ts` · verificado@07e8891 (2026-10-13) · `resources/js/Pages/NotificationTemplate/_components/CorpoVisual.tsx` · `resources/js/Pages/NotificationTemplate/_components/Previa.tsx` — editor de texto/HTML pela #9159; modo Visual, prévia e restaurar padrão em 13/10. Falta a prévia passar pelo `HtmlSanitizer`: ela isola o HTML num iframe sandbox sem scripts, mas pode mostrar markup que o envio real remove.
**Aceite:** salvar em lote só os modelos alterados, por canal e com os automáticos, conforme o contrato; preservar tags e sanitizar a prévia; teste de envio pela rota existente, sem ampliar destinatários nem canais.
**Testado em:** `tests/js/notificacoes-editor.test.tsx` · `tests/js/notificacoes-visual.test.tsx`.
Salvar em lote, canais e automáticos conforme o contrato; preservar tags e sanitizar prévia. Reusar a rota de teste de envio existente. Não ampliar destinatários ou canais.
Recorte de 13/10: editor de texto/HTML por canal, salvar em lote só dos alterados, descartar, tags no cursor, aviso de tag desconhecida, segmentos de SMS, automáticos de new_sale e payment_reminder e teste de envio pela rota existente (só e-mail, versão gravada), em `resources/js/Pages/NotificationTemplate/Index.tsx` e `_components/editor-modelo.ts`. Em 13/10 entraram o modo Visual (iframe sandbox editável), a prévia por canal com dados de exemplo e o restaurar padrão (texto de fábrica vindo do controller, sem gravar). A âncora segue `_parcial_` só pela sanitização da prévia descrita acima.

### US-NOTIF-005 · QA F4
blocked_by: US-NOTIF-004
**Implementado em:** _pendente_.
Provar round-trip, acessibilidade, layout e isolamento. UC-NOT-18/25 continuam sem prova até criar fixtures reais de teste. Registrar smoke sem mensagens para contatos reais.

### US-NOTIF-006 · Cutover F5
blocked_by: US-NOTIF-005
**Implementado em:** _pendente_.
Ativar somente com QA e decisão de cutover registrados; canary e monitoramento conforme ADR 0104. Não confundir pedido da sequência com prova de QA.
Em 13/10 [W] mandou ligar a flag para todas as empresas (#9175), antes do QA F4; a tela nova foi confirmada por ele em produção. Ficam pendentes o QA, a retirada do Blade (`?legacy=1`) e o monitoramento, por isso a US segue sem âncora.
