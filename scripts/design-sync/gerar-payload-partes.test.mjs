#!/usr/bin/env node
// @ts-check
/**
 * gerar-payload-partes.test.mjs — MORDE/SOLTA do gerador de payload em partes.
 *
 * Hermético: monta um shell de mentira num tmpdir, roda o gerador como subprocesso e afere a
 * SAÍDA. Não lê o repo, não fala com o Cowork, não instala nada (só built-ins do node).
 *
 * Cada caso tem par morde/solta — controle-negativo junto, senão a guarda pode estar sempre
 * dizendo "sim" e o teste passa por não-execução (LC-13).
 *
 * Os 3 defeitos que os casos 3, 4 e 5 travam foram REAIS, achados rodando o gerador em
 * 2026-08-22 — não são hipóteses:
 *   · a 1a versão escapava o glob ANTES de fatiar em `**`, e `--exclude` estourava o RegExp;
 *   · a mensagem da guarda de tamanho prometia que excluir joga o arquivo em `missing[]`, e o
 *     código não fazia isso (LC-15 — anunciar saída que não se honra);
 *   · `missing` ia repetido em toda parte, e o applier faz `flatMap` — 1 ausente virava "23
 *     ausentes" no relatório.
 */
import { mkdtempSync, writeFileSync, mkdirSync, readdirSync, readFileSync, statSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const GERADOR = fileURLToPath(new URL('./gerar-payload-partes.mjs', import.meta.url));
const CAP = 262144;
let falhas = 0;

const ok = (cond, msg) => {
  console.log(`  ${cond ? '✓' : '✗'} ${msg}`);
  if (!cond) falhas++;
};

/** Roda o gerador; devolve {code, out} sem lançar, pra poder afirmar tanto morde quanto solta. */
function rodar(args) {
  try {
    const out = execFileSync(process.execPath, [GERADOR, ...args], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
    return { code: 0, out };
  } catch (e) {
    return { code: e.status ?? 1, out: String(e.stdout || '') + String(e.stderr || '') };
  }
}

/** Monta um shell mínimo + refs. `tamanhos` = {arquivo: bytes de recheio}. */
function fixture(tamanhos) {
  const dir = mkdtempSync(join(tmpdir(), 'gpp-'));
  const refs = Object.keys(tamanhos);
  const links = refs.map((f) => f.endsWith('.css')
    ? `<link rel="stylesheet" href="${f}?v=1"/>`
    : `<script type="text/babel" src="${f}?v=1"></script>`).join('\n');
  writeFileSync(join(dir, 'oimpresso.com.html'), `<!doctype html>\n<html><head>\n${links}\n</head><body></body></html>\n`);
  for (const [nome, bytes] of Object.entries(tamanhos)) {
    // conteúdo com acento de propósito: exercita o caminho UTF-8 (bytes != chars)
    const recheio = '/* ç—á */\n' + 'x'.repeat(Math.max(0, bytes));
    writeFileSync(join(dir, nome), recheio);
  }
  return dir;
}

const partesDe = (out) => readdirSync(out).filter((f) => /^payload\.part\d+\.json$/.test(f)).sort();
const saida = () => mkdtempSync(join(tmpdir(), 'gpp-out-'));

console.log('\n=== 1) CONTROLE POSITIVO — fnv1a64 bate vetor publicado de FNV-1a-64 ===');
{
  // O gerador não exporta a função, então reimplemento o contrato aqui e comparo com os vetores
  // canônicos. É o controle que separa "meu hash" de "hash com nome de FNV" — a confusão que
  // custou a contradição 0/118 do lado do produtor manual (docblock do aplicar-payload).
  const fnv1a64 = (s) => {
    let h = 0xcbf29ce484222325n;
    const p = 0x100000001b3n, m = 0xffffffffffffffffn;
    for (const b of Buffer.from(s, 'utf8')) { h = ((h ^ BigInt(b)) * p) & m; }
    return h.toString(16).padStart(16, '0');
  };
  ok(fnv1a64('') === 'cbf29ce484222325', 'vetor "" = cbf29ce484222325');
  ok(fnv1a64('a') === 'af63dc4c8601ec8c', 'vetor "a" = af63dc4c8601ec8c');
  ok(fnv1a64('foobar') === '85944171f73967e8', 'vetor "foobar" = 85944171f73967e8');
}

console.log('\n=== 2) SOLTA — shell que cabe gera partes válidas ===');
{
  const dir = fixture({ 'a.css': 500, 'b.jsx': 500, 'c.css': 500 });
  const out = saida();
  const r = rodar(['--root', dir, '--out', out]);
  ok(r.code === 0, `exit 0 (obtido ${r.code})`);
  const partes = partesDe(out);
  ok(partes.length >= 1, `gerou ${partes.length} parte(s)`);

  const envs = partes.map((f) => JSON.parse(readFileSync(join(out, f), 'utf8')));
  // CONTRATO DO APPLIER (armadilha 1): fileCount é conferido POR PARTE contra files.length
  // DAQUELA parte. Declarar o total do lote reprova todas menos a última.
  ok(envs.every((e) => e.fileCount === e.files.length), 'fileCount == files.length em TODA parte');
  ok(envs.every((e) => Array.isArray(e.missing)), 'missing é array em TODA parte (armadilha 2)');
  ok(envs.every((e) => e.files.every((f) => typeof f.bytes === 'number')), 'todo arquivo traz bytes (armadilha 3)');
  ok(envs.every((e) => e.schema === 'cowork-payload/1' && e.hash === 'fnv1a64'), 'envelope declara schema + convenção do digest');

  // bytes declarado == bytes reais do conteúdo (é a prova que o applier consegue verificar)
  const errados = envs.flatMap((e) => e.files).filter((f) => Buffer.byteLength(f.content, 'utf8') !== f.bytes);
  ok(errados.length === 0, `bytes declarado bate com o real em ${envs.flatMap((e) => e.files).length}/${envs.flatMap((e) => e.files).length} arquivos`);

  // o shell entra no próprio payload, e o fechamento pega os 3 refs
  const paths = envs.flatMap((e) => e.files.map((f) => f.path));
  ok(paths.includes('oimpresso.com.html'), 'o shell entra no payload');
  ok(['a.css', 'b.jsx', 'c.css'].every((f) => paths.includes(f)), 'fechamento transitivo pegou os 3 refs (query ?v= normalizada)');
  ok(new Set(paths).size === paths.length, 'nenhum path duplicado entre as partes');
}

console.log('\n=== 3) MORDE/SOLTA — arquivo maior que o cap não cabe em parte nenhuma ===');
{
  // MORDE: arquivo é atômico dentro da parte e o applier não remonta fatia. Emitir assim
  // produziria parte que o consumidor não baixa (get_file corta em 256 KiB).
  const dir = fixture({ 'gigante.css': CAP + 50000 });
  const r = rodar(['--root', dir, '--out', saida()]);
  ok(r.code === 2, `morde: exit 2 (obtido ${r.code})`);
  ok(/NAO CABEM/.test(r.out), 'morde: diz que não cabe');
  ok(/gigante\.css/.test(r.out), 'morde: nomeia o arquivo culpado');

  // SOLTA: o mesmo arquivo com cap folgado passa — prova que a guarda olha o cap, não o nome
  const r2 = rodar(['--root', dir, '--out', saida(), '--cap', String(CAP * 3)]);
  ok(r2.code === 0, `solta com cap maior: exit 0 (obtido ${r2.code})`);
}

console.log('\n=== 4) SOLTA — --exclude funciona (o glob fatia antes de escapar) ===');
{
  // Regressão: escapar a string inteira antes de fatiar não escapa `*`, o split por `**` não
  // casava e o RegExp saía com `**` cru -> "Nothing to repeat".
  const dir = fixture({ 'a.css': 200, 'sub-nao.css': 200 });
  mkdirSync(join(dir, 'sub'), { recursive: true });
  const r = rodar(['--root', dir, '--out', saida(), '--exclude', '**/nao-existe.css']);
  ok(r.code === 0, `exit 0 com --exclude glob (obtido ${r.code})`);
  ok(!/Invalid regular expression/.test(r.out), 'não estoura RegExp');
}

console.log('\n=== 5) MORDE — excluído REFERENCIADO cai em missing[], UMA vez no lote ===');
{
  // Duas regressões num caso só: (a) LC-15 — a mensagem prometia missing[] e o código não
  // entregava; (b) o applier faz flatMap em missing, então repetir a lista em toda parte
  // multiplicava o mesmo path pelo número de partes.
  //
  // ⚠️ O FIXTURE PRECISA GERAR VÁRIAS PARTES. Com 1 parte só, repetir `missing` e não repetir
  // dão a MESMA união, e o caso não discrimina — medido: com 3 arquivos pequenos o mutante que
  // desfaz a união SOBREVIVEU o teste inteiro em verde. Teste que não pode ficar vermelho é
  // carimbo (LC-13). Por isso 12 arquivos de ~40 KB: forçam ≥2 partes, e aí a repetição
  // multiplica o path pelo número de partes e a asserção morde.
  const tam = { 'fora.css': 500 };
  for (let i = 0; i < 12; i++) tam[`g${i}.css`] = 40000;
  const dir = fixture(tam);
  const out = saida();
  const r = rodar(['--root', dir, '--out', out, '--exclude', 'fora.css']);
  ok(r.code === 0, `exit 0 (obtido ${r.code})`);

  const envs = partesDe(out).map((f) => JSON.parse(readFileSync(join(out, f), 'utf8')));
  ok(envs.length > 1, `fixture gerou ${envs.length} partes (>1 — senão o caso não discrimina)`);
  const uniao = envs.flatMap((e) => e.missing);           // <- exatamente o que o applier faz
  ok(uniao.length === 1, `missing na UNIÃO do lote = 1 (obtido ${uniao.length})`);
  ok(uniao[0] === 'fora.css', 'missing nomeia o excluído');
  ok(envs.every((e) => Array.isArray(e.missing)), 'ainda assim toda parte tem o campo (armadilha 2)');

  const paths = envs.flatMap((e) => e.files.map((f) => f.path));
  ok(!paths.includes('fora.css'), 'o excluído não foi escrito no payload');

  // CONTROLE NEGATIVO: sem --exclude, o mesmo shell fecha com missing vazio
  const out2 = saida();
  rodar(['--root', dir, '--out', out2]);
  const envs2 = partesDe(out2).map((f) => JSON.parse(readFileSync(join(out2, f), 'utf8')));
  ok(envs2.flatMap((e) => e.missing).length === 0, 'solta: sem --exclude, missing é vazio');
}

console.log('\n=== 6) SOLTA — o empacotamento respeita o cap de verdade ===');
{
  // 12 arquivos de ~40 KB forçam mais de uma parte com o cap real.
  const tam = {};
  for (let i = 0; i < 12; i++) tam[`f${i}.css`] = 40000;
  const dir = fixture(tam);
  const out = saida();
  const r = rodar(['--root', dir, '--out', out, '--cap', String(CAP)]);
  ok(r.code === 0, `exit 0 (obtido ${r.code})`);
  const partes = partesDe(out);
  ok(partes.length > 1, `partiu em ${partes.length} partes (>1)`);
  const maior = Math.max(...partes.map((f) => statSync(join(out, f)).size));
  ok(maior <= CAP, `maior parte ${maior} <= cap ${CAP}`);
  const envs = partes.map((f) => JSON.parse(readFileSync(join(out, f), 'utf8')));
  ok(envs.every((e) => e.parts === partes.length), 'toda parte declara o total correto em `parts`');
  ok(envs.map((e) => e.part).sort((a, b) => a - b).join(',') === partes.map((_, i) => i + 1).join(','), '`part` numera 1..N sem buraco');
  ok(new Set(envs.map((e) => e.generatedAt)).size === 1, 'generatedAt único no lote (o applier recusa lote misto)');
}

console.log(falhas ? `\n✗ ${falhas} asserção(ões) falharam\n` : '\n✓ todas as asserções passaram\n');
process.exit(falhas ? 1 : 0);
