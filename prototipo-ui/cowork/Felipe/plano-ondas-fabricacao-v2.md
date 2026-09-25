# Fabricação — ondas de execução (v2)

> 🔴 **CORREÇÃO DE 09/09/2026 — a onda 1 nunca esteve bloqueada, e a onda 2 caducou.** O teclado na
> linha **já está no espelho do DS que o protótipo consome** (`_ds_bundle.js` L3041-3052: `tabIndex`,
> `role="button"`, `aria-label`, Enter/Espaço) — e também no repo (`shared/DataTable.tsx` L370-381).
> **`caption` é obrigatório** (`DataTable.d.ts` L43, não-opcional): cada uma das 4 tabelas precisa
> declarar o próprio nome — "Receitas", "Ordens de produção", "Insumos", "Relatório de produção" —
> e o `<caption>` é renderizado oculto (`DataTable.jsx` L57-60). O espelho local foi regenerado em
> 09/09 (9.301 → 9.355 linhas, nos dois arquivos, shim de alias preservado).
> **O dilema 1A/1B/1C não existe: eu o construí sobre um bloqueio inexistente.** Largura mínima e
> cabeçalho fixo vêm do **CSS do módulo**, que é como o próprio repo faz (docblock L173); não são
> prop faltando. A onda 1 passa a ser: trocar a `div` (`manufacturing-page.jsx` L251) pelo
> `DataTable` do DS e trazer a largura pelo CSS — sem espera, sem decisão, sem divergência.
> A onda 2 vira `ds-mirror-drift` (push git→design), não proposta, e o `TabBar` do espelho está
> **não medido**, não "em falta". A seção "Contorno declarado × desacordo" perde o objeto.
> As ondas **0, 4 (A-03…A-06), 5, 6 e 7** seguem válidas — não dependem do DS. Os itens **B e C**
> citados na onda 4 e na 7 precisam ser remedidos contra o repo antes de ir à pauta.
> Ver `decisao-wagner-fabricacao-ds.md`.
> Ver `decisao-wagner-fabricacao-ds.md`.

> Deriva de `auditoria-aderencia-fabricacao-v2.md` (09/09/2026). Substitui o
> `plano-fabricacao-no-ds.md`, que foi escrito **antes** da onda A e ainda parte de "a família não
> consome nenhum componente do DS" — hoje ela consome 25. Os itens vivos daquele plano estão
> reancorados aqui com os ids da auditoria v2 (`P-`, `A-`, `B-`, `C-`).
>
> **Ordem por risco, não por tela.** Onda 0 é pré-requisito de todas. Ondas 1-3 pagam
> acessibilidade e comportamento; 4-6 pagam divergência visual e estrutura; 7 fecha documentos.
> Nada foi alterado. Cada onda roda só com sua aprovação.

**Procedência:** `[DS]` citado do bundle com a linha da declaração · `[TELA]` decidido aqui ·
`[PAUTA]` sai como proposta ao DS. Nenhum valor inventado, convertido ou observado em runtime.

---

## Mapa das ondas

| Onda | O que resolve | Itens | Risco | Depende de |
|---|---|---|---|---|
| **0** | Sincronizar o pacote de handoff com o código que roda | P-1, P-2 | 🔴 entrega | — |
| **1** | Teclado e leitor de tela nas 4 tabelas | B-01, B-02 | 🔴 acessibilidade | 0 · **e uma decisão sua** |
| **2** | O que o sistema anuncia (live region, abas, foco) | C-01, C-02, §8.9, §8.10 | 🔴 acessibilidade | 0 · C-01 espera prop no DS |
| **3** | Semântica de escolha e de campo | A-01, A-02, B-03, B-04, B-05, §8.6, §8.7 | 🟠 comportamento | 0 |
| **4** | Peças oficiais onde há peça caseira | A-03…A-06, B-07…B-11 | 🟡 estrutura | 0 |
| **5** | Rede de proteção do módulo | §8.11 | 🟠 robustez | 0 |
| **6** | Folha de prova e impressão | C-10, §8.9 (foco), §6 (9 cinzas) | 🟡 visual | 0 |
| **7** | Fechar o laço: handoff, pauta, CSS enxuto | C-*, D-* | — | a onda que rodar antes |

**Fora de onda, por decisão a tomar:** C-03…C-09 (peças que precisam nascer) — cada uma é proposta
ao DS, não trabalho de tela. Entram na onda 7 como pauta; só viram onda própria se você aprovar a
peça no DS.

---

## Onda 0 · Sincronizar o pacote (pré-requisito de tudo)

Sem esta onda, qualquer item aprovado nas outras é implementado a partir do código pré-onda-A.

| # | Está hoje | Deve ficar | Teste de aceite |
|---|---|---|---|
| P-1 | ~~cópia em `handoff_fabricacao/design/` divergindo da raiz~~ | **encerrado em 22/09/2026:** as cinco cópias de `.jsx` **e** a cópia de CSS (`04-modulos/manufacturing/css/manufacturing.css`, 21.032 ch × 16.534 da raiz) foram apagadas; a página-guia carrega `../../manufacturing-{recipe,insumos,producao,print,page}.jsx` e `../../manufacturing-page.css`. `manufacturing-app.jsx` continua local — é o shim de mount do pacote e **não existe na raiz** | `ls handoff_fabricacao/design/manufacturing-*.jsx` volta só `manufacturing-app.jsx`; `04-modulos/manufacturing/css/` está vazia; a aba Receitas pinta |
| P-2 | ~~a página-guia não carrega `_ds_bundle.js`~~ | **encerrado em 22/09/2026:** `<script src="../../_ds/wagner-…49a36f76…/_ds_bundle.js">` entrou antes dos `<script type="text/babel">`. **Era pior do que a ficha dizia:** ao repontar os `.jsx` para a raiz (P-1), a página passou a abrir EM BRANCO — os arquivos da raiz são pós-onda-A e consomem o bundle; as cópias apagadas eram pré-onda-A e não. O `createRoot` falha sem nada no console, que é o modo de falha silencioso | `Object.keys(window.OfficeImpressoPontoWR2DesignSystem_019dd0).filter(k => !k.startsWith('__')).length === 58` (o bundle publica também a chave interna `__errors`; contar sem filtro dá 59 e reprova página sadia) e a aba Receitas pinta |
| P-4 | ~~"sublinhado da aba ativa fica preso em Receitas"~~ | **não é defeito da tela — artefato de documento oculto (medido 22/09/2026).** Ao clicar, o `TabBar` atualiza corretamente `aria-current` e o `style.borderBottom` inline; só os três valores **transicionados** (`color`, `background-color`, `border-bottom-color`, 150 ms no componente) ficam no valor inicial. Causa medida: `document.visibilityState === "hidden"` na prévia, com `document.getAnimations()` reportando `currentTime: 0` · `playState: "running"` em duas leituras 700 ms apart — o relógio de animação não avança em documento oculto, então a transição nunca chega ao fim. Procedência `[RUNTIME]`: vale para a prévia, não para o navegador da usuária | numa aba visível, clicar "Relatório" e ler `getComputedStyle(btn).borderBottomColor` → accent. Na prévia, ler o que não transiciona: `btn.getAttribute("aria-current") === "page"` e `btn.style.borderBottom === "2px solid var(--accent)"` |
| P-3 | ~~divergência entre o comentário do shell e o nome publicado pelo bundle~~ | **encerrado em 21/09/2026:** o bundle publica só `window.OfficeImpressoPontoWR2DesignSystem_019dd0`, não há alias, e o comentário do `oimpresso.com.html` diz exatamente isso | `grep -c OfficeImpressoDesignSystem_49a36f` no bundle e nas páginas → 0 |

**Gate da onda:** o guia do pacote abre e pinta igual à raiz. Só então qualquer outra onda começa.

---

## Onda 1 · As 4 tabelas — teclado e nome acessível 🔴

A falha mais grave da auditoria: quem só tem teclado não abre receita, ordem nem insumo.

**Está hoje:** `div` com `onClick`, sem `tabIndex`, sem `role`, sem Enter/Espaço —
`manufacturing-page.jsx` L239-249 (cabeçalho) e L251 (linha) · `-producao.jsx` L77-82 e L84 ·
`-insumos.jsx` L41-46 e L48 · `-producao.jsx` L297-301 (relatório, sem ordenação).

**Bloqueio real, medido:** o `DataTable` `[DS]` L2926 entrega linha focável, `role="button"`,
Enter/Espaço (L2988-3004) e `aria-sort` (L2957) — mas o `thBase` L2911-2921 **não tem
`position:sticky`** e a tabela não aceita `minWidth`, e a tela depende dos dois (`.mfg-thead
{position:sticky}` css L37; `min-width` 960/1100/900/940 css L34, L74, L76, L78). Pior: o nome
acessível da linha sai de `row.cells.cli.primary` — a chave **`cli`** está cravada (L3040), então
receitas viriam `role="button"` **sem nome nenhum** (B-02).

**Precisa da sua decisão antes de rodar.** Em uma frase cada: **1A** usa a peça oficial e fica parada
até o DS aceitar 3 props · **1B** usa a peça premium, precisa de 1 prop, mas a tela entrega o controle
da ordenação e da seleção · **1C** conserta o teclado na tela hoje e mantém a peça caseira, declarada
e nomeando a prop que espera. "Contorno com prazo" = o handoff registra que é peça caseira, qual prop
do DS ela aguarda, e que ela sai quando a prop chegar.

| Caminho | O que a tela ganha | O que custa |
|---|---|---|
| **1A** `DataTable` + 3 props novas ao DS: `stickyHeader`, `minWidth`, `rowLabel` | mantém ordenação e seleção no estado que a tela já tem | **bloqueada** até o DS aceitar as 3 props |
| **1B** `DataTablePro` `[DS]` L3099 (header fixo L3186, largura mínima derivada `tableMin` L3183) + `rowLabel` | roda com 1 prop nova, não 3; ganha resize e densidade | ordena e seleciona **por dentro** (L3110-3113) e exige `height` (L3103) — a tela perde o controle do estado; a ordenação controlada **já é P1 na pauta** |
| **1C** contorno declarado: manter a tabela local e acrescentar só `tabIndex`/`role`/Enter/Espaço/`aria-sort` na tela | paga a acessibilidade **hoje**, sem esperar o DS | mantém peça caseira onde existe peça oficial; vai ao handoff declarado como contorno, com prazo |

*Teste de aceite (qualquer caminho):* Tab chega em toda linha; Enter e Espaço abrem o drawer; o
leitor anuncia o nome da receita/ordem/insumo; os 4 cabeçalhos ordenáveis anunciam `aria-sort`;
cabeçalho continua fixo e a rolagem horizontal continua nas 4 larguras atuais.

**Recomendo 1C agora + 1A na pauta.** A falha de teclado é de hoje; as props do DS são de quando o
DS decidir. É a única onda onde eu recomendaria contorno antes de peça oficial — e ele nasce
declarado e com data.

---

## Onda 2 · O que o sistema anuncia 🔴

**Nenhuma outra tela é tocada nesta onda — nem em nenhuma outra deste plano.** O escopo é a família
Fabricação, ponto. O que muda aqui é de onde vem o defeito: em C-01 e C-02 ele não está no código da
Fabricação, está **dentro do componente do DS que ela consome** (`TabBar` sem teclado de seta;
`Toast` sem `role="status"`). Recriar componente do DS está descartado, então a Fabricação consome
como está e a falta sobe como proposta. Consequência prática: **C-01 não tem caminho dentro da
tela** — o teclado é interno ao `TabBar`. **C-02 tem um**, se você quiser: um nó de live region na
própria família, declarado como contorno (mesma figura do 1C). As duas últimas linhas da tabela são
`[TELA]` puras e rodam sem depender de nada.

| # | Está hoje | Falta no DS | Teste de aceite |
|---|---|---|---|
| C-02 | `aviso()` page L71 → `Toast` L332-334 · "Configurações atualizadas" `-producao.jsx` L354. **`aria-live` aparece 0 vez nas 9.290 linhas do bundle**; o `Toast` L6975-7011 é `<span>` estilizada, sem `role="status"` | **região de anúncio** — `role="status"`/`aria-live="polite"` no `Toast`, ou um host de anúncio no DS. `Alert`, `EmptyState` e `Skeleton` também são só visuais. **Cabe contorno na família:** live region própria, declarada, até a prop existir | "Receita salva" é anunciada sem mover o foco |
| C-01 | `TabBar` L6677-6749 monta `<nav aria-label>` + `<button aria-current>` (L6683, L6700) — trata clique, **não é `role="tablist"`, não escuta ←/→ nem Home/End**. Uso: page L204-206 | **teclado de seta no `TabBar`** — interno ao componente. **Não cabe contorno:** acrescentar por fora exigiria reimplementar o `TabBar`, o que está descartado. Único item da auditoria sem caminho dentro da tela | ←/→ andam pelas 5 abas; Home/End vão à primeira/última |
| §8.10 | page L157-176 troca o corpo do módulo por tela cheia; o foco fica no `<body>` | nada — é `[TELA]`: mover o foco ao título do editor ao entrar, e devolver ao gatilho ao sair (o `Drawer` L3764-3800 e o `Modal` L5050-5086 já fazem isso certo — o editor não passa por eles) | entrar no editor: o leitor anuncia o título; cancelar devolve o foco ao botão de origem |
| §8.9 | `-print.jsx` L118-124: `window.print()` 120 ms após montar; `afterprint` chama `onDone` e o foco anterior **não** é restaurado | nada — `[TELA]` | fechar a folha devolve o foco ao botão "Imprimir ficha" |

**Gate:** C-01 sai como proposta assinada (e a Fabricação segue com o `TabBar` como está, declarado).
C-02 sai como proposta **e**, se você aprovar, ganha a live region de contorno na família. §8.9 e
§8.10 rodam na tela sem esperar o DS.

---

## Onda 3 · Semântica de escolha e de campo 🟠

| # | Está hoje | Deve ficar | Teste de aceite |
|---|---|---|---|
| A-01 | Chips de categoria, **escolha única** de 4, com `aria-pressed` (page L232-234) — alternância marcada onde é escolha | `RadioGroup` `[DS]` L6067 (`role="radiogroup"` L6076), `direction="row"` | seta anda pelas 4 categorias; só uma marcada. **Custo declarado:** perde a pílula |
| A-02 | Chips de permissão, **4 alternâncias independentes** (`-producao.jsx` L362-366) | `Checkbox` `[DS]` L2511 ×4 | Espaço alterna cada uma; rótulo associado |
| §8.7 | O contêiner dos chips não tem `role="group"`/`aria-label` (page L232, `-producao.jsx` L362) | resolvido de graça por A-01/A-02 | o leitor anuncia o grupo antes dos itens |
| B-03 | Busca com atalho `/` (page L228-231, ref L70, listener L76-79) · `-insumos.jsx` L32-35 · sem `<label>` nem `aria-label` em nenhuma das três (page L230, `-insumos.jsx` L34, `-recipe.jsx` L94) | `Input` `[DS]` L4481 — assinatura medida L4481-4493 **sem `ref`, sem `onKeyDown`, sem slot de ícone** (o ícone **já é P1 na pauta**) | ⚠️ **bloqueada**: trocar hoje apaga o `/` e o Enter do seletor de insumo. `[TELA]` imediato: acrescentar `aria-label` nas 3 buscas — isso não espera o DS |
| B-04 | O `Alert tone="danger"` fica **fora** do `Input` (`-recipe.jsx` L59-60 vs L57-58) | `Input error` L4481 pinta borda e grava `data-invalid` (L4504) mas **não emite `aria-invalid` nem `aria-describedby`** (ausentes em L4494-4508) | ⚠️ **bloqueada** — `[PAUTA]`: os dois atributos no `Input`. Sem eles o erro não se liga ao campo, antes ou depois da troca |
| B-05 | 9 campos numéricos com passo/teto: `-recipe.jsx` L170 e `-producao.jsx` L152 (`step="0.001"`); `-recipe.jsx` L64, L212, L221, L225 (`max="100"`), L232, L236; `-producao.jsx` L189 | `Input type="number"` | ⚠️ **bloqueada** — `min`/`max`/`step` não existem na assinatura; trocar derruba o milésimo e o teto de 100% |

**Gate:** A-01, A-02 e os `aria-label` rodam. B-03/B-04/B-05 ficam declarados como contorno até as
props existirem — **e o contorno diz qual prop espera.**

---

## Onda 4 · Peça oficial onde há peça caseira 🟡

| # | Está hoje | Deve ficar | Teste de aceite |
|---|---|---|---|
| A-03 | dica de remover em `title=` nativo (`-recipe.jsx` L157, L183) | `Tooltip` `[DS]` L7023 — abre em **foco** (L7107-7108) | teclado no botão mostra o balão |
| A-04 | "Carregando …" em `<p class="mfg-note">` (page L33-35; usos L162, L174, L292, L297, L301, L305) | `Skeleton` `[DS]` L6187 (`variant="row"`/`"card"`) | silhueta no lugar da frase. **Não resolve o anúncio** — isso é C-02 |
| A-05 | botão-ícone 22 px, raio 5 px (css L122; `-recipe.jsx` L157, L183) | `Button` `[DS]` L2246, `icon` + `size="sm"` | some 1 raio fora do canon. **Custo:** 22 → 26 px (L2257), ainda < 44 px |
| A-06 | botão "esc" da busca de insumo, texto de 10 px (css L129; `-recipe.jsx` L96) | `Button size="sm" kbd="esc"` — `kbd` está na assinatura (L2251) | a tecla vem do componente |
| B-07 | trilha de volta ×2 (`-recipe.jsx` L141-145, `-producao.jsx` L135-139) | `Breadcrumb` `[DS]` L2077 — só aceita `items [{label, href}]`; a volta aqui é por **estado** | ⚠️ bloqueada: `[PAUTA]` `onSelect` no item |
| B-08 | botão tracejado "＋ Ingrediente em ⟨grupo⟩", 3 usos (css L124-126; `-recipe.jsx` L190, L202, L204) | `Button` L2246 | ⚠️ bloqueada: falta `variant="dashed"` (existem primary/ghost/danger, L2274-2296) |
| B-09 | botão-ponte em texto corrido, 7 usos (page L393; `-producao.jsx` L261, L323, L372-374; `-insumos.jsx` L93, L101) | `Button` L2246 | ⚠️ bloqueada: falta `variant="link"` — todas têm caixa e altura fixa (L2257) |
| B-10 | "Grupo sem ingredientes." ×3 (page L376, `-recipe.jsx` L187, `-producao.jsx` L172) | `EmptyState` `[DS]` L4126 | ⚠️ bloqueada: falta variante compacta — o componente é bloco centrado (L4165-4225), o vão é de uma linha |
| B-11 | contador de aba composto `6 · 1 rasc.` (page L196-201) | `TabBar count` L6734-6746 (pílula `padding: 0 6px; minWidth: 18`) | ⚠️ decisão sua: encurtar o rótulo é `[TELA]`; acomodar contador composto é `[PAUTA]`. O botão declara `whiteSpace:'nowrap'` (L6707) — remedir depois de decidir |

**Gate:** A-03…A-06 rodam. Os B ficam declarados, cada um nomeando a prop que espera.

---

## Onda 5 · Rede de proteção 🟠

| # | Está hoje | Deve ficar | Teste de aceite |
|---|---|---|---|
| §8.11 | page L48 desestrutura 12 componentes do DS na primeira linha do render; `ds()` devolve `{}` se o bundle não carregar (page L15) → módulo inteiro cai em *"Element type is invalid"* — o mesmo sintoma que a fila lazy já obrigou a contornar para os irmãos (page L21-31) | guarda antes do render: se o bundle não veio, o módulo mostra estado de falha em vez de derrubar a árvore | simular bundle ausente: a tela informa a falha, o resto do app continua de pé |

Achado novo da v2. Uma onda curta, e ela protege todas as outras.

---

## Onda 6 · Folha de prova e impressão 🟡

| # | Está hoje | Deve ficar | Teste de aceite |
|---|---|---|---|
| C-10 | 9 valores literais de cinza no `@media print` do CSS (L182 `#fff`; L184-221 `#111 #555 #666 #777 #999 #bbb #ccc #f0eeeb`) | o cockpit **não publica token de papel/tinta** (ADR 0413) — mesmo com os 4 print-craft já em uso (`-print.jsx` L30, L27, L44, L103-104) | ⚠️ **não resolve por troca de componente.** Rodar exige **imprimir uma folha e medir**: se o token não sobrevive ao `@media print`, o resultado é a medição, não uma correção dentro do componente |
| §8.9 | foco não devolvido depois de imprimir (`-print.jsx` L118-124) | `[TELA]` — já listado na onda 2; roda com ela ou aqui, não nas duas | — |

*(A v1 dizia "11 cinzas"; medido agora, 9 valores distintos.)*

---

## Onda 7 · Fechar o laço nos documentos

| # | O quê |
|---|---|
| D-01 | `handoff_fabricacao/README.md`: o §14 ainda tem a frase "não consome nenhum componente do DS" (o `design/README.md` L87-89 já foi corrigido e é o que denunciou P-1) · §10 recontar as cores cruas que sobrarem · §12 remedir ADR 0412 · §15.3 mapa componente→arquivo real |
| D-02 | `pauta-design-system.md` — **P1:** `DataTable` sem `stickyHeader`/`minWidth`/`rowLabel` (B-01, B-02) · `Input` sem `ref`/`onKeyDown`/`min`/`max`/`step` (B-03, B-05) e sem `aria-invalid`/`aria-describedby` (B-04) · `TabBar` sem teclado de seta (C-01) · ausência de live region e de host de toast (C-02, §6). **P2:** `Select` sem variante compacta (B-06) · `Breadcrumb` sem `onSelect` (B-07) · `Button` sem `dashed`/`link` (B-08, B-09) · `EmptyState` compacto (B-10) · `TabBar` contador composto (B-11) · `Slider`, `Card`, `Separator`, `KeyValue`, rodapé de tabela, combobox inline (C-03…C-09) |
| D-03 | **Defeitos de origem (D):** `cli` cravado no nome acessível do `DataTable` L3040 · comentário errado de `oimpresso.com.html` L119 (P-3) · `StatusBadge.d.ts` declara 8 `StatusKind` e o `MAP` implementa 11 (item F-33 do plano v1, ainda vivo) |
| D-04 | Enxugar `manufacturing-page.css` classe a classe: hoje **20 seletores** fora da rampa `--fs-*` (10 px ×8: L38, L52, L61, L85, L116, L129, L135, L146 · 11 px ×3: L91, L104, L122 · 12 px ×8: L48, L56-58, L65, L134, L136, L142 · 14 px ×1: L153), **3 raios** fora do canon (3 px L71 e `.mfg-grp-n`; 5 px L122; 10 px L108, L141), **6 seletores + 4 inline** com `--accent` como texto (L31, L68, L94, L101, L125, L148 + page L389, `-recipe.jsx` L246, `-producao.jsx` L199, L255). O que ficar entra no handoff **declarado como contorno, com o motivo** |

**Não entram nesta onda, por serem decisão de outra pessoa:** `--text-mute` em texto de 10-11,5 px
(15 seletores) é **ADR 0410**, e `--accent` reprovando 2,64:1 no escuro é **ADR 0411** — o token é do
DS, e trocar por `--text-dim` é decisão sua, não consequência de troca de componente.

---

## Contorno declarado × desacordo — a distinção que decide as ondas 1 e 2

Não são a mesma coisa, e o objetivo "nenhuma tela com componente em desacordo" trata cada uma
diferente:

| | 1C (tabela local com teclado) | C-02 (live region na família) |
|---|---|---|
| Substitui componente do DS? | **não** — mas mantém peça caseira onde existe `DataTable` `[DS]` L2926 | **não** — o DS não tem peça equivalente: `aria-live` aparece **0 vez** nas 9.290 linhas do bundle |
| Inventa cor, token ou medida? | não | não |
| Altera o visual? | não | não |
| Compete com peça oficial? | **sim** | não |
| Veredito | **é desacordo**, declarado e com prazo | **não é desacordo** — é lacuna preenchida por atributo, o `Toast` do DS segue intacto |

**Consequência, dita em voz alta:** hoje **não existe caminho zero-desacordo e zero-mudança-no-DS ao
mesmo tempo** para as 4 tabelas. 1A espera 3 props, 1B espera 1 (`rowLabel` — sem ela as linhas
ficam sem nome acessível), 1C roda já e é desacordo. Escolher zero desacordo significa **as 4
tabelas seguirem sem teclado até o DS responder**. É escolha legítima — o DS sempre ganha — e é o
oposto do 1C.

**Se o objetivo for zero desacordo**, a sequência é: subir as propostas (`stickyHeader`/`minWidth`/
`rowLabel` no `DataTable`, teclado no `TabBar`, `role="status"` no `Toast`) e rodar agora só o que
não pede nada do DS — **onda 0 · onda 5 · onda 3 parcial (A-01, A-02, `aria-label`) · onda 4 parcial
(A-03…A-06) · onda 6 · onda 7**, mais C-02, que não é desacordo. Ondas 1 e 2 esperam a resposta.

---

## As 4 decisões que travam ondas

1. **Onda 1 — 1A, 1B ou 1C?** É a falha mais grave e a única onde recomendo contorno declarado
   agora (1C) com a proposta em paralelo (1A). Sem escolher, as 4 tabelas ficam onde estão.
2. **Onda 2 — a Fabricação ganha live region de contorno agora (C-02), ou espera a prop no DS?**
   Nenhuma outra tela é tocada nos dois casos; a proposta sobe igual. C-01 não tem essa escolha —
   sem prop no `TabBar`, fica como está.
3. **Onda 3 — A-01/A-02 custam aparência.** Chip → rádio/caixa conserta semântica e teclado e muda
   o visual de duas telas. Decisão sua.
4. **Onda 6 — vale imprimir a folha agora?** Sem a medição em papel, C-10 não sai do lugar.

**Se você quiser o menor caminho que paga acessibilidade:** onda 0 → onda 1 (caminho 1C) → onda 2
(as duas linhas `[TELA]`) → onda 3 (A-01, A-02, `aria-label`) → onda 5. As 3 restantes são visuais
e podem esperar.

Nada foi alterado. Diga quais ondas rodo e, na onda 1, qual caminho.
