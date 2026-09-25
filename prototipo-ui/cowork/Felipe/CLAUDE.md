# Contexto do projeto — Protótipo oficial, Produto unificado (Office Impresso / oimpresso)

## Onde este protótipo é editado e de onde vem (Felipe, 25/09/2026)

**A fonte é o protótipo do Wagner** (`prototipo-ui/cowork/Wagner/`, que desce do projeto Cowork
dele pelos handoffs). **Esta pasta é a cópia de trabalho do Felipe:** veio do zip do protótipo do
Wagner e é aqui, no repositório, que o Felipe faz as alterações de design — elas entram por PR. As
ondas A e B da Fabricação foram feitas desta forma.

**Sincronia nos dois sentidos, para os protótipos não divergirem:**

- **Wagner → Felipe:** quando um handoff do Wagner muda arquivos que esta pasta também tem, as
  mudanças dele são trazidas para cá **preservando as do Felipe**. As duas cópias não são
  idênticas (esta foi reescrita com os componentes do DS nas ondas A/B), então receber é trazer o
  comportamento e o conteúdo que ele mudou, escritos com os componentes desta pasta — não copiar o
  arquivo dele por cima. Onde os dois mexeram no mesmo ponto, o Felipe decide.
- **Felipe → Wagner:** o que muda aqui vai para o Wagner por um recibo `_saida-*.md` na caixa de
  entrada dele (`prototipo-ui/cowork/Wagner/cowork-inbox/<modulo>/`), listando o que existe só
  nesta pasta. Primeiro retorno: `cowork-inbox/manufacturing/playbook/_saida-felipe-retorno.md`.

**O projeto do Felipe no Claude Design** (`2e7d3640…`, "PROTÓTIPO OFICIAL - PRODUTO UNIFICADO V2")
tem uma importação mais antiga e **não é mais onde se trabalha** — por isso está atrás desta
pasta. Não gravar nele e não reimportar o zip dele por cima desta pasta: isso apagaria o que foi
feito aqui.

- **Isto vale só para `prototipo-ui/cowork/Felipe/`.** O design system (projeto do Wagner, fonte
  viva em `resources/js/Components/{ui,shared}/`) continua regido pela seção "Como conferir
  protótipo contra o DS", mais abaixo.
- **Fabricação:** o Wagner decidiu em 25/09/2026 (D-MFG-FONTE) que a fonte dela é a pasta dele, e
  o playbook dele prevê aposentar os `manufacturing-*` desta pasta (thread 03). A cópia de trabalho
  do Felipe fica ou não é decisão a combinar entre os dois (D-RET-02 no recibo de retorno).
- Correção de defeito do protótipo acontece aqui, vai no PR e entra no próximo recibo de retorno.
  Exemplo: em 25/09/2026 a moldura `.mfg-grid` colidia com a regra antiga de `mockup-pages.css` e
  deixava uma coluna vazia à direita da tabela no shell — corrigido em `manufacturing-page.css`.

## Pendências conhecidas em outras telas (anotado em 25/09/2026, não corrigido de propósito)

**Comentário de CSS que fecha antes da hora.** Um asterisco colado numa barra dentro do texto de
um comentário (ex.: `os-*` seguido de `/usr-*`) fecha o comentário no meio. O navegador lê o resto
como código inválido e **descarta a primeira regra depois do cabeçalho, sem erro no console**. Na
Fabricação isso derrubou `.mfg-root{display:flex…}` e cortava os botões das telas de edição em
janela baixa — corrigido em `manufacturing-page.css` em 25/09/2026. As telas abaixo têm o mesmo
defeito e ficaram para o dono de cada uma corrigir quando mexer nela. Cada arquivo tem, no topo,
um comentário `PENDENTE` com o trecho exato, a regra perdida e como corrigir.

| Arquivo | Regra que o navegador perde (medido carregando o arquivo) |
|---|---|
| `cms-page.css` | `.cms-page .os-page-h-l p .mono` |
| `hrm-page.css` | `.hrm-page .os-page-h-r` |
| `officeimpresso-page.css` | `.oi-card` |
| `modulo-padrao.css` | `.mp-page` |
| `financeiro.css` | `.fin-conf-pill-inline` |
| `vendas.css` | `.vendas-aplus .vd-sla` |
| `importado_telas/vendas/vendas.css` | `.vendas-aplus .vd-sla` (cópia do anterior) |
| `importado_ds_git/css/inertia.css` | o bloco `@layer base` logo após o cabeçalho |

O último é cópia de `resources/css/inertia.css` do **produto**, que tem o mesmo defeito. Lá o
build Vite/Tailwind tolera: medido em 25/09/2026, o CSS servido em produção contém as regras dos
`@import` seguintes. Não é urgente, mas vale corrigir no produto também quando alguém mexer nele.

**Como achar este defeito em qualquer CSS:** percorrer o arquivo contando abertura e fecho de
comentário; todo fecho encontrado **fora** de comentário é o sintoma. Conferir no navegador com
`new CSSStyleSheet().replaceSync(texto)` e comparar a primeira regra carregada com a do fonte.

## Documentos de referência (ler antes de mexer na tela)

- `manual-escrita-para-agente.md` — **as 10 leis de escrita de handoff para agente de código.**
  Ler antes de gerar ou revisar qualquer pacote de handoff. Aplicar sem exceção; o bloco §0
  ("Contrato de leitura") vai no topo de todo handoff.
- `manual-de-blocos.md` — **o contrato por bloco de tela (B-01 a B-22), aplicável a todas as telas.**
  Quem implementa lê a ficha da tela (§2), vê a lista de blocos e lê só esses blocos. Item novo em
  um bloco atualiza **só a ficha daquele bloco** e sobe a versão dele. Blocos não cobertos estão
  declarados no §1.
- `pre-export.md` + `conferir-export.mjs` + `conferir-export.cowork.js` + `_export-baseline.json`
  — **o porteiro do pacote, e ele é obrigatório.** Onze testes de máquina que reprovam o zip
  antes de ele sair: espelho único em `_ds/`, namespace lido do cabeçalho `@ds-bundle` do
  bundle, ausência de alias, código lendo só o nome publicado, espelho igual à linha de base,
  pesos de fonte distintos, caminhos mortos, referências locais, **página que monta tela do DS
  carregando o `_ds_bundle.js`** (sem ele a tela abre em branco e o console não acusa),
  duplicatas e CRLF medido por byte. A nota de tamanhos (`_export-baseline.json`) mora na **raiz**, nunca dentro de `_ds/`
  — `_ds/` é cópia do DS e nota nossa lá dentro se perde na próxima regeneração.

  **Antes de QUALQUER export daqui — inclusive em chat novo, inclusive depois de uma mudança
  pequena — rodar as dez etapas** do `conferir-export.cowork.js` (um `run_script` por etapa;
  a chamada exata está no `pre-export.md`, seção "Como usar"). Dez chamadas, sempre todas;
  parar no meio é não conferir. Reprovou: consertar e rodar de novo, ou dizer à usuária qual
  teste reprovou e por que vai assim mesmo — nunca exportar em silêncio. No Code, a mesma
  conferência é `node conferir-export.mjs .` na importação. `--baseline` **só** logo após
  regenerar o espelho da fonte viva. Teste novo entra com a seção de mesmo número no
  `pre-export.md`, nunca sozinho.
- `pre-export-ds.md` + `conferir-ds.mjs` — **o porteiro do export do MEU DS** (depois de
  puxar as atualizações do DS do Wagner). Doze testes sobre a fonte, não sobre o consumidor:
  manifest e bundle únicos na raiz, cabeçalho `@ds-bundle` coerente com o manifest, bundle
  publicando exatamente o catálogo, sem alias, `sourcePath` e `.d.ts` de todo componente,
  CSS global não-stub, fontes com pesos distintos, templates abrindo, e o DS sem caminho do
  espelho do protótipo. **Os dois arquivos moram na raiz do projeto do DS** — foram escritos
  aqui porque não dá para gravar em outro projeto; copiar para lá. No DS, **diferença de
  tamanho não é defeito, é o pull**: o teste 10 lista o que mudou e `--baseline` grava o
  recibo depois de conferido. Não misturar com o porteiro do protótipo: quatro testes de lá
  descrevem o consumidor e não valem aqui.
- `pauta-design-system.md` — propostas de prop, defeitos de origem do DS e contornos em pé.
  Classificação P1 (propor já) / P2 (esperar segundo caso) / D (defeito).
- `recomendacoes-outras-telas.md` — o que pertence a Orçamento/PDV, Cadastro, RH e Compras,
  e o modelo de dados acordado.

Ao encontrar um novo limite do design system, registrar em `pauta-design-system.md` com o mesmo
formato: estado atual, contorno na tela, proposta, prioridade.

## Princípios já decididos nesta tela (não reabrir sem motivo)

- **A consulta é leitura.** Formar preço é cadastro; calcular pedido é orçamento/PDV. O drawer
  entrega o usuário na tela responsável ("Abrir cadastro", "Formar preço", "Usar em orçamento")
  em vez de fazer o trabalho dela.
- **Quando o número exibido é um subconjunto, a linha declara isso.** Vale para saldo vendável
  vs. físico, soma da grade, montáveis de kit. Nunca omitir em silêncio.
- **Dois números do mesmo fato precisam fechar.** Custo do kit = soma da composição; saldo do pai
  = soma da grade. Derivar em código, nunca digitar nos dois lugares.
- **Regra de negócio não vive em texto digitado.** Percentual de alçada, prazo e pedido mínimo
  são campos calculados; a observação descreve a condição, não o número.
- **Nunca substituir componente do DS.** Contornar na tela, ou registrar proposta na pauta.
  Recriar componente localmente está descartado.
- **Garantir o dado, não o chrome.** O template dá altura à região de dados e deixa a moldura se
  ajustar. Limitar o shell e deixar a área de dados com `min-height:0` inverte isso: em janela curta
  a lista mostra 1 de 10 linhas e, com `overflow:hidden` no shell, não há escape. Piso na região de
  dados + `overflow:auto` no shell como válvula.
- **Copiar template não é copiar trechos.** O `min-height:100vh` do shell do PT-01 só funciona
  porque ele passa `height` ao `DataTablePro`; com `DataTable` (sem essa prop) o mesmo CSS faz o
  documento rolar e o cabeçalho fixo sair da tela, sem erro no console. Ao levar estrutura de
  template, conferir de que **outra** parte dela o valor depende.
- **`hint-size` não é medida; token escrito não é token vigente.** Publiquei "sidebar 222px" como
  diff — o 222 era o `hint-size` do PT-01 (placeholder de streaming, host `display:contents`),
  enquanto o `AppSidebar` crava `width: 260`. E o template escreve `--d-sidebar`/`--d-navpy` que
  ninguém consome. Ler do que **renderiza**, e achar **quem consome** o token antes de citá-lo.
- **Ler o docblock do componente, não só o código.** O `TagChip` declara na terceira linha
  *"Unknown tags fall back to neutral"* — eu li a tabela `TAG_HUE`, parei ali, afirmei que o DS não
  tinha chip neutro e abri proposta falsa. Duas propostas falsas na mesma sessão, as duas por parar
  de ler cedo. Comentário de componente é fonte normativa.
- **O DS decide por componente E por template.** Três passos antes de chamar algo de decisão da
  tela: (1) o componente que renderiza o elemento; (2) o **template canônico** do mesmo tipo de tela
  (`templates/pt-01-lista` para índice) — markup, `<style>` do `<helmet>` e a lógica que escreve
  tokens; (3) o guia. Só o que não está nos três é decisão da tela. **O template decide mecanismo,
  não só valor:** densidade é contrato de tokens no shell (`--d-td-y`, `--d-cpad-x`, rampa
  `--fs-*`), não prop de componente — propus essa prop ao DS como P1 e tive de retratar. Marquei a
  moldura da tabela como decisão da tela sem abrir o PT-01, que já a definia; a usuária viu na
  galeria antes de mim. **"O componente não tem" nunca é conclusão.**
- **O DS sempre ganha, e divergência autoriza pergunta — nunca ação.** Ganha do handoff, do
  protótipo, das auditorias, da régua de acessibilidade, do bom senso. Ao encontrar contradição:
  aplicar o valor do DS como está, relatar com o número medido, não alterar nada até a decisão vir.
  Exceção só assinada e registrada na pauta. **Transcrever literal do DS é citação, não cor cravada**
  — a proibição é de inventar, calcular, converter ou substituir, e toda proibição que eu escrever
  precisa dizer o que ela **não** proíbe (o `fresc-cold` foi substituído por token porque o §6 do
  patch se contradizia com o §1).

## Modelo de dados acordado

- **Grade (filho):** saldo, mínimo, local, código próprio. Venda pelo pai; o código do filho
  serve para encontrar (a busca resolve para o pai indicando a combinação).
- **Preço:** base + faixas por quantidade no pai; acréscimo por combinação no filho (% ou valor).
- **Kit:** custo e saldo derivados da composição. Montáveis = mín(saldo do componente ÷ qtd).
- **Local tem natureza:** `venda` soma no disponível; `bloqueado` existe e não vende. Custódia de
  cliente (garantia) não é ativo da empresa; baixa é lançamento com janela, não local ou saldo.
  Kits produzidos é transformação, não local — contá-lo duplicaria o material.
- **Permissões:** `preco`, `custo`, `composicao`, `compras`, `margem`. `margem` exige `custo`
  (preço + margem revelam o custo). Alçada de desconto incide sobre o preço de tabela, nunca
  sobre o custo — assim pode ser exibida a quem não vê custo.

## Escrever para quem implementa (não reabrir — custou dois retrabalhos)

Quem implementa é um agente de código, e **agente em dúvida preenche, não pergunta**. Toda lacuna
do documento vira código plausível e errado; omissão é instrução involuntária. As leis completas
estão em `manual-escrita-para-agente.md`; o mínimo que vale para toda entrega:

- **Valor em número, nunca nome de prop.** `tone="amber"` não existe no codebase alvo — escrever
  fundo 6% / borda 22% / glyph 600. Em conflito entre nome e número, o número vence.
- **Valor de componente do DS não se escreve — se cita**, com arquivo e linha. Escrevi 6%/22%/600 e
  `rounded-xl` inferindo da prosa do guia; medido, o `StatusBadge` usa 16/30 e pílula 9999, e o
  `KpiFilterCard` usa 18% sem borda. Número inventado é pior que nome de prop: parece medição. E não
  contar agregado ("9 usos no bundle") — medir o componente que renderiza aquele elemento.
- **Valor observado em runtime nunca é regra.** Marcar procedência: [DS] citado, [TELA] decidido
  aqui, [RUNTIME] observado. `--accent` é reescrito pelo seletor de matiz do shell via
  `localStorage`; o contrato é `--color-primary`. Li hue 220 de um navegador como "cor da empresa" e
  derrubei três fontes normativas.
- **Tensão medida vira decisão declarada, não correção silenciosa.** Valor do DS que não serve ao
  contexto se aplica como está e vai para a pauta — nunca "corrigido" dentro da tela.
- **Toda lista é declarada fechada ou ilustrativa.** Lista sem marca vira menu com itens do legado.
- **Todo elemento do diagrama tem seção que o governa**, ou o agente inventa aquele pedaço.
- **Proibição explícita:** "não acrescentar", "não substituir", "não completar". Regra ausente não
  é proibição.
- **Todo número exibido marcado derivado (com fórmula) ou digitado (com campo de origem).**
- **Contorno do protótipo sempre declarado como contorno** — o espelho do DS tem 22 glyphs, o
  produto usa Lucide inteiro; ícone de fallback que não se declara vira divergência.
- **Todo requisito com teste de aceite de uma linha**, para o agente se autoconferir.
- **Tela já implementada recebe diff** (está / deve ficar / como conferir), nunca descrição do alvo.

## Entrega completa — não negociável

Custou três revisões seguidas do mesmo pacote. Antes de entregar qualquer coisa:

- **Varrer o pedido inteiro.** Se a mensagem tem quatro assuntos ou o screenshot tem cinco marcas
  vermelhas, a entrega responde a todos. Item não tratado é dito em voz alta, nunca silenciado.
- **Reler o que eu mesmo acabei de escrever, contra a regra que ele carrega.** A §15 do handoff saiu
  violando a Lei 10 do manual que estava dentro do próprio pacote, e a referência de seção apontava
  para o número errado. Revisar antes de entregar, não depois de ela apontar.
- **Screenshot marcado é lista de itens.** Cada marca da usuária é um item com resposta própria —
  ícone, menu, aba, cor. Tratar dois e calar sobre o terceiro é entregar pela metade.
- **Fechar o laço no documento.** Descobri algo? Vai para o handoff (ou pauta/recomendações) na mesma
  entrega, com valor em número e teste de aceite. Achado que fica só no chat se perde.
- **Nada de "pendente" implícito.** Se algo ficou fora por decisão dela, dizer qual é e por quê.

## Pessoas do projeto (saber quem é quem antes de escrever para alguém)

- **Maiara** — dona da tela e do produto. Decide o que a tela faz, o que entra em cada onda e a
  prioridade. É com ela que as ondas são combinadas.
- **Wagner** — dono do design system. Decide o que entra, muda ou sai do catálogo de peças, e assina
  exceção quando uma tela precisa fugir da regra. A direção espelho→git é opt-in dele.
- **Luiz** — trabalha na **tela de cadastro de venda**, onde criou modificações e melhorias.
  **É mais exigente que a Maiara em criação de telas** — o que sobe para ele precisa estar mais
  apertado, não menos: valor em número, procedência marcada, teste de aceite, e nada de item não
  tratado em silêncio. Não tratar a tela de cadastro de venda como terra de ninguém: as decisões
  dela passam por ele.

Não supor papel de ninguém. Se eu não souber quem é uma pessoa citada, perguntar — não inventar e
não escrever "não sei o papel" num documento que ela vai ler.

## Como conferir protótipo contra o DS (o default, sem precisar de pedido especial)

"Conferir se está de acordo com o DS" **sempre** significa comparar com a **fonte viva**, nesta ordem,
e nunca com a cópia local:

1. `oimpresso.com/resources/js/Components/{ui,shared}/` — o repo, SSOT. Ganha de tudo.
2. `/projects/49a36f76-2672-43f6-b955-c6cbb52f7f86/` — projeto do DS: começar pelo `_ds_manifest.json`
   (mapa nome→`sourcePath`, 41 entradas) e ler `components/<Nome>/<Nome>.jsx` + `.d.ts`. O
   `_adherence.oxlintrc.json` (795 linhas) é o contrato de aderência legível por máquina.
3. `_ds/` **nunca é fonte** — é cópia para o protótipo rodar, e envelhece. Antes de qualquer
   auditoria, comparar o `_ds/` com a fonte viva e regenerar se divergir. Espelho **único**:
   `_ds/wagner-office-impresso-design-system-49a36f76-2672-43f6-b955-c6cbb52f7f86/`, que é o que
   `oimpresso.com.html` carrega. A pasta `019dd02f` e o shim de alias que vivia no fim do bundle
   **não existem mais** (21/09/2026): o bundle publica só
   `window.OfficeImpressoPontoWR2DesignSystem_019dd0` e as páginas leem esse nome. Ao regenerar,
   copiar do DS vivo **como está** — não reaplicar alias nenhum.

**Proibições que esta semana custou caro:**

- `grep` em caminho cross-project (`/projects/…`) **devolve vazio mesmo com o termo presente** —
  nunca usar como prova de ausência. Cross-project é `read_file`.
- `local_grep` que estoura o tempo **não é ausência** — o próprio retorno diz "results are incomplete".
- **Estado visual em documento oculto não é estado da tela.** Na prévia,
  `document.visibilityState` pode ser `"hidden"`, e aí o relógio de animação não avança:
  `getAnimations()` fica em `currentTime: 0` · `playState: "running"` para sempre e toda
  propriedade **transicionada** congela no valor inicial. Sintoma típico: "o indicador não
  acompanha o clique" quando `aria-current` e o `style` inline já mudaram. Antes de chamar isso
  de defeito, ler o que **não** transiciona (atributo, inline, `aria-*`) e medir o relógio em
  duas leituras. Procedência `[RUNTIME]`.
- **Quebra de linha não se mede lendo texto.** O leitor de arquivo entrega o conteúdo já
  normalizado, então "li e está em LF" é cegueira da ferramenta, não medição. LF/CRLF só por
  byte (`0x0D 0x0A`) — é o teste 10 do `conferir-export.mjs`.
- **Contorno que o gerador não gera é dívida com prazo.** O alias do bundle sobreviveu meses
  porque cada regeneração o apagava em silêncio e alguém o reaplicava. A saída nunca é lembrar
  melhor: é remover o contorno e alinhar as duas pontas (as páginas passaram a ler o nome que o
  bundle publica). Quando não der para remover, o contorno vira teste de máquina no porteiro.
- Ler o bundle compilado e concluir sobre o componente: começar pelo manifest e pelo `.jsx`.
- Reaproveitar citação de arquivo:linha de mensagem anterior. Remedir na hora.
- Corrigir número de linha por chute. Auto-sync de endereço é conserto; chute é invenção.

Toda afirmação sobre o DS sai com **arquivo:linha medido na hora** e procedência marcada: `[DS]`
citado da fonte viva · `[TELA]` decidido aqui · `[RUNTIME]` observado. Sem a fonte viva aberta, a
frase é **"não medi"**, nunca "não existe" — e "falta prop" também nunca é conclusão: bloqueio
inventado é pior que número inventado, porque para trabalho que estava liberado.

**Presença é fácil de provar; ausência é onde eu erro.** Três dos quatro erros de 09-10/09 foram
afirmações de que algo não existia. Portanto: **toda afirmação de ausência exige dois métodos
diferentes** — leitura direta do arquivo **e** o `_ds_manifest.json`. Com um método só, a frase é
"não medi". O mesmo rigor vale para "falta prop" e para "está bloqueado".

## Vocabulário fechado — três verbos, e nada mais

Toda frase de fato usa um dos três: **"medi"** (obrigatoriamente com arquivo:linha e árvore, medidos
na hora) · **"não medi"** (com o motivo e o que custaria medir) · **"suponho"** (só em conversa com
a Maiara — **nunca** em documento que Wagner, Luiz ou quem implementa vai ler; se é suposição, vira
pergunta, não frase no documento).

**Limites, para a regra não virar o oposto do que serve:**

- "Não medi" **não é escudo**. Sem motivo e sem custo declarado, é desculpa. Muitos "não medi" num
  entregável significam que ele não está pronto — contar antes de entregar.
- Marcar procedência **não é deixar de concluir**. Continuar recomendando caminho, escolhendo peça e
  dizendo qual é o certo. Depois de errar em afirmações de ausência, o risco é sobre-corrigir e
  devolver a decisão para a usuária — isso é falha, não cautela.
- Ausência continua exigindo **dois métodos** (ver seção anterior). Presença exige um: o endereço.

## Modo de trabalho que funcionou

Ondas numeradas de refinamento: eu levanto a lista (defeitos, remoções, melhorias) e a usuária
escolhe o que rodar. Perguntas de regra de negócio antes de implementar, não depois.
