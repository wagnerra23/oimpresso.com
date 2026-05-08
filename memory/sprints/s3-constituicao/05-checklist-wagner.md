# Checklist Wagner — Sprint 3 Constituição v2

> **Status:** 🔴 ESQUELETO — Wagner segue após dossier preenchido.
>
> Wagner dirige S3 pessoalmente. Sonnet só executa o que Wagner aprovar.

---

## Pré-requisitos (antes de começar)

- [ ] Sprint 1 (Daily Brief) — postmortem aprovado, métricas no alvo
- [ ] Sprint 2 (MWART) — postmortem aprovado, MWART em prod ROTA LIVRE
- [ ] §13 do ROTEIRO-MESTRE com blocos A–G aprovados
- [ ] Backup: branch `pre-s3-snapshot` criada (`git checkout -b pre-s3-snapshot && git push -u origin`)

---

## Passo 1 — Aprovar dossier (este Sprint 3)

- [ ] Ler `01-adr-constituicao-v2.md` rascunho preenchido por Sonnet
- [ ] Marcar APROVADO/RECUSADO/PARCIAL pra cada seção
- [ ] Fazer mesmo com `02-adr-skills-tiers.md`
- [ ] Aprovar tabela em `03-skills-audit.md` em 3 rodadas (5 skills × rodada)
- [ ] Aprovar estrutura `04-claude-md-novo.md` (proposta ≤100 linhas)
- [ ] Aprovar `06-rollback-plan.md`

**Tempo estimado:** ~2h leitura + decisão. Pode ser dividido em 3 sessões.

---

## Passo 2 — Mover conteúdo do CLAUDE.md atual pra 5 arquivos novos

Sequência (Sonnet executa, Wagner valida cada arquivo):

- [ ] Criar `memory/why-oimpresso.md` (extrair §1 + visão)
- [ ] Criar `memory/what-oimpresso.md` (extrair stack + módulos canônicos)
- [ ] Criar `memory/how-trabalhar.md` (extrair §2 fluxo MCP-first)
- [ ] Criar `memory/proibicoes.md` (extrair §4 + §5)
- [ ] Criar `memory/regras-time.md` (extrair §10)

**Tempo estimado:** ~3h. Custo Sonnet: ~$0.20.

---

## Passo 3 — Reescrever CLAUDE.md como índice ≤100 linhas

- [ ] Sonnet escreve nova versão seguindo `04-claude-md-novo.md`
- [ ] Wagner abre lado a lado: CLAUDE.md atual vs proposta
- [ ] Aprovar/ajustar
- [ ] Substituir CLAUDE.md em commit separado

**Tempo estimado:** ~2h. Custo: ~$0.10.

---

## Passo 4 — Auditar 19 skills

Pra cada skill (em ordem alfabética):

- [ ] Ler SKILL.md
- [ ] Validar `description` começa com "Use ao/quando" (ou reescrever)
- [ ] Confirmar tier proposto na tabela `03-skills-audit.md`
- [ ] Anotar mudanças em frontmatter (adicionar `tier:` como documentação)

**Tempo estimado:** ~5min × 19 = ~1.5h. Custo: ~$0.10.

---

## Passo 5 — Mover skills arquivadas

(Se houver decisões ARQUIVAR — proposta atual: nenhuma)

- [ ] `git mv .claude/skills/<x>/ .claude/skills/_archive/<x>/`
- [ ] Commit em batch

**Tempo estimado:** ~30min se houver. Custo: zero.

---

## Passo 6 — Promover 5 skills Tier A

- [ ] `brief-first/SKILL.md` — adicionar `tier: A` no frontmatter
- [ ] Renomear `oimpresso-mcp-first/` → `mcp-first/` (`git mv`)
- [ ] Reescrever `mcp-first/SKILL.md` description ("Use SEMPRE antes de ler arquivos com Read")
- [ ] Promover `multi-tenant-patterns/SKILL.md` de B para A
- [ ] Criar `commit-discipline/SKILL.md` (description + body)
- [ ] Criar `charter-first/SKILL.md` com `enabled: false` (dormente até S4)
- [ ] Criar `ads-route/SKILL.md` com `enabled: false` (dormente até S5)

**Tempo estimado:** ~2h. Custo: ~$0.15.

---

## Passo 7 — Atualizar hook SessionStart

- [ ] Editar `.claude/settings.json` — hook `SessionStart` chama `brief-fetch` automaticamente
- [ ] Testar 1 sessão real: `claude --no-input "olá"` deve ter `brief-fetch` na primeira tool call
- [ ] Felipe testa em sessão dele
- [ ] Reverter se quebrar

**Tempo estimado:** ~30min. Custo: zero.

---

## Passo 8 — Commitar 2 ADRs novas + atualizações

Commit 1: `feat(constituicao): ADR mãe + ADR skills tiers`
Commit 2: `refactor(claude-md): reescrita ≤100 linhas + 5 imports`
Commit 3: `feat(skills): promover 5 Tier A + auditoria 19 skills`
Commit 4: `feat(hooks): SessionStart força brief-fetch`

- [ ] PR pra main com 4 commits
- [ ] Wagner aprova merge

**Tempo estimado:** ~30min. Custo: zero.

---

## Passo 9 — Soak 7 dias + postmortem

- [ ] Wagner usa Claude Code normalmente por 7 dias
- [ ] Felipe e Maiara testam (se acessíveis)
- [ ] Medir tokens médios/sessão antes vs depois (query SQL `mcp_audit_log`)
- [ ] Postmortem em `99-postmortem.md`

**Tempo:** 7 dias passivos + ~2h escrita postmortem.

---

## Critérios de sucesso (todos precisam bater)

- [ ] CLAUDE.md ≤ 100 linhas
- [ ] 5 arquivos importados criados
- [ ] 5 skills Tier A no ar (3 ativas + 2 dormentes)
- [ ] 2 ADRs canon novas commitadas
- [ ] Token médio onboarding -30% vs baseline pré-S3
- [ ] Hook SessionStart força brief-fetch em 100% das sessões
- [ ] Felipe + Wagner testaram OK em sessão real

Se algum critério falhar → **NÃO seguir pra S4**. Postmortem investiga.

---

## Rollback (se necessário)

Ver `06-rollback-plan.md`.

Resumo: `git revert` dos 4 commits + skills voltam de `_archive/` automaticamente + hook SessionStart revertido.

---

## Próximos passos pós-S3

- Iniciar dossier S4 (Page Charters)
- Esquecer charters dormentes (charter-first vira ativa quando tool charter-fetch existir)
- Postmortem global se ≥3 critérios falharam
