---
id: resources-js-pages-fiscal-nfse-casos
casos: NFS-e Emitidas · /fiscal/nfse
irmaos: Nfse.charter.md (lei) · memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md (§6 CU)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-09-04"
last_run_ci: "0 UC executado nesta corrida — os 4 UC herdam testes que JÁ existem; veredito pendente das lanes PHP / Pest (NfeBrasil · MySQL) e Pest Fiscal"
related_us: [US-FISCAL-005, US-NFE-069]
---

# Casos de Uso & Aceite — NFS-e Emitidas

> **Revalidação `last_run` 2026-09-01 — Onda 1 Fiscal (saneamento `fx-*` → DS):** a tela mudou
> em **camada de apresentação apenas** — 5 `fx-chip` → `<Button>`, `fx-search` + `<input
> type="search">` → `<Input>`, `fx-filters` → `<Inline>`, e o campo de competência saiu de
> `<input type="month" className="fx-search">` com `style` inline para `<Input type="month">`
> (a primitiva já dá borda, raio e altura). Conferi os 4 UC um a um: **UC-FNFSE-01/02/03 são
> backend** (escopo multi-tenant, gate `fiscal.nfse.view`, competência malformada). O
> **UC-FNFSE-04 é o único que fala do cabeçalho** e foi conferido de perto: os testes dele
> assertam o **shape do `counts`** (6 chaves) e o render Inertia com `filters/counts` — tudo no
> Controller, que este PR não toca. Dos 6 indicadores, 5 vivem nos chips (preservados, com o
> `contrato-de-tela` verde nas 5 copies) e o faturamento vive no `crumb`, intocado.
> **Nenhum teste foi re-executado** (Pest = CT 100); os vereditos seguem como estavam.

> **Revalidação `last_run` 2026-08-28 — o que foi conferido:** este PR muda a tela em **um único ponto**: o atributo `data-contract="fiscal-nfse-filters"` no wrapper, âncora do mapa [`fiscal-nfse.map.json`](../../../../memory/requisitos/Fiscal/fiscal-nfse.map.json). Conferi o diff do `.tsx` contra a lista de UC deste arquivo — **nenhum UC depende de atributo de DOM**, logo nenhum aceite mudou. **Nenhum teste foi re-executado** nesta revalidação (Pest = CT 100); os vereditos seguem como estavam.

> Persona: **Eliana [E] (contadora)** — confere as notas de **serviço** do mês sem abrir o módulo NFSe legado.
>
> **Âncora:** `CU-FISC-04`, `CU-FISC-12` e `CU-FISC-13` do
> [SDD §6](../../../../memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md). Os UC derivam do **CU**, nunca do `.tsx`.
>
> **Status:** ✅ provado por teste verde que cita o UC · 🧪 tem teste, **veredito pendente da lane** · ⬜ não verificado · ❌ quebrou.

## Força do veredito

| Teste | Lane | Bloqueia merge? |
|---|---|---|
| `NfseCockpitMultiTenantTest` | `PHP / Pest (NfeBrasil · MySQL)` | ✅ **sim** — required no [baseline](../../../../governance/required-checks-baseline.json) e na allowlist do workflow |
| `NfseCockpitControllerTest` | `Pest Fiscal` (**pula** em SQLite) + suíte noturna CT 100 | ❌ **não** — advisory |
| `fiscal-densidade.test.tsx` | `Fiscal Densidade Gate` (vitest/jsdom) | ❌ **não** — advisory |
| `SeparacaoNotasDaVendaContratoTest` | `PHP / Pest (NfeBrasil · MySQL)` (allowlist do workflow) | ✅ **sim** — mesma lane required; os casos com banco pulam fora do MySQL |

## Rastreabilidade

| UC | O que defende | Prio | CU (SDD §6) | Teste que o cita | Status |
|---|---|---|---|---|---|
| UC-FNFSE-01 | isolamento da listagem | `[must]` `[T0]` | CU-FISC-12 | `NfseCockpitMultiTenantTest` | 🧪 |
| UC-FNFSE-02 | gate de permissão da tela | `[must]` `[T0]` | CU-FISC-13 | `NfseCockpitControllerTest` | 🧪 |
| UC-FNFSE-03 | competência inválida não derruba | `[must]` | CU-FISC-04 | `NfseCockpitControllerTest` | 🧪 |
| UC-FNFSE-04 | os 6 indicadores da competência | `[should]` | CU-FISC-04 | `NfseCockpitControllerTest` | 🧪 |
| UC-FNFSE-05 | a densidade escolhida acompanha a navegação | `[should]` | **—** (ver nota no caso) | `fiscal-densidade.test.tsx` | 🧪 |
| UC-FNFSE-06 | a venda se separa em mercadoria e serviço; as partes somam o total | `[must]` | **—** (US-NFE-069) | `SeparacaoNotasDaVendaContratoTest` | 🧪 |
| UC-FNFSE-07 | desconto e acréscimos rateados pelo bruto; frete na NF-e | `[must]` | **—** (US-NFE-069) | `SeparacaoNotasDaVendaContratoTest` | 🧪 |
| UC-FNFSE-08 | venda e natureza de outra empresa não entram | `[must]` `[T0]` | **—** (US-NFE-069) | `SeparacaoNotasDaVendaContratoTest` | 🧪 |
| UC-FNFSE-09 | natureza pendente não decide imposto | `[should]` | **—** (US-NFE-069) | `SeparacaoNotasDaVendaContratoTest` | 🧪 |

---

## UC-FNFSE-01 — A lista nunca mostra NFS-e de outro business `[must]` `[T0]`

**Dado** notas de serviço do business ativo e de outro business
**Quando** a lista carrega
**Então** só as do business ativo aparecem.

- **Regressão que defende:** vazamento cross-tenant Tier 0 ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)). A contraparte deste teste usa um business **semeado** (não um id fictício), porque esta tabela tem chave estrangeira para o cadastro de empresas — detalhe que já derrubou o teste irmão do certificado.
- **Teste:** `Modules/Fiscal/Tests/Feature/NfseCockpitMultiTenantTest.php` — `it('UC-FNFSE-01 · NfseEmissao HasBusinessScope esconde cross-tenant da listagem do cockpit Nfse')`
- **Status:** 🧪 lane **required**; veredito pendente.

## UC-FNFSE-02 — A tela exige `fiscal.nfse.view` `[must]` `[T0]`

**Dado** um usuário sem `fiscal.nfse.view` e sem `superadmin`
**Quando** abre `/fiscal/nfse`
**Então** recebe 403.

- **Âncora de contrato:** `R-FISCAL-003` do [SPEC.md](../../../../memory/requisitos/Fiscal/SPEC.md) §3.
- **Teste:** `Modules/Fiscal/Tests/Feature/NfseCockpitControllerTest.php` — `it('UC-FNFSE-02 · GET /fiscal/nfse aborta 403 sem permission superadmin nem fiscal.nfse.view')`
- **Status:** 🧪 advisory + noturna.

## UC-FNFSE-03 — Competência malformada não derruba a tela `[must]`

**Dado** que a contadora chega com um mês inválido na URL
**Quando** a tela processa o filtro
**Então** ela ignora o valor inválido e responde normalmente — sem erro de servidor.

- **Regressão que defende:** a competência vem da URL e é interpretada como data; entrada malformada virava 500. Este é o tipo de quebra que só aparece quando alguém compartilha um link editado à mão.
- **Teste:** `NfseCockpitControllerTest` — `it('UC-FNFSE-03 · filtro mes invalido nao crasha (ignora silenciosamente)')`
- **Status:** 🧪 advisory + noturna.

## UC-FNFSE-04 — O cabeçalho traz os 6 indicadores da competência `[should]`

**Dado** a tela carregada com uma competência
**Quando** a contadora lê o topo
**Então** encontra quantas notas de serviço existem no total, quantas foram autorizadas, quantas foram rejeitadas, quantas ainda estão em processamento, quantas foram canceladas e o faturamento de serviço do período.

- **Regressão que defende:** sumir com um indicador no refactor e a conferência do mês virar contagem manual.
- **Teste:** `NfseCockpitControllerTest` — `it('UC-FNFSE-04 · counts shape canon — 6 chaves obrigatorias')` e `it('UC-FNFSE-04 · GET /fiscal/nfse renderiza Inertia Fiscal/Nfse com filters/counts canon')` · contrato de estados em `NfseCockpitMultiTenantTest` — `it('UC-FNFSE-04 · STATUS constants estão definidas no Model — Controller depende delas')`
- **Status:** 🧪 advisory + **required** (o terceiro está na lane que bloqueia).

---

## UC-FNFSE-05 — A densidade escolhida acompanha a navegação `[should]`

**Dado** a contadora conferindo a competência na lista de NFS-e
**Quando** ela escolhe **Compacto** para ver mais linhas por tela, e depois abre o Cockpit ou a lista de NF-e
**Então** a tabela de lá já abre compacta — a preferência é do operador, não da tela.

- **Âncora:** na fonte de design as três telas de notas são a **mesma função** (`FxNotasPage`,
  chamada com `preset` diferente — [`fiscal-page.jsx:346,541-543`](../../../../prototipo-ui/cowork/Wagner/fiscal-page.jsx)),
  e a escolha persiste em `fxLS("oimpresso.fiscal.densidade")` (`:358,363`). O compartilhamento
  é grátis lá porque há um dono só; aqui a produção separou em três arquivos.
- **Estado que corrige (medido em `origin/main` d23bc3df34):** esta tela não tinha o controle
  (`fx-density` = 0), e o Cockpit — o único que tinha — guardava a escolha em `useState`, que
  morre ao trocar de tela.
- **Regressão que defende:** alguém reintroduzir estado local de densidade numa das telas. A
  preferência volta a morrer na navegação, e o sintoma é silencioso — cada tela "funciona".
- **Teste:** `tests/js/fiscal-densidade.test.tsx` —
  `it('UC-FNFSE-05 · escolher Relaxado na NFS-e faz a NF-e abrir relaxada (o caminho de volta)')`,
  `it('UC-FNFSE-05 · storage indisponível não derruba a tela (janela privada)')` e
  `it('UC-FNFSE-05 · todo valor de densidade tem classe correspondente no CSS')`.
- **Mordida provada (contrafactual 2026-09-04):** devolver `useState('comfort')` a esta tela — o
  defeito exato que o Cockpit tinha — derruba **2** casos, com a mensagem nomeando o sintoma
  (*"a NF-e ignorou a escolha feita na NFS-e"*); divergir a chave da fonte de design derruba **1**.
  Restaurado, 6/6 verde.
- **Por que `—` na coluna CU:** os CU do SDD §6 tratam do que a pessoa fiscal **faz** (isolar
  tenant, conferir competência, ler indicadores); nenhum trata de preferência de exibição.
  Ancorar num CU plausível fecharia a lacuna do painel derivado sem lastro (LC-11).
- **O que NÃO cobre:** a navegação HTTP real entre as rotas — o jsdom não a faz. A troca de
  página com o Inertia no meio é olho humano no smoke (R1).
- **Status:** 🧪 advisory (vitest/jsdom — não toca banco, logo não vira `skipped`).

---

## Separação da venda em NF-e + NFS-e (US-NFE-069 · UC-FLX-27 / UC-FLX-31)

> **Por que aqui:** a separação produz a parte que esta tela vai listar (a NFS-e da venda). A
> parte NF-e já existe; a novidade é o lado serviço. `Sells/Show.casos.md` e `Fiscal/Nfe.casos.md`
> estão em PRs abertos (#9637, #9629, #9632) — este bloco evita os três.
>
> **Escopo do PR-1:** só o serviço que separa (`Modules/NfeBrasil/Services/SeparacaoNotasDaVenda.php`).
> Nada emite: `NfeService::recusarItensDeServico` segue recusando NF-e com item de serviço e
> `NfseController::emitirParaTransaction` não foi tocado. Disparo no Faturado e emissão ficam em aberto.
>
> **Por que `—` na coluna CU:** os CU do SDD §6 tratam da listagem; a âncora destes casos é o aceite
> "separar as linhas" da US-NFE-069 no [SPEC do NfeBrasil](../../../../memory/requisitos/NfeBrasil/SPEC.md).

## UC-FNFSE-06 — A venda se separa em mercadoria e serviço, e as partes somam o total `[must]`

**Dado** uma venda com linhas de produto que o contador decidiu como `mercadoria` e como `servico`
**Quando** a separação roda
**Então** as linhas de mercadoria ficam na parte NF-e, as de serviço na parte NFS-e (com o
`codigo_servico` gravado na natureza), e a soma das duas partes é o `final_total`, centavo a centavo.

- **Regressão que defende:** nota que soma diferente da venda (REGRA MESTRE de valor).
- **Prova dupla:** cada exemplo tem o esperado escrito à mão e é refeito por um segundo caminho
  em reais, sem banco (`separacaoEsperada`).
- **Teste:** `Modules/NfeBrasil/Tests/Feature/SeparacaoNotasDaVendaContratoTest.php` —
  `it('UC-FNFSE-06 · a venda se separa em mercadoria e serviço e as duas partes somam o final_total, centavo a centavo')`
  (3 vendas: só mercadoria, só serviço, mista) e
  `it('UC-FNFSE-06 · lê a natureza e o código de serviço que o contador gravou')` (com banco).
- **Status:** 🧪 veredito pendente da lane.

## UC-FNFSE-07 — Desconto e acréscimos rateados pelo bruto; frete inteiro na NF-e `[must]`

**Dado** uma venda mista com desconto, frete ou acréscimo
**Quando** a separação roda
**Então** o frete vai inteiro para a parte NF-e (é o `vFrete` que a NF-e já monta); o resto
(`final_total − Σ bruto − frete`) é rateado proporcional ao bruto de cada parte, a parte serviço
arredondada a 1 centavo e a parte mercadoria absorvendo a sobra. Sem mercadoria, o frete vai para a
NFS-e e a separação avisa (`frete_sem_mercadoria`).

- **Regra decidida por [W] em 2026-10-21** (regra A, contra desconto todo na mercadoria ou todo no serviço): `SeparacaoNotasDaVenda::REGRA_RATEIO`.
- **Teste:** `it('UC-FNFSE-07 · o desconto é rateado pelo bruto e o frete vai inteiro para a NF-e')`,
  `it('UC-FNFSE-07 · o centavo que não divide fica na mercadoria, nunca some')` e
  `it('UC-FNFSE-07 · venda só de serviço com frete leva o frete para a NFS-e e avisa')`.
- **Status:** 🧪 veredito pendente da lane.

## UC-FNFSE-08 — Venda e natureza de outra empresa não entram `[must]` `[T0]`

**Dado** uma venda do tenant 99 e uma natureza gravada pelo 99 para um produto do 98
**Quando** o 98 pede a separação
**Então** a venda do 99 é recusada (não encontrada) e a natureza do 99 não classifica a linha do 98.

- **Regressão que defende:** vazamento cross-tenant ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
  O caso positivo (o 99 separa a própria venda) prova que a recusa vem do tenant, não do dado.
- **Teste:** `it('UC-FNFSE-08 · venda de outra empresa é recusada')` e
  `it('UC-FNFSE-08 · natureza gravada por outra empresa não classifica a linha')`.
- **Status:** 🧪 veredito pendente da lane (pula fora do MySQL — leia assertions).

## UC-FNFSE-09 — Natureza pendente não decide imposto `[should]`

**Dado** um produto com natureza `pendente`
**Quando** a separação roda
**Então** a linha fica na parte mercadoria (como a emissão faz hoje) e o produto volta listado em
`pendentes` — a separação não escolhe ICMS nem ISS (D-NATUREZA).

- **Bloqueio:** o aceite da US-NFE-069 pede que pendente **bloqueie** o faturamento — a separação
  só lista; quem bloqueia é a emissão das duas notas (UC-FNFSE-12).
- **Teste:** `it('UC-FNFSE-09 · natureza pendente fica na mercadoria e volta listada, sem decidir imposto')`.
- **Status:** 🧪 veredito pendente da lane.

## UC-FNFSE-10 — A NF-e da venda separada leva só a parte mercadoria `[must]`

**Dado** a venda mista do UC-FNFSE-07 (3 × 33,33 + instalação 150,00, desconto 17,50, frete 20,00, total 252,49)
**Quando** a NF-e é emitida com a venda separada (botão "Emitir NF-e + NFS-e")
**Então** a nota tem só a linha de mercadoria, vNF = pagamento = **112,99**, e guarda na metadata a regra
e o total da parte serviço (**139,50**); NF-e + NFS-e somam o `final_total`, centavo a centavo.
Sem a separação, a NF-e da mesma venda segue recusada por ter serviço (R-NFE-034).

- **Teste:** `it('UC-FNFSE-10 · a NF-e da venda separada leva só a parte mercadoria, com o total e o frete dela')`
  e `it('UC-FNFSE-10 · sem a separação a NF-e continua recusando o item de serviço (controle)')` em
  `Modules/NfeBrasil/Tests/Feature/EmissaoSeparadaContratoTest.php`.
- **Decomposição no XML:** a parte de 112,99 sai com vFrete 20,00 e vDesc 7,00 (R-NFE-025, #9738). Até
  2026-10-21 o `montarItensNfe` compensava desconto com frete e ela saía com vFrete 13,00 e vDesc 0.
- **Status:** 🧪 veredito pendente da lane.

## UC-FNFSE-11 — A NFS-e da venda separada leva só a parte serviço `[must]`

**Dado** a mesma venda
**Quando** a NFS-e é pedida com `separada`
**Então** o valor dos serviços é **139,50** e o código LC 116 é o que o contador gravou na natureza do
produto (`24.01`); o valor mandado no corpo é ignorado. Sem `separada`, a NFS-e segue com o `final_total`.
Serviços com códigos diferentes recusam (uma NFS-e leva um código só); NFS-e já em curso para a venda recusa (409).

- **Teste:** `it('UC-FNFSE-11 · a NFS-e da venda separada leva o total da parte serviço e o código do contador, ignorando o valor do corpo')`
  e `it('UC-FNFSE-11 · sem a separação a NFS-e segue com o final_total da venda (controle)')`.
- **Status:** 🧪 veredito pendente da lane.

## UC-FNFSE-12 — Natureza pendente bloqueia; a prévia exige permissão e respeita o tenant `[must]` `[T0]`

**Dado** uma venda com produto de natureza `pendente`
**Quando** pede as duas notas
**Então** a NFS-e volta 422 `natureza_pendente` sem montar payload e a NF-e separada recusa.
A prévia das duas partes (`GET /nfe-brasil/api/transactions/{tx}/separacao`) volta 403 sem `nfse.emit`
e 404 para venda de outra empresa (a dona enxerga a própria).

- **Teste:** `it('UC-FNFSE-12 · natureza pendente bloqueia as duas notas da venda separada')` e
  `it('UC-FNFSE-12 · a prévia das duas partes exige permissão de NFS-e e não lê venda de outra empresa')`.
- **Status:** 🧪 veredito pendente da lane (pula fora do MySQL — leia assertions).

---

## Backlog de casos (sem id — viram UC quando ganharem contrato + teste)

- **[BACKLOG · ⬜ sem teste] Os chips filtram por estado da nota de serviço** — autorizadas, rejeitadas, em processamento (rascunho + processando), canceladas. _Existe no Controller; sem teste do resultado filtrado._
- **[BACKLOG · ⬜ sem teste] A busca aceita número, código de verificação, nome e documento do tomador** — sem teste do resultado.
- **[BACKLOG · ⬜ sem teste] O filtro de competência restringe as linhas ao mês escolhido** — só o caminho de mês **inválido** tem teste; o caminho feliz não.
- **[BACKLOG · ⬜ sem teste · débito de schema] O município da prestação aparece na linha** — a coluna **não existe** no schema em produção (duelo de duas migrations para a mesma tabela, resolvido revertendo o Controller para o schema antigo e traduzindo os estados). Hoje o campo volta vazio por desenho. Ver SDD §5.2.
- **[BACKLOG · ⬜ sem teste] Venda lançada com produto, mas o cliente pede nota de serviço** `[V0]` — pedido da
  Fixar, 2026-10-28. Dado uma venda de placas sob encomenda lançada com itens cadastrados como produto, com
  tributação de produto, que ficou dias em aberto, Quando o cliente fecha e pede a nota de serviço para pagar,
  Então o sistema mostra, antes de emitir, que esses itens saem em NF-e porque a natureza fiscal deles é
  mercadoria, e oferece o caminho certo: se a operação é mesmo serviço sob encomenda, o contador corrige a
  natureza do produto (serviço, com o item da LC 116 e a fonte) e a venda passa a sair em NFS-e sem ser
  refeita, porque a emissão lê a natureza na hora de faturar (`SeparacaoNotasDaVenda`); se a operação é venda
  de mercadoria, a nota continua sendo NF-e, mesmo que o cliente prefira a de serviço. A venda mostra a
  natureza de cada item desde o lançamento, para o vendedor ver antes de o cliente pedir a nota.
  **Depende do [W]:** pela D-NATUREZA ([W] 2026-10-06, US-NFE-068 · R-NFE-034), a natureza é do produto e só o
  contador decide. Falta decidir se quem fatura pode trocar a natureza só daquele item da venda, com
  justificativa e registro, para o produto que às vezes é revenda e às vezes encomenda. A proposta é manter só
  o contador, no cadastro, com o aviso na venda e o atalho para ele; o caminho seguro para o item misto são
  dois cadastros (mercadoria e serviço).
  Hoje: a natureza por produto existe (`nfe_naturezas_produto`) e a NF-e recusa item de serviço
  (`NfeService::recusarItensDeServico`); a separação da venda já calcula (UC-FNFSE-06..12), mas a NFS-e a
  partir da venda ainda depende da US-NFE-060; a venda não mostra a natureza dos itens nem permite trocá-la.
  Fontes: Bling decide pelo tipo do cadastro, Omie pelo documento (pedido vira NF-e, ordem de serviço vira
  NFS-e), Conta Azul exige duas vendas; nenhum documenta a troca numa venda já lançada. Regra fiscal: decide a
  natureza real da operação, não o pedido do cliente (LC 116/2003, itens 13.05 e 24.01; Súmula 156 do STJ;
  SEFAZ-PR, Consulta 36/2010). Mexe em imposto: Regra Mestre. Verificado contra as regras do [W] em 2026-10-28.

## Como rodar a suíte

1. **Lane required:** `PHP / Pest (NfeBrasil · MySQL)` roda `NfseCockpitMultiTenantTest`.
2. **Advisory + noturna:** `Pest Fiscal` (SQLite, pula) e a suíte noturna CT 100 (MySQL, roda) para `NfseCockpitControllerTest`.
3. ⛔ **Nunca local** ([ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).

## Trilha do tempo
- 2026-09-04 · [C] `UC-FNFSE-05` — a tela ganha o controle de densidade, que nasce
  compartilhado com Cockpit e NF-e (`_components/DensidadeToggle.tsx`). Os 4 UC anteriores
  seguem intactos: conferi um a um — todos assertam backend e nenhum toca a tabela.

- 2026-07-03 · [CC] stub criado no Passo 3 do programa de ondas — **0 UC**.
- 2026-07-27 · [CC] `sdd-from-source` (Onda 1 / S2): **4 UC** derivados do §6 do SDD; todos herdam testes existentes. Registrado que o cabeçalho do `NfseCockpitControllerTest` ainda descreve a rota como quebrada em produção — texto **desatualizado** (a correção pelo schema antigo foi aplicada); não editado aqui para não misturar escopo, reportado no session log.
- 2026-10-20 · [C] `UC-FNFSE-06..09` — separação da venda em NF-e + NFS-e (US-NFE-069 PR-1, só o serviço que separa; rateio proposto, pendente [W]).
- 2026-10-21 · [C] `UC-FNFSE-10..12` — emissão das duas notas pelo botão "Emitir NF-e + NFS-e" do drawer (US-NFE-069 PR-2); rateio decidido por [W] (regra A).
- 2026-10-28 · [F+C] backlog "Venda lançada com produto, mas o cliente pede nota de serviço" (Fixar), sem id e
  sem teste; a troca da natureza no item da venda fica como decisão [W] (D-NATUREZA). Nenhum UC muda.
