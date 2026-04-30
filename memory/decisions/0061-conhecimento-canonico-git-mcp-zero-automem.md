# ADR 0061 — Conhecimento canônico em git/MCP, ZERO auto-mem privada

**Status:** ✅ Aceita
**Data:** 2026-04-30
**Decisores:** Wagner [W]
**Tags:** governanca · team-plan · memoria · mcp · auto-mem · regra-fundamental

Relacionada: [ADR 0027](0027-gestao-memoria-roles-claros.md) (papéis canônicos), [ADR 0053](0053-mcp-server-governanca-como-produto.md) (MCP), [ADR 0055](0055-self-host-team-plan-equivalente-anthropic.md) (Team plan), [ADR 0059](0059-governanca-memoria-estilo-anthropic-team.md) (10 pilares governança), [ADR 0056](0056-mcp-fonte-unica-memoria-copiloto-claude-code.md) (MCP fonte única).

---

## Contexto

Wagner formalizou em 2026-04-30 noite: *"não deve existir auto-mem. elas devem estar no mcp. tudo deve ser adr e sincronizada. regra do team"*.

### O problema

Auto-mems privadas (em `C:/Users/wagne/.claude/projects/D--oimpresso-com/memory/*.md`) são silos:
- ❌ Felipe/Maíra/Luiz/Eliana **não enxergam** — invisible knowledge
- ❌ Não passam por code review nem PR
- ❌ Não têm versionamento git
- ❌ Não estão no MCP (impossível buscar via `decisions-search`)
- ❌ Se a máquina do dev falhar, conhecimento perdido
- ❌ Cada dev acumula conhecimento diferente — drift de "verdade"

Hoje (30-abr) tinha **~80 auto-mems** acumuladas (incluindo recentes que eu criei: `feedback_processo_canonico_claude_team`, `reference_ssh_hardening_ct100`, etc). **Erro arquitetural** — Wagner é o único que vê esse conhecimento.

### Anti-pattern detectado

Tendência de Claude (eu) criar auto-mem como reflexo "lembre na próxima sessão". Cada uma cria silo. Acumulado em 80 entradas em 6 meses. Anti-Team plan.

---

## Decisão

**Todo conhecimento que pode ser útil pra mais de 1 dev OU pra Claude futuro vai pra git→MCP.**
**Auto-mems privadas ficam apenas pra 4 casos específicos.**

### Onde mora o quê (matriz canônica)

| Tipo de conhecimento | Antes | Agora | Por quê |
|---|---|---|---|
| Decisão arquitetural | ADR | ✅ ADR git | mantém |
| Receita técnica reproduzível (SSH hardening, Proxmox bootstrap, etc) | auto-mem | **ADR ou runbook em git** | qualquer dev reproduz |
| Padrão / convenção do projeto | auto-mem | **ADR ou `memory/04-conventions.md`** | time alinhado |
| Estado do código / cliente / histórico de incidente | auto-mem | **session log `memory/sessions/YYYY-MM-DD-*.md`** | timeline auditável |
| Quirk de cliente (ex: Larissa decorou +3h shift) | auto-mem | **`memory/requisitos/{Modulo}/quirks.md` ou ADR** | parte do produto |
| Comparativo Capterra | auto-mem | **`memory/comparativos/`** | competitive intelligence |
| Endpoint/credencial/path SSH | auto-mem | **`INFRA.md` + Vaultwarden** | ops shared |
| Preferência ESTRITAMENTE pessoal Wagner (ex: "use PT-BR sempre") | auto-mem | **`memory/05-preferences.md`** | já é git |

### As 4 exceções permitidas pra auto-mem privada

Auto-mem **só** pra:

1. **Credenciais/secrets temporários em desenvolvimento** (ex: token de teste descartável depois de 24h) — NUNCA secrets de prod (esses vão pra Vaultwarden)
2. **Working memory ad-hoc do agente atual** (ex: "Wagner pediu pra parar X às 14h" durante uma sessão) — descartável após sessão
3. **Cache local de tools/skills** — config do próprio Claude Code
4. **Hint pessoal Wagner-only** que ele NÃO quer que outros devs vejam (raro — usar `memory/05-preferences.md` quando coletivo)

### Regra geral pra Claude (eu)

**Antes de criar auto-mem, perguntar:**
- "Outro dev (Felipe/Maíra/Luiz/Eliana) precisaria saber disso pra fazer o trabalho?"
- "Eu mesmo vou querer encontrar isso via `decisions-search` daqui 1 mês?"

Se SIM pra qualquer → **ADR ou commit no `memory/*`**, NÃO auto-mem.

### Templates pra cada tipo

| Conhecimento | Caminho git | Sync MCP |
|---|---|---|
| Decisão arquitetural | `memory/decisions/NNNN-slug.md` (Nygard) | ✅ webhook |
| Decisão por módulo | `memory/requisitos/{Mod}/adr/{arq\|tech\|ui}/NNNN-slug.md` | ✅ |
| Runbook (receita) | `memory/requisitos/{Mod}/RUNBOOK.md` ou `RUNBOOK-{tema}.md` | ✅ |
| Spec funcional | `memory/requisitos/{Mod}/SPEC.md` ou `SPEC-{feature}.md` | ✅ |
| Session log | `memory/sessions/YYYY-MM-DD-{slug}.md` | ✅ |
| Comparativo | `memory/comparativos/{slug}_capterra.md` | ✅ |
| Convenção do projeto | `memory/04-conventions.md` (apenda) | ✅ |
| Preferência Wagner coletiva | `memory/05-preferences.md` (apenda) | ✅ |
| Credencial/host/SSH | `INFRA.md` (apenda) | ✅ |

---

## Migração das ~80 auto-mems existentes

Faseada — não é blocker do Cycle 02. Plano:

### Fase 1 — Auditar e classificar (1 sessão)

Lê `C:/Users/wagne/.claude/projects/D--oimpresso-com/memory/*.md`, classifica cada uma em:
- 🟢 **Migrar pra git** → cria ADR/runbook/reference apropriado
- 🟡 **Já existe versão git** → marca auto-mem como deprecated apontando pro ADR
- ⚪ **Justifica como exceção** (4 casos acima) → mantém auto-mem
- 🔴 **Obsoleta** → deletar

### Fase 2 — Migrar candidatos (3-5 sessões iterativas)

Cada batch: 5-10 auto-mems migradas, com PR no git pra Wagner approve.

### Fase 3 — Hard-rule no skill

Atualizar `.claude/skills/oimpresso-mcp-first/SKILL.md` adicionando:
> ⛔ **NUNCA criar arquivo em `~/.claude/projects/*/memory/`** — abrir PR no repo `oimpresso.com` em `memory/decisions/` ou `memory/requisitos/{Mod}/` em vez disso.

E hook PreToolUse pra alertar se Claude tentar `Write` em `~/.claude/projects/*/memory/*.md`.

### Fase 4 — Limpeza

Após migração validada (3 meses sem violação), `rm -rf C:/Users/wagne/.claude/projects/D--oimpresso-com/memory/*` exceto as 4 exceções permitidas. Conhecimento 100% no git/MCP.

---

## Justificativa

### Por que regra é necessária

**Anthropic Team plan** (referência ADR 0055/0059) tem **conhecimento compartilhado por design** — Projects/Files/skills são visíveis ao workspace inteiro. Auto-mem privada do Claude Code do Wagner viola esse princípio.

Quando Felipe entrar (`/copiloto/admin/team` token gerado), ele vai abrir Claude Code e **não enxergar** nada que Wagner aprendeu nos últimos 6 meses. Time perde knowledge institucional acumulado.

### Por que MCP, não só git

Git tem o conteúdo, mas:
- IA precisa retrieval rápido (busca semântica)
- Audit "quem leu o quê" precisa ser server-side (não client-side)
- Permission per-doc (`scope_required`) só faz sentido em DB
- Ranking por authority/recency precisa de runtime computation

Git é fonte canônica + MCP é cache governado (ADR 0053). Os 2 + sincronização automática via webhook = melhor dos dois mundos.

### Por que ADR primeiro, não direto reference

ADR força Wagner (ou Felipe future) a aprovar via PR. **Knowledge que Claude inventa sozinho** vai pelo radar humano. Reference/runbook commitado pode entrar mais leve, mas decisões arquiteturais SEMPRE ADR.

---

## Trade-offs aceitos

| Trade-off | Mitigação |
|---|---|
| **Mais fricção** pra criar conhecimento (PR+approval) | Wagner delegou self-approve (ADR 0040); Claude commita direto se rotineiro |
| **Auto-mem podia ser "rascunho rápido"** | Working memory dentro da sessão atual continua OK; não persiste cross-session privada |
| **Conhecimento sensível Wagner não quer compartilhar** | 4 exceções listadas + Vaultwarden pra secrets |
| **Migração das 80 existentes é trabalho** | Faseada, não-bloqueante |

---

## Consequências

### Positivas

- **Felipe/Maíra/Luiz/Eliana** entram com knowledge igual ao do Wagner — ramp-up zero
- **Claude futuro** (qualquer modelo) usa MCP `decisions-search` em vez de depender de auto-mem que pode mudar entre sessões
- **Audit defensável**: cada conhecimento tem git history + autor + data + PR review
- **Backup natural**: GitHub é source-of-truth, sem risco de perder
- **Governança**: ADR é o protocolo formal, auto-mem era ad-hoc
- **Single source of truth**: zero drift entre o que Wagner sabe e time sabe

### Negativas / aceitas

- **Wagner perde** controle ad-hoc rápido — agora cada nota vira PR
- **Migração das 80** consome tempo (~5 sessões iterativas)
- **Skill enforcement** precisa hook + lembrar
- **Casos limite** (preferência ultra-pessoal Wagner) ficam excepcionais

---

## Pegadinhas operacionais

- **MEMORY.md no auto-mem dir**: arquivo índice. Pode ficar como pointer pra `memory/` git. Não duplica conteúdo — só links.
- **Working memory durante sessão**: OK escrever auto-mem temporária se Wagner pedir explicitamente (ex: "guarde isso só pra esta tarefa") — descartar ao fim.
- **Skills auto-ativáveis** continuam em `.claude/skills/` (versionadas no git, não em auto-mem).
- **Hooks** em `.claude/hooks/` idem (versionados).

---

## Métricas de sucesso (revisitar 30/60/90 dias)

| Métrica | Alvo 30d | Alvo 90d |
|---|---|---|
| Auto-mems criadas por Claude | ≤ 5 | 0 |
| Auto-mems migradas pra git | 30 | 80 (todas) |
| Felipe consegue retomar sessão sem ajuda Wagner | sim | sim |
| `decisions-search` retorna conhecimento que era auto-mem | 30 hits | 80+ hits |
| Drift git ↔ auto-mem | 0 | 0 |

Se Claude criar auto-mem violando essa regra → Wagner cobra com `cc-search query:"feedback_"` que mostra timestamps recentes, e exige migração imediata.

---

## Implementação imediata (esta sessão)

1. **Esta ADR commitada** ✅
2. **Migrar `reference_ssh_hardening_ct100_2026_04_30.md`** → `memory/requisitos/Infra/RUNBOOK-ssh-hardening-ct100.md` (commit nesta sessão)
3. **Marcar auto-mem como deprecated** apontando pro runbook
4. **Atualizar `oimpresso-mcp-first` skill** com regra "nunca criar auto-mem"
5. **Atualizar CLAUDE.md §6** com regra
6. **MEMORY.md** indexando essa ADR no topo

---

## Refs externas

- [ADR 0055 — Self-host Anthropic Team plan](0055-self-host-team-plan-equivalente-anthropic.md)
- [ADR 0059 — Governança Anthropic Team plan adaptado](0059-governanca-memoria-estilo-anthropic-team.md)

---

**Última atualização:** 2026-04-30
