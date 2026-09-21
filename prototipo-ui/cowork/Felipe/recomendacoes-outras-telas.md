# Recomendações levantadas na consulta de produtos, para outras telas

Registrado em 20/08/2026, a partir das ondas de refinamento da tela **Consulta de Produtos**.
Princípio que separou os itens: **o drawer da consulta é leitura**. Formar preço é cadastro;
calcular pedido é orçamento/PDV.

## Orçamento / PDV

**Calculadora de quantidade.** O vendedor digita a quantidade e o sistema devolve a faixa
aplicada, o preço unitário e o total, somando o acréscimo da combinação escolhida. Hoje a
tabela de faixas é passiva: obriga o vendedor a achar a faixa, ler o preço, multiplicar e
somar o acréscimo de cabeça — erro fácil e na frente do cliente.

**Empurrão para a próxima faixa.** Quando a quantidade digitada está perto do degrau
seguinte, avisar quanto falta e quanto o cliente economizaria. Ex.: pediu 80 m² a
R$ 68,00 = R$ 5.440,00; com 100 m² cai para R$ 61,00 = R$ 6.100,00. Aumenta o pedido e o
cliente sente que ganhou. Nenhum sistema legado faz isso porque a tabela é passiva.

**Conferência de saldo contra o pedido.** Escolhida a combinação e digitada a quantidade,
confrontar com o saldo daquele filho: "3 m² disponíveis, você pediu 10 — faltam 7". Hoje
preço e saldo são duas leituras em duas seções, e dá para acertar o preço e prometer o que
não existe.

**Sugestão de substituição dentro da grade.** Combinação sem saldo passa a oferecer a
alternativa próxima: "sem saldo em 1,00 m — há 5 m² em 1,40 m (R$ 80,50)". Pendente de
decisão comercial: substituir por medida é aceitável ou cria expectativa errada?

**Trava de desconto por alçada.** O drawer da consulta exibe o limite ("pode fechar até
R$ 306,00 · sua alçada −8%"); o orçamento é quem *aplica*. Três faixas: até a alçada do
colaborador, fecha direto; entre a alçada e o piso da empresa, aceita com aprovação do
gerente; abaixo do piso, bloqueia. O piso nunca aparece ao vendedor — ele revelaria o custo.

**Copiar para o orçamento.** Copiar código do filho, quantidade, preço unitário e total no
formato que o orçamento aceita. Trabalho manual que existe hoje e ninguém mede.

**A entrada no orçamento parte do orçamento, não da consulta** (Wagner, 26/08/2026). O botão "Usar
em orçamento" foi **removido** do painel da Consulta de Produtos. Consequência para esta tela: o
orçamento precisa de busca de produto boa por dentro — a mesma busca da consulta (nome, código,
referência, categoria e código de filho de grade, sempre resolvendo para o pai), porque agora ela é
o único caminho. Se o vendedor tiver de sair do orçamento para achar o item, o trabalho só mudou de
lugar.

## Permissões — onde se configura

**Três naturezas, três donos.** Visibilidade (`preco`, `custo`, `composicao`, `compras`,
`margem`) é do **perfil de acesso** (molde, vale para todos os vendedores). Alçada de desconto é
da **pessoa**. Piso de margem por categoria e janela de dias sem venda são **parâmetros do
módulo**. A consulta de produtos apenas lê — nada disso é editável nela.

**Um ponto de entrada, ainda que o modelo seja separado.** A tarefa do administrador é "configurar
o que essa pessoa pode fazer", não "editar um perfil e depois um colaborador". A **ficha do
colaborador** deve concentrar: seletor de perfil de acesso + resumo do que ele concede ("vê preço ·
não vê custo, margem, composição, compras") + link para editar o perfil (que afeta todos) + campo
de alçada + exceções individuais marcadas como exceção, para auditoria. O caminho raro — mudar o
que "Vendedor" significa — tem tela própria, e é bom que tenha: atinge doze pessoas de uma vez.
*Sinal de que está resolvido: o administrador nunca abre o editor de perfis para colocar uma pessoa
nova em operação.*

**Não fundir alçada no perfil.** Viraria "Vendedor 8%", "Vendedor 12%", "Vendedor 15%" — a
explosão de perfis clássica de ERP.

**Esconder no front não é permissão.** A tela não pode receber custo e margem no payload e apenas
não desenhar: se o dado chega ao navegador, quem abre a aba de rede o vê. O filtro tem de estar na
consulta do servidor; o front apenas não renderiza o que não recebeu. Vale para custo, margem,
composição de kit e dados de compra (fornecedor, última compra).

## RH / perfil do colaborador

**Alçada de desconto por colaborador**, não por perfil. Em gráfica o vendedor sênior negocia
mais que o júnior, e a alçada é atributo da pessoa (como a comissão). Cadastrada em % sobre o
preço de tabela — nunca sobre o custo ou a margem, para poder ser exibida a quem não tem
permissão de custo. Alçada 0 = só fecha no preço de tabela.

## Cadastro do produto

**Criar e editar faixas de preço** (política do pai, uma tabela por produto).

**Definir acréscimo por combinação**, nas duas formas: percentual (custo de matéria-prima —
metálico, translúcido) e valor fixo (acabamento — corte especial). Exibir sempre o efeito
final ao lado da fórmula, para quem cadastra conferir.

**Piso de margem por categoria.** `preço mínimo = custo ÷ (1 − piso)`. Comunicação visual
sustenta margem diferente de insumo revendido; um piso único para a empresa toda erra nos
dois lados. Parâmetro do administrador, invisível ao vendedor.

**Escolher o modelo de grade** e marcar quais combinações o produto realmente oferece. O
modelo permite N; cada produto decide se passa a oferecer. Acrescentar uma cor ao modelo não
deve fazê-la aparecer sozinha em nenhum produto.

## Cadastro de unidades — sigla precisa de descrição

Levantado em 26/08/2026 pelo Felipe, na consulta: o selo da coluna Disponível mostra "0 br", "0 L",
"0 cj" e a sigla não se explica sozinha para quem não é do estoque.

**Decidido para a consulta:** a sigla fica visível (é o que o balcão usa, e a coluna é estreita) e o
nome da unidade vai na dica do selo — "br = barra". **O que falta é do cadastro:** a unidade precisa
de **sigla + descrição** como campos, e a descrição é a fonte da dica em toda tela que mostrar
quantidade. Hoje o protótipo usa um mapa ilustrativo no front, o que não escala: cada cliente
cadastra as suas siglas.

Sem descrição cadastrada, a tela não inventa nome — simplesmente não mostra dica.

## Modelo de dados acordado (vale para todas as telas)

- **Grade (filho)** — saldo, mínimo, local, código próprio. Dono: estoque. O saldo do pai é
  derivado (soma) e nunca digitado; não é vendável como bloco.
- **Preço base + faixas por quantidade** — no pai. Dono: comercial.
- **Acréscimo por combinação** — exceção, no filho, percentual ou valor. Dono: comercial.
- **Venda pelo pai.** O filho tem código e serve para *encontrar* (busca resolve para o pai,
  indicando qual combinação casou); a ação de venda é do pai.
- **Desconto.** Alçada do colaborador incide sobre o **preço de tabela**; piso de margem
  deriva do **custo**. Manter as duas contas separadas é o que permite mostrar o limite de
  negociação a quem não pode ver custo.
- **Permissões** — cinco chaves: `preco`, `custo`, `composicao`, `compras`, `margem`.
  `compras` cobre fornecedor e histórico de compra: dado de relacionamento comercial, não de
  atendimento — o vendedor não precisa e a lista de fornecedores sai da empresa com quem sai.
  O que o atendimento precisa (prazo de entrega, pedido mínimo) fica visível a todos. Regra travada: `margem` exige `custo`, porque preço + margem revelam o custo por
  aritmética (`custo = preço × (1 − margem)`). O contrário é válido e útil: o comprador vê
  custo e não precisa de margem.
- **Kit** — composição com quantidade por unidade; montáveis = mínimo de
  (saldo do componente ÷ quantidade por kit); disponível para venda = montados + montáveis.
  Custo do kit e composição compartilham a mesma permissão: oculta uma, oculta a outra.

## Design system

Pauta completa (propostas de prop, defeitos de origem e contornos em pé) em
`pauta-design-system.md` — a discutir com quem mantém o DS.

## Defeito de origem no design system

**TabBar (ADR 0403)** — declara `overflow-x: auto`, o navegador força `overflow-y: auto`, e
com botões de 36px numa caixa de 35px sobra 1px: aparece barra de rolagem vertical. Não é
suprimível de fora (overflow do ancestral não afeta o descendente que rola). A tela carrega
um override no helmet até a altura ser corrigida na origem.

**Wrapper de ícones (`Icon`)** — desenha apenas os nomes que conhece; nome fora do mapa
renderiza um quadrado vazio, sem erro. Impede unificar os dois sistemas de ícone da tela sem
mexer no wrapper.
