---
authority: generated
---

<!-- GERADO por scripts/governance/requisitos-status.mjs — NÃO editar à mão.
     Status é DERIVADO da cadeia US→CU→UC→teste. Editar aqui não muda nada:
     mude o SPEC/SDD/casos/teste e re-rode. (ADR 0256: derivado sobrevive.) -->

# Requisitos — Fiscal · status derivado

> **Cadeia medida:** `US (SPEC) → CU (SDD §6) → UC (casos.md) → teste → veredito`.
> O veredito final (✅/❌) vem da **lane de CI**, nunca deste gerador — status aqui
> nunca afirma verde sem execução (G-7 · [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)).

## Placar da cadeia

| Elo | Quantidade |
|---|---:|
| US no SPEC | 23 |
| CU no SDD | 16 |
| Telas (.tsx) | 7 |
| Telas com `casos.md` | 7 |
| UC declarados | 40 |
| UC com teste que os cita | 40 |

## Onde a cadeia QUEBRA — esta é a fila de crescimento

| Lacuna | O que falta escrever |
|---|---|
| `CU-FISC-02` sem UC | caso de uso que o exercite — Conferir NF-e/NFC-e com status SEFAZ legível |
| `CU-FISC-03` sem UC | caso de uso que o exercite — Agir dentro da janela legal de cancelamento |
| `CU-FISC-08` sem UC | caso de uso que o exercite — Cancelar NF-e autorizada com justificativa CONFAZ |
| `CU-FISC-09` sem UC | caso de uso que o exercite — Aplicar Carta de Correção (CC-e 110110) |
| `CU-FISC-10` sem UC | caso de uso que o exercite — Inutilizar faixa numérica |
| `CU-FISC-11` sem UC | caso de uso que o exercite — Retransmitir sem apagar a nota antiga |
| `CU-FISC-15` sem UC | caso de uso que o exercite — Gerar o SPED EFD-ICMS/IPI da competência |
| `CU-FISC-16` sem UC | caso de uso que o exercite — Distinguir dado real de dado de demonstração |

### Backlog — NÃO é lacuna

> US ainda não entregue (`todo`/`backlog`) **não deve** ganhar UC agora: caso sem código vira
> **UC órfão**, que o `casos-gate` G-2 pune e que bloqueia o merge de quem for implementar
> ([proibicoes §5](../../proibicoes.md) 2026-07-16 — UC não é canal de pedido). O contrato
> nasce **junto** com a implementação, não antes.

| US | status | Título |
|---|---|---|
| US-FISCAL-001 | `pr` | Cockpit NF-e · NFC-e (sub-página 2) |
| US-FISCAL-003 | `desconhecido` | ⌘K palette cross-fiscal — **backlog PR #3** |
| US-FISCAL-004 | `desconhecido` | Ações de mutação — **backlog PR #4** |
| US-FISCAL-006 | `desconhecido` | Manifesto DF-e (sub-página 4) — **backlog PR #6** |
| US-FISCAL-010 | `desconhecido` | SPED & Livros (sub-página 7) — ✅ PR #3 Wave (placeholder) |
| US-FISCAL-013 | `desconhecido` | CC-e (Carta de Correção) + Inutilização faixa — ✅ PR #5 Wave |
| US-FISCAL-014 | `desconhecido` | Retransmitir NFe rejeitada/denegada — ✅ PR #6 Wave |
| US-FISCAL-015 | `desconhecido` | ⌘K palette cross-fiscal — ✅ PR #7 Wave |
| US-FISCAL-016 | `desconhecido` | Gerador SPED EFD-ICMS/IPI MVP — ✅ PR #8 Wave |
| US-FISCAL-017 | `desconhecido` | SPED EFD-ICMS/IPI Bloco E + Bloco H — ✅ PR #9 Wave |
| US-FISCAL-011 | `desconhecido` | SPED Fiscal complete + PIS/COFINS — **backlog PR #10** |
| US-FISCAL-018 | `desconhecido` | Habilitar cockpit Fiscal Larissa biz=4 + canary 7d smoke |
| US-FISCAL-021 | `todo` | IBS/CBS cálculo no MotorTributarioService (Onda 6 — sair do scaffold) |
| US-FISCAL-022 | `todo` | Health-check certificado A1 (cron alerta vencimento) |
| US-FISCAL-024 | `todo` | IBS/CBS — split UF/Município na régua fiscal (coluna de schema) |

## UC por status

| UC | Tela | Status |
|---|---|---|
| UC-FCFG-01 | Config | 🧪 aguarda veredito da lane |
| UC-FCFG-02 | Config | 🧪 aguarda veredito da lane |
| UC-FCFG-03 | Config | 🧪 aguarda veredito da lane |
| UC-FCKP-01 | Cockpit | 🧪 aguarda veredito da lane |
| UC-FCKP-02 | Cockpit | 🧪 aguarda veredito da lane |
| UC-FCKP-03 | Cockpit | 🧪 aguarda veredito da lane |
| UC-FCKP-04 | Cockpit | 🧪 aguarda veredito da lane |
| UC-FCKP-05 | Cockpit | 🧪 aguarda veredito da lane |
| UC-FCKP-06 | Cockpit | 🧪 aguarda veredito da lane |
| UC-FDFE-01 | Dfe | 🧪 aguarda veredito da lane |
| UC-FDFE-02 | Dfe | 🧪 aguarda veredito da lane |
| UC-FDFE-03 | Dfe | 🧪 aguarda veredito da lane |
| UC-FDFE-04 | Dfe | 🧪 aguarda veredito da lane |
| UC-FDFE-05 | Dfe | 🧪 aguarda veredito da lane |
| UC-FEVT-01 | Eventos | 🧪 aguarda veredito da lane |
| UC-FEVT-02 | Eventos | 🧪 aguarda veredito da lane |
| UC-FEVT-03 | Eventos | 🧪 aguarda veredito da lane |
| UC-FEVT-04 | Eventos | 🧪 aguarda veredito da lane |
| UC-FNFE-01 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-02 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-03 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-04 | Eventos | 🧪 aguarda veredito da lane |
| UC-FNFE-05 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-06 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-07 | Dfe | 🧪 aguarda veredito da lane |
| UC-FNFE-08 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFSE-01 | Nfse | 🧪 aguarda veredito da lane |
| UC-FNFSE-02 | Nfse | 🧪 aguarda veredito da lane |
| UC-FNFSE-03 | Nfse | 🧪 aguarda veredito da lane |
| UC-FNFSE-04 | Nfse | 🧪 aguarda veredito da lane |
| UC-FSPED-01 | Sped | 🧪 aguarda veredito da lane |
| UC-FSPED-02 | Sped | 🧪 aguarda veredito da lane |
| UC-FSPED-03 | Sped | 🧪 aguarda veredito da lane |
| UC-FSPED-04 | Sped | 🧪 aguarda veredito da lane |
| UC-FSPED-05 | Config | 🧪 aguarda veredito da lane |
| UC-FSPED-06 | Sped | 🧪 aguarda veredito da lane |
| UC-FSPED-07 | Sped | 🧪 aguarda veredito da lane |
| UC-FSPED-08 | Sped | 🧪 aguarda veredito da lane |
| UC-FSPED-09 | Sped | 🧪 aguarda veredito da lane |
| UC-FSPED-10 | Sped | 🧪 aguarda veredito da lane |

---

**Como este arquivo cresce:** cada linha da tabela "onde a cadeia quebra" é o **próximo
requisito a escrever**. Fechou? Re-rode e ela some. Descobriu que NÃO se deve fazer?
Então não é lacuna — é **Non-Goal no charter** (só [W] preenche) ou entrada no **§5 de
`proibicoes.md`** se for padrão a nunca repetir. As duas saídas são legítimas; deixar
a lacuna aberta sem decisão é a única que não é.
