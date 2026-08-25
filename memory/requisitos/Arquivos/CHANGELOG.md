---
id: requisitos-arquivos-changelog
---

# Changelog — Modules/Arquivos

Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) · [Semver](https://semver.org/).

## [0.5.0] - 2026-08-25 — Onda 1 da tela · PR-2 (vista Trilha + barra de abas)

> Contexto datado: a tela `/arquivos` (US-ARQ-013) nasceu em **2026-08-24** com a vista
> **Acervo** ([PR #6216](https://github.com/wagnerra23/oimpresso.com/pull/6216) + fix #6217),
> e aquele PR não deixou entrada aqui. Esta registra a onda seguinte.

### Added — vista Trilha (`arquivos_audit_log`, read-only)

- `ArquivosAdminController::buildTrilhaPayload()` — trilha paginada do próprio business:
  quando · ação · `#id` do arquivo · quem · resumo do payload. **Nenhuma rota nova**: é
  `?tab=trilha` na `GET /arquivos` que já existia.
- **Barra de abas** (`PageHeaderTabs` canon, navegação por rota) — nasce agora, com a
  segunda vista. Eram 4 vistas planejadas; hoje 2 (retenção e cofre nos PR-3/4).
- Filtro por ação com contagem, derivado de `GROUP BY` do próprio log — **sem lista de
  ações escrita em PHP**: o dono do vocabulário é o ENUM da coluna, que já mudou 2× por
  migration (`signed_url_consumed` 2026-07-02, `exported` 2026-08-10).
- `arquivos_audit_log` ganhou seu **primeiro leitor de UI**. Até aqui só o comando
  `arquivos:audit-log` a lia — a tabela existia desde a Sprint 1 sem superfície.

### Added — contrato de entrada (`ListArquivosRequest`)

- `tab` (`acervo|trilha`) e `acao` (validada por FORMA, `regex:/^[a-z_]+$/`, não por lista).
  São os 2 únicos campos acrescentados.

### Security — multi-tenant Tier 0 (ADR 0093) numa tabela SEM model

- A trilha lê por `DB::table`: **não há global scope**. O `where` por `business_id` ali é a
  ÚNICA defesa, o oposto da regra do acervo (que lê pelo model e onde repetir o `where`
  esconderia uma quebra do scope).
- **Fail-closed**: sem `business_id` na sessão a trilha devolve vazio. O global scope do
  model faz `if ($businessId !== null)` e, sem sessão, deixa passar sem filtro — aposta que
  não se repete aqui.
- O assert de contrato que proibia `where('business_id'` foi **escopado ao acervo**: aplicado
  ao arquivo inteiro, ele passaria a proibir a própria defesa da trilha (regra de precedência
  de 2026-07-06 — gate que, cumprido ao pé da letra, produz o dano que existe pra impedir).
  Em troca entrou um assert que **exige** o filtro na trilha, mais a prova comportamental
  cross-tenant 98 vs 99.
- Assert LGPD (`storage_path`/`md5`) **apertado**: cobria do método `linha()` até o fim do
  arquivo, passa a cobrir o arquivo inteiro.

### Fixed — chip de filtro voltava com erro de validação em vez de filtrar (vinha do PR-1)

- `irPara` espalhava `with_trashed: false` cru na query. `qs.stringify` (serializador do
  Inertia) produz `with_trashed=false`, e a regra `boolean` do Laravel **reprova a string
  `"false"`** — aceita só os nativos e `0|1|"0"|"1"`. O `paraQuery` do DataTable já
  normalizava pra `1|undefined`; o caminho dos chips, não. Achado ao estender o mesmo
  `irPara` pros chips da trilha, e **medido** antes de consertar (Validator standalone +
  `qs.stringify`), não deduzido da doc.

### Added — tests (`ArquivosAdminControllerTest`, UC-INDEX-02)

- 6 asserções novas: o caminho da trilha filtra por `business_id` · fail-closed sem sessão ·
  linha expõe `#id` e nunca o nome · payload vira resumo legível (bool vira palavra, JSON
  quebrado não derruba a tela) · **cross-tenant 98 vs 99** · filtro de ação não apaga os
  outros chips.
- 4 dispensam banco (valem nas 2 lanes); 2 rodam na lane MySQL `arquivos-pest.yml`, onde este
  arquivo já estava na allowlist. Sem `RefreshDatabase`: fixture por `arquivo_id` sentinela +
  cleanup no `afterEach`.

## [0.4.0] - 2026-05-16 — Wave 18 RETRY SATURATION

### Added (D9 — Service novo com 4 spans OTel)

- `Services/ArquivosRetentionService` — políticas LGPD Art. 16 com 4 spans canônicos:
  - `arquivos.retention.scan` — lista elegíveis (read-only)
  - `arquivos.retention.expire_one` — soft-delete individual idempotente
  - `arquivos.retention.purge_one` — hard-delete + remove storage (irreversível)
  - `arquivos.retention.run` — orquestração batch (default `dry_run=true` defesa em profundidade)

### Added (D8 — FormRequests novas, ratio 2 → 5)

- `Http/Requests/DeleteArquivoRequest` — soft-delete UI, reason LGPD opcional.
- `Http/Requests/RestoreArquivoRequest` — restore com authorize gate `arquivos.restore`/`superadmin`.
- `Http/Requests/ListArquivosRequest` — filtros admin (bucket allow-list, mime regex, per_page cap 100).

### Added (D5 — README "como cliente usa")

- `memory/requisitos/Arquivos/README-COMO-CLIENTE-USA.md` — jornada por persona (operador comum, admin Wagner, auditor LGPD).

### Added (tests Wave 18 RETRY)

- `Tests/Feature/Wave18RetryArquivosSaturationTest` — cobertura D9 (spans declarados + run dry_run default) + D8 (3 FormRequests) + D5 (README existe + conteúdo).

### Changed

- `module.json`: adiciona `fsm_n_a:true` + razão (lifecycle SoftDeletes trivial, não justifica FSM canônica ADR 0143).

## [0.3.0] - 2026-05-16 — Wave 18 base

### Added

- `Services/VaultEncryptionService` gains spans `arquivos.vault.put_encrypted` + `arquivos.vault.get_decrypted`.
- `Services/ArquivosService::attach/classify/signedUrl/softDelete/restore` envelopados em `OtelHelper::spanBiz` (D9.a).
- `Tests/Feature/ArquivosOtelD9Test`.

## [0.2.0] - 2026-05-10 — Sprint 1 dia 4

### Added

- `Services/VaultEncryptionService` — AES-256-CBC envelope encryption (Crypt::encryptString) com cap 50MB.
- `Http/Requests/UploadArquivoRequest` + `DownloadArquivoRequest`.
- `arquivos:reencrypt-vault` command (rotação APP_KEY).

## [0.1.0] - 2026-05-05 — Bootstrap ADR 0123

- Entity `Arquivo` + Polimorfismo morph (`arquivable_type/arquivable_id`).
- Trait `HasArquivos` (opt-in pra módulos consumidores).
- `ArquivosService` API canônica (attach + classify + signedUrl + softDelete + restore).
- `CuradorEngine` pipeline 5-fase (DISCOVER → CLASSIFY → REPORT → REVIEW → APPLY).
- 7 comandos artisan (audit-log, dedupe-stats, export-zip, health-check, recalcular-metadata, retention-cleanup).

---

## Implementação (histórico movido de `Modules/Arquivos/CHANGELOG.md`)

> Movido em 2026-08-10. Os dois changelogs registravam eventos DIFERENTES — acima as
> decisões/requisitos, aqui o que foi de fato mergeado. Medido antes de fundir:
> sobreposição de datas era 0-2 (máx. 2, em ComVis e KB), logo nenhum lado era cópia do
> outro e escolher um deles perderia registro. Conteúdo preservado na íntegra.

# CHANGELOG — Modules/Arquivos

> Append-only. Mais novo no topo. Datas YYYY-MM-DD.

## [Wave 28 — Polish saturation 74-88 → ≥92 (+4pp)] — 2026-05-17

### Adicionado — D9 +1 span `arquivos.retention.summary` (5º span Retention canon)
- `ArquivosRetentionService::summary(int $businessId, int $retentionDays): array` — novo método público read-only retornando `{total, soft_deleted, expired_eligible, business_id}`. Útil pra HealthCheck dashboard (cron daily) e Wagner conferir saúde por tenant ANTES de aprovar `purge=true`. **Zero mutação** (preserva fail-secure dry_run W18) — apenas count buckets via `Arquivo::query()` respeitando `business_id` scope Tier 0.
- Span attributes sem PII: apenas `business_id` + `retention_days` + `module`.

### Adicionado — D2 +3 Pest Wave 28
- `Tests/Feature/Wave28ArquivosSaturationTest.php` (~7 cenários):
  - D9 W28 método novo + 5º span Retention (cumulativo 4 W18 + 1 W28)
  - D2 W28 businessId+retentionDays Tier 0 obrigatórios + shape canon `{total, soft_deleted, expired_eligible, business_id}` + zero mutação validado via source-grep block (regression guard)
  - OtelHelper fail-loud em spans `arquivos.retention.*` preservado
  - D3 W28 CHANGELOG entry (este)

### D3 W28 doc
- CHANGELOG (este entry).

### Preservado
- D7.c retention.php Wave 25 (8 entities mapeadas LGPD Art. 15-16 + grace_period_days 30)
- D9 ArquivosService spans baseline ≥6 (Wave 18 + 26 dedupe_lookup novo)
- D7.a PiiRedactor em audit log + redact_payload fail-open

### Referências
- ADR 0093 Multi-tenant Tier 0 IRREVOGÁVEL · ADR 0123 Modules/Arquivos backbone · ADR 0155 Module Grade v3 D9 saturated +1 · LGPD Art. 15-16

## [Wave 27 — Polish D5 README persona + D8 +2 FormRequests + D9 +2 spans] — 2026-05-17

### Adicionado — D5 README expandido com persona Auditor LGPD
- **`README.md`** novo (Wave 23 fez parcial — Wave 27 consolida):
  - Seção "Por que existe" + arquitetura 3 tabelas + 1 trait + 3 Services
  - Persona explícita "Auditor LGPD" (DPO/ANPD/Eliana[E]) com 5 dores típicas
    rastreáveis a feature/test correspondente (preview, ReclassifyArquivoRequest
    motivo obrigatório, RetentionRunRequest purge+motivo, report append-only,
    `Config/retention.php` declarada)
  - Tabela "Garantias canônicas ao Auditor" mapeando 7 perguntas → 7 respostas
    com refs a código + ADR
  - Quick-start integração consumer module (3 linhas: trait + attach + signedUrl)

### Adicionado — D8.c +2 FormRequests novos
- **`ReclassifyArquivoRequest`** — força `motivo` obrigatório (min 5 chars) pra
  reclassificação manual; `force_bucket` whitelist; `batch_tag` regex anti-injection;
  authorize() valida arquivo pertence ao business_id da sessão (defesa em profundidade).
- **`RetentionRunRequest`** — endpoint admin pra `ArquivosRetentionService::run()`:
  - `retention_days` faixa segura 90..3650 (off-by-one defense)
  - `dry_run` default `true` (defesa em profundidade)
  - `purge` requer `dry_run=false` E `motivo` ≥10 chars (LGPD Art. 18 §VI rastreável)
  - Helper `toServiceArgs()` retorna shape pronto pro Service

### Adicionado — D9.a +2 spans OTel em ArquivosRetentionService
- **`arquivos.retention.preview`** — agregado dry-run por bucket pra dashboard
  Auditor LGPD (Grafana "quantos arquivos vão purged amanhã?"). Sem mutação,
  retorna `{total, by_bucket[], oldest_at}`.
- **`arquivos.retention.report`** — payload determinístico append-only pós-batch
  com `meta.user_id` + `meta.batch_tag` + `law_ref` (Art. 16 + §VI explicitada).
  Loga `arquivos.retention.report` pra audit trail externa (export PDF/CSV
  consumido por endpoint admin não exposto neste service).

### Adicionado — D2 Pest cobertura Wave 27
- **`Tests/Feature/Wave27ArquivosPolishTest.php`** — 20 cenários (todos passed local, 65 assertions):
  - 5 cenários ReclassifyArquivoRequest (motivo obrig, min 5, whitelist, payload válido, anti-injection)
  - 5 cenários RetentionRunRequest (retention_days obrig, faixa 90..3650, motivo
    obrig-se-purge, toServiceArgs shape, motivo opcional soft-delete)
  - 7 cenários D9.a Services (DI, OtelHelper canon, 2 spans novos validados, ≥6
    spans Retention total, ≥13 spans cross-services, ArquivosService sem regressão,
    métodos preview/report existem com bizId int)
  - 3 cenários D5 README (existe, persona Auditor LGPD presente, 5 garantias
    canônicas mencionadas, ADR 0123 + 0093 referenciados)

### Não alterado (intencional — já saturado)
- D9.c HealthCheckCommand 5 checks canon (Sprint 2)
- D7.a PiiRedactor coverage export ZIP + audit log persist (Wave 10)
- D7.c shim canon `Config/retention.php` (Wave 25)
- Trait HasArquivos morphMany (Sprint 1)

### Referências
- ADR 0093 Multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0123 Modules/Arquivos backbone
- ADR 0155 Module Grade v3 (D5/D8/D9 polish Wave 27)
- ADR 0159 Wave 27 polish
- LGPD Art. 16 (eliminação tempestiva) + Art. 18 §VI (direito eliminação)

## [Wave 25 — Polish D7.c retention canônico] — 2026-05-16

### Adicionado — D7.c rubrica governance v3 (+1 arquivo)
- **`Config/retention.php`** novo — shim canônico LGPD que espelha `config.php['retention_days_policy']` (operacional) em formato D7.c-compliant pra auditoria governance v3:
  - 8 entities mapeadas: `nfe-xml` 1825d (Lei 8.846/94), `nfse-xml` 1825d, `documentos-fiscais` 1825d (CTN Art. 173), `contratos` 1825d (CDC Art. 27), `repair-foto` 730d, `os-anexo` 730d, `ticket-anexo` 365d, `default` 90d (LGPD Art. 15-16).
  - `strategy='hard_delete'` (alinha LGPD Art. 18 §VI direito eliminação).
  - `grace_period_days=30` (janela entre retention expirar e hard delete real — HealthCheck #4 alerta).
  - `bucket_override['sensitive']=365d` (mitiga exposição PII em bucket vault).

### Por que ter retention.php se já tem em config.php?
- `config.php` é OPERACIONAL (consumido pelo Service ao fazer upload — preenche `arquivos.retention_days` per-row).
- `retention.php` é AUDITORIAL/DOCUMENTAL (rubrica governance v3 D7.c — fonte da verdade pra compliance LGPD + facilita auditoria estado-arte).
- Mudança real DEVE atualizar ambos (acoplamento explícito documentado nos comments).

### Não alterado (intencional — já saturado)
- D9.a OtelHelper coverage já em 3 Services (ArquivosService + ArquivosRetentionService + VaultEncryptionService) desde W18.
- D9.c HealthCheckCommand já com 5 checks canônicos (orphan_files, dedupe_inconsistent, audit_log_lag, retention_overdue, vault_encryption_ratio) desde Sprint 2.
- D7.a PiiRedactor coverage já aplicada em export ZIP + audit log persist.

### Referências
- ADR 0093 Multi-tenant Tier 0
- ADR 0123 Modules/Arquivos backbone (Sprint 1+2)
- ADR 0155 Module Grade v3 (D7.c saturated 8/8 sub_destinations declaradas)
- ADR 0159 Wave 25 polish (level `biz_1_wagner_active` mantido)
- LGPD Art. 15-16 (eliminação tempestiva) + Art. 18 §VI (direito eliminação)
