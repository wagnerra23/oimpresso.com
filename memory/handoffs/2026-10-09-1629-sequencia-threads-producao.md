---
date: "2026-10-09"
time: "16:29 BRT"
slug: sequencia-threads-producao
tldr: "Seis PRs mergeadas; três Configurações ON em 93 empresas; Notificações OFF; smoke visual e DesignSync pendentes."
---

# Sequência das threads — estado publicado

## Estado MCP no momento do fechamento
- cycles-active: nenhum cycle ativo em COPI.
- my-work: nenhuma task ativa para o usuário autenticado.
- sessions-recent limit 3: dois logs de 02/10 e um de 05/10 foram devolvidos, indexados em 09/10; não provaram frescor das sessões atuais.
- decisions-search: schema não ofereceu since; busca por 2026-10-09 Configurações Notificações devolveu ADRs 0040, 0421 e TECH-0001/CRM. Não foram tratadas como decisões novas do intervalo.
- whats-active: ingest sem heartbeat fresco (fresh=0, stale=0, dead=561). Não se inferiu escopo livre. Nenhuma task foi fechada.

## Entregas e prova
PRs #9104, #9105, #9107, #9108, #9112 e #9113 mergeadas. Deploys 37979175888 e 37979437976 concluídos com sucesso. Produção 38f910d7b22d foi medida em 09/10 19:28:16 UTC.

| Flag | ON antes (18:27:15 UTC) | ON depois (19:28:16 UTC) |
|---|---:|---:|
| useV2ConfiguracoesLocais | 0/93 | 93/93 |
| useV2ConfiguracoesImpressoras | 0/93 | 93/93 |
| useV2ConfiguracoesCodigoBarras | 0/93 | 93/93 |
| useV2NotificationTemplates | não medida naquele snapshot | 0/93 |

D-CFG-LIGAR de 07/10 autorizou as três para todas. OFF conhecido no GrowthBook continuou prevalecendo; polegadas foram preservadas. CT 100: cutover nove passes/42 assertions e dual final sete passes/31 assertions. O baseline manteve UC-NOT-18/25 como todo.

## O que ainda faltou
Smoke visual não foi concluído. O navegador do app falhou, e a produção não tinha tenant/usuário sintético 99 para o caminho nominal do smoke automático. Foi pedida conferência colaborativa de título, dados/estado vazio, shell e console. Não foi criado _saida-13 nem declarada a tela funcionando por HTTP/teste de backend.

Placar do main: 282/392, 71,9%, 110 restantes; 19 não medidas. Próximos recortes: Notificações F3/F4/F5, Contas, Configurações da empresa e Usuários/09. No DesignSync w ficaram dois recibos sem confirmação: Sistema/_saida-07 e Cutover-menu/_saida-06. Os fatos de ativação não fecharam automaticamente essas pendências.
