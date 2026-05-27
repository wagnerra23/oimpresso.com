# Checklist Wagner — Sprint 2 (PR2)

> Passos manuais pra abrir PR2, mergear e rodar soak 48h.

## Pré-PR

- [ ] Sprint 1 mergeada na `main` e em prod (Daily Brief operacional)
- [ ] Branch `main` atualizada localmente
- [ ] AppShell.tsx sem regressões abertas no Sentry
- [ ] Backup do banco staging (snapshot RDS ou `mysqldump`)

## Branch + commit

```bash
git checkout main && git pull
git checkout -b feat/sprint-2-os-listagem-mwart

# Copiar os 7 arquivos da Sprint 2:
mkdir -p memory/sprints/s2-os-listagem
cp <arquivos>

git add memory/sprints/s2-os-listagem/
git commit -m "feat(memory): Sprint 2 — OS listagem MWART dossier"
git push -u origin feat/sprint-2-os-listagem-mwart
```

## Abrir PR

- **Base:** `main`
- **Compare:** `feat/sprint-2-os-listagem-mwart`
- **Title:** `feat(memory): Sprint 2 — OS listagem MWART dossier`
- **Labels:** `memory`, `sprint-2`, `mwart`
- **Description:**

```markdown
Refs: SPRINT-2

Dossier completo Sprint 2 (Listagem de OS — piloto MWART) seguindo padrão
da Sprint 1.

Conteúdo:
- README sprint
- ADR MWART-0001 (contrato LegacyMenuAdapter + flag inertia)
- Schema SQL (índices em ordens_servico)
- Spec OsController@index Inertia
- Spec Pages/Os/Index.tsx
- Skill mwart-migrate (reusável)
- Checklist Wagner (este arquivo)
- Plano de rollback

Próximo passo após merge:
Opus consulta brief-fetch + executa skill mwart-migrate com escopo
officeimpresso.os.index. PR3 será o código.
```

## Após merge PR2

### Validar Daily Brief tá pegando Sprint 2

```bash
# Via tool MCP brief-fetch
curl -X POST $MCP_BRIEFS_URL/brief-fetch \
  -H "Authorization: Bearer $MCP_TOKEN" \
  -d '{"agent":"opus","date":"today"}'

# Esperado: brief mencionando "Sprint 2 — OS listagem MWART" como
# foco ativo, com referência aos 7 deliverables.
```

### Disparar Opus pra executar

Slack DM ou prompt direto:

```
Opus, leia memory/sprints/s2-os-listagem/* e execute a skill
mwart-migrate com escopo:
- Módulo: Officeimpresso
- Rota: officeimpresso.os.index
- Flag: MWART_OS_INDEX

Output esperado: PR3 com código (Controller, Resource, Page.tsx, testes,
config/mwart.php, menu.php).
```

### Soak staging (48h)

Após PR3 mergear em `staging`:

- [ ] Deploy staging com `MWART_OS_INDEX=true`
- [ ] Aplicar migrations dos índices: `php artisan migrate`
- [ ] Validar índices criados:
  ```sql
  SHOW INDEX FROM ordens_servico WHERE Key_name LIKE 'idx_os_%' OR Key_name LIKE 'ft_os_%';
  ```
- [ ] Smoke test manual:
  - [ ] Abrir `/os` — renderiza sem erro
  - [ ] Filtro status=arte funciona
  - [ ] Busca por número funciona
  - [ ] Bulk → mudar etapa funciona
  - [ ] Paginação preserva URL
  - [ ] Toggle `MWART_OS_INDEX=false` + reload → volta pra Blade sem erro
- [ ] Testar com 3 usuários internos por 48h:
  - Wagner
  - Henrique (operacional)
  - Camila (atendimento)
- [ ] Sentry: zero erros JS em 48h
- [ ] Telescope: p95 do controller < 400ms

### Promover pra prod

Após soak 48h limpo:

```bash
# .env prod — primeiro 10% via canpro release ou User::canMwart()
MWART_OS_INDEX=true
# User::canMwart() retorna true só pra users do grupo 'mwart_beta'
```

- [ ] Deploy prod
- [ ] Monitorar Sentry + Telescope por 24h
- [ ] Se limpo: ampliar pra 100% (`canMwart() => true` pra todos)
- [ ] Atualizar `memory/migrations.md`:

```markdown
## 2026-05-XX — officeimpresso.os.index

- PR: #<n>
- Flag: `MWART_OS_INDEX`
- Soak: 2026-05-08 → 2026-05-10
- Status: 🟢 100% prod
```

### Cleanup (após 30 dias 100% on)

Calendário pra ~2026-06-10:

- [ ] Deletar `Modules/Officeimpresso/Resources/views/os/index.blade.php`
- [ ] Remover branch `if config('mwart.os_index_enabled')` do controller
- [ ] Remover `MWART_OS_INDEX` de `config/mwart.php` e `.env`
- [ ] Atualizar `migrations.md` status → ⚫
- [ ] Commit: `chore(officeimpresso): remove Blade legacy de os.index`

## Em caso de incident

Ver `08-rollback-plan.md`.
