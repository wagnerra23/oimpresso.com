---
name: alinhar-tela
description: Use quando Wagner pedir "alinhar a tela X", "ligar a máquina da tela Y", "o que já tem pronto e o que falta na tela Z", "fidelidade spec↔código de <Mod>/<Tela>", "/alinhar-tela <Mod>/<Tela>", OU ao retomar uma tela que foi construída ANTES da spec/governança (paradigma "tela primeiro, máquina depois") pra trazê-la à fidelidade verificável. Reconcilia spec ↔ charter ↔ código nos dois sentidos (US-sem-código = ghost; código-sem-US = órfão), entrega o quadro PRONTO/FALTA/TRAVADO/DRIFT, aplica os fixes seguros (âncoras ADR 0273 `Implementado em`, status-truth, buracos de numeração) e deixa os ganhos travados por gate. NÃO inventa path (toda âncora é verificada por existsSync), NÃO marca done sem código presente, NÃO evolui módulo silenciado sem OK explícito do Wagner.
trust_level: L1
owner: wagner
tier: B
parent_adr: 0273
related_adrs: [0093, 0179, 0256, 0270, 0271, 0273]
---

# /alinhar-tela — fidelidade spec↔código tela por tela

## Quando ativa

Tela já existe (foi construída), mas a "máquina" (spec viva + âncoras + gates) não está ligada ou está defasada. Objetivo: **dizer com honestidade o que está pronto e o que falta**, ligar o circuito de fidelidade, e travar pra não regredir — repetível até 100%.

Comando: `/alinhar-tela <Mod>/<Tela>` (ex.: `Cliente/Show`, `Financeiro/Conciliacao`).

## A escala de fidelidade (degraus)

| Degrau | Garante | Como |
|---|---|---|
| 1. Existência | "o artefato existe" (olho, ponto-no-tempo) | leitura |
| 2. Âncora | US→arquivo checável; link quebrado = vermelho | `**Implementado em:**` (ADR 0273) + `anchor-lint.mjs` |
| 3. Status-truth | `status:` de charter/US reflete a realidade | lint cruza charter×spec |
| 4. Prova por teste | 1 Pest/Browser verde por US `done` | suíte que morde (skill `sdd-avaliar`) |

Toda tela só **sobe** de degrau — nunca desce (catraca, ADR 0256/0271).

## Processo (5 passos)

1. **Reconciliar** (dois sentidos):
   - spec → código: cada US tem path real? (ghost = US sem código)
   - código → spec: cada Page/Controller/migration/componente tem US? (órfão = código sem US)
   - charter → realidade: `status:` do charter bate com o uso real?
2. **Reportar** — o quadro-padrão (abaixo). É o "informar o que tem e o que falta".
3. **Ligar** — fixes SEGUROS:
   - âncora `**Implementado em:** \`path\` · verificado@<sha7> (YYYY-MM-DD)` em toda US (gramática ADR 0273 §1). `_pendente_` se a tela não foi construída; `_parcial_` se falta pedaço.
   - corrige status defasado (US done que dizia backlog; charter live que é deprecated).
   - documenta buracos de numeração e pages órfãs.
4. **Travar** — promove gate (anchor-lint diff-only required quando o módulo estiver pronto; 1 Pest por US done). Gate nasce advisory (ADR 0271).
5. **Repetir** na próxima tela.

## Quadro-padrão (idêntico pra toda tela — é o "alinhado")

```
TELA: <Mod>/<Tela>  ·  SPEC: <arquivo>  ·  base @<sha7>
✅ PRONTO   <n> US   (com âncora verde + teste verde)
⬜ FALTA    <n> US   (trabalho real restante + onde plugar + estimate)
🔒 TRAVADO  <n> US   (atrás de gate: visual / decisão / dependência)
⚠️ DRIFT    <n> achados (spec mente/omite — corrigidos nesta passagem)
FIDELIDADE  degrau X → Y   |   PRONTO <pct>%
```

Relatório vai em `memory/requisitos/<Mod>/audits/ALINHAMENTO-<tela>-<data>.md`.

## Verificação de âncoras (rodar sempre antes de commit)

Toda âncora preenchida exige ≥1 path que existe + `verificado@<sha7> (data)`. Snippet (Node puro):

```js
const fs=require("fs");
const RE=/^\*\*Implementado em:\*\* (?:_pendente_(?: — .+)?|(?:_parcial_ · )?(?:`[^`]+`)(?: · `[^`]+`)* · verificado@[0-9a-f]{7} \(\d{4}-\d{2}-\d{2}\)(?: — .+)?)$/;
const txt=fs.readFileSync(SPEC,"utf8");
for(const l of txt.split("\n").filter(l=>l.startsWith("**Implementado em:**"))){
  if(!RE.test(l)) console.log("✗ gramática:",l);
  for(const m of l.matchAll(/`([^`]+)`/g)){const s=m[1];
    if(s.includes("/")&&!s.startsWith("/")&&!s.startsWith("~")&&!fs.existsSync(s)) console.log("💀 dead:",s);}
}
```

`<sha7>` = `git rev-parse --short=7 origin/main` (commit onde os paths foram verificados). Travessão `—` (U+2014), separador ` · ` (U+00B7) — literais, exigidos pela gramática.

## Pegadinhas (aprendidas na instância #1 — Cliente, 2026-06-22)

- **`anchor-lint.mjs` só varre `SPEC.md`** (glob `memory/requisitos/*/SPEC.md`). Se a spec viva tem outro nome (ex. `SPEC-us-063-078.md`), a máquina **não lê** as âncoras → a spec precisa morar em `memory/requisitos/<Nome>/SPEC.md`. _Resolvido na instância #1: a spec do cadastro foi movida pra `memory/requisitos/Cliente/SPEC.md` (Wagner "contacts ≠ crm", 2026-06-22)._ Não mexer no tooling compartilhado sozinho.
- **Backtick com `/` em nota de âncora vira path-morto** — o lint parseia `\`Sells/Create\`` como path. Em notas pós-`—`, escrever sem backtick.
- **Módulo silenciado** (banner no BRIEFING/reference) = só agir com OK explícito do Wagner.
- **Multi-tenant Tier 0** — não relaxar `business_id` ao verificar/editar (ADR 0093).
- **commit-discipline** — 1 PR = 1 intent, ≤300 linhas. Relatório+âncoras numa tela = 1 PR.
- **Nunca inventar path** pra "completar" uma âncora — `_pendente_` é estado legítimo (ADR 0273 §2).
