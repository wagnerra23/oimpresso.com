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
//   node scripts/design-sync/pendentes-cowork.mjs --projeto copia ...  # o 2º projeto (abaixo)
//   node scripts/design-sync/pendentes-cowork.mjs --conferir <dir> [--projeto copia]
//   node scripts/design-sync/pendentes-cowork.mjs --resumo [--so <rel>...]  # os 2 projetos, 1 linha cada
//
// ── UM GIT, DOIS PROJETOS NO CLAUDE DESIGN ([W] 2026-09-29) ─────────────────────
// [W], textual: "quero 1 git e dois desing syncronizado com ultimo git". Reabre a D4 de
// 2026-09-25 (proposta 2026-09-24-sincronia-entre-contas-cowork-por-dono §9) no ponto que ela
// deixou aberto: a conta da Maiara/Felipe não entra no projeto do [W] (conta pessoal; o
// compartilhamento do Claude Design só vale dentro de organização Team/Enterprise). A fonte é
// o git (`prototipo-ui/cowork/Wagner/`); os dois projetos recebem cópia dele:
//   · `w`     — o projeto de telas do [W]. Critério de sempre: sha do git × bundle ativo.
//   · `copia` — PROJETOS.telasFelipe ("PRODUTO UNIFICADO V2"), onde a Maiara vê a tela. Não
//               manda retorno, então não há bundle: pendente = sha do git ≠ sha do último
//               envio registrado para ESTE projeto. Estado próprio, para as contas não se pisarem.
// Cada conta sobe o seu: do login do [W] só o `w` é gravável; o `copia` sobe da sessão
// da Maiara — e qualquer um dos dois, conferido, satisfaz o check do espelho. O upload continua fora deste script (ADR 0315): ele SABE o que subir, não sobe.
//
// --conferir <dir>: depois de subir, o agente lê de volta (`get_file`) e salva cada arquivo em
// <dir>/<rel>. O comando compara byte a byte com o git e, só se TODOS baterem, registra o envio
// — e grava a rodada no ledger do `cowork-mirror-freshness` (é o que o check "espelho — mexeu
// depois de verificar" lê), pelo próprio `--snapshot-from`/`--compare` dele, marcando de qual
// projeto veio a leitura. Vale para os DOIS projetos ([W] 2026-09-29: "a maiara deveria poder
// fazer isso"): antes só o `w` gravava, e um PR da Maiara que tocasse o espelho só destravava
// com uma sessão logada na conta do [W]. Com o git como fonte, ler de volta do `copia` prova o
// mesmo fato — o arquivo commitado é o que está num projeto do Claude Design. O `w` não fica
// esquecido: sem retorno que o traga, o arquivo segue pendente no `--projeto w`, e o
// receber-handoff recusa retorno que o sobrescreveria.
// ⚠️ NÃO elimina a cópia manual: `get_file` devolve arquivo pequeno INLINE, e salvar o inline é
// escrita do agente (ADR 0389). O que ele elimina são os 4 passos à mão e o risco de registrar
// envio sem conferir — a comparação é que prova a cópia, não a confiança nela.

import { createHash } from 'node:crypto';
import { execFileSync, spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, readdirSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { PROJETOS } from '../design/protocolo.config.mjs';

const AQUI = dirname(fileURLToPath(import.meta.url));
export const REPO = resolve(AQUI, '..', '..');
export const ESPELHO_REL = 'prototipo-ui/cowork/Wagner';
export const ATIVO_REL = 'scripts/design-sync/state/active-bundle.json';
export const ENVIADOS_REL = 'scripts/design-sync/state/enviados-cowork.json';
export const COWORK_PROJECT_ID = '019dcfd3-6ef2-7ee6-8512-b1b0e5544e58';

/** Os dois destinos. O ID do 2º vem do painel (`PROJETOS.telasFelipe`), nunca repetido aqui. */
export const DESTINOS = {
  w: { projectId: COWORK_PROJECT_ID, enviados: ENVIADOS_REL, criterio: 'bundle' },
  copia: { projectId: PROJETOS.telasFelipe.id, enviados: 'scripts/design-sync/state/enviados-cowork-copia.json', criterio: 'envio' },
};

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

/**
 * Critério do projeto-cópia (função PURA). Sem bundle de retorno, a única base é o que já foi
 * enviado PARA ELE: pendente = nunca enviado, ou o git mudou desde o último envio.
 */
export function calcularPendentesCopia({ arquivos, enviados = {} }) {
  const out = [];
  for (const { rel, sha } of arquivos) {
    if (foraDoEscopo(rel)) continue;
    const env = enviados[rel]?.sha256;
    if (env === sha) continue;
    out.push({ rel, sha, motivo: env === undefined ? 'nunca-enviado' : 'mudou-desde-o-envio', recibo: eRecibo(rel), enviado: false });
  }
  return out.sort((a, b) => a.rel.localeCompare(b.rel));
}

export function pendentesDoDestino(root, chave) {
  const d = DESTINOS[chave];
  if (!d) throw new Error(`projeto desconhecido: ${chave} (use ${Object.keys(DESTINOS).join(' | ')})`);
  if (d.criterio === 'bundle') return pendentesDoRepo(root);
  const enviados = lerJson(root, d.enviados, { enviados: {} }).enviados || {};
  return calcularPendentesCopia({ arquivos: arquivosDoEspelho(root), enviados });
}

const NOTAS = {
  w: 'Arquivos que o Code subiu ao Cowork (DesignSync) e que o bundle ativo ainda não trouxe de volta. Escrito por pendentes-cowork.mjs; o receber-handoff limpa o que o retorno confirmar.',
  copia: 'Último envio de cada arquivo de prototipo-ui/cowork/Wagner/ ao projeto-cópia (PROJETOS.telasFelipe). Escrito por pendentes-cowork.mjs --projeto copia. Não é limpo por retorno: este projeto não gera retorno.',
};

function gravarEnviados(root, mapa, chave = 'w') {
  const ordenado = Object.fromEntries(Object.entries(mapa).sort(([a], [b]) => a.localeCompare(b)));
  writeFileSync(join(root, DESTINOS[chave].enviados), JSON.stringify({ _nota: NOTAS[chave], enviados: ordenado }, null, 2) + '\n');
}

function registrar(root, chave, pend, alvo) {
  const porRel = new Map(pend.map((p) => [p.rel, p]));
  const doc = lerJson(root, DESTINOS[chave].enviados, { enviados: {} });
  const mapa = { ...(doc.enviados || {}) };
  const em = new Date().toISOString();
  let n = 0;
  for (const rel of alvo) {
    const p = porRel.get(rel);
    if (!p) { console.error(`  ✗ ${rel} não é pendente — nada a registrar`); continue; }
    mapa[rel] = { sha256: p.sha, em };
    n++;
  }
  gravarEnviados(root, mapa, chave);
  console.log(`registrados como enviados ao projeto ${chave}: ${n} — commite ${DESTINOS[chave].enviados}`);
  return n === alvo.length ? 0 : 1;
}

/** Lista recursiva de arquivos sob `dir`, como paths relativos com `/`. */
function listarLidos(dir, base = dir) {
  const out = [];
  for (const nome of readdirSync(dir)) {
    const abs = join(dir, nome);
    if (statSync(abs).isDirectory()) out.push(...listarLidos(abs, base));
    else out.push(abs.slice(base.length + 1).split('\\').join('/'));
  }
  return out;
}

/**
 * Compara o que foi LIDO DE VOLTA do projeto com o git (função pura sobre shas).
 * `lidos` = [{ rel, sha }]; `doGit` = Map rel -> sha. Devolve o veredito por arquivo.
 */
export function compararLidos(lidos, doGit) {
  return lidos.map(({ rel, sha }) => {
    const g = doGit.get(rel);
    return { rel, veredito: g === undefined ? 'FORA-DO-GIT' : g === sha ? 'IGUAL' : 'DIFERENTE' };
  });
}

function conferir(root, chave, dir, pend) {
  if (!existsSync(dir)) throw new Error(`NÃO MEDI: ${dir} não existe`);
  const lidos = listarLidos(dir).map((rel) => ({ rel, sha: sha256(readFileSync(join(dir, rel))) }));
  if (!lidos.length) throw new Error(`NÃO MEDI: ${dir} está vazio — nada foi lido de volta`);
  const doGit = new Map(arquivosDoEspelho(root).map((a) => [a.rel, a.sha]));
  const res = compararLidos(lidos, doGit);
  for (const r of res) console.log(`  ${r.veredito === 'IGUAL' ? '✓' : '✗'} ${r.veredito.padEnd(11)} ${r.rel}`);
  const ruins = res.filter((r) => r.veredito !== 'IGUAL');
  if (ruins.length) {
    console.error(`\n✗ ${ruins.length} de ${res.length} não batem com o git — NADA foi registrado. Suba de novo ou confira a leitura.`);
    return 1;
  }
  {
    // O ledger é do cowork-mirror-freshness (dono do check do espelho): chamamos os modos dele,
    // nunca escrevemos o arquivo por fora.
    const tmp = mkdtempSync(join(tmpdir(), 'conferir-'));
    try {
      const jsons = join(tmp, 'getfile');
      mkdirSync(jsons);
      for (const { rel } of lidos) {
        writeFileSync(join(jsons, rel.split('/').join('__') + '.json'), JSON.stringify({
          method: 'get_file', path: rel, content: readFileSync(join(dir, rel), 'utf8'), isBase64: false, truncated: false,
        }));
      }
      const fr = join(root, 'scripts', 'governance', 'cowork-mirror-freshness.mjs');
      const snap = join(tmp, 'snap.json');
      const ledgerPath = join(root, 'scripts', 'governance', '.cowork-freshness-ledger.json');
      const ledgerAntes = existsSync(ledgerPath) ? readFileSync(ledgerPath, 'utf8') : null;
      for (const args of [['--snapshot-from', jsons, '--emit-snapshot', snap], ['--compare', snap, '--check', '--ledger', '--incluir-lidos', '--origem', 'agente', '--projeto-cowork', chave]]) {
        const r = spawnSync(process.execPath, [fr, ...args], { cwd: root, encoding: 'utf8' });
        if (r.status !== 0) {
          // O `--ledger` grava ANTES do `--check` sair 1: sem restaurar, "nada registrado" mentiria.
          if (ledgerAntes === null) rmSync(ledgerPath, { force: true }); else writeFileSync(ledgerPath, ledgerAntes);
          console.error(r.stdout + r.stderr); console.error(`✗ cowork-mirror-freshness ${args[0]} saiu ${r.status} — nada registrado (ledger restaurado)`); return 1;
        }
      }
      // A contagem vem da ENTRADA GRAVADA, não de `lidos.length` (2026-09-30). Antes a mensagem
      // afirmava "N verificado(s)" pelo que ENTROU, e a rodada gravada tinha `verified: []` para
      // todo `.md` — o required "mexeu depois de verificar" reprovava com o sucesso impresso
      // (#8284, #8291). Arquivo lido que não virou prova é falha, nunca contagem.
      const entradas = JSON.parse(readFileSync(ledgerPath, 'utf8'));
      const gravada = entradas[entradas.length - 1] || {};
      const provados = new Set(gravada.verified || []);
      const semProva = lidos.map((l) => l.rel).filter((rel) => !provados.has(rel) || !(gravada.verifiedHash || {})[rel]);
      if (semProva.length) {
        // A rodada sem prova sai do ledger: registro vazio com cara de verificação é o defeito.
        if (ledgerAntes === null) rmSync(ledgerPath, { force: true }); else writeFileSync(ledgerPath, ledgerAntes);
        console.error(`\n✗ o ledger NÃO recebeu prova de ${semProva.length} de ${lidos.length}: ${semProva.join(', ')} — nada registrado (ledger restaurado).`);
        return 1;
      }
      console.log(`\n✓ ledger do espelho atualizado (${provados.size} verificado(s) gravados, projeto ${chave}) — commite scripts/governance/.cowork-freshness-ledger.json`);
    } finally { rmSync(tmp, { recursive: true, force: true }); }
  }
  // Registra só o que ainda consta como pendente; conferir arquivo já em dia não é erro.
  const pendentes = new Set(pend.map((p) => p.rel));
  const alvo = lidos.map((l) => l.rel).filter((rel) => pendentes.has(rel));
  return alvo.length ? registrar(root, chave, pend, alvo) : 0;
}

function principal(argv) {
  const root = REPO;
  const tem = (f) => argv.includes(f);
  const valor = (f) => { const i = argv.indexOf(f); return i === -1 ? null : argv[i + 1]; };
  const chave = valor('--projeto') || 'w';
  if (!DESTINOS[chave]) throw new Error(`projeto desconhecido: ${chave} (use ${Object.keys(DESTINOS).join(' | ')})`);

  if (tem('--resumo')) {
    // --so <rel> [<rel>...]: restringe aos arquivos informados (o hook passa os do PR).
    const iSo = argv.indexOf('--so');
    const so = iSo === -1 ? null : new Set(argv.slice(iSo + 1).filter((a) => !a.startsWith('--')));
    for (const k of Object.keys(DESTINOS)) {
      const nao = pendentesDoDestino(root, k).filter((p) => !p.enviado && (!so || so.has(p.rel)));
      console.log(`${k}: ${nao.length} a subir${nao.length ? ' — ' + nao.slice(0, 8).map((p) => p.rel).join(', ') + (nao.length > 8 ? ', …' : '') : ''}`);
    }
    return 0;
  }

  const pend = pendentesDoDestino(root, chave);

  const iConf = argv.indexOf('--conferir');
  if (iConf !== -1) return conferir(root, chave, resolve(argv[iConf + 1] || ''), pend);

  if (tem('--limpar-confirmados')) {
    if (chave !== 'w') { console.error('--limpar-confirmados só vale para o projeto w (o único que gera retorno)'); return 1; }
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
    const alvo = tem('--registrar-envio-todos') ? pend.map((p) => p.rel) : argv.slice(iReg + 1).filter((a) => !a.startsWith('--') && a !== chave);
    return registrar(root, chave, pend, alvo);
  }

  const naoEnviados = pend.filter((p) => !p.enviado);
  // No projeto `w`, o canal de RETORNO é `cowork-inbox/` (pedido, playbook, recibo) — é o único
  // que o hook `block-design-sync-without-optin` libera sem opt-in; tela/CSS ficam em
  // `fora_do_canal` (decisão [W]). No `copia` não há canal isento: tudo exige o opt-in da sessão.
  const noCanal = (rel) => chave !== 'w' || rel.startsWith('cowork-inbox/');
  if (tem('--plano')) {
    console.log(JSON.stringify({
      projeto: chave,
      projectId: DESTINOS[chave].projectId,
      localDir: join(root, ESPELHO_REL),
      writes: naoEnviados.filter((p) => noCanal(p.rel)).map((p) => p.rel),
      deletes: [],
      fora_do_canal: naoEnviados.filter((p) => !noCanal(p.rel)).map((p) => p.rel),
      depois: 'DesignSync.write_files com localPath = o mesmo rel (até 256 por chamada); get_file de cada um salvo em <dir>/<rel>; então --conferir <dir>' + (chave === 'w' ? '' : ' --projeto ' + chave) + '. fora_do_canal: decisão [W], não sobe sozinho.',
    }, null, 2));
    return 0;
  }

  const recibos = naoEnviados.filter((p) => p.recibo).length;
  const aguardando = chave === 'w' ? ` · ${pend.length - naoEnviados.length} enviado(s) aguardando o retorno` : '';
  console.log(`pendentes para o projeto ${chave}: ${naoEnviados.length} não enviado(s) (${recibos} recibo(s))${aguardando}`);
  for (const p of naoEnviados) console.log(`  ${p.motivo === 'fora-do-bundle' || p.motivo === 'nunca-enviado' ? '+' : '~'} ${p.rel}${p.recibo ? '  (recibo)' : ''}`);
  if (naoEnviados.length) console.log(`\n  Suba: --plano -> DesignSync.finalize_plan/write_files -> get_file de volta -> --conferir <dir>`);
  return tem('--check') && naoEnviados.length ? 1 : 0;
}

const direto = process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (direto) {
  try { process.exit(principal(process.argv.slice(2))); }
  catch (e) { console.error(e.message); process.exit(/NÃO MEDI/.test(e.message) ? 2 : 1); }
}
