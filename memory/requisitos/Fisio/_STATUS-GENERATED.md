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
| US no SPEC | 28 |
| CU no SDD | 0 |
| Telas (.tsx) | 14 |
| Telas com `casos.md` | 18 |
| UC declarados | 245 |
| UC com teste que os cita | 245 |

## Onde a cadeia QUEBRA — esta é a fila de crescimento

| Lacuna | O que falta escrever |
|---|---|
| `US-FISIO-001` **entregue sem contrato** (`status: doing`) | UC que prove o que foi entregue — Trio SDD da F1 e US no SPEC — **P0** |
| `US-FISIO-002` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Esqueleto `Modules/Fisio` (ativação por empresa, permissões, |
| `US-FISIO-003` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Schema, models e append-only de prontuário e ledger do jogo  |
| `US-FISIO-004` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Biblioteca semeada e revisada pela responsável técnica — **P |
| `US-FISIO-005` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Pacientes e prontuário conforme Res. COFFITO 414/2012 — **P0 |
| `US-FISIO-010` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Painel, evolução e fila de atenção — **P0** |
| `US-FISIO-011` **entregue sem contrato** (`status: doing`) | UC que prove o que foi entregue — Questionários de uso livre (EVA, RMDQ, PSFS, LEFS, SPADI) —  |
| `US-FISIO-051` **entregue sem contrato** (`status: done`) | UC que prove o que foi entregue — Pré-anamnese no portal antes da primeira consulta — **P1** |
| `US-FISIO-013` **entregue sem contrato** (`status: doing`) | UC que prove o que foi entregue — Schema da agenda: tipos, atendimentos, bloqueios, lista de e |

### Backlog — NÃO é lacuna

> US ainda não entregue (`todo`/`backlog`) **não deve** ganhar UC agora: caso sem código vira
> **UC órfão**, que o `casos-gate` G-2 pune e que bloqueia o merge de quem for implementar
> ([proibicoes §5](../../proibicoes.md) 2026-07-16 — UC não é canal de pedido). O contrato
> nasce **junto** com a implementação, não antes.

| US | status | Título |
|---|---|---|
| US-FISIO-012 | `todo` | Fecha o loop da F1 — **P1** |
| US-FISIO-024 | `todo` | Lote do Receita Saúde no layout oficial — **P1** |
| US-FISIO-025 | `todo` | Planos do produto Fisio no Superadmin — **P1** |

## UC por status

| UC | Tela | Status |
|---|---|---|
| UC-FAG-01 | Agenda/Index | 🧪 aguarda veredito da lane |
| UC-FAG-02 | Agenda/Index | 🧪 aguarda veredito da lane |
| UC-FAG-03 | Agenda/Index | 🧪 aguarda veredito da lane |
| UC-FAG-04 | Agenda/Index | 🧪 aguarda veredito da lane |
| UC-FAG-05 | Agenda/Index | 🧪 aguarda veredito da lane |
| UC-FAG-06 | Agenda/Index | 🧪 aguarda veredito da lane |
| UC-FAG-07 | Agenda/Index | 🧪 aguarda veredito da lane |
| UC-FAG-08 | Agenda/Index | 🧪 aguarda veredito da lane |
| UC-FAV-01 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FAV-02 | Pacientes/Show | 🧪 aguarda veredito da lane |
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
| UC-FBI-12 | Biblioteca/Index | 🧪 aguarda veredito da lane |
| UC-FBP-01 | laudo-biopsicossocial (blade) | 🧪 aguarda veredito da lane |
| UC-FBP-02 | laudo-biopsicossocial (blade) | 🧪 aguarda veredito da lane |
| UC-FBP-03 | laudo-biopsicossocial (blade) | 🧪 aguarda veredito da lane |
| UC-FBP-04 | laudo-biopsicossocial (blade) | 🧪 aguarda veredito da lane |
| UC-FBP-05 | laudo-biopsicossocial (blade) | 🧪 aguarda veredito da lane |
| UC-FBP-06 | laudo-biopsicossocial (blade) | 🧪 aguarda veredito da lane |
| UC-FBP-07 | laudo-biopsicossocial (blade) | 🧪 aguarda veredito da lane |
| UC-FBP-08 | laudo-biopsicossocial (blade) | 🧪 aguarda veredito da lane |
| UC-FBP-09 | laudo-biopsicossocial (blade) | 🧪 aguarda veredito da lane |
| UC-FBP-10 | laudo-biopsicossocial (blade) | 🧪 aguarda veredito da lane |
| UC-FBP-11 | laudo-biopsicossocial (blade) | 🧪 aguarda veredito da lane |
| UC-FBP-12 | laudo-biopsicossocial (blade) | 🧪 aguarda veredito da lane |
| UC-FBP-13 | laudo-biopsicossocial (blade) | 🧪 aguarda veredito da lane |
| UC-FBP-14 | laudo-biopsicossocial (blade) | 🧪 aguarda veredito da lane |
| UC-FBP-15 | laudo-biopsicossocial (blade) | 🧪 aguarda veredito da lane |
| UC-FBP-16 | laudo-biopsicossocial (blade) | 🧪 aguarda veredito da lane |
| UC-FCA-01 | Configuracoes/Agendamento | 🧪 aguarda veredito da lane |
| UC-FCA-02 | Configuracoes/Agendamento | 🧪 aguarda veredito da lane |
| UC-FCA-03 | Configuracoes/Agendamento | 🧪 aguarda veredito da lane |
| UC-FCA-04 | Configuracoes/Agendamento | 🧪 aguarda veredito da lane |
| UC-FCW-01 | Configuracoes/Whatsapp | 🧪 aguarda veredito da lane |
| UC-FCW-02 | Configuracoes/Whatsapp | 🧪 aguarda veredito da lane |
| UC-FCW-03 | Configuracoes/Whatsapp | 🧪 aguarda veredito da lane |
| UC-FCW-04 | Configuracoes/Whatsapp | 🧪 aguarda veredito da lane |
| UC-FCW-05 | Configuracoes/Whatsapp | 🧪 aguarda veredito da lane |
| UC-FCW-06 | Configuracoes/Whatsapp | 🧪 aguarda veredito da lane |
| UC-FER-01 | exames-e-receitas (blade) | 🧪 aguarda veredito da lane |
| UC-FER-02 | exames-e-receitas (blade) | 🧪 aguarda veredito da lane |
| UC-FER-03 | exames-e-receitas (blade) | 🧪 aguarda veredito da lane |
| UC-FER-04 | exames-e-receitas (blade) | 🧪 aguarda veredito da lane |
| UC-FER-05 | exames-e-receitas (blade) | 🧪 aguarda veredito da lane |
| UC-FER-06 | exames-e-receitas (blade) | 🧪 aguarda veredito da lane |
| UC-FER-07 | exames-e-receitas (blade) | 🧪 aguarda veredito da lane |
| UC-FER-08 | exames-e-receitas (blade) | 🧪 aguarda veredito da lane |
| UC-FER-09 | exames-e-receitas (blade) | 🧪 aguarda veredito da lane |
| UC-FER-10 | exames-e-receitas (blade) | 🧪 aguarda veredito da lane |
| UC-FER-11 | exames-e-receitas (blade) | 🧪 aguarda veredito da lane |
| UC-FER-12 | exames-e-receitas (blade) | 🧪 aguarda veredito da lane |
| UC-FER-13 | exames-e-receitas (blade) | 🧪 aguarda veredito da lane |
| UC-FER-14 | exames-e-receitas (blade) | 🧪 aguarda veredito da lane |
| UC-FER-15 | exames-e-receitas (blade) | 🧪 aguarda veredito da lane |
| UC-FER-16 | exames-e-receitas (blade) | 🧪 aguarda veredito da lane |
| UC-FER-17 | exames-e-receitas (blade) | 🧪 aguarda veredito da lane |
| UC-FER-18 | exames-e-receitas (blade) | 🧪 aguarda veredito da lane |
| UC-FER-19 | exames-e-receitas (blade) | 🧪 aguarda veredito da lane |
| UC-FER-20 | exames-e-receitas (blade) | 🧪 aguarda veredito da lane |
| UC-FER-21 | exames-e-receitas (blade) | 🧪 aguarda veredito da lane |
| UC-FES-01 | Pesquisa/Index | 🧪 aguarda veredito da lane |
| UC-FES-02 | Pesquisa/Index | 🧪 aguarda veredito da lane |
| UC-FES-03 | Pesquisa/Index | 🧪 aguarda veredito da lane |
| UC-FES-04 | Pesquisa/Index | 🧪 aguarda veredito da lane |
| UC-FES-05 | Pesquisa/Index | 🧪 aguarda veredito da lane |
| UC-FES-06 | Pesquisa/Index | 🧪 aguarda veredito da lane |
| UC-FES-07 | Pesquisa/Index | 🧪 aguarda veredito da lane |
| UC-FES-08 | Pesquisa/Index | 🧪 aguarda veredito da lane |
| UC-FES-09 | Pesquisa/Index | 🧪 aguarda veredito da lane |
| UC-FES-10 | Pesquisa/Index | 🧪 aguarda veredito da lane |
| UC-FES-11 | Pesquisa/Index | 🧪 aguarda veredito da lane |
| UC-FES-12 | Pesquisa/Index | 🧪 aguarda veredito da lane |
| UC-FES-13 | Pesquisa/Index | 🧪 aguarda veredito da lane |
| UC-FES-14 | Pesquisa/Index | 🧪 aguarda veredito da lane |
| UC-FES-15 | Pesquisa/Index | 🧪 aguarda veredito da lane |
| UC-FES-16 | Pesquisa/Index | 🧪 aguarda veredito da lane |
| UC-FES-17 | Pesquisa/Index | 🧪 aguarda veredito da lane |
| UC-FES-18 | Pesquisa/Index | 🧪 aguarda veredito da lane |
| UC-FES-19 | Pesquisa/Index | 🧪 aguarda veredito da lane |
| UC-FES-20 | Pesquisa/Index | 🧪 aguarda veredito da lane |
| UC-FES-21 | Pesquisa/Index | 🧪 aguarda veredito da lane |
| UC-FES-22 | Pesquisa/Index | 🧪 aguarda veredito da lane |
| UC-FFI-01 | Fiscal/Index | 🧪 aguarda veredito da lane |
| UC-FFI-02 | Fiscal/Index | 🧪 aguarda veredito da lane |
| UC-FFI-03 | Fiscal/Index | 🧪 aguarda veredito da lane |
| UC-FFI-04 | Fiscal/Index | 🧪 aguarda veredito da lane |
| UC-FFI-05 | Fiscal/Index | 🧪 aguarda veredito da lane |
| UC-FJG-01 | jogo-constancia (blade) | 🧪 aguarda veredito da lane |
| UC-FJG-02 | jogo-constancia (blade) | 🧪 aguarda veredito da lane |
| UC-FJG-03 | jogo-constancia (blade) | 🧪 aguarda veredito da lane |
| UC-FJG-04 | jogo-constancia (blade) | 🧪 aguarda veredito da lane |
| UC-FJG-05 | jogo-constancia (blade) | 🧪 aguarda veredito da lane |
| UC-FJG-06 | jogo-constancia (blade) | 🧪 aguarda veredito da lane |
| UC-FJG-07 | jogo-constancia (blade) | 🧪 aguarda veredito da lane |
| UC-FJG-08 | jogo-constancia (blade) | 🧪 aguarda veredito da lane |
| UC-FJG-09 | jogo-constancia (blade) | 🧪 aguarda veredito da lane |
| UC-FLD-01 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FLD-02 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FLD-03 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FOB-01 | Onboarding | 🧪 aguarda veredito da lane |
| UC-FOB-02 | Onboarding | 🧪 aguarda veredito da lane |
| UC-FOB-03 | Onboarding | 🧪 aguarda veredito da lane |
| UC-FOB-04 | Onboarding | 🧪 aguarda veredito da lane |
| UC-FOB-05 | Onboarding | 🧪 aguarda veredito da lane |
| UC-FOB-06 | Onboarding | 🧪 aguarda veredito da lane |
| UC-FPA-01 | agenda-paciente (blade) | 🧪 aguarda veredito da lane |
| UC-FPA-02 | agenda-paciente (blade) | 🧪 aguarda veredito da lane |
| UC-FPA-03 | agenda-paciente (blade) | 🧪 aguarda veredito da lane |
| UC-FPA-04 | agenda-paciente (blade) | 🧪 aguarda veredito da lane |
| UC-FPA-05 | agenda-paciente (blade) | 🧪 aguarda veredito da lane |
| UC-FPA-06 | agenda-paciente (blade) | 🧪 aguarda veredito da lane |
| UC-FPA-07 | agenda-paciente (blade) | 🧪 aguarda veredito da lane |
| UC-FPI-01 | Pacientes/Index | 🧪 aguarda veredito da lane |
| UC-FPI-02 | Pacientes/Index | 🧪 aguarda veredito da lane |
| UC-FPI-03 | Pacientes/Index | 🧪 aguarda veredito da lane |
| UC-FPI-04 | Pacientes/Index | 🧪 aguarda veredito da lane |
| UC-FPI-05 | Pacientes/Index | 🧪 aguarda veredito da lane |
| UC-FPI-06 | Pacientes/Index | 🧪 aguarda veredito da lane |
| UC-FPI-07 | Pacientes/Index | 🧪 aguarda veredito da lane |
| UC-FPI-20 | Pacientes/Index | 🧪 aguarda veredito da lane |
| UC-FPI-21 | Pacientes/Index | 🧪 aguarda veredito da lane |
| UC-FPK-01 | Pacientes/Pacote | 🧪 aguarda veredito da lane |
| UC-FPK-02 | Pacientes/Pacote | 🧪 aguarda veredito da lane |
| UC-FPK-03 | Pacientes/Pacote | 🧪 aguarda veredito da lane |
| UC-FPK-04 | Pacientes/Pacote | 🧪 aguarda veredito da lane |
| UC-FPK-05 | Pacientes/Pacote | 🧪 aguarda veredito da lane |
| UC-FPK-06 | Pacientes/Pacote | 🧪 aguarda veredito da lane |
| UC-FPK-07 | Pacientes/Pacote | 🧪 aguarda veredito da lane |
| UC-FPK-08 | Pacientes/Pacote | 🧪 aguarda veredito da lane |
| UC-FPK-09 | Pacientes/Pacote | 🧪 aguarda veredito da lane |
| UC-FPK-10 | Pacientes/Pacote | 🧪 aguarda veredito da lane |
| UC-FPN-01 | Painel | 🧪 aguarda veredito da lane |
| UC-FPN-02 | Painel | 🧪 aguarda veredito da lane |
| UC-FPN-03 | Painel | 🧪 aguarda veredito da lane |
| UC-FPN-04 | Painel | 🧪 aguarda veredito da lane |
| UC-FPN-05 | Painel | 🧪 aguarda veredito da lane |
| UC-FPN-06 | Painel | 🧪 aguarda veredito da lane |
| UC-FPN-07 | Painel | 🧪 aguarda veredito da lane |
| UC-FPN-08 | Painel | 🧪 aguarda veredito da lane |
| UC-FPN-09 | Painel | 🧪 aguarda veredito da lane |
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
| UC-FPP-29 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-30 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-31 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-32 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-33 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-34 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-50 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-51 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-52 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-53 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-54 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-55 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-56 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-57 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-58 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-59 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-60 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-61 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPP-62 | Public/Portal | 🧪 aguarda veredito da lane |
| UC-FPR-01 | Protocolos/Index | 🧪 aguarda veredito da lane |
| UC-FPR-02 | Protocolos/Index | 🧪 aguarda veredito da lane |
| UC-FPR-03 | Protocolos/Index | 🧪 aguarda veredito da lane |
| UC-FPR-04 | Protocolos/Index | 🧪 aguarda veredito da lane |
| UC-FPR-05 | Protocolos/Index | 🧪 aguarda veredito da lane |
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
| UC-FPS-37 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-38 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-39 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-40 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FPS-41 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FRA-01 | Relatorio/Associacao | 🧪 aguarda veredito da lane |
| UC-FRA-02 | Relatorio/Associacao | 🧪 aguarda veredito da lane |
| UC-FRA-03 | Relatorio/Associacao | 🧪 aguarda veredito da lane |
| UC-FRA-04 | Relatorio/Associacao | 🧪 aguarda veredito da lane |
| UC-FRA-05 | Relatorio/Associacao | 🧪 aguarda veredito da lane |
| UC-FSO-01 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FSO-02 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FSO-03 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FSO-04 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-FSO-05 | Pacientes/Show | 🧪 aguarda veredito da lane |
| UC-OAP-01 | Public/Portal | 🧪 aguarda veredito da lane |

---

**Como este arquivo cresce:** cada linha da tabela "onde a cadeia quebra" é o **próximo
requisito a escrever**. Fechou? Re-rode e ela some. Descobriu que NÃO se deve fazer?
Então não é lacuna — é **Non-Goal no charter** (só [W] preenche) ou entrada no **§5 de
`proibicoes.md`** se for padrão a nunca repetir. As duas saídas são legítimas; deixar
a lacuna aberta sem decisão é a única que não é.
