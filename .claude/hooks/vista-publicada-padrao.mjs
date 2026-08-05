#!/usr/bin/env node
// vista-publicada-padrao.mjs — PreToolUse:Artifact. ADVISORY (nunca bloqueia).
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// Uma "vista" é página navegável publicada em claude.ai a partir do conhecimento
// que vive em git. Ela é RETRATO DATADO, não documentação canônica. O registro e o
// padrão vivem em memory/reference/VISTAS-PUBLICADAS.md (o dono deste hook).
//
// Este hook cobra o padrão NO MOMENTO DA PUBLICAÇÃO — que é o único chokepoint que
// o fluxo real atravessa. Registro conferido depois apodrece; a lápide §5 de
// 2026-07-09 ("chokepoint fantasma") é sobre acoplar defesa onde nada passa.
//
// Confere, no bloco `<!-- vista: ... -->` do topo do arquivo:
//   1. tema      — a família da vista (agrupa a linhagem)
//   2. estado    — `viva` ou `histórica`
//   3. medido_em — data ISO da MEDIÇÃO (não a da publicação)
//   4. dono      — caminho em git de que a vista deriva; VERIFICA que o path EXISTE
//   5. sucede    — exigido só quando o tema já tem uma viva no registro
//
// ── LIMITE HONESTO (leia antes de confiar) ───────────────────────────────────
// Isto verifica que a vista DECLARA procedência, e que o dono declarado é um path
// vivo. NÃO verifica que a procedência seja verdadeira, nem que os números da página
// batam com o dono. Declaração conferida ≠ conteúdo correto — quem confere conteúdo
// é leitura humana. Um `dono:` que existe mas nada tem a ver com a página passa.
//
// ── POR QUE NASCE ADVISORY, E POR QUE NÃO MEDI FP "ANTES" ────────────────────
// O padrão é NOVO: por construção, 100% das 23 vistas publicadas antes dele
// disparariam. Não é falso-positivo — é dívida de adoção. Por isso é forward-only e
// advisory (gate novo nunca nasce required — ADR 0271/0275/0336). Promover a
// bloqueio só faz sentido depois que o padrão for a norma, e é decisão [W].
//
// Fail-open: qualquer erro/parse-fail → exit 0 (NUNCA trava sessão).
// Selftest: node .claude/hooks/vista-publicada-padrao.test.mjs
//
// Exit: sempre 0 (advisory). O aviso sai em stderr e vira contexto pro Claude.

import { existsSync, readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');
const REGISTRO_REL = 'memory/reference/VISTAS-PUBLICADAS.md';
const CAMPOS = ['tema', 'estado', 'medido_em', 'dono'];

/**
 * NÚCLEO PURO: extrai o bloco `<!-- vista: ... -->` do topo e devolve os pares.
 * Devolve null quando o bloco não existe — que é diferente de existir e estar vazio.
 * @param {string} txt conteúdo do arquivo da vista
 * @returns {Record<string,string>|null}
 */
export function parseBlocoVista(txt) {
  const m = String(txt || '').match(/<!--\s*vista:\s*([\s\S]*?)-->/i);
  if (!m) return null;
  const out = {};
  for (const linha of m[1].split(/\r?\n/)) {
    const kv = linha.match(/^\s*([a-z_]+)\s*:\s*(.*?)\s*$/i);
    if (kv && kv[2] !== '') out[kv[1].toLowerCase()] = kv[2];
  }
  return out;
}

/**
 * NÚCLEO PURO: o tema já tem vista viva no registro? Lê a seção `## Tema: <x>` e
 * procura a marca `**viva**`. Tema ausente do registro = tema novo, não exige sucessão.
 * @param {string} registro conteúdo de VISTAS-PUBLICADAS.md
 * @param {string} tema
 */
export function temaJaTemViva(registro, tema) {
  if (!registro || !tema) return false;
  const alvo = String(tema).trim().toLowerCase();
  const secoes = String(registro).split(/^##\s+Tema:\s*/mi).slice(1);
  for (const s of secoes) {
    const nome = (s.split(/\r?\n/)[0] || '').trim().toLowerCase();
    if (nome === alvo) return /\*\*viva\*\*/i.test(s);
  }
  return false;
}

/**
 * NÚCLEO PURO: aplica o padrão e devolve a lista de queixas (vazia = conforme).
 * @param {{bloco:Record<string,string>|null, donoExiste:(p:string)=>boolean, registro:string}} ctx
 * @returns {string[]}
 */
export function conferirPadrao({ bloco, donoExiste, registro }) {
  if (bloco === null) {
    return [`sem o bloco \`<!-- vista: ... -->\` no topo (campos: ${CAMPOS.join(', ')})`];
  }
  const q = [];
  for (const c of CAMPOS) if (!bloco[c]) q.push(`campo \`${c}\` ausente ou vazio`);

  if (bloco.medido_em && !/^\d{4}-\d{2}-\d{2}$/.test(bloco.medido_em)) {
    q.push(`\`medido_em: ${bloco.medido_em}\` não é data ISO (YYYY-MM-DD)`);
  }
  if (bloco.estado && !/^(viva|hist[oó]rica)$/i.test(bloco.estado)) {
    q.push(`\`estado: ${bloco.estado}\` não é \`viva\` nem \`histórica\``);
  }
  // O ÚNICO check que não é de presença: o dono declarado precisa ser path vivo.
  if (bloco.dono && !donoExiste(bloco.dono)) {
    q.push(`\`dono: ${bloco.dono}\` não existe no repositório — dono morto não ancora nada`);
  }
  if (/^viva$/i.test(bloco.estado || '') && !bloco.sucede && temaJaTemViva(registro, bloco.tema)) {
    q.push(`tema "${bloco.tema}" já tem vista **viva** no registro — declare \`sucede: <url>\` e marque a anterior como histórica (nunca apague)`);
  }
  return q;
}

// ── stdin wrapper (fail-open em TUDO) ────────────────────────────────────────
async function readStdin() {
  const chunks = [];
  for await (const c of process.stdin) chunks.push(c);
  return Buffer.concat(chunks).toString('utf8');
}

(async () => {
  try {
    let raw = '';
    try { raw = await readStdin(); } catch { process.exit(0); }
    if (!raw) process.exit(0);

    let tool = '', input = {};
    try {
      const p = JSON.parse(raw);
      tool = String((p && p.tool_name) || '');
      input = (p && p.tool_input) || {};
    } catch { process.exit(0); }

    if (tool !== 'Artifact') process.exit(0);
    // `list` não publica nada; só `publish` (default quando o campo vem ausente).
    if (input.action && String(input.action) !== 'publish') process.exit(0);

    const fp = String(input.file_path || '');
    if (!fp || !existsSync(fp)) process.exit(0); // sem arquivo pra conferir → cala

    const bloco = parseBlocoVista(readFileSync(fp, 'utf8'));
    const registro = existsSync(join(ROOT, REGISTRO_REL)) ? readFileSync(join(ROOT, REGISTRO_REL), 'utf8') : '';
    const queixas = conferirPadrao({
      bloco,
      registro,
      donoExiste: (p) => existsSync(join(ROOT, p)) || existsSync(p),
    });

    if (queixas.length) {
      console.error(
        `[vista-publicada-padrao] ⚠️  VISTA FORA DO PADRÃO — ${fp}\n` +
        queixas.map((x) => `   · ${x}`).join('\n') +
        `\n\nO padrão e o registro vivem em ${REGISTRO_REL}.\n` +
        `Vista é retrato datado: sem procedência declarada ela vira a segunda documentação.\n` +
        `Publicação NÃO foi bloqueada (advisory) — mas registre a vista lá depois de publicar.`
      );
    }
    process.exit(0);
  } catch { process.exit(0); }
})();
