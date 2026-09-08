---
date: "2026-09-08"
time: "1200 BRT"
slug: "onda7-financeiro-recurring-paridade-e-2-fixes"
tldr: "Onda 7 fechada no escopo Financeiro + RecurringBilling: 6 vínculos de paridade, 5 telas medidas no runtime e os 2 defeitos achados corrigidos. 12 suspeitas levantadas → 2 defeitos reais; das 10 descartadas, 3 foram erro MEU de medição (a mesma classe, 4× contando a do próprio ledger)."
decided_by: [W]
cycle: null
prs: [6975, 6982, 6989, 6993]
us: []
next_steps:
  - "Classificar a divergência estrutural do Fluxo (4 KPIs strip × 3 cards) — DECIDIDA/DERIVA/DESIGN-ANDOU é julgamento [W], não medição"
  - "Handoff pro Cowork: --accent 0.70 no protótipo × 0.55 do canon (ADR 0190). A PROD está certa; corrigir nela seria descer deriva"
  - "Dívida separada: 27 textos reprovam AA no RecurringBilling — PRÉ-EXISTENTES, idênticos antes/depois da tokenização"
  - "Não medidas: Financeiro/ProvaViva (âncora é HTML fora do shell) e Financeiro/Caixa (n/a declarado)"
  - "Frescor do cobranca-recorrente-page.jsx segue sem prova por hash — volta inline no get_file e a rota fiel depende do bundle, que o painel declara sem dono"
related_adrs: ["0130-handoff-append-only-mcp-first", "0264-governanca-executavel-trio-dominio-e2e", "0190-pageheader-primary-universal-roxo"]
---

# Handoff 2026-09-08 12:00 BRT — Onda 7 (Financeiro + RecurringBilling): paridade medida e os 2 fixes

## TL;DR

Escopo fechado. `parityLinked` 18 → 66 e `declared` 98% no main (parte das sessões irmãs).
O que interessa pro próximo agente não é o placar: das **12 suspeitas** que a máquina e eu
levantamos, **2 eram defeito real**. Das 10 descartadas, **3 foram erro meu de medição** — e as
3 são a mesma classe (LC-08), com a 4ª cometida ao escrever o recibo dela.

## Cronologia desta sessão

| Quando | Evento |
|---|---|
| 10:28 | #6975 — 6 vínculos `related_visual_comparison` + 2 âncoras `n/a` declaradas |
| 10:56 | #6982 — Fluxo, Conciliação, DRE e RecurringBilling medidos no runtime |
| 11:24 | #6989 — `text-align: start` nos KPIs do Unificado (defeito 1) |
| 11:47 | #6993 — tokenização da superfície do RecurringBilling (defeito 2) |

## Estado MCP no momento do fechamento

⚠️ **Tools MCP indisponíveis nesta sessão** — o `brief-fetch` chegou pelo hook `SessionStart`
(curl), e `cycles-active` / `my-work` / `sessions-recent` / `whats-active` não estavam expostas
como tool. Usei o **fallback filesystem** que o [how-trabalhar.md §Fallback](../how-trabalhar.md)
autoriza. Declaro isso em vez de omitir: o checklist MCP-first do ADR 0130 **não** foi cumprido
pela via canônica.

O que consegui provar por outra via:
- **Brief (hook, 2h de idade no início):** cycle sem nome, 5 HITL pendentes, 0 incidentes 24h.
- **Sessões paralelas:** confirmadas por evidência de git, não por `whats-active` — o main
  recebeu #6971 (Compras/Estoque) e #6959/#6957 de sessões irmãs durante a sessão, e o
  `parityLinked` subiu de 24 (meu) para 66 (todos).
- **Colisão de path:** zero. Verifiquei arquivo a arquivo (`HEAD:<f>` × `origin/main:<f>`) antes
  de cada push; nenhum dos meus 10 arquivos foi tocado por outra sessão.
- Já existiam handoff e session log de 2026-09-08 de **sessão irmã**
  (`1145-devolutiva-rodada-pontual-e-lane-muda`) — slug diferente, arquivo novo, sem sobrescrita.

## O que foi entregue

**#6975 — vínculos.** 6 charters passaram a declarar `related_visual_comparison`. O vínculo saiu
de **ler o conteúdo** de cada inventário, nunca do nome — e isso mordeu: `index-visual-comparison.md`
**não** é do `Financeiro/Index`, é da Conciliação (`tela: /financeiro/conciliacao`).
2 órfãos ficaram **legítimos**: `boletos-*` (a tela foi deletada no #1142; `/boletos` é 301) e
`unificado-3-lentes-*` (mesmo alvo, e o parser aceita 1 vínculo por charter).
2 silenciosas resolvidas com `n/a (herda PT-02)` — **não** promovi `cobranca-recorrente-page.jsx`
a âncora: ele se declara porte reverso do git e a aba Planos dele é um `Placeholder`.

**#6982 — medição.** Mesma sonda, mesmo tema (`dark`), mesma viewport, ambos estabilizados, com a
âncora provada SYNC antes de comparar. DRE = paridade boa. Conciliação = divergência que o charter
já declarava (ADR 0236). Fluxo = estrutural. RecurringBilling = 1 achado.

**#6989 / #6993 — os 2 fixes.** `text-align` provado em runtime com controle negativo
(Conciliação, `<div>`, no-op). Tokenização: 518 de 586 cores cruas; 68 intocáveis por decisão
(`text-white` está sobre `bg-primary`, overlay, gradientes).

## O que o próximo agente precisa saber (e não repetir)

**A verificação pagou 10 vezes.** Se eu tivesse aberto trabalho para cada suspeita da máquina,
seriam 10 correções indevidas — e **duas na direção errada** (descer deriva do protótipo pra prod).

**As 3 falhas de medição minhas, todas LC-08:**
1. `getComputedStyle(card).color` — cor **herdada no container**, não a renderizada. Anunciei
   "0.94 sobre branco = ilegível"; os textos-folha mediam 19-20:1.
2. Fundo do card **ancestral** ignorando `background-image` — acusei "53 avatares reprovam AA";
   cada avatar tem `linear-gradient` próprio.
3. Luminância por **match de dígitos**, que lê `oklch(0.965 0.004 240)` como `rgb(0.965,0.004,240)`.
   Publiquei ao [W] que "89% ficariam ilegíveis". Com o medidor certo: **idêntico** antes e depois.
4. E ao escrever o recibo disso no ledger, meu script pegou o **primeiro** campo `Ocorrências` do
   arquivo (outra classe) em vez do campo dentro do bloco `## LC-08`. Revertido e refeito com o
   bloco delimitado.

**Receita que sobrevive:** contraste não se mede com regex de dígitos — deixe o browser converter
(`ctx.fillStyle` → `getImageData`), rode um **controle positivo de par conhecido** (preto/branco
deve dar ~21) antes de confiar no número, e o fundo efetivo sobe a árvore até um
`background-color` opaco **ou** um `background-image`. Edição em arquivo multi-seção delimita a
seção antes de casar campo.

## Gates que morderam, e o que ensinaram

- **`charter-us-lint`** (#6989 anterior): passou local, falhou no CI. Rodei sem `BASE_REF`; o CI
  roda com. Um script com N modos é N gates.
- **`CSS size ratchet`**: `fin-cowork.css` 704→709. Rebaseline é o caminho que o próprio gate
  prescreve; o diff do baseline ficou cirúrgico (nenhum dos outros 38 arquivos entrou de carona).
- **`casos-gate` G-6**: tocar `.tsx` acorda o stale mesmo com mudança semanticamente inerte —
  o gate mede **data**. Revalidação analítica com grep contado + controle positivo nos 14 testes
  citados (0 asserts de aparência) e verificação de que as cores de status ficaram intactas.
