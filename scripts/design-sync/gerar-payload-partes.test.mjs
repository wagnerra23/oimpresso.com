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
 * Além das regressões reais do envelope legado, cobre o contrato v2: manifesto-alvo,
 * SHA-256 por chunk, arquivo maior que o cap, parte 01 de controle e delta exato.
 * Defeitos históricos travados:
 *   · a 1a versão escapava o glob ANTES de fatiar em `**`, e `--exclude` estourava o RegExp;
 *   · a mensagem da guarda de tamanho prometia que excluir joga o arquivo em `missing[]`, e o
 *     código não fazia isso (LC-15 — anunciar saída que não se honra);
 *   · `missing` ia repetido em toda parte, e o applier faz `flatMap` — 1 ausente virava "23
 *     ausentes" no relatório.
 */
import { existsSync, mkdtempSync, writeFileSync, mkdirSync, readdirSync, readFileSync, statSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { createHash } from 'node:crypto';

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

console.log('\n=== 1) CONTROLE POSITIVO — SHA-256 bate vetor publicado ===');
{
  const digest = createHash('sha256').update('abc').digest('hex');
  ok(digest === 'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad', 'vetor SHA-256 de "abc"');
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
  ok(envs.every((e) => e.fileCount === new Set(e.chunks.map((chunk) => chunk.path)).size), 'fileCount conta paths únicos em TODA parte');
  ok(envs.every((e) => Array.isArray(e.missing)), 'missing é repetido em TODA parte');
  ok(envs.every((e) => e.chunks.every((chunk) => typeof chunk.bytes === 'number' && /^[a-f0-9]{64}$/.test(chunk.sha256))), 'todo chunk traz bytes + SHA-256');
  ok(envs.every((e) => e.schema === 'oimpresso-design-bundle/2'), 'envelope declara schema v2');
  ok(envs[0].targetManifest && envs.slice(1).every((e) => !e.targetManifest), 'manifesto-alvo existe somente na part01');
  ok(existsSync(join(out, 'bundle.manifest.json')), 'manifesto durável foi emitido para o próximo delta');

  // o shell entra no próprio payload, e o fechamento pega os 3 refs
  const paths = envs[0].targetManifest.files.map((f) => f.path);
  ok(paths.includes('oimpresso.com.html'), 'o shell entra no payload');
  ok(['a.css', 'b.jsx', 'c.css'].every((f) => paths.includes(f)), 'fechamento transitivo pegou os 3 refs (query ?v= normalizada)');
  ok(new Set(paths).size === paths.length, 'nenhum path duplicado entre as partes');
}

console.log('\n=== 3) SOLTA — arquivo maior que o cap é remontável por chunks ===');
{
  const dir = fixture({ 'gigante.css': CAP + 50000 });
  const out = saida();
  const r = rodar(['--root', dir, '--out', out, '--chunk-bytes', '65536']);
  ok(r.code === 0, `arquivo grande gera bundle v2 (exit ${r.code})`);
  const envs = partesDe(out).map((file) => JSON.parse(readFileSync(join(out, file), 'utf8')));
  const chunks = envs.flatMap((env) => env.chunks).filter((chunk) => chunk.path === 'gigante.css');
  ok(chunks.length > 1, `gigante.css foi dividido em ${chunks.length} chunks`);
  const rebuilt = Buffer.concat(chunks.sort((a, b) => a.index - b.index).map((chunk) => Buffer.from(chunk.content, 'base64')));
  ok(rebuilt.equals(readFileSync(join(dir, 'gigante.css'))), 'chunks remontam bytes idênticos ao arquivo grande');
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
  ok(envs.every((e) => e.missing.length === 1 && e.missing[0] === 'fora.css'), 'cada parte repete o missing do contrato');
  ok(envs[0].targetManifest.missing.length === 1, 'manifesto-alvo registra o excluído uma vez');

  const paths = envs.flatMap((e) => e.chunks.map((f) => f.path));
  ok(!paths.includes('fora.css'), 'o excluído não foi escrito no payload');

  // CONTROLE NEGATIVO: sem --exclude, o mesmo shell fecha com missing vazio
  const out2 = saida();
  rodar(['--root', dir, '--out', out2]);
  const envs2 = partesDe(out2).map((f) => JSON.parse(readFileSync(join(out2, f), 'utf8')));
  ok(envs2.every((e) => e.missing.length === 0), 'solta: sem --exclude, missing é vazio');
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
  ok(new Set(envs.map((e) => e.bundle.generatedAt)).size === 1, 'generatedAt único no lote (o applier recusa lote misto)');
}

console.log('\n=== 7) MORDE — nenhuma parte sai minúscula (piso de persistência do consumidor) ===');
{
  // O teto sozinho não basta: o guloso puro enche até o CAP e joga o resto na ÚLTIMA parte.
  // Parte pequena demais chega INLINE no contexto do consumidor em vez de virar arquivo em
  // disco, e aí é inaplicável. Foi como o lote do Cowork de 2026-08-22 travou (part01 = 41 KB).
  //
  // Fixture escolhido pra DOER no guloso: 6×50 KB (~300 KB, logo acima do teto). Guloso -> 5 na
  // 1a parte (~250 KB) e 1 sobrando (~50 KB, ABAIXO do piso). Equilibrado -> 2 de ~150 KB.
  const tam = {};
  for (let i = 0; i < 6; i++) tam[`h${i}.css`] = 50000;
  const dir = fixture(tam);
  const out = saida();
  const r = rodar(['--root', dir, '--out', out, '--cap', String(CAP)]);
  ok(r.code === 0, `exit 0 (obtido ${r.code})`);

  const partes = partesDe(out);
  const tamanhos = partes.map((f) => statSync(join(out, f)).size);
  ok(partes.length > 1, `partiu em ${partes.length} partes`);
  ok(Math.max(...tamanhos) <= CAP, `maior ${Math.max(...tamanhos)} <= cap`);

  // O CONTROLE que importa: a MENOR parte não pode ficar abaixo do piso.
  const PISO = 61440;
  ok(Math.min(...tamanhos) >= PISO, `menor parte ${Math.min(...tamanhos)} >= piso ${PISO}`);

  // e as partes têm que estar de fato EQUILIBRADAS — senão o teste acima passaria por sorte
  const razao = Math.max(...tamanhos) / Math.min(...tamanhos);
  ok(razao < 2, `partes equilibradas (maior/menor = ${razao.toFixed(2)} < 2)`);

  // SOLTA: o aviso existe e é RELATO, não veredito — com piso absurdo ele fala e ainda sai 0
  const r2 = rodar(['--root', dir, '--out', saida(), '--cap', String(CAP), '--piso', '999999']);
  ok(r2.code === 0, `piso absurdo AVISA mas não reprova (exit ${r2.code})`);
  ok(/piso 999999 > cap/.test(r2.out), 'o aviso de piso impossível aparece');
  // e --piso 0 desliga
  const r3 = rodar(['--root', dir, '--out', saida(), '--cap', String(CAP), '--piso', '0']);
  ok(!/abaixo do piso/.test(r3.out), '--piso 0 desliga o aviso');
}

console.log('\n=== 8) DELTA — baixa somente added/modified e declara deleted ===');
{
  const dir = fixture({ 'a.css': 1000, 'b.css': 1000, 'd.css': 1000 });
  const first = saida();
  const initial = rodar(['--root', dir, '--out', first]);
  ok(initial.code === 0, `snapshot inicial gerado (exit ${initial.code})`);

  writeFileSync(join(dir, 'a.css'), '/* alterado */\n' + 'z'.repeat(1500));
  writeFileSync(join(dir, 'c.css'), '/* novo */\n' + 'c'.repeat(700));
  rmSync(join(dir, 'b.css'));
  writeFileSync(join(dir, 'oimpresso.com.html'), [
    '<link rel="stylesheet" href="a.css">',
    '<link rel="stylesheet" href="c.css">',
    '<link rel="stylesheet" href="d.css">',
  ].join('\n'));

  const second = saida();
  const delta = rodar(['--root', dir, '--out', second, '--previous', join(first, 'bundle.manifest.json')]);
  ok(delta.code === 0, `delta gerado (exit ${delta.code})`);
  const manifest = JSON.parse(readFileSync(join(second, 'bundle.manifest.json'), 'utf8'));
  ok(manifest.mode === 'delta' && manifest.baseBundleId, 'manifesto delta aponta para bundle-base');
  ok(manifest.changes.added.join(',') === 'c.css', `added exato: ${manifest.changes.added.join(',')}`);
  ok(manifest.changes.deleted.join(',') === 'b.css', `deleted exato: ${manifest.changes.deleted.join(',')}`);
  ok(manifest.changes.modified.includes('a.css') && manifest.changes.modified.includes('oimpresso.com.html'), 'modified inclui conteúdo + shell');
  ok(manifest.changes.unchanged === 1, `unchanged=1 (d.css), obtido ${manifest.changes.unchanged}`);
  const transported = partesDe(second)
    .flatMap((file) => JSON.parse(readFileSync(join(second, file), 'utf8')).chunks)
    .map((chunk) => chunk.path);
  ok(!transported.includes('d.css') && !transported.includes('b.css'), 'delta não baixa unchanged nem deleted');
  ok(['a.css', 'c.css', 'oimpresso.com.html'].every((path) => transported.includes(path)), 'delta baixa somente os bytes added/modified');
}

console.log('\n=== 9) SOLTA — part01 pode carregar só o manifesto quando o primeiro chunk não cabe ===');
{
  // Muitos paths aumentam o manifesto; o chunk individual ainda cabe numa parte comum. A
  // regressão punha esse chunk na part01 e só descobria o excesso depois de empacotar tudo.
  const capControle = 50000;
  const tamanhos = { '000-grande.css': 30000 };
  for (let i = 0; i < 110; i++) tamanhos[`folha-${String(i).padStart(3, '0')}.css`] = 8;
  const dir = fixture(tamanhos);
  const out = saida();
  const r = rodar(['--root', dir, '--out', out, '--cap', String(capControle), '--chunk-bytes', '25000', '--piso', '0']);
  ok(r.code === 0, `manifesto grande + chunk gera bundle (exit ${r.code})`);
  const files = partesDe(out);
  const envs = files.map((file) => JSON.parse(readFileSync(join(out, file), 'utf8')));
  ok(files.length > 1, `gerou ${files.length} partes`);
  ok(envs[0].targetManifest && envs[0].chunks.length === 0, 'part01 é controle-only quando necessário');
  ok(files.every((file) => statSync(join(out, file)).size <= capControle), 'todas as partes respeitam o cap');
  ok(envs.slice(1).flatMap((env) => env.chunks).some((chunk) => chunk.path === '000-grande.css'), 'chunk grande seguiu nas partes de dados');
}

console.log(falhas ? `\n✗ ${falhas} asserção(ões) falharam\n` : '\n✓ todas as asserções passaram\n');
process.exit(falhas ? 1 : 0);
