#!/usr/bin/env node
// @ts-check
// ds-lint-alvos.mjs — serve o `component-registry.json` EM TEMPO DE LINT.
//
// POR QUE EXISTE (chip G4 da grade de réguas, dimensão design-to-code, 2026-08-14):
// o registry mapeia `bloco_prototipo → componente_react + import_path`, mas esse dado
// só chegava ao código pelo gerador de tela (`criar-tela.mjs`). No LINT — que é o
// entregador que MORDE (as regras `ds/*` do `eslint.config.js` rodam no ratchet
// required `eslint-gate.yml`) — o alvo canônico era COPIADO à mão dentro da string da
// mensagem. Cópia apodrece: renomeou o componente ou mudou o `import_path`, a mensagem
// segue mandando o dev/agente importar de um path que não existe mais, com cara de canon.
//
// A régua de mercado (Figma Code Connect) entrega o snippet do componente NO MOMENTO em
// que o agente gera o código. Este módulo é o degrau equivalente e barato: a mensagem do
// lint passa a ser DERIVADA do registry, então a âncora fica no COMPONENTE (estável,
// reusável entre telas) e não na cópia da mensagem.
//
// FRONTEIRA HONESTA — o que este módulo NÃO faz:
//   - NÃO fabrica entrada de registry. As 3 entradas `status: gap` trazem a nota literal
//     "não fabricar" e são ignoradas aqui (só `mapped` com `import_path` é servido).
//   - NÃO inventa alvo pra regra que não tem componente substituto. Regra de EIXO DE VALOR
//     (cor/radius/jargão) não tem "componente certo" — a mensagem dela é própria, e isso
//     está DECLARADO em `ALVO_POR_REGRA` com a razão, não escondido.
//   - NÃO cai em texto fixo quando o registry não conhece o componente: `alvo()` LANÇA.
//     Fallback silencioso aqui seria a lápide de 2026-07-29 (instrumento que afirma verde
//     sem ter conseguido medir) — a mensagem sairia afirmando um path que ninguém verificou.
//
// Quem valida o outro lado (o `import_path` existe e exporta o símbolo) é o
// `scripts/governance/component-registry-check.mjs` (advisory). Aqui só se CONSOME.
//
// USO:
//   import { alvo, variantes } from './prototipo-ui/ds-lint-alvos.mjs';   // eslint.config.js
//   node prototipo-ui/ds-lint-alvos.mjs                                   # relatório de cobertura
//   node prototipo-ui/ds-lint-alvos.mjs --json                            # idem, máquina-legível

import { readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

export const REGISTRY_URL = new URL('./component-registry.json', import.meta.url);

/** Lê o registry (default: o canônico ao lado deste arquivo). */
export function lerRegistry(url = REGISTRY_URL) {
  return JSON.parse(readFileSync(url, 'utf8'));
}

/**
 * Constrói os resolvedores sobre UM registry. Recebe o registry por parâmetro de
 * propósito: é o que deixa o selftest provar o controle-negativo (registry sem a
 * entrada → `alvo()` lança) sem mexer no arquivo canônico.
 */
export function criarAlvos(registry) {
  /** @type {Map<string, any>} */
  const mapeados = new Map();
  for (const e of registry.entries || []) {
    // `gap` = bloco do protótipo SEM componente React equivalente. Servir isso seria
    // exatamente a fabricação que o `_doc` do registry proíbe.
    if (e.status !== 'mapped') continue;
    if (!e.componente_react || !e.import_path) continue;
    mapeados.set(e.componente_react, e);
  }

  const buscar = (nome) => {
    const e = mapeados.get(nome);
    if (!e) {
      throw new Error(
        `[ds-lint-alvos] "${nome}" não está mapeado em prototipo-ui/component-registry.json ` +
        `(exige status "mapped" + import_path). A mensagem ds/* que aponta pra ele NÃO pode ser ` +
        `derivada. Conserte o registry (ou a chamada) — não troque por texto fixo: texto fixo ` +
        `apodrece e passa a mandar importar de um path que não existe.`,
      );
    }
    return e;
  };

  return {
    /** `<Button> (@/Components/ui/button)` — o alvo canônico, direto do registry. */
    alvo: (nome) => {
      const e = buscar(nome);
      return `<${e.componente_react}> (${e.import_path})`;
    },
    /** Só o path — pra quem precisa montar a frase de outro jeito. */
    importPath: (nome) => buscar(nome).import_path,
    /**
     * Valores de `variant=` declarados no `variant_map` da entrada, na ordem do registry.
     * Ex Badge → ['default','secondary','outline','success','warning','danger','info','neutral'].
     * Enumerar variante à mão na mensagem é a mesma cópia que apodrece: o dia em que o
     * Badge ganhar uma variante, a mensagem que a lista à mão fica errada em silêncio.
     */
    variantes: (nome) => {
      const vm = buscar(nome).variant_map || {};
      const out = [];
      for (const v of Object.values(vm)) {
        const m = String(v).match(/^variant=(.+)$/);
        if (m && !out.includes(m[1])) out.push(m[1]);
      }
      return out;
    },
    tem: (nome) => mapeados.has(nome),
    nomes: () => [...mapeados.keys()],
    totalEntradas: (registry.entries || []).length,
    totalMapeados: mapeados.size,
  };
}

const CANON = criarAlvos(lerRegistry());

export const alvo = (nome) => CANON.alvo(nome);
export const importPath = (nome) => CANON.importPath(nome);
export const variantes = (nome) => CANON.variantes(nome);
export const temNoRegistry = (nome) => CANON.tem(nome);

/**
 * COBERTURA DECLARADA — qual regra `ds/*` consegue derivar o alvo do registry e qual não.
 *
 * Não é lista de conveniência: é o registro honesto de onde a âncora chega e onde ela não
 * chega. Três estados:
 *   - `derivado` — todo alvo citado na mensagem vem do registry;
 *   - `parcial`  — parte vem do registry, parte é prosa porque o registry não tem a entrada
 *                  (declarado em `razao`, NÃO fabricado);
 *   - `proprio`  — nenhum alvo no registry, e o motivo está em `razao`.
 *
 * As chaves têm que bater 1:1 com a lista `RULES` do `scripts/governance/ds-lint-selftest.mjs`
 * (o selftest assere isso — se alguém adicionar regra ds/* e esquecer daqui, o CI avermelha).
 */
export const ALVO_POR_REGRA = {
  'no-native-radio': {
    cobertura: 'derivado',
    registry: ['RadioGroup', 'Segmented'],
  },
  'no-native-checkbox': {
    cobertura: 'derivado',
    registry: ['Checkbox'],
  },
  'no-native-select': {
    cobertura: 'derivado',
    registry: ['Select'],
  },
  'no-os-btn': {
    cobertura: 'derivado',
    registry: ['Button'],
  },
  'no-handrolled-combobox': {
    cobertura: 'derivado',
    registry: ['Command', 'Popover'],
  },
  'no-handrolled-status-pill': {
    cobertura: 'parcial',
    registry: ['Badge'],
    razao:
      'Badge (+ variantes) vem do registry; o wrapper de domínio <StatusBadge> mora em ' +
      '@/Components/shared e NÃO tem entrada no registry — fica como prosa DECLARADA. ' +
      'Criar a entrada exigiria inventar o bloco_prototipo de proveniência: não fabricar.',
  },
  'no-rounded-xl': {
    cobertura: 'proprio',
    registry: [],
    razao: 'eixo de VALOR (radius) — não existe "componente certo" pra apontar.',
  },
  'no-arbitrary-color': {
    cobertura: 'proprio',
    registry: [],
    razao: 'eixo de VALOR (cor) — o alvo é token semântico, não componente.',
  },
  'no-raw-palette-color': {
    cobertura: 'proprio',
    registry: [],
    razao: 'eixo de VALOR (cor) — o alvo é token semântico, não componente.',
  },
  'no-inline-raw-color': {
    cobertura: 'proprio',
    registry: [],
    razao: 'eixo de VALOR (cor em style inline) — o alvo é var(--token), não componente.',
  },
  'no-db-jargon-in-ui': {
    cobertura: 'proprio',
    registry: [],
    razao: 'eixo de LINGUAGEM (jargão de coluna no texto visível) — sem componente alvo.',
  },
  'no-radix-item-empty-value': {
    cobertura: 'proprio',
    registry: [],
    razao:
      'o alvo é <SafeSelectItem> (@/Components/ui/SafeSelectItem), que NÃO tem entrada no ' +
      'registry — ausência declarada, não fabricada.',
  },
  'no-inline-tablist': {
    cobertura: 'proprio',
    registry: [],
    razao:
      'os alvos <PageHeaderTabs>/<SubNav> moram em @/Components/shared e NÃO têm entrada no ' +
      'registry — ausência declarada, não fabricada.',
  },
};

/** Cobertura MEDIDA da tabela acima (nunca escrita à mão em prosa). */
export function cobertura() {
  const regras = Object.entries(ALVO_POR_REGRA);
  const derivado = regras.filter(([, v]) => v.cobertura === 'derivado');
  const parcial = regras.filter(([, v]) => v.cobertura === 'parcial');
  const proprio = regras.filter(([, v]) => v.cobertura === 'proprio');
  const citadas = [...new Set(regras.flatMap(([, v]) => v.registry))].sort();
  return {
    totalRegras: regras.length,
    derivado: derivado.length,
    parcial: parcial.length,
    proprio: proprio.length,
    comRegistry: derivado.length + parcial.length,
    entradasCitadas: citadas,
    totalEntradasRegistry: CANON.totalEntradas,
    totalMapeados: CANON.totalMapeados,
  };
}

// ── CLI (relatório) ────────────────────────────────────────────────────────────
// Só roda quando invocado direto (`node prototipo-ui/ds-lint-alvos.mjs`); importado pelo
// eslint.config.js fica silencioso.
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  const c = cobertura();
  if (process.argv.includes('--json')) {
    console.log(JSON.stringify(c, null, 2));
  } else {
    console.log('# ds/* × component-registry.json — cobertura MEDIDA\n');
    console.log(`registry: ${c.totalEntradasRegistry} entradas (${c.totalMapeados} mapped servíveis)`);
    console.log(`regras ds/*: ${c.totalRegras}`);
    console.log(`  derivado (alvo 100% do registry): ${c.derivado}`);
    console.log(`  parcial  (alvo + prosa declarada): ${c.parcial}`);
    console.log(`  próprio  (sem componente alvo):    ${c.proprio}`);
    console.log(`  → registry-backed: ${c.comRegistry}/${c.totalRegras} regras`);
    console.log(`  → entradas citadas: ${c.entradasCitadas.length}/${c.totalEntradasRegistry} (${c.entradasCitadas.join(', ')})\n`);
    for (const [r, v] of Object.entries(ALVO_POR_REGRA)) {
      const tag = v.cobertura.padEnd(8);
      const alvos = v.registry.length ? v.registry.map((n) => alvo(n)).join(' · ') : '—';
      console.log(`${tag} ds/${r}`);
      console.log(`         alvo: ${alvos}`);
      if (v.razao) console.log(`         razão: ${v.razao}`);
    }
  }
}
