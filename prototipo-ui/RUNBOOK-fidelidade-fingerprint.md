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
| **1** | **Renderizar a âncora** | Montar/servir **o `related_prototype`** (não o shell, não um png), no tema alvo. | O que você vai capturar É o design vigente. **Único ponto que ainda depende de disciplina** (ver Resíduo). |
| **2** | **Gerar o snippet ANCORADO** | `node prototipo-ui/style-fingerprint.mjs --snippet <Mod/Tela>` | Imprime `window.__ANCORA__="…";` + o snippet. Chama o `ancora.mjs` por dentro → a captura vai **declarar** a âncora. |
| **3** | **Capturar o PROTO** | Colar a saída do passo 2 no console da página do passo 1 → salvar `proto.json` | `proto.json` carrega `ancora:` (o related_prototype). |
| **4** | **Capturar a PROD** | Mesmo snippet ancorado, colar no console da tela viva (`page:` do charter), **mesmo tema** → `prod.json` | Captura comparável (mesmo vetor, mesmo tema — regra 5). |
| **5** | **Comparar com a TRAVA** | `node prototipo-ui/style-fingerprint.mjs --compare proto.json prod.json --tela <Mod/Tela>` | **Fail-closed:** re-resolve a âncora e confere `proto.json.ancora`. Proto errado (shell) → captura sem âncora ou divergente → **RECUSA exit 3**. Só compara contra a fonte certa. |
| **6** | **Ler o diff → agir** | padrão dominante (campo × nº + ⚠ SISTEMÁTICO) · triagem SO_* · verdito 1-linha | Alvos concretos de fix. Repete 1→6 até fiel. |

**A trava (passo 5) exige um dos dois** — senão RECUSA:
- `--tela <Mod/Tela>` → verifica a captura contra o charter (o caminho normal);
- `--sem-ancora <razão>` → opt-out **explícito e logado** (raro: comparar contra algo que não é a âncora).

## O que o vetor mede (25 campos + 3 passadas)
`tag,w,h,xnorm,ynorm,linhas,overflowX, fontSize,fontWeight,letterSpacing,lineHeight,textTransform,fontFamily, color,bgEfetivo,bgProprio,bgImage, radius,borderW,borderColor,boxShadow,padding,opacity,transform,display` + divisórias (inventário) + containers (layout-causa) + compostos/cards (superfície). Casamento por texto+tag; KPIs ambíguos (`<BRL>`) pareados por posição (furo 4); SO_* estrutural força triagem (furo 5).

## Os gates que cercam (não são deste loop, mas fecham a Fase 4)
Antes do PR, LOCAL: `layout-primitives-guard` · `casos:check` (trio `.tsx`+`.charter`+`.casos`) · `lint:baseline` · `tsc` · `phpstan` · `pageheader-gate` · PII scan. **PORTÃO:** screenshot 1280/1440 light+dark → **Wagner aprova o SCREENSHOT** → CI (`pr-ui-judge` + `visual-regression` + `contrato-de-tela`).

## Resíduo HONESTO (o que a máquina NÃO blinda)
- **Passo 1 é disciplina, não máquina.** A trava confere que você **declarou** `financeiro-page.jsx` — não que o DOM capturado é de fato ele. Montar o shell mas declarar a âncora certa **passa**. O browser é não-hookável; sem oráculo formal acima do charter. Mitigação: renderizar o `related_prototype` literal (passo 1) é o único caminho honesto; auditável depois pelo `url`/conteúdo da captura.
- **Ambiente:** o `--compare` roda em **node local** e precisa dos 2 JSON em disco → exige Bash **co-localizado** com o browser (browser POSTa pra receiver local, ou salva à mão). Bash **sandboxed** (não alcança teu localhost, retorno do browser trunca) → rodar o comparador **dentro do browser** sobre os painéis (fallback validado 2026-07-08).

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
