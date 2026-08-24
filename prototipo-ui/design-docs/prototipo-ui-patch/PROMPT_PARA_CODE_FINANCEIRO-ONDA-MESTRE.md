<!-- HANDOFF · Cowork [CC] → Claude Code [CL] · cole 1× -->
# Financeiro · ONDA-MESTRE → produção (varredura do print real 16/06/2026)

> **Gatilho:** [W] enviou screenshot da **produção deployada** (light mode, WR2 Sistemas, 100 lançamentos). Varri ponto a ponto. **Achado-chave:** o hero "Saldo previsto" **ainda é a caixa preta com linha verde de osciloscópio** — ou seja, **nem a modernização original nem as Ondas 1/2 foram deployadas**. Esta é a ponte-mestre que **ordena o deploy de tudo** + lista os deltas que faltam.
> **Régua de gate (todas as mudanças):** cor só por token (`--accent/--pos/--warn/--neg/--text-*`), `color-mix(in oklab,…)` ok, **sem hex cru**, sem `rounded-xl+`.
> **Onde vive:** drawer + tabela + toolbar + hero são **inline no `resources/js/Pages/Financeiro/Unificado/Index.tsx`**; CSS no bundle `.fin-*`. `⚠confirmar` = não reli o `@main` linha-a-linha nesta passada.
> **Refs exatas (espelho de intenção):** URLs no fim — `financeiro.css` + `financeiro-page.jsx` do protótipo.

---

## PRIORIDADE 0 — o que o print prova que está VELHO em produção

### P0.1 · Hero "Saldo previsto" = caixa PRETA + sparkline verde osciloscópio  ⛔ o pedido original do [W] ("parece 1990")
**Estado no print:** `background` preto (`var(--text)`), número branco, linha verde estilo terminal. É exatamente o que a modernização matou no protótipo.
**Fix (já pronto no protótipo):** hero vira **card claro elevado** com leve luz roxa da identidade + sparkline limpa:
```css
.fin-stat-hero{
  background:
    radial-gradient(540px 200px at 14% -45%, color-mix(in oklab, var(--accent) 16%, transparent), transparent 70%),
    linear-gradient(160deg, color-mix(in oklab, var(--accent) 8%, var(--surface)) 0%, var(--surface) 78%) !important;
  border: 1px solid color-mix(in oklab, var(--accent) 18%, var(--border)) !important;
  box-shadow: var(--sh-2) !important;
}
.fin-stat-hero small{ color: var(--text-2) !important; }
.fin-stat-hero b{ color: var(--text) !important; }
```
+ saldo **negativo** → número em `var(--neg)` + chip "projeção negativa" (no print está positivo, mas a regra precisa ir junto).
+ sparkline com **tom por sinal** (`pos`/`neg`) — ⚠confirmar: na Onda 1 li que o `@main` **já** tem `tone` na sparkline; então pode ser só o número/fundo que falta. **Confira o `@main` do hero antes** — talvez o bloco preto seja build deployado atrás do `@main`.
Ref: `fin-boletos.css` → `.fin-stat-hero*`.

### P0.2 · KPIs (Recebido / A receber / Pago / A pagar)
**Estado no print:** já são **cards brancos soltos** (bom — essa parte da modernização chegou). 
**Delta que falta (Onda 2 · Caso 08):** **ponto verde** nas que entram (Recebido, A receber) e **vermelho** nas que saem (Pago, A pagar), pra agrupar entrada×saída no olho:
```css
.fin-stats .fin-stat-in small::before,.fin-stats .fin-stat-out small::before{ content:""; display:inline-block; width:6px; height:6px; border-radius:50%; margin-right:6px; vertical-align:middle; }
.fin-stats .fin-stat-in small::before{ background:var(--pos); }
.fin-stats .fin-stat-out small::before{ background:var(--neg); }
```
(adicionar classes `fin-stat-in`/`fin-stat-out` aos cards correspondentes).

---

## PRIORIDADE 1 — Onda 2 (Tribunal dos Mestres) — drawer/lista
> Detalhe completo em `PROMPT_PARA_CODE_FINANCEIRO-ONDA2-TRIBUNAL.md`. Resumo executável:
1. **Veredito inferido no topo do drawer** (cadeira Victor) — 1 linha que conclui pelo usuário ("Nada pendente / Vencida há Nd — cobrar"), tom pos/warn/neg/muted pelo estado.
2. **Comparação "vs média" no valor** (Tufte) — só com ≥2 pares reais na categoria; tom neutro; **sem número fake**.
3. **Selo → dado** (Tufte/Rams) — remover badges que só re-anunciam sucesso ("100% match", "NF vinculada", "encerrada"); manter os informativos ("aguardando", "sem NF", "em atraso").
4. **Item terminal: stepper FSM → resumo 1 linha** ("✓ Emitido → Liquidado · 4 etapas · no prazo").
5. **Acento de AÇÃO na linha da lista** — `box-shadow: inset 3px 0 0 var(--neg)` (vencido) / `var(--warn)` (≤3d) na 1ª `<td>`. **No print há 81 atrasados** — esse acento ajuda MUITO a achar ação.

---

## PRIORIDADE 2 — Review visual do [W] (polish)
> Detalhe completo em `PROMPT_PARA_CODE_FINANCEIRO-REVIEW-W-CONSOLIDADO.md`. Os que o print confirma como pendentes/relevantes:
- **BUG dot do StatusBadge:** classe inválida `bg-[var(--…-soft)]0` → dot não pinta. Trocar por cor sólida do estado + peso `font-semibold`. ⚠confirmar no `@main`.
- **Seleção da linha** cinza → roxo (`color-mix(var(--accent) 14%)` + `inset 3px 0 0 var(--accent)`).
- **DirIcon** (as setas ↗↘ da coluna 2) → círculo, traço 1.75 (no print são setas "cruas").
- **Linhas decorativas coloridas** nos headers das lentes do drawer (verde/amarela/roxa por domínio).
- **Histórico mais leve** (autor 700→500, título sem uppercase).
- **Ficha sem caixa** (uma linha, não dupla).
- **Botões de ícone do header do drawer** com chrome de repouso.
- **Contador dos filtros na cor do estado** — ⚠ no print **já aparece colorido** (A receber 69 verde, A pagar 12 vermelho, Pagas roxo) → pode já estar no `@main`; confirmar só os chips de range (<30d etc., hoje neutros — manter neutro está ok).
- **Botão "Limpar" filtros** estilizado como botão + chips na mesma altura.
- **Lente Caixa/A receber/A pagar** (segmented do hero) altura 30px, selecionado não incha.

---

## NÃO se aplica / confirmar no print
- **Sidebar "remover 6 itens"** (Copiloto/MemCofre/…): a sidebar de produção é **agrupada** (CADASTRO/COMERCIAL/FINANÇAS/FISCAL/PRODUÇÃO) e **não mostra** esses itens no print. Provavelmente já não existem ou estão noutro lugar — **confirmar com [W]** antes de mexer; não aplicar às cegas.
- **Footer "? Resolver 4":** é o helper genérico (4 fluxos) — correto. **Não** transformar em alarme por-título (isso era o bug do Caso 01 da Onda 1, que no `@main` já parece resolvido).

## Vetos da Larissa (Onda 2) — INEGOCIÁVEIS
- **NÃO** remover os ícones-quadradinho de seção do drawer.
- **NÃO** "simplificar" a tipografia do valor (inteiro grande + centavos).

---

## Ordem de deploy sugerida
0. **Confirmar por que o `@main` não chegou em produção** (build atrasado?) — talvez metade disto resolva só com **deploy do `@main`**. Tire screenshot novo depois do deploy antes de aplicar P0–P2.
1. **P0.1 hero** (o pedido original — maior impacto visual).
2. **P0.2 dots** + **P2 contador/Limpar/lente** (toolbar/hero).
3. **P1 Onda 2** (drawer + acento na lista — crítico com 81 atrasados).
4. **P2 restante** (drawer polish + tabela).

_CC não commita (read-only no git). Proposta; o Code valida contra o `main`, confirma os `⚠`, e aplica. As 3 pontes-filhas (Adversário-Wave1, Onda2-Tribunal, Review-W-Consolidado) seguem válidas como detalhe — esta ordena tudo._
