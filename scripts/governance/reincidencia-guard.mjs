#!/usr/bin/env node
// reincidencia-guard.mjs — git-gate das classes C3/C4 do "Caçador de reincidência"
// (prototipo-ui/PROCESSO_MEMORIA_CC.md §16 · Onda 2 do handoff TAREFA-2).
//
// POR QUE EXISTE: as classes de erro que reincidem no loop Cowork→[CL]→git têm a mesma
// cura — máquina que recusa no instante da ação (NÚCLEO #5). Este guard mecaniza as DUAS
// classes que vivem em artefato git:
//   C3 — CABEÇALHO DE BLOCO FUNDIDO: uma edição funde dois headers numa linha só
//        (heurística `:** > **` na mesma linha) → a fila/handoff vira sopa ilegível.
//   C4 — REF MORTA: citação (link markdown OU path em `code`) a um arquivo que NÃO existe
//        no disco. Órfão/rename de `Modules/<X>` NÃO é aqui — isso é knowledge-drift/
//        ghost-fix (Regra 7 = não duplicar). Este pega o RESTO (memory/, prototipo-ui/,
//        scripts/, docs/, resources/, …).
//   C5 — (carimbo vs-main) DORMENTE: a fila viva (`> … → [CL]` + LINHA D'ÁGUA) é Cowork-only
//        (Tier 1, PROCESSO §14) → não há artefato git pra varrer. Reativar quando a fila
//        estiver no git.
//
// ESCOPO (os artefatos git de fila/handoff — "acima da linha d'água"; backups ignorados):
//   memory/handoffs/**/*.md  ·  prototipo-ui/COWORK_NOTES*.md  ·  prototipo-ui/CODE_NOTES*.md
//
// RATCHET: governance/reincidencia-baseline.json congela as violações LEGADAS; o --check só
// falha em violação NOVA (fora do baseline). Sumir do baseline = só diminui. --write regrava.
//
//   node scripts/governance/reincidencia-guard.mjs            # --check (default): falha se houver NOVA
//   node scripts/governance/reincidencia-guard.mjs --write    # regrava o baseline com o estado atual
//   node scripts/governance/reincidencia-guard.mjs --json     # relatório JSON
//
// Node puro (fs/path). Sem deps, sem DB, sem rede.

import { readdirSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import { join, resolve, dirname, relative } from 'node:path';

const ROOT = process.cwd();
const BASELINE = join(ROOT, 'governance', 'reincidencia-baseline.json');
const WRITE = process.argv.includes('--write');
const JSON_OUT = process.argv.includes('--json');

const SKIP_DIR = /(^|\/)(_BACKUP-NAO-USAR|_arquivo|node_modules|\.git)(\/|$)/;

// ── descobre os arquivos do escopo ──────────────────────────────────────────
function walk(dir, out = []) {
  if (!existsSync(dir)) return out;
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const p = join(dir, e.name);
    const relp = '/' + relative(ROOT, p).replace(/\\/g, '/');
    if (SKIP_DIR.test(relp)) continue;
    if (e.isDirectory()) walk(p, out);
    else if (e.isFile() && e.name.endsWith('.md')) out.push(p);
  }
  return out;
}

function scopeFiles() {
  const files = [];
  walk(join(ROOT, 'memory', 'handoffs'), files); // diretório de handoffs
  const protoDir = join(ROOT, 'prototipo-ui');   // fila/log no git
  if (existsSync(protoDir)) {
    for (const e of readdirSync(protoDir, { withFileTypes: true })) {
      if (e.isFile() && e.name.endsWith('.md') && /^(COWORK_NOTES|CODE_NOTES)(\.|$)/.test(e.name)) {
        files.push(join(protoDir, e.name));
      }
    }
  }
  return [...new Set(files)].sort();
}

// ── C3: cabeçalho de bloco fundido ──────────────────────────────────────────
const C3_RE = /:\*\*\s*>\s*\*\*/; // "...:** > **..." — dois headers fundidos numa linha

// ── C4: ref morta (citação a arquivo inexistente) ───────────────────────────
const MD_LINK_RE = /\[[^\]]*\]\(([^)\s]+)\)/g; // [txt](path)
const CODE_RE = /`([^`\n]+)`/g;                // `path`
const HAS_EXT = /\.[A-Za-z0-9]{1,6}$/;         // termina em .ext (após tirar âncora/linha)

function checkablePath(raw, fromCode) {
  let p = (raw || '').trim();
  if (!p) return null;
  if (/^(https?:|mailto:|tel:|#|www\.)/i.test(p) || p.includes('://')) return null; // URL/âncora
  p = p.replace(/#.*$/, '').replace(/:\d+(?:-\d+)?$/, ''); // tira #âncora e :linha
  if (!p || p.includes(' ') || /[*?{}<>]/.test(p)) return null; // glob/espaço/<placeholder> ≠ arquivo concreto
  if (!p.includes('/')) return null;                          // precisa parecer path
  if (/(^|\/)Modules\/[A-Z]/.test(p)) return null;            // domínio do knowledge-drift/ghost-fix (Regra 7), inclui ../Modules/
  if (fromCode && !HAS_EXT.test(p)) return null;              // `code` só conta se tiver extensão
  return p;
}

// Vivo se resolve em QUALQUER base plausível: refs `./`/`../` são relativas ao arquivo;
// bare-paths em handoff são escritos ora repo-root-relative, ora relativos a memory/ ou
// prototipo-ui/. Tolerância proposital — só acusa quem não existe em NENHUMA (anti falso-positivo).
function citedExists(p, fileDir) {
  const clean = p.replace(/^\//, ''); // "/x" = repo-root-relative
  const bases = (p.startsWith('./') || p.startsWith('../'))
    ? [fileDir]
    : [ROOT, join(ROOT, 'memory'), join(ROOT, 'prototipo-ui'), fileDir];
  return bases.some((b) => existsSync(resolve(b, clean)));
}

function deadRefs(file, txt) {
  const fileDir = dirname(file);
  const dead = new Set();
  const scan = (re, fromCode) => {
    let m;
    while ((m = re.exec(txt)) !== null) {
      const p = checkablePath(m[1], fromCode);
      if (p && !citedExists(p, fileDir)) dead.add(p);
    }
  };
  scan(MD_LINK_RE, false);
  scan(CODE_RE, true);
  return [...dead];
}

// ── varre + coleta violações ────────────────────────────────────────────────
function collect() {
  const viol = [];
  for (const file of scopeFiles()) {
    const rel = relative(ROOT, file).replace(/\\/g, '/');
    const txt = readFileSync(file, 'utf8');
    txt.split(/\r?\n/).forEach((line) => {
      if (C3_RE.test(line)) {
        const snip = line.trim().slice(0, 120);
        viol.push({ key: `${rel}::C3::${snip}`, file: rel, cls: 'C3', detail: snip });
      }
    });
    for (const d of deadRefs(file, txt)) {
      viol.push({ key: `${rel}::C4::${d}`, file: rel, cls: 'C4', detail: d });
    }
  }
  return viol;
}

// ── baseline + veredito ─────────────────────────────────────────────────────
const viol = collect();
const keys = [...new Set(viol.map((v) => v.key))].sort();

if (WRITE) {
  const out = {
    _meta: {
      gate: 'reincidencia-guard — C3 (cabeçalho fundido) + C4 (ref morta) em fila/handoffs git (PROCESSO_MEMORIA_CC §16)',
      generated_at: new Date().toISOString(),
      count: keys.length,
      note: 'Ratchet só-desce. --check falha SÓ em violação nova (fora deste baseline). C5 (carimbo vs-main) dormente — fila viva é Cowork-only (PROCESSO §14). Script: scripts/governance/reincidencia-guard.mjs.',
    },
    violations: keys,
  };
  writeFileSync(BASELINE, JSON.stringify(out, null, 2) + '\n');
  console.log(`✅ Baseline gravado: ${keys.length} violação(ões) congelada(s) → ${relative(ROOT, BASELINE)}`);
  process.exit(0);
}

if (!existsSync(BASELINE)) {
  console.error(`❌ Baseline ausente (${relative(ROOT, BASELINE)}). Rode: node scripts/governance/reincidencia-guard.mjs --write`);
  process.exit(2);
}

const baseline = JSON.parse(readFileSync(BASELINE, 'utf8'));
const baseSet = new Set(baseline.violations || []);
const novas = viol.filter((v) => !baseSet.has(v.key));
const sumiram = (baseline.violations || []).filter((k) => !keys.includes(k));

if (JSON_OUT) {
  console.log(JSON.stringify({ total: viol.length, baseline: baseSet.size, novas, ratchet_down: sumiram.length }, null, 2));
  process.exit(novas.length ? 1 : 0);
}

console.log(`reincidência guard · ${viol.length} violação(ões) no escopo (baseline: ${baseSet.size})`);
if (sumiram.length) console.log(`  ratchet ↓ ${sumiram.length} resolvida(s) — rode --write pra baixar o baseline.`);

if (novas.length) {
  console.error(`\n❌ ${novas.length} violação(ões) NOVA(s) (fora do baseline):\n`);
  for (const v of novas) {
    if (v.cls === 'C3') console.error(`  🔴 C3 cabeçalho fundido · ${v.file}\n        ${v.detail}`);
    else console.error(`  🔴 C4 ref morta · ${v.file} → cita \`${v.detail}\` (não existe no disco)`);
  }
  console.error(`\n  C3: separe os dois blocos em linhas distintas (heurística \`:** > **\`).`);
  console.error(`  C4: corrija o caminho citado ou remova a citação morta. Ver PROCESSO_MEMORIA_CC §16.`);
  console.error(`  Legado conhecido entra no baseline via --write (ratchet só-desce).`);
  process.exit(1);
}

console.log(`✅ Sem violação nova (C3/C4). ${sumiram.length ? `Avançou −${sumiram.length}.` : 'Estável.'}`);
process.exit(0);
