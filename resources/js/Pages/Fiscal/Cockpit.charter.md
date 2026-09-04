---
id: resources-js-pages-fiscal-cockpit-charter
page: /fiscal
component: resources/js/Pages/Fiscal/Cockpit.tsx
related_prototype: prototipo-ui/cowork/fiscal-page.jsx
bundle_source: fiscal-page.jsx
page_id: fiscal-cockpit
url: /fiscal
module: Fiscal
status: draft
created: 2026-05-20
owner: wagner
related_us: [US-FISCAL-002]
related_adrs: [0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0358-doutrina-de-teste-tenant-98-supersede-0101, 0104-processo-mwart-canonico-unico-caminho, 0114-prototipo-ui-cowork-loop-formalizado]
prototypes: [prototipo-ui/cowork/fiscal-page.jsx]
---

# Charter — `Fiscal/Cockpit`

## Mission

Dar à pessoa fiscal (Eliana contadora + Wagner operador) **visão consolidada do estado fiscal do mês** em até **3 segundos** — KPIs de emissão (NF-e/NFC-e/NFS-e/faturamento), alertas determinísticos críticos (rejeições + cert **vencido ou** vencendo + DF-e pending), e quick links pras 6 sub-páginas operacionais.

## Goals (Definition of Done PR #2)

1. **6 KPI cards eager** (não-deferred — first paint): emitidas mês, autorizadas + pct, rejeitadas (com pulse), faturamento, DF-e pending, cert vencimento dias
2. **Mini-sparklines SVG** nos **3** KPIs que o protótipo marca — emitidas, autorizadas, rejeitadas (últimos 14 dias). ⚠️ Corrigido 2026-09-04: dizia **4**, e o protótipo âncora marca **3** (`fiscal-page.jsx:114-116`; DF-e, Certificado A1 e Faturado fiscal não têm `FxSpark`). É discordância no eixo FORMA, onde a cadeia é *protótipo > teste > casos > charter* ([ADR UI-0029](../../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)) — o charter é o perdedor e cede, no mesmo PR que desenhou as séries. A série `faturamento` segue computada e serializada pelo controller; desenhá-la é que divergiria da fonte.
3. **Alertas determinísticos** (3 níveis crit/warn/info) computados em PHP sem LLM — rejeições 7d + cert **vencido (`$dias < 0`)**, vencendo hoje ou em ≤60d + DF-e pending
4. **6 quick-link cards** pra sub-páginas (2 ativos sub-pages 2/3/5 + 3 disabled futuras 4/6/7)
5. **Multi-tenant Tier 0**: NfeEmissao + NfseEmissao + NfeDfeRecebido + NfeCertificado via HasBusinessScope (ADR 0093)
6. **Permissão**: `fiscal.access` gate
7. **Pest biz=1** (ADR 0101): KPIs isolation + alertas determinísticos + permission gate

## Non-Goals (PR #2)

- ❌ Drill-down via click no KPI (vai pra sub-página correspondente, sem filtros pré-aplicados)
- ❌ Período custom (mês corrente fixo — 14d sparkline default)
- ❌ Export PDF/Excel do cockpit (PR futuro)
- ❌ Alertas push (Whatsapp/email) — só visual no cockpit
- ❌ ⌘K palette (PR #3 do roadmap)
- ❌ Sub-páginas 4 (DF-e), 6 (Config), 7 (SPED) — apenas placeholders disabled

## Contrato da fila de alertas (Onda 1 Cowork · 2026-09-03)

Destilado do alvo `prototipo-ui/cowork/fiscal-page.jsx:125-137` (`FxAlerts`) e do que a
Onda 1 entregou. Descreve o que a seção **é**; a regra de negócio segue sendo do
`computeAlerts()` (determinístico, anti-hook abaixo).

| Item | Contrato |
|---|---|
| Âncora | `data-contract="alertas-fiscais"` na raiz da lista |
| Posição | entre o `.fx-ribbon` e o `WriteOffAuditoriaCard` |
| Estado vazio | **nó ausente** — zero alertas não desenha contêiner nem mensagem |
| Item | `.fx-alert` com `data-level` ∈ `crit` · `warn` · `info`; flex, `gap:10px`, `padding:10px 14px`, `radius:10px` |
| Ordem dos filhos | ícone (`.fx-alert-ic`) → texto (`.fx-alert-t` com `<b>`+`<small>`) → botão |
| Tinta do nível | fundo a 6% e borda a ~30% do tom sobre a superfície neutra; `info` fica **sem** tinta |
| Ícone | `aria-hidden` (redundante com o nível, que já está no texto e na moldura) |
| Botão | `<Button>` do DS via `btnProps('ghost')`, alinhado à direita |
| Ordem da lista | a que o backend mandou — a tela não ordena, filtra nem agrupa |

**Dois vocabulários cruzam a fronteira PHP → TSX, e os dois falham em silêncio:**
`goto` é o `id` de uma sub-página (`nfe` · `fiscal_config` · `dfe`), resolvido pelo
`_lib/paginas-fiscais.tsx` — **nunca** um caminho; e `icon` é o vocabulário do protótipo
(`audit` · `shield` · `receipt`), traduzido em `_lib/icones-alerta.ts`. Valor fora do mapa
não levanta erro: o botão ou o ícone apenas não são desenhados. Quem defende os dois é o
`UC-FCKP-08` do [`Cockpit.casos.md`](./Cockpit.casos.md).

## Contrato do rodapé de paginação (Onda 3 Cowork · 2026-09-03)

Destilado do alvo `fiscal-page.jsx` §`FxNotasPage` — baixado do **vivo** por `DesignSync`
(`truncated: false`), não do espelho `prototipo-ui/cowork/`, que mediu 1 de 258 arquivos e
cuja própria máquina declara qualquer comparação contra ele INCONCLUSIVA.

| Item | Contrato |
|---|---|
| Âncora | `data-contract="paginacao-notas"` na raiz do rodapé |
| Posição | depois da tabela, último filho do `FxShell` |
| Estado vazio | **nó ausente** — `filtrados.length === 0` não desenha o rodapé |
| Ordem dos filhos | meta (`N–M de T carregadas`) → `Select` → `Anterior` → `.fx-pager-n` → `Próxima` |
| Tamanho de página | default **8**; opções **8 · 25 · 50** |
| Copy | `Anterior` e `Próxima` por extenso — não `‹`/`›` |
| Contador | `{pagina} / {paginas}` em `.fx-pager-n`, monoespaçado e tabular |
| Extremos | `Anterior` desabilitado na 1ª página; `Próxima`, na última |
| Reset | trocar filtro **ou** tamanho de página volta à página 1 e limpa a seleção |
| Controles | `Select` e `Button` do DS — nunca `<select>` nativo |

**O `de N` fala da lista CARREGADA, nunca do total do negócio — e isso é contrato, não
limitação a corrigir em silêncio.** `NotasUnifiedService::LIMITE` corta a fonte em 50 antes
de ela chegar à tela (o docblock de lá declara *"é resumo; a lista completa vive em
/fiscal/nfe"*), e **não existe contagem total escopada por business** para servir de
denominador honesto: `contadores()['todas']` conta a MESMA lista truncada. Por isso a copy
diz `carregadas`, e por isso a paginação é **client-side** — que é o que o protótipo
especifica (`filtrados.slice(...)`). Quem for trocar isso por paginação server-side muda o
contrato da tela, não só o rodapé: precisa de UNION nas duas fontes com total por
`business_id`, e aí o cockpit deixa de ser resumo — decisão [W], não refactor.

**O hint `J/K navega · ↵ abre · N emite` do protótipo foi OMITIDO de propósito.** No
protótipo, cockpit e lista de NF-e são o **mesmo** componente (`FxNotasPage` serve as três
rotas), então o atalho valia para os dois; em produção são telas separadas e a Onda 2
([#6707](https://github.com/wagnerra23/oimpresso.com/pull/6707)) entregou o teclado só no
`Nfe.tsx`. Copiar o hint aqui anunciaria atalho que esta tela não tem. Quem implementar
`J/K` no cockpit deve trazer o hint junto — os dois andam colados.

> ⚠️ **Precisão medida em 2026-09-04 — a frase acima fala do rodapé do protótipo, e por lá
> segue exata; mas o hint tem OUTRA superfície nesta tela, e nela o atalho JÁ é anunciado.**
> O `cheats` que o `Cockpit.tsx` passa ao `FxShell` inclui `{ keys: ['J','K'], label:
> 'navegar' }`, e o `FxShell` o desenha no rodapé do shell. Ou seja: o cockpit **anuncia
> J/K sem implementá-lo** — LC-15 (mecanismo que promete saída que não honra), e o vão
> é anterior a este PR. Não foi consertado aqui de propósito: mexer na copy do shell é
> escopo de outra onda, e a correção tem duas saídas legítimas — **implementar** o J/K
> (com o hint do rodapé junto, como este parágrafo já manda) ou **remover** a entrada do
> `cheats`. Qual delas é decisão de quem pegar a onda do J/K; o registro fica aqui para
> que ela não descubra o vão do zero. O teclado que **existe** hoje é o do bloco abaixo,
> e ele é ortogonal ao J/K.

## Contrato de teclado na lista (Onda 2 Cowork · 2026-09-04)

A lista unificada é operável sem mouse. O alvo é linha **focável** — a `<tr>` mantém o papel
implícito `row`, e virar `role="button"` está proibido no anti-hook abaixo.

| Item | Contrato |
|---|---|
| Alvo focável | a `<tr>` da lista, `tabIndex={0}` — nunca um filho, nunca um wrapper |
| Rótulo | `aria-label` = `Abrir {tipo} {número} · {cliente}`; sem cliente ⇒ `—`, nunca string vazia |
| Teclas | **Enter** e **Space** abrem o drawer da linha **focada** |
| Space | `preventDefault()` obrigatório — sem ele o browser rola a página um viewport |
| Anel | `.fx-table tbody tr:focus-visible` → `outline: 2px solid var(--fis)`, `offset -2px`. Já existe em `fiscal-cockpit.css` (Onda 2, CSS compartilhado com o `Nfe.tsx`): esta tela **não** escreve CSS próprio |
| `:focus-visible`, não `:focus` | clique de mouse não acende anel; `outline: none` em lugar nenhum — o anel do UA é **substituído**, nunca suprimido |
| `.fx-row-focus` | reservado à nota **ABERTA** no drawer (`openedId === n.id`). **Não** é cursor de teclado e não segue o foco |
| Células que não abrem | checkbox de seleção e `.fx-row-actions` param a propagação — marcar ou baixar XML/PDF não abre o drawer |

Quem defende este contrato é o `UC-FCKP-11` do [`Cockpit.casos.md`](./Cockpit.casos.md), com 7
casos de render e 4 mutações provadas.
## Contrato das séries do ribbon (item A2 · 2026-09-04)

Destilado do alvo `prototipo-ui/cowork/fiscal-page.jsx:80-84` (`FxSpark`) e `:114-116` (onde ele
aparece). Descreve o que a peça **é**; o dado segue sendo do `computeSparklines()`, que já existia
e não foi tocado. Contrato executável em `Cockpit.casos.md` (**UC-FCKP-12**), lane
`fiscal-cockpit-sparklines-gate.yml`.

| Item | Contrato |
|---|---|
| Componente | `_components/RibbonSpark.tsx` — local do Fiscal (1 módulo consome), por [`.claude/rules/components.md`](../../../../.claude/rules/components.md) |
| Quais KPIs | **exatamente 3**: Emitidas, Autorizadas, Rejeitadas. Os outros três **não** recebem série |
| Posição | último filho do `.fx-ribbon-item`, depois do `<em>` — inclusive quando o `<em>` é condicional (Rejeitadas) |
| Geometria | `viewBox="0 0 56 15"`, base em `y=14`, amplitude `12`, `strokeWidth="1.2"`, um `<polyline>` — sem área, sem gradiente |
| Escala | `v / max` com piso 1 no máximo — magnitude com base em zero, não variação relativa |
| Cor | `currentColor`, **nunca** literal — a decisão de cor fica no CSS, não no TSX. ⚠️ Corrigido 2026-09-04: a redação anterior dizia que a série "herda a tinta do KPI"; **não herda** — o `<svg>` é irmão do `<b>`/`<em>` e o `.fx-ribbon-item` não define `color`, então as três saem em `var(--fx-text)`. **Gap declarado:** falta portar `.fx-ri svg { margin-top:2px; color: color-mix(in oklch, var(--accent) 65%, var(--text-mute)) }` (`fiscal-page.css:42`); `--accent` não existe no bundle e escolher o substituto é decisão [W] |
| a11y | `aria-hidden="true"` — é redundância visual: o número ao lado já é o dado. Sem hover, sem tooltip, sem foco |
| Série de 1 ponto | **não desenha** — `length - 1` zeraria o divisor e o React serializaria `points="NaN,NaN"` sem erro |
| Série toda-zero | **desenha** na base — "nada aconteceu" é leitura honesta, e é o que o protótipo faz |

**Por que não o `Chart` do DS** (`@/Components/shared/Chart`), medido em 2026-09-04: ele se
dimensiona por `width: '100%'` sem prop de largura (o ribbon quer 56px fixos); fixa `role="img"` +
`aria-label` no `<svg>`, o oposto do `aria-hidden` deste contrato; e é **interativo**
(`onMouseEnter` com estado + tooltip `label · valor`), o que ADICIONA leitura de dado onde a peça é
decorativa. Sobrepor isso significaria editar o componente do DS — soberania [W] — e mexer nos seus
dois consumidores vivos.

## Anti-hooks

- 🚫 Não fazer N+1 query nos sparklines — agrupar com `selectRaw('DATE(emitido_em)...')` 1× e iterar em PHP
- 🚫 Não fazer Inertia::defer nos KPIs — first paint cockpit deve mostrar números (sparklines aceitam ms de delay)
- 🚫 Não cachear KPI cross-tenant/agregado — a chave de cache DEVE incluir o `business_id` (Tier 0 ADR 0093; canon no código: `CockpitController` usa `fiscal:cockpit:kpis:biz:{id}`, provado por `CockpitCacheTest`). ⚠️ Corrigido 2026-07-06: o anti-hook anterior mandava o OPOSTO ("cache só agregado") — obedecê-lo criaria vazamento cross-tenant. Achado do adversário de arquitetura (V1, session 2026-07-06).
- 🚫 Não usar LLM pra gerar alertas — receita determinística por estado (cstat/dias/pendentes)
- 🚫 Não exibir PII (CPF/CNPJ destinatário) em KPI/alerta — usar referências abstratas ("2 rejeições", "5 DF-e")
- 🚫 Não transformar a linha da lista em `role="button"` para torná-la clicável por teclado — isso apaga o papel `row` e o leitor de tela perde a estrutura da tabela (coluna, cabeçalho, posição). O alvo é linha **focável** (`tabIndex={0}` + `onKeyDown`), como trava o `UC-FCKP-11`
- 🚫 Não suprimir o anel de foco (`outline: none`) em linha, célula ou controle da lista — o anel do UA pode ser **substituído** pelo do design, nunca removido
