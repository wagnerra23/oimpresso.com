# Handoff: Consulta de Produtos (`/products/unificado`)

Módulo **Produtos** do Office Impresso. Tela de índice unificado do catálogo: identificação,
disponibilidade, preço, margem e reposição de produtos, serviços, matéria-prima e kits numa lista
só, com o detalhe em painel lateral. Este pacote descreve a tela **com detalhe suficiente para
reimplementá-la no codebase real sem ter participado da conversa que a desenhou**.

> **Versão:** 2026-08-27 (rev. 21). **A topbar sai do produto** e o cabeçalho da página fica sem
> `stats` — mais quatro correções medidas, tudo na **§25**: gatilho `⋯` do cabeçalho a 26px (altura
> do `size="sm"` dos irmãos), `aria-label` nos dois gatilhos `⋯`, acento escrito **por tema**
> (L 0,55 claro / 0,72 escuro, como o `applyTheme` do PT-01) e — enquanto a faixa existiu — hover no
> `?` e campo real de busca global. Tema padrão permanece **claro** (exceção assinada). Um defeito de
> origem novo: no escuro, `Button primary` mede **2,61:1** de contraste. Base: rev. 20, mesmo dia.
>
> **Versão anterior:** 2026-08-27 (rev. 20). **Aplica tudo o que estava aguardando decisão** nas §22.6, §23 e
> §24, por instrução do Wagner ("corrija todos os itens"): 9,5px → 10,5px nos dois textos; texto do
> gatilho de busca herda a base; botão `?` do template incluído; `--accent` → `--color-primary` nos
> 4 usos da tela; "Margem baixa" sai do `amber`; `larguraMin` conta 36px e para de somar a coluna de
> ações duas vezes; toolbar volta ao `gap 8`, sem régua superior, contagem em `11px/1 mono`, busca
> com `min-width 240`; gatilho `⋯` ganha os valores e o hover do `Button ghost`; lupa no campo da
> toolbar; `<kbd>` do `/` no padrão; placeholder da paleta igual ao rótulo do gatilho. Aceites
> nº 59-70. Base: rev. 19, mesmo dia.
>
> **Versão anterior:** 2026-08-27 (rev. 19). Acrescenta a **§24** — auditoria de filtros, pilha do topo,
> busca e as três faixas do drawer. **Nada alterado:** quatro valores do template na toolbar
> (gap 6/8, régua a mais, contagem em sans, largura mínima da busca), três na busca (campo sem
> lupa — o `Input` do DS não tem slot de ícone, `<kbd>` do `/` fora do padrão, placeholder da
> paleta), o gatilho `⋯` sem borda e sem hover, e zero divergência no header, no topo e no rodapé do
> drawer. Base: rev. 18, mesmo dia.
>
> **Versão anterior:** 2026-08-27 (rev. 18). Acrescenta a **§23** — auditoria das quatro áreas pedidas
> (topbar+`PageHeader`, abas, cards de KPI, colunas e linhas da tabela), medida contra componente e
> template. **Nada alterado na tela nesta volta:** duas divergências no header (texto do gatilho de
> busca em 12,5px; botão `?` do template ausente), zero nas abas e nos KPI, três achados de
> geometria/estado na tabela, e o contrato de `--accent` lido em 4 lugares contra a regra da própria
> tela. Base: rev. 17, mesmo dia.
>
> **Versão anterior:** 2026-08-27 (rev. 17). Segunda volta da onda de 27/08: o sub de "Margem baixa" passa a
> declarar a **base do cálculo** (`lucro abaixo de 37% do preço` — a margem é sobre o preço, não
> sobre o custo); as três divergências de tipografia da tabela (§22.5) foram **corrigidas** para os
> valores do DS; `--destructive` → `--color-destructive` nas 11 ocorrências, e `--warn` **fica**
> (medido: não é alias de `--color-warning`). Base: rev. 16, mesmo dia.
>
> **Versão anterior:** 2026-08-27 (rev. 16). Acrescenta a **§22** — onda de 27/08 do Wagner: "Sem venda 90d"
> **volta** à aba Serviços (reabre a decisão de §19.5), o sub-rótulo de "Margem baixa" passa a
> declarar o número do piso, a ação de vigência vira **par** (produto inativo oferece *Ativar*, nunca
> *Inativar*) e as duas auditorias pedidas — tabela e painel — com os valores medidos e as três
> divergências que aguardam decisão. Base: rev. 15.
>
> **Versão anterior:** 2026-08-26 (rev. 15). **Retrata o modelo de rolagem:** volta a ser o do `main` — a
> **página** rola —, e o cabeçalho fixo passa a prender no topo da janela (§3.1.1 e §19.1
> reescritos; §15.3 nº20 desconsiderado). Acrescenta **§20** e o arquivo
> `contexto/patch-charter-casos-2026-08-26.md`: o patch dos documentos de comportamento do
> repositório (`Index.charter.md` e `Index.casos.md`). Base: rev. 14, mesmo dia.
>
> **Versão anterior:** 2026-08-26 (rev. 14). Corrige o cabeçalho fixo pela causa que faltava — a faixa de
> `padding-top` da área de dados, onde uma linha ainda aparecia acima do `th` (§19.1, com a
> medição); retira a régua acima do bloco de saldo (§19.9); trava a faixa de KPI em 4 colunas
> (§19.11). Base: rev. 13, mesmo dia.
>
> **Versão anterior:** 2026-08-26 (rev. 13). Fecha a revisão da Maiara: selo de tipo **volta** à tira
> (§19.8 reescrita), "Abrir cadastro" **é a primária** do rodapé (§19.2 — decisão assinada), e a aba
> Matéria-prima esconde "Sem venda 90d" e "Margem baixa" (§19.5). O rótulo "Saldo vendável (soma da
> grade)" **fica como está** — questão levantada e retirada pela autora. Base: rev. 12, mesmo dia.
>
> **Versão anterior:** 2026-08-26 (rev. 12). Acrescenta **§19.8** (tira de identidade sem pílula),
> **§19.9** (seção Disponível sem título) e, em §19.2, a medição do rodapé contra o template
> — revisão do Felipe. Base: rev. 11, mesmo dia.
>
> **Versão anterior:** 2026-08-26 (rev. 11). Acrescenta **§19.6** (nome e código do produto na faixa do
> cabeçalho do painel — revisão do Felipe) e **§19.7** (sigla de unidade explicada na dica).
> Retira de §19.1 o `background-clip: padding-box`, medido inerte. Base: rev. 10, mesmo dia.
>
> **Versão anterior:** 2026-08-26 (rev. 10). Acrescenta a **§19** — cinco decisões do Wagner e da Maiara
> de 26/08: cabeçalho de tabela fixo como requisito, remoção de "Usar em orçamento", situação de
> saldo de volta abaixo da miniatura no painel, e as duas regras de visibilidade de KPI (perfil
> vendedor e aba Serviços). A §19 é **diff** e tem precedência sobre o texto anterior das §2, §4.1,
> §4.1.1, §5 e §16 onde houver colisão. Rev. anterior: 2026-08-25 (rev. 9). Acrescenta a auditoria contra o **template canônico PT-01**
> (§0 item 1b, §3.1.1, patch de cor §3.1/§3.2) — shell, moldura da tabela, densidade por tokens e
> arquitetura de rolagem passaram a vir do template, não de decisões desta tela. Base:
> 2026-08-21, após 27 ondas de refinamento. Substitui integralmente o handoff de
> 2026-08-17 (que descrevia avatar por linha, KPI "Ativos", chips de filtro, coluna
> "Disponibilidade", preço "a partir de" e popovers fixáveis — tudo removido ou substituído).

## 0 · Contrato de leitura (para quem implementa, humano ou agente)

1. **Precedência, sem grau:** **design system > este README > protótipo > auditorias.** Valor de cor, tamanho ou
   raio de elemento que o DS renderiza está no componente, não aqui — a fonte é
   `contexto/patch-cores-consulta-produtos.md`, que cita arquivo e linha. O protótipo **ilustra**; este documento
   **governa**; `LAUDO-*` e `CHECKLIST-*` são medição datada e não são especificação.
1b. **O DS decide por componente E por template — nesta ordem, os três passos.** Antes de tratar
   qualquer coisa como decisão desta tela: **(1)** o componente que renderiza o elemento;
   **(2)** o **template canônico** do mesmo tipo de tela (`templates/pt-01-lista` para esta) — seu
   markup, o `<style>` do `<helmet>` **e** a lógica que escreve tokens no shell; **(3)** o guia do
   DS. Só o que não existe em nenhum dos três é **[TELA]**. Valor **[TPL]** ganha de **[TELA]**
   sempre. O template decide **mecanismo**, não só valor: densidade de tabela, por exemplo, é
   contrato de tokens no shell (`--d-td-y`, `--d-cpad-x`, rampa `--fs-*`), não prop de componente.
2. **Número vence nome.** Nome de componente ou prop citado aqui (`KpiFilterCard`, `tone="amber"`,
   `state:'urgent'`) é do **protótipo** e descreve intenção — não existe no codebase alvo. Onde
   houver valor numérico para a mesma coisa (§10.2), **o número é o contrato**.
3. **Listas.** "Lista fechada" quer dizer conteúdo inteiro: **não acrescentar** o que existir no
   codebase. É o caso do menu `⋯` (§4.1.1), do menu da linha e dos estados de selo (§10.2). Sem
   essa marca, a lista é ilustrativa — e o que você acrescentar deve ser relatado na entrega.
4. **Não substituir componente do design system.** Contornar na tela, ou registrar proposta em
   `contexto/pauta-design-system.md`. Recriar componente localmente está descartado.
5. **O protótipo não é referência de pixel para o que ele contornou.** Os contornos conhecidos
   estão em §10.1 (ícones), §12 (defeitos do DS) e `design/README.md`. Ícone: implementar pela
   coluna **Lucide**, nunca pelo glyph do protótipo.
6. **O design system sempre ganha.** De este README, do protótipo, das auditorias, da régua de
   acessibilidade, do bom senso. Sem exceção e sem grau. **Não pressupor, não assumir, não
   inferir:** valor sem citação de arquivo e linha **não está decidido** — abra o componente e leia.
7. **Divergência autoriza pergunta, nunca ação.** Ao encontrar contradição, valor que pareça errado
   ou regra deste pacote que colida com o DS: **(a)** aplique o valor do DS como está; **(b)** relate
   em uma linha, com o número medido; **(c)** **não altere nada** até a decisão vir. Não corrigir,
   substituir, adaptar ou "melhorar" por iniciativa própria — nem em nome de acessibilidade, nem de
   coerência, nem de outra regra deste documento. Exceção existe só **assinada e registrada** em
   `contexto/pauta-design-system.md`; não há exceção implícita nem por urgência.
   *Precedente:* a substituição do literal `oklch(0.74 0.14 18)` do `fresc-cold` por
   `--color-destructive` foi feita assim, com medição correta, e teve de ser revertida — ver patch
   de cor §8.
8. **Em dúvida, pergunte — não preencha.** Lacuna deste documento é defeito **do documento**.
   Relate a lacuna com a entrega; não a resolva por inferência do codebase. Os dois erros da
   implementação de 24/08 (§15, itens 2 e 11) foram lacunas minhas resolvidas por inferência.
9. **Entregue com o critério de aceite de §16 conferido**, item por item. Se a tela já está
   implementada, o trabalho é a §15 — o diff — e só ele.

## 1 · Sobre os arquivos deste pacote

Os arquivos em `design/` são uma **referência de design executável** — um protótipo que mostra
aparência e comportamento pretendidos. **Não é código de produção para copiar.** A tarefa é
**recriar esta tela no ambiente do codebase alvo**.

- **Stack alvo real:** Laravel 13.6 + UltimatePOS v6 · Inertia + React + Tailwind 4 + shadcn/ui
  (`new-york`, base slate) · Lucide React · IBM Plex Sans/Mono.
- **Nível de fidelidade:** hi-fi. Comportamento, permissões e regras de negócio foram decididos
  caso a caso e estão nas seções 4–9. Medidas estruturais estão em §3.
- **Design system:** `design/_ds/wagner-office-impresso-design-system-49a36f76-…/` é **camada 0** — externo,
  nunca editado por esta tela. Vem no pacote, então o protótipo abre offline.
- **Formato do protótipo:** Design Component (`.dc.html`) com estilos inline e `support.js` como
  runtime. A divisão em `data.js`/`ui.jsx` e as 4 camadas de CSS que o manual de handoff pede
  **não** se aplicam aqui — ver `design/README.md` § "Dívida de empacotamento".

### Como ler este documento

Duas convenções que valem para todo o texto:

1. **Nome de componente ou prop citado é do espelho, não do codebase alvo.**
   `KpiFilterCard`, `StatusBadge`, `tone="amber"`, `state:'urgent'` descrevem *o que a peça faz*.
   Quando o valor importa, ele está em número — §10.2 dá as frações de cor; §10.1 dá os nomes
   Lucide. Onde houver conflito entre um nome de prop e um valor numérico, **o número vence**.
2. **"Lista fechada" quer dizer completa.** Onde o documento marca uma lista como fechada (o menu
   `⋯` em §4.1.1, o menu da linha, os estados do selo em §10.2), ela é o conteúdo inteiro: não
   completar com o que existir no codebase. Onde não houver marca, a lista é ilustrativa.

### O que este pacote NÃO resolve

1. **Backend.** Nenhuma rota, controller, policy ou migração. O protótipo carrega 14 itens em
   memória para exercitar os estados.
2. **Autorização real.** O recorte de custo/margem/compras é simulado por props (`perfil`,
   `permiteMargem`); a regra verdadeira é do servidor — §9.
3. **Volume real.** A paginação funciona, mas nunca foi exercitada com 13 mil linhas;
   virtualização não foi avaliada.
4. **Mobile.** Desenhada para cockpit desktop. Abaixo de ~1000px a tabela rola na horizontal; o
   seletor de colunas mitiga, não resolve. Versão mobile é projeto futuro (decisão do cliente).
5. **Escrita.** Toda ação (ativar, inativar, duplicar, exportar, importar, abrir cadastro, formar
   preço, ajuda) responde com `Toast` e não persiste nada. Ação de orçamento não existe nesta tela (§19.2).
6. **Auditorias deste pacote estão desatualizadas.** `LAUDO-*` e `CHECKLIST-15D-*` são de
   2026-08-17 e medem a tela anterior às 27 ondas. Precisam ser re-rodados.

## 2 · O que a tela faz

Responde, em uma lista, a pergunta que o balcão faz cem vezes por dia: *este item existe, tem
saldo vendável, e vende por quanto?* A lista permanece enxuta; o detalhe vive no painel.

**A tela é leitura.** Formar preço é cadastro; calcular pedido é orçamento/PDV. O painel entrega o
usuário na tela responsável ("Abrir cadastro", "Formar preço") em vez de fazer o trabalho dela.
**O caminho para o orçamento não parte daqui** — decisão do Wagner em 26/08, §19.2.

**Personas**

| Persona | Uso | O que precisa em 3 segundos |
|---|---|---|
| **Larissa · balcão** | consulta durante o atendimento | achar o item, ver saldo vendável e preço |
| **Rafael · compras/estoque** | reposição | quem está abaixo do mínimo, onde está, de quem se compra |
| **Wagner · dono** | saúde do catálogo | margem sob o piso, itens parados, valor em estoque |

**As regras que governam tudo**

- `[V0]` **Valor:** custo e margem só existem para perfil autorizado — **não estão no DOM** quando
  não autorizados, não é ocultação por CSS.
- `[T0]` **Tenant:** todo item pertence ao tenant da sessão; o servidor é a autoridade (§9).
- `[S1]` **Subconjunto se declara.** Quando o número exibido é parte de um todo (saldo vendável vs.
  físico, soma da grade, montáveis de kit), a linha diz isso. Nunca omitir em silêncio.
- `[S2]` **Dois números do mesmo fato fecham.** Custo do kit = soma da composição; saldo do pai =
  soma da grade. Derivado em código, nunca digitado nos dois lugares.

## 3 · Layout

### 3.1 Grade

```
┌──────────────────────────────────────────────────────────────────────────────┐
│ Produtos                                    [Escuro] [⋮] [ Novo ]            │ PageHeader
│ 14 cadastrados                                                               │
├──────────────────────────────────────────────────────────────────────────────┤
│ Todos 13 │ Produtos 7 │ Serviços 2 │ Matéria-prima 3 │ Kits 1 │ Inativos 1   │ TabBar
├──────────────────────────────────────────────────────────────────────────────┤
│ [Abaixo do mínimo] [Sem saldo] [Sem venda 90d]* [Margem baixa]*              │ KpiFilterCard
├──────────────────────────────────────────────────────────────────────────────┤
│ Categoria▾ Tipo▾ Marca▾ Disponível▾ │ Código ↑▾ │ Limpar │ 13 reg │ [buscar /]│ facetas
├──────────────────────────────────────────────────────────────────────────────┤
│ ☐ │CÓDIGO│ PRODUTO           │TIPO│ DISPONÍVEL          │CUSTO*│PREÇO│MRG*│⋯ │
│ ☐ │ 1042 │ [img] Banner lona │PROD│ Disponível 96 m²    │ 41,20│75,52│ 54%│⋯ │
│   │      │ m² · Impressão    │    │ +32 m² reservado    │      │     │    │  │
├──────────────────────────────────────────────────────────────────────────────┤
│ Valor em estoque (recorte, físico) R$ …  │  Repor até o mínimo (vendável) R$ …│
│                                     Mostrando 1–10 de 13 · [10▾] ‹ 1/2 ›     │ rodapé
└──────────────────────────────────────────────────────────────────────────────┘
        * só perfil autorizado          [BulkBar flutuante quando há seleção]
```

| Coluna | Largura | Observação |
|---|---|---|
| seleção | 44px | injetada pelo `DataTable` com `selectable` |
| Código | 88px | mono; **botão de copiar** (cursor `copy`, não abre o painel) |
| Produto | flex | miniatura 30px + nome + 2ª linha |
| Tipo | 86px | só nas abas Todos e Inativos |
| Disponível | 210px | selo com valor + 2ª linha de marcadores |
| Custo | 108px | só perfil autorizado |
| Preço de venda | 136px | |
| Margem | 92px | só com permissão de margem |
| Ações | 48px | um gatilho ⋯ de 30px |

- `min-width` da tabela é **calculado** a partir das colunas visíveis (soma das larguras + 44 da
  seleção + 48), num wrapper `overflow-x: auto`. Esconder colunas realmente elimina a rolagem.
- A página rola; a tabela não tem rolagem vertical interna.
- Contêiner: moldura canônica do template de índice do DS — borda 1px `--border`,
  `border-radius: var(--radius-lg)`, `box-shadow: 0 1px 2px rgba(0,0,0,.04)`, fundo `--surface`.
  Mesma moldura no bloco de carregamento e no de estado vazio. Fonte: `templates/pt-01-lista`,
  slot 4 — **[TPL]**, não decisão desta tela (patch de cor §3.1).

### 3.1.1 Shell — estrutura do template PT-01  **[TPL]**

A tela segue a arquitetura do template de índice, não uma própria:

```
.cockpit  display:flex · fundo = dois radial-gradients de acento sobre var(--bg)
├─ AppSidebar                                            222px
└─ <main>  flex:1 · flex column · min-height:100vh
   ├─ (topbar REMOVIDA em 27/08 — §25.1; não reintroduzir)
   ├─ PageHeader                       padding 0 var(--d-cpad-x)
   ├─ TabBar                           padding 0 var(--d-cpad-x)
   ├─ faixa de KPI                     padding var(--d-cpad-y) var(--d-cpad-x) 0
   ├─ toolbar   border-top + border-bottom · bg --bg · padding var(--d-tb-y) var(--d-cpad-x)
   ├─ área de conteúdo   flex:1 · min-height:0 · overflow:auto     ← só ela rola
   │    tabela | Skeleton | EmptyState  (mesma moldura, §3.1) + BulkBar
   └─ rodapé   border-top · bg --surface · padding 7px var(--d-cpad-x)
        totais do recorte · Pagination
```

⚠️ **A árvore acima está desatualizada no que diz respeito a rolagem — ver §19.1.** A rolagem é da
**página**, como o `Index.charter.md` do `main` sempre declarou; a área de conteúdo **não** rola por
dentro e **não** declara `overflow`. O resto da árvore (ordem dos slots, alturas do chrome, tokens de
padding) continua valendo.

Três consequências que a implementação precisa respeitar:

1. ~~**O cabeçalho não rola — e o cap tem de vir do shell.**~~ **REVOGADO em 26/08 pela dona da
   tela** — o modelo de rolagem é o do `main` (a página rola; o chrome sai de vista com ela) e só o
   **cabeçalho da tabela** fica, preso ao topo da janela. Ver **§19.1**. O texto abaixo fica como
   registro do que foi desconsiderado, não como instrução:

   ```
   .cockpit  height:100vh · min-height:0 · overflow:auto     ← escape, NÃO hidden
   main      min-height:0                                   ← NÃO min-height:100vh
   conteúdo  flex:1 · min-height:240px · overflow:auto       ← piso, NÃO min-height:0
   ```

   **Duas escolhas de valor que parecem detalhe e não são.** O template garante a **região de
   dados** (`height={420}` na tabela) e deixa o chrome se ajustar. Fazer o inverso — limitar o shell
   com `overflow:hidden` e dar `min-height:0` à área de dados — garante o chrome e deixa a tabela ir
   a zero: em viewport curto a lista mostra 1 de 10 linhas e **não há como ver o resto**, porque o
   documento também não rola. Foi o segundo erro de 25/08, no mesmo lugar.

   - `min-height:240px` na área de dados = o `height={420}` do template menos o chrome interno.
     É o piso: a lista nunca fica menor que isso.
   - `overflow:auto` no `.cockpit` = a válvula. Quando chrome + piso passam do viewport, a **página**
     rola (levando o cabeçalho), em vez de a tabela desaparecer.

   **`min-height:0` no filho é necessário e insuficiente.** Se algum ancestral tiver
   `min-height:100vh` sem `height`/`max-height` resolvido, `flex:1` resolve contra o **conteúdo**:
   a área cresce, nunca rola, e o documento inteiro passa a rolar levando o cabeçalho embora. Sem
   erro no console — só o sintoma.

   **Por que o template PT-01 usa `min-height:100vh` e funciona:** ele passa `height={420}` ao
   `DataTablePro`, que limita a tabela por dentro. O `DataTable` **não tem prop `height`**, então
   nesta tela o limite precisa vir do shell. Copiar o `min-height:100vh` do template sem copiar o
   `height` da tabela é a armadilha — foi exatamente o erro cometido em 25/08.

   *Teste, em duas alturas de janela:*

   | Janela | Deve acontecer |
   |---|---|
   | alta (≥900px) | área de dados cresce por `flex:1`; o cabeçalho **não se move** ao rolar a lista; o `.cockpit` não rola |
   | curta (~540px) | área de dados fica em **240px** e rola por dentro; o `.cockpit` rola como último recurso; **nunca** menos de ~4 linhas visíveis |

   Um teste só não pega o defeito: "documento não rola" passava com a tabela recortada a nada.

   **Chrome fixo, medido (rev. 21, sem topbar e sem `stats`):** PageHeader 57 + TabBar 36 + KPI 100 = 193px (era topbar 46 + PageHeader 80 + TabBar 36 + KPI 100 = 262px), mais toolbar e
   rodapé, que **variam** — em largura estreita quebram em 2 e 3 linhas (95px e 109px), levando o
   total a ~466px. Acima de ~1280px de largura os dois voltam a uma linha e o total cai a ~310px.
   Por isso o piso da área de dados é obrigatório: o chrome não tem altura fixa.
2. **A paginação está fora do card**, em barra própria no rodapé do `<main>` — não dentro da moldura
   da tabela. Com a rolagem de página ela fica **depois** da tabela no fluxo, como no `main`.
3. **O `BulkBar` fica dentro da área que rola** e sem invólucro: o componente já é
   `position:sticky; bottom:16; margin:0 auto` (`BulkBar.jsx` L2143-2148). Envolvê-lo em outro
   sticky quebra a ancoragem.

> ⚠ **Superado pela §25.1 (27/08):** a topbar não existe mais. O parágrafo abaixo é histórico.

**Uma adaptação declarada [TELA]:** a busca global do topbar era um **botão** que abria a paleta ⌘K, não
um `<input>` como no template. Em 27/08 virou campo real (§25.6) e, no mesmo dia, saiu junto com a faixa.

### 3.1.2 Criticidade da observação é campo próprio, não a existência da tag

O dado tem **dois campos separados**, e confundi-los inverte o alarme:

| Campo | Governa | Exemplo |
|---|---|---|
| `obs.tag` | o **texto** do chip | "Sob encomenda", "Exige aprovação" |
| `obs.critica` | a **cor** do chip e o `Alert` no painel | `true` só em "Exige aprovação" |

"Sob encomenda" é condição declarada, não impedimento: sai em `TagChip` neutro. "Exige aprovação"
trava uma ação de venda: sai em `--color-destructive` e sobe como `Alert` no topo do painel. Uma nota
sem tag sai neutra com o rótulo "observação".

**A regra errada era `if (obs.tag)`** — fazia toda observação rotulada ficar vermelha, deixando um item
benigno mais alarmante que um item com nota livre. `critica` é campo do cadastro, digitado por quem
escreve a observação; **não derivar da tag nem de palavra no texto.**

*Confere se:* 1051 e 1080 ambos com chip — o de 1051 ("sob encomenda") neutro, o de 1080
("exige aprovação") vermelho; e só 1080 mostra `Alert` ao abrir o painel.

### 3.2 Anatomia da linha

**Miniatura 30px** (foto do produto; sem foto, quadro de borda tracejada com `package` do DS) →
**nome** (600, 13px, caixa normal) → **2ª linha**: `unidade · categoria` (mono 11px) +
marcadores semânticos, quando existem:

| Marcador | Quando | Aparência |
|---|---|---|
| chip de observação | item com nota | `TagChip` do DS com a tag como rótulo, ou "observação" quando a nota não tem tag; tooltip com o texto. **Vermelho só quando `obs.critica`** — ver abaixo |
| "encontrado por Preto · 1,40 m" | busca casou com código de filho | accent, 11px |
| "4 de 6 com saldo" | produto com grade | vermelho quando há furo |
| "+32 m² reservado" | há saldo em local bloqueado | `--text-mute` |
| "2 locais" | mais de um local | tracejado, com tooltip |

**Densidade compacta** (menu ⋯ → desmarcar "Linhas confortáveis") faz **duas coisas**, e a primeira
é a que importa: escreve os tokens `--d-*` no shell — `--d-td-y` 10→6px, `--d-cpad-x` 22→14px,
`--d-tb-y` 11→7px e a rampa `--fs-1..9` inteira (patch de cor §3.2). Só depois disso, e como
decisão de produto separada, esconde o redundante da célula: `unidade · categoria` e "N locais".
Os quatro marcadores semânticos ficam.

## 4 · Passo a passo da tela

### 4.1 Abas e KPI-filtros

Abas: Todos · Produtos · Serviços · Matéria-prima · Kits · Inativos, com contador. Trocar de aba
zera KPI, seleção, filtro de tipo e volta à página 1.

KPI-filtros clicáveis, com **quatro** regras de visibilidade — nenhuma é preferência de layout,
todas foram decididas (§19.4 e §19.5):

| KPI | Aparece quando |
|---|---|
| Abaixo do mínimo | perfil com `reposicao` (**não** vendedor) **e** aba ≠ Serviços |
| Sem saldo | aba ≠ Serviços |
| Sem venda 90d | perfil com `custo` **e** aba ≠ Matéria-prima (**inclui** Serviços — §22.1) |
| Margem baixa | perfil com `margem` **e** aba ≠ Matéria-prima |

**KPI invisível não filtra.** O recorte ativo é ignorado quando o cartão que o representa não está
na faixa — senão a lista fica filtrada por um critério sem controle na tela (§19.5).

### 4.1.1 O menu `⋯` do cabeçalho — lista FECHADA

**Este menu é de apresentação da tela e de dados. Navegação não entra nele.** A lista abaixo é
completa: implementar exatamente estes itens, nesta ordem, com estes separadores, e **não
acrescentar** entradas encontradas no codebase.

```
APRESENTAÇÃO
  ✓ Linhas confortáveis          alterna densidade (§3.2)
  ─────────
  ✓ Coluna tipo                  \
  ✓ Coluna custo                  |  uma entrada por coluna ocultável,
  ✓ Coluna preço de venda         |  gerada da definição de colunas;
  ✓ Coluna margem                /   custo e margem só se houver permissão
  ─────────
DADOS
  Importar planilha              abre o assistente
  Exportar planilha              exporta o recorte inteiro, não a página
```

O ✓ é estado, não decoração: marcado = coluna visível / linhas confortáveis. Ambos persistem em
`oi.produtos.recorte.v1` (§4.7).

**Por que navegação não entra.** Categorias, Insumos · BOM, Tabelas de preço e Histórico de uso
são **outras telas**, não visões desta. Pendurá-las aqui faz um menu com três naturezas
(apresentação, navegação, dados) e obriga o usuário a abrir um menu de colunas para descobrir para
onde ir. Ir a outra tela é trabalho da sidebar; esta tela navega para outra tela apenas pelas duas
ações do rodapé do painel — "Abrir cadastro" e "Formar preço" (§2, §5, §19.2), que
levam **o item aberto** consigo. Se essas quatro telas precisam de acesso daqui, o lugar é a
sidebar do módulo, e é decisão de fora deste handoff.

**Menu da linha (`⋯` da coluna Ações), também fechado:** Ver detalhes · Copiar código · Duplicar ·
separador · Inativar produto (tom `danger`, abre `Modal` de confirmação nomeando o produto).

### 4.2 Facetas, ordenação e busca

- Facetas: Categoria, Tipo (só nas abas Todos/Inativos), Marca, Disponível. O rótulo do gatilho
  mostra o valor ativo ("Disponível: Com saldo"). Não há chips: o gatilho já é o estado.
- **Ordem** é um `DropdownMenu` próprio, com Código, Produto, Categoria, Disponível, Preço,
  Margem, marca no item ativo e inverter. O gatilho mostra a ordem corrente ("Código ↑"), então o
  padrão é explícito ao abrir. Clicar no cabeçalho da coluna também ordena e reflete no gatilho.
- Busca à direita, com `kbd` "/" visível e `aria-keyshortcuts`. Varre nome, código, referência,
  categoria **e códigos de filho da grade**, sempre resolvendo para o pai.
- Qualquer mudança de recorte (aba, KPI, faceta, busca, ordem) **limpa a seleção**.

### 4.3 Coluna Disponível

Selo do DS com rótulo + valor na mesma linha ("Disponível 96 m²"), e a 2ª linha com os
marcadores. Quatro estados: Disponível · Abaixo do mínimo · Sem saldo · Não estocável.

O número é o **saldo vendável** (§6), não a soma dos locais.

### 4.4 Seleção e ações em lote

`selectable` no `DataTable`; a caixa do cabeçalho marca a página **somando** à seleção existente,
e a `BulkBar` informa "3 itens selecionados · 2 fora desta página". Ações: Exportar seleção,
Gerar etiquetas, Inativar (destrutiva → `Modal` de confirmação nomeando a quantidade e o efeito).
"Inativar produto" no menu da linha usa o mesmo `Modal`, nomeando o produto.

### 4.5 Teclado

| Tecla | Efeito |
|---|---|
| `/` | foca a busca |
| `⌘K` / `Ctrl+K` | **único caminho para a busca global** desde §25.1 (a faixa que a exibia saiu) — paleta `Command` — Ações, Abas (com contagem), **Recentes** (8 últimos), **Grade** (códigos de filho), Recortes |
| `↑` `↓` | move a linha ativa (vira página sozinha); com painel aberto, navega entre produtos |
| `↵` | abre a linha ativa |
| `esc` | solta a linha ativa; fecha painel/paleta |

### 4.6 Paginação e totais

Rodapé sempre presente quando há linhas. À esquerda, totais do **recorte** (não da página), só
com permissão de custo: **Valor em estoque (recorte, físico)** e **Repor até o mínimo (saldo
venda)** — rótulo trocado em 21.2. À direita, `Pagination` do DS com "N–M de T" e itens por página.

### 4.7 Persistência

`localStorage` sob `oi.produtos.recorte.v1`, com debounce de 250ms: aba, KPI, busca, ordem, itens
por página, colunas ocultas, densidade e recentes. JSON inválido ou cota cheia não quebram a tela.

## 5 · O painel de detalhe (Drawer)

Largura 480px com grade ou composição, 420px nos demais. Ordem das seções — **disponibilidade
primeiro, cadastro por último**:

1. **Alertas** — produto inativo; observação crítica (`Alert`).
2. **Tira de identidade** — miniatura 60px + **selo de situação de saldo abaixo dela** + selo do
   tipo + categoria. O selo de saldo **não** fica no cabeçalho do `Drawer` (§19.3).

**Cabeçalho do painel:** nome do produto e código ficam **na faixa do cabeçalho, acima da linha**,
à esquerda do ✕ — não no bloco abaixo dela (§19.6).
3. **Barra fixa de atalhos** — só em painel longo (kit ou grade); rola até a seção. Os botões são
   o `Button` do DS (`variant="ghost" size="sm"`); só o contêiner fixo é da tela (§18.1).
4. **Disponível para venda** (kit) → **Composição**; ou **Disponível** (demais) → **Grade**.
5. **Preço e margem** — preço → margem* → custo* → limite de alçada → procedência → faixas.
6. **Reposição** — fornecedor, última compra, custo na última compra (permissão `compras`);
   prazo, entregas e pedido mínimo quando sob encomenda.
7. **Estoque** — mínimo, reservado, garantia, baixas, valor, por local.
8. **Giro** — última venda.
9. **Identificação** — marca, código e referência (ambos copiáveis).
10. **Observações** — só quando a nota não é crítica (a crítica já subiu ao alerta).

Rodapé: navegação ‹ › com "2 de 13", **Abrir cadastro**, **Formar preço** (com permissão de custo).
Não há botão de orçamento e **não há botão primário** no rodapé (§19.2).

## 6 · Saldo: vendável, físico e custódia

Cada local tem **natureza**, e é ela que decide o que entra em cada conta:

| Natureza | Exemplos | Entra no vendável? | Entra no valor? |
|---|---|---|---|
| `venda` | Loja, Depósito | sim | sim |
| `bloqueado` | Produção, Obra | **não** (aparece como "Reservado") | **sim** (é da empresa) |
| custódia | garantia de cliente | não | **não** (não é ativo da empresa) |
| baixa | quebras e perdas | não | não (é lançamento, não local) |

- **Disponível** = soma dos locais `venda`. Alimenta coluna, selo, estado, ordenação, KPI "Sem
  saldo" e o realce vermelho da linha.
- **Físico** = vendável + bloqueado. Alimenta "Valor em estoque" e o total do recorte. O rótulo
  vira "Valor em estoque (inclui reservado)" quando há bloqueado.
- **Custódia** (mercadoria de cliente em garantia) tem linha própria, com quantos clientes e a
  etapa. Fora de saldo e de valor.
- **Baixas** aparecem como **taxa com janela** ("Baixas em 90 dias: 6 un · R$ 111,60 · quebra no
  manuseio"), nunca como saldo acumulado.
- **Kits produzidos não deve ser local** — montagem é transformação; como local, o mesmo material
  seria contado duas vezes.

## 7 · Grade, preço e kit — o modelo

**Grade (filho) é o item físico.** Saldo, mínimo, local e código próprios por combinação. O saldo
do pai é **derivado** (soma) e declarado como tal; não é vendável como bloco. Venda pelo pai — o
código do filho serve para *encontrar* (a busca resolve para o pai indicando a combinação).
No painel, matriz eixo × eixo com célula = código · saldo · preço · margem*, legenda no cabeçalho.
Toda célula tem a mesma superfície; o estado vai no número e num trilho de 2px na borda esquerda —
zerada em vermelho, abaixo do mínimo em âmbar (valores em §18.2). **Sem placa de fundo tintada.**

**Preço:** base + faixas por quantidade **no pai** (uma tabela por produto, independente de ter
grade). **Acréscimo por combinação** no filho, percentual ou valor — a célula mostra o preço já
resultante. A coluna da lista mostra o **preço base**, com tracejado e tooltip para as faixas.

**Alçada de desconto** é do colaborador, incide sobre o **preço de tabela** (nunca sobre custo,
por isso pode ser exibida a quem não vê custo) e aparece **por faixa** ("até R$ 2.007,81" ao lado
de cada degrau). Abaixo do limite, aprovação.

**Kit:** custo e disponibilidade derivados da composição. Montáveis = mín(saldo do componente ÷
qtd por kit); "Pode vender agora" = montados + montáveis. A composição mostra código, saldo,
capacidade ("comporta 56 cj"), quantidade, subtotal de custo* e qual componente limita, e fecha
com "Custo do kit (soma da composição)". Quando não há montagem possível, `Alert` nomeia o
componente que falta.

## 8 · Permissões

Cinco chaves: `preco`, `custo`, `composicao`, `compras`, `margem`.

| Chave | Cobre | Regra |
|---|---|---|
| `preco` | preço de venda, faixas, alçada | sempre concedida nesta tela |
| `custo` | custo, valor em estoque, totais, KPIs de gestão | perfil autorizado |
| `composicao` | composição do kit | com `custo`; em kit, **oculta o custo também** (o custo revelaria a composição por dedução) |
| `compras` | fornecedor, última compra, custo na última compra | dado de relacionamento comercial, não de atendimento |
| `margem` | margem na coluna, no painel e na grade | **exige `custo`**: preço + margem revelam o custo (`custo = preço × (1 − margem)`). O inverso é válido e útil (comprador vê custo, não precisa de margem) |

Sem permissão, a tela **não deixa o campo vazio**: diz "Custo e margem são restritos ao
administrador" / "Margem restrita pelo administrador" / "A composição deste kit e o custo derivado
dela não são exibidos no seu perfil".

Onde se configura: visibilidade no **perfil de acesso**; alçada no **cadastro do colaborador**
(por pessoa, não por perfil); piso de margem e janela de dias como **parâmetros do módulo**. A
ficha do colaborador deve ser o ponto único de entrada — ver `contexto/recomendacoes-outras-telas.md`.

## 9 · O que o servidor precisa garantir

| Garantia | Por quê |
|---|---|
| Filtrar custo, margem, composição, fornecedor e histórico de compra **na consulta**, por permissão | Esconder no front não é permissão: se o dado chega ao navegador, quem abre a aba de rede o vê |
| `margem` só concedida junto de `custo` (validação no cadastro do perfil) | Preço + margem revelam o custo por aritmética |
| Saldo do pai **derivado** da grade; nunca campo digitado | Dois números do mesmo fato divergem em produção (`[S2]`) |
| Custo do kit **derivado** da composição | Idem — o protótipo já calcula assim |
| Natureza do local (`venda`/`bloqueado`) como atributo do cadastro de local | Sem isso, "disponível" soma o que não vende |
| Custódia de cliente **fora** do estoque e do valor do ativo | Mercadoria de terceiro no ativo é erro de balanço |
| Baixa como **lançamento** que reduz saldo, com data/motivo/custo | Como local, vira total histórico sem pergunta correspondente |
| Alçada calculada sobre preço de tabela, no servidor | O front exibe; a trava é do orçamento |
| Escopo de tenant em toda consulta e em toda ação de lote | `[T0]` |

## 10 · Design tokens

**Nenhum token novo.** Tudo vem do bundle do DS. Cor sempre `var(--*)` ou `color-mix` sobre token
— zero hex/OKLCH literal.

| Uso | Tokens |
|---|---|
| Acento (roxo, hue 295) | `--accent` · `--accent-2` · `--accent-soft` |
| Superfícies | `--bg` · `--bg-2` · `--surface` · `--border` |
| Texto | `--text` · `--text-dim` · `--text-mute` |
| Semântico | `--destructive` · `--color-destructive` · `--color-warning` |
| Tipografia | `--font-sans` (IBM Plex Sans) · `--font-mono` (IBM Plex Mono) |

Espaçamento 4/8 · raio 4–6px em célula e chip · números `tabular-nums` · ícones só do pacote do
DS (`window.Icon`).

**"Zero literal" quer dizer zero literal *inventado por você*.** Literal OKLCH **transcrito** de um
componente do DS é citação e é obrigatório onde o componente traz um — é o caso do `fg` de
"Sem saldo" (`oklch(0.74 0.14 18)`). Proibido é inventar, calcular, converter ou **substituir por
token que pareça equivalente**. Tabela completa no patch de cor §6.

### 10.1 Mapa de ícones — leia antes de implementar

**O protótipo NÃO é a referência de ícone.** O DS do produto usa **Lucide React**
(`lucide-react`, canon do guia: "Lucide React exclusively"). O bundle espelhado neste pacote
(`window.Icon`) carrega só **22 glyphs** — um subconjunto do espelho, não o inventário do sistema.
Onde o glyph não existia, o protótipo desenhou o mais próximo. **Implemente pela coluna Lucide.**

| Onde | Lucida (produção) | Glyph no protótipo | Nota |
|---|---|---|---|
| Gatilho ⋯ (linha e header) | `MoreHorizontal` | `menu` (três traços) | o hambúrguer é fallback; **não** reproduzir. Um só formato nos dois lugares — horizontal |
| KPI Abaixo do mínimo | `TriangleAlert` | `bell` | é alerta de operação, não notificação |
| KPI Sem saldo | `Ban` | `lock` | `lock` conflita com "bloqueado" do sub-rótulo — trocar |
| KPI Sem venda 90d | `Clock` | `clock` | igual |
| KPI Margem baixa | `Percent` | `bar-chart-3` | o número é percentual |
| Miniatura sem foto | `Image` | `package` | ver pauta do DS · defeito |
| Chip de observação | `FileText` | `file-text` | igual |
| Navegação do painel ‹ › | `ChevronLeft`/`ChevronRight` | `chevron-down` rotacionado | igual em desenho |
| Ordenação no cabeçalho | `ChevronsUpDown` inativo · `ChevronUp`/`ChevronDown` ativo | `↕ ↑ ↓` como texto | o duplo-chevron da produção está certo |
| Busca | `Search` | — | idem |

### 10.2 Cor — medida no componente, não inferida

**Regra:** valor de cor, tamanho ou raio de elemento que o DS renderiza **não se escreve — se cita**,
com arquivo e linha. A versão anterior desta seção trazia 6% / 22% / 600 / `rounded-xl` /
`ring-primary/40`, inferidos da prosa do guia. Medido no bundle, **estavam errados**.

Os valores completos, medidos em 24/08/2026, estão em **`contexto/patch-cores-consulta-produtos.md`
(revisão 2)**, que é a fonte para cor nesta tela. Resumo do que a medição mudou:

| Elemento | Estava escrito aqui | Medido no componente |
|---|---|---|
| Selo Disponível | fundo 6%, borda 22%, texto 700, raio `rounded-md`, 12px | `StatusBadge.jsx` L6357-6373 e L6484-6497: fundo **16%**, borda **30%**, peso **500**, raio **9999** (pílula), `--fs-2` = **11,5px** |
| Selo "Sem saldo" | texto `rose-700` | `oklch(0.74 0.14 18)` — o próprio DS clareia o tom em `fresc-cold` |
| Placa do KPI | fundo 6%, **borda 22%**, glyph 600 | `KpiFilterCard.jsx` L4866-4872: fundo **18%**, **sem borda**, glyph em literal OKLCH por tom |
| Card do KPI | `rounded-xl`, `ring-primary/40` | raio **8**, `padding` 12, `box-shadow: 0 1px 2px rgba(0,0,0,.05)`; selecionado = borda `--color-primary` + anel de **1px cheio** |
| Acento da aba | "`--accent`" | **`--color-primary`** — `--accent` é reescrito em runtime pelo seletor de matiz do shell (ver pauta do DS) |

**Procedência obrigatória.** Todo valor de cor deste pacote é **[DS]** (citado com arquivo e linha),
**[TELA]** (decidido aqui, com justificativa, por não haver equivalente no DS) ou **[RUNTIME]**
(observado num navegador — **nunca normativo**). As três marcas estão aplicadas no patch.

**Onde o DS contradisser este README, o DS vence** e o caso vai para
`contexto/pauta-design-system.md`.

## 11 · Acessibilidade — medido, não presumido

- Busca com `aria-keyshortcuts="/"` e `aria-label`, aplicados depois que o `Input` do DS monta.
- Sub-cabeçalhos "Por local" e "Preço por quantidade" com `role="heading" aria-level="5"`
  (os títulos de seção do `DrawerSection` já são `h4`).
- Foco: o `Drawer` do DS foca o primeiro elemento, prende o Tab, fecha no esc e devolve o foco.
- Miniatura sem foto tem `role="img"` + `aria-label="Produto sem imagem"`.
- Tooltips do DS abrem em hover **e** foco.

**Medido e reprovado, por decisão declarada:** os três tons de selo da coluna Disponível dão
2,86 / 2,36 / 1,94:1 sobre a placa clara, e o **chip de observação crítica** (`--color-destructive`
cheio sobre fundo a 16%, `--fs-1`) dá **≈2,4:1** — contra a régua de 4,5:1. São valores do DS, aplicados como
o DS manda; a família `fresc-*` foi autorada para o cockpit escuro. **Não corrigir na tela** —
está na pauta do DS como P1 aguardando decisão do Wagner. Ver patch de cor §8.

**Não verificado:** o resto do contraste par-a-par nos dois temas após as 27 ondas · zoom 200% · leitor de
tela · Lighthouse/axe · navegação por teclado dentro da matriz da grade · alvo de toque de 44px
(tela é desktop).

## 12 · Defeitos e lacunas do design system

Pauta completa, com estado atual, contorno, proposta e prioridade:
**`contexto/pauta-design-system.md`**. Resumo:

> **O inventário de glyphs do espelho não é o do produto.** O `Icon` deste bundle tem 22 glyphs;
> o produto usa Lucide inteiro. Todo fallback de ícone do protótipo está mapeado em §10.1.

| Item | Tipo | Contorno na tela |
|---|---|---|
| `DataTable` sem densidade | P1 · falta prop | compacto colapsa conteúdo de célula |
| `Drawer` sem variante ancorada | P1 · falta variante | nenhum; pendência aberta |
| `DrawerSection` não colapsável | P2 | barra de atalhos no topo do painel |
| `TabBar` gera barra de 1px | D · ADR 0403 | override no `<helmet>`, mirando atributo semântico |
| `Icon` falha silenciosa; sem `ellipsis`; sem `image` | D | `menu` no overflow, `package` na miniatura |
| `DropdownMenu` acrescenta caret ao gatilho-nó | resolvido | forma render-função |

| Família `fresc-*` e receita "tom cheio sobre 16%" reprovam contraste em tema claro | D · **aguarda decisão** | nenhum: aplicado como o DS manda (atinge os 3 selos `fresc-*` **e** o chip crítico) |
| `TagChip` sem tom de perigo nem slot de ícone | P2 | geometria citada do `TagChip` + tokens `destructive`; caso neutro **usa** o `TagChip` |

ADRs anteriores em `design/adr/`. **A ADR 0401 (cores cruas) NÃO pode ser fechada como "zero
literal"** — a tela tem, legitimamente, um literal transcrito do `StatusBadge`. O que ela deve
declarar é o critério certo: zero literal **inventado**; transcrição do DS é citação. Correção da
leitura anterior, que dizia "não há mais literal de cor na tela".

## 13 · Estado (React)

| Grupo | Variáveis |
|---|---|
| Recorte | `aba` · `kpi` · `busca` · `f{categoria,tipo,marca,estoque}` · `ordem{key,dir}` |
| Paginação | `pagina` · `porPagina` |
| Lista | `sel[]` · `ativa` · `colsOcultas[]` · `densa` · `recentes[]` |
| Overlays | `abertoId` · `paleta` · `confirmar{tipo,acao,nome}` · `toast{texto,tone,k}` |
| Tema | `tema` (só no protótipo) |

**Derivados (nunca em estado):** `vendavel` · `fisico` · `bloqueado` · `custoDe` (kit = soma da
composição) · `margemFrac` · `montaveis` · `faixasDe` · `precoCombo` · `estado` · totais do
recorte · `idsOrdenados`.

**Persistência:** `oi.produtos.recorte.v1` (§4.7).

## 14 · Arquivos

| Arquivo | Papel |
|---|---|
| `README.md` | este documento — o que a tela faz |
| `contexto/SPEC-consulta-produtos-v1.0.md` | de onde veio (congelado em v1.0) |
| `contexto/patch-cores-consulta-produtos.md` | **fonte de cor desta tela** — valores medidos no bundle, com arquivo e linha |
| `contexto/patch-charter-casos-2026-08-26.md` | **patch dos documentos de comportamento do repo** — blocos de substituição para `Index.charter.md` e `Index.casos.md` (§20) |
| `contexto/manual-escrita-para-agente.md` | **as 10 leis de escrita deste pacote** — ler antes de revisar ou gerar handoff |
| `contexto/pauta-design-system.md` | propostas e defeitos do DS |
| `contexto/recomendacoes-outras-telas.md` | o que pertence a Orçamento/PDV, Cadastro, RH, Compras + modelo de dados |
| `design/README.md` | como os arquivos se organizam |
| `design/Consulta de Produtos.dc.html` | a tela (template + lógica + estilo) |
| `design/support.js` | runtime do Design Component |
| `design/_ds/office-impresso-atual-…/` | camada 0 · bundle, tokens, classes, fontes |
| `design/adr/0401…0404` | ADRs abertas na primeira rodada |
| `design/LAUDO-conferencia-consulta-produtos.md` | conferência de 2026-08-17 — **desatualizada** |
| `design/CHECKLIST-15D-consulta-produtos.md` | score de 2026-08-17 — **desatualizado** |

**Como abrir:** duplo-clique em `design/Consulta de Produtos.dc.html`. Sem build, sem rede.

## 15 · Divergências observadas na implementação (24/08/2026)

Comparação entre o protótipo e `/products/unificado` em produção. Cada item é lacuna do handoff
anterior, não erro de quem implementou.

### 15.1 Abertas — o trabalho a fazer

Diff, não descrição do alvo: **está assim** hoje em produção, **deve ficar** assim, e a linha que
confere. Mexer apenas nestes sete pontos; o resto da tela está correto e **não deve ser tocado**.

| # | Está assim | Deve ficar | Como conferir |
|---|---|---|---|
| 2 | Placa do ícone do KPI branca, só o glyph colorido | fundo do tom a **18%**, **sem borda**, glyph no literal OKLCH do `TONE` — patch de cor §2 | inspecionar a placa de "Sem saldo": fundo rose perceptível, sem borda |
| 6 | Gatilho `⋯` como três pontos **verticais** | `MoreHorizontal` — três pontos horizontais, o mesmo glyph no header e na linha | comparar os dois gatilhos: idênticos e horizontais |
| 8 | 2ª linha sem "+N reservado" e sem "N locais" | ambos, conforme §3.2 · sem eles o número é subconjunto silencioso, contra `[S1]` | item 1088 (saldo em local bloqueado) mostra "+6 L reservado"; item com 2 locais mostra "2 locais" com tooltip |
| 11 | Menu `⋯` com grupo "Outras visões" (Categorias, Insumos · BOM, Tabelas de preço, Histórico de uso) | **remover o grupo inteiro.** O menu é lista fechada: 2 grupos, 7 itens — §4.1.1 | abrir o menu: zero link que navegue para outra tela |
| 13 | Aba ativa sem tinta: pílula do contador cinza | pílula no acento cheio com texto `--accent-fg`, botão a 50% do suave, sublinhado 2px — **acento vindo de `--color-primary`, não de `--accent`** — patch de cor §3 | a aba em tela é a única com pílula roxa, **e mudar o seletor de matiz do shell não altera essa cor** |
| 12 | Faixa com 3 KPI mesmo com a coluna Margem ativa | 4 KPI quando o perfil tem `margem`, na aba Todos — §4.1 e §19.4/§19.5 | com Margem visível, "Margem baixa" está na faixa |
| 5 | Facetas **Unidade** e **Margem** acrescentadas | **manter.** Unidade é boa ideia; incorporadas a §4.2 | — |

Sobre a #11: as quatro telas do grupo removido **não** ganham outro acesso nesta tela por conta —
essa é decisão de fora deste handoff (sidebar do módulo). **Não recolocar em outro lugar da tela.**

### 15.2 Fechadas em 24/08 — não reabrir

| # | O que era | Estado |
|---|---|---|
| 1 | Ícones do KPI escolhidos por conta | resolvido — §10.1 é normativa |
| 3 | Sem trilho vermelho na linha com problema | resolvido — 3px na borda esquerda |
| 4 | Marcador de grade saía como texto do cadastro | resolvido — derivado "N de N com saldo" |
| 7 | Chip de observação ausente da 2ª linha | resolvido |
| 9 | "· WR2 Sistemas" no cabeçalho | adotado — o tenant na página é melhor |
| 10 | Coluna Custo junto de Margem | correto — `margem` exige `custo` (§8) |
| 6b | Ordenação como "⇅ Código ↑" | adotado — o protótipo é que estava pobre |

### 15.3 Aplicado em 25/08 — auditoria contra o template PT-01

Sete valores que eu tratava como decisão desta tela e o **template canônico** já definia. Todos já
estão no protótipo; a implementação precisa acompanhar. Marca **[TPL]** — ver §0 item 1b.

| # | Está assim (produção) | Deve ficar | Como conferir |
|---|---|---|---|
| 14 | Tabela com borda reta | `border-radius: var(--radius-lg)` + `box-shadow: 0 1px 2px rgba(0,0,0,.04)` + fundo `--surface`, na tabela **e** nos blocos de carregamento e vazio | cantos arredondados com sombra de 1px |
| 15 | Densidade escondendo conteúdo de célula | tokens `--d-*` no shell + as 2 regras em `.cockpit table td/th` (patch §3.2). O colapso de conteúdo continua, **somado**, não em vez de | alternar "Linhas confortáveis" muda altura da linha **e** padding lateral da página |
| 16 | ~~Sem topbar~~ | **aceite revogado em 27/08 (§25.1):** a topbar sai do produto. O aceite passa a ser o inverso — `main > header` não existe e o primeiro filho do `<main>` é o invólucro do `PageHeader` | sem faixa de 46px, sem breadcrumb, sem botão `?` |
| 17 | Fundo liso `var(--bg)` | os dois radial-gradients de acento do template (§3.1.1) | bloom roxo no canto superior direito |
| 18 | Padding lateral 24px cravado | `var(--d-cpad-x)` em **todo** slot — é o que faz a densidade valer para a página | em modo compacto o padding lateral cai a 14px |
| 19 | — | **retirada: nada a fazer** — ver nota abaixo | `main` começa em x=260 |
| 20 | Paginação dentro do card; a página inteira rola | paginação em barra própria no rodapé do `<main>`; só a área de dados rola (§3.1.1) | rolar a lista: cabeçalho, abas, KPI e toolbar não se movem |

**Correção da #19, publicada na rev. 9 e retirada agora.** Ela mandava "sidebar 248px → 222px".
Errado duas vezes: (a) o `AppSidebar` do DS crava `width: 260, flex: 'none'`
(`AppSidebar.jsx` L1827-1830) e **não tem prop de largura**; (b) o "222px" saiu do
`hint-size="222px,100%"` do PT-01, e **`hint-size` é placeholder de streaming, não largura do
componente montado** — o host é `display: contents`. Medido em runtime:
`main.getBoundingClientRect().x = 260`. Quem implementasse "222px" cravaria uma largura brigando com
o DS. **Não mexer na largura da sidebar.** Se 222px for desejado, é proposta na pauta, não diff de
tela. Mesma causa dos tokens inertes `--d-sidebar`/`--d-navpy` — patch de cor §3.2.

E três que o `DataTable` **já desenha** — remover qualquer reimplementação (patch de cor §4):
trilho urgente (`state:'urgent'`), linha selecionada (`state:'selected'`), hover (`--bg-2`) e linha
arquivada (`state:'archived'`). Basta passar `state` na linha. Igual para `mono`/`align` na
definição de coluna: o componente aplica `font-mono` e `tabular-nums`.

**A causa das sete era uma só:** eu procurei a resposta no componente, não achei, e concluí que o DS
não tinha resposta. O DS decide por componente **e** por template — §0 item 1b.

### 15.4 Decidido em 25/08 — três divergências contra o template, com o motivo

Auditoria: dos 44 componentes do DS, **21 montados** nesta tela. Dos 23 não usados, 19 não se
aplicam (print-craft, Kanban, placa veicular, formulário, período). Os três restantes foram
medidos e decididos — **não reabrir sem motivo novo**.

| # | Template | Esta tela | Decisão e por quê |
|---|---|---|---|
| 21 | `FilterChip` para filtro ativo | sem chips; o rótulo do gatilho mostra o valor | **Manter sem chips.** No PT-01 os filtros vivem atrás de um botão "Filtros" e o chip é o único vestígio deles. Aqui os filtros **são** a toolbar — cinco `DropdownMenu` sempre visíveis com o valor no rótulo. O chip repetiria o dado numa linha extra, e altura é o recurso escasso (§3.1.1). Mesmo padrão, mecanismo diferente |
| 22 | `DataTablePro` | `DataTable` + `thead th` sticky por CSS | **Ficar no `DataTable`.** O `Pro` não aceita ordenação controlada (só `defaultSort`, sem `sortKey`/`sortDir`/`onSort`) nem `state:'selected'`; esta tela controla ordem por fora (gatilho "Ordem", `⌘K`, `localStorage`) e marca a linha ativa do teclado. Com o `Pro`, o gatilho dessincronizaria e o realce do teclado sumiria. O cabeçalho fixo — o ganho principal — vem por contorno de 1 linha de CSS. Proposta **P1** na pauta: dar modo controlado ao `Pro` |
| 23 | `TagChip` | `TagChip` no caso neutro; crítico com a geometria dele + tokens `destructive` | **Adotado.** Eu havia dito que não havia equivalente — errado: o docblock do `TagChip` diz *"Unknown tags fall back to neutral"*, e tag fora de `TAG_HUE` renderiza `--color-secondary` / `--color-secondary-foreground` / `--color-border`. É exatamente o chip neutro. O que **falta** de verdade é tom de perigo: o crítico usa a geometria citada (`TagChip.jsx` L4248-4258 — pílula 9999, `--fs-1`, 500, `lowercase`) com `--color-destructive` a 16%/30%. Proposta **P2** na pauta |

**Contorno declarado (o único novo desta rodada):**
`.cockpit thead th { position: sticky; top: 0; z-index: 2 }` — cabeçalho da lista fixo na rolagem
interna. Não é valor do DS; é contorno da tela pelo motivo do #22. Resize de coluna fica de fora.

### 15.5 Construído à mão, e por quê

75 `div` e 94 `span` com estilo inline no template, mais 21 `createElement` na lógica. A maior parte
é **conteúdo**, não chrome, e o próprio template faz igual: filhos de `DrawerSection` e conteúdo de
célula do `DataTable` são livres nos dois. O que é estrutura vem de componente.

Sem equivalente no DS, marcado **[TELA]** (§4 do patch de cor):

| Elemento | Por que não há componente |
|---|---|
| Miniatura 30px do produto | o DS não tem primitivo de imagem/thumbnail |
| Marcadores da 2ª linha ("N de N com saldo", "+N reservado", "N locais") | são texto derivado, não status nem tag |
| Chip de observação **crítica** | `TagChip` não tem `tone` de perigo; geometria dele é citada, só a cor muda (pauta · P2). O caso neutro **usa** o `TagChip` |
| Matriz da grade no painel | é matriz eixo×eixo, não lista — `DataTable` não serve |
| Barra fixa de atalhos do painel | `DrawerSection` não é colapsável (pauta · P2) |
| Linhas rótulo→valor do painel | livres também no template |
| `<kbd>` de atalho | o template também constrói à mão |

## 16 · Critério de aceite — conferir antes de entregar

Uma linha por requisito, verificável na tela pronta. Reprovado = não entregue.

| # | Como conferir | Passa se |
|---|---|---|
| 1 | Abrir a faixa de KPI com perfil autorizado, na aba **Todos** | **4** cards: Abaixo do mínimo, Sem saldo, Sem venda 90d, Margem baixa |
| 2 | Inspecionar a placa do ícone de "Sem saldo" | fundo rose a **18%**, **sem borda** — não branco (patch de cor §2) |
| 3 | Inspecionar o card do KPI | `--color-card`, raio 8; só a placa é tintada; selecionado com anel de 1px cheio em `--color-primary` |
| 4 | Abrir o menu `⋯` do cabeçalho | **2 grupos, 7 itens** (§4.1.1). Zero link de navegação para outra tela |
| 5 | Abrir o menu `⋯` de uma linha | 4 itens + separador; Inativar em tom danger e com `Modal` |
| 6 | Olhar o gatilho `⋯` nos dois lugares | três pontos **horizontais** (`MoreHorizontal`), o mesmo glyph nos dois |
| 6b | Olhar a faixa de abas, depois mexer no seletor de matiz do shell | pílula roxa só na aba em tela; a cor **não** muda com o seletor (vem de `--color-primary`) |
| 7 | Filtrar por "Sem saldo" | toda linha listada tem barra vermelha de 3px na borda esquerda; nenhuma linha normal tem |
| 8 | Abrir um produto com grade na lista | 2ª linha traz "N de N com saldo" **derivado**, vermelho quando há furo — não texto do cadastro |
| 9 | Achar um item com saldo em local bloqueado | 2ª linha traz "+N reservado"; o número da coluna **não** inclui esse saldo |
| 10 | Achar um item com observação | chip com a tag na 2ª linha, tooltip com o texto; se crítica, vermelho |
| 11 | Achar um item em mais de um local | chip "N locais", tracejado, com tooltip |
| 12 | Somar os locais de um item no painel | soma dos locais `venda` = número da coluna Disponível (`[S1]`) |
| 13 | Abrir um kit no painel | "Custo do kit" = soma dos subtotais da composição, sem digitar (`[S2]`) |
| 14 | Abrir o painel de um produto com grade | saldo do pai = soma da grade, declarado como soma |
| 15 | Entrar com perfil sem `custo` | Custo, Margem, totais do rodapé e KPIs de gestão **ausentes do DOM** — conferir na aba de rede que o payload não os traz |
| 16 | Entrar com perfil sem `composicao` em um kit | composição **e** custo do kit ocultos, com a frase de §8 |
| 17 | Esconder Custo e Margem no menu `⋯` | a rolagem horizontal desaparece em 1280px (`min-width` recalculado) |
| 18 | Recarregar a página | aba, KPI, busca, ordem, colunas ocultas e densidade preservados |
| 19 | Teclar `/` e `⌘K` | foca a busca · abre a paleta com Ações, Abas, Recentes, Grade, Recortes |
| 20 | Buscar o código de um filho de grade | resolve para o **pai**, com a combinação indicada na 2ª linha |
| 21 | Marcar a caixa do cabeçalho e virar a página | a `BulkBar` diz "N selecionados · M fora desta página" |
| 22 | Trocar de aba com itens selecionados | seleção zerada |
| 22b | Inspecionar o texto do selo "Sem saldo" | `oklch(0.74 0.14 18)` — o literal do DS, transcrito. **Não** `--color-destructive` (patch de cor §8) |
| 22d | Rolar a lista | cabeçalho, abas, KPI e toolbar ficam parados; só a área da tabela rola |
| 22e | Alternar "Linhas confortáveis" | muda a altura das linhas **e** o padding lateral da página (tokens `--d-*`), não só o conteúdo da célula |
| 22f | Selecionar itens e rolar | o `BulkBar` acompanha, ancorado a 16px do fundo da área de conteúdo |
| 22h | Achar um item com observação não crítica | chip neutro do `TagChip` do DS ("observação", minúscula, pílula), não construído à mão |
| 22g | Rolar a lista por dentro | o cabeçalho da tabela (CÓDIGO, PRODUTO…) fica fixo no topo da área de dados |
| 22c | Olhar os cantos da tabela | arredondados (`--radius-lg`) com sombra de 1px — igual ao template PT-01, não borda reta |
| 23 | Buscar no CSS da tela | zero hex; os únicos OKLCH literais são as transcrições do bundle citadas no patch de cor — nenhum calculado por você |
| 24 | Abrir o painel de um kit | ordem das seções conforme §5; disponibilidade antes de preço, cadastro por último |
| 25 | Inspecionar um botão da barra de atalhos do painel | `height` 26px, `font-size` 12px, `background` = `--surface`, raio 6px — é o `Button` do DS (§18.1). Zero estilo inline de cor |
| 26 | Abrir 1043 e olhar a grade | nenhuma célula com fundo tintado; `1043-PRE-100` (0 m²) com número vermelho + trilho `inset 2px`; `1043-BRA-140` com trilho âmbar; célula sem combinação com borda tracejada (§18.2) |
| 27 | Comparar "PREÇO POR QUANTIDADE" com "PREÇO E MARGEM" | `font-size`, `font-weight` e `letter-spacing` iguais (§18.3). Zero subtítulo com `.14em` |
| 28 | Medir o rótulo "Preço de venda" | `font-size` 12px, `color` `--text-dim` — o par do template PT-01 (§18.5). Zero rótulo de par em 12,5px no drawer |
| 29 | Inspecionar o espaço entre duas seções do painel | só o `border-top` do `DrawerSection`; nenhuma régua, sombra ou fundo alternado escrito pela tela (§18.4) |
| 30 | Com o painel aberto, clicar o ⋯ de uma linha | o painel fecha e o menu abre; nunca os dois visíveis ao mesmo tempo (§18.6). Zero override de `z-index` no código |
| 30b | Com o painel aberto, clicar o ⋯ do cabeçalho, e depois cada filtro (Categoria, Tipo, Marca, Disponível, Código) | em todos, o painel fecha antes do menu abrir (§18.6) |
| 31 | Rolar a lista até o fim | cabeçalho da tabela fixo no topo da área de dados, fundo opaco (§18.7) |
| 32 | Rolar a lista em janela de ~700px e medir o `th` | `position: sticky`, `top: 0px`, `z-index: 6`; nenhum pixel de linha sobre CÓDIGO / PRODUTO / TIPO / DISPONÍVEL / PREÇO DE VENDA / MARGEM (§19.1) |
| 32b | Buscar `background` na regra `.cockpit thead th` | **não existe** — nem `background`, nem `background-clip`; o fundo vem inline do `DataTable` (§19.1) |
| 33 | Abrir o rodapé do painel | **dois** botões de ação: "Abrir cadastro" e "Formar preço" (este só com `custo`). Zero botão de orçamento, zero `variant="primary"` (§19.2) |
| 34 | Abrir o painel de qualquer item | o selo "Disponível / Abaixo do mínimo / Sem saldo / Não estocável" está **abaixo da miniatura**, na tira de identidade; o cabeçalho do `Drawer` tem só título e código (§19.3) |
| 35 | Entrar com perfil vendedor | a faixa **não** tem "Abaixo do mínimo" (§19.4) |
| 36 | Selecionar a aba Serviços | a faixa não tem "Abaixo do mínimo" nem "Sem saldo"; com perfil autorizado sobram "Sem venda 90d" e "Margem baixa" (§22.1) |
| 37 | Ativar "Sem saldo", ir para a aba Serviços e olhar a contagem | a lista mostra **todos** os serviços do recorte — o KPI oculto deixou de filtrar (§19.5) |
| 38 | Abrir o painel e olhar a borda do cabeçalho | nome (17px/600) e código (mono 12,5px) **acima** da linha, na mesma faixa do ✕; nenhum bloco de título abaixo dela (§19.6) |
| 39 | Abrir o painel e ler o nome acessível do diálogo | `aria-label` = "nome · código" — não vazio (§19.6) |
| 40 | Passar o mouse no selo "Sem saldo 0 br" | dica com "br = barra"; em item com mais de um local, a mesma dica traz os locais **e** a linha da unidade (§19.7) |
| 41 | Olhar a tira de identidade do painel | tipo em **texto** (600 13,5px) + categoria (12px `--text-dim`); zero pílula de tipo ali. A única pílula da tira é o selo de saldo (§19.8) |
| 42 | Olhar o primeiro bloco de dados do painel | vai direto em "Saldo atual / Saldo vendável"; **sem** título "DISPONÍVEL" (§19.9) |
| 43 | Contar os botões do rodapé do painel | dois: "Formar preço" ghost e **"Abrir cadastro" primária, por último** (§19.2) |
| 44 | Selecionar a aba Matéria-prima | a faixa traz só "Abaixo do mínimo" e "Sem saldo"; nenhum dos dois recortes de gestão (§19.5) |
| 44b | Abrir 1067 (serviço) e medir do selo "Não estocável" à régua abaixo | **14px** — a tira tem `padding-bottom`, não encosta na seção seguinte (§19.8) |
| 45 | Olhar a tira de identidade | selo do tipo em código curto — PROD / SERV / M-PRIMA / KIT — igual ao da coluna Tipo (§19.8) |
| 46 | Rolar a **página** e medir `th.getBoundingClientRect().top` | **0** — o cabeçalho prende no topo da janela; nenhuma linha renderiza acima dele (§19.1) |
| 47 | Buscar `overflow` nos ancestrais da tabela | **nenhum** — nem `overflow-x`; a rolagem (vertical e horizontal) é do documento (§19.1) |
| 47b | Rolar a página até o fim | zero faixa vazia abaixo do rodapé: `scrollHeight` do documento = altura do `.cockpit`; a sidebar segue no lugar e rola por dentro quando o nav é mais alto que a janela (§19.1) |
| 48 | Abrir o painel e olhar acima de "Saldo atual" | sem régua entre a tira de identidade e o bloco de saldo; as outras seções continuam com a régua do `DrawerSection` (§19.9) |
| 49 | Selecionar a aba Serviços | os **dois** KPIs que sobram ocupam **um quarto** da faixa cada, alinhados à esquerda, com as duas trilhas restantes vazias — não esticam (§19.11 + §22.1) |
| 50 | Aba Serviços, perfil autorizado, contar os cartões | **dois**: "Sem venda 90d" e "Margem baixa" (§22.1) |
| 51 | Ler o sub-rótulo de "Margem baixa" em qualquer aba | `lucro abaixo de 37% do preço` — o número do piso vigente **e** a base do cálculo; mover `pisoMargem` para 0,40 muda o texto para `lucro abaixo de 40% do preço` (§22.2) |
| 52 | Aba Inativos, abrir o menu `⋯` de uma linha | último item é **Ativar produto**, sem tom de perigo; **não** existe "Inativar produto" no menu (§22.3) |
| 53 | Aba Inativos, marcar a linha e olhar a `BulkBar` | a terceira ação é **Ativar**, sem tom de perigo; confirmar abre "Ativar itens selecionados" com botão primário **Ativar** (§22.3) |
| 54 | Aba Todos, marcar 1094 (ativo) + 1133 (inativo) | a ação em lote lê **Inativar** (tom de perigo) — a seleção mista segue a regra do ativo (§22.3) |

| 55 | Medir a 2ª linha da célula Produto | `font-size: 11.5px`, `color: var(--text-mute)`, `margin-top: 1px` — valores do `Cell` do DS (§22.5) |
| 56 | Medir o nome do produto na célula | 13px/600 com `letter-spacing: -0.006em` (≈ −0,078px) (§22.5) |
| 57 | Medir as células das colunas Código e Custo | `font-size: 12.5px`, `letter-spacing: -0.01em`, família mono — as duas iguais (§22.5) |
| 58 | Buscar `var(--destructive)` no código da tela | zero ocorrência; 18 de `var(--color-destructive)`. `var(--warn)` continua com **2** — não trocar (§22.5) |
| 59 | Medir a legenda "sem imagem" e o código da célula da grade | **10,5px** os dois; nenhum texto da tela abaixo de 10,5px (§22.6) |
| 60 | ~~Medir o texto do gatilho de busca do topbar~~ | **revogado (§25.1)** — não há gatilho de busca global na tela |
| 61 | ~~Olhar à direita do gatilho de busca~~ | **revogado (§25.1)** — o botão `?` saiu com a faixa |
| 62 | Buscar `var(--accent` no código da tela | zero ocorrência fora de comentário — `a`, `a:hover`, `::selection`, marcador "encontrado por" e borda da célula achada leem `--color-primary` (§23.2) |
| 63 | Aba Todos, olhar os ladrilhos dos 4 KPI | quatro tons distintos: `amber` · `rose` · `violet` · `primary` — nenhum repetido (§23.3) |
| 64 | Medir o `min-width` da tabela contra a soma das colunas visíveis | soma + **36**, sem folga; a coluna de ações aparece **uma** vez na conta (§23.4) |
| 65 | Medir o `gap` da toolbar | **8px** (§24.1) |
| 66 | Olhar entre a faixa de KPI e a toolbar | **nenhuma** régua; a única linha da toolbar é a de baixo. Quatro réguas de 1px na primeira dobra (§24.1, §24.2) |
| 67 | Medir a contagem da toolbar | `11px/1` em `var(--font-mono)`, `--text-dim` (§24.1) |
| 68 | Passar o mouse no gatilho `⋯` (topo e linha) | fundo vira `--bg-2`, cor `--text`, borda `--text-mute`; em repouso tem borda `--border` e fundo `--surface` (§24.2) |
| 69 | Olhar o campo de busca da toolbar | lupa 14×14 dentro da borda, à esquerda, `--text-mute`; o texto digitado não passa por baixo dela (`padding-left: 30px`); `<kbd>/` em `600 10px/1` mono com `padding 2px 5px` (§24.3) |
| 70 | Abrir a paleta `⌘K` com o campo vazio | placeholder lê **"Buscar em tudo…"** (§24.3); o rótulo do gatilho já não existe (§25.1) |
| 71 | `document.querySelector('main > header')` | **null** — a topbar não existe; o primeiro filho do `<main>` é o `<div>` com `padding: 0 var(--d-cpad-x)` que monta o `PageHeader` (§25.1) |
| 72 | Olhar o cabeçalho da página | título + ações, **sem linha de números**: `header > div:first-child` não tem `<p>`; altura 57px em compacto (§25.2) |
| 73 | Medir os três filhos do bloco de ações do cabeçalho | **26px cada** — "Escuro/Claro", gatilho `⋯` e "Novo" (§25.3) |
| 74 | `document.querySelectorAll('button[aria-haspopup="menu"]')` | todos com `aria-label` não vazio: "Mais ações desta tela" no cabeçalho, "Ações de <nome do produto>" na linha (§25.4) |
| 75 | Ligar o tema escuro e ler `--accent` na raiz `.cockpit` | `oklch(0.72 0.15 295)`; `--accent-soft` `oklch(0.32 0.07 295)`; classe `dark` presente. No claro, `oklch(0.55 0.15 295)` (§25.5) |
| 76 | Carregar a tela sem tocar em nada | tema **claro** — exceção assinada; escuro disponível pelo botão do cabeçalho (§25.5) |
| 77 | Comparar o `background-color` do invólucro das abas com o do `<header>` do `PageHeader` | **iguais**; a faixa das abas não tem gradiente atravessando (§25.8) |

## 17 · Pendências conhecidas

0. **Fechar as sete divergências abertas de §15.1.** Em efeito prático, a #8 (marcadores da 2ª
   linha) é a que devolve produtividade — é a diferença entre varrer a lista e abrir item a item.
0b. **Decisão pendente do Wagner: contraste da família `fresc-*`** (P1 na pauta do DS). Duas
   saídas, ambas por ADR nas fundações: par claro/escuro para a família, ou exceção assinada para
   esta tela. **Não implementar nenhuma das duas sem a assinatura.** Até então, a tela fica conforme
   o DS, com o contraste reprovado e registrado.
1. **Re-rodar LAUDO e CHECKLIST 15D** — as auditorias medem a tela anterior às 27 ondas.
2. **Saldo por local × combinação.** Hoje o painel declara "Por local (todas as naturezas) — não
   separado por combinação". Decidir se o saldo pertence ao par (combinação × local) quando há
   vários locais com movimentação.
3. **`Drawer` ancorado** (P1 na pauta do DS) — o painel sobreposto custa metade da tela em 1280px.
4. **Fotos de produto.** `fotoPorId` está vazio; a miniatura mostra espaço reservado. Ligar URLs
   reais quando o cadastro tiver imagens.
5. **Virtualização** da tabela para catálogos grandes.
6. **Mobile** — projeto separado, decisão do cliente.

## 18 · Diff da onda de revisão do painel (25/08/2026)

Origem: revisão do Felipe sobre o drawer (cor dos botões de atalho, hierarquia dos títulos,
divisão entre blocos, cor das células da grade). Cada item é **diff** — a tela já está
implementada. Formato: está / deve ficar / como conferir.

### 18.1 Botões da barra de atalhos — trocar por `Button` do DS

**Está:** `<button>` desenhado na tela — `500 11px/1`, padding `5px 9px`, fundo `var(--bg-2)`,
borda `1px var(--border)`, raio 5px, hover trocando a borda para `var(--accent)`.

**Deve ficar:** `Button` do DS, `variant="ghost" size="sm"` — `_ds_bundle.js` L2258-2288:
altura **26px**, padding **0 10px**, fonte **500 12px/1 var(--font-sans)**, fundo
**`var(--surface)`**, texto **`var(--text-dim)`**, borda **1px `var(--border)`**, raio
**`var(--radius-md, 6px)`**, transição `background/color/border-color .15s`. Hover (do próprio
componente): fundo `var(--bg-2)`, texto `var(--text)`, borda `var(--text-mute)`. **Não** passar
`style` — nenhum valor de cor é escrito pela tela. [DS]

**Contêiner** (decisão da tela, mantida): `position:sticky; top:0; z-index:3`, `display:flex`,
`gap:6px`, `flex-wrap:wrap`, padding `10px 18px`, fundo `var(--surface)`,
`border-bottom:1px solid var(--border)`. [TELA]

**Como conferir:** abrir o painel de um kit (1080) ou de um produto com grade (1043) e inspecionar
um botão da barra: `height` 26px, `font-size` 12px, `background` = `--surface` (não `--bg-2`),
`border-radius` 6px. Zero botão da barra com estilo inline de cor.

### 18.2 Célula da grade — uma superfície, estado no número e no trilho

**Está:** três placas tintadas competindo — `color-mix(--destructive 6%)` na zerada,
`color-mix(--color-warning 14%)` na abaixo do mínimo, `color-mix(--accent 12%)` na achada pela
busca, e `var(--bg-2)` chapado na normal. Sem borda. Percentuais escolhidos na tela, sem origem
no DS. Célula sem combinação: só padding, invisível.

**Deve ficar:**

| Elemento | Valor | Procedência |
|---|---|---|
| Célula (todas) | fundo `var(--bg-2)`, borda `1px solid var(--border)`, raio 5px, padding `7px 5px`, `text-align:right` | [TELA] |
| Célula achada pela busca | mesma superfície; borda `1px solid color-mix(in oklch, var(--color-primary) 55%, transparent)` | [TELA]; token uniformizado em 27/08 (§23.2) |
| Saldo zero | trilho `box-shadow: inset 2px 0 0 var(--color-destructive)` + número `var(--color-destructive)` | [TELA], trilho pelo padrão `state:'urgent'` do `DataTable`; token uniformizado em 27/08 (§22.5) |
| Saldo ≤ mínimo | trilho `inset 2px 0 0 var(--warn)` + número `var(--warn)` | [TELA]; `--warn` = `colors_and_type.css` L296 (claro) / L340 (escuro) |
| Combinação inexistente | sem fundo, borda `1px dashed var(--border)` | [TELA] |
| Código / preço / margem | mono, 10,5px `--text-mute` / 10,5px `--text-dim` / 10,5px `--text-mute` (margem abaixo do piso: `--color-destructive`) | [TELA]; código subiu de 9,5px para o piso do sistema em 27/08 (§22.6) |

**Nenhuma cor crua:** nenhum hex nem `oklch()` literal na grade — só token e `color-mix` sobre
token. O token de âmbar é `--warn` (do shell `.cockpit`), **não** `--color-warning` (do
`@theme`): o shell é quem está renderizando.

**Como conferir:** abrir 1043 (Adesivo vinil, grade Cor × Largura). Nenhuma célula com
`background` diferente de `--bg-2`. A célula `1043-PRE-100` (0 m²) tem número vermelho e
`box-shadow` de 2px na esquerda; `1043-BRA-140` (4 m², mínimo 4) tem trilho âmbar. Toda célula
preenchida tem borda de 1px.

### 18.3 Subtítulos internos de seção — usar os valores do `DrawerSection`

**Está:** "PREÇO POR QUANTIDADE" e "POR LOCAL (…)" em `600 10px`, tracking `.14em` — um segundo
nível inventado na tela, diferente do título da seção.

**Deve ficar:** exatamente os valores do `h4` do `DrawerSection` — `_ds_bundle.js` L3916-3922:
`font: 600 10.5px/1.4 var(--font-sans)`, `text-transform: uppercase`,
`letter-spacing: .05em`, `color: var(--text-mute)`. O que separa o sub-bloco do bloco não é a
tipografia, é a régua `1px solid var(--border)` acima dele. Semântica:
`role="heading" aria-level="5"`. [DS para os valores, TELA para a régua]

**Como conferir:** comparar o computed style de "PREÇO POR QUANTIDADE" com o de "PREÇO E MARGEM":
`font-size`, `font-weight` e `letter-spacing` iguais. Zero subtítulo com `.14em` na tela.

### 18.4 Divisão entre blocos do painel — não implementar na tela

O `DrawerSection` já desenha `border-top: 1px solid var(--border-2)` (`_ds_bundle.js` L3911-3914).
No tema escuro o token não separa: `--surface` `oklch(0.30 0.008 240)` vs `--border-2`
`oklch(0.31 0.008 240)` (`colors_and_type.css` L331/L333). **Aplicado como está** e registrado
como defeito **D** em `contexto/pauta-design-system.md`.

**Proibição:** não acrescentar régua, sombra ou fundo alternado entre seções do drawer para
compensar. A correção é no token do DS, não aqui.

**Como conferir:** o DOM tem `border-top` em cada `DrawerSection`; nenhum `border`, `box-shadow`
ou `background` extra escrito pela tela entre duas seções.

### 18.5 Par rótulo → valor — o template define, e a tela divergia

**Está:** rótulo do par em `font-size:12.5px; color:var(--text-dim)` (24 ocorrências no painel).

**Deve ficar:** o padrão do template canônico — `templates/pt-01-lista/Pt01Lista.dc.html`
L101-104: contêiner `display:flex; justify-content:space-between; align-items:baseline`, rótulo
**`font-size:12px; color:var(--text-dim)`**, valor em `var(--font-mono)`. [TEMPLATE]

Eu havia declarado esse par como decisão da tela ("o DS não tem componente de par definição") —
errado: o DS não tem *componente*, mas o **template** tem o padrão, e ele decide. Corrigido nas 24
ocorrências.

**Como conferir:** medir o rótulo "Preço de venda" no painel: `font-size` 12px. Zero rótulo de par
em 12,5px dentro do drawer.

### 18.6 Menu ⋯ da linha aparecendo sobre o painel

**Está:** com o painel aberto, clicar o ⋯ de uma linha abre o menu **por cima do painel**. Não é
bug de `z-index` da tela: é a pilha do próprio DS — `DropdownMenu` `zIndex: 70`
(`_ds_bundle.js` L4040) contra `Drawer` `zIndex: 60` (L3807). Mexer nesses valores está fora de
questão; o que a tela controla é **não deixar os dois abertos**.

**Deve ficar:** abrir o menu de uma linha **fecha o painel** (`abertoId = null`) antes de o menu
aparecer. O `trigger` do `DropdownMenu` aceita render-fn `({open, onClick})` (L3991-3993): o
gatilho da tela chama `e.preventDefault()` + `e.stopPropagation()`, depois
`setState({abertoId:null})` quando `!open` e o painel está aberto, e só então repassa o
`onClick` do componente. **O `stopPropagation` é obrigatório:** sem ele o clique sobe ao
`onRowClick` do `DataTable`, que roda depois e reabre o painel na linha do próprio ⋯ — os dois
ficam abertos. Não quebra o fechar-por-clique-fora do `DropdownMenu`, que escuta `mousedown` no
document (`_ds_bundle.js` L3960-3965), não `click`. **A regra vale para todo `DropdownMenu` do chrome da lista**, não só o da linha — o ⋯ do
`PageHeader` e os gatilhos de filtro/ordenação também cruzam o painel em janela larga (medido:
painel `left 504 → right 924`, menu do cabeçalho `left 614 → right 842`, dentro do painel).
Nos gatilhos de filtro o `trigger` é texto (o botão é do próprio componente), então o fechamento
vem de `onClickCapture` no invólucro: dispara na fase de captura, antes do `onClick` do
componente, sem tocar no componente nem no `z-index`. O primeiro item do menu é "Ver detalhes", que reabre o
painel — nada de contexto se perde. [TELA]

**Proibição:** não sobrescrever `z-index` de `Drawer` nem de `DropdownMenu`, e não envolver
nenhum dos dois em contexto de empilhamento novo para "resolver" a ordem.

**Como conferir:** abrir o painel de 1042 e clicar o ⋯ da linha 1043 — o painel fecha e o menu
abre sozinho; em nenhum quadro os dois aparecem juntos. Clicar "Ver detalhes" reabre o painel.

### 18.7 Cabeçalho da tabela que acompanha a rolagem — de onde vem

Comportamento (cabeçalho fixo no topo da área de dados enquanto as linhas rolam) é **canon do DS**:
é o que o `DataTablePro` faz por dentro (`_ds_bundle.js` L3188-3190: `position:sticky; top:0;
zIndex:1` no `th`, fundo `var(--bg-2)`) e é o que o template PT-01 mostra, porque ele usa o
`Pro` com `height={420}`.

**Nesta tela o mecanismo é contorno, não o componente:** ficamos no `DataTable` (§15.1 #22 — o
`Pro` não aceita ordenação controlada nem `state:'urgent'`), que não tem cabeçalho fixo nem
rolagem interna; a tela obtém o mesmo efeito com
`.cockpit thead th { position: sticky; top: 0; z-index: 2 }` + a área de dados com
`overflow:auto` (§3.1.1). Comportamento [DS] · mecanismo [TELA].

**Como conferir:** rolar a lista — o cabeçalho (CÓDIGO, PRODUTO…) para no topo da área de dados,
com fundo opaco, sem deixar linha passar por baixo dele.

### 18.8 O que resta fora do DS e do template no painel — lista **fechada**

Não existe componente canônico para estes; são contornos da tela, declarados:

| Elemento | Por que é da tela |
|---|---|
| Miniatura 60px + legenda "sem imagem" | o DS não tem componente de imagem/thumb |
| Botão de copiar código e referência (`botaoCopia`) | o DS não tem affordance de copiar valor; é `React.createElement` — subárvore opaca ao editor |
| Contêiner fixo da barra de atalhos | o `DrawerSection` não é colapsável (pauta · P2) |
| Matriz da grade (grid eixo × eixo) | o `DataTable` é para lista, não para matriz |
| Subtítulos internos e réguas de `--border` dentro de uma seção | não há componente nem padrão de template; valores tipográficos citados do `DrawerSection` (§18.3) |

Fora desta lista, todo elemento do painel é componente do DS: `Drawer`, `DrawerSection`,
`StatusBadge`, `Alert`, `Button`, `Toast`, `Modal`.

## 19 · Onda de 26/08/2026 — decisões do Wagner e da Maiara

Cinco itens, todos **diff**: a tela está implementada em `/products/unificado`. Formato
está / deve ficar / como conferir. Nenhum deles é preferência visual — cada um tem dono e motivo.
Mexer apenas nestes cinco pontos.

### 19.1 Cabeçalho da tabela fixo — com a rolagem da PÁGINA

**Requisito assinado pelo Wagner (26/08):** rolar a lista **nunca** exibe linha de produto sobre a
faixa de títulos (Código · Produto · Tipo · Disponível · Preço de venda · Margem). Motivo declarado:
em tela pequena, sem cabeçalho fixo, não se distingue a que coluna pertence cada valor.

**Modelo de rolagem — decisão da Maiara (26/08): o do `main`, sem alteração.** `Index.charter.md`
(Goals) declara *"a rolagem vertical é da PÁGINA"*; é isso que vale. A instrução das revisões
anteriores deste handoff — limitar a área de dados e rolar por dentro dela, com `min-height:240px` e
`overscroll-behavior`, vinda da auditoria do template PT-01 (§3.1.1, §15.3 nº20) — está
**desconsiderada**. Não implementar.

**Está assim (produção):** a página rola e o cabeçalho da tabela vai com ela; as linhas passam sobre
os títulos.

**Deve ficar:**

```css
.cockpit thead th { position: sticky; top: 0; z-index: 6; }
.cockpit thead th::after { content: ''; position: absolute; left: 0; right: 0;
                           bottom: -1px; height: 1px; background: var(--border); }
.cockpit tbody td { z-index: 0; }
```

Com a página rolando e **nenhum ancestral da tabela declarando `overflow`**, o scrollport do
`sticky` é o **documento**: o `th` prende no topo da **janela** e nenhuma linha aparece sobre ele.

- **`z-index: 6`** **[TELA]** — acima do trilho da linha urgente e de qualquer célula com
  `position`. O valor 2 empatava com o trilho.
- **`::after` de 1px em `--border`** **[TELA]** — a régua acompanha o cabeçalho; borda de célula em
  `sticky` não desloca em todos os navegadores.
- **Fundo opaco: [DS]** — o `DataTable` escreve `background: var(--bg-2)` **inline** no `th`. A tela
  **não** escreve `background` nem `background-clip` ali: a shorthand inline vence o stylesheet e
  `background-clip` ficaria inerte (medido em 26/08 — a rev. 10 publicou `padding-box` como
  obrigatório e **estava errado**).

⚠️ **O ponto que quebra sozinho, e é a causa raiz das duas tentativas anteriores:** qualquer
`overflow` num ancestral da tabela — inclusive um `overflow-x: auto` posto só para a rolagem
horizontal — faz o navegador computar `auto` no outro eixo. O scrollport passa a ser aquele
contêiner e o cabeçalho volta a sair de vista com a página. Medições da rodada:

| Tentativa | Medido | Por que falhou |
|---|---|---|
| `sticky` + área de dados com `overflow:auto` e `padding-top:16px` | `th.top − scroller.top = 12…16px` | o `th` prendia **abaixo** da borda do scroller e a linha aparecia naquela faixa |
| a mesma, sem o `padding-top` | `th.top − scroller.top = 0` | correto **dentro** da área de dados, mas o modelo de rolagem não era o do `main` |
| **atual** — página rola, nenhum `overflow` na árvore da tabela | `th.top = 0` com `scrollY = 400` | é o alvo |

**Rolagem horizontal:** passa a ser a do **documento** (a tabela larga transborda a coluna do
`<main>`). O que a elimina de verdade continua sendo o seletor de colunas do menu `⋯`, com
`min-width` recalculado (aceite §16 nº 17).

**Tela estreita: a tabela corta à direita, e isso é decisão, não defeito** (Maiara, 26/08). Abaixo da
largura que as colunas visíveis pedem, Margem (e depois Preço) ficam fora da janela até a página
rolar para o lado — e rolar para o lado leva o shell inteiro, porque a barra é do documento. Saída
do usuário: esconder Custo e Margem no menu `⋯`. **Não implementar** ocultação automática por
largura, compressão de coluna sem largura mínima, nem barra horizontal própria da tabela: a barra
própria exige um contêiner com `overflow`, que quebra o cabeçalho fixo (o defeito assinado do
Wagner). As três alternativas foram apresentadas e recusadas nesta data.

**A sidebar precisa de invólucro próprio, com altura E clipe** — sem isso a rolagem de página
produz dois defeitos medidos em 26/08: **[TELA]**

```
invólucro da sidebar: flex:none · position:sticky · top:0 · left:0 · z-index:20
                      height:100vh · overflow-y:auto · overflow-x:hidden
```

- **`height:100vh` sem `overflow`**: o `<aside>` do `AppSidebar` mede a altura do próprio nav
  (1376px medidos) e **estende o documento** — sobra uma faixa morta de ~385px abaixo do rodapé, com
  a metade de baixo da sidebar aparecendo nela. Antes isso não acontecia porque o shell tinha
  `height:100vh; overflow:auto` e a caixa da sidebar era limitada por ele.
- **`overflow-y:auto` no invólucro** devolve ao nav longo a rolagem por dentro (o `overflow-y:auto`
  que o próprio `AppSidebar` declara só age se alguém limitar a altura dele).
- Conferência: `document.documentElement.scrollHeight` **igual** à altura do `.cockpit` (1002 = 1002
  medidos) e, com a página no fim, o invólucro segue no topo. Em janela com barra horizontal, o
  `100vh` ignora a altura da barra e a sidebar escorrega ~10px no fim da rolagem — cosmético, e não
  ocorre acima de 1280px, onde as colunas cabem.

**Proibições:** não trocar o `DataTable` pelo `DataTablePro` para obter o cabeçalho fixo (§15.4 #22
— o `Pro` não aceita ordenação controlada nem `state:'selected'`; a proposta **P1** na pauta do DS
ganhou prioridade com esta decisão, mas não autoriza a troca); não pôr `overflow` em nenhum
ancestral da tabela; não limitar a altura da área de dados.

**Como conferir:** rolar a página com a lista longa — a faixa de títulos para no topo da janela,
opaca, e nenhuma linha aparece sobre ela. Medida: `th.getBoundingClientRect().top === 0`.

### 19.2 "Usar em orçamento" — remover

**Está:** rodapé do painel com três ações, sendo "Usar em orçamento" a primária.

**Deve ficar:** duas ações, ambas `variant="ghost"`: **Abrir cadastro** e **Formar preço** (esta só
com permissão `custo`). **Não** promover nenhuma delas a primária, **não** substituir por outra
ação, **não** mover o botão para a linha, para o menu `⋯` da linha, para a `BulkBar` ou para a
paleta `⌘K`. A tela deixa de ter qualquer caminho para o orçamento.

**Decisão do Wagner (26/08).** O texto que descreve o efeito de inativar ("sai da consulta de venda
e do orçamento") é prosa de regra de negócio e **permanece** — não é ação.

**Como conferir:** rodapé do painel com dois botões e zero `variant="primary"`; buscar
"orçamento" no código da tela retorna só texto descritivo, nenhum `onClick`.

**Conformidade do rodapé, medida (pergunta do Felipe, 26/08).** Os dois botões **são** do DS e
estão no slot certo: `Button` `variant="ghost" size="sm"` dentro da prop `footer` do `Drawer`
(faixa `padding 12px 18px`, `gap 8`, `justify-content:flex-end`, `border-top` —
`_ds_bundle.js` L3893-3903). Nada é desenhado pela tela ali.

**Resolvido — decisão assinada pela Maiara (26/08).** O template canônico fecha o rodapé com uma
**primária** (`Pt01Lista.dc.html` L286-289: `ghost "Fechar"` + `primary "Editar OS"`); com a saída
de "Usar em orçamento" a tela tinha ficado sem nenhuma. **"Abrir cadastro" é a primária**,
`variant="primary" size="sm"`, e é o **último** botão do rodapé — a ordem do template (ghost antes,
primária por último). "Formar preço" segue `ghost`, e só com permissão `custo`.

Rodapé final, lista **fechada**: navegação ‹ › + posição · `ghost` "Formar preço" (condicional) ·
`primary` "Abrir cadastro". Nada mais.

### 19.3 Situação de saldo volta para baixo da miniatura

**Está:** o selo de situação ("Disponível", "Abaixo do mínimo", "Sem saldo", "Não estocável") no
`badge` do cabeçalho do `Drawer`, ao lado do título.

**Deve ficar:** na **tira de identidade**, abaixo da miniatura de 60px, na mesma coluna dela —
miniatura → legenda "sem imagem" (quando não há foto) → selo. O cabeçalho do `Drawer` fica só com
título (nome) e subtítulo (código); a prop `badge` **não é passada**.

- Componente: o mesmo `StatusBadge` de antes, com o mesmo `tone` por estado — nenhum valor de cor
  muda. **[DS]**
- Envoltório: `margin-top: 2px`, `white-space: nowrap`. **[TELA]**

**Decisão da Maiara e do Wagner (26/08):** a visualização fica melhor com a situação junto do
produto que ela descreve, não no chrome do painel.

**Como conferir:** abrir 1042 (Disponível) e 1339/"Broca" (Sem saldo) — em ambos o selo está sob a
miniatura, e o cabeçalho do painel não tem selo nenhum.

### 19.4 KPI "Abaixo do mínimo" não existe para o perfil vendedor

**Está:** "Abaixo do mínimo" na faixa para todos os perfis.

**Deve ficar:** o cartão só existe com a permissão de reposição — chave nova `reposicao`,
concedida a todo perfil **exceto vendedor**. Ausente do DOM, não escondido por CSS (`[V0]`).

| Chave | Cobre | Regra |
|---|---|---|
| `reposicao` | KPI "Abaixo do mínimo" | perfil ≠ vendedor |

**Nada além do KPI muda.** A faceta Disponível → "Abaixo do mínimo" e o selo da linha continuam
para todos os perfis.

**Decisão do Wagner (26/08):** repor estoque não é responsabilidade do vendedor. Ele continua vendo
o **estado da linha** ("Abaixo do mínimo" no selo da coluna Disponível) e o filtro de faceta
Disponível → Abaixo do mínimo; o que sai é o **recorte de gestão** na faixa de KPI.

**Não confundir com `custo`.** "Abaixo do mínimo" não revela valor; a chave é própria, e não deve
ser derivada de `custo`, `compras` ou `margem`.

**Como conferir:** perfil vendedor → faixa com "Sem saldo" apenas (nas abas com saldo); perfil
autorizado → os quatro cartões.

### 19.5 KPI "Abaixo do mínimo" e "Sem saldo" não aparecem na aba Serviços

> ⚠ **Parcialmente superada pela §22.1 (27/08).** Vale tudo o que esta seção diz sobre "Abaixo do
> mínimo" e "Sem saldo". **Não** vale mais a frase que tira "Sem venda 90d" da aba Serviços: o
> Wagner reabriu em 27/08 e o cartão volta. A tabela de resultado por aba correta é a da §22.1.

**Está:** os dois cartões na faixa em todas as abas, inclusive Serviços — onde `stockQty` é
`null` e os dois contam sempre zero.

**Deve ficar:** na aba **Serviços**, a faixa não traz "Abaixo do mínimo" nem "Sem saldo". Serviço
não tem saldo; um recorte que só pode dar zero é ruído. Com perfil autorizado a aba mostra
"Sem venda 90d" e "Margem baixa"; com perfil vendedor a faixa fica **vazia** na aba Serviços — e
isso é o resultado esperado, não um defeito a preencher.

**Regra geral que sai desta decisão — vale para toda faixa de KPI do ERP:**
**KPI invisível não filtra.** O recorte ativo é ignorado quando o cartão correspondente não está
na tela. Sem isso, trocar de aba ou de perfil deixa a lista filtrada por um critério que o usuário
não vê e não consegue desligar. Implementar como um único predicado de visibilidade consultado nos
dois lugares — ao montar a faixa **e** ao aplicar o recorte:

```
kpiVisivel('min')    = perm.reposicao && aba !== 'servicos'
kpiVisivel('zero')   = aba !== 'servicos'
kpiVisivel('parado') = perm.custo   && aba !== 'servicos' && aba !== 'materia'
kpiVisivel('margem') = perm.margem  && aba !== 'materia'

recorte aplicado = kpiVisivel(kpiAtivo) ? kpiAtivo : nenhum
```

**Aba Matéria-prima (decisão da Maiara, 26/08):** matéria-prima **não se vende** — "Sem venda 90d"
mediria giro de algo que não tem venda própria, e "Margem baixa" mediria margem de algo que não tem
preço de venda ao cliente. Os dois saem. Na aba Serviços, "Sem venda 90d" também sai: serviço é
recorte de gestão de estoque parado, o que ali não existe. Resultado por aba, com perfil autorizado:

| Aba | KPI na faixa |
|---|---|
| Todos · Produtos · Kits · Inativos | os quatro |
| Serviços | Margem baixa |
| Matéria-prima | Abaixo do mínimo · Sem saldo |
| (perfil vendedor) | Abaixo do mínimo sai sempre; sobra Sem saldo onde há saldo |

Lista **fechada**: são estas quatro chaves. Trocar de aba já zera o KPI (§4.1); o predicado cobre o
outro caminho — mudança de permissão com recorte ativo.

**Decisão da Maiara e do Wagner (26/08).**

**Como conferir:** ativar "Sem saldo" na aba Todos, trocar para Serviços — os dois cartões saem da
faixa e a lista volta a mostrar todos os serviços do recorte, sem filtro residual.

### 19.6 Nome e código do produto na faixa do cabeçalho do painel

**Está:** o `Drawer` recebe `title` e `subtitle`; o componente desenha os dois num bloco **abaixo**
da borda do cabeçalho, e a faixa de cima fica só com o ✕.

**Deve ficar:** nome e código **na faixa, acima da linha**, à esquerda do ✕ — como a tela em
produção. Revisão do Felipe (26/08), confirmada pela Maiara com o print de `/products/unificado`.

**Mecanismo, sem tocar no componente:** o único slot do `Drawer` que renderiza **dentro** da faixa é
`badge` (`_ds_bundle.js` L3833-3843: faixa `padding 14px 18px`, `display:flex`, `align-items:center`,
`gap:10`, `border-bottom`, com `badge` como primeiro filho e um `span` flexível depois). Então:

- passar o cabeçalho como `badge`, num `span` `flex:1; min-width:0` em coluna, `gap:3`;
- **não passar `title` nem `subtitle`** — é o que elimina o bloco abaixo da linha;
- valores tipográficos **citados do próprio `Drawer`** (`_ds_bundle.js` L3876-3889), não escolhidos
  aqui: nome `h3` `600 17px/1.3 var(--font-sans)`, `letter-spacing:-.01em`, `color:var(--text)`;
  código `12.5px` `var(--text-dim)` — nesta tela em `var(--font-mono)` com `tabular-nums`, porque
  código é número (mesma regra da coluna Código). **[DS]** para os valores, **[TELA]** para o slot.
- nome com `overflow:hidden; text-overflow:ellipsis; white-space:nowrap` — a faixa é estreita e o ✕
  não pode ser empurrado para fora.

**O `aria-label` do diálogo vem de `title`, que deixou de existir.** Sem contorno, o painel fica sem
nome acessível. A tela grava `aria-label = "nome · código"` no `[role="dialog"][aria-modal="true"]`
depois de cada render (`componentDidUpdate`). **[TELA]** — declarado, não silencioso.

**Proibições:** não recriar o `Drawer`; não usar `badge` para outra coisa nesta tela (o selo de
situação está na tira de identidade, §19.3); não pôr a **referência** no cabeçalho — ela continua na
seção Identificação, copiável. Só nome e código, foi o que o Felipe pediu.

**Proposta ao DS (pauta, P2):** o `Drawer` deveria renderizar `title`/`subtitle` **na faixa** — ou
aceitar `titleInHeader` — e derivar o `aria-label` de `title || aria-label`. Enquanto não sair,
o contorno acima é o caminho.

**Como conferir:** abrir 1043 — "Adesivo vinil branco brilho" e "1043" acima da borda, na mesma
faixa do ✕; nada de bloco de título abaixo dela; `aria-label` do diálogo preenchido.

### 19.7 Sigla de unidade — visível na lista, explicada na dica

**Pergunta do Felipe (26/08):** *"0 L significa o que? 0 br significa o que? O usuário não vai ficar
em dúvida dessa sigla?"*

**Está:** o selo da coluna Disponível mostra "Sem saldo 0 br" e nada explica `br`.

**Deve ficar:** a sigla **continua** no selo — é o que o balcão usa e a coluna tem 210px — e o nome
da unidade aparece na **dica** do selo: `br = barra`. Regra:

| Caso | Dica do selo |
|---|---|
| item estocável, 1 local | título "Unidade" + linha `sigla = nome` |
| item estocável, 2+ locais | título "Estoque por local" + as linhas de local + a linha `sigla = nome` |
| item não estocável (serviço) | sem dica de unidade |

**A fonte do nome é o cadastro de unidades (sigla + descrição), não um mapa no front.** O protótipo
carrega um mapa **ilustrativo** (m² · m · un · pç · br · L · kg · h · cj · mil) só para exercitar a
dica; **não transcrever essa lista para o codebase** — ler a descrição da unidade cadastrada e cair
para "sem dica" quando o cadastro não tiver descrição. Recomendação registrada em
`contexto/recomendacoes-outras-telas.md`.

**Não** trocar a sigla pelo nome cheio no selo (quebra a coluna e a comparação entre linhas), **não**
inventar nome para sigla que o cliente cadastrou, **não** repetir a unidade em texto ao lado do selo.

**Como conferir:** passar o mouse no selo de 1109 ("Sem saldo 0 br") — dica com "br = barra". Em
1088, que tem mais de um local, a dica traz os locais **e** a linha da unidade.

### 19.8 Tira de identidade — selo do tipo, com o código curto da lista

**Decisão da Maiara (26/08), e ela substitui a versão anterior desta seção.** O tipo fica em
**selo**, não em texto: *"era para ser aquele botãozinho cinza"*.

**Deve ficar:** `StatusBadge tone="outline"` com o **código curto** — `PROD` · `SERV` · `M-PRIMA` ·
`KIT` — exatamente o que a coluna Tipo da lista renderiza. Um só vocabulário de tipo na tela: quem
viu `PROD` na linha reencontra `PROD` no painel. Abaixo dele, a categoria em
`font-size:12px; color:var(--text-dim)`. Alinhamento `flex-start` (o selo não estica).

**Valores, todos [DS]** — `_ds_bundle.js` L6310-6314 e L6480-6497: fundo transparente, texto
`--color-foreground`, borda 1px `--color-border`, pílula 9999, `--fs-2`, peso 500. A tela não
escreve nada disso; passa `tone` e `label`.

**O que ficou registrado da tentativa anterior:** eu havia trocado o selo pelo par texto do template
(`Pt01Lista.dc.html` L95-98) por coerência formal — o `StatusBadge` é mapeado por domínio e não tem
`kind` de tipo de produto. A dona da tela decidiu pelo selo, e é o que vale. **Não** reaproveitar
`kind="tipo"` (é PJ/PF de pessoa, L6454-6457) nem os tons `tipo-pj`/`tipo-pf`: `tone="outline"`
resolve sem pegar cor semântica de outro domínio.

**Respiro da tira:** `padding: 14px 18px` — os quatro lados, **inclusive embaixo**. A tira tinha
`padding-bottom: 0` e contava com o respiro da seção seguinte; em item **não estocável** o bloco de
saldo não é montado (§19.9) e o selo de situação encostava na régua da seção "Preço e margem"
(apontado pela Maiara em 26/08). O 14px é o mesmo do `DrawerSection` (`_ds_bundle.js` L3911-3914),
citado, não escolhido. **[DS]** para o valor, **[TELA]** para a tira.

**Como conferir:** abrir 1818 — selo `PROD` ao lado da miniatura, categoria em 12px `--text-dim`
abaixo dele, e o mesmo selo `PROD` na linha 1818 da lista. Abrir 1067 (serviço, não estocável) —
14px entre o selo "Não estocável" e a régua de "Preço e margem".

### 19.9 Seção "Disponível" perde o título — a informação já está acima

**Está:** o bloco de saldo abre com o `h4` "DISPONÍVEL" e, dentro, a linha "Saldo atual · 64 un".

**Deve ficar:** `DrawerSection` **sem** `title` (a prop é opcional — `_ds_bundle.js` L3916:
`title && h('h4'…)`; sem ela o bloco não desenha cabeçalho e mantém o `border-top` e o
`padding 14px 18px`). O rótulo da linha ("Saldo atual", "Saldo vendável (soma da grade)") continua
dizendo o que é o número, e o selo de situação está a três linhas de distância, na tira.

**Vale só para este bloco.** As outras seções mantêm o título; o bloco de kit segue com
"Disponível para venda", que **não** repete nada acima. Revisão do Felipe (26/08).

**E a régua acima dele também sai** (Maiara, 26/08): o bloco de saldo é a continuação da tira de
identidade, não um bloco novo. O `DrawerSection` escreve `border-top: 1px solid var(--border-2)`
**inline** (`_ds_bundle.js` L3911-3914), então só `!important` alcança — e mirando um **invólucro
declarado**, nunca a posição:

```css
[data-sem-regua] > div { border-top: 0 !important; padding-top: 0 !important; }
```

Um `<div data-sem-regua="1">` envolve **apenas** esse `DrawerSection`. **[TELA]** — as demais
seções do painel mantêm a régua do componente, e a proibição de §18.4 (não **acrescentar** régua,
sombra ou fundo entre seções) continua valendo: aqui se retira uma, num bloco nomeado, por decisão
assinada.

**Como conferir:** abrir 1120 — o primeiro bloco de dados começa em "Saldo atual"; nenhum `h4`
"DISPONÍVEL" no DOM. Abrir 1080 (kit) — o título "Disponível para venda" continua lá.

### 19.10 Rótulo "Saldo vendável (soma da grade)" — mantido

Levantado nesta rodada e **retirado pela autora** no mesmo dia ("deixe saldo vendável como está, eu
que digitei errado"). Registrado para não voltar como dúvida: em produto com grade o rótulo continua
"Saldo vendável (soma da grade)" — é onde a tela cumpre `[S1]` (subconjunto se declara) e `[S2]`
(saldo do pai = soma da grade). Nos demais itens, "Saldo atual". Nada a fazer.

### 19.11 Faixa de KPI — 4 colunas fixas, não `auto-fit`

**Está:** `grid-template-columns: repeat(auto-fit, minmax(132px, 1fr))`. Com quatro cartões o
resultado é o esperado; com **um** — aba Serviços, depois de §19.5 — `auto-fit` colapsa as trilhas
vazias e o único cartão **estica pela faixa inteira**.

**Deve ficar:** `grid-template-columns: repeat(4, minmax(0, 1fr))`, `gap: 9px`. Quatro trilhas
sempre; um cartão ocupa um quarto e o resto fica vazio. `minmax(0, …)` em vez de `minmax(132px, …)`
para a faixa não estourar a coluna em janela estreita. **[TELA]**

**Por que 4 e não "quantos houver":** a posição do cartão passa a ser estável entre abas e perfis —
"Sem saldo" fica na mesma coluna com quatro cartões ou com dois. Grade que se reflui a cada troca de
aba obriga a reler a faixa toda.

**Como conferir:** aba Serviços com perfil autorizado — um cartão, largura de um quarto da faixa,
alinhado à esquerda. Aba Todos — os quatro, como antes.

## 20 · Onde o comportamento mora — e o patch dos documentos do repositório

Este handoff **não** é a fonte do comportamento da tela. No `main`, comportamento mora em dois
documentos ao lado do componente, em `resources/js/Pages/Produto/Unificado/`:

| Documento | Papel |
|---|---|
| `Index.charter.md` | Mission · **Goals (faz)** · **Non-Goals (não faz)** · UX Targets · UX Anti-patterns · Automation Hooks/Anti-hooks · Histórico assinado por onda. É onde o comportamento é **normativo**. |
| `Index.casos.md` | UC-PUNI-01…17, cada um com aceite e teste (lane Estoque · MySQL). É onde o comportamento é **verificável**. |

A regra de precedência do projeto (`proibicoes.md`) diz que **cada onda corrige, no seu próprio PR,
os itens do charter que ela contradiz**. As decisões de 26/08 contradizem cinco pontos do charter e
um UC — por isso o pacote traz o patch pronto, na linguagem dos documentos:

**→ `contexto/patch-charter-casos-2026-08-26.md`**

O que ele cobre:

1. **Goals · saídas do drawer** — três viram duas; "Abrir cadastro" primária.
2. **Goals · KPI-filtros** — tabela de visibilidade (reposição, Serviços, Matéria-prima), a regra
   "KPI invisível não filtra" com gate no servidor, e a faixa em 4 colunas fixas.
3. **UX Anti-patterns** — a linha do `auto-fit` ganha o segundo motivo, medido.
4. **Goals · painel** — cabeçalho com nome e código na faixa, selo de saldo abaixo da miniatura, selo
   de tipo em código curto, primeiro bloco sem título e sem régua, sigla de unidade com descrição do
   cadastro.
5. **Goals · rolagem** — **nada muda**: a rolagem é da página, como o charter já dizia; acrescenta a
   regra do cabeçalho fixo e a proibição de `overflow` em ancestral da tabela. Registra que a
   instrução em contrário deste handoff foi desconsiderada.
6. **`Index.casos.md`** — UC-PUNI-07 completado por aba; **dois UCs novos** (KPI invisível não filtra;
   sigla de unidade tem descrição).
7. **Histórico do charter** — entrada de 26/08 com autoria por decisão ([W], [M], [F]) e a retratação
   do modelo de rolagem.

## 21 · Onda de 26/08/2026 (tarde) — comentários do Felipe no painel

Cinco itens. Quatro são **diff** (a tela está implementada em `/products/unificado`); um é
**pergunta em aberto**, e um **conflita com decisão anterior** — ver 21.6.

### 21.1 Identificação sobe para o cabeçalho do produto

**Comentário:** *"Esse bloco vai subir e ficar junto com as demais informações do produto
(descrição, código, foto)."*

**Está:** `DrawerSection` "Identificação" como penúltima seção do painel, com três pares
rótulo→valor (Marca, Código, Referência) em linhas separadas.

**Deve ficar:** a seção **deixa de existir**. Os três dados entram na tira de identidade do topo
do painel (a que já tem miniatura 60px, selo de situação, selo de tipo e categoria), na coluna à
direita da foto, como uma linha de pares compactos com `flex-wrap`:

- rótulo: `font: 600 10.5px/1.4` sans, `text-transform: uppercase`, `letter-spacing: .05em`,
  `color: var(--text-mute)` — textos `Cód`, `Ref`, `Marca` (lista **fechada**, nessa ordem). **[TELA]**
- valor de Código e Referência: o mesmo botão-de-copiar já existente (mono 12,5px, `cursor: copy`),
  sem alteração. Valor de Marca: `font-size: 12px`, `color: var(--text)`. **[TELA]**
- `gap` 10px entre pares, 5px entre rótulo e valor; `margin-top` 2px em relação à categoria.
- Referência e Marca continuam condicionais (`temReferencia`, `temMarca`); Código é sempre visível.

**Não** acrescentar descrição do produto ali: nome e código já estão na faixa do cabeçalho do
`Drawer` (§19.6). **Não** manter a seção antiga como duplicata.

**Como conferir:** abrir 1080 — não existe `h4` "IDENTIFICAÇÃO" no DOM do painel; `Cód 1080` e
`Ref KIT-FAC-STD` aparecem na primeira tira, abaixo da categoria; clicar no código ainda copia.
Abrir 1101 (serviço, sem referência e sem marca) — só `Cód 1101` aparece, sem rótulo órfão.

### 21.2 "Saldo vendável" passa a "Disponível para venda"

**Comentário:** o rótulo **"Saldo vendável"** deve ler **"Disponível para venda"**.

**Está:** `rotuloSaldo` com três formas — `Saldo vendável (soma da grade)` quando há grade,
`Saldo vendável` quando há reservado, `Saldo atual` nos demais.

**Deve ficar:** duas formas — `Disponível para venda (soma da grade)` quando há grade,
`Disponível para venda` em todos os outros casos. "Saldo atual" **sai**: o número sempre foi o
vendável (§6), e dois nomes para o mesmo número era o defeito.

Mesma troca no rodapé da lista: o total `Repor até o mínimo (saldo vendável)` passa a
`Repor até o mínimo (disponível para venda)`. O vocabulário da tela fica um só, e igual ao
cabeçalho da coluna ("Disponível").

**Como conferir:** abrir 1042 (tem reservado) — lê "Disponível para venda"; abrir 1120 (sem
reservado) — lê "Disponível para venda", não "Saldo atual"; abrir 1094 (grade) — lê "Disponível
para venda (soma da grade)". Buscar "vendável" no DOM da tela: só ocorre no rodapé, na forma nova.

### 21.3 Composição — colunas nomeadas, quatro números deixam de competir

**Comentário:** *"O Luiz achou muito confuso. Então o usuário vai penar mais ainda. Tem que ser
mais claro e intuitivo."*

**Está:** cada componente do kit numa linha com **quatro números sem rótulo nenhum**: à esquerda
código + `340 m em saldo` + `comporta 12 cj`; à direita a quantidade em negrito (`1 m`), o
subtotal em R$ e o marcador `LIMITA A MONTAGEM`. Nada na tela diz qual número é consumo, qual é
saldo e qual é capacidade.

**Deve ficar:** a seção abre com **uma frase de explicação** e a lista passa a ter **cabeçalho de
coluna**, em grade de três colunas `1fr 74px 88px`, `gap` 12px:

| coluna | cabeçalho | conteúdo |
| --- | --- | --- |
| 1 | `Item` | nome (12,5px) + 2ª linha com código (mono 10,5px) e saldo do componente |
| 2 | `Por <unidade do kit>` (ex. `Por cj`) | quantidade consumida, mono 12,5px peso 600, à direita; subtotal em R$ abaixo, mono 10,5px, só com permissão de custo |
| 3 | `Dá para montar` | quanto o saldo daquele item permite montar, mono 12,5px, à direita |

- Cabeçalho da coluna: `font: 600 10.5px/1.4` sans, uppercase, `letter-spacing: .05em`,
  `color: var(--text-mute)`, com `border-bottom: 1px solid var(--border)` e `padding-bottom` 5px. **[TELA]**
- Frase de explicação, derivada da unidade do kit: *"Montar 1 cj deste kit consome as quantidades
  abaixo. A última coluna é quantos cj o saldo de cada item dá para montar."* — 11,5px,
  `line-height` 1.45, `color: var(--text-dim)`. **[TELA]**
- Coluna 3, quando o componente é **serviço** (sem saldo): lê `não limita`, 11px,
  `color: var(--text-mute)` — não `—` e não vazio.
- Coluna 3, quando o valor é **0**: `color: var(--color-destructive)`.
- O marcador do componente que define o teto passa de `LIMITA A MONTAGEM` para `É O LIMITE`
  (ou `TRAVA O KIT` quando o valor é 0), `font-size` 10,5, peso 600, `letter-spacing` .05em,
  uppercase, abaixo do número da coluna 3 — junto do número que ele qualifica, não solto à direita.
- A linha do custo derivado ("Custo do kit (soma da composição)") **não muda**.

**Como conferir:** abrir 1080 (kit) com perfil autorizado — existem três cabeçalhos de coluna
("Item", "Por cj", "Dá para montar"); a frase de explicação aparece acima deles; o componente
1109 (perfil alumínio, saldo 0) mostra `0 cj` em vermelho na coluna 3 com `TRAVA O KIT` abaixo;
o componente de serviço lê `não limita`. Nenhum número da seção fica sem cabeçalho ou rótulo.

### 21.4 "Valor em estoque" — **pergunta em aberto, nada a implementar**

**Comentário:** *"Para quê o administrador precisa saber do valor mínimo em estoque? Não entendi o
porquê da informação e se eu não entendi, o administrador muito menos."*

O comentário caiu na linha **"Valor em estoque"**, que não é o mínimo — são duas linhas vizinhas
dentro da seção Estoque, e a leitura do Felipe já é o sintoma:

- `Mínimo` — quantidade digitada no cadastro, o piso de reposição. É o que alimenta o KPI
  "Abaixo do mínimo" e a coluna Disponível.
- `Valor em estoque` — **derivado**: custo do item × saldo **físico** (vendável + reservado).
  Só aparece com permissão `custo`. Quando há reservado, o rótulo já se declara como
  `Valor em estoque (inclui reservado)`.

**Nada foi alterado.** As três saídas possíveis, para o Felipe escolher: (a) manter e renomear
para deixar a fórmula no rótulo; (b) manter só no total do rodapé, que já traz "Valor em estoque
(recorte, físico)", e tirar do painel; (c) remover das duas. Enquanto não houver decisão, a tela
segue como está.

### 21.5 Os gatilhos de faceta ("Categoria", "Tipo", "Marca", "Disponível") — **[DS]**, não desenho da tela

**Pergunta:** *"Esse filtro está de acordo com o Design System ou template? Pois em produção o
Claude Code aplicou diferente. Como você explicaria para ele corretamente?"*

**Resposta medida:** o botão é **inteiramente do DS**. A tela monta `DropdownMenu` passando só
`trigger` como **texto** (`"Categoria"` ou `"Categoria: Impressão digital"`), `items`,
`align="start"` e `width`. Quando `trigger` **não** é função, o componente desenha o botão
inteiro, inclusive o chevron. Citação, não medição minha — `components/DropdownMenu/DropdownMenu.jsx`
L34-38:

| propriedade | valor | procedência |
| --- | --- | --- |
| altura | `32` | [DS] L35 |
| padding | `0 11px` | [DS] L35 |
| gap texto→chevron | `6` | [DS] L35 |
| fundo | `var(--surface)` | [DS] L36 |
| borda | `1px solid var(--border)` | [DS] L36 |
| raio | `var(--radius-sm, 6px)` | [DS] L36 |
| tipografia | `500 13px/1 var(--font-sans)`, `color: var(--text)` | [DS] L37 |
| sombra | `var(--shadow-soft)` | [DS] L36 |
| chevron | svg 13×13, `stroke-width` 2, `opacity .6`, path `m6 9 6 6 6-6` | [DS] L38 |
| painel | `marginTop` 6, `zIndex` 70, raio 10, padding 5, `var(--shadow-pop)` | [DS] L44-47 |
| item do menu | `500 13px/1` sans, padding `7px 8px`, raio 7, ativo `var(--accent-soft)`/`var(--accent)` | [DS] L57-62 |

**Como explicar ao agente de código, em uma frase:** *não desenhe o botão de faceta — monte o
`DropdownMenu` do DS passando `trigger` como texto puro e deixe o componente desenhar o botão e o
chevron; se existir um `<button>` com estilo próprio para a faceta, a correção é **apagá-lo**, não
igualar os números aos da tabela acima.* Ou seja: divergência de faceta em produção nunca se
resolve escrevendo cor, altura ou raio — se resolve devolvendo o desenho ao componente.

O que **é** desta tela, e não do template: a **composição** da toolbar — quatro facetas + gatilho
de ordenação + "Limpar" + contagem + busca. O PT-01 põe na toolbar um campo de busca, um
`FilterChip` e a contagem, e nenhum gatilho de faceta (`templates/pt-01-lista/Pt01Lista.dc.html`
L74-81). Facetas como `DropdownMenu` são **[TELA]**, já registradas na pauta do DS
(`FilterChip` no PT-01 pressupõe filtros definidos fora da toolbar). O rótulo do gatilho refletir a
seleção ("Categoria: Impressão digital") e o item marcado com `✓` também são **[TELA]**.

### 21.5.1 Escala dos rótulos uppercase — 10,5px, uma só no painel

Os seis rótulos novos (`Cód`/`Ref`/`Marca` da tira e `Item`/`Por cj`/`Dá para montar` da
Composição), mais o marcador `É O LIMITE`/`TRAVA O KIT`, usam `600 10.5px/1.4` sans +
`letter-spacing: .05em`. É o mesmo tratamento já vigente nos subtítulos internos do painel
("Preço por quantidade", "Por local"), fechado pela §18.3. **Não** introduzir uma segunda escala
de rótulo uppercase no drawer: 10px foi escrito na primeira volta desta onda e corrigido.

**Como conferir:** medir `font-size` de todo elemento com `text-transform: uppercase` dentro do
painel — todos em 10,5px, nenhum em 10px.

### 21.6 Conflito a resolver — 21.2 contra a §19.10

A §19.10 registra este mesmo rótulo levantado em 26/08 pela manhã e **retirado pela autora**
("deixe saldo vendável como está, eu vou pensar"). O comentário do Felipe pede a troca. Apliquei a
troca do Felipe **e** deixo o conflito declarado: se a decisão da Maiara prevalecer, reverter 21.2
inteira (incluindo o rodapé) e riscar esta seção. Não há como as duas valerem ao mesmo tempo.

## 22 · Onda de 27/08/2026 — Wagner

Seis itens. Quatro são **diff** (a tela está implementada em `/products/unificado`), um é
**resposta** a uma pergunta sobre a tela, e um é **pergunta em aberto** — a auditoria do painel
(§22.6), com um valor à espera de decisão.

### 22.1 "Sem venda 90d" volta à aba Serviços — reabre a §19.5

**Pergunta:** *"Por que ele não aparece na aba de serviços, se serviço também se vende?"*

**Está:** `kpiVisivel('parado')` exige `perm.custo && aba ≠ Serviços && aba ≠ Matéria-prima`. A
§19.5 registrava o motivo de 26/08: *"serviço é recorte de gestão de estoque parado, o que ali não
existe"*.

**Deve ficar:** a condição perde o teste de Serviços — `perm.custo && aba ≠ Matéria-prima`. O
recorte não é de estoque parado, é de **giro**: mede a última venda (`ultimaVenda`), e serviço
vende. Matéria-prima continua fora, pelo motivo já assinado (§19.5): não se vende ao cliente, e o
número mediria giro de algo sem venda própria.

**Decisão do Wagner (27/08)**, superando a frase de Serviços da §19.5. Resultado por aba, com
perfil autorizado — tabela **fechada**, substitui a da §19.5:

| Aba | KPIs na faixa |
|---|---|
| Todos · Produtos · Kits · Inativos | os quatro |
| Serviços | Sem venda 90d · Margem baixa |
| Matéria-prima | Abaixo do mínimo · Sem saldo |
| (perfil vendedor) | "Abaixo do mínimo" sai sempre; "Sem venda 90d" e "Margem baixa" dependem de `custo`/`margem` |

A regra **"KPI invisível não filtra"** (§19.5) não muda: continua valendo para os cartões que
saem, com o mesmo gate no servidor.

**Contradição fechada de tabela:** o aceite nº 36 já dizia "sobram *Sem venda 90d* e *Margem
baixa*" na aba Serviços, enquanto a tabela da §19.5 dizia só "Margem baixa" e o nº 49 dizia "o KPI
que sobra" (singular). O handoff se contradizia em três lugares desde a rev. 13; a decisão de hoje
resolve pelo nº 36. Os nº 36 e 49 foram reescritos.

**Efeito de layout, sem mudança de código:** a faixa é grade de **4 colunas fixas** (§19.11), então
a aba Serviços passa a mostrar dois cartões de um quarto de largura cada, alinhados à esquerda,
com duas trilhas vazias à direita. É o comportamento assinado da §19.11 — **não** trocar por
`auto-fit`, **não** esticar, **não** preencher com cartão novo.

**Como conferir:** aba Serviços com perfil autorizado — dois cartões, "SEM VENDA 90D" e "MARGEM
BAIXA", cada um com um quarto da faixa. Aba Matéria-prima — nenhum dos dois.

### 22.2 Sub-rótulo de "Margem baixa" declara o piso, não o jargão

**Comentário:** *"Sob o piso fica confuso do usuário entender. Utilize algo mais simples, de fácil
entendimento para todos."*

**Está:** `sub="sob o piso"`, texto **digitado** no template. "Piso" é vocabulário interno de
formação de preço: quem está no balcão não tem como saber qual é o piso, nem que existe um.

**Deve ficar:** o sub-rótulo é **derivado** do parâmetro vigente e declara a **base do cálculo** —
`'lucro abaixo de ' + pct(pisoMargem) + ' do preço'` — e lê **`lucro abaixo de 37% do preço`** com o
piso padrão (0,37). A margem desta tela é `(preço − custo) ÷ preço` — percentual do **preço de
venda**, não do custo; sem a base no texto, "abaixo de 37%" admitia as duas leituras (primeira volta
desta onda dizia só `abaixo de 37%` e foi corrigida). Regra de
negócio não vive em texto digitado (princípio da tela): mover `pisoMargem` para 0,40 passa a ler
`lucro abaixo de 40% do preço` sem tocar em cópia. O rótulo do cartão continua **"Margem baixa"**. **[TELA]**

O sub do `KpiFilterCard` é `--fs-1` com `line-height: 1` e **quebra em duas linhas** na faixa de 4
colunas. É esperado — a grade iguala a altura dos quatro cartões. **Não** encurtar para caber numa
linha usando símbolo (`<`) nem tirar a base do cálculo.

Mesma limpeza no item da paleta `⌘K`: `Margem sob o piso` passa a
`Margem: lucro abaixo de 37% do preço` (mesma derivação). **Não** deixar as duas formas conviverem — "sob o piso"
sai da tela inteira.

**Como conferir:** buscar "sob o piso" no DOM e na paleta `⌘K` — zero ocorrência. O cartão lê
`lucro abaixo de 37% do preço`; com `pisoMargem = 0.40`, lê `lucro abaixo de 40% do preço`.

### 22.3 Ação de vigência é um **par**: inativo oferece Ativar

**Comentário:** *"Ao selecionar o produto inativo, tem que aparecer a opção de 'Ativar'. Agora está
aparecendo o Inativar. Não tem como inativar um produto já inativo. No botão de opções do produto
também aparece a opção de 'Inativar'. Se tiver mais problemas semelhante a esse, resolva."*

**Está:** três lugares oferecem **só** "Inativar", sem olhar `active` — menu `⋯` da linha
(último item, tom de perigo), ação em lote da `BulkBar` (tom de perigo) e o `Modal` de confirmação
(título, texto e botão). Em produto já inativo, a ação não tem efeito.

**Deve ficar:** um par, escolhido por `r.active` — lista **fechada**, três lugares:

| Lugar | Produto ativo | Produto inativo |
|---|---|---|
| Menu `⋯` da linha | `Inativar produto`, `tone: 'danger'` | `Ativar produto`, **sem** `tone` |
| `BulkBar` | `Inativar`, `tone: 'danger'` | `Ativar`, **sem** `tone` |
| `Modal` — título | `Inativar produto` / `Inativar itens selecionados` | `Ativar produto` / `Ativar itens selecionados` |
| `Modal` — botão | `Inativar`, `variant="danger"` | `Ativar`, `variant="primary"` |
| `Toast` | `<nome> inativado.`, tom `warn` | `<nome> ativado.`, tom `ok` |

- **Seleção mista** (ativos + inativos marcados juntos): a ação lê **Inativar**, com tom de perigo.
  A regra é `todos os selecionados inativos → Ativar; qualquer ativo → Inativar`. Escolha desta
  tela, para que a ação em lote nunca seja a destrutiva por engano de leitura. **[TELA]**
- Texto da confirmação de ativação: *"Ativar <nome>? Ele volta a aparecer na consulta de venda e
  pode ser incluído em orçamento."* — no lote, `Eles voltam… podem ser incluídos…`. A concordância
  segue a contagem (1 item → singular), nos dois sentidos da ação.
- O `Alert` "Produto inativo" no topo do painel **não muda** (§5): descreve a condição, não a ação.
- **Não** acrescentar "Ativar" ao rodapé do painel: as duas saídas do drawer estão fechadas em
  §19.2 ("Formar preço" e "Abrir cadastro", esta primária). Vigência se muda pela lista ou pelo
  cadastro.

**O que foi varrido junto, e não era problema:** os outros itens do menu `⋯` da linha ("Ver
detalhes", "Formação de preço", "Duplicar") e as outras ações em lote ("Exportar seleção", "Gerar
etiquetas") valem igual em produto ativo e inativo. A aba Inativos não tem ação sem efeito
remanescente.

**Como conferir:** aceites nº 52, 53 e 54.

### 22.4 Resposta — a coluna Custo não foi removida; ela está **oculta pelo menu `⋯`**, e isso não aparece na tela

**Pergunta:** *"Por que você removeu o campo de custo do grid do perfil autorizado?"*

**Medido no protótipo aberto (procedência [RUNTIME]):** a coluna existe e é montada por
`if (verCusto) colunas.push({ key: 'custo', label: 'Custo', align: 'right', mono: true, width: '108px', sortable: true })`,
com `verCusto = perm.custo` e `perm.custo = (perfil === 'autorizado')`. O que a esconde é o recorte
salvo em `localStorage`:

```
oi.produtos.recorte.v1 → {"aba":"kits", … ,"colsOcultas":["custo"], "densa":true}
```

`colsOcultas: ["custo"]` é o resultado de um clique em **`⋯ → ✓ Coluna custo`** (§4.1.1), que
persiste (§4.7) e volta em toda sessão seguinte, inclusive depois de recarregar. Reabrir o mesmo
item do menu traz a coluna de volta. Nada foi removido do desenho.

**Mas é lacuna de tela, não erro da usuária.** O filtro ativo se anuncia no rótulo do gatilho
("Disponível: Com saldo") e a busca tem "Limpar" ao lado; **coluna oculta não tem nenhum sinal na
tela** — nem contador, nem chip, nem marca no cabeçalho. O estado é invisível e permanente, e a
leitura natural é "a coluna sumiu do produto".

**Nada foi alterado** — é decisão de produto. As três saídas, para o Wagner escolher:

- **(a)** um contador no gatilho `⋯` quando `colsOcultas.length > 0` (ex. `⋯ 1`), com o mesmo
  tratamento dos contadores da `TabBar`;
- **(b)** um `FilterChip` "1 coluna oculta" na toolbar, removível — devolve todas as colunas de uma
  vez (o `FilterChip` do DS já existe e é o que o PT-01 usa na toolbar);
- **(c)** não persistir `colsOcultas`: cada sessão começa com as colunas do perfil, e esconder
  coluna vira ajuste de momento. Tira o estado invisível pela raiz.

Enquanto não houver decisão, a tela segue como está.

### 22.5 Auditoria da tabela contra o DS e o PT-01 — o que é do sistema, o que é da tela, o que divergiu

**Pergunta:** *"Em toda a estrutura da tabela (cápsulas, colunas, cores, linhas, tipografia,
hierarquia, comportamento de tela), tem algo fora do design system ou template? Ou do padrão de
tela?"*

Três passos, na ordem da Lei 16: componente que renderiza (`components/DataTable/DataTable.jsx`),
template canônico (`templates/pt-01-lista/Pt01Lista.dc.html`), guia.

**Do DS, sem nada da tela por cima** — moldura (`1px var(--border)`, `--radius-lg`,
`0 1px 2px rgba(0,0,0,.04)`, fundo `--surface`), `th` (fundo `--bg-2`, `9px 12px`, 10,5px/600
uppercase `.05em` `--text-mute`, `border-bottom 1px --border`), `td` (`10px 12px`,
`border-bottom 1px --border-2`), trilho urgente (`inset 3px 0 0 var(--color-destructive)`), linha
selecionada (`--accent-soft` em cada `td`), hover (`--bg-2`), arquivada (`opacity .55` +
`saturate(.7)`), glifos de ordenação (`↕ ↑ ↓`, `opacity .4` quando inativo), checkbox
(`accentColor var(--accent)`, 14×14), cápsulas (`StatusBadge` `fresc-*`/`outline`, `TagChip`).
Base tipográfica da tabela: `font-size: 13` do próprio componente.

**Da tela, com motivo já registrado** — cabeçalho fixo por CSS (§19.1 + pauta P1), padding por
tokens `--d-td-y`/`--d-th-y` copiado do PT-01, colapso do conteúdo de célula no modo denso,
composição da toolbar com facetas em `DropdownMenu` (§21.5), `DataTable` no lugar do
`DataTablePro` (pauta P1), chip crítico com geometria transcrita do `TagChip` (pauta P2).

**Divergiu — três valores do DS que a tela reescreveu. Corrigidos em 27/08 por decisão do Wagner
("corrija seguindo o DS"); o diff abaixo é o que a implementação precisa aplicar:**

| Elemento | Estava | **Deve ficar** (valor do DS) | Onde | Efeito do erro |
|---|---|---|---|---|
| 2ª linha da célula Produto (`unidade · categoria`) | `font-size: 11`, `color: var(--text-dim)`, `margin-top: 2` | `font-size: 11.5`, `color: var(--text-mute)`, `margin-top: 1` | `DataTable.jsx` L21 (`Cell`, ramo `{primary, sub}`) | subtítulo 0,5px menor e um passo mais escuro que em qualquer outra lista do ERP |
| Nome do produto (1ª linha) | 13px/600, `line-height 1.25`, sem `letter-spacing` | 13px/600, **`letter-spacing: -0.006em`** (o `line-height 1.25` fica — é da tela) | `DataTable.jsx` L20 | nome levemente mais largo que o das outras listas |
| Células mono **Código** e **Custo** | 13px (Código herdava a base da tabela via `font: inherit`; Custo escrevia `fontSize: 13`) | `font-size: 12.5`, `letter-spacing: -0.01em` | `DataTable.jsx` L24 (ramo `mono`) | as duas colunas declaram `mono: true` e não recebiam o estilo mono do DS |

Causa comum das três: passar **nó** onde o `Cell` do DS esperava texto ou `{primary, sub}` faz o
componente devolver o nó cru (`return value`), e o estilo que ele aplicaria não acontece. A tela
precisa de nó (miniatura na célula Produto, botão-de-copiar no Código), então a correção é **citar os
valores do componente dentro do nó** — aplicada. Preço de venda em **peso 600** (13px) **não** entra
nessa lista — a coluna não declara `mono`, e o peso é hierarquia declarada da tela (o preço é a
resposta da consulta): **[TELA]**, não mexer.

**Quarto item — contrato de token. Metade aplicada, metade **não é** alias:**

- `var(--destructive)` → **`var(--color-destructive)`** nas **11** ocorrências (marcador "N de N com
  saldo", célula da grade, composição, baixas do painel, total de reposição do rodapé). Medidos no
  shell, os dois resolvem para `oklch(0.58 0.20 18)` — renomear é **zero mudança visual**, e alinha
  a tela ao contrato `--color-*` que os componentes do DS e o `patch-cores-consulta-produtos.md`
  usam. **Aplicado em 27/08.**
- `var(--warn)` (2 ocorrências, furo da célula da grade e saldo baixo) **fica como está**. Medido:
  `--warn` = `oklch(0.58 0.12 70)` e `--color-warning` = `oklch(0.70 0.13 75)` — **valores
  diferentes** (0,12 de lightness). Não são alias: trocar seria **mudar a cor**, não uniformizar
  nome. Divergência autoriza pergunta, nunca ação — fica registrado e **não** se troca sem decisão.
  ⚠ Quem implementar não deve "corrigir" `--warn` para `--color-warning` por analogia com o item
  anterior.

**Comportamento de tela:** nada fora do padrão. Linha clicável abre o painel, `Enter`/`Espaço`
operam a linha, checkbox não propaga clique, seleção sobrevive à troca de página, ordenação
controlada pela tela reflete no cabeçalho e na paleta. O único ponto sem paralelo no PT-01 é o
recorte persistido (§4.7) — e a lacuna dele é a da §22.4.

### 22.6 Auditoria do painel — hierarquia, tipografia, cor, comportamento

**Pergunta:** *"No drawer, está seguindo a hierarquia de fontes? Tipografia, cores e etc? Tem algo
fora do design system ou template? Ou comportamento de tela?"*

**Hierarquia medida, do maior para o menor** — cada degrau com procedência:

| Nível | Valor | Procedência |
|---|---|---|
| Nome do produto (faixa do cabeçalho) | `600 17px/1.3`, `letter-spacing -.01em`, `--text` | [DS] `Drawer.jsx` L52 — transcrito, porque o slot `title` renderiza fora da faixa (§19.6 + pauta P2) |
| Código, ao lado do nome | mono 12,5px, `--text-dim` | [DS] `Drawer.jsx` L53 (`subtitle`) |
| "Pode vender agora" (número do kit) | mono 16px/600 | [TELA] — o único número ampliado do painel |
| Título de seção (`DrawerSection`) | `600 10.5px/1.4` uppercase `.05em`, `--text-mute` | [DS] `Drawer.jsx` L68 |
| Subtítulo interno ("Preço por quantidade", "Por local", cabeçalhos da Composição) | idem, 10,5px | [DS], valor citado (§18.3 + §21.5.1) |
| Rótulo de par (`Preço de venda`, `Mínimo`, …) | 12px, `--text-dim` | [TPL] `Pt01Lista.dc.html` L102 — é a linha secundária do drawer do template |
| Valor de par | mono 12,5px (peso 600 quando é o número principal da seção) | [DS] mesma medida do `subtitle` |
| Nota explicativa | 11,5px, `line-height 1.45`, `--text-dim` | [TELA] |
| Sub-nota de linha | 11px, `--text-mute` | [TELA] |

**Conclusão: a hierarquia fecha.** Nove degraus, nenhum inventado no meio, cinco deles citados do
componente. O corpo padrão do `DrawerSection` (13px `--text`) é substituído em toda linha pelo par
12px/12,5px — de propósito: par rótulo→valor não é texto corrido, e é o arranjo que o template
usa no drawer dele.

**Corrigido em 27/08 (decisão do Wagner: "corrija todos os itens"):**

| Elemento | Estava | **Deve ficar** | Piso do sistema |
|---|---|---|---|
| Legenda "sem imagem" (sob a miniatura) e código da célula da grade | **9,5px** | **10,5px** | 10,5px — o menor tamanho declarado em qualquer componente do DS (`DrawerSection` L68, `TagChip` `--fs-1`), e o piso das duas rampas de densidade |

A célula da grade ficou ~2px mais alta por linha (são 4 linhas por célula); é o custo aceito da
correção. **Não** reduzir outro texto da célula para compensar.

Correção **aplicada** em 27/08. Na célula da grade custou altura (são 4 linhas por célula) — foi a
razão pela qual ficou em 9,5px, e a decisão do Wagner foi seguir o piso do sistema.

**Cor:** nenhum literal OKLCH, nenhuma cor calculada; tudo `var(--*)` ou `color-mix` sobre token,
como o patch de cor exige. O alias `--destructive` do painel (grade, composição, baixas) passou a
`--color-destructive` em 27/08 (§22.5, quarto item); `--warn` fica, por não ser alias.

**Comportamento:** foco preso no painel e devolvido ao fechar (do componente), `Esc` fecha,
navegação `↑`/`↓` entre itens no rodapé, barra de atalhos de seção fixa no topo quando há kit ou
grade, `DropdownMenu` da linha fecha o painel antes de abrir (z-index 70 vs 60). Nada fora do
padrão. Duas pendências já registradas seguem em pé: painel não ancorado (pauta P1) e seções não
colapsáveis (pauta P2) — o painel de um kit ainda tem ~3,5 telas de rolagem.

## 23 · Auditoria de 27/08 (tarde) — topbar+header, abas, KPI e tabela

Quatro áreas pedidas pelo Wagner, medidas na mesma ordem da Lei 16: componente que renderiza →
template canônico `pt-01-lista` → guia. **Nada foi alterado nesta seção** — as divergências e as
perguntas de estilo estão listadas para decisão. O que não aparece aqui como divergência foi
medido e **confere**.

### 23.1 Header (topbar + `PageHeader`)

> ⚠ **Parcialmente superada pela §25 (27/08, noite):** tudo o que esta seção mede da **topbar** é
> histórico — a faixa saiu do produto. O que ela mede do `PageHeader` continua valendo, menos a
> linha de `stats`, que deixou de existir (§25.2).

**Do template, valor por valor** (`Pt01Lista.dc.html` L33-38 vs a tela): topbar `height 46px`,
`padding 0 var(--d-cpad-x)`, `border-bottom 1px var(--border)`, `background var(--surface)`,
`gap 12px` — **idênticos**. Caixa de busca global: `width 300px`, `max-width 32vw`, `height 30px`,
`padding 0 10px`, `margin-left auto`, `background var(--bg-2)`, `border 1px var(--border)`,
`border-radius var(--radius-md)`, `color var(--text-mute)` — **idênticos**. `<kbd>` do ⌘K:
`600 10px/1 var(--font-mono)`, `padding 2px 5px`, `background var(--surface)`, `border 1px
var(--border)`, `radius 4px` — **idênticos**. Os dois degradês radiais do `.cockpit`
(`1100px 620px at 84% -10%` e `900px 520px at 6% 110%`) são a string do template, sem uma vírgula
mudada.

**Do DS, sem nada por cima:** `Breadcrumb` (`400 12px/1.4`, `/` em `opacity .4`, último item 600
`--text`, hover do link `--bg-2` com `padding 2px 4px`, `radius 3`) e `PageHeader` (h1
`600 22px/1.3` `letter-spacing -.015em`; linha de stats `400 13px/1.45` `--text-dim` tabular com o
valor em 600; `border-bottom 1px --border`; `padding 14px 0`; ações `gap 8`). A tela passa só
`title`, `stats` e `actions`, e o invólucro `padding: 0 var(--d-cpad-x)`, como o template.

**Da tela, já declarado:** botão `Escuro/Claro` (§13 — só protótipo, o tema é do `AppShellV2`),
gatilho `⋯` (§4.1.1), `Novo` primário, `role="banner"` no topbar (correto: o `<header>` do
`PageHeader` fica dentro do `<main>` e não é landmark), e a busca global como **botão** que abre a
paleta `⌘K` em vez do `<input>` do template — a tela tem o campo de busca real na toolbar, e dois
campos de busca na mesma tela seria o defeito.

**Divergiu — dois itens, corrigidos em 27/08:**

| Item | Estava | **Deve ficar** | Origem |
|---|---|---|---|
| Texto do gatilho de busca global | `font-size: 12.5px` | **sem `font-size` próprio** — herda a base (`font: inherit` → `--d-fontsz`: 13px compacto / 13,5px confortável), como o `<input>` do template | `Pt01Lista.dc.html` L36 |
| Botão de ajuda `?` | não existia | **incluído** à direita do campo: 30×30, `1px solid var(--border)`, `var(--radius-md)`, `background var(--surface)`, `color var(--text-dim)`, `600 13px/1 var(--font-sans)`, `aria-label="Ajuda"` | `Pt01Lista.dc.html` L38 (valores transcritos) |

O `?` do template é inerte; nesta tela ele emite o aviso "Ajuda — central de ajuda do módulo
 Produtos." **[TELA]** — no produto, ligar na central de ajuda real. **Não** deixar botão morto.

**Observação de origem, sem ação na tela:** o docblock do `PageHeader` anuncia *"title 22/700"* e o
código escreve `600` (`PageHeader.jsx` L21-23). O código vence; vale uma linha de correção na doc
do componente.

### 23.2 Abas (`TabBar`)

**Tudo do componente, nada da tela.** Medido em `TabBar.jsx` L9-38: `height 36px`,
`padding 0 14px`, `gap 6`, `border-bottom 2px var(--accent)` no ativo com `margin-bottom -1px`
sobre a régua de `1px var(--border)` do `<nav>`, fundo do ativo
`color-mix(in oklch, var(--accent-soft) 50%, transparent)`, texto `600 13px/1` ativo / `500 13px/1`
inativo, cor `--text` / `--text-dim`, hover `--text` + `color-mix(--border-2 60%)`, contador
`600 10.5px/1.4 var(--font-mono)` em pílula `radius 99`, `min-width 18`, `padding 0 6px`, ativo
`--accent`/`--accent-fg` e inativo `--bg-2`/`--text-dim`, transição `.15s`. **Nenhuma cápsula,
borda, margem, cor ou tipografia das abas é decisão desta tela.**

**Da tela:** o invólucro `padding: 0 var(--d-cpad-x)` (igual ao template) e o override
`nav[aria-label="Sub-navegação"] { overflow-y: hidden }` — defeito de origem já registrado
(ADR 0403, pauta **D**), não suprimível de fora.

**Comportamento:** trocar de aba zera KPI, seleção, filtro de tipo e volta à página 1 (§4.1), e
o recorte persiste (§4.7). Sem divergência.

**Divergiu — nenhuma. Um item de contrato de token, corrigido em 27/08:** o realce da aba ativa
é `var(--accent)`, e `--accent` é exatamente o token que a pauta marca como **mutável em runtime**
(o seletor de matiz do `AppShellV2` o reescreve via `localStorage`); o contrato é `--color-primary`.
Dentro do componente **o DS ganha e nada se faz**. O que era da tela: ela lia `var(--accent)`
direto em **4 lugares**, contra a regra declarada dela mesma ("toda cor de acento desta tela vem de
`--color-primary`"). Passaram a ler o contrato:

| Lugar | Estava | **Deve ficar** |
|---|---|---|
| `a { color }` do `<helmet>` | `var(--accent)` | `var(--color-primary)` |
| `a:hover` | `color: var(--accent-2)` + sublinhado | **`color: var(--color-primary)`** + sublinhado — o guia marca hover de link com sublinhado, não com troca de matiz, e `--accent-2` é par do `--accent` |
| `::selection` | `var(--accent-soft)` | `color-mix(in oklch, var(--color-primary) 12%, transparent)` |
| Marcador "encontrado por …" (2ª linha da célula Produto) | `var(--accent)` | `var(--color-primary)` |
| Borda da célula achada na grade | `color-mix(… var(--accent) 55%)` | `color-mix(in oklch, var(--color-primary) 55%, transparent)` |

### 23.3 Cards de KPI (`KpiFilterCard`)

**Tudo do componente** (`KpiFilterCard.jsx` L18-45): placa `var(--color-card)`, borda
`1px var(--color-border)`, `border-radius 8`, sombra `0 1px 2px rgba(0,0,0,.05)`, `padding 12`,
`gap 12`, selecionado = borda `--color-primary` + `box-shadow 0 0 0 1px var(--color-primary)`,
transição `.15s`; ladrilho do ícone 36×36 `radius 8` com fundo do tom a 16–18% e glyph em
lightness 0,78-0,80; rótulo `--fs-1` 600 uppercase `letter-spacing .06em`
`--color-muted-foreground`; valor `--fs-6` 600 tabular `--color-foreground` `margin-top 4`; sub
`--fs-1` `--color-muted-foreground` `margin-top 2` `line-height 1`. Nenhum degradê, nenhuma borda
colorida, nenhuma sombra extra da tela.

**Da tela, declarado:** faixa em `grid` de **4 colunas fixas** com `gap 9px` e
`padding var(--d-cpad-y) var(--d-cpad-x) 0` — gap e padding são os do template
(`Pt01Lista.dc.html` L57-58), as 4 colunas são decisão assinada (§19.11); `minmax(0, 1fr)` no lugar
do `1fr` do template, para texto longo não esticar a trilha; ícones por fallback do espelho
(pauta **D** de `Icon`); e as regras de visibilidade por aba/perfil (§22.1).

**Divergiu — nenhum valor. Uma correção de leitura e uma pendência do DS:**

1. **"Margem baixa" sai do `amber`** (corrigido em 27/08). Dois cartões `amber` na mesma faixa
   ("Abaixo do mínimo" e "Margem baixa") faziam o tom parar de distinguir. **Deve ficar:**
   `tone="primary"`. Por que `primary` e não `emerald`: no guia, `emerald` é delta **positivo** /
   sucesso — pintar um alerta de margem de verde inverteria o sinal; `primary` é o tom
   informacional/de gestão (é o que o template usa em "Abertas"). Faixa final, lista **fechada**:
   `amber` Abaixo do mínimo · `rose` Sem saldo · `violet` Sem venda 90d · `primary` Margem baixa.
2. **Glyph pálido no tema claro** — o `TONE` foi autorado para fundo escuro (lightness 0,78-0,80).
   Já registrado na pauta como **P2**, aplicado como o DS manda. Sem ação na tela.

### 23.4 Colunas e linhas da tabela

A auditoria de tipografia, cor e cápsula está na **§22.5** (três valores corrigidos em 27/08). Esta
volta mediu geometria de coluna, estado de linha e comportamento; **três achados, nenhum alterado**:

1. **`larguraMin` contava 44px para a coluna de checkbox e somava a coluna de ações duas vezes**
   (corrigido em 27/08). O DS declara **36px** (`DataTable.jsx` L48 e L104). A conta estava
   `Σ larguras + 44 + 48` — 8px errados na seleção e um `+48` que repetia a coluna `acoes`, já
   presente em `colunas`. **Deve ficar:** `Σ larguras das colunas visíveis + 36`, sem folga. Efeito:
   o limite em que a rolagem horizontal começa fica 56px menor, agora igual à largura real da
   tabela.
2. **Prioridade de estado da linha é do componente:** `selected` vence `urgent` e `archived`
   (`DataTable.jsx` L83-85 — `isSel` é avaliado antes). Consequência real: produto **inativo
   selecionado** perde o esmaecimento (`opacity .55` + `saturate(.7)`) enquanto estiver marcado.
   É comportamento do DS, não da tela. Só vira problema se a aba Inativos passar a depender do
   esmaecimento para leitura — hoje não depende (tem a coluna e o `Alert` do painel).
3. **Moldura e rolagem horizontal: confere.** Medido com a janela em 914px e a tabela em 1102px —
   card e tabela terminam no mesmo pixel (1387 na régua do documento), `scrollWidth` do documento
   = 1387, sidebar `sticky` no lugar. A moldura **não** fica atrás da tabela, e o modelo de rolagem
   assinado (§19.1 + exceção de 26/08) está íntegro. Nada a fazer.

**O que foi medido e confere, sem item:** largura e ordem das colunas ([TELA], §3.1), alinhamento à
direita só nas três numéricas (com `font-variant-numeric: tabular-nums` vindo do componente quando
`align: 'right'`), `th` e `td` (fundo, padding via `--d-th-y`/`--d-td-y` do template, réguas
`--border` e `--border-2`), trilho urgente, fundo da linha selecionada, hover, glifos de ordenação,
checkbox, e as cápsulas da linha (`StatusBadge` `fresc-*`/`outline`, `TagChip`, chip crítico — os
três já registrados).

## 24 · Auditoria de 27/08 (noite) — filtros, topo, busca e as três faixas do drawer

Seis áreas pedidas pelo Wagner. Mesma ordem de leitura (componente → template → guia). **Nada
alterado nesta seção.** Divergência = valor do DS ou do template reescrito sem motivo declarado.

### 24.1 Filtros (toolbar)

**Do DS, inteiro:** os cinco gatilhos são `DropdownMenu` com `trigger` **texto**, e o componente
desenha o botão todo — altura 32, `padding 0 11px`, `gap 6`, `--surface`, `1px --border`,
`var(--radius-sm, 6px)`, `500 13px/1`, `var(--shadow-soft)`, caret 13×13 `opacity .6`; painel
`marginTop 6`, `zIndex 70`, `radius 10`, `padding 5`, `var(--shadow-pop)`, animação
`ds-dd-in .12s`; item `500 13px/1`, `padding 7px 8px`, `radius 7`, ativo `--accent-soft`/`--accent`,
`danger` em `--neg-soft`/`--neg`, separador 1px `--border` com `margin 5px 6px`
(`DropdownMenu.jsx` L34-66). Já detalhado em §21.5 — **nenhum pixel de cápsula, borda, sombra,
efeito ou tipografia de faceta é decisão desta tela.** "Limpar" é `Button ghost sm` (26px,
`0 10px`, `500 12px/1`) do DS.

**Da tela, declarado:** a *composição* da toolbar (cinco facetas + ordenação + Limpar + contagem +
busca), o rótulo refletindo a seleção ("Categoria: Impressão digital") e o `✓` no item marcado
(§21.5); `flex-wrap: wrap` para a faixa quebrar em janela estreita.

**Divergiu — quatro valores do template, todos corrigidos em 27/08:**

| Item | Estava | **Deve ficar** (template) | Origem |
|---|---|---|---|
| `gap` da toolbar | `6px` | **`8px`** | `Pt01Lista.dc.html` L69 |
| Régua superior | `border-top` **e** `border-bottom` | **só `border-bottom: 1px solid var(--border)`** — a faixa de KPI passa a encostar na toolbar sem régua entre as duas, como no template | L69 |
| Contagem ("N registros") | `font-size: 12px` sans, `line-height: 12px` | **`font: 11px/1 var(--font-mono)`**, `color: var(--text-dim)` | L80 |
| Largura mínima do campo de busca | `flex: 1 1 240px; min-width: 200px` | **`min-width: 240px`** (o `flex-basis` de 240 fica) | L70 |

**Comportamento:** clicar numa faceta com o painel aberto fecha o painel primeiro
(`onClickCapture`), o menu fecha em esc/clique-fora/seleção e navega por teclado — tudo do
componente. Sem divergência.

### 24.2 Topo (a pilha inteira do chrome)

> ⚠ **Superada pela §25.1 na parte da topbar.** A sequência de réguas da primeira dobra passa de
> **quatro** 1px para **três**: `PageHeader` (recuada) → `TabBar` (recuada) → toolbar (largura total).

O topbar e o `PageHeader` estão medidos na **§23.1** (dois itens: texto do gatilho de busca em
12,5px e ausência do botão `?`). Esta volta mediu a **pilha**:

**Sequência de réguas, de cima para baixo** (depois da correção de §24.1): topbar `border-bottom`
(largura total) → `PageHeader` `border-bottom` (recuada em `--d-cpad-x`, do componente) → `TabBar`
`border-bottom` (recuada) → faixa de KPI (sem régua) → toolbar `border-bottom` (largura total).
**Quatro** 1px na primeira dobra — igual ao template. Régua recuada vs largura total é do template,
não decisão desta tela: o topbar e a toolbar são full-bleed, o `PageHeader` e a `TabBar` vivem
dentro do `padding: 0 var(--d-cpad-x)`.

**Divergiu — um item novo, além dos dois da §23.1; corrigido em 27/08:** o gatilho `⋯` (topo e cada
linha) tinha fundo `transparent`, borda `1px solid transparent`, `radius 6` e **nenhum hover**.
**Deve ficar** com os valores do `Button variant="ghost"` do DS (`Button.jsx` L7-24), transcritos:
30×30, `background var(--surface)`, `border 1px solid var(--border)`,
`border-radius var(--radius-md, 6px)`, `color var(--text-dim)`,
`transition background .15s, color .15s, border-color .15s`, e hover `background var(--bg-2)` +
`color var(--text)` + `border-color var(--text-mute)`.

**Por que transcrever em vez de montar o `Button` do DS:** o `DropdownMenu` acrescenta caret quando
o `trigger` é nó, então a tela precisa da forma render-função; e o `Button` do DS **não repassa
`aria-haspopup`/`aria-expanded`** (`Button.jsx` L6 — a assinatura não tem `...rest`), que o gatilho
de menu precisa manter, nem `stopPropagation` no clique da linha. Trocar por `DS.Button` custaria
acessibilidade; citar os valores custa nada. **Lacuna do DS registrada na pauta.**

### 24.3 Busca — dois padrões na mesma tela

| Onde | O que é | Procedência |
|---|---|---|
| Gatilho global do topbar | `<button>` da tela com lupa 14×14 (`stroke-width 2`), texto e `<kbd>⌘K` | geometria, cores **e tipografia** idênticas ao `<label>` do template (L35-37) desde a correção de §23.1 |
| Campo da toolbar | `Input` do DS, `type="search"` — `13px/1.4`, `padding 7px 10px` (+20px à esquerda para a lupa), `radius var(--radius-md)`, `1px --border`, fundo `--surface`, anel de foco `0 0 0 3px var(--accent-soft)` + borda `--accent` | 100% DS (`Input.jsx` L18-33); a lupa é contorno da tela (item 1 abaixo) |
| Paleta `⌘K` | `Command` do DS — scrim `oklch(0.12 0 0 / .5)` + `blur(2px)`, painel `maxWidth 580`, `radius 14`, lupa 17×17, input 15px, rodapé de dicas | 100% DS |

**Divergiu — três itens, corrigidos em 27/08:**

1. **Lupa no campo da toolbar.** O `Input` do DS **não tem slot de ícone ou prefixo**
   (`Input.jsx` L36-44), e o template desenha o campo à mão só por isso (L70-72). Recriar o campo
   está descartado (nunca substituir componente do DS). **Deve ficar** o contorno na tela: o
   invólucro `#campo-busca` recebe `position: relative`, a lupa do DS (`Icon search`, 14×14,
   `color: var(--text-mute)`, `pointer-events: none`, `z-index: 1`) entra absoluta em
   `left: 10px; top: 50%; transform: translateY(-50%)`, e o controle abre espaço com
   `#campo-busca input { padding-left: 30px !important }` — 30 = o `padding 10px` do controle
   (`Input.jsx` L21) + 14 da lupa + 6 de folga. **O `!important` é obrigatório:** o componente
   escreve `padding` inline. **Não** desenhar um campo próprio, e **não** mexer no `Input`.
   Proposta `icon`/`suffix` registrada na pauta como **P1**.
2. **`<kbd>` do `/` no padrão do sistema.** Estava `10.5px`, peso herdado (400),
   `padding 4px 5px`. **Deve ficar** `font: 600 10px/1 var(--font-mono)`, `padding 2px 5px`,
   `border-radius 4px`, `1px solid var(--border)`, `background var(--bg-2)`,
   `color var(--text-mute)` — o mesmo `<kbd>` que o DS repete em quatro lugares
   (`DropdownMenu` L66, `Command` L64 e L80, `kbF`) e que o `⌘K` do topbar já usava.
3. **Placeholder da paleta = rótulo do gatilho.** A tela passa `placeholder="Buscar em tudo…"` ao
   `Command`, em vez de deixar o default do componente ("Buscar ou executar…"). O botão e o campo
   que ele abre passam a dizer a mesma coisa.

### 24.4 Topo do drawer (tira de identidade)

`padding 14px 18px` — o mesmo do `DrawerSection` (`Drawer.jsx` L66), então a coluna de conteúdo
alinha com todas as seções abaixo. **Sem régua acima**, porque é continuação do cabeçalho (§19.9,
override `[data-sem-regua]`). Miniatura 60px `radius 4`, `1px --border`, fundo `--bg-2`; sem foto,
borda tracejada `color-mix(--text-mute 45%)` + glyph `package` (fallback declarado, pauta **D** de
`Icon`). Selo de situação = `StatusBadge` do DS abaixo da miniatura (§19.8); selo de tipo =
`StatusBadge tone="outline"`, o mesmo da coluna Tipo. Categoria 12px `--text-dim`; rótulos
`Cód`/`Ref`/`Marca` em `600 10.5px/1.4` uppercase `.05em` `--text-mute` (§21.5.1); valores em botão
de copiar mono 12,5px (§21.1).

**Divergiu — nada novo.** O valor que estava pendente (legenda **"sem imagem"**) passou de 9,5px
para **10,5px** em 27/08 — §22.6.

### 24.5 Header do drawer (a faixa do ✕)

**Do DS, inteiro:** faixa `padding 14px 18px`, `display flex`, `align-items center`, `gap 10`,
`border-bottom 1px --border`; botão ✕ 28×28, `radius 6`, fundo transparente, cor `--text-mute`,
`font-size 16`, hover fundo `--bg-2` + cor `--text`; painel `maxWidth` da prop `width`, fundo
`--surface`, `border-left 1px --border`, sombra `-16px 0 40px -12px rgba(0,0,0,.30)`, scrim
`oklch(0.15 0 0 / 0.45)`, `zIndex 60`, foco preso e devolvido ao fechar (`Drawer.jsx` L29-49).

**Da tela, declarado:** o nome (`600 17px/1.3`, `-.01em`, `--text`) e o código (mono 12,5px
`--text-dim`) entram pelo slot `badge`, **com os valores do próprio componente transcritos**
(`Drawer.jsx` L52-53), porque `title`/`subtitle` renderizam **fora** da faixa — §19.6, e a proposta
está na pauta como **P2**. Consequências declaradas: o slot `badge` deixou de carregar selo de
status (que foi para a tira, §19.8), e o `aria-label` do diálogo é gravado por fora
(`componentDidUpdate`), porque o componente o deriva de `title`.

**Divergiu — nada.** Hierarquia: o nome é o maior texto do painel (17px) e o único acima de 13px
fora do número de "Pode vender agora" (16px mono) — dois pesos declarados, sem competição.

### 24.6 Rodapé do drawer

**Do DS, inteiro:** `margin-top auto`, `padding 12px 18px`, `display flex`, `gap 8`,
`justify-content flex-end`, `border-top 1px --border` (`Drawer.jsx` L57). Os quatro controles são
`Button` do DS no tamanho `sm`: 26px de altura, `padding 0 10px` (ou 26×26 com `icon`),
`500 12px/1` no ghost e `600 12px/1` no primário, `radius var(--radius-md)`, hover do componente.
"Abrir cadastro" é o primário — decisão assinada (§19.2).

**Da tela, declarado:** `margin-right: auto` no grupo de navegação, que divide o rodapé em dois sem
tocar no componente (o `justify-content` do DS continua valendo para as ações); contador de posição
("3 de 14") em mono 11px `--text-mute`; e o chevron de "anterior" é o `chevron-down` **rotado
180°**, porque o espelho não tem `chevron-up` (pauta **D** de `Icon`) — no produto, use
`ChevronUp`/`ChevronDown` do Lucide e **não** replique a rotação.

**Divergiu — nada.** Observação de densidade, sem ação: o rodapé tem quatro controles (dois de
navegação + dois de ação) contra dois no template. É consequência de duas decisões já assinadas
(navegação entre itens sem fechar o painel; §19.2 fechando as ações em duas), não acréscimo novo.

## 25 · Onda de 27/08/2026 (noite) — topbar removida, cabeçalho limpo e acento por tema

Oito itens. Todos **diff**: a tela está implementada em `/products/unificado`. Formato
**está / deve ficar / como conferir**. Mexer apenas nestes pontos.

**Precedência desta seção:** ela **supersede**, na parte da topbar, as §3.1 (diagrama), §3.1.1
(adaptação da busca), §23.1, §24.2 e §24.3, e revoga os aceites nº 16, 60 e 61. Onde houver conflito
entre esta seção e qualquer anterior, esta vence.

### 25.1 Topbar — remover do produto

**Está:** `<main>` abre com `<header role="banner">` de 46px — `Breadcrumb` ("Início / Produtos"),
campo de busca global com lupa + `<kbd>⌘K</kbd>`, botão `?` de 30×30, `border-bottom 1px var(--border)`,
`background var(--surface)`, `padding 0 var(--d-cpad-x)`, `gap 12px`.

**Deve ficar:** **a faixa inteira sai.** O primeiro filho do `<main>` passa a ser o `<div>` com
`padding: 0 var(--d-cpad-x)` que monta o `PageHeader`. Saem com ela, e **não devem ser recolocados em
outro lugar da tela**: trilha (`Breadcrumb`), campo de busca global e botão de ajuda. **Não**
substituir por faixa mais baixa, **não** mover a busca global para a toolbar (lá vive a busca de
escopo, §4.2), **não** criar botão de paleta no cabeçalho da página.

**Decisão:** Maiara, 27/08/2026, com o design system **já atualizado** — `templates/pt-01-lista`
(`Pt01Lista.dc.html` v1787857167413510) não traz mais a faixa, e o bloco **B-03** do
`manual-de-blocos.md` foi **aposentado**. Vale para todas as telas do cockpit, não só esta.

**Consequência declarada:** a busca global continua existindo como paleta `Command`, mas só por
teclado (`Ctrl/Cmd+K`) — **sem afordância visual em nenhuma tela**. Isso é aceito nesta rodada; se um
dia precisar de afordância, o lugar decidido é a sidebar, e é decisão de fora deste handoff.

**Como conferir:** `document.querySelector('main > header')` retorna `null`; buscar "Buscar em tudo"
no DOM da tela → zero ocorrência fora do `placeholder` da paleta; `Ctrl/Cmd+K` continua abrindo a
paleta; a primeira dobra tem **três** réguas de 1px (PageHeader recuada → TabBar recuada → toolbar
full-bleed), não quatro.

### 25.2 Cabeçalho da página — sem linha de números

**Está:** `PageHeader` com `stats={[{ value: 14, label: 'cadastrados' }]}` → a linha "14 cadastrados"
sob o título. O número é o total da base: não muda ao trocar aba, KPI, faceta ou busca, e repete o
contador da aba "Todos".

**Deve ficar:** **sem `stats`.** `PageHeader` recebe só `title` e `actions`. O prop é opcional no
componente (`_ds_bundle.js` L5192: `(stats || subtitle) &&`) e o **B-04 v2** passa a proibir número
que já viva em outro bloco. Levantamento que fundamenta a remoção: contagem do recorte na toolbar
("13 registros", B-07 item 5), contagem por recorte nas abas (B-05 item 4), alarmes nos KPI (B-06) e
valor em estoque + repor no rodapé (B-11). Não sobrava número novo. **Não** substituir por outro
número, **não** mover o total para o título.

**Como conferir:** o `<header>` do `PageHeader` não tem `<p>`; altura medida **57px** em compacto
(14+14 de padding + 28,6 do h1, contra 80px antes); nenhum número da tela aparece duas vezes.

### 25.3 Gatilho `⋯` do cabeçalho — 26px, não 30px

**Está:** `gatilhoIcone()` crava `width: 30, height: 30` para os dois usos (cabeçalho e linha). No
cabeçalho ele fica entre dois `Button size="sm"` de **26px** (`Button.jsx` H sm = 26,
`_ds_bundle.js` L2256) — 4px de desalinhamento medidos.

**Deve ficar:** o gatilho recebe a **altura do `size` dos irmãos**: 26×26 com ícone de 15px no
cabeçalho; **30×30 com ícone de 16px continua na linha da tabela**, que convive com célula e não com
`Button sm`. Nada mais muda no gatilho — ele segue sendo `<button>` transcrito porque o `Button` do
DS não repassa `aria-haspopup`/`aria-expanded` (pauta, P2).

**Como conferir:** medir os três filhos do bloco de ações → 26px cada; medir o `⋯` de uma linha → 30px.

### 25.4 Nome acessível nos gatilhos `⋯`

**Está:** `aria-label` e `title` ausentes nos dois gatilhos. Botão só de ícone sem nome: leitor de
tela anuncia "botão".

**Deve ficar:** `aria-label="Mais ações desta tela"` no do cabeçalho; `aria-label="Ações de <nome do
produto>"` no de cada linha (o nome vem da linha, não fixo). **Não** acrescentar `title` — se quiser
dica visual, o caminho é o `Tooltip` do DS.

**Nota de leitura:** o glyph desses botões são **três linhas horizontais** (`menu`), não três pontos —
o `Icon` do espelho não tem `ellipsis` (pauta, defeito de origem). No codebase real, Lucide tem
`MoreHorizontal`: usar esse, com o mesmo `aria-label`.

**Como conferir:** `document.querySelectorAll('button[aria-haspopup="menu"]')` → todos com
`aria-label` não vazio.

### 25.5 Acento escrito por tema — o que faltava no shell

**Está:** a tela escreve os tokens de densidade (`--d-*`, rampa `--fs-*`) e **não** escreve os de
acento. O DS troca apenas `--accent-soft` em `.cockpit[data-theme="dark"]`
(`colors_and_type.css` L337), então no escuro o roxo permanecia no valor do claro.

**Deve ficar:** o shell escreve os quatro tokens **por tema**, transcritos de `applyTheme` do PT-01
(`Pt01Lista.dc.html` L135-140), matiz 295:

| Token | Claro | Escuro |
|---|---|---|
| `--accent` | `oklch(0.55 0.15 295)` | `oklch(0.72 0.15 295)` |
| `--accent-2` | `oklch(0.62 0.15 295)` | `oklch(0.78 0.15 295)` |
| `--accent-soft` | `oklch(0.95 0.04 295)` | `oklch(0.32 0.07 295)` |
| `--color-primary` | `oklch(0.55 0.15 295)` | `oklch(0.72 0.15 295)` |

Mais `classList.toggle('dark', escuro)` na raiz, como o template. **Tema padrão do produto
unificado: claro** — exceção assinada pela Maiara em 27/08, que supersede o `[TPL]` L132 (que faz
dark o padrão) e o B-01 item 3 na versão v1. O escuro fica disponível pelo botão do cabeçalho.

**Contorno do protótipo, declarado:** o botão "Escuro/Claro" existe só aqui. No codebase real o tema
é do `AppShellV2` / cockpit — **não implementar botão de tema nesta tela**.

**Defeito de origem que isso revela — registrar, não corrigir:** no escuro, `Button variant="primary"`
fica com texto `--accent-fg` branco (`rgb(255,255,255)`) sobre `oklch(0.72 0.15 295)`
(`rgb(172,143,248)`) → **2,61:1** medido, contra o mínimo AA de 4,5:1 para rótulo de 12px/600. No
claro o mesmo par dá **5,17:1**. Atinge todo elemento com preenchimento `--accent` + texto
`--accent-fg`, incluindo a pílula de contador da aba ativa (`TabBar`, `_ds_bundle.js` L6665-6672).
**Aplicar o valor do DS/template como está** e abrir a decisão no DS (já em
`contexto/pauta-design-system.md`): ou `--accent-fg` escuro passa a texto escuro, ou o acento do
escuro recua para L ≤ 0,62. **Não** resolver dentro da tela.

**Como conferir:** no escuro, `getComputedStyle(document.querySelector('.cockpit')).getPropertyValue('--accent')`
= `oklch(0.72 0.15 295)`; no claro, `0.55`. Carregar a tela sem tocar em nada → tema claro.

### 25.6 Busca global e hover do `?` — histórico do mesmo dia

Aplicados pela manhã e **sem efeito prático**, porque a faixa saiu à noite (§25.1). Ficam
registrados para não serem reimplementados: (a) a busca global voltou a ser `<label>` + `<input>`
real do template, abrindo a paleta ao **focar**, com guarda de reentrada de 300ms — o `Command`
devolve o foco ao elemento anterior ao fechar (`_ds_bundle.js` L2602) e sem a guarda a paleta reabria
em laço; (b) o `?` e o campo ganharam o hover do gatilho de menu. **Nada a implementar.**

Da mesma manhã ficou uma proposta **P1** na pauta: `Command` sem `initialQuery` — é o que impediria
o campo entregar o texto já digitado à paleta. Relevante se um dia a busca global voltar a ter campo.

### 25.7 `PageHeader` e `TabBar` contra o DS e o template — medido em 27/08 (noite)

Pergunta da Maiara. Medição a 1502px de largura, densidade compacta, tema claro.

**`PageHeader` — confere.** Montado como componente do DS dentro de
`<div style="padding:0 var(--d-cpad-x)">`, igual ao `[TPL]` L34-36. Do componente, sem nada por cima:
`padding 14px 0`, `border-bottom 1px var(--border)`, `background var(--bg)`, h1
`600 22px/1.3` com `letter-spacing -.015em` (medido `-0.33px`) em `var(--text)`, com `ellipsis`;
bloco de ações `flex`, `gap 8`, `flex 0 0 auto`. Altura **57px** (o template mede 64 porque tem
`stats`; esta tela não tem mais — §25.2, permitido pelo componente).

Duas diferenças contra o template, ambas declaradas: o template põe **duas** ações (ghost "Filtros" +
primary "Nova OS", `Pt01Lista.dc.html` L256-259) e esta tela põe **três** — tema (contorno de
protótipo, §25.5), `⋯` (B-04 item 4) e "Novo" primário. Uma só primária, como o bloco exige.

**`TabBar` — confere.** Componente do DS no invólucro `padding: 0 var(--d-cpad-x)` (`[TPL]` L38-40).
Medido na aba ativa: botão **36px** de altura, `padding 0 14px`, `600 13px/1`, sublinhado de 2px em
`oklch(0.55 0.15 295)` (= `--accent` = `--color-primary`), fundo `--accent-soft` a 50%,
`margin-bottom -1px`; contador em pílula mono `600 10.5px` com `--accent`/`--accent-fg`. Inativa
`500 --text-dim` com hover do componente. Nenhum valor escrito pela tela.

Dois pontos de leitura, nenhum defeito: (a) a largura medida do sublinhado sai `1,39px` em vez de
`2px` — é arredondamento de `devicePixelRatio` da janela medida (1,44), não valor da tela; (b) a
1502px as seis abas cabem (`scrollWidth == clientWidth`), então a barra horizontal e o corte de 1px
do sublinhado descritos na nota do B-05 **não** ocorrem — eles aparecem só em janela estreita
(medidos a 835px), e o contorno `overflow-y: hidden` do ADR 0403 continua sendo o único autorizado.

**Divergiu — nada** em nenhum dos dois.

### 25.8 Faixa das abas — fundo igual ao do cabeçalho

**Está:** o invólucro da `TabBar` não escreve fundo. O `PageHeader` escreve `background: var(--bg)`
**opaco** (`_ds_bundle.js` L5171) e a `TabBar` não escreve nenhum (`_ds_bundle.js` L6610-6617), então
naquela faixa aparece o `radial-gradient` de acento do `.cockpit` (`[TPL]` L24): a linha de abas fica
clara à esquerda e tingida de roxo à direita, enquanto o cabeçalho logo acima é chapado.

**Deve ficar:** o `<div>` que monta a `TabBar` recebe `background: var(--bg)`, além do
`padding: 0 var(--d-cpad-x)` que já tinha. O valor é **citação** do `PageHeader`, não cor nova, e o
componente da `TabBar` não é tocado. **Não** usar `--surface`, **não** tirar o gradiente do
`.cockpit` (ele é do template), **não** pintar o `<main>` inteiro — a faixa de KPI e a área de dados
seguem deixando o gradiente aparecer, como no template.

**Como conferir:** `getComputedStyle` do invólucro das abas e do `<header>` do `PageHeader` devolvem o
**mesmo** `background-color` (`oklch(0.985 0.003 90)` no claro); a faixa das abas não tem variação de
cor da esquerda para a direita.
