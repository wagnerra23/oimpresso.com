# Checklist Wagner — rollout Sprint 1

> Siga **na ordem**. Cada passo tem critério de pronto. Se algum falhar,
> pause e me chame antes de seguir.
>
> **Tempo total estimado:** 1 dia útil (~4h trabalho efetivo + 24h soak).

---

## ☐ Passo 1 — ADR canônica (5 min)

```bash
cd ~/projetos/oimpresso.com

# Próximo número canônico
LAST=$(ls memory/decisions/0*.md | tail -1 | grep -oE '[0-9]+' | head -1)
NEXT=$(printf "%04d" $((10#$LAST + 1)))
echo "Próxima ADR: $NEXT"

# Copia o template do sprint pra memory/decisions/
cp memory/sprints/s1-daily-brief/01-adr-memory-daily-brief.md \
   memory/decisions/${NEXT}-daily-brief.md

# Edita: substitui "MEMORY-NNNN" por "MEMORY-${NEXT}" no arquivo
sed -i "s/MEMORY-NNNN/MEMORY-${NEXT}/g" memory/decisions/${NEXT}-daily-brief.md

git add memory/decisions/${NEXT}-daily-brief.md
git commit -m "docs(adr): MEMORY-${NEXT} Daily Brief contract — Sprint 1"
git push
```

**Pronto quando:**
- [ ] Arquivo `memory/decisions/<NEXT>-daily-brief.md` existe
- [ ] `MEMORY-NNNN` foi substituído pelo número real em todo arquivo
- [ ] Webhook GitHub indexou em `mcp_memory_documents` (verifica com
      `mcp__oimpresso__decisions-search { query: "Daily Brief" }`)

---

## ☐ Passo 2 — Migration SQL (30 min)

```bash
php artisan make:migration create_daily_brief_schema
# Copia conteúdo de memory/sprints/s1-daily-brief/02-schema-aggregator.sql
# pro arquivo de migration gerado, dentro de up() use DB::statement(<<<SQL ... SQL)

php artisan migrate

# Sanity check
# MySQL via SSH tunnel pro Hostinger (ADR 0053)
mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "DESCRIBE mcp_briefs;"
mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "DESCRIBE mcp_skill_telemetry;"
mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "DESCRIBE mcp_brief_inputs_cache;"
mysql -u $DB_USER -p$DB_PASS $DB_NAME -e "CALL refresh_brief_inputs_cache(); SELECT * FROM mcp_brief_inputs_cache\\G"
```

**Pronto quando:**
- [ ] Tabelas `mcp_briefs`, `mcp_skill_telemetry` e `mcp_brief_inputs_cache` existem
- [ ] `CALL refresh_brief_inputs_cache()` executa sem erro e popula a linha singleton
- [ ] Procedures `refresh_brief_inputs_cache()` e `get_current_brief()` existem (`SHOW PROCEDURE STATUS WHERE Db = '$DB_NAME'`)
- [ ] Migration aparece em `php artisan migrate:status`

⚠ **Tabelas futuras já comentadas no schema** com `TODO Sprint 3/5 — ATIVAR`:
- `mcp_design_locks` (Sprint 3) → campo `in_flight` fica NULL
- `mcp_page_charters` (Sprint 3) → campo `charters_stale` fica NULL
- `mcp_route_migration_state` (Sprint 5) → subcampo `flags.migration_aging_critical` fica 0

Quando os Sprints chegarem, descomente os blocos `TODO` na procedure
`refresh_brief_inputs_cache` e troque os `NULL`/`0` pelas variáveis correspondentes.

---

## ☐ Passo 3 — Module Brief + Service (1h)

```bash
php artisan module:make Brief

# Estrutura mínima:
# Modules/Brief/
#   Http/Controllers/BriefFetchController.php   ← copia do 04-tool-brief-fetch.md
#   Console/GenerateBriefCommand.php            ← copia do 04-tool-brief-fetch.md
#   Services/BriefGeneratorService.php          ← implementa chamada Anthropic
#   Services/BriefValidator.php                 ← copia do 03-prompt-generator.md
```

**BriefGeneratorService** chama claude-sonnet-4-6 via SDK ou HTTP direto:
- system prompt = bloco fixo do `03-prompt-generator.md`
- user prompt = template com `{{NOW_BR_HUMAN}}`, `{{MV_JSON}}` substituídos
- temperature 0.2, max_tokens 4096
- captura `usage.input_tokens + usage.output_tokens` pra `cost_usd`

**Pronto quando:**
- [ ] `php artisan brief:generate --dry-run` imprime markdown válido
- [ ] Validator passa (7 headers + ---END--- + ≤3500 tokens)
- [ ] Custo da call aparece no log (~$0.04-0.06)

---

## ☐ Passo 4 — Cron (15 min)

Em `app/Console/Kernel.php` (Laravel <11) ou `routes/console.php` (Laravel 11+):

```php
$schedule->command('brief:generate')
    ->cron('0 7,11,14,17,20,23 * * *')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->emailOutputOnFailure(env('OPS_EMAIL'));
```

**Pronto quando:**
- [ ] `php artisan schedule:list` mostra `brief:generate` 6x/dia
- [ ] `php artisan schedule:work` rodando em dev, gera brief sem erro
- [ ] Em prod, supervisor/systemd já tem `php artisan schedule:work`
      ativo (provavelmente já tem)

---

## ☐ Passo 5 — Registrar tool MCP (30 min)

No seu `mcp.oimpresso.com` server, adicione `brief-fetch` ao registro de
tools (formato exato depende da implementação atual — siga o padrão das
outras tools como `decisions-search`).

Schema → `04-tool-brief-fetch.md` seção "Schema da tool".
Handler → mesmo arquivo, classe `BriefFetchController`.
Rota → `routes/api.php`, prefix `mcp`, throttle `60,1`.

**Pronto quando:**
- [ ] `curl -X POST .../api/mcp/tools/brief-fetch` retorna 200 com brief
- [ ] Em qualquer Claude Code: `mcp__oimpresso__brief-fetch` aparece
- [ ] Cache funciona: 2 calls em 1min → 1 SELECT SQL apenas

---

## ☐ Passo 6 — Skill Tier A (10 min)

```bash
mkdir -p .claude/skills/brief-first
# Cola o bloco "Conteúdo do arquivo" do 05-skill-brief-first.md
# em .claude/skills/brief-first/SKILL.md

git add .claude/skills/brief-first/
git commit -m "feat(skills): brief-first Tier A — Sprint 1"
git push
```

**Pronto quando:**
- [ ] Arquivo `.claude/skills/brief-first/SKILL.md` no repo
- [ ] Em sessão Claude Code nova, skill aparece como ativa
- [ ] Primeira tool chamada na sessão é `brief-fetch` (não outra)

---

## ☐ Passo 7 — Anúncio time (10 min)

Mensagem para o time (Felipe/Maíra/Luiz/Eliana) via canal habitual
(WhatsApp do time, e-mail interno, ou MCP inbox channel `team` se já
estiver acessível a humanos):

```
🆕 Daily Brief no ar (Sprint 1 da Constituição v2)

A partir de agora, Claude Code começa toda sessão chamando uma única tool
(brief-fetch) que devolve o estado consolidado do projeto em ~3k tokens.

Pra vocês, isso significa:
• Sessões mais rápidas (-50% tokens iniciais)
• Menos custo de API
• Contexto consistente entre todos nós (humanos + agents)

O que muda no seu fluxo: NADA. A skill é automática. Só vai notar que
o Claude começa a sessão "sabendo de cara" o que tá rolando.

Brief regenera 6x/dia. Dá pra ver o último em:
> mcp__oimpresso__brief-fetch {}

Dúvidas: ADR MEMORY-XXXX no repo.
```

---

## ☐ Passo 8 — Soak 48h + métricas semana 1 (passivo)

Aguarde 48h. Depois rode:

```sql
-- Adoção
SELECT
    skill_name,
    COUNT(*) AS triggers,
    COUNT(DISTINCT agent_id) AS agents
FROM mcp_skill_telemetry
WHERE skill_name = 'brief-first'
  AND triggered_at > NOW() - INTERVAL 48 HOUR
GROUP BY skill_name;

-- Token savings
SELECT
    DATE(generated_at) AS dia,
    AVG(token_count) AS tokens_brief_avg,
    SUM(cost_usd) AS custo_dia
FROM mcp_briefs
WHERE valid = 1
  AND generated_at > NOW() - INTERVAL 7 DAY
GROUP BY 1 ORDER BY 1 DESC;

-- Distribuição de uso por agent
SELECT agent_id, COUNT(*) AS calls, MAX(triggered_at) AS ultima
FROM mcp_skill_telemetry
WHERE skill_name = 'brief-first'
  AND triggered_at > NOW() - INTERVAL 7 DAY
GROUP BY agent_id ORDER BY calls DESC;
```

**Critério de sucesso semana 1:**
- [ ] ≥6 agents distintos chamaram brief-fetch
- [ ] ≥90% das sessões começam com brief-fetch
- [ ] Custo total ≤ $3.50 (semana, ~$0.50/dia)
- [ ] Zero falhas de geração persistente (>2h sem brief válido)

Se falhar qualquer critério → me chama, ajustamos antes de seguir Sprint 2.

---

## Fim do Sprint 1

Após semana 1 OK, posso começar **Sprint 2 — Constituição v2 + 5 skills
Tier A finalizadas + audit das skills atuais (~18 válidas) + reescrita do
CLAUDE.md**.

Avise quando estiver pronto pra Sprint 2.
