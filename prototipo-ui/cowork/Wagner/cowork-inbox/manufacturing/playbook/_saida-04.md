---
sessao: "_saida-04"
thread: "04 · Ordens de produção: filtro De/Até aplica no change (sem blur, sem botão lupa)"
dono: "[CL]"
data: 2026-09-25
prefixo_tocado: resources/js/Pages/Manufacturing/Index.tsx · resources/js/Pages/Manufacturing/Index.casos.md
fora_do_prefixo: tests/js/manufacturing-index-datas.test.tsx · .github/workflows/manufacturing-jsdom-gate.yml · scripts/governance/gates-registry.json · config/pageheader-shared-baseline.json
base_lida: wagnerra23/oimpresso.com@main 45a687387 (#7979 — playbook importado)
---
# _saida-04

## 1 · Feito

- `Index.tsx`: De e Até chamam `applyDateRange(de, até)` no `onChange`. Aplica quando o
  intervalo fica válido — os dois vazios (limpa) **ou** os dois `aaaa-mm-dd` com ano ≥ 2000.
  Só um preenchido não aplica (regra de antes, mantida). Se o intervalo é o mesmo já aplicado,
  não dispara (evita request redundante).
- Saíram o `onBlur={applyDateRange}` dos dois campos, o `<Button>` "Aplicar intervalo de
  datas" e o import `Search` que ficou sem uso.
- Mantidos: partial reload `only: ['productions', 'summary', 'filters']` (é o `applyFilter` de
  sempre) e os rótulos visíveis De/Até (`htmlFor="mfg-op-data-inicial"`).
- `Index.casos.md`: **UC-OP-06** "O intervalo de datas aplica ao escolher, sem botão", com teste
  que o cita; `last_run` → 2026-09-25.

## 2 · Flood ao digitar a data à mão — medido, sem debounce

O input é `type="date"`, não texto: só emite valor com a data inteira, e não emite nada entre
teclas do mesmo segmento. O único vazamento é o ano digitado à mão, que sai como `0002-…`,
`0020-…`, `0202-…` — o guard de ano ≥ 2000 barra os três. Resultado: 1 request por data
escolhida. Debounce não entrou porque não havia rajada a amortecer; se entrasse, atrasaria o
caso normal (escolher no calendário) sem ganho. O Inertia também cancela a visita anterior em
voo quando sai uma nova.

## 3 · Teste e mordida

`tests/js/manufacturing-index-datas.test.tsx` (vitest/jsdom, componente real, só o `router`
mockado) — **5 passed** local. Casos: controle positivo (select Local aplica), De+Até aplica
com `only:` certo, ano parcial não dispara, apagar os dois limpa, botão não existe.

Mordida provada por mutação, restauração conferida por hash (`92b7a67c…` antes e depois):
guard de ano removido → **1 failed**; `applyDateRange` fora do `onChange` → **3 failed**.

A lane é nova (`manufacturing-jsdom-gate.yml`, advisory, registrada no `gates-registry.json`):
spec sem workflow seria verde-por-não-execução (LC-13). Os UC-OP-01..05 continuam no Pest do
módulo; nenhum deles foi tocado.

## 4 · Verificação da thread

| item | resultado |
|---|---|
| `npm run lint` (eslint nos 2 arquivos) | exit 0 |
| `npx tsc --noEmit` | 306 erros **pré-existentes** no repo, **0** em `Manufacturing/Index.tsx` ou no spec — o "exit 0" pedido não é atingível pelo repo hoje |
| `casos-coverage-guard` | sem violação nova; nada de Manufacturing na lista |
| `memory-health` | 0 fail (registry Check G ok) |
| runtime em prod biz=1 | **pendente** — só depois do merge (LC-30). Checar no DOM: escolher De+Até recarrega sem clicar; ano pela metade não gera request; apagar os dois volta a lista; Local e "Só finalizadas" seguem funcionando |

## 4b · Header canon (exigido pelo gate required, ADR 0409)

Tocar o `Index.tsx` acordou a dívida do header antigo: `PageHeader · ratchet` reprovou no 1º
push. A tela migrou de `@/Components/shared/PageHeader` para o canon `@/Components/PageHeader`,
no idioma do #7858: `description` → `subtitle`, `action` → `actions` com `PageHeaderPrimary`
"Nova produção". O `icon="factory"` saiu porque o protótipo (`manufacturing-page.jsx`,
`.os-page-h`) não tem ícone: só título, subtítulo e o botão primário. Baseline do guard 64 → 63.

`visual-regression` (advisory) falhou **antes** de comparar pixel (`PIXEL_OUTCOME: skipped`):
`Manufacturing` não tem contrato em `tests/Browser/visreg-screens.json`. Não há `.snap` para o
`snap-diff` decodificar; é lacuna de cobertura pré-existente, não diferença visual medida.

## 5 · Fora do prefixo, declarado

O spec, o workflow e a entrada no registry estão fora do `prefixo` da thread. Entraram porque o
pedido exige "caso com teste que o cite" e teste sem lane não roda. Nenhum arquivo do
`nao_toca` foi aberto para escrita.
