# Backup (`/backup`) — porta de entrada

Tela do UltimatePOS legado sendo migrada para Inertia + Cockpit V2, em ondas. **Não** é um
`Modules/<X>` — vive no núcleo (`app/Http/Controllers/BackUpController.php`).

## O que existe aqui

| Documento | O que responde |
|---|---|
| [DECISOES-ONDA-0.md](DECISOES-ONDA-0.md) | as 3 decisões de [W] que destravaram a migração, e o que a leitura do `main` **mediu** (incluindo uma alegação do handoff que foi refutada) |
| [RUNBOOK-index.md](RUNBOOK-index.md) | a receita da tela `Backup/Index` — fluxo, estados, rotas, multi-tenant, smoke, diagnóstico. É também a F1 que o hook MWART exige |

## Estado das ondas (2026-08-19)

| Onda | O que entrega | Estado |
|---|---|---|
| 0 | decisões de [W] + medições | ✅ mergeada |
| 1 | segurança do legado — travessia de caminho, `catch` morto, exclusão do último backup, `store()` ausente | ✅ mergeada |
| 2 | `backup:run` sai da requisição: job na fila `backups` + worker próprio no `Kernel` | PR aberto |
| 3 | render Inertia atrás de flag + trio da tela | F1 feita (o RUNBOOK); F3 pendente |
| 4 | refino de UI + contrato de tela no CI | pendente |
| 5 | decommission do Blade | pendente |

## Os três fatos que mais mordem aqui

1. **O zip é dump do banco INTEIRO** — todos os tenants num arquivo só. Por isso a permissão é de
   superadmin (decisão [W]) e a tela declara isso. Tier 0, [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md).
2. **O disco de backup é o `local`, cuja raiz é `public_path('uploads')`** — a mesma pasta de logos,
   imagens de produto e documentos de todos os tenants. É a razão de `download`/`delete` validarem
   o nome contra a listagem real do disco.
3. **Sem worker, não há backup.** A fila é `backups` e tem cron próprio no `Kernel`. A fila
   `default` não serve: quem a drena está atrás de um gate desligado por padrão.

## Onde o código vive

`app/Http/Controllers/BackUpController.php` · `app/Jobs/RunBackupJob.php` ·
`app/Console/Kernel.php` (worker) · `config/backup.php` · `app/Backup/Cleanup/KeepLatestBackups.php` ·
`resources/views/backup/index.blade.php` (legado, sai na Onda 5)

Testes: `tests/Feature/Backup/BackupSegurancaTest.php` · `tests/Feature/Backup/BackupJobTest.php`
— rodam no CT 100, nunca local ([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)).
