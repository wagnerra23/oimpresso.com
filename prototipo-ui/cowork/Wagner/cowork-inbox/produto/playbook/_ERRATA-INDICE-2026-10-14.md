---
sessao: "errata-indice"
titulo: "Produto — 07, 08, 10 e 11 com prova decidível; pedido da thread 12 (Grupos de preço em drawer)"
executor: "[CL]"
data: 2026-10-14
base: wagnerra23/oimpresso.com@main 6511ed7c34
---
# Errata do `00-INDICE.md` do Produto (pedido ao Cowork)

O Code não edita o `00-INDICE.md` no espelho. Este arquivo diz o que mudar e por quê; a correção
vale quando o Cowork a fizer e o próximo retorno a trouxer.

## 07 — absorvida: os 4 contratos estão no `main`

O índice marca a 07 como `bloqueio` ("absorvida"), e por isso o placar a conta como **bloqueada**
para sempre. O trabalho, porém, foi feito: cada contrato entrou no PR da Page dele, como o
`_saida-07.md` recomendou. Conferido em `6511ed7c34`:

```
$ git ls-tree --name-only origin/main governance/design/contracts/ | grep produto
governance/design/contracts/produto-atualizar-preco.contract.json
governance/design/contracts/produto-cadastros.contract.json
governance/design/contracts/produto-etiquetas.contract.json
governance/design/contracts/produto-importacao.contract.json
```

**Pedido:** na thread `07`, remover o campo `bloqueio` e trocar `provas` por:

```json
[
  {"tipo": "arquivo", "path": "governance/design/contracts/produto-cadastros.contract.json"},
  {"tipo": "arquivo", "path": "governance/design/contracts/produto-etiquetas.contract.json"},
  {"tipo": "arquivo", "path": "governance/design/contracts/produto-importacao.contract.json"},
  {"tipo": "arquivo", "path": "governance/design/contracts/produto-atualizar-preco.contract.json"}
]
```

O que prova que cada contrato bate com a Page é o check required `Contratos de tela`, não estas
provas; elas só registram que a thread terminou.

## 08 — o teste que a errata de 07/10 esperava já entrou

A `_ERRATA-INDICE-2026-10-07.md` deixou a 08 de fora porque o teste do menu ainda não estava no
`main`. Agora está: [#8812](https://github.com/wagnerra23/oimpresso.com/pull/8812) (código, 06/10) e
[#8899](https://github.com/wagnerra23/oimpresso.com/pull/8899) (`ProdutoMenuPermissaoContratoTest.php`,
que lê o `shell.menu` real, 07/10), ambos mergeados.

**Pedido:** na thread `08`, trocar a prova `execucao` por:

```json
[
  {"tipo": "arquivo", "path": "tests/Feature/Produto/ProdutoMenuPermissaoContratoTest.php"},
  {"tipo": "contem", "path": "app/Http/Middleware/AdminSidebarMenu.php", "padrao": "'?aba=' . $aba"}
]
```

O `contem` trava o deep-link de aba (`AdminSidebarMenu.php:261`); a prova de comportamento é o
teste. Aproveitar para apagar do §4 a linha "o arquivo do menu (`AdminSidebarMenu.php`) é
suposição" — o `_saida-08.md` fixou o arquivo.

## Placar medido

`node scripts/qa/placar.mjs --indice` em `6511ed7c34`, com o json atual e com as provas acima
aplicadas numa cópia de trabalho (restaurada depois, hash SHA-256 conferido; nada commitado no
índice):

```
ANTES
Produto: entregue 10 de 14 · próximo 0 · em curso 3 · pendente 0 · bloqueada 1
DEPOIS (só 07 e 08)
Produto: entregue 12 de 14 · próximo 0 · em curso 2 · pendente 0 · bloqueada 0
DEPOIS (07, 08, 10 e 11)
Produto: entregue 14 de 14 · próximo 0 · em curso 0 · pendente 0 · bloqueada 0
```

## 10 e 11 — comparação medida em produção

Decisão [W] 2026-10-14: a comparação é medida com sessão logada em produção (biz=1). Feita no
mesmo dia: recibo em `_COMPARACAO-10-11-2026-10-14.md` (5 abas, 0 DIVERGE(bug), uma divergência
de forma no rótulo do campo, fora do prefixo e levada ao [W]).

A prova `comparacao` não é decidível pelo placar (o avaliador de recibo saiu com a ADR 0397).
**Pedido:** nas threads `10` e `11`, trocar `provas` por:

```json
[
  {"tipo": "arquivo", "path": "prototipo-ui/cowork/Wagner/cowork-inbox/produto/playbook/_COMPARACAO-10-11-2026-10-14.md"},
  {"tipo": "arquivo", "path": "resources/js/Pages/Produto/Cadastros/_components/CadastroDrawer.tsx"}
]
```

O comportamento continua provado pelos UC-PCADAP-17..22 na lane `PHP / Pest (Estoque · MySQL)`.

## Thread nova 12 — Grupos de preço em drawer

Decisão [W] 2026-10-14: a aba **Grupos de preço** de `Produto/Cadastros` ganha thread própria. Hoje
é a única das 6 abas que ainda abre o modal da Blade (`/selling-price-group`); as fichas 10 e 11
não a cobriam.

**Pedido:** criar a ficha `12-cadastros-form-c.md` e a thread `12` no json:

- depende de `11`;
- prefixo: `resources/js/Pages/Produto/Cadastros/`, `app/Http/Controllers/SellingPriceGroupController.php`,
  `app/Http/Controllers/UnitController.php`, `tests/Feature/Produto/`;
- nao_toca: `governance/design/contracts/produto-cadastros.contract.json`;
- fonte de copy e campos: `AbaGrupos` em `produto-cadastros.jsx` (:240 em diante) — o mesmo
  contêiner drawer PT-02 das outras 5 abas;
- mesmas rotas de gravação (`POST/PUT /selling-price-group`), com os nomes de campo do modal clássico;
- a ficha deve dizer que o drawer grava **só o cadastro do grupo** (nome, descrição): preço por
  grupo continua em Atualizar preço (thread 06). Se o form tocar qualquer valor, vale a regra mestre
  de valor (dupla prova + aprovação [W]).

Pendência que não é desta errata e segue com o Cowork (já pedida em 07/10): o protótipo ainda
desenha `FormModal` central; a Page usa o drawer PT-02 decidido pelo [W] em 2026-10-05.
