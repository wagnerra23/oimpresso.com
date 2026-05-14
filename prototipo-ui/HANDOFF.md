# HANDOFF.md — estado vivo do loop

> **Sobrescrito a cada sync.** Não é log — é "onde estamos agora".
> Histórico vive em [SYNC_LOG.md](SYNC_LOG.md).

---

## Estado atual: 2026-05-14 — Jana/Chat amendment P0 bloqueia F3

**Fase global:** Revisão crítica do export Claude Design `Oimpresso-handoff.zip` revelou que o `chat.jsx` exportado não respeita o `Chat.charter.md` da Jana. **Nota 24/100** vs Glean / ChatGPT Enterprise / Notion AI / Microsoft Copilot M365 (2026). Amendment formal P0 escrito — bloqueia entrada em F3 até [CC] gerar V2.

### Em voo agora

| Tela | Fase | Responsável | Bloqueador / nota |
|---|---|---|---|
| `chat` (Jana) | **F0.5 amendment P0 entregue** | [CC] | aguardando [CC] consumir trio pedido #316 + amendment-avatar (2026-05-09) + amendment-block-renderer (2026-05-14) → gerar V2 do protótipo |
| `producao-oficina` | F2 approved | [CL] | aguarda Wagner pedir F3 (kanban 5 colunas) |
| `financeiro-fluxo` | F1 commit-only | [W] / [CL] | sem tabela nova, ~1-2h trabalho [CL] |
| `financeiro-plano-contas` | F1 commit-only | — | bloqueada por ADR `arq/0008-plano-contas-hierarquico` + migration `chart_of_accounts` |
| `financeiro-dre` | F1 commit-only | — | bloqueada por plano-contas + ADR `arq/0007-dre-hierarquico` |
| `financeiro-conciliacao` | F1 commit-only | — | bloqueada por ADR `arq/0006-importador-ofx` + tabela `bank_statement_lines` |
| `financeiro-unificado` | F1 histórico | — | tela JÁ EM PROD com fixes #355/#358 — pino é referência visual |

### Próxima da fila — decisão Wagner

**Opção A — atacar Jana V2 primeiro** (recomendado se IA-conversacional é prioridade estratégica): [CC] consome trio de amendments, gera V2 `prototipos/chat/` com 4 kinds tipados + streaming + citations. F1.5 score ≥80 → F2 screenshot → F3 implementação em `resources/js/Pages/Jana/Chat.tsx`. Estimado: ~1 dia [CC] + ~3 dias [CL] (10x IA-pair ADR 0106).

**Opção B — atacar Fluxo de caixa primeiro** (recomendado se cash-flow é prioridade): ver linha original abaixo.

`Financeiro/Fluxo` — única do batch sem tabela nova. Service `FluxoCaixaService::projetar(businessId, dias)` consome `Titulo` + `TituloBaixa` + `ContaBancaria` que já existem.

Ver [TELAS_REVIEW_QUEUE.md](TELAS_REVIEW_QUEUE.md). P0 fora desse batch: `Sells/Create`.

### Métricas rápidas

- Telas em F3 há +7d: 0 (loop saudável)
- Telas com amendment P0 pendente [CC]: **1 (Jana/Chat)**
- Protótipos sem critique-score: 6 (Cowork visual aprovado direto)
- Merges sem a11y-report: 0

### O que [W] precisa fazer

1. **Decidir prioridade**: Jana V2 vs Fluxo de caixa (escolha A ou B acima)
2. Se A: confirmar amendment-block-renderer (revisão de 2 min) e disparar [CC]
3. Se B: confirmar Fluxo F1.5 → F3 (loop ~1-2h)
4. Calibrar 4 questões abertas pra Fluxo (ver `prototipos/financeiro-fluxo/README.md` §decisões)
5. Em paralelo, responder 3 perguntas iniciais ainda em [COWORK_NOTES.md](COWORK_NOTES.md) (sobre trigger F3, override, ADR 0114)

### O que [CL] precisa fazer

Aguardar decisão Wagner (A ou B). Se A: aguardar [CC] entregar V2, depois F3 Chat.tsx. Se B: gerar visual-comparison.md Fluxo → Service real → Controller real → Pest → .tsx → charter → routes → sidebar → PR.

### O que [CC] precisa fazer

Se Wagner escolher opção A: ler [`COWORK_NOTES.amendment-jana-chat-block-renderer.md`](COWORK_NOTES.amendment-jana-chat-block-renderer.md) (19 divergências catalogadas + correção formal item-por-item) + amendment-avatar 2026-05-09 + pedido #316 original. Gerar V2 em `prototipos/chat/` com:

- Avatar Jana quadrado monocromático letra "J" (`rounded-md bg-primary`)
- Tabs `Todas / Minhas / Compartilhadas / Arquivadas`
- 4 componentes block renderer: `<MarkdownBubble/>` + `<ToolUseChip/>` + `<DataTableBubble/>` + `<ActionCardBubble/>`
- `mock-stream.js` (substitui setTimeout por fake SSE chunks)
- Empty state com 4 prompts iniciais
- PII detector regex CPF/CNPJ/cartão no composer
- Atalhos `/` `J/K` `Esc` globais
- Chip business atual no header (`LARISSA · biz=4`)
- Remover: read receipts, botão ligar, online dot, file attachment, emoji, mencionar
