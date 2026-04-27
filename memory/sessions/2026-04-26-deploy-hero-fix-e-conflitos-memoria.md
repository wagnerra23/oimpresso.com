# Sessão 2026-04-26 — Deploy fix do Hero + resolução de conflitos de memória

**Branch:** `claude/dazzling-lichterman-e59b61` (worktree) → `6.7-bootstrap`
**Worktree:** `D:\oimpresso.com\.claude\worktrees\dazzling-lichterman-e59b61\`
**Origem:** Wagner pediu deploy do commit `039a810d` em produção + revisão dos conflitos da auto-memória.

---

## O que foi feito

### 1. Deploy manual em produção
- `git pull origin 6.7-bootstrap` no Hostinger via SSH (`855987be` → `039a810d`).
- `php artisan optimize:clear` + `composer dump-autoload` (17066 classes).
- Validado: `https://oimpresso.com/` HTTP 200, bundle `Home-DclIyG54.js` contém PT-BR ("orça/imprime/monta/entrega") e nenhuma string "Automate" do seed CMS.
- Não houve mudança em `composer.json`/`composer.lock`, então `composer install` foi dispensado.
- WP `/ajuda/` não foi tocado — patch `create_function` segue intacto.

### 2. Comparativo Capterra das memórias
- Inventariados **9 sistemas de memória** convivendo no projeto.
- Mapeadas **15 funções** com vencedor por categoria.
- Detectadas anomalias: `AGENTS.md` stale (Laravel 10), ADR 0024 duplicado, ADR 0012 ausente, MemCofre subutilizado.

### 3. Conflitos de auto-memória resolvidos (10 itens)
Edits em `~/.claude/projects/D--oimpresso-com/memory/`:
- Stack IA reconciliada (4 fontes contradiziam; agora alinha com código atual: dry_run-only, plano = Vizra ADK + Prism PHP).
- Inertia v2 → v3 corrigido em CLAUDE.md e handoff (composer.lock confirma v3.0.6).
- SSH porta 65002: nota "não funciona" corrigida com receita de deploy manual validada.
- Status Copiloto: `spec-ready` → `implementado` (PR #13 mergeado, 24 testes Pest).
- Status 4 módulos promovidos: Financeiro=implementado, LaravelAI=scaffold, Nfe/Recurring=spec-ready.
- EvolutionAgent destravado (bloqueio "merge L13" não existe mais).
- CMS hidratação `cms_pages`: tentado e revertido em `039a810d`; Hero é hardcoded.
- ADRs em `reference_project_memory.md`: lista atualizada até 0026.
- "Branch produção" mencionada no prompt: confirmado que não existe; deploy é direto de `6.7-bootstrap`.
- Connector "147 arquivos perdidos": atualizado pra "drift untracked no servidor; decisão de versionar/restaurar/deletar pendente".

### 4. ADRs novos formalizando a gestão de memória
- **0027** — Gestão de memória do projeto: papéis claros por função (meta-ADR).
- **0028** — ADRs com numeração monotônica e formato Nygard.
- **0030** — Credenciais sensíveis: nunca em git.
- 0029 reservado pro rename de `0024-padrao-inertia-react-ultimatepos.md` ✅ executado em 2026-04-27.
- 0031–0036 listados como propostas no comparativo (não materializados).

### 5. CLAUDE.md ampliado
- Stack helpers: Inertia v2 → v3.
- Bloco "IA": reescrito (sem pacote IA hoje, plano Vizra ADK + Prism PHP, "Prisma" era typo).
- Nova seção 7 "Acesso à produção (Hostinger)": host/port/user/key/repo/PHP/composer + receita de deploy manual.
- Cuidados explícitos: IPv4, SSH flaky, sem editar direto, composer install pós-deploy, WP /ajuda/.
- Footer atualizado pra sessão 15 (esta).

### 6. AGENTS.md desestaleado
- "Laravel 10" → "Laravel 13.6 + PHP 8.4 + Inertia v3 + React + Tailwind 4".
- Adicionado lista de módulos (Copiloto/Financeiro/Cms/MemCofre/Officeimpresso).
- Aponta pro ADR 0027 como meta-guia das memórias.

---

## Arquivos tocados no repo (vão pro commit)

```
CLAUDE.md
AGENTS.md
memory/08-handoff.md
memory/decisions/0027-gestao-memoria-roles-claros.md (novo)
memory/decisions/0028-adrs-numeracao-monotonica.md (novo)
memory/decisions/0030-credenciais-jamais-em-git.md (novo)
memory/sessions/2026-04-26-deploy-hero-fix-e-conflitos-memoria.md (este arquivo)
```

## Arquivos tocados em auto-memória (NÃO vão pro git)

```
~/.claude/projects/D--oimpresso-com/memory/MEMORY.md
~/.claude/projects/D--oimpresso-com/memory/preference_modulos_prioridade.md
~/.claude/projects/D--oimpresso-com/memory/project_modulo_copiloto.md
~/.claude/projects/D--oimpresso-com/memory/project_modulos_promovidos_2026_04_24.md
~/.claude/projects/D--oimpresso-com/memory/project_evolutionagent_spec.md
~/.claude/projects/D--oimpresso-com/memory/project_cms_redesign_inertia.md
~/.claude/projects/D--oimpresso-com/memory/reference_project_memory.md
~/.claude/projects/D--oimpresso-com/memory/reference_quick_sync_quebrada.md
~/.claude/projects/D--oimpresso-com/memory/reference_branch_3_7.md
```

---

## Pendente

- ~~Renomear `0024-padrao-inertia-react-ultimatepos.md` → `0029-padrao-inertia-react-ultimatepos.md`~~ ✅ feito em 2026-04-27.
- Materializar ADRs 0031–0036 se Wagner aprovar.
- Investigar `Modules/Connector/` no servidor: contar untracked vs tracked, decidir versionar/deletar/restaurar (SSH instável durante esta sessão impediu confirmação).
- Re-rodar `gh run list --workflow=quick-sync.yml` quando o secret SSH for atualizado pra recuperar deploy automático.

---

**Próximo passo natural:** próximo agente que pegar essa branch já encontra papéis claros das memórias (ADR 0027) e regras para não recriar conflitos.
