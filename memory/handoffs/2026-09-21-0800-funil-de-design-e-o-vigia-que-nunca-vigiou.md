---
date: "2026-09-21"
time: "08:00 BRT"
slug: funil-de-design-e-o-vigia-que-nunca-vigiou
tldr: "Ensaio do funil de design ponta a ponta (applied 4 → validated 4, primeiras desde o #7499) destravou dois vigias que não vigiavam: a quarentena de LANE não tinha catraca nenhuma (42 testes fora do CI) e o cc-watcher nunca observou nada em 141 dias, deixando o whats-active cego. 7 PRs em main, tarefa agendada criada. Quatro afirmações minhas foram derrubadas por adversário ou peer antes de virarem canon."
prs: [7507, 7508, 7509, 7526, 7531, 7535, 7543]
decided_by: [W]
related_adrs:
  - 0384-design-sync-recibos-executaveis-por-tela
  - 0390-emenda-0384-smoke-em-ambiente-controlado
  - 0119-paralelismo-sessoes-whats-active-tier-1
next_steps:
  - "6 chips abertos em sessões próprias — ver §Próximos passos"
  - "Dup de 99,8% do cc-watcher: chip task_56d3579c, causa NÃO diagnosticada"
---

# Funil de design ponta a ponta — e os dois vigias que não vigiavam

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` → **sem tasks ativas** pra `@wr23`
- Último handoff irmão: **2026-09-16 18:09** (nenhum entre 17 e 20/09 — este é o primeiro desde então)
- `whats-active` → **9 sessões**, com `ativo 15s ago` e `paths tocados` populados

## O que aconteceu

[W] pediu um plano de teste da importação do bundle de protótipo até produção. O plano existia
(`memory/reference/FLUXO-DESIGN.md`, E0 + 9 etapas) — o que faltava era **percorrer**. Percorrer
revelou o que o papel não revelaria.

**O funil fechou**: `applied 4 → tested 4 → smoked 4 → validated 4`. São as primeiras telas
`validated` desde que o [#7499](https://github.com/wagnerra23/oimpresso.com/pull/7499) derrubou os
recibos stale. O `design-smoke-ci` voltou a ter comida e fotografou sozinho; a órfã
`governance/design-smokes` estava parada havia 11 dias **não por bug, por falta de entrada**.

**Dois vigias não vigiavam**, e os dois só apareceram por executar:

1. **Quarentena de LANE sem catraca.** Existem DUAS quarentenas: o marcador `@group
   legacy-quarantine` no `.php` (126, catracado) e a exclusão `.github/*-quarantine.list` (42) — esta
   **nada contava**. A regra já existia em prosa no cabeçalho da própria lista (*"a lista deve
   encolher"*); nenhuma máquina a cobrava. FP medido ANTES de armar: 42/42 já tinham motivo escrito,
   logo a catraca nasceu verde.
2. **O cc-watcher nunca observou nada em 141 dias** (achado do
   [#7534](https://github.com/wagnerra23/oimpresso.com/pull/7534), sessão irmã): chokidar 4 removeu
   suporte a glob. O `whats-active` (Tier 1, [ADR 0119](../decisions/0119-paralelismo-sessoes-whats-active-tier-1.md))
   estava cego, e `content_json` nunca era atribuído — **detecção de sobreposição de path nunca
   funcionou, pra sessão nenhuma**. Custo real no mesmo dia: 8 sessões paralelas nos mesmos arquivos.

## Artefatos gerados

| PR | o quê |
|---|---|
| [#7507](https://github.com/wagnerra23/oimpresso.com/pull/7507) | 4 recibos de teste + fallback de lane árvore-menos-quarentena (77→93 alvos com lane) |
| [#7508](https://github.com/wagnerra23/oimpresso.com/pull/7508) | `n_lane_quarantine` — a quarentena de lane passa a ter catraca |
| [#7509](https://github.com/wagnerra23/oimpresso.com/pull/7509) | errata do #7507: comentário não é run-set; 7 de 16 alvos eram inalcançáveis |
| [#7526](https://github.com/wagnerra23/oimpresso.com/pull/7526) | opcache do staging — o web servia bytecode do boot; smoke de UI media código de 10 dias |
| [#7531](https://github.com/wagnerra23/oimpresso.com/pull/7531) | `whats-active`: o "ativo" apontava pro futuro (`ts` é UTC, display lia local) |
| [#7535](https://github.com/wagnerra23/oimpresso.com/pull/7535) | `--write` do ratchet apagava as notas irmãs — destruiu a nota que documentava isso |
| [#7543](https://github.com/wagnerra23/oimpresso.com/pull/7543) | detector de dano da union era cego ao caso de EDIÇÃO |

**Fora do git** (máquina do [W], 21/09): tarefa agendada `oimpresso-cc-watcher` (10 min +
`IgnoreNew`), wrapper `~/.claude/cc-watcher-run.cmd`, checkout dedicado `D:\oimpresso-cc-watcher`.

## Persistência

- **git**: 7 PRs em `main`, todos verdes, nenhum force-push.
- **MCP**: ingest restaurado e agendado; `whats-active` operando com paths.
- **BRIEFING**: não atualizado — nenhuma capacidade de módulo mudou (tudo foi governança/infra).

## Próximos passos pra retomar

Seis chips em sessões próprias: aplicar no CT 100 · dono do ingest (**resolvido nesta sessão**) ·
ledger das 3 lições · janela UTC do `ts` + lane · chokepoint do `--check-lifecycle` · cabeçalho do
`financeiro-pest-quarantine.list`. Mais o `task_56d3579c` (dup de 99,8% do watcher, causa **não
diagnosticada** — declarado assim de propósito).

Comando único pra retomar: `node scripts/design-sync/status.mjs`

## Lições catalogadas

**A que dói:** distribuí a 4 sessões a receita `grep -c <NomeDoTeste> <lista>` para conferir remoção
da quarentena. Ela **mente** — conta a nota de saída que a própria remoção acrescenta, e mente
**mais** quanto melhor documentada for a saída. É LC-11 (substring em prosa contada como a coisa),
cometida dentro de uma receita de verificação, por quem passou o dia caçando essa classe.

**O corolário:** a defesa contra o `merge=union` foi prevista, medida em repo de teste e avisada de
manhã — e funcionou (a union mordeu 4 de 7 merges). A **verificação** distribuída junto tinha o
defeito. *Prever o risco e verificar o conserto são trabalhos diferentes.*

**Quatro afirmações minhas foram derrubadas antes de virar canon**, cada uma por medição alheia:
`"0 de 13"` medido em denominador que não podia falhar · causa errada em 6 de 8 lanes · 7 de 16
alvos inalcançáveis por nome de step · `162 telas` que são 162 **pares** sobre 138 alvos. O sistema
adversarial funcionou; o #7509 existe só por causa dele.

**Três vezes medi a árvore errada** — `head -5` cortando a listagem que provava o contrário, `php -l`
rodando contra a cópia do container, e uma sonda medindo o próprio regex em vez da função consertada.
Nas três, o que salvou foi refazer a medição, não reler o raciocínio.

**Não executei o R12 no primeiro disparo do hook**: ele casou `"próxima sessão"` no meu próprio
texto. Obedecer ali seria cometer a LC-11 dentro da obediência a ela. Executei quando [W] pediu.

## Pointers detalhados

- Funil e etapas: [`memory/reference/FLUXO-DESIGN.md`](../reference/FLUXO-DESIGN.md) (E0 + 9)
- Smoke por host: [ADR 0390](../decisions/0390-emenda-0384-smoke-em-ambiente-controlado.md) — decisão
  [W] *"só CI basta?"* segue **aberta**; número hoje: `producao: 0 · staging-ct100: 0 · ci: 4`
- Cadência do watcher: `IngestLivenessService::FRESH_MINUTES = 15` decide; o heartbeat é bumped
  **por batch** (`CcIngestController:122`), logo o que importa é o **gap**, não a duração do run
