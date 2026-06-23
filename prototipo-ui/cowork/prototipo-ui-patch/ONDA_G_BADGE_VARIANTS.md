# ONDA G — Badge variants semânticas (destrava o lote de 410)

> **Origem:** Cowork [CC] · 2026-05-29 · revelado pelo guard PR-B (`ds/no-adhoc-status-text` = 410, 64% do drift).
> **Gatilho:** PR-B mergeado ✅ — pode soltar. Sem URL: o Code salva o próprio doc no repo.
> **PR alvo:** `feat/onda-g-badge-variants`

## O problema (o lint revelou)

410 violações `ds/no-adhoc-status-text` = telas hardcodando `STATUS_STYLE` (`bg-rose-50 text-rose-700 border-rose-200 …`) porque o **`Badge` não tem variante de status soft**. O `destructive` é vermelho **sólido** (pra ação destrutiva — botão Excluir), não o **pill suave** de estado (autorizada/pendente/atrasada). Sem essa peça, os 410 **não têm destino limpo** — por isso o lote de badge **espera a Onda G** (escape-hatch-antes-do-guard).

`badge.tsx` hoje (CVA): `default · secondary · destructive · outline · ghost · link`. Falta o tom de status.

## As 5 variantes novas (tom soft, espelham o STATUS_STYLE que já existe → migração 1:1)

| Variante | Tailwind (light + dark) | Semântica |
|---|---|---|
| **`success`** | `bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300` | autorizada · paga · ativo |
| **`warning`** | `bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300` | pendente · processando |
| **`danger`** | `bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300` | atrasada · rejeitada · denegada |
| **`info`** | `bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/40 dark:text-sky-300` | em aberto · informativo |
| **`neutral`** | `bg-muted text-muted-foreground border-border` | idle · cancelada · n/a |

> `destructive` (sólido) **permanece** — é pra **ação** destrutiva, não estado. `danger` (soft) é o **estado**. Distinção importante: não fundir os dois.

## Mapa de migração (STATUS_STYLE → variant) — pro lote de 410

| Padrão hoje | → variant |
|---|---|
| `late` · `rejeitada` · `denegada` · `com atraso` | `danger` |
| `active` · `autorizada` · `paid` · `Pago` | `success` |
| `pendente` · `processando` | `warning` |
| `sky` · em aberto | `info` |
| `idle` · `cancelada` · `inutilizada` | `neutral` |

Arquivos-âncora (do grep): `Cliente/Index.tsx` (STATUS_STYLE l.258 + KPI danger), `Sells/_components/FiscalSection.tsx` (l.36–41 + error/success divs → `<Alert>`).

## Definição de pronto (tripé)
1. `badge.tsx` CVA + 5 variants · 2. story no `_Showcase` (badges × tema) · 3. (depois) regra `ds/no-handrolled-status-badge` que pega `STATUS_STYLE`-like → fecha o ciclo.

---

## Cola no Claude Code (sem editar) — [URL regenerada no disparo]

````bash
git checkout main && git pull origin main
git checkout -b feat/onda-g-badge-variants

# (salve o conteúdo deste doc como prototipo-ui/ONDA_G_BADGE_VARIANTS.md e commite junto)

# Parte 1: badge.tsx — +5 variants (success/warning/danger/info/neutral) no CVA, tom soft
#          (light+dark) conforme tabela acima. NÃO mexer em destructive (ação).
# Parte 2: story em Pages/_Showcase/ — as 6+5 variants × tema.
#   (NÃO migrar telas aqui — isso é o PR-C "lote badge", separado, depois.)

npm run lint:baseline:check   # delta 0 (Components/ui é fora do escopo ds/*)

git add resources/js/Components/ui/badge.tsx 'resources/js/Pages/_Showcase' \
        'prototipo-ui/ONDA_G_BADGE_VARIANTS.md'
git commit -m "feat(ui): Onda G — Badge variants semanticas (success/warning/danger/info/neutral)

Guard PR-B revelou 410 STATUS_STYLE hardcoded (ds/no-adhoc-status-text) sem
destino: Badge so tinha destructive solido (acao), faltava o pill soft de
estado. +5 variants tom soft (light+dark) espelhando o STATUS_STYLE existente
-> migracao 1:1. destructive permanece (acao != estado).

Destrava o PR-C lote-badge (migra os 410). tokens.css inalterado.

Refs: ONDA_G_BADGE_VARIANTS.md, guard PR-B, REGISTRY_DS_COMPONENTES.md"

git push -u origin feat/onda-g-badge-variants
gh pr create --title "feat(ui): Onda G — Badge variants semanticas (status pills)" \
  --body "5 variants soft (success/warning/danger/info/neutral) no badge.tsx — destrava a migracao dos 410 STATUS_STYLE hardcoded que o guard PR-B revelou. destructive (acao) permanece distinto de danger (estado). Ver ONDA_G_BADGE_VARIANTS.md." \
  --base main --head feat/onda-g-badge-variants
# NÃO MERGEIE — screenshot das variants pro [W2] + Claude Design.
````

Depois da Onda G no main → o **PR-C lote-badge** migra os 410 `STATUS_STYLE` → `<Badge variant>` com destino limpo.
