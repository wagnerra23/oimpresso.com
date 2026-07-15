---
date: "2026-07-15"
time: "18:21 BRT"
slug: protocolo-design-v2-hook-ativacao
tldr: "PROTOCOL.md ganhou §0.1 (Code É o designer-agente v2 com acesso ao design) + hook UserPromptSubmit de ativação no momento + reconciliação v1→v2 de memórias. Origem: incidente wizard cartão Financeiro onde a IA tratou design como dependência externa."
prs: [4320, 4322]
decided_by: [W]
related_adrs: [0282-protocolo-v2-colapso-ratificacao, 0241-loop-design-cowork-code-autonomo-zero-humano, 0315-design-sync-claude-design-vs-cowork-charter, 0299-figma-nao-e-fonte-de-design, 0225-recalibracao-4-8-skills-auto-trigger]
next_steps:
  - "Reabrir sessão em base fresca pra o hook design-agente-ativa.mjs entrar no contexto (esta sessão rodou stale -5254)."
  - "Se Wagner quiser campos de cartão no wizard SheetNovaCobranca: agora GERO o design (não bloqueio), com regra-mestre de valor pra parcelamento."
---

## Estado MCP no momento do fechamento

- Tools MCP (`cycles-active`/`my-work`/`sessions-recent`/`decisions-search`) **não estavam expostas** nesta sessão — não inventei snapshot (mesma condição do handoff 2026-07-13).
- GitHub confirmado: PRs #4320 e #4322 `MERGED` em `origin/main` (sha `750d37a5dc` e `c60ae21d72`). Branch órfã `claude/protocolo-acesso-design-v2` deletada.

## O que aconteceu

Wagner pediu "aplicar o financeiro, o que falta no protótipo pra descer?". Apurei (via `origin/main` fresco, base local −5254): o protótipo Financeiro **já desceu inteiro** (21 telas + todos os primitivos). Ao falar de campos de cartão no wizard, **errei**: disse *"precisa vir do Cowork / me autorize a desenhar"* — tratei design como dependência externa.

Wagner corrigiu: *"você tem acesso completo ao design, atualize o protocolo, investigue"*. Causa-raiz: o **corpo do PROTOCOL §1 (v1: 'Cowork gera / Code traduz')** vive no topo e venceu a leitura, contradizendo a v2 (§0 tabela + §10.6 DesignSync).

Depois Wagner cutucou duas vezes o mecanismo: "por que não foi ativado?" e "deveria ser um hook?". Ambas certeiras — meu 1º fix (CLAUDE.md + banner) era o "doc advisory que o agente prova não ler" (ADR 0315). A ativação real é um **hook UserPromptSubmit** (padrão do `design-compare-protocol.mjs`, nascido da mesma frase do Wagner em 07-07).

## Artefatos gerados (todos em `origin/main`)

- `prototipo-ui/PROTOCOL.md` §0.1 + tabela §0 + anti-padrão §8 (#4320)
- `.claude/hooks/design-agente-ativa.mjs` (65 linhas) — hook UserPromptSubmit cross-platform, registrado em `.claude/settings.json` (#4322)
- `CLAUDE.md` — linha "Acesso ao design v2" na seção Tier 0; corrige que **DesignSync não é acesso completo** (leitura livre, escrita gated — ADR 0315) (#4322)
- 3 memórias reconciliadas v1→v2 (append-safe): `03-skills-audit.md`, `PLAN-MWART-metas.md`, `Auditoria/BRIEFING.md` (#4322)

## Persistência

- Git: #4320 + #4322 merged. Webhook GitHub→MCP propaga o handoff/session em ~2min após este PR.
- NÃO removi ADR nem handoff/session (append-only Tier 0) — reconciliei em vez de deletar.

## Próximos passos pra retomar

`gh pr view 4322` + reabrir sessão em base fresca (o hook só ativa no contexto novo).

## Lições catalogadas

1. **Ignorei o sinal "[new branch]"** no 2º push (= PR foi mergeado cedo) — lição da minha própria auto-mem. O #4320 mergeou só o 1º commit; salvei o resto via #4322 (cherry-pick sobre main fresco, evitando reverter 2 arquivos de Produto que entraram no meio).
2. **Doc advisory ≠ ativação** (ADR 0315): consertar um doc não garante que dispare no momento. Erro-de-modelo-mental (afirmação no chat) só se mitiga com hook UserPromptSubmit advisory-no-momento — não há evento/critério pra block (ADR 0224).
3. **Introduzi imprecisão ao consertar**: superdimensionei DesignSync como "acesso completo" — corrigido no mesmo PR.

## Pointers detalhados

- PROTOCOL §0.1 + §10.6 (DesignSync) · ADR 0282 (v2) · ADR 0315 (design-sync não-fonte) · hook `design-compare-protocol.mjs` (padrão espelhado).
