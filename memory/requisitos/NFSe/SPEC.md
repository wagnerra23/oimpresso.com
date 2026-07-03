---
slug: modules-nfse-spec
title: "Modules/NFSe — SPEC"
type: spec
module: NFSe
status: ativo
authority: canonical
owner: "[E] Eliana"
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0153-module-grade-rubrica-v1
  - 0154-module-grade-v2-na-justificado
  - 0156-module-grade-v3-errata-otel-helper-na-justified
na_justified:
  D5: "Cliente único biz=1 oimpresso interno (empresa Wagner em Tubarão-SC) — NÃO ROTA LIVRE/Larissa biz=4. Módulo standalone single-tenant intencional [adr/arq/0001-cliente-oimpresso-modulo-standalone.md](adr/arq/0001-cliente-oimpresso-modulo-standalone.md) — sem cliente externo qualificado [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)."
pii: false
version: "1.0.0"
last_updated: "2026-06-03"
updated_at: 2026-05-16
---

# NFSe — SPEC + Lista de tarefas

> **Status**: Sprint A concluída · Sprint B em progresso (2026-05-01) · US-001 ✅ · US-002 ✅ · US-003 ✅ · US-004 ✅ parcial (Service+Adapter stub)
> **Owner**: Eliana[E]
> **Paralelo a**: Cycle 01 (foco Copiloto/Larissa) — não bloqueia
> **Cliente**: oimpresso (empresa Wagner) — **NÃO** ROTA LIVRE
> **Cidade**: Tubarão-SC — **SN-NFSe federal** desde 01/01/2026 ([pesquisa](PESQUISA_TUBARAO.md))
> **Decisão arquitetural**: [ADR ARQ-0001](adr/arq/0001-cliente-oimpresso-modulo-standalone.md)

---

## Objetivo

Permitir que a empresa **oimpresso** emita NFSe da **prefeitura de Tubarão-SC** (Sistema Nacional NFSe ou ABRASF municipal — confirmar) via tela própria, com integração opcional ao módulo de **recurring invoice nativo do UltimatePOS** (não usar `Modules/RecurringBilling/`).

**Marco de sucesso**: 1 NFSe emitida em produção (real, não sandbox), validada na prefeitura, com PDF DANFSE imprimível, dentro de 4-5 semanas.

---

## Pré-requisitos fora do código (Wagner ou Eliana resolve)

| Item | Owner | Bloqueante? |
|---|---|---|
| Certificado A1 (.pfx) válido da oimpresso | Wagner (assinar com contador) | 🔴 sim — sem cert não emite |
| CNPJ + IE + IM oimpresso registrados na prefeitura Tubarão | Wagner | 🔴 sim |
| Regime tributário definido (Simples Nacional / Real / Presumido) | Wagner + contador | 🔴 sim |
| CNAE + código serviço LC 116/2003 (ex.: `1.05` ou `1.07` p/ software) | Eliana + contador | 🔴 sim |
| Alíquota ISS Tubarão-SC | Eliana (consulta lei mun.) | 🔴 sim |
| Conta provider (Focus NFe / NFE.io / PlugNotas) ou direto SN-NFSe | Wagner libera $$ | 🟡 depende de US-NFSE-001 |

---

## US ativas — tasks Eliana (US-NFSE-NNN)

Capacidade Eliana: 2-4h/dia → estimativa em **dias úteis efetivos** (não calendário).

### Sprint A — Pesquisa + setup (3 dias)

### US-NFSE-001 · Pesquisa fiscal Tubarão

> owner: eliana · sprint: A · priority: p0 · estimate: 8h · status: done
> blocked_by: —

**Implementado em:** `memory/requisitos/NFSe/PESQUISA_TUBARAO.md` · verificado@0bb65dd (2026-07-02) — pesquisa fiscal (decisão SN-NFSe federal); coleta de dados fiscais oimpresso segue com contador

✅ **concluída 2026-04-30** — decisão: SN-NFSe federal direto, sem provider terceiro.

- [x] Confirmar se Tubarão-SC está no **Sistema Nacional NFSe** (SN-NFSe federal LC 214/2025) ou ainda no **ABRASF municipal** próprio → ✅ **SN-NFSe federal** desde 01/01/2026
- [x] Library PHP escolhida → `nfse-nacional/nfse-php` (Packagist)
- [x] Auth method definido → cert A1 (.pfx)
- [x] Cód LC 116 mapeado → 1.05 (licenciamento) + 1.07 (suporte)
- [ ] Coletar dados fiscais oimpresso completos (CNPJ/IE/IM/regime/CNAE/alíquota ISS Tubarão) — **owner: Eliana** confirmar com contador
- [x] Documentar resultado em [`PESQUISA_TUBARAO.md`](PESQUISA_TUBARAO.md)
- **Output**: ✅ **decisão = SN-NFSe direto** (sem provider terceiro, custo zero per-emissão)

### US-NFSE-002 · Setup composer + .env

> owner: eliana · sprint: A · priority: p1 · estimate: 4h · status: done
> blocked_by: US-NFSE-001

**Implementado em:** `Modules/NFSe/module.json` · `Modules/NFSe/composer.json` · `Modules/NFSe/Config/config.php` · verificado@0bb65dd (2026-07-02)

✅ **concluída 2026-05-01** — scaffold `Modules/NFSe/` criado, dep adicionada ao `composer.json`, vars no `.env - Copia.example`.

- [x] `composer require nfse-nacional/nfse-php ^1.19` adicionado ao `composer.json` raiz — Wagner roda `composer update` localmente e no Hostinger
- [x] `.env - Copia.example`: `NFSE_AMBIENTE=homologacao`, `NFSE_CERT_PATH=storage/certs/oimpresso.pfx`, `NFSE_CERT_SENHA=`, `NFSE_MUNICIPIO_IBGE=4218707`
- [x] Endpoints configurados em `Modules/NFSe/Config/config.php`: sandbox `https://sefin.producaorestrita.nfse.gov.br` · prod `https://sefin.nfse.gov.br`
- [x] Scaffold completo: `module.json`, `Providers/`, `Routes/web.php` + `Routes/api.php`, `Http/Controllers/NfseController.php` (stub 501), `Resources/menus/topnav.php`, `README.md`

### US-NFSE-003 · Migrations base

> owner: eliana · sprint: A · priority: p1 · estimate: 8h · status: done
> blocked_by: US-NFSE-002

**Implementado em:** `Modules/NFSe/Database/Migrations/2026_05_01_000001_create_nfe_certificados_table.php` · `Modules/NFSe/Database/Migrations/2026_05_01_000002_create_nfse_provider_configs_table.php` · `Modules/NFSe/Database/Migrations/2026_05_01_000003_create_nfse_emissoes_table.php` · `Modules/NFSe/Database/Seeders/NfseSeeder.php` · verificado@0bb65dd (2026-07-02)

✅ **concluída 2026-05-01** — 3 migrations + NfseSeeder com dados Tubarão (IBGE 4218707).

- [x] `nfe_certificados` — `cert_pfx_encrypted`, `senha_encrypted`, `valido_ate`, `titular_cnpj/nome`
- [x] `nfse_emissoes` — `status` (5 valores), `idempotency_key`, `xml_envio/retorno`, `pdf_url`, vínculo `recurring_invoice_id`
- [x] `nfse_provider_configs` — `provider`, `municipio_codigo_ibge`, `serie_default`, `cnae`, `lc116_codigo_default`, `aliquota_iss`, `ambiente`, `cert_id`
- [x] `NfseSeeder` — seeds config oimpresso: IBGE 4218707, CNAE 6201-5/00, LC 116 → 1.05, ambiente homologação

### Sprint B — Backend (3-4 dias)

### US-NFSE-004 · Adapter + Service

> owner: eliana · sprint: B · priority: p1 · estimate: 12h · status: done
> blocked_by: US-NFSE-003

**Implementado em:** `Modules/NFSe/Contracts/NfseProviderInterface.php` · `Modules/NFSe/Adapters/SnNfseAdapter.php` · `Modules/NFSe/DTO/NfseEmissaoPayload.php` · `Modules/NFSe/DTO/NfseResultado.php` · `Modules/NFSe/Services/NfseEmissaoService.php` · verificado@0bb65dd (2026-07-02)

✅ **concluída 2026-05-01** — ver ADR TECH-0001 e TECH-0002.

- [x] `NfseProviderInterface` (3 métodos: `emitir/consultar/cancelar`)
- [x] `SnNfseAdapter` — HTTP direto ao SN-NFSe (lib `nfse-nacional/nfse-php` integra quando ADR 0062 split composer.json)
- [x] DTOs imutáveis: `NfseEmissaoPayload` + `NfseResultado`
- [x] `NfseEmissaoService` — idempotência SHA256 + retry 3x backoff + 9 exceções tipadas PT-BR
- [x] **13 testes Pest** cobrindo: golden path, idempotência, cert inválido, cert expirado, RPS duplicado, ISS E501, serviço inválido, tomador inválido, prestador não autorizado L1, timeout retry, cancelamento, já cancelada, config ausente, cálculo ISS
- [x] Adapter pattern preserva flexibilidade pra ABRASF futuro

### US-NFSE-005 · Job assíncrono

> owner: eliana · sprint: B · priority: p1 · estimate: 4h · status: done
> blocked_by: US-NFSE-004

**Implementado em:** `Modules/NFSe/Jobs/EmitirNfseJob.php` · `EmitirNfseJob@handle` · verificado@0bb65dd (2026-07-02) — SPEC ainda não marcada done, mas Job existe com tries=3/backoff + idempotência via `NfseEmissaoService::montarPayload`

- [ ] `EmitirNfseJob` (queue `nfse` separada — não bloqueia outras filas)
- [ ] Retry policy: 3 tentativas com backoff exponencial
- [ ] Idempotência: `idempotency_key = hash(business_id + tomador + valor + descricao + data)`

### US-NFSE-006 · HTTP Controller + rotas

> owner: eliana · sprint: B · priority: p1 · estimate: 4h · status: done
> blocked_by: US-NFSE-004

**Implementado em:** `Modules/NFSe/Http/Controllers/NfseController.php` · `Modules/NFSe/Routes/web.php` · `Modules/NFSe/Http/Controllers/DataController.php` · verificado@0bb65dd (2026-07-02) — controller vivo (rotas index, create, store, show, cancelar, pdf) + permissions nfse.emit, nfse.cancel, nfse.view no DataController

- [ ] `POST /nfse/emitir` (cria registro + dispara job)
- [ ] `GET /nfse/{id}` (detalhe + status)
- [ ] `POST /nfse/{id}/cancelar` (motivo)
- [ ] `GET /nfse/{id}/pdf` (proxy PDF do provider)
- [ ] Spatie permissions: `nfse.emit`, `nfse.cancel`, `nfse.view`

### US-NFSE-007 · Bridge recurring nativo UPOS

> owner: eliana · sprint: B · priority: p2 · estimate: 8h · status: done
> blocked_by: US-NFSE-005

**Implementado em:** _parcial_ · `Modules/NFSe/Observers/TransactionNfseObserver.php` · verificado@0bb65dd (2026-07-02) — observer cria rascunho NFSe no recurring invoice; falta mapeamento item→LC 116 por produto e botão "Emitir NFSe" no detalhe do recurring (legacy Blade)

🟡 opcional Sprint B (pode adiar pra Sprint D se travar).

- [ ] Listener no evento de geração de `recurring_invoice` UPOS → cria NFSe `rascunho`
- [ ] Mapeamento item venda → código serviço LC 116 (config no produto)
- [ ] Botão "Emitir NFSe" no detalhe do recurring invoice (legacy Blade)

### Sprint C — UI Inertia/React (3 dias)

### US-NFSE-008 · Pages/Nfse/Index.tsx

> owner: eliana · sprint: C · priority: p1 · estimate: 8h · status: done
> blocked_by: US-NFSE-006

**Implementado em:** `resources/js/Pages/Nfse/Index.tsx` · `Modules/NFSe/Http/Controllers/NfseController.php` · `NfseController@index` · verificado@0bb65dd (2026-07-02) — Page renderizada por controller vivo (Inertia render Nfse/Index no NfseController@index)

- [ ] AppShellV2 + breadcrumb `[{ label: 'Fiscal' }, { label: 'NFSe' }]`
- [ ] DataTable com colunas: número, data, tomador, valor, status (StatusBadge), ações
- [ ] PageFilters: status (rascunho/processando/emitida/cancelada/erro), período
- [ ] EmptyState "Nenhuma NFSe emitida ainda"
- [ ] Componentes shared (PageHeader, KpiGrid, DataTable, StatusBadge)

### US-NFSE-009 · Pages/Nfse/Emitir.tsx

> owner: eliana · sprint: C · priority: p1 · estimate: 8h · status: done
> blocked_by: US-NFSE-006

**Implementado em:** `resources/js/Pages/Nfse/Emitir.tsx` · `Modules/NFSe/Http/Controllers/NfseController.php` · `NfseController@create` · `Modules/NFSe/Http/Requests/StoreNfseRequest.php` · verificado@0bb65dd (2026-07-02) — Page renderizada por controller vivo (Inertia render Nfse/Emitir no NfseController@create); submit em NfseController@store

- [ ] Form: tomador (CNPJ ou CPF + razão social + endereço), serviço (descrição + cód LC 116 + valor), retenções opcionais
- [ ] Auto-preenchimento por busca de cliente (autocomplete contacts UPOS)
- [ ] Submit → POST /nfse/emitir → toast "NFSe sendo processada" + redirect lista
- [ ] Validação react-hook-form + zod

### US-NFSE-010 · Action "Imprimir DANFSE"

> owner: eliana · sprint: C · priority: p2 · estimate: 4h · status: done
> blocked_by: US-NFSE-008

**Implementado em:** `resources/js/Pages/Nfse/Show.tsx` · `resources/js/Pages/Nfse/Index.tsx` · `Modules/NFSe/Http/Controllers/NfseController.php` · `NfseController@pdf` · verificado@0bb65dd (2026-07-02) — botão "Baixar DANFSE" (abre `/nfse/{id}/pdf`) + ação "Cancelar nota" via `NfseController@cancelar`

- [ ] Botão na linha de cada NFSe emitida → abre PDF em nova aba
- [ ] Fallback: download se provider só dá base64
- [ ] Action "Cancelar" com modal motivo (NFSe ainda no prazo legal de cancelamento)

### Sprint D — Validação + produção (2-3 dias)

### US-NFSE-011 · Testes Pest end-to-end

**Implementado em:** _parcial_ · `Modules/NFSe/Tests/Feature/AmbientePorBusinessTest.php` · `Modules/NFSe/Tests/Feature/MultiTenantIsolationTest.php` · `Modules/NFSe/Tests/Feature/SmokeRoutesTest.php` · verificado@0bb65dd (2026-07-02) — cobertura de isolamento/ambiente/smoke + 13 testes de Service (US-004); falta golden test único criar→emitir→consultar→cancelar e meta explícita 80% linhas do NfseEmissaoService

> owner: eliana · sprint: D · priority: p1 · estimate: 8h · status: done
> blocked_by: US-NFSE-009

- [ ] Golden test: criar → emitir → consultar → cancelar
- [ ] Mock provider Focus/SN-NFSe
- [ ] Cobertura: idempotência, retry, contingência (provider down)
- [ ] Coverage mínimo: 80% das linhas do `NfseEmissaoService`

### US-NFSE-012 · Deploy sandbox

> owner: eliana · sprint: D · priority: p1 · estimate: 4h · status: todo
> blocked_by: US-NFSE-011

**Implementado em:** _pendente_ — deploy/smoke sandbox (tarefa operacional humano-limitada, sem artefato de código)

- [ ] Subir migrations no Hostinger
- [ ] `.env` produção com cert + token sandbox
- [ ] Smoke test: emitir 1 NFSe sandbox + verificar XML/PDF
- [ ] Validar que prefeitura Tubarão aceita o XML

### US-NFSE-013 · Deploy produção real

> owner: eliana · sprint: D · priority: p0 · estimate: 4h · status: todo
> blocked_by: US-NFSE-012

**Implementado em:** _pendente_ — deploy produção real + emissão de 1 NFSe real (tarefa operacional humano-limitada, sem artefato de código)

🔴 marco de sucesso da SPEC inteira.

- [ ] Trocar token sandbox → produção
- [ ] Cert A1 produção (vault encrypted)
- [ ] Emitir **1 NFSe real de teste** (cliente fake oimpresso pra oimpresso, valor mínimo)
- [ ] Confirmar com contador/prefeitura
- [ ] Documentar em session log + Eliana ganha lap

### US-NFSE-014 · Rollout pros clientes oimpresso

> owner: eliana · sprint: D · priority: p2 · estimate: 8h · status: todo
> blocked_by: US-NFSE-013

**Implementado em:** _pendente_ — tela `/nfse/config` não construída (sem rota nem Page); config hoje só via seeder/DB

- [ ] Tela de configuração `/nfse/config` (provider + cert + dados fiscais por business)
- [ ] Permission gating (só superadmin oimpresso libera)
- [ ] **ROTA LIVRE permanece OFF** (config flag `nfse_habilitado=false` no business 4)

---

### US-NFSE-015 · Ambiente per-business em consultar()/cancelar() do SnNfseAdapter

> owner: eliana · priority: p2 · status: todo · type: story
> blocked_by: —

**Implementado em:** _pendente_ — `SnNfseAdapter::consultar(string $protocolo)`/`cancelar(string $numero, string $motivo)` ainda usam ambiente do bind global; só a emissão resolve per-business (via `$payload->ambiente`)

Follow-up do cutover fiscal Martinho (biz=164, PR #2147 merge `77ced51`).

A EMISSÃO NFS-e já resolve ambiente per-business (`$payload->ambiente` ← `NfseProviderConfig.ambiente`). Falta alinhar `SnNfseAdapter::consultar()` e `cancelar()`, que ainda usam o ambiente do **bind global** (`config('nfse.ambiente')`) porque recebem só strings (`$protocolo`/`$numero`), sem contexto de business.

**Risco:** depois que um business vai pra produção, um cancelamento/consulta cai no endpoint errado (homolog) e falha. Hoje NÃO é regressão (já eram globais), mas vira bug quando biz=164 estiver em prod.

**Aceite:**
- `NfseProviderInterface::consultar/cancelar` recebem o ambiente do tenant (via param ou resolvendo `NfseProviderConfig` por `business_id` da `NfseEmissao`).
- `NfseEmissaoService::cancelar(NfseEmissao $emissao)` passa o ambiente correto.
- Teste Pest cobrindo cancelar em prod vs homolog (Http::fake, igual `AmbientePorBusinessTest`).

Refs: PR #2147, `Modules/NFSe/Adapters/SnNfseAdapter.php`, `prototipo-ui/CODE_NOTES.md` (2026-06-03). Escopo "PR separado" conforme prompt do cutover.

---

## Total estimado

| Sprint | Dias úteis efetivos | Calendário (2-4h/dia) |
|---|---|---|
| A — Pesquisa+setup | 2.5 | 1 semana |
| B — Backend | 3-4 | 1-2 semanas |
| C — UI | 2.5 | 1 semana |
| D — Validação+prod | 2 | 1 semana |
| **TOTAL** | **10-11 dias úteis** | **~4-5 semanas** |

---

## Bloqueios conhecidos

1. ~~**Pesquisa Tubarão** (US-001)~~ ✅ resolvido 2026-04-30 — SN-NFSe federal, custo zero per-emissão, lib `nfse-nacional/nfse-php`
2. **Cert A1** depende de Wagner liberar com contador
3. **Cód serviço LC 116** ✅ mapeado: `1.05` (licenciamento) + `1.07` (suporte) — contador valida
4. **Alíquota ISS Tubarão** pra cód 1.05/1.07 — Eliana confirma com `fazenda@tubarao.sc.gov.br` ou contador

## Não-objetivos do MVP (postpone)

- Emissão em lote (múltiplas NFSe num único request)
- Carta de correção
- Substitutiva
- Importação XML de outras NFSe
- Multi-tenant complexo (foco oimpresso primeiro, depois opcional pros clientes ERP)
- Integração com `Modules/RecurringBilling/` (que nem existe)

---

## Refs

- [ADR ARQ-0001 (NFSe)](adr/arq/0001-cliente-oimpresso-modulo-standalone.md)
- TEAM.md → Eliana[E]
- ADR-0002 RecurringBilling (parcialmente superseded)
- LC 116/2003 (lista de serviços)
- LC 214/2025 (NFSe Nacional federal)
- https://www.gov.br/nfse (Sistema Nacional NFSe)
- Tubarão-SC ISSQN https://www.tubarao.sc.gov.br

---

## Backlog vindo do Capterra-Inventário (2026-07-03)

> Gerado via `/comparativo NFSe` cruzando [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) (nota 45/100) + código real. Aprovado por Wagner 2026-07-03 ("autorizo"). Tasks criadas no MCP. Contexto: Wagner confirmou **intenção de emitir NFSe real antes de 15/07/2026** → US-NFSE-016 (DANFSe NT 008/2026) é P0 urgente.
>
> Também re-priorizadas (DB, ADR 0144): **US-NFSE-014** p2→p1 (config UI self-service) · **US-NFSE-015** p2→p1 (bug latente ambiente per-business no cancelar/consultar). **US-NFSE-013** (marco emitir real) mantém p0; ordem crítica **016 → 017 → 013**.

### US-NFSE-016 · Geração própria do DANFSe conforme NT 008/2026

> owner: eliana · priority: p0 · estimate: 12h · status: todo · type: story
> blocked_by: —

🔴 **Prazo regulatório LIVE: 15/07/2026** (NT 008/2026, SE/CGNFS-e — prorrogado de 01/07). A API do ADN que gera o DANFSe é descontinuada; a responsabilidade de gerar o DANFSe (PDF) passa ao **sistema emissor**. Hoje o oimpresso NÃO gera — depende de `data['urlDanfse']` do provider (`SnNfseAdapter.php:71`, `NfseController:261` só proxia). Gap C05 da CAPTERRA-FICHA (nota 1/10).

**Aceite:**
- [ ] Gerar DANFSe (PDF) conforme layout único da NT 008/2026 (estrutura, campos, QR Code, informações tributárias, identificação da operação).
- [ ] Substituir o proxy de `urlDanfse` por geração local (ou fallback local quando provider não retornar URL).
- [ ] Pest cobrindo render do PDF a partir de uma emissão mockada.
- [ ] Smoke: DANFSe de 1 NFSe (homologação) abre e bate o leiaute.

**Origem:** gap detectado pelo /comparativo em 2026-07-03 (parent_audit: CAPTERRA-FICHA NFSe). Refs: [FICHA §8 achado 4](CAPTERRA-FICHA.md), NT 008/2026 gov.br/nfse. labels: capterra-gap, from-skill, regulatorio-urgente

### US-NFSE-017 · Validar DPS contra leiaute RTC v2.00 / integrar nfse-nacional/nfse-php

> owner: eliana · priority: p0 · estimate: 8h · status: todo · type: story
> blocked_by: —

Pré-requisito da 1ª emissão real (US-NFSE-013). O `SnNfseAdapter::buildDps()` monta um `infDps` **simplificado à mão** (comentário diz "TODO US-NFSE-004: integrar lib nfse-nacional/nfse-php"). Os 13 Pest passam com `Http::fake` — NÃO validam o payload contra o XSD/leiaute oficial. Um DPS incompleto é rejeitado pela SEFIN na 1ª emissão real. Gap C01 (nota 5/10, PARCIAL).

**Aceite:**
- [ ] Validar o DPS gerado contra o leiaute RTC v2.00 (NT 004/SE-CGNFSe) — campos obrigatórios, tipos, tribMun.
- [ ] Se o hand-rolled não casar: integrar `nfse-nacional/nfse-php` (respeitando split composer.json ADR 0062).
- [ ] Emissão em produção-restrita retorna sucesso (não rejeição de schema).

**Origem:** /comparativo 2026-07-03 (CAPTERRA-FICHA §8 achado 3). Refs: `Modules/NFSe/Adapters/SnNfseAdapter.php:22,106`. labels: capterra-gap, from-skill

### US-NFSE-018 · Substituição de NFSe (evento, mantém vínculo original↔nova)

> owner: eliana · priority: p1 · estimate: 8h · status: todo · type: story
> blocked_by: —

Gap C09 (nota 0/10, AUSENTE). O padrão nacional tem o evento de "cancelamento por substituição" — corrige uma nota emitida mantendo o vínculo original↔nova (janela: emissão original ≤730 dias / ≤6 meses do fato gerador). Concorrentes (Focus/PlugNotas/SP) têm. Hoje só cancela — grep 0 matches.

**Aceite:**
- [ ] Emitir NFSe substituta vinculada à original (evento no padrão nacional).
- [ ] Validar janela legal antes de permitir.
- [ ] UI na `Nfse/Show` (ação "Substituir") + persistência do vínculo.
- [ ] Pest cobrindo substituição dentro/fora do prazo.

**Origem:** /comparativo 2026-07-03. Refs: CAPTERRA-FICHA §4 C09. labels: capterra-gap, from-skill

### US-NFSE-019 · Webhook/callback assíncrono de eventos SEFIN

> owner: eliana · priority: p1 · estimate: 10h · status: todo · type: story
> blocked_by: —

Gap C10 (nota 1/10, AUSENTE). Hoje o status só atualiza via polling (`consultar()`); não há callback assíncrono quando a nota é autorizada/rejeitada/cancelada. `retention.php` já prevê log `webhook_municipal` (365d) mas não existe dispatcher/receiver. Concorrentes (Focus/eNotas/Notaas) entregam webhook até no plano free.

**Aceite:**
- [ ] Receiver de callback de eventos SEFIN/ADN → atualiza `nfse_emissoes.status` sem polling.
- [ ] (Opcional) WebhookDispatcher pra notificar sistemas do tenant (assinatura HMAC + retry backoff).
- [ ] business_id sempre explícito (job/fila — ADR 0093).
- [ ] Pest com payload de evento mockado.

**Origem:** /comparativo 2026-07-03. Refs: CAPTERRA-FICHA §4 C10, `Config/retention.php:63`. labels: capterra-gap, from-skill

### US-NFSE-020 · Alerta proativo de certificado A1 vencendo (cron D-30/D-7/D-1)

> owner: eliana · priority: p2 · estimate: 2h · status: todo · type: story
> blocked_by: —

Gap C18 (nota 4/10, PARCIAL). O `NfseHealthCommand::checkCertVencimento():144` já detecta cert vencendo em 30d (WARN), mas é health-check CLI — não notifica o usuário. Cert A1 vence anual; sem alerta, a emissão para sem aviso (risk 🔴 do BRIEFING).

**Aceite:**
- [ ] Cron (Console/Kernel) reaproveita `checkCertVencimento` e dispara alerta D-30/D-7/D-1.
- [ ] Notificação via canal existente (mcp_alertas / email / Whatsapp conforme padrão).
- [ ] Multi-tenant: alerta por business com cert próximo do vencimento.

**Origem:** /comparativo 2026-07-03. Refs: `Modules/NFSe/Console/Commands/NfseHealthCommand.php:144`. labels: capterra-gap, from-skill

### US-NFSE-021 · Dashboard de métricas NFSe (volume/erro/ISS pago)

> owner: eliana · priority: p2 · estimate: 6h · status: todo · type: story
> blocked_by: —

Gap C16 (nota 2/10, AUSENTE). Concorrentes têm painel/relatórios de emissão. Hoje há só `NfseHealthCommand` (health CLI), sem dashboard. BRIEFING gap #3.

**Aceite:**
- [ ] Página/seção com KPIs: volume emitido, taxa de erro, ISS apurado por competência.
- [ ] `Inertia::defer` nas props agregadas (skill inertia-defer-default).
- [ ] Multi-tenant scope (business_id).

**Origem:** /comparativo 2026-07-03. Refs: CAPTERRA-FICHA §4 C16. labels: capterra-gap, from-skill

### US-NFSE-022 · Carta de correção (campo discriminação dos serviços)

> owner: eliana · priority: p2 · estimate: 6h · status: todo · type: story
> blocked_by: —

Gap C14 (nota 0/10, AUSENTE). No padrão nacional/municipal a carta de correção regulariza erro/omissão apenas no campo DISCRIMINAÇÃO DOS SERVIÇOS (não altera valor/tomador). Concorrentes (Focus/SP) têm. Hoje ausente (grep 0 matches).

**Aceite:**
- [ ] Emitir carta de correção (evento) para o campo discriminação de uma NFSe emitida.
- [ ] Validar que só o campo permitido é alterado.
- [ ] UI + persistência do evento + Pest.

**Origem:** /comparativo 2026-07-03. Refs: CAPTERRA-FICHA §4 C14. labels: capterra-gap, from-skill
