# github.md

repo: wagnerra23/oimpresso.com
branch: main
path: (repo inteiro — foco em resources/css, resources/js, memory/, prototipo-ui/, scripts/)

## Last sync

date: 2026-08-31T20:40:00Z
tree: 84b62eb785e8 (hash de árvore lido pelas ferramentas — não é commit sha)

### Updated in this project

- **Reset dos documentos.** `HANDOFF.md` reescrito do zero contra `84b62eb785e8`: só o vigente, mapa separando 3 pares medidos de 40+ herdados, §5 "Não verificado" explícita. `README.md` limpo da arqueologia (errata de paths, `AppShell.tsx`, `shared/ponto/`, `ModuleTopNav`, `inertia.css` como SSOT). Versões antigas em `arquivo/`.
- `TabBar` — o `<nav>` virou o contrato: `...rest`, `className` somado, `ariaLabel`, `pad`, `size`, `off`, `icon`, `inset`. Wrapper removido dos 3 templates que usavam o padrão PT-01.
- `PageHeader` — `leading` (alinhado ao slot homônimo do canon, não caixa), `context`, `freshness`/`freshnessRel` reusando `StatusBadge kind="frescor"`.
- `HANDOFF-2026-08-31-tabbar-pageheader.md` novo — handoff da rodada, com auditoria do `main` (linha medida) e bloco de COWORK_NOTES.

## Sync history

### 2026-08-24T20:10:00Z · tree 29a59c1ce1d3

- `HANDOFF.md` novo — contrato de handoff zero-touch espelho→repo (regras duras, mapa componente→arquivo, DoD por máquina, bloco COWORK_NOTES).
- README: primary corrigido pra roxo no parágrafo de abertura (blue do shadcn legado é superseded).
- README: direção do loop corrigida — claude.ai/design é NÃO-fonte (ADR 0315/0299); push git→design via `ds-push.mjs`, design→git só com opt-in.
- README: adicionada a regra **sidebar PRETA dark-fixo** (UI-0023), AP7/AP9/AP10, e errata de paths do `main` (AppShellV2, sem `shared/ponto/`, sem `ModuleTopNav`).

## Screen map

| Tela / template daqui | Arquivos-fonte no repo |
| --- | --- |
| `templates/clientes-crm/` | `resources/js/Pages/Cliente/Index.tsx` + `Index.charter.md` + `_drawer/` + `_show/` |
| `templates/financeiro/` | `resources/js/Pages/Financeiro/Unificado/`, `Financeiro/ProvaViva.tsx` |
| `templates/oficina-auto/`, `templates/pt-07-os-detail/` | `resources/js/Pages/OficinaAuto/ServiceOrders/`, `OficinaAuto/Vehicles/` |
| `templates/pt-01-lista/` | padrão PT-01 — `Cliente/Index.tsx`, `Produto/Index.tsx`, `Sells/Index.tsx` |
| `templates/pt-05-dashboard/` | `resources/js/Pages/Home/Index.tsx`, `Pages/governance/Dashboard.tsx` |
| `templates/atendimento/` | sem par 1:1 no `main` (adjacentes: `Pages/Jana/Chat.tsx`, `Pages/Whatsapp/_components/`) |
| `components/*` (46 componentes) | `resources/js/Components/ui/*` (32), `resources/js/Components/shared/*` (16), `resources/js/Components/PageHeader/*` (canon v3.8), `resources/js/Layouts/AppShellV2.tsx` — mapa completo em `HANDOFF.md` §3 |
| `colors_and_type.css`, `cockpit_domains.css` | `resources/css/tokens/*.tokens.json` → `_generated-*.css`, `resources/css/cockpit.css`, `foundations.css` |
