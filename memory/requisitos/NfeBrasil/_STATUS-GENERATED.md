---
authority: generated
---

<!-- GERADO por scripts/governance/requisitos-status.mjs — NÃO editar à mão.
     Status é DERIVADO da cadeia US→CU→UC→teste. Editar aqui não muda nada:
     mude o SPEC/SDD/casos/teste e re-rode. (ADR 0256: derivado sobrevive.) -->

# Requisitos — NfeBrasil · status derivado

> **Cadeia medida:** `US (SPEC) → CU (SDD §6) → UC (casos.md) → teste → veredito`.
> O veredito final (✅/❌) vem da **lane de CI**, nunca deste gerador — status aqui
> nunca afirma verde sem execução (G-7 · [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)).

## Placar da cadeia

| Elo | Quantidade |
|---|---:|
| US no SPEC | 41 |
| CU no SDD | 13 |
| Telas (.tsx) | 6 |
| Telas com `casos.md` | 6 |
| UC declarados | 66 |
| UC com teste que os cita | 64 |

## Onde a cadeia QUEBRA — esta é a fila de crescimento

| Lacuna | O que falta escrever |
|---|---|
| `CU-NFE-01` sem UC | caso de uso que o exercite — Emitir NFC-e/NF-e de uma venda sem emitir duas vezes |
| `CU-NFE-03` sem UC | caso de uso que o exercite — Emitir automaticamente só onde o tenant pediu |
| `CU-NFE-04` sem UC | caso de uso que o exercite — O número fiscal nunca é reaproveitado nem apagado |
| `CU-NFE-05` sem UC | caso de uso que o exercite — A cascade resolve o tributo, e falha alto quando não resolve |
| `CU-NFE-10` sem UC | caso de uso que o exercite — Evento de manifestação é append-only e idempotente |
| `CU-NFE-12` sem UC | caso de uso que o exercite — Segredo e PII fiscal não viajam nem vão para log |
| `CU-NFE-13` sem UC | caso de uso que o exercite — O operador chega ao status certo da nota |
| `US-NFE-040` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — [Epic] Foundation domínio NFe — migrations + models + compos |
| `US-NFE-041` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — CertificadoService + storage encrypted + UI admin |
| `US-NFE-049` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Migrar models/service legados Manifesto/ItemDfe/DFeService p |
| `US-NFE-050` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — ManifestacaoService — eventos 210/220/230/240 via sped-nfe |
| `US-NFE-051` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — DistribuicaoDfeService + Job agendado puxa XMLs por NSU |
| `US-NFE-052` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — UI listar XMLs recebidos + 4 botões manifestar + alerta praz |
| `US-NFE-060` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — EmitirNFSeJob modelo 56 nacional (NT 2024-001) — paralelo ao |
| `US-NFE-061` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Charters NFe (Certificado, Tributacao, Manifestacao) antes d |
| `US-NFE-064` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Devolução de venda copia base e alíquotas da nota de origem |
| `US-NFE-066` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Benefício fiscal na regra (redução de base + cBenef) |
| `US-NFE-067` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Filial em outro estado emite com a própria UF, emitente e sé |
| `US-NFE-070` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Contingência: dhCont/xJust no XML e retransmissão agendada ( |

### Backlog — NÃO é lacuna

> US ainda não entregue (`todo`/`backlog`) **não deve** ganhar UC agora: caso sem código vira
> **UC órfão**, que o `casos-gate` G-2 pune e que bloqueia o merge de quem for implementar
> ([proibicoes §5](../../proibicoes.md) 2026-07-16 — UC não é canal de pedido). O contrato
> nasce **junto** com a implementação, não antes.

| US | status | Título |
|---|---|---|
| US-NFE-001 | `desconhecido` | Configurar certificado digital A1 |
| US-NFE-002 | `desconhecido` | Emitir NFC-e a partir de venda finalizada |
| US-NFE-003 | `desconhecido` | Visualizar e reimprimir NFCe/NFe emitida |
| US-NFE-004 | `desconhecido` | Cancelar NFe/NFC-e dentro do prazo legal |
| US-NFE-005 | `desconhecido` | Carta de Correção Eletrônica (CCe) |
| US-NFE-006 | `desconhecido` | Modo contingência (EPEC ou FS-DA) |
| US-NFE-007 | `desconhecido` | Monitor de rejeições + status SEFAZ |
| US-NFE-008 | `desconhecido` | Manifestar NF-e recebida (destinatário) |
| US-NFE-042 | `todo` | Discovery técnico instalação Gold Comunicação Visual |
| US-NFE-043 | `blocked` | Proposta comercial Gold — diferenciais vs Mubsys + pricing on-prem · 🔒 DORMENTE |
| US-NFE-044 | `blocked` | Upgrade plataforma on-prem Gold pra versão atual com NfeBrasil · 🔒 DORMENTE |
| US-NFE-045 | `blocked` | Configuração fiscal NfeBrasil Gold (cert + IE + regime + template SP) · 🔒 DORME |
| US-NFE-046 | `blocked` | Smoke fiscal homologação SEFAZ-SP biz=Gold (1ª NF-e 55 cstat 100) · 🔒 DORMENTE |
| US-NFE-047 | `blocked` | Treinamento operadora Gold + cutover NF-e produção + canary 7d · 🔒 DORMENTE |
| US-NFE-048 | `blocked` | Refinar runbook on-prem reutilizável pós-Gold (Trilha 1 dormentes) · 🔒 DORMENTE |
| US-NFE-053 | `todo` | Smoke homologação SEFAZ-SP eventos 210/220 biz=Gold |
| US-NFE-054 | `todo` | Smoke real homologação SEFAZ-SC biz=1 — 1ª NFC-e cstat 100 (Goal #1 CYCLE-03) |
| US-NFE-055 | `todo` | Estabilizar 107 tests broken Modules/NfeBrasil aplicando dual-mode SQLite/MySQL |
| US-NFE-056 | `todo` | Continuação PR #453 — migrar configBusiness(4) → configBusiness(1) em MotorTribu |
| US-NFE-057 | `todo` | Schema drift — atualizar fixtures DanfeServicePrefersArquivosTest pós-remoção `a |
| US-NFE-058 | `todo` | Aplicar pattern dual-mode em batch restante — Emitir* + NfeEmissaoController + D |
| US-NFE-059 | `todo` | Smoke prod end-to-end auto-emissão NFe55 com cliente real que opt-in |
| US-NFE-063 | `todo` | Smoke Browser MCP fresh (screenshot+console) para fluxos Certificado/Auto-emissi |
| US-NFE-071 | `todo` | Contingência: SVC-AN/SVC-RS, evento EPEC 110140 e marcação no DANFE (UC-FLX-32)  |

## UC por status

| UC | Tela | Status |
|---|---|---|
| UC-FLX-27 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-FLX-31 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFCD-01 | Tributacao/ConfigDefault | 🧪 aguarda veredito da lane |
| UC-NFCD-02 | Tributacao/ConfigDefault | 🧪 aguarda veredito da lane |
| UC-NFCD-03 | Tributacao/ConfigDefault | 🧪 aguarda veredito da lane |
| UC-NFCD-04 | Tributacao/ConfigDefault | 🧪 aguarda veredito da lane |
| UC-NFCD-05 | Tributacao/ConfigDefault | 🧪 aguarda veredito da lane |
| UC-NFCD-06 | Tributacao/ConfigDefault | 🧪 aguarda veredito da lane |
| UC-NFIM-01 | Tributacao/ImportCsv | 🧪 aguarda veredito da lane |
| UC-NFIM-02 | Tributacao/ImportCsv | 🧪 aguarda veredito da lane |
| UC-NFIM-03 | Tributacao/ImportCsv | 🧪 aguarda veredito da lane |
| UC-NFIM-04 | Tributacao/ImportCsv | 🧪 aguarda veredito da lane |
| UC-NFMA-01 | Manifestacao/Index | 🧪 aguarda veredito da lane |
| UC-NFMA-02 | Manifestacao/Index | 🧪 aguarda veredito da lane |
| UC-NFMA-03 | Manifestacao/Index | 🧪 aguarda veredito da lane |
| UC-NFMA-04 | Manifestacao/Index | 🧪 aguarda veredito da lane |
| UC-NFMA-05 | Manifestacao/Index | 🧪 aguarda veredito da lane |
| UC-NFMA-06 | Manifestacao/Index | 🧪 aguarda veredito da lane |
| UC-NFRF-01 | Tributacao/RegraForm | 🧪 aguarda veredito da lane |
| UC-NFRF-02 | Tributacao/RegraForm | 🧪 aguarda veredito da lane |
| UC-NFRF-03 | Tributacao/RegraForm | 🧪 aguarda veredito da lane |
| UC-NFRF-04 | Tributacao/RegraForm | 🧪 aguarda veredito da lane |
| UC-NFRF-05 | Tributacao/RegraForm | 🧪 aguarda veredito da lane |
| UC-NFRF-06 | Tributacao/RegraForm | 🧪 aguarda veredito da lane |
| UC-NFRF-07 | Tributacao/RegraForm | 🧪 aguarda veredito da lane |
| UC-NFRF-08 | Tributacao/RegraForm | 🧪 aguarda veredito da lane |
| UC-NFST-01 | Transactions/NfceStatus | 🧪 aguarda veredito da lane |
| UC-NFST-02 | Transactions/NfceStatus | 🧪 aguarda veredito da lane |
| UC-NFST-03 | Transactions/NfceStatus | 🧪 aguarda veredito da lane |
| UC-NFST-04 | Transactions/NfceStatus | 🧪 aguarda veredito da lane |
| UC-NFST-05 | Transactions/NfceStatus | 🧪 aguarda veredito da lane |
| UC-NFTR-01 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-02 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-03 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-04 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-05 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-06 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-07 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-08 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-09 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-10 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-10B | Tributacao/Index | 📝 sem_teste |
| UC-NFTR-11 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-12 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-13 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-14 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-15 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-16 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-17 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-18 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-19 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-20 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-21 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-22 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-23 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-24 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-25 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-26 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-27 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-28 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-29 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-30 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-31 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-NFTR-32 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-PIDX-25 | Tributacao/Index | 🧪 aguarda veredito da lane |
| UC-TRB-16 | Tributacao/Index | 📝 sem_teste |

---

**Como este arquivo cresce:** cada linha da tabela "onde a cadeia quebra" é o **próximo
requisito a escrever**. Fechou? Re-rode e ela some. Descobriu que NÃO se deve fazer?
Então não é lacuna — é **Non-Goal no charter** (só [W] preenche) ou entrada no **§5 de
`proibicoes.md`** se for padrão a nunca repetir. As duas saídas são legítimas; deixar
a lacuna aberta sem decisão é a única que não é.
