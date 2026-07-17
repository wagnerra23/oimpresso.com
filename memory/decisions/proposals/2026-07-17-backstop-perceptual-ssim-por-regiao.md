---
title: "Backstop perceptual SSIM POR-REGIÃO no harness de fidelidade — dep de imagem (o gap que o próprio código roteia pra ADR)"
status: proposed
date: "2026-07-17"
decisores: [Wagner (aprova, numera, autoriza a dep), Claude Code (autor)]
related_adrs:
  - 0290-fidelity-lock-v0-recusado
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0326-ancora-assada-na-captura-compare
  - 0258-processo-adr-estado-arte-indice-gerado-supersede-atomico
related_proposals:
  - 2026-06-23-prototipo-ssot-unico-com-historico
origem: "Chip C-F1 da grade de réguas design→código (2026-07-17, nota ~7,7). GAP auto-nomeado pelo próprio código: `prototipo-ui/style-fingerprint.mjs:53` e `fingerprint-harness.mjs:11` confessam FORA do vetor — 'backstop perceptual sem âncora (dep SSIM) → exige ADR'. Esta é a ADR que os dois comentários pedem."
prs: ["este (proposta; a dep + o código vêm SÓ após aceite [W])"]
---

# Backstop perceptual SSIM POR-REGIÃO no harness de fidelidade

> **Status: PROPOSTA.** Não é lei, não é ADR numerado, **nenhuma dependência foi adicionada e nenhuma linha de SSIM foi escrita.** [CC] rascunha; **[W] decide, numera, e autoriza a dep** (regra Tier 0 "não criar nova dependência sem ADR" — `memory/proibicoes.md §Código`). O próprio código já roteia este gap pra cá: `style-fingerprint.mjs:53` (*"backstop perceptual sem âncora (dep SSIM → ADR)"*) e `fingerprint-harness.mjs:11` (*"puxa dep de imagem → exige ADR"*). Não mergeia sem Wagner.

## Contexto

O comparador de fidelidade protótipo×produção (`style-fingerprint.mjs` + `fingerprint-harness.mjs`) mede um **vetor DOM de ~25 campos por elemento** (`CAMPOS`), casando elementos por **texto visível normalizado + tag** (1ª passada) e bordas/containers/compostos/ícones por **inventário** (2ª–6ª passadas). É poderoso e honesto sobre um limite que ele **declara sozinho** (`style-fingerprint.mjs:52-53`):

> *"Ainda FORA (precisa driver/dep): estados hover/focus/active e responsivo multi-viewport (harness Playwright…), **backstop perceptual sem âncora (dep SSIM → ADR)**."*

O ponto cego é **geometria/aparência EMERGENTE de elemento sem texto-âncora casável**: um ícone que mudou de forma, um sparkline diferente, um gráfico que perdeu o eixo, um logo redimensionado, um gradiente/ilustração. O vetor DOM **não consegue nem PAREAR** esses elementos (sem texto, a chave `tag|normTexto` não existe) — e mesmo a 6ª passada (`icones`, inventário de `<svg>` por elemento **rotulado**) só os vê quando penduram num elemento que TEM texto. Um SVG solto num card sem label é invisível.

Isso **não é hipótese** — é a mesma classe que já mordeu: o furo dos ícones das tabs (`SNIPPET` v2, comentário `(c)`) foi pego **por screenshot do [W], não pela máquina** ("nunca mais"). A 6ª passada fechou o caso *rotulado*; o *sem-âncora* segue aberto, e o próprio comentário do harness (`fingerprint-harness.mjs:11`) diz que fechá-lo **puxa dep de imagem → exige ADR**. Esta ADR responde esse pedido.

**Escopo:** só as **~11 telas ancoráveis** (as que resolvem âncora de design real via `ancora.mjs` — as demais herdam PT/DS e não têm protótipo pra comparar; ver lápide C9 §"O gap REAL do 7,0"). Roda no **harness LOCAL** (o mesmo `--gerar`/`--compare`/`fingerprint-harness` que já dirige Playwright em dispatch local), **nunca em CI**.

## O que este backstop É e o que NÃO É (as duas lápides que ele respeita)

Duas decisões vivas cercam este terreno. A proposta é desenhada pra **caber DENTRO das duas**, não pra reabri-las:

### 1. ADR 0290 (`recusado`) — render pareado EM CI é proibido
A 0290 matou o "Fidelity Lock v0" (screenshot pareado **em CI**) por 3 motivos: (a) em CI passa **verde quando os dois lados quebram** (proto precisa server+CDN+Babel; prod precisa login+tenant); (b) match de cor exige mapa mantido à mão; (c) "falha na 1ª divergência injustificada" é backdoor de prosa auto-certificada. **Critério de reabertura declarado na própria 0290:** *"reabre se surgir um check de fidelidade HERMÉTICO — render-free, sem auth/CDN, e sem mapa de cor mantido à mão."*

**Como esta proposta NÃO viola a 0290:**
- **Roda LOCAL, nunca em CI** — igual o `render-proto-baseline --gerar` (que **RECUSA sob CI, exit 4**) e o `--compare` de hoje. Em CI continua só o `--check` hermético. **Constraint dura, mecanizada:** o entrypoint SSIM deve **recusar sob CI (exit ≠ 0 com mensagem)**, espelhando a trava `--gerar exit 4` do `render-proto-baseline.mjs`.
- **Zero mapa de cor à mão** — SSIM opera sobre **pixels** (luminância janelada), não sobre um dicionário OKLCH↔Tailwind. Não há whitelist que engula divergência.
- **Não produz verdito que derruba build nem campo "injustificado"** — emite **sinal por-região** que **alimenta a triagem humana** (o mesmo lugar onde os `DIVERGE`/`SO_*` do vetor já caem). Nada auto-certifica nada.

> A 0290 rejeitou render-pareado **em CI**. Render-pareado **LOCAL** já é território ACEITO e vivo (`render-proto-baseline --gerar` renderiza o proto localmente, com trava de âncora `overlapConteudo`/`rotulosDistintivos` fail-closed — ADR 0326). Esta proposta **estende esse mecanismo local**, não ressuscita o de CI.

### 2. Lápide C9 (`proibicoes.md §5`, 2026-07-17) — NENHUM número de fidelidade agregado
A C9 proíbe *"qualquer **nota/razão/score/índice/% ÚNICO** que agregue os vereditos do fingerprint numa métrica de fidelidade"*, porque os vereditos **não são comensuráveis** enquanto a direção não for uniforme (cada `DIVERGE` é um de três: **prod-fora-do-canon** × **proto-atrasado (a prod está CERTA)** × **ruído-de-dado**). **Critério de reabertura declarado na C9:** *"só se existir um classificador que separe as 3 categorias — aí uma razão sobre a categoria 'prod-fora-do-canon' teria significado, e aí é ADR, não chip."*

**Como esta proposta NÃO viola a C9:**
- **SSIM não é um número de fidelidade — é um DETECTOR que adiciona LINHAS à triagem.** Onde o vetor DOM hoje produz `SO_*` pra elemento sem-âncora (ou nem enxerga), o SSIM por-região produz uma **linha por região gráfica sem-âncora** ("região X difere perceptualmente → olho humano"). Ele **não classifica** as 3 categorias e **não agrega** nada num escalar. Alimenta o mesmo `triagemSO()`/olho-humano que já decide as 3 categorias pros `DIVERGE` do vetor.
- **É POR-REGIÃO, jamais por-tela.** Sem soma, sem média, sem `resumoCampos` estendido virando nota. Se um dia alguém somar as SSIMs numa nota → é **exatamente** o que a C9 mata.
- **Não é "baseline de fidelidade por tela NOVO".** Não cria um `.proto-baseline.json` paralelo; **estende o dono do tema** (`render-proto-baseline.mjs` / `fingerprint-harness.mjs`) — as regiões e o pareamento saem da matriz e da âncora que esses já produzem.

## Decisão proposta

### D-1 · Autorizar a dependência de imagem (o objeto desta ADR)

Adicionar ao **harness local** (`devDependencies`, escopo Node/JS) a capacidade de **decodificar um recorte PNG → matriz de pixels** e **computar SSIM** entre dois recortes. Princípios de footprint mínimo (cultura do repo: pure-JS, selftest, "1 fato = 1 lugar", zero build nativo):

1. **SSIM vendorizado como função pura** (~40–60 linhas: grayscale → média/variância/covariância em janela deslizante 8×8 ou 11×11) — **testável por selftest** como todo o resto (`--selftest` com par idêntico → 1.0, par com forma plantada diferente → <limiar). Preferir vendorizar a puxar `ssim.js`/`image-ssim` (evita 1 dep e o supply-chain dela).
2. **Decode PNG**: o `page.screenshot({ clip })` do Playwright (já é dep, `@playwright/test ^1.49.0`) devolve **PNG Buffer**. O decode precisa de um decoder pure-JS — candidato **`pngjs`** (pure-JS, sem compile nativo). *Se* houver caminho de expor o `pngjs` que o Playwright já bundla sem virar dep frágil, melhor ainda; a decisão de implementação escolhe o menor footprint real, mas a **capacidade** (decode PNG) é o que esta ADR autoriza.

**Salvaguarda de runtime (Tier 0):** a dep é **harness-only** (dispatch local / CT 100), **JS não-PHP**, e **nunca no caminho de request do Hostinger** — não colide com a proibição de daemon/tool no Hostinger (ADR 0062): não é octane, não é MCP, não é runtime web; é ferramenta de dev que roda quando um humano dispara a comparação. **Native build (`sharp`/`canvas`) é DESACONSELHADO** justamente pra não arriscar o build no Windows do [W] / CT 100 — pure-JS fecha esse risco.

### D-2 · Desenho: região ANCORADA na matriz, não grid cego

O "sem-âncora" é do **elemento** (o ícone não tem texto), **não da região**. O pareamento vem da **âncora que já existe**:

1. O harness roda o compare DOM (existente) → sabe quais **containers/compostos CASARAM** proto↔prod (por texto agregado/inventário).
2. O `SNIPPET` é estendido pra emitir, por container casado, o **clip rect em pixels** + a flag de quais filhos são **gráfico-sem-texto** (`<svg>`/`<img>`/`<canvas>`/ícone `::before` sem text node).
3. Só pra containers **que casaram E contêm filho gráfico-sem-texto**, o harness faz `page.screenshot({ clip })` dos **dois lados**, na mesma célula (viewport×tema), e roda **SSIM** dos recortes.
4. Emite **linha de triagem** identificada pela âncora do container (assinatura de texto do pai + qual filho gráfico): `região "<texto do card>" · gráfico sem-âncora · SSIM=0.82 → olho humano`. Abaixo do limiar → **força a triagem** (como `triagemSO`), **nunca derruba build**.

**Por que ancorado e não grid:** grid cego (dividir a tela em N×M células e SSIM célula-a-célula) é rejeitado — um elemento que **desloca** faz todas as células a jusante divergirem = ruído em cascata (a mesma doença do "1280 da Larissa" que o harness já evita medindo por elemento). Ancorar o SSIM ao container casado **herda o frame de coordenadas** do match e mede só o **conteúdo gráfico interno** que o vetor de campo não vê.

### D-3 · A fronteira, mecanizada (não confiada à prosa)

Pra que a proposta **permaneça** dentro da 0290/C9 mesmo com sessões futuras:
- **`--ssim` recusa sob CI** (`process.env.CI`/GitHub Actions) com exit ≠ 0 e mensagem — espelho da trava `--gerar exit 4` (ADR 0326 / `render-proto-baseline.mjs`).
- **Saída é lista de linhas, nunca escalar** — o schema do output SSIM **não tem** campo agregado; um selftest **assevera** que não existe chave `score`/`ratio`/`fidelidade` no output (guarda contra a regressão C9 nascer aqui).
- **Sem campo "injustificado"** — a linha só carrega `{regiao, ancora, celula, ssim, limiar}`; a decisão (as 3 categorias) é do humano na triagem, fora do artefato.

## Consequências

- ✅ **Fecha o único ponto cego declarado** do comparador (`style-fingerprint.mjs:52-53`): gráfico sem-âncora deixa de ser invisível — vira **linha de triagem** com a região nomeada.
- ✅ **Fica DENTRO** da 0290 (local, hermético no que vai a CI, sem mapa de cor) e da C9 (por-região, sem nota, estende o dono do tema).
- ✅ **Selftestável** como o resto do harness — a orquestração e a função SSIM provam-se sem browser; a captura real segue exigindo 1 corrida live (evidência Tier 0, igual `fingerprint-harness.mjs:24-26`).
- ⚠️ **Custo:** +1 dep pure-JS (decode PNG) + ~50 linhas vendorizadas (SSIM) + N screenshots de recorte por célula (só nas 11 telas ancoráveis, só em containers com gráfico-sem-âncora — barato).
- ⚠️ **Não substitui o olho do [W]** — adiciona linhas ao que ele já tria; não decide gosto.

## Residuais honestos (o que este backstop NÃO resolve)

1. **Ruído-de-dado sobre gráfico** — sparkline/gráfico/avatar codificam **dado** visualmente; proto-mock ≠ prod-real → SSIM baixo por **dado**, não por fidelidade. É a mesma categoria `ruído-de-dado` que o vetor DOM já tem, e **o SSIM não a classifica** (por isso não vira razão — C9). A triagem humana desambigua. Mitigação parcial: preferir regiões de **ícone/logo/borda-geométrica** (estáveis a dado) e **marcar** regiões suspeitas de conter dado.
2. **AA / sub-pixel / font-hinting** — SSIM é mais robusto a isso que pixel-diff cru (é o motivo de SSIM e não `pixelmatch`), mas o residual não é zero; limiar + janela mitigam, não anulam.
3. **Depende do container ter casado** — se o próprio container não casou, seus gráficos já aparecem no `SO_*`; o SSIM só agrega valor onde o container **casou** mas o filho gráfico **driftou em silêncio**.
4. **A fronteira 0290/C9 é de disciplina + mecanismo** — as travas de D-3 seguram, mas qualquer PR futuro que mova o SSIM pra CI, pra um gate pass/fail, ou pra um número agregado **reabre exatamente** o que a 0290 e a C9 mataram. Reversão abaixo.

## Critério de reabertura / reversão

- **Reversão (rebaixar/remover):** se o SSIM por-região produzir **falso-positivo dominante** (a maioria das linhas é ruído-de-dado/AA e o [W] para de olhar), rebaixa a advisory-silencioso ou remove — o valor é *dirigir o olho*, não *encher a triagem de lixo*.
- **NÃO evoluir pra:** nota agregada (C9), gate de CI (0290), campo "injustificado" (0290 motivo 3), baseline SSIM por-tela paralelo ao `.proto-baseline.json` (C9 "estende o dono, não abre paralelo").

## Anchor

**Documentado em:** este arquivo (proposta). Ao aceitar, [W] numera e o mecanismo entra em `prototipo-ui/fingerprint-harness.mjs` (+ SNIPPET em `style-fingerprint.mjs`), com RUNBOOK em `memory/requisitos/_DesignSystem/` e nota no `PROTOCOLO-COMPARACAO-RUNTIME.md`. Fonte do gap: `prototipo-ui/style-fingerprint.mjs:52-53` + `prototipo-ui/fingerprint-harness.mjs:9-11`. Lápides que cerca: [ADR 0290](../0290-fidelity-lock-v0-recusado.md) + C9 (`memory/proibicoes.md §5`, 2026-07-17).
