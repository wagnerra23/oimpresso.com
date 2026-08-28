---
title: "Onda 1 do Ponto fechada em 5 PRs — e a biometria que exigia foto sem nunca ter rota"
description: "Plano revisado do Ponto executado: VRT 0→2, zero cor crua nas Pages, biometria removida do REP-P (ADR 0383). 4 erros meus da mesma família, todos pegos por máquina."
type: session
status: aceito
authority: reference
date: '2026-08-28'
topic: "Onda 1 do Ponto (VRT 0→2, tokens) + remoção da biometria do REP-P"
module: pontowr2
---

# Onda 1 do Ponto fechada — e o que a medição derrubou do plano

## O pedido

[W] colou o plano revisado do Ponto (Cowork, 5 ondas / 16 PRs) e mandou executar.
Antes de agir, medi as ~12 afirmações verificáveis dele.

## O que o plano acertou e o que errou

Acertou: 20 telas, 20/20 charters, contratos só `painel` e `espelho`,
Fechamento/Conformidade/REP-P inexistentes, e os 2 sites de token que citava.

Errou, medido:

| Plano | Medido |
|---|---|
| 13/20 com `casos.md` | **14/20** — faltam 6, não 7 |
| `Dashboard/Index` entre os faltantes | **tem** casos.md, com 7 UC (o maior do módulo) |
| "2 `Show.casos.md` sem UC" | **nenhum** casos do Ponto está sem UC |
| resíduo de token = 2 sites | **5** (depois **6**: `Configuracoes:92` emerald também) |

E omitiu o que mais importava: **18 UC citados só em docblock** (`⛓`) e o módulo
**zerado em E2E/a11y/VRT/L2** — único módulo grande nessa condição.

## A cascata que ninguém previu

O plano abria com "trocar cor em 2 arquivos". O gate visual é **fail-closed**: toda Page
sem contrato em `visreg-screens.json` é barrada. Como o Ponto tinha VRT 0/20, a Onda 1
exigiu, em cascata: uma **fixture de seed inexistente** (`VisregPontoSeeder`), edição de
**workflow required**, **2 contratos visuais**, **2 baselines** e um **RUNBOOK**.

[W] e o Cowork insistiram que o A0 vinha antes e sozinho. Tentei dobrá-lo no A1 duas
vezes; o mecanismo me corrigiu as duas.

## Entregue (5 PRs no main)

- [#6365](https://github.com/wagnerra23/oimpresso.com/pull/6365) — `VisregPontoSeeder` + contrato do Espelho + baseline
- [#6362](https://github.com/wagnerra23/oimpresso.com/pull/6362) — tokens semânticos no `Espelho/Show` (nomes do union também: `tone="blue"` é o que convida o próximo a escrever azul)
- [#6369](https://github.com/wagnerra23/oimpresso.com/pull/6369) — RUNBOOK + contrato + baseline + 3 tokens do `Configuracoes`
- [#6393](https://github.com/wagnerra23/oimpresso.com/pull/6393) — biometria removida do REP-P
- ADR **0383** — o ponto interno não coleta biometria

Medido no main: **VRT 0→2**, E2E 0→2, **zero** `blue`/`violet` cru nas Pages do Ponto,
**zero** biometria em código executável.

## A biometria: o achado maior que a pergunta

Eu ia perguntar a [W] a *copy* da tela de selfie. Ele respondeu à pergunta errada com a
resposta certa: *"LGPD não pode ter isso no sistema interno de ponto"*.

Medindo:

1. **Nunca rodou** — zero rota no repo inteiro; nada coletou selfie em produção.
2. **O hash era persistido** — compunha o `dispositivo_id`, gravado na marcação append-only.
3. **O canon mentia** — `SCOPE.md` e `catalog.json` afirmavam o endpoint como funcional;
   o `AUDIT-SENIOR` dizia *"funciona"* em contraste com os stubs 501.

Corrigi a **fonte** (`SCOPE.md`) e regenerei o derivado; preservei o `AUDIT-SENIOR`
intacto por ser registro datado. Non-Goal virou GUARD Pest, provado no CI por nome:
`✓ it GUARD LGPD · o Service NAO conhece biometria` (0.17s, executado — não skipped).

Corrigi também uma citação legal que circulava: era **Art. 5º, II + Art. 11**, não Art. 9º.

## Meus 4 erros — todos da mesma família

**Ler a fonte certa para a pergunta errada.** Nenhum pego por mim; todos por máquina.

1. **Seed errado** — inspecionei `pest-mysql-setup`, mas o gate visual usa `db:seed` + 7
   seeders `Visreg*`. Resultado: 404.
2. **`id=1` derivado, não observado** — inferido do seed. Consertado com id explícito
   `900001` no seeder, para manifesto e fixture concordarem por construção.
3. **`screen` com `/Index`** — a convenção (`screen` = `component` sem `/Index`) estava no
   manifesto que eu já tinha lido. O `Espelho/Show` acertou **por acidente de forma** (não
   é `Index`) e isso escondeu a regra até bater no `Configuracoes`.
4. **`reset --hard` encadeado** a um `checkout` que podia falhar — falhou, e o `--hard`
   caiu na branch do A1b, apagando 2 commits locais. Zero perda porque eu tinha pushado;
   foi sorte, não cautela. **Estado destrutivo não se encadeia com comando que pode falhar.**

## O que o CI me ensinou (e eu não sabia)

- **Canário anti-verde-vazio**: PR que só adiciona `.md`/`.json`/`.snap` marca
  `visual_required=true` mas resolve `screens=[]` → Pest não acha teste → sem contagens →
  o canário barra. Dar a tela ao PR conserta **e** melhora o desenho: o pixel-diff passa a
  medir contra a baseline recém-criada.
- **Corrida com o main**: ~1 commit/20min contra gate de 10-15min, e o `Preflight` exige
  ancestralidade **no instante do run**. Derrubou 3 PRs. Não converge por insistência —
  converge no merge.

## Aberto

- **5 decisões** do `HANDOFF-ponto.md` travam 9 PRs (Ondas 3 e 4). A 6 morreu com a 0383.
- **Onda 4 (REP-P) precisa ser reescrita** — PR-D1/D2 previam selfie.
- **18 UC órfãos**: o conserto NÃO é rename — é conversão PHPUnit→Pest em 3 arquivos /
  22 métodos / ~1400 linhas de teste Tier 0. O próprio `casos:report` desaconselha o lote
  (*"varrer em lote acorda gate diff-aware sobre dívida alheia"*). Recomendação registrada:
  oportunístico, só o `EspelhoContratoTest` (8 UC).
- **Dívida nomeada**: `_components/` do Ponto ainda usa `emerald-*` (semântica de estado —
  decisão de design, não troca mecânica); `Colaboradores/Index`, `Escalas/Index` e
  `Welcome` seguem barrados pelo guard MWART, cada um precisa de RUNBOOK próprio.
