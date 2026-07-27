---
id: sessions-2026-07-27-sdd-fiscal-nfe
date: "2026-07-27"
topic: "SDD Fiscal — chip S2 da Onda 1 (passo 5): SDD criado do zero + 37 UC nas 7 telas"
authors: [C]
module: Fiscal
related_docs:
  - ../requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md
  - ../requisitos/_Governanca/programa-ondas/passo-5-sdd-por-modulo.md
related_adrs:
  - 0351-sdd-from-source
  - 0352-errata-0351-venue-distiller-citacao-taxonomia
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0062-separacao-runtime-hostinger-ct100
---

# SDD Fiscal — chip S2 da Onda 1 do passo 5

> Alvo: `Fiscal/Nfe` como tela-âncora + as 6 irmãs. **Ramo sem precedente:** o módulo não tinha SDD
> (`CU no SDD = 0`); o piloto do Produto só exercitou *"SDD existe → preenche §5.3/§6"*.
> Este log persiste o **orçamento** e as **lições de mecanismo** — devolutiva de chat evapora e
> torna o custo/tela inauditável.

## 1. Placar da porta viva — antes → depois

`node scripts/governance/requisitos-status.mjs Fiscal`

| Elo | Antes | Depois |
|---|---:|---:|
| US no SPEC | 23 | 23 |
| **CU no SDD** | **0** | **16** |
| Telas `.tsx` | 7 | 7 |
| Telas com `casos.md` | 7 | 7 |
| **UC declarados** | **0** | **37** |
| **Lacunas acusadas** | **7** (`casos.md existe mas não declara nenhum UC`) | **1** (`CU-FISC-16 sem UC` — **intencional**, ver §4) |

`node scripts/casos-coverage-guard.mjs` → **`ok: true`** · violações **novas = 0** · débito total
**213** (baseline 220) · `orphan_ucs` do Fiscal = **0** · `metadata_issues` 0 · `stale_cases` 0 ·
G-7 (`status_lies`/`unverified`/`stale`) 0.

## 2. Artefatos tocados — **27 arquivos** (contados em `git status --porcelain`)

> 1 SDD novo · 7 `casos.md` · 15 testes carimbados · 1 teste novo · 2 charters · 1 session log.
> ⚠️ Esta worktree é **compartilhada** com as sessões irmãs S1 (Compras) e S3 (Ponto): o
> `git status` mostra **+23 arquivos que não são deste chip**. O isolamento por *path* segurou
> (zero overlap), mas o isolamento por *worktree* não existe — o parent precisa separar na hora
> de consolidar. É a peça que o §Regras duras de isolamento do passo 5 não cobre.

| Arquivo | O que |
|---|---|
| `memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md` | **novo** — §0–§11, 10 fluxos (F1–F10), 16 CU, 4 dívidas medidas, 10 riscos |
| `resources/js/Pages/Fiscal/{Nfe,Cockpit,Nfse,Dfe,Eventos,Config,Sped}.casos.md` | 7 reescritos: stubs de 37-41 linhas com **0 UC** → **37 UC** ancorados + backlog honesto |
| `Modules/Fiscal/Tests/Feature/*.php` (15 arquivos) | **63** descrições de teste carimbadas com o UC-id (G-2) — só o texto do `it(...)`, zero mudança de comportamento (diff 63+/63−) |
| `Modules/Fiscal/Tests/Feature/GatesPermissaoFiscalTest.php` | **novo** — 4 gates de permissão sem teste HTTP + 4 controles anti-vácuo |
| `resources/js/Pages/Fiscal/Nfe.charter.md` · `Nfse.charter.md` | reconciliação **factual** (Fase 2.6) — nenhuma intenção tocada |

**Não tocado de propósito:** `nfebrasil-pest.yml` (§5) · `SPEC.md` (§6) · `modules-pest.yml` ·
baseline global · `scripts/governance/**`.

## 3. Achados — com varredura CONTADA

| # | Achado | Varredura | Onde ficou |
|---|---|---|---|
| A1 | **4 props Inertia servem dado inventado** a uma tela fiscal, + 4 superfícies mockadas fora de prop nomeada | `grep -n "Mock'\s*=>" Modules/Fiscal/Http/Controllers/*.php` → **4** props · `grep -n "function mock"` → **8** métodos · `TODO[CL]` → **9** | SDD §5.4.1 · `CU-FISC-16` ⬜ · `[BACKLOG]` em 3 `casos.md` · **decisão [W]** |
| A2 | **Charter do `Sped` proíbe o que o código faz** — Non-Goal "sem gerador real" × `SpedIcmsIpiGeneratorService` entregue (US-016/017) | leitura das 2 fontes | SDD §5.4.2 · **decisão [W]** (intenção) |
| A3 | **Charter do `Config` proíbe edição inline** — e a tela tem 2 formulários de mutação | `grep -n "useForm\|<form" resources/js/Pages/Fiscal/Config.tsx` → `uploadForm` + `ambienteForm` | SDD §5.4.3 · **decisão [W]** (intenção) |
| A4 | **`Nfse.charter.md` cita coluna inexistente** `cpf_cnpj_tomador` | `grep -rn "cpf_cnpj_tomador" Modules/ resources/js/ database/` = **3**, e **2 delas são comentários dizendo que ela não existe** | **corrigido** (fato) + recibo no charter |
| A5 | **`Nfe.charter.md` declara 2 protótipos inexistentes** | `ls` nos 2 paths → *No such file* · `find prototipo-ui -maxdepth 2 -iname "*fiscal*"` → **0** | **corrigido** (fato) + recibo no charter |
| A6 | **`JUNIT_MODULE_LANES` do `anchor-lint.mjs` está STALE** — lista `Financeiro/Jana/NfeBrasil` mas a lane `nfebrasil-pest.yml` ratchetou `Modules/Fiscal/Tests/**` em 2026-07-17 | leitura de `anchor-lint.mjs` L442 × allowlist do workflow L172-175 | **reportado, não consertado** — `scripts/governance/**` é área proibida deste chip |
| A7 | **`requisitos-status.mjs` não enxerga teste em `Modules/`** — `listarTestes()` percorre só `tests/` e `e2e/` | `sed -n '99,110p' scripts/governance/requisitos-status.mjs` | **reportado** — por isso o painel mostra `UC com teste = 0` enquanto o `casos-gate` (que varre `Modules/`) dá `orphan_ucs = 0` |
| A8 | **Nenhum teste `[V0]` do SPED bloqueia merge** | allowlist do `nfebrasil-pest.yml` = 4 arquivos, nenhum de SPED | SDD §9 R7 · `Sped.casos.md` §Força do veredito |
| A9 | 2 rótulos desatualizados: nome do teste diz "6 chaves" e asserta **7**; cabeçalho do `NfseCockpitControllerTest` ainda diz "ROTA QUEBRADA EM PROD" (a correção pelo schema antigo foi aplicada) | leitura | **reportado, não corrigido** (escopo) |

## 4. A lacuna que ficou aberta É o resultado, não o resto

`CU-FISC-16` (procedência do dado) **não virou UC com id de propósito.** Não há contrato em 2 fontes
dizendo qual é a saída certa — marcar procedência × esconder atrás de flag × declarar Non-Goal são
três decisões de produto diferentes. UC com id sem teste é **órfão**, e o G-2 do `casos-gate`
(required) **bloqueia o merge de quem for atendê-lo** ([proibicoes §5](../proibicoes.md) 2026-07-16).
Preferi **1 lacuna nomeada** a 1 UC órfão que trava o próximo PR.

## 5. Por que a lane **não** foi tocada (e por que isso não é omissão)

O chip previa "adicionar o arquivo de teste na allowlist". Medido, o caso do Fiscal é diferente:

1. **Não há "verde impossível" aqui.** `phpunit.xml` lista `./Modules/Fiscal/Tests/Feature` e o
   `scripts/tests/shards-plan.mjs --roots tests,Modules` enumera o diretório → os testes **rodam**
   na suíte noturna CT 100 (MySQL real). E `modules-pest.yml` (matrix `Fiscal`) roda o diretório
   inteiro em todo PR — em SQLite, onde a maioria **pula**.
2. **A allowlist da lane required é catraca por prova verde**, declarada no cabeçalho do próprio
   workflow: *"a lane roda só os arquivos comprovadamente VERDES contra o MySQL real semeado"*.
   O agente **não roda teste** ([ADR 0062]) — logo não tem a prova que a catraca exige.
3. `PHP / Pest (NfeBrasil · MySQL)` é **required com `enforce_admins`**. Adicionar arquivo não
   provado ali **bloquearia o merge de todo mundo**, não só deste PR.

→ Ratchet-up do `GatesPermissaoFiscalTest` fica como **proposta ao [W]** (SDD §8.3), depois do
primeiro verde no CT 100. Reportar em vez de arriscar é a leitura correta da regra, não fuga dela.

## 6. Orçamento da corrida — o que a Onda 1 precisa medir

| Métrica | Valor |
|---|---:|
| Tool calls (total da corrida) | **~57** |
| Arquivos lidos por inteiro | 21 (7 controllers · 2 rotas/workflows · 4 charters · 3 casos · SPEC · SUPERFICIE · template · 2 testes) |
| Arquivos lidos parcialmente (`grep`/`sed`) | ~25 |
| Varreduras contadas (sem `head_limit`) | 9 (mocks · `cpf_cnpj_tomador` · protótipos · ids `UC-F*` · `CU-FISC` · nomes de teste × 4 lotes) |
| Arquivos escritos/editados | **16** |
| UC gerados | **37 ancorados** + **19 `[BACKLOG]`** sem id |
| CU gerados | 16 (15 com UC ancorando + 1 declaradamente aberto) |
| Testes carimbados com UC-id | 63 descrições em 15 arquivos |
| Testes novos | 1 arquivo · 8 casos (4 contratos + 4 controles anti-vácuo) |
| Gates rodados | `casos-coverage-guard` (2×) · `requisitos-status` (2×) · `anchor-lint` (2×) · `screen-coverage-map` (1×) · AJV nos charters (1×) |

### Reuso entre as 7 telas (Fase 1.4) — mediu-se o que se prometeu

**Custou 1 análise de MÓDULO + 7 confirmações de tela**, não 7 análises.

| Reusado (pago 1× para as 7) | Re-varrido por tela |
|---|---|
| SPEC (23 US + 3 Gherkin) · SUPERFICIE (gerado) · template do SDD · mapa de lanes · `required-checks-baseline.json` · modelo de dados (§5.2) · governança (§3) · NFR (§7) | o Controller da tela · o charter da tela · a lista de testes que a cobrem · o backlog do stub anterior |

**A 1ª tela (`Nfe`) consumiu ~60% do custo total**; as 6 irmãs saíram a ~7% cada. O que **não** se
reusou, por regra: a resolução da fonte legada (§0.1) e a varredura de consumidores do fluxo.
**Gargalo:** a Camada 1 (leitura dos 7 controllers + reconciliação charter×código) — ~2/3 da corrida.
A Camada 2 foi barata **porque** os stubs de 2026-07-03 já traziam ~60 itens de backlog com citação
de teste: o débito real era **rastreabilidade**, não ausência de contrato. Módulo sem esse trabalho
prévio custará mais.

### Comparação honesta com o piloto (Produto)

O piloto fechou em **4 runs**; este chip fechou em **1 run**. Isso **não** prova que o desenho é 4×
melhor — o Fiscal tem **7 telas contra 8**, mas **23 US contra 9**, e (decisivo) **já tinha 19 testes
Pest e 7 stubs de casos**. A comparação por nº de telas engana; o §6 cresce com US e com dívida
prévia. O sinal utilizável: **criar o SDD do zero (§0–§11) coube num run** — a fase "sem precedente"
não foi o gargalo que o plano temia.

## 7. Lições de mecanismo (o que na definição do agent atrapalhou)

1. **"A lane é allowlist explícita — adicionar o teste é parte do chip" colide com catraca-por-prova-verde.**
   Onde a lane required cresce só com prova de verde e o agente é proibido de rodar teste, as duas
   regras se anulam. A definição precisa de um ramo explícito: *"se a lane declara ratchet por prova,
   NÃO adicione — proponha"*. Segui isso, mas por leitura do cabeçalho do workflow, não por instrução.
2. **A pergunta "esse teste roda?" tem uma 4ª porta não listada.** O agent lista 3 (phpunit/shards ·
   paths-filter · baseline). Falta a **matrix de módulo** (`modules-pest.yml`), que roda o diretório
   inteiro mas em SQLite — onde a maioria **pula**. "Roda no PR" e "exercita comportamento no PR" são
   coisas diferentes, e só a segunda importa.
3. **Duas portas discordam sobre "UC tem teste" e nenhuma está errada.** `casos-coverage-guard` varre
   `['Modules','tests','app','e2e']`; `requisitos-status.mjs` varre só `tests` e `e2e`. Num módulo
   nWidart o resultado é `orphan_ucs = 0` numa e `UC com teste = 0` na outra (A7). Quem ler só uma
   tira conclusão oposta — a classe **LC-08** embutida nas próprias réguas.
4. **`@covers-us` só é enxergado via `Testado em:` no SPEC.** Anotei os 4 testes da lane que
   emite JUnit; o `anchor-lint` **não mudou** (`16 US sem teste que a cobre` antes e depois), porque
   o índice de covers só lê arquivos citados em linhas `Testado em:` — que o SPEC do Fiscal não
   tem. Somado ao A6 (`JUNIT_MODULE_LANES` stale), as marcações ficam **inertes** até dois PRs de
   terceiros. Deixei-as (corretas e baratas) e **declaro que não moveram métrica** — não são ganho.
5. **A Fase 2.6 precisa de um 4º balde.** "Fato × intenção × promessa" não cobre *"o charter proíbe
   o que uma US posterior aprovada entregou"* (A2/A3). Não é fato (não é path morto), não é promessa
   não-cumprida (é o inverso: entregou-se além), e mexer é intenção. Tratei como divergência aberta.
6. **A armadilha da "Blade homônima" não existe em módulo nascido em React** — mas gastar a Camada 1
   provando isso foi correto e barato. O que **substitui** a armadilha aqui é *telas competindo entre
   dois módulos* (Fiscal × NfeBrasil), que a Onda ESTABILIZAR já tratou. Vale a definição citar esse
   caso irmão.

## 8. Pendências que precisam do [W]

1. **`CU-FISC-16`** — qual saída para o dado de demonstração numa tela fiscal (A1).
2. **Charter do `Sped`** — atualizar o Non-Goal ou assumir gerador dormente (A2).
3. **Charter do `Config`** — Non-Goal *"sem edição inline"* × 2 formulários vivos (A3).
4. **`ANTI-REGRESSAO-fiscal-legacy.md`** — criar a partir do manual WR Comercial, ou assumir que a
   fonte 4 não se aplica (SDD §0.1). O agente é proibido de inventá-la.
5. **Ratchet-up da lane** — `GatesPermissaoFiscalTest` → allowlist do `nfebrasil-pest.yml`, depois
   do verde no CT 100.
6. **Âncoras de teste no SPEC** — propostas abaixo; **não aplicadas** (tocar SPEC legado
   acorda o `anchor-lint` diff-aware sobre dívida grandfathered).

### Proposta de linha `Testado em:` (aplicar só se [W] pedir)

```
US-FISCAL-001 · **Testado em:** `Modules/Fiscal/Tests/Feature/NfeCockpitMultiTenantTest.php`
US-FISCAL-002 · **Testado em:** `Modules/Fiscal/Tests/Feature/CockpitMultiTenantTest.php`
US-FISCAL-005 · **Testado em:** `Modules/Fiscal/Tests/Feature/NfseCockpitMultiTenantTest.php`
US-FISCAL-007 · **Testado em:** `Modules/Fiscal/Tests/Feature/EventosCockpitMultiTenantTest.php`
```

Os 4 arquivos **já** declaram `@covers-us` da US correspondente (feito nesta corrida). Só surtem
efeito no `anchor-lint` **depois** de (a) estas linhas entrarem no SPEC **e** (b) `JUNIT_MODULE_LANES`
incluir `Modules/Fiscal/Tests` (A6) — senão contam como *"verde impossível"*.

## 9. Veredito da Camada 3 — por US

⚖️ **Vocabulário:** o agent **não roda teste** ([ADR 0062]). Nada abaixo é "verde"; é `🧪 sem veredito`.

| US | UC que a atendem | Fontes (C1) | casos-gate | anchor-lint | Veredito |
|---|---|---|---|---|---|
| US-FISCAL-001 | UC-FNFE-01..08 | 3/4 (sem Delphi) | 8 UC · 0 órfão | `anchored_ok` · `@covers-us` inerte (§7.4) | 🧪 pendente da lane **required** |
| US-FISCAL-002 · 019 | UC-FCKP-01..06 | 3/4 | 6 UC · 0 órfão | `anchored_ok` | 🧪 pendente (1 na required, 5 advisory) |
| US-FISCAL-005 | UC-FNFSE-01..04 | 3/4 | 4 UC · 0 órfão | `anchored_ok` | 🧪 pendente |
| US-FISCAL-007 | UC-FEVT-01..04 | 3/4 | 4 UC · 0 órfão | `anchored_ok` | 🧪 pendente da lane **required** |
| US-FISCAL-008 · 012 | UC-FDFE-01..05 | 3/4 | 5 UC · 0 órfão | `anchored_ok` | 🧪 pendente (advisory) |
| US-FISCAL-009 | UC-FCFG-01..03 | 3/4 | 3 UC · 0 órfão | `anchored_ok` | 🧪 pendente (advisory) |
| US-FISCAL-010 · 016 · 017 · 020 | UC-FSPED-01..07 `[V0]` | 3/4 | 7 UC · 0 órfão | `anchored_ok` | 🧪 pendente — **nenhum bloqueia merge** (A8) |
| US-FISCAL-013 · 014 | UC-FNFE-05/06/07 | 3/4 | — | `anchored_ok` | 🧪 pendente (advisory) |
| **Σ módulo** | **37 UC · 19 backlog** | fonte 4 **ausente** (declarada) | **`ok: true` · 0 novas · 0 órfãos** | 87% · 0 dead · 0 zombie | 🧪 **sem veredito — CT 100/CI decide** |
