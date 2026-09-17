#!/usr/bin/env node
// @ts-check
/**
 * receber-handoff.test.mjs — MORDE/SOLTA da recepção de handoff (ZIP) e do leitor de ZIP.
 *
 * Hermético: monta ZIPs de mentira em memória (nenhum built-in do node escreve zip, então o
 * próprio teste é o gerador — o que faz dele um CONTROLE POSITIVO do leitor: se o formato que
 * eu escrevo e o que eu leio divergissem, os casos "solta" cairiam).
 *
 * Cada caso tem par morde/solta. Sem o par, uma guarda que diz "sim" pra tudo passa por
 * não-execução (LC-13).
 *
 * Defeitos que ficam travados aqui:
 *   · CRC-32 conferido de verdade — `unzip -q` aceita conteúdo corrompido calado;
 *   · ZIP64 RECUSADO em vez de lido pela metade (fail-closed: não medi != está vazio);
 *   · zip-slip (`..` no nome da entrada) barrado ANTES de escrever;
 *   · `auditarPacote` detecta pacote velho pelo sha256 declarado x real, NÃO pelo `generatedAt`
 *     — que é a lição de 2026-09-10: três zips diferentes traziam o mesmo pacote de anteontem;
 *   · `classificar` NAO nomeia direcao: o 1o rotulo era `ZIP-NOVO` e caiu num caso em que o
 *     zip era mais VELHO. Com 3 hashes se sabe quem esta fora do bundle, nao quem avancou.
 */
import { deflateRawSync } from 'node:zlib';
import { mkdtempSync, mkdirSync, writeFileSync, existsSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { createHash } from 'node:crypto';
import { lerZip, extrairZip, crc32, nomeSeguro } from './zip-reader.mjs';
import { createManifest } from './bundle-contract.mjs';
import { acharRaiz, auditarPacote, classificar, listarRelativos, decidirDono, ignoradosPeloRepo, pathNoEspelho } from './receber-handoff.mjs';

let falhas = 0;
const ok = (cond, nome) => {
  console.log(`  ${cond ? 'ok  ' : 'FALHOU'} ${nome}`);
  if (!cond) falhas++;
};
const lanca = (fn, trecho, nome) => {
  let msg = null;
  try { fn(); } catch (e) { msg = e.message; }
  ok(msg !== null && msg.includes(trecho), `${nome} (msg: ${msg ? msg.slice(0, 60) : 'NAO LANCOU'})`);
};

const sha = (b) => createHash('sha256').update(b).digest('hex');

/** Monta um ZIP válido. `metodo` 0 = stored, 8 = deflate. */
function montarZip(entradas, { corromperCrc = false, forcarZip64 = false } = {}) {
  const locais = [];
  const centrais = [];
  let offset = 0;
  for (const e of entradas) {
    const nome = Buffer.from(e.nome, 'utf8');
    const cru = Buffer.from(e.dados);
    const metodo = e.metodo ?? 8;
    const comp = metodo === 0 ? cru : deflateRawSync(cru);
    const crc = corromperCrc ? (crc32(cru) ^ 0xffff) >>> 0 : crc32(cru);

    const lfh = Buffer.alloc(30);
    lfh.writeUInt32LE(0x04034b50, 0);
    lfh.writeUInt16LE(20, 4);
    lfh.writeUInt16LE(metodo, 8);
    lfh.writeUInt32LE(crc, 14);
    lfh.writeUInt32LE(comp.length, 18);
    lfh.writeUInt32LE(cru.length, 22);
    lfh.writeUInt16LE(nome.length, 26);
    locais.push(lfh, nome, comp);

    const cd = Buffer.alloc(46);
    cd.writeUInt32LE(0x02014b50, 0);
    cd.writeUInt16LE(20, 4);
    cd.writeUInt16LE(20, 6);
    cd.writeUInt16LE(metodo, 10);
    cd.writeUInt32LE(crc, 16);
    cd.writeUInt32LE(forcarZip64 ? 0xffffffff : comp.length, 20);
    cd.writeUInt32LE(cru.length, 24);
    cd.writeUInt16LE(nome.length, 28);
    cd.writeUInt32LE(offset, 42);
    centrais.push(cd, nome);

    offset += lfh.length + nome.length + comp.length;
  }
  const corpo = Buffer.concat(locais);
  const central = Buffer.concat(centrais);
  const eocd = Buffer.alloc(22);
  eocd.writeUInt32LE(0x06054b50, 0);
  eocd.writeUInt16LE(entradas.length, 8);
  eocd.writeUInt16LE(entradas.length, 10);
  eocd.writeUInt32LE(central.length, 12);
  eocd.writeUInt32LE(corpo.length, 16);
  return Buffer.concat([corpo, central, eocd]);
}

export function selftest() {
  console.log('\nreceber-handoff --selftest\n');

  // ── leitor de ZIP ───────────────────────────────────────────────────────────
  const zipBom = montarZip([
    { nome: 'projeto/oimpresso.com.html', dados: '<html>shell</html>' },
    { nome: 'projeto/app.jsx', dados: 'const a = 1;\n', metodo: 0 },
  ]);
  const lidas = lerZip(zipBom);
  ok(lidas.length === 2, 'SOLTA: zip valido devolve as 2 entradas');
  ok(lidas.find((e) => e.nome.endsWith('app.jsx')).dados.toString() === 'const a = 1;\n', 'SOLTA: metodo STORED volta identico');
  ok(lidas.find((e) => e.nome.endsWith('.html')).dados.toString() === '<html>shell</html>', 'SOLTA: metodo DEFLATE volta identico');

  lanca(() => lerZip(montarZip([{ nome: 'a.txt', dados: 'x' }], { corromperCrc: true })), 'CRC-32', 'MORDE: CRC-32 divergente');
  lanca(() => lerZip(montarZip([{ nome: 'a.txt', dados: 'x' }], { forcarZip64: true })), 'ZIP64', 'MORDE: sentinela ZIP64 recusada');
  lanca(() => lerZip(Buffer.from('nada disso e um zip')), 'End Of Central Directory', 'MORDE: buffer que nao e zip');

  ok(nomeSeguro('a/b/c.txt'), 'SOLTA: nome comum e seguro');
  ok(!nomeSeguro('../fora.txt'), 'MORDE: zip-slip com ..');
  ok(!nomeSeguro('/etc/passwd'), 'MORDE: caminho absoluto posix');
  ok(!nomeSeguro('C:/Windows/x'), 'MORDE: caminho absoluto windows');
  lanca(() => extrairZip(montarZip([{ nome: '../fuga.txt', dados: 'x' }]), mkdtempSync(join(tmpdir(), 'oi-t-'))), 'zip-slip', 'MORDE: extrair recusa zip-slip');

  // ── acharRaiz ───────────────────────────────────────────────────────────────
  const base = mkdtempSync(join(tmpdir(), 'oi-raiz-'));
  extrairZip(zipBom, base);
  ok(acharRaiz(base) === join(base, 'projeto'), 'SOLTA: acha a raiz pelo shell');
  ok(acharRaiz(mkdtempSync(join(tmpdir(), 'oi-vazio-'))) === null, 'MORDE: arvore sem shell devolve null');

  // ── listarRelativos (o insumo do --live-only, tirado da arvore) ─────────────
  const arvore = mkdtempSync(join(tmpdir(), 'oi-lista-'));
  extrairZip(montarZip([
    { nome: 'oimpresso.com.html', dados: 'x' },
    { nome: 'sub/a.jsx', dados: 'y' },
    { nome: 'sub/mais/b.css', dados: 'z' },
  ]), arvore);
  const rels = listarRelativos(arvore);
  ok(rels.length === 3, 'SOLTA: lista os 3 arquivos, em qualquer profundidade');
  ok(rels.includes('sub/mais/b.css'), 'SOLTA: path relativo POSIX, com barra normal');
  ok(!rels.some((r) => r.startsWith('/') || r.includes('\\')), 'MORDE: nunca emite absoluto nem barra invertida');
  ok(!rels.includes('sub'), 'MORDE: diretorio NAO entra na lista (o denominador e de arquivos)');

  // ── auditarPacote ───────────────────────────────────────────────────────────
  const conteudo = { 'app.jsx': Buffer.from('const a = 1;\n'), 'styles.css': Buffer.from('body{}\n') };
  const ler = (p) => conteudo[p] ?? null;
  const arquivos = Object.entries(conteudo)
    .map(([path, b]) => ({ path, bytes: b.length, sha256: sha(b), role: 'cowork-source' }))
    .sort((a, b) => a.path.localeCompare(b.path));

  // manifesto conforme: id calculado pelo mesmo contrato que o auditor usa
  const conforme = createManifest({ source: 'teste', files: arquivos, missing: [] });
  ok(auditarPacote(conforme, ler).veredito === 'CONFORME', 'SOLTA: pacote que descreve a propria arvore');

  // pacote velho: o manifesto e valido, mas a arvore andou
  const lerAndou = (p) => (p === 'app.jsx' ? Buffer.from('const a = 2;\n') : ler(p));
  const velho = auditarPacote(conforme, lerAndou);
  ok(velho.veredito === 'DESATUALIZADO', 'MORDE: pacote velho (sha256 declarado x real)');
  ok(velho.divergentes.length === 1 && velho.divergentes[0] === 'app.jsx', 'MORDE: aponta QUAL arquivo divergiu');

  // fora do contrato: bundleId adulterado
  const adulterado = { ...conforme, bundleId: 'f'.repeat(64) };
  ok(auditarPacote(adulterado, ler).veredito === 'FORA-DO-CONTRATO', 'MORDE: bundleId que nao bate com o calculo');

  // ── classificar (3 pontos) ──────────────────────────────────────────────────
  ok(classificar({ zipHash: 'a', repoHash: 'a', manifestoHash: 'a' }) === 'IGUAL', 'SOLTA: iguais');
  ok(classificar({ zipHash: 'b', repoHash: 'a', manifestoHash: 'a' }) === 'ZIP-FORA-DO-BUNDLE', 'SOLTA: zip difere e espelho bate com o bundle');
  ok(classificar({ zipHash: 'a', repoHash: 'b', manifestoHash: 'a' }) === 'ESPELHO-FORA-DO-BUNDLE', 'SOLTA: espelho andou por outra rota');
  ok(classificar({ zipHash: 'b', repoHash: 'c', manifestoHash: 'a' }) === 'AMBOS-DIVERGEM', 'MORDE: indecidivel fica indecidido');
  ok(classificar({ zipHash: null, repoHash: 'a', manifestoHash: 'a' }) === 'AUSENTE-NO-ZIP', 'SOLTA: ausente no zip');
  ok(classificar({ zipHash: 'a', repoHash: null, manifestoHash: 'a' }) === 'AUSENTE-NO-ESPELHO', 'SOLTA: ausente no espelho');

  // -- decidirDono (PASSO 0 na rota ZIP) ---------------------------------------
  // Registro de mentira de proposito: acoplar ao CONTAS/PROJETOS real faria o caso mudar de
  // veredito quando alguem registrasse uma conta nova — quebraria por DADO, nao por bug.
  const contasT = { w: { id: 'w', dono: '[W] Wagner', espelhada: true }, outra: { id: 'outra', dono: '[X] Outra', espelhada: false } };
  const projsT  = { telas: { conta: 'w', espelho: 'prototipo-ui/cowork/Wagner/' }, semEspelho: { conta: 'outra' } };
  const vinc   = { veredito: 'vinculada', conta: 'w', projeto: 'telas', porque: 'id fora do cache', placar: [] };
  const indet  = { veredito: 'indeterminado', conta: null, projeto: null, porque: 'so o cache _ds/', placar: [] };
  const naoVin = { veredito: 'nao-vinculada', conta: null, projeto: null, porque: 'nenhum id conhecido', placar: [] };

  ok(decidirDono(vinc, null, contasT, projsT).ok === true, 'SOLTA: vinculada passa sem declarar conta');
  ok(decidirDono(indet, null, contasT, projsT).ok === false, 'MORDE: indeterminado SEM --conta nao importa');
  ok(decidirDono(indet, null, contasT, projsT).exigeDeclaracao === true, 'MORDE: e diz que a saida e declarar a conta');
  ok(decidirDono(indet, 'w', contasT, projsT).ok === true, 'SOLTA: indeterminado + --conta w (a conta COM espelho) passa');
  ok(decidirDono(indet, 'w', contasT, projsT).conta === 'w', 'SOLTA: registra a conta declarada');
  // O caso que motivou o passo: conta sem espelho escreveria no espelho de OUTRO dono.
  ok(decidirDono(indet, 'outra', contasT, projsT).ok === false, 'MORDE: --conta sem espelho no repo nao importa (arquivo orfao)');
  ok(decidirDono(indet, 'naoexiste', contasT, projsT).ok === false, 'MORDE: --conta fora de CONTAS nao importa');
  // nao-vinculada NAO tem escape: declarar carimbaria origem desconhecida com conta conhecida.
  ok(decidirDono(naoVin, 'w', contasT, projsT).ok === false, 'MORDE: nao-vinculada nao cede nem com --conta w');
  ok(decidirDono(naoVin, 'w', contasT, projsT).motivo.includes('--conta NAO vale aqui'), 'MORDE: e diz por que nao cede');
  // CONTROLE NEGATIVO do par: se decidirDono virasse "nao pra tudo", os 5 SOLTA caem juntos.
  ok(decidirDono(vinc, 'outra', contasT, projsT).ok === true, 'SOLTA: vinculada ignora --conta irrelevante (nao virou nao-pra-tudo)');

  // -- ignoradosPeloRepo (o que o .gitignore proibe NAO entra no manifesto) --------
  // Injetor FAKE de proposito: acoplar ao .gitignore real faria o caso mudar de veredito quando
  // alguem editasse a regra — quebraria por DADO, nao por bug (mesmo criterio do registro acima).
  const soPng = (paths) => paths.filter((p) => p.endsWith('.png'));

  ok(pathNoEspelho('inbox-photo-c1.png') === 'prototipo-ui/cowork/Wagner/inbox-photo-c1.png',
    'MAPA: path de tela pousa no espelho do Wagner');
  ok(pathNoEspelho('_ds/x/colors_and_type.css', 'preview-cache').startsWith('prototipo-ui/design-system/'),
    'MAPA: preview-cache pousa no design-system (dsRuntimeRelPath)');

  const ign = ignoradosPeloRepo(['inbox-photo-c1.png', 'inbox-page.jsx', 'app.jsx'], soPng);
  ok(ign.has('inbox-photo-c1.png'), 'MORDE: png que o .gitignore exclui sai do export');
  // CONTROLE NEGATIVO: se o filtro virasse "tudo fora", os dois abaixo caem junto.
  ok(!ign.has('inbox-page.jsx') && !ign.has('app.jsx'), 'SOLTA: fonte .jsx continua no export');
  ok(ignoradosPeloRepo(['app.jsx', 'styles.css'], soPng).size === 0, 'SOLTA: export sem ignorado devolve vazio');
  // `_ds/**` tem dono proprio (passo [4], projeto DS) — nunca entra neste filtro, mesmo gitignored.
  ok(ignoradosPeloRepo(['_ds/x/assets/fonts/a.woff2'], () => ['qualquer-coisa']).size === 0,
    'SOLTA: preview-cache fica fora do filtro (dono e o projeto DS)');
  // Path fora do contrato build-only faz `roleForPath` LANCAR — nao pode derrubar o import.
  ok(ignoradosPeloRepo(['.gitignore', '.thumbnail'], soPng).size === 0,
    'SOLTA: path fora do contrato build-only nao lanca (o gerador ja o descarta)');
  // O injetor que EXPLODE tem que propagar: "0 ignorados" silencioso seria gate mudo (LC-33).
  let propagou = false;
  try { ignoradosPeloRepo(['app.jsx'], () => { throw new Error('git sumiu'); }); }
  catch { propagou = true; }
  ok(propagou, 'MORDE: falha do check-ignore propaga (nao vira "0 ignorados")');

  console.log(`\n  ${falhas === 0 ? 'OK' : 'FALHAS: ' + falhas}\n`);
  if (falhas) process.exit(1);
}

if (process.argv[1] && process.argv[1].endsWith('receber-handoff.test.mjs')) await selftest();
