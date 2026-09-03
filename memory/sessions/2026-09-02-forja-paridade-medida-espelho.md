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

Dia inteiro sobre **uma** pergunta: o que falta pra Forja ficar igual ao protótipo. **Manhã** — espelho do Cowork completado e primeira comparação **por sonda** em 5 pares (o primary 0,55 × 0,70 aparece em todos, e a causa é o componente ignorando o token dark). **Tarde** — ADR 0388 ("réplica primeiro") + Ondas 1, 2 e 2.1 no ar, com os 3 DIVERGE re-medidos **iguais** ao protótipo a 2560. **Noite** — Ondas 3, 4 e 8 em réplica; a **ERRATA** que derrubou a linha "Larguras menores" (o rail do protótipo vinha de `localStorage` poluído por um `innerWidth: 0` do Browser pane, não de regra de largura — a ≤1279, não a 1280); e a medição de **produção a 1280**, que fechou o "não medido" da 2.1 e **quantificou o defeito**: 1 de 6 destinos do topnav fora da viewport com a sidebar `expanded`. Decisão do dia: [ADR UI-0030](../requisitos/_DesignSystem/adr/ui/0030-sidebar-auto-rail-responsivo.md) — produção passa a fazer auto-rail a ≤1280, com a escolha manual vencendo.

**Pendências que saem do dia:** `--accent` dark na fundação (hoje escopado à Forja) · bundle v2 do Cowork · e o **MCP inalcançável a sessão inteira** (medido com controle positivo), então nenhuma task foi tocada e nenhum markdown de task foi criado ([ADR 0070](../decisions/0070-jira-style-task-management-current-md-removed.md)).

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

---

## Sessão da noite (sessão própria) — Onda 8: a view MCP vira réplica e o painel Handoffs volta pra dentro

> Continuação do mesmo tema no mesmo dia — por isso **apende aqui** em vez de abrir session log paralelo (proibicoes §Memória: se já existe similar, edita). O que está acima é da sessão da manhã/tarde e **não foi tocado**.

**PR:** [#6575](https://github.com/wagnerra23/oimpresso.com/pull/6575) · **Onda 8 do §11** (`MCP + Handoffs dentro`).

### O que a onda entregou

`/forja/mcp` passou ao vocabulário do bundle (`fj-mcp*` · `fj-perm*` · `fj-token*` · `fj-audit*` · `fj-ho-*`) na ordem do protótipo — **intro `mockado` → Handoffs F1→F3 → grid [contrato | tokens] → auditoria** — e o painel de handoffs voltou pra dentro da aba, cumprindo a promessa que o comentário do `FORJA_TABS` registrava desde a Onda 2 (*"Handoffs (seção do MCP, Onda 8)"*). A rota `/forja/handoffs` **segue viva** e renderiza o **mesmo componente**, com a **mesma projeção** (`ForjaMcpService`): um dono, dois pontos de render. Nasceu também o `ForjaRoleBadge` — o selo de ator do protótipo (`window.FjRoleBadge`), cujas classes já desciam no bundle da Onda 1 sem ninguém usá-las.

### A causa-raiz do D4, que era CSS ausente e não markup

O `DIVERGE` de tipografia da rodada da manhã (*"col0/col2 sem mono × mono"*) não se resolvia copiando markup: **`.mono` é utilitária do SHELL do protótipo** (`styles.css:1740`) e **não existe em produção** — 0 ocorrências globais em `resources/css/*.css`; o bundle só a traz escopada em 3 pontos. Medido no protótipo: os **6** lugares que a view usa (`fj-token-id`, `fj-audit-ts|tool|args`, `fj-ho-slug`, `fj-ho-pr`) são monoespaçados e **nenhum** tem regra própria. Sem isso o DOM ficaria igual e o **render diferente** — LC-08 na forma mais silenciosa. Desceu escopada (`.fj-mcp .mono, .fj-ho .mono`).

### O erro que quase virou número falso (registrado, não apagado)

A primeira medição do protótipo devolveu `color: rgb(0,0,0)` em toda a tabela e `--text-dim` **vazio** no `:root`. Eu ia tratar aquilo como "cor divergente". Não era: o `_ds/` (gitignored) estava ausente no espelho e `colors_and_type.css` + `cockpit_domains.css` carregaram com **0 regras** — o protótipo renderizou sem token nenhum. O portão `cowork-mirror-freshness --preview-ds` (fail-closed) repôs 10 deps e a medição foi refeita. **Lição operacional:** o `--preview-ds` roda ANTES de medir, não depois — sem ele qualquer cor lida é lixo com aparência de dado.

Dois outros deslizes menores, corrigidos no caminho: (1) rodei `replica-inconsistencias --prototipo` com o glob **entre aspas**, ele não expandiu e o relatório perdeu os 13 itens `origem = prototipo` (101→90); re-rodado sem aspas deu 102 e `exit 0`. (2) escrevi a continuação de linha do YAML da lane com `\` num heredoc e a **barra colapsou** (LC-26) — refeito com `chr(92)`, conferido em `cat -A`.

### Verificação

Fonte provada ANTES de tocar código: `forja-page.css` (`9c180a5d92ae`) e `forja-page.jsx` (`e4339537969d`) baixados do Cowork vivo por `DesignSync.get_file` e medidos contra o espelho → **`igual` nos dois**. O `forja-mcp.jsx` **não pôde ser medido por hash** (31 KB volta inline pelo `get_file`, abaixo do teto de persistência) — mitigado por conferência estrutural contra o vivo que li: 9 linhas de contrato, 6 de auditoria, 3 tokens, 6 handoffs e o `<HandoffPanel/>` dentro do `ForjaMCPView`, todos batendo.

Gates locais verdes: `foundation` · `conformance` · `stylelint` · `css:size` · `layout` · `casos-coverage-guard` · `baseline-tamper-guard` · `build:inertia` · `tsc` (0 erro novo). **`eslint-baseline` fechou em delta 0** — apareceu +1 (`react-refresh/only-export-components`), era **minha e evitável** (exportei um helper junto do componente): consertada, **não absorvida**. `ds-guard` segue bloqueando por `--dev-*(4)`: **provado herdado** rodando o blob de `origin/main` no mesmo diretório — veredito idêntico, delta zero.

**Teste novo:** `ForjaMcpHandoffsInlineTest` (3 casos citando `UC-FORJA-15`), nas **duas** lanes. Ele não testa classe CSS de propósito — isso é do `design-diff`, e duplicar régua consolidada é proibido; testa o que some **em silêncio**: sem as props deferidas o `<Deferred>` fica em fallback eterno, sem erro no console.

### O que NÃO fechou, e é honesto dizer

O **`compare 0 bug` do §11 exige produção deployada** — merge de `.tsx` é humano (ADR 0283). A linha 8 do §11 ficou **🧪 código no ar em PR**, não ✅. Os valores-alvo do lado design ficam **medidos** no visual-comparison pro pós-deploy ser um `--compare` direto.

`visual-regression` vermelho: as telas em zona cinza são **`Ponto/Dashboard` (0,21%)**, **`Jana` (0,29%)** e **`financeiro-unificado · selecionar-lote`** — a **mesma lista herdada** que a sessão da tarde declarou, e **nenhuma delas é desta onda** (as minhas, `team-mcp/Forja*`, que o raio detectou, passaram). Medi que o check **não é required** (união de 45 contexts do classic + ruleset), logo não bloqueia merge — e por isso **não apliquei** `visreg-gray-approved`: aprovar zona cinza de outros módulos não é escopo desta onda.

**Nota de instrumento:** `ancora.mjs TeamMcp/Forja/Cockpit` responde *"sem charter pra essa tela"*; a query que resolve é **`Forja/Cockpit`** (o path real é `team-mcp/`, não `TeamMcp/`). O charter existe e a âncora confirma `related_prototype: forja-page.jsx` com frescor verificado.

## Noite (sessão paralela) — produção a 1280 medida, e a conclusão que a ERRATA corrigiu

Fechei o *"Produção a 1280 não foi medida"* da Onda 2.1. **A conclusão que tirei estava errada e vai registrada corrigida:** eu disse *"o protótipo raila a 1280 e produção não ⇒ divergência de shell ⇒ decisão [W]"*, tomando o **registro narrativo** antigo (`rail 56 · 3 linhas · 174,4px`) como se fosse medição. A ERRATA desta mesma noite prova que aquele retrato vinha de `localStorage` poluído: com a chave limpa, o protótipo a **1280** dá `260px 1020px` — **igual à produção**; o rail é a **≤1279**. Não havia divergência de shell a 1280, e a decisão já estava tomada ([UI-0030](../requisitos/_DesignSystem/adr/ui/0030-sidebar-auto-rail-responsivo.md)). **LC-08 no mesmo vetor que a errata descreve** — duas sessões caíram nele na mesma noite, por caminhos diferentes.

**O que sobreviveu e valeu:** a medição do lado **produção**, que ninguém tinha feito. Com a sidebar `expanded`, `.fj-viewtabs` termina em **x=1318** (38px além da viewport) e o **Integrador (borda 1315) nasce fora da tela** — 1 de 6 destinos. Alternando para `rail`: `56px 1224px`, overflow **38 → 0**, cortadas **1 → 0**. Isso **quantifica o defeito que a UI-0030 conserta** e valida o remédio no próprio ambiente, em vez de deixá-lo por argumento.

**Método (vale além desta tela):** `resize_window` devolveu `"Successfully resized"` **sem redimensionar** (`innerWidth` parado em 2560) — só não virei vítima porque conferi o `innerWidth`, não a mensagem; e **não há Chrome** aqui, a extensão roda no **Brave**. Junto com o `innerWidth: 0` que a errata documenta: **medição de largura tem que provar a largura antes de medir qualquer outra coisa.** Viewport obtida por iframe same-origin de 1280px, com 781/781 nós estáveis.

**Tarefa das tasks no MCP: parada.** Controle positivo `oimpresso.com/login` **200 em 1,07s** · DNS resolve · ICMP responde 1ms · `/api/mcp` **000/rc=28** · TCP 443 **falha**. Host de pé, serviço HTTPS fora. Zero task tocada, zero markdown de task (ADR 0070). E `list_sessions` mostrava **8 sessões rodando** nas Ondas 3–11 — criar tasks às cegas duplicaria trabalho em curso.
