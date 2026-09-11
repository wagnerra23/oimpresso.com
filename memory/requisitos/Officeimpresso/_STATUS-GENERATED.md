---
authority: generated
---

<!-- GERADO por scripts/governance/requisitos-status.mjs — NÃO editar à mão.
     Status é DERIVADO da cadeia US→CU→UC→teste. Editar aqui não muda nada:
     mude o SPEC/SDD/casos/teste e re-rode. (ADR 0256: derivado sobrevive.) -->

# Requisitos — Officeimpresso · status derivado

> **Cadeia medida:** `US (SPEC) → CU (SDD §6) → UC (casos.md) → teste → veredito`.
> O veredito final (✅/❌) vem da **lane de CI**, nunca deste gerador — status aqui
> nunca afirma verde sem execução (G-7 · [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)).

## Placar da cadeia

| Elo | Quantidade |
|---|---:|
| US no SPEC | 17 |
| CU no SDD | 0 |
| Telas (.tsx) | 2 |
| Telas com `casos.md` | 2 |
| UC declarados | 21 |
| UC com teste que os cita | 20 |

## Onde a cadeia QUEBRA — esta é a fila de crescimento

_Nenhuma lacuna: toda tela tem caso **com UC**, todo CU é citado, e toda US **entregue** tem contrato._

### Backlog — NÃO é lacuna

> US ainda não entregue (`todo`/`backlog`) **não deve** ganhar UC agora: caso sem código vira
> **UC órfão**, que o `casos-gate` G-2 pune e que bloqueia o merge de quem for implementar
> ([proibicoes §5](../../proibicoes.md) 2026-07-16 — UC não é canal de pedido). O contrato
> nasce **junto** com a implementação, não antes.

| US | status | Título |
|---|---|---|
| US-OI-001 | `desconhecido` | — F2: Pest baseline do comportamento atual |
| US-OI-002 | `desconhecido` | — F2: action dual + feature flag `useV2OfficeimpressoLogs` |
| US-OI-003 | `desconhecido` | — F2: mapa de paridade Blade↔React |
| US-OI-004 | `desconhecido` | — F3: tela `Logs/Index` (Máquinas Cadastradas) em PT-01 |
| US-OI-005 | `desconhecido` | — F3: tela `Logs/Timeline` (acessos por máquina) em PT-07 |
| US-OI-006 | `desconhecido` | — F4: QA — os itens `alta` da paridade viram teste |
| US-OI-007 | `desconhecido` | — F5: cutover e sunset do Blade |
| US-OI-008 | `desconhecido` | — F2: baseline, payload seguro e flag da `Licencas/Index` |
| US-OI-009 | `desconhecido` | — F3: tela `Licencas/Index` (Computadores Cadastrados) em PT-01 |
| US-OI-010 | `desconhecido` | — F4: QA da `Licencas/Index` — os itens `alta` viram teste |
| US-OI-011 | `desconhecido` | — F2: baseline, payload seguro e flag da `Empresa/Show` |
| US-OI-012 | `desconhecido` | — F3: tela `Empresa/Show` (ficha + computadores) em PT-03 |
| US-OI-013 | `desconhecido` | — F4: QA da `Empresa/Show` — os itens `alta` viram teste |
| US-OI-014 | `desconhecido` | — F2: baseline, payload seguro e flag da `Empresas/Index` |
| US-OI-015 | `desconhecido` | — F3: tela `Empresas/Index` (Empresas Licenciadas) em PT-01 |
| US-OI-016 | `desconhecido` | — F4: QA da `Empresas/Index` — os itens `alta` viram teste |
| US-OI-017 | `desconhecido` | — F5: cutover das 3 telas da Onda 2 e sunset dos Blades |

## UC por status

| UC | Tela | Status |
|---|---|---|
| UC-LOGS-01 | Logs/Index | 🧪 aguarda veredito da lane |
| UC-LOGS-02 | Logs/Index | 🧪 aguarda veredito da lane |
| UC-LOGS-03 | Logs/Index | 🧪 aguarda veredito da lane |
| UC-LOGS-04 | Logs/Index | 🧪 aguarda veredito da lane |
| UC-LOGS-05 | Logs/Index | 🧪 aguarda veredito da lane |
| UC-LOGS-06 | Logs/Index | 🧪 aguarda veredito da lane |
| UC-LOGS-07 | Logs/Index | 🧪 aguarda veredito da lane |
| UC-LOGS-08 | Logs/Index | 🧪 aguarda veredito da lane |
| UC-LOGS-09 | Logs/Index | 🧪 aguarda veredito da lane |
| UC-LOGS-10 | Logs/Index | 🧪 aguarda veredito da lane |
| UC-LOGS-11 | Logs/Index | 🧪 aguarda veredito da lane |
| UC-LOGS-12 | Logs/Index | 🧪 aguarda veredito da lane |
| UC-LOGS-13 | Logs/Index | 🧪 aguarda veredito da lane |
| UC-TL-01 | Logs/Timeline | 📝 sem_teste |
| UC-TL-02 | Logs/Timeline | 🧪 aguarda veredito da lane |
| UC-TL-05 | Logs/Timeline | 🧪 aguarda veredito da lane |
| UC-TL-06 | Logs/Timeline | 🧪 aguarda veredito da lane |
| UC-TL-07 | Logs/Timeline | 🧪 aguarda veredito da lane |
| UC-TL-08 | Logs/Timeline | 🧪 aguarda veredito da lane |
| UC-TL-09 | Logs/Timeline | 🧪 aguarda veredito da lane |
| UC-TL-10 | Logs/Timeline | 🧪 aguarda veredito da lane |

---

**Como este arquivo cresce:** cada linha da tabela "onde a cadeia quebra" é o **próximo
requisito a escrever**. Fechou? Re-rode e ela some. Descobriu que NÃO se deve fazer?
Então não é lacuna — é **Non-Goal no charter** (só [W] preenche) ou entrada no **§5 de
`proibicoes.md`** se for padrão a nunca repetir. As duas saídas são legítimas; deixar
a lacuna aberta sem decisão é a única que não é.
