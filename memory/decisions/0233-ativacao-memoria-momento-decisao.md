---
slug: 0233-ativacao-memoria-momento-decisao
number: 233
title: "Ativação de memória no momento-decisão — ciclo de vida 8 etapas + convenção gatilho/evento + hooks comportamentais"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-29"
proposed_at: "2026-05-29"
module: governance
quarter: 2026-Q2
tags: [governance, memoria, ativacao, hooks, momento-decisao, gatilho, enforcement, comportamental, worktree]
supersedes: []
related:
  - 0061-conhecimento-canonico-git-mcp-zero-automem
  - 0131-tiering-memoria-canonico-local-segredo
  - 0095-skills-tiers-convencao-interna
  - 0130-handoff-append-only-mcp-first
  - 0094-constituicao-v2-7-camadas-8-principios
authors:
  - W
  - C
---

# 0233 — Ativação de memória no momento-decisão

## Contexto

Wagner (2026-05-29), depois de uma sessão onde Claude tinha **todas as regras escritas** (CLAUDE.md, `proibicoes.md`, `PROTOCOLO-WAGNER-SEMPRE.md`) e mesmo assim **violou várias**: pulou `brief-fetch`, trocou a branch do checkout que serve o `oimpresso.test` (violou R8) em vez de usar worktree, devolveu menu de decisão técnica em vez de recomendar, e chutou a causa de um HTTP 500 duas vezes sem ler o log.

Pergunta do Wagner: *"explique melhor como isso vai funcionar... liste como deveria ser, verifique como está hoje, avalie cada etapa, planeje como deveria ser."* Queixa recorrente (catalogada em R12, mesmo dia): *"mas não está funcionando porque? se existe mas não funciona tá errado. qual momento tem que ser ativado?"*

**O problema não é falta de memória — é a memória não DISPARAR no momento da decisão.** Conhecimento canônico que carrega no `SessionStart` (banner Tier A) **decai em sessão longa** (200+ turnos / 8h+) e sai do contexto do Claude.

## Diagnóstico — ciclo de vida de 8 etapas (conhecimento → comportamento)

Pra um aprendizado virar comportamento garantido, ele precisa atravessar 8 etapas. Verificado contra o encanamento real (`.claude/settings.json` + docs canon):

| # | Etapa | Como está hoje | Nota |
|---|---|---|---|
| 1 | Capturar | Manual (Wagner fala → às vezes vira `feedback-*.md`) | 🟡 |
| 2 | Armazenar | Forte: `memory/reference/*.md`, `decisions/*.md` append-only, `governance-gate.yml` | 🟢 |
| 3 | **Classificar gatilho** | Parcial: matchers por **tipo de tool** (`Write/Edit/Bash/Read`); momentos **comportamentais** não mapeados | 🟠 |
| 4 | Distribuir | Forte: webhook GitHub→MCP; hooks/skills no git | 🟢 |
| 5 | **Ativar no momento** | DIVIDIDO: ops arquivo/bash = `PreToolUse` por-tool (forte); comportamental = `tier-a-banner.ps1` SessionStart (**decai**) | 🔴 |
| 6 | **Forçar** | DIVIDIDO: `block-automem`/`block-mwart` bloqueiam (exit 2); comportamental = banner mole | 🔴 |
| 7 | **Verificar** | Parcial: `block-claim-without-evidence` exige curl; aderência comportamental não auditada | 🟠 |
| 8 | Evoluir | Forte: ADR supersedes, `ui-lint` ratchet, post-mortem | 🟢 |

**Conclusão:** o projeto é forte em armazenar/distribuir/evoluir (2,4,8) e em ativar/forçar **operações de arquivo/bash** (5,6 para Write/Edit/Bash). O furo é estreito e específico: **ativação + enforcement do conhecimento COMPORTAMENTAL** (etapas 3,5,6,7). As 4 violações desta sessão caem todas nessas mesmas etapas, só no eixo comportamental — não é azar, é furo estrutural.

## Decisão

### D1 — Convenção `gatilho:`/`evento:` em todo conhecimento comportamental (fecha etapa 3)

Todo `feedback-*.md` comportamental novo **nasce com frontmatter declarando o momento de ativação**:

```yaml
gatilho: "Claude vai devolver menu de decisão técnica sem recomendar"
evento: Stop            # SessionStart | PreToolUse:<matcher> | PostToolUse | Stop | UserPromptSubmit
hook: nudge-recommend-not-menu.ps1   # arquivo que ativa (ou "n/a — só protocolo")
enforcement: advisory   # block (exit 2) | advisory (nudge) | banner (passivo)
```

Sem `gatilho:` mapeado, não há como ativar — vira "passivo que não dispara". Isso é regra de governança: feedback sem gatilho é incompleto.

### D2 — Modelo de 3 camadas de ativação (canoniza o que a R12 já provou)

A R12 (fechamento de sessão) descobriu empiricamente (2026-05-28) que regra comportamental precisa de **3 camadas em depth**. Generalizamos:

| Camada | Mecanismo | Garantia |
|---|---|---|
| 1 | Skill Tier A (`wagner-protocol-enforce`) | SessionStart eager — **decai em sessão longa** |
| 2 | Skill Tier B description-match lazy | recarrega inline no trigger word |
| 3 | **Hook de momento-decisão** (`PreToolUse`/`Stop`/`UserPromptSubmit`) | **determinístico no instante** |

Camada 3 é a que faltava pro comportamental. Regra-mestre: **comportamento crítico = camada 1 (baseline) + camada 3 (garantia).**

### D3 — 3 hooks de momento-decisão (fecha etapas 5,6,7 das violações desta sessão)

| Hook | Evento | Fecha | Enforcement |
|---|---|---|---|
| `block-serving-branch-switch.ps1` | `PreToolUse:Bash` | Troca de branch no checkout servido (dá dentes à **R8** existente, que não tinha hook) | **block** (exit 2) + escape `serving-branch-override` |
| `nudge-recommend-not-menu.ps1` | `Stop` | Menu de decisão técnica sem recomendação (**R13** nova) | advisory |
| `nudge-diagnosis-without-evidence.ps1` | `Stop` | Diagnóstico afirmado sem evidência (grep/log) — estende R1 | advisory |

### D4 — R13 nova no PROTOCOLO

**R13 — Recomendar decisão técnica, não devolver menu.** Cálculo de prioridade/ROI/sequenciamento é trabalho do Claude (recomenda com razão; Wagner valida). Menu só pra **preferência/gosto** do Wagner (ex: "roxo ou azul?"). Origem: Wagner 2026-05-29 *"eu não deveria decidir isso, eu vou errar a escolha. qual escolha é melhor pro meu caso?"*.

## Consequências

**Positivas:**
- Comportamental ganha enforcement no momento-decisão, não só banner que decai.
- R8 ganha hook (a violação central desta sessão não se repete silenciosamente).
- Convenção `gatilho:` força todo feedback futuro a declarar COMO será ativado — fecha a etapa 3 na raiz.
- Padrão replicável: próximo feedback comportamental = doc + gatilho + hook.

**Negativas / limites (honestidade):**
- Hooks `Stop` comportamentais são fuzzy (regex erra em casos sutis) — por isso são **advisory** (nudge, não block), fail-open (erro do hook → exit 0, nunca trava sessão).
- Não é 100%: leva de "passivo que não dispara" pra "dispara na maioria das vezes no momento certo". O salto que importa.
- Mais hooks = +latência marginal por tool call (mitigado: hooks rápidos, fail-open).

## Alternativas consideradas

- **Só escrever mais memória (doc/PROTOCOLO):** rejeitado — é exatamente o que já falhava (passivo, decai).
- **Tudo via skill Tier A always-on:** rejeitado — decai em sessão longa (causa-raiz da R12).
- **Hooks block (exit 2) para comportamental:** rejeitado pro fuzzy — false-positive bloquearia trabalho legítimo do time. Block só pro determinístico (git-guard).
