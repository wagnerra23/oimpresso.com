---
id: requisitos-design-system-adr-ui-0009-cockpit-sidebar-light-padrao
---

# ADR UI-0009 · Sidebar do Cockpit segue tema do usuário (light por padrão)

- **Status**: accepted
- **Data**: 2026-05-04
- **Decisores**: Wagner, Claude
- **Categoria**: ui · evolução
- **Substitui parcialmente**: [UI-0008 §"Componentes obrigatórios do Cockpit"](0008-cockpit-layout-mae-do-erp.md) — trecho "Sidebar (260px, **dark fixo na vibe workspace**)"
- **Refs**: [ADR raiz 0039](../../../decisions/0039-ui-chat-cockpit-padrao.md), `resources/css/cockpit.css`, BRIEFING_CLAUDE_DESIGN.md §2 §6

## Contexto

A primeira versão do Cockpit (ADR UI-0008, 2026-04-27) fixou a **sidebar como dark** independente do tema do usuário, espelhando o AppShell legado AdminLTE-like. Os tokens `--sb-*` em `cockpit.css` foram hardcoded em `oklch(0.21 0 0)` etc. — uma única paleta para light e dark mode.

Em **2026-05-04**, validação visual do Wagner em produção (`oimpresso.com/copiloto`) expôs o problema: com `data-theme="dark"` ativo no usuário, o main column ficava escuro, mas a sidebar permanecia preta-pura, sem identidade. Em `data-theme="light"` (vibe canônica do projeto), a sidebar preta colidia com o main creme/branco — visual "pesado", contraste estranho, longe do protótipo Cowork "Oimpresso ERP - Chat.html" que usa sidebar **clara** harmoniosa com o main.

O protótipo Cowork (referenciado em DESIGN.md §6.3 como "verdade visual mais atual") **evoluiu** após UI-0008: sidebar virou light, espelhando linguagem visual de ferramentas tipo Linear/Notion/Vercel. Wagner formalizou em sessão 2026-05-04 ("branca é a correta muito mais linda") + autorizou padronização ("apagar 1 deles para não confundir" — referência ao AppShell legado, removido nesta mesma sessão).

## Decisão

**A sidebar do Cockpit segue o `data-theme` do usuário**, não é mais "dark fixo":

- **`data-theme="light"` (default)** — sidebar **clara**, paleta creme harmoniosa com o main (`--sb-bg: oklch(0.985 0.003 90)`). Espelha o protótipo Cowork atual.
- **`data-theme="dark"`** — sidebar **escura azul-cinza profundo** (não preto puro), `--sb-bg: oklch(0.18 0.006 240)`, ligeiramente mais escura que o main pra hierarquia visual. Variante elegante tipo Linear/Notion dark.

Tokens `--sb-*` agora têm variantes por tema, declarados em `.cockpit{}` (light) e `.cockpit[data-theme="dark"]{}` em `resources/css/cockpit.css`. Hardcodes pretos (`oklch(0.20 0 0)`, `oklch(0.32 0 0)`, `oklch(0.40 0 0)`) foram substituídos pelos tokens auxiliares `--sb-bg-2`, `--sb-scroll`, `--sb-bullet-out`.

A regra **"sidebar não muda no tweaks Vibe"** continua valendo (Vibe não toca `--sb-*`). Vibe afeta accent/density/textura do main; tema (light/dark) afeta a paleta inteira inclusive sidebar.

## Consequências

### Positivas

- **Coerência visual**: sidebar e main usam a mesma família de tons em cada tema. Fim do "main claro + sidebar preta" estranho.
- **Identidade do produto**: light mode bonito alinha com expectativa de ERP moderno (referências: Linear, Notion, Vercel, Pipedrive). Dark mode elegante sem ser "AdminLTE preto puro".
- **Sem opção nova pro usuário**: continua só `data-theme={light|dark|auto}`. Decisão é interna do tema, não vira mais um toggle.
- **Tokens organizados**: `--sb-*` com variantes em ambos temas, sem hardcodes — facilita futuro Tweaks "vibe daylight/focus" que precise repintar a sidebar.

### Negativas / mitigações

- **Quebra de paridade visual com versões anteriores em prod** — usuários acostumados com sidebar preta verão sidebar clara em dark mode. **Mitigação**: comunicar via release notes; é melhoria estética, não funcional.
- **Documentação canônica desatualizada** (UI-0008 + BRIEFING + cockpit.html legado) — ainda dizem "dark fixo". **Mitigação**: este ADR substitui parcialmente UI-0008; BRIEFING_CLAUDE_DESIGN.md §2/§6 patchados na mesma PR; `_DesignSystem/ui_kits/cockpit.html` será regenerado em sessão futura quando Claude Design exportar a versão atual do Cowork.
- **Ferramentas de teste visual (audits)** podem ter screenshots do estado antigo — re-baselinar quando rodar audit próxima.

## Alternativas consideradas

- **Manter sidebar dark fixo + escolher tom melhor (cinza-azulado em vez de preto puro)** — rejeitada: não resolve o desencontro com o main em light mode. Wagner explicitamente quer light no light.
- **Sidebar separada do tema (toggle próprio)** — rejeitada: vira mais um knob pro usuário; viola simplicidade.
- **Forçar light mode no Cockpit, ignorar `data-theme`** — rejeitada: usuários que preferem dark perderiam opção.

## Validação

- ✅ `cockpit.css` linha 7-17: tokens `--sb-*` light declarados
- ✅ `cockpit.css` linha 60+: `.cockpit[data-theme="dark"]{}` com override `--sb-*` dark profundo
- ✅ Hardcodes `oklch(0.20 0 0)`, `oklch(0.32 0 0)`, `oklch(0.40 0 0)`, `oklch(0.22 0 0)` substituídos por tokens
- ⏳ Smoke visual em local + prod (`/copiloto`, `/copiloto/cockpit`, `/financeiro`, `/ponto/dashboard`) — Wagner valida em PR
- ⏳ BRIEFING_CLAUDE_DESIGN.md §2 e §6 patchados (próximo passo desta PR)
- ⏳ CHANGELOG.md atualizado (próximo passo desta PR)
- ⏳ `_DesignSystem/ui_kits/cockpit.html` re-exportado do Cowork (PR futura — fora deste escopo)

## Notas para próximos agentes

Se você abrir UI-0008 e ler "Sidebar (260px, **dark fixo na vibe workspace**)" — esse trecho está **superseded por este ADR UI-0009**. Sidebar segue `data-theme` do usuário; light é default; dark é variante elegante (não preto puro).

Mudanças nos tokens `--sb-*` agora **devem** ter variante em ambos os temas. Não introduza paleta única que vaze entre eles.
