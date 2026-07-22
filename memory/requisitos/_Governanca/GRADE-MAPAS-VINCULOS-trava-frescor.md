# Vínculos "arquivo → doc obrigatório" — mapa pras portas vivas (cross-módulo)

> **O que é isto.** O índice de **"mexeu no arquivo X → qual doc Y deve acompanhar"**, resolvido para as
> **portas vivas** que já derivam/enforçam cada vínculo — **não** um catálogo de 35 mapas escritos à mão.
> Substitui o antigo "roteiro de generalização" (que mandava montar 1 `MAPA-VINCULOS.md` por módulo).
>
> **Por que mudou (decisão [F], Opção B — 2026-07-22).** Um mapa escrito à mão apodrece no dia
> (lei-mãe [ADR 0256](../../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md): *derivado+enforçado
> sobrevive; escrito+lembrado apodrece*). As contagens por módulo (telas/charter/casos) que o roteiro carregava
> **já são derivadas** pela porta viva `screen-coverage:report` (gate required). Ver
> [how-trabalhar.md §"Mapa de arquivos por tela — é COMANDO, não arquivo"](../../how-trabalhar.md). Então os
> vínculos passam a **apontar pra máquina que já os cobre**, e o que sobra são os **gaps** (§3) — insumo pro
> mecanismo de enforcement que o [W] está construindo.
>
> **Base:** portas confirmadas em `origin/main` (o checkout local é stale). **Nada de enforcement é montado aqui.**

---

## 0. Decisões que fecham o desenho (herdadas do piloto + esta sessão)

O piloto ([Produto/MAPA-VINCULOS-trava-frescor.md](../Produto/MAPA-VINCULOS-trava-frescor.md) §0) resolveu as pendências;
esta grade herda e **não reabre**:

| # | Pendência (era) | Decisão | Efeito |
|---|---|---|---|
| 1 | Palavra de escape | **Não haverá escape** ([W]) | Sem `escape_word`; cada mudança tem uma porta com lar. |
| 2 | Blade legacy entra na v1? | **Sim, entra** ([F]) | Vínculo firme, não `[candidato]`. |
| 3 | Q-noise — BRIEFING super-dispara | **Resolvido pela porta** | O BRIEFING **não** dispara em todo `.tsx`; frescor dele é medido **doc↔código** por `briefing-code-staleness` (não em CSS puro). |
| 4 | Onde a trava mora / codar o gate | **É do [W]** (outro mecanismo) | Aqui só se **declara qual porta viva** cobre cada vínculo. |
| 5 | Formato dos 35 mapas | **Opção B — portas vivas, não 35 `.md`** ([F] 2026-07-22) | Esta grade vira índice→porta + gaps. |

---

## 1. O mapa: gatilho → doc → porta viva que já cobre

Cada linha é um vínculo "mexeu em X → doc Y deve acompanhar". A coluna **porta** é a máquina que já deriva/enforça —
verificada em `origin/main`. `required` = gate que bloqueia merge.

| Mexeu em (gatilho) | Doc que deve acompanhar | Porta viva (já existe) | Gate |
|---|---|---|---|
| `resources/js/Pages/<Mod>/<Tela>.tsx` | **charter** (`.charter.md`) fresco/válido | `charter:audit` via [ChartersFreshnessChecker](../../../Modules/Governance/Services/Checkers/ChartersFreshnessChecker.php) + [CharterAuditCommand](../../../Modules/Governance/Console/Commands/CharterAuditCommand.php) ([ADR 0220](../../decisions/0220-charters-freshness-checker-adapter.md)) | `charter status:live precisa de sinal de prod` — **required** |
| `resources/js/Pages/<Mod>/<Tela>.tsx` | **casos + teste** (trio) | `npm run casos:report` → [casos-coverage-guard.mjs](../../../scripts/casos-coverage-guard.mjs) ([ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)) | `Casos-coverage · ratchet` — **required** |
| `resources/js/Pages/<Mod>/<Tela>.tsx` | **charter/e2e/scorecard/a11y** presentes | `npm run screen-coverage:report` → [screen-coverage-map.mjs](../../../scripts/qa/screen-coverage-map.mjs) | `screen-coverage-gate` — **required** |
| Backend do módulo (Controller/Service/Model) | **BRIEFING** do módulo fresco | [briefing-code-staleness.mjs](../../../scripts/governance/briefing-code-staleness.mjs) (compara doc↔código) | advisory |
| CSS/design **global** (`resources/css/*`, tokens, shell) | **`_DesignSystem/CHANGELOG.md`** + índice | freshness G1 ([ADR 0236](../../decisions/0236-governanca-evolucao-doc-design.md)) | advisory |
| Protótipo Cowork ↔ `.tsx` | **`<tela>.map.json`** fresco | [design-code-map-check.mjs](../../../scripts/governance/design-code-map-check.mjs) (staleness por SHA) | advisory |

**Consequência:** os vínculos de **tela** (charter · casos · cobertura) já estão cobertos por gate **required**; os de
**módulo/design** (BRIEFING · CSS · map.json) têm porta viva em nível advisory. Nenhum precisa de `.md` novo à mão.

---

## 2. A lista de módulos é derivada — não se escreve à mão

O antigo roteiro tinha uma tabela de 41 módulos com contagens `telas/charter/casos`. Isso **apodrece** (ADR 0256) e
**duplica** a porta viva. Para o retrato atual (quais módulos/telas, o que falta), rode:

```bash
npm run screen-coverage:report   # cobertura por tela (charter/e2e/scorecard/a11y)
npm run casos:report             # trio (casos + teste) por tela
```

Os números vivem lá, carimbados e recalculados da árvore — nunca nesta página.

---

## 3. Gaps reais (o que NENHUMA porta viva cobre ainda) — insumo pro mecanismo do [W]

Só **dois** vínculos do piloto não têm porta que os force. É aqui que o mecanismo de enforcement do [W] agrega valor —
o resto já está coberto:

| Gatilho | Doc alvo | Por que é gap | Nota |
|---|---|---|---|
| Decisão de design por tela | **`<tela>.decisoes.md`** | [ADR 0293](../../decisions/0293-governanca-decisao-design-responsavel-registro-veredito.md) cravou o Decision Register, mas **0 arquivos existem** e nada força o preenchimento | Ativar o `.decisoes.md` é o maior ganho isolado |
| Backend → **SDD** | `SDD-*.md` | SDD só existe no **Produto** (1 de 41 módulos) → gatilho→SDD nasce **dormente** nos demais | Liga quando o módulo ganhar SDD |

---

## 4. Âncoras (zero-órfãos)

Piloto: [Produto/MAPA-VINCULOS-trava-frescor.md](../Produto/MAPA-VINCULOS-trava-frescor.md) ·
Lei-mãe: [ADR 0256](../../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md) ·
Portas: [screen-coverage-map.mjs](../../../scripts/qa/screen-coverage-map.mjs) ·
[casos-coverage-guard.mjs](../../../scripts/casos-coverage-guard.mjs) ·
[briefing-code-staleness.mjs](../../../scripts/governance/briefing-code-staleness.mjs) ·
[design-code-map-check.mjs](../../../scripts/governance/design-code-map-check.mjs) ·
[ChartersFreshnessChecker.php](../../../Modules/Governance/Services/Checkers/ChartersFreshnessChecker.php) ·
[CHANGELOG do DS](../_DesignSystem/CHANGELOG.md) ·
[how-trabalhar.md](../../how-trabalhar.md).
ADRs: [0220](../../decisions/0220-charters-freshness-checker-adapter.md) ·
[0236](../../decisions/0236-governanca-evolucao-doc-design.md) ·
[0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md) ·
[0293](../../decisions/0293-governanca-decisao-design-responsavel-registro-veredito.md).

---

_Append-only. Autor: Claude (Opus 4.8) + Felipe [F]. Opção B (portas vivas, não docs à mão). Base: `origin/main`. Sem PII. Sem mudança de código._

## Histórico
| Data | Autor | Mudança |
|---|---|---|
| 2026-07-21 | [F]/[CC] | Grade criada como roteiro de 35 mapas à mão (4 ondas P0-P3). |
| 2026-07-22 | [F]/[CC] | **Reescrita — Opção B:** vínculos apontam pras portas vivas (ADR 0256); tabelas de contagem à mão removidas (deriváveis por `screen-coverage:report`); §3 isola os 2 gaps reais (`.decisoes.md` vazio + SDD dormente) pro mecanismo do [W]. |
