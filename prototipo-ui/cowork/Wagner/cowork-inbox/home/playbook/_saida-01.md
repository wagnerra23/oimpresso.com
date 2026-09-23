---
sessao: "01"
titulo: Saída da thread 01 — premissa REFUTADA, charter não tocado
dono: "[CL]"
medido_em: 2026-09-23
base_medida: 1061dbf2e0f6 (origin/main fresco; a base do playbook 4dc1176f68df está atrás)
arquivos_de_producao_tocados: 0
veredito: "REFUTADO — no catálogo do repo PT-04 É Dashboard e PT-05 é Kanban; o charter já está certo"
---

# 01 · Saída — o charter da Visão geral já declara o PT certo

> **Nada foi editado no prefixo** (`resources/js/Pages/Home/Index.charter.md`). A thread pedia
> trocar `PT-04 Dashboard` por `PT-05 Dashboard`. Medido, a troca **quebra um gate** e
> faz a tela declarar Kanban.

## 1 · O que a thread afirmava

`01-pt05.md`: *"No catálogo, PT-04 é o Modal e o Dashboard é PT-05."*

## 2 · O que o repo diz (medido em `1061dbf2e0f6`)

| fonte | PT-04 | PT-05 |
|---|---|---|
| `memory/requisitos/_DesignSystem/padroes-tela/PT-04-Dashboard.md` (`pattern_id: PT-04`, `nome: Dashboard`) | **Dashboard** | — |
| `memory/requisitos/_DesignSystem/padroes-tela/PT-05-Kanban.md` (`# PT-05 · Kanban`) | — | **Kanban** |
| `scripts/governance/pt-conformance.mjs` (selftest: `PT-04 ok com KPIs` · `PT-05 ok com kanban`) | Dashboard | Kanban |
| `prototipo-ui/design-system/_ds_manifest.json` (espelho do DS Cowork) | *"Centered confirmation dialog (PT-04)"* = **Modal** | `templates/pt-05-dashboard` = **Dashboard** |

A premissa da thread vem da **última linha** — a numeração do DS espelhado do Cowork. O
catálogo canônico do repo (camada 3 da [UI-0013](../../../../../../memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md))
e a máquina que o fiscaliza numeram ao contrário. **São dois catálogos com o mesmo código e
significados trocados.**

## 3 · Consequência medida da troca (bite-test, revertido)

Apliquei a troca no working tree, rodei o gate, restaurei (sha256 do charter idêntico antes
e depois: `3575e196…f3fbe1`):

| estado do charter | `node scripts/governance/pt-conformance.mjs --check` |
|---|---|
| como está (`PT-04 Dashboard`) | `OK — 82 declarações de PT conferem com a assinatura (0 mismatch)` · exit **0** |
| com a troca (`PT-05 Dashboard`) | `✗ /dashboard-legacy declara PT-05 — sinais: {"kanban":false,"kpi":true,…}` · exit **1** |

O `pt-conformance` extrai o PT do `related_prototype` e lê `PT-05` como Kanban; a tela tem
KPIs e nenhum kanban, então vira MISMATCH. O workflow `.github/workflows/pt-conformance.yml`
roda esse `--check`.

## 4 · O que fica aberto (não é desta thread)

- **A prova do `00-INDICE.md` (`nao_contem "PT-04 Dashboard"`) está errada** e segue
  reprovando — com este recibo o placar marca 01 como `em curso`, e não fecha. O índice é do Cowork; a
  correção é marcar a thread com `bloqueio`/descartada lá, não editar o charter.
- **A divergência de numeração entre o DS espelhado (PT-04 Modal / PT-05 Dashboard) e o
  catálogo do repo (PT-04 Dashboard / PT-05 Kanban)** é o achado real. Qual catálogo é o
  dono do código `PT-0X` é decisão [W]: renumerar o do repo mexe em
  `padroes-tela/`, no `pt-conformance.mjs` e nas 82 declarações de PT que o gate confere hoje.
  O que **não** é opção: trocar charter a charter para a numeração do espelho — cada troca
  avermelha o `pt-conformance`.

## 5 · Desfecho (2026-09-23, mesmo dia)

[W] decidiu: **o catálogo do repo é o dono do código `PT-0X`.** A thread 01 ganhou
`bloqueio` no `00-INDICE.md` e o placar passou a `bloqueada 1`. Os dois pontos da §4
ficam assim: a prova errada do índice deixa de cobrar (thread descartada), e a numeração
do DS espelhado (PT-04 Modal / PT-05 Dashboard) é a que diverge — alinhá-la é trabalho do
lado Cowork, não de charter.
