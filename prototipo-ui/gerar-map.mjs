#!/usr/bin/env node
// gerar-map.mjs — deriva o ESQUELETO do <tela>.map.json a partir do <tela>-gap.md.
//
// EIXO deste mapa (deconflito 2026-07-09 — RUNBOOK Fase 1 §"Deconflito dos 3 eixos"): ANCHOR-MAP
// POR REGIÃO de tela (por PARTE, o bloco do protótipo ↔ arquivo/range da tela viva, carregando o
// sha do protótipo que o gerou) — parente do anchor-lint, NÃO o "Code Connect" do projeto. O
// Code Connect (eixo componente, âncora estável, reusável entre telas) é o
// prototipo-ui/component-registry.json; o roteamento de ARQUIVOS na ingestão é o cowork-map.json.
// Este artefato ataca o gap canônico #1 do estado-da-arte (memory/sessions/
// 2026-06-22-arte-design-to-code-sdd.md: mapeamento re-derivado em PROSA por tela a cada sessão)
// tornando-o máquina-legível e versionado — saída documentada da FASE 1 do
// RUNBOOK-aplicar-prototipo-orquestracao.md.
//
// Reusa parsePartes/ehAcionavel/slug/resolveGap/frontmatterBlock/fmVal de gerar-contrato.mjs —
// a tabela de PARTES do gap.md é a MESMA fonte que o contrato região-a-região consome (1 fato =
// 1 lugar; anti-bifurcação — dívida confessada no RUNBOOK §Refactor, aqui evitada de propósito).
//
// Diferença de gerar-contrato.mjs: o map.json cobre TODAS as partes (não só as acionáveis — o
// 4º veredito "vivo à frente"/"no-op" também precisa de âncora dupla pra provar a alegação,
// RUNBOOK Fase 1 regra 1 "Âncora dupla obrigatória... sem âncora, NAO_VERIFICADO"), e persiste
// `prototipo_sha` (git log --format=%h do(s) arquivo(s)-fonte do protótipo) — a Fase 4 usa isso
// pra ABORTAR e regenerar quando o protótipo re-exportar (sha mudou).
//
// NÃO fabrica linha — `grep -n` real é do humano/agente da Fase 1 (regra dura, mesma do
// RUNBOOK). O gerador só monta o ESQUELETO: id + status inicial + acao (contexto) + arquivo
// resolvido (quando determinístico) com `linhas: "TODO"` — nunca um número inventado.
//
// Uso:
//   node prototipo-ui/gerar-map.mjs <gap.md|Mod/Tela>       # emite o esqueleto JSON (stdout)
//   node prototipo-ui/gerar-map.mjs --selftest                # fixture hermético
//
// Verificação (anchors existem? sha ficou stale? % telas mapeadas?) NÃO é deste script — é do
// scripts/governance/design-code-map-check.mjs (mesma separação gerar-contrato × contrato-de-tela).
//
// Exit: 0 = ok | 1 = gap.md não-parseável | 2 = uso

import { readFileSync, existsSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { join, resolve, dirname, basename } from 'node:path';
import { fileURLToPath } from 'node:url';
import { slug, ehAcionavel, parsePartes, resolveGap, frontmatterBlock, fmVal } from './gerar-contrato.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO = resolve(HERE, '..');

// extrai os caminhos de arquivo do campo frontmatter `prototipo:` — formato real observado:
// "prototipo-ui/cowork/financeiro-page.jsx + financeiro-ops.jsx (NOVO) @ 4e3aacfc0f (import ...)"
// 1º token = path completo; tokens seguintes (após "+") são basenames no MESMO diretório.
export function resolverArquivosPrototipo(fmProtoField) {
  if (!fmProtoField) return [];
  const dirTok = fmProtoField.match(/([\w./-]+\/)?[\w.-]+\.(?:jsx|tsx|html|css|php)/);
  if (!dirTok) return [];
  const dir = dirTok[0].includes('/') ? dirTok[0].replace(/[\w.-]+\.(?:jsx|tsx|html|css|php)$/, '') : '';
  const out = [];
  for (const m of fmProtoField.matchAll(/([\w./-]+\/)?[\w.-]+\.(?:jsx|tsx|html|css|php)/g)) {
    const tok = m[0];
    out.push(tok.includes('/') ? tok : dir + tok);
  }
  return [...new Set(out)];
}

// 1º path resources/js/Pages/... (ou fixture) citado no campo `tela_viva:`.
export function resolverArquivoVivo(fmTelaVivaField) {
  const m = String(fmTelaVivaField || '').match(/((?:resources\/js\/Pages|prototipo-ui\/fixtures)\/[\w./-]+\.(?:tsx|jsx))/);
  return m ? m[1] : null;
}

// sha do estado ATUAL do(s) arquivo(s) do protótipo — git log -1 --format=%h|%ct por arquivo,
// devolve o do commit MAIS RECENTE entre eles (o map fica válido até o mais novo mudar de novo).
// Sem histórico git rastreável (arquivo untracked / repo sem .git) → 'sem-historico', NUNCA lança
// (staleness fica indeterminada, não falsa — o checker trata isso como WARN, não DRIFT).
export function computeGitSha(relPaths, root = REPO) {
  let melhor = null;
  for (const rel of relPaths) {
    const abs = join(root, rel);
    if (!existsSync(abs)) continue;
    try {
      const out = execFileSync('git', ['log', '-1', '--format=%h|%ct', '--', rel], { cwd: root, encoding: 'utf8' }).trim();
      if (!out) continue;
      const [sha, ct] = out.split('|');
      const ts = Number(ct);
      if (!melhor || ts > melhor.ts) melhor = { sha, ts };
    } catch { /* git ausente/erro — segue sem esse arquivo */ }
  }
  return melhor ? melhor.sha : 'sem-historico';
}

export function gerar(gapPath, { root = REPO, hoje = null } = {}) {
  const md = readFileSync(gapPath, 'utf8');
  const fm = frontmatterBlock(md);
  const partes = parsePartes(md);
  if (!partes) return { erro: `gap.md sem tabela de partes com colunas "Parte" + "Ação": ${gapPath}` };

  const tela = fmVal(fm, 'tela') || basename(gapPath).replace(/-gap\.md$/, '');
  const protoField = fmVal(fm, 'prototipo');
  const telaVivaField = fmVal(fm, 'tela_viva');
  const arquivosPrototipo = resolverArquivosPrototipo(protoField);
  const arquivoVivo = resolverArquivoVivo(telaVivaField);
  const prototipo_sha = arquivosPrototipo.length ? computeGitSha(arquivosPrototipo, root) : 'sem-historico';

  const seen = new Map(); // desambigua ids repetidos (partes com mesmo nome em tabelas maiores)
  const mapPartes = partes.map((p) => {
    let id = slug(p.parte);
    const n = (seen.get(id) || 0) + 1; seen.set(id, n);
    if (n > 1) id = `${id}-${n}`;
    return {
      id,
      prototipo: { arquivo: arquivosPrototipo[0] || 'TODO', linhas: 'TODO' },
      vivo: { arquivo: arquivoVivo || 'TODO', linhas: 'TODO' },
      status: 'pendente-mapeamento',
      acao: (p.acao || '').replace(/\*\*/g, '').trim(),
      _acionavel: ehAcionavel(p.acao),
    };
  });

  const mapa = {
    version: '1',
    _doc: 'ANCHOR-MAP POR REGIÃO de tela (eixo tela — NÃO o Code Connect do projeto, que é component-registry.json no eixo componente; ver RUNBOOK Fase 1 §Deconflito dos 3 eixos): por PARTE, o bloco do protótipo ↔ arquivo/range da tela viva. Gerado por prototipo-ui/gerar-map.mjs a partir do gap_fonte — TODO em arquivo/linhas = âncora ainda não preenchida (grep -n real, nunca fabricar). prototipo_sha invalida o map quando o protótipo re-exportar (scripts/governance/design-code-map-check.mjs detecta o drift).',
    tela,
    gap_fonte: relPosix(root, gapPath),
    prototipo_sha,
    gerado_em: hoje || fmVal(fm, 'gerado_em') || null,
    partes: mapPartes,
  };
  const pendentes = mapPartes.filter((p) => p.prototipo.arquivo === 'TODO' || p.vivo.arquivo === 'TODO' || p.prototipo.linhas === 'TODO' || p.vivo.linhas === 'TODO').length;
  return { mapa, totalPartes: partes.length, pendentes, arquivosPrototipo, prototipo_sha };
}

function relPosix(root, p) {
  const r = resolve(p).startsWith(resolve(root)) ? resolve(p).slice(resolve(root).length + 1) : p;
  return r.replaceAll('\\', '/');
}

function selftest() {
  let fails = 0; const t = (l, c) => { if (!c) fails++; console.log(`  [${c ? 'PASS' : 'FAIL'}] ${l}`); };
  const fx = join(HERE, 'fixtures', 'gerar-map');

  t('resolverArquivosPrototipo: path completo + basename compartilha dir', (() => {
    const arqs = resolverArquivosPrototipo('prototipo-ui/cowork/financeiro-page.jsx + financeiro-ops.jsx (NOVO) @ 4e3aacfc0f');
    return arqs.length === 2 && arqs[0] === 'prototipo-ui/cowork/financeiro-page.jsx' && arqs[1] === 'prototipo-ui/cowork/financeiro-ops.jsx';
  })());
  t('resolverArquivoVivo: extrai path Pages real', resolverArquivoVivo('resources/js/Pages/Financeiro/Unificado/Index.tsx (2784 ln) + _components/') === 'resources/js/Pages/Financeiro/Unificado/Index.tsx');
  t('computeGitSha: arquivo inexistente / sem repo git → sem-historico, não lança', computeGitSha(['prototipo-ui/fixtures/gerar-map/__nao-existe.jsx'], '/tmp') === 'sem-historico');

  if (existsSync(join(fx, 'boa-gap.md'))) {
    const g = gerar(join(fx, 'boa-gap.md'), { root: HERE.replace(/\/prototipo-ui$/, ''), hoje: '2026-01-01' });
    t('gera 1 parte por linha da tabela (inclui as "no-op"/4º veredito, não só acionáveis)', !g.erro && g.mapa.partes.length === 3);
    t('ids = slug das partes', g.mapa.partes[0].id === 'parte-a' && g.mapa.partes[1].id === 'parte-b' && g.mapa.partes[2].id === 'parte-c');
    t('arquivo/linhas nascem TODO (nunca fabrica âncora)', g.mapa.partes.every((p) => p.prototipo.linhas === 'TODO' && p.vivo.linhas === 'TODO'));
    t('_acionavel reflete ehAcionavel (Nada=false, resto=true)', g.mapa.partes[2]._acionavel === false && g.mapa.partes[0]._acionavel === true);
    t('gap_fonte relativo ao root, POSIX', g.mapa.gap_fonte === 'prototipo-ui/fixtures/gerar-map/boa-gap.md');
    t('prototipo_sha presente (sem-historico OK em fixture sem git tracking dedicado)', typeof g.mapa.prototipo_sha === 'string' && g.mapa.prototipo_sha.length > 0);
    const semTab = gerar(join(fx, 'sem-tabela-gap.md'));
    t('gap sem tabela → erro (não crasha)', !!semTab.erro);
  } else { t('fixtures presentes', false); }

  console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — esqueleto do map.json deriva do gap.md; verificação = design-code-map-check.mjs.');
  process.exit(fails ? 1 : 0);
}

const argv = process.argv.slice(2);
const invokedDirectly = process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url);
if (invokedDirectly) {
  if (argv.includes('--selftest')) selftest();
  else {
    const gapArg = argv.find((a) => !a.startsWith('--'));
    if (!gapArg) { console.error('uso: node prototipo-ui/gerar-map.mjs <gap.md|Mod/Tela> | --selftest'); process.exit(2); }
    const gapPath = resolveGap(gapArg);
    if (!gapPath) { console.error(`gap.md não encontrado pra: ${gapArg}`); process.exit(1); }
    const g = gerar(gapPath);
    if (g.erro) { console.error(`✗ ${g.erro}`); process.exit(1); }
    const sugestao = gapPath.replace(/-gap\.md$/, '.map.json');
    console.error(`# ${g.totalPartes} partes (${g.pendentes} com âncora TODO) · prototipo_sha=${g.prototipo_sha} · sugestão de destino: ${relPosix(REPO, sugestao)}`);
    console.log(JSON.stringify(g.mapa, null, 2));
    process.exit(0);
  }
}
