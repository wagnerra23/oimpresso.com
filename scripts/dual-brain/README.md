# Brain A Daemon — ADS

System 1 do Dual Brain (ARQ-0002). Monitor 24/7 que detecta eventos no codebase
e submete ao Decision Router via `POST /api/ads/route`.

## Setup (uma vez)

```bash
cd scripts/dual-brain
cp .env.example .env
npm install
```

Edite `.env`:

```env
ADS_API_URL=https://oimpresso.test/api/ads/route
ADS_HEALTH_URL=https://oimpresso.test/api/ads/health
ADS_API_KEY=<copiar de ADS_API_KEY no .env do Laravel>
DEFAULT_BUSINESS_ID=1
REPO_PATH=D:/oimpresso.com
LARAVEL_LOG_PATH=D:/oimpresso.com/storage/logs/laravel.log
ALLOW_INSECURE_TLS=true   # dev local com Herd; false em produção
```

## Smoke test (validar e2e)

```bash
npm run smoke
```

Esperado: 3 eventos sintéticos roteados (blocked, brain_b, brain_b),
gravados em `mcp_dual_brain_decisions`.

## Operação contínua

```bash
npm start
```

O daemon:
1. Faz health check inicial (sai com erro se ADS não responde)
2. Lê HEAD do git e armazena como `lastSha`
3. Lê tamanho atual do `laravel.log` e armazena como `offset` (não reprocessa histórico)
4. Loop:
   - A cada `GIT_POLL_INTERVAL_MS` (padrão 30s): compara HEAD; novos commits → triage → ADS
   - A cada `LOG_POLL_INTERVAL_MS` (padrão 5s): lê novos bytes do log; ERROR/CRITICAL → triage → ADS

`Ctrl+C` para parar (graceful shutdown).

## Triage rule-based (v1)

`triage.js` mapeia padrões observados (commit subject, log line) para `event_type`
canônico do RiskEngine. Sem LLM nesta versão — substituível por chamada Ollama
no v2.

Exemplos de mapeamento:
- `^migrate(...)` → `db_schema_change`
- `composer.json` → `composer_json_change`
- `auth|middleware.*auth` → `auth_middleware`
- `nfse|nfe|fiscal` → `nfse_fiscal_logic`
- `^docs(...)` → `md_link_fix`
- log `PDOException|SQLSTATE` → `db_schema_change`

Padrão não reconhecido vira `unknown_commit` (RiskEngine usa prior conservador).

## Próximos passos (v2+)

- [ ] Substituir triage rule-based por Ollama qwen2.5-coder
- [ ] Watcher de métricas DB (anomalia em `copiloto_memoria_metricas`)
- [ ] Watcher de tasks paradas há >2h em `mcp_tasks`
- [ ] Persistir `lastSha` e log `offset` em arquivo (sobreviver restart)
- [ ] Rodar como serviço Windows (NSSM) ou systemd no Linux
