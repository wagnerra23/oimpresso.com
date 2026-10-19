---
authority: generated
---

<!-- GERADO por scripts/governance/requisitos-status.mjs — NÃO editar à mão.
     Status é DERIVADO da cadeia US→CU→UC→teste. Editar aqui não muda nada:
     mude o SPEC/SDD/casos/teste e re-rode. (ADR 0256: derivado sobrevive.) -->

# Requisitos — Fisio · status derivado

> **Cadeia medida:** `US (SPEC) → CU (SDD §6) → UC (casos.md) → teste → veredito`.
> O veredito final (✅/❌) vem da **lane de CI**, nunca deste gerador — status aqui
> nunca afirma verde sem execução (G-7 · [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)).

## Placar da cadeia

| Elo | Quantidade |
|---|---:|
| US no SPEC | 12 |
| CU no SDD | 0 |
| Telas (.tsx) | 5 |
| Telas com `casos.md` | 6 |
| UC declarados | 89 |
| UC com teste que os cita | 89 |

## Onde a cadeia QUEBRA — esta é a fila de crescimento

| Lacuna | O que falta escrever |
|---|---|
| `US-FISIO-003` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Schema, models e append-only de prontuário e ledger do jogo  |
| `US-FISIO-004` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Biblioteca semeada e revisada pela responsável técnica — **P |
| `US-FISIO-005` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Pacientes e prontuário conforme Res. COFFITO 414/2012 — **P0 |
| `US-FISIO-006` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Editor de programa, protocolos-modelo e aviso de contraindic |
| `US-FISIO-010` **entregue sem contrato** (`status: doing`) | UC que prove o que foi entregue — Painel, evolução e fila de atenção — **P0** |
| `US-FISIO-011` **entregue sem contrato** (`status: doing`) | UC que prove o que foi entregue — Questionários de uso livre (EVA, RMDQ, PSFS, LEFS, SPADI) —  |

### Backlog — NÃO é lacuna

> US ainda não entregue (`todo`/`backlog`) **não deve** ganhar UC agora: caso sem código vira
> **UC órfão**, que o `casos-gate` G-2 pune e que bloqueia o merge de quem for implementar
> ([proibicoes §5](../../proibicoes.md) 2026-07-16 — UC não é canal de pedido). O contrato
> nasce **junto** com a implementação, não antes.

| US | status | Título |
|---|---|---|
| US-FISIO-001 | `todo` | Trio SDD da F1 e US no SPEC — **P0** |
| US-FISIO-002 | `todo` | Esqueleto `Modules/Fisio` (ativação por empresa, permissões, menu condicionado)  |
| US-FISIO-012 | `todo` | Fecha o loop da F1 — **P1** |

## UC por status

| UC | Tela | Status |
|---|---|---|
| UC-FBI-01 | Biblioteca/Index | 🧪 aguarda veredito da lane |
| UC-FBI-02 | Biblioteca/Index | 🧪 aguarda veredito da lane |
| UC-FBI-03 | Biblioteca/Index | 🧪 aguarda veredito da lane |
| UC-FBI-04 | Biblioteca/Index | 🧪 aguarda veredito da lane |
| UC-FBI-05 | Biblioteca/Index | 🧪 aguarda veredito da lane |
| UC-FBI-06 | Biblioteca/Index | 🧪 aguarda veredito da lane |
| UC-FBI-07 | Biblioteca/Index | 🧪 aguarda veredito da lane |
| UC-FBI-08 | Biblioteca/Index | 🧪 aguarda veredito da lane |
| UC-FBI-09 | Biblioteca/Index | 🧪 aguarda veredito da lane |
| UC-FBI-10 | Biblioteca/Index | 🧪 aguarda veredito da lane |
| UC-FBI-11 | Biblioteca/Index | 🧪 aguarda veredito da lane |
| UC-FJG-01 | jogo-constancia (blade) | 🧪 aguarda veredito da lane |
| UC-FJG-02 | jogo-constancia (blade) | 🧪 aguarda veredito da lane |
| UC-FJG-03 | jogo-constancia (blade) | 🧪 aguarda veredito da lane |
| UC-FJG-04 | jogo-constancia (blade) | 🧪 aguarda veredito da lane |
| UC-FJG-05 | jogo-constancia (blade) | 🧪 aguarda veredito da lane |
| UC-FPI-01 | Pacientes/Index | 🧪 aguarda veredito da lane |
| UC-FPI-02 | Pacientes/Index | 🧪 aguarda veredito da lane |
| UC-FPI-03 | Pacientes/Index | 🧪 aguarda veredito da lane |
| UC-FPI-04 | Pacientes/Index | 🧪 aguarda veredito da lane |
| UC-FPI-05 | Pacientes/Index | 🧪 aguarda veredito da lane |
| UC-FPI-06 | Pacientes/Index | 🧪 aguarda veredito da lane |
| UC-FPI-07 | Pacientes/Index | 🧪 aguarda veredito da lane |
| UC-FPI-20 | Pacientes/Index | 🧪 aguarda veredito da lane |
| UC-FPN-01 | Painel | 🧪 aguarda veredito da lane |
| UC-FPN-02 | Painel | 🧪 aguarda veredito da lane |
| UC-FPN-03 | Painel | 🧪 aguarda veredito da lane |
| UC-FPN-04 | Painel | 🧪 aguarda veredito da lane |
| UC-FPN-05 | Painel | 🧪 aguarda veredito da lane |
| UC-FPN-06 | Painel | 🧪 aguarda veredito da lane |
| UC-FPN-07 | Painel | 🧪 aguarda veredito da lane |
| UC-FPN-08 | Painel | 🧪 aguarda veredito da lane |
| UC-FPP-01 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-02 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-03 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-04 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-05 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-06 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-07 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-08 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-09 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-10 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-11 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-12 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-13 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-14 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-15 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-16 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-17 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-18 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-19 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-20 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-21 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-22 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-23 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-24 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-25 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-26 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-27 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-28 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPS-01 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-02 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-03 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-04 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-05 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-06 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-07 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-08 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-09 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-10 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-11 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-12 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-13 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-14 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-15 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-16 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-17 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-18 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-19 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-20 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-21 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-30 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-31 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-32 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-33 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-34 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-35 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-36 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-OAP-01 | Public/Portal | 🧪 aguarda veredito da lane |

---

**Como este arquivo cresce:** cada linha da tabela "onde a cadeia quebra" é o **próximo
requisito a escrever**. Fechou? Re-rode e ela some. Descobriu que NÃO se deve fazer?
Então não é lacuna — é **Non-Goal no charter** (só [W] preenche) ou entrada no **§5 de
`proibicoes.md`** se for padrão a nunca repetir. As duas saídas são legítimas; deixar
a lacuna aberta sem decisão é a única que não é.
