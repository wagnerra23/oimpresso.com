---
date: 2026-06-18
topic: "Maratona Caixa Unificada: port inbox-cur (Guia/Notas) + QR Reconectar + catraca Contrato de Tela + memória de proveniência design→código completa"
tela: Atendimento/CaixaUnificada
related_adrs: [0114, 0135, 0256, 0264]
pii: false
---

# Maratona 2026-06-18 — Caixa Unificada + memória de proveniência

> **Gatilho:** Wagner sentiu "o design não está sendo aplicado em produção" na Caixa. A investigação revelou que o problema-raiz NÃO era a tela (estava ok + design system aplicado) — era a **tradução design→código sem verificação** e a **memória de decisões dispersa**. A sessão construiu o mecanismo que prende isso.

## O que landou (todos no main · auto-merge)

| PR | Entrega |
|---|---|
| #2971 | **Guia** (troubleshooters + trilhas) — port `inbox-cur.jsx`. **Validado live em prod.** |
| #2972 | **Notas por-mensagem** — port `MsgCommentWrap` (localStorage per-user). |
| #2973 | **Catraca "Contrato de Tela"** — a perna de fidelidade visual do trio-de-tela. v0 ("Fidelity Lock", screenshot pareado em CI) DERRUBADO por 2 adversários; v1 = `--preflight`/`--contract`/`--omission` determinísticos. `RUNBOOK-contrato-de-tela.md`. |
| #2974 | **QR Reconectar in-place** — modal com QR REAL do backend (reusa `channels.connect`/`status`). 1º piloto da catraca: `caixa-unificada.contract.json` (5/5 seções ✓). |
| #2975 | PR-0 **fonte única** — contrato → caminho canônico; 8→3 cópias de `inbox-page.jsx`. |
| #2976 | PR-2 **`--map`** — gerador do mapa protótipo→prod (derivado, mata o SYNC_LOG manual). |
| #2977 | apaga `prototipo-ui/_BACKUP-NAO-USAR/` (−1082 arquivos · peso morto). |
| #2978 | PR-4 **`bundle-lint`** — esteira ≠ armazém (régua 6) + régua no `PROTOCOL.md`/`README-DESTINO`. |
| #2980 | PR-5 **ingestão** — plano da Caixa → `memory/` (vínculo MCP/RAGAS via charter). |

## As decisões duráveis (também no charter §Decisões da Caixa)
- **A Caixa é o OURO — não repintar** (diff de computed-style = 0). "Aplicar o design" = extrair o DS dela pras OUTRAS telas (Piloto→Prova→Propaga).
- **O verde do WhatsApp fica** (token `--ch-wa`).
- **`workspace-3` NÃO é universal** (só mestre→corpo→aside) — RECUSADO.
- **Figma como ponte — RECUSADO** (só vale com designer humano; ver `2026-06-18-arte-ponte-design-producao.md`).

## A cadeia de proveniência (operacional)
```
design (fonte ✓) → contract.json → âncoras data-contract → commit (Refs:) → PR → charter (§Decisões)
                          ↓
            --map (mapa verificado)   +   memory/ → MCP/RAGAS (vínculo c/ a tela)
```
**Régua-mestre:** derivado-não-mantido. Esteira (bundle) ≠ armazém (`memory/`). Ingerido = apagado do bundle.

## Pendente (próxima onda)
- **`--knowledge`** — índice de conhecimento POR-TELA gerado dos ~1.622 docs soltos (287 sessions + 128 handoffs + 841 requisitos + 366 ADRs; só 38 com `tela:`) + backlink assistido. Adversário planeja antes. Chip de tarefa `task_f63d9f0c`.

## Plano-mãe + método
`memory/sessions/2026-06-18-plano-memoria-proveniencia.md` (adversarial) + `RUNBOOK-contrato-de-tela.md`.
