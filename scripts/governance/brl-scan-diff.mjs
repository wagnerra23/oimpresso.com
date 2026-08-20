#!/usr/bin/env node
/**
 * brl-scan-diff — varre as LINHAS ADICIONADAS de um PR procurando valor BRL não-redigido.
 *
 * ── POR QUE ESTA CAMADA EXISTE (o hook não bastava, e isso foi MEDIDO) ──────────
 * `.claude/hooks/block-brl-values-in-memory.mjs` defende a mesma regra desde 2026-07-09.
 * Mesmo assim, 4 commits levaram ~22 linhas com R$ para `memory/` depois disso. A causa
 * foi medida e NÃO é indisciplina:
 *   - Bash escreve sem passar por hook (heredoc, tee, sed -i, python -c)
 *   - dos 56 worktrees do repo, 7 não têm `.claude/settings.json` e 1 não registra o hook
 *     → worktree criado de branch anterior a 09/07 simplesmente NÃO TEM a defesa
 *   - a escape valve OIMPRESSO_BRL_OK foi usada 0 vezes (ninguém driblou de propósito)
 * O hook mora no runtime do agente. Este gate mora no CI: independe de worktree, de
 * runtime e de autor — é a única camada que cobre PR de outra pessoa e commit direto.
 * Comparação que motivou: multi-tenant, PII e segredo têm gate required; dinheiro tinha zero.
 *
 * ── DIFF-ONLY POR CONSTRUÇÃO (não acorda o legado) ─────────────────────────────
 * Varre SÓ linhas `+` de `git diff BASE...HEAD`. O passivo (84 tokens em 27 arquivos)
 * fica intocado — tocar legado em massa é o big-bang que morre no CI (§5 2026-07-12).
 *
 * ── FONTE ÚNICA DO PREDICADO ───────────────────────────────────────────────────
 * Importa `scanBrlLeak` do próprio hook. Hook e gate NUNCA divergem: um conserto no
 * predicado (ex.: a exceção de `R$ 0`) vale nos dois de graça.
 *
 * ── ADVISORY DE NASCENÇA (ADR 0271/0275) ───────────────────────────────────────
 * FP medido na janela de 90 dias: 61 linhas em 24 commits; ~40% vazamento genuíno,
 * ~60% vetor de teste do incidente `num_uf` e mock de protótipo — que nenhum regex
 * separa de dinheiro real. FP esperado 55-60% → alto demais para required. Nasce
 * advisory; promoção exige o bite-log da ADR 0336 DR-2 DEPOIS que o allowlist
 * absorver a classe vetor/mock. Sai exit 1 quando acha (vermelho visível e honesto),
 * mas o job não está em branch protection — não bloqueia merge.
 *
 * Allowlist: `.github/brl-scan-allowlist.txt` (uma substring por linha, `#` = comentário).
 * Precedente literal: `.github/pii-scan-allowlist.txt`.
 *
 * Uso:
 *   node scripts/governance/brl-scan-diff.mjs --base <sha>       # varre o diff
 *   node scripts/governance/brl-scan-diff.mjs --stdin            # varre texto (PR body)
 *   node scripts/governance/brl-scan-diff.mjs --selftest
 */

import { execFileSync, spawnSync } from 'node:child_process';
import { readFileSync, readSync, existsSync, mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { scanBrlLeak } from '../../.claude/hooks/block-brl-values-in-memory.mjs';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const ALLOWLIST = join(ROOT, '.github', 'brl-scan-allowlist.txt');

/** Substrings que isentam a linha (uma por linha do arquivo; `#` comenta). */
export function carregarAllowlist(path = ALLOWLIST) {
  if (!existsSync(path)) return [];
  return readFileSync(path, 'utf8')
    .split('\n')
    .map((l) => l.trim())
    .filter((l) => l && !l.startsWith('#'));
}

/**
 * Arquivos da PRÓPRIA ferramenta — isentos por construção.
 *
 * Precedente literal: o job `pii-scan` do governance-gate.yml exclui
 * `.github/scripts/pii-scan.sh` e `.github/pii-scan-allowlist.txt` do que ele varre.
 * Mesma razão aqui, e ela apareceu na 1ª execução real: o hook e este scanner
 * DOCUMENTAM o predicado com exemplos (`| MRR | R$ [redacted Tier 0] | R$ 5.000,00 |`
 * é o comentário que explica o bypass corrigido). Sem esta isenção o gate acusa a
 * própria documentação — 16 hits, todos falso-positivo.
 *
 * É isenção estreita e nomeada, NÃO allowlist de pasta: só os 4 arquivos que
 * implementam ou configuram a defesa. Qualquer outro arquivo segue varrido.
 */
const ARQUIVOS_DA_FERRAMENTA = [
  '.claude/hooks/block-brl-values-in-memory.mjs',
  'scripts/governance/brl-scan-diff.mjs',
  '.github/brl-scan-allowlist.txt',
  '.github/workflows/brl-scan.yml',
];

export function ehArquivoDaFerramenta(arquivo) {
  const p = String(arquivo || '').replace(/\\/g, '/');
  return ARQUIVOS_DA_FERRAMENTA.includes(p);
}

/** Extrai só as linhas ADICIONADAS de um diff unificado, com o arquivo de origem. */
export function linhasAdicionadas(diffText) {
  const out = [];
  let arquivo = null;
  for (const raw of String(diffText || '').split('\n')) {
    if (raw.startsWith('+++ b/')) { arquivo = raw.slice(6); continue; }
    if (raw.startsWith('+++') || raw.startsWith('---')) continue;
    if (!raw.startsWith('+')) continue;
    out.push({ arquivo, linha: raw.slice(1), isento: ehArquivoDaFerramenta(arquivo) });
  }
  return out;
}

/** As que efetivamente vão ao predicado (tira os arquivos da própria ferramenta). */
export function varriveis(adicionadas) {
  return (adicionadas || []).filter((a) => !a.isento);
}

/** Aplica o predicado do HOOK (fonte única) + allowlist. */
export function acharVazamentos(adicionadas, allow = []) {
  const hits = [];
  for (const { arquivo, linha, isento } of adicionadas) {
    if (isento) continue; // a defesa documenta a si mesma
    if (allow.some((a) => linha.includes(a))) continue;
    // scanBrlLeak espera texto; passamos a linha isolada. Fence não se aplica a
    // linha solta de diff — e isso é DELIBERADO: um fence aberto noutro hunk não
    // deve virar bypass do gate (o hook já tolera fence no fluxo local).
    if (scanBrlLeak(linha).blocked) hits.push({ arquivo, linha: linha.trim() });
  }
  return hits;
}

/** Mascara o valor — o relatório do CI é público no log do Actions. */
export function mascarar(linha) {
  return String(linha).replace(/R\$\s?\d[\d.,]*/g, 'R$ <valor>');
}

/** Espera síncrona — o leitor de stdin é síncrono e não dá pra virar async sem mexer no main. */
function dormirSync(ms) {
  Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, ms);
}

/**
 * Lê o fd 0 até o EOF, tolerando EAGAIN.
 *
 * POR QUE: `readFileSync(0)` não faz retry. Stdin como pipe non-blocking cujo escritor
 * (`printf`, `git log`) ainda não pôs byte devolve EAGAIN e MATA o processo. Medido
 * 2026-08-20 sobre ~2.000 runs deste workflow: ~2-3% caem assim, e o check ficava
 * VERMELHO sem ter medido nada (§5 proibicoes 2026-07-29, na cor oposta) — e vermelho
 * que não significa nada treina o time a ignorar o vermelho que significa.
 *
 * EAGAIN ≠ EOF: fim de entrada é `readSync` devolvendo 0 (ou erro `EOF`, como o Windows
 * sinaliza). EAGAIN = escritor vivo que ainda não escreveu → espera e tenta de novo, com
 * teto; byte lido ZERA o orçamento, então escritor lento não estoura por acumulação.
 * Estourou o teto: LANÇA — nunca devolve ''. Vazio legítimo (PR body em branco) segue
 * distinguível de "não consegui ler": quem chama sai 2 (não medi), jamais 0 (nada a
 * reportar). `read`/`sleep` são parâmetro pra o selftest exercitar ESTA função (a de
 * produção, parametrizada) e não uma cópia paralela — §5 2026-08-14.
 */
export function lerStdinComRetry({ read = readSync, sleep = dormirSync, tetoMs = 5000, passoMs = 5 } = {}) {
  const buf = Buffer.alloc(64 * 1024);
  const partes = [];
  let esperaMs = 0;
  for (;;) {
    let n;
    try {
      n = read(0, buf, 0, buf.length, null);
    } catch (e) {
      if (e && e.code === 'EOF') break; // Windows sinaliza fim de pipe por exceção
      if (e && e.code === 'EAGAIN') {
        if (esperaMs >= tetoMs) {
          throw new Error(`stdin deu EAGAIN por ${tetoMs}ms seguidos sem entregar byte`);
        }
        sleep(passoMs);
        esperaMs += passoMs;
        continue;
      }
      throw e;
    }
    if (n === 0) break;
    esperaMs = 0;
    partes.push(Buffer.from(buf.subarray(0, n)));
  }
  return Buffer.concat(partes).toString('utf8');
}

function selftest() {
  const casos = [
    ['acha valor em linha adicionada', '+++ b/memory/x.md\n+custo R$ 1.234,56', 1],
    ['IGNORA linha removida', '+++ b/memory/x.md\n-custo R$ 1.234,56', 0],
    ['IGNORA linha de contexto', '+++ b/memory/x.md\n custo R$ 1.234,56', 0],
    ['IGNORA cabecalho +++', '+++ b/memory/R$ 1.txt\n+ok', 0],
    ['R$ 0 NAO acusa (exceção de zero, herdada do hook)', '+++ b/memory/x.md\n+total R$ 0 hoje', 0],
    ['redacted NAO acusa', '+++ b/memory/x.md\n+valor R$ [redacted Tier 0]', 0],
    ['acha em .yaml tambem', '+++ b/memory/g/x.yaml\n+t: R$ 999,00', 1],
    ['acha FORA de memory/ (gate cobre o repo, nao so memory)', '+++ b/README.md\n+preco R$ 50,00', 1],
    ['multiplas linhas', '+++ b/a.md\n+R$ 1,00\n+ok\n+R$ 2,00', 2],
    // A defesa documenta a si mesma — sem isto o gate acusa o próprio comentário.
    // Achado na 1ª execução real (16 FP, todos comentário do hook/scanner).
    ['ISENTA o hook (documenta o predicado)', '+++ b/.claude/hooks/block-brl-values-in-memory.mjs\n+// exemplo: R$ 5.000,00', 0],
    ['ISENTA o proprio scanner', '+++ b/scripts/governance/brl-scan-diff.mjs\n+// exemplo: R$ 1,00', 0],
    ['ISENTA o allowlist e o workflow', '+++ b/.github/brl-scan-allowlist.txt\n+R$ 9,99', 0],
    ['NAO isenta outro arquivo de .claude/hooks', '+++ b/.claude/hooks/outro.mjs\n+R$ 9,99', 1],
  ];
  let ok = 0;
  const falhas = [];
  for (const [nome, diff, esperado] of casos) {
    const n = acharVazamentos(linhasAdicionadas(diff), []).length;
    if (n === esperado) ok++;
    else falhas.push(`  x ${nome}: esperado ${esperado}, veio ${n}`);
  }
  // allowlist isenta
  const comAllow = acharVazamentos(linhasAdicionadas('+++ b/a.md\n+fixture R$ 9,99 # brl-allowlist'), ['# brl-allowlist']);
  if (comAllow.length === 0) ok++; else falhas.push('  x allowlist deveria isentar');
  // mascaramento nao vaza
  if (!/1\.234/.test(mascarar('custo R$ 1.234,56'))) ok++;
  else falhas.push('  x mascarar() deixou o valor passar');
  // fonte unica: o predicado vem MESMO do hook
  if (typeof scanBrlLeak === 'function' && scanBrlLeak('R$ 5,00').blocked) ok++;
  else falhas.push('  x predicado do hook nao importado');

  // ── E2E da guarda anti-vácuo (sandbox git real, CLI de fora) ────────────────
  // NÃO é assert de helper puro: a guarda vive no fluxo do CLI, e testar um helper
  // exportado provaria a função, não o contrato do pipeline (§5 LC-15). Por isso
  // sandbox por cwd + `spawnSync` do próprio script, lendo o exit code.
  const e2e = [];
  const gitS = (cwd, ...a) => spawnSync('git', a, { cwd, encoding: 'utf8' });
  let sandbox = '';
  try {
    sandbox = mkdtempSync(join(tmpdir(), 'brl-vacuo-'));
    gitS(sandbox, 'init', '-q');
    gitS(sandbox, 'config', 'user.email', 't@t');
    gitS(sandbox, 'config', 'user.name', 't');
    writeFileSync(join(sandbox, 'a.txt'), 'linha um\nlinha dois\n');
    gitS(sandbox, 'add', '-A');
    gitS(sandbox, 'commit', '-q', '-m', 'base');
    const base = gitS(sandbox, 'rev-parse', 'HEAD').stdout.trim();

    // (a) SUBTRAÇÃO PURA — remove o arquivo: 0 adições, mas HÁ remoção → libera
    rmSync(join(sandbox, 'a.txt'));
    gitS(sandbox, 'add', '-A');
    gitS(sandbox, 'commit', '-q', '-m', 'remove tudo');
    const rmOnly = spawnSync(process.execPath, [fileURLToPath(import.meta.url), '--base', base], { cwd: sandbox, encoding: 'utf8' });
    if (rmOnly.status === 0) ok++;
    else e2e.push(`  x subtração pura deveria LIBERAR (exit 0), veio ${rmOnly.status}`);

    // (b) CEGO de verdade — commit vazio: 0 adições E 0 remoções, HEAD != base → morde
    const base2 = gitS(sandbox, 'rev-parse', 'HEAD').stdout.trim();
    gitS(sandbox, 'commit', '-q', '--allow-empty', '-m', 'vazio');
    const vazio = spawnSync(process.execPath, [fileURLToPath(import.meta.url), '--base', base2], { cwd: sandbox, encoding: 'utf8' });
    if (vazio.status === 2) ok++;
    else e2e.push(`  x diff totalmente vazio deveria MORDER (exit 2), veio ${vazio.status}`);

    // (c) BINÁRIO-ONLY — 0 linha de TEXTO, mas o diff existe → libera (2026-08-17).
    // Byte NUL no conteúdo é o que faz o git classificar como binário; sem ele o
    // arquivo entra como texto e o caso não testaria nada. O controle é o (b) logo
    // acima: lá o numstat vem VAZIO (cegueira real) e continua mordendo.
    const base3 = gitS(sandbox, 'rev-parse', 'HEAD').stdout.trim();
    writeFileSync(join(sandbox, 'fonte.woff2'), Buffer.from([0x77, 0x4f, 0x46, 0x32, 0x00, 0x01, 0x00, 0x00]));
    gitS(sandbox, 'add', '-A');
    gitS(sandbox, 'commit', '-q', '-m', 'so binario');
    const bin = spawnSync(process.execPath, [fileURLToPath(import.meta.url), '--base', base3], { cwd: sandbox, encoding: 'utf8' });
    if (bin.status === 0 && /BINÁRIOS/.test(bin.stdout)) ok++;
    else e2e.push(`  x binário-only deveria LIBERAR (exit 0) declarando o motivo, veio ${bin.status}`);
  } catch (e) {
    e2e.push(`  x sandbox E2E falhou: ${e.message}`);
  } finally {
    if (sandbox) { try { rmSync(sandbox, { recursive: true, force: true }); } catch { /* ignore */ } }
  }
  falhas.push(...e2e);

  // ── BITE-TEST do leitor de stdin (o caminho que morria de EAGAIN) ──────────
  // Exercita a função DE PRODUÇÃO parametrizada (§5 2026-08-14: parâmetro, nunca cópia).
  // LIMITE declarado: injetando o `read` isto prova a lógica de retry, NÃO o O_NONBLOCK do
  // runner — Node não expõe fcntl. O fd non-blocking REAL foi reproduzido à mão num Linux
  // (CT 100; recibo no corpo do PR) e o pipe real fica no E2E abaixo, que roda o CLI de fora.
  const eagain = () => Object.assign(new Error('EAGAIN'), { code: 'EAGAIN' });
  const eof = () => Object.assign(new Error('EOF'), { code: 'EOF' });
  // seq(...): fabrica um `read`. String = entrega bytes; função = lança; fim da lista = EOF.
  const seq = (...passos) => { let i = 0; return (_fd, b) => {
    const passo = passos[i++];
    if (typeof passo === 'function') throw passo();
    return passo === undefined ? 0 : b.write(passo, 0, 'utf8');
  }; };
  // esperado null = tem que LANÇAR: é o controle negativo, e o ponto inteiro do conserto
  // — "não consegui ler" ≠ "nada a reportar".
  for (const [nome, read, esperado] of [
    ['EAGAIN transitório entrega o texto', seq(eagain, eagain, eagain, 'novo'), 'novo'],
    ['EOF imediato = vazio LEGÍTIMO (PR body em branco)', seq(), ''],
    ['EOF-por-exceção (Windows) preserva o já lido', seq('meio', eof), 'meio'],
    ['EAGAIN eterno LANÇA, jamais devolve vazio', () => { throw eagain(); }, null],
  ]) {
    let veio; let erro = null;
    try { veio = lerStdinComRetry({ read, sleep: () => {}, tetoMs: 100 }); } catch (e) { erro = e; }
    if (esperado === null ? !!erro : (!erro && veio === esperado)) ok++;
    else falhas.push(`  x ${nome}: veio ${erro ? `erro ${erro.message}` : JSON.stringify(veio)}`);
  }
  // E2E do CLI com pipe REAL — prova a fiação `--stdin` → leitor → predicado.
  const eu = fileURLToPath(import.meta.url);
  for (const [nome, entrada, esperado] of [
    ['--stdin com texto limpo deveria sair 0', 'sem valor aqui', 0],
    ['--stdin com valor deveria sair 1', 'custo de R' + '$ 1.234,56', 1],
  ]) {
    const r = spawnSync(process.execPath, [eu, '--stdin'], { input: entrada, encoding: 'utf8' });
    if (r.status === esperado) ok++;
    else falhas.push(`  x ${nome}, veio ${r.status}`);
  }

  const total = casos.length + 6 + 6;
  console.log(`brl-scan-diff selftest: ${ok}/${total}`);
  if (falhas.length) { console.error(falhas.join('\n')); process.exit(1); }
  console.log('  ACHA: linha + com valor, em qualquer extensao, dentro e fora de memory/');
  console.log('  IGNORA: linha removida/contexto, R$ 0, redacted, allowlist');
  process.exit(0);
}

function main() {
  const argv = process.argv.slice(2);
  if (argv.includes('--selftest')) selftest();

  const allow = carregarAllowlist();
  let adicionadas = [];

  if (argv.includes('--stdin')) {
    // PR body / commit subjects.
    //
    // O texto vai INTEIRO ao scanBrlLeak, não linha a linha — senão o fence nunca
    // fecha e a whitelist de bloco de código (que o hook honra desde sempre) fica
    // morta aqui. Sem isso, um PR que DOCUMENTA o predicado dispara o predicado:
    // aconteceu neste próprio PR, com `R$ 0,50` citado como exemplo do que bloqueia.
    // Mesmo trade-off que o hook já aceita: exemplo didático vai em ``` … ```.
    //
    // A LEITURA vai pelo `lerStdinComRetry`: `readFileSync(0)` morria de EAGAIN em
    // ~2-3% das runs (medido 2026-08-20) sem ter varrido nada. Ver o docblock dele.
    let txt;
    try {
      txt = lerStdinComRetry();
    } catch (e) {
      console.error(`brl-scan-diff: NÃO consegui LER o stdin (${e.message}).`);
      console.error('Isto é ausência de MEDIÇÃO, não ausência de valor BRL no texto.');
      console.error('Por isso exit 2 (não medi), nunca exit 0 (nada a reportar) — §5 2026-07-29.');
      process.exit(2);
    }
    const r = scanBrlLeak(txt);
    const allowTxt = carregarAllowlist();
    if (r.blocked && !allowTxt.some((a) => r.line.includes(a))) {
      console.error('\n1 linha com valor BRL não-redigido no texto (valor MASCARADO):\n');
      console.error(`  linha ${r.lineNumber}: ${mascarar(r.line)}`);
      console.error('\nSe for EXEMPLO didático, ponha em bloco de código (``` … ```) — igual ao hook.');
      console.error('Se for valor real: R$ [redacted Tier 0], ou comunique fora-banda.');
      console.error('\nADVISORY: não bloqueia merge (ADR 0271/0275).');
      process.exit(1);
    }
    console.log(`brl-scan-diff: texto varrido (${txt.split('\n').length} linhas); fence respeitado.`);
    console.log('OK — nenhum valor BRL não-redigido.');
    process.exit(0);
  }
  {
    const i = argv.indexOf('--base');
    const base = i >= 0 ? argv[i + 1] : null;
    if (!base) {
      console.error('brl-scan-diff: falta --base <sha> (ou --stdin). Falha visível, não exit 0 silencioso.');
      process.exit(2);
    }
    // O base tem que ser ALCANÇÁVEL. Num checkout shallow ele não é, e aí o
    // `git diff` devolve vazio SEM erro — o gate reportaria "0 linhas varridas"
    // e sairia 0. Falso-verde: parece que varreu, não varreu nada.
    // Achado testando este próprio script (2026-07-28). "Gate mudo é pior que gate
    // ausente, porque parece cobertura."
    try {
      execFileSync('git', ['cat-file', '-e', `${base}^{commit}`], { stdio: 'ignore' });
    } catch {
      console.error(`brl-scan-diff: base ${base} NÃO é alcançável neste checkout.`);
      console.error('Provável causa: clone shallow. No CI use `fetch-depth: 0`.');
      console.error('Falha VISÍVEL de propósito — exit 0 aqui seria falso-verde.');
      process.exit(2);
    }

    let diff;
    try {
      diff = execFileSync('git', ['diff', '--unified=0', `${base}...HEAD`], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
    } catch (e) {
      console.error(`brl-scan-diff: git diff falhou (${e.message}). Falha visível.`);
      process.exit(2);
    }
    adicionadas = linhasAdicionadas(diff);

    // Diff vazio com HEAD != base é sinal de instrumento quebrado, não de PR limpo.
    // Conta o TOTAL adicionado (inclui isentos) — um PR que só toca a própria
    // ferramenta é legítimo e teria 0 varríveis sem estar cego.
    if (adicionadas.length === 0) {
      // SUBTRAÇÃO PURA não é cegueira. A premissa original ("um PR com commits sempre
      // tem linha adicionada") é FALSA pra PR que só REMOVE — e não é hipótese: o
      // PR #5300 (aposenta 2 scripts de deploy one-shot; dois `git rm`, zero adições)
      // reprovou aqui em 2026-08-05 sem ter valor monetário nenhum.
      //
      // O discriminador é o PRÓPRIO diff: se há linha REMOVIDA, o `git diff` produziu
      // saída — o instrumento enxergou. Cego é diff TOTALMENTE vazio (nem + nem −), que
      // é o sintoma real de base inalcançável/shallow. E o risco que este gate existe
      // pra pegar é INTRODUÇÃO de valor: linha removida não introduz nada.
      //
      // Cuidado no regex: `--- a/arquivo` também começa com `-`; só conta o `-` que
      // NÃO seja o cabeçalho `---`.
      const temRemocao = /^-(?!--)/m.test(diff);
      let head = '';
      try { head = execFileSync('git', ['rev-parse', 'HEAD'], { encoding: 'utf8' }).trim(); } catch { /* ignore */ }

      // BINÁRIO-ONLY é o irmão da subtração pura, e caiu na mesma armadilha em
      // 2026-08-17: o PR #5863 adiciona 4 `.woff2` e NADA de texto. `git diff` de
      // binário não emite linha `+` nem `-` — some assim como o diff de base
      // inalcançável, e o gate reprovava um PR que ele não tinha o que varrer.
      // Discriminador: `--numstat` emite uma linha POR arquivo tocado, e binário sai
      // como `-\t-\tpath`. Se veio ≥1 linha e TODAS são binárias, o instrumento
      // ENXERGOU o diff — só não há texto pra ler. Zero linha de numstat continua
      // sendo cegueira de verdade e cai no ramo de baixo.
      let numstat = '';
      try {
        numstat = execFileSync('git', ['diff', '--numstat', `${base}...HEAD`], { encoding: 'utf8', maxBuffer: 16 * 1024 * 1024 });
      } catch { /* sem numstat, cai no comportamento antigo */ }
      const linhasNumstat = numstat.split(/\r?\n/).filter((l) => l.trim());
      const soBinario = linhasNumstat.length > 0 && linhasNumstat.every((l) => /^-\t-\t/.test(l));

      if (temRemocao) {
        console.log('brl-scan-diff: 0 linha(s) adicionada(s), mas HÁ remoções — PR de SUBTRAÇÃO pura.');
        console.log('Nada a varrer: linha removida não introduz valor. Instrumento OK (o diff produziu saída).');
      } else if (soBinario) {
        console.log(`brl-scan-diff: 0 linha(s) de texto, e os ${linhasNumstat.length} arquivo(s) do diff são BINÁRIOS.`);
        console.log('Nada a varrer: valor BRL é padrão de TEXTO. Instrumento OK (o numstat enxergou o diff).');
      } else if (head && !head.startsWith(String(base).slice(0, 7))) {
        console.error(`brl-scan-diff: diff VAZIO entre ${String(base).slice(0, 10)} e HEAD — suspeito.`);
        console.error('Nem adição nem remoção, com HEAD != base: instrumento provavelmente cego (base inalcançável/shallow).');
        process.exit(2);
      }
    }
  }

  const hits = acharVazamentos(adicionadas, allow);
  const nIsentas = adicionadas.filter((a) => a.isento).length;
  console.log(`brl-scan-diff: ${adicionadas.length} linha(s) adicionada(s); ${adicionadas.length - nIsentas} varrida(s), ${nIsentas} isenta(s) (arquivos da própria ferramenta); allowlist com ${allow.length} entrada(s).`);

  if (!hits.length) {
    console.log('OK — nenhum valor BRL não-redigido nas linhas novas.');
    process.exit(0);
  }

  console.error(`\n${hits.length} linha(s) com valor BRL não-redigido (valor MASCARADO abaixo):\n`);
  for (const h of hits) console.error(`  ${h.arquivo}: ${mascarar(h.linha)}`);
  console.error(`
REGRA (memory/proibicoes.md, Tier 0): valores monetários NUNCA vão pra git.
Só Wagner/Eliana veem valores; o resto do time vê escopo e contagem, não R$.
Reincidência já custou 'git filter-repo' em 5.033 commits + force-push em main.

COMO RESOLVER:
  - troque pelo sentinela:  R$ [redacted Tier 0]
  - ou comunique fora-banda (chat direto), não no git
  - contagens/escopo (108 subs, 1311 invoices) são OK — o gate só pega R$<número>
  - vetor de teste/fixture legítimo: adicione a substring em .github/brl-scan-allowlist.txt

ADVISORY: este job NÃO bloqueia merge (ADR 0271/0275 — gate novo nasce advisory).
Vermelho aqui é sinal honesto, não trava.`);
  process.exit(1);
}

main();
