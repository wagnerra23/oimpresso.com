# ADR ARQ-0001 (DocVault) · Estratégia de upgrade Laravel 9.51 → 13

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner, Claude
- **Categoria**: arq
- **Relacionado**: CLAUDE.md (stack atual + alvo), user memory `project_roadmap_milestones.md`

## Contexto

Stack atual: **Laravel 9.51 + PHP 8.4** (ver CLAUDE.md §1 e §7 revogação PHP 7.1).
Alvo declarado por Wagner: **Laravel 13 + Laravel Boost + IA no core**.

Laravel 9 perdeu security patches em Nov 2024 (EOL). Cada major (10, 11, 12, 13) tem mudanças significativas:

| Versão | Lançamento | PHP mínimo | Breaking principais |
|---|---|---|---|
| 10 | Feb 2023 | 8.1 | migrações `return new class`, invokable validation rules, `str()` helper |
| 11 | Mar 2024 | 8.2 | **reestruturação app/** (sem `app/Http/Kernel.php`), middleware em `bootstrap/app.php`, Sanctum separado |
| 12 | Feb 2025 | 8.2 | Broadcasting refactor, Pennant default |
| 13 | Feb 2026 | 8.3 | **Inertia 2.0 nativo**, schedule em classes dedicadas, Prompts CLI, Livewire 4 integrado |

Dependências críticas acompanham cada major (versões atuais no `composer.json` — verificadas em 2026-04-22):
- **laravel/framework**: `^9.51` → alvo 13.x
- **nwidart/laravel-modules**: `^9.0` → precisa v10/v11/v12/v13 conforme upgrade
- **nwidart/laravel-menus**: `6.0.x-dev` (fork em github.com/dineshsailor/nWidart-laravel-menus) — possível blocker, verificar upstream
- **spatie/laravel-permission**: `^5.5` → v6 em Laravel 10+
- **laravel/passport**: pinned em `11.6.1` — versões > 12 exigem Laravel 10
- **laravel/ui**: `4.x` (compat Laravel 9, pode migrar pra Breeze/Fortify)
- **laravel/legacy-factories**: `^1.3` (compat Laravel 7-9) — **remover ao migrar factories pro formato novo**
- **inertiajs/inertia-laravel**: `^1.0` → v2.0 disponível (trivial upgrade)
- **laravelcollective/html**: `^6.3` (Laravel 9 only) — considerar remoção em Laravel 11+
- **barryvdh/laravel-dompdf**: `^2.0` (ok até Laravel 11)
- **myfatoorah/laravel-package**: `^2.2` (gateway pagamento — verificar upstream)
- **openai-php/laravel**: `^0.4.1` — **já instalado** ✅ (desbloqueia `DOCVAULT_AI_ENABLED=true` quando tiver OPENAI_API_KEY)
- **mpdf**: conhecido problema com PHP 8.4, já no borderline

Pergunta central: **quando fazer e como evitar retrabalho**.

## O que precisa refazer (impacto real do upgrade)

**Não precisa refazer** (ficam intactos):
- Código React/Inertia — Inertia 2 é backward-compatible com Inertia 1.
- Componentes shadcn/ui em `resources/js/Components/ui/`.
- Tailwind 4 CSS (já é versão moderna).
- DocVault: `RequirementsFileReader`, `MemoryReader`, `ChatAssistant`, `ModuleAuditor` — PHP puro, sem magia framework.
- Todos ADRs, SPECs, CHANGELOGs — documentação sobrevive infinito.
- Comentários `@docvault` nas telas.
- Migrations (só mudar formato `return new class` → já é o padrão Laravel 10+).

**Precisa refazer** (~15% do código):
- `app/Http/Kernel.php` → `bootstrap/app.php` (Laravel 11).
- `app/Console/Kernel.php::schedule()` → `routes/console.php` ou classes dedicadas (Laravel 13).
- Middleware aliases em `bootstrap/app.php`.
- Alguns ServiceProviders se usarem API legada.
- `app/Exceptions/Handler.php` → `bootstrap/app.php->withExceptions()` (Laravel 11).

## Decisão

**Upgrade progressivo em 4 sessões dedicadas, DEPOIS de consolidar DocVault e migrar 80%+ dos módulos ativos pra formato pasta.**

### Ordem recomendada

**Fase 0 — Consolidação (atual + próximas 2-3 sessões)**
- ✅ DocVault funcional (19 commits, score 94/100)
- ⏳ Migrar módulos ativos pra pasta (Accounting, Hrm, Crm, Manufacturing, Project, Essentials done)
- ⏳ Anotar `@docvault` em 30+ telas React restantes
- ⏳ Testes dos stubs gerados por `gen-test`
- ⏳ Conectar OpenAI real (ADR 0006 C1)

**Fase 1 — Baseline auditada (1 sessão curta)**
- Rodar `docvault:audit-module --all` e travar score mínimo de 70 em módulos ativos
- Snapshot git `v-pre-upgrade` como âncora
- Gravar ADRs de "como estava antes" pra comparar depois

**Fase 2 — Laravel 10 (1 sessão)**
- Seguir upgrade guide oficial
- Atualizar nwidart/laravel-modules pra versão 10-compat
- Rodar `docvault:audit-module --all` — score tem que ficar ≥ baseline
- Commit único, testável, reverter se quebrar

**Fase 3 — Laravel 11 (1 sessão pesada)**
- Maior refactor: migrar `app/Http/Kernel.php` + `app/Console/Kernel.php` + `app/Exceptions/Handler.php` pra `bootstrap/app.php`
- DocVault tem scheduler registrado aqui — atualizar
- UltimatePOS v6.7: verificar se já está em Laravel 11 ou se forka daqui

**Fase 4 — Laravel 12 (1 sessão curta)**
- Upgrade menos disruptivo
- Broadcasting refactor se usar WebSockets (provavelmente não no caso do DocVault)

**Fase 5 — Laravel 13 + Laravel Boost + IA no core (1 sessão)**
- Install Boost (já traz MCP + AI tools integradas)
- Conectar ChatAssistant via Boost se facilitar
- Inertia 2 nativo (Inertia 1 → 2 é trivial)

### Por que NÃO agora?

1. **Muitos módulos ainda em Blade legado.** Migrar Laravel quando o código fica mudando é dobro de trabalho (precisa re-testar tudo a cada migração Blade→React).
2. **DocVault é o medidor.** Se subirmos Laravel sem baseline de qualidade documentada, não sabemos o que quebrou.
3. **UltimatePOS v6.7 é fork.** Precisa validar se o upstream acompanha Laravel 10+; se não, precisamos decidir se mantemos fork.
4. **Laravel 9 ainda funciona.** Fora security patches, roda sem dramas no PHP 8.4. Produção não vai virar abóbora amanhã.

### Por que PROGRESSIVO e não pulado?

- **Pular de 9 direto pra 13** = aplicar 4 anos de breaking changes de uma vez. Impossível diagnosticar o que quebrou.
- **Cada upgrade tem Upgrade Guide oficial** com checklist específico — melhor seguir o script da Laravel do que inventar.
- **Cada fase vira commit revertível** — se Laravel 11 quebrar algo crítico, rollback pra 10.

## Consequências

**Positivas:**
- Minimiza retrabalho — consolidação primeiro, upgrade depois.
- DocVault vira **instrumento de medição** do upgrade (baseline → diff).
- Cada fase tem escopo claro, 1 sessão, ADR próprio.
- Laravel Boost chega na última fase, quando o alicerce está firme.

**Negativas:**
- Laravel 9 fica fora de security patches durante a Fase 0 (mitigação: ambiente produção controlado, não exposto a tráfego público massivo).
- ~5-6 sessões totais dedicadas a upgrade.

**Trade-off consciente**: aceitar atraso de meses pra fazer upgrade robusto vs. pular direto pra 13 e quebrar 30 módulos em paralelo.

## Roadmap consolidado no DocVault

Esse ADR referencia e alinha com:
- `memory/requisitos/DocVault/adr/0006-analise-de-melhorias-e-roadmap-docvault.md` (backlog geral)
- `memory/08-handoff.md` (estado atual projeto)
- `memory/decisions/` (ADRs históricos do projeto pré-DocVault)
- Memórias Claude `project_roadmap_milestones.md` e `project_roadmap_a_plus.md`

## Alternativas consideradas

- **Pular direto pra 13**: rejeitado — breaking changes acumuladas impossibilitam debug.
- **Congelar em Laravel 9**: rejeitado — Laravel Boost e IA são centrais pro alvo de Wagner.
- **Fork paralelo (branch laravel-13)**: rejeitado — duplica manutenção, git cherry-pick entre branches é caro.
- **Usar LTS só (LTS mais recente é 11)**: viável, mas não atende o alvo Laravel 13.

## Sinais pra começar a Fase 2 (Laravel 10)

Começar quando **todos** forem verdade:
- [ ] `docvault:audit-module --all` retorna ≥ 70 em todos módulos ativos
- [ ] `docvault:sync-pages` detecta ≥ 80% das telas com `@docvault`
- [ ] Baseline de testes: pelo menos R-PONT-001 e R-DOCVAULT-001 passando no CI
- [ ] UltimatePOS v6.7 upstream verificado (compat com Laravel 10+)
- [ ] Wagner alocou 1 sessão de 4+ horas dedicada
