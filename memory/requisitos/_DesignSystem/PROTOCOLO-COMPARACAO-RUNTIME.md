---
slug: protocolo-comparacao-runtime-prod-prototipo
title: "Protocolo de comparação RUNTIME — produção × protótipo (v2)"
type: protocolo
module: _DesignSystem
status: proposto
owner: wagner
date: "2026-07-06"
related_adrs: [0299, 0315, 0324, 0110]
related: [SCREEN-GRADE-METODO.md, framework-15-dimensoes.md]
---

# Protocolo de comparação RUNTIME — produção × protótipo (v2)

> **Por que existe (Wagner 2026-07-06):** a comparação v1 (extrator de DOM medindo *presença* de
> elementos) declarou "essencialmente igual" e **errou** — perdeu: footer/somatório, ícone de seta,
> filtro em 2 linhas, título, e o **full-reload** (anti-padrão). Wagner: *"acho que a comparação feita
> está errada. peço um método melhor… solicito um protocolo melhor."* Este é o método melhor.

## Os 2 erros de raiz do v1 (que este protocolo conserta)

1. **FONTE ERRADA.** O v1 comparou prod contra o **espelho do repo** (`prototipo-ui/cowork/<arq>`),
   que **pode estar velho** (o mesmo drift que o `cowork-mirror-freshness` existe pra pegar — [ADR 0324](../../decisions/proposals/0324-frescor-espelho-cowork-dispatch-sla-limite-plataforma.md)).
   A fonte da comparação é o **Cowork VIVO** (projeto `019dcfd3`, via `DesignSync.get_file` ou render),
   **nunca** o espelho estático sem antes provar `SYNC`. **Regra dura:** antes de comparar, rodar
   `--compare` do mirror-freshness pra aquele arquivo; se `STALE`, re-exportar ANTES.
2. **DIMENSÃO RASA.** Mediu "elemento existe?" (presença). Não mediu **comportamento**, **layout**,
   **ícone**, **tipografia**, **footer**. Presença ≠ fidelidade (L-24 / "presença ≠ correção").

## As 7 dimensões (medir TODAS, nos DOIS lados, com a MESMA sonda)

| # | Dimensão | O que medir | Técnica (Chrome MCP) | O que o v1 perdeu |
|---|---|---|---|---|
| **D1** | **Comportamento / rede** ⭐ | toda interação de filtro/nav = **partial reload** (Inertia `only:[...]` + `preserveScroll`), **NUNCA** re-fetch de todas as props nem document-load | clicar 1 filtro → `read_network_requests` (initiatorType = xhr/fetch, não document) **E** checar no código `router.get(..., { only:[...] })` + controller `Inertia::defer`. Marker `window.__x` deve sobreviver (senão = document reload). | **o reload D-14** — o pior, não estava em dimensão nenhuma |
| **D2** | **Layout / quebra de linha** | nº de LINHAS visuais de cada zona (ex: barra de filtro = 2 linhas no proto vs 1 na prod), ordem das zonas, wrapping | agrupar `getBoundingClientRect().top` dos controles; contar grupos | filtro 1×2 linhas (eu **dispensei** dizendo "não rearranjei") |
| **D3** | **Ícones** | qual ícone e COMO (SVG lucide vs glyph de texto vs emoji) | `el.querySelector('svg')` vs texto; comparar o nome/《d》do path | seta ‹› (prod texto) vs ChevronLeft (proto SVG) |
| **D4** | **Tipografia** | `font-size`/`weight` dos elementos-chave (título da página, título da lista, valor do KPI, linha da tabela) | `getComputedStyle` de cada âncora tipográfica | "título grande" |
| **D5** | **Footer / somatórios** | conteúdo + FORMATO de cada total (labels, ordem, se há linha de saldo/net, formatação BRL) | `textContent` estruturado do footer, campo a campo | "somatório diferente" |
| **D6** | **Cor / token** | accent (hue), pills (radius/border/saturação), estados | `getComputedStyle` computado (v1 acertou aqui) | — (ok) |
| **D7** | **Densidade** | row-height, paddings, gaps | computed (v1 parcial) | — (parcial) |

⭐ **D1 é a mais importante e a mais barata de esquecer.** Um "print igual" pode esconder um
full-reload. Comportamento **antes** de pixel.

## Procedimento (passo a passo)

0. **Provar frescor da fonte** — `node scripts/governance/cowork-mirror-freshness.mjs --compare <snap>`
   pro arquivo da tela; `STALE` ⇒ re-exportar do Cowork ANTES de comparar. (Sem isso, você compara
   contra design velho — o erro do v1.)
1. **Abrir os DOIS** — prod (Chrome logado) + o Cowork vivo (mesma tela, mesmo tema — o tema é
   escolha do Wagner; comparar no MESMO tema).
2. **Rodar a MESMA sonda D1–D7** nos dois → JSON estruturado por dimensão.
3. **D1 sempre**: clicar 1 filtro em prod, `read_network_requests`, classificar (partial vs full).
   Ler `aplicar()`/controller pra confirmar `only:` + `defer`.
4. **Diff por dimensão** → tabela `dimensão | prod | proto | veredito {IGUAL / DIVERGE (bug) /
   DIVERGE (decisão) / PROD-À-FRENTE}`.
5. **Screenshots de REGIÃO** (footer, barra de filtro, título) — não só a tela inteira; o v1
   olhou o todo e perdeu as partes.
6. **Registrar** no `<tela>-visual-comparison.md` (append; 1 tema = 1 doc).

## Regra 0 — PÓS-DEPLOY OBRIGATÓRIO, SEM O WAGNER PEDIR (Tier 0 deste protocolo)

> Origem (Wagner 2026-07-06, verbatim): *"preciso pedir para olhar se deu certo no chrome? e
> comparar com o prototipo? por que já sei que vai falar que o que tu fez está tudo aplicado,
> mas sempre deixa de analisar e comparar e eu tenho que falar de novo. isso deve ficar no método."*

Depois de **TODO merge que toca tela** (`resources/js/Pages/**` · `resources/css/**` · controller
que alimenta a tela), o agente **NÃO declara "aplicado/pronto/funcionando"** até completar, por
conta própria:

1. **Esperar o DEPLOY ficar `success`** (não o merge — o deploy).
2. **Abrir a tela em PRODUÇÃO no Chrome** (aba nova, sem injeção prévia) e **verificar item a
   item o que o PR prometeu** (computed style/DOM pra cada mudança — não só "a página abre").
3. **Comparar com o protótipo** (render do espelho SYNC-provado ou Cowork vivo, MESMO tema) —
   as dimensões D1–D7 tocadas pelo PR, com a MESMA sonda nos dois lados.
4. **D1 sempre que o PR tocou navegação/filtro**: 1 interação em prod + trace de rede
   (partial vs full-reload).
5. **Entregar PROVA no chat**: screenshot da prod + (quando visual) do protótipo, com veredito
   por item. "CI verde" e "merge feito" NÃO são prova de aplicado — prova é pixel/DOM/rede em prod.

Divergiu do prometido → é regressão AGORA (não "quase deu"). Pareia com R1 (smoke real) do
PROTOCOLO-WAGNER-SEMPRE e com o hook `post-merge-ui-smoke-required` — este protocolo ADICIONA o
que o hook não cobre: **a comparação com o protótipo**, sem o Wagner mandar.

## Regra de veredito (não reverter decisão aprovada)

Divergência **não é automaticamente bug**: pode ser **prod-à-frente** (evolução aprovada — ex: pills
#3391) ou **decisão**. Cruzar com o **charter** + trilha antes de "consertar". Só é **bug** o que
(a) é anti-padrão do sistema (D1 full-reload), (b) contradiz o token/ADR canon, ou (c) o Wagner
aponta como não-intencional. Caso contrário: **a prod é o mais novo → re-exportar o protótipo**, não
arrastar a prod pra trás.

## Achados da 1ª aplicação (Financeiro/Unificado, 2026-07-06)

- **D1 🔴 BUG (prioridade):** `aplicar()` = `router.get(url, params, {preserveState,preserveScroll,replace})`
  **sem `only:`** ([Index.tsx:1240](../../../resources/js/Pages/Financeiro/Unificado/Index.tsx)) + controller
  **sem `Inertia::defer`** ([UnificadoController.php:295](../../../Modules/Financeiro/Http/Controllers/UnificadoController.php)) →
  cada filtro re-roda TODAS as queries (kpis/lancamentos/contas/categorias) + re-render da página inteira.
  XHR, mas "carrega tudo" = o D-14 que a proibição "Inertia::defer DEFAULT" combate. **"Não pode em tela nenhuma"** (Wagner).
- **D3 🟡 DIVERGE:** seta do navegador de mês — prod = glyph texto `‹ ›`; proto = SVG `ChevronLeft/Right` lucide.
- **D2 🟡 DIVERGE:** barra de filtro — proto em 2 linhas (Filtrar-por+período / chips); prod comprime em 1.
- **D4/D5 ⚠️ INCONCLUSIVO:** no ESPELHO local, título (22px/700) e footer (fmt "N lançamentos · Total
  entrada · Total saída") são IGUAIS à prod — mas o espelho pode estar velho. **Refazer contra o
  Cowork vivo** (passo 0) antes de cravar "título grande"/"somatório diferente".
