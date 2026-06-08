---
description: Detecta skills (.claude/skills/*/SKILL.md) novas ou modificadas desde a última sessão deste dev. Avisa o que mudou, lê o conteúdo novo via Read, e diz claramente se basta usar via Read direto OU se exige restart do Claude Code (harness só carrega matching automático no startup).
---

# /sync-skills — atualizar skills locais sem perder o que mudou

**O contexto técnico que importa:**
- Skills vivem em `.claude/skills/<nome>/SKILL.md` (versionadas em git).
- O **harness do Claude Code** lê e indexa as skills **só no startup**. Skill nova adicionada após o startup **não dispara matching automático** — fica dormente até `/clear` ou reiniciar a aplicação.
- **Mas:** Claude pode ler `SKILL.md` via `Read` direto e usar o conteúdo nesta sessão. Só não recebe a injeção automática via `system-reminder`.

Resultado: dev faz `git pull`, recebe skill nova, mas o Claude do dev não "sabe" que ela existe. Esse comando resolve.

## O que faz

1. **Detecta diff** — compara o estado atual de `.claude/skills/` com o último estado conhecido pelo dev (timestamp salvo em `.claude/.last-skills-sync`).
2. **Lê via git** o que mudou desde o último sync — usa `git log` filtrado em `.claude/skills/`.
3. **Para cada skill nova/modificada:**
   - `Read` o `SKILL.md` completo.
   - Mostra: nome + description + autor último commit + 1-linha do que mudou.
4. **Classifica o impacto:**
   - **🟢 leve** (typo, melhoria de description, link corrigido) → "pode esperar próximo restart, sem urgência".
   - **🟡 moderado** (mudança em pegadinhas, novo gotcha, novo caso) → "lê o conteúdo novo agora; matching automático só no próximo restart".
   - **🔴 alto** (skill nova inteira, mudança de regra) → "skill nova: harness não vai matchar automaticamente — usa via `Read` quando precisar OU reinicia Claude Code pra ativar matching".
5. **Atualiza** `.claude/.last-skills-sync` com timestamp atual (gitignored).
6. **Resumo final:** quantas skills mudaram, recomendação de restart sim/não.

## Comportamento esperado (passo a passo)

1. Roda `git log --since='<last-sync-ou-7-dias>' --name-only --diff-filter=AMR -- .claude/skills/` pra listar arquivos tocados.
2. Se nada mudou: imprime `✅ skills atualizadas (último sync <timestamp>)` e termina.
3. Se houver mudanças:
   - Lista cada SKILL.md afetado com:
     - Nome (slug = pasta).
     - Tipo de mudança (`A` adicionado / `M` modificado / `R` renomeado).
     - 1-linha do último commit que tocou: `git log -1 --pretty=format:"%h %s" -- <path>`.
     - `description:` extraída do frontmatter.
   - Para skills 🔴 (novas inteiras): aviso explícito "harness atual NÃO vai matchar — usa via `Read .claude/skills/<slug>/SKILL.md` quando precisar".
   - Para skills 🟡: mostra as mudanças concretas (`git diff` resumido) + "vou ler o conteúdo novo agora pra ter na sessão atual".
4. Atualiza `.claude/.last-skills-sync` com `Get-Date -Format "yyyy-MM-ddTHH:mm:ss"`.
5. Resumo final 1-linha:
   - **Se houver 🔴:** `⚠️ <N> skills novas: reinicia Claude Code (Ctrl+C + relançar) ou /clear pra ativar matching automático.`
   - **Se só houver 🟡/🟢:** `✅ <N> skills modificadas, conteúdo lido na sessão. Matching automático no próximo restart.`

## Quando rodar

- **Sempre que fizer `git pull` em main** com mudanças em `.claude/skills/`.
- **No início da sessão** se o último restart foi há mais de 1 dia útil (alguém pode ter mergeado).
- **Quando outro dev (Wagner/Felipe) avisar "merguei skill nova"**.
- **Hook SessionStart** pode auto-disparar este comando — ver `.claude/hooks/check-skills-fresh.ps1`.

## Multi-tenant / time

Cada dev (Wagner/Felipe/Maiara/Luiz/Eliana) tem seu próprio `.claude/.last-skills-sync` local — gitignored. Não polui git. Cada um vê só o diff desde o **seu** último sync.

## Diferença pra ADR 0073 (Team MCP P0)

- **Este comando**: skills LOCAIS no filesystem do dev, integradas no Claude Code via auto-matching.
- **ADR 0073**: skills/policies em DB MCP, queryáveis via tool `skills-search` mesmo sem ter no filesystem.
- **Complementares**: ADR 0073 entrega "saber que existe"; este comando entrega "ativar no meu Claude Code".

## Limitações honestas

- O Claude Code **não expõe API de hot-reload** de skills. Restart é necessário pra ativar matching automático de skills novas.
- `Read` direto funciona em skills modificadas mas o **header `<system-reminder>` da sessão atual fica desatualizado** — o description listado lá vai continuar com o conteúdo antigo. Sem solução nesta camada.
- Se Anthropic adicionar API de reload no futuro, este comando vira obsoleto — substituir por chamada direta.

## NÃO faz

- ❌ `git pull` automático (deixa pro `/sync-mem` ou ação explícita do dev).
- ❌ Mexer em skills de outros plugins (`~/.claude/plugins/`).
- ❌ Editar skills.
- ❌ Reiniciar Claude Code (não tem permissão pra isso).
