---
status: shipped
priority: -
problem: "Migrar UltimatePOS 3.7 (Laravel 5.8) → v6 (Laravel 9) → Laravel 13 mantendo 147+ customizações ROTA LIVRE. Precisava IA conectada na raiz do servidor pra resolver problemas in-place durante upgrade."
persona: "Wagner — operador único do upgrade, sem time de DevOps"
estimated_effort: "Já entregue: ~6 semanas wallclock (2026-03 a 2026-04-23 com M3+M5+M7+M10 fechados)"
references:
  - https://claude.ai/chat/c113a681-899d-4d92-9012-b9f4784086fe
  - reference_hostinger_server.md (SSH key id_ed25519_oimpresso instalado 2026-04-23)
  - project_roadmap_milestones.md (M3+M5+M7+M10 done)
  - branch 6.7-bootstrap
related_modules:
  - (todos — foi infra que viabilizou o resto)
---

# Ideia: AutomacaoCC — Claude Code conectado ao Hostinger pra upgrade UltimatePOS

> ⚠️ **Status `shipped`** — não é ideia futura, é registro histórico do **playbook que foi executado** entre 2026-03 e 2026-04-23. Mantida aqui pelo aprendizado e pra futuros upgrades.

## Problema (resolvido)

ROTA LIVRE rodava UltimatePOS 3.7 customizado em Laravel 5.8 com 147+ arquivos modificados (Connector + módulos custom). Precisava chegar a Laravel 13 sem perder customizações.

**Restrição-chave:** Wagner sozinho, sem DevOps, sem ambiente de staging dedicado. Precisava IA que **conectasse direto na raiz do Hostinger** pra resolver problemas conforme aparecessem.

## O que foi entregue

| Marco | Data | Resultado |
|---|---|---|
| M3 — Migração Laravel 5.8 → 9 | 2026-04 | OK |
| M5 — Atualização UltimatePOS 3.7 → 6.x | 2026-04 | OK |
| M7 — Salto Laravel 9 → 13.6 | 2026-04-23 | OK |
| M10 — Inline knox/pesapal (deps abandonadas) | 2026-04-23 | OK |
| Claude Code com SSH ed25519 no Hostinger | 2026-04-23 | OK (`reference_hostinger_server.md`) |

Branch atual: `6.7-bootstrap` (Wagner commita direto, não em `main`).

## Estratégia que funcionou

### Caminho A escolhido: comprar v6 + reintegrar customizações

Conversa propôs 2 caminhos:
- **A** — baixar UltimatePOS v6 original do Codecanyon, mapear customs do 3.7, **portar só o modificado** pra v6
- **B** — migração incremental manual 5.8 → 6 → 7 → 8 → 9

**Wagner escolheu A** (mais rápido). O fornecedor já tinha entregue v6.12 estável em PHP 8 + Laravel moderno — não fazia sentido reinventar.

### Stack de upgrade

1. **Laravel Shift** (laravelshift.com) — automatiza salto entre versões (deps, métodos renomeados, return types PHP 8, migrations anônimas)
2. **Claude Code** na raiz do servidor — corrige depreciações, conserta erros em runtime
3. **tmux** pra sessões persistentes (sobrevive a queda de SSH)
4. **Usuário não-root** (`deploy` com sudo) — evitar `claude` rodando root puro

### Audit script (gerado na conversa)

`audit.php` — scaneia projeto e gera relatório markdown/HTML/JSON com:

| Categoria | O que detecta |
|---|---|
| **Git diff** | Arquivos modificados/adicionados vs tag original |
| **composer.json** | Pacotes incompatíveis com Laravel 9 |
| **Depreciações** | +25 funções removidas (`array_get`, `str_slug`, etc.) |
| **PHP compat** | `?->` nullsafe, `match()`, `enum` |
| **Tags custom** | TODO, CUSTOM, HACK, FIXME, OVERRIDE |
| **Críticos** | Score por arquivo + lista mais urgentes |

Output `audit_report.json` alimentava o Claude Code:
> "Leia audit_report.json e corrija automaticamente todas as depreciações listadas"

### Setup script (gerado na conversa)

`setup.sh` — provisionava Hostinger do zero:

| Fase | Ação |
|---|---|
| 1 | Cria usuário `deploy` + UFW + Fail2Ban |
| 2 | Atualiza sistema |
| 3 | Instala PHP 8.2 + extensões |
| 4 | Composer v2 |
| 5 | Node.js 20 LTS |
| 6 | Claude Code + atalho `claude-pos` |
| 7 | Backup completo (arquivos + banco + .env) |
| 8 | Branch git + modo manutenção |
| 9 | Gera `CLAUDE_UPGRADE.md` com instruções |

Depois: `su - deploy && tmux new -s upgrade-pos && claude-pos`. Dentro do Claude Code: "Leia CLAUDE_UPGRADE.md e execute todas as tarefas".

## Lições gravadas

### O que **funcionou**

- **Caminho A (port custom → v6 nova)** poupou semanas vs migração 5 saltos manual
- **Claude Code na raiz** resolveu 90%+ dos problemas em runtime sem trocar contexto
- **tmux** salvou múltiplas vezes — SSH cai, Claude continua
- **Audit antes de upgrade** evitou surpresas

### O que **deu errado** (e ficou aprendido)

- **Edit silencioso**: Edit sem Read prévio falha sem alertar (`feedback_format_now_local_e_default_datetime.md`). Validar pós-deploy via `grep` no servidor, não só `git status` local.
- **Timezone shift histórico**: corrigir bug visível pode quebrar dados decorados. Separar API novo do antigo (`format_date` mantém shift, `format_now_local` novo) > consertar in-place. Ver `feedback_carbon_timezone_bug.md`.
- **Form shim laravelcollective→spatie**: `disabled=false` virava `disabled` ativo no HTML. Bug universal corrigido em 5 linhas + 19 testes regressivos. Ver `feedback_form_shim_bool_attrs.md`.

### Permissão Spatie

Roles formato `{Nome}#{biz_id}` (ex: `Vendas#4` pra ROTA LIVRE). Adicionar `location.{id}` quando criar role nova de operador. Ver `cliente_rotalivre.md` (incidente Vendas#4 sem `location.4` quebrava `/sells/create`).

## Conexões

- **Hostinger Server Access** (`reference_hostinger_server.md`) — SSH `-4 -i ~/.ssh/id_ed25519_oimpresso`, instalado 2026-04-23
- **Análise DB Hostinger** (`reference_hostinger_analise.md`) — receita SSH+MySQL em IPv4
- **Roadmap milestones** (`project_roadmap_milestones.md`) — M3+M5+M7+M10 done
- **Branch 3.7-com-nfe** (`reference_branch_3_7.md`) — snapshot pré-migração; Connector tem 147 arquivos pendentes de restauração
- **Diff 3.7 vs 6.7 Officeimpresso** (`reference_diff_3_7_vs_6_7_officeimpresso.md`) — armadilha master user, campos do registry licenca_computador

## Por que mantida aqui (e não deletada)

1. **Playbook reutilizável** — próximo upgrade Laravel 13 → 14/15 vai usar o mesmo padrão (audit + Claude Code + tmux)
2. **Origem rastreável** dos scripts `audit.php` e `setup.sh` — útil quando alguém quiser saber "de onde veio essa ideia?"
3. **Lições gravadas** — referência viva pra evitar erros já cometidos
