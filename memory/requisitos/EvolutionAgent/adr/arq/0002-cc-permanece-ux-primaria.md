# ADR ARQ-0002 (EvolutionAgent) · Claude Code permanece como UX primária

- **Status**: accepted
- **Data**: 2026-04-26
- **Decisores**: Wagner
- **Categoria**: arq

## Contexto

Wagner: "quero continuar usando a central aqui no claude code. vai ter que estar integrado. ja me adptei aqui. nem tudo vai ser por api."

Vizra ADK oferece interface própria (`vizra:chat`, dashboard Livewire). Adotar essa interface significaria Wagner mudar de ferramenta — perda de adaptação acumulada.

Adicionalmente: Vizra é **MCP client** (consome MCP), não MCP server out-of-the-box. Não dá pra plugar Vizra como tool nativo no Claude Code sem escrever wrapper MCP.

## Decisão

Modelo **híbrido**:
- **Claude Code = interface primária**. Wagner sempre fala com CC.
- **Vizra ADK = backend invisível**. Roda no Laravel, expõe Artisan commands.
- **Bridge = `php artisan evolution:*`** chamado via Bash pelo CC.
- **Subagent CC** (`.claude/agents/evolucao.md`) instrui CC a usar os Artisan commands em vez de re-ler `memory/`.
- **Dashboard Vizra** disponível em `/admin/vizra` mas só pra inspeção sob demanda; não é fluxo padrão.

## Consequências

**Positivas:**
- Wagner não muda nada na rotina; aproveita memory + eval do Vizra de graça.
- ~12× ROI vs construir MCP server custom (1-2 dias evitados).
- Vizra continua viável como base mesmo sem MCP server; pode-se adicionar depois.
- Eval em CI roda **sem CC online**: GH Actions chama `evolution:eval` via composer.

**Negativas:**
- Texto serializado entre `artisan` e CC (em vez de tools nativas). Latência ~1-2s extra por chamada.
- CC pode esquecer de chamar `evolution:query` e re-ler `memory/` direto (gasto de tokens). Mitigação: subagent instrui explicitamente.
- Dashboard Vizra menos visível; Wagner tem que lembrar que existe quando quer trace.

**ROI estimado**: ~12× vs MCP custom (path B descartado).

## Alternativas consideradas

| Alt | Motivo de rejeição |
|---|---|
| MCP server custom envolvendo Vizra | +1-2 dias de wrapper; Wagner não pediu UX nativa. Pode ser feito na Fase 4 se latência incomodar. |
| Só `.claude/agents/` (sem Vizra) | Sem memory persistente entre sessões; sem eval. Volta à dor original. |
| Vizra `vizra:chat` como UI | Wagner explicit vetou. |

## Implementação

- `app/Console/Commands/Evolution/*.php` — wrappers Artisan que chamam Vizra agents internamente.
- `.claude/agents/evolucao.md` — subagent CC com YAML frontmatter; allowlist Bash `php artisan evolution:*`.
- Output dos comandos sempre em **JSON** quando consumido por CC; markdown quando usado direto pelo Wagner com `--human`.

## Re-avaliação

Re-avaliar este ADR se:
- Latência de `artisan` > 3s na maioria das queries (gargalo PHP boot).
- Wagner pedir UX mais rica (autocomplete de comandos, etc.).
- Vizra publicar suporte oficial a expor agentes como MCP server.

## Links

- [SPEC §5 Arquitetura](../../SPEC.md#5-arquitetura)
- [ADR ARQ-0001 Vizra como base](0001-vizra-adk-como-base.md)
