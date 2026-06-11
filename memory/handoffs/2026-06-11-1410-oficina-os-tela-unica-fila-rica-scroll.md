---
date: "2026-06-11"
time: "14:10 BRT"
slug: oficina-os-tela-unica-fila-rica-scroll
tldr: "OficinaAuto — tela de OS unificada (1 tela, 4 abas Quadro·Lista·Grade·Fila) + drawer único (RichBody extraído) + Fila com detalhe rico inline + fix scroll do shell (chrome fixo) + deploy SSH robusto (ConnectTimeout=180). 6 PRs deployados; OficinaAuto grade 79→80."
decided_by: ["W"]
cycle: CYCLE-08
prs: [2533, 2535, 2538, 2544, 2548, 2551]
next_steps:
  - "Confirmar com [W] se hard-reload resolveu o 'Oficina parece pendente' (provável bundle em cache)"
  - "Opcional: skeleton leve na Fila durante o fetch pesado do detalhe rico (~5s no Hostinger)"
  - "Opcional: padronizar o fix de scroll (root flex-1 min-h-0 flex flex-col) em outras telas altas"
related_adrs:
  - 0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada
  - 0265-oficina-reparo-erradica-locacao
---

# Oficina Auto · tela de OS: unificação + Fila rica + scroll, com deploy SSH consertado no meio

## Estado MCP no momento
- Cycle: CYCLE-08 Receita — Onda A (off-cycle: trabalho de UI/OficinaAuto, não toca goal de receita direto).
- PRs desta sessão mergeados+deployados: **#2533, #2535, #2538, #2541(fechado), #2544, #2548, #2551**.
- OficinaAuto module-grade: 79 → **80** (Module Grades Gate).
- Git: detached HEAD pós-manobras de branch; working tree limpo; tudo em `main` via deploy verde `27363536608`.

## O que aconteceu
Pedido inicial [W]: polir Lista/Fila de OS ao canon do Board (PR #2533). Evoluiu em camadas conforme [W] olhava o resultado vs o protótipo Cowork:
1. **#2533** — polish Lista/Fila (KPIs canon clicáveis, toolbar consolidada, VALOR real via `withSum`, drawer eyebrow/meta compacta). [W] viu e disse "ficou aquém do demo".
2. **Deploy quebrou** ("sempre quebra"). Causa raiz que **eu mesmo introduzi**: encurtei `ConnectTimeout` pra 30s no #2535, contra o manual (`hostinger.md` diz **900 — handshake leva minutos**). #2538 restaurou `ConnectTimeout=180` + warm-up canônico → deploy voltou a segurar mesmo com Hostinger oscilando ~50%. [W]: "está usando ipv4? cuidado com hostinger" (estava; o erro era o timeout).
3. [W] "vai duplicar os componentes?" — **certo**. Mantive Index e Board separados = duplicação. **#2544 UNIFICA**: 1 tela "Oficina Auto" com toggle **Quadro·Lista·Grade·Fila** in-page sobre o MESMO payload `columns`; KPIs+abas+toolbar compartilhados; `index()` delega pro `board()`; Index.tsx/ServiceOrderFila/ServiceOrderSheet **deletados**. Net −1110 linhas.
4. [W] "drawer são os mesmos, só o principal deve existir" — **#2544/#2548**: ServiceOrderSheet (simples) aposentado; `ServiceOrderRichSheet` é o único; **#2548 extrai `ServiceOrderRichBody`** (corpo rico = export nomeado) usado pelo drawer E pela Fila inline (1 corpo, 2 chrome). Fila ganhou detalhe RICO inline (DVI/Fotos/Peças/Checklist/Pipeline/Timeline).
5. [W] "ao rolar a tela corta, acontece no Financeiro também, sistêmico" — **#2551**: o `.main-body` (overflow-y:auto) rolava inteiro porque o workspace era só `flex-1`. Fix: `root flex-1 min-h-0 flex flex-col` + `conteúdo flex-1 min-h-0 overflow-auto` → chrome fixo, só conteúdo rola. Verificado AO VIVO no browser (4 views, chrome fixo, scroll liso).

## Artefatos gerados
- `resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx` — tela unificada (4 views in-page) + BoardLista/BoardFila + fix de scroll.
- `.../ProducaoOficina/_components/ServiceOrderRichSheet.tsx` — `ServiceOrderRichBody` extraído (drawer = wrapper Sheet fino).
- `.github/workflows/deploy.yml` — SSH `ConnectTimeout=180`/`ConnectionAttempts=3`/`ServerAlive 3/200`, warm-up canônico.
- `memory/reference/hostinger.md` §warm-up — "NÃO encurtar ConnectTimeout" (lição cara).
- Deletados: `ServiceOrders/Index.tsx`+charter, `_components/ServiceOrderSheet.tsx`, `_components/ServiceOrderFila.tsx`, helpers `buildServiceOrderKpisPayload`/`buildStagesPayload`.
- Charter `Board.charter.md` → v5 (page=/ordens-servico, 4 views, Fila rica, drawer único).

## Persistência
- git: tudo mergeado em `main`, deployado verde.
- MCP: webhook GitHub→MCP propaga em ~2min.
- BRIEFING OficinaAuto: candidato a `brief-update` (módulo tocado pesado) — NÃO rodado nesta sessão.

## Próximos passos pra retomar
- **Onda 3 (opcional)**: aplicar o mesmo fix de scroll (`root flex-1 min-h-0 flex flex-col` + conteúdo `flex-1 min-h-0 overflow-auto`) nas outras telas altas (Financeiro usa CSS `.fin-curadoria` que deixa o main-body rolar — [W] disse estar "certo", então decidir se padroniza).
- **Fila loading**: ~5s de spinner no detalhe rico (fetch pesado do show() no Hostinger) — adicionar skeleton mais leve (sugerido, não feito).
- [W] reportou "Oficina parece pendente" vs Financeiro — diagnóstico: provável **bundle em cache** na aba dele (eu só vi o fix após Ctrl+Shift+R). Confirmar com [W] se hard-reload resolveu.

## Lições catalogadas
- **L (deploy)**: NUNCA encurtar `ConnectTimeout` do SSH Hostinger — manual diz minutos; encurtar dá "sempre quebra". Catalogado em `hostinger.md`.
- **L (arquitetura)**: "paridade com o demo" sem unificar = duplicação. No demo são ABAS, não páginas. Extrair corpo compartilhado (1 corpo, N chrome) é o padrão anti-duplicação.
- **L (scroll sistêmico)**: cockpit é `height:100vh overflow:hidden`; quem rola é `.main-body`. Página alta sem `flex-1 min-h-0 flex flex-col` no root faz o main-body rolar inteiro (chrome some). Fix por-tela é mais seguro que mexer no `.cockpit` global.
- **L (cache pós-deploy)**: aba aberta pode segurar bundle velho; smoke visual exige hard-reload (Ctrl+Shift+R) pra ver o fix.
- **L (visual-regression)**: não é required check (branch protection) — mudança visual intencional não bloqueia merge; aprovação real do screenshot por [W] é pós-deploy.

## Pointers detalhados
- Manual SSH/deploy: `memory/reference/hostinger.md` §SSH config robusto + §warm-up.
- Charter da tela: `resources/js/Pages/OficinaAuto/ServiceOrders/Board.charter.md` (v5).
- ADR 0194 (domínio mecânica) · ADR 0265 (locação erradicada) · ADR 0143 (FSM LIVE) · ADR 0093 (multi-tenant).
