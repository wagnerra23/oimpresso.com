# 📦 Pacote Claude Code — PageHeader canon v4.1

> ## ⚠️ REVOGADO em 2026-05-24 — **NÃO COLAR NO CODE**
>
> Pacote referenciava `pageheader-canon.css` que foi deletado. SPEC e ADR foram revogados (ver headers). Não rodar este prompt — aplicaria mudanças contraditórias ao canon real (ADR UI-0013 + PT-01 Lista).
>
> Aplicação correta agora vive in-situ em `clientes-page.jsx` + `clientes-page.css` no Cowork.

---

Cole o prompt abaixo no Claude Code (no repo `wagnerra23/oimpresso.com` checkout local). URLs válidas ~1h. Se expirar, regenero.

---

## Prompt zero-touch (copiar tudo)

```
Tarefa: PageHeader canon v4.1 (ADR-0115) — Onda 1 piloto Cadastro/Clientes.

Branch: feat/pageheader-canon-v4
Base: main

PASSO 1 — Fetch dos 4 artefatos vindos do Cowork ([CC]):

mkdir -p /tmp/pageheader-canon-v4
curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/pageheader-canon-v4/SPEC.md?t=636e50cee9f307bfb95c4626e740881876c0b5e1d486a0fd8612cd23bf3d1506.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779668426&direct=1" -o /tmp/pageheader-canon-v4/SPEC.md
curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/pageheader-canon.css?t=636e50cee9f307bfb95c4626e740881876c0b5e1d486a0fd8612cd23bf3d1506.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779668426&direct=1" -o /tmp/pageheader-canon-v4/pageheader-canon.css
curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/pageheader-canon-v4/ADR-0115-pageheader-canon-v4.md?t=636e50cee9f307bfb95c4626e740881876c0b5e1d486a0fd8612cd23bf3d1506.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779668426&direct=1" -o /tmp/pageheader-canon-v4/ADR-0115-pageheader-canon-v4.md
curl -sL "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/pageheader-canon-v4/index.html?t=636e50cee9f307bfb95c4626e740881876c0b5e1d486a0fd8612cd23bf3d1506.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779668426&direct=1" -o /tmp/pageheader-canon-v4/index.html

Leia SPEC.md e ADR-0115 antes de continuar. SPEC é canon executável.

PASSO 2 — Criar branch:

git checkout main && git pull origin main
git checkout -b feat/pageheader-canon-v4

PASSO 3 — Aplicar artefatos no repo:

(a) Copiar CSS canon:
mkdir -p resources/css/tokens
cp /tmp/pageheader-canon-v4/pageheader-canon.css resources/css/tokens/page-header.css

(b) Copiar ADR:
mkdir -p memory/decisions
cp /tmp/pageheader-canon-v4/ADR-0115-pageheader-canon-v4.md memory/decisions/0115-pageheader-canon-v4.md

(c) Em resources/css/styles.css, dentro do bloco :root onde estão --origin-OS/CRM/FIN/PNT/MFG (linhas ~35-40), ADICIONAR após a última linha de origins:

  --origin-CAD-bg: oklch(0.92 0.06 180);  --origin-CAD-fg: oklch(0.40 0.10 180);
  --origin-FIS-bg: oklch(0.93 0.06 340);  --origin-FIS-fg: oklch(0.42 0.12 340);
  --origin-SIS-bg: oklch(0.94 0.005 240); --origin-SIS-fg: oklch(0.50 0.01 80);

E dentro de [data-theme="dark"]:

  --origin-CAD-bg: oklch(0.28 0.06 180);  --origin-CAD-fg: oklch(0.80 0.10 180);
  --origin-FIS-bg: oklch(0.30 0.07 340);  --origin-FIS-fg: oklch(0.82 0.11 340);
  --origin-SIS-bg: oklch(0.24 0.005 240); --origin-SIS-fg: oklch(0.68 0.005 90);

(d) Importar tokens/page-header.css. Em resources/css/inertia.css (ou app.css principal), adicionar como ÚLTIMA importação:

  @import "./tokens/page-header.css";

(e) Adicionar container-type ao shell. Em resources/css/cockpit.css (ou onde estiver definida .main-body do AppShellV2), garantir:

  .main-body {
    container-type: inline-size;
    container-name: page-shell;
  }

(f) Migrar resources/js/Pages/Cadastro/Clientes/Index.tsx pra markup v4.

Estrutura nova (substituindo o <div className="os-page-h"> atual):

  <header className="os-page-h" data-group="cadastro" data-subtitle={hasSubtitle ? "true" : undefined}>
    <div className="os-page-h-l">
      <nav className="os-page-h-bc" aria-label="Trilha">
        <Link href="/cadastro">Cadastro</Link>
        <span className="os-page-h-bc-sep">/</span>
        <span>Clientes</span>
      </nav>
      <h1 className="os-page-h-title">Clientes</h1>
      <p className="os-page-h-sub">
        {totals.total} cadastrados · {totals.active} com OS aberta
        {totals.late > 0 && <> · <strong data-tone="danger">{totals.late} com atraso</strong></>}
      </p>
    </div>
    <div className="os-page-h-c">
      <nav className="os-page-h-nav" aria-label="Sub-navegação">
        {TABS.map(t => (
          <button key={t.id}
            className="os-page-h-tab"
            aria-current={filter === t.id ? "page" : undefined}
            onClick={() => setFilter(t.id)}>
            <span>{t.label}</span>
            <span className="os-page-h-tab-n">{t.count}</span>
          </button>
        ))}
      </nav>
    </div>
    <div className="os-page-h-r">
      <button className="os-page-h-secondary">
        <Upload size={13}/> Importar
      </button>
      <button className="os-page-h-primary">
        <Plus size={13}/> Novo cliente
        <kbd className="os-page-h-kbd">⌘N</kbd>
      </button>
    </div>
  </header>

Mover as tabs do antigo .os-toolbar pra zona-C do header.
Toolbar passa a ter SÓ a barra de busca + filtros avançados.

Referência completa de markup (com data real preenchida): /tmp/pageheader-canon-v4/index.html

PASSO 4 — Pest 4 Browser baseline.

Criar tests/Browser/PageHeaderCanon.test.ts seguindo §19 da SPEC.
Rodar 1x localmente pra gerar baselines (cadastro × 3 densidades × 2 themes = 6 snapshots iniciais).
Não bloqueie merge no diff de visual regression na 1ª run — só salva baseline.

PASSO 5 — Commit + push + PR.

git add resources/css/tokens/page-header.css
git add resources/css/styles.css
git add resources/css/inertia.css     # ou app.css se for o caso
git add resources/css/cockpit.css     # se .main-body estiver lá
git add resources/js/Pages/Cadastro/Clientes/Index.tsx
git add memory/decisions/0115-pageheader-canon-v4.md
git add tests/Browser/PageHeaderCanon.test.ts

git commit -m "feat(ui): PageHeader canon v4.1 (ADR-0115) — Onda 1 piloto Cadastro/Clientes

- novo tokens/page-header.css (~350 LOC, density-aware)
- 3 novos origin tokens (CAD/FIS/SIS) em styles.css
- container-type page-shell em .main-body
- markup v4 aplicado em Pages/Cadastro/Clientes/Index.tsx
- tabs migradas de .os-toolbar pra zona-C do header
- Pest 4 Browser baseline (cadastro)
- ADR-0115 documentando decisão

[F1 design CC] + [F3 código CL] — aguardando F1.5 critique + F2 screenshot W2"

git push -u origin feat/pageheader-canon-v4

gh pr create \
  --base main \
  --title "feat(ui): PageHeader canon v4.1 — Onda 1 piloto Cadastro/Clientes" \
  --body "PageHeader canon v4.1 (ADR-0115). Aplica markup v4 em Cadastro/Clientes/Index.tsx, adiciona tokens CAD/FIS/SIS, novo resources/css/tokens/page-header.css, container-type em .main-body. F1+F3 prontos, aguardando F1.5 (CD critique), F2 (W2 screenshot), F3.5 (CA a11y), F4 (W2 merge). Ver memory/decisions/0115-pageheader-canon-v4.md."

Se algum passo falhar (typo, conflito de merge, lint), pare e relate. Não mergeie — F4 é responsabilidade do Wagner aprovador [W2].
```

---

## Resumo do que vai pro repo

| Arquivo | Tamanho | Função |
|---|---|---|
| `resources/css/tokens/page-header.css` | 12 KB | CSS canon v4.1 (geometry, tokens, density, hue, a11y, print) |
| `resources/css/styles.css` (+12 linhas) | — | 3 origin tokens CAD/FIS/SIS (light + dark) |
| `resources/css/inertia.css` (+1 linha) | — | `@import "./tokens/page-header.css"` |
| `resources/css/cockpit.css` (+2 props) | — | `container-type` em `.main-body` |
| `resources/js/Pages/Cadastro/Clientes/Index.tsx` | — | Markup v4 (tabs migradas) |
| `memory/decisions/0115-pageheader-canon-v4.md` | 7 KB | ADR completa |
| `tests/Browser/PageHeaderCanon.test.ts` | — | Pest 4 Browser baseline |

**4 URLs públicas válidas ~60min.** Se expirar antes do Code rodar, me avisa que regenero.

## O que [CC] NÃO pode fazer

- Commitar / push / PR (Code faz)
- Rodar Pest 4 (Code/CI)
- F1.5 critique (responsabilidade [CD])
- F2 screenshot approval (síncrono com Wagner)
- F3.5 a11y review (responsabilidade [CA])
- F4 merge (Wagner W2)
