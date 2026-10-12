---
date: "2026-10-09"
time: "17:11 BRT"
slug: notificacoes-f3-baseline
tldr: "Notificações F3 preparou seleção, trio e baseline canônica; CT100 passou; PR #9115 aguardou checks; flag OFF e QA completo pendentes."
---

# Continuação da sequência — Notificações F3

## Estado MCP no momento do fechamento
Snapshot de 09/10 salvo em .audit/mcp/2026-10-09-f3-fechamento.json, consultado na ordem canônica antes deste handoff: cycles-active sem cycle ativo em COPI; my-work sem tasks ativas; sessions-recent limit 3 devolveu logs de 02/10 e 05/10 indexados em 09/10; decisions-search por 2026-10-09 Notificações F3 Configurações trouxe cinco ADRs de contexto, sem prova de decisões novas do intervalo (schema não aceitou since). whats-active permaneceu sem heartbeat fresco: fresh=0, stale=0, dead=561. Não se inferiu escopo livre nem se fecharam tasks.

## Entrega preparada e evidência
A #9115 corrigiu o render sem destino deixado pela #9113, com primeira Page real, trio draft, seleção/busca/atalhos/canais e dados dinâmicos por tenant. O editor Blade continuou acessível por legacy=1. R4 preservou PageHeader e dispensou tabela somente no arquétipo de configuração desta tela, com três controles positivo/negativos. A flag permaneceu default OFF.

CT100: primeira versão teve 13 passes/46 verificações de arquitetura+dual; versão corrigida teve 11 passes/42 verificações (oito dual e três R4). Cinco testes React passaram. ui:lint teve zero violações. Auditoria cockpit-runbook estática deu 89/100, sem CRITICAL no recorte; não comprovou WCAG/pixels. Comparativo de quinze dimensões foi registrado retrospectivamente e ficou draft.

Captura canônica 37984378351 concluiu com sucesso e gerou somente a baseline nova de NotificationTemplate. Snapshot incorporado à #9115; PR auxiliar #9116 encerrada sem merge. Imagem inspecionada mostrou shell, título, rail, seleção, canais restritos do extrato e vazio da fixture. A ausência de corpos na fixture não provou editor nem prévia. Checks do último commit da #9115 ainda estavam em execução no momento deste registro; nenhum merge/deploy desse recorte foi declarado.

## Sequência e pendências preservadas
Seis PRs de entrega anteriores foram mergeadas (#9104, #9105, #9107, #9108, #9112, #9113); #9114 publicou o placar/handoff, também mergeada. Produção medida 38f910d7 teve Locais, Impressoras e Código de barras ON em 93/93 empresas, Notificações OFF em 93/93. Placar permaneceu 282/392 (71,9%), 110 restantes e 19 não medidas.

Próximos passos: checks/merge da #9115, editor React e QA de Notificações; Contas; Configurações da empresa; ativação de Usuários. Smoke das três Configurações ainda sem prova: navegador do app falhou e conta sintética 99 não existia na produção. Dois recibos ainda sem confirmação no DesignSync principal: Sistema/_saida-07 e Cutover-menu/_saida-06. Não foi criado recibo final _saida-13.

A divergência Sells/Index dark de 8,6694% continuou separada: a imagem antiga tinha cartões brancos, e a atual mostrou cartões escuros e o item Bater ponto. Nenhuma baseline existente foi regenerada para silenciar a diferença.
