---
id: resources-js-pages-fiscal-nfse-casos
casos: NFS-e Emitidas · /fiscal/nfse
irmaos: Nfse.charter.md (lei) · memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md (§6 CU)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-09-04"
last_run_ci: "0 UC executado nesta corrida — os 4 UC herdam testes que JÁ existem; veredito pendente das lanes PHP / Pest (NfeBrasil · MySQL) e Pest Fiscal"
related_us: [US-FISCAL-005]
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

## Rastreabilidade

| UC | O que defende | Prio | CU (SDD §6) | Teste que o cita | Status |
|---|---|---|---|---|---|
| UC-FNFSE-01 | isolamento da listagem | `[must]` `[T0]` | CU-FISC-12 | `NfseCockpitMultiTenantTest` | 🧪 |
| UC-FNFSE-02 | gate de permissão da tela | `[must]` `[T0]` | CU-FISC-13 | `NfseCockpitControllerTest` | 🧪 |
| UC-FNFSE-03 | competência inválida não derruba | `[must]` | CU-FISC-04 | `NfseCockpitControllerTest` | 🧪 |
| UC-FNFSE-04 | os 6 indicadores da competência | `[should]` | CU-FISC-04 | `NfseCockpitControllerTest` | 🧪 |
| UC-FNFSE-05 | a densidade escolhida acompanha a navegação | `[should]` | **—** (ver nota no caso) | `fiscal-densidade.test.tsx` | 🧪 |

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
  chamada com `preset` diferente — [`fiscal-page.jsx:346,541-543`](../../../../prototipo-ui/cowork/fiscal-page.jsx)),
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

## Backlog de casos (sem id — viram UC quando ganharem contrato + teste)

- **[BACKLOG · ⬜ sem teste] Os chips filtram por estado da nota de serviço** — autorizadas, rejeitadas, em processamento (rascunho + processando), canceladas. _Existe no Controller; sem teste do resultado filtrado._
- **[BACKLOG · ⬜ sem teste] A busca aceita número, código de verificação, nome e documento do tomador** — sem teste do resultado.
- **[BACKLOG · ⬜ sem teste] O filtro de competência restringe as linhas ao mês escolhido** — só o caminho de mês **inválido** tem teste; o caminho feliz não.
- **[BACKLOG · ⬜ sem teste · débito de schema] O município da prestação aparece na linha** — a coluna **não existe** no schema em produção (duelo de duas migrations para a mesma tabela, resolvido revertendo o Controller para o schema antigo e traduzindo os estados). Hoje o campo volta vazio por desenho. Ver SDD §5.2.

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
