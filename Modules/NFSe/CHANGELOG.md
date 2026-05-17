# CHANGELOG — Modules/NFSe

Mudanças observáveis na capacidade NFSe (ISSQN municipal). Append-only por release/wave.

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
