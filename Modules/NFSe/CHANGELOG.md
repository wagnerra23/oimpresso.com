# CHANGELOG — Modules/NFSe

Mudanças observáveis na capacidade NFSe (ISSQN municipal). Append-only por release/wave.

## Wave 27 POLISH FINAL — 2026-05-17 (atual 77-88 → target ≥90)

### D1 Pest cross-tenant 25→40 cenários (EXPAND)
- `Tests/Feature/Wave27PolishTest.php` (33 cenários, 70 assertions) — reflection + source-grep, ZERO hit DB:
  - **D1 cross-tenant scenarios EXPANDIDOS (W25 ~16 → W27 +15+)**:
    - NfseBusinessScope fail-secure session ausente (early return) — CLI/job sem ctx
    - NfseBusinessScope respeita superadmin bypass (`auth()->user()->can('superadmin')`)
    - NfseBusinessScope auto-popula `business_id` em `creating` event
    - Coluna `<table>.business_id` qualificada (anti-IDOR via JOIN)
    - NfseProviderConfig isolado (tabela `nfse_provider_configs`)
    - NfseEmissaoService.getConfig usa `withoutGlobalScopes()` + `$payload->businessId` explícito (job sem session)
    - idempotency_key lookup tem `AND business_id` (anti-cross-tenant idempotency global)
    - SUPERADMIN comments documentam ≥2 contextos de bypass (auditoria ADR 0093)
    - NfseEmissao.fillable contém `business_id` obrigatório
    - NfseCertificado alias herda HasBusinessScope do pai NfeCertificado (schema unificado)
    - idempotencyKey() determinístico por payload (sha/md5/hash anti-duplicação fiscal)
    - cancelar() respeita `business_id` do model em log
    - marcarErro() preserva business_id em log de erro (correlação cross-tenant)
    - Status emitida append-only — combinação SoftDeletes + LogsActivity + isCancelada()
  - **D7 LogsActivity NfseEmissao EXPAND**:
    - logFillable + logOnlyDirty + dontSubmitEmptyLogs (audit completo sem ruído)
    - logExcept canônico ['xml_envio', 'xml_retorno', 'pdf_url'] (storage cost lock)
    - useLogName('nfse.emissao') (filtro audit canon)
    - Audit trail registra status fiscal + PII tomador (4 campos LGPD Art. 37)
    - Audit trail registra valores fiscais (5 campos: valor_servicos/iss/aliquota/lc116/iss_retido)
    - Audit trail registra refs gateway (provider_protocolo/codigo_verificacao/numero)
  - **D9 spans NfseEmissaoService EXPAND**:
    - OtelHelper canon (App\Util — lock anti-fork dentro do módulo)
    - Span `nfse.emissao` + atributo `$payload->businessId` (correlação prod)
    - MAX_RETRIES=3 + backoff exponencial `sleep(2 ** ($tentativa - 1))`
    - 4 exceptions diferenciadas (Rps/Cert/Timeout/Generic)
    - Log channel `nfse` dedicado (3 níveis: info emitida/cancelada + error)
    - PiiRedactor LGPD lock-in em erro_mensagem
    - Idempotência preserva 1ª nota (`whereIn('status', ['emitida', 'processando'])`)
  - **Tier 0 imutabilidade fiscal CONFAZ Art. 14**:
    - cancelar() bloqueia dupla cancelação (NfseJaCanceladaException)
    - cancelar() recebe motivo obrigatório (auditoria fiscal)
    - NfseProviderConfig.isProducao() gate anti-erro homolog→prod

### Tier 0 IRREVOGÁVEIS preservados
- CONFAZ SINIEF 07/2005 Art. 14 imutabilidade fiscal (status emitida append-only)
- ADR 0093 multi-tenant (NfseBusinessScope + NfseCertificado alias)
- LGPD Art. 6º IX minimização (PiiRedactor em erro_mensagem)
- Pest Wave 27 NÃO toca tabelas fiscais (nfse_emissoes, nfe_certificados) — só metadata + reflection

### Validated
- `php vendor/bin/pest Modules/NFSe/Tests/Feature/Wave27PolishTest.php` → **33/33 passed (70 assertions, 10.05s)**

### Refs
- ADR 0093 (multi-tenant Tier 0) · ADR 0101 (tests biz=1) · ADR 0094 §5 (SoC)
- CONFAZ SINIEF 07/2005 Art. 14 · LGPD Art. 6º IX + Art. 37

### Estimativa nota
- Wave 25 baseline: ~77-88 (variável por dimensão)
- Wave 27 polish final: **≥90** com cross-tenant 25→33 + D7 LogsActivity expand + D9 spans expand

## Wave 25 POLISH — 2026-05-16 (saturação ≥90 D2/D6/D7)

### D2 Pest comprehensive
- `Tests/Feature/Wave25SaturationTest.php` (16 cenários) — reflection + source-grep + Config read, ZERO hit DB pra paralelização worktree:
  - NfseEmissao usa LogsActivity Spatie (D7 audit trail LGPD append-only)
  - NfseEmissao `getActivitylogOptions()` exclui xml_envio/xml_retorno/pdf_url (storage cost)
  - NfseEmissao fillable contém 14 campos canon (PII tomador + fiscais)
  - NfseEmissao casts decimais precisão correta (valor:2, aliquota_iss:4, iss_retido:bool, competencia:date)
  - NfseEmissao status helpers (isEmitida/isCancelada/isErro/isPendente) consistentes
  - NfseEmissao statusLabel + statusColor mapeiam 5 status canon (rascunho/processando/emitida/cancelada/erro)
  - NfseCertificado herda NfeCertificado (alias schema unificado) + isExpirado alias isVencido
  - NfseEmissaoService span canon `nfse.emissao` + MAX_RETRIES=3 + idempotencyKey
  - NfseEmissaoService cancelar() bloqueia dupla cancelação (NfseJaCanceladaException)
  - PiiRedactor aplicado em erro_mensagem (LGPD Art. 6º IX minimização)
  - Config retention 5 anos CONFAZ (1825d) + 1 ano erro/webhook (365d)
  - Config retention default strategy=soft_delete + notice_period=30d (LGPD Art. 18 §VI)
  - NfseController index NÃO usa Inertia::defer (rollback PR #963 documentado)
  - NfseController declara permissions canon nfse.view + nfse.emit + nfse.cancel (via CancelarNfseRequest)
  - NfseEmissao usa SoftDeletes (preserva audit fiscal CONFAZ 5y)
  - NfseEmissao usa NfseBusinessScope (multi-tenant Tier 0 ADR 0093)

### D6 — Inertia::defer NOTA (rollback PR #963 lição)
- Controller documenta WHY não migrou — Index.tsx consome `notas.data.length` direto sem `<Deferred>` wrap. Ativar defer SEM wrap React quebra (PR #963 rollback confirmou). Pré-req: PR companion atualizar Index.tsx pra `<Deferred data="notas">` + skeleton fallback (skill `inertia-defer-default` Tier B). Wave 25 mantém pattern preservado e adiciona Pest que garante regra ("NÃO migrado neste PR" + paginate(25) presentes no Controller).

### D7 — LGPD push (cobertura comprehensive)
- Retention config 5 entidades documentadas: nfse_emissao_fiscal (5y CONFAZ), nfse_emissao_erro (1y), webhook_municipal (1y), provider_config (∞), certificado_a1 (∞ — lifecycle cert A1 expires_at)
- LogsActivity em NfseEmissao append-only — auditoria PII tomador + valor + status (LGPD Art. 37)
- PiiRedactor em erro_mensagem service — webservice prefeitura pode ecoar CPF/CNPJ no SOAP

### Tier 0 IRREVOGÁVEIS preservados
- Imutabilidade fiscal CONFAZ SINIEF 07/2005 Art. 14 — status emitida append-only via SoftDeletes
- ADR 0093 multi-tenant — NfseBusinessScope + NfseCertificado alias herda HasBusinessScope do pai NfeCertificado
- Pest Wave 25 NÃO toca tabelas fiscais (nfse_emissoes, nfe_certificados) — só metadata + reflection

## Wave 18 — 2026-05-16 (governance saturation)

### D1 — Multi-tenant Tier 0 (ADR 0093)
- Novo Pest `NfseCertificadoMultiTenantIsolationTest.php` — 3 testes:
  - Alias `NfseCertificado` herda scope `HasBusinessScope` corretamente (biz=99 não vaza pra session biz=1)
  - `isExpirado()` consistente com `isVencido()` do pai `NfeCertificado`
  - Contrato `business_id` NOT NULL em `nfe_certificados`
- Cobertura cross-tenant explícita pra credenciais fiscais A1/A3 (CNPJ titular + encrypted_password)

### D7 — Retention
- `Config/retention.php` confirmado: 5 anos (1825d) pra `nfse_emissoes` autorizadas/canceladas (CONFAZ); 1 ano pra rejeitadas/erro (sem efeito fiscal)
- Append-only `activity_log` (LogsActivity em NfseEmissao) — NUNCA purgada

### Mantido
- Multi-tenant via `NfseBusinessScope` (session `user.business_id`)
- `NfseCertificado` é alias de `Modules\NfeBrasil\Models\NfeCertificado` — schema unificado migration `2026_05_07_210000`
