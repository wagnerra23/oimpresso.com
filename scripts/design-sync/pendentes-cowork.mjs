#!/usr/bin/env node
// pendentes-cowork.mjs — o sentido Code -> Cowork do ciclo: o que o espelho tem e o Cowork NÃO tem.
//
// POR QUE EXISTE ([W] 2026-09-24: "isso deveria já estar no protocolo esse envio dos arquivos" ·
// "deve acontecer quando gerar um retorno para code"). O ciclo tinha só um sentido mecânico:
// Cowork -> Code, pelo retorno (zip) + receber-handoff. O sentido contrário não existia como
// passo. Toda mudança do Code no espelho — errata de índice, recibo `_saida`, restauração — ficava
// só no repo, e o retorno seguinte, gerado de um Cowork que nunca a recebeu, a desfazia ou
// derrubava o gate "espelho — mexeu depois de verificar". Medido em 2026-09-23/24: o #7843 e o
// #7866 editaram o 00-INDICE.md do Patrimônio (main vermelho duas vezes, todos os PRs travados),
// e o import (35) ia podar 12 arquivos que o #7847 [W+C] restaurou.
//
// O CRITÉRIO NÃO É HEURÍSTICA: o bundle ativo (`state/active-bundle.json`) guarda o sha256 de cada
// arquivo do ÚLTIMO pacote do Cowork. Arquivo versionado do espelho cujo hash difere do bundle, ou
// que não está nele, é conteúdo que o Cowork não tem. Medido no main 68e071305: 40 pendentes, e
// os 40 eram exatamente o que o Cowork não tinha (7 threads + 1 índice + 32 recibos) — zero FP.
//
// O CICLO QUE ISTO FECHA:
//   1. o Code muda o espelho (PR)          -> `--plano` diz o que subir
//   2. o agente logado sobe (DesignSync)   -> `--registrar-envio` grava sha + data
//   3. o Cowork gera o próximo retorno     -> já contém a mudança
//   4. o receber-handoff importa           -> o arquivo volta a bater com o bundle e sai da lista
// O receber-handoff RECUSA aplicar retorno que apagaria ou sobrescreveria um pendente: é a rede
// de segurança pra quando o passo 2 foi esquecido.
//
// ⚠️ O UPLOAD NÃO É FEITO AQUI, e não pode ser: escrever no Cowork exige o login interativo do
// claude.ai (ADR 0315) e o opt-in do hook `block-design-sync-without-optin`. Este script é quem
// SABE o que subir; quem sobe é o agente, com `DesignSync.finalize_plan` + `write_files`
// (`localPath`: o conteúdo sai do disco, nunca do contexto).
//
// USO
//   node scripts/design-sync/pendentes-cowork.mjs                      # relatório
//   node scripts/design-sync/pendentes-cowork.mjs --plano              # JSON pro DesignSync
//   node scripts/design-sync/pendentes-cowork.mjs --registrar-envio a b  # após write_files OK
//   node scripts/design-sync/pendentes-cowork.mjs --registrar-envio-todos
//   node scripts/design-sync/pendentes-cowork.mjs --check              # exit 1 se há não-enviado
//   node scripts/design-sync/pendentes-cowork.mjs --limpar-confirmados # o retorno já trouxe

import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const AQUI = dirname(fileURLToPath(import.meta.url));
export const REPO = resolve(AQUI, '..', '..');
export const ESPELHO_REL = 'prototipo-ui/cowork/Wagner';
export const ATIVO_REL = 'scripts/design-sync/state/active-bundle.json';
export const ENVIADOS_REL = 'scripts/design-sync/state/enviados-cowork.json';
export const COWORK_PROJECT_ID = '019dcfd3-6ef2-7ee6-8512-b1b0e5544e58';

/** Mesmo recibo que a poda poupa (bundle-transaction.mjs RECIBO_CODE_RE) + o sufixo `-<tela>`. */
export const eRecibo = (rel) => /(^|\/)_saida-[^/]*\.md$/.test(rel);

/** `_ds/**` é cache do projeto Design System (outro dono, #7096) e `.gitignore` é guarda local. */
export const foraDoEscopo = (rel) => rel === '.gitignore' || rel.startsWith('_ds/') || rel.includes('/.gitignore');

const sha256 = (buf) => createHash('sha256').update(buf).digest('hex');

/**
 * Função PURA do critério. `arquivos` = [{ rel, sha }] versionados no espelho; `ativo` = manifesto
 * do bundle ativo; `enviados` = { rel: { sha256 } }. Devolve cada pendente com o motivo.
 */
export function calcularPendentes({ arquivos, ativo, enviados = {} }) {
  const noBundle = new Map((ativo?.files || []).filter((f) => f.role !== 'preview-cache').map((f) => [f.path, f.sha256]));
  const out = [];
  for (const { rel, sha } of arquivos) {
    if (foraDoEscopo(rel)) continue;
    const b = noBundle.get(rel);
    if (b === sha) continue;
    out.push({
      rel,
      sha,
      motivo: b === undefined ? 'fora-do-bundle' : 'difere-do-bundle',
      recibo: eRecibo(rel),
      enviado: enviados[rel]?.sha256 === sha,
    });
  }
  return out.sort((a, b) => a.rel.localeCompare(b.rel));
}

/** Arquivos VERSIONADOS do espelho (sujeira não-rastreada não é "o Code mudou"). */
export function arquivosDoEspelho(root = REPO) {
  let lista;
  try {
    lista = execFileSync('git', ['ls-files', '-z', '--', ESPELHO_REL], { cwd: root, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
  } catch (e) {
    throw new Error(`NÃO MEDI: git ls-files falhou (rc=${e.status})`);
  }
  return lista.split('\0').filter(Boolean).map((p) => {
    const abs = join(root, p);
    const rel = p.slice(ESPELHO_REL.length + 1);
    return existsSync(abs) ? { rel, sha: sha256(readFileSync(abs)) } : null;
  }).filter(Boolean);
}

export function lerJson(root, rel, vazio) {
  const abs = join(root, rel);
  return existsSync(abs) ? JSON.parse(readFileSync(abs, 'utf8')) : vazio;
}

export function pendentesDoRepo(root = REPO) {
  const ativo = lerJson(root, ATIVO_REL, null);
  if (!ativo) throw new Error(`NÃO MEDI: ${ATIVO_REL} ausente — sem bundle ativo não há base para comparar`);
  const enviados = lerJson(root, ENVIADOS_REL, { enviados: {} }).enviados || {};
  return calcularPendentes({ arquivos: arquivosDoEspelho(root), ativo, enviados });
}

function gravarEnviados(root, mapa) {
  const ordenado = Object.fromEntries(Object.entries(mapa).sort(([a], [b]) => a.localeCompare(b)));
  writeFileSync(join(root, ENVIADOS_REL), JSON.stringify({
    _nota: 'Arquivos que o Code subiu ao Cowork (DesignSync) e que o bundle ativo ainda não trouxe de volta. Escrito por pendentes-cowork.mjs; o receber-handoff limpa o que o retorno confirmar.',
    enviados: ordenado,
  }, null, 2) + '\n');
}

function principal(argv) {
  const root = REPO;
  const tem = (f) => argv.includes(f);
  const pend = pendentesDoRepo(root);

  if (tem('--limpar-confirmados')) {
    const doc = lerJson(root, ENVIADOS_REL, { enviados: {} });
    const aindaPendente = new Set(pend.map((p) => p.rel));
    const antes = Object.keys(doc.enviados || {}).length;
    const fica = Object.fromEntries(Object.entries(doc.enviados || {}).filter(([rel]) => aindaPendente.has(rel)));
    gravarEnviados(root, fica);
    console.log(`enviados confirmados pelo retorno: ${antes - Object.keys(fica).length} · ainda aguardando retorno: ${Object.keys(fica).length}`);
    return 0;
  }

  const iReg = argv.indexOf('--registrar-envio');
  if (iReg !== -1 || tem('--registrar-envio-todos')) {
    const alvo = tem('--registrar-envio-todos') ? pend.map((p) => p.rel) : argv.slice(iReg + 1).filter((a) => !a.startsWith('--'));
    const porRel = new Map(pend.map((p) => [p.rel, p]));
    const doc = lerJson(root, ENVIADOS_REL, { enviados: {} });
    const mapa = { ...(doc.enviados || {}) };
    const em = new Date().toISOString();
    let n = 0;
    for (const rel of alvo) {
      const p = porRel.get(rel);
      if (!p) { console.error(`  ✗ ${rel} não é pendente — nada a registrar`); continue; }
      mapa[rel] = { sha256: p.sha, em };
      n++;
    }
    gravarEnviados(root, mapa);
    console.log(`registrados como enviados ao Cowork: ${n} — commite ${ENVIADOS_REL}`);
    return n === alvo.length ? 0 : 1;
  }

  const naoEnviados = pend.filter((p) => !p.enviado);
  // O canal de RETORNO é `cowork-inbox/` (pedido, playbook, recibo) — é o único que o hook
  // `block-design-sync-without-optin` libera sem opt-in. Tela/CSS mudados no espelho ficam FORA
  // do plano automático: o espelho de tela é build-only, e subir isso é decisão [W].
  const noCanal = (rel) => rel.startsWith('cowork-inbox/');
  if (tem('--plano')) {
    console.log(JSON.stringify({
      projectId: COWORK_PROJECT_ID,
      localDir: join(root, ESPELHO_REL),
      writes: naoEnviados.filter((p) => noCanal(p.rel)).map((p) => p.rel),
      deletes: [],
      fora_do_canal: naoEnviados.filter((p) => !noCanal(p.rel)).map((p) => p.rel),
      depois: 'DesignSync.write_files com localPath = o mesmo rel; em seguida --registrar-envio <os writes>. fora_do_canal: decisão [W], não sobe sozinho.',
    }, null, 2));
    return 0;
  }

  const recibos = naoEnviados.filter((p) => p.recibo).length;
  console.log(`pendentes para o Cowork: ${naoEnviados.length} não enviado(s) (${recibos} recibo(s)) · ${pend.length - naoEnviados.length} enviado(s) aguardando o retorno`);
  for (const p of naoEnviados) console.log(`  ${p.motivo === 'fora-do-bundle' ? '+' : '~'} ${p.rel}${p.recibo ? '  (recibo)' : ''}`);
  if (naoEnviados.length) console.log(`\n  Suba antes do próximo retorno: --plano -> DesignSync.finalize_plan/write_files -> --registrar-envio-todos`);
  return tem('--check') && naoEnviados.length ? 1 : 0;
}

const direto = process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (direto) {
  try { process.exit(principal(process.argv.slice(2))); }
  catch (e) { console.error(e.message); process.exit(/NÃO MEDI/.test(e.message) ? 2 : 1); }
}
