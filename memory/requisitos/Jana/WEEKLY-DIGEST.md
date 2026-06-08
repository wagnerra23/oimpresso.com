# Jana Weekly Digest — guia operacional

> **Status:** ✅ ATIVO em prod desde 2026-05-13 (AUDITORIA G8 P2 — geração arquivo+DB) → 2026-05-15 (D8 #6 — entrega por email)
> **Schedule:** segundas 09:00 BRT (`app/Console/Kernel.php` linha 84-94)
> **Custo médio:** ~R$ [redacted Tier 0] por digest (gpt-4o-mini, ~3-5k tokens input / ~1k output)
> **ADR base:** ADR 0091 (Daily Brief) — mesma camada L7 Constituição V2

Fecha o gap D8 #6 catalogado por `memoria-senior` 2026-05-15: Daily Brief 6×/dia já existia
(ADR 0091), mas **faltava Weekly Digest consolidado** entregue ao Wagner toda segunda 09h.

## Pattern de referência

Reflect-style weekly review (Reflect.app pattern). Estrutura fixa em 5 seções consolidando
toda a atividade da semana — diferente da **síntese narrativa Wagner-style** que roda
sexta 18h (`copiloto:sintese-semanal`, modelo Haiku, prosa pessoal). Funções complementares:

| | `copiloto:sintese-semanal` | `jana:weekly-digest` |
|---|---|---|
| **Quando** | sex 18h | seg 09h |
| **Estilo** | Narrativa Wagner-style | Reflect-style estruturado |
| **Modelo** | Anthropic Haiku 4.5 | OpenAI gpt-4o-mini |
| **Output** | `memory/sessions/SEMANA-YYYY-Www-resumo.md` | `memory/sessions/WEEKLY-DIGEST-YYYY-Www.md` + DB + Email |
| **Quem lê** | Wagner em retro | Wagner abre segunda + time MCP via tool |

## 5 seções fixas do digest

Gerado pelo `WeeklyDigestAgent` (`Modules/Jana/Ai/Agents/WeeklyDigestAgent.php`):

1. **Marco da semana** — 1-2 frases destacando o que mais marcou (mais peso)
2. **Trabalho entregue** — bullets de PRs mergeados + US closed
3. **Cycle progress** — % goals achieved/in_progress/blocked
4. **Decisões importantes** — ADRs novas + escalations HITL
5. **Próxima semana — sugestões priorizadas** — 3-5 itens prováveis

## Schedule (já registrado)

```php
// app/Console/Kernel.php linha 84-94
$schedule->command('jana:weekly-digest')
    ->mondays()
    ->at('09:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->environments(['live'])
    ->onFailure(function () {
        \Log::channel('copiloto-ai')->error(
            'Schedule jana:weekly-digest FALHOU — Reflect-style weekly não gerado'
        );
    });
```

## Como rodar manual

```bash
# Semana anterior (default), envia email pro business.owner.email
php artisan jana:weekly-digest

# Semana específica
php artisan jana:weekly-digest --week=2026-W19

# Coleta + estima custo SEM chamar LLM nem enviar email
php artisan jana:weekly-digest --dry-run

# Re-gerar sobrescrevendo existente
php artisan jana:weekly-digest --week=2026-W19 --force

# Pular envio email (útil em CI / teste local)
php artisan jana:weekly-digest --no-email

# Override destinatário (debug ou multi-recipient)
php artisan jana:weekly-digest --email-to=outro@example.com

# Business alvo (default 1 — superadmin)
php artisan jana:weekly-digest --business-id=4
```

## Métricas tracked + fontes

Coletadas por `WeeklyDigestService::coletarContextoEMetricas()`:

| Métrica | Fonte | Observações |
|---|---|---|
| `commits` | `git log --since=... --until=...` no working tree | top 50 + count total |
| `prs_merged` | `gh pr list --state merged --search merged:RANGE` | fallback gracioso se gh CLI ausente |
| `us_closed` | `mcp_tasks` WHERE status='done' AND closed_at IN range | colunas detectadas via `Schema::getColumnListing` (back-compat) |
| `us_created` | `mcp_tasks` WHERE created_at IN range | top 50 |
| `adrs_new` | `git log --diff-filter=A memory/decisions/` | só commits que ADICIONARAM ADR |
| `handoffs` | `memory/handoffs/YYYY-MM-DD-HHMM-*.md` por filename prefix | sort filename |
| `cycle_progress_pct` | `mcp_cycles` ativo + `mcp_cycle_goals.status` | achieved/in_progress/blocked |
| `audit_decisions` (texto) | `mcp_audit_log` filtrado por `action LIKE '%hitl%'` ou `%escalat%` | só se tabela existir |

Todas as queries **NÃO** usam `business_id` global scope nos casos `mcp_*` porque
essas tabelas são **repo-wide** (governance superadmin), não tenant data — ver ADR 0093
§"Tabelas repo-wide vs tenant".

## Email entrega

`Modules/Jana/Mail/WeeklyDigestMail.php` (Mailable Markdown Laravel) + template
`Modules/Jana/Resources/views/emails/weekly-digest.blade.php` (Blade markdown components).

Destinatário derivado em runtime de `Business::find($businessId)->owner->email`
(ADR 0093: PII nunca hardcoded). Override via `--email-to=`.

Subject: `Jana — Weekly Digest 2026-W19 (2026-05-11 → 2026-05-17)`

CTA principal: botão markdown apontando pra `/governance` (Cockpit governance).

## Persistência DB

Tabela: `mcp_weekly_digests` (`uniq_weekly_digest_week` — 1 row por semana ISO)

```sql
CREATE TABLE mcp_weekly_digests (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    week VARCHAR(8) UNIQUE,           -- "2026-W19"
    range_start DATE,
    range_end DATE,
    digest_markdown LONGTEXT,
    metrics TEXT,                     -- JSON serializado
    tokens_in INT UNSIGNED DEFAULT 0,
    tokens_out INT UNSIGNED DEFAULT 0,
    cost_brl DECIMAL(10,6) DEFAULT 0,
    model VARCHAR(50) DEFAULT 'gpt-4o-mini',
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

Migration: `Modules/Jana/Database/Migrations/2026_05_13_140000_create_mcp_weekly_digests_table.php`.

Tool MCP `weekly-digest-fetch` (`Modules/Jana/Mcp/Tools/WeeklyDigestFetchTool.php`) permite
consultar via MCP server (tipicamente Felipe/Maiara abrem digest via Claude Code).

## Como adicionar nova métrica

1. Editar `WeeklyDigestService::coletarContextoEMetricas()` — adicionar passo numerado
2. Acrescentar chave em `$metrics = [...]`
3. Atualizar heredoc `CTX` pra incluir bloco bruto pro LLM
4. Atualizar tabela em `weekly-digest.blade.php` (linha `| Métrica | Valor |`)
5. Pest test em `JanaWeeklyDigestCommandTest.php`: verificar `toHaveKeys([..., 'nova_metrica'])`

## Troubleshooting

### "Digest não chegou no email da segunda"

```bash
# 1. Confirma se schedule rodou
grep "weekly-digest" storage/logs/laravel.log | tail -20

# 2. Roda manual pra ver erro
php artisan jana:weekly-digest --force

# 3. Verifica destinatário
php artisan tinker
>>> App\Business::find(1)->owner->email
```

### "Métricas zeradas em semana cheia de PRs"

- `gh` CLI não autenticado no servidor? → `gh auth status` (CT 100 ou Hostinger)
- Working tree desatualizado? → `git pull origin main`
- Tabela `mcp_tasks` ausente? → migrate

### "Email não chega mas SUCCESS no log"

Caso comum: business sem `owner_id` ou owner sem `email`. Override:
```bash
php artisan jana:weekly-digest --email-to=wagner@oimpresso.com
```

### "Custo subindo acima do esperado"

Default ~R$ [redacted Tier 0]/run. Se `cost_brl > 0.01` consistente, verificar:
- Range de commits anormalmente grande (semana de migração?)
- Contexto truncado a 25k chars no service — não deveria estourar
- Modelo trocou? Check `mcp_weekly_digests.model` — deve ser `gpt-4o-mini`

## Histórico

- **2026-05-13** (G8 P2 AUDITORIA-KNOWLEDGE-ARCHITECTURE) — geração inicial: command + service + agent + tabela + Pest 6 + MCP tool
- **2026-05-15** (D8 #6 AUDITORIA-MEMORIA, memoria-senior) — entrega por email: Mailable + Blade markdown + opções `--no-email`/`--email-to`/`--business-id` + Pest 6 novos. Fecha gap "Weekly digest populado" pro Wagner abrir caixa segunda 09h e ter o roundup pronto, sem precisar abrir Cockpit manual.

## Evolução possível (próximos passos)

1. **Slack webhook opcional** — adicionar flag `--slack-channel=#geral` que renderiza mesma estrutura no Slack
2. **Centrifugo notify Wagner** quando digest pronto — push real-time além do email
3. **Diff vs semana anterior** — coluna "Δ vs prev" na tabela métricas (delta_pct vs WoW)
4. **Comparativo cycle inteiro** — segunda do último cycle, digest consolidado das 2 semanas
5. **Time MCP destinatários** — quando Felipe/Maiara/Eliana entrarem (TEAM.md), digest envia pra todos os roles ≥dev
