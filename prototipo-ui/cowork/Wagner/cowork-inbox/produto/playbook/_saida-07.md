---
sessao: "07"
titulo: "Contratos das 4 telas novas de Produto — saída da thread"
autor: "[CL]"
criado: 2026-10-01
base: c70451a8c
thread: 04-contratos.md
veredito: "NÃO entregue — bloqueio medido: o gate required recusa contrato cuja Page ainda não existe. Os 4 contratos estão derivados e conferidos (abaixo), prontos para entrar junto com a Page de cada thread."
---

# _saída 07 · Contratos das 4 telas novas

## Estado: bloqueada pelo gate, não por falta de fonte

Os 4 `governance/design/contracts/produto-*.contract.json` **não foram commitados**. Se entrassem agora, o check required `Contratos de tela (fidelidade + intenção)` ficaria vermelho neste PR e em todo PR seguinte que tocar `resources/js/Pages/**`.

Medido em `c70451a8c` (`origin/main`, já com a A2 #8341):

```
$ node scripts/contrato-de-tela.mjs --contract <rascunho>/produto-cadastros.contract.json
X nenhum .tsx/.ts no alvo do contrato (resources/js/Pages/Produto/Cadastros)
❌ 1 falha(s).   rc=1
$ node scripts/contrato-de-tela.mjs --contract <rascunho>/produto-atualizar-preco.contract.json
X nenhum .tsx/.ts no alvo do contrato (resources/js/Pages/Produto/AtualizarPreco)
❌ 1 falha(s).   rc=1
```

A causa está em `scripts/contrato-de-tela.mjs`, `checkContract()`: alvo sem `.tsx` devolve `1`. O step do workflow roda **todos** os `*.contract.json` ativos, e o `--map --check` ainda cobra âncora `data-contract` por seção. O `<!-- design-deviation -->` não serve de saída, porque ele também é lido do alvo. Não existe hoje um estado "contrato antes da Page". Mudar o gate está fora do `prefixo` desta thread.

`main` antes do PR: `node scripts/contrato-de-tela.mjs --map --check` rc 0. Este PR não muda isso.

## Decisão pendente (para o [CC] / [W]; não editei o índice)

A ficha 04 diz "Contratos (antes das Pages)". O gate não aceita essa ordem. Há duas saídas:

1. **Mover cada contrato para o PR da Page dele.** Cada thread de tela (02 Cadastros · 04 Etiquetas · 05 Importação · 06 Atualizar preço) ganha o seu `${CONTRATOS}/produto-<tela>.contract.json` no `prefixo` e commita o rascunho abaixo **sem mudar a copy**, instrumentando a Page com os `data-contract`. A thread 07 vira `bloqueio` ou sai do índice, e as threads 02/04/05/06 perdem a dependência `07`. Não precisa mudar código, e o contrato continua vindo do protótipo, porque o rascunho já está fixado aqui antes de existir `.tsx`.
2. **Dar ao gate um estado "planejado"**, em que contrato sem alvo fica advisory até a Page nascer. Isso muda um gate required e é decisão [W] com PR próprio.

Recomendação: a 1. É a única que não reabre o gate e não deixa nenhum contrato verde sem tela.

Enquanto isso, o placar vai mostrar a 07 como `em curso` (tem `_saida`, faltam as 4 provas `arquivo`). Esse estado está certo: a thread passou por aqui e parou por um motivo que o índice ainda não registra.

## Os 4 contratos, derivados do protótipo e nunca do `.tsx`

Fonte das seções: os alvos medidos da A2 (`governance/design/targets/produto--<tela>--index.secoes.json`), uma seção do contrato por seção medida. Fonte da copy: o componente do protótipo (`produto-cadastros.jsx:484-516` · `produto-acoes.jsx`: `TelaImportar`/`ImportarProdutos` :71-181, `AtualizarPreco` :193-240, `Etiquetas` :259+).

Conferência: as **42 de 42** strings de copy casam na `fonte` declarada, pela mesma regra de fronteira de identificador do `copyPresente` do gate. Controle negativo (string que não está na fonte) dá ausente. Nenhuma string foi lida de `.tsx`: as Pages ainda não existem.

Critérios aplicados:
- **`header` sem copy.** O título da tela vem de `TITULOS` em `produto-blade.jsx:994`, fora da `fonte` de cada contrato. Declarar o título ali deixaria a copy sem âncora na fonte. A seção fica como âncora de estrutura.
- **O aviso A-P1 ficou fora.** É o `SemGate`/Alert "sem can() no legado" (Cadastros `aviso`, e a 1ª linha de Etiquetas/Importação/Atualizar preço). Ele anota um defeito do legado que a thread 01 corrige; a Page de produção não deve carregá-lo. Por isso a seção `aviso` da A2 não tem seção no contrato de Cadastros.
- **`abas` de Cadastros usa `produto-cadastros-abas`**, o único `data-contract` que o protótipo já declara (`produto-cadastros.jsx:505`). As outras seções seguem o mesmo prefixo.
- **Cadastros cobre só a aba default (Variações)**, a mesma que a A2 mediu. Barra, ajuda e colunas das outras 5 abas exigem clique e não foram medidas.
- **Importação = só `prod-importar`** (Importar produtos), como a A2 (errata A2 #2). "Importar estoque inicial" não tem alvo nem contrato.
- **Atualizar preço:** só o contrato. A Page mexe em preço (regra mestre de valor), e isso é da thread 06, com [W].

```json
{"tela":"Produto/Cadastros","fonte":"prototipo-ui/cowork/Wagner/produto-cadastros.jsx","alvo":["resources/js/Pages/Produto/Cadastros"],"alvo_medido":"governance/design/targets/produto--cadastros--index.alvo.json",
 "secoes":[{"id":"produto-cadastros-header","copy":[]},
  {"id":"produto-cadastros-widget","copy":["Cadastros de apoio"]},
  {"id":"produto-cadastros-abas","copy":["Variações","Grupos de preço","Unidades","Categorias","Marcas","Garantias"]},
  {"id":"produto-cadastros-barra","copy":["Buscar variação ou valor…","Nova variação"]},
  {"id":"produto-cadastros-ajuda","copy":["Modelo reaproveitado no cadastro de produto variável"]},
  {"id":"produto-cadastros-tabela","copy":["Variação","Valores","Em uso","Ações"]}],
 "ordem":["produto-cadastros-header","produto-cadastros-widget","produto-cadastros-abas","produto-cadastros-barra","produto-cadastros-ajuda","produto-cadastros-tabela"]}
```

```json
{"tela":"Produto/Etiquetas","fonte":"prototipo-ui/cowork/Wagner/produto-acoes.jsx","alvo":["resources/js/Pages/Produto/Etiquetas"],"alvo_medido":"governance/design/targets/produto--etiquetas--index.alvo.json",
 "secoes":[{"id":"produto-etiquetas-header","copy":[]},
  {"id":"produto-etiquetas-produtos","copy":["Produtos para etiquetar","Buscar produto","Digite o nome do produto para imprimir etiquetas","Nenhum produto na folha"]},
  {"id":"produto-etiquetas-campos","copy":["Informações na etiqueta","Modelo de etiqueta","Preço a imprimir","Corpo (pt)"]},
  {"id":"produto-etiquetas-previa","copy":["Prévia da folha","Sem produtos na lista — nada a imprimir."]}],
 "ordem":["produto-etiquetas-header","produto-etiquetas-produtos","produto-etiquetas-campos","produto-etiquetas-previa"]}
```

```json
{"tela":"Produto/Importacao","fonte":"prototipo-ui/cowork/Wagner/produto-acoes.jsx","alvo":["resources/js/Pages/Produto/Importacao"],"alvo_medido":"governance/design/targets/produto--importacao--index.alvo.json",
 "secoes":[{"id":"produto-importacao-header","copy":[]},
  {"id":"produto-importacao-enviar","copy":["Importar produtos","A planilha grava direto no catálogo","Arquivo para importar *","Enviar planilha","Baixar modelo"]},
  {"id":"produto-importacao-instrucoes","copy":["Instruções","Nº","Coluna","O que colocar"]}],
 "ordem":["produto-importacao-header","produto-importacao-enviar","produto-importacao-instrucoes"]}
```

```json
{"tela":"Produto/AtualizarPreco","fonte":"prototipo-ui/cowork/Wagner/produto-acoes.jsx","alvo":["resources/js/Pages/Produto/AtualizarPreco"],"alvo_medido":"governance/design/targets/produto--atualizar-preco--index.alvo.json",
 "secoes":[{"id":"produto-atualizar-preco-header","copy":[]},
  {"id":"produto-atualizar-preco-planilha","copy":["Atualizar preço por planilha","Exporte, edite, devolva"]},
  {"id":"produto-atualizar-preco-passos","copy":["Passo 1 — exportar","Exportar preços atuais","Passo 2 — devolver","Aplicar preços"]},
  {"id":"produto-atualizar-preco-instrucoes","copy":["Instruções","Gerenciar grupos","Editar preço na tela"]}],
 "ordem":["produto-atualizar-preco-header","produto-atualizar-preco-planilha","produto-atualizar-preco-passos","produto-atualizar-preco-instrucoes"]}
```

## Provas do índice

As 4 provas `arquivo` da 07 continuam **ausentes**, de propósito. Para conferir: `node scripts/qa/placar.mjs --indice prototipo-ui/cowork/Wagner/cowork-inbox/produto/playbook/00-INDICE.md --thread 07`.

## O que não foi tocado

Nada em `resources/js/Pages/` nem em `governance/design/contracts/`. Nenhum `.charter.md` (Lei IT2: sem `.tsx` irmão, não há charter). Nenhum outro arquivo do playbook. O upload ao Cowork fica com a sessão-mãe.
