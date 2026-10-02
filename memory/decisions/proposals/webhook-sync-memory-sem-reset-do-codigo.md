---
title: "Webhook sync-memory deixa de publicar código em produção — só a pasta memory/ acompanha o main"
status: proposta
date: "2026-10-02"
owners: [W]
proposed_by: Claude Code (a pedido de [W])
parent_module: infra
related_adrs: [216, 269, 62]
related_specs:
  - Modules/Forja/Http/Controllers/Mcp/SyncMemoryWebhookController.php (sincronizarComOrigin)
  - Modules/Jana/Services/Mcp/IndexarMemoryGitParaDb.php (lerGitSha)
  - Modules/Governance/Services/Checkers/DeployDriftChecker.php
  - .github/workflows/deploy.yml (paths-ignore memory/**)
related_charters: []
---

# Webhook sync-memory deixa de publicar código em produção

> **Status: `proposta`.** Não muda código. É a mesa de decisão para [W]. O código vem num PR
> seguinte, se a recomendação da §4 for aceita.

## 1 · O que acontece hoje (medido em 2026-10-02)

Existem **dois** caminhos que trocam o código de `oimpresso.com`, e só um deles é o deploy.

| Caminho | Gatilho | O que faz no servidor | Passos depois do reset |
|---|---|---|---|
| `deploy.yml` ([ADR 0269](../0269-deploy-automatico-build-no-runner.md)) | push no `main` que toca código | `git reset --hard <sha do run>` | build dos bundles no runner, `composer dump-autoload`, cache clear, OPcache reset, smoke, failsafe |
| Webhook `POST /api/mcp/sync-memory` → `SyncMemoryWebhookController` | **todo** push no `main` | `git fetch` + `git reset --hard origin/main` | **nenhum** — só reindexa `memory/` |

O webhook é o hook `614879953` do repositório (`push` + `pull_request`, ativo), e roda dentro do
próprio PHP de produção (`base_path()`).

**Prova de que é o webhook que move o código, não o deploy** — entregas do hook no GitHub × reflog
do git no servidor (lido por SSH, só leitura):

| Webhook entregue | Reset no servidor |
|---|---|
| 12:37:06.5 | 12:37:06 → `3eaa054556` (`reset: moving to origin/main`) |
| 12:45:11.5 | 12:45:10 |
| 12:46:32.6 | 12:46:33 |

O `deploy.yml` deixa outra assinatura no reflog (`reset: moving to <sha completo>`), e as duas
aparecem intercaladas: o deploy das 12:31:38 pôs `0c6a7c8d1c`, e o webhook das 12:37:06 passou por
cima com `3eaa054556`.

Consequência observada no mesmo dia: o conserto do #8500 estava no ar às 12:43, quando o deploy
que o continha só fez o reset às 13:02. Na prática, todo PHP mergeado vai para produção ~3 s
depois do merge, **sem** os passos que o `deploy.yml` faz depois do reset.

## 2 · Por que isso é risco, e não conveniência

- **Já derrubou produção.** A [ADR 0216](../0216-deploy-webhook-rodar-composer-dump-autoload.md)
  registra 2h31 de 500 em toda rota web em 2026-05-28: classe PHP nova entrou pelo webhook sem
  `composer dump-autoload`. A decisão foi rodar dump-autoload depois do pull — o controller atual
  **não** roda.
- **O `deploy.yml` diz, em comentário, que este reset é errado** — "`reset --hard origin/main`
  seria ERRADO aqui… a receita exata dos 500 de boot de 2026-06-18 e 2026-06-23" — e por isso
  reseta para o SHA do run. O webhook faz exatamente o que o comentário proíbe, a cada push.
- **PHP e JS saem descasados.** Os bundles só mudam no deploy. Um PR que mexe em `.tsx` e no
  controller junto entra com o PHP novo na hora e o JS antigo até o deploy terminar — em
  2026-10-02 o deploy fez o reset 1h40 depois do merge do #8483 (10:51 → 12:31) e 1h14 depois do
  #8500 (11:48 → 13:02).
- **O guard do webhook é parcial.** Ele pula o reset só quando o push toca `composer.*`,
  `package*.json`, `vite.config`, migrations ou `public/build/`. Arquivo PHP novo (o caso da
  0216) não está na lista.
- **Cega o detector de deploy atrasado.** O `DeployDriftChecker` compara o HEAD do servidor com o
  SHA de `main` que o próprio webhook grava. Como o webhook também move o HEAD para `main`, os dois
  sempre batem: o detector não tem como acusar atraso.
- Às 12:37:14 uma entrega do webhook respondeu **500** em 10 s. Causa não investigada.

## 3 · Por que não dá para só apagar o reset

O `deploy.yml` ignora pushes em `memory/**`, `**.md` e `prototipo-ui/**`. Hoje, **o único caminho
que leva doc de memória ao servidor é este reset.** Sem ele, `mcp_memory_documents` (a busca de
memória do MCP) para no último deploy de código, e push só de doc nunca chega.

Também o `IndexarMemoryGitParaDb::lerGitSha()` lê `git log -- <arquivo>` a partir do HEAD; com o
HEAD parado no deploy, um doc novo sairia sem `git_sha`.

## 4 · Opções

| | O que muda | Memória fica fresca? | Código só pelo deploy? | Custo |
|---|---|---|---|---|
| **A (recomendada)** | Trocar `git reset --hard origin/main` por `git checkout origin/main -- memory/`; `lerGitSha` passa a usar `git log origin/main -- <arquivo>` | sim, a cada push | sim | ~2 linhas + teste |
| B | Indexar a partir de um `git archive origin/main memory` extraído em pasta temporária, sem tocar o working tree | sim | sim | `memory/` hoje são 4.699 arquivos / ~51 MB de tar por push, num host compartilhado |
| C | Apagar o reset e disparar a reindexação no fim do `deploy.yml` | só quando houver deploy de código | sim | push só de doc nunca reindexa — teria de tirar `memory/**` do `paths-ignore` e pagar um deploy inteiro por doc |
| D | Manter como está e cumprir a 0216 (rodar dump-autoload + optimize no webhook) | sim | **não** — segue havendo dois caminhos de deploy | repete no webhook o que o `deploy.yml` já faz, sem build de bundle nem failsafe |

**Por que A:** é a única que mantém as duas propriedades — memória atualizada a cada push e código
só pelo caminho que tem build, autoload, OPcache e failsafe — com mudança mínima. O working tree de
produção fica com `memory/` à frente do HEAD entre um deploy e outro; o próximo `git reset --hard
<sha>` do `deploy.yml` sobrescreve isso, e o webhook seguinte volta a atualizar. Quem lê `memory/`
em runtime — ao menos 30 arquivos fora de teste, entre comandos e serviços de Governance, Jana e
Forja (`git grep` por `base_path('memory` e `'memory/` em `app/`, `Modules/`, `routes/`) — continua
vendo a mesma versão de hoje, a do `main`.

## 5 · O que muda para quem usa

- **Fix de código passa a levar o tempo do deploy**, não ~3 s. Em 2026-10-02 isso foi 1h14 e 1h40
  nos dois casos medidos, por causa da fila e dos cancelamentos (`concurrency: deploy-production`). É o
  comportamento que a ADR 0269 já descreve; hoje ele é mascarado pelo webhook.
- O `DeployDriftChecker` volta a enxergar deploy atrasado.
- Hotfix urgente continua tendo o caminho manual (`workflow_dispatch` do `deploy.yml`).

## 6 · Decisões que são do [W]

1. Aceitar a opção A (ou escolher outra da §4).
2. Aceitar que fix de código passe a esperar o deploy (§5).
3. Se aceita: o PR de código também registra uma ADR curta que **supersede a parte de execução da
   0216** (o webhook deixa de ser caminho de deploy), já que a 0216 decidiu reforçar o webhook e
   esta proposta o tira desse papel.

## 7 · Como verificar depois do PR de código

- Reflog do servidor: nenhuma entrada `reset: moving to origin/main` depois do deploy do PR — só
  `moving to <sha>` do `deploy.yml`.
- Push só de doc em `memory/`: o doc aparece em `mcp_memory_documents` com `git_sha` preenchido, e
  `git status` no servidor mostra só `memory/` diferente do HEAD.
- `DeployDriftChecker` acusa atraso enquanto um deploy está na fila.
