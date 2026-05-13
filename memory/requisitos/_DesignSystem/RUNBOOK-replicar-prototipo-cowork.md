---
slug: runbook-replicar-prototipo-cowork
title: "RUNBOOK — Replicar protótipo Cowork pra Inertia React (caso Kanban Caçambas validado 2026-05-13)"
type: runbook
status: live
authority: canonical
module: _DesignSystem
created: 2026-05-13
related_skill: cowork-prototype-replication
related_adrs: [0114, 0107, 0104, 0093, 0143]
caso_real: PRs #735→#740 — Kanban Producao Oficina Caçambas pré-Martinho
---

# RUNBOOK — Replicar protótipo Cowork pra Inertia React

> Receita reproduzível pra transformar `prototipo-ui/prototipos/<tela>/visual-source.html` (Cowork canon) em página Inertia React funcional + bonita + auditada.
>
> **Caso real validado:** Wagner pediu 2026-05-13 madrugada (5h antes reunião Martinho 10h) — "use skill" + "as informações e o modelo são muito superiores" + "pode fazer drag drop". Resultado: 6 PRs (#735→#740) com Kanban Caçambas pixel-perfect canon Cowork. Esta receita documenta o caminho.

## Pré-requisitos

- [ ] Protótipo Cowork existe em `prototipo-ui/prototipos/<tela>/visual-source.html` (E opcionalmente `F1.html` simplificado + `cowork-app.jsx`)
- [ ] Vertical alvo definida (Vestuario/ComunicacaoVisual/OficinaAuto/etc) — ler `memory/reference_dominios_verticais_oimpresso.md`
- [ ] Módulo destino existe (8 peças canônicas via skill `criar-modulo`) com topnav + DataController + InstallController
- [ ] Endpoint backend disponível (Controller + Inertia render) ou capacidade de criar 1 controller novo

## F0 — Sync loop Cowork (5min)

```bash
# 1. Read estado vivo do loop
cat prototipo-ui/HANDOFF.md  # Identifica tela em qual fase

# 2. Confirma protótipo alvo existe
ls prototipo-ui/prototipos/<tela-kebab>/
# Espera: F1.html (draft) + visual-source.html (canon Cowork) + cowork-app.jsx (componentes)
```

Se protótipo NÃO existe → **STOP**. Não tem fonte canônica → use `mwart-comparative` Tier A direto (gera visual-comparison sem fonte canon).

## F1 — Mapping vocabulário vertical (10min) ⭐ CRÍTICO

### Sub-passo 1: Identificar vertical alvo

Ler `memory/reference_dominios_verticais_oimpresso.md` pra confirmar:
- Cliente piloto (ex: Martinho — caçambas avulsas locação)
- CNAE (ex: 4581-4/00)
- Unidades primárias (ex: m³ pra caçamba, NÃO m² gráfica)
- Sub-vertical específica (ex: OficinaAuto.locacao_cacamba)

### Sub-passo 2: Construir tabela mapping vocabulário

Exemplo Kanban Producao Oficina (visual-source.html era pra MECÂNICA carros — adaptamos pra CAÇAMBAS):

| Termo canon (mecânica) | Vocabulário vertical alvo (caçamba locação) |
|---|---|
| "Honda Civic 2019" | "Caçamba CC-001 5m³" |
| "84.220 km · Marcos Aleixo" | "Construtora Aliança · Rod. BR-101 km 142" |
| "Box B1 / Elevador E1" | "Capacidade 3m³ / 5m³ / 7m³" |
| "Recepção / Diagnóstico / Aguardando peças / Em execução / Pronto retirar" | "Disponível / Locada / Aguardando recolhimento / Em manutenção / Pronta entrega" |
| "Mecânico Pedro Souza (PS)" | "Atendente Wagner (WR)" |
| "Sintoma: Barulho rodas" | "Observação: Demo Martinho — auto-gerado" (rental_notes) |
| "Triagem → / Cobrar OK / Iniciar →" | "Iniciar locação → / Recolher → / Concluir → / Entregar →" |
| "Encomendado · Disco BR-2188 chega 09/05" | "Atrasada · cobrar cliente" (banner rose) |

⚠️ **NUNCA** confundir vocabulário entre verticais:
- m³ = caçamba (volume 3D pra entulho)
- m² = gráfica/comunicação visual (área 2D banner)
- pneu = recapagem (Vargas)
- peça = vestuário (Larissa)

### Sub-passo 3: Definir KPIs vertical

Canon mostra 6 KPIs (Recepção/Diagnóstico/Peças/Execução/URGENTES rose/Valor em curso emerald). Adaptar:

```
TOTAL          LOCADAS         AGUARDANDO       EM MANUTENÇÃO    ATRASADAS         VALOR EM CURSO
8 caçambas     4               1                1                1                 R$ 2.250
no estoque     em campo        recolhimento     oficina          prazo crítico     faturamento previsto
              (cinza)         (amber bg)       (cinza)          (rose bg)         (emerald bg)
```

## F2 — Mapping CSS Cowork → Tailwind (10min)

### Sub-passo 1: Read FULL visual-source.html

```bash
wc -l prototipo-ui/prototipos/<tela>/visual-source.html
# Esperar 800-1500 linhas. Read em 2-3 chunks de 400 linhas.
```

### Sub-passo 2: Extrair classes CSS canônicas

Procurar padrões `prod-*`, `ofc-*`, `cv-*`, `kanban-*`. Documentar cada classe + estilo.

### Sub-passo 3: Tabela equivalência Cowork → Tailwind 4

| Cowork CSS | Tailwind 4 equivalente | Notas |
|---|---|---|
| `oklch(0.45 0.13 250)` | `bg-blue-700` (aprox) | OU inline style se cor exata necessária |
| `oklch(0.62 0.16 20)` | `bg-rose-500` | dot urgente |
| `oklch(0.58 0.13 155)` | `bg-emerald-500` | dot pronto |
| `font-family: ui-monospace` | `font-mono` OU `style={{fontFamily: 'ui-monospace, "Cascadia Code", Menlo, monospace'}}` | usar style se quer fontes específicas Mercosul |
| `.prod-col-{slate,blue,rose,violet,emerald}` border-top 2px | `border-t-2 border-{color}-400` | dot 2x2 redondo lado |
| `.ofc-veh-row` flex layout | `flex items-center gap-2` linha placa+título+cliente | |
| `.ofc-symptom` text-base text-slate-700 | `text-[12px] text-slate-700 leading-snug` | NÃO italic line-clamp-2 (apaga demais) |
| `.ofc-eta-row` flex-between | `flex justify-between text-[11px]` | |
| `.ofc-mech-av` 18px circle iniciais | `w-[18px] h-[18px] rounded-full bg-slate-200 text-[10px] font-semibold flex items-center justify-center` | |
| `.prod-progress` h-1 + bar interna | `h-1 bg-blue-200 rounded` + `<div className="h-full bg-blue-500 rounded" style={{width: \`${pct}%\`}}/>` | |
| `.ofc-parts.warn/.ok/.await` | banner inline `bg-{rose,emerald,amber}-50 border border-{color}-200 px-2 py-1 rounded text-[11px]` | |
| `tokens custom (ink-50/900)` | usar `slate-50/900` nativos Tailwind 4 | NÃO duplicar palette |

### Sub-passo 4: Identificar elementos visuais especiais

- **Placa Mercosul** (`.ofc-plate`) → component dedicado `MercosulPlate.tsx` (ver caso real PR #736)
- **Avatar mecânico/atendente** → derivado iniciais ou `Components/ui/avatar.tsx` shadcn
- **Banners status** → component inline (3 variants rose/violet/emerald)

## F3 — Component hierarchy (15min)

### Estrutura típica

```
resources/js/Pages/<Mod>/<Tela>/
├── Index.tsx                      # Page principal
└── _components/
    ├── <Item>Card.tsx             # Card individual (memo + useDraggable se DnD)
    ├── <Item>KanbanColumn.tsx     # Coluna (memo + useDroppable se DnD)
    ├── <Item>Sheet.tsx            # Drawer rico 5 sections
    ├── <Item>StatusBadge.tsx      # Badges status semantic
    ├── <Plate>.tsx                # Visual canônico (ex: MercosulPlate)
    ├── KanbanDndProvider.tsx      # Se DnD: DndContext + sensors
    └── DragConfirmDialog.tsx      # Se DnD: AlertDialog confirmação
```

### Components NEW vs reusar (existing)

Antes de criar component novo, **VERIFICAR** se já existe:
- `resources/js/Components/ui/*` — shadcn (Sheet, Dialog, AlertDialog, Button, Card, Badge, Input)
- `resources/js/Components/shared/*` — KpiCard, KpiGrid, PageHeader, EmptyState (Cockpit V2 ADR 0110)
- Pages siblings — pode importar drawer/sheet de outro módulo se justificado

## F4 — useMemo/useCallback descendentes (5min) ⭐ CRÍTICO

### Lição PR #717 (re-render loop fix)

Combinação **TanStack/dnd-kit/sortable + handlers descendentes** sem refs estáveis = **loop infinito** quando user clica filtro/dropdown/drag.

### Padrões obrigatórios

```tsx
// ❌ ERRADO — handler inline cria nova ref a cada render
<Card onClick={(id) => router.visit(`/x/${id}`)} />

// ✅ CERTO — useCallback estabiliza ref
const handleClick = useCallback((id: number) => {
  router.visit(`/x/${id}`);
}, []);
<Card onClick={handleClick} />

// ❌ ERRADO — array literal nova ref a cada render
const grouping: GroupingState = column ? [column] : [];

// ✅ CERTO — useMemo
const grouping = useMemo<GroupingState>(
  () => (column ? [column] : []),
  [column]
);

// ❌ ERRADO — state object literal
state: { grouping, expanded }

// ✅ CERTO — useMemo wrapper
const tableState = useMemo(() => ({ grouping, expanded }), [grouping, expanded]);
state: tableState

// ✅ memo() em components que recebem callbacks
export default memo(CacambaCardImpl);
```

### Anti-pattern detector

```bash
# Pre-merge check — grep handlers inline em hierarquia profunda
grep -rE "onClick={[^}]*=>\s*(router|setOpen|setSelected)" resources/js/Pages/<Mod>/<Tela>/
# Se > 5 matches em arquivo, considerar useCallback
```

## F5 — Pest estrutural (10min)

### Template canônico

```php
<?php

declare(strict_types=1);

use App\Models\User;

const BIZ_WAGNER = 1;
const BIZ_CROSS_TENANT = 99;  // NUNCA biz=4 (cliente real ROTA LIVRE — ADR 0101)

beforeEach(function () {
    if (env('DB_CONNECTION') === 'sqlite' && ! env('USE_REAL_MYSQL')) {
        $this->markTestSkipped('Schema MySQL específico — pula em SQLite memory');
    }
    if (! \Schema::hasColumn('vehicles', 'current_status')) {
        $this->markTestSkipped('Wave 5-A migration ausente — schema OK só local Pest');
    }
    $admin = User::where('business_id', BIZ_WAGNER)->first();
    if (! $admin) {
        $this->markTestSkipped('User biz=1 admin ausente — defensive');
    }
    $this->actingAs($admin);
});

it('payload V2 enriquecido tem todos campos canon', function () {
    // ... assertions
});

it('cross-tenant biz=99 NÃO vê dados biz=1 (Tier 0 ADR 0093)', function () {
    // ... assertions
});

// Anti-regressão PR #717 — regex useMemo/useCallback presente
it('CacambaCard tem useMemo+useCallback (lição PR #717)', function () {
    $content = file_get_contents(base_path('resources/js/Pages/<Mod>/<Tela>/_components/<Item>Card.tsx'));
    expect($content)->toMatch('/use(Memo|Callback)/');
    expect($content)->toContain('memo(');
});
```

## F6 — Deploy (10min)

### Sequência canônica

```bash
# 1. Branch fresh de origin/main
git fetch origin main --quiet
git checkout -B claude/<mod>-<feature>-rich origin/main

# 2. Stash se necessário (se tinha trabalho anterior na branch)
git stash push -u -m "<feature>"

# 3. Add seletivo (NÃO -A, evita .claude/scheduled_tasks.lock)
git add Modules/<Mod>/Http/Controllers/<Controller>.php \
        resources/js/Pages/<Mod>/<Tela>/Index.tsx \
        resources/js/Pages/<Mod>/<Tela>/_components/ \
        tests/Feature/Modules/<Mod>/<Tela>RichUITest.php

# 4. Commit (HEREDOC pra preservar formatação)
git commit -m "$(cat <<'EOF'
feat(<mod>): <feature> rica espelhando canon Cowork visual-source.html

[descrição detalhada]

Refs: ADR-0114 visual-source.html-canon-NNNNL PR-#NNN-anterior demo-X

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"

# 5. Push + abrir PR via REST (rate limit GraphQL friendly)
git push -u origin claude/<mod>-<feature>-rich
PR=$(gh api -X POST repos/wagnerra23/oimpresso.com/pulls \
  -f base=main \
  -f head=claude/<mod>-<feature>-rich \
  -f title="feat(<mod>): <feature> rica" \
  -f body="..." \
  --jq '.number')

# 6. Admin merge (Wagner=owner)
gh api -X PUT "repos/wagnerra23/oimpresso.com/pulls/$PR/merge" -f merge_method=squash

# 7. SSH Hostinger pull + clear
ssh -4 -o ConnectTimeout=900 -o ServerAliveInterval=3 -i ~/.ssh/id_ed25519_oimpresso \
  -p 65002 u906587222@148.135.133.115 \
  'cd domains/oimpresso.com/public_html && git pull --quiet && php artisan optimize:clear'
```

### Se package.json mudou (deps NPM novas)

⚠️ **CRÍTICO:** package-lock.json precisa estar atualizado ANTES do push, senão `npm ci` em prod falha.

```powershell
# Local Windows com Node 24 + npm 11
npm install --no-audit --no-fund

# Verifica package-lock.json modificado
git status -s package-lock.json

# Commit + push em PR separada (limpeza)
git add package-lock.json
git commit -m "chore(deps): regenerate package-lock"
git push
```

Quick-sync.yml detecta mudança em package-lock.json + roda `npm ci` + `npm run build:inertia` automático em Hostinger (Node 24 via nvm).

## F7 — Smoke INTERATIVO (10min) ⭐ CRÍTICO

### Por que é diferente de smoke renderizar

Pest estrutural + CI Vite passa MAS feature pode quebrar em runtime React 19. Lição PR #717: bug de re-render loop só apareceu quando user clicou filtro/dropdown.

### Workflow obrigatório

```javascript
// 1. Tab fresh (limpa estado prévio)
mcp__Claude_in_Chrome__tabs_create_mcp

// 2. Navegar
navigate("https://oimpresso.com/<mod>/<tela>", tabId)
wait(6, tabId)
screenshot(tabId)  // ← captura estado inicial

// 3. INTERAGIR (clicar filtros, mudar dropdowns, drag-drop, abrir drawer)
find("dropdown Agrupar", tabId) // → ref
left_click(ref, tabId)
screenshot(tabId)  // ← captura dropdown aberto

find("opção Status pagamento", tabId)
left_click(ref, tabId)
wait(2, tabId)
screenshot(tabId)  // ← captura re-agrupamento sem travar

// 4. Verificar console errors
read_console_messages(tabId, pattern: "error|Error|warning", limit: 20)

// 5. Se travou ou erro: identificar root cause + fix forward (NÃO revert prematuro — lição PR #712)
```

### Anti-padrões smoke

- ❌ **Smoke só renderizar** sem interagir — pega 50% dos bugs apenas
- ❌ **Revert imediato** quando user reporta "travou" — primeiro pedir hard reload + incognito + 2ª máquina (lição PR #712 falso alarme)
- ❌ **Ignorar console warnings** — alguns indicam bug latente

## Caso real validado (madrugada 2026-05-13)

| Fase | Tempo real | Output |
|---|---|---|
| F0 sync | 3min | Identificou F1.html + visual-source.html (1213L) + cowork-app.jsx |
| F1 mapping | 8min | Tabela 12 termos mecânica → caçamba (incluindo m³ = caçamba ≠ m² gráfica) |
| F2 mapping CSS | 12min | Extraiu .ofc-plate, .prod-col-*, .ofc-symptom etc → Tailwind nativo |
| F3 hierarchy | 18min | Identificou: MercosulPlate NEW + CacambaCard rewrite + CacambaProducaoSheet NEW + reuso ServiceOrderFsmActionPanel (PR #729) |
| F4 useMemo/useCallback | aplicado em todos componentes | 0 re-render loops |
| F5 Pest | 7 specs entre PRs | markTestSkipped defensivo cobre SQLite + colunas FSM ausentes |
| F6 deploy | 5min/PR | 6 PRs em 5h via gh CLI REST + SSH automation |
| F7 smoke | 4 rounds (V1, V2, V3, DnD) | Wagner aprovou visual em cada round |

**Total:** Kanban Producao Oficina LIVE em prod biz=1 com 8 caçambas reais + 5 OS + drag-drop FSM funcional, ANTES da reunião 10h. Wagner: "as informações e o modelo são muito superiores".

## Refs

- [Skill `cowork-prototype-replication`](.claude/skills/cowork-prototype-replication/SKILL.md) — orquestração desta receita
- [Skill `mwart-comparative` Tier A](.claude/skills/mwart-comparative/SKILL.md) — pai arquitetural (loop Cowork formalizado)
- [ADR 0114 — Loop Cowork ↔ Claude Code formalizado](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- [ADR 0107 — Visual gate F3](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- [ADR 0143 — FSM Pipeline LIVE](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
- [ADR 0093 — Multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [reference_dominios_verticais_oimpresso.md (auto-mem)] — vocabulário vertical
- [Caso real Kanban Caçambas](producao-oficina-cacamba-visual-comparison.md) — visual-comparison artifact PRs #735→#740
