---
id: handoff-backup-tela
tela: Backup · /backup
protótipo: prototipo-ui/cowork/backup-page.jsx (+ backup-page.css) — rota `backup` do shell Cockpit V2
fonte lida no main: app/Http/Controllers/BackUpController.php · resources/views/backup/index.blade.php · config/backup.php · app/Backup/Cleanup/KeepLatestBackups.php · app/Utils/Util.php::getCronJobCommand · routes/web.php:743-746
autor: [CC]
data: 2026-08-19
status: pedido F1 → F3
---

# Handoff — Backup (Blade legado → Inertia)

> Protótipo visual pronto (F1). Este documento é o pedido pro [CL]: alvo, contrato, casos de uso com
> aceite verificável e a suíte que os defende. **Não commitei nada** — a ponte é você colar/abrir PR.

## 1. Alvo no repo

| O que | Caminho |
|---|---|
| Página Inertia | `resources/js/Pages/Backup/Index.tsx` |
| Charter (lei) | `resources/js/Pages/Backup/Index.charter.md` (conteúdo em `handoff/Backup.charter.md`) |
| Casos de uso | `resources/js/Pages/Backup/Index.casos.md` (conteúdo em `handoff/Backup.casos.md`) |
| Contrato de tela | `prototipo-ui/contrato/backup.contract.json` (ADR 0286) |
| Controller | `app/Http/Controllers/BackUpController.php` (refatorar, ver §3) |
| Rotas | `routes/web.php:743-746` — manter as URLs, trocar `view()` por `Inertia::render` |

## 2. Props do controller (contrato de dados)

```php
Inertia::render('Backup/Index', [
    'backups' => $backups,          // [{file_name, file_size, file_size_human, last_modified (ISO8601), origem}]
    'destino' => [                  // config('backup.backup.destination.disks')[0] + pasta
        'disk' => $disk,            // 'local' | 's3' | …
        'remoto' => $ehRemoto,      // bool — dispara o alerta "backup no mesmo servidor não é backup"
        'pasta' => config('backup.backup.name'),
    ],
    'retencao' => ['estrategia' => 'KeepLatestBackups', 'manter' => 5],
    'cron' => $cronJobCommand,      // Util::getCronJobCommand() — string vazia em demo
    'agendado_em' => $horaSchedule, // hora do schedule:run do backup (console.php)
    'pode' => ['gerar' => …, 'baixar' => …, 'excluir' => …], // permissão `backup` + demo
]);
```

`backups` e `destino` sob `Inertia::defer` (listar/`size()` no disco custa I/O); `cron`/`retencao`/`pode` eager.

## 3. Três defeitos reais que a leitura do `main` mostrou (corrigir junto)

1. **Path traversal em `download()` e `delete()`** — `$file = config('backup.backup.name').'/'.$file_name;` sem
   validação. `GET /backup/download/..%2F..%2F..%2F.env` sai da pasta. **Validar** `preg_match('/^[\w\-]+\.zip$/', $file_name)`
   ou casar contra a lista de arquivos do disco → 404 caso não bata. Tem teste no §4 (UC-BKP-06).
2. **`catch (Exception $e)` sem barra** dentro de `namespace App\Http\Controllers` → não captura nada; qualquer
   falha do `backup:run` estoura 500 em vez do banner. Trocar por `\Throwable`.
3. **`Artisan::call('backup:run')` na requisição** — dump + zip de ~260 MB dentro do request-lifecycle.
   Vira `dispatch(new RunBackupJob)` + estado `gerando` (polling ou broadcast). O protótipo já desenha o
   estado "Gerando backup…" com progresso e o aviso de não recarregar; com job o texto muda para
   "pode fechar a tela".

## 4. Suíte que precisa existir

**Pest — `tests/Feature/Backup/BackupInertiaTest.php`**
- `GET /backup` sem permissão `backup` → 403.
- `GET /backup` com permissão → Inertia `Backup/Index` com props `backups`, `destino`, `cron`, `retencao`.
- `backups` vem do mais novo pro mais velho e só `.zip`.
- `POST`/`GET backup/create` em `APP_ENV=demo` → volta com `status.success = 0`.
- `GET backup/create` dispara `RunBackupJob` (`Queue::fake` + `assertPushed`).
- `GET backup/download/{f}` com nome fora do padrão (`..%2F..%2F.env`, `x.php`) → 404 e **nada** é lido do disco.
- `GET backup/{f}/delete` idem + arquivo inexistente → 404.
- `GET backup/{f}/delete` do último backup existente → recusa (`status.success = 0`, "não deixe o disco sem backup").

**Pest — `tests/Feature/Backup/BackupRetencaoTest.php`**
- `KeepLatestBackups` mantém 5 e apaga o 6º (Storage::fake).
- Nunca apaga o mais novo, mesmo se todos forem do mesmo dia.

**Pest — `tests/Feature/Backup/BackupPermissaoTest.php`** (Tier 0)
- Usuário de `business_id=99` com permissão `backup` **também** acessa? O dump é do banco inteiro (todos os
  tenants) — decidir com [W]: ou a permissão vira exclusiva de superadmin, ou a tela declara isso.
  Teste trava a decisão. **Bloqueante: precisa de [W].**

**Vitest — `resources/js/Pages/Backup/__tests__/Index.test.tsx`**
- Renderiza N linhas com nome/tamanho/idade formatados em pt-BR.
- `destino.remoto = false` → alerta "Backup no mesmo servidor não é backup" visível; `true` → ausente.
- `pode.gerar = false` → botão "Gerar backup agora" desabilitado.
- `backups = []` → EmptyState `first` com o motivo (não card vazio).
- Clique em excluir abre confirmação e **não** dispara request antes do confirm.
- `cron = ''` (demo) → bloco de cron não renderiza.

**Contrato de tela — `node scripts/contrato-de-tela.mjs --contract prototipo-ui/contrato/backup.contract.json`**
Âncoras `data-contract` já estão no protótipo: `cabecalho`, `kpis`, `alerta-destino`, `lista`, `cron`.

## 5. Copy literal (não reescrever no F3)

- Título: `Backup` · sub: `N de 5 arquivos guardados · último há X h · Y GB no disco`
- Botão: `Gerar backup agora` · secundário: `Auditoria`
- KPIs: `Backups guardados` · `Último backup` · `Espaço ocupado` · `Agendamento diário`
- Alerta: `Backup no mesmo servidor não é backup`
- Cron: `Backup automático` — `O agendado só roda se esta linha existir no crontab do servidor.`
- Confirmações: `Gerar backup agora?` / `Excluir este backup?`

## 6. Pendências pra [W]

1. Permissão `backup` é de superadmin ou de admin de negócio? (§4, Tier 0 — o zip contém todos os tenants).
2. `BACKUP_DISK` vai pra S3/Backblaze? Se sim, a tela ganha coluna de destino e o alerta muda de tom.
3. Retenção 5 arquivos ≈ 1,3 GB e ~3 dias de janela. Confirmar se basta ou se vira 7 diários + 4 semanais.
