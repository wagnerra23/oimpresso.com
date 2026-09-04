---
id: sessions-2026-09-04-fiscal-onda10-sped-goals-cowork
topic: "Fiscal Onda 10 (SPED) — os Goals do charter do Cowork que a Onda 9 não entregou"
date: "2026-09-04"
authors: ["C"]
modulo: Fiscal
prs: [6723, 6728, 6737, 6741]
us: [US-FISCAL-010, US-FISCAL-016, US-FISCAL-017]
related_adrs:
  - 0062-separacao-runtime-hostinger-ct100
  - 0093-multi-tenant-isolation-tier-0
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0286-contrato-de-tela
---

# Sessão — Fiscal Onda 10 (SPED): os Goals do charter do Cowork

## O pedido

Implementar os 4 Goals do charter do Cowork que a Onda 9 ([#6708](https://github.com/wagnerra23/oimpresso.com/pull/6708),
`3f935647ed`) não entregou. O pedido já vinha com o levantamento medido pela ERRATA do handoff
anterior — 5 Goals no charter, 1 entregue — e com duas armadilhas nomeadas antes de eu começar.

## O que saiu

Três PRs de código **empilhados** — [#6723](https://github.com/wagnerra23/oimpresso.com/pull/6723)
(Goals 1, 4, 5) → [#6728](https://github.com/wagnerra23/oimpresso.com/pull/6728) (Goal 2) →
[#6741](https://github.com/wagnerra23/oimpresso.com/pull/6741) (Goal 3) — mais o
[#6737](https://github.com/wagnerra23/oimpresso.com/pull/6737) de docs, independente.

| Goal | Antes | Depois |
|---|---|---|
| 1. Barra com os 4 pré-requisitos | 🟡 dentro do drawer | ✅ barra na página + a data de encerramento no motivo |
| 2. Bypass de superadmin explícito | ❌ | ✅ ação nomeada, com efeito no servidor |
| 3. Prévia do TXT | ❌ | ✅ layout de um arquivo de referência |
| 4. Cartão de validação externa | ❌ | ✅ medido do disco |
| 5. Blocos com os registros | ❌ | ✅ medido do golden |

Âncoras `data-contract` do protótipo na tela viva: **1 → 5** (a que falta, `panorama-sped`, é a
tabela que a tela já tinha antes do F1).

O Goal 3 saiu depois de [W] decidir no meio da sessão — a pergunta e a informação medida que a
destravou estão em §"O que fica aberto" abaixo, mantidas como o percurso real.

## As três coisas que valem para a próxima sessão

### 1. A fonte contradizia o repo, e a fonte é que estava velha

O cartão do Goal 4 no protótipo diz literalmente *"Golden file do TXT: não existe"*. O charter do
Cowork é de **2026-08-24**; o golden nasceu em **2026-09-03** (#6708) — **um dia depois**. Traduzir
a copy literal teria posto **afirmação falsa** na tela de uma contadora.

A saída não foi escrever a copy certa à mão (que apodreceria igual, só que na outra direção): foi
criar `SpedReferenciaArquivoService`, que **mede** o golden a cada request — bytes, linhas,
SHA-256, registros por bloco — e deriva o "nunca executado" do PVA-EFD da **ausência** de
`sped-pva-smoke.recibo.md`. O dia em que alguém rodar o PVA e deixar o recibo, a tela para de dizer
"nunca executado" sozinha.

O `UC-FSF1-07` existe exatamente para impedir a cópia voltar.

### 2. O protótipo cita a data errada, e isso tem um teste próprio

O `UC-FSF1-03` do Cowork pede que o motivo do mês em aberto mostre *"a data em que a competência
fecha"*, e o protótipo renderiza ali o campo `entrega`. **São datas diferentes:** 09/2026 encerra
em 30/09 e a EFD vence 15/10. Quem lesse a data de entrega esperaria **duas semanas a mais** do que
precisa para gerar.

Segui o espírito e corrigi a letra: o motivo cita o **encerramento**; o prazo de entrega segue na
coluna própria da tabela. Um dos casos do `UC-FSF1-01` asserta `not->toContain(prazo de entrega)` —
quem copiar o campo do protótipo fica vermelho.

### 3. Teste verde venceu o charter, e a divergência ficou escrita

O `UC-FSF1-02` do Cowork quer a tela abrindo **bloqueada** mesmo para superadmin (opt-in). Mas
`SimplesOnlyGateTest::UC-FSPED-09 · superadmin bypassa flag` é teste **verde** e prova o contrário.
Precedência do projeto: *teste verde > casos > charter*.

Levei a [W] com o custo dos dois lados. Ele escolheu **opt-out**: o default preserva o
comportamento provado, e a ação explícita só consegue **restringir** — nunca afrouxar. A
divergência está registrada no `Sped.charter.md` e no `Sped.casos.md`, não escondida.

## Medições que mudaram o que eu ia fazer

**O espelho local não servia, e isso já estava medido.** `cowork-mirror-freshness --sla` mediu
**1 de 258**, com 157 arquivos do vivo ausentes. Baixei tudo por ID do projeto Cowork
(`019dcfd3-…`) — charter, casos, README, `FiscalOndasF1Test.php` e o protótipo `fiscal-subpages.jsx`.

**As 2 falhas da suíte SPED são pré-existentes, e eu MEDI em vez de deduzir.** Restaurei o
`SpedController.php` do `origin/main` no container do CT 100, rodei `SimplesOnlyGateTest` isolado, e
`UC-FSPED-09 · superadmin bypassa` falhou **igual** — `PermissionDoesNotExist: superadmin`, seed do
ambiente. Depois restaurei o meu e reconferi o sha. O handoff da Onda 9 atribuía essa falha a
`users_username_unique`; **é outra causa**, e o registro fica corrigido aqui.

**O contador é que prova execução.** `--filter='Sped|SimplesOnly'` foi de **46 passed** (Onda 9)
para **57 passed (360 assertions)** — delta **+11**, batendo exatamente com os casos adicionados.
O `SpedOnda10Test` isolado deu **11 passed (50 assertions)**, e o caso HTTP que confere as props do
Inertia **executou** (1.87s), não skipou.

**`SpedBypassSuperadminTest`: 5 passed, 1 skipped (13 assertions).** O caso do **403 rodou**; o do
**503 ponta-a-ponta skipou** por falta do seed de `superadmin` — a mesma lacuna acima. Skip sai com
exit 0 e não prova nada, então os 4 casos de régua, que rodam em toda lane sem banco, cobrem a
mesma regra.

## Higiene do ambiente compartilhado (CT 100)

6 sessões Fiscal ativas. Uma toca SPED — *"Investigar emitente vazio no SPED (CNPJ/IE/UF fixa SP)"*
— mas no `SpedIcmsIpiGeneratorService` e no registro `0000`, que a lei desta onda me proibia tocar.
**Interseção de arquivos: vazia.**

O `Modules/Fiscal/Routes/web.php` do container está numa revisão antiga (`c1abe9548`), e
sobrescrevê-lo quebraria rotas para as outras sessões. Apliquei **só a inserção da rota nova** sobre
o arquivo que estava lá, com **backup antes**, e **restaurei ao fim** conferindo o sha
(`59a5c5b4efe2fa54`). Numa das cópias anteriores eu tinha sobrescrito o `SpedController.php` **sem**
guardar o original — deslize, contornado porque a versão anterior era reconstruível do
`origin/main`, mas o backup passou a ser feito antes de qualquer escrita.

## Ferramentas que quebraram no caminho

Heredoc do Bash **colapsou duas vezes** ao transportar conteúdo com aspas e escapes (a classe
LC-26). As duas vezes o conserto foi a mesma: escrever pela ferramenta de escrita direta, ou
`--body-file` a partir de um arquivo. E medi `rc` de gate com `$?` depois de um pipe uma vez — o
que mediu o `tail`, não o script; refiz com o exit code certo antes de citar qualquer número.

## O Goal 3, e a pergunta que o destravou

Levei a pergunta antes de codar, porque o pedido mandava. [W] inclinou para *"blocos + linhas do
golden como referência de layout"* mas disse que faltava informação para decidir bem — e estava
certo, porque a minha própria formulação supunha um risco que eu não tinha medido.

Medi: o golden é 1.794 bytes / 47 linhas / sha `e4eeccd4…`, saída **real** do gerador, e a
**primeira linha dele se identifica como fictícia** — `|0000|018|0|01012026|31012026|CI TENANT 98
(FICTICIO)|…`. Uma prévia com essas linhas é auto-evidentemente não-sua; o risco de a contadora
confundir com o arquivo dela é bem menor do que eu supunha. Com isso, [W] confirmou.

Duas escolhas de implementação que vieram disso, e ambas viraram teste:

- **Uma linha por registro DISTINTO**, não as N primeiras. As 12 primeiras linhas cobrem só o
  Bloco 0 — o operador nunca veria um `C170` ou um `E110`.
- **O nome do emitente é LIDO do `0000`**, não escrito como "fictício". Um rótulo escrito viraria
  mentira no dia em que o golden fosse regerado de outro tenant.

E `previaTxt` — a prévia do arquivo **do operador** — continua `null`, com a ausência declarada. As
duas coisas convivem, e a copy "Não é a sua competência" está travada no contrato de tela.

## Baseline visual — e por que eu não regravei antes de olhar

O `visual-regression` do #6723 saiu vermelho e **era meu** (1,2261%, dentro do raio). A tentação
era regravar direto: eu sabia que tinha adicionado UI. Mas a lápide de 2026-08-29 é explícita —
vermelho de baseline não se explica por dedução.

Baixei o artifact, confirmei que a "baseline" do HTML é **byte-idêntica** ao `.snap` do repo (senão
eu estaria comparando imagens recomprimidas) e **olhei** o diff. Confirmou o esperado e nada além:
barra nova no topo com *"encerrou em 31/05/2026"*, tabela deslocando, card de blocos no rodapé;
sidebar, header e subnav intactos. Só então levei a [W], que autorizou o modo update escopado.

Nota de método: rodei o `snap-diff.mjs` e ele deu **31,27%**, contra os 1,2261% do gate. Não são
contraditórios — o gate usa pixelmatch perceptual (YIQ + skip de anti-aliasing + threshold 0.3) e o
`snap-diff` conta delta bruto. A métrica canônica é a do gate; a do `snap-diff` serve pra
**assinatura** (Δmax=253 = conteúdo real, não rasterização).

## O que fica aberto

1. **Seed da permission `superadmin` no CT 100** — enquanto faltar, o `UC-FSPED-09` e o 503
   ponta-a-ponta do `UC-FSF1-02` não executam em lane nenhuma. É lacuna de ambiente, não de teste.
2. **Smoke visual da barra** — pendente do merge (a tela precisa estar em produção).
3. **Nova passada de baseline a cada merge da pilha** — os três PRs mudam a mesma tela.
