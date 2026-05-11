# Como conversar entre sessões Claude (nuvem ↔ local)

## Por que existe

Wagner roda Claude em paralelo: claude.ai/code (nuvem, sandbox Linux), Claude Code local (Windows desktop), Cursor, agentes worktree. As sessões **não se enxergam diretamente** — cada uma começa do zero, filesystem isolado. Git é a única ponte universal.

Casos típicos:
- Sessão nuvem gerou arquivos (protótipo, doc, script) que precisam virar PR no repo
- Sessão local precisa de artefato que outra Claude produziu lá fora
- Qualquer transferência onde "copy/paste no chat" é proibitivo (>200 linhas) ou perigoso (PII, segredos)

**Anti-padrão tentador:** dividir arquivo em chunks e colar no chat. Validado em sessão 2026-05-11 (Cockpit V2): 3707 linhas viraram 19 chunks, foi cansativo e custoso. Bridge branch via device flow resolveu em 2 minutos.

## Caminho canônico — bridge branch via GitHub device flow

1. **Sessão nuvem** gera arquivos, `git checkout -b proto/<slug> origin/main` + `git add` explícito + `git commit`
2. **Sessão nuvem** autentica GitHub: `gh auth login --hostname github.com --git-protocol https --web`
3. **Wagner** abre `https://github.com/login/device` no browser, cola o código de 8 chars, autoriza com conta `wagnerra23`
4. **Sessão nuvem** roda `gh auth setup-git && git push -u origin proto/<slug>` + cria PR **draft**
5. **Sessão local** roda `git fetch + git checkout proto/<slug>`, valida arquivos
6. **Sessão local** marca PR ready + squash merge `--admin` (autor final = `wagnerra23`, assinado por GitHub web key)
7. **Sessão local** deleta branch ponte (local + remota)

## Template — cola na sessão nuvem

```
Bridge branch via device flow (memory/how-bridge-cloud-local.md). Sem PAT,
sem chunks copiados. Você empurra a branch ponte; sessão local revisa e
abre/mergeia o PR final.

1. cd no repo, git status (limpo ou identificar exatamente o que entra —
   NUNCA git add -A nem git add .)
2. git fetch origin && git checkout -b proto/<slug> origin/main
3. git add <arquivos por nome explícito>  (lista, não wildcard)
4. git commit -m "wip(<escopo>): <descrição curta>"
   - Conventional commits, PT-BR
   - SEM PII (CPF/CNPJ/email cliente)
   - SEM segredos (.env, tokens, chaves)
   - NUNCA --no-verify
   - --no-gpg-sign SÓ se signing-server do sandbox bugar (commit final em
     main vai ser refeito via squash com GitHub web key — histórico em
     main fica assinado)
5. gh auth login --hostname github.com --git-protocol https --web
   - Imprima EXATAMENTE pro Wagner: o código (XXXX-XXXX) + URL
     (https://github.com/login/device)
   - PARE até Wagner confirmar "autorizei"
6. gh auth setup-git
7. git push -u origin proto/<slug>
8. gh pr create --base main --head proto/<slug> --draft --title "..."
   --body "..."

Imprima o bloco final pra Wagner copiar:
  BRANCH-PONTE-PRONTO
  branch:  proto/<slug>
  sha:     <git rev-parse HEAD>
  files:   <lista>
  url:     https://github.com/wagnerra23/oimpresso.com/pull/<N>

Se algum passo falhar (sem credencial, hook bloqueando, conflito), PARE e
reporte o erro literal — não tente workaround.
```

## Comandos de salvação — sessão local

```bash
# Puxar bridge branch
git fetch origin proto/<slug>
git checkout -B proto/<slug> origin/proto/<slug>

# Sanity check (autor, assinatura, arquivos)
git log -1 --format="sha=%H%nauthor=%an <%ae>%nsig=%G?%nsubject=%s"
git diff main...HEAD --stat
git diff main...HEAD --name-only

# Marcar ready + squash merge
gh pr ready <N>
gh pr merge <N> --squash --delete-branch --admin \
  --subject "feat(<escopo>): <título limpo>" \
  --body "..."

# Cleanup local (se --delete-branch falhou)
git push origin --delete proto/<slug>   # remota
git switch --detach                      # libera a branch
git branch -D proto/<slug>               # local
```

## Gotchas

- **Signing server da nuvem pode estar bugado** ("missing source" 400). `--no-gpg-sign` é aceitável **apenas** em branch descartável (`proto/`) que vai ser squash-mergeada — o commit final em `main` fica assinado pela GitHub web key via squash. **Nunca** `--no-gpg-sign` em commit que vai direto pra `main`/`feat/`/`fix/`.
- **PAT no chat = nunca** ([`feedback_nunca_publicar_credenciais_no_chat`](../../.claude/projects/D--oimpresso-com/memory/feedback_nunca_publicar_credenciais_no_chat.md) — regra dura). Device flow não expõe credencial.
- **`gh auth setup-git` é essencial** depois do device flow — senão `git push` ainda tenta HTTPS password e falha com "could not read Password".
- **PR sempre do lado local** — só local enxerga `memory/`, ADRs, charters, gates Tier 0 (`mwart-gate`, `multi-tenant-patterns`, `commit-discipline`). Nuvem abre `--draft` no máximo.
- **Prefixo de branch importa:** `proto/<slug>` (descartável, será deletada) ≠ `claude/<slug>` (worktree Claude Code) ≠ `feat/fix/docs/<slug>` (PR sério). Facilita cleanup em massa: `git branch -D proto/*`.
- **`sig=E` localmente após squash** significa "chave não importada pra verificar" — GitHub assina com web key, seu git local não tem a chave pública. No GitHub aparece "Verified". É OK.

## Referência

- [ADR 0040](decisions/0040-policy-publicacao-claude-supervisiona.md) — publication-policy (quem pode publicar o quê)
- [ADR 0061](decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) — conhecimento canônico no git, não em sessão local
- [ADR 0070](decisions/0070-jira-style-task-management-current-md-removed.md) — tasks via MCP, não markdown
- Skill `commit-discipline` (Tier A) — 1 PR = 1 intent, ≤300 linhas, conventional commits
- Validado em PR [#552](https://github.com/wagnerra23/oimpresso.com/pull/552) (Cockpit V2 — 4 arquivos, 3707 linhas, sessão 2026-05-11)
