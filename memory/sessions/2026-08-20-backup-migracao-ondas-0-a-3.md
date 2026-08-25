---
date: "2026-08-20"
topic: "Migração do módulo Backup (Blade → Inertia) — Ondas 0 a 3-F1 + conserto do transporte de design"
authors: [W, C]
outcomes:
  - "5 PRs mergeados: Ondas 0, 1, 2 e a F1 da 3, mais o conserto do protocolo de design"
  - "Travessia de caminho em /backup fechada — o dano real era apagar arquivo de OUTRO tenant, não ler o .env"
  - "backup:run saiu da requisição COM worker próprio (sem ele o job encalharia numa fila que ninguém drena)"
  - "Teto de transporte do payload de design diagnosticado, com guarda e bite-test"
prs: [5979, 5980, 5999, 6000, 6003]
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
  - 0062-separacao-runtime-hostinger-ct100
---

# Sessão 2026-08-20 — Backup: Ondas 0→3-F1 e o teto do transporte de design

**TL;DR** — [W] entregou um pacote de handoff com plano em 5 ondas para migrar a tela `/backup`.
Quatro entregas foram ao `main`. A F3 (a tela) segue bloqueada por transporte, não por decisão.
Pelo caminho, três alegações do pacote não sobreviveram à medição, e o próprio protocolo de
design revelou uma rota que não fecha.

## O que entrou

| Onda | PR | Entrega |
|---|---|---|
| 0 | #5979 | decisões de [W] (permissão superadmin · disco local · retenção 7+4) + o que a leitura do `main` mediu |
| 1 | #5980 | validação de nome em `download`/`delete`, `catch (\Throwable)`, recusa de excluir o último backup, `store()` que não existia |
| 2 | #5999 | `RunBackupJob` na fila dedicada `backups` **+ o worker que a drena** no `Kernel` |
| 3 (F1) | #6000 | `RUNBOOK-index.md` (destrava o hook MWART) + `BRIEFING.md` |
| — | #6003 | guarda de truncagem/incompletude no `aplicar-payload.mjs` + painel honesto |

## O que a medição desmentiu

1. **"`download/..%2F..%2F..%2F.env` lê o `.env` hoje"** — falso. Flysystem 3.33 lança
   `PathTraversalDetected` quando o `..` escapa a raiz do disco. A travessia existe, mas o alcance
   para em `public/uploads` — e o dano alcançável é **apagar** arquivo de outro tenant pelo
   `delete`. Continua Tier 0; só não é o `.env`. A correção proposta estava certa; a justificativa
   é que não estava.
2. **`store()` existia** — não existia. `Route::resource(...)->only(…, 'store')` registrava
   `POST /backup` sem método no controller: a rota estourava. O plano assumia o método nas ondas 1 e 2.
3. **"conferir `queue:work` no CT100"** — insuficiente. Quem drena `default` está atrás de
   `config('queue.backlog_worker_enabled')` (default `false`), e **nada** drenava `backups`. O job
   ficaria parado na tabela `jobs` e a tela diria "backup na fila" para sempre. Por isso a Onda 2
   entrega o worker junto com o job.

## Dois defeitos que o pacote teria introduzido

- **`public string $queue = 'backups'`** — o trait `Queueable` declara `public $queue;` sem default;
  redeclarar é **fatal na carga da classe**, e o `php -l` não pega. Provado com sonda isolada antes
  de corrigir. O repo já tinha a receita em `Modules/NfeBrasil/Jobs/EmitirNfceJob.php` L57-61.
  A mesma armadilha existe, dormente, em `Modules/PaymentGateway/Jobs/ProcessarWebhookPixInterJob.php:55`.
- **fila `default`** — ver item 3 acima.

## O teto do transporte de design (por que a F3 não saiu)

O protótipo **existe** no Cowork (`backup-page.jsx` + `.css`), e ele depende de `acessos-ds.jsx`,
que provê `window.AcessosDS` — sem esse arquivo a tela nem carrega (desestruturação no topo do
módulo). Nenhum dos três está no espelho.

A rota canônica de download é o payload servido (`sync/payload.json`), que tem **~3,5 MB**. O único
transporte disponível ao agente (`DesignSync.get_file`) corta em **256 KiB** e devolve
`"truncated": true` — corte medido em 256.567 chars. O painel anunciava esse caminho como
"sem teto get_file": verdade para o conteúdo por arquivo, falso para o payload.

O #6003 não remove o teto (isso é do lado do design, que precisa emitir em partes) — mas troca um
`SyntaxError: Unterminated string` por um diagnóstico que nomeia a causa e ensina o remédio.

## Erros meus, registrados

- **LC-15** — o painel anunciava rota que não fecha. Consertado e virou lápide.
- **LC-08** — tentei consertar `front_door_coverage` com um `README.md` depois de ler o `TRUTH_RE`
  (que conta docs *concorrentes*) em vez do predicado da porta (`existsSync(BRIEFING.md)`). A métrica
  não se mexeu, e **isso** era o sinal de que eu tinha lido errado.
- **Deadlock de concurrency que eu mesmo criei** — disparei `gh run rerun` e o gatilho de um label
  quase juntos; os dois caíram no grupo `visual-regression-<ref>` e se cancelaram. Custou quatro
  tentativas até eu ler as linhas 33-46 do próprio workflow, que descrevem o vetor e a saída.

## O que NÃO era meu

Três vermelhos travaram horas e vieram do repo: `casos.md` stale do Superadmin (corrigido em #6006),
baselines do Financeiro (rebake em #6012) e um advisory de acoplamento Jana→Whatsapp. O sinal que
separou um do outro foi sempre o mesmo: o diff do PR não tocava a área acusada.

## Próximo passo

Um `sync/payload.backup.json` com os 3 arquivos cabe folgado no transporte e destrava a F3 no mesmo
dia. O caminho completo é o gerador emitir `payload.part*.json` de até 256 KiB — o applier já junta
lotes (`payloads.flatMap`), suporte que existia e não estava documentado.
