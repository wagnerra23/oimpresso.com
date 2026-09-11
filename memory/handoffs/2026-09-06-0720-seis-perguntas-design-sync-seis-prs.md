---
date: "2026-09-06"
time: "0720 BRT"
slug: "seis-perguntas-design-sync-seis-prs"
tldr: "[W] autorizou resolver as 6 perguntas do #6892; viraram 6 PRs (#6893–#6898): baseline RAGAS re-curado, guard LC-20 no recorder, 3 telas Fiscal applied+tested, 18 órfãos com destino (bloqueadas 0), 7 âncoras medidas/3 descidas, 11 gap.md com mapa (23/23). Smoke D-6 bloqueado: produção pede login e eu não digito senha — tab aberta em /fiscal/config. CI em execução no fechamento; ordem de merge: #6895 antes de #6898 (empilhado)."
decided_by: ["W"]
cycle: null
prs: [6893, 6894, 6895, 6896, 6897, 6898]
us: []
next_steps:
  - "[W] logar como biz=1 na tab do Chrome (https://oimpresso.com/fiscal/config) → eu gravo --record-smoke (deploy 26ac293f46, tenant 1) no #6898 e Config vira validated"
  - "Merge em ordem: #6894 (mata o watchdog vermelho em todo PR) · #6893 · #6896 · #6895 → #6898 (empilhado; update-branch depois) · #6897"
  - "Ratchet-up da nfebrasil-pest.yml com DfeControllerTest/AcoesDfe* (Dfe é a única tela Fiscal sem teste na lane)"
  - "Cowork: regenerar o bundle ao fim do ciclo — 23 âncoras abaixo do piso do get_file seguem sem veredito"
  - "Preencher linhas dos 9 mapas novos por grep -n real (70 TODO) quando cada tela entrar em onda"
related_adrs: ["0130-handoff-append-only-mcp-first", "0384-design-sync-recibos-executaveis-por-tela", "0374-emenda-0315-espelho-cowork-e-rota-prevista", "0317-cron-watchdog-generaliza-auto-canario"]
---

# Handoff — seis perguntas do design-sync, seis PRs

> Narrativa em [sessions/2026-09-06-seis-perguntas-design-sync-resolvidas.md](../sessions/2026-09-06-seis-perguntas-design-sync-resolvidas.md). Este handoff é o **estado pro próximo**.

## Estado dos PRs no fechamento (07:20 BRT)

| PR | intent | CI no fechamento |
|---|---|---|
| #6893 | espelho: 3 âncoras descidas (7 medidas) | UNSTABLE = required verdes; só watchdog advisory (some com #6894) |
| #6894 | baseline RAGAS re-curado | 1 pendente, 0 falhas |
| #6895 | 18 órfãos → destino (bundle_source CV · ALIAS Suporte · 15 A_CRIAR) | re-rodando após 3 consertos (related_us · ALIAS · nota em chave própria) |
| #6896 | LC-20 aviso pré-recibo + bite-test | só watchdog advisory |
| #6897 | 10 gap.md + 11 map.json (23/23) | re-rodando após 2 consertos (prototipo TODO · BRL redigido) |
| #6898 | Fiscal Config/Eventos/Sped applied+tested | empilhado em #6895 (merge do q3 absorvido) |

## Armadilhas desta rodada (leia antes de repetir)

- **Nota inline em frontmatter YAML quebra consumidor**: `gerar-map` leu `TODO  # …` como caminho; `charter-us-lint` leu `[US-X]  # …` como slug. Nota vai em chave própria.
- **Tocar charter legado acorda o `charter-us-lint`** — e se o SPEC do módulo não tem US, não há related_us honesto: use o ALIAS do detector, não invente slug (o lint só valida forma).
- **`git checkout -- <path>` restaura do índice**, não do main; pra desfazer toque já commitado no branch: `git checkout origin/main -- <path>`.
- **`get_file` inline (<~49 KB) não alimenta `--snapshot-from`** — o piso é do transporte; 23 âncoras ficam pra regeneração do bundle no Cowork.
- **CT 100 não é oráculo de teste hoje**: checkout em 26/08 com 42 sujos de outra sessão. O recibo de teste do Fiscal usa o job CI da lane NfeBrasil (que carrega os testes do Fiscal por lista explícita).

## Estado MCP no momento do fechamento

Brief #611 do início da sessão (05/09) — tools MCP não expostas neste worktree filho; sem sinal de sessão paralela nos paths tocados (`whats-active` indisponível). Nenhuma task MCP criada/fechada: todo o trabalho é derivado do #6892 e registrado nos PRs.
