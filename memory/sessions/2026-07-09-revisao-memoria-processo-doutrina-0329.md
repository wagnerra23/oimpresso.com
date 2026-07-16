# Sessão 2026-07-09 — Teste do protocolo ponta-a-ponta → revisão da memória do processo → doutrina 0329 → estabilização

**Sessão:** `wonderful-moser-71ba82`. **Off-cycle.** **Base:** todo trabalho a partir de `origin/main` fresco (a worktree nasceu −4947 stale; o guard de SessionStart avisou e foi respeitado).

> Handoff pareado: [`2026-07-09-1520-revisao-memoria-processo-estabilizacao.md`](../handoffs/2026-07-09-1520-revisao-memoria-processo-estabilizacao.md) (o estado pro próximo). Este log conta o trabalho.

## O arco

Pedido inicial [W]: *"como posso testar todo protocolo de aplicação do protótipo?"* — evoluiu, ao longo do dia, para a pergunta estrutural: *"por que sempre fica algo pendente?"*. A resposta atravessou 4 fases:

### 1. Testar o protocolo de verdade (não selftest-teatro)

- **Trilha A:** 16/16 selftests verdes em checkout limpo (fases −1..5 + gate-selftest required + guards).
- **Trilha B (dados vivos):** Onda 1 = pull direto DesignSync (ADR 0325) — `vendas-page.jsx` 92KB do projeto Cowork `019dcfd3` sem browser/ZIP; Onda 2 = `detectar-telas` sobre o staging real: 51 sources, **0 órfãos**, as 2 telas de venda resolvidas separadas (o bug histórico não regrediu); Onda 3 (parte node) = `ancora Sells/Index --staging` ✓; Onda 3 (browser) = fingerprint injetado no `/financeiro/unificado` vivo — 258 elementos medidos, **primary `oklch(0.55 0.15 295)` = token do DS baixado na Onda 1** (ciclo fechado).
- 3 furos achados e fechados: **#3999** (selftest órfão do style-fingerprint → CI), **#4001** (`protocolo.config.mjs` — IDs/staging/mapa-FASES viravam fonte única executável com `--selftest` que morde, provado por mutação), **#4002** (`visual-comparison-staleness` — 14 comparativos stale 51-53d invisíveis).

### 2. Revisão profunda da memória do processo (workflow adversarial, 57 agentes)

7 leitores (método/lições/proibições/skills/mecanismo/ADRs/erros-da-sessão) → 13 categorias → 36 propostas → **cada uma atacada por um cético** (presence-gate? duplica? viola 0314? já descartado §5?) → 26 sobrevivem, 10 rejeitadas. **Meta-padrão refinado:** não é "falta conhecimento" — é *enforcement desacoplado do merge*: advisory por política, opt-in (`--compare` nunca invocado no PR), proposto-nunca-construído, Windows-only (43/60 hooks `.ps1`), construído-desarmado. **Correção-do-mecanismo ≠ invocação.**

- **Passo 1 "parar de mentir" (#4003):** verificação item-a-item pegou **3 falsos-positivos do próprio relatório** (ragas-gate existe; "46" não existe; mwart-gate está em ADR aceita → tombstone). 2 erros reais corrigidos: pageheader-canon C4 aprovava o hue-de-grupo morto (145→295, ADR 0190) + TODO obsoleto.
- **5 chips paralelos** (worktrees isoladas, ressalvas do adversário embutidas no prompt): P26 hook BRL **#4004** · P34 IT2/§14 **#4005** · P16 ghost-ref **#4006** · P25 SafeSelectItem **#4007** (4 mergeados no dia) · P16-cura **#4009** e P10 ref-integrity **#4010** (abertos).

### 3. A lei — ADR 0329 (a doutrina), no formato que o MCP indexa

[W] pediu "faça a lei" e depois barrou o formato errado: *"estude melhor o assunto, vai ficar tudo fora do padrão do MCP as buscas"*. Workflow de 4 leitores (schema, **indexador MCP**, gates, lifecycle) provou: o sync faz `glob('memory/decisions/*.md')` **não-recursivo** → `proposals/` é invisível a `decisions-search` (prova viva: 0320 `aceito` presa lá); `proposal_id` é deprecado. A doutrina nasceu **top-level numerada** (`0329`, `status: proposto`, `kind: meta`) com `adr-index --write` no mesmo PR — **#4008 MERGED**, 71 checks verdes.

Conteúdo: 5 propriedades (executável · fonte-única · ligada-ao-gate · cross-plataforma · auto-fresca) + porquê em ADR append-only + teste ácido + camadas 0-4.

### 4. Estabilização em ondas ([W]: "sempre fica algo pendente")

- **Onda 0 inventário:** `my-work` = 20 tasks, **zero desta sessão** → o furo era o fechamento do protocolo CLAUDE.md (tasks MCP + handoff + session log) que a sessão não executava.
- **Onda 1 bateria:** 22/22 verde no main pós-9-merges. 1 falso-alarme diagnosticado (`node --test` dir-mode: Node 24 local × Node 20 CI; 24/24 testes individuais OK; CI real `success`).
- **Onda 2:** zero vermelho real; worktrees de teste limpos (sem tocar os dos chips); 5/5 chips com PR.
- **Onda 3 registro:** **US-GOV-049..052** criadas via `tasks-create` (ratificar 0329 · ratificar 0314/0299+mover 0320 · merge #4009/#4010 · backlog M/G) + este session log + handoff 15:20 + blocos no SPEC Governance.
- **Onda 4 trava:** sentinela **`adr-proposto-parado.mjs` (#4011)** — A: decidido preso em `proposals/` (7 reais!) · B: numerado preso (3) · C: proposto >14d (30; feature-wish excluído por ADR 0105). Selftest 11 casos; o caso 0125 (comentário YAML inline) virou fixture após pegar bug real no desenvolvimento. Eixo 3 do workflow de staleness.

## Lições (candidatas a canon)

1. **Pendência sem máquina que a surfe não é pendência — é esquecimento agendado.** ([W]: "isso vai acabar não sendo feito"). Conserto: registrar no sistema que o dono lê (tasks MCP) + sentinela que reporta até resolver.
2. **Nenhum achado de auditoria é lei até verificado contra o repo vivo** — nem de auditoria adversarial (3/7 falsos-positivos).
3. **A camada onde o conhecimento mora determina se ele sobrevive** — `proposals/` vs top-level mudou "lei aceita" de invisível pra encontrável.
4. Toolchain Windows rendeu 3 falso-alarmes (MSYS colon-mangling, worktree órfã pós `--delete-branch`, Node24 dir-mode) — diagnosticar antes de "consertar" evitou 3 consertos-fantasma.
