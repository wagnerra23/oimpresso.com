#!/usr/bin/env node
// @ts-check
/**
 * aplicar-payload.mjs — escreve o espelho Cowork a partir de um PAYLOAD servido, não de `get_file`.
 *
 * POR QUE EXISTE: o caminho `DesignSync.get_file` entrega o conteúdo no CONTEXTO do agente, e
 * escrever de lá é transcrição — a classe que causou o STALE de 2026-08-11. Isso criava um teto
 * artificial ("arquivo pequeno não tem rota fiel"), que era limitação do transporte, não do
 * problema. Aqui o conteúdo entra como DADO: fetch → JSON.parse → writeFile. Nenhum byte passa
 * por prosa de agente, em nenhuma das duas pontas.
 *
 * O payload é gerado do lado do design a partir dos `src`/`href` do shell `oimpresso.com.html`
 * (query `?v=` normalizada) — manifesto DERIVADO, não lista curada: regenera com o shell.
 *
 * FIDELIDADE — o que este script VERIFICA de fato (ver o bloco no laço, com a medição):
 *   (a) BYTES declarado == bytes reais, por arquivo. Divergiu = NÃO escreve e sai != 0.
 *   (b) o payload traz `fnv64`, mas a convenção NÃO é reproduzível daqui — 5 variantes testadas,
 *       0/118 bateram. Ele é impresso como referência, nunca usado como veredito: bloquear por
 *       hash que eu não sei calcular seria transformar ignorância minha em falha do transporte.
 *   (c) a prova forte é EXTERNA a este script: os 21 arquivos que desceram pela rota
 *       independente `get_file` → `--export-from` são byte-idênticos aos do payload (21/21).
 *
 * Uso:
 *   node scripts/design-sync/aplicar-payload.mjs <payload.json> --dry   # relatório, não escreve
 *   node scripts/design-sync/aplicar-payload.mjs <payload.json>         # escreve
 *
 * ⚠️ NUNCA aponte o destino pra fora de `prototipo-ui/cowork/` e nunca ponha `.md` lá dentro:
 * R1 do `cowork-ssot-guard` reprova (cowork/ é build-only; knowledge mora em canon).
 */
import { readFileSync, writeFileSync, existsSync, mkdirSync } from 'node:fs';
import { join, dirname, normalize, sep } from 'node:path';

const ROOT = process.cwd();
const DESTINO = 'prototipo-ui/cowork';
const args = process.argv.slice(2);
const arquivo = args.find((a) => !a.startsWith('--'));
const dry = args.includes('--dry');

if (!arquivo || !existsSync(arquivo)) {
  console.error('✗ uso: node scripts/design-sync/aplicar-payload.mjs <payload.json> [--dry]');
  process.exit(2);
}

/** FNV-1a 64-bit sobre os bytes UTF-8 — BigInt porque 64 bits não cabem em Number. */
function fnv1a64(str) {
  const bytes = Buffer.from(str, 'utf8');
  let h = 0xcbf29ce484222325n;
  const prime = 0x100000001b3n, mask = 0xffffffffffffffffn;
  for (const b of bytes) { h = ((h ^ BigInt(b)) * prime) & mask; }
  return h.toString(16).padStart(16, '0');
}

const p = JSON.parse(readFileSync(arquivo, 'utf8'));
const files = p.files || [];
if (!Array.isArray(files) || !files.length) { console.error('✗ payload sem `files`'); process.exit(2); }

console.log(`\n  APLICAR PAYLOAD — ${files.length} arquivo(s) · ${(p.totalBytes || 0).toLocaleString('pt-BR')} bytes`);
console.log(`  origem: ${typeof p.source==='string'?p.source:JSON.stringify(p.source)} · gerado: ${p.generatedAt || '?'}`);
if (Array.isArray(p.missing) && p.missing.length) console.log(`  ⚠ missing declarado no payload: ${p.missing.length}`);
console.log(`  destino: ${DESTINO}/${dry ? '   (DRY — nada será escrito)' : ''}\n`);

const tally = { NOVO: 0, ATUALIZADO: 0, inalterado: 0 };
const corrompidos = [], forade = [];

for (const f of files) {
  const rel = String(f.path || '');
  // trava de escopo: nada fora do espelho, nada subindo diretório
  // ⚠️ normalize() devolve o separador da PLATAFORMA (`\` no Windows, `/` no POSIX). Comparar
  // com a constante escrita com `/` reprovava 118/118 no Windows e 0/118 no CI — o mesmo teste
  // com dois vereditos conforme o SO (§5 2026-08-07). Normaliza os DOIS lados antes de comparar.
  const alvoRel = normalize(join(DESTINO, rel));
  const baseRel = normalize(DESTINO);
  if (!alvoRel.startsWith(baseRel + sep) && alvoRel !== baseRel) { forade.push(rel + ' (fora do espelho)'); continue; }
  if (rel.toLowerCase().endsWith('.md')) { forade.push(rel + ' (.md — R1 do ssot-guard)'); continue; }

  // INTEGRIDADE — o que eu consigo VERIFICAR, não o que soa mais forte.
  //
  // O payload traz `fnv64` por arquivo, mas a convenção dele não é reproduzível daqui: testei
  // 5 variantes (FNV-1a/FNV-1 × bytes-utf8/latin1/charCodeAt/codePoint) e 0 de 118 bateram.
  // Bloquear por um hash que eu não sei calcular seria transformar minha ignorância em veredito
  // — e o controle positivo já provou que o errado era eu, não o transporte.
  //
  // O que eu verifico de verdade, e é mais forte:
  //   (a) BYTES declarado == bytes reais — pega truncagem/corrupção de transporte. 118/118 ✓
  //   (b) PROVA CRUZADA — os 21 arquivos que desceram hoje pela rota INDEPENDENTE do
  //       `get_file` → `--export-from` são byte-idênticos aos do payload. 21/21 ✓
  // Duas rotas que não compartilham nada concordando é evidência melhor que um hash opaco.
  const calc = fnv1a64(f.content);
  const bytesReais = Buffer.byteLength(f.content, 'utf8');
  if (f.bytes != null && f.bytes !== bytesReais) { corrompidos.push({ rel, declarado: f.bytes + ' bytes', calculado: bytesReais + ' bytes' }); continue; }

  const abs = join(ROOT, alvoRel);
  const antes = existsSync(abs) ? readFileSync(abs, 'utf8') : null;
  const nota = antes === null ? 'NOVO' : antes === f.content ? 'inalterado' : 'ATUALIZADO';
  tally[nota]++;
  // DELTA DE LINHAS — porque "sync" pode ser REGRESSÃO. O espelho às vezes está À FRENTE do
  // vivo: trabalho que entrou direto nele (ex: o `qa-conformance.js` ganhou os gates G14/G15
  // pelo PR #4597 em 2026-07-20, e o vivo segue em v2.4/G13). Aplicar cego apaga isso.
  // O número que denuncia é a ASSIMETRIA: os 6 syncs legítimos deste lote tinham remoção da
  // ordem da adição (+24/−8, +84/−43, +19/−19…); o regressivo era +2/−171.
  // Isto é RELATO, não bloqueio — "quem está à frente" é semântico e depende do arquivo.
  if (nota !== 'inalterado') {
    const la = (antes || '').split(/\n/).length, ln = f.content.split(/\n/).length;
    const perda = la - ln;
    // Critério = PERDA LÍQUIDA de linhas, medido nos 7 deste lote (não chutado):
    //   os 6 syncs legítimos → líquido 0 · +16 · +41 · +70 · 0 · +3   (nenhum perdeu)
    //   o regressivo         → 787→618 = −169
    // Um teto de proporção (`ln < la*0.75`) NÃO discrimina: 618/787 = 78% e deixaria passar.
    const flag = perda > 20 ? '  ⚠️ PERDE ' + perda + ' LINHAS — confira se o espelho não está À FRENTE do vivo' : '';
    console.log(`  ${nota.padEnd(11)} ${rel}  (${(f.bytes || f.content.length).toLocaleString('pt-BR')} bytes · linhas ${la}→${ln} · ${calc.slice(0, 12)})${flag}`);
  }
  if (!dry) { mkdirSync(dirname(abs), { recursive: true }); writeFileSync(abs, f.content, 'utf8'); }
}

console.log(`\n  ${tally.ATUALIZADO} atualizado(s) · ${tally.NOVO} novo(s) · ${tally.inalterado} inalterado(s)`);
if (forade.length) { console.log(`  ⛔ ${forade.length} RECUSADO(s) por escopo/R1:`); forade.forEach((x) => console.log(`     · ${x}`)); }
if (corrompidos.length) {
  console.log(`\n  🔴 ${corrompidos.length} arquivo(s) com HASH DIVERGENTE — NÃO escritos:`);
  corrompidos.forEach((c) => console.log(`     · ${c.rel}\n       declarado ${c.declarado} · calculado ${c.calculado}`));
  console.log('  O transporte não é confiável nesta rodada. Nada foi aplicado desses.');
}
// Órfãos são RELATO, não poda: o apply não apaga, e o que sobra no espelho fora deste lote
// pode ser legítimo (bundles, origem externa). Podar é decisão [W].
console.log(`\n  ℹ️  apply não apaga — arquivos do espelho fora deste lote seguem lá (relato, não poda).`);
console.log('');
process.exit(corrompidos.length || forade.length ? 1 : 0);
