# HANDOFF.md — estado vivo do loop

> **Sobrescrito a cada sync.** Não é log — é "onde estamos agora".
> Histórico vive em [SYNC_LOG.md](SYNC_LOG.md). Como reportar: [PROTOCOL.md §10.2](PROTOCOL.md).

---

## Estado atual: 2026-05-30 — DS adoção (`ds/* → 0`) · loop reancorado

**Fase global:** o visual do **DS v4 (accent roxo · ADR 0235)** está completo e o **guard `ds/*` + ratchet** (ADR 0209) está ligado — o drift **parou de crescer**. Agora roda a **limpeza da dívida tela-a-tela** (roadmap "DS até zero", fases A–F em `PROMPT_PARA_CODE_DS-ROADMAP-ATE-ZERO.md`).

**Placar (`npm run ds:report`): `ds/* = 616`** (29/05 era 639; −23 do Financeiro #1982).

| Regra `ds/*` | Hits | Fase que fecha |
|---|---:|---|
| `no-adhoc-status-text` | 410 | A (Tipo 1 erro-de-form) → C+D (Tipo 2 badge) |
| `no-native-select` | 93 | A |
| `no-rounded-xl` | 66 | A |
| `no-native-checkbox` | 41 | A |
| `no-native-radio` | 6 | A |
| `no-arbitrary-color` | 0 | B (já limpo) |

### Em voo agora

| Frente | Fase | Resp. | Nota |
|---|---|---|---|
| **Conserto do loop** (este PR) | aguardando merge | [CL] | PROTOCOL §10 (gatilho de ida + canal de retorno) · `ds:report` · 6 prompts versionados · HANDOFF/SYNC_LOG reancorados |
| **Roadmap DS — Fase A** (controles + FieldError T1) | fila ativa, **não iniciada** | [CL] | ordem: Sells → RecurringBilling → OficinaAuto → Repair → Purchase → Admin → Whatsapp → Settings → Financeiro → Cliente (ver `PROMPT_PARA_CODE_PR-C-WORKLIST.md`) |
| Fase B (cor crua → token) | independente | [CL] | `no-arbitrary-color` já = 0 — fila provável vazia, confirmar |
| Fase C (Onda G badge variants) | component-only | [CC]/[CL] | pré-req da D |
| Fase D (lote-badge 410 Tipo 2) | após C mergear | [CL] | o grosso do drift (66%) |
| Fase E (FormSection) | após A, por módulo | [CL] | — |

### Próxima da fila — decisão Wagner

Depois deste PR de conserto mergear: **disparar Fase A pelo Sells** (1 módulo = 1 branch = 1 PR, para no gate visual). Cada PR reporta de volta via [§10.2](PROTOCOL.md).

### Workstreams parados (ponteiro, não ativos)

- **Jana** (`Chat.tsx` / `Cockpit.tsx`) — pivot 2026-05-15, score F1.5 78/100; congelado até Wagner reabrir (detalhe no SYNC_LOG 2026-05-15).
- **Financeiro** Fluxo / Plano-contas / DRE / Conciliação — F1 commit-only, bloqueados por ADRs arq + migrations.

### O que [CL] faz agora
Aguardar merge deste conserto → disparar Fase A (Sells).

### O que [CC] (Cowork) faz
Fechar **todo** roadmap com o Gatilho de RETORNO ([§10.2](PROTOCOL.md)). Ler o estado **por aqui** (`prototipo-ui/`), não por `memory/handoffs/` — `[CC]` não lê de lá.
