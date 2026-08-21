# Pacote de migração — módulo Backup (`/backup`)

Tudo o que o [CL] precisa para migrar a tela do Blade legado (UltimatePOS) para Inertia + Cockpit V2,
**em 5 ondas** — cada uma um PR independente com rollback próprio.

## Comece aqui

1. **`PLANO-ONDAS.md`** — as ondas, o que entra em cada uma, aceite e rollback. É o roteiro.
2. **`HANDOFF-backup-tela.md`** — alvo no repo, props do controller, os três defeitos reais do legado e a suíte completa.
3. **`charter/Index.charter.md`** — a lei da tela (goal, Tier 0, estados, anti-padrões, copy literal).
4. **`charter/Index.casos.md`** — UC-BKP-01..10, aceite Dado/Quando/Então, rastreabilidade.

## Conteúdo

```
PLANO-ONDAS.md                  roteiro das 5 ondas
HANDOFF-backup-tela.md          pedido técnico (props, defeitos, suíte)
aplicar.sh                      copia os arquivos por onda: ./aplicar.sh <repo> <onda>
charter/Index.charter.md         → resources/js/Pages/Backup/Index.charter.md
charter/Index.casos.md           → resources/js/Pages/Backup/Index.casos.md
contrato/backup.contract.json    → prototipo-ui/contrato/backup.contract.json
php/app/Http/Controllers/BackUpController.php   controller refatorado (ondas 1 e 3)
php/app/Jobs/RunBackupJob.php                   onda 2
php/routes-backup.php                           trecho de routes/web.php
tests/Feature/Backup/BackupInertiaTest.php      Pest — traversal, permissão, job, props
tests/Feature/Backup/BackupRetencaoTest.php     Pest — KeepLatestBackups
js/Pages/Backup/Index.tsx                       página Inertia (onda 3)
js/Pages/Backup/__tests__/Index.test.tsx        Vitest — estados e âncoras do contrato
build/backup-page.jsx · build/backup-page.css   protótipo aprovado (referência visual)
```

## Uso

```bash
chmod +x aplicar.sh
./aplicar.sh ~/dev/oimpresso.com 1     # onda 1: segurança
docker exec oimpresso-staging php artisan test --filter=Backup
```

O script **não** commita, não faz push e não abre PR — põe os arquivos no lugar e imprime o que rodar.
A onda 1 chega como `BackUpController.php.onda1-proposto` de propósito: nela só os métodos
`download`/`delete`/`store` mudam, o `index()` segue no Blade até a onda 3.

## O que este pacote conserta no legado

1. **Path traversal** em `download()`/`delete()` — `GET /backup/download/..%2F..%2F..%2F.env` lê o `.env` hoje (UC-BKP-06).
2. **`catch (Exception $e)` sem barra** dentro do namespace → falha de backup vira 500 em vez de banner (UC-BKP-09).
3. **`Artisan::call('backup:run')` na requisição** → 504 em base grande; vira job (UC-BKP-03).
4. **Excluir o único backup** ficava permitido, sem lixeira (UC-BKP-05).

## Pendências de [W] (onda 0, antes de codar)

- Permissão `backup`: superadmin ou admin de negócio? O zip contém **todos** os tenants.
- `BACKUP_DISK` vai para S3/Backblaze? Muda o alerta principal da tela.
- Retenção de 5 arquivos (~3 dias) basta, ou 7 diários + 4 semanais?
