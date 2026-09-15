---
date: "2026-09-13"
topic: "Thread 01 do playbook fiscal — os 2 specs E2E que faltavam (cockpit + NF-e)"
authors: [C]
outcomes:
  - "e2e/fiscal-cockpit.spec.ts e e2e/fiscal-nfe.spec.ts criados: 6 casos executáveis + 3 fixme declarados"
  - "Thread 02 (paginação) confirmada FEITA — a prova do playbook era falso-negativo por buscar a string Pagination"
  - "Hipótese medida, NÃO consertada: o Cockpit anuncia 4 atalhos e tem 1 handler de tecla"
---

# Thread 01 · Rede E2E do Fiscal

## TL;DR

- **Entregue:** `e2e/fiscal-cockpit.spec.ts` + `e2e/fiscal-nfe.spec.ts` — 9 casos, **6 executáveis + 3 `fixme`** com o motivo medido. O valor não é "mais um teste": é o eixo que os 4 suítes jsdom do módulo declaram **fora do próprio alcance** (travessia física por Tab, navegação HTTP real entre rotas).
- **Premissa do playbook corrigida:** a thread 02 (paginação) está **FEITA** — a prova dela buscava a string `Pagination` e a implementação usa PT-BR (`pagina`/`porPagina`). `D-LANE` respondida: `e2e-gate.yml` cobre `e2e/**`.
- **`PARAR SE (b)` acionado:** nenhum seeder cria `nfe_emissoes`, então os 3 casos de teclado/paginação ficaram declarados, não inventados.
- **Achado medido, NÃO consertado** (fora do prefixo): o `Cockpit.tsx` anuncia 4 atalhos e tem **1 de 1** handler de tecla — 3 das 4 não respondem. Mesma classe do `UC-FNFE-12(b)`, que a tela irmã já fixou como defeito. Precisa de dono.
- **Erro próprio, corrigido no mesmo PR:** a 1ª versão do `UC-FCKP-03` usou `innerText` e reprovou no CI — o `.fx-ribbon-item small` tem `text-transform: uppercase`, então eu media o que o browser **pintou** em vez da copy do DOM (§5 2026-07-16).

Execução da thread **01** do playbook `fiscal` (recuperado do git — a pasta
`prototipo-ui/design-docs/cowork-inbox/` foi removida pela [#7224](https://github.com/wagnerra23/oimpresso.com/pull/7224),
ADR 0397 D5; lido via `git show 4f51a9ec78^:<path>`).

## O que foi medido antes de escrever (re-medição contra `origin/main` fresco)

O checkout estava **23 commits atrás**; trabalhei numa branch nova a partir de `origin/main`
(`claude/fiscal-e2e-rede-thread01`, 0/0 com o remoto no momento da escrita).

| Premissa do playbook | Re-medido em 2026-09-13 | |
|---|---|---|
| `e2e/fiscal-cockpit.spec.ts` ausente | ausente | ✅ confere |
| `e2e/fiscal-nfe.spec.ts` ausente | ausente | ✅ confere |
| "17 specs em `e2e/`" | **20** specs | ⚠️ frescor mudou (base do playbook era `11eff17f13db`) |
| contratos em `prototipo-ui/contrato/` | migrados para `governance/design/contracts/` | ⚠️ caminho pré-#7224 |
| âncoras `data-contract` dos contratos | `fiscal-cockpit-kpis` em `Cockpit.tsx:423`, `fiscal-nfe-filters` em `Nfe.tsx:212` | ✅ existem — o `PARAR SE (a)` não se aplicou |
| thread 02 (paginação) "DE PÉ" | **FEITA** — `Cockpit.tsx:721` tem `data-contract="paginacao-notas"` | ❌ premissa do playbook morta |

**Por que a prova do playbook errou na thread 02:** ela procurava a string `Pagination` em
`Pages/Fiscal/`, e a implementação usa nomes em PT-BR (`pagina`/`porPagina`/`paginas`). Zero
hit não era ausência de capacidade — era o padrão errado.

## D-LANE respondida (o playbook a deixava em aberto)

`.github/workflows/e2e-gate.yml` roda em `pull_request` com `e2e/**` no `paths-filter`, sobe
MySQL 8 + schema-squash + `VisregTenantSeeder` (biz=1) e executa `npm run e2e:check`. Logo os
specs **entram no CI** — não ficam fora dele. Ela é advisory (não-required) por decisão da
ADR 0261 registrada no próprio workflow.

⚠️ O `types:` dela é `[opened, reopened, ready_for_review]` — **sem `synchronize`**. A lane
roda no primeiro commit do PR e não re-roda a cada push (é a emenda 2026-09-08 de
`proibicoes.md`). Por isso o PR saiu com **1 commit só**.

## `PARAR SE (b)` acionado — e o que ele custou

Os casos 2, 3 e 6 da thread (Tab até a linha · Space · J/K) precisam de **nota na lista**.
Medido por duas vias independentes:

1. `git grep -l 'NfeEmissao|nfe_emissoes'` em `database/seeders/` + `Modules/*/Database/Seeders/`
   → **zero** seeders.
2. `Nfe.casos.md` (UC-FNFE-09) já registrava o mesmo por escrito: *"nenhuma lane de hoje tem
   `nfe_emissoes`"*.

Sem emissão, `Cockpit.tsx:566` e `Nfe.tsx:280` entregam o empty state e a `<table>` não
existe. Os 3 casos nasceram `test.fixme` com o motivo no corpo — **declarados, não criados**,
como a thread manda; fixture de nota aqui abriria um segundo jeito de semear, paralelo ao Pest.

## O que os specs acrescentam (e não é "mais um teste")

O módulo já tem 3 suítes jsdom e 21 UC. O valor daqui é o **resíduo que elas declaram fora do
próprio alcance**, por escrito:

- `Cockpit.casos.md` UC-FCKP-11: *"o jsdom não implementa a travessia por Tab do browser (...)
  a travessia física, o anel pintado e o leitor de tela são olho humano no smoke"*.
- `Nfe.casos.md` UC-FNFE-14: *"a navegação HTTP real entre as rotas — o jsdom não a faz (...)
  o Cockpit não é renderizado (recebe ~15 props de payload)"*.

Os casos executáveis miram exatamente isso: o chip da NF-e **viajando na querystring** via
`router.visit` e a densidade atravessando `/fiscal/nfe` → `/fiscal` → `/fiscal/nfe` com HTTP
real, nos dois sentidos.

## Hipótese MEDIDA, não consertada (fora do meu prefixo)

Varredura contada em `Cockpit.tsx` (`origin/main`): o rodapé anuncia **4** atalhos —
`⌘K buscar` · `N emitir` · `J/K navegar` · `? mais atalhos` (`:370-375`) — e o arquivo tem
**1 de 1** linha com `e.key`: a `:606`, que trata só `Enter`/`Space` na linha da tabela. Não há
`addEventListener('keydown')` (o único `addEventListener` é `mousedown`, `:336`). Os outros
donos de tecla do shell tratam: `CmdKPalette` → `⌘/Ctrl+K`, `Escape`, setas, `Enter`;
`FxShell` → só os dígitos de `FX_PAGES.short`.

**Logo 3 das 4 teclas anunciadas no Cockpit não têm handler.** É a mesma classe que o
`UC-FNFE-12(b)` fixou como defeito na tela irmã (*"tecla anunciada sem handler"*), onde `R`/`X`
foram removidos em 2026-09-04 com mordida provada.

É **hipótese**, não achado fechado: falta a perna (c) do §5 2026-07-15 — o teste vermelho.
Deliberadamente não escrevi esse caso: `Pages/Fiscal/` está no `nao_toca` da thread, um
vermelho novo travaria o PR, e a correção (remover as 3 do rodapé × implementar os atalhos) é
decisão de produto — `X`/`N` abrem fluxo com formulário, então não são tecla, são porta.

## Verificação feita (e a que não deu para fazer)

| O quê | Como | Resultado |
|---|---|---|
| sintaxe + tipos dos 2 specs | `tsc` (repo principal) com tsconfig espelhando o do projeto, **canário reprovando primeiro** | canário exit 2 · specs **exit 0** |
| bytes de controle / CRLF | sonda em node com canário (`\b` + `\r`) | canário morde · specs `ctrl=0 crlf=0` |
| guardas da thread | `onKeyDown` em `Cockpit.tsx` (1 hit) · os 2 contratos com diff vazio vs `origin/main` | intactos |
| **execução dos specs** | pela lane `e2e-gate` no PR ([#7257](https://github.com/wagnerra23/oimpresso.com/pull/7257)) — não é possível local (ADR 0062) | **5 de 6 passaram no 1º run**; 1 reprovou e foi consertado |

### O veredito do CI, e os 3 vermelhos do 1º run

| check | dono | causa |
|---|---|---|
| `E2E Playwright · UCs críticos` | **meu** | `UC-FCKP-03` reprovou: usei `innerText` e o `.fx-ribbon-item small` tem `text-transform: uppercase` (`fiscal-cockpit.css:814`) → vinha `"EMITIDAS"`. Trocado por `textContent` nos 3 pontos de asserção de copy |
| `Schema session log` | **meu** | faltava `## TL;DR` (ou `## Resumo executivo`/`## Contexto`) — adicionado |
| `UI architecture gate` | **infra** | `composer install` levou **HTTP 503** clonando `dev.azure.com/myfatoorahsc` (dep externa fora do ar). Nada do diff: não toco PHP nem `composer.lock`. No `main` o gate está `success` |

O erro do `UC-FCKP-03` é a lápide **§5 2026-07-16** (medir a propriedade errada) num eixo novo:
ali o caso era afirmar UI pelo que *se mandou* em vez do que o browser resolveu; aqui foi o
inverso simétrico — medi o que o browser **pintou** quando o contrato fala do texto do **DOM**.
A prova de que eu já tinha a resposta dentro do próprio arquivo: o `UC-FCKP-13` passou usando
`toContainText`, que lê `textContent` por padrão. Regra que fica: **asserção de copy lê
`textContent`; `innerText` é renderização, e prendê-la acopla o caso ao CSS.**

### Duas sondas minhas mentiram nesta sessão — as duas por dependência ausente

1. **`grep -P`** (bytes de controle) falhou por locale (`LC_ALL=C`) e o `if` leu o erro como
   "nada encontrado", imprimindo OK. Refeita em node, com canário.
2. **`jq` não existe neste Windows** — e o monitor de CI que escrevi usava `jq` em toda linha.
   Resultado: **30 minutos de silêncio** que seriam lidos como "nada reprovou", quando havia 3
   vermelhos. Remedido em node. É a lápide §5 2026-08-11 (`jq` ausente) somada à §5 2026-07-29
   (instrumento que afirma verde sem ter conseguido medir) — as duas apanhadas dentro da sessão,
   e nenhuma conclusão foi tirada do silêncio.

## Endereço canônico do recibo — em aberto para [W]

A thread pede `_saida-01.md` na pasta do playbook. Ela **não existe mais** (#7224) e não pode
ser recriada em `prototipo-ui/`: `scripts/governance/cowork-ssot-guard.mjs` R3 só admite `.md`
em `prototipo-ui/cowork/<dono>/handoffs/<nome>.md`, flat. O recibo foi para o corpo do PR +
este log. **Onde os `_saida-NN.md` das próximas threads devem morar é decisão de [W]** — não
inventei pasta nova.

## Estado no fechamento

- MCP indisponível nesta sessão (`brief-fetch` caiu com "servidor inalcançável"); usei os
  substitutos: `gh pr list` cruzado com `e2e/`, `git log --remotes` e `list_sessions`.
- Sessão irmã no Fiscal (`claude/fiscal-contingencia-ui`) toca `Config.tsx` + Controllers —
  **não toca `e2e/`**. Zero colisão com este prefixo.
- Nenhum PR aberto (8 medidos, um a um) toca `e2e/`.
