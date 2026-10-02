---
date: "2026-10-02"
time: "17:26 BRT"
slug: conta-demo-gestor-7-areas-e-deploy-duplo
tldr: "Continuação do handoff de 11:43. Conta demo fechada para as lojas: smoke pela API completo (me, espelho, marcar), gestor.demo com as 7 áreas (cadastro de ponto DEMO-0002) e chaves do Passport em 660. No caminho, produção recuou 5 vezes por haver 2 caminhos de deploy — o webhook sync-memory dava reset no servidor; o #8547 (outra sessão) o restringiu a memory/, confirmado no reflog."
decided_by: [W]
prs: [8478, 8499, 8543, 8547]
next_steps:
  - "[W]: login real com senha no app como revisor.ponto e como gestor.demo (senhas no Vaultwarden, itens ponto-demo-revisor / ponto-demo-gestor) — único teste que o agente não faz."
  - "Antes de enviar às lojas: `php artisan ponto:demo-dados` (refaz 'hoje') e `php artisan ponto:demo-smoke --sem-marcar`."
  - "Notas do revisor (memory/requisitos/Ponto/REVISAO-LOJAS-NOTAS.md) ainda descrevem a barra Início · Ponto · Conta; refazer uma vez só com os nomes finais das 7 áreas (D13), vindos da sessão APP CAPACITOR."
  - "Dados de Tarefas/Pedidos/Produção no business 235: quando as telas/APIs existirem e a coordenação avisar."
---

# Conta demo: gestor com as 7 áreas, e o deploy duplo — 2026-10-02

Continuação de [`2026-10-02-1143-conta-demo-revisores-lojas.md`](2026-10-02-1143-conta-demo-revisores-lojas.md).

## Estado MCP no momento do fechamento
- Servidor MCP `oimpresso` **indisponível nesta sessão** (ToolSearch sem `cycles-active`/`my-work`); estado tirado do git, do GitHub e de leitura em produção.
- Coordenação do app: sessão "Coordenar app das lojas (oimpresso-app)"; decisões em `docs/lojas-app/DECISOES.md`.
- [W] confirmou nesta sessão: telas próprias + API Passport (D5), duas contas (D7), merge automático dos PRs da conta demo.

## Conta demo (produção, business 235)
| Item | Estado medido |
|---|---|
| `revisor.ponto` (1805) | `/api/app/inicio` 200 · colaborador · abre no Ponto · áreas `[ponto, mais]` · Pedidos/Produção/Pessoas 403 |
| `gestor.demo` (1806) | 200 · erp · abre no Início · **7 áreas** · `estoque_baixo` numérico · `/ponto/api/me` DEMO-0002 |
| Smoke (`ponto:demo-smoke`) | API Passport em processo, sem senha: hoje, saldo, me, espelho (31 linhas), mês futuro 422, marcar 201 |
| Marcações no 235 | 2, ambas de smoke (NSR 1 e 2) |
| Client OAuth do app | 110, password grant, público (`/oauth/token` responde 400 a usuário inexistente) |
| Chaves do Passport | 660 (`-rw-rw----`); token inválido → 401, usuário inexistente → 400 (PHP web lê as duas) |

O 500 do gestor no `/api/app/inicio` (`count()` sobre um Builder em `InicioController`) foi achado aqui e corrigido pela coordenação no #8534. A falta da área Ponto era dado: o gestor não tinha cadastro de ponto, e o #8543 cria o DEMO-0002, sem marcação.

## Achado: dois caminhos de deploy
O `git reflog` de produção mostrou dois escritores do HEAD:
- **Actions** (`deploy.yml`): `checkout main` + `reset` para o SHA do próprio run;
- **webhook `POST /api/mcp/sync-memory`**: `reset: moving to origin/main` a cada push, sem migrate, autoload nem cache (não era o hPanel; é o único webhook do repositório).

Quando um deploy lento do Actions terminava depois de o webhook já ter publicado commits mais novos, ele pinava o próprio SHA e **recuava produção**. Houve 5 recuos medidos (14:26, 14:52, 15:49, 16:45 e 17:08 UTC); o pior tirou 5 PRs do ar por cerca de 2 minutos, incluindo a máscara de CPF da API de Pessoas (#8537). O #8547, de outra sessão (ADR 0425), restringiu o webhook a `git restore` só de `memory/`. Confirmado em produção: depois de 17:08 UTC, pushes novos em main não geram mais `reset: moving to origin/main`, e `memory/` bate com o HEAD.

## Erros meus que valem registro
- Repassei às sessões que "Expo veio da sessão Android" sem ver a fonte; o [W] tinha de fato respondido Expo antes de fixar Capacitor. Corrigido nas sessões.
- Escrevi uma correção paralela do webhook antes de ver o #8547, que já existia e era melhor: a minha deixaria `memory/` desatualizada nos pushes só de docs, porque o `deploy.yml` ignora `memory/**`. Não abri PR e apaguei o branch (LC-19).
