---
slug: 0131-tiering-memoria-canonico-local-segredo
number: 131
title: "Tiering de memória — canônico (git/MCP) / máquina-local / segredo (Vaultwarden)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-10"
module: Governance
tags: [governanca, memoria, vaultwarden, auto-mem, seguranca, multi-dev, lgpd]
supersedes: []
supersedes_partially: []
amends: [0061]
superseded_by: []
related: [0040-policy-publicacao-claude-supervisiona, 0061-conhecimento-canonico-git-mcp-zero-automem, 0094-constituicao-v2-7-camadas-8-principios, 0119-paralelismo-sessoes-whats-active-tier-1, 0130-handoff-append-only-mcp-first]
pii: false
review_triggers:
  - "≥1 incidente de segredo vazado em git em 90d → endurecer hook + adicionar gitleaks pre-push obrigatório"
  - "≥1 incidente de auto-mem privada renascer em ~/.claude/projects/*/memory/ em 30d → endurecer hook block-automem (negar TODA escrita lá, sem exceção)"
  - "Dev novo entrar no time (Eliana ainda não DPO, ou contratação) → confirmar skill oimpresso-team-onboarding cobre criação de ~/.claude/oimpresso-local/"
  - "OneDrive/Dropbox do Wagner falhar → reavaliar recomendação de backup de oimpresso-local/ (sugerir alternativa: rclone pra B2/R2, ou simples zip mensal)"
  - "Surgir caso de uso que não cabe em nenhum dos 3 tiers → criar 4º tier explícito (não inflar 0131; criar 0NNN nova com amends)"
---

# ADR 0131 — Tiering de memória (canônico / máquina-local / segredo)

## Contexto

[ADR 0061](0061-conhecimento-canonico-git-mcp-zero-automem.md) (2026-04-30) decidiu **ZERO auto-mem privada** em `~/.claude/projects/*/memory/`. Motivo legítimo: auto-mem isolada criava silo (Eliana/Felipe não enxergavam o que estava lá), conhecimento canônico era perdido em re-/clear, e a IA tinha 2 fontes contradizendo (git vs auto-mem).

Hook `block-automem.ps1` foi escrito (2026-04-30) bloqueando Write/Edit em `~/.claude/projects/*/memory/*.md`.

### Sintoma observado (2026-05-10)

Wagner formulou (2026-05-10):
> "estou confuso sobre isso, como posso proteger os dados e fazer funcionar"
> "tem que achar uma alternativa, lista de tarefas e maquina local configurações da maquina"

O problema real: ADR 0061 jogou **3 tipos de informação distintos** na mesma cesta proibida:

1. **Conhecimento canônico** (ADR, decisão arquitetural, feedback institucional) — esse SIM precisa ir pro git/MCP, time inteiro precisa ver
2. **Configuração de máquina pessoal** (path `D:\oimpresso.com`, monitor 1280px, atalhos Cursor, TODO Wagner pessoal não-MCP) — esse NÃO faz sentido no git: poluição visual pro time + atrito de revisão de PR pra config local
3. **Segredo** (token MCP, ADMIN_TOKEN Vaultwarden, IPs LAN, certs `.pfx`, senhas Firebird) — esse NUNCA pode ir nem pro git (vazamento público) nem pra auto-mem (sem criptografia)

ADR 0061 absolutista força (1) corretamente, mas deixa (2) e (3) **sem lugar legítimo**. Resultado prático: ou Claude tenta empurrar config-de-máquina pro git (atrito), ou Wagner aceita auto-mem na surdina (viola 0061), ou conhecimento útil simplesmente some.

### Inventário atual (2026-05-10)

Auto-mem em `C:\Users\wagne\.claude\projects\D--oimpresso-com\memory\` tem ~100 entries (índice `MEMORY.md`). Amostragem:

- `reference_hostinger.md` (SSH creds + IP + path) — mistura **segredo** + **canônico**
- `reference_local_dev_setup.md` (Herd + paths Windows) — **máquina-local**
- `reference_vaultwarden_credenciais.md` (ADMIN_TOKEN referência) — **segredo** (refs a Vaultwarden)
- `feedback_check_main_antes_de_pr.md` — **canônico** (regra de trabalho que vale pro time)
- `user_profile.md` (Wagner stack) — **canônico** parcial + **máquina-local** parcial
- `cliente_rotalivre.md` (biz=4 quirks) — **canônico** (ADR 0066 já cobre + cliente_rotalivre devia ir pra `memory/requisitos/Vestuario/`)

Ou seja: a auto-mem velha é uma sopa de 3 tiers misturados que ADR 0061 proibiu mas não migrou.

## Decisão

### 3 lugares físicos, 3 funções distintas

| Tier | Onde mora | O que vai | Quem enxerga | Como protegido |
|---|---|---|---|---|
| **1. Canônico** | `memory/` no git → webhook → MCP server | ADRs, decisões, feedback time, regras, runbooks, SPECs | Time inteiro via tools MCP | Branch protection + PR review + gitleaks + pre-commit |
| **2. Máquina-local** | `~/.claude/oimpresso-local/` (FORA do worktree) | path Windows, monitor, TODO pessoal, atalhos IDE, refs pro Vaultwarden, config Herd | Só o próprio dev | Fora do git (nunca commitado) + recomendação backup OneDrive/Dropbox |
| **3. Segredo** | Vaultwarden (`vault.oimpresso.com`) | tokens, senhas, certs `.pfx`, IPs LAN privados, ADMIN_TOKEN | Quem tem permissão Vaultwarden (Wagner + Eliana[E]) | Criptografia E2E + ADMIN_TOKEN + 2FA |

### Critério de classificação (1 pergunta só)

```
Este fato é:

  ┌─ SEGREDO (token, senha, cert, IP privado, cred operacional)
  │   └─→ Vaultwarden. Auto-mem máquina-local guarda só PONTEIRO
  │       ("ADMIN_TOKEN está em Vaultwarden item 'mcp2'")
  │
  ├─ SÓ MEU (path da minha máquina, meu monitor, meu workflow,
  │   minha lista TODO não-MCP, minha config Cursor/Herd)
  │   └─→ ~/.claude/oimpresso-local/
  │
  └─ PRECISA SER VISTO PELO TIME (ADR, regra de domínio,
      feedback institucional, runbook reproduzível, decisão)
      └─→ memory/ no git
```

Quando ambíguo: **default é git canônico** (princípio 7 Constituição v2 — transparência). Local só quando claramente pessoal/máquina.

### Estrutura sugerida de `~/.claude/oimpresso-local/`

```
~/.claude/oimpresso-local/
├── README.md                  ← 5 linhas explicando o que mora aqui
├── tasks-pessoais.md          ← TODO Wagner pessoal (não-MCP)
├── config-maquina.md          ← paths D:\, monitor, IDE setup
├── vault-refs.md              ← ponteiros pro Vaultwarden (sem segredos)
└── workflow-tips.md           ← atalhos, dicas só pra mim
```

Cada dev cria os arquivos que precisar. Não há schema obrigatório — é zona pessoal.

### Hook `block-automem.ps1` ajustado

Hook continua existindo, mas regra muda:

- ❌ **Bloqueia** Write/Edit em `~/.claude/projects/*/memory/*.md` (path da auto-mem velha — força migração)
- ❌ **Bloqueia** Write/Edit em `AppData/Local/.claude*/memory/`, `AppData/Roaming/.claude*/memory/`
- ✅ **Permite** Write/Edit em `~/.claude/oimpresso-local/**` (escape valve explícito)
- ✅ **Permite** Write/Edit em `memory/` do worktree git (canônico)

Mensagem do bloqueio passa a citar **os 3 tiers** + critério de classificação acima, não só "ADR 0061 proíbe".

### Smoke test (`.claude/hooks/block-automem.test.ps1`)

3 casos pwsh validando o hook (rodar manual; CI pode chamar futuro):

1. Write em `~/.claude/projects/D--oimpresso-com/memory/foo.md` → **deny** esperado
2. Write em `~/.claude/oimpresso-local/tasks-pessoais.md` → **allow** esperado
3. Write em `memory/decisions/0XXX-foo.md` (canônico git) → **allow** esperado

### Migração da auto-mem velha (~100 entries)

**NÃO neste PR.** Trabalho separado pela skill `automem-classify` (reescrita da `automem-pending` atual) — Tier B, batch supervisionado, Wagner aprova 1 batch (~10 entries) por vez, classifica cada uma em Vaultwarden / oimpresso-local / git, aplica. Estimativa: 6-8 batches × ~30min = ~4h.

Enquanto migração não acontecer:
- Auto-mem velha continua acessível via `Read` (não bloqueada — só Write bloqueado)
- Hook `block-automem` mostra mensagem "este path é legado — migrar via skill `automem-classify`"
- MEMORY.md auto-mem continua sendo lido pela harness pra preservar contexto até migração completa

### Backup do `~/.claude/oimpresso-local/`

**Recomendação (não obrigação):** symlink ou pasta dentro de OneDrive/Dropbox/iCloud que o dev já use.

Wagner: `~/OneDrive/Claude/oimpresso-local/` + symlink em `~/.claude/oimpresso-local/`.

Sem backup, se a máquina morre → perde TODO + config + refs Vaultwarden. Conteúdo é pessoal, mas reconstruir custa horas.

### Onboarding (dev novo)

Skill `oimpresso-team-onboarding` ganha **passo 10** após setup MCP: orientar o dev a criar `~/.claude/oimpresso-local/` com README mínimo. Tier 2 fica disponível desde o dia 1.

## Não-decidido (fora de escopo)

- **Tool MCP `secrets-fetch <slug>`** — proxy autenticado pra Vaultwarden retornar segredo sob demanda. Tentador (zero copy-paste) mas eleva superfície de ataque. Avaliar quando US específica aparecer.
- **Sincronizar `oimpresso-local/` cross-device do mesmo dev** (Wagner notebook + Wagner desktop) — não-trivial (merge?), fica responsabilidade do dev via OneDrive/Dropbox.
- **Auto-mem da harness Claude Code** (`~/.claude/projects/*/memory/MEMORY.md`) — continua sendo lido pela harness em SessionStart (não dá pra desligar sem patchar harness). Decisão: aceitar, migrar conteúdo via skill `automem-classify`, e depois reduzir MEMORY.md auto-mem a apenas ponteiros pros 3 tiers reais (1 linha por entry).

## Consequências

### Positivas

- **Cada tipo de info tem 1 lugar legítimo** — fim da confusão "onde colocar"
- **Segurança aumenta** — segredos saem do auto-mem (não criptografado) e vão pro Vaultwarden (E2E)
- **Time não vê poluição pessoal** — config de máquina do Wagner não aparece em PR review do Felipe
- **0061 fica refinada, não invalidada** — espírito (zero silo de canônico) preservado, escape valves explícitas adicionadas
- **LGPD reforçado** — credenciais cliente nunca mais em auto-mem (vai Vaultwarden); paths/IPs internos (PI técnica) ficam fora do git

### Negativas

- **Migração da auto-mem velha (~100 entries)** — esforço real (~4h supervisionadas). Mitigado por automação batch via `automem-classify` (próximo PR).
- **Dev precisa entender 3 tiers** — onboarding ganha 1 passo, mas critério é 1 pergunta só ("segredo? só meu? time?"). Cognitivamente simples.
- **Backup é responsabilidade do dev** — `oimpresso-local/` fora do git significa fora do version control também. Recomendação OneDrive resolve, mas dev tem que configurar.

### Neutras

- **Amends [ADR 0061](0061-conhecimento-canonico-git-mcp-zero-automem.md)** sem supersedir — 0061 continua válida no espírito (zero auto-mem privada de conhecimento canônico). Esta ADR explicita as 2 escape valves legítimas (oimpresso-local + Vaultwarden) que 0061 deixou implícitas.

## Plano de implementação (1 PR — neste worktree)

1. ADR 0131 (este arquivo) — registro da decisão
2. `~/.claude/oimpresso-local/README.md` — 5 linhas explicativas (criar a pasta)
3. `.claude/hooks/block-automem.ps1` — ajustar pra permitir `~/.claude/oimpresso-local/` + atualizar mensagem com 3 tiers
4. `.claude/hooks/block-automem.test.ps1` — smoke test 3 casos
5. `.claude/skills/oimpresso-team-onboarding/SKILL.md` — adicionar passo 10 (criar oimpresso-local pro dev novo)
6. (sem mexer em automem-classify ainda — fica próximo PR)

Total estimado: ~250 linhas adicionadas + 30 linhas modificadas no hook.

## Referências

- [ADR 0040](0040-policy-publicacao-claude-supervisiona.md) Policy publicação — "PII reais NUNCA em PR" tem agora 1 destino legítimo (Vaultwarden), não só proibição
- [ADR 0061](0061-conhecimento-canonico-git-mcp-zero-automem.md) Zero auto-mem privada — esta ADR refina sem invalidar
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) Constituição v2 — princípio 1 (Context as a product) + princípio 7 (transparência) + princípio 8 (confiabilidade com fallback) fundamentam tiering explícito
- [ADR 0119](0119-paralelismo-sessoes-whats-active-tier-1.md) Paralelismo sessões — multi-dev cenário onde o problema de auto-mem-isolada apareceu primeiro
- [ADR 0130](0130-handoff-append-only-mcp-first.md) Handoff append-only — irmã desta ADR (mesma sessão de governança 2026-05-10); ambas tratam de "onde mora memória institucional"
- `reference_vaultwarden_credenciais.md` (auto-mem velha) — Vaultwarden em `vault.oimpresso.com` (LAN `:8200`); ADMIN_TOKEN salvo lá
