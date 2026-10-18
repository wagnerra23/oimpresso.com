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
| Telas (.tsx) | 2 |
| Telas com `casos.md` | 5 |
| UC declarados | 48 |
| UC com teste que os cita | 48 |

## Onde a cadeia QUEBRA — esta é a fila de crescimento

_Nenhuma lacuna: toda tela tem caso **com UC**, todo CU é citado, e toda US **entregue** tem contrato._

### Backlog — NÃO é lacuna

> US ainda não entregue (`todo`/`backlog`) **não deve** ganhar UC agora: caso sem código vira
> **UC órfão**, que o `casos-gate` G-2 pune e que bloqueia o merge de quem for implementar
> ([proibicoes §5](../../proibicoes.md) 2026-07-16 — UC não é canal de pedido). O contrato
> nasce **junto** com a implementação, não antes.

| US | status | Título |
|---|---|---|
| US-FISIO-001 | `todo` | Trio SDD da F1 e US no SPEC — **P0** |
| US-FISIO-002 | `todo` | Esqueleto `Modules/Fisio` (ativação por empresa, permissões, menu condicionado)  |
| US-FISIO-003 | `todo` | Schema, models e append-only de prontuário e ledger do jogo — **P0** |
| US-FISIO-004 | `todo` | Biblioteca semeada e revisada pela responsável técnica — **P0** |
| US-FISIO-006 | `todo` | Editor de programa, protocolos-modelo e aviso de contraindicação — **P0** |
| US-FISIO-007 | `todo` | Portal do paciente (link + PIN, consentimentos, check-in, telemonitoramento) — * |
| US-FISIO-008 | `todo` | Jogo de constância (XP, nível, sequência, missões, pausa por dor) — **P1** |
| US-FISIO-009 | `todo` | Lembrete diário e envio do programa por WhatsApp (driver whatsmeow) — **P0** |
| US-FISIO-010 | `todo` | Painel, evolução e fila de atenção — **P0** |
| US-FISIO-011 | `todo` | Questionários de uso livre (EVA, RMDQ, PSFS, LEFS, SPADI) — **P1** |
| US-FISIO-012 | `todo` | Fecha o loop da F1 — **P1** |

## UC por status

| UC | Tela | Status |
|---|---|---|
| UC-FBI-01 | Biblioteca/Index | 🧪 aguarda veredito da lane |
| UC-FBI-02 | Biblioteca/Index | 🧪 aguarda veredito da lane |
| UC-FBI-03 | Biblioteca/Index | 🧪 aguarda veredito da lane |
| UC-FBI-04 | Biblioteca/Index | 🧪 aguarda veredito da lane |
| UC-FPI-01 | Pacientes/Index | 🧪 aguarda veredito da lane |
| UC-FPI-02 | Pacientes/Index | 🧪 aguarda veredito da lane |
| UC-FPI-03 | Pacientes/Index | 🧪 aguarda veredito da lane |
| UC-FPI-04 | Pacientes/Index | 🧪 aguarda veredito da lane |
| UC-FPN-01 | Painel | 🧪 aguarda veredito da lane |
| UC-FPN-02 | Painel | 🧪 aguarda veredito da lane |
| UC-FPN-03 | Painel | 🧪 aguarda veredito da lane |
| UC-FPN-04 | Painel | 🧪 aguarda veredito da lane |
| UC-FPN-05 | Painel | 🧪 aguarda veredito da lane |
| UC-FPN-06 | Painel | 🧪 aguarda veredito da lane |
| UC-FPN-07 | Painel | 🧪 aguarda veredito da lane |
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
| UC-OAP-01 | Public/Portal | 🧪 aguarda veredito da lane |

---

**Como este arquivo cresce:** cada linha da tabela "onde a cadeia quebra" é o **próximo
requisito a escrever**. Fechou? Re-rode e ela some. Descobriu que NÃO se deve fazer?
Então não é lacuna — é **Non-Goal no charter** (só [W] preenche) ou entrada no **§5 de
`proibicoes.md`** se for padrão a nunca repetir. As duas saídas são legítimas; deixar
a lacuna aberta sem decisão é a única que não é.
