---
dominio: Fisio — vertical de fisioterapia (pacientes, prontuário, programa de exercícios, portal, jogo de constância)
fonte_unica: este arquivo é a fonte canônica do vocabulário de domínio do módulo (ADR 0264 G-4)
gate: dominio:check (scripts/domain-dict-guard.mjs) — enum de migration ⇔ bloco `json` abaixo
owner: wagner
related_adrs: [0264-governanca-executavel-trio-dominio-e2e, 0437-modulo-fisio-vertical-fisioterapia, 0093-multi-tenant-isolation-tier-0]
---

# Dicionário de domínio — Fisio

> **Fonte única do vocabulário de domínio** do Fisio (ADR 0264 G-4). Nasceu com o fundo do módulo
> (US-FISIO-003, 2026-10-18; modelo de dados do `memory/requisitos/Fisio/HANDOFF-F1.md`).
> **Editar aqui é uma decisão de domínio** — e, no que toca prontuário, contraindicação ou texto ao
> paciente, passa pela revisão clínica da Dra. Dejeane.

## Semântica

- **Paciente** (`fisio_patients`): é um `contact` do business; o Fisio guarda só o perfil clínico
  1:1. "Excluir paciente" = **bloquear** (`blocked_at`), nunca apagar (Lei 13.787/2018, guarda de 20 anos).
- **Registro de prontuário** (`fisio_records.kind`, append-only quando assinado — Res. COFFITO 414/2012):
  `avaliacao` (avaliação inicial/anamnese), `evolucao` (evolução por atendimento), `telemonitoramento`
  (gerado pelo check-in do portal — Res. COFFITO 619/2025; não é criável pela tela do profissional) e
  `adendo` (correção de um registro anterior, com `amends_id`; o original fica intacto).
- **Origem do programa** (`fisio_programs.origin`): `manual` (montado exercício a exercício) ou
  `protocolo` (a partir de um protocolo-modelo da clínica, com a lógica toda visível).
- **Modo do alerta de dor** (`fisio_programs.pain_alert_mode`): `absoluto` (dor ≥ limiar) ou
  `sobre_basal` (dor acima da basal do paciente; padrão nos protocolos de dor crônica). Com o alerta,
  o paciente vê só a instrução estática do profissional (`stop_instruction`) — nunca conduta
  calculada pelo software (ADR 0437 §5).
- **Razão do XP** (`fisio_game_ledger.reason`, append-only): `exercicio_concluido` e `checkin`
  (registrar o dia, inclusive com dor alta). A etapa 02 propõe mais razões (`registro_do_dia`,
  `missao`, `desafio_semanal`, `medalha`, `escudo_ganho`, `escudo_usado`, `pausa_protegida`) —
  decisão da US-FISIO-008, que muda este bloco junto com a migration.
- **Questionário** (`fisio_questionnaires.instrument`): só os de uso livre (ADR 0437 §8) — `eva`,
  `rmdq` (Roland-Morris), `psfs`, `lefs`, `spadi`. ODI, NDI, KOOS, QuickDASH e WOMAC ficam fora até
  haver licença comercial.
- **Status do programa** (`fisio_programs.status`, texto, fora do bloco `json`): `ativo` ou
  `encerrado` — um ativo por paciente.

## Enums canônicos (machine-checked por `dominio:check`)

```json
{
  "module": "Fisio",
  "enums": {
    "fisio_records.kind": ["avaliacao", "evolucao", "telemonitoramento", "adendo"],
    "fisio_programs.origin": ["manual", "protocolo"],
    "fisio_programs.pain_alert_mode": ["absoluto", "sobre_basal"],
    "fisio_game_ledger.reason": ["exercicio_concluido", "checkin"],
    "fisio_questionnaires.instrument": ["eva", "rmdq", "psfs", "lefs", "spadi"]
  }
}
```