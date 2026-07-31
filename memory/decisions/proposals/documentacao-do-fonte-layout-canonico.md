---
status: proposal
title: "Documentação do fonte — modelo canônico por módulo (onde cada doc mora, e a fronteira do trio)"
proposed_by: Felipe [F] + Claude
proposed_at: 2026-07-31
relates_to:
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
  - 0130-handoff-append-only-mcp-first
  - 0094-constituicao-v2-7-camadas-8-principios
---

# PROPOSAL — Documentação do fonte: modelo canônico por módulo

> **Status:** `proposal` — [F] patrocina; ratificação segue o caminho canônico (PR + aprovação
> [W], porque toca `memory/decisions/` + um gate **required** — CODEOWNERS `@wagnerra23`).
> **Origem (2026-07-31):** [F] — *"gostaria de toda documentação junta, nada fora"* +
> *"organize melhor, tudo dentro lá como ficar melhor"*. A dor real: **achar toda a
> documentação de um módulo a partir de um lugar só**, sem caçar em duas árvores.

## Contexto

### O que foi MEDIDO (não afirmado) — 2026-07-31, read-only

| Família de doc | Onde vive hoje | Longe do fonte? | Imposto por |
|---|---|:--:|---|
| SDD, SPEC, ANTI-REGRESSAO, BRIEFING, RUNBOOK, CAPTERRA*, audits, visual-comparison, `*.proto-baseline.json`, `*.map.json` | `memory/requisitos/<Modulo>/` | ✓ sim | `anchor-lint` (SPEC), schemas |
| **`charter.md` + `casos.md` (trio de tela)** | `resources/js/Pages/<Mod>/<Tela>.charter.md` / `.casos.md` | ✗ **ao lado do `.tsx`** | **`casos-gate` G-1 (required)** + **[ADR 0264](../0264-governanca-executavel-trio-dominio-e2e.md)** |

Dois achados que a proposta precisa respeitar:

1. **A documentação pesada JÁ mora em `memory/requisitos/<Modulo>/`** — o objetivo "docs em
   `memory/`" está 90% satisfeito. O `Modules/` (backend PHP) está limpo de doc de módulo.
2. **A pasta cresceu sem forma** — `Sells/` tem ~45 arquivos, `Financeiro/` ~50, em raiz +
   subpastas inconsistentes (`_telas/`, `adr/`, `audits/` usados de forma desigual). **É aqui
   que mora o ganho real de "organize melhor".**

### Fronteira com a proposta existente (anti-duplicação — §5 2026-07-23)

Esta ADR **não** re-decide o que `estrutura-canon-memoria` já é dona:
- **`estrutura-canon-memoria` Parte I** = frontmatter/schema por família (AJV).
- **`estrutura-canon-memoria` Parte II** = layout espacial **dentro de `memory/`** + máquina de realocação.

**Esta ADR decide o que aquela NÃO cobre:** o **modelo de documentação do fonte por módulo**
(quais docs, em que sub-estrutura) e — o ponto que [F] levantou — **a fronteira do trio**, que
hoje mora **fora de `memory/`** (em `resources/js/`) por força da ADR 0264 + gate required.

## O problema-decisão

O objetivo de [F] ("tudo achável de um lugar") admite **duas realizações**, e a escolha entre
elas é o coração desta ADR. A meta é a mesma; muda o custo.

### Opção A — Consolidação FÍSICA do trio em `memory/`

Mover `charter.md`/`casos.md` para `memory/requisitos/<Modulo>/_telas/<Tela>.*` e **re-apontar
o `casos-gate`** para o novo caminho. Satisfaz "nada fora" ao pé da letra.

**Custo (medido):**
- **Supera a [ADR 0264](../0264-governanca-executavel-trio-dominio-e2e.md)** (canônica) — append-only exige ADR nova com `supersedes: [264]` + ratificação [W].
- **Re-path de um gate required** (`casos-gate` tem `PAGES_DIR = resources/js/Pages` hard-coded).
- **Degrada o anti-apodrecimento** ([ADR 0256](../0256-knowledge-survival-meia-vida-catraca-sentinela.md)): o dev que abre a pasta da tela **deixa de ver o contrato ao lado do código**. O gate ainda força completude do trio, mas a visibilidade-no-lugar (o que mais garante que o contrato seja atualizado junto) se perde.
- **Raio de impacto grande:** `charter-first` (hook lê charter ao lado do `.tsx`), `criar-tela.mjs` (carimba o trio ao lado), `screen-coverage` (mapeia pela pasta do `.tsx`), âncoras `related_prototype`, skills `aplicar-prototipo`/`alinhar-tela`. Cada um precisa migrar.
- **§5 2026-07-12:** mover/renomear arquivo que um gate diff-aware vigia **acorda o gate** sobre a dívida grandfathered; em lote = big-bang, **descartado**.

### Opção B — Consolidação LÓGICA (índice derivado) — **recomendada**

O trio **fica ao lado do `.tsx`** (ADR 0264/0256 intactas, gate intacto), e a achabilidade "de um
lugar só" vem de um **índice DERIVADO** (gerado, nunca mantido à mão — a doutrina `how-trabalhar.md`:
*"o mapa de quais arquivos cada tela tem é COMANDO, não arquivo"*).

**Custo:** ~zero. Reusa as portas vivas que já existem (`screen-coverage:report`, `casos:report`)
escopadas por módulo. Nenhum arquivo movido, nenhum gate re-apontado, nenhuma ADR superada.

**O que [F] ganha:** a partir de `memory/requisitos/<Modulo>/` (onde toda a doc pesada já mora),
**um comando lista o trio de cada tela + seu status**, onde quer que ele esteja no disco. Dois
lugares no disco, **uma consulta**.

## Decisão proposta

1. **Casa canônica da documentação do módulo = `memory/requisitos/<Modulo>/`**, com sub-estrutura
   fixa (forward-only — arquivos novos nascem assim; legado migra **oportunístico**, quando a
   pasta já for tocada por trabalho real):

   ```
   memory/requisitos/<Modulo>/
   ├── SPEC.md                       # US (anchor-lint)
   ├── BRIEFING.md                   # estado consolidado (1 pág)
   ├── SDD-<tela|dominio>-vN.md      # design detail por tela/domínio
   ├── ANTI-REGRESSAO-<tela>-legacy.md   # destilado do legado (pré-cond. Camada 1)
   ├── RUNBOOK-<tela>.md
   ├── _telas/                       # tudo por-tela: visual-comparison, gap, proto-baseline
   ├── adr/{arq,tech,ui}/            # ADRs locais do módulo (onde já existe, ex. Financeiro)
   ├── audits/                       # CAPTERRA*, AUDIT*, DISCOVERY*
   └── _STATUS-GENERATED.md          # derivado (não editar)
   ```

2. **O trio de tela (`charter`+`casos`) permanece ao lado do `.tsx`** ([ADR 0264](../0264-governanca-executavel-trio-dominio-e2e.md)/[0256](../0256-knowledge-survival-meia-vida-catraca-sentinela.md) intactas) — **Opção B**.

3. **Achabilidade = índice DERIVADO**, não pasta: as portas vivas (`screen-coverage:report`,
   `casos:report`) respondem "toda a doc desta tela/módulo, onde quer que esteja". Se faltar um
   ponto de entrada por módulo, ele **nasce derivado** (gerado no CI), nunca escrito à mão.

4. **Sem big-bang.** A sub-estrutura do item 1 é **forward-only + oportunística** (§5 2026-07-12).
   Reorganizar as ~45–50 doc dos módulos grandes em massa está **fora de escopo** — apodreceria
   no CI e não é a dor do [F] (a dor é achar, não mover).

## Se [W] preferir a Opção A (consolidação física)

A ADR então especifica a migração como **projeto próprio, [W]-gated**: (a) esta ADR vira
`supersedes: [264]`; (b) `casos-gate` re-aponta para `memory/requisitos/<Modulo>/_telas/`; (c)
migração **por módulo, forward-only** (nunca lote); (d) atualizar os 6 consumidores do raio de
impacto acima; (e) revisão adversarial antes do flip (toca gate required). Custo alto, benefício
= "nada fora" literal. **Registrado como opção consciente, não recomendado.**

## Consequências

- **Positivo (B):** achabilidade num comando; zero risco; ADR 0264/gate intactos; a dor do [F]
  resolvida hoje; a sub-estrutura padroniza o que nasce daqui pra frente (a campanha SDD sai
  consistente).
- **Custo aceito (B):** o trio segue fisicamente em `resources/js/` — "dois lugares, uma consulta".
- **Se A:** ver raio de impacto; vira epic de migração [W]-gated.

## Riscos Tier 0

- **Append-only:** esta ADR **não edita** a 0264 — se A for escolhida, cria nova com `supersedes`.
- **Gate required:** nenhum re-path sem [W] + revisão adversarial (é a regra "a IA não altera a
  máquina que a fiscaliza" — CODEOWNERS `memory/decisions/` + `.github/` = [W]).
- **Legado:** sub-estrutura forward-only; zero reorg em massa (§5 2026-07-12).

## Ratificação

Caminho canônico: PR + aprovação **[W]** (CODEOWNERS). Recomenda-se **revisão adversarial**
(`ciclo-adversary` / `debate-adversarial`) antes do merge, por tocar máquina required.
