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
- **Razão do XP** (`fisio_game_ledger.reason`, append-only): `exercicio_concluido`, `checkin`
  (registrar o dia, inclusive com dor alta), `missao` (missão do dia cumprida; `ref` diz qual) e
  `desafio_semanal` (desafio aceito e batido; `ref` = segunda da semana) — as duas últimas entram
  com a T-08c (2026-10-19). Medalha não é razão: é derivada do histórico, 0 XP. Decisão da US-FISIO-008 (2026-10-18): fica `checkin` (é o
  `registro_do_dia` da etapa 02 com o nome que a suíte e o banco já usam). As outras razões da etapa 02
  (`missao`, `desafio_semanal`, `medalha`, `escudo_ganho`, `escudo_usado`, `pausa_protegida`) entram
  com o código de missões/escudos (T-08c), mudando este bloco junto com a migration — razão sem código não entra.
  Situação em 2026-10-19: entraram `missao` e `desafio_semanal`; `escudo_*` e `pausa_protegida` seguem sem código.
- **Questionário** (`fisio_questionnaires.instrument`): só os de uso livre (ADR 0437 §8) — `eva`,
  `rmdq` (Roland-Morris), `psfs`, `lefs`, `spadi`. ODI, NDI, KOOS, QuickDASH e WOMAC ficam fora até
  haver licença comercial.
  Fibromialgia (etapa 07, P-03, 2026-11-11): `acr2016` (critérios ACR 2016, WPI + SSS, versão
  FSQ-Brasil), `fiqr` (FIQR-Br), `pgic` (impressão global de mudança) e `sentar_levantar_30s` (teste de
  desempenho, registrado pelo profissional). Estar no enum não libera: só aparecem e aceitam resposta se
  estiverem em `config('fisio.instrumentos_liberados')`, desligado em produção até a licença (P-01). A
  versão do instrumento fica em `instrument_version`. O resultado dos critérios ACR é informação para o
  profissional, nunca diagnóstico automático (ADR 0437 §5).
- **Lançamento do pacote** (`fisio_package_ledger.kind`, append-only, US-FISIO-020): `credito`
  (sessões na abertura), `debito` (uma sessão usada; `ref` diz o atendimento), `estorno`
  (devolve um débito, `ref = estorno:<id>`, +1; ou tira a sobra do saldo quando a venda do pacote é
  cancelada, `ref = venda_cancelada:<id>`, −sobra) e `extensao` (nova validade, 0 sessão). O saldo é a
  soma. O pacote não guarda dinheiro: preço é da venda do núcleo, título do Financeiro.
- **Marca do Receita Saúde** (`fisio_health_receipts.marca`, US-FISIO-023): `enviado` (o
  profissional emitiu o recibo do pagamento) ou `retificar`. Sem linha = pendente. Não guarda
  valor nem data: esses são do `transaction_payments`.
- **Status do programa** (`fisio_programs.status`, texto, fora do bloco `json`): `ativo` ou
  `encerrado` — um ativo por paciente.
- **Agenda** (US-FISIO-013, etapa 04 §3.2): tipo de atendimento em `presencial` ou `teleconsulta`
  (`fisio_appointment_types.modality`). Atendimento (`fisio_appointments.status`, fora do bloco `json`
  porque a lista mora numa constante da migration, espelhada em `Appointment::STATUS`) anda
  `agendado → confirmado → chegou → em_atendimento → realizado`, ou termina em `faltou` ou `cancelado`;
  "realizado" só com evolução assinada. Ninguém apaga atendimento: desmarcar é `cancelado`, com quem
  desmarcou (`cancelled_by`: `clinica` ou `paciente`). Origem (`source`): `clinica` (marcado pela
  recepção), `online` (pelo app do paciente) ou `lista_espera` (vaga liberada que foi oferecida).
- **Lista de espera** (`fisio_waitlist.status`, fora do bloco `json` como os outros `status`): `ativo` (esperando), `oferecido` (vaga oferecida, com prazo),
  `atendido` (ficou com a vaga), `saiu` (o paciente ou a clínica tirou) ou `expirado`. **Quem marca pelo app**
  (`fisio_booking_settings.who_can_book`): `pacote` (só com pacote ativo; enquanto o pacote não existir, ninguém),
  `todos` (em tratamento) ou `ninguem` (o app só mostra e confirma).

## Enums canônicos (machine-checked por `dominio:check`)

```json
{
  "module": "Fisio",
  "enums": {
    "fisio_records.kind": ["avaliacao", "evolucao", "telemonitoramento", "adendo"],
    "fisio_programs.origin": ["manual", "protocolo"],
    "fisio_programs.pain_alert_mode": ["absoluto", "sobre_basal"],
    "fisio_game_ledger.reason": ["exercicio_concluido", "checkin", "missao", "desafio_semanal"],
    "fisio_questionnaires.instrument": ["eva", "rmdq", "psfs", "lefs", "spadi", "acr2016", "fiqr", "pgic", "sentar_levantar_30s"],
    "fisio_appointment_types.modality": ["presencial", "teleconsulta"],
    "fisio_appointments.source": ["clinica", "online", "lista_espera"],
    "fisio_appointments.cancelled_by": ["clinica", "paciente"],
    "fisio_booking_settings.who_can_book": ["pacote", "todos", "ninguem"],
    "fisio_package_ledger.kind": ["credito", "debito", "estorno", "extensao"],
    "fisio_health_receipts.marca": ["enviado", "retificar"]
  }
}
```