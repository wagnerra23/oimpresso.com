---
id: resources-js-pages-sells-createv3-casos
casos: Venda V3 (preview de design) · /sells/create-v3
irmaos: CreateV3.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: luiz
last_run: "2026-08-12"
---

# Casos de Uso & Aceite — Venda V3 (preview)

> **Tela de PREVIEW, não de produção.** Dono: **[L] Luiz**. A venda real continua em `/pos/create`
> (`Sells/Create.tsx`), operada pela ROTA LIVRE — e essa tela **não pode ser alterada**
> (restrição de negócio [L] 2026-08-06). Lei da tela: [`CreateV3.charter.md`](CreateV3.charter.md).
>
> **Status:** ✅ passa (com prova no manifesto) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.

---

## Como esta lista cresce

Um `UC-*` só existe honestamente quando **≥1 teste o cita** — é o G-2 do `casos-gate`, que é **required**.
Criar UC sem teste faria o contrato nascer órfão e **bloquearia o merge de quem viesse atendê-lo**.

Então o que ainda não tem teste fica como **prosa declarada** no formato `[BACKLOG]` (sem id):
visível pra quem for escrever o teste, sem gate que ela não possa cumprir. Cada item vira
`UC-V3xx` **no PR que trouxer o teste que o cita** — não antes.

**2026-08-07 (US-SELL-058):** os três primeiros foram promovidos junto com
[`SellsCreateV3ContratoTest.php`](../../../../tests/Feature/Sells/SellsCreateV3ContratoTest.php).
O resto segue backlog — de propósito: eles exigem sessão autenticada, permissão semeada ou
render, e nada disso foi escrito ainda.

⚠️ **`✅` não é "o teste passou" — é "o manifesto do G-7 provou".** A primeira redação daqui
dizia *"vira ✅ com run verde citado"*, e estava errada: o `casos-gate` (required) lê
`scripts/casos-test-results.json`, e `✅` sem entrada lá vira `status:unverified` e **derruba o
gate**. Run verde é insumo; o veredito é o manifesto, alimentado por
`node scripts/casos-results-collect.mjs` (merge per-UC) a partir do JUnit da lane.

---

## UC-V301 · A rota de leitura existe e é servida pelo controller do preview

**Status:** ✅ — verde na lane `Pest (Sells · MySQL)`, [run 31203571574](https://github.com/wagnerra23/oimpresso.com/actions/runs/31203571574) sobre `c0214c6` (`40 passed · 133 assertions`; o arquivo contribuiu `passed: 3 · failed: 0`), com o veredito no manifesto do G-7.

- **Dado** o roteador da aplicação carregado,
- **Quando** se procura a rota nomeada `sells.create-v3`,
- **Então** ela existe, responde em `sells/create-v3`, aceita `GET` e é servida por `SellsV3Controller@create`.

Prova: `tests/Feature/Sells/SellsCreateV3ContratoTest.php`. Oráculo é o **roteador em runtime**
(`Route::getRoutes()`), não `grep` no `routes/web.php` — ler o arquivo responde "o texto está lá",
não "a rota está registrada" ([§5](../../../../memory/proibicoes.md), 2026-07-17).

---

## UC-V302 · A tela não grava — nenhuma rota de escrita aponta pro controller do preview

**Status:** ✅ — verde no mesmo [run 31203571574](https://github.com/wagnerra23/oimpresso.com/actions/runs/31203571574), veredito no manifesto do G-7.

- **Dado** que o preview existe para ensaiar desenho, não para vender,
- **Quando** se enumeram todas as rotas cujo controller é `SellsV3Controller`,
- **Então** nenhuma delas aceita `POST`, `PUT`, `PATCH` ou `DELETE`, e o controller não declara `store()`, `update()` nem `destroy()`.

Anti-vácuo: o teste primeiro prova que o controller **está roteado** — sem isso, "zero rotas de
escrita" também seria verdade num mundo onde o controller não existe.

---

## UC-V303 · Fronteira: o preview não encosta nos artefatos da tela viva

**Status:** ✅ — verde no mesmo [run 31203571574](https://github.com/wagnerra23/oimpresso.com/actions/runs/31203571574), veredito no manifesto do G-7.

- **Dado** que a razão de a tela existir é não tocar em `Sells/Create.tsx` (ROTA LIVRE, 99% do volume),
- **Quando** se inspecionam o controller e a Page do preview,
- **Então** o controller não usa/estende/instancia `SellPosController`, e nenhum `import` da Page vem de `Sells/Create` nem de `Sells/_components`.

O acoplamento é medido no que o **parser** vê (tokens PHP sem comentário; especificador de
`import`), não no texto cru — o docblock do V3 **cita** `SellPosController@create` para explicar
por que a tela existe, e um `toContain` no arquivo inteiro reprovaria a própria documentação.

**Endurecido em 2026-08-10 (mesmo UC, assert mais forte).** A redação anterior comparava o
especificador **cru** contra `"Sells/_components"`, então um import **relativo**
(`./_components/…`) passava sem conter o prefixo — mesmo apontando para dentro da pasta
vigiada. Passava por *forma*, não por estar certo. Agora o especificador é **normalizado**
(relativo → caminho do repo) antes de comparar, com controle positivo de que a normalização
de fato aconteceu, e a exceção é explícita: `_components/v3/` é a casa dos primitivos nascidos
para o preview — a cópia local que a Fronteira do charter manda criar em vez de editar o
original. O que dá **substância** à exceção é o assert do outro lado: a tela **viva** não
importa nada de `_components/v3/`. No dia em que importar, a pasta deixa de ser exclusiva e
editar um arquivo dela volta a vazar pra ROTA LIVRE — que é o que este UC existe pra impedir.

---

## Parcelas — onda 3 (`ParcelasDrawer` · CU-SELL-09)

> ⚠️ **Território Tier 0 — VALOR.** Diferente da onda 2 (que só derivava peso), esta onda
> divide **dinheiro**. A REGRA MESTRE de [`proibicoes.md`](../../../../memory/proibicoes.md)
> exige prova por **dois caminhos independentes**, e ela existe:
> [`tests/js/parcelas-dominio.test.ts`](../../../../tests/js/parcelas-dominio.test.ts) —
> **15/15**, comparando a função real contra aritmética à mão em centavos inteiros para
> **11 totais × 48 quantidades**. O que ainda protege: a tela **não grava** (UC-V302).
>
> **Por que estes 8 nascem 🧪 e não ✅.** O teste existe, cita o UC no título e passa — mas
> `✅` não é "o teste passou", é "**o manifesto do G-7 provou**". O manifesto
> (`scripts/casos-test-results.json`) é alimentado pelo JUnit das lanes e aterrissado pelo
> `casos-results-publish.yml`; até o primeiro run em `main` da lane
> [`sells-v3-dominio-gate.yml`](../../../../.github/workflows/sells-v3-dominio-gate.yml)
> ser colhido, não há entrada — e `✅` sem entrada vira `status:unverified`, que **derruba o
> casos-gate (required)**. `🧪` é a não-afirmação honesta que o G-7 aceita sem prova.
>
> ⚠️ **A lane nasceu neste mesmo PR, e a razão é o achado.** Os 4 specs de domínio do preview
> passavam 70/70 na máquina de quem os escreveu e rodavam em **ZERO lanes** — medido
> 2026-08-11 com `rg --hidden` pelos 4 nomes no repo inteiro: nenhum `.github/workflows`.
> Era verde-por-não-execução (LC-13). Promover UC apoiado num teste que o CI nunca roda
> teria fabricado cobertura de contrato com o gate verde — exatamente o que o §5 proíbe.

## UC-V320 · Cada ação da linha do item abre o drawer numa aba própria

**Status:** 🧪 — 3 testes citam o UC e passam localmente (`tests/js/item-acoes-dominio.test.ts`); sem entrada no manifesto até a lane rodar em `main`.

- **Dado** uma linha de item com as ações da âncora — lupa "Detalhes do item" e "Impostos",
- **Quando** o operador escolhe uma delas,
- **Então** o drawer abre na aba **daquela** ação: a lupa em *Geral*, o Impostos direto em *Tributação*.

Derivado da âncora (`prototipo-ui/cowork/venda-v3/sells-create.jsx:63-65`), onde a aba viaja
no próprio `abrirItem(i, aba)` — não do `.tsx`. O defeito que isto guarda é concreto: a aba
inicial estava **fixa** no render, então os dois botões caíam em Tributação e o de detalhe
não tinha o que fazer de diferente.

O invariante é escrito como **propriedade** (ações distintas ⇒ abas distintas), não como
"detalhe devolve `geral`" — repetir o literal do mapa passaria verde mesmo se o mapa inteiro
apontasse pro mesmo lugar, que é a lápide §5 2026-06-05 sobre teste tautológico. Provado por
mordida: revertendo o mapa pro defeito original, 2 dos 3 testes ficam vermelhos.

## UC-V330 · O rateio não perde nem inventa centavo

**Status:** 🧪 — 4 testes citam o UC e passam localmente; sem entrada no manifesto até a lane rodar em `main`.

- **Dado** um total de venda e uma quantidade de parcelas de 1 a 48,
- **Quando** `ratear(total, n)` divide o valor,
- **Então** a soma das parcelas é **exatamente** o total, a conta roda em centavos inteiros e o resto vai para as **primeiras** parcelas.

Prova por **dois caminhos independentes**, como a REGRA MESTRE exige: caminho A é a função
real; caminho B parte da **definição** do invariante (soma de inteiros), escrito de forma
diferente, para não virar tautológico — o §5 tem lápide de 2026-06-05 sobre teste que deriva
do código. Cobre também os degenerados que viram `NaN` ou lista vazia se ninguém olhar:
`n = 0`, `n` negativo, `n` fracionado (trunca, não arredonda) e total zero.

## UC-V331 · `100,00` em 3 dá `33,34 · 33,33 · 33,33`

**Status:** 🧪 — 1 teste cita o UC e passa localmente; aguardando o manifesto.

- **Dado** o caso que dá nome ao problema,
- **Quando** se divide `100,00` em 3 parcelas,
- **Então** o resultado é `33,34 · 33,33 · 33,33` — e **não** `33,33 × 3 = 99,99`.

O teste não se contenta em afirmar o certo: ele **reproduz o erro clássico** (dividir em float
e arredondar cada parcela) e prova que aquele caminho somaria `99,99`. Sem o contraste, o caso
provaria só que a implementação faz o que faz. É 1 centavo que vira diferença de conciliação
que ninguém acha depois.

## UC-V332 · "Mesmo dia de cada mês" não é somar 30 dias

**Status:** 🧪 — 3 testes citam o UC e passam localmente; aguardando o manifesto.

- **Dado** um vencimento em **31/01**,
- **Quando** se gera a parcela do mês seguinte,
- **Então** ela cai em **28/02** (grampeada no último dia do mês curto), e não em **02/03**.

A escolha do caso é o que dá força ao teste: para 31/05 os dois métodos coincidem em 30/06 e
o caso **não distinguiria nada**. Fevereiro é onde a diferença aparece — e mês diferente é
**competência diferente**, com efeito em mês fechado no financeiro.

## UC-V333 · Quantidade de parcelas digitada é saneada, nunca `NaN`

**Status:** 🧪 — 2 testes citam o UC e passam localmente; aguardando o manifesto.

- **Dado** o campo de quantidade de parcelas, que é texto livre,
- **Quando** chega vazio, lixo (`abc`), `0` ou negativo,
- **Então** vale **1**; acima de 48 é grampeado no teto.

Entrada de texto que vira `NaN` num laço de geração de parcelas é laço infinito ou tela em
branco — o saneamento é o que impede o drawer de travar com o usuário digitando.

## UC-V334 · O plano só fecha quando as parcelas somam o total

**Status:** 🧪 — 1 teste cita o UC e passa localmente; aguardando o manifesto.

- **Dado** um conjunto de parcelas editadas à mão,
- **Quando** a soma delas bate com o total da venda,
- **Então** `fechaNoTotal` é verdadeiro — é o predicado que libera o **Confirmar parcelas**.

Plano de pagamento que não paga a venda não pode sair do drawer como se estivesse pronto.
⚠️ O teste prova o **predicado**, não a ligação dele ao atributo `disabled` do botão: a
fiação para a UI segue sem prova (ver backlog).

## UC-V335 · Quando falta ou sobra, a tela diz de que lado

**Status:** 🧪 — 1 teste cita o UC e passa localmente; aguardando o manifesto.

- **Dado** parcelas que não fecham no total,
- **Quando** se pergunta a diferença,
- **Então** o **sinal** distingue os dois casos: positivo é "falta distribuir", negativo é "passou do total".

Um valor absoluto diria só "não fecha" — e "falta um centavo" e "sobrou um centavo" pedem ações
opostas do operador. ⚠️ A ação de **jogar a diferença na última parcela** segue sem prova
(ver backlog).

## UC-V336 · Vencida é comparação por DIA, não por instante

**Status:** 🧪 — 2 testes citam o UC e passam localmente; aguardando o manifesto.

- **Dado** uma parcela que vence **hoje**,
- **Quando** se pergunta se está vencida às 23h59,
- **Então** **não** está — ontem está, amanhã não.

Comparar por instante marcaria como vencida toda parcela do próprio dia depois do primeiro
milissegundo, o que encheria a tela de vermelho falso e treinaria o operador a ignorar o sinal.

## UC-V337 · O parse pt-BR sobrevive ao separador de milhar

**Status:** 🧪 — 1 teste cita o UC e passa localmente; aguardando o manifesto.

- **Dado** o valor `1.115,40` digitado no padrão brasileiro,
- **Quando** ele é somado,
- **Então** vale **mil cento e quinze**, não um milhão.

É o guard do incidente `num_uf` (ROTA LIVRE `biz=4`, 2026-06-05): 16 vendas infladas ~×100k
porque um parser leu o ponto decimal como separador de milhar. A regra perene está no §5 —
separador de milhar tem **sempre** 3 dígitos.

---

## Detalhe do item — onda 4 (`ItemDetalhe` · tributação)

> ⚠️ **Erro fiscal sai desta tela direto para a NF-e.** Rejeição da SEFAZ não é detalhe de UI:
> é a venda parada e retrabalho de quem emite. Provado em
> [`tests/js/item-fiscal-dominio.test.ts`](../../../../tests/js/item-fiscal-dominio.test.ts) — **18/18**.
>
> Todos nascem **🧪** pelo mesmo motivo da onda 3: `✅` exige entrada no manifesto do G-7, e
> ela só chega quando a lane rodar em `main`.

## UC-V340 · NCM exige 8 dígitos e a mensagem diz **quantos faltam**

**Status:** 🧪 — 2 testes citam o UC e passam localmente; aguardando o manifesto.

- **Dado** o campo NCM,
- **Quando** ele chega vazio ou incompleto,
- **Então** a mensagem diz **quantos dígitos faltam** — não apenas "inválido"; com máscara ou sem, 8 dígitos passam.

"Inválido" obriga o operador a contar dígitos na mão. Verificado em produção: NCM `3919`
acusa "faltam 4".

## UC-V341 · CFOP tem 4 dígitos e o **primeiro carrega significado**

**Status:** 🧪 — 3 testes citam o UC e passam localmente; aguardando o manifesto.

- **Dado** o campo CFOP,
- **Quando** o primeiro dígito é 1/2/3 (entradas) ou 5/6/7 (saídas),
- **Então** passa; **4, 8 e 9 não existem** como primeiro dígito e são recusados, assim como comprimento diferente de 4.

Validar só o comprimento aceitaria `9999`, que não existe na tabela — o primeiro dígito é
domínio, não formato.

## UC-V342 · CEST, GTIN e cBenef são opcionais: vazio passa, preenchido é conferido

**Status:** 🧪 — 3 testes citam o UC e passam localmente; aguardando o manifesto.

- **Dado** os campos opcionais,
- **Quando** ficam vazios,
- **Então** não acusam; **quando preenchidos**, valem CEST 7 dígitos, GTIN em 8/12/13/14 (os quatro padrões reais de código de barras) e cBenef como 2 letras de UF + 6 dígitos.

Campo opcional que acusa vazio treina o operador a ignorar erro; campo opcional que aceita
qualquer coisa quando preenchido não é validação.

## UC-V343 · Alíquota e redução vivem na faixa 0..100

**Status:** 🧪 — 2 testes citam o UC e passam localmente; aguardando o manifesto.

- **Dado** alíquota ou redução de base,
- **Quando** o valor é negativo ou acima de 100,
- **Então** é recusado — e o pt-BR (vírgula decimal) é aceito na faixa válida.

## UC-V344 · Coerência CST × alíquota — o erro que a validação campo-a-campo **não** pega

**Status:** 🧪 — 4 testes citam o UC e passam localmente; aguardando o manifesto.

- **Dado** um CST que declara **não haver imposto** (40 isenta, 41 não tributada, 60 ST cobrado anteriormente, 04),
- **Quando** vem acompanhado de alíquota **maior que zero**,
- **Então** o **par** é rejeitado — e no sentido oposto, **CST 00** (tributada integralmente) **com alíquota zero** também é contradição.

**É o caso mais forte desta onda, e o teste prova por quê:** existe um caso dedicado
mostrando que a validação de **formato** sozinha **aprovaria** o par incoerente — CST 40 é um
CST válido, 18% é uma alíquota válida, e cada campo passa isolado. Só o cruzamento pega. Sem
ele, a rejeição só apareceria na SEFAZ, com a venda já emitida. Há também o controle
positivo: os mesmos CSTs **passam** quando a alíquota é zero, então a regra não é "CST 40
sempre reprova".

## UC-V345 · CST 102 (Simples) tem **três** dígitos e não pode ser lido como "10"

**Status:** 🧪 — 1 teste cita o UC e passa localmente; aguardando o manifesto.

- **Dado** o CST `102` do Simples Nacional,
- **Quando** a coerência é avaliada,
- **Então** ele **não** entra em nenhuma das duas regras de UC-V344 — ler os dois primeiros dígitos o confundiria com um CST de 2 dígitos.

Truncar `102` em `10` é o tipo de bug que só aparece no cliente do Simples — que é a maioria
da base.

## UC-V346 · A aba mostra **todos** os erros de uma vez

**Status:** 🧪 — 3 testes citam o UC e passam localmente; aguardando o manifesto.

- **Dado** um item com mais de uma pendência fiscal,
- **Quando** os erros são apurados,
- **Então** vêm **todos de uma vez** — não um por vez a cada tentativa de salvar; item correto não acusa nada, e a incoerência de UC-V344 é pega **mesmo com todo o resto correto**.

Erro-a-erro transforma o preenchimento em tentativa e erro. ⚠️ O teste prova a **lista**; a
contagem no rótulo da aba e o `disabled` do **Confirmar item** são fiação de UI e seguem sem
prova (ver backlog).

---

## Comissão — onda 5 (`ComissaoDrawer`)

> ⚠️ **Tier 0 — comissão é dinheiro devido a alguém.** Provado em
> [`tests/js/comissao-dominio.test.ts`](../../../../tests/js/comissao-dominio.test.ts) — **16/16**.
>
> **Por que isto não é um campo "Comissionista":** quem **vendeu**, quem **trouxe** o cliente
> e quem **executou** raramente são a mesma pessoa, e cada um tem regra própria. Um select
> único não expressa isso — e o resultado é comissão calculada em planilha, fora do sistema.

## UC-V350 · Uma venda aceita **vários** beneficiários, e o total é a soma

**Status:** 🧪 — 2 testes citam o UC e passam localmente; aguardando o manifesto.

- **Dado** funcionário, representante, agência e técnico na mesma venda,
- **Quando** cada um tem base, regra e valor próprios,
- **Então** o total é a **soma** deles; lista vazia é **zero**, nunca `NaN`.

⚠️ O teste prova o número; a **mensagem** "esta venda não gera comissão" (em vez de um zero
sem explicação) é UI e segue sem prova (ver backlog).

## UC-V351 · A base é escolhida por beneficiário, e a escolha é de **incentivo**

**Status:** 🧪 — 1 teste cita o UC e passa localmente; aguardando o manifesto.

- **Dado** o mesmo percentual de 3%,
- **Quando** incide sobre o **bruto** ou sobre a **margem**,
- **Então** dá números **diferentes** — e a diferença é a política: sobre o bruto a empresa paga o vendedor para dar desconto (o desconto sai do caixa dela, não da comissão dele); sobre a margem, alinha.

Provado por **dois caminhos** (função real × aritmética à mão), como a REGRA MESTRE exige
para cálculo que vira dinheiro.

## UC-V352 · Regra **fixa** ignora a base

**Status:** 🧪 — 1 teste cita o UC e passa localmente; aguardando o manifesto.

- **Dado** um beneficiário com regra de valor fixo,
- **Quando** a base muda entre bruto e margem,
- **Então** o valor é **o mesmo** — fixo é fixo.

## UC-V353 · A faixa é por valor **TOTAL**, não progressiva por fatia

**Status:** 🧪 — 2 testes citam o UC e passam localmente; aguardando o manifesto.

- **Dado** uma tabela de faixas,
- **Quando** a venda cai na faixa de 4%,
- **Então** recebe **4% sobre tudo** — e **não** 2% até 5k + 3% até 20k + 4% no resto.

Confundir os dois muda o que a empresa paga. É o mesmo mal-entendido que existe entre
alíquota progressiva de imposto e faixa comercial — aqui a regra é comercial.

## UC-V354 · O gatilho por **recebimento** libera proporcional ao que o cliente pagou

**Status:** 🧪 — 4 testes citam o UC e passam localmente; aguardando o manifesto.

- **Dado** uma venda com comissão por recebimento,
- **Quando** o cliente pagou parte,
- **Então** libera **proporcional ao pago**: nada recebido libera zero **mesmo com a venda emitida**, tudo recebido libera tudo; em **emissão** e **faturamento** o direito nasce **inteiro** no evento.

É o que impede pagar comissão de venda inadimplente — o caso "emitida mas não paga" tem
teste próprio justamente porque é onde o dinheiro escapa.

## UC-V355 · Estorno é **proporcional** ao devolvido

**Status:** 🧪 — 3 testes citam o UC e passam localmente; aguardando o manifesto.

- **Dado** uma devolução,
- **Quando** metade é devolvida,
- **Então** estorna metade; devolução total estorna tudo, nada devolvido estorna zero, e devolvido **acima** do total não estorna mais que a comissão.

Sem isso a devolução vira **prejuízo dobrado**: a empresa devolve ao cliente e mantém a
comissão paga. O teto no estorno impede o espelho do bug — cobrar do vendedor mais do que ele
recebeu.

## UC-V356 · A tela avisa quando a comissão come a margem

**Status:** 🧪 — 3 testes citam o UC e passam localmente; aguardando o manifesto.

- **Dado** a margem da venda,
- **Quando** a comissão passa de **metade** dela,
- **Então** é alerta — e margem **zero ou negativa** com qualquer comissão já é alerta.

É o número que transforma "vendi" em "vendi com prejuízo" antes de a venda fechar.

---

## Colunas do grid — onda 6 (`ColunasModal`)

> A preferência mora no `localStorage`, que é **entrada não confiável**: o usuário pode
> editar, uma versão futura pode remover uma coluna, um JSON pode truncar. Uma tela de venda
> que quebra porque o storage tem lixo é pior que uma tela sem preferência salva. Provado em
> [`tests/js/colunas-dominio.test.ts`](../../../../tests/js/colunas-dominio.test.ts) — **21/21**.

## UC-V360 · Coluna **fixa** não desliga e não sai do lugar

**Status:** 🧪 — 3 testes citam o UC e passam localmente; aguardando o manifesto.

- **Dado** as colunas fixas (produto, quantidade, valor, total),
- **Quando** se tenta desligá-las ou movê-las,
- **Então** nada acontece — e coluna comum também não é solta em cima de posição de fixa.

A linha não existe sem elas, e **um grid de venda sem total não é um grid de venda**.

## UC-V361 · `localStorage` podre nunca derruba a tela

**Status:** 🧪 — 4 testes citam o UC e passam localmente; aguardando o manifesto.

- **Dado** storage vazio, JSON inválido, storage **bloqueado** (modo anônimo) ou **cota cheia**,
- **Quando** a preferência é carregada ou salva,
- **Então** **nada lança**: cai no padrão e segue.

Modo anônimo e cota cheia lançam de verdade no browser — não são hipóteses. Preferência de
coluna não pode derrubar uma venda em andamento.

## UC-V362 · Chave de coluna que **não existe mais** é descartada

**Status:** 🧪 — 1 teste cita o UC e passa localmente; aguardando o manifesto.

- **Dado** uma preferência salva numa versão anterior,
- **Quando** uma coluna foi removida no meio-tempo,
- **Então** a chave órfã é descartada **sem quebrar o resto** da preferência.

É o custo de manter preferência versionada em cliente: o schema muda, o dado salvo não.

## UC-V363 · Duplicata e lixo não viram grid quebrado

**Status:** 🧪 — 3 testes citam o UC e passam localmente; aguardando o manifesto.

- **Dado** uma preferência com chave **repetida**, com um valor que não é lista, ou só com lixo,
- **Quando** é saneada,
- **Então** a repetida mantém **só a primeira**, e não-lista ou lixo total caem no **padrão** — nunca em grid **vazio**.

Cair em lista vazia seria pior que ignorar a preferência: a tela abriria sem coluna nenhuma.

## UC-V364 · Coluna fixa **ausente** da preferência é reinserida

**Status:** 🧪 — 1 teste cita o UC e passa localmente; aguardando o manifesto.

- **Dado** uma preferência salva sem uma das fixas,
- **Quando** é carregada,
- **Então** a fixa é **reinserida**.

É o par de UC-V360 pelo outro lado: não basta impedir desligar pela UI se um storage editado
à mão consegue o mesmo resultado.

## UC-V365 · O catálogo é íntegro e o padrão é utilizável

**Status:** 🧪 — 3 testes citam o UC e passam localmente; aguardando o manifesto.

- **Dado** o catálogo de colunas,
- **Quando** é inspecionado,
- **Então** as chaves são **únicas**, o padrão **inclui todas as fixas** e toda coluna pertence a um **grupo conhecido**.

Duas colunas com a mesma chave se sobrescreveriam silenciosamente — este UC é a defesa contra
o catálogo crescer torto, e é o único que falha no dia em que alguém adicionar uma coluna
duplicada.

## UC-V366 · Operação válida funciona; fora-do-catálogo é ignorado

**Status:** 🧪 — 6 testes citam o UC e passam localmente; aguardando o manifesto.

- **Dado** a preferência de colunas,
- **Quando** se move uma coluna comum, liga uma que estava fora ou desliga uma comum,
- **Então** funciona; **índice fora da lista** e **chave desconhecida** são **ignorados**, em vez de corromper a ordem.

É o controle positivo do resto da onda: sem estes casos, "nada quebra" seria satisfeito por
uma implementação que simplesmente **não faz nada**.

---

## Backlog de contrato

- **[BACKLOG]** A rota `/sells/create-v3` responde **403** a usuário autenticado sem `sell.create` e sem `superadmin` — mesma alçada da tela de venda real, o preview não afrouxa permissão.
- **[BACKLOG]** A rota responde **302** (login) a usuário não autenticado.
- **[BACKLOG]** A resposta Inertia renderiza o componente `Sells/CreateV3` e traz a prop `cena` com as chaves `cliente`, `itens`, `catalogo`, `tabelas`, `fsm` e `papeisDoUsuario`.
- **[BACKLOG]** A tela renderiza a **faixa de preview** — quem abre por engano precisa saber em 1 segundo que não é produção.
- **[BACKLOG]** O finalizador exibe a **ação nomeada do estágio atual** (não um select de estágio), e fica **desabilitado** quando o papel exigido falta — mostrando **qual** papel falta.
- **[BACKLOG]** A cena omite `sell.approve` de `papeisDoUsuario` de propósito: o preview precisa exercitar o caminho negado, não só o feliz.
- **[BACKLOG]** Os efeitos colaterais de uma transição (`ReservarEstoque`, `ConsumirEstoque`…) são exibidos **antes** de executá-la.
- **[BACKLOG]** `parseBR('204.99605')` devolve `204.99605` e **não** `20499605` — o guard do incidente `num_uf` (separador de milhar tem sempre 3 dígitos), e `submitSafe` arredonda a 2 casas antes de qualquer submit.

### Lançamento do item (onda 1 — modal `LancarItem`)

- **[BACKLOG]** A **unidade do cadastro** decide se a quantidade é derivada ou digitada — não um checkbox: `m²` pede peças+altura+largura, `m³` acrescenta espessura, `m` usa só a largura, e qualquer outra unidade pede a quantidade direto.
- **[BACKLOG]** Quantidade faturada de item dimensional é `peças × área da peça`, **nunca digitada** — o campo não existe nesse modo.
- **[BACKLOG]** Desconto e acréscimo incidem sobre o **preço unitário**, nunca sobre o total — aplicar sobre o total daria outro número quando a quantidade é fracionada, que é o caso normal em item dimensional.
- **[BACKLOG]** Preço abaixo de **85% da tabela** sai da alçada do vendedor e é sinalizado; no empate exato o erro é pedir liberação a mais, nunca deixar passar preço baixo demais.
- **[BACKLOG]** Peças negativas contam como **0** — quantidade negativa viraria total negativo e, no dia em que isto gravar, estoque somando em vez de baixar.
- **[BACKLOG]** Quantidade acima do estoque **não bloqueia** o lançamento; avisa que vai gerar saldo negativo.

### Consulta de clientes (extra — `ConsultaCliente`)

> ⚠️ **O que esta onda garante sobre VALOR, e como cada metade foi estabelecida.**
> Trocar de cliente **não reprecifica a venda**. Isso vale por duas razões, e elas têm
> forças diferentes — declarar as duas como "provado" seria inflar:
> **(a) MEDIDO** (`grep` em `CreateV3.tsx`, 2026-08-11): `tabelaCadastro`/`tabelaAtiva`/
> `tabelaTrocada` aparecem só no cartão "Tabela de preço", nunca em `linhaTotal`,
> `subtotal` ou `total`; o preço unitário mora em `itens`, que a seleção não toca.
> **(b) PROVADO** em [`tests/js/cliente-consulta-dominio.test.ts`](../../../../tests/js/cliente-consulta-dominio.test.ts) — **23/23**,
> com mutação confirmando que os guards mordem: cliente novo nasce com `tabela: null`,
> e `ClienteConsulta` não carrega campo de preço nenhum (a ausência é a defesa).
> É deliberadamente o caminho oposto ao da lápide de 2026-07-15, em que o
> `CustomerSearchAutocomplete` **entrega** `selling_price_group_id` no `onSelect` e o parent
> `Sells/Create.tsx:500` reaplica via `handlePriceGroupChange`. A distinção importa: quem
> reprecifica é o **consumidor**, não o componente — medido em 2026-08-12, quando a leitura
> abreviada ("o componente reaplica") fabricou um Tier-0 fantasma no import do `Repair`,
> que consome o mesmo componente e **ignora** esse campo.

- **[BACKLOG]** A consulta abre em modal de **880px** e lista os cadastros do business atual com código, nome/razão social, CNPJ/CPF, situação de ICMS, cidade/UF e grupo de preço.
- **[BACKLOG]** Clicar na linha **traz o cadastro inteiro** (documento, IE, regime, endereço, grupo, prazo) e abre os **detalhes do destinatário** — quem troca de cliente precisa conferir o que veio junto, não descobrir na NF-e.
- **[BACKLOG]** A troca de cliente entra no **desfazer**, como qualquer outra ação destrutiva da tela.
- **[BACKLOG]** Situação de ICMS aparece em **três** estados visualmente distintos (contribuinte · isento · não contribuinte) — isento exibido como contribuinte é erro fiscal, não estético.
- **[BACKLOG]** A linha do cliente **já selecionado** é destacada na consulta; a seleção marca, não filtra.
- **[BACKLOG]** Busca sem resultado mostra **estado vazio nomeando o termo**, nunca uma tabela em branco.
- **[BACKLOG]** A linha inteira é clicável, mas quem recebe o clique é um `<button>` real — alcançável por teclado (`a11y:check`).
- **[BACKLOG]** **Cadastro mínimo** (só o nome é obrigatório) cria o cliente **em memória** e o devolve já selecionado; o que não foi perguntado entra como `—`, nunca inventado — e o cliente novo **não nasce com tabela de preço**, então cai no padrão do balcão.
- **[BACKLOG]** O código do cliente novo continua a sequência **preservando a largura** (`0288` → `0289`) e não colide com código existente.

> **Duas divergências CONSCIENTES do protótipo, e as duas fazem a busca cumprir a
> própria copy.** A fonte compara `(cod + nome + doc + cidade).toLowerCase().includes(termo)`
> literal, então o placeholder promete *"Buscar por nome, CNPJ/CPF, cidade ou código…"*
> e mesmo assim **não acha** `29417508` (o operador digita o documento sem máscara, que é
> como ele vem do papel) nem `itajai` (sem acento). Aqui o documento casa por **dígito** e
> o texto casa **sem acento**. O que **não** muda: continua `includes` e não fuzzy, termo
> vazio devolve a lista inteira, e a ordem é preservada. Há controle negativo provando que
> o eixo numérico não vaza — sem ele, `soDigitos('atacado')` daria `''` e `''.includes('')`
> casaria toda linha, devolvendo a lista inteira para qualquer termo textual.

> **Os dados de cena divergem do protótipo, e o motivo é Tier 0.** O CNPJ que a fonte usa
> no cliente Governo foi **medido VÁLIDO** — é documento real, e a allowlist do `pii-scan`
> é explícita que *"CPF/CNPJ real JAMAIS entra"*. Ele não entrou (nem em comentário, para
> não deixar o número identificável em prosa), o nome do órgão
> não é o de uma prefeitura existente, e "Rota Livre" saiu da cena por ser o cliente-piloto de
> verdade (biz=4), não personagem. Os documentos que ficaram têm DV **medido inválido**, com
> controle positivo e negativo no validador.

---

### Colunas do grid (onda 6) — o que a promoção NÃO cobriu

> Os 7 casos desta onda viraram **UC-V360..UC-V366** acima. Sobrou o item de **acessibilidade**,
> que é decisão de técnica de UI e não de lógica — o teste de domínio prova que `mover`
> reordena, nunca **como** o usuário dispara o movimento.

- **[BACKLOG]** Reordenar é por **botão** (‹ ›), não por arrastar: drag-and-drop sem alternativa de teclado é inacessível. A fonte descreve a **intenção** ("arrastar e ordenar"), não a técnica.

---

### Comissão (onda 5) — o que a promoção NÃO cobriu

> Os 7 casos de cálculo desta onda viraram **UC-V350..UC-V356** acima. Sobrou o item de
> **mensagem** — o teste de domínio prova o número, nunca o texto que a tela mostra.

- **[BACKLOG]** Sem beneficiário, a tela **diz** que a venda não gera comissão, em vez de mostrar zero sem explicação (UC-V350 prova que o total é zero, não a mensagem).

---

### Detalhe do item (onda 4) — o que a promoção NÃO cobriu

> As 7 regras de validação desta onda viraram **UC-V340..UC-V346** acima. Sobrou o que o
> teste de domínio **não** alcança: fiação de UI (`disabled`, contagem no rótulo) e um item
> de produção que não tem prova nenhuma.

- **[BACKLOG]** A aba **Tributação exibe a contagem** de erros no rótulo (UC-V346 prova a lista, não o rótulo).
- **[BACKLOG]** **Confirmar item** fica **desabilitado** enquanto houver pendência fiscal — salvar incoerência seria empurrar à SEFAZ uma rejeição que o sistema já sabia prever. (A lista de pendências é UC-V346; a fiação ao botão, não.)
- **[BACKLOG]** No fluxo de produção, **responsável é PESSOA e setor é ONDE** — a fonte registra que misturar os dois numa coluna só foi o defeito apontado na revisão do desenho.

> **O que esta onda NÃO faz:** não apura tributo. A tabela de impostos mostra base e
> alíquota (incluindo **IBS** e **CBS**, os da reforma), mas o valor por imposto é
> calculado no servidor na emissão. A tela confere **preenchimento**, não valor — e
> segue sem gravar. Upload de anexo também fica fora: preview não grava arquivo.

---

### Parcelas (onda 3) — o que a promoção NÃO cobriu

> Os 8 casos desta onda viraram **UC-V330..UC-V337** acima. Sobrou aqui só o que os testes de
> domínio genuinamente **não** provam: os dois itens abaixo são de **fiação com a UI**, e o
> teste é de lógica pura (sem render). Separá-los é o ponto — promover a metade não provada
> junto teria feito o UC afirmar mais do que a prova sustenta.

- **[BACKLOG]** O predicado de UC-V334 está de fato ligado ao atributo `disabled` do botão **Confirmar parcelas** (a lógica está provada; a fiação, não).
- **[BACKLOG]** A tela oferece **jogar a diferença na última parcela** quando falta ou sobra (UC-V335 prova o sinal da diferença, não a ação).

---

### Entrega e frete (onda 2 — `EntregaFrete` · CU-SELL-11)

> ⚠️ **Leia isto antes de mexer nesta onda.** Ela é a dívida **D-6** do
> [`SDD-tela-venda-v1.0.md`](../../../../memory/requisitos/Sells/SDD-tela-venda-v1.0.md):
> `CU-SELL-11` está `[must]` e 🟡 **parcial** porque a tentativa anterior — **PR #2104** —
> foi **revertida** (#2107) por regressão reportada por cliente ~30 min após o merge
> ([incidente](../../../../memory/sessions/2026-06-02-incidente-revert-pr2-sells-endereco.md)).
>
> As três causas-raiz suspeitas daquele incidente **não alcançam o preview**, e a razão é
> estrutural, não otimismo: (1) o `ContactController@getCustomers` quebrou porque passou a
> fazer `->with(['addresses'])` num endpoint **compartilhado com o Blade** — aqui a cena é
> estática e não há fetch; (2) o `shipping_address` não persistia — aqui **não há
> persistência**; (3) o cliente não achava o frete na tela viva — esta **não é** a tela viva.
> A lição registrada foi *"refazer só após smoke real"*, e um preview que não grava é
> exatamente onde esse ensaio cabe.

- **[BACKLOG]** `9 — Sem ocorrência de transporte` **apaga o grupo inteiro** de transporte: sem transportadora, sem veículo, sem volumes. É o `modFrete` da NF-e, não uma escolha de UI.
- **[BACKLOG]** Escolher a transportadora na consulta **traz placa, ANTT, UF e modalidade** do cadastro — e os campos seguem ajustáveis nesta venda.
- **[BACKLOG]** O peso bruto é **somado dos itens** (peso cadastrado × quantidade) e **item sem peso entra como zero** — somar zero é honesto; inventar massa, não.
- **[BACKLOG]** Ligar "informar peso manualmente" faz o digitado **ignorar** o somado; desligar volta a somar. O texto de ajuda diz qual dos dois está valendo.
- **[BACKLOG]** Peso **não é valor nem estoque**: mudar peso não move subtotal, imposto, frete nem total.
- **[BACKLOG]** O valor do frete continua entrando no total pela cadeia da Page (`vFrete`) — a onda 2 **não altera** a aritmética do fechamento.
- **[BACKLOG]** Sem "entregar em outro endereço", vale o **endereço do cadastro** do cliente, e a tela diz de onde veio.
- **[BACKLOG]** A consulta de transportadoras filtra por **código, razão social, CNPJ ou cidade**, e a linha inteira é alcançável por **teclado** (é `<button>`, não `<div onClick>`).

> **Divergência consciente do handoff, e é de arquitetura.** A fonte declara as
> transportadoras **dentro** do `sells-entrega.jsx`, contradizendo a própria regra do bundle
> (*"nenhuma lista de domínio nasce em arquivo de UI"* — README do `venda-v3`). Aqui a lista
> volta pro lugar certo: `SellsV3Controller`, como **dado de cena**. Isso não é preciosismo —
> é a distinção que separa esta onda do #2104, que quebrou justamente ao transformar uma
> lista de UI em consulta a banco num endpoint compartilhado.
>
> **Fora do escopo desta onda, de propósito:** o bloco *"Fiscal do pedido"* (natureza da
> operação, esquema de numeração, nº da fatura, imposto do pedido, informações complementares
> da NF-e) existe no `sells-entrega.jsx`, mas é **`CU-SELL-10`**, que o SDD já marca ✅.
> Portá-lo aqui misturaria dois CUs num PR só.

---

> **Duas divergências CONSCIENTES do handoff, e as duas são de arredondamento.**
> O handoff aplica `submitSafe` (2 casas — o guard de **dinheiro**) na área da peça
> e na quantidade faturada. Medido no harness: uma tira de `0,50 × 0,004 m` dá
> `0,002 m²`, que com 2 casas vira **zero** — quantidade zero, total zerado, e o
> botão "Adicionar à venda" desabilitado, isto é, **o item não entra na venda**.
> Medida não é dinheiro: a área não arredonda e a quantidade arredonda a 4 casas
> (`CASAS_DE_MEDIDA`). O guard do `num_uf` continua **intacto** onde é dele — preço
> unitário e total do item seguem em `submitSafe`.
> Provado por dois caminhos antes de aplicar (REGRA MESTRE valor/estoque): função
> real × aritmética à mão (19/19) e tabela antes→depois, com controle de que o caso
> normal não se move — `lona 5× 0,50×5,00m` dá `12,5000` nos dois.

> ⚠️ **Item retirado em 2026-08-10, e a razão importa mais que o item.**
> Havia aqui um `[BACKLOG]` dizendo *"nenhum número exibido é calculado no front
> nem no controller: os valores de `fechamento` são strings já formatadas"*. Ele
> descrevia o scaffold ([#5356](https://github.com/wagnerra23/oimpresso.com/pull/5356)),
> e o porte do handoff o tornou **falso**: a tela calcula, e a chave `fechamento`
> nem existe mais na cena. Contrato que afirma o contrário do código é instrução
> ativa pra regressão — então ele sai daqui e o conflito fica registrado **onde a
> decisão mora**: o Non-Goal *"não calcula"* do [`CreateV3.charter.md`](CreateV3.charter.md),
> que é declaração literal de [L] e **só [L] reescreve**.

---

## Fronteira que os testes desta tela devem preservar

Não é caso de uso desta tela, mas é o contrato que a existência dela serve — e o teste que o provar
pertence a `Sells/Create`, não aqui:

- `/pos/create` continua servido por `SellPosController@create` renderizando `Sells/Create`, **sem alteração de comportamento**, com esta tela existindo ao lado.

---

## Pendências declaradas

- Testes: **5 arquivos**, em **duas lanes**.
  - `tests/Feature/Sells/SellsCreateV3ContratoTest.php` (UC-V301/302/303), na allowlist da lane `Pest (Sells · MySQL)`. **Executado e verde** no [run 31203571574](https://github.com/wagnerra23/oimpresso.com/actions/runs/31203571574) (CI; a lane nunca roda local · [ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)), com veredito no manifesto do G-7.
  - `tests/js/{parcelas,item-fiscal,comissao,colunas}-dominio.test.ts` (UC-V330..V366 — 29 UCs, **70 testes**), na lane [`sells-v3-dominio-gate.yml`](../../../../.github/workflows/sells-v3-dominio-gate.yml). **Verde local (70/70); sem run de CI ainda** — a lane nasceu junto com esta promoção.
- ⚠️ **Os 29 UCs de domínio estão 🧪, não ✅, e isso é literal:** o veredito deles ainda **não** existe no manifesto do G-7. Ele chega quando a lane rodar em `main` e o [`casos-results-publish.yml`](../../../../.github/workflows/casos-results-publish.yml) (cron 07:30 BRT) colher o JUnit. Só **depois** disso alguém pode flipar para ✅ — e o flip é edição consciente deste arquivo, porque o publisher escreve o manifesto, nunca o `casos.md`. Declarar ✅ antes vira `status:unverified` e **derruba o casos-gate (required)**.
- ⚠️ **Antes desta promoção os 4 specs rodavam em ZERO lanes** (medido 2026-08-11: `rg --hidden` pelos 4 nomes no repo inteiro não devolve nenhum `.github/workflows`). Eram 70 testes verdes que o CI nunca executou — verde-por-não-execução (LC-13). A lane foi criada no mesmo PR justamente porque promover UC apoiado em teste que ninguém roda fabrica cobertura com o gate verde.
- O que os 32 UCs **não** cobrem: nada de auth, permissão, render, persistência ou fiação de UI (`disabled`, rótulo, mensagem). São contrato de **roteamento/fronteira** (V301..V303) e de **lógica de domínio pura** (V330..V366) — os itens de `[BACKLOG]` acima seguem sem prova, e os resíduos por onda dizem exatamente qual metade ficou de fora.
- Smoke real de tela: pendente. O RUNBOOK ([`RUNBOOK-create-v3.md`](../../../../memory/requisitos/Sells/RUNBOOK-create-v3.md) §F4) prevê smoke em staging; nada disso rodou.
- Tenant de teste é o fictício **98** ([ADR 0358](../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)) — `biz=4` é proibido em teste, fixture ou smoke.
