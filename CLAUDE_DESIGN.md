# CLAUDE_DESIGN.md — Como trabalhar em design/UI no Oimpresso ERP

> **Leia ANTES de tocar `resources/css/`, `resources/js/Layouts/`, `resources/js/Components/cockpit/` ou qualquer `Pages/*.tsx`.**
> Este guia evita 5 horas de reaprendizado. Mantenha-o curto. Se algo aqui está errado, conserte direto.

---

## 1. Fonte de verdade

- **ADR 0039** — `memory/decisions/0039-ui-chat-cockpit-padrao.md` é o contrato de UI do ERP.
- **Protótipo canônico** — ZIP em `C:\Users\wagne\OneDrive\Área de Trabalho\Oimpresso ERP Conunicação Visual..zip` (HTML + JSX + `styles.css`). Toda divergência visual deve ser justificada contra ele.
- **Layout-mãe** — `resources/js/Layouts/AppShellV2.tsx` (3 colunas: sidebar 260 + main 1fr + linked 320). **AppShell legado está deprecado.**
- **CSS canônico** — `resources/css/cockpit.css` (~1300 linhas, escopado em `.cockpit { ... }`). Espelha 1:1 o `styles.css` do protótipo.
- **Página de referência** — `resources/js/Pages/Copiloto/Cockpit.tsx` é o exemplo bom; copie o padrão dela.

## 2. Regras invioláveis

1. **Toda Page React usa AppShellV2 via persistent layout** (Inertia v3):
   ```tsx
   Component.layout = (page: ReactNode) => (
     <AppShellV2 title="Tela X" breadcrumbItems={[{ label: 'Módulo' }, { label: 'Tela' }]}>
       {page}
     </AppShellV2>
   );
   ```
   - `breadcrumbItems` (NÃO `breadcrumb`).
   - Title dinâmico: NÃO passar `title` ao shell, deixar `<Head title={dynamic}>` na render.
   - **NUNCA** envolver em `<AppShell>` ou criar layout próprio.
2. **CSS escopado em `.cockpit`** — não criar regras em `:root` nem em `app.css` que vazem pro cockpit.
3. **Tokens OKLCH** definidos em `cockpit.css`. Não inventar cor nova; use as variáveis.
4. **Fonte** — IBM Plex Sans 13.5px. Não trocar.
5. **Antes de mexer em qualquer componente cockpit**, leia o equivalente no protótipo (ZIP).

## 3. Bug recorrente (já queimou 2 vezes)

Após `npm run build:inertia`, **TEM QUE COMMITAR E DAR PUSH** dos assets gerados em `public/build-inertia/`. Esquecer o push = produção fica com TSX novo + JS compilado antigo (renderiza shell errado).

Receita correta:
```bash
npm run build:inertia                         # NÃO npm run build (vite.config.ts não existe)
git add resources/css/ resources/js/ public/build-inertia/
git commit -m "..."
git push origin main                          # OBRIGATÓRIO
# Deploy Hostinger:
for i in 1 2 3 4 5; do curl -s -o /dev/null --max-time 15 https://oimpresso.com/login; done
ssh -4 -o ConnectTimeout=90 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  "cd domains/oimpresso.com/public_html && git pull origin main && php artisan optimize:clear"
```

Hostinger NÃO tem Node. Build é local + commit dos assets.

## 4. Checklist antes de subir mudança visual

- [ ] Comparar visualmente com protótipo (zoom 200% se preciso) — `proto-styles.css` no ZIP é a referência
- [ ] Build rodou sem erro
- [ ] Assets commitados (`git status` não mostra `public/build-inertia/` modificado)
- [ ] Push feito pro `origin/main`
- [ ] Deploy feito (SSH Hostinger)
- [ ] Hard-refresh (Ctrl+Shift+R) em `/copiloto`, `/financeiro/dashboard`, `/ponto`, `/essentials/todo` — todas devem ter sidebar idêntica

## 5. Onde NÃO mexer

- `app.css` (Tailwind 4 + shadcn) — não vaza pro cockpit, deixa quieto.
- `AppShell.tsx` legado — não importar em Page nova. Se ver, migrar.
- `:root { ... }` no cockpit.css — viola escopamento.
- Componentes shadcn (`Components/ui/*`) — não tematizar; o cockpit já compõe ao redor.

## 6. Tokens visuais essenciais (pra não inventar)

| Variável | Valor |
|---|---|
| `--sb-bg` | `oklch(0.21 0 0)` (sidebar dark) |
| `--accent` | `oklch(0.58 0.09 220)` (azul, controlado por slider Tweaks) |
| `--bubble-them` | `oklch(0.96 0.003 90)` (bolha quase-branca) |
| `--origin-OS-bg` | hue 70 (laranja) |
| `--origin-CRM-bg` | hue 220 (azul) |
| `--origin-FIN-bg` | hue 145 (verde) |
| `--origin-PNT-bg` | hue 295 (roxo) |
| `--row-h` | 30px (skim 26 / briefing 34, controlado por slider) |
| `--radius` | 8px (sm 6 / lg 12) |

## 7. Gaps conhecidos vs protótipo

- **Tela `/tarefas`** — inbox unificada não existe ainda (precisa `TaskRegistry` PHP + `Pages/Tarefas/Index.tsx`).
- **Bloco Ponto no LinkedApps** — não implementado (`LinkedApps.tsx` tem OS/CRM/FIN/Anexos/Histórico mas falta PNT).
- **Atalhos J/K/E/A** — não implementados.
- **Backend de conversas** — sidebar Chat tab tem estrutura mas não há tabela `conversations`.

Se for trabalhar em qualquer um desses, abra ADR antes.

## 8. Quando bate dúvida

1. Olhar o protótipo (ZIP) primeiro
2. Olhar `Pages/Copiloto/Cockpit.tsx` (referência canônica)
3. Olhar ADR 0039
4. Só então perguntar pro Wagner

**Fim.** Se este guia não cobre o que você precisa, atualize-o em vez de duplicar conhecimento em outro lugar.
