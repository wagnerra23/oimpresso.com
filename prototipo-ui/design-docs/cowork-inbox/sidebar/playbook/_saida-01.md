---
sessao: "_saida-01"
thread: "01 · Seção CORPO — nav + a11y A1–A12 + aposentar Tabs/Chat/ConvRow"
dono: "[CC]"
data: 2026-09-10
prefixo_tocado: prototipo-ui/cowork/sidebar.jsx · prototipo-ui/cowork/styles.css · (host: bump de ?v=)
base_lida: wagnerra23/oimpresso.com@main — árvore af09f7c3a0fd
---
# _saida-01

## Feito
1. **Clicáveis viraram controles reais.** `ItemRow` e o item de topo do `SidebarMenu` eram `<div role="link" tabIndex={0}>` com `onKeyDown` à mão; viraram `<button type="button">`. Idem `UserMenu` (10 `.um-item`), os itens do dropdown de empresa (`.sb-dd-i`, `.sb-dd-foot`) nas duas variantes (expandida e rail).
2. **Nomes acessíveis e estado.** `CompanyPicker`/`CompanyPickerRail` ganharam `aria-haspopup="menu"` + `aria-expanded` + `aria-label` ("Empresa: X. Trocar de empresa"); o `<div className="sb-dd">` virou `role="menu"` com `role="menuitemradio"`/`aria-checked` por empresa. Rodapés de usuário: `aria-haspopup`/`aria-expanded`, avatar `aria-hidden`. Cascata Superadmin com `aria-expanded`.
3. **Decorativos silenciados:** `.sb-kbd` (`aria-hidden="true"` — o atalho real vive no listener global), setas `›`, `⌘K`, avatares, o `⋯` do ghost-more.
4. **`WipMark`** era `<span aria-label>` mudo (ignorado pela AT) → `role="img"`.
5. **"⋯ mais N"** ganhou nome literal: `aria-label="Mostrar mais N telas"` / "Mostrar menos telas".
6. **Código morto removido** (RESÍDUO-2 = remover): `SidebarTabs` · `SidebarChat` · `ConvRow` do `sidebar.jsx`; 150 linhas de CSS órfão do `styles.css` (`.sb-tabs`/`.sb-tab`, `.sb-chat`, `.sb-actions`/`.sb-action*`, `.sb-section-h`, `.sb-pin-empty`, `.sb-bullet*`, `.sb-conv*`, `.sb-routine*`). Zero consumidores em todo o projeto — medido, não presumido.
7. **Foco visível** (`:focus-visible`, anel de accent `outline-offset:-2px`) em `.sb-item`, `.um-item`, `.sb-dd-i`, `.sb-dd-foot`, `.sb-ghost-more`, `.sb-group-h`.
8. Assinatura do `Sidebar` perdeu `tab`/`onTab`/`activeConvId`/`onSelectConv`.

## Medição — o layout NÃO mudou
Tema dark, após `__oiLazyDone`, `getComputedStyle` (nunca a classe declarada). Antes × depois da troca `div`→`button`:

| medida | antes | depois |
|---|---|---|
| `.sb-item` (1ª linha) | DIV · h 34px · pl 10px · fs 13px · IBM Plex Sans · w 233px | **BUTTON** · h 34px · pl 10px · fs 13px · IBM Plex Sans · w 233px |
| item ativo | pl 18px · cor `oklch(0.97 0.004 295)` · bg `oklch(0.34 0.05 295)` | idem, idem, idem |
| `aside.sb` | 260px | 260px |
| `.sb-body` overflow-y | auto | auto |

Contadores depois: `role="link"` **42 → 0** · `div` com `onClick` na sidebar **42 → 0** · `.sb-item` que são `<button>` **0/42 → 42/42** · `.sb-kbd` todos `aria-hidden` **true** · `.sb-wip` `role="img"` · `.sb-tabs/.sb-conv/.sb-chat` **0** · `<nav>` na sidebar **1**.

**Sanidade antes do veredito:** a primeira rodada da sonda devolveu "42 DIVs, nada mudou". Não era o código — era **`?v=` velho** no host servindo cache, e a aba do [W] ainda no load anterior. O caso de sanidade (`.sb-item` existe e tem altura) passava nas duas, então o que separou foi comparar contra um valor esperado, não contra "parece ok". Bump `sidebar.jsx?v=sb21` · `styles.css?v=ph17s` · `app.jsx?v=eb26`.

## Não feito, e por quê
- **A3 · ícone anônimo:** `window.I` (`icons.jsx`) monta `<svg>` sem `aria-hidden` e **não repassa props** — não dá pra corrigir por chamada. O conserto certo é uma linha em `icons.jsx`, **fora deste prefixo** (Lei 1), e vale pro app inteiro. Precedente do mesmo defeito e do mesmo conserto: `crm-blade.jsx:23-31` (A3, 2026-09-04). **Fica como pedido de 1 arquivo.** Impacto hoje: baixo — toda linha da sidebar tem rótulo de texto, então o glyph é ruído, não perda de nome.
- **Contraste do texto dim:** não medido em número nesta rodada.
- **`:focus-visible`:** a regra está no CSS, mas `.focus()` programático não a dispara neste motor — a sonda leu `outline: 3px none`, que é outra regra. **Precisa de conferência por teclado (Tab), não por script.**

## Descobertas
1. **O playbook estava errado no item 1.** Ele mandava transformar `.sb-body` em `<nav aria-label="Navegação principal">` porque o vivo faz isso. Mas o protótipo **já tinha** a `<nav>` com esse mesmo rótulo — em `.sb-menu` (e em `.sb-menu-rail`), um nível abaixo. Executar ao pé da letra criaria **landmark duplicado**. Comparei string de classe, não árvore. Nada a fazer: 1 `<nav>`, rótulo correto, nos dois modos.
2. **Quase apaguei uma regra viva.** O bloco de CSS morto era contíguo… menos por `.sb-body`, que morava no meio dele e **é usado**. Removi junto e recuperei a regra exata (`flex`/`overflow-y`/scrollbar) do `sync/payload.part35-36.json` de 07/09 — o pacote serviu de backup. Lição: recortar bloco por marcador de comentário sem listar as classes que saem é apostar.
3. `.sb-item` não tem `font-family` própria — herdava do documento enquanto era `div`. Como `<button>`, herdaria Arial do UA. Por isso o reset traz `font-family: inherit` e **não** `font: inherit` (que sobrescreveria o `font-size: 13px` da classe por especificidade). Medido: continua IBM Plex Sans 13px.
