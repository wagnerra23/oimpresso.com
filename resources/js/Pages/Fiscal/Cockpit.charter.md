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

Dar à pessoa fiscal (Eliana contadora + Wagner operador) **visão consolidada do estado fiscal do mês** em até **3 segundos** — KPIs de emissão (NF-e/NFC-e/NFS-e/faturamento), alertas determinísticos críticos (rejeições + cert vencendo + DF-e pending), e quick links pras 6 sub-páginas operacionais.

## Goals (Definition of Done PR #2)

1. **6 KPI cards eager** (não-deferred — first paint): emitidas mês, autorizadas + pct, rejeitadas (com pulse), faturamento, DF-e pending, cert vencimento dias
2. **Mini-sparklines SVG** nos 4 KPIs principais (últimos 14 dias)
3. **Alertas determinísticos** (3 níveis crit/warn/info) computados em PHP sem LLM — rejeições 7d + cert <60d + DF-e pending
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

## Contrato do selo de procedência (CU-FISC-16 · decisão [W] 2026-09-04)

Destilado do alvo `prototipo-ui/cowork/fiscal-page.jsx` (o botão do cabeçalho, `:60`, e as 7
chamadas de `window.FxProc`) + `fiscal-actions.jsx:93-99` (o componente) e `fiscal-data.jsx:194`
(o vocabulário `kind`/`label`/`explica`).

[W] escolheu, em 2026-09-04, a **saída (a)** das três que o SDD §5.4.1 abriu — marcar a
procedência na tela. As outras duas (esconder atrás de flag; declarar Non-Goal) foram
descartadas e **não devem ser re-propostas** sem decisão nova.

| Item | Contrato |
|---|---|
| Âncora do botão | `data-contract="procedencia"` no cabeçalho (`FxShell`) |
| Âncora do selo | `data-contract="procedencia-selo"`, com `data-origem` e `data-superficie` |
| Copy do botão | `Procedência` |
| Título do botão | `Mostra, por superfície, o que é leitura real e o que é demonstração` |
| Estado do botão | `aria-pressed` — é toggle, não comando |
| Preferência | `localStorage["oimpresso.fiscal.procedencia"]`, valores `"1"` / `"0"` |
| Alcance da preferência | vale para as telas do módulo Fiscal, e sobrevive à navegação entre elas |
| Copy do selo | `leitura real` · `demonstração` — vocabulário fechado, 2 valores |
| Forma do selo | `Badge` do DS com `dot`, variante `success` (real) / `warning` (demonstração) |
| Explicação | uma frase por superfície, em `Tooltip` do DS |
| Foco | o selo é focável (`tabIndex={0}`) — o tooltip abre no **foco**, não só no hover |
| Estado desligado | **nó ausente** — nenhum selo é renderizado |
| Botão em tela sem mapa | **nó ausente** — botão inerte é pior que botão ausente |

**O selo ACOMPANHA o número — nunca o esconde nem o substitui.** Esta é a metade do contrato
que decide se a saída (a) é honesta: um selo que ocultasse a superfície viraria a saída (b)
disfarçada, e (b) foi descartada. O `writeOffSummary` continua exibindo 2.470 candidatos; o
que muda é que agora a tela diz de onde eles vêm.

**A procedência é DECLARADA PELO CONTROLLER, nunca inferida na tela.** O motivo é medido:
quando o [#6541](https://github.com/wagnerra23/oimpresso.com/pull/6541) trocou a lista mockada
pelo `NotasUnifiedService`, o protótipo (`fiscal-data.jsx:198` — *"Lista unificada … é mock do
Controller"*) e o SDD §5.4.1 continuaram afirmando "demonstração" para `notas` e
`savedViewCounts`. Se a tela adivinhasse, herdaria esse atraso e passaria a **mentir com
selo** — pior que não ter selo. Declarando a poucas linhas do `Inertia::render`, quem troca o
dado troca a declaração no mesmo diff, e o `UC-FCKP-13` reprova quem esquecer.

**Cobertura declarada (medida em 2026-09-04, tip `d23bc3df34`):** 8 superfícies desta tela,
das quais **4 são demonstração** — e são exatamente os 4 métodos `mock*` do
`CockpitController`. A prop `sparklines` **não** entra: é real, mas a tela não desenha
sparkline nenhuma (`_pendente_w` do `fiscal-cockpit.contract.json`), e selo sem superfície
visível não tem onde pousar.

## Anti-hooks

- 🚫 Não fazer N+1 query nos sparklines — agrupar com `selectRaw('DATE(emitido_em)...')` 1× e iterar em PHP
- 🚫 Não fazer Inertia::defer nos KPIs — first paint cockpit deve mostrar números (sparklines aceitam ms de delay)
- 🚫 Não cachear KPI cross-tenant/agregado — a chave de cache DEVE incluir o `business_id` (Tier 0 ADR 0093; canon no código: `CockpitController` usa `fiscal:cockpit:kpis:biz:{id}`, provado por `CockpitCacheTest`). ⚠️ Corrigido 2026-07-06: o anti-hook anterior mandava o OPOSTO ("cache só agregado") — obedecê-lo criaria vazamento cross-tenant. Achado do adversário de arquitetura (V1, session 2026-07-06).
- 🚫 Não usar LLM pra gerar alertas — receita determinística por estado (cstat/dias/pendentes)
- 🚫 Não exibir PII (CPF/CNPJ destinatário) em KPI/alerta — usar referências abstratas ("2 rejeições", "5 DF-e")
- 🚫 Não inferir procedência na tela a partir do nome ou do formato da prop — ela vem declarada por `CockpitController::procedencia()`. Adivinhar reintroduz o atraso que o `#6541` já provou existir
- 🚫 Não usar o selo para esconder, borrar ou substituir o número que ele descreve — isso é a saída (b), que [W] descartou em 2026-09-04
- 🚫 Não desenhar o botão "Procedência" em tela que não declara o mapa — toggle que não acende selo nenhum ensina o operador a ignorar o botão
