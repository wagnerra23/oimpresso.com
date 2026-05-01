# NFSe — SPEC + Lista de tarefas

> **Status**: planejamento (2026-04-30) · US-001 ✅ concluída
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

#### US-NFSE-001 · Pesquisa fiscal Tubarão (1d) ✅ **concluída 2026-04-30**
- [x] Confirmar se Tubarão-SC está no **Sistema Nacional NFSe** (SN-NFSe federal LC 214/2025) ou ainda no **ABRASF municipal** próprio → ✅ **SN-NFSe federal** desde 01/01/2026
- [x] Library PHP escolhida → `nfse-nacional/nfse-php` (Packagist)
- [x] Auth method definido → cert A1 (.pfx)
- [x] Cód LC 116 mapeado → 1.05 (licenciamento) + 1.07 (suporte)
- [ ] Coletar dados fiscais oimpresso completos (CNPJ/IE/IM/regime/CNAE/alíquota ISS Tubarão) — **owner: Eliana** confirmar com contador
- [x] Documentar resultado em [`PESQUISA_TUBARAO.md`](PESQUISA_TUBARAO.md)
- **Output**: ✅ **decisão = SN-NFSe direto** (sem provider terceiro, custo zero per-emissão)

#### US-NFSE-002 · Setup composer + .env (0.5d)
- [ ] `composer require nfse-nacional/nfse-php` (fallback: `Rainzart/nfse-nacional` se gap em RTC v2.00)
- [ ] `.env`: `NFSE_AMBIENTE=homologacao|producao`, `NFSE_CERT_PATH=/storage/certs/oimpresso.pfx`, `NFSE_CERT_SENHA=...`
- [ ] Endpoints: sandbox `https://sefin.producaorestrita.nfse.gov.br` · prod `https://sefin.nfse.gov.br`
- [ ] Doc em `Modules/NFSe/README.md`

#### US-NFSE-003 · Migrations base (1d)
- [ ] `nfe_certificados` (compartilhado pra futuro NfeBrasil) — `business_id`, `cert_pfx_encrypted`, `senha_encrypted`, `valido_ate`
- [ ] `nfse_emissoes` — `business_id`, `numero`, `serie`, `tomador_cnpj/cpf`, `valor`, `iss`, `xml`, `pdf_url`, `status` (rascunho/processando/emitida/cancelada/erro), `provider_protocolo`, `idempotency_key`
- [ ] `nfse_provider_configs` — `business_id`, `provider`, `municipio_codigo_ibge`, `serie_default`, `cnae`, `lc116_default`
- [ ] Seeder com dados oimpresso

### Sprint B — Backend (3-4 dias)

#### US-NFSE-004 · Adapter + Service (1.5d)
- [ ] Interface `NfseProvider` (3 métodos: `emitir/consultar/cancelar`)
- [ ] Implementação concreta `SnNfseAdapter` (US-001 decidiu: SN-NFSe federal direto, sem provider terceiro)
- [ ] `NfseEmissaoService` (validação payload + chamada adapter + persistência + retry)
- [ ] Testes Pest com mock do provider (golden flow + idempotência + cancelamento)
- [ ] Adapter pattern preserva flexibilidade pra ABRASF futuro (municípios não-aderidos)

#### US-NFSE-005 · Job assíncrono (0.5d)
- [ ] `EmitirNfseJob` (queue `nfse` separada — não bloqueia outras filas)
- [ ] Retry policy: 3 tentativas com backoff exponencial
- [ ] Idempotência: `idempotency_key = hash(business_id + tomador + valor + descricao + data)`

#### US-NFSE-006 · HTTP Controller + rotas (0.5d)
- [ ] `POST /nfse/emitir` (cria registro + dispara job)
- [ ] `GET /nfse/{id}` (detalhe + status)
- [ ] `POST /nfse/{id}/cancelar` (motivo)
- [ ] `GET /nfse/{id}/pdf` (proxy PDF do provider)
- [ ] Spatie permissions: `nfse.emit`, `nfse.cancel`, `nfse.view`

#### US-NFSE-007 · Bridge recurring nativo UPOS (0.5-1d) 🟡 opcional Sprint B
- [ ] Listener no evento de geração de `recurring_invoice` UPOS → cria NFSe `rascunho`
- [ ] Mapeamento item venda → código serviço LC 116 (config no produto)
- [ ] Botão "Emitir NFSe" no detalhe do recurring invoice (legacy Blade)

### Sprint C — UI Inertia/React (3 dias)

#### US-NFSE-008 · Pages/Nfse/Index.tsx (1d)
- [ ] AppShellV2 + breadcrumb `[{ label: 'Fiscal' }, { label: 'NFSe' }]`
- [ ] DataTable com colunas: número, data, tomador, valor, status (StatusBadge), ações
- [ ] PageFilters: status (rascunho/processando/emitida/cancelada/erro), período
- [ ] EmptyState "Nenhuma NFSe emitida ainda"
- [ ] Componentes shared (PageHeader, KpiGrid, DataTable, StatusBadge)

#### US-NFSE-009 · Pages/Nfse/Emitir.tsx (1d)
- [ ] Form: tomador (CNPJ ou CPF + razão social + endereço), serviço (descrição + cód LC 116 + valor), retenções opcionais
- [ ] Auto-preenchimento por busca de cliente (autocomplete contacts UPOS)
- [ ] Submit → POST /nfse/emitir → toast "NFSe sendo processada" + redirect lista
- [ ] Validação react-hook-form + zod

#### US-NFSE-010 · Action "Imprimir DANFSE" (0.5d)
- [ ] Botão na linha de cada NFSe emitida → abre PDF em nova aba
- [ ] Fallback: download se provider só dá base64
- [ ] Action "Cancelar" com modal motivo (NFSe ainda no prazo legal de cancelamento)

### Sprint D — Validação + produção (2-3 dias)

#### US-NFSE-011 · Testes Pest end-to-end (1d)
- [ ] Golden test: criar → emitir → consultar → cancelar
- [ ] Mock provider Focus/SN-NFSe
- [ ] Cobertura: idempotência, retry, contingência (provider down)
- [ ] Coverage mínimo: 80% das linhas do `NfseEmissaoService`

#### US-NFSE-012 · Deploy sandbox (0.5d)
- [ ] Subir migrations no Hostinger
- [ ] `.env` produção com cert + token sandbox
- [ ] Smoke test: emitir 1 NFSe sandbox + verificar XML/PDF
- [ ] Validar que prefeitura Tubarão aceita o XML

#### US-NFSE-013 · Deploy produção real (0.5d) 🔴 marco de sucesso
- [ ] Trocar token sandbox → produção
- [ ] Cert A1 produção (vault encrypted)
- [ ] Emitir **1 NFSe real de teste** (cliente fake oimpresso pra oimpresso, valor mínimo)
- [ ] Confirmar com contador/prefeitura
- [ ] Documentar em session log + Eliana ganha lap

#### US-NFSE-014 · Rollout pros clientes oimpresso (1d)
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
