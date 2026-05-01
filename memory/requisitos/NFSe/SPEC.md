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

## Lista de tasks Eliana (US-NFSE-NNN)

Capacidade Eliana: 2-4h/dia → estimativa em **dias úteis efetivos** (não calendário).

### Sprint A — Pesquisa + setup (3 dias)

### US-NFSE-001 · Pesquisa fiscal Tubarão

> owner: eliana · sprint: A · priority: p0 · estimate: 8h · status: done
> blocked_by: —

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

✅ **concluída 2026-05-01** — scaffold `Modules/NFSe/` criado, dep adicionada ao `composer.json`, vars no `.env - Copia.example`.

- [x] `composer require nfse-nacional/nfse-php ^1.19` adicionado ao `composer.json` raiz — Wagner roda `composer update` localmente e no Hostinger
- [x] `.env - Copia.example`: `NFSE_AMBIENTE=homologacao`, `NFSE_CERT_PATH=storage/certs/oimpresso.pfx`, `NFSE_CERT_SENHA=`, `NFSE_MUNICIPIO_IBGE=4218707`
- [x] Endpoints configurados em `Modules/NFSe/Config/config.php`: sandbox `https://sefin.producaorestrita.nfse.gov.br` · prod `https://sefin.nfse.gov.br`
- [x] Scaffold completo: `module.json`, `Providers/`, `Routes/web.php` + `Routes/api.php`, `Http/Controllers/NfseController.php` (stub 501), `Resources/menus/topnav.php`, `README.md`

### US-NFSE-003 · Migrations base

> owner: eliana · sprint: A · priority: p1 · estimate: 8h · status: done
> blocked_by: US-NFSE-002

✅ **concluída 2026-05-01** — 3 migrations + NfseSeeder com dados Tubarão (IBGE 4218707).

- [x] `nfe_certificados` — `cert_pfx_encrypted`, `senha_encrypted`, `valido_ate`, `titular_cnpj/nome`
- [x] `nfse_emissoes` — `status` (5 valores), `idempotency_key`, `xml_envio/retorno`, `pdf_url`, vínculo `recurring_invoice_id`
- [x] `nfse_provider_configs` — `provider`, `municipio_codigo_ibge`, `serie_default`, `cnae`, `lc116_codigo_default`, `aliquota_iss`, `ambiente`, `cert_id`
- [x] `NfseSeeder` — seeds config oimpresso: IBGE 4218707, CNAE 6201-5/00, LC 116 → 1.05, ambiente homologação

### Sprint B — Backend (3-4 dias)

### US-NFSE-004 · Adapter + Service

> owner: eliana · sprint: B · priority: p1 · estimate: 12h · status: done
> blocked_by: US-NFSE-003

✅ **concluída 2026-05-01** — ver ADR TECH-0001 e TECH-0002.

- [x] `NfseProviderInterface` (3 métodos: `emitir/consultar/cancelar`)
- [x] `SnNfseAdapter` — HTTP direto ao SN-NFSe (lib `nfse-nacional/nfse-php` integra quando ADR 0062 split composer.json)
- [x] DTOs imutáveis: `NfseEmissaoPayload` + `NfseResultado`
- [x] `NfseEmissaoService` — idempotência SHA256 + retry 3x backoff + 9 exceções tipadas PT-BR
- [x] **13 testes Pest** cobrindo: golden path, idempotência, cert inválido, cert expirado, RPS duplicado, ISS E501, serviço inválido, tomador inválido, prestador não autorizado L1, timeout retry, cancelamento, já cancelada, config ausente, cálculo ISS
- [x] Adapter pattern preserva flexibilidade pra ABRASF futuro

### US-NFSE-005 · Job assíncrono

> owner: eliana · sprint: B · priority: p1 · estimate: 4h · status: todo
> blocked_by: US-NFSE-004

- [ ] `EmitirNfseJob` (queue `nfse` separada — não bloqueia outras filas)
- [ ] Retry policy: 3 tentativas com backoff exponencial
- [ ] Idempotência: `idempotency_key = hash(business_id + tomador + valor + descricao + data)`

### US-NFSE-006 · HTTP Controller + rotas

> owner: eliana · sprint: B · priority: p1 · estimate: 4h · status: todo
> blocked_by: US-NFSE-004

- [ ] `POST /nfse/emitir` (cria registro + dispara job)
- [ ] `GET /nfse/{id}` (detalhe + status)
- [ ] `POST /nfse/{id}/cancelar` (motivo)
- [ ] `GET /nfse/{id}/pdf` (proxy PDF do provider)
- [ ] Spatie permissions: `nfse.emit`, `nfse.cancel`, `nfse.view`

### US-NFSE-007 · Bridge recurring nativo UPOS

> owner: eliana · sprint: B · priority: p2 · estimate: 8h · status: todo
> blocked_by: US-NFSE-005

🟡 opcional Sprint B (pode adiar pra Sprint D se travar).

- [ ] Listener no evento de geração de `recurring_invoice` UPOS → cria NFSe `rascunho`
- [ ] Mapeamento item venda → código serviço LC 116 (config no produto)
- [ ] Botão "Emitir NFSe" no detalhe do recurring invoice (legacy Blade)

### Sprint C — UI Inertia/React (3 dias)

### US-NFSE-008 · Pages/Nfse/Index.tsx

> owner: eliana · sprint: C · priority: p1 · estimate: 8h · status: todo
> blocked_by: US-NFSE-006

- [ ] AppShellV2 + breadcrumb `[{ label: 'Fiscal' }, { label: 'NFSe' }]`
- [ ] DataTable com colunas: número, data, tomador, valor, status (StatusBadge), ações
- [ ] PageFilters: status (rascunho/processando/emitida/cancelada/erro), período
- [ ] EmptyState "Nenhuma NFSe emitida ainda"
- [ ] Componentes shared (PageHeader, KpiGrid, DataTable, StatusBadge)

### US-NFSE-009 · Pages/Nfse/Emitir.tsx

> owner: eliana · sprint: C · priority: p1 · estimate: 8h · status: todo
> blocked_by: US-NFSE-006

- [ ] Form: tomador (CNPJ ou CPF + razão social + endereço), serviço (descrição + cód LC 116 + valor), retenções opcionais
- [ ] Auto-preenchimento por busca de cliente (autocomplete contacts UPOS)
- [ ] Submit → POST /nfse/emitir → toast "NFSe sendo processada" + redirect lista
- [ ] Validação react-hook-form + zod

### US-NFSE-010 · Action "Imprimir DANFSE"

> owner: eliana · sprint: C · priority: p2 · estimate: 4h · status: todo
> blocked_by: US-NFSE-008

- [ ] Botão na linha de cada NFSe emitida → abre PDF em nova aba
- [ ] Fallback: download se provider só dá base64
- [ ] Action "Cancelar" com modal motivo (NFSe ainda no prazo legal de cancelamento)

### Sprint D — Validação + produção (2-3 dias)

### US-NFSE-011 · Testes Pest end-to-end

> owner: eliana · sprint: D · priority: p1 · estimate: 8h · status: todo
> blocked_by: US-NFSE-009

- [ ] Golden test: criar → emitir → consultar → cancelar
- [ ] Mock provider Focus/SN-NFSe
- [ ] Cobertura: idempotência, retry, contingência (provider down)
- [ ] Coverage mínimo: 80% das linhas do `NfseEmissaoService`

### US-NFSE-012 · Deploy sandbox

> owner: eliana · sprint: D · priority: p1 · estimate: 4h · status: todo
> blocked_by: US-NFSE-011

- [ ] Subir migrations no Hostinger
- [ ] `.env` produção com cert + token sandbox
- [ ] Smoke test: emitir 1 NFSe sandbox + verificar XML/PDF
- [ ] Validar que prefeitura Tubarão aceita o XML

### US-NFSE-013 · Deploy produção real

> owner: eliana · sprint: D · priority: p0 · estimate: 4h · status: todo
> blocked_by: US-NFSE-012

🔴 marco de sucesso da SPEC inteira.

- [ ] Trocar token sandbox → produção
- [ ] Cert A1 produção (vault encrypted)
- [ ] Emitir **1 NFSe real de teste** (cliente fake oimpresso pra oimpresso, valor mínimo)
- [ ] Confirmar com contador/prefeitura
- [ ] Documentar em session log + Eliana ganha lap

### US-NFSE-014 · Rollout pros clientes oimpresso

> owner: eliana · sprint: D · priority: p2 · estimate: 8h · status: todo
> blocked_by: US-NFSE-013

- [ ] Tela de configuração `/nfse/config` (provider + cert + dados fiscais por business)
- [ ] Permission gating (só superadmin oimpresso libera)
- [ ] **ROTA LIVRE permanece OFF** (config flag `nfse_habilitado=false` no business 4)

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
