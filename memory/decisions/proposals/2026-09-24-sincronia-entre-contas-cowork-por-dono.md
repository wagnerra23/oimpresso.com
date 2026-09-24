---
title: "Sincronia entre as duas contas Cowork por DONO do arquivo — Fabricação (F) ↔ DS/governança (W)"
status: proposta
date: "2026-09-24"
owners: [W, F]
proposed_by: Claude Code (a pedido de [F])
parent_module: design-system
related_adrs: [315, 374, 405, 412]
related_specs:
  - scripts/design/protocolo.config.mjs (CONTAS + PROJETOS — as duas contas já registradas)
  - scripts/design-sync/pendentes-cowork.mjs (sentido Code → Cowork, hoje só da conta do [W])
  - .github/workflows/cowork-bundle.yml (pacote automático, hoje só de cowork/Wagner/**)
related_charters: []
---

# Sincronia entre as duas contas Cowork, decidida pelo DONO do arquivo

> **Status: `proposta`.** Não muda código. É a mesa de decisão para [W] e [F]. O código fica para
> um PR seguinte, depois das decisões da §4, e depois de a sessão do [W] que está mexendo em
> `pendentes-cowork.mjs` + `receber-handoff.mjs` (worktree `hopeful-cerf-da6f30`, ativa em
> 2026-09-24) mergear. Mexer nesses dois arquivos agora seria colisão (§5 2026-09-05).

## 1 · O pedido

[F], 2026-09-24, textual: *"Nós nunca vamos mexer na mesma tela ao mesmo tempo. Por exemplo, estou
mexendo em Fabricação. O Wagner está mexendo em alguns arquivos de governança sobre o DS do cowork.
Se tiver algum update lá, tenho que obtê-las aqui também. Eu mexer aqui na Fabricação, meus updates
precisam cair lá para ele."*

A regra é: **cada arquivo tem um dono, e o que o dono muda chega na conta do outro.** O sentido da
cópia é decidido pelo dono, nunca pela data. Como ninguém edita a mesma tela ao mesmo tempo, não há
conflito a resolver, só propagação.

## 2 · O que existe hoje (medido em `origin/main` 1d68b53a, 2026-09-24)

| peça | estado |
|---|---|
| as duas contas | registradas: `PROJETOS.cowork` (conta `w`, espelho `cowork/Wagner/`) e `PROJETOS.telasFelipe` (conta `felipe`, espelho `cowork/Felipe/`, ativado 2026-09-21) |
| importar retorno | `receber-handoff.mjs --zip … --conta` já roteia para o espelho da conta liberada |
| aplicar pacote | `bundle-transaction.mjs::pathsForOwner()` já aceita `Wagner` e `Felipe` |
| bundle ativo da conta do [F] | existe: `scripts/design-sync/state/Felipe/active-bundle.json` |
| pacote automático (CI) | **só Wagner**: `cowork-bundle.yml` → `paths: prototipo-ui/cowork/Wagner/**`, `--root prototipo-ui/cowork/Wagner` |
| medidor Code → Cowork | **só Wagner**: `pendentes-cowork.mjs` → `ESPELHO_REL = 'prototipo-ui/cowork/Wagner'`, `COWORK_PROJECT_ID` do [W], estado único em `state/enviados-cowork.json` |
| subir ao Cowork sem opt-in | ADR 0412 (**proposto**): só `cowork-inbox/**`, só no projeto do [W] (`RETORNO_PROJECT_ID` no hook). Tela `*.jsx`/CSS continua opt-in [W] (D2) |
| escrever no Cowork da conta do [F] | **impossível daqui**: o DesignSync autentica como [W]; a conta do [F] é invisível por construção (`protocolo.config.mjs`, CONTAS.felipe) |

A Fabricação nas duas pastas (blob, `git hash-object`):

| arquivo | `cowork/Wagner` | `cowork/Felipe` |
|---|---|---|
| `manufacturing-page.jsx` | 193ea1a | 8a3b73d |
| `manufacturing-recipe.jsx` | ccbf461 | 67d7875 |
| `manufacturing-producao.jsx` | 80614e0 | 867c396 |
| `manufacturing-insumos.jsx` | 91653aa | 2b636a9 |
| `manufacturing-print.jsx` | b8b635c | 9cccd45 |
| `manufacturing-data.jsx` | 453fa65 | 453fa65 (igual) |

Último commit dos dois lados: Wagner em 2026-09-11, Felipe em 2026-09-22. A pasta do [W] tem a
versão antiga, e o pacote `handoff_fabricacao/design/` (da conta do [F]) foi montado a partir dela.

## 3 · Os dois sentidos, e o que cada um precisa

### 3.1 — [W] → [F]: DS e governança

- **DS: já funciona, e não é por máquina de espelho.** [F] 2026-09-21: *"Puxo as atualizações direto
  do main do git"* — o DS da conta do [F] (`49a36f76-…`) é cópia que puxa de
  `prototipo-ui/design-system/` no `main`. Não há nada a construir.
- **Governança de design do [W]** (playbooks, `_DECISOES-W-*`, ADRs): mora em `memory/` e em
  `cowork/Wagner/cowork-inbox/`, no git. A conta do [F] lê do git, igual ao DS. **Pergunta aberta
  (§4 D1):** existe arquivo de governança que precisa aparecer DENTRO do projeto Cowork do [F], e
  não só no git? Se não, este sentido também está resolvido.

### 3.2 — [F] → [W]: a Fabricação

É o sentido que não tem caminho. Dois passos, e só o primeiro é do Code:

1. **Repo:** copiar os `manufacturing-*` de `cowork/Felipe/` para `cowork/Wagner/`, por PR,
   só os arquivos cujo dono é [F]. ⚠️ Isto **edita o espelho do [W] à mão**, que é o vetor do
   §5 2026-09-24 (o gate `espelho — mexeu depois de verificar` trava o `main` se o arquivo já
   verificado não subir ao Cowork logo depois). Então o passo 1 só é seguro **no mesmo ciclo** do
   passo 2.
2. **Cowork do [W]:** subir os `*.jsx`. Pela ADR 0412 D2, tela exige opt-in [W] a cada vez. Não há
   como isentar sem nova decisão.

## 4 · Decisões pedidas

| # | decisão | de quem | opções |
|---|---|---|---|
| D1 | a governança do [W] precisa estar dentro do Cowork do [F], ou basta o git? | [F] | (a) basta o git (nada a fazer) · (b) listar os arquivos |
| D2 | a Fabricação do [F] deve existir no projeto Cowork do [W]? | [W] | (a) sim, por propagação · (b) não: o [W] consulta a Fabricação na pasta `cowork/Felipe/` do repo e o espelho dele deixa de ter `manufacturing-*` |
| D3 | se D2 = (a): o upload de tela do dono [F] ao projeto do [W] continua opt-in por vez, ou ganha isenção como a do `cowork-inbox/`? | [W] | (a) opt-in por vez (ADR 0412 D2 intacta) · (b) emenda à 0412 isentando arquivo cujo dono declarado é a outra conta e cujo blob é o do `main` |

**Recomendação: D2 = (b).** É a única opção que não exige opt-in recorrente nem editar à mão o
espelho do [W]. Ela também acaba com a duplicata que fez o pacote `handoff_fabricacao` nascer
desatualizado: com uma cópia só, não há o que envelhecer. O custo é o [W] olhar a pasta do [F] no
repo, e não o próprio Cowork, para ver a Fabricação.

## 5 · O PR de código, depois das decisões

Vale para qualquer resposta da §4: as duas máquinas que hoje só enxergam o [W] passam a ler
`PROJETOS` em vez de um literal. Nenhuma máquina nova.

- `cowork-bundle.yml`: `paths` ganha `prototipo-ui/cowork/Felipe/**` e o job roda uma vez por
  conta alterada (`--root` e `--previous` vindos de `pathsForOwner()`).
- `pendentes-cowork.mjs`: `--conta w|felipe` (default `w`, comportamento atual intacto); espelho,
  projectId e `enviados-cowork.json` por conta (`state/Felipe/enviados-cowork.json`). Para a conta
  `felipe`, o `--plano` só relata: o upload é feito pelo login do [F], porque o DesignSync daqui
  não vê aquela conta.
- **Quem é dono de cada tela:** ~~um arquivo novo `glob → conta dona`~~. **Correção
  (2026-09-24, mesma sessão):** esse arquivo seria máquina paralela ([LC-19](../../LICOES_CODE.md)).
  O dono já existe: `scripts/design/design-lock.mjs`, que declara por tela o `prototype_path` (com
  a pasta da conta) e o `content_hash` em `governance/design/design-lock.json`. O script está em
  `main`, mas o arquivo de lock **ainda não foi criado**. Medido em `origin/main` (`--ambiguidade`):
  **40 de 46** telas que declaram `-page.jsx` têm 2+ candidatos com conteúdo diferente, e a
  ferramenta escolhe o primeiro da varredura (alfabética, a pasta do [F]). O mapa de donos é
  **popular esse lock**, não criar outro.

## 6 · Uma pasta ou duas: medido, não opinado

- **Uma pasta não é mais segura.** O import de árvore completa **poda** do espelho da conta tudo o
  que não veio no pacote (`bundle-transaction.mjs`, `mirrorScope === 'tree'`). Com uma pasta só,
  um retorno da conta do [F] apagaria os arquivos que só o [W] tem, e vice-versa: hoje são
  **597** só na do [W] e **186** só na do [F]. Faria falta uma poda por dono, que não existe.
- **Duas pastas também não resolvem sozinhas.** As 40 telas ambíguas acima já leem a fonte errada
  em silêncio.
- **O que dá segurança é o lock por tela**, e ele funciona com duas pastas: cada conta segue
  importando só para a sua, e o lock diz qual das cópias é a fonte daquela tela.

## 6.1 · Resposta do [F] sobre a pasta dele (2026-09-24)

[F], textual: *"São trabalhos meus, Maiara. Consulta de produtos tem alguns detalhes que quero
mesclar na tela nova do protótipo, por isso ela existe."* Logo, os arquivos de fora da Fabricação
na pasta `cowork/Felipe/` (ex.: `erp-shell-v2/`, `handoff_produtos_consulta/`) **não são cópia
velha do [W]**: no lock inicial, as telas deles apontam para a pasta do [F].

Dos **111** arquivos que diferem fora da Fabricação, **17** tiveram commit na pasta do [W] depois
do último commit na do [F]. ⚠️ Data de commit é data de **import**, não de edição no Cowork, então
isso indica candidatos, não prova de versão mais nova. Esses 17 foram entregues ao [F] em pacote,
para ele subir no próprio Cowork. **Não** foram copiados para `cowork/Felipe/` no repo: o próximo
retorno da conta dele sobrescreveria a cópia com a versão do Cowork dele (§5 2026-09-24,
edição à mão no espelho).

## 7 · Trocar de tela

Pegar uma tela nova = um PR que muda a linha dela no lock para a pasta de quem vai mexer. Devolver
= outro PR. Como todo merge já passa por aprovação humana (R10), a autorização é a revisão desse
PR: tela hoje declarada na pasta do [W] precisa do "ok" dele; esta é a **proposta**, não regra em
vigor. O lock não impede ninguém de editar a tela no próprio Cowork. Ele decide qual versão vale,
e a outra fica na pasta de quem a fez, ignorada, sem ser apagada.

Prova exigida no PR: `pendentes-cowork.mjs --conta w` com saída idêntica à de hoje (controle), e
`--conta felipe` listando os pendentes do espelho do [F] contra `state/Felipe/active-bundle.json`.
