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
| US no SPEC | 30 |
| CU no SDD | 16 |
| Telas (.tsx) | 7 |
| Telas com `casos.md` | 9 |
| UC declarados | 148 |
| UC com teste que os cita | 142 |

## Onde a cadeia QUEBRA — esta é a fila de crescimento

_Nenhuma lacuna: toda tela tem caso **com UC**, todo CU é citado, e toda US **entregue** tem contrato._

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
| US-FISCAL-015 | `desconhecido` | ⌘K palette cross-fiscal — ✅ PR #7 Wave |
| US-FISCAL-016 | `desconhecido` | Gerador SPED EFD-ICMS/IPI MVP — ✅ PR #8 Wave |
| US-FISCAL-017 | `desconhecido` | SPED EFD-ICMS/IPI Bloco E + Bloco H — ✅ PR #9 Wave |
| US-FISCAL-011 | `desconhecido` | SPED Fiscal complete + PIS/COFINS — **backlog PR #10** |
| US-FISCAL-018 | `desconhecido` | Habilitar cockpit Fiscal Larissa biz=4 + canary 7d smoke |
| US-FISCAL-021 | `todo` | IBS/CBS cálculo no MotorTributarioService (Onda 6 — sair do scaffold) |
| US-FISCAL-024 | `todo` | IBS/CBS — split UF/Município na régua fiscal (coluna de schema) |
| US-FISCAL-025 | `todo` | IBS/CBS na NF-e de todo emissor do regime normal — **Legal** |
| US-FISCAL-026 | `todo` | IBS/CBS na DPS da NFS-e nacional — **Legal** |
| US-FISCAL-027 | `todo` | NFS-e em município fora do padrão nacional — **Legal** |
| US-FISCAL-028 | `todo` | SPED EFD-ICMS/IPI blocos D e K — **P2** |
| US-FISCAL-029 | `todo` | MDF-e e CT-e no módulo novo — **P2** |
| US-FISCAL-031 | `todo` | Split payment (LC 214/2025) e EFD-Reinf — **P3** |

## UC por status

| UC | Tela | Status |
|---|---|---|
| UC-01 | apuracao-das (blade) | 🧪 aguarda veredito da lane |
| UC-06 | Dfe | 🧪 aguarda veredito da lane |
| UC-10 | Dfe | 🧪 aguarda veredito da lane |
| UC-DAS-01 | apuracao-das (blade) | 🧪 aguarda veredito da lane |
| UC-DAS-02 | apuracao-das (blade) | 🧪 aguarda veredito da lane |
| UC-DAS-03 | apuracao-das (blade) | 🧪 aguarda veredito da lane |
| UC-DAS-04 | apuracao-das (blade) | 🧪 aguarda veredito da lane |
| UC-DAS-05 | apuracao-das (blade) | 🧪 aguarda veredito da lane |
| UC-FCERT-01 | cert-health-check (blade) | 🧪 aguarda veredito da lane |
| UC-FCERT-02 | cert-health-check (blade) | 🧪 aguarda veredito da lane |
| UC-FCERT-03 | cert-health-check (blade) | 🧪 aguarda veredito da lane |
| UC-FCERT-04 | cert-health-check (blade) | 🧪 aguarda veredito da lane |
| UC-FCERT-05 | cert-health-check (blade) | 🧪 aguarda veredito da lane |
| UC-FCERT-06 | cert-health-check (blade) | 🧪 aguarda veredito da lane |
| UC-FCFG-01 | Config | 🧪 aguarda veredito da lane |
| UC-FCFG-02 | Config | 🧪 aguarda veredito da lane |
| UC-FCFG-03 | Config | 🧪 aguarda veredito da lane |
| UC-FCFG-04 | Config | 🧪 aguarda veredito da lane |
| UC-FCFG-05 | Config | 🧪 aguarda veredito da lane |
| UC-FCFG-06 | Config | 🧪 aguarda veredito da lane |
| UC-FCFG-07 | Config | 🧪 aguarda veredito da lane |
| UC-FCFG-08 | Config | 🧪 aguarda veredito da lane |
| UC-FCFG-09 | Config | 🧪 aguarda veredito da lane |
| UC-FCFG-10 | Config | 🧪 aguarda veredito da lane |
| UC-FCKP-01 | Cockpit | 🧪 aguarda veredito da lane |
| UC-FCKP-02 | Cockpit | 🧪 aguarda veredito da lane |
| UC-FCKP-03 | Cockpit | 🧪 aguarda veredito da lane |
| UC-FCKP-04 | Cockpit | 🧪 aguarda veredito da lane |
| UC-FCKP-05 | Cockpit | 🧪 aguarda veredito da lane |
| UC-FCKP-06 | Cockpit | 🧪 aguarda veredito da lane |
| UC-FCKP-07 | Cockpit | 🧪 aguarda veredito da lane |
| UC-FCKP-08 | Cockpit | 🧪 aguarda veredito da lane |
| UC-FCKP-09 | Cockpit | 🧪 aguarda veredito da lane |
| UC-FCKP-10 | Cockpit | 🧪 aguarda veredito da lane |
| UC-FCKP-11 | Cockpit | 🧪 aguarda veredito da lane |
| UC-FCKP-12 | Cockpit | 🧪 aguarda veredito da lane |
| UC-FCKP-13 | Cockpit | 🧪 aguarda veredito da lane |
| UC-FCKP-14 | Cockpit | 🧪 aguarda veredito da lane |
| UC-FDFE-01 | Dfe | 🧪 aguarda veredito da lane |
| UC-FDFE-02 | Dfe | 🧪 aguarda veredito da lane |
| UC-FDFE-03 | Dfe | 🧪 aguarda veredito da lane |
| UC-FDFE-04 | Dfe | 🧪 aguarda veredito da lane |
| UC-FDFE-05 | Dfe | 🧪 aguarda veredito da lane |
| UC-FDFE-06 | Dfe | 🧪 aguarda veredito da lane |
| UC-FDFE-07 | Dfe | 🧪 aguarda veredito da lane |
| UC-FDFE-08 | Dfe | 🧪 aguarda veredito da lane |
| UC-FDFE-09 | Dfe | 🧪 aguarda veredito da lane |
| UC-FDFE-10 | Dfe | 🧪 aguarda veredito da lane |
| UC-FDFE-11 | Dfe | 🧪 aguarda veredito da lane |
| UC-FDFE-12 | Dfe | 🧪 aguarda veredito da lane |
| UC-FDFE-13 | Cockpit | 🧪 aguarda veredito da lane |
| UC-FEVT-01 | Eventos | 🧪 aguarda veredito da lane |
| UC-FEVT-02 | Eventos | 🧪 aguarda veredito da lane |
| UC-FEVT-03 | Eventos | 🧪 aguarda veredito da lane |
| UC-FEVT-04 | Eventos | 🧪 aguarda veredito da lane |
| UC-FEVT-05 | Eventos | 🧪 aguarda veredito da lane |
| UC-FEVT-06 | Eventos | 🧪 aguarda veredito da lane |
| UC-FEVT-07 | Eventos | 🧪 aguarda veredito da lane |
| UC-FEVT-08 | Eventos | 🧪 aguarda veredito da lane |
| UC-FLX-26 | Nfe | 🧪 aguarda veredito da lane |
| UC-FLX-27 | Nfse | 🧪 aguarda veredito da lane |
| UC-FLX-31 | Nfse | 🧪 aguarda veredito da lane |
| UC-FLX-32 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-01 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-02 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-03 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-04 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-05 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-06 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-07 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-08 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-09 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-10 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-11 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-12 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-14 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-15 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-16 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-17 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-18 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-19 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-20 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-21 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-22 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-23 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-30 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-31 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-32 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-33 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-34 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-35 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-36 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-37 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-38 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-39 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-40 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-41 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-42 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-43 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-47 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-48 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-49 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-50 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-51 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-52 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-53 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-54 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-55 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFE-56 | Nfe | 🧪 aguarda veredito da lane |
| UC-FNFSE-01 | Nfse | 🧪 aguarda veredito da lane |
| UC-FNFSE-02 | Nfse | 🧪 aguarda veredito da lane |
| UC-FNFSE-03 | Nfse | 🧪 aguarda veredito da lane |
| UC-FNFSE-04 | Nfse | 🧪 aguarda veredito da lane |
| UC-FNFSE-05 | Nfse | 🧪 aguarda veredito da lane |
| UC-FNFSE-06 | Nfse | 🧪 aguarda veredito da lane |
| UC-FNFSE-07 | Nfse | 🧪 aguarda veredito da lane |
| UC-FNFSE-08 | Nfse | 🧪 aguarda veredito da lane |
| UC-FNFSE-09 | Nfse | 🧪 aguarda veredito da lane |
| UC-FNFSE-10 | Nfse | 🧪 aguarda veredito da lane |
| UC-FNFSE-11 | Nfse | 🧪 aguarda veredito da lane |
| UC-FNFSE-12 | Nfe | 🧪 aguarda veredito da lane |
| UC-FSF1-01 | Sped | 🧪 aguarda veredito da lane |
| UC-FSF1-02 | Sped | 🧪 aguarda veredito da lane |
| UC-FSF1-03 | Sped | 🧪 aguarda veredito da lane |
| UC-FSF1-04 | Sped | 🧪 aguarda veredito da lane |
| UC-FSF1-05 | Config | 🧪 aguarda veredito da lane |
| UC-FSF1-06 | Sped | 🧪 aguarda veredito da lane |
| UC-FSF1-07 | Sped | 🧪 aguarda veredito da lane |
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
| UC-FSPED-11 | Sped | 🧪 aguarda veredito da lane |
| UC-FSPED-12 | Sped | 🧪 aguarda veredito da lane |
| UC-FSPED-13 | Sped | 🧪 aguarda veredito da lane |
| UC-FSPED-14 | Sped | 🧪 aguarda veredito da lane |
| UC-TRB-05 | Nfe | 📝 sem_teste |
| UC-TRB-13 | Nfe | 📝 sem_teste |
| UC-TRB-16 | Nfe | 📝 sem_teste |
| UC-TRB-23 | Nfe | 📝 sem_teste |
| UC-TRB-24 | Nfe | 📝 sem_teste |
| UC-TRB-33 | Nfe | 📝 sem_teste |

---

**Como este arquivo cresce:** cada linha da tabela "onde a cadeia quebra" é o **próximo
requisito a escrever**. Fechou? Re-rode e ela some. Descobriu que NÃO se deve fazer?
Então não é lacuna — é **Non-Goal no charter** (só [W] preenche) ou entrada no **§5 de
`proibicoes.md`** se for padrão a nunca repetir. As duas saídas são legítimas; deixar
a lacuna aberta sem decisão é a única que não é.
