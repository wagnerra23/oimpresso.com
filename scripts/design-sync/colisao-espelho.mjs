#!/usr/bin/env node
/**
 * Mede colisão de nome entre o espelho do projeto Cowork e o canon do repo.
 *
 * POR QUE EXISTE: `prototipo-ui/design-docs/` é cópia fiel e congelada do projeto de design.
 * Vários arquivos de lá têm o MESMO nome de um arquivo vivo do repo (`LICOES_F3_FINANCEIRO_REJEITADO.md`,
 * `REGISTRY_DS_COMPONENTES.md`, charters de tela). O espelho é namespaced, então não sobrescreve nada —
 * o risco é alguém `git grep` um nome, achar as duas cópias e citar a ARQUIVADA como se fosse canon.
 *
 * Classifica em três baldes, porque o tratamento é diferente:
 *   A — cópia de arquivo DO REPO dentro do projeto de design. A lápide do próprio Cowork
 *       (`design-docs/_arquivo/LAPIDE-DEDUP-2026-06-10.md`) proíbe: "espelho local de arquivo
 *       do repo = PROIBIDO existir … fotocópia que envelhece". Pedido = apagar na origem.
 *   B — doc de design cujo conteúdo já foi aplicado no repo. Pedido = mover pra pasta de
 *       processados que já existe dos dois lados.
 *   C — nome genérico (README/INDEX/SCOPE/SPEC…) que é estrutural no repo. Nada a fazer:
 *       o path desambigua e pedir rename só gera ruído.
 *
 * NÃO EDITA NADA. Mede e reporta.
 *
 * Uso:
 *   # 1) gere a listagem do projeto Cowork (DesignSync list_files) num .json
 *   # 2) rode apontando pra ela:
 *   node scripts/design-sync/colisao-espelho.mjs <listagem.json> [--md]
 *
 * A listagem aceita tanto `{"paths":[...]}` quanto o envelope do tool (`{"content":"{...}"}`).
 * `--md` imprime em markdown (formato do pedido ao [CD]).
 */
import { execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const ESPELHO = 'prototipo-ui/design-docs';
const arg = process.argv[2];
const comoMd = process.argv.includes('--md');

if (!arg || arg.startsWith('--')) {
  console.error('uso: node scripts/design-sync/colisao-espelho.mjs <listagem-do-cowork.json> [--md]');
  console.error('     a listagem vem de DesignSync{method:"list_files"} salvo em arquivo.');
  process.exit(2);
}
if (!fs.existsSync(arg)) { console.error(`listagem não encontrada: ${arg}`); process.exit(2); }

let bruto = JSON.parse(fs.readFileSync(arg, 'utf8'));
if (typeof bruto.content === 'string') bruto = JSON.parse(bruto.content);
const fonte = (bruto.paths || []).filter((p) => p.endsWith('.md'));
if (!fonte.length) { console.error('listagem sem .md — confira o arquivo'); process.exit(2); }

// o que ainda não desceu
const walk = (d) =>
  fs.existsSync(d)
    ? fs.readdirSync(d, { withFileTypes: true }).flatMap((e) => {
        const f = path.join(d, e.name);
        return e.isDirectory() ? walk(f) : e.name.endsWith('.md') ? [f] : [];
      })
    : [];
const local = new Set(walk(ESPELHO).map((f) => f.split(path.sep).join('/').slice(ESPELHO.length + 1)));
const faltam = fonte.filter((p) => !local.has(p)).sort();

// índice do repo por basename, excluindo o próprio espelho
const porNome = new Map();
for (const p of execSync('git ls-files', { encoding: 'utf8', maxBuffer: 1e8 }).split('\n')) {
  if (!p.endsWith('.md') || p.startsWith(ESPELHO + '/')) continue;
  const b = p.split('/').pop();
  if (!porNome.has(b)) porNome.set(b, []);
  porNome.get(b).push(p);
}

const generico = (b, n) => n > 3 || /^(INDEX|README|SCOPE|CONTRACTS|COMPARISON|CRITIQUE|REPORT|SPEC)\.md$/i.test(b);
const A = [], B = [], C = [];
for (const f of faltam) {
  const hits = porNome.get(f.split('/').pop()) || [];
  if (!hits.length) continue;
  if (f.startsWith('_arquivo/repo-mirror/')) A.push({ f, hits });
  else if (generico(f.split('/').pop(), hits.length)) C.push({ f, hits });
  else B.push({ f, hits });
}

if (comoMd) {
  const p = (s) => console.log(s);
  p(`| # | arquivo | balde | já no repo |`);
  p(`|---:|---|---|---|`);
  [...A.map((x) => ({ ...x, b: 'A apagar' })), ...B.map((x) => ({ ...x, b: 'B mover' })), ...C.map((x) => ({ ...x, b: 'C nada' }))]
    .forEach((x, i) => p(`| ${i + 1} | \`${x.f}\` | ${x.b} | \`${x.hits[0]}\`${x.hits.length > 1 ? ` +${x.hits.length - 1}` : ''} |`));
} else {
  console.log(`fonte .md: ${fonte.length} · já no espelho: ${local.size} · faltam: ${faltam.length}`);
  console.log(`colidem: ${A.length + B.length + C.length}  →  A(apagar)=${A.length}  B(mover)=${B.length}  C(nada)=${C.length}`);
  const bloco = (t, arr) => { if (!arr.length) return; console.log(`\n${t}`); arr.forEach(({ f, hits }) => console.log(`  ${f}\n     ↳ ${hits[0]}${hits.length > 1 ? ` (+${hits.length - 1})` : ''}`)); };
  bloco('A · cópia DO REPO no projeto de design (a lápide do Cowork proíbe) — apagar na origem:', A);
  bloco('B · doc já aplicado no repo — mover pra prototipo-ui-patch/_processados/:', B);
  bloco('C · nome genérico, path desambigua — não mexer:', C);
  console.log(`\nsem colisão (podem descer já): ${faltam.length - (A.length + B.length + C.length)}`);
}
