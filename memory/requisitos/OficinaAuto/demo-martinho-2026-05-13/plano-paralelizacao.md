# Plano de paralelização — OficinaAuto Fase 1 (pós-Martinho)

**Gerado por:** `coordenador-paralelo` (agent) · **Data:** 2026-05-13 06:53 BRT
**Disparar quando:** Martinho fechar Opção A na reunião 10h
**Charter relacionado:** [charter-1pager.md](charter-1pager.md) + [demo-script.md](demo-script.md)
**ROADMAP fonte:** [ROADMAP.md §Fase 1](../ROADMAP.md)

---

## Fase 1 — Research (estado-da-arte concorrentes oficina mecânica BR)

| Player | Como resolve onboarding cliente legacy | Diferencial |
|---|---|---|
| **Auto Manager** | Importação CSV manual + planilha modelo | Forte no atendimento, fraco em importação automatizada |
| **Mecânico Net** | Onboarding assistido por consultor (~30 dias) | Customer success humano caro |
| **Lokoz** | Sem importação — começa do zero | Foco em pequenas oficinas urbanas |
| **Garagem ERP** | Migração paga R$1500+ via SQL custom | Lento, frágil |
| **Tecnomotor** | Importer DBF (FoxPro legacy) automatizado | Único com importer real — Wagner imita esse pattern |

**Estado-da-arte 2026:** importer automatizado de legacy + cleanup tools (dedupe + drift detect + write-off candidate) é diferencial REAL. Wagner já tem 90% da fundação (PR #555 Agent F identificou padrão).

---

## Fase 2 — Inventário OficinaAuto (estado atual)

**V0 LIVE (PR #556 — 2026-05-11):**
- ✅ 8 peças nWidart canônicas
- ✅ `vehicles` + `service_orders` migrations (multi-tenant Tier 0 ADR 0093)
- ✅ Models com `business_id` global scope + soft delete
- ✅ 9 permissions registradas
- ✅ 8 Pages Inertia CRUD
- ✅ 16 Pest tests (cross-tenant biz=1 vs biz=99)
- ✅ 4 RUNBOOKs MWART
- ✅ Migrations adicionais (caçamba fields, rental fields, transaction_sell_line_id, current_stage_id, contact_id)
- ✅ ProducaoOficinaController + Kanban prototype (PRs #735-740 madrugada)

**Gap pra Fase 1 (Martinho real):**
1. Rename `vehicles` → `oa_vehicles` (Wagner pendente decidir)
2. Importer Firebird Martinho (91 veículos + 44.709 vendas histórico)
3. Cleanup tools (3 sub-features) — ROI imediato pra cliente legacy
4. Defeitos múltiplos JSON array

**Módulos referência (imitar, não duplicar):**
- `Modules/Repair/` — Kanban OS shared infrastructure
- `Modules/Sells/` — FSM Pipeline ADR 0143 LIVE prod biz=1
- `scripts/legacy-migration/` (se existir importer Repair/Vargas — imitar)

---

## Fase 3 — Decomposição em waves

### ⚠️ Wave 0 (SEQUENCIAL — bloqueia paralelas) — Rename V0 `oa_*`

**Quando disparar:** PRIMEIRO, antes de qualquer paralelização.

```yaml
wave_id: 0
nome: "Rename vehicles → oa_vehicles, service_orders → oa_service_orders"
us_relacionada: pré-req Fase 1 (sem US ID — discovery)
area_permitida:
  - "Modules/OficinaAuto/Database/Migrations/2026_05_*_rename_*.php"
  - "Modules/OficinaAuto/Entities/{Vehicle,ServiceOrder}.php"
  - "Modules/OficinaAuto/Http/Controllers/*.php"
  - "Modules/OficinaAuto/Tests/Feature/*.php"
  - "resources/js/Pages/OficinaAuto/**/*.tsx"
area_proibida_overlap:
  - "Tudo fora de Modules/OficinaAuto/ e resources/js/Pages/OficinaAuto/"
restricoes_tier_0:
  - "multi-tenant business_id global scope (ADR 0093) — preservar em rename"
  - "PT-BR no domínio"
output_esperado:
  - "1 migration rename idempotente"
  - "Models atualizados (table name)"
  - "Controllers atualizados (model references)"
  - "Pages Inertia atualizadas (props names se mudar)"
  - "16 Pest tests verdes pós-rename"
esforco_estimado: "~1h IA-pair (2h margem)"
agente_sugerido: 1 sub-agent solo
```

**Critério de saída:** `php artisan test --filter=OficinaAuto` verde local.

---

### Wave A (PARALELA pós-Wave 0) — Importer Firebird Martinho

```yaml
wave_id: A
nome: "Importer Firebird EQUIPAMENTO_VEICULO + VENDA → oa_vehicles + oa_service_orders"
us_relacionada: US-OFICINA-002 (existe no MCP)
area_permitida:
  - "scripts/legacy-migration/officeimpresso/oficina-auto/**"
  - "Modules/OficinaAuto/Console/Commands/ImportMartinhoCommand.php (NEW)"
  - "Modules/OficinaAuto/Database/Seeders/MartinhoFixturesSeeder.php (NEW)"
  - "tests/Feature/OficinaAuto/ImportMartinhoTest.php (NEW)"
area_proibida_overlap:
  - "Modules/OficinaAuto/Http/Controllers/  # Wave B toca aqui"
  - "Modules/OficinaAuto/Entities/  # Wave 0 já tocou"
  - "resources/js/Pages/  # Waves B+C tocam aqui"
restricoes_tier_0:
  - "multi-tenant: importer recebe {business_id} obrigatório como arg"
  - "Hostinger ≠ CT 100 (ADR 0062) — script Python roda local, NÃO no Hostinger"
  - "SSH IPv4-bound `127.0.0.1` (feedback_legacy_migration_python_importer)"
  - "ZERO PII no log (CPF/CNPJ Martinho via PiiRedactor)"
comparar_nao_duplicar:
  - "LER scripts/legacy-migration/ existente (pode ter pattern Repair/Vargas)"
  - "LER feedback_legacy_migration_python_importer.md ANTES"
  - "LER memory/research/clientes-legacy-officeimpresso/05-martinho-cacambas/ pra schema Firebird real"
output_esperado:
  - "1 script Python bridge-mode (dry-run → --confirm)"
  - "1 artisan command Laravel wrapper"
  - "1 seeder com 5 fixtures dummy (sem dados reais)"
  - "1 Pest test cross-tenant biz=1 vs biz=99"
  - "1 update bridge table oa_vehicles_legacy_map (idempotente)"
esforco_estimado: "~4h IA-pair (8h margem)"
```

---

### Wave B (PARALELA pós-Wave 0) — Cleanup Tools UI (3 sub-features)

```yaml
wave_id: B
nome: "Cleanup tools cliente legacy (Revisão pendências + Conciliação drift + Dedupe pessoas)"
us_relacionada: US-OFICINA-005 (existe no MCP)
area_permitida:
  - "Modules/OficinaAuto/Http/Controllers/Cleanup{Pendencias,Conciliacao,Dedupe}Controller.php (NEW)"
  - "Modules/OficinaAuto/Services/Cleanup{Pendencias,Conciliacao,Dedupe}Service.php (NEW)"
  - "Modules/OficinaAuto/Routes/web.php (adicionar 3 routes)"
  - "resources/js/Pages/OficinaAuto/Cleanup/{Pendencias,Conciliacao,Dedupe}/Index.tsx (NEW)"
  - "tests/Feature/OficinaAuto/Cleanup*Test.php (NEW)"
area_proibida_overlap:
  - "Modules/OficinaAuto/Console/Commands/  # Wave A toca aqui"
  - "scripts/legacy-migration/  # Wave A toca aqui"
  - "Modules/OficinaAuto/Database/Migrations/  # Wave 0 já tocou"
restricoes_tier_0:
  - "multi-tenant: toda Eloquent query usa global scope"
  - "publication-policy: write-off candidate marca flag, NÃO deleta — Wagner aprova batch"
  - "LGPD: PESSOAS deduplicador NÃO expõe CPF em logs (PiiRedactor)"
  - "ROTA LIVRE format_date shift +3h preservar (ADR 0066) — não tocar"
comparar_nao_duplicar:
  - "LER Modules/Financeiro/ pattern de batch UI (visão unificada AR/AP)"
  - "LER Modules/Crm/ pattern dedupe (se existir) — fuzzy match em contatos"
  - "LER ADR 0066 (format_date shift +3h ROTA LIVRE) — não quebrar legacy"
output_esperado:
  - "3 Controllers + 3 Services + 3 Pages Inertia"
  - "3 routes registradas (`/oficinaauto/cleanup/{pendencias,conciliacao,dedupe}`)"
  - "3 Pest tests cobertura cross-tenant"
  - "1 charter `.charter.md` por tela (3 charters)"
esforco_estimado: "~12h IA-pair (24h margem) — sub-divisível em 3 sub-waves se Wagner quiser"
```

---

### Wave C (PARALELA pós-Wave 0) — Defeitos múltiplos JSON array

```yaml
wave_id: C
nome: "Defeitos múltiplos por OS (JSON array em oa_service_orders)"
us_relacionada: US-OFICINA-009 (existe no MCP)
area_permitida:
  - "Modules/OficinaAuto/Database/Migrations/2026_05_*_add_defeitos_to_oa_service_orders.php (NEW)"
  - "Modules/OficinaAuto/Entities/ServiceOrder.php (cast json)"
  - "Modules/OficinaAuto/Http/Controllers/ServiceOrderController.php (validation rule array)"
  - "resources/js/Pages/OficinaAuto/ServiceOrders/{Create,Edit,Show}.tsx (multi-input)"
  - "tests/Feature/OficinaAuto/ServiceOrderDefeitosTest.php (NEW)"
area_proibida_overlap:
  - "Cleanup/  # Wave B"
  - "Console/Commands/  # Wave A"
restricoes_tier_0:
  - "multi-tenant preservado"
  - "Validation: array max 10 defeitos, cada item string max 200 chars"
comparar_nao_duplicar:
  - "LER Modules/Repair/ se tem campo defeito similar — imitar cast/validation"
output_esperado:
  - "1 migration JSON column"
  - "1 cast no Model"
  - "1 validation rule"
  - "3 Pages atualizadas (multi-input UX)"
  - "1 Pest test"
esforco_estimado: "~3h IA-pair (6h margem)"
```

---

## Fase 4 — Spawn paralelo (sequência operacional)

### Etapa 1: Wave 0 (sequencial, ~2h wallclock)

```
1 sub-agent solo, prompt = Wave 0 acima.
Aguarda done + Wagner valida Pest filter OficinaAuto verde.
```

### Etapa 2: Wave A + B + C paralelas (~24h wallclock máximo)

```
Spawn 3 sub-agents em MESMA mensagem (3 Agent tool_use blocks):
- Sub-agent A: Importer Firebird (prompt = Wave A)
- Sub-agent B: Cleanup Tools (prompt = Wave B — pode sub-dividir em 3 se Wagner quiser)
- Sub-agent C: Defeitos múltiplos (prompt = Wave C)

Cada prompt inclui literalmente:
- Áreas permitidas + proibidas
- Restrições Tier 0 IRREVOGÁVEIS
- "Comparar e não duplicar" — lista módulos referência
- "ZERO git ops. Só Write/Edit/Read/Grep/Glob/Bash em pastas permitidas."
- Output esperado + Pest obrigatório
```

---

## Fase 5 — Consolidação (Wagner executa)

Após 3 sub-agents retornarem:

```bash
# Stash all + branches frescos por wave:
git stash push -u -m "coord-oficina-fase1-all-waves"

# Wave 0 (rename) → PR #1
git checkout -B claude/oficina-rename-oa origin/main
git stash pop
git add Modules/OficinaAuto/Database/Migrations/*rename* \
        Modules/OficinaAuto/Entities/ \
        Modules/OficinaAuto/Http/Controllers/ \
        Modules/OficinaAuto/Tests/Feature/ \
        resources/js/Pages/OficinaAuto/
git commit -F - <<'EOF'
chore(oficina-auto): rename vehicles -> oa_vehicles + service_orders -> oa_service_orders

Pré-req Fase 1 ROADMAP. Idempotente. 16 Pest verdes.

Refs: ROADMAP-OficinaAuto Fase 1 pré-req
EOF
git push -u origin claude/oficina-rename-oa
gh pr create --title "chore(oficina-auto): rename tables to oa_*" \
             --body "..."

# Wave A (importer) → PR #2 (depois rename mergeado)
git checkout -B claude/oficina-importer-martinho origin/main
# untracked persistem
git add scripts/legacy-migration/officeimpresso/oficina-auto/ \
        Modules/OficinaAuto/Console/Commands/ \
        Modules/OficinaAuto/Database/Seeders/MartinhoFixturesSeeder.php \
        tests/Feature/OficinaAuto/ImportMartinhoTest.php
git commit + push + PR

# Wave B (cleanup) → PR #3
git checkout -B claude/oficina-cleanup-legacy origin/main
git add Modules/OficinaAuto/Http/Controllers/Cleanup* \
        Modules/OficinaAuto/Services/Cleanup* \
        resources/js/Pages/OficinaAuto/Cleanup/ \
        tests/Feature/OficinaAuto/Cleanup*
git commit + push + PR

# Wave C (defeitos) → PR #4
git checkout -B claude/oficina-defeitos-multi origin/main
git add Modules/OficinaAuto/Database/Migrations/*defeitos* \
        Modules/OficinaAuto/Entities/ServiceOrder.php \
        Modules/OficinaAuto/Http/Controllers/ServiceOrderController.php \
        resources/js/Pages/OficinaAuto/ServiceOrders/ \
        tests/Feature/OficinaAuto/ServiceOrderDefeitosTest.php
git commit + push + PR
```

**4 PRs separados, mergeáveis individualmente. Wave 0 deve mergear PRIMEIRO (pré-req).**

---

## Resumo executivo

| Wave | Esforço IA-pair | Wallclock | US | Dependência |
|---|---|---|---|---|
| 0 (rename) | ~1h | ~2h | pré-req | bloqueante |
| A (importer) | ~4h | ~8h | US-OFICINA-002 | pós-Wave 0 |
| B (cleanup) | ~12h | ~24h | US-OFICINA-005 | pós-Wave 0 |
| C (defeitos) | ~3h | ~6h | US-OFICINA-009 | pós-Wave 0 |
| **TOTAL** | **~20h** | **~24-26h wallclock** | **3 US done** | — |

Vs estimativa ROADMAP linear (Fase 1 = ~40h IA-pair = ~2 sem): **paralelização reduz pra ~24h wallclock = ~3 dias trabalho focado.**

---

## Pré-requisitos Wagner sign-off ANTES de disparar

- [ ] Martinho aceitou Opção A (beta 30d) na reunião 10h — sem isso, plano fica dormente
- [ ] Wagner aprovou rename `vehicles` → `oa_vehicles` (Fase 1 pré-req do ROADMAP)
- [ ] Wagner aprovou matriz ROI top 5 (Fase 1 pré-req do ROADMAP)
- [ ] Confirmar `scripts/legacy-migration/` pattern existente (LER antes de disparar Wave A)
- [ ] Confirmar `MARTINHO_FB_DSN` configurado pro importer (sem isso, Wave A roda dry-run-only)

---

## Sinais de drift a monitorar durante execução

- ⚠️ Sub-agent toca pasta de outra wave → falha Fase 3, parar e refazer decomposição
- ⚠️ Conflito git no merge → pasta esquecida na regra de área permitida
- ⚠️ Pest filter OficinaAuto vermelho pós-Wave 0 → rename quebrou algo, parar paralelas
- ⚠️ Custo Opus > 200k tokens total → simplificar prompts ou sub-dividir Wave B (12h é maior risco)

---

## Quando reabrir esse plano

- Martinho recusou na reunião → plano vai pra `memory/decisions-drafts/` aguardando próximo piloto
- Vargas qualificado primeiro → reescrever (Vargas é multi-item Fase 2, mais complexo)
- ROADMAP Fase 2 inicia antes de Fase 1 fechar → repriorizar P0
