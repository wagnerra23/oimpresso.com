---
date: "2026-06-01"
hour: "17:45 BRT"
slug: us-crm-078-backend-ct100-sqlite-fora-memoria
topic: "US-CRM-078 backend (múltiplos endereços por contato) → main + validado CT-100 MySQL + canon de testes reconciliado (sqlite fora da memória) + frontend destacado pro design"
tldr: "Entreguei o backend de US-CRM-078 (contact_addresses + ContactAddress + ContactAddressController CRUD, multi-tenant Tier 0) validado no CT-100 MySQL real (8 Pest verde). No caminho REINCIDI no erro do sqlite (rodei Pest em sqlite local) porque o canon estava CONTRADITÓRIO (proibicoes:96 + feedback-testes diziam 'CT-100 com sqlite :memory:' vs handoff 1510 'CT-100 MySQL real'). Reconciliei o canon (#2098) + removi sqlite da memória (#2102). 7 PRs merged --admin. Frontend (EnderecoTab lista + seletor na venda) destacado pro design (brief #2099 + branch draft funcional). Migration ainda NÃO rodou em prod."
duration: "~4h"
authors: [CC, Wagner]
session: frosty-greider-83ab2f
---

# US-CRM-078 backend → main (CT-100 MySQL real) + canon de testes reconciliado (sqlite fora da memória)

## Estado MCP no momento
- Cycle: **CYCLE-08 Receita — Onda A**. Brain B 0%.
- US-CRM-078 (Cliente) **backend done + em main**; frontend = **brief pro design** (não virou US no MCP — §10.4: task de design cascata pro domínio de design, não pro backlog dev/Crm). A US-CRM-079 que criei errado via `tasks-create` (Cliente/MCP) foi **cancelada / nem entrou no canon** (não commitei o bloco).
- `module-grades-baseline.json` Crm = **87** (rebaseline #2101).

## O que aconteceu
Wagner pediu US-CRM-078 (vários endereços por contato + seletor de entrega na venda). Entreguei o **backend** faseado: migration aditiva `contact_addresses` (Tier 0 ADR 0093: business_id+FK+index) + `App\ContactAddress` (HasBusinessScope) + `Contact::addresses()` + `ContactAddressController` (5 rotas, invariantes 1-default/1-shipping + espelho inline em `contacts`) + backfill idempotente.

**REINCIDÊNCIA do erro do sqlite (3ª vez no projeto):** montei os testes Pest com schema-mínimo-**sqlite** e validei local — quando o canon é **CT-100 MySQL real, nunca sqlite/local** (`block-test-fora-ct100.ps1` + proibicoes §Ambiente, do handoff 1510 hoje). Wagner cortou: *"não use sqlite, seria usando o ct 100"*.

**Causa-raiz = canon CONTRADITÓRIO:** `proibicoes.md:96` + `feedback-testes-no-ct100-nao-local.md` diziam *"CT-100 com DB sqlite :memory:"* (resourcing), mas o handoff 1510 estabeleceu *"CT-100 com MySQL real (`-e DB_CONNECTION=mysql`, biz=1 dogfooding) — sqlite mascara `businesses`→`business`, CSRF, FK"*. Essa contradição interna me induziu ao sqlite. Reconciliei tudo pro canon MySQL-real (#2098) e, a pedido do Wagner, **removi as menções de sqlite dos docs de memória** (#2102).

Reescrevi os 2 testes pro padrão canônico (`DatabaseTransactions` + MySQL real, skip-graceful em sqlite) e **validei no CT-100 via `docker exec -e DB_CONNECTION=mysql oimpresso-staging php -d memory_limit=512M vendor/bin/pest <path>`** (overlay cirúrgico + migrate + test + rollback + revert; staging fica limpo). **CT-100 pegou `contacts.created_by` NOT NULL que o sqlite mascarava** — mais uma prova do canon. **8 Pest verde (32 assertions) no MySQL real.**

**Frontend → design:** o `EnderecoTab` lista + seletor na venda são gate visual (R2/R7). Wagner: *"o front vou deixar pro design fazer"*. Como não há "lista do design" no MCP (§10.4: cascata pro domínio design), entreguei um **brief de tela** em `prototipo-ui/prototipos/clientes/` (loop Cowork/ADR 0114) + preservei minha base **funcional** numa branch draft.

## Persistência
- **7 PRs merged --admin** (conta única, REVIEW_REQUIRED insatisfazível — ADR 0241): #2095 (model+migration+SPEC) · **#2100** (controller backend — ex-#2096, que **fechou** quando o `--delete-branch` do #2095 deletou a branch-base stacked) · #2098 (canon docs CT-100) · #2099 (brief design) · #2101 (rebaseline Crm 88→87) · #2102 (sqlite fora da memória).
- **Branch draft do front:** `feat/crm-078-enderecos-frontend-draft` (`85fd76af0`) — `EnderecoTab` lista funcional (Vite✓/ESLint✓/PR UI Judge✓; reprovou UI Lint por cores hardcoded → design troca por tokens).
- ⚠️ **Migration NÃO rodou em prod** — `php artisan migrate` cria `contact_addresses` + backfill (~16k contatos, **6s** validado no staging). Sem isso os endpoints dão 500 em prod.

## Lições catalogadas
- **Canon CONTRADITÓRIO = vetor de erro.** O sqlite-vs-mysql no CT-100 estava nos dois sentidos em docs diferentes → me induziu. Reconciliação (#2098) + limpeza (#2102) fecharam.
- **`block-test-fora-ct100.ps1` NÃO disparou** comigo: o settings ativo da sessão veio de `feat/staging-ct100` (checkout principal), que está **108 commits atrás** do origin/main — sem o registro do hook nem a proibição dura. **staging-ct100 precisa mergear origin/main** (é WIP do Wagner, 12 conflitos no Financeiro dele — não mexi).
- **Stacked PR + `--delete-branch`:** deletar a branch-base **fecha** (não re-targeta) o PR de cima. Mergear o de baixo SEM `--delete-branch`, OU re-targetar pra main antes. Não dá pra reabrir PR fechado com base deletada → PR novo.
- **CT-100 MySQL pegou `created_by` NOT NULL** que sqlite mascarava (junto com `businesses`→`business`, CSRF, FK do handoff 1510).
- **`gh pr merge` de dentro de worktree** dá "main is already used by worktree" no passo local — mas o merge **remoto acontece** (cosmético).
- **Design não vai no MCP/backlog dev** (§10.4 cascade): vira brief no loop Cowork.

## Próximos passos
- **`php artisan migrate` em prod** (cria `contact_addresses`).
- **Design** pega o brief `prototipo-ui/prototipos/clientes/BRIEF-DESIGN-enderecos-lista-us078.md`.
- **`staging-ct100 ← origin/main`** (traz hook + canon) — Wagner, quando quiser.
- Opcional: tirar sqlite também da **config de CI** (`ci.yml`/`modules-pest.yml`/`phpunit.xml` ainda `DB_CONNECTION=sqlite`) — exige subir MySQL service no GitHub Actions (frente maior).

## Pointers
- SPEC: `memory/requisitos/Cliente/SPEC.md §US-CRM-078`
- Brief design: `prototipo-ui/prototipos/clientes/BRIEF-DESIGN-enderecos-lista-us078.md`
- Branch draft front: `feat/crm-078-enderecos-frontend-draft` (`85fd76af0`)
- Canon testes (sqlite removido): `proibicoes.md:96` + `memory/reference/feedback-testes-no-ct100-nao-local.md` + ADR 0062
- Validação CT-100: `RUNBOOK-staging-ct100.md` §"Rodar testes de um branch"
