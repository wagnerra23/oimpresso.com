---
thread: "27 · Emendas de charter + guards"
dono: "[CL]"
estado: feito
base_lida: "wagnerra23/oimpresso.com@main 5606344ca (2026-09-29)"
prefixo_tocado: "resources/js/Pages/Ponto/**/*.charter.md · resources/js/Pages/Ponto/**/*.casos.md · Modules/Ponto/Tests/Feature/"
veredito: "entregue — 8 emendas no main, 2 guards (5 casos Pest) verdes na lane ponto-pest do main; os 3 UCs que a thread deixou em BACKLOG viraram UC com teste em 2026-09-29"
---

# _saida-27 · Emendas de charter + guards

Recibo escrito em 2026-09-29, depois do merge, por uma sessão que não fez os PRs. Tudo abaixo foi
medido no `main` em `5606344ca`. O handoff da sessão que fez o trabalho é
`memory/handoffs/2026-09-28-1840-ponto-thread-27-emendas-casos-guards.md`.

## 1 · Feito

### PRs (estado pedido junto com o merge: `gh pr view <N> --json state,mergedAt,baseRefName`)

| PR | estado | base | merge (UTC) | conteúdo |
|---|---|---|---|---|
| #8087 | MERGED | main | 2026-09-28 20:36 | charters E3, E3-bis, E4, E6, E7, E8 |
| #8095 | MERGED | main | 2026-09-28 21:05 | charters E1, E2, E5 |
| #8097 | MERGED | main | 2026-09-28 21:16 | casos Colaboradores e Importações (BACKLOG) |
| #8094 | MERGED | `claude/ponto-27-casos` | 2026-09-28 21:08 | os 2 guards Pest, dentro do #8093 |
| #8104 | MERGED | `claude/ponto-27-casos` | 2026-09-28 21:37 | charter de Intercorrências/Index cita o guard, dentro do #8093 |
| #8093 | MERGED | main | 2026-09-28 21:58 | casos Intercorrências + #8094 + #8104 |

Os 4 commits de merge em `main` (`06c55153bf`, `9f8b7e6654`, `21e0dbef74`, `59f0d15d19`) são
ancestrais de `origin/main` (`git merge-base --is-ancestor`, os 4 = sim).

### As 8 emendas × arquivo:linha (em `resources/js/Pages/Ponto/`)

| emenda | arquivo | linha |
|---|---|---|
| E1 · colunas, filtros, CPF/PIS mascarado | `Colaboradores/Index.charter.md` | Goals `:36-39`; pendência fechada `:79` |
| E2 · diagnóstico + amostra de erros | `Importacoes/Show.charter.md` | Goals `:35-36` |
| E3 · KPIs agregados | `BancoHoras/Index.charter.md` | Goals `:31-32` |
| E3-bis · teto e prazo do acordo | `BancoHoras/Show.charter.md` (pendência fechada em `BancoHoras/Index.charter.md`) | Goals `:34-35`; pendência `Index :73-74` |
| E4 · anexo + Non-Goal LGPD Art. 11 | `Intercorrencias/Create.charter.md` | Goals `:35`; Non-Goal `:50` |
| E5 · 5º bloco IA só leitura | `Configuracoes/Index.charter.md` | Goals `:31`; Non-Goal `:46-47`; item `D-CFG-IA-CAMINHO` `:72` |
| E6 · tinta de papel | `Espelho/Show.charter.md` | Non-Goal `:45-50` |
| E7 · lista só oferece Ver | `Intercorrencias/Index.charter.md` | `:41-45`, cita o guard |
| E8 · remover escala com trava | `Escalas/Index.charter.md` | Non-Goal `:50-54`; pendência fechada `:81` |

A pendência de expiração que a thread punha no `Show` estava no `Index`; foi fechada lá.

### Os 2 guards (`Modules/Ponto/Tests/Feature/`)

| guard | arquivo | casos |
|---|---|---|
| E7 · `D-INTERC-ACOES` | `IntercorrenciaContratoTest.php:324` | `UC-INTIDX-04 · a linha da fila só oferece Ver — não submete nem edita` |
| E8 · `D-ESC-DESTROY` | `EscalaRemocaoContratoTest.php:131, :166, :224, :253` | 4 casos `UC-ESCIDX-04`: com vínculo e sem vínculo, pela UI e por `DELETE` direto |

### Provas

- **Guards rodando no CI do `main`:** run `36629398397` do `ponto-pest.yml`, `push` em `5606344caa`
  (o tip do `main` nesta medição), `success`. O log mostra ✓ nos 5 casos acima pelo nome, e 0
  deles com falha. Resumo da lane: `1 skipped, 423 passed (1547 assertions)`. Os dois arquivos
  estão na lista de alvos do workflow (`ponto-pest.yml:316` e `:326`).
- **`casos-gate`:** `node scripts/casos-coverage-guard.mjs` rc=0, *"Sem violações novas"*, nenhum
  `uc-orphan` (0 ocorrências de `uc-orphan` e de `UC-INTIDX-04` na saída). Era o risco que o
  handoff mandava conferir depois do merge.
- **`loop-fechar-check.test.mjs`:** 79 linhas `[OK]`, 0 falhas; a medição E1 do regime (a que roda
  o casos-guard e que derrubava as 2 advisory do #8093) mediu: `regime E1: MEDIU → pendente (57/0)`.

## 2 · Não feito, e por quê (o que ficou em BACKLOG)

**O que a thread deixou em `[BACKLOG]` não está mais em BACKLOG.** Os 3 UCs de capacidade
inexistente viraram UC com teste no dia seguinte, cada um no PR que construiu a capacidade, como
o handoff previa:

| emenda | UC | PR que construiu | estado no `.casos.md` |
|---|---|---|---|
| E1 · filtro Sem PIS | `UC-COLIDX-04` | #8121 (2026-09-29) | `Colaboradores/Index.casos.md:70`, BACKLOG riscado, "RESOLVIDO 2026-09-29" |
| E2 · amostra de erros | `UC-IMPSH-06` | #8124 (2026-09-29) | `Importacoes/Show.casos.md:77`, idem |
| E4 · anexo de comprovante | `UC-INTCRE-04` | #8128 (2026-09-29) | `Intercorrencias/Create.casos.md:34`, idem |

O que segue aberto, medido em `5606344ca`:

- **E1:** só a situação *Sem PIS* foi construída (`Colaboradores/Index.tsx:51`). As colunas
  "Último ponto" e "Saldo BH" e as situações Ativos / Só quem controla ponto / Desligados não
  existem no `.tsx`. O default da Situação (protótipo abre em *Ativos*, a tela em *Todos*) está em
  `[BACKLOG]` no `Colaboradores/Index.casos.md:74`.
- **E2:** o card "Diagnóstico do processamento" (log bruto) não foi construído; o charter diz isso
  em `Importacoes/Show.charter.md:43-44`.
- **E3:** `BancoHoras/Index.tsx` ainda mostra os 4 KPIs antigos; o charter registra (`:34-35`,
  re-medido em 2026-09-29).
- **E5:** `D-CFG-IA-CAMINHO` segue aberto — o caminho de pacote/permissão para as flags de IA não
  existe (flags por env global). O bloco "IA do Ponto" não aparece no `Configuracoes/Index.tsx`
  (0 ocorrências). Decisão [W].
- **E8:** o guard afirma **redirect com flash `error`** e a escala intacta
  (`EscalaRemocaoContratoTest.php`, `assertRedirect(route('ponto.escalas.index'))` +
  `assertSessionHas('error', …'vinculado'…)`), **não 403** como o esboço da thread pedia. É o que o
  `EscalaController@destroy` faz; trocar para 403 é decisão nova de [W], não conserto.
- **Âncoras:** as regiões `faixa-de-kpi`, `kpis-do-extrato`, `anexo-de-comprovante`,
  `acao-por-linha`, `remover-escala` e `barra-de-busca-e-filtros` não têm `contrato=` no protótipo;
  âncora estável exige id novo nos dois lados, nascido no Cowork.

## 3 · Pedido literal

> Thread 27 do Ponto. Tarefa: (1) confirmar pelo `gh pr view` (pedindo `state` junto) que os PRs
> da thread mergearam; (2) rodar as provas que o §7 do 00-INDICE declara para a 27 contra o main;
> (3) escrever `_saida-27.md` com os 5 itens do formato de saída (o que foi feito, provas, o que
> ficou em BACKLOG — E1/E2/E4 são UCs de capacidade inexistente; E8 afirma redirect, não 403).
> Se alguma prova não fechar, registre o que falta em vez de dar por feito.

O §7 declara `provas: []` para a 27, com a nota *"prova = _saida-27 com as 8 emendas ×
arquivo:linha e os 2 guards Pest nomeados"*. As provas da subseção Provas são as que rodei para sustentar
essa tabela.

## 4 · Descobertas

- **A premissa "E1/E2/E4 são capacidade inexistente" envelheceu em 1 dia.** Era verdade no fecho
  da sessão (2026-09-28) e deixou de ser com #8121/#8124/#8128. Registrei o estado de hoje, não o
  do handoff.
- **E3-bis foi construído antes do charter mudar de estado.** `BancoHoras/Show.tsx:215-216` tem
  "Teto do acordo" e "Prazo de compensação" desde o #8113 (2026-09-28); o charter
  (`BancoHoras/Show.charter.md:37`) diz *"Estado em 2026-09-28: a construir"*. É fato datado, não
  mentira; quem tocar o charter pode acrescentar a data da construção.
- **O #8093 mergeou em 2026-09-28 21:58 UTC**, que ainda é 2026-09-28 em BRT (18:58). O pedido
  dizia 2026-09-29.

## 5 · Prefixo tocado

Esta sessão tocou só este arquivo:
`prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/_saida-27.md`. Nenhum charter, casos, teste
ou `.tsx`. O `00-INDICE.md` não foi editado (o estado é derivado pelo placar).
