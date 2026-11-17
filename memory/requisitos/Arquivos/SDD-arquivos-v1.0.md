---
id: requisitos-arquivos-sdd-backbone-v1-0
slug: arquivos-sdd
title: "SDD — Arquivos: anexar, deduplicar, classificar, reter, avisar e servir (backbone de armazenamento)"
type: sdd
module: Arquivos
status: ativo
owner: W
version: "1.0.0"
last_updated: "2026-11-16"
related_docs:
  - SPEC.md
  - SCOPE.md
  - BRIEFING.md
  - SUPERFICIE.md
  - RUNBOOK-index.md
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0123-modules-arquivos-backbone
  - 0126-vault-chunked-encryption-sprint-2
  - 0421-arquivos-aviso-ao-titular-registro-e-janela
  - 0422-arquivos-aviso-titular-canal-email-whatsapp
  - 0453-escopo-de-empresa-fecha-sem-contexto
---

# SDD — Arquivos: backbone de armazenamento

> Primeiro SDD do módulo. Derivado por leitura de código contra `origin/main` (`ec9584b996`, 2026-11-16).
> Âncoras são `caminho:linha` nessa árvore; o §5 e o §6 são re-deriváveis — se uma linha andar, re-localize pelo símbolo citado.

---

## §0 — Base empírica 🖐 curado (foto datada — 2026-11-16)

Fontes: (1) canon — `SPEC.md`, `SCOPE.md`, `BRIEFING.md`, ADRs 0123/0421/0422/0453, `Pages/Arquivos/Index.casos.md` (UC-INDEX-01..09); (2) código do módulo + consumidores. **(3) Blade legada:** procurado em `resources/views/**` e `Modules/Arquivos/Resources/` — não há Blade do módulo (a pasta `Resources/` não existe). **(4) Delphi:** não há `ANTI-REGRESSAO-*`/`PARIDADE-*` em `memory/requisitos/Arquivos/`. Módulo de armazenamento não tem tela equivalente no legado; nada foi inventado para as fontes 3 e 4. Sem medição de produção nesta versão (volume de linhas/disco ⬜ não-medido).

---

## §1 — Visão geral ⚙️ derivado

Backbone de binários do ERP: qualquer Model anexa arquivo; o módulo guarda no disco certo, deduplica por conteúdo dentro da empresa, classifica em bucket (cofre cifrado para sensível), registra trilha append-only, serve por link assinado e aplica retenção/aviso ao titular. Uma tela de governança própria (`/arquivos`, `Pages/Arquivos/Index.tsx`, leitura + classificar/excluir/restaurar/simular).

| Superfície | Onde |
|---|---|
| API para consumidores | `ArquivosService::attach` `Modules/Arquivos/Services/ArquivosService.php:46` · `attachNoNegocio` `:69` · trait `Modules/Arquivos/Concerns/HasArquivos.php:26` (`arquivos()`), `:31` (`attachArquivo`) |
| App das lojas (sem sessão) | contrato `App\Contracts\Arquivos\AnexosDoApp` → `Modules/Arquivos/Services/AnexosDoAppService.php:28` |
| Download | `GET arquivos/download/{arquivo}` `Modules/Arquivos/Routes/web.php:90-92` → `DownloadController` |
| Tela | `Modules/Arquivos/Routes/web.php:38-66` → `ArquivosAdminController` + `RetencaoSimulacaoController` |
| Agendados | `arquivos:health-check` `app/Console/Kernel.php:798` · `arquivos:avisar-titulares --todos` `app/Console/Kernel.php:815` |

---

## §2 — Público-alvo e personas 🖐 curado

⬜ Não-verificado. Consumidor primário é **código** (módulos que anexam); humano é o administrador/superadmin na tela de governança. Persona formal é decisão [W].

---

## §3 — Governança aplicável ⚙️ derivado

| Tier 0 | Como morde aqui |
|---|---|
| Multi-tenant ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) | `attach` exige empresa na sessão (`ArquivosService.php:48-51`); dedupe filtra `business_id` (`:255-257`); `Arquivo` tem global scope (`Entities/Arquivo.php:94`). **Falha aberto sem sessão** — ver §5.4 L2 |
| LGPD Art. 37 (trilha) | `arquivos_audit_log` append-only; payload passa por `PiiRedactor` (`ArquivosService.php:377`, `:408`) |
| LGPD Art. 18 VI (aviso) | [ADR 0421](../../decisions/0421-arquivos-aviso-ao-titular-registro-e-janela.md) + [ADR 0422](../../decisions/0422-arquivos-aviso-titular-canal-email-whatsapp.md) |
| Cifragem em repouso | bucket `sensitive` → disco `vault` com `Crypt::encryptString` (`VaultEncryptionService.php:79`) |
| UI nunca apaga de vez (D4) | `RetencaoSimulacaoController.php:30` recusa `purge`; `SimularRetencaoJob.php:62` recusa de novo |

---

## §4 — Design system aplicável ⚙️ derivado

Uma tela (`Arquivos/Index`), contrato em `Index.charter.md` + `Index.casos.md` (UC-INDEX-01..09). Este SDD é do backbone; a forma da tela fica com o charter.

---

## §5 — Arquitetura ⚙️ derivado

### 5.1 Camadas

```
Model consumidor (HasArquivos: arquivos() morphMany)       App das lojas (token, sem sessão)
        │  serviço/controller do módulo                         │ AnexosDoAppService::anexar
        ▼                                                       ▼
ArquivosService::attach (biz da sessão) ──── attachNoNegocio (biz explícito)
   ├─ dedupe md5 (dono → empresa) ── arquivos_dedupe (contador global)
   ├─ CuradorEngine::classify → bucket ── sensitive → VaultEncryptionService → disco vault
   │                                  └── demais → disco disk_default
   ├─ INSERT arquivos ──► arquivos_audit_log ('upload')
   ▼
signed URL (60 min) → DownloadController → decifra se encrypted → stream  ('signed_url_consumed')
Retenção: RetentionCleanupCommand (lixeira, manual) · ArquivosRetentionService (dry-run pela tela)
Aviso: arquivos:avisar-titulares (diário) → AvisarTitularJob → AvisoTitularService::registrarAviso ('notice')
```

### 5.2 Modelo de dados

| Tabela | Papel | Âncora |
|---|---|---|
| `arquivos` | linha por (dono, arquivo); morph `arquivable_*`, `disk`, `storage_path`, `md5`, `bucket`, `encrypted`, `retention_days` (nullable, nunca preenchida), `titular_avisado_at`, `legenda`/`etapa`, soft-delete | `Database/Migrations/2026_05_10_000001_create_arquivos_table.php:47` · `2026_10_01_000001_…:43` · `2026_10_15_000001_…:23-26` |
| `arquivos_audit_log` | trilha append-only; `action` enum ampliado 3× (`signed_url_consumed`, `exported`, `notice`); `down()` recusa estreitar com linha gravada | `2026_05_10_000002_…:25-28` · `2026_07_02_000001_…:30` · `2026_08_10_000001_…:46` · `2026_10_01_000001_…:35` |
| `arquivos_dedupe` | contador global por md5, **sem** `business_id` de propósito (anti side-channel) | `2026_05_10_000003_…:8-12`, `:21-23` |

### 5.3 Fluxos críticos

**F1 — Consumidor anexa arquivo.** O trait dá a relação e um atalho; o atalho `attachArquivo` (`HasArquivos.php:31-33`) **não tem chamador de produção** — os consumidores reais chamam o serviço direto:
`Fisio/Services/MidiaExercicio.php:115` · `OficinaAuto/Http/Controllers/DviInspectionController.php:185` · `OficinaAuto/Http/Controllers/ServiceOrderPhotoController.php:79` · app: `app/Http/Controllers/Api/App/OsArquivosController.php:152` e `PedidoEntregaController.php:316` via `AnexosDoAppService.php:42`.
**Models que usam o trait** (`git grep -l "use HasArquivos\|HasArquivos;" -- '*.php'` = 16 arquivos: o próprio trait, 2 testes, 1 controller que só cita em comentário, **11 models**): `Cms/Entities/CmsPage` · `Financeiro/Models/BoletoRemessa` · `Fisio/Entities/DocumentoPaciente` · `Fisio/Entities/Exercise` · `NfeBrasil/Models/NfeDfeRecebido` · `NfeBrasil/Models/NfeEmissao` · `OficinaAuto/Entities/OaInspectionItem` · `OficinaAuto/Entities/OsAssinatura` · `OficinaAuto/Entities/ServiceOrder` · `Repair/Entities/JobSheet` · `Whatsapp/Entities/Message`. O app também anexa a `App\Transaction` (`PedidoEntregaController.php:316`), que **não** usa o trait — o morph aceita qualquer Model.

**F2 — Dedupe por md5 na empresa** (`ArquivosService::attachInternal`, `:86`). `md5_file` (`:88`) → (1) o mesmo dono já tem → devolve a linha dele, idempotente (`dedupeDoDono`, `:97`, `:297-304`); (2) outro dono da empresa tem → linha nova apontando para o **mesmo blob**, sem regravar (`dedupe` `:252-257` → `vincularBlobExistente` `:314-351`); (3) senão grava. `arquivos_dedupe` só conta ocorrências (`:353-368`). Hard-delete só apaga o blob na última referência (`blobCompartilhado`, `:281-292`).

**F3 — Classificação e cofre.** `CuradorEngine::classify` (`Services/Curador/CuradorEngine.php:75`) devolve bucket `active`/`memory`/`discard`/`sensitive` (`ArquivosService.php:116`). `sensitive` → disco `config('arquivos.disk_vault')` (`:117-118`, default `vault` em `Config/config.php:23`) e `VaultEncryptionService::putFileEncrypted` (`:130`), com teto de 50 MB que não pode ser desligado (`VaultEncryptionService.php:42-47`, `Config/config.php:45`). Demais → `disk_default` (`ArquivosService.php:119`). Caminho `biz-{id}/AAAA/MM/{md5}.{ext}` (`:125`). Reclassificar pela tela grava `classify` com motivo redigido (`ArquivosAdminController.php:722`, `:756`).

**F4 — Retenção.** Três peças, nenhuma automática:
- `arquivos:retention-cleanup` (`Console/Commands/RetentionCleanupCommand.php:46`) purga **só a lixeira**: `deleted_at` anterior a N dias (`:92-94`), N = `--days` ou `retention_days_default` = 90 (`:251`, `Config/config.php:57`); apaga blob se não compartilhado (`:148-153`), apaga a linha (`:172`), grava `hard_delete` (`:175`). **Sem schedule** (Kernel só agenda health-check e aviso).
- `ArquivosRetentionService` varre arquivo **vivo** por `created_at` (`Services/ArquivosRetentionService.php:50-63`), com `expireOne` (`:75`) e `purgeOne` (`:106`); só é alcançado pela simulação.
- Simulação: `POST arquivos/retencao/simular` (`Routes/web.php:63-65`, só superadmin) → `RetencaoSimulacaoController.php:30-45` força dry-run → `SimularRetencaoJob::handle` (`Jobs/SimularRetencaoJob.php:60-88`) chama `run(..., true, false)` e guarda relatório em cache 24 h (`:86`). Não escreve em `arquivos` nem na trilha.
- Excluir pela tela = soft-delete com motivo (`ArquivosAdminController.php:781-787`); restaurar só superadmin e só dentro do grace de 30 dias (`:799-813`, `Config/retention.php:117`).
- Prazos por contexto existem em **dois** espelhos (`Config/config.php:76-85` e `Config/retention.php:84-93`), lidos só para exibir na tela e calcular vencimento do aviso (`ArquivosAdminController.php:341`, `AvisoTitularService.php:173`). Qual prazo vale, para quê, e quem decide: **em aberto (decisão B1 [W])**.

**F5 — Aviso ao titular.** `arquivos:avisar-titulares --todos` diário 10:00 BRT só em `live` (`app/Console/Kernel.php:815-820`) → `AvisarTitularesCommand::handle` (`:37`, `elegiveis` `:105`, `AvisarTitularJob::dispatchSync` `:116`) → `AvisarTitularJob::handle` (`Jobs/AvisarTitularJob.php:63`): só canais ligados no negócio (`:67-71`), re-checa elegibilidade (`:74`), envia e-mail/WhatsApp respeitando opt-in (`:120-190`), e só se algum canal saiu chama `registrarAviso` (`:114`). `AvisoTitularService` (`Services/AvisoTitularService.php`): elegível = `sensitive`, fora da lixeira, não avisado, dono `App\Contact` (`:51`), faltando 1–30 dias (`:43`, `:58-74`); `registrarAviso` faz UPDATE condicional + linha `notice` sem PII na mesma transação (`:136-165`). Avisar nunca apaga (ADR 0421 D6).

**F6 — Download por link assinado.** Link de 60 min por `ArquivosService::signedUrl` (`:200-209`, grava `signed_url_issued`) ou pela tela sem gravar emissão (`ArquivosAdminController.php:457-467`). Rota `['throttle:60,1','web','auth','signed']` (`Routes/web.php:90`) → `DownloadController::__invoke` (`Http/Controllers/DownloadController.php:32`): `Arquivo::find` (`:40`), grava `signed_url_consumed` com IP e user-agent (`:46`, `:84-94`), decifra se `encrypted` (`:57-65`) ou faz stream do disco (`:68`).

**F7 — Trilha `arquivos_audit_log`.** Escritores (varredura `git grep "arquivos_audit_log"` fora de testes/migrations: 7 pontos de `insert`): `ArquivosService.php:379` (upload/reclassify/signed_url_issued/soft_delete/restore) · `DownloadController.php:84` · `RetentionCleanupCommand.php:175` · `ArquivosAdminController.php:756` (`classify`) · `AnexosDoAppService.php:66` · `AvisoTitularService.php:151` (`notice`) · `ExportZipCommand.php:376` (`exported`). Leitores: tela (`ArquivosAdminController.php:523`), `AuditLogCommand`, `HealthCheckCommand.php:252`, `CuradorStatsReader.php:58`.

### 5.4 O que não está garantido hoje (LACUNAS — não é comportamento)

- **L0 — download sem empresa garantida.** A rota não roda `SetSessionData` (`Routes/web.php:90`); o `Arquivo::find` (`DownloadController.php:40`) depende do global scope, que só filtra se a sessão já tiver a empresa (`Entities/Arquivo.php:95-98`). Sem essa chave, link assinado válido serve arquivo de qualquer empresa ao usuário autenticado. O comentário `ArquivosAdminController.php:453-455` afirma o contrário. Em conserto no lote L0 da auditoria.
- **L2 — escopo falha aberto.** `Entities/Arquivo.php:96` (`if ($businessId !== null)`) não filtra quando não há empresa; `creating` grava `business_id = 0` nesse caso (`:104`). Correção prevista pela [ADR 0453](../../decisions/0453-escopo-de-empresa-fecha-sem-contexto.md), lote L2.
- **Disco padrão no webroot.** `disk_default` = `env('ARQUIVOS_DISK_DEFAULT', 'local')` (`Config/config.php:11`), config registrada (`Providers/ArquivosServiceProvider.php:64`), e o disco `local` tem raiz `public_path('uploads')` (`config/filesystems.php:33-35`). Sem a env (não há `ARQUIVOS_DISK_DEFAULT` em arquivo versionado fora do config), arquivo não-sensível vai para dentro de `public/`, servível sem link assinado. O disco `arquivos` (`config/filesystems.php:63-66`) só é usado como fallback inline que nunca dispara (`ArquivosService.php:119`) e em `DownloadController.php:48`.
- **Backup não cobre os discos do módulo.** `config/backup.php:3` inclui só `public_path('uploads')` e `.env`; `storage/app/arquivos` e `storage/app/vault` (`config/filesystems.php:65`, `:81`) ficam fora. (Ironia: com o disco padrão errado acima, o não-sensível entra no backup por estar em `public/uploads`; o cofre nunca entra.)
- **Retenção de arquivo vivo não existe.** Nada preenche `arquivos.retention_days` (só `$fillable`; `attachInternal` ignora `$opts['context']`, `ArquivosService.php:86-160`); `RetentionCleanupCommand` não lê os prazos por contexto nem `arquivos_retention.enabled` (`Config/retention.php:75`, sem leitor). Política: em aberto (decisão B1 [W]).
- **Purge do serviço sem trilha.** `ArquivosRetentionService::expireOne`/`purgeOne` só escrevem `Log` (`:82-88`, `:131-133`), não `arquivos_audit_log` — diferente do comando (`RetentionCleanupCommand.php:175`).
- **Aviso sem população.** O critério de titular é `App\Contact` (`AvisoTitularService.php:51`), mas `Contact` não usa o trait e nenhum chamador de `attach`/`attachNoNegocio` em F1 anexa a ele: a janela tende a sair vazia. Ampliar o critério é pendência [W] da ADR 0421.
- **Trilha best-effort.** Falha de INSERT vira `Log::warning` e o fluxo segue (`ArquivosService.php:387-393`, `DownloadController.php:95-99`); redação de PII é fail-open (`ArquivosService.php:408-414`). O payload do download grava IP e user-agent sem redação (`DownloadController.php:89-92`).
- **`encrypted` por literal.** `ArquivosService.php:158` compara com `'vault'`, enquanto a escolha do disco usa `config('arquivos.disk_vault')` (`:127`): renomear o disco do cofre por env cifraria o blob e gravaria `encrypted=false`, e o download serviria texto cifrado.

---

## §6 — Casos de uso ⚙️ derivado + 🖐 [W] confere

> Derivados do contrato (ADR 0123/0421/0422, `Index.casos.md`) e confirmados no código. Nenhum `CU-ARQ-*` existia antes (`git grep "CU-ARQ-"` = 0). Ids de teste (UC-*) moram nos `casos.md`; aqui não se cria UC novo.

| CU | Ator · gatilho | Passos | Âncora |
|---|---|---|---|
| **CU-ARQ-01** Anexar arquivo a um registro | código do módulo consumidor · upload do usuário | sessão com empresa → md5 → dedupe → classifica → grava no disco → linha `arquivos` → `upload` na trilha | `ArquivosService.php:46-51`, `:86-175` |
| **CU-ARQ-02** Anexar pelo app sem sessão | app das lojas (token) · foto/anexo de OS ou entrega | controlador confere o dono na empresa → `attachNoNegocio` com empresa explícita | `AnexosDoAppService.php:28-42` · `ArquivosService.php:69` |
| **CU-ARQ-03** Reenviar o mesmo arquivo não duplica | consumidor · mesmo conteúdo | mesmo dono → mesma linha; outro dono da empresa → linha nova, mesmo blob | `ArquivosService.php:97-107`, `:297-351` · teste `DedupeMultiOwnerTest` |
| **CU-ARQ-04** Arquivo sensível vai cifrado ao cofre | curador · upload `.env`/chave/XML com PII | bucket `sensitive` → disco `vault` → `Crypt::encryptString` → `encrypted=true` | `ArquivosService.php:116-130`, `:158` · `VaultEncryptionService.php:79` |
| **CU-ARQ-05** Reclassificar com motivo | admin (`arquivos.access`) · tela | `POST {id}/classificar` → curador → linha `classify` com motivo redigido | `Routes/web.php:44-47` · `ArquivosAdminController.php:722`, `:756` |
| **CU-ARQ-06** Excluir e restaurar | admin exclui; superadmin restaura | soft-delete com motivo; restaurar só ≤ 30 dias | `ArquivosAdminController.php:781-787`, `:799-813` · `Routes/web.php:51-60` |
| **CU-ARQ-07** Purgar a lixeira vencida | operador · comando manual | seleciona `deleted_at` < hoje−N → apaga blob se último → apaga linha → `hard_delete` | `RetentionCleanupCommand.php:92-94`, `:148-175` |
| **CU-ARQ-08** Simular retenção sem apagar | superadmin · tela | recusa `purge` → força dry-run → job após resposta → relatório em cache 24 h | `RetencaoSimulacaoController.php:30-45` · `SimularRetencaoJob.php:62-86` |
| **CU-ARQ-09** Avisar titular antes do vencimento | agendador diário · negócio com canal ligado | elegíveis → re-checa → envia e-mail/WhatsApp com opt-in → registra `notice` só se saiu | `Kernel.php:815` · `AvisarTitularesCommand.php:105-116` · `AvisarTitularJob.php:63-116` · `AvisoTitularService.php:114-165` |
| **CU-ARQ-10** Baixar por link assinado | usuário autenticado · link da tela ou do consumidor | assinatura+expiração → busca arquivo → `signed_url_consumed` → decifra ou stream | `Routes/web.php:90-92` · `DownloadController.php:40-74` |
| **CU-ARQ-11** Não ver arquivo de outra empresa `[T0]` | usuário de outra empresa | dedupe, tela e download filtram por empresa | `ArquivosService.php:255-257` · `Entities/Arquivo.php:94-98` — **só com sessão** (§5.4 L0/L2) |
| **CU-ARQ-12** Trilha append-only consultável | admin · tela/comando | leitura por empresa; migration recusa estreitar o enum | `ArquivosAdminController.php:523` · `2026_10_01_000001_…:59-79` |

---

## §7 — Requisitos não-funcionais ⚙️ derivado

- **Observabilidade:** spans `arquivos.*` (`attach`, `dedupe_lookup`, `download`, `vault.*`, `retention.*`) via `OtelHelper::spanBiz`.
- **Throttle:** 60 req/min em todas as rotas (`Routes/web.php:38`, `:72`, `:90`).
- **Memória:** cifragem carrega o arquivo inteiro; teto 50 MB (`Config/config.php:45`); stream cifrado é a [ADR 0126](../../decisions/0126-vault-chunked-encryption-sprint-2.md), ainda não feito.
- **Saúde:** `arquivos:health-check --alert` diário 06:30 BRT (`app/Console/Kernel.php:798`).

---

## §8 — Estratégia de qualidade ⚙️ derivado

31 arquivos em `Modules/Arquivos/Tests/Feature/`. A lane `arquivos-pest.yml` lista 15 deles (`.github/workflows/arquivos-pest.yml:203-`); `node scripts/governance/test-lane-coverage.mjs --json` não acusou arquivo do módulo fora de lane (2026-11-16). A lane **não** está nas listas de required do `governance/required-checks-baseline.json` (lidas pelo parser em 2026-11-16) — vermelho dela não bloqueia merge.

**Lacunas de teste:** download sem empresa na sessão (L0); escopo sem sessão (L2); disco padrão `local`; `purgeOne` sem linha na trilha. Nenhum teste prova a ausência desses defeitos — e não deve ser escrito como se provasse.

---

## §9 — Riscos e dívidas 🖐 curado

Medido em 2026-11-16 contra o código.

| # | Risco | Âncora | Severidade |
|---|---|---|---|
| 1 | Download serve arquivo de outra empresa quando a sessão não tem a empresa (lote L0) | `Routes/web.php:90` · `DownloadController.php:40` · `Entities/Arquivo.php:96` | alta (Tier 0) |
| 2 | Escopo de empresa falha aberto; `business_id = 0` no create sem sessão (lote L2, ADR 0453) | `Entities/Arquivo.php:96`, `:104` | alta (Tier 0) |
| 3 | Disco padrão `local` = `public/uploads` sem `ARQUIVOS_DISK_DEFAULT` | `Config/config.php:11` · `config/filesystems.php:33-35` | alta |
| 4 | Backup não inclui `storage/app/arquivos` nem `storage/app/vault` | `config/backup.php:3` | alta |
| 5 | Nenhuma política de retenção de arquivo vivo; prazos por contexto só exibidos | `ArquivosService.php:86-160` · `Config/config.php:76-85` | em aberto (decisão B1 [W]) |
| 6 | `purgeOne`/`expireOne` não gravam na trilha | `ArquivosRetentionService.php:82-88`, `:131-133` | média |
| 7 | Aviso ao titular sem população real (critério só `App\Contact`) | `AvisoTitularService.php:51` | média |
| 8 | Trilha e redação best-effort; IP sem redação no download | `ArquivosService.php:387`, `:413` · `DownloadController.php:90` | média |
| 9 | `encrypted` comparado a literal `'vault'` | `ArquivosService.php:158` | baixa |
| 10 | `arquivos_retention.enabled` declarado sem leitor | `Config/retention.php:75` | baixa |

---

## §10 — Roadmap 🖐 [W] prioriza

1. Fechar L0 (download) e L2 (ADR 0453) — lotes da auditoria em curso.
2. Fixar o disco padrão fora do webroot e incluir `arquivos`/`vault` no backup.
3. Decisão B1 [W]: prazos de retenção, quem decide, e se a retenção de arquivo vivo existe.
4. Gravar purge do serviço na trilha; ampliar critério de titular (pendência ADR 0421).

---

## §11 — Referências ⚙️ derivado

- [`SPEC.md`](SPEC.md) · [`SCOPE.md`](SCOPE.md) · [`BRIEFING.md`](BRIEFING.md) · [`SUPERFICIE.md`](SUPERFICIE.md) · [`RUNBOOK-index.md`](RUNBOOK-index.md)
- Contrato da tela: `resources/js/Pages/Arquivos/Index.casos.md` (UC-INDEX-01..09)
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0123](../../decisions/0123-modules-arquivos-backbone.md) · [ADR 0126](../../decisions/0126-vault-chunked-encryption-sprint-2.md) · [ADR 0421](../../decisions/0421-arquivos-aviso-ao-titular-registro-e-janela.md) · [ADR 0422](../../decisions/0422-arquivos-aviso-titular-canal-email-whatsapp.md) · [ADR 0453](../../decisions/0453-escopo-de-empresa-fecha-sem-contexto.md)
- Proposta: [`decisions/proposals/arquivos-retencao-ui-aviso-titular.md`](../../decisions/proposals/arquivos-retencao-ui-aviso-titular.md) (D4/D5)
