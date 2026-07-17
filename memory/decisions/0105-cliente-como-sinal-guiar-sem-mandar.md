---
slug: 0105-cliente-como-sinal-guiar-sem-mandar
number: 105
title: "Cliente como sinal + guiar sem mandar (3 graus de regulação)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by:
  - W
decided_at: '2026-05-08'
quarter: 2026-Q2
related:
  - 0040-policy-publicacao-claude-supervisiona
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0104-processo-mwart-canonico-unico-caminho
pii: false
---

# ADR 0105 — Cliente como sinal + guiar sem mandar

**Status:** ✅ Aceita
**Data:** 2026-05-08
**Decisão por:** Wagner Rocha
**Não supersede:** ADRs 0040 (publication policy), 0094 (Constituição V2), 0104 (processo MWART). Estende com filosofia de governança.

---

## Contexto

Wagner observou em sessão 2026-05-08, após criar ADR 0104:

> *"Cliente sabe onde dói, ele sempre reporta isso, rege o desenvolvimento a maioria das vezes. Se o sistema não tiver e o cliente paga bem, é desenvolvido. Sempre é assim o fluxo."*

> *"Como guiar sem parecer que está mandando, fazendo a pessoa tomar as decisões corretas sem parecer chato?"*

Duas observações que parecem separadas mas formam um princípio único: **o sistema regula por sinal externo (cliente paga + reporta) e por orientação suave-firme (3 graus de regulação)**, não por autoridade interna.

Sem este princípio explicitado, há risco de:
- Equipe pequena (5 devs) virar burocrática (gates demais → bypass)
- Backlog encher de "alguém na rua sugeriu" sem qualificação
- Skills/hooks/CI virarem polícia em vez de orientação
- Cliente reclamar e não virar input formal do sistema

## Decisão

Adotar 2 princípios canônicos de governança:

### Princípio 1 — Cliente como sinal qualificado

A demanda de desenvolvimento é regida por **sinal externo**, não por especulação interna. Sinal qualifica quando:

| Critério | Peso | Exemplo |
|---|---|---|
| **Cliente paga** | Pré-requisito | ROTA LIVRE biz=4 paga = sinal forte |
| **Cliente reporta** | Sinal direto | "Tela tá lenta" → ticket |
| **Métrica detecta drift** | Sinal indireto proativo | `jana:health-check` falha 3 dias seguidos |
| **Larissa pergunta no WhatsApp** | Sinal humano | Vira `client_signal` row no MCP — fluxo formalizado em US-INFRA-002 |

Backlog **só recebe item se cumpre 1 desses 4 critérios**. Hipótese ("acho que seria legal") sem sinal **não entra** — vira anotação em ADR de feature wish, não US ativa.

**Atenção crítica:** cliente sabe **onde** dói, raramente sabe **por quê**. Causa raiz exige observabilidade (OTEL, APM full-stack, traces). Sem isso, fix vira "trata sintoma, ignora causa" e a dor volta. Por isso **APM full-stack é P1** (US-INFRA-003).

### Princípio 2 — Guiar sem mandar (3 graus de regulação suave-firme)

Em vez de polícia ("nada pode") ou anarquia ("tudo pode"), regular em 3 graus crescentes. Cada grau:
1. Tem mensagem **clara em PT-BR explicando o por quê**
2. Oferece **caminho de saída** (comando pra resolver, override autorizado)
3. Aumenta consequência **só se o anterior não bastou**

| Grau | Mecanismo | Quando dispara | Mensagem ao dev |
|---|---|---|---|
| **1. Orientar** | Skill Tier A always-on | Sempre (custo trivial) | "Lembra que MWART tem 5 fases — começa pela F1." |
| **2. Ensinar** | Hook PreToolUse trava em ato | Dev pula etapa | "Não posso editar `Pages/Sells/Create.tsx` porque o RUNBOOK ainda não existe. Roda `/cockpit-runbook /sells/create` primeiro." |
| **3. Bloquear** | CI gate no merge + override autorizado `/mwart-override <razão>` | Última linha | "PR bloqueado — falta audit modo B ≥70. Score atual: 58. Use `/mwart-override` se for exceção justificável (vira ADR per-tela)." |

**Princípios derivados:**

- **Mostrar consequência** ("isso quebra ROTA LIVRE 99%") — não só dizer "não pode"
- **Override autorizado não é fraqueza** — é respeito ao dev. Diz: "confiamos que você tem razão; só registra ADR pro futuro"
- **Cliente reportando direto** fecha o loop por baixo: se sistema deixou passar bug, cliente trava o avanço — *fitness function humana*
- **Iniciante (`[L]`) e Wagner passam pelo MESMO caminho** — gates não distinguem hierarquia (só o override autorizado é restrito a Wagner)

## Consequências

### Boas

- **Equipe pequena escala.** 5 devs + 5 IAs operam sem burocracia central. Skills + hooks + CI + cliente formam "loop fechado" autossustentável.
- **Backlog enxuto.** Só sinal qualificado entra. Wagner não vira filtro humano.
- **Onboarding rápido.** Novo dev lê este ADR + ADR 0104 + 1 RUNBOOK exemplo → entende como o sistema "decide" sem precisar perguntar.
- **Cultura saudável.** "Sistema avisa, dev acata, exceções viram ADR" — em vez de "Wagner ralha, dev defende, ressentimento".

### Ruins / mitigações

- **Cliente sinal pode ser ruidoso.** Larissa diz "tela ruim" sem detalhe. **Mitigação:** `client_signal` formal exige campos mínimos (URL, sintoma, severidade). APM completa o quadro.
- **3 graus podem virar 1 grau** se time for relaxado com Camada 1 (skill). **Mitigação:** Camada 2 (hook) é trava real — funciona mesmo quando skill é ignorada.
- **Cliente que paga não é cliente certo.** ROTA LIVRE (biz=4) é 99% volume mas pode pedir feature ruim pra todos. **Mitigação:** Wagner como gate humano sobre features de impacto multi-tenant; ADR 0093 protege.
- **Override autorizado pode virar atalho.** Se Wagner usa `/mwart-override` em todo PR, gates morrem. **Mitigação:** `mwart_overrides_quarter` é métrica; alerta se >3/quarter.

## Loop fechado de governança

Combinando este ADR com 0104 (MWART), 0094 (Constituição V2), 0091 (brief), o ciclo completo é:

```
META (cycle_goal) →
  TRACKING (cycle-goals-track) →
    SINAL (client_signal + health-check + brief) →
      DETECÇÃO DE DESVIO (delta vs alvo > threshold) →
        TRIAGEM (ADS quando S5 chegar; manual hoje) →
          RECÁLCULO (Brain A barato / Brain B caro) →
            HITL Wagner →
              EXECUÇÃO (skills + hooks + CI gates) →
                MEDIÇÃO → volta pro TRACKING
```

US-INFRA-001 (GrowthBook) adiciona **canary com percentage rollout** ao loop — ADS recomenda "10% dos sells", GrowthBook executa. US-INFRA-002 (`client_signal`) formaliza a entrada do sinal.

## Alternativas consideradas

- **A — Backlog livre, Wagner filtra tudo.** Rejeitada: bottleneck. Wagner já reporta ser bottleneck (auto-mem `regras-time.md`).
- **B — Backlog Jira-style com pontuação interna.** Rejeitada: pontuação interna sem sinal externo gera priorização viesada (engenheiro acha lindo, cliente nem usa).
- **C — Polícia 100% (gates duros sem override).** Rejeitada: equipe pequena revolta. Override autorizado mantém autonomia em casos legítimos.

## Refs

- [ADR 0040 — Publication policy](0040-policy-publicacao-claude-supervisiona.md) — Wagner supervisiona ações sensíveis
- [ADR 0091 — Daily Brief](0091-daily-brief.md) — observabilidade de estado
- [ADR 0093 — Multi-tenant Tier 0](0093-multi-tenant-isolation-tier-0.md) — gate cross-tenant é hard
- [ADR 0094 — Constituição V2](0094-constituicao-v2-7-camadas-8-principios.md) — princípio "Loop fechado por métrica"
- [ADR 0104 — Processo MWART canônico](0104-processo-mwart-canonico-unico-caminho.md) — implementa os 3 graus de regulação
- [skill ads-decision-flow](../../.claude/skills/ads-decision-flow/SKILL.md) — futuro motor de triagem automática
- [skill publication-policy](../../.claude/skills/publication-policy/SKILL.md) — matriz Wagner-supervisiona

## Designer

**Decisão por Wagner** em sessão 2026-05-08. Frase exata gravada como princípio: *"Cliente sabe onde dói. Como guiar sem parecer que está mandando, fazendo a pessoa tomar as decisões corretas sem parecer chato?"*

---

**Última atualização:** 2026-05-08
