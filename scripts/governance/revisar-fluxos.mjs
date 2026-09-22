#!/usr/bin/env node
// @ts-check
/**
 * revisar-fluxos.mjs — revisão derivada dos fluxos e das máquinas que eles citam.
 *
 * Descobre `memory/reference/FLUXO-*.md`, verifica o contrato mínimo de documentação,
 * resolve os paths de máquinas citados, procura invocadores e provas, e opcionalmente
 * executa os donos já existentes. Não usa baseline para concluir saúde: ausência de
 * oráculo vira NÃO MEDIDO.
 *
 * Uso:
 *   node scripts/governance/revisar-fluxos.mjs
 *   node scripts/governance/revisar-fluxos.mjs --json
 *   node scripts/governance/revisar-fluxos.mjs --check
 *   node scripts/governance/revisar-fluxos.mjs --strict
 *   node scripts/governance/revisar-fluxos.mjs --execute --check
 *   node scripts/governance/revisar-fluxos.mjs --live --execute
 */
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { basename, dirname, join, relative, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';

const posix = (p) => p.replaceAll('\\', '/');
const argv = process.argv.slice(2);
const arg = (name) => argv.includes(name) ? argv[argv.indexOf(name) + 1] : null;
const ROOT = resolve(arg('--root') || process.cwd());
const JSON_OUT = argv.includes('--json');
const CHECK = argv.includes('--check');
const STRICT = argv.includes('--strict');
const EXECUTE = argv.includes('--execute');
const LIVE = argv.includes('--live');

const DIMENSOES = {
  entrada: /\bentrada\b/i,
  invocador: /invocador|quem (?:executa|transforma)|workflow|hook/i,
  decisao: /decis[aã]o|bloque|recusa|veredito/i,
  saida_duravel: /sa[ií]da|\bgrava|artefato|recibo/i,
  falso_verde: /falso[- ]verde/i,
  prova: /\bprova|\bteste|selftest|bite[- ]test/i,
  limite: /\blimite|n[aã]o prova|lacuna residual|n[aã]o mede/i,
};

function walk(dir, accept, out = []) {
  if (!existsSync(dir)) return out;
  for (const ent of readdirSync(dir, { withFileTypes: true })) {
    const p = join(dir, ent.name);
    if (ent.isDirectory()) walk(p, accept, out);
    else if (accept(p)) out.push(p);
  }
  return out;
}

function read(p) { try { return readFileSync(p, 'utf8'); } catch { return ''; } }

/** Extrai somente paths executáveis; URL e exemplo genérico ficam fora. */
export function extrairMaquinas(texto) {
  const rx = /(?:`|\]\()((?:scripts|\.github\/workflows|\.claude\/hooks)\/[A-Za-z0-9_./-]+\.(?:mjs|js|cjs|ya?ml))(?:`|[)#])/g;
  return [...new Set([...String(texto).matchAll(rx)].map((m) => m[1]).filter((p) => !/\.test\./.test(p)))].sort();
}

export function auditarDocumento(rel, texto) {
  const dimensoes = Object.fromEntries(Object.entries(DIMENSOES).map(([k, rx]) => [k, rx.test(texto)]));
  const frontmatter = /^---[\s\S]*?^---/m.test(texto);
  const canonico = /^authority:\s*canonical\s*$/m.test(texto);
  const ativo = /^lifecycle:\s*ativo\s*$/m.test(texto);
  const etapas = [...texto.matchAll(/^##+\s+(.+)$/gm)].map((m) => m[1]).filter((h) => !/^como ler/i.test(h));
  return {
    arquivo: rel, frontmatter, canonico, ativo, etapas: etapas.length,
    dimensoes, faltam: Object.entries(dimensoes).filter(([, ok]) => !ok).map(([k]) => k),
    maquinas: extrairMaquinas(texto),
  };
}

function fontesInvocadoras(root) {
  const paths = [
    ...walk(join(root, '.github', 'workflows'), (p) => /\.ya?ml$/.test(p)),
    ...walk(join(root, '.claude'), (p) => /settings.*\.json$/.test(p)),
    join(root, 'package.json'),
    ...walk(join(root, 'scripts'), (p) => /\.(?:mjs|js|cjs)$/.test(p) && !/\.test\./.test(p)),
  ].filter(existsSync);
  return paths.map((p) => ({ rel: posix(relative(root, p)), text: read(p) }));
}

function fontesDeProva(root) {
  const paths = [
    ...walk(join(root, 'scripts'), (p) => /\.test\.(?:mjs|js|cjs)$/.test(p)),
    ...walk(join(root, '.claude', 'hooks'), (p) => /\.test\.(?:mjs|js|cjs)$/.test(p)),
  ];
  return paths.map((p) => ({ rel: posix(relative(root, p)), text: read(p) }));
}

function linhasExecutaveis(texto) {
  return String(texto).split(/\r?\n/).filter((l) => !/^\s*#/.test(l));
}

export function auditarMaquina(root, rel, fontes = fontesInvocadoras(root)) {
  const abs = join(root, rel);
  const base = basename(rel);
  const stem = base.replace(/\.(?:mjs|js|cjs|ya?ml)$/, '');
  const self = posix(rel);
  const invoca = (f) => {
    const linhas = f.text.split(/\r?\n/).filter((l) => l.includes(self) || l.includes(base));
    if (f.rel.startsWith('.github/workflows/') || f.rel === 'package.json' || /settings.*\.json$/.test(f.rel)) return linhas.length > 0;
    return linhas.some((l) => /\b(?:import|from|spawnSync|execFileSync|execSync|node)\b/.test(l));
  };
  const invocadores = fontes
    .filter((f) => f.rel !== self && invoca(f))
    .map((f) => f.rel);
  const sibling = rel.replace(/\.(mjs|js|cjs)$/, '.test.$1');
  const provas = fontesDeProva(root);
  const stemQuoted = new RegExp(`['\"]${stem.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}['\"]`);
  const candidatos = provas.filter((p) =>
    p.rel === sibling || linhasExecutaveis(p.text).some((l) =>
      l.includes(self) || l.includes(base) || (rel.startsWith('.claude/hooks/') && stemQuoted.test(l)),
    ),
  );
  const testeLigado = candidatos.find((p) => fontes.some((f) =>
    f.rel.startsWith('.github/workflows/') && linhasExecutaveis(f.text).some((l) => l.includes(p.rel)),
  ));
  const selftestLigado = /--selftest\b/.test(read(abs)) && fontes.some((f) =>
    f.rel.startsWith('.github/workflows/') && linhasExecutaveis(f.text).some((l) => l.includes(self) && /--selftest\b/.test(l)),
  );
  const workflow = rel.startsWith('.github/workflows/');
  const evento = workflow && /^on\s*:/m.test(read(abs));
  return {
    arquivo: rel,
    existe: existsSync(abs) && statSync(abs).isFile(),
    invocadores: workflow && evento ? ['evento do próprio workflow'] : invocadores,
    prova: testeLigado || selftestLigado ? 'teste+wiring' : candidatos.length ? 'teste-sem-wiring' : 'nao-localizada',
    teste: selftestLigado ? `${rel} --selftest` : testeLigado?.rel || candidatos[0]?.rel || null,
  };
}

const PASSOS = [
  ['inventario', ['scripts/governance/maquinas-inventario.mjs', '--check']],
  ['selftests-wiring', ['scripts/governance/selftest-registry-check.mjs', '--check']],
  ['catracas-mordem', ['scripts/governance/gate-selftest.mjs', '--json']],
  ['jornada-completa', ['scripts/governance/fluxo-jornada.test.mjs']],
];

function executar(root, [id, cmd]) {
  if (!existsSync(join(root, cmd[0]))) return { id, status: 'NAO_MEDIDO', motivo: `ausente: ${cmd[0]}` };
  const r = spawnSync(process.execPath, cmd, { cwd: root, encoding: 'utf8', windowsHide: true, maxBuffer: 64 * 1024 * 1024 });
  if (r.error) return { id, status: 'NAO_MEDIDO', motivo: r.error.message };
  const saida = `${r.stdout || ''}${r.stderr || ''}`.trim().split(/\r?\n/).slice(-8).join('\n');
  return { id, status: r.status === 0 ? 'PASSOU' : 'FALHOU', exit: r.status, saida };
}

export function slugDoRemote(remote) {
  const m = String(remote || '').trim().match(/github\.com[/:]([^/]+)\/(.+)$/i);
  return m ? `${m[1]}/${m[2].replace(/\.git$/i, '')}` : null;
}

function repoSlug(root) {
  const r = spawnSync('git', ['config', '--get', 'remote.origin.url'], { cwd: root, encoding: 'utf8', windowsHide: true });
  return slugDoRemote(r.stdout);
}

function medirEnforcementVivo(root) {
  const slug = repoSlug(root);
  if (!slug) return { status: 'NAO_MEDIDO', motivo: 'remote GitHub não resolvido' };
  const endpoints = [`repos/${slug}/rules/branches/main`, `repos/${slug}/branches/main/protection/required_status_checks`];
  const recibos = [];
  for (const endpoint of endpoints) {
    const r = spawnSync('gh', ['api', endpoint], { cwd: root, encoding: 'utf8', windowsHide: true });
    if (r.error || r.status !== 0) return { status: 'NAO_MEDIDO', motivo: `${endpoint}: ${(r.error?.message || r.stderr || `exit ${r.status}`).trim()}` };
    recibos.push({ endpoint, bytes: Buffer.byteLength(r.stdout || '') });
  }
  return { status: 'MEDIDO', fonte: 'GitHub API vivo', recibos };
}

export function revisar(root, opts = {}) {
  const dir = join(root, 'memory', 'reference');
  const arquivos = existsSync(dir) ? readdirSync(dir).filter((n) => /^FLUXO-.*\.md$/i.test(n)).sort() : [];
  const documentos = arquivos.map((n) => auditarDocumento(`memory/reference/${n}`, read(join(dir, n))));
  const refs = [...new Set(documentos.flatMap((d) => d.maquinas))].sort();
  const fontes = fontesInvocadoras(root);
  const maquinas = refs.map((p) => auditarMaquina(root, p, fontes));
  const execucoes = opts.execute ? PASSOS.map((p) => executar(root, p)) : [];
  const enforcement = opts.live ? medirEnforcementVivo(root) : { status: 'NAO_MEDIDO', motivo: 'use --live para consultar o GitHub; baseline local não é prova viva' };
  const falhasConcretas = [
    ...maquinas.filter((m) => !m.existe).map((m) => `${m.arquivo}: path fantasma`),
    ...execucoes.filter((e) => e.status === 'FALHOU').map((e) => `${e.id}: exit ${e.exit}`),
  ];
  const pendencias = [
    ...documentos.flatMap((d) => d.faltam.map((x) => `${d.arquivo}: falta ${x}`)),
    ...maquinas.filter((m) => m.existe && m.invocadores.length === 0).map((m) => `${m.arquivo}: sem invocador localizado`),
    ...maquinas.filter((m) => m.existe && m.prova !== 'teste+wiring').map((m) => `${m.arquivo}: prova ${m.prova}`),
  ];
  if (documentos.length === 0) falhasConcretas.push('zero documentos FLUXO-*.md localizados');
  return {
    _meta: { schema: 'revisar-fluxos/v1', gerado_em: new Date().toISOString(), baseline_usada: false },
    resumo: { fluxos: documentos.length, maquinas_referenciadas: maquinas.length, falhas_concretas: falhasConcretas.length, pendencias: pendencias.length },
    documentos, maquinas, execucoes, enforcement, falhas_concretas: falhasConcretas, pendencias,
    plano_code: [
      'corrigir primeiro cada path fantasma ou máquina sem invocador',
      'para cada prova ausente, criar BITE (defeito) e RELEASE (controle bom) e ligar o teste',
      'preencher no fluxo entrada, decisão, saída durável, falso-verde, prova e limite',
      'consultar o runtime com --live; indisponibilidade permanece NÃO MEDIDO',
      'repetir --execute --live --strict até zero falha e zero pendência',
    ],
  };
}

function imprimir(r) {
  console.log('\nREVISÃO DOS FLUXOS DO SISTEMA — fonte derivada, sem baseline\n');
  console.log(`Fluxos: ${r.resumo.fluxos} · máquinas citadas: ${r.resumo.maquinas_referenciadas} · falhas: ${r.resumo.falhas_concretas} · pendências: ${r.resumo.pendencias}`);
  for (const d of r.documentos) console.log(`  ${d.faltam.length ? '△' : '✓'} ${d.arquivo} · ${d.etapas} seção(ões) · faltam: ${d.faltam.join(', ') || 'nada'}`);
  for (const e of r.execucoes) {
    console.log(`  ${e.status === 'PASSOU' ? '✓' : e.status === 'FALHOU' ? '✗' : '○'} ${e.id}: ${e.status}`);
    if (e.status !== 'PASSOU' && e.saida) console.log(e.saida.split(/\r?\n/).map((l) => `      ${l}`).join('\n'));
  }
  console.log(`  ○ enforcement: ${r.enforcement.status}${r.enforcement.motivo ? ` — ${r.enforcement.motivo}` : ''}`);
  if (r.falhas_concretas.length) console.log(`\nFALHAS CONCRETAS\n- ${r.falhas_concretas.join('\n- ')}`);
  if (r.pendencias.length) console.log(`\nPENDÊNCIAS PARA O CODE PESQUISAR\n- ${r.pendencias.join('\n- ')}`);
  console.log('\nPróximo passo: corrigir falhas; pesquisar cada pendência na fonte e no runtime; repetir com --execute --live --strict.\n');
}

const isMain = resolve(process.argv[1] || '') === resolve(new URL(import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1'));
if (isMain) {
  const resultado = revisar(ROOT, { execute: EXECUTE, live: LIVE });
  if (JSON_OUT) console.log(JSON.stringify(resultado, null, 2)); else imprimir(resultado);
  if (CHECK && resultado.falhas_concretas.length) process.exit(1);
  if (STRICT && (resultado.falhas_concretas.length || resultado.pendencias.length)) process.exit(1);
  if ((CHECK || STRICT) && LIVE && resultado.enforcement.status !== 'MEDIDO') process.exit(2);
}
