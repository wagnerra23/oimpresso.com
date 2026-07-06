#!/usr/bin/env node
// Self-test cowork-mirror-freshness — prova a classificação vs o CONTRATO, não vs a
// implementação. Contrato ancorado em: ADR 0324 (este sentinela) · ADR 0315 (DesignSync:
// leitura livre, escrita gateada, git é a fonte) · ADR 0299 (Cowork é a fonte) · session
// 2026-07-06 (ponto cego #2: "protótipo de bubble velha" — cópia do repo é design ANTIGO,
// só um diff byte-a-byte pega). Roda: node scripts/governance/cowork-mirror-freshness.test.mjs
import { readFileSync, mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  md5,
  classifyMirror,
  verdictFor,
  shouldFail,
  buildManifest,
} from './cowork-mirror-freshness.mjs';

let fails = 0;
const check = (n, c, extra = '') => { console.log(`${c ? '[OK]' : '[FAIL]'} ${n}${c ? '' : '  → ' + extra}`); if (!c) fails++; };

// 1. md5 — freshness é IDENTIDADE DE BYTES (determinístico + sensível ao byte, incl. quebra de linha).
check('md5 determinístico (mesmo byte → mesmo hash)', md5('financeiro-page') === md5('financeiro-page'));
check('md5 sensível ao byte (1 char muda o hash)', md5('a') !== md5('b'));
check('md5 sensível a \\n (design antigo != novo por 1 linha)', md5('x') !== md5('x\n'));
check('md5(Buffer) === md5(string) pro mesmo utf8 (repo bytes == get_file content)', md5(Buffer.from('áé')) === md5('áé'));

// 2. classifyMirror — o coração (3 vias).
check('md5 iguais → SYNC (espelho fresco · ADR 0299 repo==vivo)', classifyMirror({ repoMd5: 'ae3a2cfe', liveMd5: 'ae3a2cfe' }) === 'SYNC');
check('md5 diferem → STALE (ponto cego #2: cópia do repo é design ANTIGO)', classifyMirror({ repoMd5: 'ae3a2cfe', liveMd5: 'deadbeef' }) === 'STALE');
check('vivo null → LIVE-ABSENT, NÃO stale (rename/delete upstream ≠ divergência)', classifyMirror({ repoMd5: 'ae3a2cfe', liveMd5: null }) === 'LIVE-ABSENT');
check('vivo "" → LIVE-ABSENT (ausência explícita)', classifyMirror({ repoMd5: 'ae3a2cfe', liveMd5: '' }) === 'LIVE-ABSENT');

// 3. verdictFor — distingue "não buscado" de "buscado e ausente" (a suite não mente por silêncio).
check('chave fora do snapshot → UNCHECKED (agente não buscou; NUNCA vira SYNC calado)', verdictFor('x.jsx', 'aaa', {}) === 'UNCHECKED');
check('chave presente e igual → SYNC', verdictFor('x.jsx', 'aaa', { 'x.jsx': 'aaa' }) === 'SYNC');
check('chave presente e diferente → STALE', verdictFor('x.jsx', 'aaa', { 'x.jsx': 'bbb' }) === 'STALE');
check('chave presente e null → LIVE-ABSENT (não confunde com UNCHECKED)', verdictFor('x.jsx', 'aaa', { 'x.jsx': null }) === 'LIVE-ABSENT');

// 4. shouldFail (--check) — SÓ STALE morde; "não deu pra verificar" não bloqueia.
check('STALE presente → gate morde (exit 1)', shouldFail(['SYNC', 'STALE', 'UNCHECKED']) === true);
check('só SYNC → gate libera', shouldFail(['SYNC', 'SYNC']) === false);
check('UNCHECKED/LIVE-ABSENT sozinhos → gate NÃO morde (são warn, não podre)', shouldFail(['UNCHECKED', 'LIVE-ABSENT', 'SYNC']) === false);

// 5. Counterfactual: o md5 governa, não o nome do arquivo.
check('conteúdo idêntico mas "nome diferente" → ainda SYNC (é conteúdo, não filename)',
  classifyMirror({ repoMd5: md5('same-bytes'), liveMd5: md5('same-bytes') }) === 'SYNC');

// 6. READ-ONLY por contrato (ADR 0315 Eixo B): a rotina NÃO pode embutir método de ESCRITA
//    do DesignSync — senão viraria backdoor de publicação (nuvem→git). Prova no fonte.
{
  const HERE = dirname(fileURLToPath(import.meta.url));
  const src = readFileSync(join(HERE, 'cowork-mirror-freshness.mjs'), 'utf8');
  const writeMethods = ['finalize_plan', 'write_files', 'delete_files', 'create_project', 'register_assets'];
  const embedded = writeMethods.filter((w) => src.includes(`${w}(`) || src.includes(`method: '${w}'`) || src.includes(`"${w}"`));
  check('fonte não invoca método de ESCRITA do DesignSync (só leitura — 0315)', embedded.length === 0, `achou: ${embedded.join(', ')}`);
}

// 7. buildManifest — enumera o mesmo conjunto que o anchor-content (via anchorFile), em fixture hermético.
{
  const dir = mkdtempSync(join(tmpdir(), 'mirror-fresh-'));
  try {
    const charterDir = join(dir, 'resources', 'js', 'Pages', 'Mod');
    const coworkDir = join(dir, 'prototipo-ui', 'cowork');
    mkdirSync(charterDir, { recursive: true });
    mkdirSync(coworkDir, { recursive: true });
    // charter A: âncora resolvível → x-page.jsx (existe no espelho)
    writeFileSync(join(charterDir, 'Tela.charter.md'), 'related_prototype: prototipo-ui/cowork/x-page.jsx\n');
    writeFileSync(join(coworkDir, 'x-page.jsx'), 'export function X(){return null}\n');
    // charter B: âncora PROSA (sem arquivo) → deve ser PULADA (mesmo escopo do anchor-content)
    mkdirSync(join(dir, 'resources', 'js', 'Pages', 'Mod2'), { recursive: true });
    writeFileSync(join(dir, 'resources', 'js', 'Pages', 'Mod2', 'Tela.charter.md'), 'related_prototype: prototipo Cowork "payment-gateway-ui"\n');
    // charter C: âncora resolvível mas arquivo NÃO existe no espelho → fora do manifesto (MISSING é do anchor-content)
    mkdirSync(join(dir, 'resources', 'js', 'Pages', 'Mod3'), { recursive: true });
    writeFileSync(join(dir, 'resources', 'js', 'Pages', 'Mod3', 'Tela.charter.md'), 'related_prototype: sumiu.jsx\n');
    // arquivo extra no espelho SEM charter → só aparece no modo --all
    writeFileSync(join(coworkDir, 'orfao.html'), '<div/>\n');

    const man = buildManifest(dir);
    check('manifesto enumera só a âncora resolvível+existente (1)', man.length === 1, `tem ${man.length}: ${man.map((m) => m.cowork).join(',')}`);
    check('manifesto pula prosa e MISSING (escopo == anchor-content)', !man.some((m) => /payment|sumiu/.test(m.cowork)));
    check('manifesto NÃO inclui órfão sem charter no modo default', !man.some((m) => m.cowork === 'orfao.html'));
    check('repoMd5 do manifesto casa com md5 do arquivo real', man[0]?.repoMd5 === md5(readFileSync(join(coworkDir, 'x-page.jsx'))));
    check('manifesto guarda a tela que aponta pra âncora', man[0]?.telas?.includes('Mod/Tela'));

    const manAll = buildManifest(dir, { all: true });
    check('--all inclui o órfão do espelho (orfao.html)', manAll.some((m) => m.cowork === 'orfao.html'));

    // Fim-a-fim do veredito: monto um snapshot e confiro os 4 desfechos contra o manifesto real.
    const snapSync = { 'x-page.jsx': man[0].repoMd5 };
    check('E2E: snapshot igual → SYNC', verdictFor('x-page.jsx', man[0].repoMd5, snapSync) === 'SYNC');
    check('E2E: snapshot diferente → STALE', verdictFor('x-page.jsx', man[0].repoMd5, { 'x-page.jsx': 'outra' }) === 'STALE');
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
}

console.log(fails ? `\n✗ ${fails} falha(s)` : '\n✓ contrato do sentinela de frescor do espelho preservado');
process.exit(fails ? 1 : 0);
