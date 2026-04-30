# Manual de Uso — Oimpresso Design System no Claude Code

> **Para:** Wagner, Maíra, Felipe, Luiz, Eliana  
> **Quando usar:** sempre que for criar ou alterar tela React no ERP  
> **Última atualização:** 2026-04-30

---

## 1. Como ativar este Design System numa sessão Claude Code

Cole como **primeira mensagem** ao abrir uma sessão nova:

```
Leia o arquivo SKILL.md neste projeto de Design System e use-o como guia visual.
Também leia o README.md para contexto completo do produto.
Vou pedir para criar/alterar [descreva a tela].
```

Ou se você já está no repo `oimpresso.com`, aponte para este projeto:

```
Tenho um Design System em /projects/<ID-DESTE-PROJETO>.
Leia o SKILL.md e README.md de lá antes de começar.
```

---

## 2. Arquivos que o Claude Code deve ler (em ordem)

| Arquivo | O que contém | Quando ler |
|---------|-------------|-----------|
| `SKILL.md` | Regras rápidas, tokens, layout obrigatório | SEMPRE — 1ª leitura |
| `README.md` | Contexto completo, visual foundations | SEMPRE |
| `colors_and_type.css` | Todos os CSS vars | Ao criar/editar CSS |
| `resources/css/cockpit.css` | CSS canônico completo do Cockpit | Ao criar tela nova |
| `ui_kits/cockpit/index.html` | Protótipo interativo de referência | Ao replicar UI |
| `memory/requisitos/_DesignSystem/SPEC.md` | Regras R-DS-001..012 | Ao revisar PR |
| `resources/js/Components/cockpit/Sidebar.tsx` | Sidebar real React | Ao mexer na sidebar |
| `resources/js/Layouts/AppShellV2.tsx` | Layout-mãe Cockpit | Ao criar página nova |

---

## 3. Prompt padrão para criar tela nova

```
Preciso criar a tela [Nome] em React/Inertia para o módulo [Módulo].

Contexto do Design System:
- Layout: AppShellV2 (Cockpit) — sidebar 260px dark + main 1fr + Apps Vinculados 320px
- Tipografia: IBM Plex Sans (UI) + IBM Plex Mono (números/IDs)
- Cores: sempre via CSS vars (--accent, --text, --bg, --border, etc.) — NUNCA hardcoded
- Ícones: lucide-react exclusivamente
- PT-BR em todo label/copy/comentário
- localStorage com prefixo oimpresso.<modulo>.*

A tela deve seguir o padrão ADR UI-0006 (listagem CRUD):
- PageHeader + KpiGrid + PageFilters + DataTable + EmptyState

Componentes shared disponíveis em resources/js/Components/shared/:
- PageHeader, KpiGrid, KpiCard, DataTable, PageFilters, StatusBadge, EmptyState, BulkActionBar

Arquivo de referência: resources/js/Pages/Copiloto/Dashboard.tsx
```

---

## 4. Tokens CSS essenciais — cole no contexto quando precisar

```css
/* Superfícies */
--bg          /* fundo principal */
--bg-2        /* fundo secundário (listas, painéis) */
--surface     /* branco / card */
--border      /* borda padrão */
--border-2    /* borda sutil */

/* Texto */
--text        /* texto principal */
--text-dim    /* texto secundário */
--text-mute   /* placeholder, metadata */

/* Accent */
--accent      /* azul oklch(0.58 0.09 220) — botões, foco, active */
--accent-2    /* hover do accent */
--accent-soft /* fundo suave do accent — focus ring */

/* Tipografia */
--font-sans   /* IBM Plex Sans */
--font-mono   /* IBM Plex Mono */

/* Layout */
--row-h       /* altura de linha (26/30/34px conforme densidade) */
--radius      /* 8px */
--radius-sm   /* 6px */
--radius-lg   /* 12px */

/* Origin badges (5 módulos fixos) */
--origin-OS-bg/fg    /* amber  */
--origin-CRM-bg/fg   /* blue   */
--origin-FIN-bg/fg   /* green  */
--origin-PNT-bg/fg   /* violet */
--origin-MFG-bg/fg   /* orange */
```

---

## 5. Checklist mínimo antes de abrir PR

Copie e cole no PR description:

```
## Checklist Design System

- [ ] Tela vive dentro de `<AppShellV2>` (não AppShell legado)
- [ ] Nenhuma cor hardcoded — só tokens CSS
- [ ] Ícones: apenas `lucide-react`
- [ ] PT-BR em todo label, copy, comentário
- [ ] Dark mode testado (toggle em Tweaks)
- [ ] Estado persistido em `localStorage` com prefixo `oimpresso.<modulo>.*`
- [ ] Componentes shared usados (PageHeader, DataTable, etc.) antes de criar novo
- [ ] Apps Vinculados entregue se há entidade em foco
- [ ] Atalhos J/K/E/A se for master/detail
- [ ] Empty state implementado
- [ ] Loading state implementado
- [ ] Session log atualizado em `memory/sessions/`
- [ ] **`npm run build:inertia` rodado E `public/build-inertia/` commitado**
- [ ] **`git push origin main` feito** (assets compilados precisam ir pro remote)
```

### Bug recorrente: assets não-pushados

⚠️ **Já queimou 2x.** Mudança em TSX/CSS sem `npm run build:inertia` + commit dos assets compilados resulta em produção com TSX novo + JS antigo (renderiza shell errado). Hostinger NÃO tem Node — build é local + commit dos assets.

Receita correta:
```bash
npm run build:inertia                         # NÃO `npm run build` (vite.config.ts não existe)
git add resources/css/ resources/js/ public/build-inertia/
git commit -m "..."
git push origin main                          # OBRIGATÓRIO

# Deploy Hostinger (warm-up SSH + git pull):
for i in 1 2 3 4 5; do curl -s -o /dev/null --max-time 15 https://oimpresso.com/login; done
ssh -4 -o ConnectTimeout=90 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  "cd domains/oimpresso.com/public_html && git pull origin main && php artisan optimize:clear"
```

---

## 6. Origin badges — como usar

```tsx
// Em TSX/JSX
<span className="tk-origin" style={{
  background: `var(--origin-${task.origin}-bg)`,
  color: `var(--origin-${task.origin}-fg)`
}}>
  {task.origin}
</span>

// Origens válidas: OS | CRM | FIN | PNT | MFG
// NUNCA invente nova cor — abra ADR se precisar de 6º módulo
```

---

## 7. Como criar um Apps Vinculados (coluna direita)

```tsx
// Em resources/js/Components/LinkedApps/<NomeDoModulo>.tsx
export function LinkedNomeModulo({ data }) {
  return (
    <div className="lblock">
      <div className="lblock-h">
        <span className="origin-badge o-OS">OS</span>
        <b>Nome do Bloco</b>
        <span className="spacer"/>
      </div>
      <div className="lblock-b">
        <div className="lkv">
          <span>Campo</span>
          <b>{data.valor}</b>
        </div>
        <button className="lblock-cta">Ação primária →</button>
      </div>
    </div>
  );
}

// Estado colapsado persiste em:
// localStorage.setItem('oimpresso.linked.<bloco>.collapsed', '1')
```

---

## 8. Referência rápida de componentes shared

```tsx
import PageHeader   from '@/Components/shared/PageHeader';
import { KpiGrid, KpiCard } from '@/Components/shared/KpiGrid';
import DataTable    from '@/Components/shared/DataTable';
import PageFilters  from '@/Components/shared/PageFilters';
import StatusBadge  from '@/Components/shared/StatusBadge';
import EmptyState   from '@/Components/shared/EmptyState';
import BulkActionBar from '@/Components/shared/BulkActionBar';

// Uso básico:
<PageHeader
  icon="clock"
  title="Aprovações pendentes"
  description="12 solicitações aguardando"
  action={<Button>Nova intercorrência</Button>}
/>

<StatusBadge kind="intercorrencia" value="pendente" />
// kinds: intercorrencia | aprovacao | prioridade | payment | financeiro_titulo
```

---

## 9. Padrão de persistência localStorage

```ts
// Prefixos por escopo
localStorage.setItem('oimpresso.cockpit.sidebar.tab', 'chat');   // shell
localStorage.setItem('oimpresso.linked.os.collapsed', '1');       // linked blocks
localStorage.setItem('oimpresso.financeiro.filtro', 'aberto');    // módulo
localStorage.setItem('oimpresso.ponto.aba', 'espelho');           // módulo

// NUNCA sessionStorage — perde na nova aba
// NUNCA sem prefixo — colide com outras libs
```

---

## 10. Quando divergir do padrão

1. **Pare antes de codificar**
2. Abra ADR em `memory/requisitos/<Modulo>/adr/ui/NNNN-slug.md`
3. Peça aprovação do Wagner antes de mergear
4. Atualize `DESIGN.md` com a nova regra

---

## 11. Arquivos de referência no repo

| O que quer | Arquivo de referência |
|------------|----------------------|
| Tela de listagem CRUD | `resources/js/Pages/Financeiro/` |
| Tela master/detail | `resources/js/Pages/Copiloto/Cockpit.tsx` |
| Componentes shared | `resources/js/Components/shared/` |
| Cockpit layout | `resources/js/Layouts/AppShellV2.tsx` |
| CSS tokens | `resources/css/cockpit.css` |
| Módulo de referência | `Modules/Jana/` ou `Modules/Repair/` |

---

> **Dica rápida:** antes de criar qualquer componente novo, pergunte ao Claude Code:
> *"Existe algum componente shared em resources/js/Components/shared/ que resolve isso?"*
> A resposta quase sempre é **sim**.
