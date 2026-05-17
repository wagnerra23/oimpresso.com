---
module: NFSe
status: homologação SN-NFSe federal (cert A1 pendente Wagner) — recente 2026-05-01
piloto: oimpresso biz=1 (Wagner empresa, Tubarão-SC) — cliente único interno
last_review: 2026-05-16
owner: eliana
parent_adr: ARQ-0001
related_adrs: [0093, 0101, 0121, 0153, 0155, 0156]
nota_atual_v2: "~56/100 (injusto — D5 penaliza cliente único interno em homologação)"
nota_esperada_v3: "~65-75/100 pós-PR4 na_justified D5 declarado"
na_justified: [D5]
---

# BRIEFING — `Modules/NFSe`

> **Tipo:** BRIEFING canônico do módulo — 1 página executiva
> **Refs:** [proibicoes.md §Sempre fazer](../../proibicoes.md) — Tier 0 "BRIEFING.md atualizado em todo PR mergeado"
> **Skill auto-trigger:** `brief-update` (Tier B)
> **Owner:** Eliana[E] · **Paralelo a:** Cycle 01 (não bloqueia)
> Última atualização: 2026-05-16 (Wave 5 re-try — `na_justified` D5 declarado no SPEC pareado)

---

## 1. O que é

**URL principal:** `https://oimpresso.com/nfse`
**Backend:** `Modules/NFSe/`
**Frontend:** `resources/js/Pages/Nfse/` (Index, Emitir, Show)

Emissão de Nota Fiscal de Serviço eletrônica (NFSe) via **SN-NFSe federal** (LC 214/2025), direto SEFIN nacional sem provider terceiro. Cliente piloto interno: empresa **oimpresso** (Wagner, Tubarão-SC). Pareado com `Modules/NfeBrasil` para módulo `Modules/ComunicacaoVisual` (CNAE 1813-0/01 — produtos e serviços conjuntos).

## 2. Estado consolidado

| Dimensão | % | Última medição |
|---|---|---|
| Operacional PME (P0+P1 core) | 65% | 2026-05-16 |
| Capterra score vs top-mercado | 56/100 | 2026-05-16 (Wave A grade) |
| Diferencial competitivo (SN-NFSe direto sem provider) | 70% | 2026-05-16 |
| Cobertura SPEC formal (done/spec'ado) | 70% | 2026-05-16 (Sprint A ✅ · Sprint B em curso) |
| Documentação canon (SPEC + ACESSOS + PESQUISA + RUNBOOK) | 80% | 2026-05-16 |
| Deploy/ops (prod) | 0% — homologação | 2026-05-16 (cert A1 pendente Wagner) |

### Score module-grade (v3 pós-PR4)

| Versão | Score | Observação |
|---|---|---|
| v2 (pré-PR4) | ~56/100 | Penalizava D5 (cliente externo) — homologação com biz=1 oimpresso interno único |
| **v3 (pós-PR4)** | **~65-75/100** (esperado) | `na_justified` D5 declarado no SPEC → rubrica v3 redistribui peso (ADR 0156) + cobertura prefeituras inicial limita upside |

**`na_justified` declarado no SPEC:**
- **D5 (cliente externo):** módulo recente (2026-05-01), homologação SEFIN com biz=1 oimpresso interno (Wagner empresa, Tubarão-SC). Sem cliente externo pagante ainda — próximo canary ComunicacaoVisual (Vargas/Extreme/Gold/Zoom/Fixar/Mhundo) aguarda ativação Modules/ComunicacaoVisual produção. NUNCA biz=4 ROTA LIVRE (vestuário CNAE 4781-4/00 só emite NFCe).

## 3. Capacidades hoje

- **Provider**: SN-NFSe federal direto (LC 214/2025) — sandbox + prod endpoints configurados
- **Tela emissão**: `Nfse/Emitir.tsx` — form completo com pré-fill via `transaction_id` (vinculação venda → NFSe)
- **Tela listagem**: `Nfse/Index.tsx` — filtros status/competência/tomador + paginate 25
- **Tela detalhe**: `Nfse/Show.tsx` — status real-time, link DANFSE proxy, cancelamento
- **Service `NfseEmissaoService`**: idempotência via `idempotency_key`, retry 3× com backoff exponencial em timeout, log estruturado canal `nfse`
- **Job assíncrono**: `EmitirNfseJob` na fila `nfse` com payload DTO (cert A1 em base64 + senha decriptada)
- **Cert A1**: storage encriptado (`cert_pfx_encrypted`), `senhaDecriptada()` + `pfxDecriptado()` runtime
- **Cancelamento**: motivo min 15 chars (SEFIN exige), update status, log canal nfse

## 4. Diferenciais únicos (não-replicáveis BSPs)

1. **SN-NFSe federal direto sem provider terceiro** — custo zero per-emissão (concorrentes pagam Focus NFe / NFE.io / PlugNotas R$ [redacted Tier 0]-1,00/emissão)
2. **Vinculação venda → NFSe nativa** — `transaction_id` no payload + pre-fill form (Bling/Tiny obrigam re-digitação)
3. **Idempotência por `idempotency_key`** — duplo-submit não cria dupla nota
4. **Multi-tenant Tier 0** — cada `business_id` com config + cert isolados (ADR 0093)
5. **Job retry com backoff exponencial** — 3 tentativas em timeout SEFIN, 1s/2s/4s

## 5. Gaps remanescentes (próxima onda)

| # | PR alvo | Esforço IA-pair | Score impact |
|---|---|---|---|
| 1 | Pest cobertura emissão end-to-end (biz=1 NUNCA biz=4) | 4h | +3pp |
| 2 | Bulk emissão (recurring invoices mensais) | 6h | +4pp |
| 3 | Dashboard métricas NFSe (volume/erro/ISS pago) | 4h | +2pp |
| 4 | UI configuração cert A1 + provider (substituir Tinker manual) | 8h | +3pp |
| 5 | Webhook callback SEFIN async (em vez de polling) | 6h | +2pp |

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

- 🔴 **Cert A1 expira** sem alerta proativo — adicionar cron alerta D-30/D-7/D-1 (US futuro)
- 🟡 **SN-NFSe LC 214/2025 ainda em adoção** — algumas prefeituras retardatárias podem voltar a exigir ABRASF municipal (cobertura SEFIN evolui em 2026)
- 🟡 **Idempotência só cobre store()** — race em update concorrente status precisa lock pessimista futuro
- 🟢 **Sem cliente real em prod ainda** — risco financeiro contido a homologação

## 9. Métricas-chave (last 7d)

- Volume: 0 (módulo ainda em homologação)
- Custo: R$ [redacted Tier 0]/dia (SN-NFSe federal gratuito)
- Erros emissão: N/A
- Tempo médio emissão: ~2-4s SEFIN (esperado)

## 10. Cliente piloto / canary

- **Atual:** oimpresso biz=1 (Wagner empresa) — homologação SEFIN
- **Próximo canary:** ComunicacaoVisual candidatos (Vargas/Extreme/Gold/Zoom/Fixar/Mhundo) quando ativar `Modules/ComunicacaoVisual` produção
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

**Atualizado:** 2026-05-16 BRT — Wave 18 saturation (D1 NfseCertificado cross-tenant Pest)
**Próximo update esperado:** quando US-NFSE-007 (bulk emission) ou cert A1 ativado prod
**Mantenedor:** Claude (auto) + Eliana (owner) + Wagner (review)

### Wave 18 deltas (2026-05-16)
- D1: novo Pest `NfseCertificadoMultiTenantIsolationTest.php` (3 testes — scope herdado de NfeCertificado, isExpirado() alias, contrato business_id NOT NULL)
- CHANGELOG.md criado pelo módulo
- Cobertura cross-tenant explícita pra credenciais fiscais A1/A3 (CNPJ titular + encrypted_password)
