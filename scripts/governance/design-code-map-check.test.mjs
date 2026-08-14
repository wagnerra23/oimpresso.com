#!/usr/bin/env node
// @ts-check
// SELF-TEST — prova que design-code-map-check.mjs DETECTA drift (--strict exit 1) e LIBERA
// quando o map.json bate com o disco (exit 0). Monta repo-fixture temporário COM git real
// (staleness precisa de `git log` de verdade — sem isso não dá pra provar a invalidação por
// sha, que é a razão de existir do prototipo_sha). Rodar: node scripts/governance/
// design-code-map-check.test.mjs — exit 0 = passa.
import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(__dirname, 'design-code-map-check.mjs');

let fails = 0;
const check = (name, cond) => { console.log(`${cond ? '[OK]' : '[FAIL]'} ${name}`); if (!cond) fails++; };

function git(root, args) { return spawnSync('git', args, { cwd: root, encoding: 'utf8' }); }
function sha(root) { return git(root, ['log', '-1', '--format=%h']).stdout.trim(); }

// ── fixture: repo git real com 1 tela mapeada ──────────────────────────────
const root = mkdtempSync(join(tmpdir(), 'dcmc-'));
git(root, ['init', '-q']);
git(root, ['config', 'user.email', 'test@test.local']);
git(root, ['config', 'user.name', 'test']);

const protoDir = join(root, 'prototipo-ui', 'cowork');
const vivoDir = join(root, 'resources', 'js', 'Pages', 'Fixture');
const reqDir = join(root, 'memory', 'requisitos', 'Fixture');
mkdirSync(protoDir, { recursive: true });
mkdirSync(vivoDir, { recursive: true });
mkdirSync(reqDir, { recursive: true });

writeFileSync(join(protoDir, 'fixture-page.jsx'), 'export default function Page() { return null }\n');
writeFileSync(join(vivoDir, 'Index.tsx'), 'export default function Index() { return null }\n');
git(root, ['add', '-A']);
git(root, ['commit', '-q', '-m', 'v1 protótipo']);
const shaV1 = sha(root);

// GITHUB_STEP_SUMMARY zerado: rodando no CI, o script anexaria os números DA FIXTURE ao resumo
// do job real (mesma precaução de documentation-loop.mjs). O publicador tem teste próprio abaixo.
const runCheck = (extra = []) => spawnSync('node', [SCRIPT, '--root', root, ...extra], {
  encoding: 'utf8', env: { ...process.env, GITHUB_STEP_SUMMARY: '' },
});

function writeMap(overrides = {}) {
  const base = {
    version: '1', tela: 'Fixture/Index', prototipo_sha: shaV1, gerado_em: '2026-01-01',
    partes: [
      { id: 'header', prototipo: { arquivo: 'prototipo-ui/cowork/fixture-page.jsx', linhas: '1-10' }, vivo: { arquivo: 'resources/js/Pages/Fixture/Index.tsx', linhas: '1-5' }, status: 'paridade', acao: 'no-op' },
    ],
  };
  writeFileSync(join(reqDir, 'index.map.json'), JSON.stringify({ ...base, ...overrides }, null, 2));
}

// 1. LIBERA: map íntegro, sha bate com o commit atual → advisory e strict passam
writeMap();
const okStrict = runCheck(['--check', '--strict']);
check('map íntegro (sha em dia, âncoras existem) → strict exit 0', okStrict.status === 0);
check('reporta 1 map.json encontrado', /1 map\.json encontrado/.test(okStrict.stdout));
check('cobertura sai no relatório', /cobertura:/.test(okStrict.stdout));

// 2. DRIFT — âncora vivo.arquivo quebrada (path que não existe)
writeMap({ partes: [{ id: 'header', prototipo: { arquivo: 'prototipo-ui/cowork/fixture-page.jsx', linhas: '1-10' }, vivo: { arquivo: 'resources/js/Pages/Fixture/FANTASMA.tsx', linhas: '1-5' }, status: 'paridade', acao: 'no-op' }] });
const badAnchor = runCheck(['--check', '--strict']);
check('vivo.arquivo inexistente → strict exit 1', badAnchor.status === 1);
check('motivo aponta FANTASMA', /FANTASMA/.test(badAnchor.stdout));
const badAnchorAdvisory = runCheck(['--check']);
check('mesmo drift, modo advisory (sem --strict) → exit 0', badAnchorAdvisory.status === 0);

// 3. STALE — protótipo re-exportou (novo commit no arquivo-fonte), sha salvo ficou pra trás
writeMap(); // volta ao mapa íntegro (sha=shaV1)
writeFileSync(join(protoDir, 'fixture-page.jsx'), 'export default function Page() { return "v2" }\n');
git(root, ['add', '-A']);
git(root, ['commit', '-q', '-m', 'v2 protótipo re-exportado']);
const stale = runCheck(['--check', '--strict']);
check('protótipo mudou sem regenerar o map → STALE, strict exit 1', stale.status === 1 && /STALE/.test(stale.stdout));

// 4. Regenerar o sha resolve o STALE
const shaV2 = sha(root);
writeMap({ prototipo_sha: shaV2 });
const fixed = runCheck(['--check', '--strict']);
check('sha atualizado (regenerado) → strict exit 0 de novo', fixed.status === 0);

// 5. TODO (âncora ainda não preenchida) NUNCA é drift, mesmo em --strict
writeMap({ partes: [{ id: 'header', prototipo: { arquivo: 'TODO', linhas: 'TODO' }, vivo: { arquivo: 'TODO', linhas: 'TODO' }, status: 'pendente-mapeamento', acao: 'x' }] });
const todoOnly = runCheck(['--check', '--strict']);
check('map só com TODO (recém-gerado) → strict exit 0 (pendência ≠ drift)', todoOnly.status === 0);
check('reporta pendente(s), não [DRIFT]', /pendente/.test(todoOnly.stdout) && !/\[DRIFT\]/.test(todoOnly.stdout));

// 6. schema quebrado (sem 'partes') → sempre drift
writeFileSync(join(reqDir, 'index.map.json'), JSON.stringify({ version: '1', tela: 'x' }, null, 2));
const badSchema = runCheck(['--check', '--strict']);
check('schema sem partes[] → strict exit 1', badSchema.status === 1);

// ── ÂNCORA ESTÁVEL (PR-B): vivo.ancora + data-contract="<id>" no .tsx ─────────
const shaAtual = sha(root);
const parteBase = (extraVivo = {}) => ({
  id: 'header',
  prototipo: { arquivo: 'prototipo-ui/cowork/fixture-page.jsx', linhas: '1-10' },
  vivo: { arquivo: 'resources/js/Pages/Fixture/Index.tsx', linhas: '1-5', ...extraVivo },
  status: 'paridade', acao: 'no-op',
});

// 7. declarada + presente → estável, strict exit 0
writeFileSync(join(vivoDir, 'Index.tsx'), 'export default function Index() { return <div data-contract="header">x</div> }\n');
writeMap({ prototipo_sha: shaAtual, partes: [parteBase({ ancora: true })] });
const anchored = runCheck(['--check', '--strict']);
check('ancora:true + data-contract presente → strict exit 0', anchored.status === 0);
check('resumo conta 1 âncora estável', /âncora estável \(data-contract no vivo\): 1\/1/.test(anchored.stdout));

// 8. declarada + AUSENTE (refactor removeu) → DRIFT, strict exit 1
writeFileSync(join(vivoDir, 'Index.tsx'), 'export default function Index() { return <div>refatorado sem ancora</div> }\n');
const anchorGone = runCheck(['--check', '--strict']);
check('ancora:true + data-contract SUMIU → strict exit 1 (rot silencioso vira drift)', anchorGone.status === 1 && /DECLARADA/.test(anchorGone.stdout));

// 9. NÃO-declarada: nunca pune (linha-only), e se a âncora JÁ existe no .tsx → nudge WARN
writeMap({ prototipo_sha: shaAtual, partes: [parteBase()] });
const linhaOnly = runCheck(['--check', '--strict']);
check('sem vivo.ancora (linha-only) → strict exit 0 (backfill não vira punição)', linhaOnly.status === 0);
check('resumo reporta linha-only frágil', /1 linha-only/.test(linhaOnly.stdout));
writeFileSync(join(vivoDir, 'Index.tsx'), 'export default function Index() { return <div data-contract="header">x</div> }\n');
const nudge = runCheck(['--check', '--strict']);
check('âncora presente mas não-declarada → WARN nudge (declare e trave), exit 0', nudge.status === 0 && /não declara vivo\.ancora/.test(nudge.stdout));

// ── SHA POR CONTEÚDO (PR-C, ADR 0324): formato sha256: roteado pro contentHash ────
const { computeProtoHash } = await import('../../prototipo-ui/gerar-map.mjs');
// sha FRESCO por conteúdo — a partir daqui o conteúdo do protótipo muda dentro do próprio teste,
// então reusar o git-sha antigo faria os casos seguintes falharem por STALE (staleness é outro
// eixo; os casos abaixo testam ÂNCORA). Recalcular por chamada isola um eixo do outro.
const shaProtoFresco = () => computeProtoHash(['prototipo-ui/cowork/fixture-page.jsx'], root);

// 10. sha256: fresco → strict exit 0 (release no formato canônico)
writeFileSync(join(vivoDir, 'Index.tsx'), 'export default function Index() { return null }\n');
writeMap({ prototipo_sha: computeProtoHash(['prototipo-ui/cowork/fixture-page.jsx'], root), partes: [parteBase()] });
const contentFresh = runCheck(['--check', '--strict']);
check('prototipo_sha sha256: (contentHash) fresco → strict exit 0', contentFresh.status === 0);

// 11. conteúdo do protótipo mudou SEM COMMIT → STALE (o git-sha era CEGO a isso)
writeFileSync(join(protoDir, 'fixture-page.jsx'), 'export default function Page() { return "v3 re-export sobrescreveu o espelho, commit ainda não" }\n');
const contentStale = runCheck(['--check', '--strict']);
check('re-export sobrescreveu sem commit → sha256 STALE, strict exit 1 (contentHash morde onde git-sha não via)', contentStale.status === 1 && /STALE/.test(contentStale.stdout));
check('mensagem manda regenerar com --atualizar (preserva preenchido)', /--atualizar/.test(contentStale.stdout));

// 12. commit que toca o path SEM mudar conteúdo NÃO invalida sha256 (o falso-STALE do
// git-sha — caso real unificado.map.json 4e3aacfc0f×6cb6566311, blobs idênticos)
writeMap({ prototipo_sha: computeProtoHash(['prototipo-ui/cowork/fixture-page.jsx'], root), partes: [parteBase()] });
git(root, ['add', '-A']); git(root, ['commit', '-q', '-m', 'commit toca o repo, conteúdo do proto intacto']);
const contentImune = runCheck(['--check', '--strict']);
check('commit sem mudança de conteúdo do proto → sha256 segue fresco (imune ao falso-STALE do git-sha)', contentImune.status === 0);

// ── NUDGE GENÉRICO + DECOMPOSIÇÃO DO LINHA-ONLY (2026-08-14) ────────────────
// Antes, o nudge exigia data-contract="<p.id>" — coincidência entre o vocabulário do id da parte
// (slug da seção do -gap.md) e o do contrato-de-tela (nome de região). Medido no corpus real:
// 0 avisos em 30 partes, com 15 data-contract existindo no repo. Inerte por construção.

// 13. MORDE: id DIFERENTE do p.id → nudge dispara e LISTA o id disponível (o caso que era mudo)
writeFileSync(join(vivoDir, 'Index.tsx'), 'export default function Index() { return <div data-contract="reconnect-modal">x</div> }\n');
writeMap({ prototipo_sha: shaProtoFresco(), partes: [parteBase()] });
const nudgeIdDiferente = runCheck(['--check', '--strict']);
check('âncora de id DIFERENTE presente + ancora ausente → nudge dispara (era mudo antes)',
  nudgeIdDiferente.status === 0 && /não declara vivo\.ancora/.test(nudgeIdDiferente.stdout));
check('nudge NOMEIA o id disponível no arquivo', /reconnect-modal/.test(nudgeIdDiferente.stdout));
check('nudge sugere a forma de id custom quando o p.id não casa', /vivo\.ancora: "<um destes ids>"/.test(nudgeIdDiferente.stdout));
check('decomposição conta a parte como linha-only COM data-contract no arquivo',
  /1 com data-contract JÁ no vivo\.arquivo/.test(nudgeIdDiferente.stdout));

// 14. id custom declarado + presente → estável (prova que a forma sugerida pelo nudge funciona)
writeMap({ prototipo_sha: shaProtoFresco(), partes: [parteBase({ ancora: 'reconnect-modal' })] });
const idCustom = runCheck(['--check', '--strict']);
check('vivo.ancora: "<id custom>" + data-contract presente → estável 1/1, strict exit 0',
  idCustom.status === 0 && /âncora estável \(data-contract no vivo\): 1\/1/.test(idCustom.stdout));

// 15. CONTROLE-NEGATIVO: `ancora: false` EXPLÍCITO é opt-out consciente → NÃO nudga, mesmo com
// âncora disponível no arquivo (é o caso real das 13 partes de caixa-unificada.map.json, cujo
// _nota_mapeamento registra a decisão). Campo ausente ≠ campo false.
writeMap({ prototipo_sha: shaProtoFresco(), partes: [parteBase({ ancora: false })] });
const optOut = runCheck(['--check', '--strict']);
check('ancora:false explícito + âncora disponível → SEM nudge (opt-out consciente respeitado)',
  optOut.status === 0 && !/não declara vivo\.ancora/.test(optOut.stdout));
check('opt-out consciente aparece contado no resumo', /1 com 'vivo\.ancora: false' explícito/.test(optOut.stdout));

// 16. CONTROLE-NEGATIVO: arquivo SEM nenhum data-contract → nunca nudga, e o resumo diz POR QUE
// o 0/N é 0 (não há o que declarar), em vez de sugerir um backfill inexistente.
writeFileSync(join(vivoDir, 'Index.tsx'), 'export default function Index() { return <div>sem ancora nenhuma</div> }\n');
writeMap({ prototipo_sha: shaProtoFresco(), partes: [parteBase()] });
const semAncora = runCheck(['--check', '--strict']);
check('arquivo sem data-contract → SEM nudge, strict exit 0',
  semAncora.status === 0 && !/não declara vivo\.ancora/.test(semAncora.stdout));
check('resumo explica o 0/N: sem data-contract no arquivo, ancorar o .tsx primeiro',
  /1 sem NENHUM data-contract no vivo\.arquivo/.test(semAncora.stdout));

// ── PUBLICAÇÃO no job summary (a cobertura saindo do log pra superfície que se lê) ──
const { publicarResumo } = await import('./design-code-map-check.mjs');
const sumFile = join(root, 'step-summary.md');
const dadosFake = {
  maps: 3, cov: { cobertas: 3, total: 15 }, pctCobertura: 20, charters: 209,
  totalEstaveis: 0, ancoraveis: 30, totalLinhaOnly: 30, totalComAncora: 0, totalSemAncora: 30,
  totalOptOut: 30, totalDrift: 0, totalPendentes: 1,
};
check('publicarResumo sem $GITHUB_STEP_SUMMARY → no-op (rodada local não escreve nada)',
  publicarResumo(dadosFake, '') === false && publicarResumo(dadosFake, undefined) === false);
check('publicarResumo com destino → grava', publicarResumo(dadosFake, sumFile) === true);
const resumo = readFileSync(sumFile, 'utf8');
check('resumo publica a COBERTURA 3/15 (denominador -gap.md, não charter)', /\*\*3\/15\*\* \(20%\)/.test(resumo));
check('resumo declara que o denominador canônico é o -gap.md', /Denominador canônico da cobertura é o `-gap\.md`/.test(resumo));
check('resumo traz o comando que o reproduz (número nunca sem comando ao lado)',
  /node scripts\/governance\/design-code-map-check\.mjs --check/.test(resumo));
check('resumo decompõe o linha-only em acionável × precisa-ancorar', /fila acionável/.test(resumo) && /ancorar o `\.tsx` primeiro/.test(resumo));

rmSync(root, { recursive: true, force: true });
console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — design-code-map-check morde (âncora quebrada, sha stale por CONTEÚDO ou legado git, schema, data-contract declarado que sumiu) e libera certo (íntegro, TODO pendente, linha-only, commit sem mudança de conteúdo).');
process.exit(fails ? 1 : 0);
