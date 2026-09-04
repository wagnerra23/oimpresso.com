---
id: resources-js-pages-fiscal-sped-charter
page: /fiscal/sped
component: resources/js/Pages/Fiscal/Sped.tsx
related_prototype: prototipo-ui/cowork/fiscal-subpages.jsx
bundle_source: fiscal-page.jsx
page_id: fiscal-sped
url: /fiscal/sped
module: Fiscal
status: draft
created: 2026-05-20
owner: wagner
related_us: [US-FISCAL-010, US-FISCAL-016, US-FISCAL-017, US-FISCAL-020]
related_adrs: [0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0358-doutrina-de-teste-tenant-98-supersede-0101, 0104-processo-mwart-canonico-unico-caminho]
prototypes: [prototipo-ui/cowork/fiscal-subpages.jsx]
---

# Charter — `Fiscal/Sped`

> ⚠️ **Reconciliado em 2026-07-27** — este charter descrevia só o PR #3 (placeholder) e por isso
> **contradizia o código em produção**: declarava Non-Goal "❌ Gerador SPED real" e anti-hook
> "🚫 NÃO emitir SPED real" quando `SpedIcmsIpiGeneratorService` + a rota de download já tinham sido
> entregues em **US-FISCAL-016 (PR #8)** / **US-FISCAL-017 (PR #9)** e integrados ao MotorTributario
> em **US-FISCAL-020**. Obedecer o charter velho significaria remover código correto. Precedência
> aplicada: *teste verde > casos > charter > SPEC* (proibicoes.md §REGRA DE PRECEDÊNCIA) — o charter
> era o perdedor e foi corrigido no mesmo PR. **Nenhum Non-Goal novo foi inventado aqui**: só saíram
> os dois que o código já refutava; os demais seguem como [W] os aprovou.

## Mission

Dar à contadora (Eliana) o **panorama das últimas competências** — notas autorizadas, valor e status de
apuração — e o **download do arquivo EFD-ICMS/IPI** (layout CONFAZ Guia Prático v3.1.1, perfil A) da
competência escolhida, sem sair do cockpit Fiscal.

## Goals

**Panorama (US-FISCAL-010 · PR #3)**
1. 5 últimas competências → contagem `NfeEmissao` autorizadas + valor
2. Status estimado (mês atual = aberto · M-1 = pronto · M-2+ = entregue) — visual apenas
3. Permissão `fiscal.sped.export` no acesso à tela

**Gerador (US-FISCAL-016/017 · PR #8+#9, expandido por US-FISCAL-020)**
4. `GET /fiscal/sped/icms-ipi/{ano}/{mes}` devolve o TXT com os 23 registros canônicos dos Blocos
   0+C+E+H+9, `Content-Disposition: attachment`
5. Validação de competência (ano ≥ 2020, não-futuro, mês 1–12) antes de qualquer query
6. Guard cross-tenant explícito no Service (ADR 0093) além do global scope
7. Tributo por item resolvido pelo `MotorTributarioService` quando configurado; fallback Simples
   Nacional (CSOSN 102) quando o motor não tem regra
8. Download atrás da feature-flag `fiscal.sped_simples_only_lock` enquanto o fallback depender de
   hardcodes (audit sênior 2026-05-25 R1) — superadmin bypassa

## Non-Goals (Wagner aprova explicitamente)

- ❌ EFD-Contribuições (PIS/COFINS — arquivo separado) — PR dedicado
- ❌ Livros fiscais (Apuração ICMS/ISS, Conciliação SEFAZ × ERP) — backlog
- ❌ Workflow de validação contador → entrega SEFIN — backlog
- ❌ Bloco H com inventário real (hoje é esqueleto `IND_MOV=1`) — exige integração Stock/ProductCatalogue
- ❌ Entradas (NF-e contra o CNPJ via DF-e manifestada) — só saídas por ora
- ❌ Saldo credor anterior real no E110

## Contrato destilado — régua de geração (Onda 9 · 2026-09-03)

> Destilado do `FiscalOndasF1Test` que desceu do Cowork. O que está aqui é **lei da tela**; o
> comportamento provado vive em `Sped.casos.md` (`UC-FSF1-03`, `UC-FSF1-05`) e nos testes.

**A régua tem 4 checagens, e o servidor é o dono delas.** `SpedController::checagens` avalia e
manda `{id, ok, rotulo, motivo}` por competência; a tela **renderiza**, não decide. Isso não é
preferência de arquitetura — é o que impede a tela e o Service divergirem sobre quando um arquivo
fiscal pode sair.

| id | Regra | Onde também é recusada |
|---|---|---|
| `ano-minimo` | ano ≥ 2020 | `SpedIcmsIpiGeneratorService::validar()` |
| `nao-futura` | competência não-futura | idem |
| `fechada` | mês encerrado | `competenciaFechada()`, na mesma `validar()` |
| `trava` | `fiscal.sped_simples_only_lock` desligada, ou superadmin | `SpedController::gerar()` (503) |

**Cada checagem carrega o motivo em texto, e esse motivo é contrato de UI** — vai pro `title` do
controle desabilitado. Motivo genérico ("competência inválida") viola o contrato: o operador tem
de saber *qual* critério reprovou e *por quê*.

**O gate do download é um só.** `motivoBloqueio()` soma as 4 checagens à contagem de notas
autorizadas — o padrão `disabled` + `title` que a tela já usava foi **estendido**, não duplicado.

**A prévia do TXT é ausência DECLARADA, não amostra.** `previaTxt` é sempre `null` hoje e a tela
diz isso em texto, listando só o que o layout já fixa (v3.1.1, perfil A, `0000` com `COD_VER 018` e
`COD_FIN 0`). Ver o Non-Goal correspondente abaixo.

## Contrato destilado — o que a tela DECLARA sobre o arquivo (Onda 10 · 2026-09-04)

> Destilado do charter do Cowork (`cowork-inbox/fiscal/Sped.charter.md`, lido por ID em
> 2026-09-04). Ele tem 5 Goals; a Onda 9 entregou 1. Esta onda fecha os Goals 1 (completar),
> 4 e 5. O comportamento provado vive em `Sped.casos.md` (`UC-FSF1-01/06/07`).

**A barra de validação vive na PÁGINA.** Até a Onda 9 a régua só existia dentro do drawer, então
só via quem clicasse na lupa. Agora ela abre com a página, na competência que o operador de fato
vai gerar (a primeira **pronta**; sem nenhuma, a primeira da lista). Trocar de competência é
clicar no mês na tabela. A régua **também** aparece no drawer, pelo **mesmo componente e o mesmo
payload** — não é segunda fonte: o drawer é o passo imediatamente anterior ao download, e mandar
o operador fechá-lo pra ler por que o botão ao lado está cinza esconderia a resposta na hora em
que ela é pedida.

**O motivo do mês em aberto cita a data em que a competência ENCERRA.** Não o prazo de entrega. O
protótipo do Cowork cita ali o campo `entrega` (dia 15 do mês seguinte), e os dois são diferentes:
09/2026 encerra em 30/09 e vence 15/10. Quem lesse a data de entrega esperaria duas semanas a mais
do que precisa. O prazo segue na coluna própria da tabela.

**Estrutura e validação são MEDIDAS, nunca escritas.** `SpedReferenciaArquivoService` lê o golden
(`Modules/Fiscal/Tests/Fixtures/sped-icms-ipi-golden.txt`) a cada request e devolve os registros
por bloco, os bytes, as linhas e o SHA-256; o "nunca executado" do PVA-EFD é derivado da **ausência**
de `sped-pva-smoke.recibo.md`. Sem arquivo, a tela **declara a ausência** — não presume estrutura.

**Este cartão contradiz o protótipo de propósito, e é o único ponto em que faz isso.** O F1 diz
literalmente *"Golden file do TXT: não existe"*; o charter do Cowork é de 2026-08-24 e o golden
nasceu em 2026-09-03 ([PR #6708](https://github.com/wagnerra23/oimpresso.com/pull/6708)). Traduzir
a copy literal teria posto afirmação **falsa** na tela.

**O bypass de superadmin é ação nomeada, e a trava global não é tocada.** O superadmin lê na barra
que o perfil dele dispensa a trava fail-secure, e tem **"Reativar trava nesta sessão"** — que
recusa o download **no servidor** (`POST /fiscal/sped/trava` grava a sessão; `gerar()` devolve 503
com mensagem que aponta o clique de volta). `fiscal.sped_simples_only_lock` segue `true` em
`config/fiscal.php`: o que alterna é só o bypass de quem clica. Quem não é superadmin não vê ação
e a rota responde **403**.

⚠️ **Divergência declarada com o charter do Cowork.** O `UC-FSF1-02` de lá quer a tela abrindo
**bloqueada** até o superadmin liberar (opt-in). O `SimplesOnlyGateTest::UC-FSPED-09 · superadmin
bypassa flag` é **teste verde** e prova o contrário. Precedência: *teste verde > casos > charter* —
o default preserva o comportamento provado e a ação explícita só **restringe**. Inverter aquele
contrato fiscal é decisão de [W], que escolheu esta forma em 2026-09-04.

## Non-Goals (Wagner aprova explicitamente) — Onda 9

> ⚠️ **O primeiro Non-Goal abaixo foi RESOLVIDO em 2026-09-04 (Onda 10), e fica preservado como
> fato datado.** A pergunta estava mal formulada: a fonte do Cowork mostra que a prévia **nunca
> exigiu rodar o gerador** — o protótipo renderiza linhas fixas, e o charter dele declara Non-Goal
> "não gerar o arquivo de verdade". O que restava era mais estreito: em produção, *encenar* seria
> **fabricar**. [W] decidiu mostrar linhas de um arquivo de **referência real** (o golden),
> declaradas como layout. `previaTxt` — a prévia do arquivo **do operador** — **continua `null`**,
> e a ausência dele segue declarada: as duas coisas convivem e a copy "Não é a sua competência"
> está travada no contrato de tela pra que não se confundam. Ver `UC-FSF1-04`.

- ❌ **Prévia server-side do conteúdo do TXT** — *pendente de decisão, não recusada.* Gerar uma
  amostra fiel exigiria rodar `SpedIcmsIpiGeneratorService::gerar()` **inteiro** em request
  síncrono (não existe modo parcial: o único método público monta o arquivo completo em memória).
  Isso **contornaria a trava fail-secure** `sped_simples_only_lock`, que hoje devolve 503 no
  download justamente para o arquivo não sair. As saídas seriam job com artefato, ou um modo
  parcial no gerador — as duas são decisão, não implementação silenciosa. Enquanto não houver
  decisão, a tela declara a ausência em vez de fabricar amostra.
- ❌ **Corrigir o emitente do registro 0000** (CNPJ/IE vazios, UF fixa `SP`) — achado medido em
  2026-09-03 e documentado em `sped-icms-ipi-golden.meta.md`. É motor fiscal e muda o CFOP de toda
  operação; a trava já cobre o risco.

## Anti-hooks

- 🚫 **NÃO deixar a rota `fiscal.sped.trava` aceitar quem não é superadmin, nem fazê-la escrever em
  `config/fiscal.php`** — ela alterna o bypass de UMA sessão e só consegue restringir. Endpoint que
  aceita quem não deveria é superfície que a próxima mudança transforma em buraco; e uma ação de
  tela que escrevesse na flag global viraria exatamente a "configuração escondida" que o Goal 2
  veio eliminar, só que com o sinal invertido.
- 🚫 **NÃO liberar a flag `sped_simples_only_lock` sem antes eliminar os hardcodes de fallback** — o TXT
  vai pro Fisco; CFOP/CST errado em venda interestadual expõe a multa (audit R1). Default é `true`
  (fail-secure) e a decisão de desligar é do [W].
- 🚫 **NÃO usar CFOP fixo** — 5xxx é operação interna e 6xxx interestadual (Convênio S/Nº de 1970); o
  CFOP sai da UF origem × destino. O hardcode 5102 já gerou SPED inválido uma vez.
- 🚫 **NÃO gerar sem o guard cross-tenant** — `RuntimeException` antes de qualquer query (ADR 0093).
- 🚫 NÃO sugerir prazo de entrega via cron auto (legal/contador decide; o prazo da EFD é fixado por UF —
  o painel usa dia 15 só como heurística visual).
- 🚫 **NÃO declarar o gerador "validado" sem smoke no PVA-EFD** — os testes de bloco atuais são
  source-grep (ver `Sped.casos.md` §Backlog), não provam o conteúdo do arquivo. _(Desde 2026-09-03
  o `UC-FSF1-05` confere estrutura, blocos e contadores contra um golden real — mas o PVA-EFD é
  ferramenta externa e continua sem smoke, e o próprio golden mostra por que ele recusaria hoje.)_
- 🚫 **NÃO escrever à mão os registros de cada bloco, nem o estado da validação externa** — as
  duas superfícies são medidas no arquivo pelo `SpedReferenciaArquivoService`. Uma lista escrita
  continuaria "certa" na tela depois de o gerador parar de emitir um registro, e um "golden file:
  não existe" escrito vira falso no dia em que ele nasce — que foi exatamente o que aconteceu com
  a copy do protótipo, um dia depois do charter do Cowork.
- 🚫 **NÃO apresentar o arquivo de referência como sendo a competência do operador** — o `0000` do
  golden declara `CI TENANT 98 (FICTICIO)` como emitente, e a tela repete esse nome LIDO do
  arquivo, nunca um rótulo "fictício" escrito à mão (que viraria mentira se o golden fosse
  regerado de outro tenant). Toda superfície que o exibir diz que é referência de layout.
- 🚫 **NÃO transformar a amostra no arquivo inteiro, nem preencher `previaTxt` com ela** — a
  amostra é uma linha por registro distinto e é MENOR que o arquivo (há teste para os dois). O
  `previaTxt` é a prévia da competência **do operador**, e ela continua ausente e declarada;
  colapsar as duas faria a contadora imprimir um arquivo de outro emitente achando que é o dela.
- 🚫 **NÃO mover a decisão das 4 checagens para o cliente** — a régua é avaliada no servidor
  (`SpedController::checagens`) e a tela só renderiza. Regra duplicada no `.tsx` diverge da
  `validar()` do Service no primeiro ajuste, e aí a tela libera o que o servidor recusa (ou o
  contrário) sem ninguém perceber.
- 🚫 **NÃO editar o golden à mão** — ele é saída capturada do gerador, com SHA declarado. Se o
  gerador mudar de propósito, o golden se **regera** (receita no `.meta.md`); editar o arquivo pra
  fazer o teste passar é reescrever o baseline pra ficar verde.
