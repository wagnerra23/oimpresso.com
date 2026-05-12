# SPEC — Cancelamento NFSe (Modelo 56) per-município

**Status:** draft (framework abstrato pronto; drivers per-município pendentes)
**Owner:** [W] · **Refs:** US-SELL-034 (NFe55/NFCe65 já fechado PR #626) · ADR 0093 (multi-tenant) · ADR 0061 (auto-mem zero)

---

## 1. Por que NFSe é diferente

NFe55 e NFCe65 têm protocolo SEFAZ central padronizado (`tpEvento=110111`).
NFSe modelo 56 **não tem padrão único** — cada município escolhe:

| Padrão | Adoção BR aprox | Protocolo | Operação cancelamento |
|---|---|---|---|
| **ABRASF v1.0** | ~15% (legacy) | SOAP | `CancelarNfse` |
| **ABRASF v2.04** | ~45% (recomendado CONFAZ) | SOAP | `CancelarNfse` (lote unitário) |
| **GINFES** | ~10% (alguns SP/MG) | SOAP próprio | `CancelarNfse` |
| **IPM** | ~8% (SC/RS) | REST proprietário | `POST /nfse/{n}/cancelar` |
| **Tiplan** | ~5% (RJ/MG) | REST proprietário | endpoint privado |
| **nfse.gov.br/sefin** | crescendo (NT 2024-001, MEI obrigatório 09/2023) | REST nacional | `POST /eventos` tpEvento=cancelamento |
| **Outros municipais** | ~17% (Tinus, Issnet, Megasoft, etc) | varia | varia |

⚠️ Por isso oimpresso entrega cancelamento como **framework abstrato** + driver
per-município. Driver concreto é US separada.

---

## 2. Arquitetura

```
CancelarVendaCascade (App\Domain\Fsm\SideEffects)
  └── doc_type=nfse56? → dispatch CancelarNfseJob ($businessId, $docId, $motivo)
                            └── NfseCancelService::cancelar()
                                  ├── valida motivo 15-255 chars
                                  ├── cross-tenant guard
                                  ├── idempotência (status=cancelled)
                                  └── resolveDriver(emissao.municipio_codigo_ibge)
                                       └── NfseCancelDriverInterface::cancelar()
                                            ├── ABRASF v2.04 (stub PR atual)
                                            ├── GINFES        (US-NFSE-CANCEL-003)
                                            ├── IPM           (US-NFSE-CANCEL-004)
                                            └── ...
```

**Manager pattern** com **driver registry via container tag**:
- `NfeBrasilServiceProvider::registerNfseCancelDrivers()` tagga drivers em `'nfse.cancel.drivers'`
- `NfseCancelService` recebe `iterable` resolvido pelo container
- Adicionar driver = `$this->app->tag([NovoDriver::class], 'nfse.cancel.drivers')` — sem mexer no service nem no Job

**Tabela append-only** `nfse_eventos_cancelamento`:
- 1 row por tentativa de cancelamento (auditoria forense)
- `driver_key` qual padrão foi tentado · `protocolo_municipal` retorno · `payload_request/response` debug
- FK `nfse_emissao_id` cascade-delete

---

## 3. Mapeamento município → padrão (TODO popular per-cliente)

Tabela viva — atualizar quando business novo ativar NFSe. Cliente piloto ROTA
LIVRE (biz=4) está em **Termas do Gravatal/SC** (IBGE 4218400) — padrão ainda
não confirmado (provavelmente IPM ou municipal próprio — investigar quando
ativar).

| IBGE | Município/UF | Padrão | Driver | Cliente oimpresso ativo |
|---|---|---|---|---|
| 4218400 | Termas do Gravatal/SC | TODO investigar | TODO | ROTA LIVRE (biz=4) — NFSe não ativa ainda |
| 3550308 | São Paulo/SP | Próprio (PMSP) | TODO | candidatos OfficeImpresso legacy |
| 3304557 | Rio de Janeiro/RJ | Tiplan/Próprio | TODO | — |
| 3106200 | Belo Horizonte/MG | BHISS Digital (próprio) | TODO | — |
| 4106902 | Curitiba/PR | ISS Curitiba (próprio) | TODO | — |
| 4314902 | Porto Alegre/RS | NFSe-e (próprio) | TODO | — |
| 2304400 | Fortaleza/CE | ISS Fortaleza | TODO | — |
| 5300108 | Brasília/DF | NFS-e DF | TODO | — |
| ... | (demais municípios oimpresso virá atender) | — | — | — |

**TODO**: cruzar com `mcp_brief` `clientes_ativos` quando businesses extras
ativarem NFSe. Hoje só ROTA LIVRE em prod e ainda não emite NFSe (NFe55+NFCe65
via UltimatePOS).

---

## 4. User stories propostas

| US | Descrição | Padrão | Estimativa | Depende de |
|---|---|---|---|---|
| **US-NFSE-CANCEL-001** | Framework abstrato (interface, manager, migration, job, stub ABRASF v2.04, tests) | — | **4h** ✅ esta PR | — |
| **US-NFSE-CANCEL-002** | Driver ABRASF v2.04 real (SOAP CancelarNfseEnvio + assinatura A1 + parse retorno) | ABRASF v2.04 | 12h | US-001 + cliente piloto em município ABRASF |
| **US-NFSE-CANCEL-003** | Driver GINFES (SOAP próprio — algumas prefeituras SP/MG) | GINFES | 10h | sinal qualificado cliente |
| **US-NFSE-CANCEL-004** | Driver IPM (REST proprietário SC/RS) | IPM | 8h | sinal qualificado (talvez ROTA LIVRE) |
| **US-NFSE-CANCEL-005** | Driver Tiplan (REST proprietário RJ/MG) | Tiplan | 8h | sinal qualificado |
| **US-NFSE-CANCEL-006** | Driver nfse.gov.br/sefin (REST nacional NT 2024-001) | NFSE_GOV_BR | 10h | adoção MEI/Simples crescer |
| **US-NFSE-CANCEL-007** | Driver PMSP (São Paulo próprio) | PMSP | 12h | cliente SP ativo |
| **US-NFSE-CANCEL-008** | Driver BHISS Digital (Belo Horizonte) | BHISS | 10h | sinal qualificado |
| **US-NFSE-CANCEL-009** | UI admin: listar drivers + cobertura município + testar cancelamento (sandbox) | — | 6h | US-002+ |
| **US-NFSE-CANCEL-010** | Cross-tenant audit + RUNBOOK cancelamento NFSe (operacional) | — | 4h | US-002+ |

**Total estimado:** ~84h (4h fechadas nesta PR + 80h backlog driver-by-driver
seguindo ADR 0105 — só ativa quando cliente real exige).

> Estimates 2026 (ADR 0106): fator 10x já aplicado em códigaveis com IA-pair.
> Drivers reais inflam por: certificado A1 quirks per-município, parse XML SOAP
> não-padrão, sandbox+homologação ritualizada por prefeitura.

---

## 5. Definição de pronto (US-NFSE-CANCEL-XXX driver real)

Pra fechar uma US-NFSE-CANCEL-XXX que entrega driver per-município:

- [ ] `supportedMunicipios()` retorna ≥1 código IBGE (não vazio)
- [ ] `cancelar()` faz round-trip real SOAP/REST + assinatura A1
- [ ] Cobertura Pest: happy path (mock SEFAZ), rejeição municipal, idempotência, cross-tenant
- [ ] RUNBOOK `memory/requisitos/NfeBrasil/RUNBOOK-cancelar-nfse-<padrao>.md`
- [ ] Smoke real em sandbox da prefeitura (Wagner manual — ADR 0093)
- [ ] Atualizar tabela §3 com municípios cobertos
- [ ] Canary 7d em prod (cliente piloto) antes de remover flag

---

## 6. Refs

- ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0105 — Cliente como sinal qualificado (drivers só por cliente real)
- ADR 0106 — Recalibração velocidade fator 10x IA-pair
- US-SELL-034 — Cancelar NFe55/NFCe65 (PR #626 ✅ pattern espelhado)
- Modules/NfeBrasil/Contracts/NfseCancelDriverInterface.php
- Modules/NfeBrasil/Services/NfseCancelService.php
- Modules/NfeBrasil/Services/NfseDrivers/AbrasfV204CancelDriver.php
- Modules/NfeBrasil/Jobs/CancelarNfseJob.php
- Modules/NfeBrasil/Models/NfseEventoCancelamento.php

---

**Última atualização:** 2026-05-12 — framework abstrato pronto (US-NFSE-CANCEL-001 ✅ via PR atual). Backlog 9 US aguardando sinal qualificado (ADR 0105).
