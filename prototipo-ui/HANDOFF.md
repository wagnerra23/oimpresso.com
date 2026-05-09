# HANDOFF.md — estado vivo do loop

> **Sobrescrito a cada sync.** Não é log — é "onde estamos agora".
> Histórico vive em [SYNC_LOG.md](SYNC_LOG.md).

---

## Estado atual: 2026-05-09 21:15 — 6 protótipos commitados, 1 em F3 imediato sugerido

**Fase global:** batch Financeiro F1 commitado como pinos. Wagner decide qual atacar 1º.

### Em voo agora

| Tela | Fase | Responsável | Bloqueador / nota |
|---|---|---|---|
| `producao-oficina` | F2 approved | [CL] | aguarda Wagner pedir F3 (kanban 5 colunas, prototipo aprovado 19:30) |
| `financeiro-fluxo` | F1 commit-only | [W] / [CL] | **sugestão 1ª da fila** — sem tabela nova, ~1-2h trabalho [CL] |
| `financeiro-plano-contas` | F1 commit-only | — | bloqueada por ADR `arq/0008-plano-contas-hierarquico` + migration `chart_of_accounts` |
| `financeiro-dre` | F1 commit-only | — | bloqueada por plano-contas (depende) + ADR `arq/0007-dre-hierarquico` |
| `financeiro-conciliacao` | F1 commit-only | — | bloqueada por ADR `arq/0006-importador-ofx` + tabela `bank_statement_lines` |
| `financeiro-unificado` | F1 histórico | — | tela JÁ EM PROD com fixes #355/#358 — pino é referência visual, NÃO sobrescrever |

### Próxima da fila

`Financeiro/Fluxo` — única do batch sem tabela nova. Service `FluxoCaixaService::projetar(businessId, dias)` consome `Titulo` + `TituloBaixa` + `ContaBancaria` que já existem.

Ver [TELAS_REVIEW_QUEUE.md](TELAS_REVIEW_QUEUE.md). P0 fora desse batch: `Sells/Create`.

### Métricas rápidas

- Telas em F3 há +7d: 0 (loop saudável)
- Protótipos sem critique-score: 6 (todos — Wagner aprova direto via Cowork visual; F1.5 pendente decidir se vamos rodar `design:design-critique` retroativo ou registrar `/design-override` em massa)
- Merges sem a11y-report: 0

### O que [W] precisa fazer

1. Decidir: **atacar Fluxo de caixa agora?** (loop F1.5 → F3 estimado ~1-2h trabalho [CL])
2. Calibrar 4 questões abertas pra Fluxo (ver `prototipos/financeiro-fluxo/README.md` § decisões)
3. Em paralelo, responder 3 perguntas iniciais ainda em [COWORK_NOTES.md](COWORK_NOTES.md) (sobre trigger F3, override, ADR 0114)

### O que [CL] precisa fazer

Aguardar resposta de Wagner sobre Fluxo. Se `sim`: gerar visual-comparison.md → Service real → Controller real → Pest → .tsx (refatorando do pino) → charter → routes → sidebar → PR.
