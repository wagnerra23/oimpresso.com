---
id: requisitos-design-system-adr-ui-0017-design-system-v3-reconciliacao-ui-v2
---

# ADR UI-0017 · Design System v3 = implementação concreta das camadas 1–3 da Constituição UI v2 (não supersede; instancia)

- **Status**: accepted
- **Data**: 2026-05-28
- **Aprovado em**: 2026-05-28 — Wagner explícito: *"merge e adote padrão[:] qualquer coisa diferente deveria ser errado e pedir para validar"*
- **Decisores**: Wagner (aprovador), Claude Code (autor)
- **Categoria**: ui · estruturante · governança · design-system
- **Substitui**: nada (aditiva)
- **Substituído por**: —
- **Refs**:
  - [ADR UI-0013](0013-constituicao-ui-v2-camadas.md) — Constituição UI v2 (4 camadas) · **mãe**
  - [ADR 0110](../../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md) — Cockpit Pattern V2 (Padrão de Tela)
  - [ADR 0190](../../../decisions/0190-primary-button-roxo-universal-295.md) — primary roxo 295 (citado pelos tokens DS)
  - [ADR 0114](../../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md) — loop Cowork ↔ Code
  - [prototipo-ui/CODE_DESIGN_CONTRACT.md](../../../../prototipo-ui/CODE_DESIGN_CONTRACT.md) — contrato visual CC↔CL (DS v3)
  - [prototipo-ui/tokens.css](../../../../prototipo-ui/tokens.css) · [design-system.css](../../../../prototipo-ui/design-system.css)
  - [Auditoria conformidade Sells](../../Sells/sells-create-ds-v3-conformance.md) — caso concreto que expôs a lacuna
  - PR #1893 — landing do DS v3 (mergeado **sem ADR** — esta ADR cobre retroativamente)

> **Errata 2026-05-28** (mesmo dia da aceitação): a versão original listava o "gate de
> CI/hook que falha o PR com cor/classe fora do DS" como **lacuna futura**. Isso era **falso** —
> o gate já existe e bloqueia: `php artisan ui:lint` (R1–R6) em modo ratchet via
> `ui-lint.yml`, validado rodando contra `Sells/Create.tsx` (27 violações R1 congeladas no
> baseline; uma 28ª falharia o PR). Decisão inalterada; corrigido só o fato. Ponto 6 e lacunas
> atualizados.

## Contexto

Em 2026-05-28 o **Design System v3** entrou no repo (PR #1893): `tokens.css` (single
source of truth de cor/tipo/espaço), `design-system.css` (~45 componentes em classes CSS),
`ds-behavior.js` e `CODE_DESIGN_CONTRACT.md`. Entrou como **vocabulário**, sem ADR
posicionando-o no resto do sistema — apesar de o próprio contrato exigir *"Cada mudança no
DS: 3. ADR registrada em memory/decisions/"*.

Ao tentar aplicar o DS v3 na primeira tela real (`/sells/create`, Passo 2), a auditoria de
conformidade expôs **duas perguntas estruturais sem resposta**:

1. O DS v3 **supersede** a Constituição UI v2 ([UI-0013](0013-constituicao-ui-v2-camadas.md)) e o Cockpit Pattern V2 ([ADR 0110](../../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md)), ou **coexiste**?
2. As telas reais são **React + Tailwind + shadcn/ui**; o DS v3 é **classes CSS + tokens**. Quem é a fonte de verdade quando os dois divergem, e como convergem sem quebrar 39+ testes de charter por tela?

Sem responder, qualquer reskin é gambiarra — exatamente o débito que o `CODE_DESIGN_CONTRACT`
existe pra evitar. **Fato técnico relevante:** os tokens do DS v3 foram escritos pra *casar*
com a realidade do repo — `--primary-page` cita [ADR 0190](../../../decisions/0190-primary-button-roxo-universal-295.md), `--sb-*` cita o `cockpit.css` real.
Ou seja, o DS v3 **não contradiz** as fundações existentes; ele as **formaliza** num lugar só.

## Decisão

**1. Posicionamento — DS v3 instancia, não supersede.**
O Design System v3 é a **implementação concreta e versionada das camadas 1–3** da
Constituição UI v2 ([UI-0013](0013-constituicao-ui-v2-camadas.md)). UI-0013 continua sendo a **mãe** (o mental model);
DS v3 é o **artefato** que a materializa:

| Camada UI v2 | Artefato DS v3 |
|---|---|
| 1 · Fundações (tokens) | `prototipo-ui/tokens.css` |
| 2 · Shell | `.pageheader`, `.moduletopnav`, `.sb-*` (sidebar canon real) |
| 3 · Padrão de Tela | PT-01..PT-08 + biblioteca de ~45 componentes |
| 4 · Módulo | permanece React/Inertia por módulo (não muda) |

DS v3 **não cria constituição nova** e **não revoga** UI-0001..UI-0016. É aditivo, como
UI-0013 foi sobre as 12 ADRs anteriores.

**2. Coexistência com Cockpit Pattern V2 (ADR 0110).**
Cockpit V2 é um **Padrão de Tela** (camada 3): header sticky + KPIs + pills + drawer/footer.
Permanece `aceito`. Passa a ser **expresso no vocabulário do DS v3** (`.pageheader` +
`.savebar` + section-nav + `.combobox` + `.badge`…). Onde o DS v3 ainda não cobre um
elemento do Cockpit V2 (ex: stat-card de KPI gigante, payment-split-row), **o DS é estendido
primeiro** (regra única do contrato) — o Cockpit V2 não é quem cede.

**3. Fonte de verdade e ponte de tecnologia.**
- **Tokens** (`tokens.css`) são a **única** fonte de verdade de cor/tipo/espaço/raio/sombra.
- O tema **Tailwind/shadcn** do app passa a **derivar** desses tokens via uma camada de
  ponte no `:root` (ex: `--background`→`--bg`, `--primary`→`--primary-page`, `--radius`→`--radius`).
  Re-tematizar = mexer em `tokens.css`, não em componente.
- **Componentes React (shadcn + shared) são a implementação** das classes do DS para telas
  Inertia. As classes CSS vanilla do `design-system.css` valem para protótipos Cowork e
  contextos não-React. Os dois lados consomem **os mesmos tokens** → param de divergir.
- Em conflito **token vs hardcoded**: vence o token (hierarquia UI v2: Fundações > Shell > PT > Módulo).

**4. Migração é gradual e por camada, nunca big-bang.**
1. **Token bridge global** (camada 1, 1× pro app): mapeia o tema Tailwind pros tokens DS.
   Baixo risco, não toca markup, captura ~80% da aparência. Não quebra charters/testes.
2. **Por tela** via loop MWART completo ([ADR 0114](../../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)): F1 Cowork → aprovação → tradução.
   Telas existentes não são quebradas; charter + testes anti-regressão seguem verdes ou são
   emendados explicitamente.

**5. Governança do DS (formaliza o que o contrato já pede).**
Toda mudança no DS v3: (a) bump de versão no `CODE_DESIGN_CONTRACT.md`; (b) entrada no
`Design System.html` §Histórico; (c) **ADR** (main track ou UI track conforme escopo);
(d) migração agendada, não obrigatória imediata. Esta ADR **cobre retroativamente** o landing
do PR #1893.

**6. Regra-mãe de enforcement — divergência do DS é ERRO (Wagner-explícito 2026-05-28).**
Qualquer coisa fora do canon — **cor** fora dos tokens, **componente/classe** que não existe no
DS, **padrão de tela** que não bate com PT-01..PT-08, **token** redeclarado — é tratada como
**erro**, não como liberdade criativa. Diante dela, o agente (ou humano):

1. **PARA** — não implementa o diferente.
2. **Pede validação** — propõe extensão do DS via `COWORK_NOTES.md` (template do contrato) e
   aguarda [CC] atualizar o DS + Wagner aprovar.
3. Só depois aplica, agora dentro do canon.

Vale **igual** pra humano, esposa, Felipe, Maiara e qualquer agente — mesmo caminho, sem atalho.
"Só faz funcionar" não é override. Override real exige a extensão aprovada do DS. Isto é a
"regra única" do [CODE_DESIGN_CONTRACT.md](../../../../prototipo-ui/CODE_DESIGN_CONTRACT.md)
elevada a decisão canônica. **Enforcement já existe e bloqueia** (errata 2026-05-28, ver topo):
o comando `php artisan ui:lint` (regras R1 cor crua · R2 FontAwesome · R3 emoji · R4 PT-01 ·
R5 origens · R6 blade) roda em CI via [`.github/workflows/ui-lint.yml`](../../../../../.github/workflows/ui-lint.yml)
em modo **ratchet** contra `config/ui-lint-baseline.json`: dívida existente congelada
(~7.5k violações), e **qualquer divergência NOVA em arquivo alterado falha o PR** (`--strict`).
Some-se a isso o gate `mwart-comparative` F1.5. A única peça ainda ausente é uma regra de
**componente/classe inexistente no `design-system.css`** (faz sentido só após o token bridge —
ver lacunas).

## O que esta ADR NÃO decide (lacunas explícitas)

- ❌ A **implementação** do token bridge (camada 1) — vai em PR próprio, app-wide.
- ❌ Os **F1 de cada tela** (Clientes, Vendas, etc.) — loop MWART, um por vez.
- ❌ A criação dos componentes DS faltantes (stat-card, payment-split-row) — Cowork, no F1.
- ❌ A limpeza de cores cruas pré-existentes (ex: azuis em `Sells/Create.tsx`) — PR isolado.
- ❌ Uma regra `ui:lint` **R7 — componente/classe inexistente no `design-system.css`** (a única peça de enforcement ainda ausente; o resto — cor crua R1, ícone R2, emoji R3 — **já bloqueia** via `ui-lint.yml`). Faz sentido só após o token bridge. ~~Antes (errata): este item dizia que o gate inteiro não existia — falso.~~
- ❌ **Sidebar light vs dark** — permanece light por decisão Wagner-explícita ([UI-0009](0009-cockpit-sidebar-light-padrao.md) + [UI-0014](0014-sidebar-light-mantida-v2-parcial.md)); os tokens `--sb-*` dark do DS v3 não revogam isso sem ADR.

## Consequências

### Positivas
- **Reskin deixa de ser gambiarra** — DS v3 ganha mandato e lugar no mental model.
- **Token bridge único** destrava Clientes/OS/Vendas/Compras/Financeiro de uma vez (camada 1).
- **Zero quebra** — telas existentes e seus testes seguem; convergência é por token, não por markup.
- **Coexistência clara** — 0110 e UI-0013 permanecem; DS v3 é o "como", não um "novo o quê".
- **Dívida de governança quitada** — PR #1893 deixa de estar sem ADR.

### Negativas
- **Token bridge tem custo de acerto fino** — mapear shadcn↔DS pode exigir 1-2 rodadas de ajuste de contraste/raio. Mitigação: visual regression (Onda 4 do contrato).
- **Risco de "duas verdades" de componente** (classe CSS vs React) se a ponte de token não for respeitada. Mitigação: tokens como única fonte + checklist pre-commit do contrato.

### Neutras / a observar
- DS v3 documenta/forma o que em grande parte **já existe** (tokens já citam o repo). Não é mudança visual por si — é formalização.
- PT-05..PT-08 do DS v3 entram como camada 3 conforme ≥2 módulos pedirem (princípio UI-0013).

## Pegadinhas conhecidas
- **Não** trocar `<Button>` shadcn por `.btn` vanilla tela a tela (caminho B do audit) — quebra os 39+ testes estruturais. Token bridge primeiro.
- **Não** tratar os tokens `--sb-*` dark do DS como autorização pra escurecer a sidebar — isso é Wagner-explícito (UI-0009/0014).
- **Não** aplicar DS numa tela sem F1 Cowork aprovado — fura o gate `mwart-comparative` F1.5.
