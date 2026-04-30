# ADR ARQ-0001 (MemoriaAutonoma) · Fase 1 — Auto-síntese semanal

- **Status**: accepted
- **Data**: 2026-04-30
- **Decisores**: Wagner
- **Categoria**: arq
- **Relacionado**: ADRs 0035 (laravel/ai), 0050 (memória métricas), 0053 (MCP server)

## Contexto

O oimpresso já tem memória **compartilhada + auditada + self-host + custo zero** via:
- git source-of-truth (`memory/`)
- webhook GitHub → `mcp_memory_documents` cache
- `mcp_audit_log` (todas leituras tools MCP)
- skill `memory-sync` + `/sync-mem` (criados 2026-04-30) garantem propagação

Lacuna restante pra "memória que evolui sozinha": **auto-síntese**. Hoje `CURRENT.md` semanal é editado à mão; resumo do mês idem; Wagner gasta 2-3h/semana só consolidando o que aconteceu.

Comparação SOTA público (2026): Mem0/Letta/LangMem fazem auto-extração de fatos via LLM. Anthropic Memory Tool oficial (out/2025) idem. Todos cobram LLM call por mensagem.

## Decisão

**Implementar auto-síntese semanal como Fase 1** de uma stack `MemoriaAutonoma` em 4 fases:

| Fase | Capability | Custo/mês estimado | Esforço |
|---|---|---|---|
| **1 (esta ADR)** | Síntese semanal automática | $0.50/semana | 1 dia |
| 2 (futuro) | Auto-extração de drafts ADR | $5-15 | 2 dias |
| 3 (futuro) | Auto-validação contradição/duplicação | $2 | 3 dias |
| 4 (futuro) | Auto-evolução de skills | integrado F2 | 1 semana |

### Componentes Fase 1

1. **Comando**: `php artisan copiloto:sintese-semanal [--week=YYYY-Www] [--dry-run] [--force]`
2. **Schedule**: cron sex 18:00 (após semana de trabalho fechar)
3. **LLM**: Claude Haiku 4.5 (`claude-haiku-4-5-20251001`) via `laravel/ai` — modelo barato suficiente pra síntese
4. **Inputs coletados** (range segunda 00:00 → domingo 23:59):
   - Commits da semana (`git log --since=... --pretty`)
   - Arquivos novos em `memory/sessions/`, `memory/decisions/`, `memory/requisitos/`
   - Diffs de `CURRENT.md`, `TASKS.md`, `TEAM.md`
5. **Output**: `memory/sessions/SEMANA-YYYY-Www-resumo.md`
   - Frontmatter: `tipo: sintese-semanal`, `range: YYYY-MM-DD..YYYY-MM-DD`, `gerado_em: ts`, `gerado_por: copiloto-haiku-4-5`
   - Seções: Decisões da semana · Implementações mergeadas · Bloqueios identificados · Próximos passos · Referências (paths)
6. **Idempotência**: re-rodar mesma semana sobrescreve (com `--force`); sem `--force` aborta se arquivo já existe
7. **Auditoria**: logs em `copiloto-ai` channel + métrica `sintese_semanal_total` em `copiloto_memoria_metricas`

### Por que Haiku, não Sonnet/Opus
- Síntese é tarefa de extração+resumo, não raciocínio
- Custo Haiku: ~$0.001 input + $0.005 output por 1k tokens (ordem de magnitude menor)
- Tempo: ~5-15s por semana (input: ~5-10k tokens contexto, output: ~1-2k tokens síntese)
- Custo estimado: ~R$ 0.10 por execução = R$ 5/ano

### Por que sex 18h
- Fecha semana de trabalho; segunda Wagner abre Claude com síntese pronta
- Não conflita com cron 23:00 sync MemCofre nem 23:55 métricas nem dom 03:00 cleanup

## Consequências

### Positivas
- Wagner economiza 2-3h/semana de consolidação manual
- Síntese vira input pro `CURRENT.md` (Wagner copia/edita o que precisar)
- Time (Eliana/Felipe) que não acompanha tudo lê 1 arquivo curto e fica calibrado
- Vira histórico arqueológico — daqui 6 meses, "o que decidimos em mai/2026?" = ler 4 arquivos de SEMANA
- Custo trivial (~R$ 5/ano)
- Funciona com infra existente (laravel/ai já está aí, schedule já existe)

### Negativas
- LLM pode alucinar resumo errado — mitigação: arquivo é draft-só-pra-Wagner-revisar, não vira ADR canônica direto
- Dependência de API Anthropic — mitigação: fallback noop (pula semana, loga erro) se API down
- Janela fechada no domingo 23:59 — não capta deploy noturno de domingo (tradeoff aceitável)

## Alternativas consideradas

- **Auto-síntese diária** — REJEITADO: muito ruído, semana é granularidade certa pra reflexão
- **Auto-síntese ao push** — REJEITADO: dispara N vezes/dia, custo desnecessário
- **Síntese gerada na hora pelo Claude Code (sem cron)** — REJEITADO: depende do Wagner abrir Claude Code; queremos que apareça sozinho na pasta
- **Mem0/Letta hosted** — REJEITADO: cloud, lock-in, custo per-msg, contradiz princípio self-host
- **Opus/Sonnet** — REJEITADO: 10x mais caro, ganho marginal pra síntese

## Plano de implementação

1. `Modules/Copiloto/Ai/Agents/SinteseSemanalAgent.php` (instructions + prompt builder)
2. `Modules/Copiloto/Services/MemoriaAutonoma/SinteseSemanalService.php` (coleta inputs + chama agent + salva)
3. `Modules/Copiloto/Console/Commands/SinteseSemanalCommand.php` (CLI wrapper)
4. `app/Console/Kernel.php` — schedule `->fridays()->at('18:00')` ambiente live
5. `Modules/Copiloto/Tests/Feature/SinteseSemanalCommandTest.php` (golden + dry-run + idempotência)

## Refs

- ADR 0035 (laravel/ai canônico)
- ADR 0050 (copiloto_memoria_metricas)
- ADR 0061 (zero auto-mem privada — toda síntese vai pra git)
- skill `memory-sync` + `/sync-mem` (2026-04-30)
- Mem0 docs (referência SOTA)
- Anthropic Memory Tool (out/2025, alternativa rejeitada)
