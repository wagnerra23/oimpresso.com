---
sessao: "03"
titulo: "Aposentar cowork/Felipe/manufacturing-* — NÃO EXECUTADA (D-RET-02)"
autor: "[CL]"
data: "2026-09-28"
base: "origin/main a699f69d6"
prefixo_tocado: "nenhum"
fora_do_prefixo: "este recibo"
---
# _saida-03 · Thread não executada por decisão [W]

## 1 · Decisão

A thread **não foi executada**, e as 7 peças `prototipo-ui/cowork/Felipe/manufacturing-*` **ficam**.

- **D-RET-02**, no comentário da conta `wagnerra23` no PR #7991 (2026-09-28T10:51:49Z): *"com a versão
  do Felipe como principal, a pasta `cowork/Felipe/manufacturing-*` fica. A thread 03 (aposentar) não
  deve ser executada."* O PR foi aprovado pela mesma conta às 12:19:27Z e mergeado às 12:20:26Z.
- Confirmada por [W] no chat desta sessão (2026-09-28), ao escolher "não executar a 03" entre as
  duas opções apresentadas.
- O `_saida-felipe-retorno.md` (entrou com o #7991) já pedia isso: a pasta é a cópia de trabalho do
  Felipe, e apagá-la a encerra.

Mesmo sem a decisão, a regra da própria thread mandaria parar. Ela diz: *"se a lista não estiver
vazia, pare antes de remover e devolva ao [CC]"*. A lista do que existe só na cópia do Felipe não
está vazia: é a §2 do `_saida-felipe-retorno.md` (DS nas ondas A/B, reset do `.mfg-grid`, rodapé
cortado nas telas de edição, drawers, faixa de 15px das grades, alinhamento do cabeçalho, ficha e
via de produção no tema escuro).

## 2 · Estado do placar — esperado, não é pendência a consertar aqui

`placar.mjs --thread 03` segue **não-feito**, e vai continuar assim: as 7 provas `ausente` do índice
exigem que os arquivos sumam, e eles ficam por decisão. Quem resolve é o **índice**, que é do Cowork
e não se edita no espelho: a thread 03 sai do `00-INDICE.md` (ou ganha `bloqueio` com a D-RET-02), e
o próximo import traz isso.

## 3 · Para o [CC] — a D-MFG-FONTE do índice ficou desatualizada

O índice registra **D-MFG-FONTE = "Wagner, fonte única"** (2026-09-25). O comentário de 2026-09-28
diz o contrário: *"a versão do Felipe (este PR) é a principal da Fabricação"*. O que foi entregue em
cima da D-MFG-FONTE aponta para `cowork/Wagner/` e **não foi alterado por esta sessão**:

| artefato | aponta hoje |
|---|---|
| `Index.charter.md` · `Report.charter.md` · `Settings.charter.md` | `cowork/Wagner/manufacturing-producao.jsx` |
| `Recipes.charter.md` | `cowork/Wagner/manufacturing-page.jsx` |
| `Insumos.charter.md` | `cowork/Wagner/manufacturing-insumos.jsx` |
| `manufacturing-index.contract.json` (`fonte`, thread 01) | `cowork/Wagner/manufacturing-producao.jsx` |
| `manufacturing-recipes.contract.json` (`fonte`) | `cowork/Wagner/manufacturing-page.jsx` |

Trocar essas âncoras para `cowork/Felipe/` (ou não) é decisão [W]. Se trocar, é uma thread nova no
playbook: a mesma forma da 02, no sentido inverso.

## 4 · Verificação

- Nenhum arquivo removido. `git ls-tree origin/main -- prototipo-ui/cowork/Felipe/` segue com as 7
  peças `manufacturing-*` (medido em `a699f69d6`).
- O `cowork-ssot-guard` que a thread pedia só faz sentido depois de uma remoção; não se aplica.
