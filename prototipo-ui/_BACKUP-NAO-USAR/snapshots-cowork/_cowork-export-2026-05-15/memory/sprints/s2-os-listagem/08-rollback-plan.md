# Rollback Plan — Sprint 2 (MWART OS Index)

> Procedimento pra reverter a migração Blade → React em caso de incident.
> Alvo: rollback completo em < 5 minutos, sem perda de dados.

## Princípio

Toda migração MWART é reversível por **uma única env var**. Não há mudança destrutiva no banco — índices novos não quebram Blade, e Blade não foi deletado.

## Cenários

### Cenário 1 — Erros JS no React (Sentry spike)

**Sintoma:** Sentry alerta com taxa > 1% de erros em `/os`.

**Ação imediata:**
```bash
# Em prod ou staging
MWART_OS_INDEX=false
php artisan config:clear
# Não precisa restart — Inertia detecta na próxima request
```

**Confirmação:** próxima request em `/os` retorna view Blade. Sentry para de alertar em < 60s.

**Pós-rollback:**
- Issue no GitHub com label `mwart-incident`
- Anexar dump do Sentry
- Bloquear `MWART_OS_INDEX=true` até root cause + fix + retest soak

### Cenário 2 — Performance degradada (p95 > 800ms)

**Sintoma:** Telescope/New Relic mostra p95 do controller > 2x baseline.

**Diagnóstico antes de rollback:**
```sql
-- Validar se índices estão sendo usados
EXPLAIN ANALYZE SELECT id, numero, status, prazo_entrega
FROM ordens_servico
WHERE empresa_id = ? AND status IN (?,?,?)
ORDER BY prazo_entrega
LIMIT 50;

-- Esperado: "Index Lookup" em idx_os_empresa_status_prazo
-- Se "Full Scan": índice não foi criado ou query mudou
```

Se índices ok mas latência alta → rollback igual cenário 1.
Se índices faltando → rodar migration de índices, não rollback.

### Cenário 3 — Bug de comportamento (filtro retorna lista errada)

**Sintoma:** usuário reporta que filtro X retorna OS que não deveria aparecer.

**Diagnóstico antes de rollback:**
```bash
# Reproduzir em staging com mesma query
php artisan tinker
> $req = Request::create('/os?status[]=arte&cliente_id=42');
> app(OsController::class)->index($req);
```

Se bug confirmado e crítico (multi-tenancy, RLS) → **rollback IMEDIATO** + audit log dos acessos no período.
Se bug menor (filtro cosmético) → hotfix + redeploy, sem rollback.

### Cenário 4 — Ataque ou vazamento via novos endpoints

**Sintoma:** SOC alerta sobre endpoint `/os/bulk` ou query string maliciosa.

**Ação imediata:**
```bash
# Rollback flag + bloqueio temporário no firewall
MWART_OS_INDEX=false
# WAF rule: bloquear POST /officeimpresso/os/bulk
```

Após bloqueio, security team revisa logs e patch antes de re-habilitar.

## Rollback do banco

**Os índices novos NÃO precisam ser revertidos** — não afetam Blade nem queries existentes. Mantêm em produção mesmo com flag off.

Se algum índice estiver causando lock/perf issue (improvável):
```sql
DROP INDEX idx_os_empresa_status_prazo ON ordens_servico;
DROP INDEX idx_os_empresa_cliente ON ordens_servico;
DROP INDEX idx_os_responsavel_status ON ordens_servico;
DROP INDEX idx_os_numero ON ordens_servico;
DROP INDEX ft_os_descricao_obs ON ordens_servico;
```

## Comunicação durante incident

1. **T+0min** — Slack `#oimpresso-incidents`: "MWART OS Index rollback em andamento. Causa: <breve>. ETA resolução: 5min."
2. **T+5min** — Confirmação rollback ok, link pra Sentry/Telescope normalizado
3. **T+1h** — Postmortem inicial em `memory/incidents/<data>-mwart-os-index.md`
4. **T+24h** — Postmortem completo + plano de retest

## Re-habilitar após rollback

Critérios mínimos pra `MWART_OS_INDEX=true` de novo:

- [ ] Root cause documentada
- [ ] Fix mergeado e em staging
- [ ] Soak 48h novo em staging (zero do mesmo erro)
- [ ] Aprovação Wagner explícita
- [ ] Janela de baixo tráfego (madrugada/fds)
- [ ] Rollout gradual: 10% → 50% → 100% com 24h entre etapas

## Sinais que NÃO justificam rollback

- ⚠️ 1 ou 2 erros JS isolados (investigar, não reverter)
- ⚠️ Reclamação de UX de 1 usuário (logar, não reverter — Sprint 2 é port 1:1)
- ⚠️ p95 marginalmente acima do target em 1 medição (medir 30min antes de agir)
- ⚠️ Lentidão geral do sistema sem correlação com `/os` (não é o MWART)

## Donos

- **Rollback técnico:** Wagner (acesso `.env` prod)
- **Decisão de rollback:** Wagner ou on-call
- **Postmortem:** quem fez o deploy + Wagner
- **Re-habilitar:** Wagner (irrevogável)

## Verificação trimestral

Todo trimestre, rodar drill de rollback em staging:
1. Forçar `MWART_OS_INDEX=true`
2. Simular erro (throw em OsController)
3. Cronometrar tempo até rollback
4. Atualizar este doc se procedimento mudou
