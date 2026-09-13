---
date: "2026-09-13"
topic: "Thread 03 do playbook ponto (a11y não-cor no Espelho) — medida e encontrada JÁ FEITA; a premissa do playbook mediu a propriedade errada"
authors: [C]
outcomes:
  - "Thread 03 NÃO executada como trabalho de código: as 3 pernas do alvo já estavam em origin/main"
  - "Premissa do playbook (0 sr-only · 0 aria-live) media implementação, não predicado"
  - "Recibo do axe: run 33927949960 — 28 passed (82 assertions), 0 violação CRITICAL nas 12 telas declaradas"
related_adrs: [0383-ponto-interno-nao-coleta-biometria]
---

# Thread 03 (ponto · a11y não-cor) — parada por medição, não por bloqueio

## Veredito

**A thread 03 já estava feita quando o playbook foi escrito.** O trabalho saiu em
[#6407](https://github.com/wagnerra23/oimpresso.com/pull/6407) (2026-08-28) — cujo título é
literalmente o escopo da thread: *"Espelho — divergência para de ser só cor, e o dia-a-dia cabe
no telefone"* — e foi complementado por
[#6777](https://github.com/wagnerra23/oimpresso.com/pull/6777) (2026-09-04). O playbook é de
**2026-09-06**, 9 dias depois do primeiro.

Nenhum arquivo de tela foi tocado nesta sessão. O pedido do [W] previa exatamente este desfecho:
*"Se o sinal visual já estiver lá, PARE e reporte — não force trabalho."*

## O que o alvo pedia × o que está em `origin/main`

Medido contra `origin/main` (repo não-raso, `is-shallow-repository=false`; branch fresca 0/0).

| Perna do alvo (03-a11y-divergencia.md) | Estado | Evidência |
|---|---|---|
| `MonthHeatmap` — sinal visual não-cor por estado | ✅ | `stateIcons` (`X`/`Clock`/`ChevronsUp`/`ChevronUp`/`Check`) renderizado ao lado do valor |
| `MonthHeatmap` — divergência com sinal próprio | ✅ | `AlertTriangle` no canto da célula, `currentColor`, além do anel âmbar |
| `MonthHeatmap` — texto para leitor de tela | ✅ | `aria-label` compondo estado + valor + `"divergência na apuração"` |
| `MonthHeatmap` — chave de leitura do glifo | ✅ | legenda repete o mesmo glifo + entrada "Divergência" com contador |
| `Show` — linha divergente com sinal não-cor | ✅ | `AlertTriangle` no início da linha (o `bg-warning/5` é tint de 5%) |
| `Show` — estado legível por leitor de tela | ✅ | coluna `Estado` imprime `divergencia` **em letra** (superior a `sr-only`: não duplica) |
| Mobile-fit — sem overflow horizontal em 390 px | ✅ | tabela de 8 colunas é `hidden md:block`; abaixo de `md`, `Stack` de `DiaCard` |
| Mobile-fit — alvo de toque ≥ 24 px | ✅ | `min-h-[44px]` nos blocos do cartão; célula da grade ≈ 40,8 px em 390 px |
| `Espelho/Index.tsx` | ✅ nada a fazer | é lista de colaboradores (matrícula/nome/CPF/e-mail); **não tem estado sinalizado por cor** |

## Por que o playbook concluiu o contrário

A premissa era *"0 `sr-only` · 0 `aria-live` em `resources/js/Pages/Ponto/**`"*, e a Prova declarada
era *"`MonthHeatmap.tsx` contém `sr-only`"*. As duas contam **uma implementação possível**, não o
**predicado** — *o estado é comunicado sem depender de cor?*. O #6407 resolveu por `aria-label` +
glifo + legenda, que satisfaz WCAG 2.2 SC 1.4.1 e SC 1.3.1 sem duplicar texto na árvore.

Recontagem de hoje em `Pages/Ponto/**`: `sr-only` **0** · `aria-live` **0** · `aria-label` **10** ·
`role=` **2**. A premissa estava certa nos dois primeiros números e o gap não existia.

Família já catalogada: §5 2026-07-16 (*medir a propriedade errada e chamar de verificado*) e
§5 2026-09-03 (*lápide que declara um GAP tem prazo de validade implícito — re-medir antes de citar*).

## Medição de a11y — o oráculo já existe e não precisou de pa11y ad-hoc

O playbook pedia axe/pa11y antes e depois. Sem delta de código, não há dois números a comparar;
o que há é o **veredito vigente da máquina que já mede**, instalada pelo #6777:

- `tests/Browser/CoreScreens/A11yAxeBrowserTest.php` roda **axe-core em Chromium real**, piso
  `level: 0` (CRITICAL), sobre o dataset derivado de `"a11y": true` em `visreg-screens.json`.
- São **12 telas** declaradas; `Ponto/Espelho/Show` e `Ponto/Espelho/Index` estão **entre elas**.
- Último run com execução confirmada: **33927949960** (04/09 23:02) — `PASS A11yAxeBrowserTest`,
  **28 passed (82 assertions)**. Assertions > 0 é o que prova execução, não o `conclusion` (LC-13).

Histórico útil: no run anterior do mesmo PR (33925329742) o gate **mordeu** —
`[critical] Form elements must have labels` numa tela do Ponto, `1 failed / 27 passed`. O conserto
é o `aria-label="Mês de referência"` hoje no `Show.tsx`, com o comentário explicando por que não
virou `<label>` visível (mexeria no pixel e exigiria gate visual). Gate que já reprovou uma vez é
gate que morde.

## Achados adjacentes (medidos, não consertados aqui)

1. **O step do axe aparece `skipped` em 14 de 15 runs de `main`** (17/08 → 09/09). Fui verificar
   antes de chamar de gate morto: **não é**. A condição é
   `if: steps.mode.outputs.ui == 'true'`, e `ui = steps.impact.outputs.visual_required` — ou seja,
   **path-scoped por desenho**, com `skip-as-pass` declarado (padrão ADR 0271 onda 2). Push que não
   toca UI não deve mesmo pagar uma lane de browser. O veredito válido vem dos PRs que tocam UI.
   Registro porque `success` de lane com step `skipped` é ausência de medição, e quem ler o verde
   sem abrir o step conclui saúde.

2. **Ponteiro impreciso no docblock do `A11yAxeBrowserTest.php`**: ele cita *"VERDES no CI
   (run 33925329742)"*, mas 33925329742 é justamente o run **vermelho** (o que achou a violação
   de label). O verde é o **33927949960**. A afirmação material — telas do Ponto auditadas e
   verdes — é verdadeira; o número ao lado aponta pro run errado. Não corrigi: é arquivo de outro
   intent, e `commit-discipline` manda 1 PR = 1 intent.

## Endereço do recibo — em aberto para [W]

O playbook manda escrever `_saida-03.md`, mas esse endereço **não existe mais**: o
`cowork-ssot-guard` R3 só admite `.md` flat em `prototipo-ui/cowork/<dono>/handoffs/`, e a pasta
`design-docs/` foi removida pelo #7224 (ADR 0397 D5). O recibo desta thread vive **neste session
log + no corpo do PR**. **Decisão pendente [W]:** onde passam a morar os `_saida-NN.md` das threads
dos playbooks — e, no limite, se o próprio playbook (que só existe em `4f51a9ec78^`) volta a ter
endereço no repo ou segue recuperável por git.

## O que NÃO foi feito, e por quê

- **Nenhum Edit em `Pages/Ponto/**`** — não havia gap. Inventar `sr-only` redundante ao lado de um
  `aria-label` que já diz a mesma coisa criaria duplicação na árvore de acessibilidade
  (texto lido duas vezes), piorando a tela para fechar um número.
- **Nenhum componente novo no DS** — o `PARAR SE` da thread previa virar pedido de DS; não chegou
  a ser necessário, o glifo saiu de `lucide-react` já em uso no arquivo.
- **Copy e ordem do contrato `ponto-espelho` intocadas** — "Divergência", "Falta", "Feriado"
  seguem literais.
