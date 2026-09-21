# Quem manda em quem — explicado sem jargão

> Companion do `o-que-vale-como-fonte.md`, que é a versão técnica com arquivo e linha.
> Aqui é a mesma coisa em português comum.

## A imagem que resolve

Pense numa gráfica.

- Existe **o arquivo final aprovado**, o que vai para a máquina e sai impresso no cliente.
- Existe **o mostruário oficial** de papéis, tintas e acabamentos — o que a casa aceita usar.
- Existe **a fotocópia do mostruário** que fica na mesa de quem desenha.
- E existe **a prova**, o rascunho que a gente imprime para olhar antes de valer.

O nosso caso é exatamente isso, com outros nomes.

## As quatro camadas, de cima para baixo

**1. O sistema que roda de verdade** (o repositório, `oimpresso.com`)
É o produto no ar, usado por gente real. **Manda em todo mundo.** Se ele e qualquer outra camada
discordarem, ele ganha e as outras estão erradas.

**2. O mostruário oficial** (o Design System, o "DS")
O catálogo de peças prontas: botão, tabela, aba, campo, gaveta, etiqueta. Cada peça já vem com
comportamento embutido — a tabela oficial, por exemplo, já sabe responder ao teclado. **Manda na
camada 3 e na 4.** Quem escolhe o que entra ou muda nesse catálogo é o Wagner.

**3. A fotocópia do mostruário** (a pasta `_ds/` aqui dentro)
Uma cópia do catálogo que fica neste projeto só para o protótipo conseguir funcionar.
**Não manda em nada.** É cópia. E cópia envelhece: a nossa estava velha e foi isso que causou a
confusão desta semana.

**4. A prova** (o protótipo — as telas que eu monto para você ver)
Rascunho de alta fidelidade. **Não manda em nada, e não decide nada sobre o catálogo.** É onde o
problema *aparece*, nunca onde ele se resolve.

## Quem é quem no processo

| Quem | Função | O que essa pessoa decide |
|---|---|---|
| **Você** | dona da tela e do produto | o que a tela precisa fazer, o que entra em cada onda, o que é prioridade |
| **Wagner** | dono do mostruário | o que entra, muda ou sai do catálogo de peças; assina exceção quando alguma tela precisa fugir da regra |
| **Luiz** | trabalha na **tela de cadastro de venda** — fez modificações e melhorias nela. É mais exigente que a Maiara em criação de telas | as decisões da tela de cadastro de venda passam por ele; o que sobe para ele precisa estar mais apertado, não menos |
| **Eu** | desenho a prova e confiro se ela usa as peças certas | nada sozinho. Eu meço, relato e proponho; a decisão é sua ou do Wagner |
| **Os conferentes automáticos** | programas que ficam vigiando | avisam quando a fotocópia está velha ou quando alguém usou peça errada. Hoje eles **avisam, não bloqueiam** |

## O caminho normal das coisas

1. O catálogo muda no sistema (camada 1 ou 2).
2. Alguém manda essa mudança para a fotocópia daqui (camada 3). Isso é livre e acontece sempre nessa
   direção: **do sistema para a cópia**.
3. Eu monto a prova usando as peças da cópia (camada 4).
4. Você olha e aprova, ou pede mudança.

**A direção contrária é diferente.** Se eu invento uma peça na prova e ela parece boa, ela **não
entra** no catálogo sozinha. Vira proposta, o Wagner analisa, e só então entra. Isso é de propósito:
se o caminho fosse automático, qualquer improviso meu se tornaria regra da casa.

## Três palavras que confundem, traduzidas

**"Baseline"** — não é catálogo nem lista de peças. É um **piso de tolerância**: quanto de diferença
entre o sistema e a cópia a gente aceita antes do alarme tocar. Alarme, não trava — hoje ele só
avisa.

**"Âncora"** — nada a ver com aparência de tela. Serve para os documentos não apodrecerem: quando um
trecho de código muda de lugar, ela corrige **o endereço** citado no documento, e nunca a frase. É
uma distinção importante, e foi justamente ela que eu violei: chutei um número de linha novo em vez
de ir medir. Errei o chute.

**"Onda"** — nosso jeito de trabalhar. Eu levanto a lista de problemas, agrupo por risco, e você
escolhe quais grupos rodar. Nada roda sem você escolher.

## O que deu errado esta semana, em uma frase

Eu olhei a fotocópia velha, vi que faltava uma peça, e anunciei que "o catálogo não tem" — quando o
catálogo tinha, e desde ontem. Pior: usei uma busca que devolve resultado vazio mesmo quando a
palavra está no arquivo, e tratei esse vazio como prova.

Consequência prática: eu quase pedi ao Wagner a aprovação de três coisas que já existiam, e chamei
de "defeito" algo que era só a minha cópia estar atrasada. Vocês estavam certos em recusar.

Três coisas que passei a fazer por causa disso:

1. Antes de dizer "o catálogo não tem", abrir o catálogo de verdade. Sem ele aberto, a frase é **"não
   medi"** — nunca "não existe".
2. Nunca tratar busca vazia como prova de ausência.
3. Toda afirmação com o endereço do que eu li, medido na hora, não reaproveitado.

## Como me pedir uma conferência sem margem para eu errar

**A resposta honesta: você não deveria precisar de fórmula.** Errar o lugar da comparação foi
método meu, não ambiguidade sua — "confere com o DS" sempre quis dizer a fonte viva. Já registrei o
default no `CLAUDE.md`, então "confere se está de acordo com o DS" agora basta.

Se quiser travar de qualquer forma, a frase que fecha todas as saídas:

> **"Compare com a fonte viva do DS (repo e projeto do DS), não com o `_ds/`. Antes de começar,
> regenere o `_ds/` e me diga se estava velho. Cada peça com arquivo:linha medido agora, e marque o
> que você mediu e o que só leu."**

Três cobranças curtas que você pode fazer a qualquer momento, e que pegam exatamente os meus erros:

1. **"Você mediu ou leu?"** — foi a sua pergunta que virou a auditoria.
2. **"De qual árvore veio esse número?"** — separa fonte viva de cópia velha.
3. **"O `_ds/` estava atualizado quando você mediu?"** — se eu não souber responder, a auditoria não vale.

E uma que vale para tudo, não só para DS: **"o que você não mediu?"** Item não medido dito em voz
alta é barato; item não medido apresentado como fato custou três retrabalhos.

## As respostas certas para essas quatro perguntas

Para cada uma: o que é resposta boa, o que **parece** boa e não é, e como você confere em cinco
segundos sem abrir código nenhum.

**1. "Você mediu ou leu?"**
- **Boa:** *"Medi agora: `components/DataTable/DataTable.jsx`, linha 57."* Endereço e árvore na mesma
  frase. Ou, quando for o caso, o oposto sem rodeio: *"Li, não medi — então não afirmo."*
- **Parece boa:** "Medi" sozinho. "Como eu disse antes, linha 2989." Número reaproveitado de mensagem
  anterior é o erro do `cli` — L2989 virou L2993 por chute, e o certo era L3040.
- **Seu teste:** resposta sem endereço não é medição. Número que já apareceu antes tem que vir com
  "remedido agora".

**2. "De qual árvore veio esse número?"**
- **Boa:** nomeia uma das quatro camadas com caminho — *"projeto do DS, `components/DataTable/DataTable.jsx`"*
  ou *"repo, `shared/DataTable.tsx`"*.
- **Parece boa:** *"do DS"*. É exatamente essa vagueza que me deixou errar a semana inteira: o
  espelho velho também se chamava "o DS". *"Do bundle"* também é fraco — bundle é compilado, não é
  ponto de partida.
- **Seu teste:** a palavra "DS" sem caminho não é resposta.

**3. "O `_ds/` estava atualizado quando você mediu?"**
- **Boa:** com número. *"Comparei antes: fonte viva 9.355 linhas, cópia 9.290 — estava velha,
  regenerei os dois espelhos."* Ou: *"Não usei o `_ds/` para concluir nada; a conclusão saiu da fonte
  viva."*
- **Parece boa:** *"Sim, está atualizado."* Sem número, é fé.
- **Seu teste:** resposta sem número não é resposta.

**4. "O que você não mediu?"**
- **Boa:** lista curta, específica, com o motivo — *"não medi se o sonner está instalado no repo; não
  medi a terceira pasta `_ds/office-impresso-atual-d7f88676`, e não sei se alguém a consome."*
- **Parece boa:** *"Medi tudo."* Nunca é verdade.
- **Seu teste:** se a lista vier vazia, está errada. Sempre tem algo fora.

## A forma comum dos meus erros — e o que muda a taxa de acerto

**Três dos quatro erros desta semana foram afirmações de ausência:** "o DS não tem `caption`", "falta
`stickyHeader`/`minWidth`, então está bloqueado", "não existe live region". Nenhum foi erro de leitura
de valor — foram erros de dizer que algo **não existe**.

Isso tem explicação e conserto. Provar que algo **existe** é fácil: eu abro o arquivo e mostro a
linha. Provar que **não existe** exige varrer tudo, e é aí que a ferramenta barata mente — a busca
cross-project devolve vazio com o termo presente, e a busca local que estoura o tempo avisa
"incomplete" numa linha que eu ignorei.

O que passo a fazer, e você pode cobrar:

1. **Toda afirmação de ausência precisa de dois métodos diferentes** — leitura direta do arquivo
   **e** o manifest do DS. Um método só não sustenta um "não tem".
2. **Ausência sem os dois métodos vira "não medi"**, que é uma resposta legítima e barata. "Não
   existe" é caro e eu tenho errado nele.
3. **"Falta prop" recebe o mesmo rigor que "não existe"** — foi o bloqueio inventado que travou a
   onda 1 por dois dias. Bloqueio falso é pior que número falso: para trabalho que estava liberado.

A pergunta única que resume as quatro, se você quiser só uma: **"isso é presença ou ausência, e se é
ausência, com que dois métodos você mediu?"**

## O que continua valendo do que eu levantei

Independente de toda essa confusão, três coisas seguem de pé e nenhuma depende do Wagner ou do Luiz:

- **O pacote de instruções da Fabricação está desatualizado.** Quem for programar lendo aquilo vai
  refazer a versão antiga, sem as correções que já pagamos.
- **A tela não tem rede de proteção:** se o catálogo não carregar, o módulo inteiro apaga em vez de
  avisar.
- **As tabelas da nossa prova continuam sem responder ao teclado** — mas agora sabemos que a peça
  oficial já resolve isso. É trocar a peça caseira pela oficial. Sem esperar ninguém, sem discussão.
