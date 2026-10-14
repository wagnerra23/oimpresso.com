// placar-recibo.mjs — o AVALIADOR DE RECIBO do placar da lista (provas `execucao` · `revisao` ·
// `comparacao`). Consumidor: scripts/qa/placar-indice.mjs::avaliarProva.
//
// ── DE ONDE VEIO ──────────────────────────────────────────────────────────────────────────
// Saiu do repo com a ADR 0397 (#7224, 2026-09-11) junto com `prototipo-ui/design-docs/**`. A
// lógica foi lida do git em `4f51a9ec781^` (`_scripts/placar-evidencia.mjs` +
// `placar-formatos.mjs` + `placar-comparacao.mjs`) e reescrita no endereço que a D3 designa.
// Duas mudanças deliberadas, e as duas medidas:
//   sem Ajv    a lane `placar-de-lista.yml` não roda `npm ci` (o `ajv` é devDependency): importar
//              daria ERR_MODULE_NOT_FOUND na lane (§5 2026-09-22). O schema também saiu com a 0397;
//              aqui fica só a forma que cada tipo precisa pra não mentir.
//   comparador o antigo chamava `prototipo-ui/design-diff.mjs`, que a 0397 D3 moveu pra
//              `scripts/design/design-diff.mjs`. Exit 2 do comparador (identidade da view não
//              provada) agora sai NÃO MEDIDA — antes caía em "falhou" (LC-33).
//   playwright o formato `playwright-json` não foi portado: nenhuma prova viva o usa (medido
//              2026-10-14). Prova que o peça sai NÃO MEDIDA nomeada, nunca "ok".
//
// ── O QUE ELE CERTIFICA, E O QUE NÃO ─────────────────────────────────────────────────────
// O recibo é um JSON no `path` da prova que vincula, por SHA-256, a `_saida-NN.md`, os testes,
// as fontes e os alvos. Certifica execução DECLARADA e FRESCOR (hash bate com o arquivo de hoje).
// Não certifica a honestidade de quem o emitiu. O placar nunca executa comando do recibo.
import { createHash } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { join } from 'node:path';

export const TIPOS_RECIBO = ['execucao', 'revisao', 'comparacao'];
export const hash = (texto) => createHash('sha256').update(texto).digest('hex');
const seguro = (p) => typeof p === 'string' && p.length > 0
  && !/^[\\/]|^[A-Za-z]:|\\/.test(p) && !p.split('/').includes('..');

export function validarRevisao(r, criterios) {
  return typeof r.revisor === 'string' && r.revisor.trim().length > 0
    && r.resultado === 'aprovado' && Array.isArray(r.criterios)
    && (criterios || []).every((id) => {
      const itens = r.criterios.filter((c) => c.id === id);
      return itens.length === 1 && itens[0].resultado === 'aprovado'
        && typeof itens[0].justificativa === 'string' && itens[0].justificativa.trim().length > 0;
    });
}

/** Comparador canônico. Devolve 'ok' | 'falhou' | 'nao-medi'. Nunca executa `command` do recibo. */
export function compararNoRepo(root, recibo, prova) {
  const run = spawnSync(process.execPath, [join(root, 'scripts/design/design-diff.mjs'),
    '--compare', join(root, recibo.producao), join(root, recibo.prototipo),
    '--contrato', join(root, prova.contrato), '--check', '--check-shell', '--json'],
  { cwd: root, encoding: 'utf8', timeout: 30000, maxBuffer: 4 * 1024 * 1024 });
  if (run.status === 2) return 'nao-medi';
  if (run.status !== 0 || typeof run.stdout !== 'string') return 'falhou';
  try {
    const r = JSON.parse(run.stdout.slice(run.stdout.indexOf('{')));
    const igual = r.sameTheme === true && r.bugs === 0 && r.shell === 0 && Array.isArray(r.rows)
      && (prova.dimensoes || []).every((dim) => {
        const rows = r.rows.filter((x) => x.dim === dim);
        return (dim === 'SHELL' || rows.length > 0) && rows.every((x) => x.veredito === 'IGUAL');
      });
    return igual ? 'ok' : 'falhou';
  } catch { return 'falhou'; }
}

/**
 * Avalia uma prova de recibo cujo `path` (e demais paths) JÁ foi resolvido.
 * ctx: { thread, saidaPath, existe(p), ler(p), comparar(recibo, prova) → 'ok'|'falhou'|'nao-medi',
 *        alvos: paths das provas estruturais da thread (precisam estar no hash) }.
 */
export function avaliarRecibo(prova, ctx) {
  const falha = (motivo) => ({ ok: false, path: prova.path, motivo });
  const ok = { ok: true, path: prova.path, motivo: '' };
  if (prova.formato === 'playwright-json') {
    return { ok: false, naoMedida: true, path: prova.path, motivo: 'formato playwright-json não portado (ver docblock de placar-recibo.mjs)' };
  }
  if (!ctx.existe(prova.path)) return falha('recibo ausente');
  let r;
  try { r = JSON.parse(ctx.ler(prova.path)); } catch { return falha('recibo ilegível (JSON inválido)'); }
  if (!r || typeof r !== 'object' || Array.isArray(r)) return falha('recibo precisa ser objeto');
  if (r.thread !== ctx.thread.id) return falha('recibo pertence a outra thread');
  const arquivos = r.arquivos;
  if (!arquivos || typeof arquivos !== 'object' || Array.isArray(arquivos)) return falha('hashes ausentes');
  const tipo = prova.tipo;
  if (tipo === 'execucao') {
    if (r.exitCode !== 0 || !['ct100', 'ci'].includes(r.runner) || !Array.isArray(r.command) || !r.command.length
      || r.command.some((x) => typeof x !== 'string' || !x.trim())) return falha('recibo sem execução válida desta thread');
    if (!Array.isArray(r.testes) || !r.testes.length || (prova.testes || []).some((p) => !r.testes.includes(p))) {
      return falha('recibo não executou os testes exigidos pelo plano');
    }
  }
  const obrigatorios = [ctx.saidaPath, ...ctx.alvos,
    ...(tipo === 'execucao' ? [r.summary, ...r.testes] : (prova.fontes || [])),
    ...(tipo === 'comparacao' ? [r.producao, r.prototipo, prova.contrato] : [])];
  const fora = obrigatorios.find((p) => !seguro(p) || !Object.hasOwn(arquivos, p));
  if (fora !== undefined) return falha(`recibo não vincula ${fora || '(path vazio)'}`);
  for (const [p, h] of Object.entries(arquivos)) {
    if (!seguro(p) || !/^[a-f0-9]{64}$/.test(h) || !ctx.existe(p) || hash(ctx.ler(p)) !== h) return falha(`evidência ausente ou stale: ${p}`);
  }
  if (tipo === 'revisao') {
    // Revisão documental não fecha thread que mexe em código.
    if ((ctx.thread.prefixo || []).some((p) => !/\.md$|\.contract\.json$/.test(p))) return falha('revisão documental não encerra alteração de código');
    return validarRevisao(r, prova.criterios) ? ok : falha('parecer incompleto ou não aprovado');
  }
  if (tipo === 'comparacao') {
    const v = ctx.comparar ? ctx.comparar(r, prova) : 'nao-medi';
    if (v === 'nao-medi') return { ok: false, naoMedida: true, path: prova.path, motivo: 'comparador não provou a identidade da view (exit 2)' };
    return v === 'ok' ? ok : falha('comparação divergente, incompleta ou sem proveniência');
  }
  let s;
  try { s = JSON.parse(ctx.ler(r.summary)); } catch { return falha('resumo JUnit ilegível'); }
  if (!s || s.schema !== 'junit-summary/v1' || s.invalid || s.coherent !== true || s.provou_algo !== true
    || !Array.isArray(s.files)) return falha('resumo JUnit inválido');
  for (const p of r.testes) {
    const f = s.files.find((x) => x.file === p);
    if (!f || !(f.passed > 0) || !(f.assertions > 0) || f.failed !== 0 || f.errors !== 0 || f.skipped !== 0
      || f.tests !== f.passed) return falha(`teste não comprovado integralmente: ${p}`);
  }
  return ok;
}
