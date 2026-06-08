# CAPTERRA-FICHA — Officeimpresso

> Ficha canônica de benchmark do módulo bridge Officeimpresso.
> **Bucket**: `vertical_client_facing` ([ADR 0160](../../decisions/0160-scoped-scorecard-evaluator-v3.md))
> **Wave 23** — saturação 60 → ≥85 (rubrica scoped vertical_client_facing.yaml)
> ADR governança: [0089](../../decisions/0089-capterra-driven-module-evolution.md) + [0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) §P7

---

## Identidade do módulo

- **Nome interno**: `Officeimpresso`
- **Domínio**: Bridge desktop Delphi (sistema legacy OfficeImpresso 26+ anos) ↔ Hostinger (sistema novo Laravel)
- **Função**: Licenciamento + sync de dados externos + audit Passport API + parse log
- **Estado lifecycle** (ADR 0121): **legacy bridge** (sem FSM, audit-only LicencaLog append)
- **Clientes**: 6-7 saudáveis (Vargas, Extreme, Gold, Zoom, Fixar, Mhundo, Produart) + sistema cliente desktop antigo
- **Diferencial-chave**: bridge legado preservada SEM perda de cliente + migração gradual pra ComunicacaoVisual

## Concorrentes-alvo

| Concorrente | Pricing/mês | Foco | Lacuna oimpresso preenche |
|---|---|---|---|
| **Mubisys** | R$ 350-800 | gráficas rápidas | sem bridge legado, migração custosa |
| **Sigma Soft** | R$ 250-600 | sistemas gráficos legacy | sem stack moderna paralela |
| **Calcgraf** | R$ 250-600 | calculadora nicho | só cálculo |

## Capacidades em operação

```yaml
capacidades_operacao:
  - nome: "Licenciamento por computador (control acesso Delphi)"
    score: P0
    onde: "Licenca_Computador entity + LicencaComputadorController"

  - nome: "Audit log Passport API (todas chamadas Delphi → Hostinger)"
    score: P0
    onde: "LicencaLog (append-only) + LogDelphiAccess middleware"

  - nome: "Parse log Laravel idempotente (cursor offset)"
    score: P0
    onde: "ParseLicencaLogCommand (schedule everyFiveMinutes)"
    evidencia: "dedup hash linha + offset Cache, sem dupes"

  - nome: "Granular retention por evento (LGPD Art. 16)"
    score: P0
    onde: "module.json retention_days[evento] (api_call 1y, admin 7y CC Art. 206)"
```

## Top 5 gaps P0 (pra subir nota ≥85)

| G | Capacidade | Esforço | ROI estimado | Concorrente que tem |
|----|------------|---------|--------------|---------------------|
| G1 | LicencaImporter idempotente (bulk import licenças legadas) | 12h | alto (migração 6 saudáveis) | nenhum tem |
| G2 | Dashboard saúde clientes Delphi (heartbeat + alerta down) | 16h | alto (proativo suporte) | parcial Sigma |
| G3 | Job artisan `licenca-log:retention-purge` (executa retention canon) | 8h | médio (LGPD operacional) | n/a |
| G4 | Onboarding wizard cliente Delphi → Laravel (1-click migration) | 24h | alto (reduz fricção piloto) | nenhum tem |
| G5 | API REST/webhooks pra integrações externas (Bling/CRMs) | 20h | médio (paridade horizontal) | Bling, Mubisys |

## Diferenciais oimpresso vs concorrentes

1. **Bridge legado preservada** — clientes Delphi continuam operando + opção migrar gradualmente
2. **Multi-tenant Tier 0** — cada cliente Delphi tem business_id próprio (isolation por design)
3. **Audit append-only** LicencaLog (Passport + Delphi sync) — auditoria LGPD pronta
4. **Retention granular por evento** (api_call 1y, login 2y, admin 7y) — CC Art. 206 + LGPD Art. 16
5. **Migração path-clear** pra ComunicacaoVisual quando cliente quiser stack moderna
6. **Stack moderna** Laravel 13.6 + Pest 4 com Delphi seguindo separado (zero acoplamento código)

## Score Capterra W22 → W23

| Dimensão (scoped vertical_client_facing) | W22 | W23 alvo |
|------------------------------------------|-----|----------|
| V1 Pest E2E (idempotência parse, audit, lifecycle) | 6/15 | **12/15** |
| V2 Code Quality FormRequests | 9/10 | 9/10 |
| V3 Perf UX (Delphi UI legacy, sem Inertia) | 8/10 | 8/10 |
| V4 LGPD retention granular | 12/15 | **14/15** |
| V5 Docs canon (SPEC + RUNBOOK migração + PROPOSTA) | 9/20 | **18/20** |
| V6 Capterra ROI Top 5 + concorrentes | 4/10 | 8/10 |
| **Total scoped** | **60/100** (médio) | **≥85/100** |

## Status lifecycle (ADR 0121)

- ✅ `bridge legacy` — Delphi desktop ativo + audit Hostinger
- ⏳ `migração ComunicacaoVisual` — Q3/26+ conforme sinal qualificado ADR 0105
- ⏳ `retired` — quando todos 6-7 saudáveis migrarem (meta 2028+)

## Anti-padrões (Tier 0 IRREVOGÁVEIS)

- ⛔ Tabela `licenca_*` sem `business_id` indexed + FK + global scope (ADR 0093)
- ⛔ LicencaLog SoftDeletes (audit append-only — CCom Art. 195 + LGPD Art. 16)
- ⛔ Bypass Passport auth Delphi sem audit LicencaLog
- ⛔ Forçar migração cliente Delphi sem qualificação ADR 0105 (preserva 26 anos relacionamento)
- ⛔ Mexer no schema legado Firebird sem ADR (cliente desktop quebra)

## Referências

- [SPEC.md](SPEC.md) — funcional bridge
- [PROPOSTA-COMERCIAL-vs-mubsys.md](PROPOSTA-COMERCIAL-vs-mubsys.md) — proposta migração
- [RUNBOOK-migracao-react.md](RUNBOOK-migracao-react.md) — migração progressiva pra Inertia
- [RUNBOOK-recuperacao-on-prem.md](RUNBOOK-recuperacao-on-prem.md) — recovery legacy
- [OFFICEIMPRESSO-FIREBIRD-SCHEMA.md](OFFICEIMPRESSO-FIREBIRD-SCHEMA.md) — schema legado

---

**Próxima revisão**: 2026-08-16 (trimestre) ou quando 1º saudável migrar.
**Wave**: 23 (saturação bucket vertical_client_facing — ADR 0160).
