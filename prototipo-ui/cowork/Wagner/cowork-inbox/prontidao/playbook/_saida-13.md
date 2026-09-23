---
sessao: "13"
titulo: Scorecard · superadmin (4 telas)
executor: "[CL]"
base: 317e1b4ec33
---
# _saida 13

## Checklist
1. ✅ Pré-Flight das 4 telas: charter + casos existem ao lado de cada `.tsx` (nenhum PARAR SE de charter)
2. ✅ Nota 16-dim por tela, com evidência `arquivo:linha` por dimensão (campo `evidencia:`)
3. ✅ 4 YAMLs com o slug exato da espec · `baseline_anterior` = a própria nota
4. ✅ YAML parseia (js-yaml: 16 dimensões + 16 evidências + gaps com `best_of_class`/`fix` nas 4)
5. ✅ Só o prefixo tocado — `.tsx` e charters intactos; zero git ops
6. ✅ Sem valor em R$ nos YAMLs (`grep -nE 'R\$\s?[0-9]'` → 0)

## Notas

| tela | slug do YAML | arquétipo | nota | pior dimensão | gaps |
|---|---|---|---|---|---|
| `superadmin/Assinaturas/Index` | `superadmin-assinaturas-index` | list | **77** | error_recovery 62 | 3 |
| `superadmin/Dashboard/Index` | `superadmin-dashboard-index` | dashboard | **75** | preflight_conformance 62 | 4 |
| `superadmin/Negocios/Index` | `superadmin-negocios-index` | list | **74** | a11y_wcag 58 | 3 |
| `superadmin/Pacotes/Index` | `superadmin-pacotes-index` | grid-cards | **77** | affordance 68 | 3 |

`nota` = média aritmética das 16 dimensões, arredondada (assinaturas 76,9 · dashboard 74,5 · negócios 74,4 · pacotes 77,0). Persona única `wagner` (1440px) em todas — `personas-por-modulo.yml:91` + a seção Mission de cada charter.

## Como a nota foi dada — e o que ela NÃO é

**Nota de leitura de código.** Lida em `.tsx` + charter + controller, **sem browser de prod**:
sem screenshot, sem axe, sem medição de perf. O `PARAR SE` "a tela não abrir em prod" **não foi
verificado** nesta thread — só o Dashboard traz smoke registrado (frontmatter do charter,
2026-08-19); Assinaturas, Negócios e Pacotes têm charter `draft` justamente por falta de sinal de
prod. Quem rodar os passos 2-4 (E2E/axe/smoke) deve re-medir a11y e performance, que são as duas
dimensões mais sensíveis a ver a tela viva.

## Achados que merecem olho (estão nos gaps)

- **Negócios — a11y é a pior nota das quatro (58):** a linha abre o drawer só por clique
  (`Negocios/Index.tsx:337-341`, sem teclado) e o drawer é hand-roll sem focus trap
  (`:387-405`), enquanto a irmã Assinaturas já usa `Sheet` (`Assinaturas/Index.tsx:568`).
- **Assinaturas — erro de validação é invisível:** os dois `useForm` da gaveta nunca renderizam
  `.errors` (`Assinaturas/Index.tsx:552-563`); se o servidor recusar a transição, nada aparece.
- **Dashboard — charter × código divergem:** `Dashboard/Index.charter.md:61-65` descreve um MRR
  que distingue "N vigentes sem preço"; o `.tsx` (`:206-211`) só distingue fonte indisponível /
  sem assinatura ativa, com a fonte já trocada para `rb_subscriptions`. Pela regra de precedência,
  corrigir o perdedor no mesmo PR — **não tocado aqui** (charter fora do prefixo).
- Cores cruas: `text-emerald-600` (`Dashboard/Index.tsx:83`) e `bg-amber-500` (`Negocios/Index.tsx:443`).

Gaps ficam no YAML; **nenhum virou task** — batch `tasks-create` é decisão [W].

## `node scripts/qa/prototipo-readiness.mjs` (sem --json)

Antes (árvore `317e1b4ec33`):

```
  ✅ PRONTAS pra aplicar HOJE (trio + casos+UC + scorecard trava o comportamento): 59
       [Superadmin] superadmin/Assinaturas/Index             falta: scorecard
       [Superadmin] superadmin/Dashboard/Index               falta: scorecard
       [Superadmin] superadmin/Negocios/Index                falta: scorecard
       [Superadmin] superadmin/Pacotes/Index                 falta: scorecard
  Total de telas com protótipo real: 94
```

Depois:

```
  ✅ PRONTAS pra aplicar HOJE (trio + casos+UC + scorecard trava o comportamento): 63
       [Superadmin] superadmin/Assinaturas/Index
       [Superadmin] superadmin/Dashboard/Index
       [Superadmin] superadmin/Negocios/Index
       [Superadmin] superadmin/Pacotes/Index
  Total de telas com protótipo real: 94
```

59 → 63: exatamente as 4 telas saíram de "falta: scorecard" para PRONTAS.

## `node scripts/qa/screen-grades-ratchet.mjs`

```
Catraca screen-grade · 196 telas · ✅ 192 ok/subiu · ✨ 4 novas · 🔻 0 regrediram · 🗑 0 deleção(ões) legítima(s)
✓ CATRACA: nenhuma tela regrediu.
```

rc=0. As 4 entram como **novas** (ausentes em `origin/main`) e viram o baseline da catraca.

## PARAR SE
- Charter ausente: **não acionado** (as 4 têm charter + casos).
- Tela não abre em prod: **não verificável** sem browser nesta thread — declarado acima, não assumido.
