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
//   (e) .contract.json      contrato de tela em prototipo-ui/contrato/ — a perna de FIDELIDADE
//                           VISUAL do trio (contract.schema.json). Nasce com as seções do
//                           arquétipo + as âncoras `data-contract` correspondentes já no .tsx,
//                           e `copy` VAZIA (a copy literal é decisão [W] — ver contratoTemplate).
//                           Consumido pelo `contrato:check` que já existe; nenhum gate novo.
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

import { readFileSync, writeFileSync, existsSync, mkdirSync, readdirSync } from 'node:fs';
import { join, dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { detectSignals, REQUIRED } from './lib/pt-signatures.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '..', '..');

// Data de GERAÇÃO em BRT (o fuso do time e o mesmo do `git log %cs` das máquinas daqui).
// NÃO é "quando o teste rodou": é quando o trio foi carimbado — que é exatamente o evento
// que o G-6 do casos-gate compara contra a data de commit do .tsx (scripts/casos-coverage-guard.mjs).
// Até 2026-08-24 isto era `const HOJE = '2026-07-11'` fixo, nunca bumpado desde #4111: todo
// trio nascia com `last_run` 44+ dias no passado e, pelo G-6, STALE no PR que o criou.
// O selftest não asserta sobre a data, então a geração dinâmica não o torna instável.
const hojeBRT = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Sao_Paulo' }).format(new Date());

const PT_META = {
  'PT-01': { nome: 'Lista', arquetipo: 'DataTable + PageHeader + filtros' },
  'PT-02': { nome: 'Form/Drawer', arquetipo: 'useForm + FormSection + FormGrid' },
  'PT-03': { nome: 'Detalhe', arquetipo: 'seções detalhe + FsmActionPanel' },
  'PT-04': { nome: 'Dashboard', arquetipo: 'KpiGrid + KpiCard' },
  'PT-05': { nome: 'Kanban', arquetipo: 'KanbanDndProvider/BoardColumn (dnd-kit)' },
};

// ─────────────────────────────────────────────────────────────────────────────
// SEÇÕES DO ARQUÉTIPO — fonte única do 5º artefato (contrato de tela).
//
// Um `id` aqui significa DUAS coisas que TÊM de casar, senão o contrato nasce vermelho:
//   (a) uma âncora `data-contract="<id>"` no .tsx carimbado;
//   (b) uma entrada em `secoes[]` do `.contract.json`.
// Escrever as duas listas à mão faria elas driftarem no primeiro arquétipo alterado. Aqui
// a (b) é DERIVADA desta tabela, a (a) é literal no template (a estrutura JSX de cada PT é
// diferente demais pra gerar genericamente) — e o `--selftest` prova a inclusão nos 5
// arquétipos (`conferirAncorasDoArquetipo`), então o drift vira vermelho em vez de silêncio.
//
// `id` respeita `^[a-z0-9-]+$` do contract.schema.json. Os `_papel` NÃO vão pro JSON: o
// schema declara `additionalProperties: false` dentro de `secoes[]` (medido 2026-08-25 —
// contratos escritos à mão violam isso hoje; o gerado não vai violar).
const PT_SECOES = {
  'PT-01': [
    { id: 'cabecalho', _papel: 'PageHeader — identidade da tela' },
    { id: 'filtros', _papel: 'faixa de filtros/busca acima da tabela' },
    { id: 'lista', _papel: 'a DataTable em si (colunas + paginação)' },
  ],
  'PT-02': [
    { id: 'formulario', _papel: 'as FormSection/FormGrid com os campos' },
    { id: 'acoes', _papel: 'barra de submit/cancelar' },
  ],
  'PT-03': [
    { id: 'detalhe', _papel: 'seções de dados + histórico auditável' },
    { id: 'acoes-fsm', _papel: 'painel de próxima ação (FSM/RBAC) — o que distingue o PT-03' },
  ],
  'PT-04': [
    { id: 'cabecalho', _papel: 'PageHeader — identidade do painel' },
    { id: 'kpis', _papel: 'a KpiGrid com os agregados' },
  ],
  'PT-05': [
    { id: 'quadro', _papel: 'as colunas arrastáveis do board' },
  ],
};

// ─────────────────────────────────────────────────────────────────────────────
// slugs / helpers de nome
// ─────────────────────────────────────────────────────────────────────────────
const kebab = (s) => s.replace(/([a-z0-9])([A-Z])/g, '$1-$2').replace(/[_\s]+/g, '-').toLowerCase();
// UC prefix: letras do nome da Tela, MAIÚSCULO, ≤6 — o teto do prefixo é o `UC_CORE` da
// fonte única scripts/lib/uc-regex.mjs (1 letra + até 5 alfanuméricos). Se um dia mudar,
// muda LÁ: aqui é só o gerador do id, não um 2º dono do formato.
const ucPrefix = (tela) => (tela.replace(/[^A-Za-z]/g, '').toUpperCase().slice(0, 6) || 'TELA');

// ─────────────────────────────────────────────────────────────────────────────
// CODE CONNECT em tempo de GERAÇÃO (degrau `dtc-code-connect-geracao`, memory/reguas —
// a nota mais baixa da dimensão design→código: 6,0, parada desde 2026-07-18).
//
// O import do template deixa de ser string literal e passa a ser DERIVADO do
// component-registry.json — a mesma natureza do Figma Code Connect: a âncora vive no
// COMPONENTE (estável, reusável entre telas), não na cópia dentro de cada template.
// Componente `mapped` que mude de `import_path` passa a chegar sozinho na tela nova.
//
// LIMITE DECLARADO (não é omissão): o registry é **protótipo-first** — a chave dele é
// `bloco_prototipo` (bloco Cowork → React) e ele NÃO cataloga todo componente do repo.
// QUANTOS dos imports dos templates ele cobre HOJE é pergunta pro registry, não pra este
// comentário (§5 2026-07-17 — doc não restateia número que outro sistema sabe melhor):
// o oráculo é `component-registry-check.mjs --check --strict`, e o `--selftest` daqui
// REPORTA a cobertura derivada. Componente sem entrada cai no path declarado abaixo e
// migra sozinho no dia em que ganhar uma.
//
// ⚠️ PEGADINHA MEDIDA (2026-08-24): o `import_path` do registry pode apontar pra um
// BARRIL cuja API pública é NOMEADA — `@/Components/PageHeader` resolve pro `index.ts`,
// que exporta `{ PageHeader }` e NÃO tem `default`. Trocar o path sem trocar a FORMA do
// import emite `TS2613: Module has no default export`. Foi o que aconteceu quando o
// #6210 mapeou o PageHeader canon: o gerador passou a produzir .tsx que não compila.
// Por isso o template usa a forma que o registry declara em `exports`, e o selftest
// confere isso em todos os arquétipos (`conferirFormaDosImports`).
const REGISTRY = join(ROOT, 'prototipo-ui', 'component-registry.json');
let _regCache = null;
/** Entradas `mapped` do registry — uma leitura só, dois índices (por componente e por path). */
function registryMapped() {
  if (_regCache) return _regCache;
  _regCache = { porComponente: new Map(), porPath: new Map() };
  try {
    const j = JSON.parse(readFileSync(REGISTRY, 'utf8'));
    const arr = Array.isArray(j.entries) ? j.entries : Object.values(j.entries || {});
    for (const e of arr) {
      if (e && e.status === 'mapped' && e.componente_react && e.import_path) {
        _regCache.porComponente.set(e.componente_react, e.import_path);
        _regCache.porPath.set(e.import_path, e);
      }
    }
  } catch { /* registry ausente/ilegível → templates seguem com o path declarado (nunca crasha) */ }
  return _regCache;
}

/** import_path do registry (entrada `mapped`) ou o declarado. Exportado pro selftest. */
export function importPath(componente, declarado) {
  return registryMapped().porComponente.get(componente) || declarado;
}

/** Quem está amarrado ao registry vs. quem segue no path declarado — visibilidade honesta. */
export function provenienciaImports(pares) {
  const reg = registryMapped().porComponente;
  return pares.map(([c, d]) => ({ componente: c, declarado: d, registry: reg.get(c) || null, fonte: reg.has(c) ? 'registry' : 'declarado' }));
}

/**
 * A FORMA do import gerado (default × nomeado) bate com os `exports` que o registry
 * DECLARA pra aquele `import_path`? Devolve as divergências (vazio = conforme).
 *
 * Existe porque o path sozinho não descreve a API: barril com `export { X }` não aceita
 * `import X from` (TS2613). Só olha path que o registry mapeia — import de fora dele
 * (`@inertiajs/react`, `./_components/...`) não é assunto daqui.
 *
 * `porPath` é parâmetro pra que o controle da sonda rode a MESMA função da produção com
 * um registry sintético, em vez de uma cópia paralela (§5 2026-08-14).
 */
export function conferirFormaDosImports(tsx, porPath = registryMapped().porPath) {
  const divergencias = [];
  const re = /import\s+(?:(\{[^}]*\})|([A-Za-z_$][\w$]*))\s+from\s+'([^']+)'/g;
  for (const [, chaves, padrao, path] of tsx.matchAll(re)) {
    const e = porPath.get(path);
    if (!e) continue;
    const exports = Array.isArray(e.exports) ? e.exports : [];
    if (padrao && !exports.includes('default')) {
      divergencias.push(`${path}: import default de \`${padrao}\`, mas o registry declara exports=[${exports}] (sem 'default') → TS2613`);
    }
    for (const s of (chaves || '').replace(/[{}]/g, '').split(',').map((x) => x.trim().split(/\s+as\s+/)[0]).filter(Boolean)) {
      if (!exports.includes(s)) divergencias.push(`${path}: import nomeado \`{${s}}\` fora de exports=[${exports}]`);
    }
  }
  return divergencias;
}

// ─────────────────────────────────────────────────────────────────────────────
// TEMPLATES do .tsx por arquétipo — cada um carimba a ASSINATURA do PT (pt-signatures).
// Esqueleto real (importa componentes canônicos) + marcadores {/* TODO */} pro dev preencher.
// ─────────────────────────────────────────────────────────────────────────────
function tsxTemplate(pt, mod, tela) {
  const head = `// ${mod}/${tela} — carimbado do ${pt} ${PT_META[pt].nome} por criar-tela.mjs (UI-0013).\n// Herda o Padrão de Tela: NÃO reinvente a estrutura — preencha os {/* TODO */}.\n`;
  const bodies = {
    'PT-01': `import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '${importPath('PageHeader', '@/Components/PageHeader')}';
import DataTable from '${importPath('DataTable', '@/Components/shared/DataTable')}';
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
      {/* \`data-contract\` = âncora do contrato de tela (prototipo-ui/contrato/). NÃO remova o
          atributo sem tirar a seção do .contract.json — o gate contrato-de-tela cobra os dois. */}
      <div data-contract="cabecalho">
        <PageHeader title="${tela}" subtitle="TODO: descrição da lista" />
      </div>
      <div data-contract="filtros">
        {/* TODO: filtros da lista (SellsDateFilter / busca / status) acima da tabela */}
      </div>
      {/* \`endpoint\` é OBRIGATÓRIO no DataTable (shared/DataTable.tsx:56) — troque pela rota real. */}
      <div data-contract="lista">
        <DataTable columns={columns} data={paginator.data} pagination={paginator as never} endpoint="/TODO-rota-da-lista" />
      </div>
    </AppShellV2>
  );
}
`,
    'PT-02': `import AppShellV2 from '@/Layouts/AppShellV2';
import { useForm } from '@inertiajs/react';
import { FormSection, FormGrid } from '${importPath('FormSection', '@/Components/ui/form-section')}';
import { Input } from '${importPath('Input', '@/Components/ui/input')}';
import { Button } from '${importPath('Button', '@/Components/ui/button')}';

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
          {/* \`data-contract\` = âncora do contrato de tela (prototipo-ui/contrato/). NÃO remova o
              atributo sem tirar a seção do .contract.json — o gate contrato-de-tela cobra os dois. */}
          <div data-contract="formulario">
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
          </div>
          <div data-contract="acoes" className="flex justify-end gap-2">
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
      {/* \`data-contract\` = âncora do contrato de tela (prototipo-ui/contrato/). NÃO remova o
          atributo sem tirar a seção do .contract.json — o gate contrato-de-tela cobra os dois. */}
      <div className="grid gap-4 lg:grid-cols-[1fr_320px]">
        <div data-contract="detalhe">
          {/* TODO: seções de detalhe (dados + Histórico/Timeline auditável) */}
          <dl className="grid grid-cols-2 gap-2">
            <dt>Campo</dt>
            <dd>{String(registro?.id ?? '—')}</dd>
          </dl>
        </div>
        {/* Painel de próxima ação (FSM/RBAC) — o que distingue Detalhe (PT-03) de Dashboard */}
        <div data-contract="acoes-fsm">
          <FsmActionPanel /* TODO: subject, actions, user */ />
        </div>
      </div>
    </AppShellV2>
  );
}
`,
    'PT-04': `import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '${importPath('PageHeader', '@/Components/PageHeader')}';
import KpiGrid from '${importPath('KpiGrid', '@/Components/shared/KpiGrid')}';
import KpiCard from '${importPath('KpiCard', '@/Components/shared/KpiCard')}';

interface Props { kpis?: Record<string, number> /* TODO: agregados do dashboard */ }

export default function ${tela}({ kpis }: Props) {
  return (
    <AppShellV2>
      {/* \`data-contract\` = âncora do contrato de tela (prototipo-ui/contrato/). NÃO remova o
          atributo sem tirar a seção do .contract.json — o gate contrato-de-tela cobra os dois. */}
      <div data-contract="cabecalho">
        <PageHeader title="${tela}" subtitle="TODO: descrição do painel" />
      </div>
      <div data-contract="kpis">
        <KpiGrid cols={4}>
          {/* TODO: KPIs reais do módulo */}
          <KpiCard label="TODO" value={kpis?.total ?? 0} />
        </KpiGrid>
      </div>
      {/* TODO: gráficos/tabelas de apoio abaixo dos KPIs */}
    </AppShellV2>
  );
}
`,
    'PT-05': `import AppShellV2 from '@/Layouts/AppShellV2';
import KanbanDndProvider from './_components/KanbanDndProvider'; // TODO: crie/reuse o provider dnd-kit da tela
import BoardColumn from '${importPath('BoardColumn', '@/Components/board/BoardColumn')}';

interface Card { id: number /* TODO */ }
interface Props { colunas: { key: string; titulo: string; cards: Card[] }[] }

export default function ${tela}({ colunas }: Props) {
  return (
    <AppShellV2>
      {/* Kanban com drag-and-drop (dnd-kit) — o que distingue o PT-05 */}
      {/* \`data-contract\` = âncora do contrato de tela (prototipo-ui/contrato/). NÃO remova o
          atributo sem tirar a seção do .contract.json — o gate contrato-de-tela cobra os dois. */}
      <KanbanDndProvider /* TODO: onDragEnd que persiste a transição via FSM */>
        <div data-contract="quadro" className="flex gap-4 overflow-x-auto">
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
// PROTÓTIPO DO MÓDULO — o buraco que este detector fecha (achado 2026-08-09).
//
// Antes: o template escrevia `related_prototype: n/a (herda PT-0X)` SEMPRE, e o
// `anchor-content-check` (required) só valida âncora DECLARADA — sem âncora, nada
// a validar, gate verde. Resultado: tela nova nascia sem NINGUÉM perguntar "existe
// protótipo pra esta família?". Foi assim que o Quadro da Forja nasceu ignorando
// `forja-page.jsx`, que já desenhava KanbanView/KanbanCard com RoleBadge, TypeChip
// e FrescorPill.
//
// ⚠️ `n/a` CONTINUA legítimo e é a maioria — tela que nasce do DS não tem protótipo
// e nunca terá (proibicoes §5 2026-07-17). O que este detector muda é só quem
// DECIDE: hoje o gerador decidia sozinho; agora ele só decide quando não há nada
// pra achar. Onde HÁ protótipo, a escolha é explícita do autor.
//
// Duas fontes, na ordem em que o `ancora.mjs` também olha:
//   (a) outro charter do mesmo módulo que já declara um protótipo resolvível
//   (b) a convenção de nome `prototipo-ui/cowork/<modulo>-page.jsx`
// ─────────────────────────────────────────────────────────────────────────────
function detectarPrototipoDoModulo(mod, root = ROOT) {
  const achados = [];

  // (a) herda o vizinho: se uma tela do módulo já tem âncora, a família tem fonte.
  const dirMod = join(root, 'resources', 'js', 'Pages', mod);
  const varrer = (dir) => {
    let entradas;
    try { entradas = readdirSync(dir, { withFileTypes: true }); } catch { return; }
    for (const e of entradas) {
      const alvo = join(dir, e.name);
      if (e.isDirectory()) { varrer(alvo); continue; }
      if (!e.name.endsWith('.charter.md')) continue;
      let txt = '';
      try { txt = readFileSync(alvo, 'utf8'); } catch { continue; }
      const m = txt.match(/^related_prototype:\s*(.+)$/m);
      const v = m && m[1].trim();
      // `n/a …` é decisão consciente do vizinho, não fonte — não conta como achado.
      if (!v || /^n\/a/i.test(v)) continue;
      const caminho = v.split(/\s+/)[0];
      if (existsSync(join(root, caminho))) achados.push(caminho);
    }
  };
  varrer(dirMod);

  // (b) convenção de nome do Cowork.
  const porConvencao = `prototipo-ui/cowork/${mod.toLowerCase()}-page.jsx`;
  if (existsSync(join(root, porConvencao))) achados.push(porConvencao);

  return [...new Set(achados)];
}

// ─────────────────────────────────────────────────────────────────────────────
// TEMPLATE do charter (herda o PT — related_prototype "n/a (herda PT-0X…)")
//
// SEM `last_validated:` de propósito. A tela nasce `status: draft` — ninguém validou nada
// ainda, e campo que o gerador não sabe preencher fica AUSENTE, nunca com placeholder
// (decisão [W] aceita em 2026-08-11 · proposal `templates-8-artefatos-ANEXO`, que nomeia
// `last_validated` entre os 7 campos). O campo é opcional no schema canônico
// (scripts/memory-schemas/charter.schema.json — só page/component/status são required), e quem
// escreve a data é a skill `charter-write` quando o charter sobe draft→live.
//
// Fato datado: até 2026-08-24 este arquivo carimbava `last_validated: "2026-07-11"` fixo —
// nascido junto com o gerador (#4111, 2026-07-11) e nunca bumpado nos 3 commits seguintes que
// o tocaram (#4875, #5512, #5777), de modo que toda tela nova afirmava uma validação que não
// houve. A ausência também preserva o determinismo que motivou a constante.
// ─────────────────────────────────────────────────────────────────────────────
/**
 * ALCANCE — a camada que nenhum gate de código vê: como o HUMANO chega na tela.
 *
 * Rota nomeada → permission → entrada de menu → pacote do business. Não é código React,
 * então `pt-conformance`, `casos-gate` e `ciclo-completo` passam por cima dela.
 *
 * Nasceu do caso `/arquivos` (2026-08-25): trio completo no main, rota respondendo 200,
 * 26 testes Feature — e `modifyAdminMenu()` era NO-OP, com um comentário afirmando que o
 * módulo "não tem tela própria". Ninguém alcançava a tela pelo menu. Quem pegou foi o [W]
 * a olho, no smoke do sidebar; gate nenhum reclamou.
 *
 * Os valores abaixo são DERIVADOS como sugestão — o autor corrige no charter se a
 * convenção do módulo for outra. O golden vivo é
 * `Modules/Arquivos/Http/Controllers/DataController.php` (as 3 camadas de habilitação:
 * pacote → permission → menu), que é o que este bloco descreve.
 */
function derivarAlcance(mod, tela, rota) {
  const slug = kebab(mod);
  return {
    rota,
    rota_nome: tela.toLowerCase() === 'index' ? `${slug}.index` : `${slug}.${kebab(tela)}`,
    permission: `${slug}.access`,
    menu_hook: `Modules/${mod}/Http/Controllers/DataController.php::modifyAdminMenu`,
    pacote: `${slug}_module`,
  };
}

/** Bloco YAML do alcance — some quando a tela declara `--sem-rota`. */
function alcanceYaml(alcance, semRotaRazao) {
  if (!alcance) {
    // `page:` acima ficou com a área do módulo (o schema exige path). A verdade precisa —
    // "esta tela não tem URL própria" — é ESTA linha, e é ela que o guard de alcance lê.
    return `alcance:\n  rota: n/a (${semRotaRazao})`;
  }
  return [
    'alcance:',
    `  rota: ${alcance.rota}`,
    `  rota_nome: ${alcance.rota_nome}        # name() da rota — é o que o guard procura`,
    `  permission: ${alcance.permission}      # declarada em DataController::user_permissions`,
    `  menu_hook: ${alcance.menu_hook}`,
    `  pacote: ${alcance.pacote}              # superadmin_package`,
  ].join('\n');
}

function charterTemplate(pt, mod, tela, componentRel, protoDecl, alcance, semRotaRazao) {
  // `page:` é REQUIRED no charter.schema.json com pattern `^/.*$`, e os 293 charters do
  // repo respeitam isso — medido em 2026-08-25. Então `--sem-rota` NÃO pode escrever
  // "n/a" aqui: nasceria o primeiro charter inválido do projeto.
  //
  // A separação que resolve: `page` responde "sob qual URL esta tela é VISTA" (a área do
  // módulo, quando ela é sub-tela de drawer/modal); `alcance.rota` responde "ela tem URL
  // PRÓPRIA?" — e é lá que o n/a mora, com a razão do autor.
  const pageValor = alcance ? alcance.rota : `/${kebab(mod)}`;

  return `---
page: ${pageValor}
component: ${componentRel}
owner: wagner
status: draft
parent_module: ${mod}
related_prototype: ${protoDecl ?? `n/a (herda ${pt} ${PT_META[pt].nome}; segue o Padrão de Tela)`}
${alcanceYaml(alcance, semRotaRazao)}
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
function casosTemplate(mod, tela, alcance) {
  const uc = `UC-${ucPrefix(tela)}-01`;
  const uc00 = `UC-${ucPrefix(tela)}-00`;

  // O UC de ALCANCE só nasce quando a tela TEM rota própria. Com `--sem-rota` a decisão
  // já está registrada no charter e cobrar "chegue pelo menu" seria inventar defeito.
  const blocoAlcance = alcance
    ? `## ${uc00} · Chego na tela pelo menu, sem digitar URL
- **Persona:** Larissa — abre o sistema e encontra a tela pelo sidebar.
- **Aceite:** Dado usuário com a permission \`${alcance.permission}\` · Quando abre o sistema ·
  Então o item existe no sidebar e leva a \`${alcance.rota}\` (200, sem digitar URL).
- **Regressão que defende:** a tela responder 200 e ninguém alcançar — \`modifyAdminMenu()\`
  no-op passa por todo gate de código, porque um método vazio é sintaticamente perfeito.
- **Status: ⬜**

---

`
    : '';

  return `---
casos: ${mod}/${tela} — carimbado do Padrão de Tela
irmaos: ${tela}.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "${hojeBRT()}"
---

# Casos de Uso & Aceite — ${mod}/${tela}

> Nascido de \`criar-tela.mjs\`. **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão. O stub \`e2e/${kebab(mod)}-${kebab(tela)}.spec.ts\` já cita \`${uc}\`.

---

${blocoAlcance}## ${uc} · TODO: o caminho feliz da tela
- **Persona:** Larissa (ROTA LIVRE) — TODO: o que ela quer fazer nesta tela.
- **Aceite:** Dado TODO · Quando TODO · Então TODO (resultado verificável).
- **Teste:** \`e2e/${kebab(mod)}-${kebab(tela)}.spec.ts\` — stub \`test.fixme\` citando \`${uc}\` (troque por asserção real).
- **Regressão que defende:** TODO — o que não pode voltar a quebrar.
- **Status: ⬜** — stub; vira 🧪/✅ quando o teste executar e passar.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** TODO: próximo caso.

## Trilha do tempo
- ${hojeBRT()} · [CC] carimbado por criar-tela.mjs — trio nascido junto (charter + casos + teste). Refs: UI-0013 · ADR 0264 G-1/G-2.
`;
}

// ─────────────────────────────────────────────────────────────────────────────
// TEMPLATE do stub de teste E2E (Playwright). test.fixme = pendente (não roda/não quebra CI).
// Cita o UC-id no título → casos-guard G-2 encontra a rastreabilidade caso↔teste.
// ─────────────────────────────────────────────────────────────────────────────
function testeTemplate(mod, tela, alcance) {
  const uc = `UC-${ucPrefix(tela)}-01`;
  // A rota real, não `/TODO-rota`: o stub nasce apontando pra onde a tela vai morar.
  const destino = alcance ? alcance.rota : '/TODO-rota (tela sem URL própria — ver charter)';
  return `import { test, expect } from '@playwright/test';

// Stub E2E carimbado por criar-tela.mjs — contrato em resources/js/Pages/${mod}/${tela}.casos.md.
// test.fixme = PENDENTE (não executa, não quebra o CI). Troque por asserção real de comportamento
// quando a tela ${mod}/${tela} estiver implementada. Locators RESILIENTES (role/label/text), nunca
// classe CSS (L-24). NÃO edite a tela viva sem charter + gate visual.

test.fixme('${uc}: TODO caminho feliz de ${mod}/${tela}', async ({ page }) => {
  await page.goto('${destino}');
  await expect(page.getByRole('heading', { name: '${tela}' })).toBeVisible();
  // TODO: Dado/Quando/Então do ${uc}.
});
`;
}

// ─────────────────────────────────────────────────────────────────────────────
// CONTRATO DE TELA — o 5º artefato (prototipo-ui/contrato/<mod>-<tela>.contract.json).
//
// O buraco que fecha: `prototipo-ui/contrato/` tem contrato de tela e NENHUM do módulo
// Arquivos — porque o gerador carimbava 4 artefatos e o contrato ficava pra "depois", que
// nunca chega. Agora nasce junto, já consumido pelo `contrato:check` que existe (nenhum
// gate novo — o job "Preflight + contratos ativos" roda `git ls-files '*.contract.json'`
// e exige que TODOS passem, só o EXEMPLO é isento).
//
// POR QUE A `copy` NASCE VAZIA (é decisão, não esquecimento):
// o gate exige match LITERAL de cada string de `copy` no alvo. As únicas strings que o .tsx
// carimbado tem são placeholders ("TODO: descrição da lista", o nome da tela como título).
// Pinar placeholder faria o gate ficar vermelho no dia em que o autor escrevesse a copy DE
// VERDADE — reprovar por acertar. Pior: congelaria o TODO como se fosse lei, que é o mesmo
// erro que o `jana-painel.contract.json` recusou ao NÃO pinar o botão "(em breve)".
// Então o gerado carimba o que É wiring do agente — ÂNCORA + ORDEM, derivadas do arquétipo —
// e deixa a COPY pro [W], registrada em `_pendente_w` (a convenção que jana-painel e
// ponto-painel já usam). Seção com `copy: []` passa no gate e não afirma nada de falso.
//
// `estados` fica FORA de propósito: quais estados esta tela terá é pergunta do domínio, não
// do arquétipo. Inventar "empty/loading" aqui seria anti-padrão inventado com cara de canon.
function contratoTemplate(pt, mod, tela, protoDecl, outRoot) {
  const componentRel = `resources/js/Pages/${mod}/${tela}.tsx`;
  const secoes = PT_SECOES[pt];

  // `fonte` TEM de apontar pra arquivo existente: `--map --check` (required no mesmo job)
  // falha com "fonte aponta arquivo inexistente". Duas origens honestas, nesta ordem:
  //   1. o protótipo que o autor declarou em `--prototipo` — se existir NO DISCO;
  //   2. o próprio .tsx carimbado — quando não há protótipo (`--sem-prototipo`/módulo sem
  //      fonte), porque aí a estrutura vem MESMO do arquétipo PT materializado no .tsx.
  // O caso 2 tem precedente vivo: `jana-painel.contract.json` aponta `fonte` pra tela viva
  // e explica no próprio contrato. Resolve contra `outRoot` — é o root em que o contrato vai
  // viver, e é contra ele que o gate resolve o path.
  const protoPath = protoDecl && !protoDecl.startsWith('n/a') ? protoDecl : null;
  const protoExiste = protoPath ? existsSync(resolve(outRoot, protoPath)) : false;

  const contrato = {
    _nota: `Contrato de tela GERADO por criar-tela.mjs junto com o ${pt} ${PT_META[pt].nome} de `
      + `${mod}/${tela} (${hojeBRT()}). Roda em `
      + `\`node scripts/contrato-de-tela.mjs --contract <este-arquivo>\`, no mesmo job que já `
      + `varre todos os *.contract.json. As seções são as do ARQUÉTIPO e as âncoras `
      + `data-contract correspondentes já nascem no .tsx — âncora e ordem são wiring do agente. `
      + `A COPY não: veja _pendente_w.`,
    tela: `${mod}/${tela}`,
    fonte: protoExiste ? protoPath : componentRel,
  };

  if (!protoExiste) {
    contrato._nota_fonte = protoPath
      ? `\`fonte\` aponta pro .tsx e NÃO pro protótipo declarado (\`${protoPath}\`) porque esse `
        + `caminho não existe no disco no momento da geração. Ponteiro quebrado deixaria o `
        + `contrato nascer vermelho no --map --check. Corrija o path e aponte \`fonte\` pra ele.`
      : `\`fonte\` aponta pra própria tela porque esta não tem protótipo Cowork: a estrutura `
        + `vem do arquétipo ${pt} materializado no .tsx. Mesma situação (e mesma redação) do `
        + `jana-painel.contract.json. Quando a tela ganhar protótipo versionado, aponte aqui.`;
  }

  contrato.alvo = [componentRel];
  // `copy: []` — ver o bloco POR QUE A `copy` NASCE VAZIA acima. NÃO preencher por palpite.
  contrato.secoes = secoes.map((s) => ({ id: s.id, copy: [] }));
  contrato.ordem = secoes.map((s) => s.id);
  contrato._pendente_w = [
    `COPY DE CADA SEÇÃO — hoje todas as \`copy\` estão vazias, e o gate passa sem exigir string `
      + `nenhuma. A copy literal que o design exige é decisão [W] (how-trabalhar.md §Pedido de `
      + `tela: "\`## Contrato visual\` (copy literal + ordem)"). Preencha seção a seção conforme `
      + `os TODO do .tsx forem virando texto real — cada string vira match exato no alvo.`,
    `ESTADOS — \`estados\` foi omitido de propósito (empty/loading/erro dependem do domínio, `
      + `não do arquétipo). Declare quando a tela tiver os estados de verdade.`,
    ...secoes.map((s) => `seção \`${s.id}\` — ${s._papel}. Copy pendente.`),
  ];

  return JSON.stringify(contrato, null, 2) + '\n';
}

// ─────────────────────────────────────────────────────────────────────────────
// Motor de geração
// ─────────────────────────────────────────────────────────────────────────────
export function renderConjunto(pt, mod, tela, protoDecl, alcance = null, semRotaRazao = null, outRoot = ROOT) {
  const componentRel = `resources/js/Pages/${mod}/${tela}.tsx`;
  return {
    tsx: tsxTemplate(pt, mod, tela),
    charter: charterTemplate(pt, mod, tela, componentRel, protoDecl, alcance, semRotaRazao),
    casos: casosTemplate(mod, tela, alcance),
    teste: testeTemplate(mod, tela, alcance),
    contrato: contratoTemplate(pt, mod, tela, protoDecl, outRoot),
  };
}

// Wrapper `<div data-contract>` desbalanceado = .tsx que NÃO COMPILA — e o gerador já emitiu
// .tsx quebrado antes (#6210, TS2613, medido com tsc). Aqui não dá pra chamar o tsc: o parser
// vive em node_modules e o --selftest roda sem `npm ci` (node puro, como o resto). LIMITE
// DECLARADO: isto prova BALANCEAMENTO de tag, não tipagem — a forma dos imports é coberta por
// `conferirFormaDosImports`, e o tsc de verdade roda na lane que compila o repo.
export function balancoDeTags(tsx, tag = 'div') {
  const abre = (tsx.match(new RegExp(`<${tag}(?=[\\s>])(?![^>]*/>)`, 'g')) || []).length;
  const fecha = (tsx.match(new RegExp(`</${tag}>`, 'g')) || []).length;
  return abre - fecha;
}

// Toda seção do arquétipo TEM âncora `data-contract` no .tsx? (o par (a)↔(b) do PT_SECOES).
// Exportada porque o --selftest a exerce nos 5 arquétipos: sem isto, mexer num template e
// esquecer a âncora faria a tela nascer com contrato vermelho — silenciosamente.
export function conferirAncorasDoArquetipo(pt, tsx) {
  const presentes = new Set([...tsx.matchAll(/data-contract\s*=\s*["'`]([^"'`]+)["'`]/g)].map((m) => m[1]));
  return PT_SECOES[pt].filter((s) => !presentes.has(s.id)).map((s) => s.id);
}

function planPaths(mod, tela, outRoot) {
  const base = join(outRoot, 'resources', 'js', 'Pages', mod);
  return {
    tsx: join(base, `${tela}.tsx`),
    charter: join(base, `${tela}.charter.md`),
    casos: join(base, `${tela}.casos.md`),
    teste: join(outRoot, 'e2e', `${kebab(mod)}-${kebab(tela)}.spec.ts`),
    // Nome = `<kebab(mod)>-<kebab(tela)>.contract.json`, o padrão dos contratos que já existem
    // (`superadmin-dashboard`, `ponto-painel`). O diretório é fixo: é o que `listContracts()`
    // e o step "Contratos ativos" varrem.
    contrato: join(outRoot, 'prototipo-ui', 'contrato', `${kebab(mod)}-${kebab(tela)}.contract.json`),
  };
}

function gerar({ mod, tela, pt, force, outRoot, protoDecl, alcance, semRotaRazao }) {
  const paths = planPaths(mod, tela, outRoot);
  const conj = renderConjunto(pt, mod, tela, protoDecl, alcance, semRotaRazao, outRoot);
  const existentes = Object.values(paths).filter((p) => existsSync(p));
  if (existentes.length && !force) {
    console.error(`❌ Já existe(m) (use --force pra sobrescrever):`);
    for (const p of existentes) console.error(`   ${relOut(p, outRoot)}`);
    process.exit(1);
  }
  const writes = [
    [paths.tsx, conj.tsx], [paths.charter, conj.charter],
    [paths.casos, conj.casos], [paths.teste, conj.teste],
    [paths.contrato, conj.contrato],
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
    t(!/^last_validated:/m.test(conj.charter), `${pt}: charter NÃO afirma validação (draft nasce SEM last_validated)`);
    t(/^## UC-[A-Z]+-01/m.test(conj.casos), `${pt}: casos.md tem UC stub (contrato de teste)`);
    const ucMatch = conj.casos.match(/## (UC-[A-Z]+-01)/);
    t(ucMatch && conj.teste.includes(ucMatch[1]), `${pt}: stub de teste cita o UC (G-2 rastreabilidade)`);
    t(/test\.fixme/.test(conj.teste), `${pt}: stub de teste é fixme (não quebra CI)`);
    // 5º artefato: o par (a) âncora no .tsx ↔ (b) seção no contrato NÃO pode drifar.
    const semAncora = conferirAncorasDoArquetipo(pt, conj.tsx);
    t(semAncora.length === 0,
      `${pt}: toda seção do arquétipo tem âncora data-contract no .tsx${semAncora.length ? ` — falta ${semAncora.join(', ')}` : ''}`);
    const ctr = JSON.parse(conj.contrato);
    t(ctr.secoes.length === PT_SECOES[pt].length && ctr.secoes.every((s, i) => s.id === PT_SECOES[pt][i].id),
      `${pt}: as seções do contrato são as do arquétipo (derivadas de PT_SECOES, não escritas 2×)`);
    t(ctr.secoes.every((s) => Array.isArray(s.copy) && s.copy.length === 0),
      `${pt}: a copy nasce VAZIA (pinar placeholder reprovaria quem escrevesse a copy real)`);
    // O schema declara `additionalProperties: false` dentro de secoes[] — o gerado respeita.
    t(ctr.secoes.every((s) => Object.keys(s).every((k) => ['id', 'copy', 'estados'].includes(k))),
      `${pt}: seção só tem chaves do contract.schema.json (id/copy/estados)`);
    t(ctr.secoes.every((s) => /^[a-z0-9-]+$/.test(s.id)),
      `${pt}: todo id de seção casa o pattern ^[a-z0-9-]+$ do schema`);
    t(Array.isArray(ctr.alvo) && ctr.alvo.length > 0 && !!ctr.fonte,
      `${pt}: contrato tem os campos obrigatórios do schema (alvo + secoes) e a fonte`);
    t(balancoDeTags(conj.tsx) === 0,
      `${pt}: os wrappers <div data-contract> fecham (delta ${balancoDeTags(conj.tsx)}) — .tsx não nasce quebrado`);
  }
  // Controle-positivo da sonda de âncoras: um `=== 0` verde também fica verde se ela for cega.
  t(conferirAncorasDoArquetipo('PT-01', '<div>sem ancora nenhuma</div>').length === PT_SECOES['PT-01'].length,
    'controle-positivo: conferirAncorasDoArquetipo ACUSA .tsx sem nenhuma âncora');
  // Controles da sonda de balanceamento: ela acusa o desbalanceado e não acusa o self-closing.
  t(balancoDeTags('<div data-contract="x"><span/>') === 1,
    'controle-positivo: balancoDeTags ACUSA <div> sem fechamento');
  t(balancoDeTags('<div className="a" /><div>ok</div>') === 0,
    'controle-negativo: <div /> self-closing não é contado como aberto');
  // Controle positivo da sonda acima: um `!regex` verde também fica verde se o regex for cego.
  // Aqui provo que ele CASA quando o campo EXISTE (proibicoes §5 2026-08-01).
  const charterComCampo = `---
status: draft
last_validated: "2026-01-01"
---`;
  t(/^last_validated:/m.test(charterComCampo),
    'controle-positivo: a sonda de `last_validated` CASA quando o campo existe');

  // ── CODE CONNECT em tempo de geração (degrau dtc-code-connect-geracao) ──────
  // BITE: o registry VENCE o path declarado no template. Passo um declarado sabidamente
  // errado — se o retorno ainda for o do registry, a injeção é real (e não decoração).
  const doRegistry = importPath('Button', '@/PATH/DECLARADO/ERRADO');
  t(doRegistry !== '@/PATH/DECLARADO/ERRADO' && doRegistry === '@/Components/ui/button',
    'MORDE: componente `mapped` no registry vence o path declarado no template (injeção real)');
  t(importPath('__ComponenteInexistente__', '@/fallback/ok') === '@/fallback/ok',
    'controle-negativo: componente fora do registry cai no declarado (não crasha, não inventa)');
  t(renderConjunto('PT-02', 'X', 'Y').tsx.includes("from '@/Components/ui/button'"),
    'PT-02 gerado carrega o import_path vindo do registry');
  // Proveniência: a COBERTURA é DERIVADA do registry vivo e só REPORTADA — não asserida.
  // Aqui morava `=== 3 registry && === 5 declarado`, congelado em 2026-08-14. Em 2026-08-24 o
  // #6210 subiu o registry de 40 pra 69 entradas e a cobertura foi a 7/8: o assert reprovou
  // por GANHO, e a lane ficou vermelha em todo PR por ter MELHORADO (§5 2026-08-24 — predicado
  // ABSOLUTO onde cabia DELTA). Congelar de novo (`=== 7`) só adia o mesmo defeito; um piso
  // (`>= 3`) seria decorativo, porque não pegaria uma queda de 7 pra 4.
  //
  // O que fica ASSERTADO abaixo é o que tem consequência de verdade quando muda — a FORMA do
  // import e o header @deprecated — e nenhum dos dois apodrece com o crescimento do registry.
  const prov = provenienciaImports([['Button', '@/Components/ui/button'], ['Input', '@/Components/ui/input'],
    ['FormSection', '@/Components/ui/form-section'], ['PageHeader', '@/Components/PageHeader'],
    ['DataTable', '@/Components/shared/DataTable'], ['KpiGrid', '@/Components/shared/KpiGrid'],
    ['KpiCard', '@/Components/shared/KpiCard'], ['BoardColumn', '@/Components/board/BoardColumn']]);
  const nReg = prov.filter((p) => p.fonte === 'registry').length;
  console.log(`  · cobertura do Code Connect (derivada, não asserida): ${nReg}/${prov.length} imports vêm do registry`
    + ` — sem entrada: ${prov.filter((p) => p.fonte === 'declarado').map((p) => p.componente).join(', ') || 'nenhum'}`);

  // BITE (o defeito que o número congelado escondia): trocar o `import_path` NÃO troca a
  // FORMA do import. O #6210 mapeou PageHeader pro barril `@/Components/PageHeader`, que
  // exporta `{ PageHeader }` e não tem `default` — o gerador passou a emitir
  // `import PageHeader from …`, ou seja .tsx que NÃO COMPILA (TS2613, medido com tsc).
  for (const pt of Object.keys(PT_META)) {
    const d = conferirFormaDosImports(renderConjunto(pt, 'Fixtura', 'MinhaTela').tsx);
    t(d.length === 0, `${pt}: forma do import bate com os \`exports\` do registry${d.length ? ` — ${d.join(' · ')}` : ''}`);
  }
  // Controle da sonda acima, com registry SINTÉTICO (mesma função da produção, §5 2026-08-14):
  // um `=== 0` verde também fica verde se a sonda for cega.
  const regFake = new Map([['@/Fake/barril', { import_path: '@/Fake/barril', exports: ['Coisa'], status: 'mapped' }]]);
  t(conferirFormaDosImports("import Coisa from '@/Fake/barril';", regFake).length === 1,
    'controle-positivo: a sonda ACUSA default import de barril que só exporta nomeado (TS2613)');
  t(conferirFormaDosImports("import { Coisa } from '@/Fake/barril';", regFake).length === 0,
    'controle-negativo: import nomeado conforme não é acusado');
  t(conferirFormaDosImports("import Qualquer from '@/Path/ForaDoRegistry';", regFake).length === 0,
    'controle-negativo: path fora do registry não é assunto da sonda (não inventa divergência)');

  // Ratchet com consequência: o fallback declarado do PageHeader era o `shared/PageHeader`,
  // @deprecated CONGELADO — tela NOVA que o importe reprova no `pageheader-gate.yml`. O
  // gerador não pode semear isso, com ou sem entrada no registry.
  const comHeaderCongelado = Object.keys(PT_META)
    .filter((pt) => renderConjunto(pt, 'X', 'Y').tsx.includes("'@/Components/shared/PageHeader'"));
  t(comHeaderCongelado.length === 0,
    `nenhum arquétipo nasce com o PageHeader @deprecated (ratchet pageheader-gate)${comHeaderCongelado.length ? ` — ${comHeaderCongelado.join(', ')}` : ''}`);

  // cross-check: PT-02 NÃO deve passar como se fosse PT-05 (assinaturas distintas)
  const pt02 = detectSignals(renderConjunto('PT-02', 'X', 'Y').tsx);
  t(!REQUIRED['PT-05'](pt02), 'PT-02 carimbado NÃO satisfaz assinatura de PT-05 (arquétipos distintos)');

  // ── ÂNCORA DE DESIGN (achado 2026-08-09) ───────────────────────────────────
  // O gerador escrevia `n/a` SEMPRE, e o `anchor-content-check` (required) só
  // valida âncora DECLARADA — sem âncora, gate verde. Tela nascia ignorando
  // protótipo existente. Estes casos provam que ele agora PROCURA antes.
  //
  // ⚠️ O bite-test roda o CLI DE FORA (spawnSync), não a função pura: o veto vive
  // no fluxo do CLI, e assert sobre helper exportado não prova contrato de
  // pipeline (proibicoes §5 2026-07-30).
  const { spawnSync } = await import('node:child_process');
  const { mkdtempSync } = await import('node:fs');
  const { tmpdir } = await import('node:os');
  const rodar = (args) => spawnSync(process.execPath, [fileURLToPath(import.meta.url), ...args],
    { cwd: ROOT, encoding: 'utf8' });
  const tmp = () => mkdtempSync(join(tmpdir(), 'criar-tela-'));

  // Detector: acha onde HÁ e não inventa onde NÃO há.
  t(detectarPrototipoDoModulo('Forja').length > 0,
    'detector ACHA o protótipo do módulo Forja (forja-page.jsx)');
  t(detectarPrototipoDoModulo('ModuloQueNaoExisteXyz').length === 0,
    'detector não inventa protótipo em módulo inexistente');

  // BITE: módulo COM protótipo, sem escolha explícita → recusa (exit 2).
  const bite = rodar(['Forja/FixturaAncora', 'PT-01', '--out', tmp()]);
  t(bite.status === 2, 'BITE: módulo COM protótipo sem --prototipo/--sem-prototipo → exit 2');
  t(/TEM protótipo/.test(bite.stderr || ''), 'BITE: a recusa NOMEIA o candidato encontrado');

  // A partir de 2026-08-25 o gerador também exige a decisão de ROTA, então as chamadas
  // abaixo levam `--rota`: sem ela sairiam 2 por ALCANCE e os CN da âncora mediriam
  // outra coisa (verde/vermelho pelo motivo errado é pior que vermelho).
  // CN-1: módulo SEM protótipo segue exatamente como antes (não virou gate hostil).
  const cn1 = tmp();
  const semProto = rodar(['ModuloSemPrototipoXyz/Tela', 'PT-01', '--out', cn1, '--rota', 'fixtura-sem-proto']);
  t(semProto.status === 0, 'CN-1: módulo SEM protótipo continua gerando (exit 0)');
  t(/related_prototype:\s*n\/a \(herda PT-01/.test(
      readFileSync(join(cn1, 'resources/js/Pages/ModuloSemPrototipoXyz/Tela.charter.md'), 'utf8')),
    'CN-1: sem protótipo no módulo, o default `n/a (herda PT-0X)` é preservado');

  // CN-2/CN-3: as DUAS saídas explícitas funcionam e escrevem o que prometem.
  const cn2 = tmp();
  rodar(['Forja/FixturaAncora', 'PT-01', '--out', cn2, '--prototipo', 'prototipo-ui/cowork/forja-page.jsx', '--rota', 'fixtura-ancora']);
  t(/related_prototype:\s*prototipo-ui\/cowork\/forja-page\.jsx/.test(
      readFileSync(join(cn2, 'resources/js/Pages/Forja/FixturaAncora.charter.md'), 'utf8')),
    'CN-2: --prototipo escreve o path declarado');

  const cn3 = tmp();
  rodar(['Forja/FixturaAncora', 'PT-01', '--out', cn3, '--sem-prototipo', 'motivo de fixtura', '--rota', 'fixtura-ancora']);
  t(/related_prototype:\s*n\/a \(motivo de fixtura\)/.test(
      readFileSync(join(cn3, 'resources/js/Pages/Forja/FixturaAncora.charter.md'), 'utf8')),
    'CN-3: --sem-prototipo escreve n/a COM a razão (decisão fica registrada)');

  // ── ALCANCE (2026-08-25) — mesmo rigor: bite pelo CLI + os dois controles ───────
  // O defeito de origem: /arquivos nasceu com `page: /TODO-rota`, respondeu 200 em prod e
  // ninguém chegava nela pelo menu. Nenhum gate viu, porque alcance não é código React.
  const biteRota = rodar(['ModuloSemPrototipoXyz/Tela', 'PT-01', '--out', tmp()]);
  t(biteRota.status === 2, 'BITE-ALCANCE: sem --rota/--sem-rota → exit 2');
  t(/sem rota declarada/.test(biteRota.stderr || ''),
    'BITE-ALCANCE: a recusa NOMEIA que o que falta é a rota');
  t(/TODO-rota/.test(biteRota.stderr || ''),
    'BITE-ALCANCE: a recusa cita o placeholder que ela existe pra impedir');

  // CN-4: com --rota, o charter nasce com a rota REAL e o bloco de alcance.
  const cn4 = tmp();
  const comRota = rodar(['ModuloSemPrototipoXyz/Tela', 'PT-01', '--out', cn4, '--rota', 'minha-tela']);
  t(comRota.status === 0, 'CN-4: com --rota gera (exit 0)');
  const chCn4 = readFileSync(join(cn4, 'resources/js/Pages/ModuloSemPrototipoXyz/Tela.charter.md'), 'utf8');
  t(/^page: \/minha-tela$/m.test(chCn4), 'CN-4: `page:` é a rota real, nunca /TODO-rota');
  t(/^alcance:$/m.test(chCn4) && /^\s+rota_nome: /m.test(chCn4),
    'CN-4: o charter carimba o contrato de alcance (rota_nome/permission/menu_hook/pacote)');
  t(/UC-TELA-00/.test(readFileSync(join(cn4, 'resources/js/Pages/ModuloSemPrototipoXyz/Tela.casos.md'), 'utf8')),
    'CN-4: o casos.md ganha o UC-00 de alcance ("chego pelo menu")');
  t(/page\.goto\('\/minha-tela'\)/.test(readFileSync(join(cn4, 'e2e/modulo-sem-prototipo-xyz-tela.spec.ts'), 'utf8')),
    'CN-4: o stub e2e aponta pra rota real, não pro placeholder');

  // CN-5: --sem-rota registra a decisão e NÃO inventa o UC-00.
  const cn5 = tmp();
  const semRota = rodar(['ModuloSemPrototipoXyz/Tela', 'PT-01', '--out', cn5, '--sem-rota', 'sub-tela de drawer']);
  t(semRota.status === 0, 'CN-5: com --sem-rota gera (exit 0)');
  const chCn5 = readFileSync(join(cn5, 'resources/js/Pages/ModuloSemPrototipoXyz/Tela.charter.md'), 'utf8');
  t(/rota: n\/a \(sub-tela de drawer\)/.test(chCn5), 'CN-5: alcance.rota vira n/a COM a razão');
  // `page:` NÃO pode virar "n/a": é required com pattern `^/.*$` no charter.schema.json,
  // e os 293 charters do repo respeitam (medido). Nasceria o 1º charter inválido.
  t(/^page: \/[\w-]/m.test(chCn5), 'CN-5: `page:` continua path válido (schema exige `^/.*$`)');
  t(!/UC-TELA-00/.test(readFileSync(join(cn5, 'resources/js/Pages/ModuloSemPrototipoXyz/Tela.casos.md'), 'utf8')),
    'CN-5: sem rota própria, o UC-00 NÃO é carimbado (não se inventa defeito)');

  // CN-6: MSYS path mangling do Git Bash — `--rota /x` chega como "C:/Program Files/Git/x".
  // Medido de verdade em 2026-08-25, no primeiro bite deste bloco. Ensinar a saída vale
  // mais que "rota inválida", que manda o autor investigar o lugar errado.
  const cn6 = rodar(['ModuloSemPrototipoXyz/Tela', 'PT-01', '--out', tmp(), '--rota', 'C:/Program Files/Git/minha-tela']);
  t(cn6.status === 2, 'CN-6: rota manglada pelo MSYS → exit 2');
  t(/MSYS path mangling/.test(cn6.stderr || ''), 'CN-6: a recusa explica que é o Git Bash, não erro do autor');
  t(/--rota minha-tela/.test(cn6.stderr || ''), 'CN-6: a recusa dá a forma que atravessa o shell');
  // ── CONTRATO DE TELA (5º artefato) — provado contra o GATE REAL, não contra a minha ideia ──
  // Asserir a forma do JSON aqui em cima prova que o gerador escreve o que eu quis escrever.
  // NÃO prova que o `contrato:check` aceita — e um .contract.json que o gate reprova é PIOR que
  // nenhum: o job "Preflight + contratos ativos" varre TODOS os *.contract.json (só EXEMPLO é
  // isento), então um contrato vermelho quebra o CI de quem nem tocou naquela tela.
  // Por isso o bite roda o gate DE FORA, nos DOIS modos que o job roda (§5 2026-07-28 — validar
  // um gate rodando UM dos modos que o CI roda): `--contract` (step "Contratos ativos") e
  // `--map --check` (step "Mapa protótipo→prod"). E com controle positivo: sonda que só sabe
  // dizer "passou" é cega.
  const gate = (args, cwdRoot) => spawnSync(process.execPath,
    [join(ROOT, 'scripts', 'contrato-de-tela.mjs'), '--root', cwdRoot, ...args],
    { cwd: ROOT, encoding: 'utf8' });
  // `--map` lista os contratos por `git ls-files`, que lê o ÍNDICE — `git add` basta, sem commit.
  const indexar = (dir) => {
    spawnSync('git', ['init', '-q'], { cwd: dir, encoding: 'utf8' });
    spawnSync('git', ['add', '-A'], { cwd: dir, encoding: 'utf8' });
  };

  // Ramo 1 — tela COM protótipo: `fonte` aponta pro protótipo declarado.
  const c1 = tmp();
  mkdirSync(join(c1, 'prototipo-ui', 'cowork'), { recursive: true });
  writeFileSync(join(c1, 'prototipo-ui', 'cowork', 'fixtura-page.jsx'), '// protótipo de fixtura\n');
  const g1 = rodar(['Fixtura/MinhaTela', 'PT-01', '--out', c1,
    '--prototipo', 'prototipo-ui/cowork/fixtura-page.jsx', '--rota', 'fixtura']);
  t(g1.status === 0, 'CONTRATO CN-1: geração com protótipo sai limpa (exit 0)');
  const p1 = 'prototipo-ui/contrato/fixtura-minha-tela.contract.json';
  t(existsSync(join(c1, p1)), 'CONTRATO CN-1: o 5º artefato foi escrito no lugar que o gate varre');
  t(JSON.parse(readFileSync(join(c1, p1), 'utf8')).fonte === 'prototipo-ui/cowork/fixtura-page.jsx',
    'CONTRATO CN-1: `fonte` é o protótipo declarado (a mesma âncora que o charter registra)');
  indexar(c1);
  const chk1 = gate(['--contract', p1], c1);
  t(chk1.status === 0, `MORDE(inverso) CN-1: o contrato gerado PASSA no contrato:check — ${(chk1.stdout || '').trim().split('\n').pop()}`);
  const map1 = gate(['--map', '--check'], c1);
  t(map1.status === 0, 'CONTRATO CN-1: passa também no `--map --check` (fonte existe + toda seção ancorada)');

  // Ramo 2 — tela SEM protótipo: `fonte` cai no .tsx (precedente jana-painel), e ainda passa.
  const c2 = tmp();
  rodar(['SemProtoXyz/Painel', 'PT-04', '--out', c2, '--sem-prototipo', 'fixtura', '--rota', 'sem-proto']);
  const p2 = 'prototipo-ui/contrato/sem-proto-xyz-painel.contract.json';
  t(JSON.parse(readFileSync(join(c2, p2), 'utf8')).fonte === 'resources/js/Pages/SemProtoXyz/Painel.tsx',
    'CONTRATO CN-2: sem protótipo, `fonte` cai no .tsx (nunca aponta pra arquivo inexistente)');
  indexar(c2);
  t(gate(['--contract', p2], c2).status === 0, 'CONTRATO CN-2: PT-04 sem protótipo também passa no contrato:check');
  t(gate(['--map', '--check'], c2).status === 0, 'CONTRATO CN-2: e no `--map --check`');

  // CONTROLE POSITIVO 1 — âncora: seção que o .tsx NÃO tem TEM de reprovar.
  // Sem isto, "passou" não distingue gate-que-mede de gate-que-carimba.
  const mau = JSON.parse(readFileSync(join(c1, p1), 'utf8'));
  mau.secoes.push({ id: 'secao-que-nao-existe', copy: [] });
  writeFileSync(join(c1, p1), JSON.stringify(mau, null, 2));
  const bad1 = gate(['--contract', p1], c1);
  t(bad1.status !== 0, 'CONTROLE-POSITIVO: contrato com seção sem âncora REPROVA (exit ≠ 0)');
  t(/sem âncora data-contract/.test(bad1.stdout || ''), 'CONTROLE-POSITIVO: e a recusa NOMEIA a seção órfã');

  // CONTROLE POSITIVO 2 — copy: string que o alvo não tem TEM de reprovar.
  // É o que provaria que pinar placeholder faz o gate morder — a razão de `copy` nascer vazia.
  const mau2 = JSON.parse(readFileSync(join(c2, p2), 'utf8'));
  mau2.secoes[0].copy = ['ESTA COPY NAO EXISTE NO TSX'];
  writeFileSync(join(c2, p2), JSON.stringify(mau2, null, 2));
  const bad2 = gate(['--contract', p2], c2);
  t(bad2.status !== 0 && /copy ausente/.test(bad2.stdout || ''),
    'CONTROLE-POSITIVO: copy que o alvo não tem REPROVA (por isso `copy: []`, não placeholder)');

  // CONTROLE POSITIVO 3 — fonte quebrada: é o modo que só o `--map --check` pega.
  const mau3 = JSON.parse(readFileSync(join(c2, p2), 'utf8'));
  mau3.secoes[0].copy = [];
  mau3.fonte = 'prototipo-ui/cowork/nao-existe.jsx';
  writeFileSync(join(c2, p2), JSON.stringify(mau3, null, 2));
  const bad3 = gate(['--map', '--check'], c2);
  t(bad3.status !== 0 && /fonte aponta arquivo inexistente/.test(bad3.stdout || ''),
    'CONTROLE-POSITIVO: `fonte` inexistente REPROVA no --map --check (o modo que o --contract não vê)');

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

// ── ÂNCORA DE DESIGN: decisão do AUTOR, não default do gerador ───────────────
// Falhar AQUI é barato (o autor está no terminal). Deixar nascer `n/a` cego e
// descobrir depois custa uma tela inteira construída sem olhar a fonte — foi o
// que aconteceu com o Quadro da Forja em 2026-08-09.
const protoIdx = process.argv.indexOf('--prototipo');
const semIdx   = process.argv.indexOf('--sem-prototipo');
const protoFlag = protoIdx >= 0 ? process.argv[protoIdx + 1] : null;
const semFlag   = semIdx   >= 0 ? process.argv[semIdx + 1]   : null;

let protoDecl = null;
if (protoFlag) protoDecl = protoFlag;
else if (semFlag) protoDecl = `n/a (${semFlag})`;
else {
  const candidatos = detectarPrototipoDoModulo(mod);
  if (candidatos.length) {
    console.error(`❌ O módulo ${mod} TEM protótipo — a âncora não pode nascer como "n/a" por default.`);
    console.error('');
    console.error('   Candidato(s) encontrado(s):');
    for (const c of candidatos) console.error(`     • ${c}`);
    console.error('');
    console.error('   Escolha explicitamente (a decisão é sua, não do gerador):');
    console.error(`     --prototipo ${candidatos[0]}`);
    console.error('     --sem-prototipo "<razão pela qual esta tela não herda a fonte>"');
    console.error('');
    console.error('   Por quê: anchor-content-check (required) só valida âncora DECLARADA.');
    console.error('   Sem âncora não há o que validar, o gate fica verde, e a tela nasce');
    console.error('   ignorando um protótipo que existe. Ver proibicoes §5 2026-08-09.');
    process.exit(2);
  }
  // Módulo sem protótipo: segue como antes — `n/a (herda PT-0X)`.
}

// ── ALCANCE: rota é decisão do AUTOR, não `/TODO-rota` cego ──────────────────
// Mesmo desenho da ÂNCORA acima: falhar aqui é barato (o autor está no terminal).
// Deixar `page: /TODO-rota` nascer custa uma tela que responde 200 e ninguém alcança —
// foi o caso de /arquivos em 2026-08-25, pego a olho pelo [W] no smoke do sidebar.
const rotaIdx = process.argv.indexOf('--rota');
const semRotaIdx = process.argv.indexOf('--sem-rota');
const rotaFlag = rotaIdx >= 0 ? process.argv[rotaIdx + 1] : null;
const semRotaFlag = semRotaIdx >= 0 ? process.argv[semRotaIdx + 1] : null;

let alcance = null;
let semRotaRazao = null;

if (rotaFlag) {
  // ⚠️ MSYS path mangling (Git Bash no Windows): `--rota /arquivos` chega aqui como
  // "C:/Program Files/Git/arquivos". Medido em 2026-08-25 no primeiro bite-test deste
  // bloco — o autor no Windows tropeçaria nisso, não é hipótese. Detectar e ensinar a
  // saída vale mais que recusar com "rota inválida", que manda investigar o lugar errado.
  const manglado = /^[A-Za-z]:[/\\]/.test(rotaFlag) || rotaFlag.includes('Program Files');
  if (manglado) {
    const chute = '/' + rotaFlag.split(/[/\\]/).pop();
    console.error(`❌ A rota chegou como caminho de disco: "${rotaFlag}".`);
    console.error('');
    console.error('   Isto é o MSYS path mangling do Git Bash no Windows, não erro seu: ele');
    console.error('   converte um argumento que começa com "/" em caminho absoluto do sistema.');
    console.error('');
    console.error('   Duas saídas, ambas funcionam:');
    console.error(`     --rota ${chute.slice(1)}          (sem a barra — o gerador normaliza)`);
    console.error(`     MSYS_NO_PATHCONV=1 node ... --rota ${chute}`);
    process.exit(2);
  }

  // Aceita com ou sem barra inicial e normaliza. Sem barra é a forma que atravessa o
  // Git Bash intacta, então é a que o autor no Windows vai usar.
  const rotaNormalizada = rotaFlag.startsWith('/') ? rotaFlag : `/${rotaFlag}`;
  if (!/^\/[\w\-/]*$/.test(rotaNormalizada)) {
    console.error(`❌ Rota inválida "${rotaFlag}" — use um path simples (ex: --rota ${kebab(mod)}).`);
    process.exit(2);
  }
  alcance = derivarAlcance(mod, tela, rotaNormalizada);
} else if (semRotaFlag) {
  semRotaRazao = semRotaFlag;
} else {
  const sugestao = `/${kebab(mod)}`;
  console.error('❌ Tela nova sem rota declarada — `page:` não pode nascer como "/TODO-rota".');
  console.error('');
  console.error('   Escolha explicitamente (a decisão é sua, não do gerador):');
  console.error(`     --rota ${sugestao}`);
  console.error('     --sem-rota "<por que esta tela não tem URL própria>"');
  console.error('');
  console.error('   Por quê: nenhum gate lê `page: /TODO-rota`. Tela nasce, responde 200, e');
  console.error('   ninguém a alcança pelo menu — foi o caso de /arquivos (DataController');
  console.error('   com modifyAdminMenu no-op, pego a olho pelo [W] no smoke em 2026-08-25,');
  console.error('   não por gate). A camada de ALCANCE (rota → permission → menu → pacote)');
  console.error('   é a única do ciclo que não é código React, e por isso é invisível.');
  process.exit(2);
}

const paths = gerar({ mod, tela, pt, force, outRoot, protoDecl, alcance, semRotaRazao });

// Aviso GOLDEN-LIVE: se o golden do PT ainda é draft, a tela não FECHA o ciclo (ciclo-completo
// cobra golden live). Não bloqueia a geração — só avisa (o lado Design precisa terminar o golden).
let goldenStatus = 'desconhecido';
try {
  const g = readFileSync(join(ROOT, 'memory/requisitos/_DesignSystem/padroes-tela', ptFile(pt)), 'utf8');
  goldenStatus = (g.match(/^status:\s*(\w+)/mi) || [])[1] || 'desconhecido';
} catch { /* ignore */ }

console.log(`✅ Tela carimbada do ${pt} ${PT_META[pt].nome} — conjunto do ciclo nasceu completo:`);
for (const k of ['tsx', 'charter', 'casos', 'teste', 'contrato']) console.log(`   • ${relOut(paths[k], outRoot)}`);
console.log(`\nPróximos passos:`);
console.log(`   1. Preencha os {/* TODO */} do .tsx (o arquétipo já passa no pt-conformance).`);
console.log(`   2. Complete Mission/Goals/Non-Goals no charter.`);
console.log(`   3. Escreva o UC real no casos.md + troque o test.fixme por asserção.`);
console.log(`   4. Preencha a \`copy\` do .contract.json conforme os TODO virarem texto real —`);
console.log(`      ela nasce VAZIA de propósito (copy literal é decisão [W]); as âncoras`);
console.log(`      \`data-contract\` já estão no .tsx. Confira: npm run contrato:check -- \\`);
console.log(`        ${relOut(paths.contrato, outRoot)}`);
console.log(`   5. Wagner aprova o screenshot → charter sai de draft → live.`);

// ── ALCANCE: as 4 linhas que o gerador NÃO escreve, e sem as quais ninguém chega ──
// Não são geradas de propósito: mexem em arquivo vivo do módulo (rotas + DataController),
// que o gerador não tem licença pra reescrever. Mas ficam ditas em voz alta, senão a
// próxima tela repete o "abri o sistema e não tem nada".
if (alcance) {
  console.log(`\n🚪 ALCANCE — escreva estas 4 à mão (o charter já declara o contrato):`);
  console.log(`   a. rota    → Modules/${mod}/Routes/web.php:  Route::get('${alcance.rota}', ...)->name('${alcance.rota_nome}')`);
  console.log(`   b. gate    → a mesma rota com ->middleware('can:${alcance.permission}')`);
  console.log(`   c. perm    → '${alcance.permission}' em DataController::user_permissions (nasce default false)`);
  console.log(`   d. menu    → DataController::modifyAdminMenu com Menu::modify + url('${alcance.rota}')`);
  console.log(`\n   Golden copiável (as 3 camadas de habilitação, pacote → permission → menu):`);
  console.log(`     Modules/Arquivos/Http/Controllers/DataController.php`);
  console.log(`\n   ⚠️  '${alcance.permission}' nasce FALSE. Mesmo com as 4 linhas acima, o item só`);
  console.log(`      aparece depois de ligar a permission numa função em /roles/{id}/edit — isso é`);
  console.log(`      dado de runtime, nenhum gate cobra, e é onde o "abri e não tem nada" nasce.`);
} else {
  console.log(`\n🚪 ALCANCE — declarado como n/a: "${semRotaRazao}".`);
  console.log(`   O charter registra a decisão e o casos.md NÃO carimba o UC-00 (cobrar "chegue`);
  console.log(`   pelo menu" numa tela sem URL própria seria inventar defeito).`);
}
if (goldenStatus !== 'live') {
  console.log(`\n⚠️  O golden do ${pt} está "${goldenStatus}" (não live): esta tela NÃO fecha o ciclo-completo`);
  console.log(`   até o Design terminar o golden do ${pt} (GOLDEN-LIVE enforcement).`);
}
