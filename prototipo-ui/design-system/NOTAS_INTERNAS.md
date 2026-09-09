# Notas internas — decisões do espelho DS

> Registro curto do **porquê** de cada mudança estrutural. Não é documentação de uso
> (isso é o `README.md`), nem handoff pro repo (isso é o `HANDOFF.md`). Aqui fica o
> raciocínio que normalmente se perde entre uma sessão e outra.

---

## 2026-08-31 · Reset dos documentos

**Problema.** `HANDOFF.md` e `README.md` tinham virado catálogo da própria bagunça. O
handoff abria com uma seção "Errata de paths" explicando quais caminhos citados no
próprio documento não existiam mais; o README listava como "key files read" um
`AppShell.tsx`, um `shared/ponto/` e um `ModuleTopNav.tsx` que não existem, e chamava
`inertia.css` de "source of truth" quando os tokens nascem em DTCG há duas versões.
Três épocas de regra misturadas, e a camada mais nova gastando espaço pra desmentir a
mais velha.

**Por que isso trava desenho.** Não é higiene: é que regra morta ainda arbitra. Nesta
mesma sessão, três rodadas de vaivém saíram de um mapa desatualizado — o handoff mandava
estender `shared/PageHeader.tsx`, que está congelado, e replicar uma caixa de ícone que o
canon v3.8 aposentou. Documento errado não fica inerte; ele responde perguntas.

**Decisão.** Reset com data, não emenda. Regra do baseline: **só entra o que é vigente e
verificado.** Afirmação sobre o `main` sem `arquivo:linha` medido por ferramenta não entra
no mapa — vai pra uma seção §5 "Não verificado", explícita. Regra morta não é anotada como
morta; é apagada. As versões antigas foram pra `arquivo/`, inteiras, caso alguém precise
da arqueologia — mas ninguém precisa ler pra trabalhar.

**A parte desconfortável, de propósito.** O §4 agora separa 3 pares medidos de 40+ pares
herdados marcados como "pista, não fato". Ficou pior de ler e mais honesto: dos poucos
que foram medidos nesta rodada, dois estavam errados. Um mapa que não distingue o que foi
conferido do que foi herdado mente com confiança uniforme.

**O que NÃO mudou.** `git` continua SSOT. Cheguei a propor inverter a direção de
autoridade — recusado, e com razão: o lado do Code tem defesa legítima (gates, ratchets,
medição em produção), e o problema real nunca foi quem manda, foi o espelho carregar
história que não usa.

---

## 2026-08 · Importação das lacunas verdadeiras

**Problema.** Sete peças estavam sendo reimplementadas em cada módulo — cada tela com
seu próprio card com título, sua própria tabela paginada, sua própria barra de ações,
seu próprio menu "⋮", sua própria linha do tempo, seu próprio CSS de impressão. Isso
significa: densidade e espaçamento diferentes por tela, teclado e acessibilidade
implementados só onde alguém lembrou, e qualquer ajuste de estilo virando N edições.

**Decisão.** Promover as sete a componentes do DS: `Widget`, `DataGrid`, `Toolbar`
(+`ToolbarButton`/`Search`/`Divider`/`Spacer`), `Kebab`, `Segmented`, `Timeline`,
`PresenterMode`. Critério usado pra aceitar cada uma: aparecia em **3+ módulos**,
tinha comportamento (não só estilo) e o comportamento estava divergindo entre cópias.

**Migração.** PT-01, PT-05, PT-07, Financeiro, CRM e Oficina Auto passaram a consumir
as peças; `Atendimento` ficou de fora porque é layout de inbox, não tinha as lacunas.

---

## 2026-08-31 · Ciclo de análise completa

**Escopo varrido.** 55 exports / 46 componentes, 62 cards, 7 templates, 245 tokens.
Compilador sem issues, nenhum export órfão, nenhum card apontando pra `.jsx` cru.
Aliases (`DataTable`, `DataTablePro`, `Kebab`, `KpiFilterCard`) renderizam pelo namespace.

### P1 — quebra no tema claro (cor cravada onde deveria haver token)

| Componente | O que está cravado | Efeito |
| --- | --- | --- |
| `BulkBar` | `oklch(0.21 0 0)` fundo, `oklch(0.96 0 0)` texto, `0.35`/`0.30` divisor e hover | Barra sempre escura; no cockpit claro ela não pertence ao sistema e ignora `accentHue`. |
| `TagChip` | paleta `oklch(0.30 …)` / `oklch(0.84 …)` por hue | Chips calibrados só pro fundo escuro — no claro o contraste inverte. |
| `Tooltip` | `--tt-bg: oklch(0.24 0.01 80)` | Aceitável (tooltip escuro é intencional), mas deveria ser token, não literal. |

### P2 — cor cravada que ignora o `accentHue` dos templates

- `Pagination`: sombra da página ativa fixa em `oklch(0.55 0.15 295 / .5)` — não acompanha o hue.
- `KpiCard` (hero): setas de delta em `oklch(0.80 0.11 162)` / `oklch(0.78 0.13 25)` em vez de `--pos`/`--neg`.
- `Avatar`: pontos de status `online`/`away` cravados.
- `AppSidebar`: `#fff` nos glyphs de empresa (gradiente por hash é decorativo e pode ficar).

### P3 — dívida estrutural

1. **Cinco buscas.** `Input` (form), `ToolbarSearch` (barra) e markup solto nas topbars de
   PT-01, PT-05, PT-07, CRM e Atendimento. Uma peça só deveria existir.
2. **Atendimento** ainda tem botões-filtro `class="rowbtn"` inline (deveriam ser `FilterChip`
   ou `ToolbarButton`) e o painel do FsmStepper no PT-07 continua `<section>` cru (→ `Widget`).
3. **Cards dos aliases** aparecem no grid como componentes de primeira classe; falta marcar
   "alias / compat" no subtítulo pra ninguém escolher o antigo em tela nova.
4. **Sem "Starting points"** registrados — quem chega no DS não tem ponto de entrada além dos templates.
5. **Tokens em três escopos** (`.dark`, `.cockpit`, `.cockpit[data-theme="dark"]`) — herança do
   espelho; funciona, mas é onde uma divergência com o git vai nascer primeiro.

**Conclusão do ciclo.** Nada quebrado em tema escuro (o padrão dos templates). O risco real é
tema claro + `accentHue` customizado: aí P1 e P2 aparecem juntos. P3 é custo de manutenção,
não defeito visível.

---

## 2026-08-31 · TabBar sem wrapper + PageHeader absorve o `cli-pagehead`

**Problema.** Três telas de Produto estavam bloqueadas: o `TabBar` cravava `className`,
`aria-label` e as medidas no próprio `<nav>` e não repassava atributo nenhum, então
qualquer consumidor precisava embrulhar a barra num `<div>` só pra pendurar
`data-contract` e rótulo. Wrapper em volta de nav = contrato inseguro (o atributo
não está no elemento que ele descreve) e um nó a mais na chain de overflow (AP10).
Em paralelo, duas lacunas tinham dono temporário fora do DS: `cli-seg` e `cli-pagehead`.

**Decisão — o `<nav>` é o contrato.** `TabBar` passou a espalhar `...rest` no `<nav>`
(pega `data-contract`, `data-*`, `aria-*`, `id`, `role`, `style`), concatenar `className`
com `ds-tabbar` em vez de substituir, e aceitar `ariaLabel`, `pad`, `size` (sm/md/lg),
`off`, `icon` (fallback pras abas sem ícone próprio) e `inset` (recuo lateral no próprio
nav — mata também o `<div>` de padding que os templates usavam, e faz a borda inferior
sangrar de ponta a ponta, que é o desenho correto). Todos os defaults são os valores
antigos (14px de pad, 36px/13px em `md`) — extensão de API, nenhum template quebra,
nenhuma cor ou token novo, logo sem ADR. Regra generalizada em `HANDOFF.md` §1.13:
contrato mora no elemento, não num wrapper.

**Decisão — PageHeader absorve o `cli-pagehead`.** Três props: `leading` (marca de
identidade antes do título), `context` (linha mono/uppercase acima do título) e
`freshness` (+`freshnessRel`), que reusa `StatusBadge kind="frescor"` em vez de repintar
a pílula. `cli-pagehead` pode morrer.

**Corremção na mesma rodada — eu tinha recriado o que o canon aposentou.** A primeira
versão chamava a prop de `glyph` e desenhava uma caixa 34×34 à esquerda do título. Ao ler
o `main` pra montar o handoff: (a) o header canon (`Components/PageHeader/PageHeader.tsx:52`)
**já tem** o slot, chamado `leading`, opt-in desde 2026-08-08, renderizando dentro do `h1`
pra acompanhar a linha de base; (b) a caixa 40×40 `bg-primary/10` que eu estava replicando
é do `shared/PageHeader.tsx`, que está `@deprecated`/CONGELADO — o canon v3.8 a aposentou
quando virou flat. Renomeado pra `leading` e movido pra dentro do `h1`. Lição: ler o alvo
no `main` **antes** de nomear a prop, não só antes de afirmar sobre ele (§1.2 vale pro
desenho também, não só pro texto do handoff).

**`cli-seg` já era redundante.** O `Segmented` do DS (`options/value/size/full/iconOnly/
ariaLabel`) cobre o caso; o que sobrou com esse nome no `oficina-base.css` é um *pill de
segmento de cliente* (rótulo estático), não um controle — isso é `TagChip`, não `Segmented`.
Nada a promover: é substituição de uso, não componente novo. A classe está sem nenhum
consumidor no markup dos templates — é CSS morto, sai junto com o arquivo do dono.

**Pendente.** Os seis mini-DS (AcessosDS 20 consumidores · PBUI 15 · ModuloPadrao 12 ·
HrmUI 7 · CatchupUI · PontoUI) não existem com esses nomes no `main` — a triagem precisa
do mapeamento nome → pasta antes de virar inventário. Critério já acordado: AcessosDS e
PBUI sobem; ModuloPadrao sobe só o que é comportamento (resto vira template); HrmUI vira
adaptador com prazo; CatchupUI e PontoUI morrem por default até alguém provar consumidor.

**Handoff.** `HANDOFF-2026-08-31-tabbar-pageheader.md` — auditoria do `main` com
arquivo+linha, diff pedido, gates e bloco de COWORK_NOTES.

**Falha de processo nesta rodada (registrar, n\u00e3o repetir).** A primeira vers\u00e3o do handoff
citava `arquivo:linha` com n\u00fameros **estimados de mem\u00f3ria**, n\u00e3o medidos \u2014 oito dos onze
estavam errados, dois deles por 15 e 24 linhas. \u00c9 exatamente o que a \u00a71.2 do HANDOFF existe
pra impedir, cometido dentro do documento cuja autoridade inteira vem dessa regra. Um
n\u00famero errado \u00e9 pior que nenhum: manda o leitor auditar o trecho errado e voltar dizendo
\"n\u00e3o \u00e9 isso\". Remedidos por busca no ref. **Regra:** linha s\u00f3 entra em documento se veio\nde ferramenta que devolve linha; se n\u00e3o d\u00e1 pra medir, citar s\u00f3 o arquivo e o s\u00edmbolo.\n\n**Renomea\u00e7\u00e3o com custo.** `glyph` (vocabul\u00e1rio do [W], herdado do `cli-pagehead`) virou\n`leading` pra casar com o slot hom\u00f4nimo do canon. Ganho: um nome s\u00f3 entre espelho e git,\nque \u00e9 onde a diverg\u00eancia da P3.5 nasceria. Custo: mudei o termo de quem pediu. Se o [W]\npreferir, `glyph` volta como alias fino \u2014 mas a\u00ed s\u00e3o dois nomes pra mesma coisa, que \u00e9 o\nque a fus\u00e3o de agosto passou a evitar.\n\n---\n\n## 2026-08 · Fus\u00e3o dos componentes duplicados

**Problema.** Depois da importação, três componentes faziam o mesmo trabalho com três
APIs (`DataTable`, `DataTablePro`, `DataGrid`), e mais três pares se sobrepunham. Custo
real: quem monta uma tela precisa escolher entre variantes quase idênticas — e escolhe
errado; um bug de acessibilidade precisa ser corrigido em três lugares; o bundle carrega
três implementações da mesma tabela.

**Decisão.** Uma implementação por trabalho, com **alias fino** mantendo os nomes antigos
funcionando (nenhum template quebra, migração pode ser gradual).

| Antes | Agora | Por quê |
| --- | --- | --- |
| `DataTable`, `DataTablePro`, `DataGrid` | **`DataGrid`** — `pagination`, `resizable`, densidade, seleção/ordenação internas **ou** controladas | Eram a mesma tabela; a diferença real cabia em três props. Os dois nomes antigos viraram alias com as mesmas defaults de antes. |
| Paginação reescrita no rodapé do `DataGrid` | rodapé usa **`Pagination`** (`compact`) | Duas paginações com números, reticências e estados de borda diferentes. Fallback inline continua, caso o bundle carregue fora de ordem. |
| `Kebab` com lógica própria de menu | **`Kebab` = gatilho "⋮" do `DropdownMenu`** | Teclado, clique-fora, ancoragem e tom danger estavam duplicados. Kebab agora só desenha o botão. |
| `KpiCard` + `KpiFilterCard` | **`KpiCard variant="filter"`** | Mesmo tile; o "filter" só acrescenta ícone, `selected` e `onClick`. |
| `PeriodBar` com segmented interno | `PeriodBar` usa **`Segmented`** | O preset Dia/Semana/Mês era um segmented copiado, já divergindo em altura e sombra. |

**O que NÃO foi fundido, de propósito:** `Modal`/`Drawer` (foco e direção de entrada
distintos), `Alert`/`Toast` (inline permanente × transitório), `TabBar`/`Segmented`
(navegação × alternância de estado), `Widget`/`ProofFrame` (painel de tela × folha de
prova), `Toolbar`/`BulkBar` (barra fixa × barra contextual de seleção),
`Timeline`/`FsmStepper`/`Progress` (histórico × máquina de estados × medida).

**Pendência conhecida.** Ainda há três campos de busca no sistema: `Input` (form),
`ToolbarSearch` (barra) e o markup solto da topbar dos templates. O caminho é a topbar
consumir `ToolbarSearch`; não foi feito ainda pra não misturar com esta fusão.

**Regra pra próxima vez.** Antes de criar um componente, procurar quem já faz 80 % do
trabalho: se a diferença cabe em uma prop, é prop — não é componente novo. Nome novo só
quando o *papel* na tela é outro, não quando o *visual* é outro.
