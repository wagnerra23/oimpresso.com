---
id: audits-2026-05-pre-sales-02-wcag-manual-5-telas
---

# WCAG 2.1 AA — auditoria manual 5 telas (sem plugin Anthropic Design)

> Auditoria estática (Tailwind class → estimativa contraste/touch/foco).
> Não substitui testes com axe-core/Lighthouse + reader screen real.
> Worktree: `amazing-williamson-0c8854` · Data: 2026-05-09

Legenda severidade: **A** = falha 1.x/2.x/3.x/4.x WCAG 2.1 nível A · **AA** = falha nível AA · **AAA** = recomendação AAA.

---

## Tela 1 — Login (`resources/js/Pages/Site/Login.tsx`)

| # | Issue | Linha | Severidade | Recomendação |
|---|---|---|---|---|
| L-1 | Floating-label esconde `<label>` até foco/preenchimento (placeholder=" "). Screen reader **anuncia label** mas usuário sighted enxerga só placeholder vazio até clicar | 213-222 | AA (3.3.2 Labels) | Sempre exibir label visível acima OU usar placeholder descritivo + aria-label |
| L-2 | Checkbox "Lembrar de mim" `h-3.5 w-3.5` = 14px | 142-144 | AA (2.5.5 Target Size) | Mínimo 24×24 (AAA quer 44×44). Trocar pra `h-5 w-5` ou wrapper `<label>` clicável `min-h-[44px]` |
| L-3 | Botão "Mostrar/Ocultar senha" texto puro `text-xs` (12px) sem aria-pressed | 127-133 | A (4.1.2 Name/Role/Value) + AA target | Adicionar `aria-pressed={showPassword}` + aumentar target |
| L-4 | Erro `text-destructive` (vermelho default Tailwind ~ #ef4444) sobre `bg-card` (white). Contraste **estimado 3.7:1** com texto sm — falha AA pra texto pequeno | 226-227 | AA (1.4.3) | Trocar pra `text-red-700` (≥4.5:1) ou aumentar texto |
| L-5 | `text-muted-foreground` em `text-[11px]` ("Login social ainda não configurado", "Ao entrar você concorda…") → contraste fraco + texto < 12px | 95, 171 | AA (1.4.4 Resize Text) | Min `text-xs` (12px) com contraste ≥4.5:1 |
| L-6 | Heading hierarchy ok (h1 único: "Bem-vindo de volta") | 50 | ✅ | — |
| L-7 | Foco visível `focus:ring-2 focus:ring-primary/40` (40% opacidade) — contraste fraco | 214 | AA (2.4.7 Focus Visible) | Subir pra `focus:ring-primary/60` ou adicionar `focus:ring-offset-2` |
| L-8 | Link "Esqueceu a senha?" `text-xs` = 12px | 147-153 | AA target tap (2.5.5) | Aumentar pra `text-sm min-h-[24px]` |
| L-9 | Region landmark: faltam `<main>`, `<header>`, `<nav>` — só `<section>` | 41 | A (1.3.1 Info/Relationships) | Wrap em `<main>` |

**Resumo Login:** 2 P0 (L-1 floating label, L-2 checkbox), 5 P1.

---

## Tela 2 — Dashboard home (`resources/views/home/index.blade.php`)

| # | Issue | Linha | Severidade | Recomendação |
|---|---|---|---|---|
| D-1 | Texto branco `tw-text-white` sobre gradiente `tw-from-primary-800 tw-to-primary-900`. Se primary=blue/sky → contraste ok (~9:1). Se primary=yellow/orange → falha (yellow-800 ≈ #854d0e — ok mas yellow-700 fail) | 6 | AA (1.4.3) | Forçar `text-white` apenas pra cores escuras; restringir cores válidas a Blue/Purple/Green/Red/Sky (já feito linha 56-70 BusinessController) |
| D-2 | KPI cards: `tw-text-gray-500 tw-text-sm` em background branco — gray-500 = #6b7280 → contraste 4.83:1 sobre white ✅ | 92 | ✅ | — |
| D-3 | KPI value `total_sell` recebe valor via JS sem aria-live → screen reader não anuncia atualização | 96 | A (4.1.3 Status Messages) | `aria-live="polite"` no `<p class="total_sell">` |
| D-4 | Botão filter date com SVGs decorativos OK (aria-hidden) mas botão sem aria-label se label texto for traduzido vazio | 35-63 | A (4.1.2) | Adicionar `aria-label="Filtrar por data"` redundante |
| D-5 | `Form::select('dashboard_location', $all_locations, …)` é `<select>` legacy sem label visível associado | 25-29 | A (3.3.2) | Adicionar `<label for="dashboard_location">Local</label>` |
| D-6 | Heading hierarchy: h1 "Bem-vindo {nome}" + outros h-tags ausentes (KPIs são `<p>` não `<h2>`) | 15-18 | A (1.3.1) | Cards KPI deveriam ter `<h2>` ou role region+aria-labelledby |
| D-7 | Hover `hover:tw-translate-y-0.5` quebra para usuários `prefers-reduced-motion` | 74 | AA (2.3.3 Animation) | Wrap em `@media (prefers-reduced-motion: no-preference)` ou usar `motion-safe:` |
| D-8 | Cards clickáveis? Não — só visual hover. ✅ | — | ✅ | — |

**Resumo Dashboard:** 1 P0 (D-1 condicional), 4 P1.

---

## Tela 3 — Sells/create (`resources/js/Pages/Sells/Create.tsx`)

| # | Issue | Linha | Severidade | Recomendação |
|---|---|---|---|---|
| S-1 | Botão "Remover produto" `<button>` `p-1` + `Trash2 h-4 w-4` → target ~24×24px | 619-626 | AA (2.5.5 Target Size) | Mínimo 44×44 — `p-2` ou `min-h-11 min-w-11` |
| S-2 | Pills navegação seções (`'Dados'`, `'Produtos'`...) `px-3.5 py-1.5 text-xs` ≈ 28px altura | 350-376 | AA target | Subir pra `py-2 text-sm` (`min-h-11`) |
| S-3 | Pills usam `aria-current={isActive ? 'true' : undefined}` ✅ mas não anunciam contagem como label | 360 | AA (1.3.1) | Adicionar `aria-label` que inclui count: `aria-label="Produtos, 3 itens"` |
| S-4 | KPI card "Status pgto" usa cor amber/blue/emerald + texto cor matching → contraste **borderline** com text-amber-700/blue-700 sobre bg-{color}-50. Amber-700 (#b45309) sobre amber-50 (#fffbeb) ≈ 6.8:1 ✅ | 425-444 | ✅ | — |
| S-5 | Inputs `h-8` (32px) — abaixo do mínimo recomendado AA 44px | 568-613 | AA (2.5.5) | `h-9` (36px) é mínimo Tailwind defensivo; Cockpit pattern oimpresso usa `h-9`/`h-10` |
| S-6 | Disabled prices `disabled={!props.permissions.editPrice}` sem feedback visual contrastante | 595 | AA (1.4.3) | Adicionar `disabled:bg-muted disabled:opacity-70` (Tailwind default opacity=0.5 falha contraste) |
| S-7 | Tabela produtos sem `<caption>` ou aria-label | 543-643 | A (1.3.1) | `<table aria-label="Produtos da venda">` |
| S-8 | "Status" Select trigger não vincula explicitamente Label→trigger via aria-labelledby (Radix faz mas verificar) | 480-495 | A (4.1.2) | Auditoria runtime axe-core |
| S-9 | KPI cards "Itens"/"Total venda" usam `<div>` com cor + valor — sem semântica role="region" + aria-labelledby | 384-444 | A (1.3.1) | Adicionar role/aria |
| S-10 | Heading hierarchy: h1 "Adicionar venda" + section CardTitle "Dados da venda" não vira h2 (CardTitle padrão Radix shadcn é `<h3>`) | 318, 453 | A (1.3.1) | Verificar o Card component shared |
| S-11 | Filtro chips Box/Elevador (Repair Producao, mas pattern repete aqui) sem keyboard navigation custom (Tab anda 1 a 1) | — | ✅ | OK (button nativo) |

**Resumo Sells:** 3 P0 (S-1, S-2, S-5 touch), 5 P1.

---

## Tela 4 — Repair ProducaoOficina (`resources/js/Pages/Repair/ProducaoOficina/Index.tsx`)

| # | Issue | Linha | Severidade | Recomendação |
|---|---|---|---|---|
| R-1 | **Drag-and-drop nativo HTML5 SEM alternativa keyboard.** Usuário só-teclado / screen reader não consegue mover cards | 86-114, 197-217 | A (2.1.1 Keyboard) **CRÍTICO** | Adicionar botão "Mover para…" com Select; ou usar `dnd-kit` que tem keyboard sensor |
| R-2 | Cards drag não anunciam status drag/drop pra screen reader | 86-115 | AA (4.1.3 Status Messages) | `aria-live` region + `aria-grabbed` (deprecated mas semantic) ou Live Region custom |
| R-3 | Filter chips `bg-slate-900 text-white` (active) ≈ 18:1 ✅ inactive `bg-slate-100 text-slate-700` (#f1f5f9 / #334155) ≈ 11:1 ✅ | 254-258 | ✅ | — |
| R-4 | Filter chips `px-2.5 py-1 text-sm` ≈ altura 32px | 254 | AA target | `py-2 min-h-11` |
| R-5 | Tone dot `w-2 h-2` (8×8px) só carrega cor, sem texto/título | 306 | A (1.4.1 Use of Color) | Adicionar `aria-label` com nome da cor/status OU vincular text label adjacente |
| R-6 | Mock badge `text-[10px]` < 12px | 173 | AA (1.4.4 Resize) + 1.4.12 Text Spacing | `text-xs` mínimo |
| R-7 | "data_source mock" badge usa só `title="…"` (tooltip browser) — não acessível keyboard | 172-176 | A (1.3.1) | Trocar pra `<button>` com aria-describedby ou expandir info inline |
| R-8 | Drag visual: `cursor: grab` não definido — só CSS hover muda | — | AA (3.2.4 Consistent ID) | Adicionar `cursor-grab active:cursor-grabbing` |
| R-9 | Heading: h1 ausente (ProducaoOficina é página interna AppShell) — só `<h3>{column.label}` | 307 | A (1.3.1) | h1 "Produção da oficina" no PageHeader |
| R-10 | Filtros Box/Elevador `<button>` sem `aria-pressed` ou `role="radio"` (radio group pattern) | 250-263 | AA (4.1.2) | Wrap em `role="radiogroup"` + `aria-pressed` |

**Resumo Repair:** 1 BLOQUEADOR P0 (R-1 keyboard impossível), 5 P1.

---

## Tela 5 — Visão Unificada Financeiro (`resources/js/Pages/Financeiro/Unificado/Index.tsx`)

| # | Issue | Linha | Severidade | Recomendação |
|---|---|---|---|---|
| F-1 | Densidade buttons usam **glyphs Unicode** ◰ ▦ ▤ sem aria-label/visible text | 306-312 | A (1.1.1 Non-text Content) **CRÍTICO** | `aria-label="Densidade compacta"` etc — usuário cego não sabe o que clica |
| F-2 | Densidade buttons `px-2 py-0.5 text-[11.5px]` ≈ 18×16px | 306-312 | AA (2.5.5 Target Size) | Tamanho ridículo — subir pra `px-3 py-2 min-h-11` |
| F-3 | Linha tabela `compact: h-8` = 32px — abaixo de 44px AA. Usuário com motor impairment não acerta clique | 92-96 | AA (2.5.5) | Default `comfortable` h-11 ✅ mas compact deve avisar "modo denso" |
| F-4 | Texto `text-[12.5px]`/`text-[10px]` < 12px | 93-95, 322 | AA (1.4.4) | Min `text-xs` (12px) ou refatorar densidade pra usar zoom CSS |
| F-5 | KPI Cards via `<KpiCard>` shared — auditar separadamente, mas cor `recebido=emerald`, `pago=stone`, `atrasado=rose` cobre só sighted | — | A (1.4.1) | Adicionar texto status redundante + ícone com aria-label |
| F-6 | Cmd+K palette listener `e.metaKey \|\| e.ctrlKey` não tem indicação visual ("Press ?") | 228-237 | AA (3.3.5 Help) | Adicionar `<kbd>⌘K</kbd>` visível |
| F-7 | Atalho `/` foca search — bom UX, mas search input não tem `aria-keyshortcuts="/"` | 231-233 | A (4.1.2) | Adicionar atributo |
| F-8 | Tabela sem `<caption>` ou aria-label | 320 | A (1.3.1) | `<table aria-label="Lançamentos financeiros">` |
| F-9 | Status pill `status-tone` usa cor + texto ✅ — emerald/rose/amber/stone ok | 98-106 | ✅ | — |
| F-10 | Linhas `<tr>` clicáveis (`onSelect`) mas sem `tabindex=0` ou `role="button"` — não acessíveis keyboard | — (LinhaTabela) | A (2.1.1) | Adicionar tabindex + onKeyDown Enter/Space |
| F-11 | Drawer/Sheet abre sem retornar foco depois (Radix faz default mas verificar) | 376 | AA (2.4.3 Focus Order) | Auditar runtime |
| F-12 | Heading: PageHeader provavelmente h1 ✅; SheetTitle = h2 ✅ | 254, 381 | ✅ | — |
| F-13 | Tab buttons (TABS) altura `px-2.5 py-1 text-[12.5px]` ≈ 26px | 277-281 | AA target | `py-2 text-sm` |

**Resumo Financeiro:** 2 P0 (F-1 glyphs, F-10 linha não-keyboard), 6 P1.

---

## Top 10 P0/P1 consolidados — WCAG

| # | ID | Tela | Severidade WCAG | Esforço fix |
|---|---|---|---|---|
| 1 | **R-1** drag-drop sem keyboard | Repair | **A 2.1.1** (bloqueador) | 8h (refator dnd-kit + keyboard sensor) |
| 2 | **F-1** densidade buttons sem label | Financeiro | **A 1.1.1** (bloqueador) | 5 min (aria-label) |
| 3 | **F-10** linhas clicáveis sem tabindex | Financeiro | **A 2.1.1** | 30 min |
| 4 | **L-1** floating label esconde label | Login | AA 3.3.2 | 1h refator FloatingField |
| 5 | **L-2** checkbox 14×14 | Login | AA 2.5.5 | 5 min |
| 6 | **S-1, S-2, S-5** touch targets <44px | Sells | AA 2.5.5 | 1h batch |
| 7 | **R-2** drag sem live region anúncio | Repair | AA 4.1.3 | 1h |
| 8 | **F-2/F-3/F-4** density compact <44px + texto<12px | Financeiro | AA 2.5.5+1.4.4 | 30 min |
| 9 | **D-3** KPI sem aria-live | Dashboard | A 4.1.3 | 5 min |
| 10 | **L-9** falta `<main>` landmark | Login | A 1.3.1 | 5 min |

**Recomendação:** Antes de demo a prospect compliance-aware (govtech, banking, healthcare): consertar pelo menos R-1 (bloqueador), F-1, F-10, L-2 (~10h totais). Outros são P1 polishable em sprint dedicado.

**Nota crítica:** plugin `design:accessibility-review` está esgotado nesta sessão. Auditoria estática Tailwind→WCAG é heurística — recomenda-se rodar **axe DevTools + Lighthouse + screen reader (NVDA/VoiceOver)** em ambiente real antes de afirmar conformidade AA.
