#!/usr/bin/env node
/**
 * Codemod R3 · glifo de texto -> ícone lucide, no MESMO tamanho ótico.
 *
 * POR QUE A TABELA DE SITES É EXPLÍCITA (e não um mapa glifo->ícone):
 * a substituição NÃO é derivável do caractere. O mesmo `→` é ícone em
 * "Ver no Board →" (afordância de link) e é TEXTO em "F0 → F3.5",
 * "todo → doing → done", "status_changed → done" (prosa: "de X para Y").
 * Nenhuma regra sintática separa os dois — quem mapear por glifo troca prosa
 * por SVG. Por isso cada site entra medido, com o texto exato ao redor.
 *
 * O QUE O `--measure` RESPONDE (e o reporter não): quantos dos hits que
 * `scripts/governance/replica-inconsistencias.mjs` conta como R3 são de fato
 * UI. Ele classifica cada ocorrência pelo nó AST que a contém — comentário e
 * string de dado não são UI. O dono declarado da regra
 * (`app/Console/Commands/UiLintCommand.php::checkR3`) já pula comentário; o
 * reporter não pula, e é daí que vem a diferença de contagem entre os dois.
 *
 * Modos:
 *   --measure <arquivos...>   classifica os glifos por contexto AST (mede o FP)
 *   --dry-run (default)       mostra arquivo:linha + antes/depois de cada site
 *   --apply                   aplica
 *   --selftest                fixtures provando que as guardas mordem
 *
 * GUARDAS (cada uma aborta antes de escrever — nunca aplica pela metade):
 *   G1 o needle ocorre EXATAMENTE 1x no arquivo
 *   G2 o glifo do needle cai dentro de um nó JSXText (não comentário/string)
 *   G3 o arquivo parseia depois da troca
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
import { parse } from '@babel/parser';
import _traverse from '@babel/traverse';
import MagicString from 'magic-string';

const traverse = _traverse.default ?? _traverse;
const B = 'Modules/Forja/Resources/js/Pages/';

// MESMA classe de caractere do reporter (replica-inconsistencias.mjs, const GLIFOS)
// — copiada de propósito, pra medir o MESMO conjunto que a lista conta.
const GLIFOS = /[←-⇿☀-➿⬀-⯿\u{1F300}-\u{1FAFF}•■-◿]/gu;

const SETA = '→';
const AVISO = '⚠';
const VS16 = '️';

/**
 * Sites curados. `icons` é mesclado no import existente de lucide-react.
 * Exportado porque o teste de identidade (`*.identidade.mjs`) precisa da MESMA
 * tabela pra reverter — duas cópias que deveriam ser iguais são a semente do drift.
 */
export const SITES = [
  {
    file: B + 'ads/Admin/ProjectShow.tsx',
    icons: ['CornerDownRight'],
    needle: '<span>↳ depende de: ',
    repl: '<span className="inline-flex items-center"><CornerDownRight className="h-3 w-3 mr-1" /> depende de: ',
    nota: 'a linha irmã acima já usa <Wallet className="h-3 w-3 mr-1" /> — mesmo idioma do arquivo',
  },
  {
    file: B + 'team-mcp/CcSessions/Index.tsx',
    icons: ['ArrowRight'],
    needle: '>Ver SPEC do watcher ' + SETA + '</a>',
    repl: '>Ver SPEC do watcher <ArrowRight className="inline h-3.5 w-3.5" /></a>',
    nota: 'afordância de link (text-sm 14px -> h-3.5)',
  },
  {
    file: B + 'team-mcp/CcSessions/_components/SessionDrawer.tsx',
    icons: ['ArrowDown', 'ArrowUp'],
    needle: '↓{m.tokens_in ?? 0} ↑{m.tokens_out ?? 0}',
    repl: '<ArrowDown className="inline h-3 w-3" />{m.tokens_in ?? 0} <ArrowUp className="inline h-3 w-3" />{m.tokens_out ?? 0}',
    nota: 'direção de tokens in/out (text-[10px] font-mono -> h-3)',
  },
  {
    file: B + 'team-mcp/CcSessions/_components/SessionDrawer.tsx',
    icons: ['AlertTriangle'],
    needle: AVISO + VS16 + ' Truncado em 500 mensagens.',
    repl: '<AlertTriangle className="inline h-3 w-3 mr-1 -mt-0.5" />Truncado em 500 mensagens.',
    nota: 'aviso (text-xs 12px -> h-3)',
  },
  {
    file: B + 'team-mcp/Scorecard/Index.tsx',
    icons: ['AlertTriangle'],
    needle: AVISO + ' {!facts!.audit_log_present',
    repl: '<AlertTriangle className="inline h-3 w-3 mr-1 -mt-0.5" />{!facts!.audit_log_present',
    nota: 'aviso (text-[11px] -> h-3)',
  },
  {
    file: B + 'team-mcp/Team/Index.tsx',
    icons: ['Settings'],
    needle: '⚙' + VS16 + '\n',
    repl: '<Settings className="h-3.5 w-3.5" />\n',
    nota: 'conteúdo único de <Button variant=ghost size=sm> — engrenagem = Settings',
  },
  ...['Fazendo', 'Revisão', 'Concluído'].map((rotulo) => ({
    file: B + 'team-mcp/Tasks/Index.tsx',
    icons: ['ArrowRight'],
    needle: '>' + SETA + ' ' + rotulo + '</Button>',
    repl: '><ArrowRight className="mr-1 h-3.5 w-3.5" />' + rotulo + '</Button>',
    nota: 'ação em massa (idioma do repo: <Icon className="mr-1 h-3 w-3" />)',
  })),
];

const parseTsx = (src) =>
  parse(src, { sourceType: 'module', plugins: ['typescript', 'jsx'], ranges: true, attachComment: true });

/** Spans dos nós que carregam texto + comentários. */
function spansOf(src) {
  const ast = parseTsx(src);
  const out = [];
  traverse(ast, {
    enter(p) {
      const n = p.node;
      if (n.start == null) return;
      if (n.type === 'JSXText') out.push([n.start, n.end, 'JSXText']);
      else if (n.type === 'StringLiteral') out.push([n.start, n.end, 'StringLiteral']);
      else if (n.type === 'TemplateElement') out.push([n.start, n.end, 'TemplateElement']);
    },
  });
  for (const c of ast.comments || []) out.push([c.start, c.end, 'Comment']);
  return out;
}

/** Categoria do nó MAIS INTERNO que contém o offset. */
function catAt(spans, off) {
  let best = null;
  for (const s of spans) {
    if (off >= s[0] && off < s[1] && (!best || s[1] - s[0] < best[1] - best[0])) best = s;
  }
  return best ? best[2] : 'Outro';
}

const lineAt = (src, off) => src.slice(0, off).split('\n').length;

// ── --measure ─────────────────────────────────────────────────────────────────
function measure(files) {
  const tally = {};
  let total = 0;
  for (const f of files) {
    const src = readFileSync(f, 'utf8');
    let spans;
    try {
      spans = spansOf(src);
    } catch (e) {
      console.error('PARSE FAIL ' + f + ': ' + e.message);
      continue;
    }
    let m;
    GLIFOS.lastIndex = 0;
    while ((m = GLIFOS.exec(src))) {
      const cat = catAt(spans, m.index);
      tally[cat] = (tally[cat] || 0) + 1;
      total++;
    }
  }
  console.log('R3 (regex do reporter) em ' + files.length + ' arquivo(s): ' + total + ' ocorrencia(s)');
  for (const [k, v] of Object.entries(tally).sort((a, b) => b[1] - a[1])) {
    console.log('  ' + String(v).padStart(4) + '  ' + k.padEnd(16) + ((100 * v) / total).toFixed(1) + '%');
  }
  console.log('');
  console.log('  JSXText = candidato a icone. Comment/StringLiteral/TemplateElement = texto.');
  console.log('  Comentario nao e UI (o dono UiLintCommand::checkR3 ja pula comentario), e');
  console.log('  seta em string costuma ser prosa de transicao ("F0 -> F3.5"), nao afordancia.');
  return total;
}

// ── aplicação ─────────────────────────────────────────────────────────────────
export function mergeImport(src, ms, icons) {
  const ast = parseTsx(src);
  let decl = null;
  traverse(ast, {
    ImportDeclaration(p) {
      if (p.node.source.value === 'lucide-react') decl = p.node;
    },
  });
  if (!decl) throw new Error('sem import de lucide-react (este codemod nao cria import novo)');
  const have = decl.specifiers.map((s) => (s.imported ? s.imported.name : s.local.name));
  const add = icons.filter((i) => !have.includes(i));
  if (!add.length) return [];
  const ordenado = have.every((v, i) => i === 0 || have[i - 1].toLowerCase() <= v.toLowerCase());
  const next = ordenado
    ? [...have, ...add].sort((a, b) => a.toLowerCase().localeCompare(b.toLowerCase()))
    : [...have, ...add];
  const first = decl.specifiers[0];
  const last = decl.specifiers[decl.specifiers.length - 1];
  ms.overwrite(first.start, last.end, next.join(', '));
  return add;
}

function run(apply) {
  const byFile = new Map();
  for (const s of SITES) {
    if (!byFile.has(s.file)) byFile.set(s.file, []);
    byFile.get(s.file).push(s);
  }

  let okSites = 0;
  let nFiles = 0;
  for (const [file, sites] of byFile) {
    const src = readFileSync(file, 'utf8');
    const spans = spansOf(src);
    const ms = new MagicString(src);
    const icons = new Set();

    for (const s of sites) {
      const n = src.split(s.needle).length - 1;
      if (n !== 1) {
        throw new Error('G1 ' + file + ': needle ocorre ' + n + 'x (esperado 1) -> ' + JSON.stringify(s.needle.slice(0, 48)));
      }
      const at = src.indexOf(s.needle);
      GLIFOS.lastIndex = 0;
      const gm = GLIFOS.exec(s.needle);
      if (!gm) throw new Error('G2 ' + file + ': needle sem glifo');
      const goff = at + gm.index;
      const cat = catAt(spans, goff);
      if (cat !== 'JSXText') {
        throw new Error('G2 ' + file + ':' + lineAt(src, goff) + ': glifo esta em ' + cat + ', nao JSXText — recusado');
      }
      ms.overwrite(at, at + s.needle.length, s.repl);
      s.icons.forEach((i) => icons.add(i));
      okSites++;
      const show = (t) => t.replace(/\n/g, '\\n').trim().slice(0, 100);
      console.log('  ' + file.replace(B, '') + ':' + lineAt(src, goff) + '  ' + gm[0] + ' -> ' + s.icons.join(' + '));
      console.log('      - ' + show(s.needle));
      console.log('      + ' + show(s.repl));
    }

    const added = mergeImport(src, ms, [...icons]);
    if (added.length) console.log('      import lucide += ' + added.join(', '));

    const out = ms.toString();
    parseTsx(out); // G3
    if (apply) writeFileSync(file, out, 'utf8');
    nFiles++;
  }
  console.log('');
  console.log((apply ? 'APLICADO' : 'DRY-RUN') + ': ' + okSites + ' site(s) em ' + nFiles + ' arquivo(s)');
}

// ── selftest ──────────────────────────────────────────────────────────────────
function selftest() {
  const checks = [];
  const t = (nome, fn) => {
    try {
      fn();
      checks.push([true, nome]);
    } catch (e) {
      checks.push([false, nome + ' :: ' + e.message]);
    }
  };

  const boa = 'const A = () => <a>Ver ' + SETA + '</a>;\n';
  const emComentario = '// prosa: F0 ' + SETA + ' F3.5\nconst A = () => <a>Ver</a>;\n';
  const emString = 'const L = { k: "todo ' + SETA + ' done" };\n';
  const emTemplate = 'const L = `de ' + SETA + ' ate`;\n';

  t('aceita glifo em JSXText (controle POSITIVO)', () => {
    const c = catAt(spansOf(boa), boa.indexOf(SETA));
    if (c !== 'JSXText') throw new Error('classificou como ' + c);
  });
  t('RECUSA glifo em comentario (controle negativo)', () => {
    const c = catAt(spansOf(emComentario), emComentario.indexOf(SETA));
    if (c !== 'Comment') throw new Error('classificou como ' + c);
  });
  t('RECUSA glifo em string de dado (controle negativo)', () => {
    const c = catAt(spansOf(emString), emString.indexOf(SETA));
    if (c !== 'StringLiteral') throw new Error('classificou como ' + c);
  });
  t('RECUSA glifo em template literal (controle negativo)', () => {
    const c = catAt(spansOf(emTemplate), emTemplate.indexOf(SETA));
    if (c !== 'TemplateElement') throw new Error('classificou como ' + c);
  });
  t('G1 conta needle repetido', () => {
    const s = 'x' + SETA + ' y' + SETA;
    if (s.split(SETA).length - 1 !== 2) throw new Error('contagem errada');
  });
  t('mergeImport insere em ordem quando a lista ja e ordenada', () => {
    const src = "import { Bot, User } from 'lucide-react';\n";
    const ms = new MagicString(src);
    mergeImport(src, ms, ['ArrowRight']);
    if (!ms.toString().includes('{ ArrowRight, Bot, User }')) throw new Error('saiu: ' + ms.toString().trim());
  });
  t('mergeImport nao duplica icone ja importado', () => {
    const src = "import { ArrowRight } from 'lucide-react';\n";
    const ms = new MagicString(src);
    const add = mergeImport(src, ms, ['ArrowRight']);
    if (add.length !== 0) throw new Error('duplicou');
  });

  const ruins = checks.filter((c) => !c[0]);
  checks.forEach((c) => console.log('  ' + (c[0] ? 'ok  ' : 'X   ') + c[1]));
  console.log(
    ruins.length
      ? 'X selftest: ' + ruins.length + ' falha(s)'
      : 'ok selftest: ' + checks.length + ' asserts (3 controles negativos + 1 positivo)'
  );
  return ruins.length ? 1 : 0;
}

// CLI só quando executado direto — importar este módulo (o teste de identidade
// faz isso, pra reusar a tabela SITES) não pode disparar nem dry-run nem escrita.
// `pathToFileURL` e nao string: em Windows `file://` + `D:\...` faz a letra do
// drive virar HOST da URL, e a comparacao nunca bate (lapide §5 2026-08-07).
const executadoDireto = Boolean(process.argv[1]) && import.meta.url === pathToFileURL(process.argv[1]).href;
if (executadoDireto) {
  const argv = process.argv.slice(2);
  if (argv[0] === '--selftest') process.exit(selftest());
  else if (argv[0] === '--measure') measure(argv.slice(1));
  else if (argv[0] === '--apply') run(true);
  else run(false);
}
