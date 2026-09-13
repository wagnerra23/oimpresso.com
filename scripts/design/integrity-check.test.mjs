#!/usr/bin/env node
// integrity-check.test.mjs — BITE-TEST dos Testes de Integridade (§15 do PROCESSO_MEMORIA_CC).
//
// POR QUE EXISTE. O integrity-check.mjs roda em CI (design-memory-gate.yml) e o CLAUDE.md §4b
// manda rodá-lo "ao formalizar". Ele é o guarda da espinha da memória — e até 2026-09-13 nada
// provava que ele morde: sem teste, sem `--selftest`. Um guarda de estrutura que ninguém
// exercita é indistinguível de um que carimba verde, e o próprio arquivo carrega a cicatriz
// dessa classe: o IT6 já teve o 3º argumento `true` fixo e "passava" exatamente quando NÃO
// achava o DS, e o IT2 deu PASS sobre 172 de 209 charters durante a migração do #5686.
//
// COMO ELE EXERCITA O CLI DE FORA (e por que precisa de sandbox). O integrity-check deriva
// ROOT de `import.meta.url` — não aceita cwd, argv nem env pra escolher a raiz. Então não dá
// pra sandboxá-lo por diretório de trabalho. A saída é montar um repo-fixture e COPIAR o
// script real pra dentro dele, na MESMA profundidade relativa (scripts/design/), rodando-o
// como subprocesso.
//
//   - A cópia é byte-idêntica e feita em tempo de execução: é o MESMO arquivo, reposicionado.
//     Não é reimplementação (o anti-padrão do §5 2026-08-14, em que o selftest exercitava uma
//     função paralela `scanForTest` e ficava verde enquanto o pipeline regredia).
//   - T0 fecha o único modo de falha dessa técnica: ele lê os imports relativos do script REAL
//     e falha, com mensagem explícita, se aparecer um import que a cópia não levou. Sem T0, um
//     import novo faria o teste quebrar por motivo errado.
//
// O QUE CADA PROVA FIXA. Um caso BOM (árvore sã → exit 0) e, para cada IT, uma mutação
// cirúrgica que quebra SÓ aquele IT. O caso bom é o que impede o "verde que não pode ficar
// vermelho" de virar "vermelho que não pode ficar verde".
//
//   T0  a cópia levou todas as dependências do script real
//   T1  árvore SÃ → exit 0 (controle positivo — sem ele, um gate que reprova tudo passaria)
//   T2  IT1 espinha incompleta (falta MEMORY_INDEX)        → exit 1
//   T3  IT2 charter sem .tsx irmão                         → exit 1
//   T4  IT2 enxerga a 2ª raiz (Modules/<X>/Resources/...)  → exit 1  [regressão do #5686]
//   T5  IT2b `component:` apontando pra arquivo inexistente→ exit 1
//   T6  IT3 STATUS que não aponta pro PROCESSO             → exit 1
//   T7  IT4 LICOES com buraco em L-NN · e com duplicata    → exit 1
//   T8  IT5 benchmark STALE (data velha)                   → exit 1
//   T9  IT6 é ADVISORY: DS ausente vira WARN e NÃO derruba → exit 0
//   T10 IT7 alvo do espinha ausente (link morto)           → exit 1
//
// Node puro, sem deps/rede/DB. Fixtures no tmpdir do Node (nunca "/tmp" literal: §5 2026-08-21
// — no Windows o Bash e o Node resolvem esse caminho pra lugares diferentes).
//
// Rodar: node scripts/design/integrity-check.test.mjs
//   exit 0 = os 8 ITs mordem e liberam certo · exit 1 = alguma prova caiu

import { mkdtempSync, mkdirSync, writeFileSync, rmSync, copyFileSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { execFileSync } from 'node:child_process';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, '../..');
const SCRIPT = join(HERE, 'integrity-check.mjs');

let fails = 0;
const check = (name, cond, extra = '') => {
  console.log(`${cond ? '[OK]' : '[FAIL]'} ${name}${cond ? '' : '  <- ' + extra}`);
  if (!cond) fails++;
};

const hoje = () => new Date().toISOString().slice(0, 10);
const diasAtras = (n) => new Date(Date.now() - n * 86400000).toISOString().slice(0, 10);

// dependencias relativas que a copia leva pro sandbox (origem -> destino relativo a raiz)
const DEPS = [
  ['scripts/design/integrity-check.mjs', 'scripts/design/integrity-check.mjs'],
  ['scripts/qa/page-path.mjs', 'scripts/qa/page-path.mjs'],
];

const TMP = mkdtempSync(join(tmpdir(), 'integrity-bite-'));
const w = (raiz, rel, txt) => {
  const p = join(raiz, rel);
  mkdirSync(dirname(p), { recursive: true });
  writeFileSync(p, txt, 'utf8');
  return p;
};

// monta um repo-fixture SÃO: todos os 8 ITs passam. Cada mutacao parte daqui.
function repoSao(nome) {
  const R = join(TMP, nome);
  for (const [de, para] of DEPS) {
    const dst = join(R, para);
    mkdirSync(dirname(dst), { recursive: true });
    copyFileSync(join(ROOT, de), dst);
  }
  // IT1 + IT3 + IT5 + IT7 — espinha do processo
  w(R, 'memory/reference/prototipo-ui/STATUS.md', '# STATUS\n\nsempre-ler: PROCESSO_MEMORIA_CC.md\n');
  w(R, 'memory/reference/prototipo-ui/PROCESSO_MEMORIA_CC.md',
    `# PROCESSO\n\n## §11 Benchmark\n\n| data | nota |\n|---|---|\n| ${hoje()} | 9 |\n`);
  w(R, 'memory/reference/prototipo-ui/MEMORY_INDEX.md', '# INDEX\n');
  w(R, 'memory/reference/prototipo-ui/PROTOCOL.md', '# PROTOCOL\n');
  w(R, 'memory/reference/prototipo-ui/REGISTRY_DS_COMPONENTES.md', '# REGISTRY\n');
  w(R, 'memory/reference/prototipo-ui/ARQUITETURA.md', '# ARQUITETURA\n');
  // IT4 — L-NN contiguo, sem buraco nem duplicata
  w(R, 'memory/LICOES_CC.md', '# LICOES\n\n## L-01 primeira\n\ntexto\n\n## L-02 segunda\n\ntexto\n');
  // IT6 — DS canonico (espelho) legivel
  for (const f of ['colors_and_type.css', 'styles.css', 'cockpit_domains.css']) {
    w(R, `prototipo-ui/design-system/${f}`, ':root{--fg:oklch(0.2 0 0)}\n');
  }
  // IT2 + IT2b — charter com .tsx irmao e `component:` vivo
  w(R, 'resources/js/Pages/Demo/Index.tsx', 'export default function Index(){return null}\n');
  w(R, 'resources/js/Pages/Demo/Index.charter.md',
    '---\ncomponent: resources/js/Pages/Demo/Index.tsx\n---\n\n# Charter\n');
  return R;
}

// roda a COPIA do CLI real dentro do repo-fixture, de fora, como subprocesso
function run(R, env = {}) {
  const script = join(R, 'scripts/design/integrity-check.mjs');
  try {
    const out = execFileSync(process.execPath, [script], {
      encoding: 'utf8', cwd: R, stdio: ['ignore', 'pipe', 'pipe'],
      env: { ...process.env, ...env },
    });
    return { code: 0, out };
  } catch (e) {
    // stdout E stderr — erro sai por stderr por convencao (§5 2026-08-14)
    return { code: e.status ?? 1, out: (e.stdout || '') + (e.stderr || '') };
  }
}

try {
  // ---- T0 — a copia levou TODAS as dependencias do script real -----------------
  // Fecha o unico modo de falha da tecnica de sandbox: import novo no script real que a
  // copia nao leve faria os testes abaixo quebrarem por motivo errado. Aqui isso vira
  // uma mensagem explicita, em vez de um ERR_MODULE_NOT_FOUND enigmatico.
  {
    const src = readFileSync(SCRIPT, 'utf8');
    const rel = [...src.matchAll(/^import[^'"]*from\s+['"](\.\.?\/[^'"]+)['"]/gm)].map((m) => m[1]);
    const levados = DEPS.map(([de]) => de.replace(/^scripts\/design\//, '').replace(/^scripts\//, '../'));
    const faltando = rel.filter((r) => {
      const abs = resolve(dirname(SCRIPT), r).replace(/\\/g, '/');
      return !DEPS.some(([de]) => abs.endsWith(de.replace(/^scripts\//, 'scripts/')));
    });
    check('T0 a copia leva todas as dependencias relativas do script real',
      faltando.length === 0,
      `import(s) nao copiado(s): ${faltando.join(', ')} — acrescente em DEPS (levados: ${levados.join(', ')})`);
  }

  // ---- T1 — arvore SA sai 0 (controle positivo) --------------------------------
  // Sem este caso, um gate que reprovasse TUDO passaria em todas as provas de mordida.
  {
    const R = repoSao('sao');
    const r = run(R);
    check('T1 arvore SA -> exit 0 (todos os 8 ITs passam)',
      r.code === 0 && /estrutura sa/.test(r.out),
      `code=${r.code} out=${JSON.stringify(r.out.slice(-300))}`);
  }

  // ---- T2 — IT1 espinha incompleta ---------------------------------------------
  {
    const R = repoSao('it1');
    rmSync(join(R, 'memory/reference/prototipo-ui/MEMORY_INDEX.md'));
    const r = run(R);
    check('T2 IT1 morde: espinha sem MEMORY_INDEX -> exit 1',
      r.code === 1 && /\[FAIL\] IT1/.test(r.out) && /MEMORY_INDEX/.test(r.out),
      `code=${r.code} out=${JSON.stringify(r.out.slice(0, 400))}`);
  }

  // ---- T3 — IT2 charter sem .tsx irmao -----------------------------------------
  {
    const R = repoSao('it2');
    w(R, 'resources/js/Pages/Demo/Orfao.charter.md', '---\n---\n\n# lei sem tela viva\n');
    const r = run(R);
    check('T3 IT2 morde: charter sem .tsx irmao -> exit 1',
      r.code === 1 && /\[FAIL\] IT2 /.test(r.out) && /Orfao\.charter\.md/.test(r.out),
      `code=${r.code} out=${JSON.stringify(r.out.slice(0, 400))}`);
  }

  // ---- T4 — IT2 enxerga a 2a RAIZ (Modules/<X>/Resources/js/Pages) --------------
  // Regressao especifica do #5686: a tela migrou pro modulo dono e o IT2 dava PASS sobre
  // 172 de 209 charters — aprovava sem ter olhado os 37 que moram la. Este caso fixa que
  // o charter orfao no modulo dono TAMBEM derruba.
  {
    const R = repoSao('it2-2a-raiz');
    w(R, 'Modules/Demo/Resources/js/Pages/Tela/Vivo.tsx', 'export default function V(){return null}\n');
    w(R, 'Modules/Demo/Resources/js/Pages/Tela/Orfao2.charter.md', '---\n---\n\n# orfao no modulo dono\n');
    const r = run(R);
    check('T4 IT2 enxerga a 2a raiz: charter orfao em Modules/**/Pages -> exit 1',
      r.code === 1 && /Orfao2\.charter\.md/.test(r.out),
      `code=${r.code} — se passou, o IT2 voltou a medir so a raiz do nucleo (regressao #5686). out=${JSON.stringify(r.out.slice(0, 400))}`);
  }

  // ---- T5 — IT2b `component:` morto --------------------------------------------
  // Pergunta DIFERENTE do IT2: "tem tela ao lado?" nao responde "o ponteiro declarado
  // esta vivo?". O irmao .tsx existe de proposito aqui — so o ponteiro e' que mente.
  {
    const R = repoSao('it2b');
    w(R, 'resources/js/Pages/Demo/Ponteiro.tsx', 'export default function P(){return null}\n');
    w(R, 'resources/js/Pages/Demo/Ponteiro.charter.md',
      '---\ncomponent: resources/js/Pages/Nao/Existe.tsx\n---\n\n# ponteiro morto\n');
    const r = run(R);
    check('T5 IT2b morde: `component:` aponta pra arquivo inexistente -> exit 1',
      r.code === 1 && /\[FAIL\] IT2b/.test(r.out) && /Nao\/Existe\.tsx/.test(r.out),
      `code=${r.code} out=${JSON.stringify(r.out.slice(0, 400))}`);
  }

  // ---- T6 — IT3 STATUS nao aponta pro PROCESSO ---------------------------------
  {
    const R = repoSao('it3');
    w(R, 'memory/reference/prototipo-ui/STATUS.md', '# STATUS\n\n(sem ponteiro pro always-read)\n');
    const r = run(R);
    check('T6 IT3 morde: STATUS sem ponteiro pro PROCESSO -> exit 1',
      r.code === 1 && /\[FAIL\] IT3/.test(r.out),
      `code=${r.code} out=${JSON.stringify(r.out.slice(0, 400))}`);
  }

  // ---- T7 — IT4 buraco e duplicata em L-NN -------------------------------------
  {
    const buraco = repoSao('it4-buraco');
    w(buraco, 'memory/LICOES_CC.md', '# LICOES\n\n## L-01 a\n\n## L-03 c\n');   // falta L-02
    const rb = run(buraco);
    check('T7a IT4 morde: buraco em L-NN (L-01, L-03) -> exit 1',
      rb.code === 1 && /\[FAIL\] IT4/.test(rb.out) && /buracos: 2/.test(rb.out),
      `code=${rb.code} out=${JSON.stringify(rb.out.slice(0, 400))}`);

    const dup = repoSao('it4-dup');
    w(dup, 'memory/LICOES_CC.md', '# LICOES\n\n## L-01 a\n\n## L-02 b\n\n## L-02 b-de-novo\n');
    const rd = run(dup);
    check('T7b IT4 morde: L-NN duplicado -> exit 1',
      rd.code === 1 && /\[FAIL\] IT4/.test(rd.out) && /dups: 2/.test(rd.out),
      `code=${rd.code} out=${JSON.stringify(rd.out.slice(0, 400))}`);
  }

  // ---- T8 — IT5 benchmark STALE ------------------------------------------------
  // O IT5 ja foi "verde pra sempre": aceitava linha de QUALQUER data, entao benchmark
  // parado 5+ semanas passava. Este caso fixa a staleness, e o T1 (data de hoje) fixa
  // que medicao fresca LIBERA — os dois lados, senao o teste so provaria metade.
  {
    const R = repoSao('it5');
    w(R, 'memory/reference/prototipo-ui/PROCESSO_MEMORIA_CC.md',
      `# PROCESSO\n\n| data | nota |\n|---|---|\n| ${diasAtras(90)} | 9 |\n`);
    const r = run(R);
    check('T8 IT5 morde: benchmark parado ha 90d -> exit 1 (STALE)',
      r.code === 1 && /\[FAIL\] IT5/.test(r.out) && /STALE/.test(r.out),
      `code=${r.code} out=${JSON.stringify(r.out.slice(0, 400))}`);
  }

  // ---- T9 — IT6 e' ADVISORY: nao derruba ---------------------------------------
  // Prova a assimetria declarada no cabecalho do integrity-check: IT6 reporta, nao falha.
  // Se um dia ele virar duro sem decisao, este caso avisa (e a decisao e' do [W]).
  {
    const R = repoSao('it6');
    rmSync(join(R, 'prototipo-ui/design-system'), { recursive: true, force: true });
    const r = run(R);
    check('T9 IT6 e advisory: DS ausente vira WARN e NAO derruba (exit 0)',
      r.code === 0 && /\[WARN\] IT6/.test(r.out) && /AUSENTE/.test(r.out),
      `code=${r.code} out=${JSON.stringify(r.out.slice(0, 400))}`);
  }

  // ---- T10 — IT7 link morto no espinha -----------------------------------------
  {
    const R = repoSao('it7');
    rmSync(join(R, 'memory/reference/prototipo-ui/REGISTRY_DS_COMPONENTES.md'));
    const r = run(R);
    check('T10 IT7 morde: alvo-git do espinha ausente -> exit 1 (link morto)',
      r.code === 1 && /\[FAIL\] IT7/.test(r.out) && /link morto/.test(r.out),
      `code=${r.code} out=${JSON.stringify(r.out.slice(0, 400))}`);
  }
} finally {
  try { rmSync(TMP, { recursive: true, force: true }); } catch { /* tmp: melhor esforco */ }
}

console.log(fails
  ? `\n${fails} prova(s) caiu(ram) — o integrity-check nao esta mordendo/liberando como o §15 manda.`
  : '\nintegrity-check morde e libera certo (12 provas · arvore sa + 1 mutacao por IT).');
process.exit(fails ? 1 : 0);
