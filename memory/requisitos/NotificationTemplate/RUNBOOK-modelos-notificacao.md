---
slug: notificationtemplate-runbook-modelos-notificacao
title: "Modelos de notificação — migração da Blade"
type: runbook
module: NotificationTemplate
owner: W
status: rascunho
last_validated: "2026-10-09"
---

# RUNBOOK — Modelos de notificação

F1 da thread Sistema/06. Fontes lidas no main e5ec05dda251: NotificationTemplateController, NotificationTemplate, Blade e NotificationTemplateTest; desenho e contrato no pacote cowork-inbox/notificacoes aprovado em 19/08. A existência dos testes não equivale a baseline aprovado nesta sessão. Esta entrega planejou a migração; não ligou flag nem enviou mensagens.

## 1. Objetivo
Migrar /notification-templates para NotificationTemplate/Index, preservando os três grupos, modelos de módulos, edição em lote e isolamento por empresa. A Page usará rail e painel do protótipo notificacoes-page; a descrição genérica de lista/drawer da thread não substitui o contrato específico aprovado da tela.

## 2. Pré-condições
Permissão send_notification no GET e POST. Tenant fictício 98 e 99 para testes. A fonte visual foi prototipo-ui/cowork/Wagner/notificacoes-page.jsx e .css. Charter, casos e contrato ficaram no pacote cowork-inbox/notificacoes; entram nos destinos vivos junto da Page, evitando charter sem .tsx.

## 3. Passo-a-passo
1. Publicar F1: este RUNBOOK, SPEC e modelos-notificacao-parity.md.
2. Reexecutar NotificationTemplateTest no CT 100; registrar testes passando e os dois todo existentes separadamente. Examinar emissão de JUnit da lane, pois o manifesto por UC consultado não continha UC-NOT.
3. Acrescentar flag default OFF, ação dual e testes da resposta Blade/Inertia. Reusar FeatureFlagService e comando existente; não criar segundo mecanismo de flags.
4. Migrar a lista de modelos e seleção; depois campos por canal, tags e edição em lote. Manter rotas e payload template_data.
5. Importar e reconciliar charter/casos/contrato do pacote com os vereditos dos testes, sem promover UC sem prova.
6. Executar QA visual, permissões, tenant e round-trip; separar smoke de edição de teste de envio. O smoke não deve enviar a contatos reais.
7. Fazer cutover somente após gates e decisão de ativação registrada; preservar fallback até cumprir a fase correspondente.

## 4. Tokens CSS
Herdar tokens do DS e AppShellV2, conforme a Constituição UI v2 e o protótipo aprovado. Nenhum token novo foi proposto. Inventário de componentes e correspondência de estilos serão conferidos em F3; o pacote anotou limitações de ref/onFocus para inserir tags.

## 5. Estados visuais
Preservar estados do charter de origem: carregando, sem permissão, modelo vazio, alterado não salvo, tag desconhecida e canal indisponível. Tag desconhecida gera aviso sem impedir salvar. Prévia deve tratar HTML como não confiável; reusar o caminho de sanitização existente, sem criar execução de script no navegador.

## 6. Responsividade
Conferir a forma aprovada no protótipo em desktop e viewport móvel antes de F3. Este planejamento não mediu pixels nem declarou conformidade responsiva. A seleção do modelo e o editor devem continuar acessíveis por teclado em ambos.

## 7. Atalhos
Nenhum atalho novo foi decidido. Preservar navegação por teclado dos componentes do DS; inserir tags respeitando seleção e cursor do campo, conforme o contrato da tela.

## 8. Component contract
Controller entrega general_notifications, customer_notifications e supplier_notifications, com name/extra_tags e subject, email_body, sms_body, whatsapp_text, auto_send, auto_send_sms, auto_send_wa_notif, cc e bcc. Salvar envia template_data por modelo em um POST. O backend deriva a whitelist da mesma composição do índice. Não codificar lista fixa no React.

## 9. DoD checklist
- [x] F1: plano e mapa de paridade escritos a partir do contrato e legado.
- [ ] F2: baseline executado no CT 100, com recibo e todo explicitados.
- [ ] F2: dual + flag OFF + testes de permissão, payload e tenant.
- [ ] F3: Page, charter e casos vinculados, com UCs testados.
- [ ] F4: visual, acessibilidade, round-trip e QA de isolamento.
- [ ] F5: decisão de ativação, canary e monitoramento conforme ADR 0104.

## 10. Pegadinhas
- new_booking depende de business.enabled_modules contendo booking; não existe módulo nWidart Booking.
- Checkbox ausente vira zero. cc/bcc são um endereço por campo, não listas.
- send_ledger esconde SMS/WhatsApp na Blade; endurecer o backend dessa regra ainda demanda decisão do pacote (D5).
- A rota de teste já existia no controller; os testes verificam destinatário logado e chave permitida. A Page não deve criar outra rota.
- UC-NOT-18 e UC-NOT-25 ficaram como todo no baseline. Não afirmar que canais vazios e lembrete desligado foram provados.
- Contas é a segunda tela da Sistema/06, com pré-flight e mapa de valor próprios; esta entrega não a migrou.

## 11. ADR de origem
[ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md), [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) e [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md). Fonte de domínio: pacote aprovado em prototipo-ui/cowork/Wagner/cowork-inbox/notificacoes.
