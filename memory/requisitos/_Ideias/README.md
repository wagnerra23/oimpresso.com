---
nome: _Ideias — incubadora de módulos
description: Pasta cross-cutting onde nascem ideias de módulos novos antes de virarem `memory/requisitos/{Nome}/` formais. Cada subpasta é uma ideia em algum estágio do ciclo de vida (idea → researching → spec-ready → in-progress → shipped).
type: meta
last_updated: 2026-04-24
---

# _Ideias — incubadora de módulos

Pasta especial (prefixo `_` igual a `_DesignSystem`) onde ideias de módulos novos vivem antes de virar requisito/módulo de fato. Permite **capturar pesquisa, conversas, brainstorms** sem poluir `requisitos/` com módulos que talvez nunca saiam do papel.

## Por que existe

Wagner conversa muito sobre módulos novos no Claude (mobile, web). Sem essa pasta, o conhecimento ficaria:
- Em chats efêmeros (perde quando muda de conversa)
- Em sessions cronológicas (difícil achar por área)
- Em `requisitos/{Nome}/` direto (mas o módulo pode nem existir ainda)

`_Ideias/` é o **lugar de transição**: junta evidências, brainstorms e decisões iniciais até a ideia ganhar maturidade pra virar SPEC.

## Estrutura padrão de cada ideia

```
_Ideias/{Nome}/
├── README.md              # overview: problema, persona, status, prioridade, refs
├── _brainstorm.md         # opcional — exploração de problema/persona/JTBD
├── decisoes-iniciais.md   # opcional — ADRs informais pré-spec
└── evidencias/            # conversas, prints, links externos, papers
    ├── conversa-claude-{data}-{tema}.md
    ├── benchmark-x-vs-y.md
    └── *.csv / *.png / etc.
```

## Frontmatter padrão do README

```yaml
---
status: idea | researching | spec-ready | in-progress | shipped | abandoned
priority: alta | média | baixa
problem: "Frase única descrevendo o problema"
persona: "Quem sente esse problema (operador/gestor/auditor/etc.)"
estimated_effort: "1 dia | 1 semana | 1 mês | semestre"
references:
  - https://claude.ai/chat/xxx
  - memory/requisitos/_DesignSystem/...
---
```

## Ciclo de vida

```
┌─────────┐  guarda  ┌──────────────┐  pesquisa  ┌─────────────┐  promove  ┌──────────────┐  scaffold  ┌────────────────┐  ship  ┌──────────┐
│ surge   │ ──────▶  │ _Ideias/X/   │ ─────────▶ │ status:     │ ────────▶ │ requisitos/  │ ─────────▶ │ Modules/X/     │ ─────▶ │ shipped  │
│ ideia   │          │ status:idea  │            │ researching │           │ X/ (SPEC)    │            │ (código real)  │        │          │
└─────────┘          └──────────────┘            └─────────────┘           └──────────────┘            └────────────────┘        └──────────┘
```

Quando uma ideia vira módulo real (etapa "promove"), `_Ideias/{Nome}/` vira **tombstone** com link pro novo lugar:

```markdown
# {Nome} — promovido em YYYY-MM-DD

Esta ideia virou módulo real. Spec atual em [`memory/requisitos/{Nome}/`](../../{Nome}/).
Conteúdo histórico desta pasta foi migrado pra evidências em `requisitos/{Nome}/_evidencias/`.
```

## Como usar com o trigger "guarde no cofre"

Quando Wagner disser **"guarde no cofre essa ideia"** ou similar:

1. IA cria/atualiza `_Ideias/{Nome}/README.md` com frontmatter
2. Se for evidência específica (link, conversa, screenshot), adiciona em `evidencias/`
3. Se for decisão pré-spec, adiciona em `decisoes-iniciais.md`
4. Confirma com link curto ao Wagner

## Importar conversas do claude.ai (em batch)

A pasta [`_tools/`](_tools/) tem o caminho oficial pra trazer conversas mobile como evidências:

1. **Settings → Privacy → Export data** em claude.ai → recebe ZIP por email
2. **Edita** [`_tools/conversas-pendentes.tsv`](_tools/conversas-pendentes.tsv) com URL → output_path
3. **Roda**:
   ```bash
   cd memory/requisitos/_Ideias/_tools
   python import_claude_export.py /path/to/data-XXX-batch-0000.zip conversas-pendentes.tsv
   ```

Output: markdown com frontmatter padrão (`origin_url`, `origin_title`, `extracted_at`, `created_at`, `message_count`) + turnos numerados Wagner/Claude com timestamps.

**Custo**: zero tokens IA, zero browser. Limitação: artifacts do painel lateral não vêm no JSON (só texto da conversa).

Fallback Playwright (se não tiver export disponível): [`_tools/scrape_claude_conversation.py`](_tools/scrape_claude_conversation.py) — abre Chromium autenticado e extrai por DOM.

## Índice de ideias atuais

(Atualizado quando entradas novas surgem; a tabela abaixo é populada conforme as conversas mobile do Claude vão sendo extraídas em 2026-04-24.)

| Ideia | Status | Prioridade | Esforço | Origem principal |
|---|---|---|---|---|
| [NfeBrasil](NfeBrasil/) | researching | alta | 5-6 semanas | Conversa Claude mobile "Implementar documentos fiscais" |
| [Financeiro](Financeiro/) | researching | alta | 4-6 semanas | Conversa Claude mobile "Módulo financeiro brasileiro" |
| [CobrancaRecorrente](CobrancaRecorrente/) | researching | alta | 12-14 semanas | Conversa Claude mobile "Estado da arte em módulos de cobrança recorrente" |
| [LaravelAI](LaravelAI/) | idea | média | 2-3 sem MVP / 6-8 sem prod | Conversa Claude mobile "Laravel AI SDK / RAG / knowledge graph" |
| [AutomacaoCC](AutomacaoCC/) | shipped | - | já entregue (M3+M5+M7+M10) | Conversa Claude mobile "Upgrade UltimatePOS via Claude Code" |

## Política de housekeeping

- **`status: abandoned`** ideias podem ser removidas após 90 dias sem mexida (deletar pasta + tombstone em `_Ideias/_arquivo/`)
- **`status: shipped`** ideias migram pra `memory/requisitos/{Nome}/` e o `_Ideias/{Nome}/` vira tombstone (mantém-se pelo histórico)
- README desta pasta deve ser atualizada quando ideias novas entram ou mudam de status

## Relação com outras estruturas

- **`memory/requisitos/{Nome}/`** — spec formal de módulo já decidido
- **`memory/requisitos/_DesignSystem/`** — design system cross-cutting (é cross-cutting, não ideia incubada)
- **`memory/sessions/YYYY-MM-DD-*.md`** — narrativa cronológica das sessões; pode citar `_Ideias/` mas não substitui
- **Auto-memória (`~/.claude/projects/.../memory/`)** — preferências/triggers/feedbacks pessoais; `_Ideias/` é compartilhada via repo
