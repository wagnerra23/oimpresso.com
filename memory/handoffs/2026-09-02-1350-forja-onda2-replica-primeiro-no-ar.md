# Handoff 2026-09-02 13:50 BRT — Forja: "réplica primeiro" (ADR 0388) e o header do protótipo no ar (Ondas 1, 2, 2.1)

> Sessão `trusting-kare-641c14` · [C] sob autorização [W] *"pode merge e compare em produção"*.
> Session log: [2026-09-02-forja-paridade-medida-espelho.md](../sessions/2026-09-02-forja-paridade-medida-espelho.md) (manhã + tarde).

## Estado em uma frase

O header/topnav do Cockpit da Forja em produção **é o do protótipo, medido** (6 destinos · 3 pílulas · na linha do título · 88,4px · botão 25px · `--accent` dark 0,70) — deploy `a91ce0cd5c`. A política "réplica primeiro" virou lei ([ADR 0388](../decisions/0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias.md)) com máquina (`scripts/governance/replica-inconsistencias.mjs` → `memory/requisitos/Forja/INCONSISTENCIAS-replica.md`, 101 itens).

## O que está no ar (main)

- [#6547](https://github.com/wagnerra23/oimpresso.com/pull/6547) ADR 0388 + reporter + `ds-guard --report`.
- [#6553](https://github.com/wagnerra23/oimpresso.com/pull/6553) Onda 2 (shell, 6 rotas, `/forja/integrador` novo) · deploy `e1412acef3`.
- [#6563](https://github.com/wagnerra23/oimpresso.com/pull/6563) Onda 2.1 (3 DIVERGE medidos em prod) · deploy `a91ce0cd5c`.
- [#6565](https://github.com/wagnerra23/oimpresso.com/pull/6565) recibo em docs — **aberto** ao fechar este handoff (só docs; merge quando CI acabar).

## Próximo passo (Onda 3 · PARIDADE §11)

Aprovações = view `hoje` do `forja-page.jsx` (número-herói + faixa "Ao vivo" + mesa), compare 0-bug. Antes de Edit em `.tsx`: `node prototipo-ui/ancora.mjs TeamMcp/Forja/Cockpit` + `cowork-mirror-freshness --compare --check` (espelho SYNC em 2026-09-02) + skill `comparar-design-prod`. A Onda 10 (Integrador) tem a referência do protótipo medida no comentário do #6563 (CliTabs do DS, 13px / `0 14px`).

## Decisões [W] pendentes (não são do agente)

1. `--accent` dark na **fundação**: protótipo 0,70 × prod 0,55 (é o "0,55 × 0,70" de todas as rodadas de todos os módulos). Hoje escopado à Forja (`.fj-hub`/`.fj-page`). Reconciliar globalmente = mudança de fundação (UI-0013), decisão [W].
2. Zona cinza herdada do `visual-regression` (Jana · Ponto/Dashboard · Fiscal/Cockpit 0,85% · financeiro-unificado): passei com `visreg-gray-approved` em #6553/#6563 sob a autorização de merge; a baseline dessas telas **segue divergente**.
3. Cowork: regenerar o bundle v2 (o DS local publica 44 componentes, o vivo 55 — sem `Segmented`) e consertar na fonte as inconsistências (pedido escrito em `prototipo-ui/CODE_NOTES.prompt-cowork-inconsistencias-na-fonte-2026-09-02.md`).
4. Shell a 1280: o protótipo vira rail 56px automaticamente; prod só por toggle. Fundação, não Forja. Prod a 1280 não foi medida (Browser pane sem sessão; Chrome não aceitou resize).

## Armadilhas que esta sessão pagou (pra próxima não pagar)

- Valor dentro de `@media` copiado como base (Onda 2, padding 20/18/14) — **meça no browser**, não leia o CSS (LC-08).
- `foundation-guard --write` removeu `inertia.css: 74` da baseline sem eu pedir — conferir o diff da baseline antes de commitar.
- `BASELINE-ABSORB:` tem que estar no commit que **toca** a baseline (tamper-guard).
- `gh pr merge --delete-branch` falha em "main is already used by worktree" mas o merge **acontece** — ler `gh pr view --json state` antes de repetir.
- Deploy é fila serializada (`concurrency: deploy-production`) e um run anterior falhou no reset de OPcache com 500 transitório — prod estava 200/302 na hora; o run seguinte passou.

## Estado MCP no momento do fechamento

Servidor MCP `mcp.oimpresso.com` **não respondeu** no SessionStart (hook `brief-fetch` caiu no fallback por timeout) e as tools `cycles-active`/`my-work`/`sessions-recent`/`decisions-search` não estavam disponíveis nesta sessão — o checklist MCP-first **não pôde ser cumprido**; o estado acima vem do git (`gh pr view`, `gh run list`) e de `list_sessions` (nenhuma sessão paralela em execução às 13:00 BRT). Quem abrir a próxima: rodar `brief-fetch` primeiro e conferir se as tasks da Forja no MCP refletem as Ondas 0–2.1 (não registrei `tasks-update` por indisponibilidade).
