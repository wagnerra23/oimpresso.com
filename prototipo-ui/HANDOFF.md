# HANDOFF.md — estado vivo do loop

> **Sobrescrito a cada sync.** Não é log — é "onde estamos agora".
> Histórico vive em [SYNC_LOG.md](SYNC_LOG.md).

---

## Estado atual: 2026-05-15 — Pivot Cowork ACEITO · `chat-jana.jsx` evolui `/jana/cockpit`

**Fase global:** `[CC]` entregou novo export via Claude Design. Snapshot salvo em [`_cowork-export-2026-05-15/`](_cowork-export-2026-05-15/_SNAPSHOT.md) — 161 arquivos · 3.3 MB. CRITIQUE [interim](_cowork-export-2026-05-15/CRITIQUE-chat-jana-vs-amendment.md) gerado e revisado pós Caixa Unificada check (Wagner 2026-05-15).

**✅ PIVOT ACEITO** — Cowork pivotou de "chat 2-col conversacional" pra "Cockpit Analista IA" (dashboard + aba IA). O pivot é correto:

1. **Caixa Unificada V4 já cumpre o paradigma 2-col humano** ([`/atendimento/caixa-unificada/Index.charter.md`](../resources/js/Pages/Atendimento/CaixaUnificada/Index.charter.md)) — refazer outro 2-col em `/jana/` duplicaria conceito.
2. **`Modules/Jana` já tem 3 páginas em prod**: `Chat.tsx` (`/jana/` — 2-col conversacional live) · `Cockpit.tsx` (`/jana/cockpit` — MVP piloto paralelo) · `Dashboard.tsx` (`/jana/dashboard` — KPIs/Farol). O `chat-jana.jsx` mapeia naturalmente pra **evolução do `Cockpit.tsx`** com a parte do `Dashboard.tsx` absorvida como tab.
3. **Zero overlap arquitetural com Caixa Unificada** — audiência, layout, modelo de dado, real-time, ACL, composer, identidade visual: tudo distinto. Apenas tokens "Cockpit V2" e atalho `J/K` compartilhados (escopos diferentes — convs vs mensagens).
4. **Routes já preparadas** — `Modules/Jana/Http/routes.php:30` comenta literal: *"rota PARALELA ao /copiloto atual; nao substitui Chat.tsx"*. Wagner previu separação.

**Score F1.5 interim:** **78/100** (gate ≥80 não atingido ainda · 1 round de refator necessário).

19 divergências P0 → 4 ✅ closed · 6 🟡 partial · 7 ❌ open · 2 ⚪ moot (B5/B6 não fazem sentido sem lista de conversas).

**8 refinos abertos pra fechar F1.5 ≥80** (~3-4h Cowork V2.1):

1. A1 — `JanaAvatar` quadrado mono primary letra "J" (substitui gradient + 🤖)
2. A3 — bubbles simétricos sem tail (remove `border-bottom-*-radius:4px` assimétrico)
3. A5 — `mock-stream.js` SSE fake + `<TypingIndicator>` chip (A2 vira automático)
4. B7 — keydown global `/` `J/K` `Esc`
5. C1 — switch 4 kinds + 4 componentes (`<MarkdownBubble>`, `<ToolUseChip>`, `<DataTableBubble>`, `<ActionCardBubble>`)
6. C2 — citations inline `[1]` clicáveis
7. C4 — PII detector regex no composer
8. C7 — `react-markdown` + `rehype-sanitize` (consistente com `Chat.tsx`)

**Workstreams separados:**

- `/jana/` (`Chat.tsx` 2-col conversacional) → permanece live · amendment-block-renderer 2026-05-14 fica válido pra ele em workstream isolado se/quando Wagner reabrir.
- `/jana/cockpit` (`Cockpit.tsx` evolução) → workstream principal · próxima ação.
- `/jana/dashboard` (`Dashboard.tsx`) → folda como tab `dashboard` do `Cockpit.tsx` (canary 7d depois historical).

**Outras telas candidatas no snapshot** (não há `prototipos/<tela>/` correspondente no repo): `crm-page.jsx`, `kb-page.jsx` (+ 5 satélites kb-*), `equipe-page.jsx`. Comparar antes de criar dir novo. Lista completa no [_SNAPSHOT.md](_cowork-export-2026-05-15/_SNAPSHOT.md).

**⚠️ AVISO** — `_cowork-export-2026-05-15/prototipo-ui-patch/{Modules,Pages,resources,routes,app}` contém código de produção do Cowork. **NÃO copiar direto pra raiz do repo** — viola Tier 0 ([ADR 0093](../memory/decisions/0093-multi-tenant-isolation-tier-0.md)), mesmo bloqueio do PR #352 (2026-05-09) e do zip canon visual (2026-05-11). Material visual pode ser promovido tela-a-tela; controllers/migrations/rotas exigem reescrita do zero seguindo MWART ([ADR 0104](../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)).

### Em voo agora

| Tela | Fase | Responsável | Bloqueador / nota |
|---|---|---|---|
| `cockpit` (Jana) | **F1 score 78/100 · pivot aceito · 1 round refator pendente** | [CC] / [W] | snapshot tem `chat-jana.jsx`+`chat-jana.css` → evolui `Pages/Jana/Cockpit.tsx`. 8 refinos abertos (A1+A3+A5+B7+C1+C2+C4+C7) · ~3-4h Cowork V2.1 antes de score ≥80 e promoção pra `prototipos/cockpit/` |
| `chat` (Jana 2-col) | F1.5 amendment válido em workstream separado | [W] | `Chat.tsx` (`/jana/`) permanece live · amendment-block-renderer 2026-05-14 fica congelado pra ele se Wagner reabrir |
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
