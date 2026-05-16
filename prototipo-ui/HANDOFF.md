# HANDOFF.md — estado vivo do loop

> **Sobrescrito a cada sync.** Não é log — é "onde estamos agora".
> Histórico vive em [SYNC_LOG.md](SYNC_LOG.md).

---

## Estado atual: 2026-05-15 — PIVOT Cowork detectado · F1.5 bloqueado aguardando Wagner

**Fase global:** `[CC]` entregou novo export via Claude Design (`Oimpresso ERP - Chat.html` era o arquivo aberto no handoff). Snapshot salvo em [`_cowork-export-2026-05-15/`](_cowork-export-2026-05-15/_SNAPSHOT.md) — 161 arquivos · 3.3 MB · **NÃO promovido pra `prototipos/<tela>/` ainda**. Heavy dirs (`uploads/` 9.3 MB, `backups/` 2 MB, `scraps/`) excluídos.

**🔄 PIVOT detectado** — comparação contra amendment 2026-05-14 [(CRITIQUE-chat-jana-vs-amendment.md)](_cowork-export-2026-05-15/CRITIQUE-chat-jana-vs-amendment.md):

Cowork **não fez V2 do chat 2-col conversacional** que o amendment-block-renderer pediu. Em vez disso entregou tela diferente — `chat-jana.jsx` é "**Cockpit do Analista IA**" (dashboard com Brief diário + 4 KPIs + 6 análises + 4 ações HITL · com chat embutido como aba `ia` ao lado da aba `dashboard`). Routing em [app.jsx:474](_cowork-export-2026-05-15/app.jsx) confirma: `route === "chat"` → `<window.JanaCockpit/>` (novo). O `chat.jsx` antigo (WhatsApp shell) virou dead code no shell exportado.

Paradigma: **Glean Home / Copilot M365** (dashboard-com-chat-tab) — não **ChatGPT Enterprise / Front** (chat-conversacional-puro) que o charter atual descreve.

**Score F1.5 das 19 divergências P0:** 4 ✅ fechados · 6 🟡 parciais · 7 ❌ abertos · 2 ⚪ moot.
- Sob critério literal: **41/100** (Cowork ignorou ~7 anti-patterns)
- Sob critério "pivot aceito": **78/100** (dashboard é forte, falta refinar)
- Sob critério "pivot rejeitado": **22/100** (entregou tela errada)

**🔴 BLOQUEADOR Wagner:** decidir entre 3 caminhos antes de F1.5 critique-score formal:

- **A) Aceitar pivot** — reescrever charter `Chat.charter.md` → `Cockpit.charter.md` com novos goals; reescrever amendment marcando 2 itens moot; pedir [CC] V2.1 focado em A1+A3+A5 (4 violações abertas) + C1+C2+C4+C7 (4 features IA abertas).
- **B) Rejeitar pivot** — mandar Cowork voltar ao paradigma 2-col + aproveitar `BriefDiario`/`KPICard`/`AnaliseCard` em outra tela `/jana/dashboard` separada.
- **C) Ambos** — `/jana/dashboard` recebe (A), `/jana/chat` recebe (B). 2 charters, 2 implementações.

Detalhe completo + check item-a-item das 19 divergências: [CRITIQUE-chat-jana-vs-amendment.md](_cowork-export-2026-05-15/CRITIQUE-chat-jana-vs-amendment.md).

**Anti-pattern meta:** Cowork mudou paradigma sem amendment formal. Candidato a virar override `/pivot-detected` em PROTOCOL.md §5 (igual `/design-override` etc).

**Outras telas candidatas no snapshot** (não há `prototipos/<tela>/` correspondente no repo): `crm-page.jsx`, `kb-page.jsx` (+ 5 satélites kb-*), `equipe-page.jsx`. Comparar antes de criar dir novo. Lista completa no [_SNAPSHOT.md](_cowork-export-2026-05-15/_SNAPSHOT.md).

**⚠️ AVISO** — `_cowork-export-2026-05-15/prototipo-ui-patch/{Modules,Pages,resources,routes,app}` contém código de produção do Cowork. **NÃO copiar direto pra raiz do repo** — viola Tier 0 ([ADR 0093](../memory/decisions/0093-multi-tenant-isolation-tier-0.md)), mesmo bloqueio do PR #352 (2026-05-09) e do zip canon visual (2026-05-11). Material visual pode ser promovido tela-a-tela; controllers/migrations/rotas exigem reescrita do zero seguindo MWART ([ADR 0104](../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)).

### Em voo agora

| Tela | Fase | Responsável | Bloqueador / nota |
|---|---|---|---|
| `chat` (Jana) | **F1 candidato V2 recebido** | [CL] / [W] | snapshot tem `chat-jana.jsx`+`chat-jana.css` — comparar com amendment-block-renderer (19 divergências P0) antes de promover pra `prototipos/chat/`. F1.5 score ≥80 = gate. |
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
