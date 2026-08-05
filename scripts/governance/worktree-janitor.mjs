#!/usr/bin/env node
// Faxineiro de worktrees — classifica worktree MORTO vs VIVO por ORÁCULO, nunca por heurística.
//
// ───────────────────────────────────────────────────────────────────────────
// POR QUE EXISTE (2026-08-04, [W]: "ta na hora de limpa os worktress mortos"
// + "porque isso sempre fica atrazado? não gosto disso" + "posso confiar que
// vai ficar sempre atualizado?"):
//
//   Acumularam **85 worktrees** (69 mortos) e **5 diretórios órfãos** sem
//   registro no git. A limpeza foi feita 100% NA MÃO — varrendo `gh pr list`,
//   `git status` e junctions worktree a worktree. Sem máquina, volta a
//   acumular: era a resposta honesta pro "posso confiar?" — NÃO.
//
//   Regra do sistema (memory/proibicoes.md §Sempre fazer):
//   "LIGUE A MÁQUINA — máquina é sempre melhor que fazer na mão."
//
//   E o efeito colateral que ninguém tinha ligado: o branch `main` LOCAL
//   estava checado out no worktree `.claude/worktrees/ads-parte6`. Git só
//   permite 1 checkout por branch → o repo principal `D:\oimpresso.com`
//   ficava MECANICAMENTE impedido de voltar pro main, e por isso vivia numa
//   branch de codex, stale. Não era indisciplina: era trava. Este script
//   reporta essa trava (§ MAIN-PRESO).
// ───────────────────────────────────────────────────────────────────────────
//
// A LEI DE CLASSIFICAÇÃO — cada veredito vem de um ORÁCULO, não de palpite:
//
//   | pergunta                        | oráculo (NUNCA substituir por heurística)          |
//   |---------------------------------|----------------------------------------------------|
//   | o PR dessa branch fechou?       | `gh pr list --state all` (GitHub sabe; squash-merge |
//   |                                 | deixa a branch "ahead" mesmo depois de mergeada —   |
//   |                                 | contar commit MENTE aqui)                           |
//   | tem sessão viva usando?         | `~/.claude/sessions/<pid>.json` .cwd + PID vivo     |
//   | tem trabalho não salvo?         | `git status --porcelain` + o untracked existe em    |
//   |                                 | origin/main? (log de sessão já mergeado ≠ trabalho) |
//   | tem commit que se perde?        | `git rev-list --count origin/main..HEAD`            |
//   | tem junction pro repo real?     | `lstat().isSymbolicLink()` (Windows: junction)      |
//
// ⛔ TIER 0 — A ARMADILHA DA JUNCTION (memory/proibicoes.md §Ambiente):
//   Deletar worktree com junction `vendor`/`node_modules` ainda presente faz o
//   delete recursivo SEGUIR o link e ESVAZIAR O ALVO REAL do repo principal.
//   Já custou 2×: 2026-05-11 (`vendor/` 318MB → 0B) e 2026-07-14
//   (`node_modules/` ~700 pacotes → 0). O `--force` NÃO é a causa — o `remove`
//   sem flag também segue. Por isso `--prune` aqui:
//     1. remove a junction ANTES (rmdir do LINK, jamais do alvo)
//     2. CONFERE que o alvo real continua populado
//     3. ABORTA TUDO se o alvo encolheu
//
// USO:
//   node scripts/governance/worktree-janitor.mjs            # relatório (advisory, não apaga)
//   node scripts/governance/worktree-janitor.mjs --prune    # remove só os MORTOS provados
//   node scripts/governance/worktree-janitor.mjs --json     # saída máquina
//   node scripts/governance/worktree-janitor.mjs --selftest # bite-test da lógica pura
//
// Advisory por desenho: `--check` nunca falha o CI. Promover a required exigiria
// mordida provada (ADR 0336) — e não há o que morder: isto é higiene, não Tier 0.

import { execFileSync } from 'node:child_process';
import { readdirSync, readFileSync, lstatSync, rmdirSync, readlinkSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { homedir } from 'node:os';
import { fileURLToPath } from 'node:url';

// ── Vereditos ──────────────────────────────────────────────────────────────
export const VIVO = {
  SELF: 'sessão ATUAL roda aqui',
  SESSAO_VIVA: 'sessão Claude Code rodando aqui (PID vivo)',
  PRINCIPAL: 'repo principal',
  LOCKED: 'worktree travado (locked) — alguém marcou de propósito',
  PR_ABERTO: 'PR ainda aberto',
  TRABALHO_NAO_SALVO: 'tem alteração não commitada que só existe aqui',
  COMMIT_SEM_PR: 'tem commit à frente do main e nenhum PR fechado',
  DETACHED_AHEAD: 'detached com commit à frente — apagar torna o commit inalcançável',
};
export const MORTO = {
  PR_FECHADO: 'PR já mergeado/fechado e nada de trabalho aqui',
  BASE_LIMPA: 'detached na base do main, sem nada próprio',
  SUMIU: 'diretório não existe mais',
};

/**
 * Lógica de decisão PURA — testável sem git, sem rede, sem disco.
 * Ordem dos testes é a lei: o que PRESERVA vem antes do que APAGA.
 *
 * @param {object} w
 * @param {boolean} w.isPrincipal   repo principal (não é worktree linkado)
 * @param {boolean} w.isSelf        é o cwd desta sessão
 * @param {boolean} w.temSessaoViva algum PID vivo com cwd aqui
 * @param {boolean} w.locked        git worktree locked
 * @param {boolean} w.existe        diretório existe
 * @param {string|null} w.branch    null = detached
 * @param {string|null} w.prState   MERGED | CLOSED | OPEN | null (sem PR)
 * @param {number} w.ahead          commits à frente de origin/main
 * @param {number} w.sujeiraReal    arquivos alterados que NÃO estão no origin/main
 * @returns {{morto:boolean, razao:string}}
 */
export function classificar(w) {
  if (!w.existe) return { morto: true, razao: MORTO.SUMIU };
  if (w.isPrincipal) return { morto: false, razao: VIVO.PRINCIPAL };
  if (w.isSelf) return { morto: false, razao: VIVO.SELF };
  if (w.temSessaoViva) return { morto: false, razao: VIVO.SESSAO_VIVA };
  if (w.locked) return { morto: false, razao: VIVO.LOCKED };
  if (w.prState === 'OPEN') return { morto: false, razao: VIVO.PR_ABERTO };
  if (w.sujeiraReal > 0) return { morto: false, razao: VIVO.TRABALHO_NAO_SALVO };

  // PR fechado é o oráculo que VENCE a contagem de commits: squash-merge deixa
  // a branch "ahead" mesmo depois de mergeada. Contar commit aqui daria
  // falso-VIVO em massa (medido 2026-08-04: 40+ worktrees mergeados "ahead").
  if (w.prState === 'MERGED' || w.prState === 'CLOSED') {
    return { morto: true, razao: MORTO.PR_FECHADO };
  }
  // Sem PR: commit à frente = trabalho que ninguém publicou. Preserva.
  if (w.ahead > 0) {
    return {
      morto: false,
      razao: w.branch ? VIVO.COMMIT_SEM_PR : VIVO.DETACHED_AHEAD,
    };
  }
  return { morto: true, razao: MORTO.BASE_LIMPA };
}

// ── Coleta (efeitos colaterais isolados abaixo desta linha) ────────────────
const ROOT = process.env.OIMPRESSO_ROOT || process.cwd();

function git(args, cwd = ROOT, timeout = 30000) {
  try {
    return execFileSync('git', args, { cwd, encoding: 'utf8', timeout, stdio: ['ignore', 'pipe', 'pipe'] }).trim();
  } catch {
    return '';
  }
}

/** Sessões Claude Code VIVAS: arquivo por PID + PID de fato vivo. */
export function sessoesVivas() {
  const dir = join(homedir(), '.claude', 'sessions');
  const vivas = [];
  let arquivos = [];
  try { arquivos = readdirSync(dir).filter((f) => f.endsWith('.json')); } catch { return vivas; }
  for (const f of arquivos) {
    try {
      const s = JSON.parse(readFileSync(join(dir, f), 'utf8'));
      if (!s.cwd || !s.pid) continue;
      try { process.kill(s.pid, 0); } catch (e) { if (e.code === 'ESRCH') continue; } // ESRCH = morto; EPERM = vivo
      vivas.push(String(s.cwd).replace(/\\/g, '/').toLowerCase());
    } catch { /* arquivo corrompido não vira veredito */ }
  }
  return vivas;
}

/** Reparse points (junction/symlink) no topo do worktree — a armadilha Tier 0. */
export function junctions(dir) {
  const achados = [];
  for (const nome of ['vendor', 'node_modules']) {
    const p = join(dir, nome);
    try { if (lstatSync(p).isSymbolicLink()) achados.push(p); } catch { /* não existe */ }
  }
  return achados;
}

function estadosDePr() {
  const mapa = new Map();
  try {
    const out = execFileSync('gh', ['pr', 'list', '--state', 'all', '--limit', '900', '--json', 'headRefName,state'],
      { cwd: ROOT, encoding: 'utf8', timeout: 120000 });
    for (const pr of JSON.parse(out)) {
      // primeiro vence: `gh` devolve do mais recente pro mais antigo
      if (!mapa.has(pr.headRefName)) mapa.set(pr.headRefName, pr.state);
    }
  } catch {
    console.error('⚠️  `gh` indisponível — sem o oráculo de PR o faxineiro NÃO classifica morto (fail-safe).');
    return null;
  }
  return mapa;
}

function levantar() {
  git(['fetch', 'origin', 'main', '--quiet'], ROOT, 120000);
  const prs = estadosDePr();
  const vivas = sessoesVivas();
  const eu = process.cwd().replace(/\\/g, '/').toLowerCase();

  const blocos = git(['worktree', 'list', '--porcelain']).split('\n\n').filter(Boolean);
  const out = [];
  for (const bloco of blocos) {
    const path = (bloco.match(/^worktree (.+)$/m) || [])[1];
    if (!path) continue;
    const head = (bloco.match(/^HEAD (.+)$/m) || [])[1] || '';
    const branch = ((bloco.match(/^branch refs\/heads\/(.+)$/m) || [])[1]) || null;
    const locked = /^locked/m.test(bloco);
    const existe = existsSync(path);
    const norm = path.replace(/\\/g, '/').toLowerCase();

    let sujeiraReal = 0;
    if (existe) {
      for (const linha of git(['status', '--porcelain'], path).split('\n').filter(Boolean)) {
        const arquivo = linha.slice(3).trim();
        if (linha.startsWith('??')) {
          // untracked que JÁ está no main não é trabalho — é log de sessão já mergeado
          if (!execSafe(['cat-file', '-e', `origin/main:${arquivo}`])) sujeiraReal++;
        } else {
          sujeiraReal++; // modificação de arquivo trackeado sempre conta
        }
      }
    }

    const ahead = existe && head ? parseInt(git(['rev-list', '--count', `origin/main..${head}`]) || '0', 10) : 0;
    const w = {
      path, branch, head, locked, existe,
      isPrincipal: out.length === 0, // git sempre lista o principal primeiro
      isSelf: norm === eu,
      temSessaoViva: vivas.includes(norm),
      prState: prs && branch ? (prs.get(branch) ?? null) : (prs ? null : 'OPEN'), // sem gh → trata como vivo
      ahead,
      sujeiraReal,
      junctions: existe ? junctions(path) : [],
    };
    out.push({ ...w, ...classificar(w) });
  }
  return out;
}

function execSafe(args) {
  try {
    execFileSync('git', args, { cwd: ROOT, stdio: 'ignore', timeout: 15000 });
    return true;
  } catch { return false; }
}

/**
 * Remove junction com segurança: apaga o LINK, confere o ALVO, aborta se encolheu.
 * O alvo é LIDO do próprio link (`readlink`) — jamais adivinhado por nome, senão
 * a prova de integridade mediria a pasta errada e passaria verde no desastre.
 */
function removerJunctionComProva(link) {
  const alvo = readlinkSync(link);
  const contar = () => { try { return readdirSync(alvo).length; } catch { return -1; } };
  const antes = contar();
  if (antes <= 0) throw new Error(`ABORTADO — alvo ${alvo} de ${link} ilegível/vazio ANTES de mexer.`);
  rmdirSync(link); // rmdir remove o LINK — jamais o conteúdo do alvo
  const depois = contar();
  if (depois !== antes) {
    throw new Error(`ABORTADO — alvo real ${alvo} foi de ${antes}→${depois} ao remover ${link}. NÃO CONTINUE.`);
  }
}

// ── Saída ──────────────────────────────────────────────────────────────────
function main() {
  const args = process.argv.slice(2);
  if (args.includes('--selftest')) return selftest();

  const ws = levantar();
  const mortos = ws.filter((w) => w.morto);
  const vivos = ws.filter((w) => !w.morto);

  if (args.includes('--json')) {
    console.log(JSON.stringify({ total: ws.length, mortos: mortos.length, worktrees: ws }, null, 2));
    return;
  }

  // A trava do main — a causa mecânica do principal viver stale.
  const presoMain = ws.find((w) => !w.isPrincipal && w.branch === 'main');
  if (presoMain) {
    console.log(`\n🔒 MAIN PRESO: o branch \`main\` está checado out em ${presoMain.path}`);
    console.log('   → o repo principal NÃO consegue voltar pro main enquanto isso (git: 1 checkout por branch).');
    console.log('   → é por isso que ele vive numa branch alheia e stale. Libere removendo esse worktree.\n');
  }

  console.log(`Worktrees: ${ws.length} | vivos: ${vivos.length} | mortos: ${mortos.length}`);
  if (mortos.length) {
    console.log('\n── MORTOS ' + '─'.repeat(50));
    for (const w of mortos) {
      const j = w.junctions.length ? `  ⛔ JUNCTION: ${w.junctions.map((x) => x.split(/[\\/]/).pop()).join(', ')}` : '';
      console.log(`  ${w.path}\n      ${w.razao}${j}`);
    }
  }
  console.log('\n── PRESERVADOS ' + '─'.repeat(45));
  for (const w of vivos) console.log(`  ${w.path}\n      ${w.razao}`);

  if (!args.includes('--prune')) {
    if (mortos.length) console.log(`\n→ Para remover os ${mortos.length}: node scripts/governance/worktree-janitor.mjs --prune`);
    return;
  }

  console.log('\n── REMOVENDO ' + '─'.repeat(47));
  let ok = 0;
  for (const w of mortos) {
    try {
      for (const link of w.junctions) {
        console.log(`  junction primeiro: ${link}`);
        removerJunctionComProva(link);
      }
      execFileSync('git', ['worktree', 'remove', '--force', w.path], { cwd: ROOT, stdio: 'ignore', timeout: 120000 });
      console.log(`  ✓ ${w.path}`);
      ok++;
    } catch (e) {
      console.log(`  ✗ ${w.path} :: ${e.message}`);
      if (String(e.message).includes('ABORTADO')) { console.log('\n⛔ PARANDO — integridade do repo real em risco.'); process.exit(2); }
    }
  }
  execFileSync('git', ['worktree', 'prune'], { cwd: ROOT, stdio: 'ignore' });
  console.log(`\nRemovidos: ${ok}/${mortos.length}`);
}

// ── Bite-test: prova que MORDE no caso ruim e NÃO morde no bom ─────────────
function selftest() {
  const base = { existe: true, isPrincipal: false, isSelf: false, temSessaoViva: false, locked: false, branch: 'x', prState: null, ahead: 0, sujeiraReal: 0 };
  const casos = [
    // MORDE (morto)
    ['PR mergeado e limpo', { ...base, prState: 'MERGED' }, true],
    ['PR mergeado AINDA QUE ahead (squash)', { ...base, prState: 'MERGED', ahead: 16 }, true],
    ['PR fechado sem merge, limpo', { ...base, prState: 'CLOSED' }, true],
    ['detached na base do main', { ...base, branch: null, ahead: 0 }, true],
    ['diretório sumiu', { ...base, existe: false }, true],
    // NÃO morde (vivo) — controles negativos, um por razão de preservação
    ['sessão rodando AGORA (mesmo com PR mergeado)', { ...base, prState: 'MERGED', temSessaoViva: true }, false],
    ['é a sessão atual', { ...base, prState: 'MERGED', isSelf: true }, false],
    ['repo principal', { ...base, isPrincipal: true }, false],
    ['travado (locked)', { ...base, prState: 'MERGED', locked: true }, false],
    ['PR aberto', { ...base, prState: 'OPEN' }, false],
    ['trabalho não commitado', { ...base, prState: 'MERGED', sujeiraReal: 4 }, false],
    ['commit à frente sem PR', { ...base, prState: null, ahead: 3 }, false],
    ['detached à frente (commit ficaria inalcançável)', { ...base, branch: null, ahead: 14 }, false],
  ];
  let falhas = 0;
  for (const [nome, w, esperado] of casos) {
    const { morto, razao } = classificar(w);
    const passou = morto === esperado;
    if (!passou) falhas++;
    console.log(`  ${passou ? '✓' : '✗'} ${nome} → ${morto ? 'MORTO' : 'VIVO'} (${razao})`);
  }
  console.log(`\n${casos.length - falhas}/${casos.length} passaram`);
  process.exit(falhas ? 1 : 0);
}

if (process.argv[1] && realpathish(process.argv[1]) === realpathish(fileURLToPath(import.meta.url))) main();
function realpathish(p) { return p.replace(/\\/g, '/').toLowerCase(); }
