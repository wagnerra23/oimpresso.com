# Pedido zero-toque — Arquivos (Sprint 2 · US-ARQ-013 · ADR 0123)

> De [CC] para [CL] · 2026-08-24 · escopo a aprovar por [W]
> **Lido no `main` NESTE turno** (tree `4ebb8d193a74`): `Modules/Arquivos/module.json` ·
> `Routes/web.php` · `Http/Controllers/{DataController,DownloadController,InstallController}.php` ·
> `Providers/ArquivosServiceProvider.php` · as 7 `Http/Requests/*` · assinaturas de
> `Services/{ArquivosService,ArquivosRetentionService,VaultEncryptionService,Curador/*}` ·
> árvore de `Modules/Arquivos` (62 arquivos) e de `resources/js/Pages` (706 arquivos).
> ⚠️ **Nada aqui está commitado** — as tools de GitHub do Cowork são read-only.
> Build F1: `arquivos-page.jsx` + `arquivos-data.jsx` (rota `arquivos` do app único).

## Contexto em 4 linhas (fatos, não impressão)

1. O módulo existe e é robusto: 3 services, 7 commands, 8 migrations, 25 testes Feature — **e nenhuma tela**. `DataController::modifyAdminMenu()` é no-op **de propósito** e o docblock aponta o destino: "Pages/Arquivos … Sprint 2".
2. `Routes/web.php` só tem as 3 rotas Install (ADR 0024) + `arquivos.download` (signed). **Nenhuma rota admin.**
3. Mas o **contrato da tela já está escrito**: `UploadArquivoRequest` · `ListArquivosRequest` · `DownloadArquivoRequest` · `DeleteArquivoRequest` · `RestoreArquivoRequest` · `ReclassifyArquivoRequest` · `RetentionRunRequest` — 7 FormRequests **órfãs**, com authorize() multi-tenant Tier 0, `dry_run` default `true` e `motivo` obrigatório pra purge. Alguém desenhou a UI no backend e parou antes do controller.
4. `resources/js/Pages/` **não tem** `Arquivos/` nem `Admin/` (filtro na árvore: 0/706). A tela é 100% nova — este handoff é criação, não tradução.

Consequência de método: **o F1 não inventa endpoint**. Cada onda abaixo liga uma FormRequest que já existe a um método de Service que já existe. O único gap real de domínio é o **aviso ao titular** (onda 3).

## Decisões [W] que travam ondas (responder antes do PR-1)

| # | Decisão | Recomendação [CC] |
|---|---|---|
| D1 | **Onde a tela mora**: `Modules/Admin` (o que o docblock diz) vs `resources/js/Pages/Arquivos/Index.tsx` (o que o resto do app faz) | `Pages/Arquivos/Index.tsx` no app — não existe `Pages/Admin/`, criar um Admin Center só por causa disso abre padrão novo |
| D2 | **Permissão**: `arquivos.access` (declarada em `DataController::user_permissions`, default `false`) é a lei de todas as vistas? | sim, `can('arquivos.access')` na rota; **não verifiquei** se algo já consome essa chave (busca no repo foi truncada) |
| D3 | **Papel de governança** — retenção/cofre/trilha exigem segundo nível (auditor/gestor) ou basta `arquivos.access`? | segunda permissão `arquivos.governanca` pra purge + aviso; o F1 já separa por `papel` |
| D4 | **Purge pela UI existe?** ou hard-delete continua só no `RetentionCleanupCommand`? | UI dispara **dry-run + relatório**; purge real só via command/fila com `motivo` — a tela nunca apaga no request |
| D5 | **Aviso ao titular** (LGPD Art. 18 §VI, `notice: 30` em `retention.php`) precisa ADR + coluna nova (`titular_avisado_at`) | sim: ADR pequeno + migration; é o único item que muda schema |
| D6 | Menu: `modifyAdminMenu()` deixa de ser no-op **quando**? | só depois da onda 1 verde — menu apontando pra tela incompleta é pior que menu ausente |

## Ondas de PR

Regra de ordem: **leitura antes de mutação, mutação reversível antes de irreversível, menu por último.** Cada PR é 1 PR (não squash), empilhado no anterior (`arq/w1-acervo` → `arq/w1-trilha` → …). Todo PR fecha com a lane verde do módulo (`Modules/Arquivos/Tests/**` já tem 25 arquivos — nenhum pode ficar vermelho).

### Onda 0 — prova, zero código de produção
- **PR-0 · trio da tela + contrato.** `resources/js/Pages/Arquivos/Index.charter.md` + `Index.casos.md` (UC em Dado/Quando/Então, formato de `Pages/Cliente/Ledger.*`) + `prototipo-ui/contrato/arquivos.contract.json` (ADR 0286: as 4 vistas — acervo · retenção · cofre · trilha — copy literal, estados, proibições). Destrava `scripts/qa/prototipo-readiness.mjs`.
- Se D5 = sim: **PR-0b · ADR** do aviso ao titular. ADR é Tier 0 → texto proposto por [CC], **cunhagem só [W]**.

### Onda 1 — ler (reversível, sem escrever nada)
- **PR-1 · acervo.** Rota `GET arquivos` (mesma stack de middleware do grupo Install + `can(D2)`) → `ArquivosAdminController@index` usando **`ListArquivosRequest`** (já existe) → Inertia `Arquivos/Index`. Colunas do F1: arquivo + `sub_destination` (com a política e a **base legal** ao lado, de `Config/retention.php`), dono (`arquivable` → link pra tela do dono; sem dono = **órfão**, achado e não item), bucket/visibility, disk/`encrypted`, tamanho, vence em. Sem upload nesta tela (upload é da tela do dono, via `HasArquivos`).
- **PR-2 · trilha.** `GET arquivos/trilha` — leitura paginada de `arquivos_audit_log` com o enum já alargado 2×. Trilha é **append-only**: nenhuma ação de edição na UI.
- **PR-3 · retenção (dry-run puro).** `GET arquivos/retencao` alimentado por `ArquivosRetentionService::summary()` + `preview()` — nenhuma escrita, nenhum job. Mostra grace 30d, `notice` 30d e o que vence nos próximos 30/90 dias.
- **PR-4 · cofre.** `GET arquivos/cofre`: achados do `HealthCheckCommand` + `DedupeStatsCommand` + `CuradorStatsReader::fetch()` (órfão, md5 duplicado, `vault` sem `encrypted`, metadata a recalcular). Números vêm do command — a tela não recalcula.
- **Portão da onda 1:** smoke 1280/1440 sem scroll horizontal · nenhum enum cru na UI · **zero PII** nas vistas de governança · `contrato:check` verde. Só então **PR-5 · menu** (`modifyAdminMenu()` deixa de ser no-op) — D6.

### Onda 2 — mutar o reversível
- **PR-6 · classificar.** `POST arquivos/{arquivo}/classificar` com **`ReclassifyArquivoRequest`** → `ArquivosService::classify()`; grava `classified_by/at` + audit `classify` com o `motivo` (já obrigatório, min 5). Drawer PT-02, nunca modal full-screen.
- **PR-7 · excluir + restaurar no grace.** `DeleteArquivoRequest` → `softDelete()`; `RestoreArquivoRequest` → `restore()`. A UI só oferece restaurar **dentro do grace** (30d) e mostra quanto sobra. Hard-delete não aparece aqui.
- **Portão:** teste que prova que soft-delete + restore preservam a trilha e que fora do grace o botão não existe (não só desabilitado).

### Onda 3 — o irreversível e o legal (só com D3/D4/D5 respondidos)
- **PR-8 · rodar retenção pela tela, em dry-run.** `POST arquivos/retencao/simular` com **`RetentionRunRequest`** (`dry_run=true` forçado no controller) → `run()` + `report()`; resultado em job/fila, nunca no request. Faixa 90..3650 já validada pela Request.
- **PR-9 · aviso ao titular** (D5). Migration `titular_avisado_at` + ação `notice` no enum do audit log + envio; janela = `notice` (30d) antes do vencimento, só `bucket=sensitive` com titular identificado.
- **PR-10 · purge atrás de portão** (só se D4 = sim). `purge=true` exige `dry_run=false` + `motivo` ≥10 (a Request já cobra) + confirmação dupla na UI + permissão de governança + registro `hard_delete` na trilha. **Não entra sem** onda 2 mergeada, lane verde, e aprovação de [W2] em screenshot. Enquanto não entrar, o `RetentionCleanupCommand` continua sendo o único caminho — e isso é aceitável.

### Onda 4 — acabamento
- **PR-11 · DS vivo:** trocar peças caseiras por `DataTable`/`StatusBadge`(kind `frescor`)/`KpiCard`/`EmptyState`/`Drawer`/`Toast` do DS. Chips e busca ficam (padrão do shell).
- **PR-12 · a11y + performance:** nome acessível em toda ação só-ícone (o F1 já resolve com `sr-only`), foco visível, `tabular-nums` nas colunas de data/tamanho, paginação server-side no acervo e na trilha.

## Checklist pós-merge

- [ ] `npm run screen:files -- Arquivos/Index` → trio ✅
- [ ] lane `Modules/Arquivos/Tests/**` verde (25 arquivos existentes) + os novos de controller/policy
- [ ] `contrato:check` verde nas 4 vistas
- [ ] smoke: nenhum nome de titular/PII nas vistas retenção/cofre/trilha
- [ ] multi-tenant: usuário biz=1 não vê arquivo biz=4 em nenhuma das 4 vistas (espelha `MultiTenantTest`)
- [ ] `arquivos.download` intacta (signed + throttle 60,1)
- [ ] paridade: build exportado em `prototipo-ui/cowork/arquivos/` declarado no host (`scripts/cowork-paridade.mjs --check`)

## Como aplicar

Cole `PROMPT-ZERO-TOQUE.md` (nesta pasta) uma vez no Claude Code plugado no repo. Este arquivo é o anexo de escopo — o prompt referencia as ondas por número.
