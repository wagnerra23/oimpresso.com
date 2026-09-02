---
date: "2026-09-02"
hour: "08:00 BRT"
duration: "1.5h"
topic: "Forja — o que falta pra aparência bater com o protótipo: espelho completado e primeira medição por sonda em 5 pares"
authors: [W, C]
outcomes:
  - "Espelho da Forja provado no dia: shell e styles.css estavam STALE e 4 adaptadores cli-* estavam ausentes — sem eles Trabalho e Integrador quebravam no protótipo local"
  - "Primeira comparação medida (design-diff) em 5 pares: primary 0,55×0,70 em todos (componente ignora o token dark), KPI valor 22px×17/28/30px, Trabalho 3×13 colunas e 1×3 linhas de filtro, MCP sem mono/cor própria, Changelog 5×2 colunas"
  - "Bloqueio de transporte declarado: bundle do DS local publica 44 componentes, o vivo 55 (sem Segmented) e o get_file devolve o vivo TRUNCADO — só o pacote v2 resolve, e ele não tem dono"
prs: []
us: []
related_adrs:
  - "0374-emenda-0315-espelho-cowork-e-rota-prevista"
  - "0367-cockpit-unico-forja-project-mgmt-morre"
---

# Forja — paridade protótipo × produção, medida (2026-09-02)

**Pergunta do [W]:** *"confira o protocolo e máquinas — o que falta para o módulo Forja ficar igual ao protótipo? a aparência"*, com as instruções *"considere as sessões paralelas"* e *"controle tudo"*. Hipótese dele no meio: *"falta css, acho que a camada do DS"*.

## TL;DR

Dia inteiro sobre **uma** pergunta: o que falta pra Forja ficar igual ao protótipo. **Manhã** — espelho do Cowork completado (shell e `styles.css` STALE, 4 adaptadores `cli-*` ausentes) e primeira comparação **por sonda** em 5 pares: o primary 0,55 × 0,70 aparece em todos, e a causa é o componente ignorando o token dark, não o protótipo fora do canon. **Tarde** — ADR 0388 ("réplica primeiro") + Ondas 1, 2 e 2.1 no ar; os 3 DIVERGE que a sonda pegou em prod foram corrigidos e re-medidos **iguais** ao protótipo a 2560. **Noite** — produção medida a **1280** (o monitor do [W]), fechando o "não medido" que a 2.1 tinha declarado: com a sidebar no default, **1 dos 6 destinos do topnav (`Integrador`) nasce fora da viewport**, e a causa é **shell** (`cockpit.css` não tem auto-rail por largura), não CSS da Forja ⇒ decisão [W], não PR de código.

**Pendências que saem do dia:** `--accent` dark na fundação (hoje escopado à Forja) · auto-rail a ≤1280 · bundle v2 do Cowork · e a **Tarefa B parada**: o MCP ficou inalcançável a sessão inteira (medido com controle positivo), então nenhuma task foi tocada e nenhum markdown de task foi criado ([ADR 0070](../decisions/0070-jira-style-task-management-current-md-removed.md)).

## Sessões paralelas

`Aplicar forja do protótipo` estava RODANDO na branch `claude/forja-prototipo-f3cafb` com o [#6537](https://github.com/wagnerra23/oimpresso.com/pull/6537) aberto (topnav 13 → 9, toca `ForjaHub.tsx` · `core_topnavs.php` · `Cockpit.casos.md` · `PARIDADE-area-forja-diagnostico-e-ondas.md`). Esta sessão **não tocou nenhum desses arquivos** — trabalhou só no espelho (`prototipo-ui/cowork/**`), no ledger e no dono do inventário por tela (`forja-cockpit-visual-comparison.md`).

## Máquinas rodadas (na ordem do painel)

1. `protocolo.config.mjs` + `--selftest` ✓ · `--sla` (7/254 medidos ontem, 157 vivos fora do espelho) · `--manifest --all` (7 âncoras da Forja).
2. `ancora.mjs` nas 17 telas: 3 apontam pra `forja-page.jsx` (Cockpit, Trabalho, Aprovações); 2 sem `related_prototype` (Board, Gantt); 12 declaram `n/a` legítimo.
3. `status.mjs --check-mapping`: os 3 alvos `ANCHORED` sem `map.json` ("gerar/registrar map.json antes de aplicar").
4. **Fonte provada** pelo DesignSync (16 `get_file` → extração por script do transcript → `--snapshot-from` → `--compare --check`): `oimpresso.com.html` e `styles.css` STALE · `cli-seg.js`/`cli-tabs.jsx`/`cli-pagehead.jsx`/`cli-kebab.jsx` AUSENTES · os 7 `forja-*` SYNC. `--export-from` (fiel por construção) → `--manifest` → `--preview-ds` ✓ → `--compare --check --ledger` (14 SYNC · 0 stale).
5. **Sonda `design-diff --probe`** injetada nos dois renders (mesmo tema dark, espera de render, 2 leituras estáveis) e `--compare --check` em 5 pares. Resultado no [dono do inventário](../requisitos/TeamMcp/forja-cockpit-visual-comparison.md) §2026-09-02.
6. D1 (rede): chip de ordenação em `/forja/trabalho` = GET Inertia parcial, marcador sobreviveu.
7. Gates do espelho: `cowork-ssot-guard` ✓ · `--unverified --check` ✓ · `anchor-content-check --check` ✓ · `--check-orfaos` ✓.

## O que falta pra "ficar igual" — por camada, do mais barato ao mais caro

1. **Token do primary no dark (1 arquivo):** `PageHeaderPrimary.tsx:70` fixa `oklch(0.55 0.15 295)` inline; o canon dark (`_generated-inertia-dark.css`, emenda 2026-07-08 "fidelidade ao proto") é `oklch(0.7 0.15 295)`. É a D6 vermelha dos 5 pares — e afeta todo módulo que usa o PageHeader canon, não só a Forja.
2. **Camada de DS do módulo:** não existe `resources/css/cowork-forja-bundle.css`; as telas de produção não têm um `fj-*`/`ap-*` sequer. O protótipo vive de `forja-page.css` (97 KB, 765 seletores `.fj-` + 76 `.ap-`). A regra da casa é bundle inteiro primeiro, customização depois — e a lápide de 2026-07-10 manda provar que nenhum consumidor renderiza em portal antes de "deduplicar" token.
3. **Composição por tela** (a parte cara, e é onda por onda — [PARIDADE §7](../requisitos/Forja/PARIDADE-area-forja-diagnostico-e-ondas.md)): Aprovações (mesa inteira: herói + ao-vivo + fila + painel + placar), Trabalho (KPI que filtra, 3 linhas de filtro, linha de 13 colunas, segmentado Lista|Quadro|Gantt), MCP (painel Handoffs dentro do MCP, mono e cor própria nas células), Changelog (linha dot+corpo com 5 blocos, sessão com título), Saúde (sparkline + `ver →` por KPI).
4. **Topnav:** 13 → 9 já no #6537; 6 em 3 grupos-pílula depende de construção (Handoffs/Equipe no MCP, Sessões no Changelog, Triagem em Aprovações).

## Bloqueio de transporte (decisão fora do meu alcance)

O bundle do DS que o espelho consome (`mirror-snapshot/_ds_bundle.js`) é o do pacote de **24/08** (44 componentes). O vivo publica **55** — inclui `Segmented`, que a Lista|Quadro|Gantt do protótipo usa via `window.CliSeg`. O `get_file` devolve o bundle vivo **TRUNCADO** (290 KB > 256 KiB), então o segmentado do protótipo **não renderiza localmente** até o Cowork regerar o pacote v2 — pedido formal já enviado em 2026-09-01 (`CODE_NOTES.prompt-cowork-regenerar-bundle-por-ciclo-2026-09-01.md`); o `sync/bundle.manifest.json` vivo confirma o mesmo `bundleId` de 24/08.

## Não fiz, de propósito

- Não editei `.tsx`/`.css` de produção (merge de `.tsx` é humano, e a onda 0 é decisão [W] — US-FORJA-006).
- Não criei `contrato/forja*.contract.json` (copy literal é slot do [W]).
- Não remendei o espelho à mão (ADR 0374) — tudo desceu por `--export-from` a partir do JSON do `get_file`.

---

## Tarde (10:00 → 13:50 BRT) — a missão: "igual ao protótipo, revogar o resto" · ADR 0388 · Ondas 1, 2 e 2.1 no ar

**Decisão [W] (textual, 2026-09-02):** *"pode fazer igual ao protótipo e revogar todo o resto (…) se tiver que apagar para refazer de novo, faça. Eu apenas quero que trace uma meta de conseguir fazer o mesmo layout"* · *"tem muita regra preexistente que proíbe de fazer igual ao protótipo. isso é errado"* · *"quero que isso sirva para todo o protótipo (…) poderia ter uma lista de inconsistências? para o code resolver depois de aplicar"* · *"pode merge e compare em produção"*.

**O que shippou (PRs mergeados por [C] sob a autorização acima):**

| PR | o quê | recibo |
|---|---|---|
| [#6543](https://github.com/wagnerra23/oimpresso.com/pull/6543) | Onda 0 — decisão no SPEC (US-FORJA-006) + PARIDADE §11 (meta medida + 11 ondas) | merge |
| Onda 1 | `cowork-forja-bundle.css` verbatim + baselines (foundation · conformance · fontramp · stylelint · css-size) | gates verdes |
| [#6547](https://github.com/wagnerra23/oimpresso.com/pull/6547) | **ADR 0388 "réplica primeiro"** + reporter `replica-inconsistencias.mjs` (R1/R3/R4/FONTRAMP/IMPORTANT/HEX/FLEX-CRU/PALETA/SINTAXE, receitas por regra) + `ds-guard --report` + pedido ao Cowork | 101 itens listados pra Forja |
| [#6553](https://github.com/wagnerra23/oimpresso.com/pull/6553) | **Onda 2** — header do Cockpit é o do protótipo (6 destinos · 3 pílulas · na linha do título), 6 rotas, `/forja/integrador` novo, bundle importado no `ForjaHub` | deploy `e1412acef3`; `/forja/integrador` 404→302 |
| [#6563](https://github.com/wagnerra23/oimpresso.com/pull/6563) | **Onda 2.1** — 3 DIVERGE que a sonda pegou em prod (padding do `@media` copiado como base · `line-height` do preflight · `--accent` dark 0,55×0,70 escopado) + baseline regenerada | deploy `a91ce0cd5c`; re-medido = protótipo |
| [#6565](https://github.com/wagnerra23/oimpresso.com/pull/6565) | recibo em docs (visual-comparison + PARIDADE §11) | — |

**O que a sonda provou (prod × protótipo, mesma sonda, dark, 2560):** depois da 2.1, header 88,4px `12px 24px` · botão 25px `line-height: normal` · pílula ativa e primária `oklch(0.7 0.15 295)` · kbtn 31px — **iguais**. D1 parcial (marcador sobreviveu a 2 cliques, `GET 200` Inertia). Ressalva: a 1280 o **shell** do protótipo vira rail 56px e o header quebra em 3 linhas; prod só faz rail por toggle — fundação, não Forja.

**Erros meus registrados no caminho (não apagados):** (1) a Onda 2 gravou o padding do `@media (max-width:1100px)` como "valor efetivo" — lido do CSS, não medido (LC-08); a sonda em prod pegou. (2) Cherry-pick errado de um PR de baseline antigo (#6467), revertido e reaberto com pedido de desculpas. (3) `\|` em `grep -E` barrado pelo hook `block-sonda-que-mente` (P4) — corrigido.

**Gates que precisaram de absorção (todas com `BASELINE-ABSORB:` no commit que toca a baseline):** ESLint ds/no-os-btn + ds/no-inline-tablist (réplica, ADR 0388) · conformance 0→1 (branco literal do primary) · foundation +4 tokens escopados (`inertia.css: 74` que o `--write` removeu foi restaurado) · css-size +26. Label `visreg-gray-approved` aplicada em #6553 e #6563 pela zona cinza **herdada** (Jana · Ponto/Dashboard · Fiscal/Cockpit · financeiro-unificado) — a baseline dessas telas segue divergente, declarado em comentário.

**Pendente pro [W]:** reconciliar `--accent` dark na fundação (0,55 × 0,70 do protótipo) — hoje escopado à Forja; Cowork regenerar o bundle v2 e consertar na fonte as inconsistências listadas (pedido em `prototipo-ui/CODE_NOTES.prompt-cowork-inconsistencias-na-fonte-2026-09-02.md`).

## Noite — produção a 1280 MEDIDA (fecha o "não medido" da 2.1) · MCP fora, Tarefa B parada

**A pergunta.** A rodada da tarde declarou honestamente: *"Produção a 1280 não foi medida (o Browser pane não tem a sessão; a janela do Chrome não aceitou o resize)"*. 1280 é o monitor do [W], então era o número que faltava.

**Como consegui a viewport (as duas tentativas anteriores caíram, e a razão importa).** `resize_window` do Chrome MCP devolveu **"Successfully resized window ... to 1280x900"** e o `innerWidth` **continuou 2560** — instrumento afirmando sucesso sem ter feito. Só peguei porque conferi o `innerWidth` depois, não a mensagem (LC-15 / §5 2026-07-29). Enumerando as janelas por Win32 descobri o resto: **não existe processo Chrome** — a extensão roda no **Brave**, em 2 janelas, nenhuma com a Forja em foco, então redimensionar "a janela do Chrome" mexeria no desktop do [W] sem sequer acertar o alvo. Solução: **iframe same-origin de 1280px** injetado na própria aba autenticada — `contentWindow.innerWidth === 1280` conferido **antes** de medir, montagem do Inertia esperada até **duas leituras iguais** de `querySelectorAll('*')` (781/781, §5 2026-08-24), tema dark, `data-sidebar="expanded"` (o estado real do [W]).

**O achado — 1 de 6 destinos nasce fora da tela.** Com a sidebar no default (260px), o `.cockpit` fica `260px 1020px 0px` e `.fj-viewtabs` se estende até **x=1318**, 38px além da viewport. Por borda direita: Aprovações 731 · Trabalho 819 · Saúde 967 · MCP 1032 · Changelog 1216 · **Integrador 1315 → fora**. Não há scroll de página (`scrollWidth === clientWidth === 1280`); o `.main-body` absorve com 38px de `overflow-x`. Header = **136,4px em 2 linhas** (`.os-page-h-l` y=12 × `.os-page-h-r` y=91,4), com o padding `12px 24px` da Onda 2.1 **resistindo** a 1280.

**A causa é o shell, e a prova está no CSS de `origin/main`.** O `@media (max-width:1280px)` do `cockpit.css` (L57-59) **dispara** e colapsa a coluna direita (320→0, batendo com o `0px` medido), mas mantém 260px de sidebar — rail de 56px só sob `[data-sidebar="rail"]` (L55). **Não há auto-rail por largura.** Alternando o atributo para `rail` na mesma página, sem tocar em CSS: overflow **38→0**, cortadas **1→0**. Mas o header segue 136,4px/2 linhas — produção **nunca** reproduz as 3 linhas/174,4px do protótipo. Vai para decisão [W] (auto-rail a ≤1280 · e, separadamente, wrap 2×3 linhas), **não** para PR de código: o `@media` é shell de todos os módulos.

**Limite declarado.** O protótipo **não** foi re-medido; o JSON dele a 1280 **não existe** nas fontes que o pedido citava — procurei no corpo do #6563, nos **72** comentários, nos review-comments (0) e neste próprio log: zero ocorrência de `1280`, `174` ou `rail`. Comparei contra o **registro narrativo** do visual-comparison, e isso está dito lá.

**Tarefa B (tasks da Forja no MCP) — PARADA, com recibo.** As tools `mcp__oimpresso__*` não estão carregadas nesta sessão, e o servidor **segue inalcançável** — medido com controle positivo, não suposto:

| sonda | resultado |
|---|---|
| controle `oimpresso.com/login` | **200 em 1,07s** (rede e curl funcionam) |
| DNS `mcp.oimpresso.com` | resolve → 177.74.67.30 |
| ICMP no IP | **responde, 1ms, 0% perda** (host vivo) |
| `/api/mcp` (endpoint do `.mcp.json`) | **000, rc=28** — timeout em 21s |
| TCP 443 direto | **falha** (rc=124, nem completa handshake) |

Diagnóstico: **o host está de pé, o serviço HTTPS não aceita conexão.** Nenhuma task foi atualizada e **nenhum arquivo de task em markdown foi criado** ([ADR 0070](../decisions/0070-jira-style-task-management-current-md-removed.md)) — a instrução do pedido era exatamente essa: registrar e parar.

**Um dado que muda a Tarefa B quando o MCP voltar:** `list_sessions` mostra **8 sessões rodando agora** justamente nas Ondas 3, 4, 5-6, 8, 9, 10 e 11 da Forja (`forja-onda4-trabalho-lista`, `forja-onda8-mcp-handoffs`, `forja-onda9-changelog`, `forja-onda10-integrador-tabs`, `forja-onda-11-revogacao`, `forja-saude-view`, `busy-swartz` = Onda 3, `forja-ondas-5-6-quadro-gantt`). Criar uma task por onda 3–11 às cegas, como o pedido previa, **duplicaria trabalho já em curso** — quando o servidor voltar, o passo certo é `tasks-list module:Forja` **antes** de qualquer `tasks-create`, e conferir contra essas sessões.
