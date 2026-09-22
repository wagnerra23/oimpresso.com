---
date: "2026-09-22"
time: "0814 BRT"
slug: "kpi-grid-breakpoint-e-a-divida-que-nao-existia"
tldr: "O chip pedia pra consertar a anatomia do KPI do Painel da Jana; re-medida na tela viva, ela já estava fechada desde 2026-09-03 (24/26 campos idênticos). O que existia era OUTRO eixo — o breakpoint do grid — e era a queixa literal de [W] (\"quantidade de colunas de kpi\"). Fechado por réplica local `JanaKpiGrid` sobre o primitivo `<Grid>`; [W] confirmou na tela: 4 colunas. Duas lápides §5 registradas, e o `ciclo-adversary` derrubou a CLASSE de uma delas antes de virar canon."
decided_by: [W]
cycle: null
prs: [7655, 7682]
us:  []
next_steps:
  - "Nada bloqueando. O `margin-bottom` que eu declarei aberto foi fechado por outra sessão (UC-JPAIN-33, #7653) — já corrigido nos 3 sites onde eu tinha escrito."
  - "Se alguém for mexer no grid de KPIs: ler o docblock do `JanaKpiGrid.tsx` ANTES — arbitrary variant no `className` do `KpiGrid` shared sai inerte, medido 2×."
related_adrs: ["0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias", "0253-primitivos-layout", "0344-two-strikes-cobre-processo"]
---

# KPI do Painel da Jana — o breakpoint, e a dívida que não existia mais

## TL;DR

O chip mandava fechar a dívida visual do KPI descrevendo três itens (rótulo sans 11px, caixa de
ícone 36×36, valor 22×24px). **Re-medidos na tela viva, os três já estavam iguais** — fechados em
2026-09-03 pelo [#6662](https://github.com/wagnerra23/oimpresso.com/pull/6662). Obedecer teria
reescrito código correto.

O que a re-medição achou foi **outro eixo, que nenhuma rodada anterior tinha medido**: o
**breakpoint do grid**. E ele bate com a queixa literal de [W], que a sessão irmã me repassou:
*"quantidade de colunas de kpi"*.

## Cronologia desta sessão

1. **Coordenação antes de tocar arquivo.** `whats-active` acusou **5 sessões irmãs** no mesmo
   Painel. Declarei escopo à da grade de Análises e recebi o dela; nenhum conflito de raio.
2. **Fonte + D0.** `ancora.mjs Jana/Index` → `jana-merge.jsx`; `--sla` ⬜ INCONCLUSIVO (4 ausentes,
   todos config/meta). D0 provado nos dois lados (`data-screen-label="Jana — Painel"` × `/ia` +
   `.cockpit`).
3. **Medição com canário.** Sonda ad-hoc byte-idêntica nos dois lados, dark × dark, 1440.
   **24 de 26 campos idênticos.** Canário acusou em ambos (padding → 40px muda o veredito).
4. **As 2 divergências dissolvidas por decomposição:** `small` ausente no 1º card é **dado**; os
   4px de altura decompõem em `+6` (o `emph` não dispara no staging) `−2` (borda que o meu render
   do espelho não pintou). ⚠️ Isso **refuta** a causa registrada em 09-03 (*"line-height do
   `small`"*): ele é `16.5px` idêntico nos dois lados.
5. **O achado.** Medido em 6 viewports: `.jc-kpis` quebra em 1100px sem degrau de mobile; o
   `colsMap[4]` do `KpiGrid` quebra em `lg:`(1024) e `sm:`(640). Divergiam **1080 · 1050 · 600**.
6. **Duas tentativas de conserto MEDIDAS INERTES** antes da que funcionou (ver Decisões).
7. **Merge + smoke.** [W] autorizou direto, mergeei no ato (sem auto-merge). Ele confirmou na
   tela: *"ficou certo, 4 colunas"*.
8. **Ledger.** 2 lápides §5 + 3 `rec`, com o `ciclo-adversary` rodado **antes** de commitar.

## Estado atual dos artefatos

| artefato | estado |
|---|---|
| `_components/JanaKpiGrid.tsx` | **novo** — réplica da `.jc-kpis` sobre o primitivo `<Grid cols={2}>` (ADR 0253) |
| `_components/JanaCockpit.tsx` | consome `<JanaKpiGrid>`; o `KpiGrid` shared saiu desta tela |
| `Index.casos.md` | **UC-JPAIN-34** (cedi o 30 — ver Decisões) |
| `PainelContratoTest.php` | UC-JPAIN-34 + extrator `painelKpisDoGrid` migrado para `<JanaKpiGrid>` |
| `Index-visual-comparison.md` | rodada de 2026-09-21 + **nota de fechamento** na tabela §KPIs |
| `licoes-rejeitadas.md` / `LICOES_CODE.md` | 2 lápides + 3 `rec`; LC-08 116→**118**, LC-30 3→**4** |

## Decisões tomadas

**Réplica local, não `className` no shared — e a razão foi MEDIDA, não escolhida.** O Tailwind 4
emite os variants **arbitrários antes dos nomeados**, então com o `colsMap` no meio o `lg:` sempre
vence. Duas tentativas passaram em typecheck, lint e CI inteiro **sem mover um pixel**. Sem o
`colsMap` competindo a ordem funciona a favor. Detalhe no docblock do componente.

**Troquei o PR no meio do caminho (v2 → v3).** O primeiro carregava violação Tier 0 no histórico:
escrevi valores em `R$` numa **mensagem de commit**, e este repo tem
`squash_merge_commit_message: COMMIT_MESSAGES` — o valor entraria no `main`. **Fiz PR novo em vez
de force-push**: o `--force-with-lease` está barrado pelo hook, e a autorização de [W] tinha me
chegado **através de outra sessão** — mensagem de peer não vale para desbloquear permissão do
usuário. Custo declarado: o thread do #7641 ficou lá.

**Cedi o UC-JPAIN-30.** 3ª colisão de id nesta tela; o do `h1` entrou em `main` enquanto eu
esperava. Quem está em `main` fica. Meu virou **34** (acima do maior; o 32 estava vago e podia
estar reservado). Troca cirúrgica por linha, para não tocar o UC alheio.

## Bloqueios / pendências

**Nenhum bloqueio.** Duas pendências viraram fato datado e já estão corrigidas no canon:

- O **`margin-bottom` 18×16** que declarei "medido e aberto" foi fechado por outra sessão
  (UC-JPAIN-33, [#7653](https://github.com/wagnerra23/oimpresso.com/pull/7653)) **enquanto este PR
  esperava** — e a minha análise do conserto estava **errada**: medi certo *de onde vinha* em
  produção (o `space-y-4`), e concluí errado *como se fecha*. Na âncora o 18px não vem de
  container, vem de cada seção. Corrigido nos 3 sites.
- **Cor de fundo/borda/texto do lado DESIGN não foram medidas** nesta rodada: no render do espelho
  o `colors_and_type.css` veio com 0 regras e `--surface`/`--border`/`--text-3` ficaram vazios.
  Nenhum dos 24 campos ✅ depende deles. **D1 (rede)** também não foi exercitada.

## Próximos passos (ordem)

1. Nada obrigatório. O eixo está fechado e confirmado por [W] na tela.
2. Quem for mexer no grid de KPIs: **ler o docblock do `JanaKpiGrid.tsx` antes** — ele carrega os
   dois offsets medidos e a razão de não usar `className` no shared.
3. Se a cor do lado design virar prioridade, o pré-requisito é o espelho com o
   `colors_and_type.css` completo — hoje ele carrega vazio no render local.

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**.
- `whats-active hours:3` → **11 sessões**, várias no mesmo Painel da Jana (gráficos, Metas, h1,
  ritmo vertical, VRT). Coordenei com duas delas por mensagem direta ao longo da sessão; escopos
  declarados e sem sobreposição de raio.
- ⚠️ **Contexto que o próximo agente precisa saber:** houve **merges sem aprovação registrada**
  hoje (auto-merge religando sozinho), e [W] mandou reverter dois (#7637 e #7640, via
  [#7654](https://github.com/wagnerra23/oimpresso.com/pull/7654)). A causa **segue em aberto** —
  uma sessão irmã eliminou [W] como origem por medição (`0 de 13` ativações dentro de 30s, depois
  de corrigir um corpus contaminado) e o "Auto-fix" segue suspeito sem prova. **Eu não liguei
  auto-merge em nenhum PR**; mergeei os dois no ato, com autorização direta.

## Referências

- PRs: [#7655](https://github.com/wagnerra23/oimpresso.com/pull/7655) (o conserto) ·
  [#7682](https://github.com/wagnerra23/oimpresso.com/pull/7682) (o ledger) ·
  [#7641](https://github.com/wagnerra23/oimpresso.com/pull/7641) (fechado, substituído pelo 7655)
- Medição: `Index-visual-comparison.md` §"Rodada MEDIDA de 2026-09-21 — o eixo RESPONSIVO dos KPIs"
- Lições: `licoes-rejeitadas.md` §5 2026-09-21 (arbitrary variant inerte · emenda do opcache) ·
  `LICOES_CODE.md` LC-08 e LC-30
- O que fechou a anatomia antes desta sessão: [#6662](https://github.com/wagnerra23/oimpresso.com/pull/6662)
  (Onda 2, 2026-09-03), com a medição de runtime em `Index.casos.md:835`
