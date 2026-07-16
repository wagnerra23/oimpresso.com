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
// `prototipo_sha` — a Fase 4 (consumir-map.mjs) usa isso pra ABORTAR e regenerar quando o
// protótipo re-exportar (sha mudou).
//
// IDENTIDADE DO PROTÓTIPO (2026-07-09, PR-C — régua do ADR 0324): `prototipo_sha` é
// `sha256:<12hex>` = contentHash(normalize(conteúdo)) dos arquivos-fonte, REUSANDO
// contentHash/normalize de cowork-mirror-freshness.mjs (fonte única; "hashear só conteúdo
// persistido em arquivo, nunca 'de memória'"). NÃO é git-sha: o git-sha acusava STALE FALSO
// quando um commit tocava o path sem mudar conteúdo (caso real: unificado.map.json salvo
// @4e3aacfc0f vs @6cb6566311 — blobs IDÊNTICOS, checker gritando STALE) e ficava CEGO a
// re-export sobrescrevendo o espelho antes do commit. Maps legados com git-sha seguem
// verificados no formato antigo via computeGitSha (back-compat, sem punição retroativa).
//
// NÃO fabrica linha — `grep -n` real é do humano/agente da Fase 1 (regra dura, mesma do
// RUNBOOK). O gerador só monta o ESQUELETO: id + status inicial + acao (contexto) + arquivo
// resolvido (quando determinístico) com `linhas: "TODO"` — nunca um número inventado.
//
// Uso:
//   node prototipo-ui/gerar-map.mjs <gap.md|Mod/Tela>              # emite o esqueleto JSON (stdout)
//   node prototipo-ui/gerar-map.mjs <gap.md|Mod/Tela> --atualizar  # re-gera PRESERVANDO o
//       preenchimento humano do <tela>.map.json existente (linhas/vivo/status/acao por id) e
//       atualizando prototipo_sha — é o caminho do "aborta se sha mudou → REGENERA" da Fase 4
//       sem perder o trabalho de âncora já feito. Partes que saíram do gap.md são removidas
//       (com aviso em stderr); partes novas nascem TODO.
//   node prototipo-ui/gerar-map.mjs --selftest                     # fixture hermético
//
// Verificação (anchors existem? sha ficou stale? % telas mapeadas?) NÃO é deste script — é do
// scripts/governance/design-code-map-check.mjs (mesma separação gerar-contrato × contrato-de-tela).
//
// Exit: 0 = ok | 1 = gap.md não-parseável | 2 = uso

import { readFileSync, existsSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { join, resolve, dirname, basename } from 'node:path';
import { fileURLToPath } from 'node:url';
import { slug, ehAcionavel, parsePartes, resolveGap, frontmatterBlock, fmVal } from './gerar-contrato.mjs';
import { contentHash } from '../scripts/governance/cowork-mirror-freshness.mjs';

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

// Identidade CANÔNICA do protótipo (ADR 0324): sha256 combinado dos contentHash(normalize())
// por arquivo, keyed por path relativo ordenado — muda se QUALQUER fonte mudar de conteúdo
// (mesmo sem commit), NÃO muda se um commit tocar o path sem mudar conteúdo. Formato
// `sha256:<12hex>` distingue do git-sha legado. Nenhum arquivo legível → 'sem-arquivo'.
export function computeProtoHash(relPaths, root = REPO) {
  const linhas = [];
  for (const rel of [...relPaths].sort()) {
    const abs = join(root, rel);
    if (!existsSync(abs)) continue;
    try { linhas.push(`${rel}:${contentHash(readFileSync(abs))}`); } catch { /* ilegível — fora do combinado */ }
  }
  if (!linhas.length) return 'sem-arquivo';
  return 'sha256:' + createHash('sha256').update(linhas.join('\n'), 'utf8').digest('hex').slice(0, 12);
}

const SHA_INDETERMINADO = new Set(['sem-historico', 'sem-arquivo']);
export function shaIndeterminado(sha) { return !sha || SHA_INDETERMINADO.has(sha); }

// Recomputa o sha ATUAL no MESMO formato do salvo (fonte única pra checker + consumir-map):
// `sha256:` → contentHash combinado (canônico) · resto → git-sha legado (back-compat).
export function shaAtualPara(salvo, relPaths, root = REPO) {
  return String(salvo || '').startsWith('sha256:') ? computeProtoHash(relPaths, root) : computeGitSha(relPaths, root);
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
  const prototipo_sha = arquivosPrototipo.length ? computeProtoHash(arquivosPrototipo, root) : 'sem-arquivo';

  const seen = new Map(); // desambigua ids repetidos (partes com mesmo nome em tabelas maiores)
  const mapPartes = partes.map((p) => {
    let id = slug(p.parte);
    const n = (seen.get(id) || 0) + 1; seen.set(id, n);
    if (n > 1) id = `${id}-${n}`;
    return {
      id,
      prototipo: { arquivo: arquivosPrototipo[0] || 'TODO', linhas: 'TODO' },
      // ancora: false = linha-only (frágil). Depois de adicionar data-contract="<id>" no .tsx
      // (mesmo id — convergência com gerar-contrato por construção), vire true: o checker passa
      // a EXIGIR a âncora (sumiu = DRIFT) e o range de linha vira só informativo.
      vivo: { arquivo: arquivoVivo || 'TODO', linhas: 'TODO', ancora: false },
      status: 'pendente-mapeamento',
      acao: (p.acao || '').replace(/\*\*/g, '').trim(),
      _acionavel: ehAcionavel(p.acao),
    };
  });

  const mapa = {
    version: '1',
    _doc: 'ANCHOR-MAP POR REGIÃO de tela (eixo tela — NÃO o Code Connect do projeto, que é component-registry.json no eixo componente; ver RUNBOOK Fase 1 §Deconflito dos 3 eixos): por PARTE, o bloco do protótipo ↔ arquivo/range da tela viva. Gerado por prototipo-ui/gerar-map.mjs a partir do gap_fonte — TODO em arquivo/linhas = âncora ainda não preenchida (grep -n real, nunca fabricar). prototipo_sha = sha256:contentHash(normalize) dos arquivos-fonte (ADR 0324 — identidade por CONTEÚDO, não git-sha); invalida o map quando o protótipo re-exportar: a Fase 4 consome via prototipo-ui/consumir-map.mjs (aborta se stale → regenerar com gerar-map.mjs --atualizar, que preserva o preenchido). Lado VIVO: range de linha é INFORMATIVO (frágil); a âncora verificável é vivo.ancora: true + data-contract="<id>" no .tsx (declarada e ausente = DRIFT). scripts/governance/design-code-map-check.mjs verifica tudo.',
    tela,
    gap_fonte: relPosix(root, gapPath),
    prototipo_sha,
    gerado_em: hoje || fmVal(fm, 'gerado_em') || null,
    partes: mapPartes,
  };
  const pendentes = mapPartes.filter((p) => p.prototipo.arquivo === 'TODO' || p.vivo.arquivo === 'TODO' || p.prototipo.linhas === 'TODO' || p.vivo.linhas === 'TODO').length;
  return { mapa, totalPartes: partes.length, pendentes, arquivosPrototipo, prototipo_sha };
}

// --atualizar: funde o esqueleto RE-gerado com o map existente PRESERVANDO o preenchimento
// humano (âncoras grep -n reais + status + acao enriquecida) das partes de MESMO id. O sha e
// a lista de partes vêm do esqueleto novo (gap.md manda); o conteúdo preenchido nunca é
// regredido a TODO. Partes que saíram do gap.md são removidas (reportadas em `removidas`).
export function fundirComExistente(esqueleto, existente) {
  const antigas = new Map((existente?.partes || []).map((p) => [p.id, p]));
  let preservadas = 0;
  const partes = esqueleto.partes.map((nova) => {
    const antiga = antigas.get(nova.id);
    if (!antiga) return nova;
    antigas.delete(nova.id);
    preservadas++;
    return {
      ...nova,
      prototipo: { ...nova.prototipo, ...(antiga.prototipo?.linhas && antiga.prototipo.linhas !== 'TODO' ? { arquivo: antiga.prototipo.arquivo, linhas: antiga.prototipo.linhas } : {}) },
      vivo: { ...nova.vivo, ...(antiga.vivo?.arquivo && antiga.vivo.arquivo !== 'TODO' ? antiga.vivo : {}) },
      status: antiga.status && antiga.status !== 'pendente-mapeamento' ? antiga.status : nova.status,
      acao: antiga.acao || nova.acao,
    };
  });
  return { mapa: { ...esqueleto, partes }, preservadas, novas: partes.length - preservadas, removidas: [...antigas.keys()] };
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
    // separador-agnóstico: no Windows HERE vem com backslash (pegadinha pega 2026-07-09 — o
    // replace com "/" fixo no-opava e o check de gap_fonte falhava só fora do CI linux)
    const g = gerar(join(fx, 'boa-gap.md'), { root: resolve(HERE, '..'), hoje: '2026-01-01' });
    t('gera 1 parte por linha da tabela (inclui as "no-op"/4º veredito, não só acionáveis)', !g.erro && g.mapa.partes.length === 3);
    t('ids = slug das partes', g.mapa.partes[0].id === 'parte-a' && g.mapa.partes[1].id === 'parte-b' && g.mapa.partes[2].id === 'parte-c');
    t('arquivo/linhas nascem TODO (nunca fabrica âncora)', g.mapa.partes.every((p) => p.prototipo.linhas === 'TODO' && p.vivo.linhas === 'TODO'));
    t('vivo.ancora nasce false explícito (linha-only até ancorar com data-contract)', g.mapa.partes.every((p) => p.vivo.ancora === false));
    t('_acionavel reflete ehAcionavel (Nada=false, resto=true)', g.mapa.partes[2]._acionavel === false && g.mapa.partes[0]._acionavel === true);
    t('gap_fonte relativo ao root, POSIX', g.mapa.gap_fonte === 'prototipo-ui/fixtures/gerar-map/boa-gap.md');
    t('prototipo_sha presente (sha256:/sem-arquivo em fixture)', typeof g.mapa.prototipo_sha === 'string' && g.mapa.prototipo_sha.length > 0);
    const semTab = gerar(join(fx, 'sem-tabela-gap.md'));
    t('gap sem tabela → erro (não crasha)', !!semTab.erro);

    // identidade por CONTEÚDO (ADR 0324) — sem git, hermético:
    const h1 = computeProtoHash(['fixtures/gerar-map/boa-gap.md'], HERE);
    t('computeProtoHash: formato sha256:<12hex> e determinístico', /^sha256:[0-9a-f]{12}$/.test(h1) && h1 === computeProtoHash(['fixtures/gerar-map/boa-gap.md'], HERE));
    t('computeProtoHash: conteúdo diferente → hash diferente (morde re-export sem commit)', h1 !== computeProtoHash(['fixtures/gerar-map/sem-tabela-gap.md'], HERE));
    t('computeProtoHash: nenhum arquivo legível → sem-arquivo (nunca lança)', computeProtoHash(['__nao-existe.jsx'], HERE) === 'sem-arquivo');
    t('shaAtualPara: roteia sha256: → contentHash e legado → git-sha', shaAtualPara('sha256:abc', ['fixtures/gerar-map/boa-gap.md'], HERE) === h1 && /^sha256:/.test(shaAtualPara('sha256:abc', ['fixtures/gerar-map/boa-gap.md'], HERE)) === true && shaAtualPara('4e3aacfc0f', ['__nao-existe.jsx'], '/tmp') === 'sem-historico');
    t('shaIndeterminado: sentinelas e vazio', shaIndeterminado('sem-historico') && shaIndeterminado('sem-arquivo') && shaIndeterminado('') && !shaIndeterminado('sha256:abc'));

    // --atualizar (fundirComExistente) — preserva preenchido, remove o que saiu, nasce TODO o novo:
    const preenchido = {
      ...g.mapa,
      prototipo_sha: 'sha256:velho000000',
      partes: [
        { id: 'parte-a', prototipo: { arquivo: 'p.jsx', linhas: '10-20' }, vivo: { arquivo: 'resources/js/Pages/X/Index.tsx', linhas: '5-9', ancora: true }, status: 'paridade', acao: 'no-op (enriquecida à mão)' },
        { id: 'parte-fantasma', prototipo: { arquivo: 'p.jsx', linhas: '1-2' }, vivo: { arquivo: 'n/a', linhas: '' }, status: 'artefato', acao: 'rejeitar' },
      ],
    };
    const f = fundirComExistente(g.mapa, preenchido);
    const pa = f.mapa.partes.find((p) => p.id === 'parte-a');
    t('fundir: parte preenchida preserva linhas/vivo/status/acao (nunca regride a TODO)', pa.prototipo.linhas === '10-20' && pa.vivo.ancora === true && pa.status === 'paridade' && pa.acao.includes('enriquecida'));
    t('fundir: sha vem do esqueleto novo (regenerado), não do map velho', f.mapa.prototipo_sha === g.mapa.prototipo_sha && f.mapa.prototipo_sha !== 'sha256:velho000000');
    t('fundir: parte que saiu do gap.md é removida e reportada', f.removidas.includes('parte-fantasma') && !f.mapa.partes.some((p) => p.id === 'parte-fantasma'));
    t('fundir: partes novas do gap nascem TODO (não preenchidas)', f.mapa.partes.filter((p) => p.id !== 'parte-a').every((p) => p.vivo.linhas === 'TODO'));
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
    if (!gapArg) { console.error('uso: node prototipo-ui/gerar-map.mjs <gap.md|Mod/Tela> [--atualizar] | --selftest'); process.exit(2); }
    const gapPath = resolveGap(gapArg);
    if (!gapPath) { console.error(`gap.md não encontrado pra: ${gapArg}`); process.exit(1); }
    let g = gerar(gapPath, { hoje: new Date().toISOString().slice(0, 10) });
    if (g.erro) { console.error(`✗ ${g.erro}`); process.exit(1); }
    const destino = gapPath.replace(/-gap\.md$/, '.map.json');

    if (argv.includes('--atualizar')) {
      if (!existsSync(destino)) { console.error(`✗ --atualizar: não existe map pra fundir em ${relPosix(REPO, destino)} — rode sem a flag pro esqueleto inicial.`); process.exit(1); }
      let existente;
      try { existente = JSON.parse(readFileSync(destino, 'utf8')); }
      catch (e) { console.error(`✗ --atualizar: ${relPosix(REPO, destino)} não é JSON válido (${e.message})`); process.exit(1); }
      const f = fundirComExistente(g.mapa, existente);
      g = { ...g, mapa: f.mapa };
      console.error(`# --atualizar: ${f.preservadas} parte(s) preservada(s) · ${f.novas} nova(s) TODO${f.removidas.length ? ` · REMOVIDAS (saíram do gap.md): ${f.removidas.join(', ')}` : ''} · sha ${existente.prototipo_sha} → ${g.mapa.prototipo_sha}`);
    }

    // Âncora SEMPRE computada (ancora.mjs / hook block-ancora-no-olho): cross-check do
    // frontmatter `prototipo:` do gap.md contra a âncora que o CHARTER declara. Divergiu =
    // WARN (o gap pode citar arquivos extra tipo -ops.jsx, mas o 1º arquivo deve ser a âncora).
    try {
      const { resolveAncora } = await import('./ancora.mjs');
      // campo `tela:` do gap costuma vir "Mod/Tela (/rota)" — a query da âncora é só o Mod/Tela
      const r = await resolveAncora(String(g.mapa.tela).split('(')[0].trim());
      if (r.ok) {
        const declarada = r.ancoras.map((a) => a.valor).join(' ');
        const principal = g.arquivosPrototipo[0];
        if (principal && declarada && !declarada.includes(basename(principal))) {
          console.error(`⚠️ âncora computada do charter (${r.charter}) não cita ${principal} — confira o frontmatter 'prototipo:' do gap.md (âncora nunca no olho).`);
        }
      } else {
        console.error(`⚠️ ancora.mjs: ${r.motivo} (map segue pelo frontmatter do gap.md)`);
      }
    } catch (e) { console.error(`⚠️ cross-check ancora.mjs indisponível: ${e.message}`); }

    console.error(`# ${g.mapa.partes.length} partes · prototipo_sha=${g.mapa.prototipo_sha} · destino: ${relPosix(REPO, destino)}`);
    console.log(JSON.stringify(g.mapa, null, 2));
    process.exit(0);
  }
}
