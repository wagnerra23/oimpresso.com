#!/usr/bin/env node
// criar-tela.mjs — GERADOR de tela que NASCE do Padrão de Tela (Constituição UI v2 · UI-0013).
//
// =====================================================================================
// POR QUE EXISTE
// =====================================================================================
// Wagner 2026-07-11: "fazer na mão é sorteio e não garante funcionamento". Tela feita à mão
// nasce inconsistente (cada dev inventa a estrutura) e MUITAS vezes fora do padrão — o gate
// pt-conformance depois reprova, e o ciclo-completo aponta o que faltou. Este gerador inverte
// a lógica: em vez de fazer-e-torcer, a tela é CARIMBADA a partir do golden do PT-0X escolhido.
// O conjunto obrigatório nasce COMPLETO e o .tsx PASSA no pt-conformance POR CONSTRUÇÃO (a
// assinatura vem da MESMA lib/pt-signatures.mjs que o gate consome — sem drift).
//
// Dado `(Mod/Tela, PT-0X)`, carimba o conjunto do ciclo:
//   (a) <Tela>.tsx          esqueleto do arquétipo, já importando os componentes canônicos
//   (b) <Tela>.charter.md   component + related_prototype "herda PT-0X" + Mission/Goals/Non-Goals
//   (c) <Tela>.casos.md     stub de UC (o contrato de teste · ADR 0264 G-1/G-2)
//   (d) stub de teste E2E   e2e/<mod>-<tela>.spec.ts citando o UC (satisfaz G-2 rastreabilidade)
//
// Arquétipos (assinatura mínima carimbada, verificada por pt-conformance):
//   PT-01 Lista      → DataTable + PageHeader + filtros
//   PT-02 Form/Drawer→ useForm + FormSection + FormGrid
//   PT-03 Detalhe    → seções detalhe + FsmActionPanel
//   PT-04 Dashboard  → KpiGrid + KpiCard
//   PT-05 Kanban     → KanbanDndProvider/BoardColumn (dnd-kit)
//
// Uso:
//   node scripts/governance/criar-tela.mjs <Mod/Tela> <PT-0X> [--force] [--out <root>]
//   node scripts/governance/criar-tela.mjs Financeiro/Conciliacao PT-01
//   node scripts/governance/criar-tela.mjs --selftest    # fixtures herméticas (carimbo → pt-conformance)
//
// Contrato: UI-0013 (herança de Padrão de Tela) · ADR 0264 (trio-de-tela) · pt-conformance.mjs.

import { readFileSync, writeFileSync, existsSync, mkdirSync } from 'node:fs';
import { join, dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { detectSignals, REQUIRED } from './lib/pt-signatures.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '..', '..');
const HOJE = '2026-07-11'; // sem Date.now() (determinismo/resume); bumpe ao reusar o gerador.

const PT_META = {
  'PT-01': { nome: 'Lista', arquetipo: 'DataTable + PageHeader + filtros' },
  'PT-02': { nome: 'Form/Drawer', arquetipo: 'useForm + FormSection + FormGrid' },
  'PT-03': { nome: 'Detalhe', arquetipo: 'seções detalhe + FsmActionPanel' },
  'PT-04': { nome: 'Dashboard', arquetipo: 'KpiGrid + KpiCard' },
  'PT-05': { nome: 'Kanban', arquetipo: 'KanbanDndProvider/BoardColumn (dnd-kit)' },
};

// ─────────────────────────────────────────────────────────────────────────────
// slugs / helpers de nome
// ─────────────────────────────────────────────────────────────────────────────
const kebab = (s) => s.replace(/([a-z0-9])([A-Z])/g, '$1-$2').replace(/[_\s]+/g, '-').toLowerCase();
// UC prefix: letras do nome da Tela, MAIÚSCULO, ≤6 (bate com ucHeadRe {0,6}-? do casos-guard).
const ucPrefix = (tela) => (tela.replace(/[^A-Za-z]/g, '').toUpperCase().slice(0, 6) || 'TELA');

// ─────────────────────────────────────────────────────────────────────────────
// TEMPLATES do .tsx por arquétipo — cada um carimba a ASSINATURA do PT (pt-signatures).
// Esqueleto real (importa componentes canônicos) + marcadores {/* TODO */} pro dev preencher.
// ─────────────────────────────────────────────────────────────────────────────
function tsxTemplate(pt, mod, tela) {
  const head = `// ${mod}/${tela} — carimbado do ${pt} ${PT_META[pt].nome} por criar-tela.mjs (UI-0013).\n// Herda o Padrão de Tela: NÃO reinvente a estrutura — preencha os {/* TODO */}.\n`;
  const bodies = {
    'PT-01': `import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import DataTable from '@/Components/shared/DataTable';
import type { ColumnDef } from '@tanstack/react-table';

interface Row { id: number /* TODO: campos da linha */ }
interface Props { paginator: { data: Row[] } & Record<string, unknown> /* Inertia paginator */ }

export default function ${tela}({ paginator }: Props) {
  // TODO: defina as colunas reais da lista.
  const columns: ColumnDef<Row>[] = [
    { accessorKey: 'id', header: 'ID' },
  ];
  return (
    <AppShellV2>
      <PageHeader title="${tela}" description="TODO: descrição da lista" />
      {/* TODO: filtros da lista (SellsDateFilter / busca / status) acima da tabela */}
      <DataTable columns={columns} data={paginator.data} pagination={paginator as never} />
    </AppShellV2>
  );
}
`,
    'PT-02': `import AppShellV2 from '@/Layouts/AppShellV2';
import { useForm } from '@inertiajs/react';
import { FormSection, FormGrid } from '@/Components/ui/form-section';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';

interface Props { /* TODO: modelo em edição (Edit) ou vazio (Create) */ }

export default function ${tela}(_props: Props) {
  const form = useForm({ /* TODO: campos do formulário */ nome: '' });
  const { data, setData, processing, errors } = form;

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    form.post('/TODO-rota'); // TODO: rota do submit (parametrize por prop se reusar Create+Edit)
  }

  return (
    <AppShellV2>
      <form onSubmit={handleSubmit} className="cw-form-layout">
        <div>
          <FormSection title="Identificação">
            <FormGrid>
              <Input
                value={data.nome}
                onChange={(e) => setData('nome', e.target.value)}
                placeholder="TODO"
              />
              {errors.nome && <p role="alert">{errors.nome}</p>}
            </FormGrid>
          </FormSection>
          <div className="flex justify-end gap-2">
            <Button type="submit" disabled={processing}>
              {processing ? 'Salvando…' : 'Salvar'}
            </Button>
          </div>
        </div>
        {/* TODO: rail de contexto sticky (preview + prontidão) — ver golden Cliente/Create (PT-02) */}
      </form>
    </AppShellV2>
  );
}
`,
    'PT-03': `import AppShellV2 from '@/Layouts/AppShellV2';
import FsmActionPanel from './_components/FsmActionPanel'; // TODO: crie/reuse o painel de ação FSM da tela

interface Props { registro: Record<string, unknown> /* TODO: entidade em detalhe */ }

export default function ${tela}({ registro }: Props) {
  return (
    <AppShellV2>
      <div className="grid gap-4 lg:grid-cols-[1fr_320px]">
        <div>
          {/* TODO: seções de detalhe (dados + Histórico/Timeline auditável) */}
          <dl className="grid grid-cols-2 gap-2">
            <dt>Campo</dt>
            <dd>{String(registro?.id ?? '—')}</dd>
          </dl>
        </div>
        {/* Painel de próxima ação (FSM/RBAC) — o que distingue Detalhe (PT-03) de Dashboard */}
        <FsmActionPanel /* TODO: subject, actions, user */ />
      </div>
    </AppShellV2>
  );
}
`,
    'PT-04': `import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';

interface Props { kpis?: Record<string, number> /* TODO: agregados do dashboard */ }

export default function ${tela}({ kpis }: Props) {
  return (
    <AppShellV2>
      <PageHeader title="${tela}" description="TODO: descrição do painel" />
      <KpiGrid cols={4}>
        {/* TODO: KPIs reais do módulo */}
        <KpiCard label="TODO" value={kpis?.total ?? 0} />
      </KpiGrid>
      {/* TODO: gráficos/tabelas de apoio abaixo dos KPIs */}
    </AppShellV2>
  );
}
`,
    'PT-05': `import AppShellV2 from '@/Layouts/AppShellV2';
import KanbanDndProvider from './_components/KanbanDndProvider'; // TODO: crie/reuse o provider dnd-kit da tela
import BoardColumn from '@/Components/board/BoardColumn';

interface Card { id: number /* TODO */ }
interface Props { colunas: { key: string; titulo: string; cards: Card[] }[] }

export default function ${tela}({ colunas }: Props) {
  return (
    <AppShellV2>
      {/* Kanban com drag-and-drop (dnd-kit) — o que distingue o PT-05 */}
      <KanbanDndProvider /* TODO: onDragEnd que persiste a transição via FSM */>
        <div className="flex gap-4 overflow-x-auto">
          {colunas.map((c) => (
            <BoardColumn key={c.key} /* TODO: header + cards arrastáveis */>
              {c.titulo}
            </BoardColumn>
          ))}
        </div>
      </KanbanDndProvider>
    </AppShellV2>
  );
}
`,
  };
  return head + bodies[pt];
}

// ─────────────────────────────────────────────────────────────────────────────
// TEMPLATE do charter (herda o PT — related_prototype "n/a (herda PT-0X…)")
// ─────────────────────────────────────────────────────────────────────────────
function charterTemplate(pt, mod, tela, componentRel) {
  return `---
page: /TODO-rota
component: ${componentRel}
owner: wagner
status: draft
last_validated: "${HOJE}"
parent_module: ${mod}
related_prototype: n/a (herda ${pt} ${PT_META[pt].nome}; segue o Padrão de Tela)
tier: B
charter_version: 1
---

# Page Charter — ${mod}/${tela} (DRAFT · carimbado do ${pt})

> Nascida do Padrão de Tela **${pt} ${PT_META[pt].nome}** via \`criar-tela.mjs\` (UI-0013 — herança
> de padrão, NÃO bespoke). Golden do arquétipo: [${pt}](../../../../memory/requisitos/_DesignSystem/padroes-tela/${ptFile(pt)}).
> Preencha os TODO antes de subir de \`draft\` → \`live\` (exige screenshot aprovado por Wagner).

## Mission

TODO: o que esta tela resolve pro cliente (1 frase). Herda a estrutura do ${pt}; o módulo
configura o conteúdo, não a estrutura.

## Goals — Features (faz)

- TODO: capacidade 1
- TODO: capacidade 2
- PT-BR em todo label/placeholder/mensagem

## Non-Goals — Features (NÃO faz)

- TODO: ❌ o que esta tela explicitamente NÃO faz

## UX Targets

- Cabe em 1280px sem scroll horizontal (monitor da Larissa/ROTA LIVRE)
- TODO: metas de p95 se aplicável

## Refs

- Padrão de Tela: ${pt} ${PT_META[pt].nome} (${PT_META[pt].arquetipo})
- Constituição UI v2: UI-0013
`;
}

function ptFile(pt) {
  return {
    'PT-01': 'PT-01-Lista.md', 'PT-02': 'PT-02-Form-Drawer.md', 'PT-03': 'PT-03-Detalhe.md',
    'PT-04': 'PT-04-Dashboard.md', 'PT-05': 'PT-05-Kanban.md',
  }[pt];
}

// ─────────────────────────────────────────────────────────────────────────────
// TEMPLATE do casos.md (contrato de teste · ADR 0264 G-1/G-5). Status ⬜ = não-afirmação
// honesta (G-7 só cobra prova de ✅). O UC é citado pelo stub de teste (satisfaz G-2).
// ─────────────────────────────────────────────────────────────────────────────
function casosTemplate(mod, tela) {
  const uc = `UC-${ucPrefix(tela)}-01`;
  return `---
casos: ${mod}/${tela} — carimbado do Padrão de Tela
irmaos: ${tela}.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "${HOJE}"
---

# Casos de Uso & Aceite — ${mod}/${tela}

> Nascido de \`criar-tela.mjs\`. **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão. O stub \`e2e/${kebab(mod)}-${kebab(tela)}.spec.ts\` já cita \`${uc}\`.

---

## ${uc} · TODO: o caminho feliz da tela
- **Persona:** Larissa (ROTA LIVRE) — TODO: o que ela quer fazer nesta tela.
- **Aceite:** Dado TODO · Quando TODO · Então TODO (resultado verificável).
- **Teste:** \`e2e/${kebab(mod)}-${kebab(tela)}.spec.ts\` — stub \`test.fixme\` citando \`${uc}\` (troque por asserção real).
- **Regressão que defende:** TODO — o que não pode voltar a quebrar.
- **Status: ⬜** — stub; vira 🧪/✅ quando o teste executar e passar.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** TODO: próximo caso.

## Trilha do tempo
- ${HOJE} · [CC] carimbado por criar-tela.mjs — trio nascido junto (charter + casos + teste). Refs: UI-0013 · ADR 0264 G-1/G-2.
`;
}

// ─────────────────────────────────────────────────────────────────────────────
// TEMPLATE do stub de teste E2E (Playwright). test.fixme = pendente (não roda/não quebra CI).
// Cita o UC-id no título → casos-guard G-2 encontra a rastreabilidade caso↔teste.
// ─────────────────────────────────────────────────────────────────────────────
function testeTemplate(mod, tela) {
  const uc = `UC-${ucPrefix(tela)}-01`;
  return `import { test, expect } from '@playwright/test';

// Stub E2E carimbado por criar-tela.mjs — contrato em resources/js/Pages/${mod}/${tela}.casos.md.
// test.fixme = PENDENTE (não executa, não quebra o CI). Troque por asserção real de comportamento
// quando a tela ${mod}/${tela} estiver implementada. Locators RESILIENTES (role/label/text), nunca
// classe CSS (L-24). NÃO edite a tela viva sem charter + gate visual.

test.fixme('${uc}: TODO caminho feliz de ${mod}/${tela}', async ({ page }) => {
  await page.goto('/TODO-rota');
  await expect(page.getByRole('heading', { name: '${tela}' })).toBeVisible();
  // TODO: Dado/Quando/Então do ${uc}.
});
`;
}

// ─────────────────────────────────────────────────────────────────────────────
// Motor de geração
// ─────────────────────────────────────────────────────────────────────────────
export function renderConjunto(pt, mod, tela) {
  const componentRel = `resources/js/Pages/${mod}/${tela}.tsx`;
  return {
    tsx: tsxTemplate(pt, mod, tela),
    charter: charterTemplate(pt, mod, tela, componentRel),
    casos: casosTemplate(mod, tela),
    teste: testeTemplate(mod, tela),
  };
}

function planPaths(mod, tela, outRoot) {
  const base = join(outRoot, 'resources', 'js', 'Pages', mod);
  return {
    tsx: join(base, `${tela}.tsx`),
    charter: join(base, `${tela}.charter.md`),
    casos: join(base, `${tela}.casos.md`),
    teste: join(outRoot, 'e2e', `${kebab(mod)}-${kebab(tela)}.spec.ts`),
  };
}

function gerar({ mod, tela, pt, force, outRoot }) {
  const paths = planPaths(mod, tela, outRoot);
  const conj = renderConjunto(pt, mod, tela);
  const existentes = Object.values(paths).filter((p) => existsSync(p));
  if (existentes.length && !force) {
    console.error(`❌ Já existe(m) (use --force pra sobrescrever):`);
    for (const p of existentes) console.error(`   ${relOut(p, outRoot)}`);
    process.exit(1);
  }
  const writes = [
    [paths.tsx, conj.tsx], [paths.charter, conj.charter],
    [paths.casos, conj.casos], [paths.teste, conj.teste],
  ];
  for (const [p, content] of writes) {
    mkdirSync(dirname(p), { recursive: true });
    writeFileSync(p, content);
  }
  return paths;
}

const relOut = (p, outRoot) => p.replace(outRoot + '/', '').replace(outRoot + '\\', '').replace(/\\/g, '/');

// ─────────────────────────────────────────────────────────────────────────────
// SELFTEST — carimba os 5 arquétipos e prova que cada .tsx PASSA no pt-conformance
// POR CONSTRUÇÃO (mesma lib pt-signatures que o gate usa). Anti-fantasma (ADR 0256).
// ─────────────────────────────────────────────────────────────────────────────
if (process.argv.includes('--selftest')) {
  let fails = 0;
  const t = (c, m) => { if (c) console.log(`  ✓ ${m}`); else { console.error(`  ✗ ${m}`); fails++; } };
  for (const pt of Object.keys(PT_META)) {
    const conj = renderConjunto(pt, 'Fixtura', 'MinhaTela');
    const sig = detectSignals(conj.tsx);
    t(REQUIRED[pt](sig), `${pt}: tsx carimbado PASSA no pt-conformance (assinatura ${PT_META[pt].nome})`);
    t(/related_prototype:.*PT-0[1-5]/.test(conj.charter), `${pt}: charter declara o Padrão de Tela`);
    t(/status:\s*draft/.test(conj.charter), `${pt}: charter nasce draft (exige screenshot Wagner)`);
    t(/^## UC-[A-Z]+-01/m.test(conj.casos), `${pt}: casos.md tem UC stub (contrato de teste)`);
    const ucMatch = conj.casos.match(/## (UC-[A-Z]+-01)/);
    t(ucMatch && conj.teste.includes(ucMatch[1]), `${pt}: stub de teste cita o UC (G-2 rastreabilidade)`);
    t(/test\.fixme/.test(conj.teste), `${pt}: stub de teste é fixme (não quebra CI)`);
  }
  // cross-check: PT-02 NÃO deve passar como se fosse PT-05 (assinaturas distintas)
  const pt02 = detectSignals(renderConjunto('PT-02', 'X', 'Y').tsx);
  t(!REQUIRED['PT-05'](pt02), 'PT-02 carimbado NÃO satisfaz assinatura de PT-05 (arquétipos distintos)');
  console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — todo arquétipo nasce conforme ao seu PT.');
  process.exit(fails ? 1 : 0);
}

// ─────────────────────────────────────────────────────────────────────────────
// CLI
// ─────────────────────────────────────────────────────────────────────────────
const args = process.argv.slice(2).filter((a) => a !== '--force');
const force = process.argv.includes('--force');
const outIdx = process.argv.indexOf('--out');
const outRoot = outIdx >= 0 ? resolve(process.argv[outIdx + 1]) : ROOT;
const positional = args.filter((a) => !a.startsWith('--') && a !== outRoot && a !== process.argv[outIdx + 1]);

const alvo = positional[0];
const pt = (positional[1] || '').toUpperCase();

if (!alvo || !pt) {
  console.error('Uso: node scripts/governance/criar-tela.mjs <Mod/Tela> <PT-0X> [--force] [--out <root>]');
  console.error('Ex:  node scripts/governance/criar-tela.mjs Financeiro/Conciliacao PT-01');
  console.error('PTs: ' + Object.entries(PT_META).map(([k, v]) => `${k} ${v.nome}`).join(' · '));
  process.exit(1);
}
if (!/^[A-Za-z][\w]*\/[A-Za-z][\w]*$/.test(alvo)) {
  console.error(`❌ Alvo inválido "${alvo}" — use <Mod>/<Tela> em PascalCase (ex: Financeiro/Conciliacao).`);
  process.exit(1);
}
if (!PT_META[pt]) {
  console.error(`❌ Padrão de Tela inválido "${pt}" — use um de: ${Object.keys(PT_META).join(', ')}.`);
  process.exit(1);
}

const [mod, tela] = alvo.split('/');
const paths = gerar({ mod, tela, pt, force, outRoot });

// Aviso GOLDEN-LIVE: se o golden do PT ainda é draft, a tela não FECHA o ciclo (ciclo-completo
// cobra golden live). Não bloqueia a geração — só avisa (o lado Design precisa terminar o golden).
let goldenStatus = 'desconhecido';
try {
  const g = readFileSync(join(ROOT, 'memory/requisitos/_DesignSystem/padroes-tela', ptFile(pt)), 'utf8');
  goldenStatus = (g.match(/^status:\s*(\w+)/mi) || [])[1] || 'desconhecido';
} catch { /* ignore */ }

console.log(`✅ Tela carimbada do ${pt} ${PT_META[pt].nome} — conjunto do ciclo nasceu completo:`);
for (const k of ['tsx', 'charter', 'casos', 'teste']) console.log(`   • ${relOut(paths[k], outRoot)}`);
console.log(`\nPróximos passos:`);
console.log(`   1. Preencha os {/* TODO */} do .tsx (o arquétipo já passa no pt-conformance).`);
console.log(`   2. Complete Mission/Goals/Non-Goals no charter + a rota (page:).`);
console.log(`   3. Escreva o UC real no casos.md + troque o test.fixme por asserção.`);
console.log(`   4. Wagner aprova o screenshot → charter sai de draft → live.`);
if (goldenStatus !== 'live') {
  console.log(`\n⚠️  O golden do ${pt} está "${goldenStatus}" (não live): esta tela NÃO fecha o ciclo-completo`);
  console.log(`   até o Design terminar o golden do ${pt} (GOLDEN-LIVE enforcement).`);
}
