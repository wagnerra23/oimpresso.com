---
date: "2026-09-06"
topic: "Exporte o ALVO: primeiro <tela>.alvo.json do protocolo de export sai da máquina (Painel da Jana) — e a medição real pegou a máquina aprovando o esqueleto; --aguardar-sumir e --quieto-ms com bite-test"
authors: ["C"]
related_adrs: ["0384-design-sync-recibos-executaveis-por-tela", "0374-emenda-0315-espelho-cowork-e-rota-prevista", "0387-github-md-diario-cowork-aceito-e-tratado"]
prs: [6918]
outcomes:
  - "prototipo-ui/alvos/jana--index.alvo.json: 9 seções do Painel, seletores do --mapa, 3 runs byte-idênticos (sha256 1023efb9…), T5 provado (kpis 3→2 filhos)"
  - "alvo.mjs: --aguardar-sumir (entra e sai; nunca sai → NÃO MEDI) e --quieto-ms (janela de silêncio); selftest 6/6 → 9/9 com controles negativos"
  - "Medido: espelho atrás do Cowork no ciclo Jana 04/09 (cli-tabs.jsx, chat-jana.jsx < 48 KB) — só pacote regenerado fecha; pacote congelado em 24/08"
  - "Protocolo de export lido inteiro do Cowork (truncated:false): módulo → tela → seção → blocos A–D com ancoragem dupla — já é a forma; o pacote da Jana segue"
---

# O ALVO do Painel da Jana saiu da máquina

## Pedido

[W]: *"consegue mandar o design como deve transportar com protocolo, ele está bom? EXPORTE o ALVO"* · *"vai ter que separar por módulo → tela → blocos → ancoragem dupla? senão contratos de construção de telas não ficam tão detalhados, e o code acaba não entendendo e corta os comandos"* · *"é usado máquina para baixar?"* · *"gere o comando para o designsync fazer agora"*.

## O que foi feito, na ordem

1. Base: worktree estava 229 atrás; branch nova de `origin/main` fresco (`claude/design-transporte-alvo`). Painel do `protocolo.config.mjs` rodado; skill `aplicar-prototipo` carregada.
2. Os dois docs do protocolo de export **não estão no git** — vieram por `DesignSync.get_file` (`COLAR-NO-CODE-PROTOCOLO-COWORK-EXPORT.md`, `COLAR-NO-CODE-jana-tabs-cor-e-icone.md`, ambos `truncated:false`). Lidos como dado; não transcritos.
3. `alvo.mjs --selftest --browser` 6/6 nesta máquina (chromium presente).
4. Espelho servido pelo `servirEspelho` que o `design-diff-lote.mjs` já exporta (runner de sessão no scratchpad, não no repo). Rota default do shell = `chat` + tab `painel` — nenhuma injeção de rota.
5. `--mapa --raiz .jc-page` devolveu **4 filhos** com `jm-sk` (esqueleto). Leitura do protótipo: `JmPainelSkeleton` renderiza `jm-sk-nota` sem DS, `.jm-sk` com DS; `carregando` 650 ms re-armado por `company.id`.
6. Flag `--aguardar-sumir`: run A 1011 nós, run B **719** (o esqueleto ainda não tinha montado quando a espera por `detached` passou). Corrigido: `attached` (3 s) antes de `detached`. Ainda assim um run em três pegou 719 — fases encadeadas.
7. Flag `--quieto-ms 2000`: 3 runs byte-idênticos, 1011 nós, 0 ausentes. T5 com `--injetar-falha` em `.jc-kpis`: 3 → 2 filhos, JSON muda.
8. README dos alvos, secoes.json versionado, PR #6918.

## Números do alvo (1280×900, dark, espelho de 03/09)

header 2 filhos · tabs 6 (buttons; nav sem classe no espelho) · brief 7 · kpis 3 · metas 2 · h2 análises 2 · grid 5 · h2 ações 1 · ações 3. Ordem do render igual ao §3 do pacote do Cowork (header → tabs → brief → KPIs → METAS → análises → ações). Alturas diferem do §3 por viewport (Cowork mediu a 1650 px de conteúdo).

## O que NÃO foi feito, declarado

- T7 (`design-diff --compare --check` prod × proto) não rodou — exige prod autenticada.
- O pacote do Cowork **não** foi regenerado (roda do lado do design). O comando foi entregue a [W] pra colar no Design.
- Os dois `.md` do Cowork não foram persistidos no repo (`get_file` inline; ADR 0389 permite escrita inline sob 4 condições, mas não era o pedido).
