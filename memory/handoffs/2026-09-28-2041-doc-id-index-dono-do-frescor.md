---
date: "2026-09-28"
time: "2041 BRT"
slug: "doc-id-index-dono-do-frescor"
tldr: "governance/doc-id-index.json ganhou dono do frescor: o job refresh do system-map.yml roda doc-id-index --refresh todo dia (07:30 BRT). #8085 mergeado por [W], CI 115 pass / 3 skip / 0 fail. Primeira execução real do cron ainda não aconteceu — conferir amanhã."
decided_by: [W]
cycle: null
prs: [8085]
us: []
next_steps:
  - "Conferir a 1ª run do cron do system-map.yml (29/09 07:30 BRT): o step doc-id-index --refresh rodou e o auto-PR inclui governance/doc-id-index.json se houve drift"
  - "Decidir se o skip de binário do ripgrep (arquivo com byte NUL) vira emenda da lápide §5 2026-07-30 (rg --hidden)"
related_adrs: []
---

# Handoff 2026-09-28 20:41 BRT — doc-id-index ganhou dono do frescor

## TL;DR

O índice `governance/doc-id-index.json` estava 330 ids atrás do `main`, porque o `--write` não tinha invocador. Agora o job `refresh` do `system-map.yml` roda `doc-id-index --refresh` todo dia. Esse modo regenera o índice, mas recusa quando há move pendente. [#8085](https://github.com/wagnerra23/oimpresso.com/pull/8085) mergeado por [W].

## Estado MCP no momento do fechamento

- `cycles-active`: nenhum cycle ativo em COPI.
- `my-work`: sem tasks ativas para @wr23.
- `sessions-recent limit:3`: os três mais recentes indexados são de 2026-09-13 (refutação #7224 r2, Compras thread 01, DS-átomos thread 03). Nenhum toca o doc-id-index.
- `decisions-search "doc-id-index frescor índice"`: nenhuma ADR sobre o tema. O desenho do índice vive na proposta `proposals/2026-07-23-referencia-id-estavel-doc-links.md` e no docblock do script.
- `whats-active` (início da sessão): 15 sessões ativas, nenhuma tocando `doc-id-index` ou `system-map.yml`. `gh pr list --search doc-id-index` sem PR aberto.

## Cronologia desta sessão

| Quando | Evento |
|---|---|
| início | `--check` rc=1 em `origin/main` d0018abce. Faltavam 330 ids e 0 tinham path mudado |
| medição | Consumidores: 89 arquivos citam o nome (`git grep`), só `doc-auto-relink --detect` lê o `.json` para decidir |
| medição | Hook de commit recusado (conflito no bloco `stats` + apaga o sinal de move); publicador diário escolhido |
| código | `--refresh` + bite-test pelo CLI (18/18; mutante sem a recusa → 17/18) |
| 20:34Z | #8085 mergeado por `wagnerra23` |

## Estado atual dos artefatos

### Entregue nesta sessão

| Arquivo | Status | Notas |
|---|---|---|
| `scripts/governance/doc-id-index.mjs` | ✅ | `pendingMoves()` exportada + modo `--refresh` + docblock "DONO DO FRESCOR" com a medição |
| `.github/workflows/system-map.yml` | ✅ | step `--refresh` no job `refresh`; JSON no `add-paths` do auto-PR |
| `governance/doc-id-index.json` | ✅ | regenerado: 2520 → 2850 ids, `--check` rc=0 |

### PRs

| PR | Status | Conteúdo |
|---|---|---|
| [#8085](https://github.com/wagnerra23/oimpresso.com/pull/8085) | merged (`ee07e6efbf`) | fix(governance): doc-id-index ganha dono do frescor — regen diário no system-map |

## Decisões tomadas

| Pergunta | Decisão | Justificativa |
|---|---|---|
| Quem mantém o índice em dia? | Job `refresh` do `system-map.yml` (cron diário, escritor único) | Publicador de derivados que já existe; 0 corridas medidas em 16/09 |
| Estender o hook `maquinas-inventario-no-commit`? | Não | 32/300 commits mudam o conjunto de docs; todo regen reescreve `stats` → PRs concorrentes conflitam. E regen no commit do move apaga o sinal do `--detect` |
| Promover `--check` a gate de PR? | Não | Já medido e recusado no docblock (30/07): ~64% dos PRs ficariam vermelhos |
| Regenerar com move pendente? | Não; `--refresh` recusa com exit 0 + `::warning::` | Move pendente é trabalho do `doc-auto-relink --detect --apply`, e regenerar antes esconde o move para sempre |

## Bloqueios / pendências

- [ ] A 1ª execução real do `--refresh` no cron ainda não aconteceu. Conferir o run de 29/09 e o auto-PR. Owner: próxima sessão.
- [ ] O `#8037` removeu 2 ids do JSON à mão sem atualizar o `stats` (2520 ids × `resolved: 2522`). Com o regen diário isso some, mas mostra que PRs editam o JSON à mão, e cada edição dessas pode conflitar com o auto-PR diário. Owner: observar.
- [ ] Arquivo órfão de 42 bytes em `C:\Users\wagne\AppData\Local\x`, criado por um redirect meu por engano. O `block-destructive` barrou o `rm`, então fica para [W] apagar à mão.

## Achado que vale além desta sessão

- **O ripgrep pula arquivo com byte NUL, mesmo com `--hidden`.** `rg --hidden -g '!.git/**' -l doc-id-index` deu 88 e `git grep -l` deu 89. O que faltava era `scripts/governance/doc-auto-relink.mjs`, exatamente o único consumidor que lê o JSON. Ele tem um NUL literal na linha `${file}\x00${raw}`, e o `rg` o trata como binário. É a mesma família da lápide §5 2026-07-30 (a receita de varredura não dizia `--hidden`), num eixo novo. O teste barato continua valendo: se a contagem do `rg` difere da do `git grep`, a varredura estava cega. **Não registrei como emenda nem como `rec` no LC-08**: foi pego pelo desempate antes de virar afirmação. Fica a decisão de registrar.

## Próximos passos (ordem)

1. Amanhã, após 07:30 BRT: `gh run list --workflow=system-map.yml --limit 1` e ler o log do step "Regenera doc-id-index". Se houve drift, o auto-PR deve trazer o JSON.
2. `node scripts/governance/doc-id-index.mjs --check` no `main` logo após o merge do auto-PR → rc=0 esperado.
3. Se aparecer `::warning::` de move pendente: rodar `node scripts/governance/doc-auto-relink.mjs --detect` e aplicar o relink antes.
