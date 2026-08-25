---
id: requisitos-backup-runbook-index
title: "RUNBOOK — Backup (`/backup`)"
module: Backup
tela: Backup/Index
owner: W
status: rascunho
last_validated: "2026-08-19"
preconditions:
  - "Usuário autenticado com permission `backup` (Spatie UPOS canon)"
  - "Disco de backup configurado — `config('backup.backup.destination.disks')[0]`, hoje `local` (raiz `public_path('uploads')`)"
  - "Pasta `config('backup.backup.name')` = `UltimatePOS` existente no disco"
  - "Para gerar: worker da fila `backups` de pé (cron do Kernel) e `QUEUE_CONNECTION=database`"
preconditions_short: permission backup, disco local configurado, worker da fila backups
steps:
  - "GET /backup lista os .zip da pasta, do mais novo pro mais velho"
  - "Gerar: GET backup/create (link do Blade) ou POST /backup → despacha RunBackupJob na fila `backups`"
  - "Baixar: GET backup/download/{file_name} → valida nome + listagem real do disco → stream como attachment"
  - "Excluir: GET backup/{file_name}/delete → valida nome → recusa se for o único backup do disco"
  - "Worker do Kernel drena `backups` a cada minuto (max-time 55, withoutOverlapping 30)"
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
  - 0062-separacao-runtime-hostinger-ct100
  - 0358-doutrina-de-teste-tenant-98-supersede-0101
---

# RUNBOOK — Backup (`/backup`)

> **F1 do MWART.** Este documento existe antes da tela Inertia: o hook `block-mwart-violation`
> recusa Write em `resources/js/Pages/Backup/Index.tsx` sem ele, e não tem override.
>
> ⚠️ **Estado em 2026-08-19:** o backend das Ondas 1 e 2 está feito; a **tela ainda é Blade**
> (`resources/views/backup/index.blade.php`). Por isso `status: rascunho` — as seções de UI
> descrevem o **alvo** da Onda 3, não o que está no ar hoje.

## 1. Objetivo

Deixar o dono do negócio (a) ver se existe backup recente, (b) gerar um sob demanda, (c) baixar
e (d) excluir os antigos — sem abrir terminal. O arquivo é um **dump do banco inteiro**, não do
tenant do usuário; ver §9.

## 2. Persona principal

**Wagner / superadmin.** Decisão [W] de 2026-08-19 (Onda 0): a permissão `backup` fica restrita a
superadmin, porque o zip contém dados de **todos** os businesses. Ver
[DECISOES-ONDA-0.md](DECISOES-ONDA-0.md) §1.

## 3. Pré-requisitos

Ver frontmatter `preconditions`. Dois pontos que costumam morder:

- **Sem worker, não há backup.** A fila é `backups` e quem a drena é a entrada do
  `app/Console/Kernel.php` (Onda 2). A fila `default` **não serve**: quem a drena está atrás de
  `config('queue.backlog_worker_enabled')`, desligado por padrão.
- **`QUEUE_CONNECTION=sync` anula a Onda 2** — `dispatch()` roda inline e o 504 volta.

## 4. Fluxo principal (golden path)

1. Usuário abre `/backup` → lista de zips (mais novo primeiro), com nome, tamanho e idade.
2. Clica em **Gerar backup agora** → confirmação → despacha `RunBackupJob` → banner
   "Backup na fila. Pode fechar a tela — ele roda no servidor."
3. O worker do cron pega o job em até ~1 min e roda `backup:run` fora da requisição.
4. Ao recarregar, o zip novo aparece no topo.
5. **Baixar** entrega o arquivo como attachment. **Excluir** pede confirmação.

## 5. Sub-componentes (alvo da Onda 3)

| Peça | Papel |
|---|---|
| Cabeçalho | título + subtítulo com contagem / idade do último / espaço ocupado |
| KPIs | backups guardados · último backup · espaço ocupado · agendamento diário |
| Alerta de destino | "Backup no mesmo servidor não é backup" enquanto o disco for `local` |
| Lista | nome, tamanho, idade, origem (agendado × manual), ações baixar/excluir |
| Bloco de cron | comando do `Util::getCronJobCommand()` + aviso de que o agendado só roda se a linha existir |

## 6. Estados (loading / empty / error / success)

- **loading** — lista sob `Inertia::defer` (listar + `size()` no disco custa I/O); skeleton.
- **empty** — nenhum zip: `EmptyState` dizendo **por que** está vazio (nunca rodou × disco limpo),
  não card vazio.
- **error** — falha ao gerar vira **banner**, nunca 500. O `catch (\Throwable)` da Onda 1 é o que
  garante isso; o legado usava `catch (Exception)` sem barra e nunca capturava.
- **success** — banner "Backup na fila" (a geração é assíncrona; não prometer arquivo pronto).
- **desabilitado** — em `APP_ENV=demo` as ações voltam com `status.success = 0` e motivo.

## 7. Atalhos de teclado

Herda o padrão do AppShellV2 (⌘K palette). Sem atalhos próprios — tela de baixa frequência.

## 8. Dependências de API/backend

| Rota | Método | Controller | Observação |
|---|---|---|---|
| `backup` | GET | `BackUpController@index` | hoje `view('backup.index')`; Onda 3 troca por `Inertia::render` |
| `backup/create` | GET | `@create` | link do Blade legado; sai na Onda 5 |
| `backup` | POST | `@store` | delega a `create()`; a rota já existia sem o método até a Onda 1 |
| `backup/download/{file_name}` | GET | `@download` | valida nome + listagem real antes de ler |
| `backup/{id}/delete` | GET | `@delete` | GET destrutivo do legado; vira `DELETE` na Onda 5 |

## 9. Multi-tenant + LGPD

⛔ **Tier 0.** O zip é dump do banco **inteiro** — todos os businesses num arquivo. Não existe
"backup do meu tenant". Consequências:

- a permissão é de **superadmin** (decisão [W], §2);
- a tela **declara** isso ao usuário, em vez de deixar implícito;
- o disco de backup é `local`, cuja raiz é `public_path('uploads')` — a mesma pasta de logos,
  imagens de produto e documentos de todos os tenants. É por isso que `download`/`delete` validam
  o nome contra a listagem real: sem isso, um `..` alcançava (e o delete **apagava**) arquivo de
  outro tenant.

Os zips não vazam por URL direta: `public/.htaccess` nega `^uploads/.*\.(…|zip|…)$`. É proteção
via `.htaccess` — some se o servidor deixar de honrá-lo.

## 10. Smoke check pós-deploy

```bash
curl -sv https://oimpresso.com/backup 2>&1 | grep '^< HTTP'          # 200 logado, 302 deslogado
curl -sv 'https://oimpresso.com/backup/download/..%2F..%2F.env' 2>&1 | grep '^< HTTP'   # 404
```

Além disso: gerar um backup pela tela e conferir, ~1 min depois, que o zip apareceu — é a
**consequência**, e é o que prova que o worker está vivo (o banner sozinho não prova nada).

## 11. O que NÃO fazer

- ⛔ **Redeclarar `public $queue` em job.** O trait `Queueable` declara `public $queue;` sem
  default; redeclarar é **fatal na carga da classe** e o `php -l` não pega. Setar via
  `$this->onQueue()` no constructor (padrão de `Modules/NfeBrasil/Jobs/EmitirNfceJob.php`).
- ⛔ **Mandar o backup pra fila `default`.** Ninguém a drena hoje — o job encalha e a tela mente
  dizendo "na fila".
- ⛔ **`tries > 1` no job de backup.** Backup pela metade é pior que nenhum.
- ⛔ **Concatenar `$file_name` sem validar.** Foi o defeito da Onda 1.
- ⛔ **Prometer "backup pronto" no banner.** A geração é assíncrona.

## 12. Diagnóstico/Troubleshoot

| Sintoma | Causa provável | Como confirmar |
|---|---|---|
| Banner "na fila" mas nenhum zip aparece | worker da fila `backups` não roda | contar linhas pendentes na tabela `jobs` com `queue='backups'` |
| Gerar devolve 504 | `QUEUE_CONNECTION=sync` → dispatch inline | `php artisan config:show queue.default` no ambiente |
| Download devolve 404 num zip que existe | nome fora de `^[A-Za-z0-9_\-\.]+\.zip$` | conferir o nome na listagem do disco |
| Excluir recusa | é o único backup do disco | gerar um novo antes |

## 13. Refs

- [DECISOES-ONDA-0.md](DECISOES-ONDA-0.md) — decisões [W] + o que a leitura do `main` mediu
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0
- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — MWART, caminho único
- [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md) — Hostinger ≠ CT 100
- [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md) — tenant de teste 98
- `app/Http/Controllers/BackUpController.php` · `app/Jobs/RunBackupJob.php` · `app/Console/Kernel.php`
- `tests/Feature/Backup/BackupSegurancaTest.php` · `tests/Feature/Backup/BackupJobTest.php`
