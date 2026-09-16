# github.md

repo: wagnerra23/oimpresso.com
branch: main
path: (repo inteiro — foco em resources/css, resources/js, memory/, prototipo-ui/, scripts/)

## Last sync

date: 2026-09-16T22:50:00Z
commit: 5c55e4f96f1 (sha real do merge do PR #7456 — nao e tree hash)

### Updated in this project

- **`colors_and_type.css` — push git->espelho dos 8 tokens que estavam divergentes.** O git avancou 2x
  (v1.2.0 em 02/09, v1.3.0 em 08/09) e o espelho ficou no valor pre-conserto; o `ds-mirror-drift`
  acusava 8 vs baseline 0 em 15 runs seguidas, todas `success` por ser advisory. Valores agora iguais
  ao canon: `--color-success-foreground` e `--color-warning-foreground` (light e dark) em
  `oklch(0.20 0.02 h)`; `--accent-soft`/`--pos`/`--neg`/`--warn` do `.cockpit[data-theme="dark"]`.
  Efeito visivel: pilulas de status ganham tinta escura sobre chip solido (conserto de contraste do
  `StatusBadge`). Medicao de a11y anterior a esta data foi feita contra tokens velhos.
- **3 `@font-face` corrigidas de carona.** Os pesos 500/600/700 do IBM Plex Sans apontavam para
  `ibm-plex-sans-400.woff2` na copia do repo. O scaffold do push foi o arquivo do handoff 22 (leitura
  do vivo), entao o vivo manteve os pesos certos e o repo recebeu o conserto.
- `cockpit_domains.css` reescrito sem diferenca de conteudo (write do mesmo par).
- Verificacao: `ds-push` VALOR:0 vs canon · `write_files` written:2 · releitura com 8 de 8 valores
  presentes · `ds-mirror-drift` drift 0 baseline 0 nos 4 escopos.

## Sync history

### 2026-08-31T20:40:00Z · tree 84b62eb785e8

- **Reset dos documentos.** `HANDOFF.md` reescrito do zero contra `84b62eb785e8`: só o vigente, mapa separando 3 pares medidos de 40+ herdados, §5 "Não verificado" explícita. `README.md` limpo da arqueologia (errata de paths, `AppShell.tsx`, `shared/ponto/`, `ModuleTopNav`, `inertia.css` como SSOT). Versões antigas em `arquivo/`.
- `TabBar` — o `<nav>` virou o contrato: `...rest`, `className` somado, `ariaLabel`, `pad`, `size`, `off`, `icon`, `inset`. Wrapper removido dos 3 templates que usavam o padrão PT-01.
- `PageHeader` — `leading` (alinhado ao slot homônimo do canon, não caixa), `context`, `freshness`/`freshnessRel` reusando `StatusBadge kind="frescor"`.
- `HANDOFF-2026-08-31-tabbar-pageheader.md` novo — handoff da rodada, com auditoria do `main` (linha medida) e bloco de COWORK_NOTES.

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
