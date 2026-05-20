# prototipo-ui — Chat Cockpit (Oimpresso ERP)

> **O que é isto:** protótipo de alta fidelidade, em React+JSX direto no browser (sem build), do novo shell **Chat Cockpit** que vai substituir o `AppShell.tsx` atual quando a migração Blade→React (MWART) avançar.
>
> **De onde vem:** desenhado no Cowork (Claude.ai), exportado periodicamente para esta pasta do repo.
>
> **Para que serve:** referência visual e de interação. Quando uma tela do protótipo for promovida a produção, ela é portada para `resources/js/Pages/<Modulo>/<Tela>.tsx` seguindo [ADR 0039](../memory/decisions/0039-ui-chat-cockpit-padrao.md).

---

## Como abrir

Não precisa de build. É HTML + JSX (Babel in-browser).

```bash
# qualquer servidor estático na pasta
cd prototipo-ui
npx serve .
# abre http://localhost:3000/Oimpresso ERP - Chat.html
```

Ou abre direto pelo VS Code com a extensão **Live Server**.

---

## Estrutura

```
prototipo-ui/
├── Oimpresso ERP - Chat.html     ← entry point
├── app.jsx                       ← shell (sidebar + main + tweaks)
├── sidebar.jsx                   ← AppShell sidebar (espelho do AppShell.tsx real)
├── chat.jsx                      ← coluna lista de conversas + thread
├── tasks.jsx                     ← inbox unificada (TaskProvider mockado)
├── viewers.jsx                   ← componentes do painel direito
├── linked-apps.jsx               ← Apps Vinculados (coluna 320px)
├── laravel-panel.jsx             ← debug panel (dev only, oculto)
├── tweaks-panel.jsx              ← painel de tweaks expressivos
├── icons.jsx                     ← icon set (lucide-flavored)
├── styles.css                    ← tokens + componentes (vibe/density/accent)
│
├── data.jsx                      ← mock empresas, menu, conversas
├── data-os.jsx                   ← mock OS, clientes, catálogo, timeline
├── data-clientes.jsx             ← mock clientes (Fase 3)
├── data-orc-prod.jsx             ← mock orçamentos + produtos
│
├── os-page.jsx                   ← Listagem + detalhe + Nova OS + Aprovar arte
├── clientes-page.jsx             ← Listagem de clientes (Fase 3)
├── orc-page.jsx                  ← Listagem de orçamentos (Fase 3)
├── prod-page.jsx                 ← Catálogo de produtos (Fase 3)
├── producao-page.jsx             ← Fila de produção
│
├── Inventario - Migracao Blade React.html  ← inventário visual da migração
│
├── memory/
│   ├── HANDOFF.md                ← estado vivo da migração (escopo, fases, próximos passos)
│   ├── decisions/                ← ADRs do design (espelham `memory/decisions/` raiz)
│   └── sessions/                 ← logs de sessão de design
│
└── README.md                     ← este arquivo
```

---

## Mapa: protótipo → produção

| Tela no protótipo | Vira em produção | Status |
|---|---|---|
| `app.jsx` (shell) | `resources/js/Layouts/AppShellV2.tsx` | 🟡 a portar |
| `sidebar.jsx` | `resources/js/Layouts/AppShell/Sidebar.tsx` | 🟡 a portar |
| `tasks.jsx` | `resources/js/Pages/Tarefas/Index.tsx` | 🟢 já parcial |
| `os-page.jsx` (lista+detalhe+novo+aprovar) | `resources/js/Pages/Os/{Index,Show,Edit,AprovarArte}.tsx` | 🔴 só protótipo |
| `clientes-page.jsx` | `resources/js/Pages/Clientes/Index.tsx` | 🔴 só protótipo |
| `orc-page.jsx` | `resources/js/Pages/Orcamentos/Index.tsx` | 🔴 só protótipo |
| `prod-page.jsx` | `resources/js/Pages/Produtos/Index.tsx` | 🔴 só protótipo |
| `producao-page.jsx` | `resources/js/Pages/Producao/Fila.tsx` | 🔴 só protótipo |
| `linked-apps.jsx` | `resources/js/Components/LinkedApps/*.tsx` | 🔴 só protótipo |
| `viewers.jsx` | `resources/js/Components/Viewers/*.tsx` | 🟢 já parcial |

---

## Convenções importadas pro código de produção

Ao portar uma tela, garantir:

1. **Tokens CSS** vêm do shell (`--accent`, `--row-h`, `--origin-OS-bg` etc) — definidos em `resources/css/app.css`. Não inventar cor.
2. **Persistência** em `localStorage` com prefixo `oimpresso.<modulo>.<chave>`.
3. **Atalhos** J/K/E/A em master/detail (ver `tasks.jsx` e `os-page.jsx`).
4. **Apps Vinculados** (coluna direita 320px) renderizada quando há contexto vinculado — cada bloco em `Components/LinkedApps/` separado.
5. **TaskProvider** para inbox de novo módulo — não criar tela de listagem própria, registrar provider e deixar `Tarefas/Index.tsx` agregar.
6. **PT-BR** em todo label/copy/comentário.

Detalhes completos em [`memory/HANDOFF.md`](memory/HANDOFF.md) e [ADR 0039](../memory/decisions/0039-ui-chat-cockpit-padrao.md).

---

## Sync com o Cowork

Este protótipo é **vivo** no Cowork. Toda vez que houver mudança significativa:

1. No Cowork, exportar o projeto como `.zip`
2. Em `D:\oimpresso.com\`, executar (ou pedir ao Claude Code):
   ```
   claude "Sincroniza prototipo-ui/ com o zip em D:\downloads\oimpresso-prototipo.zip,
   cria branch chore/prototipo-sync-$(date +%Y-%m-%d), commita diffs, abre PR."
   ```
3. Revisar PR no GitHub e mergear

---

## Status atual

Ver `memory/HANDOFF.md`. Última atualização: ver topo do arquivo.

**Fases concluídas:** 1 (shell), 2 (OS piloto), 3 (Clientes/Orçamentos/Produtos).
**Próxima:** Fase 4 — operacional de produção (fila, acabamento, expedição).
