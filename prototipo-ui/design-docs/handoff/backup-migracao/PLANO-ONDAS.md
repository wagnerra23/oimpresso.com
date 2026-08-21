# Migração do módulo Backup — plano em ondas

> Tela `/backup` (UltimatePOS legado) → Inertia + Cockpit V2. Protótipo F1 pronto (`build/`).
> Cada onda é **um PR que pode ir sozinho pra produção** e tem rollback próprio. Nada de "big bang":
> as duas primeiras ondas são backend e já valem mesmo se a UI nova atrasar.
>
> Origem lida no `main`: `app/Http/Controllers/BackUpController.php` · `resources/views/backup/index.blade.php`
> · `config/backup.php` · `app/Backup/Cleanup/KeepLatestBackups.php` · `app/Utils/Util.php::getCronJobCommand`
> · `routes/web.php:743-746`.

| Onda | O que entra | Toca UI? | Risco | Bloqueio |
|---|---|---|---|---|
| 0 | Decisões de [W] | não | — | **[W]** |
| 1 | Segurança do legado (traversal · `\Throwable` · último backup) | não | baixo | nenhum |
| 2 | Geração assíncrona (job + estado) | não | médio | fila de pé |
| 3 | Render Inertia atrás de flag (dual-render) | sim | médio | onda 1+2 |
| 4 | Refino de UI (KPIs, alertas, estados) + contrato no CI | sim | baixo | onda 3 |
| 5 | Decommission do Blade | sim | baixo | onda 4 aprovada por [W2] |

---

## Onda 0 — decidir antes de escrever código (sem PR)

Três perguntas que mudam o desenho. Sem elas a onda 3 vira retrabalho:

1. **Permissão `backup` é de superadmin ou de admin de negócio?** O zip contém o banco **inteiro** —
   todos os tenants. Hoje qualquer role com a permissão baixa tudo. (Tier 0 no charter.)
2. **`BACKUP_DISK` vai para remoto (S3/Backblaze)?** Se sim, a tela ganha coluna de destino e o alerta
   "backup no mesmo servidor não é backup" muda de tom.
3. **Retenção 5 arquivos** (≈1,3 GB, ~3 dias) basta, ou vira 7 diários + 4 semanais?

**Saída:** resposta registrada no charter (`charter/Index.charter.md`, seção Tier 0) — nada mais.

---

## Onda 1 — fechar os buracos do legado (backend, sem UI)

Vale por si só: hoje qualquer usuário com a permissão consegue ler `.env` pela rota de download.

**Entra**
- `php/app/Http/Controllers/BackUpController.php` — só os métodos `download`, `delete` e o
  `resolverArquivo()` privado. **Não** troque o `index` ainda (segue `view('backup.index')`).
- `catch (\Throwable)` no `create`/`store` (o `catch (Exception)` do legado, sem barra, dentro do
  namespace, não captura nada → 500 em vez de banner).
- Recusa de exclusão quando é o único backup no disco.

**Testes** — `tests/Feature/Backup/BackupInertiaTest.php` (blocos "traversal", "último backup", "permissão")
+ `tests/Feature/Backup/BackupRetencaoTest.php`.

**Aceite:** `GET /backup/download/..%2F..%2F..%2F.env` → 404 e nada é streamado · excluir o único
arquivo → recusa com motivo · as 4 rotas → 403 sem a permissão.

**Rollback:** reverter o arquivo do controller. A view Blade não foi tocada.

---

## Onda 2 — geração fora da requisição (backend, sem UI)

**Entra**
- `php/app/Jobs/RunBackupJob.php` — `Artisan::call('backup:run')` dentro de um job (timeout 1800, `tries=1`).
- `store()` despacha o job e volta com `status.success = 1` ("backup enfileirado").
- `php/routes-backup.php` — `Route::post('backup', …)` além do `create` legado (o Blade usa link GET;
  mantenha os dois até a onda 5).

**Testes:** `Queue::fake()` + `assertPushed(RunBackupJob::class)` · job em `APP_ENV=demo` não é despachado.

**Aceite:** clicar em gerar responde em <300 ms e o zip aparece no disco depois, sem 504.

**Rollback:** voltar `store()` para `Artisan::call` síncrono. O job pode ficar no repo sem uso.

**Pré-requisito operacional:** `queue:work` de pé no CT100 (`supervisor`), senão o backup nunca roda.

---

## Onda 3 — render Inertia atrás de flag (dual-render)

**Entra**
- `index()` do controller novo (props `backups`/`destino`/`retencao`/`cron`/`pode`, `Inertia::defer` na lista)
  — **atrás da flag** `mwart.backup.enabled`, no mesmo padrão do Cliente/Import: flag desligada → Blade legado.
- `js/Pages/Backup/Index.tsx` — tradução do protótipo (`build/backup-page.jsx`) para os primitivos do repo.
- `charter/Index.charter.md` + `charter/Index.casos.md` → `resources/js/Pages/Backup/`.
- `js/Pages/Backup/__tests__/Index.test.tsx` (Vitest).

**Aceite:** `GET /backup` com a flag ligada renderiza Inertia `Backup/Index` com as props · flag desligada
continua no Blade · a copy literal do charter §"Copy literal" bate caractere a caractere.

**Rollback:** desligar a flag (`mwart.backup.enabled=false`) — zero deploy.

---

## Onda 4 — refino de UI + contrato no CI

**Entra**
- Estados que o protótipo já desenha, agora com dado real: alerta de **agendado que não rodou nas
  últimas 27 h**, alerta de **falha da última geração**, `EmptyState` de disco vazio, ações
  desabilitadas com motivo (demo / permissão de leitura).
- KPIs: hero "Último backup" com sparkline, barras de retenção (n/5) e de uso do disco (limite 5 GB).
- `contrato/backup.contract.json` → `prototipo-ui/contrato/` + âncoras `data-contract` no `.tsx`
  (`cabecalho` · `kpis` · `alerta-destino` · `lista` · `cron`), rodando em CI (ADR 0286).

**Aceite:** `node scripts/contrato-de-tela.mjs --contract prototipo-ui/contrato/backup.contract.json` verde ·
screenshot aprovado por [W2].

**Rollback:** o contrato é advisory até [W] promover; a UI volta pela flag da onda 3.

---

## Onda 5 — decommission do Blade

**Entra**
- Remover `resources/views/backup/index.blade.php` e o branch de dual-render.
- Flag `mwart.backup` sai do `config/mwart.php`.
- Rota `backup/{id}/delete` (GET destrutivo do legado) vira `DELETE backup/{file_name}`.
- `readiness`/`FRESCOR` da tela para 🔵 "puxe o vivo".

**Aceite:** nenhuma referência a `backup.index` no repo (`rg "backup.index"` vazio) · suíte verde ·
prontidão da máquina (`scripts/qa/prototipo-readiness.mjs`) reconhece o trio.

---

## Ordem de leitura para quem for executar

1. `HANDOFF-backup-tela.md` — alvo, props, os três defeitos e a suíte completa.
2. `charter/Index.charter.md` — a lei da tela (goal, Tier 0, estados, anti-padrões).
3. `charter/Index.casos.md` — UC-BKP-01..10 com aceite Dado/Quando/Então.
4. `aplicar.sh` — copia os arquivos de cada onda para os caminhos do repo (não commita).
