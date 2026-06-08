# Rollback Plan — Sprint 2 (MWART Repair Index)

> Procedimento pra reverter a migração Blade → React em caso de incident.
> Alvo: rollback completo em < 5 minutos, sem perda de dados.

## Princípio

Toda migração MWART é reversível por **uma única env var** (ou shrink da lista beta de `business_ids`). Não há mudança destrutiva no banco — índices novos não quebram Blade, e Blade não foi deletado.

## Cenários

### Cenário 1 — Erros JS no React (Sentry spike)

**Sintoma:** Sentry alerta com taxa > 1% de erros em `/repair/repair`, ou Larissa (ROTA LIVRE) reclama no WhatsApp.

**Ação imediata (todos os businesses):**

```bash
ssh -p 65002 u906587222@148.135.133.115
cd domains/oimpresso.com/public_html

# Editar .env: MWART_REPAIR_INDEX=false
php artisan config:clear
# Não precisa restart — Inertia detecta na próxima request
```

**Ação imediata (apenas 1 business problemático):**

```bash
# Edita .env: tirar o id da lista MWART_REPAIR_INDEX_BIZ
# Ex: de "4,12,33" pra "12,33" (remove ROTA LIVRE)
php artisan config:clear
```

**Confirmação:** próxima request em `/repair/repair` retorna view Blade. Sentry para de alertar em < 60s.

**Pós-rollback:**

- Issue no GitHub com label `mwart-incident`
- Anexar dump do Sentry + screenshot do erro (se houver)
- Bloquear `MWART_REPAIR_INDEX=true` até root cause + fix + retest soak
- Comunicar via skill `publication-policy`: Wagner notifica time + (se afetar cliente) cliente

### Cenário 2 — Performance degradada (p95 > 800ms)

**Sintoma:** Telescope mostra p95 do `RepairController@index` > 2x baseline (target = 400ms).

**Diagnóstico antes de rollback:**

```sql
-- Validar se índices estão sendo usados
EXPLAIN ANALYZE
SELECT t.id, t.invoice_no, t.repair_status_id, t.repair_due_date
FROM transactions t
WHERE t.business_id = 4
  AND t.type = 'sell'
  AND t.sub_type = 'repair'
  AND t.repair_status_id IN (1,2,3)
ORDER BY t.repair_due_date
LIMIT 50;

-- Esperado: "Index Lookup" em idx_repair_biz_status_due
-- Se "Full Scan" ou "Using filesort" sem índice: índice não foi criado
```

- Se índices ok mas latência alta → rollback igual cenário 1
- Se índices faltando → rodar `php artisan migrate --force` (idempotente, ver 02), não rollback
- Se query plan mudou após upgrade MySQL/MariaDB → rollback temporário + analyse table

### Cenário 3 — Bug de comportamento (filtro retorna lista errada)

**Sintoma:** usuário reporta que filtro X retorna OS que não deveria aparecer (ou pior — vê OS de outro business).

**⚠️ Se sintoma envolve cross-tenant (vê OS de outro business):**

- **ROLLBACK IMEDIATO** + audit log dos acessos no período
- Issue label `security`, severity `critical`
- Skill `multi-tenant-patterns` no review do fix obrigatória
- Memória do projeto: vazar entre tenants é o pior bug deste projeto

**Diagnóstico antes de rollback (bugs comuns):**

```bash
# Reproduzir em staging com mesma query
php artisan tinker
> $req = Request::create('/repair/repair?repair_status_id[]=3&contact_id=42');
> $req->setLaravelSession(...);  // simular session com business_id
> app(RepairController::class)->index($req);
```

- Bug confirmado e crítico (multi-tenancy) → rollback imediato
- Bug menor (filtro cosmético, ordem errada) → hotfix + redeploy, sem rollback

### Cenário 4 — Quebra de permissão Spatie

**Sintoma:** user com só `repair.view_own` consegue ver OS de colegas (ou inverso, user com `repair.view` recebe 403).

**Ação:**

- Rollback imediato (cenário 1)
- Audit `transactions.created_by` vs ` $user->id` no log dos últimos N dias
- Fix em PR separado com Pest reproduzindo o bug
- Re-deploy só após PR mergeado e teste passing

### Cenário 5 — Composer/build fora de sincronia

**Sintoma:** tela branca ou erro `null.component` Inertia (memória `composer_install_obrigatorio_pos_deploy`).

**Ação:**

```bash
ssh -p 65002 u906587222@148.135.133.115
cd domains/oimpresso.com/public_html
composer install --no-interaction --prefer-dist
php artisan config:clear
php artisan view:clear
# Não usar --no-dev (Faker é usado em prod)
```

Se persistir, rollback igual cenário 1.

### Cenário 6 — Ataque ou query injection via novos endpoints

**Sintoma:** SOC alerta sobre query string maliciosa em `/repair/repair` ou bulk.

**Ação imediata:**

- Rollback flag (cenário 1)
- Bloqueio temporário no firewall Hostinger se ataque ativo
- Security review antes de re-habilitar

## Rollback do banco

**Os índices novos NÃO precisam ser revertidos** — não afetam Blade nem queries existentes. Mantêm em produção mesmo com flag off. Custo de manutenção é baixo (< 50 MB total).

Se algum índice estiver causando lock/perf issue (improvável):

```sql
DROP INDEX idx_repair_biz_status_due ON transactions;
DROP INDEX idx_repair_biz_contact_created ON transactions;
DROP INDEX idx_repair_biz_waiter_status ON transactions;
DROP INDEX idx_repair_biz_creator_status ON transactions;
DROP INDEX idx_repair_biz_location_status ON transactions;
```

**ALERTA**: `transactions` é tabela quente do UltimatePOS. Drop de índice em horário de pico pode causar lock. Fazer fora de horário comercial e com `pt-online-schema-change` se volume justificar.

## Comunicação durante incident

1. **T+0min** — Anotar no canal interno (ou WhatsApp Wagner-time): "MWART Repair Index rollback em andamento. Causa: <breve>. ETA resolução: 5min."
2. **T+5min** — Confirmação rollback ok, link pra Sentry/Telescope normalizado
3. **T+1h** — Postmortem inicial em `memory/incidents/<YYYY-MM-DD>-mwart-repair-index.md`
4. **T+24h** — Postmortem completo + plano de retest

Se afetar cliente externo (ROTA LIVRE em beta): mensagem WhatsApp pra Larissa explicando o que aconteceu. Wagner cuida da comunicação (memória `cliente_rotalivre` — relação direta).

## Re-habilitar após rollback

Critérios mínimos pra `MWART_REPAIR_INDEX=true` de novo:

- [ ] Root cause documentada em incident postmortem
- [ ] Fix mergeado e em staging (PR separado, label `mwart-incident-fix`)
- [ ] Soak 48h novo em staging (zero do mesmo erro)
- [ ] Aprovação Wagner explícita (skill `publication-policy`)
- [ ] Janela de baixo tráfego (madrugada/sábado)
- [ ] Rollout gradual: começar de novo do beta (1 business_id), não direto 100%

## Sinais que NÃO justificam rollback

- ⚠️ 1 ou 2 erros JS isolados (investigar, não reverter)
- ⚠️ Reclamação de UX de 1 usuário (logar em backlog Sprint 3+, não reverter — Sprint 2 é port 1:1)
- ⚠️ p95 marginalmente acima do target em 1 medição (medir 30min antes de agir)
- ⚠️ Lentidão geral do sistema sem correlação com `/repair/repair` (não é o MWART — investigar Hostinger/MySQL global)
- ⚠️ Pedido pra "voltar a tela antiga" sem bug concreto (responder com a memória `preference_cache_estado_preservado` — talvez seja regressão de UX que merece fix, não rollback)

## Donos

- **Rollback técnico (executar):** Wagner [W] (acesso `.env` prod via SSH Hostinger)
- **Decisão de rollback:** Wagner [W] ou on-call (se houver rotação)
- **Postmortem:** quem fez o deploy + Wagner
- **Re-habilitar:** Wagner [W] (irrevogável; skill `publication-policy`)
- **Comunicação cliente:** Wagner [W] (Larissa direto via WhatsApp)

## Verificação trimestral (drill)

Todo trimestre, rodar drill de rollback em staging:

1. Forçar `MWART_REPAIR_INDEX=true` + ROTA LIVRE simulado em staging
2. Simular erro (throw em RepairController após buildIndexData)
3. Cronometrar tempo até rollback
4. Atualizar este doc se procedimento mudou (ex: SSH Hostinger flaky pediu warm-up obrigatório — memória `reference_hostinger_analise`)

## Apêndice — receita SSH robusto pra emergência

Memória `reference_hostinger_analise`:

```bash
# 1) Warm-up Hostinger (5 hits curl)
for i in 1 2 3 4 5; do
  curl -s -o /dev/null --max-time 15 https://oimpresso.com/login
done

# 2) SSH com timeouts altos (primeira tentativa quase sempre dá timeout sem isso)
ssh -4 -o ConnectTimeout=900 -o ServerAliveInterval=3 \
    -o ServerAliveCountMax=200 -o ConnectionAttempts=5 \
    -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
    'cd domains/oimpresso.com/public_html && sed -i "s/^MWART_REPAIR_INDEX=true/MWART_REPAIR_INDEX=false/" .env && php artisan config:clear'
```
