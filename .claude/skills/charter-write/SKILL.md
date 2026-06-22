---
name: charter-write
mission: "Substituir escrita manual de Page Charter por draft assistido + revisão humana."
description: ATIVAR quando user pedir "criar charter da tela X", "escrever charter pra /caminho", "gerar charter de Index.tsx Y", "novo charter Page", "/charter-write {pagina}". Lê o `.tsx` da tela + Controller + topnav/routes pra inferir Mission/Goals/UX targets/Automation hooks; gera draft em `*.charter.md` ao lado do `.tsx`; PARA aguardando Wagner revisar Non-Goals + Anti-hooks (parte mais sensível, anti-alucinação). NUNCA marca charter como `status: live` sozinho — Wagner aprova.
type: process-skill
status: active
version: 1.0.0
trust_level: L2
owner: wagner
created_at: 2026-05-08
charter_adr: 0101
parent_mission: "Toda skill substitui trabalho humano repetitivo com ROI provado, rumo ao ERP autônomo."
triggers_on:
  - "/charter-write"
  - "/charter-write {pagina}"
  - "criar charter da tela {pagina}"
  - "escrever charter pra {pagina}"
  - "gerar charter {pagina}"
  - "novo charter Page {pagina}"
does_not_trigger_on:
  - editar charter existente (use Edit direto + Wagner revisa)
  - criar charter de feature/mission (escala diferente — outro template)
  - criar charter de tela Blade legacy (Tier C — fora de F1, vai pra F2)
  - aprovar charter como `status: live` (só Wagner aprova)
roi_metric:
  type: time
  baseline: "Wagner escreve charter manual em ~30min (lê .tsx + pensa Non-Goals + monta frontmatter)"
  target: "/charter-write {pagina} reduz pra ~5min — ler draft + revisar Non-Goals + aprovar"
metrics:
  charters_drafted: 0
  drafts_aprovados_total: 0
  drafts_rejeitados_total: 0
  paginas_cobertas: []
artefatos_governados:
  - "resources/js/Pages/{X}/{Y}.charter.md (output gerado, status=wip)"
parent_adr: 0095
tier: B
---

# charter-write

Skill que gera draft de Page Charter a partir de `.tsx` + Controller. Pattern canônico em [ADR 0101](../../../memory/decisions/0101-sistema-charter-capterra-governanca-escopo.md).

## Os 6 passos

### 1. Validar pré-condições

```
- resources/js/Pages/{X}/{Y}/Index.tsx existe?
  - SE NÃO: parar, instruir "Tela não existe ainda. Crie a tela primeiro"
- Existe charter no mesmo dir?
  - SE SIM: parar, instruir "Charter já existe. Use Edit direto ou crie supersede *.charter-v2.md"
- Tela é Inertia (tem `import` de @inertiajs)?
  - SE NÃO: parar, instruir "Tela é Blade legacy → Tier C, fora de escopo desta skill"
```

### 2. Ler artefatos da tela

```
Read resources/js/Pages/{X}/{Y}/Index.tsx (limit 200)
Glob Modules/{X}/Http/Controllers/*.php → achar controller que renderiza Inertia::render('{X}/{Y}/Index')
Read controller (método index ou similar, limit 80)
Glob Modules/{X}/Resources/menus/topnav.php → ver onde a tela aparece na nav
```

### 3. Inferir cada seção

| Seção | Como inferir |
|---|---|
| **Mission** | Comentário no topo do `.tsx` + `<h1>` ou `PageHeader title=` + 1 frase resumida |
| **Goals** | Componentes renderizados (KPIs, listas, formulários, ações) — listar verbo no presente |
| **Non-Goals** | ⚠️ **NÃO INFERIR** — gera placeholder `❌ TODO Wagner: confirmar Non-Goals` e PARA |
| **UX Targets** | p95 < (1500 admin / 800 prod) ms, 1280px ROTA LIVRE, AppShellV2, multi-tenant |
| **UX Anti-patterns** | Comuns: ❌ modal pra read-only, ❌ confirmação dupla, ❌ window.location.reload |
| **Automation Hooks** | Endpoint do Controller, jobs/listeners citados em comments do controller |
| **Automation Anti-hooks** | ⚠️ **NÃO INFERIR** — gera placeholder + PARA. Critico pra anti-alucinação. |
| **Métricas vivas** | Lista 3-5 stubs `{X}{Y}CharterTest::it_does_not_X()` baseados em Non-Goals |

### 4. Escrever draft com `status: wip`

```yaml
---
page: /caminho/inferido
component: resources/js/Pages/X/Y/Index.tsx
owner: wagner               # default; user pode trocar
status: wip                 # SEMPRE wip — Wagner aprova pra virar live
last_validated: {today}
parent_module: X
related_adrs: [0101]
tier: A | B                 # inferir; B se não houver telemetria/incidente
charter_version: 1
---
```

### 4b. Integridade de refs (catraca `charter_refs_broken`)

Charter linka pra docs do repo. Há **dois esquemas** — não misturar:

- **Frontmatter** (`component`/`runbook`/`parent_capterra`): caminho **repo-relative** (da raiz), ex `memory/requisitos/X/CAPTERRA-INVENTARIO.md`. **Sem `../`.**
- **Links de body** (`[txt](../...)`): **relativo ao charter**. Profundidade = nº de pastas do charter até a raiz. Charter em `resources/js/Pages/<A>/Index.charter.md` → **4** `../`; cada subpasta a mais → +1 (`<A>/<B>/` → 5).

**NÃO conte `../` no olho** (foi a fonte de 215 links mortos por off-by-one). Antes de apresentar/commitar o draft, rode a garantia mecânica:

```
node scripts/governance/charter-refs.mjs --fix    # corrige profundidade off-by-one sozinho
node scripts/governance/charter-refs.mjs --check   # falha se > teto (gate charter-refs-gate.yml)
```

`--fix` só reescreve link cujo alvo existe na profundidade certa (seguro). Se sobrar quebrada, o alvo mudou de nome (repath) ou morreu (remova a ref).

### 5. Apresentar pra Wagner

Mostrar pro Wagner em texto curto:

```
Charter draft criado: resources/js/Pages/{X}/{Y}/Index.charter.md (status: wip)

Inferi:
- Mission: {1 frase}
- {N} Goals
- {N} UX Targets

PRECISA SUA REVISÃO:
- Non-Goals (❌ TODO) — você precisa listar o que essa tela NÃO faz
- Automation Anti-hooks (❌ TODO) — você precisa listar o que ela NUNCA dispara

Sem Non-Goals + Anti-hooks reais, charter não vira `status: live` (Pest GUARD vazio).
```

**PARA. NÃO MARCA `status: live` sem Wagner.**

### 6. Após aprovação Wagner

Se Wagner mandar Non-Goals + Anti-hooks:
- Edita o charter draft com a info
- Muda `status: wip` → `status: live`
- Atualiza `last_validated: {today}`
- Sugere git commit + push (não faz sozinho — `commit-discipline`)

## Reprovações (não fazer)

- ❌ Inferir Non-Goals sem Wagner (anti-alucinação central)
- ❌ Inferir Automation Anti-hooks sem Wagner
- ❌ Marcar `status: live` sem aprovação humana
- ❌ Editar charter existente (use Edit direto)
- ❌ Criar charter pra tela Blade (escopo Tier C, futuro)
- ❌ Criar charter sem `parent_module` (FK pra Modules/)
- ❌ Pular validação pré-condições
- ❌ Contar `../` no olho nos links de body — rode `charter-refs.mjs --fix` (§4b)

## Critério de validação ROI

A cada draft aprovado, atualizar `metrics:` no frontmatter:
- `charters_drafted` += 1
- `drafts_aprovados_total` += 1 (se Wagner aprovou)
- `paginas_cobertas` adicionar `{rota}`

ROI medido em F4 — M3 charter coverage Tier A.

## Referências

- [ADR 0101](../../../memory/decisions/0101-sistema-charter-capterra-governanca-escopo.md) — Sistema Charter-Capterra
- [Sprint S6 F2](../../../memory/sprints/s6-charter-capterra/README.md) — esta skill nasce em F2
- Charter exemplo: [Repair/Dashboard](../../../resources/js/Pages/Repair/Dashboard/Index.charter.md)
