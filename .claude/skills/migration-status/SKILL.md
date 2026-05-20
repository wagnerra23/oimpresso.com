---
name: migration-status
mission: "Substituir exploração manual de status migração Firebird→MySQL/Blade→Inertia por inventário governado com volumes reais, dependências e % efetividade composta."
description: ATIVAR quando user pedir "status migração", "% migrado {módulo}", "tabelas Firebird", "status da migração por tabelas", "dependências da migração", "/migration-status {módulo}", "como está a migração de X", "compare Firebird com migrado". Roda em 4 dimensões cruzadas (Firebird origem volume real + schema MySQL cobertura + Blade→Inertia UI grades + sync runtime ativo) e devolve tabela com % efetividade composta + grafo de dependências (US blocked_by + ADRs feature-wish + cliente piloto sinal) + caveats explícitos sobre estimativas vs medições reais. NÃO inventa números — quando volume cliente-dependente, marca "TODO probe SQL prod" via skill `officeimpresso-financial-snapshot`. Substitui ~6 turnos exploratórios MCP+filesystem+grep ad-hoc por 1 chamada governada.
type: process-skill
status: active
version: 1.0.0
trust_level: L2
owner: maira
created_at: 2026-05-20
updated_at: 2026-05-20
parent_mission: "Toda skill substitui trabalho humano repetitivo com ROI provado, rumo ao ERP autônomo de R$ 10M em 24 meses."
related_adrs:
  - 0089-capterra-driven-module-evolution  # pattern cruza FICHA + SPEC + código
  - 0093-multi-tenant-isolation-tier-0     # business_id scope em probes
  - 0104-processo-mwart-canonico-unico-caminho  # UI grades Blade→Inertia
  - 0105-cliente-como-sinal-guiar-sem-mandar    # sinal qualificado cliente bloqueia/desbloqueia
  - 0113-integracao-delphi-laravel-ads-3-caminhos  # bridge Delphi→Laravel
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12   # marco FSM rollout
  - 0147-cascade-review-defesa-drift-time-mcp      # legacy-delphi/ docs canon
  - 0171-oficinaauto-ativacao-piloto-martinho-faseada  # piloto Martinho
related_skills:
  - comparativo-do-modulo                  # pattern análogo: FICHA × SPEC × código
  - avaliar-modulo                         # rubrica module-grade-v3 (paralelo)
  - officeimpresso-financial-snapshot      # probe Firebird per cliente
  - officeimpresso-source-analysis         # leitura Controllers .pas Delphi
triggers_on:
  - "/migration-status"
  - "/migration-status {modulo}"
  - "status migração"
  - "status da migração"
  - "% migrado {modulo}"
  - "porcentagem da migração"
  - "tabelas Firebird"
  - "compare Firebird com migrado"
  - "dependências da migração"
  - "como está a migração de {modulo}"
  - "status migração por tabelas"
does_not_trigger_on:
  - "auditar gap mercado {modulo}" (use comparativo-do-modulo)
  - "nota do módulo X" (use avaliar-modulo)
  - "rodar probe Firebird cliente Y" (use officeimpresso-financial-snapshot)
  - "código fonte Controller.X.pas" (use officeimpresso-source-analysis)
roi_metric:
  type: turns
  baseline: "Wagner pergunta status migração → 6+ turnos exploratórios (Glob + Grep + decisions-search + git show legacy-delphi/ + tasks-list + composição heurística ad-hoc com pesos arbitrários)"
  target: "/migration-status {modulo} reduz pra 1 turno governado com tabela + dependências + caveats explícitos + ROI-ordenado de próximas ações"
metrics:
  inventarios_gerados: 0
  modulos_cobertos: []
  caveats_marcados_total: 0
artefatos_lidos:
  - "memory/legacy-delphi/SCHEMA-FIREBIRD.md (393 tabelas v1468 + volumes ServidorWR2)"
  - "memory/legacy-delphi/MAPEAMENTO-DELPHI-LARAVEL.md (19 Controllers Delphi → Modules Laravel)"
  - "memory/requisitos/{Modulo}/SPEC.md (US-XXX-NNN status + blocked_by)"
  - "Modules/{Modulo}/SCOPE.md (contains[] declarado)"
  - "Modules/{Modulo}/Database/Migrations/ (schema MySQL cobertura)"
  - "resources/js/Pages/{Modulo}/ (UI grades migradas)"
  - "Modules/{Modulo}/Resources/views/ (Blade legacy restante)"
artefatos_emitidos:
  - "1 tabela markdown 4 dimensões (Firebird × MySQL × UI × Sync)"
  - "1 grafo dependências US blocked_by (recursivo)"
  - "1 % efetividade composta (4 eixos ponderados explícitos)"
  - "Lista próxima ação ROI-ordenada"
tier: B
parent_adr: 0095
---

# migration-status — Status governado da migração por tabelas + dependências

Skill genérica pra auditar status de migração de qualquer módulo do oimpresso cruzando **4 dimensões** numa única chamada governada:

1. **Schema Firebird origem** — volume real ServidorWR2 (`memory/legacy-delphi/SCHEMA-FIREBIRD.md`)
2. **Schema MySQL destino** — cobertura migrations + Models + Controllers
3. **UI grades** — `Index.tsx` Inertia vs `index.blade.php` legacy (MWART)
4. **Sync runtime** — bridge `Controller.OImpresso.pas` ativo per business + integrações externas (Inter/Asaas)

Substitui ~6 turnos de exploração manual por 1 turno com saída canônica.

## Quando ativar

Triggers explícitos:
- `/migration-status {modulo}` (slash command)
- `/migration-status` (sem arg — lista módulos disponíveis + pede escopo)
- "status da migração por tabelas" / "como está a migração de Financeiro"
- "% migrado NfeBrasil" / "porcentagem da migração do OficinaAuto"
- "compare Firebird com migrado" (global, todos módulos)
- "dependências da migração" (foca em grafo blocked_by)

## Quando NÃO ativar

- Wagner pergunta gap de mercado vs concorrentes → skill **`comparativo-do-modulo`**
- Wagner pergunta nota agregada do módulo → skill **`avaliar-modulo`** (`module-grade-v3`)
- Wagner pede probe Firebird de cliente específico → skill **`officeimpresso-financial-snapshot`**
- Wagner pede leitura de Controller Delphi `.pas` → skill **`officeimpresso-source-analysis`**

Skill `migration-status` é a **camada agregadora** que cruza output das skills acima sem re-executar. Quando algum input está stale, sinaliza mas não bloqueia.

## Os 6 passos do ciclo

### 1. Validar pré-condições

```
- memory/legacy-delphi/SCHEMA-FIREBIRD.md existe em origin/main?
- memory/legacy-delphi/MAPEAMENTO-DELPHI-LARAVEL.md existe?
- Modules/{Modulo}/ existe? SCOPE.md existe?
- memory/requisitos/{Modulo}/SPEC.md existe?
```

Sem `SCHEMA-FIREBIRD.md` (módulo greenfield sem origem Delphi) → pular dimensão #1, marcar "N/A — não migrado de Firebird, módulo nasceu no oimpresso".

### 2. Coletar Firebird origem (Dimensão 1)

Lê `memory/legacy-delphi/SCHEMA-FIREBIRD.md`:
- Total tabelas vivas no módulo Delphi correspondente (ex: `financeiro` 46 tabelas)
- Tabelas críticas com volume real ServidorWR2 (ex: `FINANCEIRO` 59.186 · `BOLETOS` 29.946)
- Tabelas BRIDGE Delphi→oimpresso (`OIMPRESSO*`)

Cruza com `MAPEAMENTO-DELPHI-LARAVEL.md` pra mapping campo-a-campo conhecido.

### 3. Coletar MySQL destino (Dimensão 2)

```bash
# Cobertura schema
ls Modules/{Modulo}/Database/Migrations/ | wc -l
ls Modules/{Modulo}/Models/ | wc -l
ls Modules/{Modulo}/Http/Controllers/ | wc -l

# Bridge tables (legacy_id columns)
grep -r "legacy_id\|legacy_map\|firebird_" Modules/{Modulo}/ --include="*.php" -l
```

Mapeia 1:1 onde possível, marca placeholder onde mapping pendente.

### 4. Coletar UI grades (Dimensão 3)

```bash
# Blade legacy
find Modules/{Modulo}/Resources/views -name "index.blade.php" | wc -l

# Inertia migrado
find resources/js/Pages/{Modulo} -name "Index.tsx" | wc -l

# Controllers usando Inertia::render vs yajra/DataTables
grep -l "Inertia::render" Modules/{Modulo}/Http/Controllers/*.php | wc -l
grep -l "yajra\|DataTables::of" Modules/{Modulo}/Http/Controllers/*.php | wc -l
```

% UI migrada = `Inertia / (Inertia + Blade)`.

### 5. Coletar Sync runtime (Dimensão 4)

```
- Existe endpoint `/api/oimpresso/{módulo-lowercase}` no DataController?
- Há integração externa (Asaas/Inter/Whatsapp)? Status PRs?
- Cron jobs ativos? (php artisan schedule:list | grep {modulo})
```

% sync = código pronto / (código pronto + ativações pendentes Wagner externo portal).

### 6. Compor saída governada

**Tabela 4 dimensões:**

| Dimensão | Universo | Migrado | % | Fonte |
|---|---:|---:|---:|---|
| Schema Firebird (tabelas) | 46 | ~14 | ~30% | MAPEAMENTO-DELPHI-LARAVEL.md |
| Schema MySQL (migrations) | — | 20 | — | filesystem |
| UI grades (Blade→Inertia) | 17 | 16 | 94% | filesystem |
| Sync runtime (endpoint ativo) | — | ✅ | 100% | DataController |

**Efetividade composta (pesos explícitos justificados):**

```
UI/UX       × 25% = ...
Schema/dados × 35% = ...  (peso maior — dados são o produto)
Sync runtime × 20% = ...
Integrações  × 20% = ...
TOTAL = X%
```

Pesos default acima são razoáveis pra módulos com cliente legacy ativo. **Pra módulos greenfield (OficinaAuto/Vestuario):** ajustar pesos zerando Schema/dados (não há Firebird origem).

**Grafo de dependências:**

```
US-RB-044 (NFe a partir de boleto pago)
  ← blocked_by US-RB-047 (Inter PJ Fase 3) → review
    ← blocked_by Wagner ativa 4 escopos portal Inter (humano externo)
```

Cita explicitamente quem bloqueia o quê + por que (US, ADR feature-wish, sinal cliente faltando).

**Caveats obrigatórios:**

- Volume Firebird = ServidorWR2 Wagner. Cliente real (ex: Martinho biz=164) pode ter ordem de grandeza diferente
- `tasks-list status:done` pode mostrar 0% mesmo com código vivo (drift SPEC vs MCP)
- % composta usa heurística de pesos — se Wagner quer rubrica oficial, usar skill `avaliar-modulo` (module-grade-v3)
- Snapshot só de prod biz=Wagner (biz=1). Pra cliente específico, sugerir `officeimpresso-financial-snapshot`

**Próxima ação ROI-ordenada:**

1. Item desbloqueando mais downstream (resolve raiz do grafo)
2. Item com sinal qualificado cliente (ADR 0105 — cliente paga + reporta)
3. Item com menor esforço × maior impacto

## Saída canônica completa (template)

```markdown
# Status migração — {Modulo} (gerado em {data})

## Tabela 4 dimensões

| ... | (ver acima) |

## Efetividade composta: X%

| Eixo | Peso | Score | Contribuição |
|...|

## Grafo de dependências

```
{tree blocked_by recursivo}
```

## Caveats

- {volume cliente-dependente}
- {SPEC vs código drift}
- {pesos heurística vs rubrica oficial}

## Próxima ação ROI-ordenada

1. {item desbloqueia mais}
2. {item com sinal cliente}
3. {item baixo esforço alto impacto}

## Inputs lidos

- memory/legacy-delphi/SCHEMA-FIREBIRD.md ({sha})
- memory/legacy-delphi/MAPEAMENTO-DELPHI-LARAVEL.md ({sha})
- Modules/{Modulo}/SCOPE.md ({sha})
- tasks-list module:{Modulo} (snapshot {timestamp})
```

## Limitações conhecidas

- **Sem acesso prod**: snapshot de cliente real exige skill `officeimpresso-financial-snapshot` em runtime (chave do cliente + SCP query Python). Skill `migration-status` lê só schema canônico + estimates ServidorWR2.
- **Pesos da composição são heurística**: pra rubrica oficial governada, usar `avaliar-modulo` (module-grade-v3 — ADR 0155).
- **Cobertura mapping Delphi**: depende de `MAPEAMENTO-DELPHI-LARAVEL.md` estar atualizado. Felipe owna esse doc. Quando estiver stale, skill avisa "TODO refresh mapping".

## Casos de uso

### Caso 1: Wagner pergunta "% Financeiro"

```
/migration-status Financeiro
→ Tabela 4 dimensões
→ Efetividade ~64%
→ Grafo: US-RB-044 bloqueado por Inter portal (Wagner externo)
→ Caveat: volume biz=1 Wagner ≠ biz=4 Larissa
→ Próxima ação: Wagner ativa 4 escopos portal Inter (1h, desbloqueia 4 US)
```

### Caso 2: Maira quer saber dependências antes de pegar uma US

```
"dependências da migração do OficinaAuto"
→ Grafo: 36 tasks ativas
  US-OFICINA-002 ← US-OFICINA-001 (importer veículos)
  US-OFICINA-007 ← US-OFICINA-002 (Vargas 1.064 veículos)
  US-OFICINA-008 → bloqueia 4 outras (garantia per-item)
→ Recomendação: pegar US-OFICINA-001 primeiro (raiz do grafo)
```

### Caso 3: Felipe quer overview global pré-onboarding

```
"compare Firebird com migrado em todos os módulos"
→ Tabela agregada 14 módulos Delphi × Module Laravel
→ % por módulo + status macro
→ Lista de 4-5 frentes prioritárias (cycle drift visualizado)
```

## Próximas evoluções (v2.0)

- Integração direta com `module:grade --json` pra cruzar com nota rubrica
- Output JSON estruturado pra rodar em CI (gate "module X não regrediu")
- Snapshot de prod de cliente específico via plugin `officeimpresso-financial-snapshot`
- UI dashboard em `/copiloto/admin/migration-status` agregando últimas runs

## Referências

- ADR 0089 Capterra-driven evolution (pattern análogo FICHA + SPEC + código)
- ADR 0093 Multi-tenant Tier 0 (probes biz=1 nunca cliente real)
- ADR 0104 MWART canônico (UI grades Blade→Inertia)
- ADR 0105 Cliente como sinal qualificado (bloqueador externo)
- ADR 0113 Integração Delphi↔Laravel 3 caminhos
- ADR 0143 FSM Pipeline LIVE prod biz=1
- ADR 0147 Cascade Review §10.4 (legacy-delphi/ docs canon)
- ADR 0171 OficinaAuto piloto Martinho faseada
