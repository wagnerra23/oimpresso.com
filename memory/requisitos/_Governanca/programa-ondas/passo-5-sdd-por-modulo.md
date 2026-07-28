---
id: requisitos-governanca-programa-ondas-passo-5-sdd-por-modulo
titulo: "Passo 5 do ciclo-padrão — SDD derivado do fonte, por módulo, em sessões paralelas"
status: proposto
owner: W
criado: '2026-07-27'
related: PLANO-MESTRE.md
related_adrs:
  - '0351-sdd-from-source'
  - '0352-errata-0351-venue-distiller-citacao-taxonomia'
  - '0264-governanca-executavel-trio-dominio-e2e'
  - '0119-paralelismo-sessoes-whats-active-tier-1'
  - '0062-separacao-runtime-hostinger-ct100'
---

# Passo 5 — SDD por módulo (execução em sessões paralelas)

> Status vivo do programa: [PLANO-MESTRE.md](PLANO-MESTRE.md) §Status vivo (1 plano = 1 registro).
> **Extensão do ciclo-padrão, não plano paralelo.** O programa-ondas nasceu 2026-07-02 com 4
> passos (adversário · gaps · régua · catraca); o agent [`sdd-from-source`](../../../../.claude/agents/sdd-from-source.md)
> só existe desde a [ADR 0351](../../../decisions/0351-sdd-from-source.md) (ratificada [W] 2026-07-24).
> O passo 5 é a camada que faltava: **derivar o SDD/contratos do fonte** depois que o módulo
> já passou pelo adversário e pela régua.

## Por que agora (medido em `origin/main`, 2026-07-27)

| Fato | Porta que mediu |
|---|---|
| **1 de 40** módulos com tela React tem `SDD-tela-*.md` (o Produto) | `ls memory/requisitos/*/SDD*.md` |
| 235 telas · **192 sem `casos.md`** · débito 220 | `npm run casos:report` |
| Charter 235/235 · E2E 9/235 · scorecard 223/235 | `npm run screen-coverage:report` |
| `anchor_coverage` 84,9% · `full_suite` floor 345 | `governance/sdd-scorecard.json` |

O piloto Produto fechou 2026-07-27 (lacunas 4→0 · 11 `casos.md` · 54 UC · 0 sem teste) em
**4 runs** — [session B3](../../../sessions/2026-07-27-sdd-produto-fluxos-sem-tela.md). O método
está provado 1×; falta escala. **Escala aqui é paralelismo de SESSÕES** ([ADR 0119](../../../decisions/0119-paralelismo-sessoes-whats-active-tier-1.md)),
não de subagents: o chip é caro, longo e termina em PR.

## O chip: 1 sessão = 1 MÓDULO (não 1 tela)

**Regra de desenho, derivada do agent:** a Fase 2.1 do `sdd-from-source` proíbe `§5` por tela
(*"o SDD é do MÓDULO/família, nunca da tela"*) — logo **duas sessões na mesma família escrevem
no MESMO `SDD-tela-*.md` §5.3/§6 e colidem**. E a Fase 1.4 diz que a parte cara da Camada 1 é
do módulo, não da tela: a 2ª tela do mesmo módulo custa sensivelmente menos.

→ **Paralelize módulos. Serialize telas dentro do módulo.**

Entrega de cada chip: `SDD-tela-<slug>.md` (§0–§10) · `<Tela>.casos.md` por tela ·
`…ContratoTest.php` · `**Implementado em:**` no SPEC · allowlist da lane · `_STATUS-GENERATED.md`
re-derivado · session log.

## Regras duras de isolamento (derivadas das colisões MEDIDAS no piloto)

O run maior do piloto ([#4826](https://github.com/wagnerra23/oimpresso.com/pull/4826)) tocou
15 arquivos, dos quais **4 são globais**. Em paralelo, esses 4 são conflito garantido:

| Regra | Recibo |
|---|---|
| Toque **só** `memory/requisitos/<SeuMod>/`, `resources/js/Pages/<SeuMod>/`, `Modules/<SeuMod>/Tests/` e o YAML da **sua** lane | zero overlap entre sessões |
| ⛔ **Não escreva** `scripts/casos-coverage-baseline.json` · `governance/required-checks-baseline.json` · `.github/workflows/governance-gate-umbrella.yml` · `scripts/governance/*.mjs` · `memory/proibicoes.md` · `memory/LICOES_CODE.md` · `memory/08-handoff.md` | o piloto tocou 3 desses. Achou algo? **reporta no session log**, não conserta |
| Testes em `Modules/<Mod>/Tests/Feature/` quando o módulo é nWidart | `tests/Feature/<Mod>` só tem lane pra `Produto`/`Estoque`/`Domain` |
| **A lane é allowlist explícita** — adicionar o arquivo de teste ao YAML é parte do chip | `financeiro-pest.yml:128+` lista arquivo a arquivo; sem isso o teste é "verde impossível" |
| Branch `claude/sdd-<modulo>` · PR aberto pela sessão · **merge é do [W]** | R10 |
| Zero teste local — CT 100 ou CI | [ADR 0062](../../../decisions/0062-separacao-runtime-hostinger-ct100.md) |
| Rode `whats-active` no início | [ADR 0119](../../../decisions/0119-paralelismo-sessoes-whats-active-tier-1.md) — detecta sessão irmã no mesmo path |

### Mapa de lanes (medido 2026-07-27 · `grep` em `.github/workflows/*pest*.yml` + `ci-sqlite-pest.list`)

| Lane | Módulos que ela roda | Colisão |
|---|---|---|
| `nfebrasil-pest.yml` | **Fiscal + NfeBrasil** | ⚠️ compartilhada — nunca na mesma onda |
| `modules-pest.yml` (matrix) | Arquivos · ComunicacaoVisual · Fiscal · NfeBrasil · Repair · Vestuario | ⚠️ compartilhada |
| `financeiro-pest` · `compras-pest` · `ponto-pest` · `essentials-pest` · `kb-pest` · `jana-pest` · `arquivos-pest` · `estoque-pest` | 1 módulo cada | ✅ isolada |
| **sem lane** | Sells · Cliente · OficinaAuto · RecurringBilling · Repair · ads | ❌ não é chip válido até a Onda 0 |

## Fila (ordenada por prontidão medida, não por tamanho)

### Onda 1 — 3 sessões em paralelo, lanes distintas

> ⚠️ **Errata da 1ª versão deste plano (2026-07-27, mesmo dia).** A ordem original punha o
> Fiscal como "o chip mais barato do repo — contrato de tela já 100%". **Falso.** Os 7
> `casos.md` do Fiscal são **stubs de 37-41 linhas com ZERO UC** — eu li presença de ARQUIVO
> como contrato, que é a classe **LC-11** (presence-gate) cometida na montagem do próprio
> plano. A porta agora acusa isso (ver §Máquina corrigida). Ordem refeita abaixo.

| Sessão | Módulo | Telas | UC reais | US no SPEC | Lane | Por que |
|---|---|---:|---:|---:|---|---|
| S1 | **Compras** | 1 | 0 | 21 | `compras-pest` | 1 tela · lacunas já **nomeadas** pela porta (`US-COM-006`/`007` entregues sem contrato) → o menor chip honesto, e o teste do desenho |
| S2 | **Fiscal** | 7 | **0** (7 stubs) | 23 | `nfebrasil-pest` | chip normal, não barato: os `casos.md` existem mas não declaram contrato |
| S3 | **Ponto** | 20 | 0 | 10 | `ponto-pest` | volume alto, ambiguidade baixa (Portaria MTP 671/2021 fecha o domínio) |

### O caminho que a Onda 1 percorre NUNCA foi exercitado

`git log --diff-filter=A` (repo completo, `is-shallow=false`): o SDD do Produto **nasceu
2026-07-14 à mão** ([#4260](https://github.com/wagnerra23/oimpresso.com/pull/4260)) — **10 dias
antes** de o agent existir ([ADR 0351](../../../decisions/0351-sdd-from-source.md), 24/07). Os 3
runs do agent (26–27/07) rodaram **com o SDD já pronto**, exercitando só o ramo *"SDD existe →
preenche §5.3/§6"*. O ramo *"SDD não existe → cria §0–§10"* — que é o de **todos os 39 módulos
restantes** — tem **zero corridas**. A Onda 1 é a primeira.

## Cronograma (ancorado no único ponto de dados, com a incerteza declarada)

| Fase | Medido / estimado |
|---|---|
| Piloto Produto — **fase-agent** | **2 dias** (26→27/07): 3 PRs principais + 5 follow-ups · 9 US · 8 telas · **SDD pré-existente** |
| Piloto Produto — **total** (com o SDD escrito à mão antes) | 13 dias (14→27/07), 13 commits |
| **Onda 1** (S1+S2+S3 em paralelo) | **3–5 dias** — os 2 dias da fase-agent **mais** a fase sem precedente de criar SDD do zero. É faixa, não promessa |
| **Onda 2** (NfeBrasil · Financeiro · Essentials) | só estimável **depois** que a Onda 1 medir a fase nova |
| 33 módulos restantes | **não estimado** — seria chute sobre chute |

## Máquina corrigida (pré-requisito da Onda 1 — feito 2026-07-27)

A régua que as sessões usam pra dizer "fechei o módulo" — `requisitos-status.mjs` — tinha **dois
falso-verdes**, ambos medidos e corrigidos antes de abrir os chips:

| Defeito | Recibo | Depois |
|---|---|---|
| `telasDoModulo` não recursava (e não usava a fonte única `page-path.mjs`) | subcontava **20 de 40 módulos**: `ads` 19→0 · `Financeiro` 21→2 · `Ponto` 20→1 · `Essentials` 13→0 | consome `isPageScreenPath`; Produto 7→**8** (a 8ª, `Unificado/Index`, era invisível — e o piloto fechou como "7/7") |
| `casos.md` presente contava como tela coberta | Fiscal: 7 arquivos, **0 UC**, painel imprimia *"Nenhuma lacuna: toda tela tem caso"* | lacuna nova `casos.md existe mas não declara nenhum UC`; Fiscal passa a acusar **7** |

Provado por **bite-test** no `--selftest` (27/27): fixture que morde (`Unificado/Index` na conta ·
stub sem UC não cobre) **e** controle-negativo (`_components` não vira tela · `casos.md` com UC
cobre). Sem o par, "consertei" seria afirmação — e afirmação é o que este passo existe pra matar.

### Onda 2 — depois do merge da 1

| Sessão | Módulo | Nota |
|---|---|---|
| S4 | **NfeBrasil** (6 telas · 34 US) | lane compartilhada com Fiscal → serial obrigatório |
| S5 | **Financeiro** (21 telas · 5 casos · 58 US) | sessão longa dedicada; maior valor de negócio |
| S6 | **Essentials** (13 telas · 11 US) | lane própria |

### Onda 0 (pré-requisito, sessão SOZINHA — toca arquivo global)

Criar a casa de teste de **Sells · Cliente · OficinaAuto · RecurringBilling** (lane nova ou
entrada no `ci-sqlite-pest.list`). Sem isso esses 4 não são chips válidos — o teste nasceria
sem lane, que é exatamente o achado #7 do piloto (*"verde impossível"* no `anchor-lint`).

> ⚠️ **Errata da Onda 0 (medida 2026-07-28, `origin/main`).** A lista dos 4 acima estava
> **errada nos dois sentidos**, e a Onda 0 nunca foi paga:
> **Sells JÁ tinha lane** (`sells-pest.yml` roda `tests/Feature/Sells/**`, 74 arquivos) e
> **Repair também** (`modules-pest.yml`, por *matrix* — não por path literal). Em compensação,
> **6 módulos fora da lista** estão órfãos, e **Cliente + OficinaAuto tiveram o chip mergeado
> mesmo assim** — a regra *"sem lane não é chip válido"* não foi enforçada por máquina nenhuma.
> Números na tabela §Fila abaixo. Método da medição: `grep -rlE "Modules/<M>/Tests|tests/Feature/<M>"
> .github/workflows/` + leitura das matrizes; contar por glob `Modules/<M>/Tests/**/*.php`
> **subconta** (perde arquivo direto no diretório) — foi o que me fez errar Sells na 1ª passada.

### Fila derivada dos 32 restantes (medida 2026-07-28 · re-rodar antes de usar)

> **Recibo, não afirmação atemporal.** Colunas `telas` e `testes` derivadas de
> `git ls-files 'resources/js/Pages/**/*.tsx'` (excluindo `_components/`) e
> `git ls-files Modules/<M>/Tests tests/Feature/<M>`; coluna `lane` do `grep` acima.
> Os números envelhecem — **re-rode, não edite** ([lápide §5 2026-07-17](../../../proibicoes.md)).
> A ordem abaixo é derivada de dois fatos medidos (tem lane? quantas telas?), **não** de
> julgamento de valor de produto — priorizar por valor é decisão [W].

**Estado em 2026-07-28:** 40 módulos com tela React · **8 com SDD** (Cliente · Compras ·
Financeiro · Fiscal · OficinaAuto · Ponto · Produto · Sells) · **32 sem**.

| # | Módulo | Telas | Testes | Lane | Chip válido hoje? |
|---|---|---:|---:|---|---|
| 1 | **NfeBrasil** | 6 | 49 | `nfebrasil-pest` + `modules-pest` | ✅ — é o **S4 declarado** da Onda 2 |
| 2 | **Essentials** | 13 | 15 | `essentials-pest` | ✅ — **S6 declarado** |
| 3 | **Jana** | 16 | 150 | `jana-pest` + `jana-logica-pura` + sqlite | ✅ (92 US — chip caro) |
| 4 | **Repair** | 13 | 22 | `modules-pest` (matrix) | ✅ |
| 5 | **Whatsapp** | 3 | 121 | `ci-sqlite-list` | ✅ |
| 6 | **ComunicacaoVisual** | 1 | 20 | `modules-pest` (matrix) | ✅ |
| 7 | **Vestuario** | 1 | 15 | `modules-pest` (matrix) | ✅ |
| — | **RecurringBilling** | 6 | 39 | ❌ | ⛔ Onda 0 |
| — | **Admin** | 8 | 19 | ❌ | ⛔ Onda 0 |
| — | **Manufacturing** | 1 | 17 | ❌ | ⛔ Onda 0 |
| — | **ProjectMgmt** | 9 | 13 | ❌ | ⛔ Onda 0 |
| — | **ConsultaOs** | 1 | 11 | ❌ | ⛔ Onda 0 |
| — | **Auditoria** | 2 | 18 | ❌ | ⛔ Onda 0 |
| — | ads · Atendimento · Site · governance · MemCofre · team-mcp · Purchase · Nfse · TransactionPayment · kb · Settings · StockAdjustment · StockTransfer · Suporte · superadmin · Home · Modules · Tarefas · User | 1–19 | **0** | — | 🟡 chip **sem perna de teste**: o trio nasceria sem `ContratoTest`. Precisa de decisão de desenho antes (ver abaixo) |

### A dívida real da Onda 0 — **225 arquivos de teste que nenhum job roda**

| Módulo | Arquivos órfãos | Tem SDD? |
|---|---:|---|
| **Cliente** | 64 (`tests/Feature/Cliente`) | ✅ chip mergeado **sem lane** |
| **OficinaAuto** | 44 (`Modules/OficinaAuto/Tests`) | ✅ chip mergeado **sem lane** |
| RecurringBilling | 39 | — |
| Admin | 19 | — |
| Auditoria | 18 | — |
| Manufacturing | 17 | — |
| ProjectMgmt | 13 | — |
| ConsultaOs | 11 | — |

Cliente e OficinaAuto são o caso mais duro: o chip **entregou contrato + teste e mergeou**, e o
teste é decorativo por construção — é o defeito #7 do piloto reaparecendo *depois* de nomeado.
A lane existia na branch da sessão irmã (`cliente-pest.yml`/`oficinaauto-pest.yml` em
`claude/sdds-pendentes-c3a697`) e **não chegou ao main**. Isto é dívida de **entrega**, não de
plano — e nenhuma máquina a acusou, porque *"teste sem lane"* não é gate.

### Os 19 módulos com 0 testes — pergunta de desenho, não de execução

Metade da fila restante não tem `Modules/<X>/Tests` nem `tests/Feature/<X>`. Para eles o chip,
como definido hoje, entrega **SDD + `casos.md` + UC sem nenhum teste que os cite** — que é
exatamente o que o **G-2 do `casos-gate` (required) reprova**. Três saídas possíveis, e a
escolha é de [W] porque muda o contrato do chip:

1. **Onda 0 estendida** — criar lane + primeiro teste antes do SDD (caro: 19 lanes).
2. **Chip de 2 tempos** — SDD + `casos.md` com os UC marcados `[BACKLOG]` (prosa honesta sem
   gate, forma já canon em [how-trabalhar](../../../how-trabalhar.md)), teste em run seguinte.
3. **Fora do denominador** — vários não são módulo de negócio (`Home`, `Settings`, `User`,
   `Modules`, `superadmin`): declarar que SDD-de-tela não se aplica, com a razão registrada.

Enquanto não houver corte, **a fila executável é de 7 módulos**, não 32.

## Contrato do prompt de cada sessão

```
/sdd-from-source <Modulo>/<TelaAncora>
```

Colar junto: *"Chip da Onda N do passo 5 (`memory/requisitos/_Governanca/programa-ondas/passo-5-sdd-por-modulo.md`).
Toque APENAS `memory/requisitos/<Mod>/`, `Pages/<Mod>/`, `Modules/<Mod>/Tests/` e o YAML da lane
`<lane>.yml`. NÃO escreva baseline global, umbrella, `scripts/governance/`, proibicoes,
LICOES_CODE nem 08-handoff — achou algo lá, reporta no session log. A fonte 4 (ANTI-REGRESSAO
Delphi) NÃO existe neste módulo: declare o gap, não invente. Branch `claude/sdd-<mod>`.
Testes só no CT 100/CI. PR aberto; merge é do [W]."*

## Consolidação (parent, 1× após a onda mergear)

1. `npm run casos:baseline:write` — **uma vez só**, depois dos 3 merges (nenhum chip escreve baseline).
2. Re-rodar `screen-coverage:report` + `sdd-scorecard.mjs` e registrar o delta.
3. Recolher os "reportes" de arquivo global das 3 sessões num PR único.

## Incógnitas declaradas (não escondidas)

- **Custo do chip é desconhecido.** Os 4 PRs do piloto não casaram com sessão local
  (`agent-cost-per-pr --pr` → *"sem sessão local casada"*); a referência global da janela 14d é
  **mediana $34,29/PR** — isso **não é** o custo do chip. A Onda 1 é também a medição.
- **A fonte 4 (Delphi) só existe no Produto** — `find memory -iname "*ANTI-REGRESSAO*"` = 2
  arquivos, ambos dele. Nos demais a triangulação é de 3 fontes: mais barata, e com contrato de
  paridade mais fraco. Vale declarar nos dois sentidos.
- **O piloto é o menor módulo da fila em US** (Produto = 9 US; Sells 51 · Financeiro 58 ·
  Jana 92). Comparabilidade por nº de telas engana — o §5.3/§6 cresce com US, não com telas.
- **Fiscal/NfeBrasil também aparecem no `modules-pest.yml`** (matrix). Se o chip precisar mexer
  lá, é colisão extra — S1 verifica e **reporta**, não resolve.

## O que a Onda 1+2 provou que FALTAVA na definição do chip (2026-07-27)

Sete chips rodaram. Nenhuma revisão do plano teria achado isto — só a execução:

| # | Buraco | Como apareceu | Correção no chip |
|---|---|---|---|
| 1 | **BRIEFING não era atualizado** | `distiller_freshness` 0→1 no ratchet **armado** (GT-G3, required) — criar SDD deixa o doc do módulo mais novo que o `distilled_at`. É a armadilha da [lápide §5 2026-07-12](../../../proibicoes.md) | o chip redestila **parcialmente** e declara isso no `distilled_by`. Editar o baseline seria maquiar |
| 2 | **`SUPERFICIE.md` não era regenerada** | gate `SUPERFICIE == árvore` vermelho em 5 PRs — os arquivos novos mudam a árvore | rodar `module-surface <Mod> --write` |
| 3 | **Lane nova não era registrada no censo** | `memory-health` (**required**) 🔴 *"workflow NOVO sem registro em `gates-registry.json`"* + o campo `promote_by` que ele cobra em seguida | registrar `nome`/`classe`/`terminal`/`anchor`/`promote_by` |
| 4 | **Contradição no próprio prompt** | mandei "CRIE a lane" e proibi tocar `scripts/governance/` — que é onde a lane se registra. Erro de desenho meu, não do chip | o parent registra o censo, ou a área do chip inclui só essa chave |

Dois vieram de **três chips independentes** acusando o mesmo defeito de máquina (`Modules/<X>/Tests` invisível), o que é o sinal mais confiável de que o gargalo estava na régua, não no chip.

### Corolário sobre autonomia ([W] 2026-07-27: *"a responsabilidade é do dono fazer da melhor forma"*)

Os chips fecharam pedindo decisão em coisas que **têm fonte no repo** — e o parent repetiu o vício ao
repassá-las. O corte é: **tem fonte canônica → decide e registra a âncora; não tem → é [W]**.

| Parecia decisão de [W] | Era decisão técnica, e a fonte é |
|---|---|
| atualizar BRIEFING? | o gate cobra e o CLAUDE.md exige — não há escolha |
| consertar o `403` do teste? | teste que falha por não-execução não prova nada; conserta |
| alargar whitelist × aba emitir status core? | [`memory/dominio/compras.md`](../../../dominio/compras.md) define `ordered→pending→received` e é fonte única do `dominio:check` (required) — alargar criaria 2º vocabulário no módulo |

Continua sendo de [W], e só: **merge** · **Non-Goals/Anti-hooks do charter** (o agente é proibido de
inferir) · **prioridade de produto** (o bug entra agora ou vira US).

## Kill-condition

Se **S1 (Fiscal — o mais barato possível)** custar mais que o piloto inteiro do Produto, o
desenho do chip está errado: **pare a Onda 1**, não abra a 2, e reveja a unidade (talvez o chip
seja "1 tela-âncora + SDD esqueleto", com as irmãs em run seguinte).
