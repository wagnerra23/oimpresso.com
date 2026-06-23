# [CC]→[CL] · Sidebar flutuante no mobile (Caixa Unificada / shell)

## ▶ COMO USAR (Wagner)
Copie TODO este arquivo e cole UMA vez no Claude Code. Tudo está inline — não precisa abrir link.

## ⚠️ AUDITADO CONTRA O `main` (commit `cb1a546`, lido nesta sessão via GitHub)
Verifiquei os arquivos reais antes de escrever. Conclusão honesta:

| Item do [W] | Situação no `main` | Ação |
|---|---|---|
| 1. Menu flutuante no celular | `cockpit.css`: em ≤1280px a sidebar **continua 260px** (só zera a coluna de Apps). **Não há** drawer off-canvas. | ✅ **ÚNICA mudança desta ponte** |
| 2. Cor do strip de Contexto / aba cheia no mobile | `ContextSidebarV4.tsx` **já** usa `hidden lg:block` (tira) + `lg:hidden` (corpo) → no mobile já mostra o painel cheio; strip usa `bg-card` (coerente). | ❌ **JÁ OK — não tocar** |
| 3. Barra de rolagem na lista | `ConversationListV4.tsx`: `<ul className="flex-1 overflow-auto …">` sem estilo de scrollbar. | ⚠️ opcional (cosmético) — §B |
| 4. Botão roxo "Comentar" desalinhado | é o "comentar numa mensagem pra equipe" — feature **só do protótipo Cowork (curadoria)**, **não existe no repo**. | ❌ **não aplica ao repo** |

**Então o trabalho real = só a Onda A (sidebar mobile).** §B é bônus. O resto não mexe.

---

## ONDA A — Sidebar vira drawer flutuante no mobile (≤768px)
Toca o **layout-mãe** (ADR 0039 / `AppShellV2`) — validar que **desktop não regride** antes de mergear.

**Arquivos:** `resources/js/Layouts/AppShellV2.tsx` + `resources/css/cockpit.css`.
Hoje o shell é `<div className="cockpit" data-sidebar={…}>` com `<aside className="sb">` + `<div className="main">` (+ LinkedApps). Grid em `cockpit.css .cockpit{ grid-template-columns: 260px 1fr 320px }`. **Nenhum breakpoint** transforma a sidebar em overlay.

### A1. `cockpit.css` — adicionar no fim da seção da sidebar (depois de `.cockpit .sb { position: relative; … }`)
```css
/* ── Mobile (≤768px): sidebar vira drawer flutuante off-canvas ── [W] 2026-06-17 */
.cockpit-mobile-toggle { display: none; }
.cockpit-mobile-backdrop { display: none; }

@media (max-width: 768px) {
  /* conteúdo ocupa a largura toda — sidebar sai do fluxo do grid */
  .cockpit,
  .cockpit[data-sidebar="rail"],
  .cockpit[data-linked="off"] { grid-template-columns: 1fr; }

  .cockpit > .sb {
    position: fixed; top: 0; left: 0; bottom: 0;
    width: min(290px, 84vw); z-index: 90;
    transform: translateX(-100%);
    transition: transform .24s cubic-bezier(.2, .8, .2, 1);
    box-shadow: 4px 0 28px oklch(0 0 0 / .3);
  }
  .cockpit[data-mob-open] > .sb { transform: translateX(0); }

  /* alça desktop de colapsar/rail não faz sentido no drawer */
  .cockpit .sb-collapse-handle { display: none !important; }

  /* backdrop escurece o conteúdo */
  .cockpit-mobile-backdrop {
    display: block; position: fixed; inset: 0;
    background: oklch(0 0 0 / .42); z-index: 85;
    animation: cockpitBackdropIn .18s ease;
  }
  @keyframes cockpitBackdropIn { from { opacity: 0 } to { opacity: 1 } }

  /* hambúrguer flutuante (usa tokens do .cockpit: --surface/--border/--text) */
  .cockpit-mobile-toggle {
    display: grid; place-items: center;
    position: fixed; top: 10px; left: 10px;
    width: 40px; height: 40px; border-radius: 10px;
    border: 1px solid var(--border); background: var(--surface); color: var(--text);
    z-index: 95; cursor: pointer; padding: 0;
    box-shadow: 0 2px 10px oklch(0 0 0 / .16);
    transition: left .24s cubic-bezier(.2, .8, .2, 1);
  }
  .cockpit[data-mob-open] .cockpit-mobile-toggle { left: calc(min(290px, 84vw) + 8px); }
}
```
> Se `--surface`/`--text` não existirem no escopo `.cockpit`, trocar pelos tokens reais (`--bg`/`--text-dim` etc — conferir o bloco `.cockpit{}` no topo do arquivo).

### A2. `AppShellV2.tsx` — estado mobile + hambúrguer + backdrop
No componente, junto dos outros `useState`:
```tsx
const [isMobile, setIsMobile] = useState(() =>
  typeof window !== 'undefined' && window.matchMedia('(max-width: 768px)').matches);
const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
useEffect(() => {
  const mq = window.matchMedia('(max-width: 768px)');
  const h = () => setIsMobile(mq.matches);
  mq.addEventListener('change', h);
  return () => mq.removeEventListener('change', h);
}, []);
// fecha o drawer ao navegar (Inertia) e trava o scroll do body enquanto aberto
useEffect(() => { setMobileMenuOpen(false); }, [page.url]);
useEffect(() => {
  if (isMobile && mobileMenuOpen) {
    document.body.style.overflow = 'hidden';
    return () => { document.body.style.overflow = ''; };
  }
}, [isMobile, mobileMenuOpen]);
```
No `<div className="cockpit" …>`, acrescentar o atributo de aberto (mantendo `data-sidebar` em `expanded` no mobile pra a drawer mostrar o menu cheio, não o rail):
```tsx
<div
  className="cockpit"
  data-sidebar={isMobile ? 'expanded' : sidebarMode}
  data-mob-open={isMobile && mobileMenuOpen ? '' : undefined}
  /* …demais data-*/ style={cockpitStyle}
>
```
Logo após a `<aside className="sb">…</aside>` (ainda dentro do `.cockpit`), adicionar o botão e o backdrop:
```tsx
{isMobile && (
  <button
    type="button"
    className="cockpit-mobile-toggle"
    onClick={() => setMobileMenuOpen(v => !v)}
    aria-label={mobileMenuOpen ? 'Fechar menu' : 'Abrir menu'}
    aria-expanded={mobileMenuOpen}
  >
    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor"
         strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
      {mobileMenuOpen ? <path d="M18 6 6 18M6 6l12 12" /> : <path d="M3 12h18M3 6h18M3 18h18" />}
    </svg>
  </button>
)}
{isMobile && mobileMenuOpen && (
  <div className="cockpit-mobile-backdrop" onClick={() => setMobileMenuOpen(false)} />
)}
```
**Atenção ao hambúrguer:** ele fica fixo no topo-esquerdo. Se cobrir o título de alguma página, dar `padding-left` no header no mobile **na página específica** (ex.: header do `CaixaUnificada/Index.tsx`) — não globalmente. Mantenha o atalho ⌘\\ e o rail/expanded **intactos no desktop** (≥769px o bloco novo nem aplica).

**Pronto quando:** ≤768px o menu desliza por cima (não empurra), conteúdo full-width, hambúrguer abre/fecha + backdrop fecha, fecha ao navegar; ≥769px **idêntico ao de hoje** (260px/rail/⌘\\). Screenshots @375 / @768 / @1280.

---

## §B (opcional) — Scrollbar visível na lista
Em `ConversationListV4.tsx`, no `<ul className="flex-1 overflow-auto p-1.5 …">`, acrescentar utilitários Tailwind arbitrários:
```
[scrollbar-width:thin] [scrollbar-color:var(--border)_transparent]
```
Pra Webkit, uma regra global (em `cockpit.css` ou `foundations.css`):
```css
.cockpit ul[role="listbox"]::-webkit-scrollbar { width: 9px; }
.cockpit ul[role="listbox"]::-webkit-scrollbar-thumb {
  background: var(--border); border-radius: 5px; border: 2px solid transparent; background-clip: content-box;
}
```
Não muda os itens da lista.

---

## NÃO TOCAR (já corretos / inexistentes no main)
- **Contexto no mobile** — `ContextSidebarV4.tsx` já resolve via `hidden lg:block` / `lg:hidden`. Não recriar.
- **Comentário inline na mensagem** — feature só do protótipo Cowork; não existe no repo. Ignorar o "botão roxo".

Não cunhar ADR (Tier 0 = [W]). Ao terminar: `[PROCESSADO AAAA-MM-DD]` aqui + retorno em `CODE_NOTES.md`. Cowork é read-only no git.
