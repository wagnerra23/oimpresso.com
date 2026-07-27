<!-- GERADO por scripts/governance/requisitos-status.mjs — NÃO editar à mão.
     Status é DERIVADO da cadeia US→CU→UC→teste. Editar aqui não muda nada:
     mude o SPEC/SDD/casos/teste e re-rode. (ADR 0256: derivado sobrevive.) -->

# Requisitos — Cliente · status derivado

> **Cadeia medida:** `US (SPEC) → CU (SDD §6) → UC (casos.md) → teste → veredito`.
> O veredito final (✅/❌) vem da **lane de CI**, nunca deste gerador — status aqui
> nunca afirma verde sem execução (G-7 · [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)).

## Placar da cadeia

| Elo | Quantidade |
|---|---:|
| US no SPEC | 22 |
| CU no SDD | 15 |
| Telas (.tsx) | 7 |
| Telas com `casos.md` | 7 |
| UC declarados | 22 |
| UC com teste que os cita | 22 |

## Onde a cadeia QUEBRA — esta é a fila de crescimento

| Lacuna | O que falta escrever |
|---|---|
| `CU-CLI-04` sem UC | caso de uso que o exercite — Autopreencher por CNPJ e CEP |
| `CU-CLI-12` sem UC | caso de uso que o exercite — Atender o titular do dado (esquecimento e portabilidade) |
| `CU-CLI-15` sem UC | caso de uso que o exercite — Não perder as abas de fornecedor do contato |
| `US-CRM-066` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — — Tab Documents & Note no Show |
| `US-CRM-067` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — — ActionsMenu + AddDiscountModal no Show |
| `US-CRM-068` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — — Tab Pessoas de contato no Show |
| `US-CRM-069` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — — Tab Assinaturas (subscriptions) no Show |
| `US-CRM-070` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — — Tab Reward Points no Show |
| `US-CRM-071` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — — KB-9.75 Slice A no Index |
| `US-CRM-072` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — — Restaurar campos fiscais BR perdidos no upgrade UPOS 6.7 |
| `US-CRM-073` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — — UI campos BR em Create/Edit/Show |
| `US-CRM-074` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — — Comando artisan backfill cpf_cnpj |
| `US-CRM-075` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — — BrasilAPI lookup CNPJ + botão Buscar |
| `US-CRM-078` **entregue sem contrato** (`status: doing`) | UC que prove o que foi entregue — — Múltiplos endereços por contato + seletor de endereço na v |

### Backlog — NÃO é lacuna

> US ainda não entregue (`todo`/`backlog`) **não deve** ganhar UC agora: caso sem código vira
> **UC órfão**, que o `casos-gate` G-2 pune e que bloqueia o merge de quem for implementar
> ([proibicoes §5](../../proibicoes.md) 2026-07-16 — UC não é canal de pedido). O contrato
> nasce **junto** com a implementação, não antes.

| US | status | Título |
|---|---|---|
| US-CRM-079 | `todo` | — Anonimização fiscal-aware do titular (DsrService → contacts) — LGPD Art. 18 |
| US-CRM-080 | `todo` | — Teste cross-tenant no App\Contact pai + avaliar global scope (Tier 0) |
| US-CRM-081 | `todo` | — Limite de crédito com bloqueio/aviso na venda (wirar enforcement) |
| US-CRM-082 | `todo` | — Import de clientes com preview + dedupe/merge (CPF/CNPJ) |
| US-CRM-083 | `todo` | — UI de consentimento (opt-in/opt-out) + base legal por finalidade |
| US-CRM-084 | `todo` | — Extrato (Ledger) render inline 100% — parar de abrir Blade legacy ao filtrar |
| US-CRM-085 | `todo` | — Export de portabilidade do titular (registro completo CSV/JSON) — LGPD Art. 18 |

## UC por status

| UC | Tela | Status |
|---|---|---|
| UC-CCRE-01 | Create | 🧪 aguarda veredito da lane |
| UC-CCRE-02 | Create | 🧪 aguarda veredito da lane |
| UC-CCRE-03 | Create | 🧪 aguarda veredito da lane |
| UC-CEDI-01 | Edit | 🧪 aguarda veredito da lane |
| UC-CEDI-02 | Edit | 🧪 aguarda veredito da lane |
| UC-CEDI-03 | Edit | 🧪 aguarda veredito da lane |
| UC-CEDI-04 | Edit | 🧪 aguarda veredito da lane |
| UC-CIDX-01 | Index | 🧪 aguarda veredito da lane |
| UC-CIDX-02 | Index | 🧪 aguarda veredito da lane |
| UC-CIDX-03 | Index | 🧪 aguarda veredito da lane |
| UC-CIDX-04 | Index | 🧪 aguarda veredito da lane |
| UC-CIMP-01 | Import | 🧪 aguarda veredito da lane |
| UC-CLED-01 | Ledger | 🧪 aguarda veredito da lane |
| UC-CLED-02 | Ledger | 🧪 aguarda veredito da lane |
| UC-CLED-03 | Ledger | 🧪 aguarda veredito da lane |
| UC-CLED-04 | Ledger | 🧪 aguarda veredito da lane |
| UC-CLED-05 | Ledger | 🧪 aguarda veredito da lane |
| UC-CMAP-01 | Map | 🧪 aguarda veredito da lane |
| UC-CMAP-02 | Map | 🧪 aguarda veredito da lane |
| UC-CSHW-01 | Show | 🧪 aguarda veredito da lane |
| UC-CSHW-02 | Show | 🧪 aguarda veredito da lane |
| UC-CSHW-03 | Show | 🧪 aguarda veredito da lane |

---

**Como este arquivo cresce:** cada linha da tabela "onde a cadeia quebra" é o **próximo
requisito a escrever**. Fechou? Re-rode e ela some. Descobriu que NÃO se deve fazer?
Então não é lacuna — é **Non-Goal no charter** (só [W] preenche) ou entrada no **§5 de
`proibicoes.md`** se for padrão a nunca repetir. As duas saídas são legítimas; deixar
a lacuna aberta sem decisão é a única que não é.
