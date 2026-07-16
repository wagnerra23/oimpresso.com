# RUNBOOK — Fidelidade proto×prod por fingerprint (o gate de medição)

> **Escopo:** o **loop de MEDIÇÃO de fidelidade** — dado um design (a âncora do charter) e a tela viva, *provar mecanicamente* onde o código diverge do design, **contra a fonte CERTA** (trava de âncora fail-closed). NÃO é o processo de aplicar o protótipo — esse é o [`RUNBOOK-aplicar-prototipo-orquestracao.md`](RUNBOOK-aplicar-prototipo-orquestracao.md) (fases −1…5) + a mecânica por-tela em [`RUNBOOK-replicar-prototipo-cowork.md`](../memory/requisitos/_DesignSystem/RUNBOOK-replicar-prototipo-cowork.md) (F0–F7). Este doc é **o "está fiel?"** que roda DENTRO da Fase 4 (APLICAR) e também sozinho pra auditar uma tela já viva. 1 tema = 1 doc: aqui NÃO se repete a orquestração.
>
> **Origem:** 2026-07-08 — Wagner: *"as máquinas não estão funcionando em conjunto com os hooks"* (âncora podre reincidente 07-06 → 07-08). A cola virou máquina fail-closed ([ADR 0326](../memory/decisions/0326-trava-ancora-compare-fingerprint.md)). Este RUNBOOK desenha o fluxo ponta-a-ponta que faltava.

## Onde encaixa
Dentro da **Fase 4 · APLICAR** (1 sessão limpa por tela), depois de mexer no `.tsx`, ANTES do PORTÃO do screenshot. É o loop que responde *"o que eu apliquei bate com o design?"* com números — complementa o `anchor-lint --check` (que fecha fidelidade **spec↔código**; este fecha fidelidade **design-renderizado↔prod-renderizado**).

## O loop 0→6

| # | Passo | Comando / ação | O que GARANTE |
|---|---|---|---|
| **0** | **Resolver a âncora** — nunca no olho | `node prototipo-ui/ancora.mjs <Mod/Tela>` | Devolve o `related_prototype` do charter (ex.: `financeiro-page.jsx`). É AQUI que se descobre qual é o proto — não chutando qual `.html` servir. |
| **1** | **Renderizar a âncora** | **MECANIZADO (2026-07-09):** `node prototipo-ui/render-proto-baseline.mjs --gerar <Mod/Tela>` faz 0→3 sozinho (resolve âncora, serve o bundle staging local, roteia o shell, assa a âncora, captura a matriz `{1280,1440}×{light,dark}`) e grava o **baseline versionado** (ver §Baseline abaixo). Manual (proto sem staging/rota): montar/servir **o `related_prototype`** (não o shell, não um png), no tema alvo. | O que você vai capturar É o design vigente. A máquina ainda confere **identidade staging==SSOT do repo** (hash normalizado ADR 0324) — staging driftado recusa (senão o `prototipo_sha` mentiria). |
| **2** | **Gerar o snippet ANCORADO** | `node prototipo-ui/style-fingerprint.mjs --snippet <Mod/Tela>` | Imprime `window.__ANCORA__="…";` + o snippet. Chama o `ancora.mjs` por dentro → a captura vai **declarar** a âncora. |
| **3** | **Capturar o PROTO** | Colar a saída do passo 2 no console da página do passo 1 → salvar `proto.json` | `proto.json` carrega `ancora:` (o related_prototype). |
| **4** | **Capturar a PROD** | Mesmo snippet ancorado, colar no console da tela viva (`page:` do charter), **mesmo tema** → `prod.json` | Captura comparável (mesmo vetor, mesmo tema — regra 5). |
| **5** | **Comparar com a TRAVA** | `node prototipo-ui/style-fingerprint.mjs --compare proto.json prod.json --tela <Mod/Tela>` | **Fail-closed:** re-resolve a âncora e confere `proto.json.ancora`. Proto errado (shell) → captura sem âncora ou divergente → **RECUSA exit 3**. Só compara contra a fonte certa. |
| **6** | **Ler o diff → agir** | padrão dominante (campo × nº + ⚠ SISTEMÁTICO) · triagem SO_* · verdito 1-linha | Alvos concretos de fix. Repete 1→6 até fiel. |

**A trava (passo 5) exige um dos dois** — senão RECUSA:
- `--tela <Mod/Tela>` → verifica a captura contra o charter (o caminho normal);
- `--sem-ancora <razão>` → opt-out **explícito e logado** (raro: comparar contra algo que não é a âncora).

## Cobertura obrigatória — multi-eixo + região (NÃO é 1 foto)
> Empírico 2026-07-08 (teste do protocolo na Financeiro/Unificado): uma captura só (1 tema · 1 largura · 1 estado · página cheia) **subestima e mente**. O loop 0→6 roda por:
- **Tema:** light **E** dark. Proto×prod sempre no MESMO tema (regra 5) — mas OS DOIS temas medidos: um pode divergir e o outro não.
- **Largura:** 1280 **E** 1440. **1280 = monitor real da persona Larissa (biz=4)** — não medir só no default.
- **Estados:** default + vazio/sem-resultado + loading/skeleton + erro + hover + drawer aberto. O vetor é foto de UM estado.
- **Região, não página:** escopar ao conteúdo da tela via [`recortar-regiao.mjs`](recortar-regiao.mjs)/[`analise-regiao.mjs`](analise-regiao.mjs). Capturar a página INTEIRA mistura shell (sidebar do ERP) + dados (linhas reais × mock) e infla os `SO_*`/miss com RUÍDO — na Unificado, 540 "miss" eram sidebar+dados, **não** gap de design.

**Direção NÃO é uniforme (não aplicar cego):** o proto pode estar ATRÁS do prod. Ex.: primário — prod `oklch(0.55 0.15 295)` bate o canon ([ADR 0190](../memory/requisitos/_DesignSystem/adr/ui/)) e o proto está em `0.7` → aplicar o protótipo **REGRIDE**. Cada `DIVERGE` precisa de julgamento de direção (**prod-fora-do-canon** × **proto-atrasado** × **ruído-de-dado**). Pareia com o anti-padrão "regredir tela à frente do protótipo" da skill `aplicar-prototipo`.

## Baseline versionado do PROTO (mecanizado 2026-07-09 · roubo #7, régua Applitools Eyes)
> O design renderizado vira **baseline commitado** — a prod passa a ser comparável contra a **INTENÇÃO** continuamente, não só contra o próprio passado (o `visual-regression` required compara prod×baseline-própria = REGRESSÃO; isto compara prod×PROTÓTIPO = DRIFT DE INTENÇÃO). Máquina: [`render-proto-baseline.mjs`](render-proto-baseline.mjs).

| Modo | Onde roda | O que faz |
|---|---|---|
| `--gerar <Mod/Tela>` | **SÓ LOCAL** (recusa sob CI, exit 4 — fronteira [ADR 0290](../memory/decisions/0290-fidelity-lock-v0-recusado.md): render pareado em CI passa verde quando os 2 lados quebram) | Loop 0→3 automático → grava `memory/requisitos/<Mod>/<tela>.proto-baseline.json` (matriz de fingerprints, âncora assada, **`prototipo_sha` carimbado** — padrão do map.json: proto re-exportou ⇒ STALE). |
| `--check` | **CI** (design-memory-gate, advisory de nascença — required só reabrindo a [ADR 0314](../memory/decisions/0314-poda-gates-onda-2-lei-fusoes.md)) | HERMÉTICO sobre o JSON commitado: schema + âncora re-resolvida do charter ([ADR 0326](../memory/decisions/0326-trava-ancora-compare-fingerprint.md)) + freshness por sha. Zero browser/rede. |
| `--extract <baseline> <1280\|dark>` | local | Tira 1 célula como `proto.json` → o **`--compare` EXISTENTE** (`style-fingerprint --compare proto.json prod.json --tela <Mod/Tela>`) roda prod×proto-baseline com a trava fail-closed de sempre (âncora ✓ + conteúdo F5 ✓). |
| `--nudge [files\|stdin]` | **CI** (design-memory-gate, advisory · exit 0 sempre) | **O compare possível em CI sem violar a 0290:** mapeia os arquivos da PR (`git diff --name-only`) pros baselines commitados do MESMO módulo (`Pages/<Mod>/**` incl. `_components/`) e imprime no step summary o comando LOCAL exato (extract + snippet + compare). Zero render, zero rede. |

**Direção NÃO é uniforme (PROD_A_FRENTE nunca regride):** o compare REPORTA, humano DECIDE — vale aqui em dobro, porque o baseline torna a comparação barata/frequente. Prova viva 2026-07-09: `Financeiro/Unificado` gerado end-to-end (4 células × 228 elementos, âncora ✓, conteúdo ✓ 19/82 — mesmos números da corrida manual 07-08), `--check` íntegro.

**O que o CI cobre × o que é dever-de-casa LOCAL (fronteira honesta, ADR 0290):**
- **CI mecaniza** (design-memory-gate, advisory): `--check` hermético (schema + âncora re-resolvida + **sha STALE quando o PROTO re-exporta** — cobre o lado-design) + `--nudge` (PR tocou `Pages/` de módulo com baseline → aponta o compare — cobre o lado-código). O CI compara **JSON commitado × JSON commitado/diff**, nunca renderiza.
- **LOCAL obrigatório** (o compare REAL): `--extract` + capturar `prod.json` na tela viva (mesmo tema) + `style-fingerprint --compare … --tela`. Não existe captura-do-lado-vivo commitada — commitá-la mentiria a cada PR de UI (stale silencioso); por isso o compare fica no loop 0→6 local, com o nudge lembrando no PR.

**Cobertura (2026-07-09):** `Financeiro/Unificado` (financeiro-page.jsx) · `Sells/Index` (vendas-page.jsx, `related_prototype` formalizado no charter — o `visual_source` já o declarava) · `Compras/Index` (compras-page.jsx) — 4 células `{1280,1440}×{light,dark}` cada, BRL redigido, identidade staging==SSOT ✓.
**Limitação declarada (não hackear):** as telas ancoradas em `financeiro-telas-extras.jsx` (Conciliação · DRE · Fluxo · Impostos) **não têm baseline mecanizado** — o arquivo não segue `<id>-page.jsx` e o shell (`app.jsx`) não tem rota standalone que o monte (a rota `financeiro` monta `FinanceiroPage initialTela="unified"`). Pra essas, o loop 0→6 segue **manual** (servir o proto na mão, passo 1 manual) até o shell ganhar rota própria por tela-extra.

## O que o vetor mede (25 campos + 3 passadas)
`tag,w,h,xnorm,ynorm,linhas,overflowX, fontSize,fontWeight,letterSpacing,lineHeight,textTransform,fontFamily, color,bgEfetivo,bgProprio,bgImage, radius,borderW,borderColor,boxShadow,padding,opacity,transform,display` + divisórias (inventário) + containers (layout-causa) + compostos/cards (superfície). Casamento por texto+tag; KPIs ambíguos (`<BRL>`) pareados por posição (furo 4); SO_* estrutural força triagem (furo 5).

## Os gates que cercam (não são deste loop, mas fecham a Fase 4)
Antes do PR, LOCAL: `layout-primitives-guard` · `casos:check` (trio `.tsx`+`.charter`+`.casos`) · `lint:baseline` · `tsc` · `phpstan` · `pageheader-gate` · PII scan. **PORTÃO:** screenshot 1280/1440 light+dark → **Wagner aprova o SCREENSHOT** → CI (`pr-ui-judge` + `visual-regression` + `contrato-de-tela`).

## Resíduo HONESTO (o que a máquina NÃO blinda)
- **Passo 1 manual segue disciplina** (o caminho mecanizado `render-proto-baseline --gerar` fecha isto pra telas com bundle staging + rota `<id>-page.jsx`): na mão, a trava confere que você **declarou** `financeiro-page.jsx` — não que o DOM capturado é de fato ele (o F5/overlap mitiga). Resíduo NOVO do caminho mecanizado: a rota do shell é heurística (`financeiro-page.jsx`→`financeiro`; `--route` corrige) e o baseline captura o estado DEFAULT — estados (vazio/hover/drawer) seguem no loop manual/harness.
- **Ambiente:** o `--compare` roda em **node local** e precisa dos 2 JSON em disco → exige Bash **co-localizado** com o browser (browser POSTa pra receiver local, ou salva à mão). Bash **sandboxed** (não alcança teu localhost, retorno do browser trunca) → rodar o comparador **dentro do browser** sobre os painéis (fallback validado 2026-07-08). Variante validada 2026-07-08: proto servido local (`python -m http.server`, harness Cowork completo com shell `oimpresso.com.html`) + **receiver POST em `127.0.0.1:8800`** — Chrome trata `127.0.0.1` como trustworthy, então **a aba https de prod consegue POSTar** o fingerprint sem mixed-content; o `javascript_tool` trunca retorno grande, por isso o handoff é via POST-em-disco, não via valor de retorno.
- **O map (`detectar-telas`) é CEGO a CSS e a comportamento** — só diffa o `.tsx` (estrutura, linha). Tokens/superfície/borda/tipografia vivem no **CSS computado**, e **este loop é a ÚNICA camada que os vê**. "Map limpo" ≠ "fiel ao design": as duas camadas são complementares (estrutura × pixel renderizado). Não somar diff de `.css` bruto ao map (bundle Cowork ≠ CSS do repo 1:1 → seria ruído); o fingerprint é o lugar certo pra CSS.

## Furos conhecidos & melhorias
> Revisão **adversarial** 2026-07-08 (histórico + hooks + settings + workflows lidos de `origin/main`). **Veredito do adversário:** o gate é *teatro no ponto de enforcement* — a ferramenta (`style-fingerprint`) é boa, mas **NADA dela é required em CI** e a superfície do erro (captura no browser) é não-hookável. Ranqueado impacto×esforço:

| # | Furo | Evidência | Melhoria | Resíduo | Status |
|---|---|---|---|---|---|
| **F1** | Erro mora no **browser** (navigate/paste/eval) — superfície não-hookável; nenhum PreToolUse vigia o ponto de decisão. Reincidiu 07-06→07-08. | `settings.json` só tem `block-ancora-no-olho` no matcher `Read`; o hook confessa "Chrome/paste escapam". | Enforcement na MÁQUINA (trava do `--compare`, feito hoje). + system-reminder da âncora resolvida antes de navegar. | Browser não-hookável; sem oráculo acima do charter. | **feito** (trava) + resíduo perene |
| **F2** | **Zero** da máquina de fidelidade é **required em CI** — `style-fingerprint`/`ancora.mjs`/`anchor-content-check` são advisory (`design-memory-gate.yml` NÃO está nos 23 required; rebaixado pela ADR 0314). | `git grep fingerprint .github/` → nada; `gh api .../protection` não lista design-memory-gate. | **Promover `anchor-content-check.mjs --check` a required** — determinístico, headless, sem browser: `related_prototype`=SHELL/MISSING passa a **falhar merge**. Custo ~0. | Só pega SHELL/MISSING; não "arquivo de outra tela do módulo". | **decisão Wagner** (mexe em required → reabrir ADR 0314) |
| **F3** | `--compare` é "lembre de rodar", não gate — o `exit 1` só é sentido se um humano invoca. | RUNBOOK-orquestração:88 chama de "mecanismo" mas é doc; ADR 0315 "doc advisory = canal que o agente não lê". | **Stop-hook nudge**: sessão que tocou `Pages/**` de fidelidade sem log de `--compare` recente → avisa (pattern `design-compare-protocol.mjs`). | Precisa 2 páginas vivas → impossível gatear headless. | proposto |
| **F4** | **Matcher de Chrome com casing errado** — `mcp__Claude_in_Chrome__.*` (settings.json:154) **nunca casa** a tool real minúscula `mcp__claude-in-chrome__navigate`. Mesmo bug no regex `post-merge-ui-smoke-required.ps1:107` → chamada Chrome minúscula **não conta** como smoke. | naming das tools (`mcp__claude-in-chrome__*`) × matcher capitalizado, case-sensitive. | Trocar pra `mcp__[Cc]laude.?[Ii]n.?[Cc]hrome__.*` nos dois lugares. **Trivial.** Explica a dor recorrente do UI-smoke garantido. | nenhum (bug puro) — *verificar case-sensitivity ao vivo*. | **trivial — corrigir** |
| **F5** | A trava passa um **CLAIM**: `window.__ANCORA__` é assado do ARGUMENTO `--snippet <tela>`, não do DOM. Colar o snippet ancorado no tab do **shell** → `proto.ancora` certo, DOM errado → `--compare` **PASSA**. O incidente 07-08 ainda passaria. `--sem-ancora` é bypass grátis. | `verificarAncora` checa `protoFp.ancora===esperada`; resíduo textual na ADR. | **FEITO** (`overlapConteudo`): extrai os rótulos distintivos do `.jsx` da âncora e cruza com o texto da captura; overlap baixo → **recusa**. E2E: shell disfarçado (0/237 rótulos) agora recusa. Claim → evidência fraca-porém-real. | JSX não-renderizado offline → só overlap de texto, não fidelidade visual; `--sem-ancora` ainda é bypass logado. | **feito** |
| **F6** | `anchor-content-check` (o sentinela do 07-06) é **advisory** e cego a "tela certa do mesmo módulo". | session 2026-07-06; "ligado advisory, lei 0314". | Promover a required (= F2). | wrong-file intra-módulo sem oráculo. | **decisão Wagner** (= F2) |
| **F7** | Referência **morta**: `block-mwart-violation.ps1` cita `mwart-gate.yml` **deletado** (ADR 0271 onda 2). | `git cat-file -e origin/main:.github/workflows/mwart-gate.yml` → deleted. | Remover a menção morta + ajustar a mensagem do override. | nenhum. | **trivial — corrigir** |

**Meta-achado (composição):** `ancora.mjs` (resposta certa) era advisory e `--compare` aceitava qualquer JSON — nunca se falaram. A trava de hoje os conecta por subprocesso fail-closed, mas **herda o teto do F5** (claim) e **não resolve F2** (nada é required). Princípio a internalizar: *superfície não-hookável → enforcement dentro da máquina que produz o artefato, como input obrigatório fail-closed — **e essa máquina precisa ser required**, senão continua sendo lembrança.*

## Pareado com
- [`RUNBOOK-aplicar-prototipo-orquestracao.md`](RUNBOOK-aplicar-prototipo-orquestracao.md) (fases −1…5 — a Fase 4 chama este loop) · [`ancora.mjs`](ancora.mjs) (resolve a âncora) · [`style-fingerprint.mjs`](style-fingerprint.mjs) (o vetor + a trava) · `anchor-lint` (fidelidade spec↔código) · skills `cowork-prototype-replication` · `mwart-process`.
