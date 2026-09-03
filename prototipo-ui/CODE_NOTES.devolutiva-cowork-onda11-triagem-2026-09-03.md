# Devolutiva ao [CC] — Onda 11 (Triagem) fechada declarando, e a premissa que não vale aqui

> **Decisão [W] 2026-09-03:** a Onda 11 do `COLAR-NO-CODE-EXPORT-FORJA-MODULO.md` fecha **sem criar
> tela e sem replicar**. Isto não é recusa de trabalho — é o desfecho que a medição sustenta, e o
> próprio pacote previu ao declarar `FjTriagemView` órfão no §8. O registro canônico do lado do
> Code está no `Cockpit.casos.md` (bloco do `UC-FORJA-08`) e no `Cockpit.charter.md` (errata
> 2026-09-03). Este arquivo devolve ao Cowork **só o que muda o próximo build**.

## 1 · A premissa do §6-bis não vale neste produto (é o achado, e é seu para decidir)

O `github.md` e a `PARIDADE §6-bis` dizem que a Triagem **é tipo `Proposta` dentro de Aprovações**.
No protótipo isso é coerente. **Aqui não é**, e a razão é de domínio, não de layout:

| | filtro real medido no `main` |
|---|---|
| Triagem (`/forja`) | `McpTask::scopeTriage()` = `owner IS NULL OR priority IS NULL OR status='backlog'` |
| Aprovações (`/forja/aprovacoes`) | `ForjaAprovacoesService::fila()` = `status='pending_approval'` **apenas** |

Nenhum é subconjunto do outro. E o slot `Proposta` **já está ocupado**: dos 4 tipos de submissão da
sua mesa, só `Proposta` tem estado canônico aqui, e esse estado é `pending_approval`
(`Aprovacoes/Index.tsx:38-39`) — o estado **posterior** à triagem.

Ou seja, no produto o funil é **Triagem (F0) → Aprovações (gate)**: duas etapas, não duas telas para
a mesma coisa. Absorver uma na outra sem mexer no filtro faz Aprovações abrir vazia enquanto a
Triagem tem tickets vivos — perda já medida e registrada na `PARIDADE §9.7`.

**O que pedimos que você decida** (é design de fluxo, não CSS): a sua mesa de Aprovações deve
passar a mostrar **duas faixas** (o que espera decisão × o que espera enriquecimento), ou a Triagem
continua sendo uma superfície própria no seu build? Hoje ela é a **landing** `/forja` — destino de
4 redirects permanentes e do botão primário `Novo issue` — e a barra já fechou nos **6** destinos
que você desenhou, sem ela. Ela não disputa espaço no topnav.

## 2 · Precisão sobre o §1 — o `forja-novo-issue` não tem receptor, mas o GATILHO tem

O §1 lista `forja-novo-issue` entre as superfícies "sem receptor no `main` hoje". **Está correto**:
o compositor não existe aqui. O que vale registrar é mais fino e ajuda quem for construí-lo — o
**botão que o abriria já existe e já aponta para outro lugar**: `ForjaHub.tsx:141` é
`<Link href="/forja" data-testid="forja-novo-issue">Novo issue</Link>`, ou seja, hoje ele leva à
**lista** de triagem, não a um compositor. Quando o overlay nascer, o receptor a trocar é esse
`Link` — não há botão a criar.

## 3 · Nada a pedir sobre o bundle (já pedido, não duplicar)

`forja-triagem.jsx` é um dos arquivos que existem **só no Cowork vivo** e ainda não desceram ao
espelho. Isso **já** é o pedido do [#6671](https://github.com/wagnerra23/oimpresso.com/pull/6671)
(regeneração do pacote por `gerar-payload-partes.mjs`, que só roda do seu lado — ADR 0374). Não
abrimos pedido novo: quando o bundle descer, o `forja-triagem.jsx` vem junto com os outros.

Enquanto ele não desce, qualquer comparação de fidelidade desta view é **inconclusiva por
construção** — e é por isso que a onda não podia terminar em "repliquei e ficou igual".
