# Checklist Wagner — Sprint 2 (PR2 + PR3)

> Passos manuais pra abrir PR2 (este dossier), PR3 (código) e rodar soak 48h.

## Antes de começar

- [ ] Sprint 1 mergeada na `main` (commit `dd1d8a4e`) — ✅ confirmado em `git log`
- [ ] `main` atualizada localmente (`git pull origin main`)
- [ ] AppShellV2 sem regressões abertas no Sentry (validar 30s no painel)
- [x] Tool MCP `brief-fetch` deployed (`c4fc2680`, PR #109) + skill `brief-first` Tier A ([ADR 0091](../../decisions/0091-daily-brief.md)) — toda sessão deve começar chamando `brief-fetch` (~3k tokens) em vez de 5-8 tool calls exploratórias

## PR2 — dossier (este conteúdo)

### Branch + commit

```bash
git checkout main && git pull
git checkout -b feat/sprint-2-os-listagem-mwart-dossier

# Os 8 arquivos já foram criados em memory/sprints/s2-os-listagem/
git add memory/sprints/s2-os-listagem/

git commit -m "feat(memory): Sprint 2 — OS listagem MWART dossier (Repair)"
git push -u origin feat/sprint-2-os-listagem-mwart-dossier
```

### Abrir PR via `gh` (autenticado nesta máquina pós-Sprint 1)

```bash
gh pr create \
  --base main \
  --title "feat(memory): Sprint 2 — OS listagem MWART dossier" \
  --label memory,sprint-2,mwart,repair \
  --body "$(cat <<'BODY'
Refs: SPRINT-2

Dossier completo Sprint 2 (Listagem de OS — piloto MWART) seguindo padrão Sprint 1.

Conteúdo (\`memory/sprints/s2-os-listagem/\`):

- README sprint
- 01 — ADR MWART-0001 (controller dual-mode + flag por business_id)
- 02 — Schema SQL (índices em \`transactions\` filtrados por \`sub_type='repair'\`)
- 03 — Spec \`RepairController@index\` Inertia
- 04 — Spec \`Pages/Repair/Index.tsx\` (Persistent Layout AppShellV2)
- 05 — Skill \`mwart-migrate\` (reusável)
- 06 — Checklist Wagner (este arquivo)
- 07 — Plano de rollback

Targeting \`Modules/Repair/\` (não Officeimpresso — corrigido vs draft inicial).

Próximo passo após merge:
PR3 = código (Controller, Resource, Page.tsx, testes, migration índices, config/mwart.php).
BODY
)"
```

## PR3 — código

Quando os créditos voltarem (ou outro dev/IA pegar):

```bash
git checkout main && git pull
git checkout -b feat/sprint-2-repair-index-mwart
```

Implementar conforme specs 03 + 04, seguindo skill `05-skill-mwart-migrate.md`. Arquivos esperados:

```
Modules/Repair/Http/Controllers/RepairController.php  (modify - dual-mode)
Modules/Repair/Http/Resources/RepairListResource.php  (new)
Modules/Repair/Database/Migrations/2026_05_XX_add_repair_listing_indexes.php  (new)
Modules/Repair/Tests/Feature/RepairIndexTest.php       (new)
resources/js/Pages/Repair/Index.tsx                    (new)
resources/js/Pages/Repair/Index.test.tsx               (new) — ou em tests/js/
config/mwart.php                                       (new)
.env.example                                           (modify - MWART_REPAIR_INDEX)
phpunit.xml                                            (modify se Repair não está registrado)
memory/migrations.md                                   (new ou append)
```

Todos os checks da skill passando antes de abrir PR.

## Após merge PR3 em `main`

### 1. Validar índices em staging

```bash
ssh -p 65002 u906587222@148.135.133.115
cd domains/oimpresso.com/public_html

# Pull + composer (CLAUDE.md §4 — composer install OBRIGATÓRIO pós-deploy)
git pull origin main
composer install --no-interaction --prefer-dist
php artisan migrate --force
php artisan config:clear
php artisan route:clear
```

```sql
-- Validar índices criados
SHOW INDEX FROM transactions
WHERE Key_name LIKE 'idx_repair_%';
```

Esperado: 5 linhas (idx_repair_biz_status_due, idx_repair_biz_contact_created, idx_repair_biz_waiter_status, idx_repair_biz_creator_status, idx_repair_biz_location_status).

### 2. Smoke test staging com flag on

`.env` staging:

```env
MWART_REPAIR_INDEX=true
MWART_REPAIR_INDEX_BIZ=
```

Manualmente:

- [ ] Abrir `/repair/repair` — renderiza React sem erro JS no console
- [ ] Filtro de status funciona
- [ ] Busca por `invoice_no` funciona
- [ ] Busca por nome de cliente funciona
- [ ] Busca por serial_no funciona
- [ ] Filtro por contact, location, service_staff funcionam
- [ ] Date range (abertura + prazo) funcionam
- [ ] Sort por cada coluna sortável funciona
- [ ] Paginação preserva URL (testar `?page=2&repair_status_id[]=3`)
- [ ] Bulk → mudar status funciona
- [ ] Permission `repair.view_own` filtra por `created_by` (criar user de teste)
- [ ] Permission ausente → 403
- [ ] Toggle `MWART_REPAIR_INDEX=false` + reload → volta pra Blade (assertVisualNotebrokenmente)
- [ ] Multi-tenant: logar em business diferente, validar não vazamento

### 3. Soak 48h staging

3 usuários internos:

- Wagner [W]
- Maiara [M]
- Felipe [F]

Cada um usa por 48h. Critérios:

- Sentry: 0 erros JS em `/repair/repair` por 48h corridos
- Telescope: p95 do controller `RepairController@index` < 400ms
- 0 reclamação de UX dos 3 usuários (memória `cliente_rotalivre` lembra: `MWART` é port 1:1, mudança de UX = bug)

### 4. Promoção pra prod — beta ROTA LIVRE

Após soak limpo:

`.env` prod:

```env
MWART_REPAIR_INDEX=true
MWART_REPAIR_INDEX_BIZ=4
```

(memória `cliente_rotalivre`: `business_id=4`, Larissa, monitor 1280px — mas avisar Larissa antes pra ela saber que algo "novo no visual" pode aparecer mesmo sendo port 1:1)

Deploy:

```bash
ssh -p 65002 u906587222@148.135.133.115
cd domains/oimpresso.com/public_html
git pull origin main
composer install --no-interaction --prefer-dist
# editar .env (MWART_REPAIR_INDEX=true + _BIZ=4)
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

Monitorar 24h:

- Sentry específico de `business_id=4`
- Telescope p95 segmentado
- Atalho J/K reportado pela Larissa? (memória diz Larissa decorou shortcuts)

### 5. Rollout 100% (após 7 dias beta limpo)

```env
MWART_REPAIR_INDEX=true
MWART_REPAIR_INDEX_BIZ=
```

Atualizar `memory/migrations.md`:

```markdown
## 2026-05-XX — repair.index

- PR: #<n>
- Flag: `MWART_REPAIR_INDEX`
- Beta: business_id=4 (2026-05-XX → 2026-05-XX)
- Soak staging: 2026-05-XX → 2026-05-XX
- Status: 🟢 100% prod
- Owner: [W]
```

### 6. Cleanup (após 30 dias 100% on, zero rollback)

Calendário pra ~2026-06-15:

- [ ] Deletar `Modules/Repair/Resources/views/repair/index.blade.php`
- [ ] Remover branch `if (config('mwart.repair_index....'))` do controller
- [ ] Remover entrada `repair_index` de `config/mwart.php` e do `.env`
- [ ] Atualizar `migrations.md` status → ⚫
- [ ] Commit: `chore(repair): remove Blade legacy de repair.index`

## Em caso de incident

Ver [`07-rollback-plan.md`](07-rollback-plan.md). TL;DR: `MWART_REPAIR_INDEX=false` + `php artisan config:clear` resolve em < 60s.

## Pendência aberta — créditos

Wagner ficou sem créditos por ~3 dias após criar este dossier (commit do dia, ver session log). Sprint fica em **estado planejado, não implementado**:

- ✅ Dossier completo (este PR2, se mergeado)
- ⏳ Código (PR3) pendente — quando créditos voltarem ou Felipe/Maiara pegarem
- ⏳ Soak staging — depois do PR3
- ⏳ Beta prod — depois do soak

Marcar no MCP via tool `tasks-create` quando convier:

```
title: Sprint 2 PR3 — Implementar RepairController dual-mode + Page React
module: Repair
priority: P1
labels: mwart, sprint-2, repair, dossier-pronto
```
