---
id: requisitos-documentacao-runbook-casos-por-tela
title: "RUNBOOK — /documentacao/casos-por-tela (pedidos de clientes e contrato por tela)"
module: Documentacao
tela: Documentacao/CasosPorTela
owner: W
status: rascunho
last_validated: "2026-10-29"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0093-multi-tenant-isolation-tier-0
spec_ref: memory/requisitos/Documentacao/SPEC.md
---

# RUNBOOK — `/documentacao/casos-por-tela`

> Tela nova (não migra Blade). Substitui o artefato "Casos de uso por tela", que era publicado à
> mão com `npm run casos:pagina`. O compartilhado de `/documentacao` vive em
> **[RUNBOOK-documentacao.md](RUNBOOK-documentacao.md)**.

## Rota

- `GET /documentacao/casos-por-tela` → `CasosPorTelaController@index` → `Inertia::render('Documentacao/CasosPorTela')`.
- `POST /documentacao/casos-por-tela/conferencia/{P-NNN}` → marca/desmarca "Conferido".
- `POST /documentacao/casos-por-tela/decisao/{D-NN}` → registra a resposta do [W].

Declaradas **acima do `{slug}`**, pelo mesmo motivo de `/programa`.

## A fonte

Os pedidos (`[BACKLOG]` com o subitem `Pedido: P-NNN`), as decisões (`[DECISÃO] D-NN`) e os casos
de uso com id (`## UC-...`) moram nos `*.casos.md` das telas. O veredito de cada UC vem de
`scripts/casos-test-results.json` (o manifesto do casos-gate). A página lê tudo isso **a cada
acesso** (cache de 5 min), com `App\Services\Documentacao\CasosPorTela`.

Dois leitores, uma saída: o PHP é o porte de `scripts/casos-backlog/leitor.mjs` + `gerar.mjs`.
`scripts/casos-backlog/paridade-php.test.mjs` roda os dois no repositório inteiro e exige o mesmo
resultado; ele é o que garante que a página no sistema mostra os mesmos números de
`npm run casos:pagina -- --resumo`. Nome e área das telas: `scripts/casos-backlog/telas.json`.

**Por que PHP e não um JSON gerado no deploy** (medido 2026-10-28): o deploy tem
`paths-ignore: '**.md'` e o sync leve faz `git reset` sem publicar bundle. Um JSON do build
ficaria parado justo quando só os casos.md mudam; os arquivos no disco, não. O checkout de
produção tinha os mesmos 351 casos.md do git e o manifesto de testes.

## Como entra um pedido novo

Pedido de cliente (ou do [W]) entra pelo comando, não escrito à mão:

```bash
npm run casos:novo -- --proximo                 # só mostra o próximo P-NNN livre
npm run casos:novo -- --caso caso.json --dry-run   # mostra o texto sem gravar
npm run casos:novo -- --caso caso.json             # grava no casos.md da tela
```

O `caso.json` tem `tela`, `titulo`, `texto` (Dado · Quando · Então), `ancora`, `fazer`, `hoje`,
`clientes` e, opcionais, `marcas`, `decisao`, `cv`, `etiquetas`, `autor` e `data`; o formato
completo está no topo de `scripts/casos-backlog/novo.mjs`. O comando reserva o próximo P-NNN,
escreve o bullet com os subitens na seção `## Pedidos de clientes` do casos.md (cria a seção se
faltar), acrescenta a linha na `## Trilha do tempo` quando o arquivo tem trilha, e relê o arquivo
com o leitor da página antes de gravar. Recusa pedido sem cliente, `hoje`/`cv` fora do
vocabulário (`ok`, `part`, `miss`, `gray`) e valor em R$.

Dívida técnica (caso sem teste, débito de schema) não entra por aqui: segue como `[BACKLOG]` sem
`Pedido`, fora da página.

Numeração: `telas.json` → `ultimoReservado` guarda até onde o documento de origem já numerou
(P-152 em 2026-10-29, inclusive os casos que ainda não estão no git). O número vem do git no disco;
rode com o main atualizado. Dois PRs abertos ao mesmo tempo podem pegar o mesmo número, e aí o
leitor acusa o repetido (ver Pegadinhas).

## Quem vê, confere e decide

| Ação | Permissão | Quem tem por padrão |
|---|---|---|
| Ver | `documentacao.casos_tela.ver` | quem tinha `jana.superadmin` (papel Operacional#1: Maiara, Felipe…) |
| Conferir | `documentacao.casos_tela.conferir` | idem |
| Responder decisões | `documentacao.casos_tela.decidir` | ninguém; o [W] passa por `administrator_usernames` |

Checadas com `checkPermissionTo`, nunca `can()`: o `Gate::before` libera tudo ao `Admin#{biz}` de
qualquer empresa, e esta página não é da empresa (ADR 0093 — documentação da plataforma).

## Conferência e decisões

Gravam em `doc_casos_marcas` (tipo `conferencia` com chave `P-NNN`, ou `decisao` com `D-NN`), sem
`business_id` de propósito. No artefato elas moravam em `conferencia/c<n>` e `decisoes/D-NN`;
medido em 2026-10-28, as duas coleções estavam vazias, então não houve marca a migrar.

## Pegadinhas

- Um P-NNN repetido em dois casos.md faz o leitor lançar exceção (é erro de dado, igual ao Node).
  A página mostra o erro (qual id, quais dois arquivos) em vez da lista, até alguém corrigir o
  arquivo. O `gerar.mjs --resumo` acusa a mesma coisa antes do merge.
- Mexeu no formato do casos.md (grafia de backlog, subitem novo)? Mude o leitor Node **e** o PHP;
  o teste de paridade cai se só um mudar.
