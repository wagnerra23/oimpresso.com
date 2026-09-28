---
sessao: "03"
titulo: "Aposentar cowork/Felipe/manufacturing-* — EXECUTADA"
autor: "[CL]"
data: "2026-09-28"
base: "origin/main f0b305111 + #8052"
prefixo_tocado: "prototipo-ui/cowork/Felipe/manufacturing-"
fora_do_prefixo: "este recibo · prototipo-ui/cowork/Felipe/oimpresso.com.html (7 tags passam a apontar ../Wagner/)"
---
# _saida-03 · Thread executada (tarde de 2026-09-28)

## 1 · Decisão

De manhã, a **D-RET-02** suspendeu a thread (registro abaixo, preservado). À tarde o Felipe propôs o
caminho inverso: a pasta do Wagner vira a fonte única e a cópia dele sai, **depois** de a versão dele
chegar ao projeto do Wagner. [W] aprovou no chat em 2026-09-28: *"sim, pode subir e apagar a pasta do
Felipe"* + opt-in *"sobe pro design-sync"*. Isso substitui a D-RET-02, e a D-MFG-FONTE ("Wagner,
fonte única") volta a valer.

## 2 · so no Felipe (o que se perde)

**Nada.** Antes de remover, a versão do Felipe (#7991) subiu ao projeto de telas do Wagner (DesignSync,
7 arquivos) e desceu ao espelho `cowork/Wagner/` pelo #8052 (`--export-from` + ledger). Medido com
`cmp`: as 7 peças `manufacturing-*` estavam **byte a byte idênticas** entre `cowork/Felipe/` e
`cowork/Wagner/` no momento da remoção. As mudanças do Wagner desde 2026-09-11 (D-RET-01, 4 KPIs,
Limpar, datas vazias) já estavam reescritas dentro da versão do Felipe.

## 3 · O que foi feito

- Removidas as 7: `manufacturing-{data,insumos,page,print,producao,recipe}.jsx` e `manufacturing-page.css`.
- A `cowork/Felipe/oimpresso.com.html` passa a carregar `../Wagner/manufacturing-*`, então a
  Fabricação continua abrindo no host do Felipe sem cópia. Medido no navegador (1280×720): as 7
  requisições respondem 200, `window.ManufacturingPage` existe e `.mfg-root` renderiza, tanto no host
  do Felipe quanto no do Wagner.
- Os charters já apontavam para `cowork/Wagner/` (thread 02) e ficaram como estavam.

## 4 · Verificação

- `node scripts/governance/cowork-ssot-guard.mjs` → exit 0.
- `cowork-mirror-freshness --check-refs` → nenhuma deleção no espelho do Wagner.
- `placar.mjs --thread 03` → as 7 provas `ausente` passam a valer, e a `guarda` (`cowork/Wagner/manufacturing-producao.jsx`) continua presente.

## 5 · Daqui para a frente

O Felipe trabalha no git. As mudanças dele na Fabricação são feitas **na pasta do Wagner via PR** e,
depois do merge, uma sessão com o login do Wagner sobe os arquivos ao projeto de telas e registra
a volta no ledger. Sem essa subida, o próximo import do Cowork desfaz a mudança.

---

# Histórico — registro da manhã (thread NÃO executada, D-RET-02)

## _saida-03 · Thread não executada por decisão [W]

### 1 · Decisão

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

### 2 · Estado do placar — esperado, não é pendência a consertar aqui

`placar.mjs --thread 03` segue **não-feito**, e vai continuar assim: as 7 provas `ausente` do índice
exigem que os arquivos sumam, e eles ficam por decisão. Quem resolve é o **índice**, que é do Cowork
e não se edita no espelho: a thread 03 sai do `00-INDICE.md` (ou ganha `bloqueio` com a D-RET-02), e
o próximo import traz isso.

### 3 · Para o [CC] — a D-MFG-FONTE do índice ficou desatualizada

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

### 4 · Verificação

- Nenhum arquivo removido. `git ls-tree origin/main -- prototipo-ui/cowork/Felipe/` segue com as 7
  peças `manufacturing-*` (medido em `a699f69d6`).
- O `cowork-ssot-guard` que a thread pedia só faz sentido depois de uma remoção; não se aplica.
