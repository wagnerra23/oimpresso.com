---
page: papel/[W]
component: governança do loop (não é tela) · meta-charter
owner: wagner
status: proposta (Cowork) — vira oficial quando no git main
last_validated: 2026-05-31
parent_module: Governança
related: [STATUS.md, prototipo-ui/PROTOCOL.md §10.4, AUTOMACAO-LOOP-AUTONOMO.md]
related_adrs: [0079, 0094, 0114, 0238, 0241]
persona: Wagner [W] — soberano do loop, time de 1 pessoa + instâncias Claude
tier: A (governança)
charter_version: 1
---

# Charter de Governança — [W] como champion

> **Status:** proposta de memória-por-papel (mesmo padrão charter-first, L-14). Captura **o que [W] decide**, **o que [W] NÃO faz**, e como medir se a governança está saudável — pra parar de virar muleta do loop a cada sessão.
> **Lei mãe:** ADR 0079 (7 camadas de governança) · ADR 0094 (Constituição Oimpresso V2) · ADR 0238 (soberania da constituição = só [W]) · ADR 0114 (loop) · ADR 0241 (loop autônomo 0-humano).
> **NÃO é lei suprema:** descreve como [W] exerce a soberania que os ADRs acima já definem. Numeração de ADR derivado = git (§10.4 · ADR 0238), [CC] não cunha número.

---

## Mission

Ser o **soberano de Tier 0** de um loop que roda sozinho. Memória manda o git; [CC] desenha; [CL] aplica; **[W] decide** — mas só o que **não tem resposta no git**. O sucesso de [W] não se mede pelo quanto ele toca, e sim pelo **quão pouco** o loop precisa dele: idealmente, aprovar 2-3 Tier 0 por semana e nada mais. A frase-guia é a do próprio [W]: *"isso não pode depender de mim"*.

---

## Goals — O que [W] FAZ (e só [W] pode)

**Decisões Tier 0 (lista curta e deliberada — PROTOCOL §2 overlay autônomo):**
- Aprovar **ADR novo** / mudança de constituição (0094 · UI-0013) — o "sim" que vira proposta em lei.
- **Multi-tenant** · **segredo/Vaultwarden** · **lógica de lint/tooling** · **decisão de produto**.
- O **subjetivo** que o git não responde: **estético · estratégico · prioridade · dinheiro** (§10.4 "regra de ouro").

**Atos soberanos exclusivos:**
- **Pôr a "regra acima"** que vence o consenso dos agentes a qualquer hora (override soberano · ADR 0238). Palavra final é sempre de [W].
- **Briefar** (início, em `COWORK_NOTES.md`) e **autorizar merge de Tier 0** (fim). São os 2 momentos do [W] no loop.
- **Transformar erro em gate:** quando algo vaza, decidir que vira **trava que falha** (ratchet), não conselho.

---

## Non-Goals — O que [W] NÃO faz (anti-muleta)

- ❌ **Virar carteiro de status** — copiar/colar HANDOFF/SYNC na mão. Se [W] está transportando estado, **faltou gate** (retorno automático §10.2), não faltou [W].
- ❌ **Responder o que o git já responde** — "ADR X existe?", "tela Y está feita?", "base está fresca?". Isso é §10.4: o agente valida **sozinho**. Cada vez que [W] responde um checável, ele **vira o ponto de erro** que o §10.4 existe pra remover.
- ❌ **Microgerenciar F1 (design [CC]) ou F3 (código [CL])** — papéis são lentes, [W] não executa dentro delas.
- ❌ **Editar a constituição no automático** — mudar ADR 0094/UI-0013/PROTOCOL = reindexar tudo; é raro, pesado e definitivo.
- ❌ **Aprovar no impulso sob pressão** — se o agente trouxe proposta com evidência citável e não-Tier-0, ele já podia ter agido sozinho (§10.4); [W] não precisa carimbar.

---

## Champion Test (como medir governança saudável)

> **Passa:** o loop roda uma semana inteira sem [W] tocar em nada **exceto aprovar 2-3 Tier 0**.
> **Falha:** [W] está respondendo perguntas que o git responde, ou transportando status na mão.

Quando falha, o conserto **nunca** é "[W] devia responder melhor / mais rápido". É sempre uma de três:
1. **Faltou gate** → erro vira ratchet automático (ESLint `ds/*`, Stylelint, health-check de charter, `git-base-freshness-guard`).
2. **Faltou canal de retorno** → §10.2 (`ds:report:write` + `SYNC_LOG` + `HANDOFF` a cada merge).
3. **Faltou regra acima** → [W] põe um ADR/override que resolve a classe inteira do problema, não o caso isolado.

---

## Anti-patterns de governança (REPROVADO — não repetir)

- ❌ **Human-in-loop como ponto de verdade** — [W] 2026-05-30/31: *"se eu responder eu posso errar? isso não pode depender de mim"*. Origem do §10.4 e do Passo 0 (base fresca).
- ❌ **Gate rodado sobre base stale** — o F0 "rotinas-design" validou contra checkout −47 vs `origin/main` → 3 achados errados, pego por sorte. Lição: todo gate começa por `git fetch` + ancorar em `origin/main`.
- ❌ **Proibição advisory que não segura** — drift azul-220→roxo vazou porque era conselho, não trava. Identidade/cor/regra só conta se o CI barra.
- ❌ **[CC] reinventar canal/cunhar número/marcar proposta como lei** — REGRA DE OURO (4 gates pré-flight, topo do STATUS). [W] cobra isso dos agentes, não supre na mão.
- ❌ **Imposição unilateral entre agentes** — quem vê melhoria **propõe ao par**; consenso de agentes nunca supera regra de [W] (proposta peer-review, [W] autorizou "formalize").

---

## Refs

- STATUS.md — REGRA DE OURO (4 gates pré-flight) + lei suprema
- prototipo-ui/PROTOCOL.md §2 (overlay autônomo) · §10.2 (retorno) · §10.4 (gate de validação + Passo 0)
- AUTOMACAO-LOOP-AUTONOMO.md — modelo 0-humano (merge `gh --admin`, gate visual = CI)
- ADR 0079 (7 camadas governança) · 0094 (Constituição V2) · 0114 (loop) · 0238 (soberania) · 0241 (loop autônomo)
