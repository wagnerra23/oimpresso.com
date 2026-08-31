---
date: "2026-08-28"
time: "1040 BRT"
slug: "design-sync-recibos-fiscal"
tldr: "Design Sync agora deriva o estado por tela de recibos reais; Fiscal ficou 7/7 em compared, sem fabricar aplicação, teste ou smoke. Implementação e gates locais integrados ao main, sem push ou PR."
decided_by: [W]
cycle: null
prs: []
us: []
next_steps:
  - "Ratificar a ADR 0384 no rito próprio"
  - "Na próxima aplicação Fiscal, avançar applied, tested e validated somente com recibos reais"
related_adrs:
  - "0384-design-sync-recibos-executaveis-por-tela"
  - "0379-bundle-design-transacao-manifesto-delta-staging"
---

# Handoff 2026-08-28 10:40 BRT — Design Sync com recibos e piloto Fiscal

## TL;DR

O protocolo passou a distinguir ancoragem, comparação, aplicação, teste e smoke por recibos
verificáveis. Fiscal está 7/7 em `compared`; estados posteriores permanecem pendentes porque
não houve aplicação visual nova, teste CT 100, deploy ou smoke nesta sessão.

## Cronologia desta sessão

| Quando | Evento |
|---|---|
| 09:55 | Trabalho isolado em worktree baseado no `origin/main`; checkout principal preservado. |
| 10:10 | Ledger v2, relatório v3, CLI do lifecycle e controles de invalidação implementados. |
| 10:22 | Sete telas Fiscal ancoradas, mapeadas e registradas como comparadas. |
| 10:35 | Selftests, catracas de design e ratchets locais concluídos. |

## Estado atual dos artefatos

### Entregue nesta sessão

| Artefato | Status | Notas |
|---|---|---|
| `scripts/design-sync/` | ✅ pronto | Recibos, lifecycle e catraca por escopo. |
| `memory/requisitos/Fiscal/` | ✅ comparado | Sete gaps e sete maps íntegros. |
| `resources/js/Pages/Fiscal/` | ✅ ancorado | Charters ligados às fontes; apenas `data-contract`, sem alteração visual. |
| ADR 0384 | 🟡 proposta | Implementada com autorização; ratificação formal pendente. |

### PRs

Nenhum PR criado. A branch `codex/design-sync-lifecycle` foi integrada localmente ao `main`.

## Decisões tomadas

| Pergunta | Decisão Wagner | Justificativa | Referência |
|---|---|---|---|
| Tornar o processo permanente? | Fazer tudo e testar. | Evitar repetir o falso “baixou/aplicou” e usar Fiscal como piloto. | ADR 0384 |
| Quando uma tela está aplicada? | Somente com evidência durável e hashes atuais. | `related_prototype` prova ancoragem, não aplicação. | ADR 0384 D-2 a D-4 |
| Como testar/validar? | Com execução registrada e smoke posterior. | Nome de teste e relato livre não são recibos. | ADR 0384 D-5 e D-6 |

## Bloqueios / pendências

- [ ] Ratificação formal da ADR 0384 — owner: W.
- [ ] `applied/tested/validated` do Fiscal — só quando ocorrer aplicação real, teste no CT 100
  e smoke pós-deploy; não é bloqueio desta entrega.
- [ ] Três maps globais stale preexistentes (Atendimento, Financeiro e Sells) — owners dos
  módulos; fora do escopo desta sessão.

## Próximos passos (ordem)

1. Revisar/ratificar a ADR 0384.
2. Escolher a primeira tela Fiscal que realmente receberá mudança visual.
3. Executar `mark-applied`, `run-test` no ambiente correto e `record-smoke` após deploy.

## Estado MCP no momento do fechamento

> As tools `brief-fetch`, `cycles-active`, `my-work`, `sessions-recent`, `decisions-search` e
> `whats-active` não estavam expostas nesta sessão. O fechamento registra a indisponibilidade,
> sem inventar snapshot MCP.

### cycles-active
```text
N/A — tool MCP não disponível.
```

### my-work
```text
N/A — tool MCP não disponível.
```

### sessions-recent limit:3
```text
N/A — tool MCP não disponível.
```

### decisions-search since:2026-08-28
```text
N/A — tool MCP não disponível.
```

### whats-active
```text
N/A — tool MCP não disponível.
```

## Referências

- Session log: [2026-08-28-session-01.md](../sessions/2026-08-28-session-01.md)
- Handoff anterior: [2026-08-28-0945-onda1-ponto-fechada-biometria-removida.md](2026-08-28-0945-onda1-ponto-fechada-biometria-removida.md)
- ADR 0384: [Design Sync deriva o estado da tela de recibos executáveis](../decisions/0384-design-sync-recibos-executaveis-por-tela.md)
