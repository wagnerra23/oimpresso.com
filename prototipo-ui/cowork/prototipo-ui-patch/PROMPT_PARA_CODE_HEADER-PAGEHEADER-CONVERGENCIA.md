# [CC]→[CL] · Header + PageHeader — convergir telas ao componente canon (EM ONDAS)

> **Decisão [W] 2026-06-16:** padrão único = o do git (`<PageHeader>` v3.8 ADR 0189 +
> `<PageHeaderPrimary>` ADR 0190). O protótipo Cowork já foi alinhado por [CC]. Falta
> só **adoção no repo**: telas que ainda renderizam header inline (`os-page-h` /
> `fin-page-h`) trocam pelo componente que **JÁ EXISTE** em `@/Components/PageHeader`.
>
> **AUTO-CONTIDO — não precisa de nenhum arquivo do Cowork nem URL.** Tudo que o Code
> precisa (o componente canon e seus props) já está no `main`. Não buscar link externo.
>
> **§10.4:** valide contra `main`. Se algo aqui contradiz o repo, o repo vence.

---

## Como o componente canon funciona (já está em `resources/js/Components/PageHeader/`)
```tsx
import { PageHeader, PageHeaderPrimary } from '@/Components/PageHeader';

<PageHeader
  title="Financeiro"
  suffix=" · Visão unificada"           // cinza, font-semibold muted (opcional)
  subtitle={<>Junho 2026 · ROTA LIVRE · caixa unificado</>}  // text-xs tabular muted
  subnav={<FinanceiroSubNav active="unificado" />}           // Zona C (opcional)
  actions={<>
    <button className="…">⋮</button>
    <PageHeaderPrimary label="Novo lançamento" onClick={…} />   // roxo universal
  </>}
/>
```
Regras do canon (não reinventar): header flat `border-b` warm, sem bg/rounded, sem caixa
de ícone, h1 22/700/tight, **1 só `<PageHeaderPrimary>` por header**. Hue-per-grupo fica
só no sidebar — o primário das telas é SEMPRE roxo `oklch(0.55 0.15 295)`.

---

## ONDA 0 — inventário (1 comando, sem PR)
```
grep -rn "os-page-h\|fin-page-h\|cli-pageheader" resources/js/Pages resources/js/Modules 2>/dev/null
npm run pageheader:guard   # se existir no package.json — é a régua
```
Liste as telas que ainda hand-rollam header. **Confirmados por [CC] lendo @main nesta
sessão** (ponto de partida — a grep acha o resto):
- `resources/js/Pages/Financeiro/Unificado/Index.tsx` (~L1113: `os-page-h fin-page-h` inline)
- `resources/js/Pages/Financeiro/Dashboard/Index.tsx` (~L156: `os-page-h fin-page-h`)
- `resources/js/Pages/Financeiro/Dre/Index.tsx` (comentário "copy-paste do bloco os-page-h")
- ✅ **JÁ usa `<PageHeader>` — NÃO tocar:** `Financeiro/ContasPagar/Index.tsx` (Wave 4).

---

## UMA ONDA = UMA TELA = UM PR. Não fazer tudo de uma vez. Ordem:

### Onda 1 — `Financeiro/Unificado/Index.tsx`
Trocar o `<header className="os-page-h fin-page-h">…</header>` inline por `<PageHeader>`:
- `title="Financeiro"` · `suffix=" · Visão unificada"` (ou o subtítulo de lente atual)
- `subtitle` = a linha de período/caixa que já existe
- `subnav` = o `FinanceiroSubNav` que já está na tela (vai pra Zona C)
- `actions` = as 3 lentes + "Novo lançamento" como `<PageHeaderPrimary>` + o menu `⋮`
Remover o CSS/markup inline `fin-page-h`. **Critério de pronto:** `pageheader:guard` verde
nessa tela · screenshot igual ou melhor · sem `os-page-h`/`fin-page-h` no arquivo.

### Onda 2 — `Financeiro/Dashboard/Index.tsx` (mesmo recipe)
### Onda 3 — `Financeiro/Dre/Index.tsx` (mesmo recipe; tirar o copy-paste inline)
### Onda 4+ — cada tela restante que a Onda 0 achar (1 PR por tela/família)

Em TODA onda, o botão de criação (verde/azul por-tela, `.os-btn.primary`,
`FinanceiroPrimaryButton`/`JanaPrimaryButton` — já DEPRECATED por ADR 0190) vira
`<PageHeaderPrimary>` roxo.

---

## Esperam decisão de [W] (NÃO codar ainda)
- **Header global:** [W] já escolheu o do git (breadcrumb + `topnav-chip`, `hideTopbar`). Nada a fazer no repo além de manter.
- **Peso do H1 (600 vs 700) / tempero no canon:** vira amendment v3.9 da ADR 0189 — Tier 0, só com OK de [W]. Até lá o v3.8 fica como está.

**Ao terminar cada onda:** marque `[PROCESSADO AAAA-MM-DD]` aqui e escreva o retorno em `CODE_NOTES.md`.
Nada está commitado do lado do Cowork — read-only. O Code resolve com este pedido.
