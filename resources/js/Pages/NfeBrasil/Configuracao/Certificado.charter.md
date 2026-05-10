---
page: /nfe-brasil/configuracao/certificado
component: resources/js/Pages/NfeBrasil/Configuracao/Certificado.tsx
owner: wagner
status: live
last_validated: 2026-05-10
parent_module: NfeBrasil
related_adrs: [0029, 0093, 0094]
related_us: [US-NFE-041, US-NFE-061]
tier: A
charter_version: 1
---

# Page Charter — /nfe-brasil/configuracao/certificado

> **Status:** live em 2026-05-10. Charter criado por skill `charter-write` disparado pela auditoria de completude (US-NFE-061 P0 da skill `module-completeness-audit`). Non-Goals + Anti-hooks aprovados por Wagner em 2026-05-10. Bloqueador dos 4 PRs Manifestação NFe foi liberado.

---

## Mission

Centralizar **upload, validação e diagnóstico** do certificado A1 (.pfx/.p12) do business — única tela onde o usuário sobe credencial fiscal, vê alertas de vencimento, consulta painel fiscal consolidado (CNPJ, regime, NCM padrão, série NFe, ambiente SEFAZ) e roda smoke real contra SEFAZ antes de emitir.

---

## Goals — Features (faz)

- AppShellV2 + Head `Certificado A1 · NF-e Brasil`
- Upload de `.pfx`/`.p12` + senha (FormRequest valida `nfe.configuracao.manage` em `UploadCertificadoRequest::authorize`)
- Validação CNPJ titular do cert × CNPJ do business (com fallback se cert antigo sem `cnpj_titular`)
- StatusBadge tri-estado: `ok` (emerald), `proximo_vencimento` (≤30d, amber), `vencido` (negativo, red)
- Painel fiscal consolidado read-only: razão social, regime, NCM padrão, série NFe, próximo número, CFOP/CSOSN/CST defaults, UF/cidade
- Toggle ambiente SEFAZ (1=PRODUÇÃO / 2=HOMOLOGAÇÃO) via Inertia POST com `preserveScroll`
- Smoke "Testar SEFAZ" — fetch local (não Inertia, evita reload) chama `/nfe-brasil/configuracao/certificado/testar`, mostra `cstat`, `xMotivo`, `tempoResposta`, UF
- Toast feedback em todas mutações (sonner)
- Multi-tenant Tier 0: query `NfeCertificado::where('business_id', $businessId)` + global scope (ADR 0093)
- Storage cert encrypted em disco (CertificadoService — ADR satélite NfeBrasil/adr/arq/0003)

---

## Non-Goals — Features (NÃO faz)

> Anti-alucinação. Cada item vira Pest GUARD test (Non-Goal violado = CI quebra).

- ❌ Renovação automática do cert (cert vencendo é só alerta; renovação é manual fora desta tela)
- ❌ Multi-cert por business (1 ativo por vez, modelo único `NfeCertificado::where('ativo', true)`)
- ❌ Cert A3 (token/cartão físico) — só A1 nesta tela
- ❌ Backup/export do cert criptografado pelo client (storage backend é canon)
- ❌ Histórico de certs anteriores na própria tela (audit log via `activity_log`, não UI aqui)
- ❌ Compartilhar cert entre businesses (multi-tenant Tier 0 IRREVOGÁVEL)

---

## UX Targets

- p95 first-paint < 1500ms (cert + painel fiscal carregados via `montarPainelFiscal`)
- Smoke SEFAZ < 4000ms p95 (timeout do endpoint test SEFAZ)
- 0 erros JS console
- Cabe em monitor 1280px sem scroll horizontal (cliente ROTA LIVRE — embora ROTA LIVRE seja Modules/Vestuario, padrão se mantém)
- Tipografia canon ADR 0110: header 24px, body 14px, badge 12px
- Cores semânticas: emerald (cert OK), amber (vencendo ≤30d), red (vencido), sky (info painel fiscal)
- File input limpa após upload bem-sucedido (`fileRef.current.value = ''`)
- Toast só pra ações concluídas (não pra render); error pega 4xx/5xx
- Multi-tenant: cert é por business_id — nunca aparece cert de outro tenant

---

## UX Anti-patterns

- ❌ Modal pra mostrar status do cert (canon = inline na própria tela; modal só pra confirmar destrutivos)
- ❌ Mostrar senha do cert em plain text mesmo após upload (canon = senha vai pra storage e some da UI)
- ❌ Auto-upload em colar arquivo (gatilho explícito via botão Submit)
- ❌ Cor crua `bg-(green|red|yellow)-N` (canon = emerald/amber/red semântico ADR 0110)
- ❌ Loading spinner sem timeout (canon = `Loader2 animate-spin` com timeout 4s smoke + erro UI)
- ❌ Reload full da page após upload (canon = `preserveScroll: true` Inertia)
- ❌ Mostrar cert de outro tenant em listagem (multi-tenant Tier 0 — escopo `business_id` global)

---

## Automation Hooks

- `GET /nfe-brasil/configuracao/certificado` → `CertificadoController::status` (Inertia render com painel fiscal + cert info se houver)
- `POST /nfe-brasil/configuracao/certificado` → `CertificadoController::upload` (FormRequest valida arquivo+senha+permissão; service criptografa em disco)
- `POST /nfe-brasil/configuracao/certificado/testar` → smoke real SEFAZ via `NfeService` (consultaStatusServico CSTAT)
- `POST /nfe-brasil/configuracao/certificado/ambiente` → `CertificadoController::updateAmbiente` (toggle 1↔2; afeta todas emissões posteriores)
- Storage: cert criptografado em `storage/app/nfe-certs/{business_id}/{cert_id}.pfx.enc` (ADR satélite arq/0003)
- Multi-tenant: query usa `business_id` global scope; HasBusinessScope no `NfeCertificado` model
- Audit: `activity('nfe.certificado')->log()` em mutações (upload + updateAmbiente) — implementação via US-NFE-062 (P1)

---

## Automation Anti-hooks

> O que essa tela NUNCA dispara. Vira Pest GUARD.

- ❌ Não dispara emails ao abrir
- ❌ Não dispara emails no upload (apenas toast UI)
- ❌ Não escreve no banco no render inicial (só no POST)
- ❌ Não acessa cert de outro `business_id` (multi-tenant Tier 0)
- ❌ Não persiste senha do cert em plain text (encrypted storage obrigatório)
- ❌ Não chama SEFAZ no render (só no POST `/testar`)
- ❌ Não dispara emissão de NFe (essa tela é configuração, não operação)
- ❌ Não loga senha em log (sanitizar PII antes de qualquer Log::info)

---

## Métricas vivas (Pest GUARD — a escrever em F1.5)

```php
// Modules/NfeBrasil/Tests/Charters/CertificadoCharterTest.php

it('renders under 1500ms p95 with cert + painel fiscal')
it('does not emit emails on render or upload')
it('does not call SEFAZ on render (only on POST /testar)')
it('does not write to DB on render (only on POST upload+ambiente)')
it('isolates certs by business_id (cross-tenant 404)')
it('encrypts cert before disk write (storage path matches pattern)')
it('strips password from any Log entry')
it('renders at 1280px without horizontal scroll')
it('toggles ambiente 1↔2 with preserveScroll true')
it('smoke /testar respects 4s timeout')
```

---

## Refs

- [US-NFE-041](../../../../memory/requisitos/NfeBrasil/SPEC.md) — CertificadoService + storage encrypted + UI admin
- [ADR satélite NfeBrasil/arq/0003](../../../../memory/requisitos/NfeBrasil/adr/arq/0003-cert-a1-storage-criptografado.md) — storage criptografado
- [ADR 0029](../../../../memory/decisions/0029-inertia-upos.md) — Inertia + UltimatePOS
- [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR 0094](../../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2
- [RUNBOOK-smoke-sefaz.md](../../../../memory/requisitos/NfeBrasil/RUNBOOK-smoke-sefaz.md) — playbook smoke SEFAZ

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-10 | [CC] charter-write skill + [W] | Draft criado por US-NFE-061 (auditoria de completude module-completeness-audit). Wagner aprovou Non-Goals + Anti-hooks no mesmo dia → status:live. |
