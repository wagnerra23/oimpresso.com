---
id: requisitos-nfse-briefing
module: NFSe
status: homologação SN-NFSe federal + cutover fiscal por-business habilitado (biz=164 Martinho — #2147); cert A1 pendente Wagner
piloto: oimpresso biz=1 (Wagner, Tubarão-SC) homologação + biz=164 (Martinho OficinaAuto) cutover por-business
last_review: 2026-08-01
owner: eliana
parent_adr: ARQ-0001
related_adrs: [0093, 0101, 0121, 0153, 0155, 0156]
nota_stale: "número de module-grade abaixo é HISTÓRICO (2026-05-16). Fonte viva: php artisan module:grade NFSe --detail (CT 100) — não restatear à mão (LC-08)"
na_justified: [D5]
---

# BRIEFING — `Modules/NFSe`

> **Tipo:** BRIEFING canônico do módulo — 1 página executiva
> **Refs:** [proibicoes.md §Sempre fazer](../../proibicoes.md) — Tier 0 "BRIEFING.md atualizado em todo PR mergeado"
> **Skill auto-trigger:** `brief-update` (Tier B)
> **Owner:** Eliana[E] · **Paralelo a:** Cycle 01 (não bloqueia)
> Última atualização: 2026-08-01 (catch-up 76d — reflete até `origin/main` 2026-07-31; deltas §13.1)

---

## 1. O que é

**URL principal:** `https://oimpresso.com/nfse`
**Backend:** `Modules/NFSe/`
**Frontend:** `resources/js/Pages/Nfse/` (Index, Emitir, Show)

Emissão de Nota Fiscal de Serviço eletrônica (NFSe) via **SN-NFSe federal** (LC 214/2025), direto SEFIN nacional sem provider terceiro. Município: Tubarão-SC (IBGE 4218707). Bucket de governança = `functional_horizontal` ([module.json](../../../Modules/NFSe/module.json), 2026-05-17 [W]) — **infra fiscal cross-vertical, não cliente único**. Consumidores hoje: **biz=1** (Wagner, homologação) + **biz=164** (Martinho/OficinaAuto — cutover fiscal por-business habilitado, #2147). Pareado com `Modules/NfeBrasil` (cert A1 + schema `nfe_certificados` unificados).

## 2. Estado consolidado

| Dimensão | % | Última medição |
|---|---|---|
| Operacional PME (P0+P1 core) | 65% | 2026-05-16 |
| Capterra score vs top-mercado | 56/100 | 2026-05-16 (Wave A grade) |
| Diferencial competitivo (SN-NFSe direto sem provider) | 70% | 2026-05-16 |
| Cobertura SPEC formal (done/spec'ado) | 70% | 2026-05-16 (Sprint A ✅ · Sprint B em curso) |
| Documentação canon (SPEC + ACESSOS + PESQUISA + RUNBOOK) | 80% | 2026-05-16 |
| Deploy/ops (prod) | 0% — homologação | 2026-05-16 (cert A1 pendente Wagner) |

### Score module-grade

> ⚠️ **Números abaixo são HISTÓRICO datado (2026-05-16), não o estado atual.** Fonte viva da nota = `php artisan module:grade NFSe --detail` (roda no CT 100, baseline em `governance/module-grades-baseline.json`). Não restatear o número à mão (LC-08 · lápide 2026-07-17 "não repetir número que outro sistema sabe melhor").

| Versão (datada) | Score | Observação |
|---|---|---|
| v2 (2026-05-16) | ~56/100 | Penalizava D5 (cliente externo) — então biz=1 interno único |
| v3 esperado (2026-05-16) | ~65-75/100 | `na_justified` D5 → rubrica v3 redistribui peso (ADR 0156) |

**`na_justified` D5 declarado no SPEC:** módulo fiscal foundation-horizontal; sem cliente externo pagante isolado enquanto biz=1 (homologação) e biz=164 (cutover Martinho) são os consumidores. **NUNCA biz=4 ROTA LIVRE** (vestuário CNAE 4781-4/00 só emite NFCe).

## 3. Capacidades hoje

- **Provider**: SN-NFSe federal direto (LC 214/2025) — sandbox + prod endpoints configurados
- **Tela emissão**: `Nfse/Emitir.tsx` — form completo com pré-fill via `transaction_id` (vinculação venda → NFSe)
- **Tela listagem**: `Nfse/Index.tsx` — filtros status/competência/tomador + paginate 25
- **Tela detalhe**: `Nfse/Show.tsx` — status real-time, link DANFSE proxy, cancelamento
- **Service `NfseEmissaoService`**: idempotência via `idempotency_key`, retry 3× com backoff exponencial em timeout, log estruturado canal `nfse`
- **Ambiente POR-BUSINESS (cutover fiscal)**: emissão resolve `homologacao`/`producao` de `nfse_provider_configs.ambiente` do tenant (`montarPayload` L76-80 + `SnNfseAdapter::resolveBaseUrl`), NÃO do bind global — ligar produção pra biz=164 não afeta os demais (#2147, teste `AmbientePorBusinessTest`)
- **Job assíncrono**: `EmitirNfseJob` na fila `nfse` com payload DTO (cert A1 em base64 + senha decriptada)
- **Cert A1**: storage encriptado, delegado ao `CertificadoService` do NfeBrasil (schema unificado migration 2026_05_07_210000). Import via `php artisan nfse:importar-cert --pfx=<arquivo.pfx> --senha=<senha> --business=<id>` (`ImportarCertificadoCommand`)
- **Health check**: `php artisan nfse:health` — 6 sinais (tabelas, providers ativos, **cert vencendo ≤30d**, rejeitadas 24h) — `NfseHealthCommand`, Wave 23 D6
- **Cancelamento**: motivo obrigatório, bloqueia dupla cancelação (`NfseJaCanceladaException`), span OTel `nfse.cancelar` (Wave 28, #2678), log canal nfse
- **Observabilidade**: spans OTel `nfse.emissao` + `nfse.cancelar` com atributo `businessId` (correlação prod cross-tenant)

## 4. Diferenciais únicos (não-replicáveis BSPs)

1. **SN-NFSe federal direto sem provider terceiro** — custo zero per-emissão (concorrentes pagam Focus NFe / NFE.io / PlugNotas R$ [redacted Tier 0]-1,00/emissão)
2. **Vinculação venda → NFSe nativa** — `transaction_id` no payload + pre-fill form (Bling/Tiny obrigam re-digitação)
3. **Idempotência por `idempotency_key`** — duplo-submit não cria dupla nota
4. **Multi-tenant Tier 0** — cada `business_id` com config + cert isolados (ADR 0093)
5. **Job retry com backoff exponencial** — 3 tentativas em timeout SEFIN, 1s/2s/4s

## 5. Gaps remanescentes (próxima onda)

| # | PR alvo | Esforço IA-pair | Nota |
|---|---|---|---|
| 1 | Pest emissão end-to-end com hit DB real (Waves 25/27/28 são reflection+source-grep, ZERO hit DB — CHANGELOG) | 4h | cobertura de comportamento ainda não é DB-real |
| 2 | Bulk emissão (recurring invoices mensais) | 6h | aberto |
| 3 | Dashboard métricas NFSe (volume/erro/ISS pago) | 4h | **parcial** — `nfse:health` já dá 6 sinais CLI; falta tela |
| 4 | UI configuração cert A1 + provider | 8h | **parcial** — `nfse:importar-cert` substituiu Tinker; falta UI web |
| 5 | Webhook callback SEFIN async (em vez de polling) | 6h | aberto |
| 6 | Non-Goals/Anti-hooks dos 3 charters (status `draft`, pendem aprovação [W] pra virar `live`) | — | #4113 criou os charters; falta [W] |

## 6. Bloqueadores manuais Wagner

- **Certificado A1 (.pfx)** válido oimpresso — assinar com contador
- **CNPJ + IE + IM** registrados prefeitura Tubarão (Wagner confirma)
- **Regime tributário** (Simples Nacional / Real / Presumido) — Wagner + contador
- Decisão custo: usar SN-NFSe direto vs Focus NFe / NFE.io / PlugNotas (US-NFSE-001 ✅ decidiu SN-NFSe)

## 7. ROI defendido vs concorrentes

| Concorrente | Como ganhamos | Como perdemos |
|---|---|---|
| Focus NFe / NFE.io / PlugNotas | Zero custo per-emissão (SN-NFSe direto) | Setup mais complexo (cert A1 self-managed) |
| Bling/Tiny/Omie NFSe | ERP nativo + vinculação venda automática | UX polida, suporte 24/7 |
| eNotas | Multi-tenant Tier 0 isolation real | Provider já integrado com prefs municipais antigas (legacy ABRASF) |

## 8. Risks ativos

- 🟡 **Cert A1 expira** — `nfse:health` já sinaliza `cert_vencimento_alarme` ≤30d (era 🔴 no BRIEFING de 05-16); falta cron que dispare o alerta proativamente (D-30/D-7/D-1)
- 🟡 **SN-NFSe LC 214/2025 ainda em adoção** — algumas prefeituras retardatárias podem voltar a exigir ABRASF municipal (cobertura SEFIN evolui em 2026)
- 🟡 **Idempotência só cobre store()** — race em update concorrente status precisa lock pessimista futuro
- 🟢 **Sem cliente real em prod ainda** — risco financeiro contido a homologação

## 9. Métricas-chave (last 7d)

- Volume: 0 (módulo ainda em homologação)
- Custo: R$ [redacted Tier 0]/dia (SN-NFSe federal gratuito)
- Erros emissão: N/A
- Tempo médio emissão: ~2-4s SEFIN (esperado)

## 10. Cliente piloto / canary

- **biz=1** (Wagner/oimpresso, Tubarão-SC) — homologação SEFIN
- **biz=164** (Martinho / OficinaAuto) — **cutover fiscal por-business habilitado no código** (#2147, 2026-06-03). O ambiente do tenant é lido de `nfse_provider_configs.ambiente`; se/qual business está de fato flipado pra `producao` em prod é fato de runtime (CT 100/prod DB) — não afirmar "LIVE emitindo" sem medir
- **Próximo canary:** ComunicacaoVisual (Vargas/Extreme/Gold/Zoom/Fixar/Mhundo) quando `Modules/ComunicacaoVisual` for a produção
- **NUNCA biz=4 ROTA LIVRE** — Larissa é vestuário CNAE 4781-4/00, não emite NFSe (só NFCe)

## 11. ADRs centrais do módulo

- [ADR ARQ-0001](adr/arq/0001-cliente-oimpresso-modulo-standalone.md) — cliente oimpresso (NÃO ROTA LIVRE)
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) — Pest biz=1 nunca cliente real
- [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) — modular especializado por vertical (pareado NfeBrasil)
- [ADR 0155](../../decisions/0155-module-grade-v3-anti-injustica-na-justified.md) — Rubrica v3 `na_justified`
- [ADR 0156](../../decisions/0156-rubrica-v3-pesos-redistribuidos.md) — Pesos redistribuídos v3

## 12. Sessões e handoffs relevantes (últimos 30d)

- Sprint A concluída 2026-05-01 — US-001/002/003 ✅ scaffold + migrations + pesquisa Tubarão
- Sprint B em curso 2026-05-01+ — US-004 parcial (Service+Adapter stub), US-006/008/009 done

---

## 13. Último update

**Atualizado:** 2026-08-01 BRT — catch-up 76d (#5153): cutover fiscal por-business biz=164 Martinho, nota de module-grade vira ponteiro. _(Revisão anterior: 2026-05-16, Wave 18 saturation — D1 NfseCertificado cross-tenant Pest.)_

> **Carimbo único — não duplicar.** Este rodapé é a data que o `briefing-code-staleness` lê
> (junto de `updated_at`/`distilled_at`/`reviewed_at`, pegando a MAIOR). O frontmatter
> `last_review:` **não é lido por ele**. Se as duas discordarem, o detector acredita nesta —
> foi o que produziu um alarme falso de **88 dias** entre 08-01 e 08-16, com o módulo já
> revisado. Ao revisar, mova as duas juntas ou mantenha só uma.
**Próximo update esperado:** quando US-NFSE-007 (bulk emission) ou cert A1 ativado prod
**Mantenedor:** Claude (auto) + Eliana (owner) + Wagner (review)

### Wave 18 deltas (2026-05-16)
- D1: novo Pest `NfseCertificadoMultiTenantIsolationTest.php` (3 testes — scope herdado de NfeCertificado, isExpirado() alias, contrato business_id NOT NULL)
- CHANGELOG.md criado pelo módulo
- Cobertura cross-tenant explícita pra credenciais fiscais A1/A3 (CNPJ titular + encrypted_password)
