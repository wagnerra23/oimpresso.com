# ADR 0027 — Gestão de memória do projeto: papéis claros por função

**Status:** ✅ Aceita
**Data decisão:** 2026-04-26
**Autor:** Wagner (dono/operador)
**Registrado por:** Claude (sessão `dazzling-lichterman-e59b61`, comparativo Capterra das memórias)
**Relacionado:** ADR 0010 (sistema de memória), ADRs 0028–0036 (este consolida os subsequentes)

---

## Contexto

O projeto tem **9 sistemas de memória** convivendo sem regras explícitas de quando usar cada um:

1. `CLAUDE.md` (primer no repo)
2. `AGENTS.md` (mirror leve no repo)
3. `memory/00-09` numerados (memória do projeto)
4. `memory/decisions/` (ADRs)
5. `memory/sessions/` (sessões cronológicas)
6. `memory/requisitos/{Mod}/` (specs de módulo)
7. Auto-memória do Claude em `~/.claude/projects/D--oimpresso-com/memory/`
8. `Modules/MemCofre/` (vault de evidências do app)
9. Git history + PRs no GitHub

Em 2026-04-26 foi feito comparativo "estilo Capterra" (15 funções × 9 produtos) que revelou:

- Sobreposição de papéis (handoff atual aparecia em 3 lugares)
- Conflitos: stack IA tinha 4 fontes contradizendo entre si
- Anomalias estruturais: `AGENTS.md` stale (dizia "Laravel 10"); ADR 0024 duplicado; ADR 0012 ausente
- Auto-memória sendo usada pra registrar coisas que deveriam estar no repo (cross-agent)

## Decisão

**Cada função tem um único sistema canônico.** Outros podem mencionar, mas não podem ser fonte de verdade.

| Função | Canônico | Justificativa |
|---|---|---|
| Onboarding (primer) | `CLAUDE.md` | Sempre carregado; primer obrigatório |
| Estado momentâneo | `memory/08-handoff.md` | Sobrescrito a cada sessão |
| Decisões arquiteturais | `memory/decisions/NNNN-slug.md` | Formato Nygard, why+alternativas+status |
| Histórico cronológico | `memory/sessions/YYYY-MM-DD-NN-slug.md` | Append-only |
| Especificação de módulo | `memory/requisitos/{Mod}/` | Template fixo: README+SPEC+ARCHITECTURE+GLOSSARY+CHANGELOG+adr/{arq,tech,ui}/ |
| Convenções de código | `memory/04-conventions.md` | Já existe, único arquivo dedicado |
| Glossário de domínio | `memory/06-domain-glossary.md` | CLT/AFD/REP-P consolidados |
| Cross-conversation Claude | Auto-memória (`~/.claude/projects/...`) | Único que sobrevive a `/clear` |
| Cross-agent (Cursor + Claude) | Qualquer arquivo do repo | Cursor lê o repo; auto-memória **NÃO** |
| Credenciais sensíveis | `.env` + 1Password | Nunca em CLAUDE.md/git/MemCofre/auto-memória |
| Evidências do usuário (prints, erros, chat logs) | `Modules/MemCofre/` | Único com pipeline de upload + classificação IA |
| Auditoria "quem mudou quê" | `git log` + PRs no GitHub | Imutável, autoritativo |
| Edição rápida durante sessão | Auto-memória | Sem commit, instantâneo |
| Domain quirks (ex.: ROTA LIVRE 1280px) | Auto-memória | Idiossincrasia que não vale poluir CLAUDE.md |
| Atualizar memory/04-09 | Conscientemente, não a cada sessão | São conceitos estáveis; mudanças de estado vão pra 08-handoff |

## Regras derivadas

1. **Não duplicar.** Se uma info já está no repo (cross-agent), auto-memória só pode apontar ("ver `memory/08-handoff.md`").
2. **Quando descobrir conflito de memória** (ex.: 2 fontes discordando), atualizar todas e abrir ADR se a discordância revela decisão não tomada.
3. **Auto-memória nunca contém info canônica do projeto** — somente preferências do usuário, quirks de cliente, atalhos de comunicação.
4. **CLAUDE.md cresce com cuidado:** sempre carregado custa contexto. Conteúdo procedural (deploy, SSH) é OK; conteúdo histórico (lista completa de ADRs, status de cada módulo) NÃO — esses ficam em `memory/`.

## Consequências

✅ Agente novo (Claude ou Cursor) tem path determinístico.
✅ Conflitos detectáveis automaticamente (cada fato tem um lugar — divergência = bug).
✅ Auto-memória deixa de ser "rascunho geral" e vira ferramenta cross-conversation focada.
✅ ADRs subsequentes (0028–0036) materializam regras específicas.
⚠️ Migração: `AGENTS.md` precisa virar tombstone apontando pra CLAUDE.md (ADR 0028).
⚠️ ADR 0024 duplicado e ADR 0012 ausente precisam de cleanup (ADR 0030).
⚠️ Comparativo Capterra completo está em [memory/sessions/2026-04-26-session-15.md](../sessions/2026-04-26-session-15.md).

## Alternativas consideradas

- **Centralizar tudo em CLAUDE.md:** rejeitado — cresce demais, custo de contexto inviável.
- **Migrar memory/ pra Notion/wiki externa:** rejeitado — perde versionamento git e cross-agent grátis.
- **Eliminar auto-memória:** rejeitado — perde cross-conversation barato. Agente recomeçaria do zero a cada `/clear`.
- **Eliminar MemCofre:** rejeitado — pipeline de evidência é diferenciável (concorrentes não têm).
