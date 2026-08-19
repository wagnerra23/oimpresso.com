# Backup (`/backup`) — decisões da Onda 0 + o que a leitura do `main` mediu

> Registro datado. Onda 0 do [PLANO-ONDAS] da migração Blade → Inertia do módulo Backup.
> Decisões de [W] tomadas em **2026-08-19**. Estas três respostas eram pré-requisito das
> ondas 3-5; a Onda 1 (segurança do legado) não dependia delas e foi executada antes.

## 1. Decisões de [W] (2026-08-19)

| # | Pergunta | Decisão [W] | Onde isso morde |
|---|---|---|---|
| 1 | A permissão `backup` é de superadmin ou de admin de negócio? | **Só superadmin** | O zip é dump do banco INTEIRO (todos os tenants). Enquanto a permissão for concedível a admin de negócio, existe leitura cross-tenant por desenho — [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) Tier 0. Vira `[must]` no charter da tela + teste que trava a decisão. |
| 2 | `BACKUP_DISK` vai pra S3/Backblaze? | **Continua `local`** por enquanto | A tela mantém o alerta "Backup no mesmo servidor não é backup" como aviso principal. Nenhuma onda de infra entra no plano. |
| 3 | Retenção de 5 arquivos (~3 dias) basta? | **Não — vira 7 diários + 4 semanais** | Troca a estratégia de limpeza. Hoje `config('backup.cleanup.strategy')` aponta pra `App\Backup\Cleanup\KeepLatestBackups` (mantém 5, número fixo no código). |

### Consequência da decisão 3 que precisa de olho antes de aplicar

A janela sai de ~5 arquivos para até ~11. O `config/backup.php` **já traz** o
`default_strategy` do Spatie configurado (`keep_all_backups_for_days` etc.), então o
caminho provável é voltar pra `DefaultStrategy` com os números ajustados em vez de
manter a classe própria — mas isso é **lógica que APAGA backup**, então não entrou de
carona na Onda 1. Ponto de atenção medido: o disco de backup é `local`, cuja raiz é
`public_path('uploads')` — mais arquivos retidos = mais espaço dentro da pasta web.

## 2. O que a leitura do `main` mediu (2026-08-19, `17bc32f099`)

Correções ao que o pacote de handoff afirmava. Registradas aqui para **não** virarem
canon errado quando o charter da tela nascer na Onda 3.

| Alegação do handoff | Veredito medido | Evidência |
|---|---|---|
| `GET /backup/download/..%2F..%2F..%2F.env` **lê o `.env` hoje** | ❌ **falso** | Flysystem 3.33 — `WhitespacePathNormalizer::normalizeRelativePath` lança `PathTraversalDetected` quando o `..` escapa a raiz do disco. Esse payload vira erro, não leitura. |
| Existe travessia de caminho em `download`/`delete` | ✅ **verdadeiro, porém limitada** | Sem validação de nome, mas o alcance para na raiz do disco: `public/uploads`. Dano real = ler e, sobretudo, **APAGAR** arquivo de qualquer outro tenant (logo, imagem de produto, documento de despesa). Continua Tier 0 — só não é o `.env`. |
| `catch (Exception $e)` sem barra não captura nada | ✅ **verdadeiro** | Não há `use Exception` no arquivo; dentro de `namespace App\Http\Controllers` o nome resolve para uma classe inexistente, o catch nunca casa e a falha vira 500. |
| `Artisan::call('backup:run')` roda dentro da requisição | ✅ **verdadeiro** | `create()`. É o alvo da Onda 2. |
| Excluir o único backup era permitido | ✅ **verdadeiro** | Não havia verificação. |

### Achado que o plano não tinha

`routes/web.php:746` registra `Route::resource('backup', ...)->only('index', 'create', 'store')`,
mas o controller legado **não implementava `store()`** — a rota `POST /backup` existia e
estourava. O plano assumia um `store()` existente nas ondas 1 e 2.

### Verificado e descartado como risco

Os zips ficam em `public/uploads/UltimatePOS/`, dentro da raiz web. **Não** são baixáveis
por URL direta: `public/.htaccess` tem regra que nega `^uploads/.*\.(…|zip|…)$`. A proteção
é via `.htaccess` — some se o servidor deixar de honrá-lo.

## 3. Estado das ondas em 2026-08-19

| Onda | Estado | Observação |
|---|---|---|
| 0 | ✅ fechada | Este documento. |
| 1 | ✅ código escrito, **não commitado** | `BackUpController` (download/delete/store/`\Throwable`/último backup) + `tests/Feature/Backup/BackupSegurancaTest.php` + chave `backup_ultimo_nao_excluir` em `lang/pt` e `lang/en`. **Suíte não executada** — Pest só roda no CT 100 ([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)). |
| 2 | ⏸️ pré-requisito operacional não verificado | Precisa de `queue:work` de pé antes de `store()` virar dispatch; sem worker, o backup deixa de acontecer em silêncio. |
| 3 | ⛔ bloqueada por mecanismo | O hook `block-mwart-violation` recusa Write em `resources/js/Pages/Backup/Index.tsx` enquanto não existir `memory/requisitos/Backup/RUNBOOK-<tela-kebab>.md`. O hook **não tem override** — o caminho é fazer a F1 e criar o RUNBOOK. |
| 4-5 | ⏸️ dependem da 3 | — |

[PLANO-ONDAS]: pacote de handoff `handoff/backup-migracao/PLANO-ONDAS.md` (entregue por [W] em 2026-08-19).
