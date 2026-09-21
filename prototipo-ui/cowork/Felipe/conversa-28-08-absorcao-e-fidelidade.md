# Conversa de 28/08/2026 — absorção da V2 e fidelidade da tela "Todos os produtos"

Registro da sessão entre Maiara (dona da tela V2) e o agente. Ordem cronológica. Serve para retomar
o assunto sem reler o chat: cada bloco diz o que foi perguntado, o que foi medido, o que foi decidido
e onde o resultado ficou escrito.

**Documentos tocados nesta sessão:** `absorcao-v2-em-todos-os-produtos.md` (§-1, §2-bis, §4, §7,
§9-§21, §R) · `pauta-design-system.md` (pergunta ao shell) · `produto-blade.css` (onda 0) ·
`produto-blade.jsx` (ondas 0c e 0d).

---

## 1 · Alvo da comparação — errei duas vezes

**Pergunta.** *"Comparou com a tela correta? A do Wagner (Todos os Produtos — Produtos Catálogo) não
está em react?"*

Primeiro erro: eu tinha comparado contra a tela certa e me convenci do contrário. Escrevi uma errata
apontando o alvo para o repo React (`resources/js/Pages/Produto/Unificado/Index.tsx`). Segundo erro:
a errata estava errada — a tela em questão é a do **protótipo**, `produto-blade.jsx`, rota
`prod-lista`, confirmada por screenshot da usuária.

**Direção da absorção, confirmada:** a `Consulta de Produtos V2` (`.dc.html`, da Maiara) **doa**; a
`Todos os produtos` (do Wagner, no protótipo) **recebe e é a autoridade**. Ao fim, o item da V2 sai
da sidebar; o arquivo não é apagado sem ordem.

**Fica registrado** (§-1 do documento, como contexto para quem implementa no repo, não como
autoridade): a tela React `/products/unificado` **já absorveu** boa parte da V2 em ondas anteriores —
`Permissoes {custo, preco, composicao, inativar}`, seis abas por tipo, KPI-filtros, `EstadoEstoque`
com "Disponível / Abaixo do mínimo / Sem saldo", `marcadorGrade` "4 de 6 com saldo", saldo por local,
paginação server-side.

---

## 2 · Nomenclatura — quatro decisões da Maiara

Ela escolheu, conceito por conceito, entre o léxico do protótipo e o do repo React:

| Conceito | Fica | Origem |
| --- | --- | --- |
| Identificador do item | **Referência** (SKU cadastrado) + **Código** (id interno) | repo React |
| Quantidade em estoque | **Estoque atual** | protótipo (alvo) |
| Limite de reposição | **Mínimo** ("Abaixo do mínimo") | repo React |
| Estado do saldo | **Disponível / Abaixo do mínimo / Sem saldo / Não estocável** | repo React |

**Pendência aberta:** "Disponível" já existe no alvo com outro sentido — `Disponível nos locais`
(L478) é a **lista de locais** onde o produto é vendável, não quantidade. Com o estado do saldo
chamado "Disponível", a palavra passa a significar duas coisas na mesma tela. Sugestão registrada:
a linha do drawer vira **"Vendável nos locais"**. Não implementado.

---

## 3 · Como pedir comparações (§9) e os eixos que faltavam

**Pergunta.** *"Qual seria o modo correto de eu te pedir para fazer essas comparações, considerando
funções/comportamentos/usabilidade/fidelidade ao template e ds? Esses pontos você verificou também?"*

Resposta honesta na hora: eu tinha rodado função e comportamento, usabilidade só em parte, e
**fidelidade ao template/DS não tinha rodado**. Ficou escrito o §9 com quatro eixos e a frase que
basta: *"compare A com B nos quatro eixos; para cada achado diga qual eixo, a linha nas duas pontas e
se duplica algo que já existe."*

Depois ela pediu os eixos restantes, e eles renderam seis blocos novos:

- **§10 · fidelidade ao template e ao DS** — 9 divergências (padding em px, densidade por classe,
  rampa não usada, piso de 10px, KPI local, raio sem sombra, tabelas secundárias, rodapé, cursor).
- **§11 · modelo de dados** — 11 campos que só a V2 tem, 11 que só o alvo tem. Achado que muda a
  ordem: absorver saldo por local exige mudar a **forma** do dado, não só acrescentar campo.
- **§12 · exportação** — o alvo tem dois botões parecidos com escopos diferentes: "Baixar Excel"
  exporta o catálogo inteiro ignorando filtros; "Exportar filtrados" exporta o recorte. E o CSV
  exporta a coluna **Compra** (custo) sem consultar permissão.
- **§13 · papel × o que vê** — os dois sistemas de permissão não se encostam. O alvo tem 13
  permissões de cadastros de apoio; os nomes certos para custo e preço existem no legado
  (`view_purchase_price`, `access_default_selling_price`). Matriz 9×4 proposta, fechada, para marcar.
- **§14 · estados** — a lista é a única tela do módulo que não sabe representar erro nem falta de
  permissão (a prop `estado` existe e para antes dela).
- **§15 · toque e janela** — aqui a **V2 está atrás**: o alvo tem alvos de 44px e dois breakpoints; a
  V2 não tem media query nenhuma. Absorver a faixa de KPI da V2 como está perderia isso.
- **§16 · acessibilidade** — cada tela tem o que a outra não tem. Da V2: `aria-keyshortcuts="/"` e
  `role="img"` no espaço da foto. Do alvo, já correto e a não mexer: `title` no Drawer, `<h3>` nos
  sub-títulos.

**Total: 21 itens de absorção**, mais a matriz do §13, as 9 divergências do §10 e 11 pendências no §7.

---

## 4 · Recomendação item a item (§R)

**Pergunta.** *"Me mostre a lista completa e sua recomendação para cada item e suas consequências."*

- **Absorver como está (8):** custo da composição derivado · montáveis do kit · consequência do
  estado (reusando a frase que o modal já tem) · ◂▸ no drawer · vazio por causa · recentes na paleta ·
  erro e sem permissão · aria do `/` e da foto.
- **Absorver com condição (7):** KPI clicável (os quatro KPIs continuam sendo **os do alvo**) ·
  permissões (com os nomes do legado, e o CSV no mesmo gate) · totais com escopo no rótulo · KPI que
  não recorta desaparece (adaptado ao select "Tipo", já que o alvo não tem abas por tipo) · recorte
  persistido **sem** a busca · teclado com o contorno do `DataTablePro` declarado · exportar seleção
  junto do renome dos dois botões.
- **Depende de dado novo (3):** subconjunto declarado · rótulo com escopo · observação com
  criticidade (`product_description` não serve).
- **Não nesta onda (3):** paginação (recusa mantida, dívida declarada) · reposição e giro · alçada de
  desconto (origem inexistente, e quem calcula pedido é Orçamento/PDV).

---

## 5 · A pergunta que mudou o plano — fidelidade antes da absorção

**Pergunta.** *"Não é mais fácil primeiro colocar a tela do Wagner dentro do DS/template/comportamento
de tela e só depois fazer a absorção?"*

**Resposta: é, para os itens de moldura; não é como regra geral.** O KPI virar componente do DS já
era pré-requisito do KPI clicável. Mas sete itens não tocam moldura nenhuma e não ganham nada
esperando. A ordem foi reescrita em ondas 0, 0b, 0c, 1, 2, 3, 4 — com a onda 1 correndo em paralelo à
0b.

**Cuidado registrado:** "colocar a tela dentro do template" não é reescrever a tela sobre o PT-01. O
`min-height:100vh` do shell dele só funciona porque ele passa `height` ao `DataTablePro`; copiado para
tela com `DataTable`, o cabeçalho fixo sai da tela sem erro no console.

### A decisão sobre a onda 0b

**Pergunta.** *"Vai me gerar retrabalho depois? Atrasar alguma modificação? Recomendação sincera e
realista."*

Resposta: retrabalho perto de zero (quase tudo novo entra dentro de componentes do DS, que já trazem
o espaçamento deles), atraso nenhum, e o risco real não é técnico — é a tarefa ser invisível, mexer em
**11 telas** e nunca ter dono pressionando.

**Decisão da Maiara: adiar com as duas condições.** A 0b roda sozinha, em rodada sem nada mais
mexendo no módulo; e todo CSS novo que eu escrever no módulo já usa as variáveis, para a fila não
crescer. **Defeito que fica aberto e a usuária vê:** o botão **Compacto** promete adensar a tela e
adensa só a tabela. Registrado no §17.

---

## 6 · Onda 0 — aplicada (`produto-blade.css`)

| Item | Está agora | Estava |
| --- | --- | --- |
| Piso tipográfico | `var(--fs-1)` (10,5px) em **5** seletores | `10px` cravado |
| Moldura do card | `var(--radius-lg)` + `var(--shadow-soft)` | raio 12px cravado, sem sombra |
| Cursor | `cursor:pointer` em **7** controles clicáveis | `cursor:default` |

**Erratas medidas ao aplicar** (as duas contra a minha própria auditoria):

1. O item do raio era **menos** do que eu disse: dentro do cockpit, `--radius-lg` **é** 12px — o mesmo
   número. A aparência não muda; muda o mecanismo. O que muda de verdade é a sombra, e ela também tem
   token (`--shadow-soft`) — que o próprio template escreve literal em vez de usar.
2. O piso estava rompido em **5** lugares, não 2.
3. **Ainda abaixo do piso, não corrigido porque não estava declarado:** `kbd` do `/` (9px), texto
   "IMG" da miniatura (9px), seta de ordenar (8,5px), miniatura no compacto (8px). Recomendado tratar
   junto do item do aria.
4. **Fora do escopo, não mexido:** raio cravado do modal e da moldura de imagem.

---

## 7 · Onda 0c — KPI pelo componente do DS (`produto-blade.jsx`)

As quatro placas passam a ser `KpiCard` do DS; o `.pb-kpi` local fica só como plano B enquanto o
bundle (`defer`) não chegou. Rótulos, sub-linhas, cor de alarme e a grade responsiva ficaram iguais.

**Decisão tomada dentro da onda, contrariando o que eu tinha recomendado:** usei `KpiCard` (leitura),
**não** `KpiFilterCard`. O `KpiFilterCard` é um `<button aria-pressed>` — montá-lo sem clique
entregaria quatro botões mortos, dois deles para sempre. Ele entra no item do KPI clicável, e só nas
duas placas que recortam.

---

## 8 · O buraco que a Maiara achou — cabeçalho e abas

**Pergunta.** *"Tem certeza que você aplicou todas as alterações necessárias? O pageheader parece um
pouco diferente, assim como não vejo a tabbar."*

Não tinha. O §10 olhou KPI, padding, rampa, raio, cursor e tabelas — **e não olhou cabeçalho nem
abas**. Buraco meu, achado por ela olhando a tela.

**Abas (era caso de tela, foi feito — onda 0d).** Eram `nav.cli-moduletopnav` com dois botões escritos
à mão, estilizados por `clientes-page.css` — imitação da `TabBar`, não a `TabBar`. Agora são o
`TabBar` do DS, com o contador preservado, e nada mais saiu da tela. Três consequências declaradas:
o `aria-label` passa a ser "Sub-navegação" (valor do componente); o sublinhado ativo usa `--accent`
(que o shell reescreve em runtime — defeito já aberto); e a `TabBar` tem defeito conhecido de 1px de
barra de rolagem, cujo contorno **não** apliquei porque ela pediu para não acrescentar nada.
**Não tocado, declarado:** o mesmo `nav` **dentro do drawer**.

**Cabeçalho (não é caso de tela).** A tela monta `M.Header` (`modulo-padrao.jsx` L18), não o
`PageHeader` do DS. O mesmo `M.Header` serve **nove** telas do protótipo — produto, venda, venda
index, CRM, portal, compras, financeiro, WooCommerce, restaurante. Trocar só aqui criaria duas
gramáticas de cabeçalho no mesmo shell: a tela ficaria "certa" pelo template e órfã dentro do
produto. Virou **pergunta na pauta**, não conserto.

---

## 9 · Cabeçalho — a proposta que eu retirei

**Pergunta.** *"Interessante sua recomendação: PageHeader do DS ganha essas duas capacidades... Como
você faria isso?"*

Fui medir para escrever a proposta e **ela não era necessária**. O `PageHeader` (`_ds_bundle.js`
L5158-5216) já aceita `title`, `subtitle`, `stats` e `actions`, e o `stats` é exatamente uma lista de
`{value, label, tone}` impressa separada por `·` com `tabular-nums` — é a linha de contexto. O
"atualizado às" cabe em `actions`. Não há nada a pedir ao DS. **Proposta retirada antes de virar
pedido**, e a pergunta na pauta passou de três saídas para duas: compor agora, ou exceção assinada.

O caminho, se a saída 1 for escolhida: o `M.Header` mantém a assinatura atual (`modulo`, `papel`,
`contexto`, `atualizadoAs`, `onRefresh`, `glyph`, `acoes`) e por dentro monta o `PageHeader`. Nenhuma
das nove telas muda de chamada.

**Três consequências medidas, registradas na pauta:**

1. **A placa do glyph desaparece** — o `PageHeader` não tem prop de ícone, e a tela React em produção
   também não tem glyph. É conformidade, não defeito. Se o glyph for identidade a preservar, aí **sim**
   é proposta ao DS. **Decisão da Maiara, pendente.**
2. **O `stats` corta em 56ch** — o contexto atual tem quatro itens e seria truncado. Recomendado cair
   para três, tirando "papel: Administrador", que já aparece no rodapé da sidebar.
3. **"Atualizado às" deveria virar texto**, com "Atualizar" dentro do `⋯` — horário é contexto,
   reapurar é ação. E o padrão da tela React é **uma** ação primária visível + `⋯`.

---

## 10 · Abas por tipo — por que não estão na TabBar

**Pergunta.** *"Na tabbar não está aparecendo os tipos de produto porquê? Por que não dá de deixar os
tipos de produto e o relatório de estoque na tabbar?"*

Porque a onda 0d trocou a **moldura**, não o conteúdo: as abas da tela do Wagner sempre foram duas, e
o recorte por tipo mora no select "Tipo de produto". As seis abas por tipo são da V2, e o §3 as havia
descartado por autoridade da tela do Wagner.

**Dá para juntar os dois na mesma barra** — nada no componente impede. O problema é de desenho: uma
barra com dois eixos. Cinco abas de tipo filtram a mesma tabela; "Relatório de estoque" troca a tabela
por outra, com colunas próprias — quem clica em "Serviços" e depois no relatório não sabe se o recorte
sobreviveu.

**Fato que resolve:** "Relatório de estoque" **já tem porta própria na sidebar**. Tirá-lo da barra não
perde acesso. É também o que a tela React faz. **Recomendação:** seis abas por tipo na `TabBar`, o
relatório pela sidebar. Se ficar na barra, com a condição de o recorte de tipo atravessar. **Pendente
de decisão dela.**

---

## 11 · Onda 0e — filtros (autorizada, a rodar)

**Pergunta.** *"E os filtros da tela estão de acordo com o ds/template?"* Medido: **não**. O widget
"Filtros" fica (o §3 já decidiu), mas os **controles** são HTML cru com CSS da tela — sete `<select>`
e um `<input type=checkbox>` estilizados por `.pb-fld` — quando o DS tem `Select` e `Checkbox` no
bundle já carregado. Mesmo caso do `.pb-kpi`.

Muda: os oito controles. **Não muda:** o widget recolhível, o título, a grade de quatro colunas, os
rótulos, a ordem e o botão de limpar. O campo de busca fica como está — o `Input` do DS não tem slot
de ícone, e é por isso que o próprio template o desenha à mão.

---

## 12 · Estado no fim da sessão

**Aplicado:** onda 0 (piso, moldura, cursor) · onda 0c (KPI pelo `KpiCard`) · onda 0d (abas pelo
`TabBar`). Console sem erro nas duas últimas.

**Autorizado, a rodar:** onda 0e (controles do widget Filtros).

**Adiado com condições:** onda 0b (tokens de espaçamento e rampa; 11 telas), com o defeito do botão
Compacto declarado.

**Esperando decisão da Maiara:**

1. Glyph no cabeçalho — perder por conformidade, ou abrir proposta ao DS?
2. Cabeçalho — compor o `PageHeader` dentro do `M.Header` (nove telas), ou exceção assinada?
3. Seis abas por tipo na `TabBar`, com o relatório pela sidebar?
4. Os 21 itens de absorção, a matriz 9×4 de permissões e as 11 pendências do §7.

**Erros meus nesta sessão, para não repetir:** apontei o alvo errado e depois escrevi uma errata
errada sobre isso · afirmei que o `PageHeader` precisava ganhar capacidades que ele já tem, e retirei ·
recomendei `KpiFilterCard` onde ele entregaria botão morto, e corrigi ao aplicar · contei 2 quebras de
piso onde havia 5 · e entreguei uma auditoria de fidelidade sem cabeçalho e sem abas, buraco que a
usuária achou antes de mim. Padrão comum aos cinco: **parar de medir cedo e escrever conclusão como
se fosse medição.**
