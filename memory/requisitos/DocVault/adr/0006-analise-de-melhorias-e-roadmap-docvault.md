# ADR 0006 · Análise de melhorias e roadmap do DocVault

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner, Claude
- **Supersede**: complementa ADR 0005

## Contexto

DocVault entregou Fase 1+2 (Dashboard, Ingest, Inbox, Viewer, Chat, estrutura pasta-por-módulo, ADRs, comandos migrate-module/sync-pages/validate/gen-test, hub de memória unificada). 14 commits na branch `6.7-react` em uma sessão.

Precisamos catalogar o **que falta** e priorizar por impacto × esforço antes da fadiga de contexto virar dívida técnica.

## Decisão

Roadmap dividido em 4 eixos. Cada item tem prioridade (P0 crítico / P1 alta / P2 média / P3 opcional) e esforço (S = horas, M = dia, L = semana).

### Eixo A — Adoção (aplicar o que já existe nos 30 módulos)

| # | Item | P | Esforço |
|---|---|---|---|
| A1 | Migrar módulos ativos restantes (Accounting, Hrm, Crm, Manufacturing) pra formato pasta | P1 | M |
| A2 | Anotar `@docvault` em todas telas React (hoje 3/39) — foco: Essentials/Ponto restantes | P1 | M |
| A3 | Implementar testes dos stubs gerados por `gen-test` (priorizar R-PONT-001 multi-tenant) | P1 | M |
| A4 | Criar ADRs UI pras decisões de tela já tomadas (split view Intercorrências, tabs Espelho, drawer Todo) | P2 | S |
| A5 | Popular `Testado em:` em todas regras após A3 | P2 | S |

### Eixo B — Sincronização e automação

| # | Item | P | Esforço |
|---|---|---|---|
| B1 | Comando `docvault:sync-memories` (Claude→memory/claude/) + scheduler diário | P0 | S |
| B2 | Pre-commit hook rodando `docvault:validate` (bloqueia critical) | P1 | S |
| B3 | GitHub Actions: sync-pages + validate + testes em todo PR | P2 | M |
| B4 | `docvault:weekly-digest` email com evolução de trace + issues novas | P3 | M |

### Eixo C — Capacidades novas

| # | Item | P | Esforço |
|---|---|---|---|
| C1 | Conectar OpenAI de verdade no ChatAssistant (stub vira real quando AI_ENABLED=true) | P1 | S |
| C2 | Render markdown nativo em Memoria.tsx (shadcn + react-markdown) — hoje só `<pre>` | P2 | S |
| C3 | Busca full-text em `/docs/memoria` (filtro atual é só por nome) | P2 | S |
| C4 | Grid/kanban das issues do validator (lista atual é tabela CLI) | P2 | M |
| C5 | Export/import de módulo: `docvault:export Modulo` gera .zip com todos os .md | P3 | S |
| C6 | OpenAPI auto-gerado por módulo a partir das rotas + FormRequests | P3 | L |
| C7 | Embeddings (OpenAI ada-002 ou local) pra busca semântica de verdade | P3 | L |

### Eixo D — Qualidade e observabilidade

| # | Item | P | Esforço |
|---|---|---|---|
| D1 | Gráfico histórico de trace_score (últimos 30 dias) no dashboard | P2 | S |
| D2 | Storybook: popular `storybook_url` em `docs_pages` quando tem `.stories.tsx` irmão | P3 | S |
| D3 | Versionamento de ADRs (quando um superseda outro, mostrar grafo na UI) | P3 | M |
| D4 | Cobertura de testes por módulo no dashboard (lê PHPUnit coverage.xml) | P3 | M |

## Prioridades sugeridas (próximas 3 sessões)

**Sessão N+1** — **adoção** em módulos ativos (A1 + A2 parcial):
- Migrar Accounting, Hrm, Crm, Manufacturing pra pasta
- Anotar `@docvault` em mais 10 telas (Essentials: todo/reminder/document/settings/messages; Ponto: aprovações/banco-horas/configurações; HRM: holidays/settings)
- Impacto: Dashboard sai de 3 módulos em pasta pra 7; trace_score global sobe.

**Sessão N+2** — **loop de validação** (B2 + C1 + A3):
- Pre-commit hook com validator
- ChatAssistant com OpenAI real (bye-bye stub)
- Implementar 3 testes prioritários (R-PONT-001, R-PONT-002, R-DOCVAULT-001)
- Impacto: documentação para de mentir, IA responde sintético, loop fecha.

**Sessão N+3** — **polimento UX** (C2 + C3 + D1 + C4):
- Markdown render nativo
- Busca full-text na memória
- Gráfico histórico de trace
- Kanban de issues
- Impacto: DocVault vira ferramenta diária, não apenas "painel de métricas".

## Consequências

**Positivas:**
- Roadmap visível dentro do próprio DocVault (dogfooding — DocVault documenta a si mesmo).
- Cada item tem tamanho calibrado, priorização clara.
- Contexto não se perde entre sessões.

**Negativas:**
- Roadmap vive em markdown (não em issue tracker). Risco de drift se não revisarmos.

**Mitigação**: `docvault:validate` pode futuramente verificar se ADR com status `accepted` tem itens marcados como completos em tempo razoável (adicionar check `ROADMAP_STALE`).

## Alternativas consideradas

- **GitHub Issues**: descartado — fricção alta pra 1 dev, preferimos manter tudo no repo por enquanto.
- **Notion/Linear**: descartado — contradiz ADR 0002 (file-based com DB espelhado).
- **Comentários TODO no código**: complementar, não substituto. TODO vira issue D5 eventual.
