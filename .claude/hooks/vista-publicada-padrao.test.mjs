#!/usr/bin/env node
// Selftest de vista-publicada-padrao.mjs.
//
// Prova as DUAS pernas — sem as duas, o hook é decorativo:
//   MORDE          — vista fora do padrão gera aviso
//   NÃO MORDE      — vista conforme, tool alheia e fail-open ficam SILENCIOSAS
//
// Advisory sempre sai com exit 0; portanto a mordida se mede pelo STDERR, nunca
// pelo código de saída. Um teste que olhasse só o exit passaria com o hook mudo.
//
// Uso: node .claude/hooks/vista-publicada-padrao.test.mjs

import { spawnSync } from 'node:child_process';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { conferirPadrao, parseBlocoVista, temaJaTemViva } from './vista-publicada-padrao.mjs';

const HOOK = resolve(dirname(fileURLToPath(import.meta.url)), 'vista-publicada-padrao.mjs');
const TMP = mkdtempSync(join(tmpdir(), 'vista-'));
let ok = 0, fail = 0;

const t = (nome, cond) => {
  if (cond) { ok++; console.log(`  ✅ ${nome}`); }
  else { fail++; console.log(`  ❌ ${nome}`); }
};

/** roda o hook com um payload e devolve {code, err} */
const rodar = (payload) => {
  const r = spawnSync('node', [HOOK], { input: JSON.stringify(payload), encoding: 'utf8' });
  return { code: r.status, err: r.stderr || '' };
};

/** cria um arquivo de vista no tmp e devolve o path */
const vista = (nome, corpo) => {
  const p = join(TMP, nome);
  writeFileSync(p, corpo, 'utf8');
  return p;
};

const BLOCO_BOM = `<!-- vista:
tema: teste
estado: viva
medido_em: 2026-07-29
dono: README.md
-->
<h1>ok</h1>`;

const REGISTRO_COM_VIVA = `## Tema: sistema
| Data | Vista | Estado |
| 2026-07-29 | [x](u) | **viva** |

## Tema: vazio
| 2026-01-01 | [y](u) | histórica |
`;

console.log('\n── núcleo puro ──');
t('parseBlocoVista: sem bloco devolve null', parseBlocoVista('<h1>nada</h1>') === null);
t('parseBlocoVista: lê os pares', parseBlocoVista(BLOCO_BOM)?.tema === 'teste');
t('parseBlocoVista: campo vazio não vira chave', !('sucede' in (parseBlocoVista('<!-- vista:\ntema: x\nsucede:\n-->') || {})));
t('temaJaTemViva: acha viva no tema certo', temaJaTemViva(REGISTRO_COM_VIVA, 'sistema') === true);
t('temaJaTemViva: tema sem viva devolve false', temaJaTemViva(REGISTRO_COM_VIVA, 'vazio') === false);
t('temaJaTemViva: tema inexistente devolve false', temaJaTemViva(REGISTRO_COM_VIVA, 'nao-existe') === false);

console.log('\n── MORDE (fora do padrão) ──');
const sempre = () => true;
t('sem bloco', conferirPadrao({ bloco: null, donoExiste: sempre, registro: '' }).length === 1);
t('campo dono ausente', conferirPadrao({
  bloco: { tema: 'a', estado: 'viva', medido_em: '2026-07-29' }, donoExiste: sempre, registro: '',
}).some((q) => q.includes('dono')));
t('dono declarado NÃO existe', conferirPadrao({
  bloco: { tema: 'a', estado: 'viva', medido_em: '2026-07-29', dono: 'memory/fantasma.md' },
  donoExiste: () => false, registro: '',
}).some((q) => q.includes('não existe no repositório')));
t('medido_em fora do ISO', conferirPadrao({
  bloco: { tema: 'a', estado: 'viva', medido_em: '29/07/2026', dono: 'README.md' }, donoExiste: sempre, registro: '',
}).some((q) => q.includes('ISO')));
t('estado inválido', conferirPadrao({
  bloco: { tema: 'a', estado: 'rascunho', medido_em: '2026-07-29', dono: 'README.md' }, donoExiste: sempre, registro: '',
}).some((q) => q.includes('não é `viva`')));
t('viva sobre tema que já tem viva, sem sucede', conferirPadrao({
  bloco: { tema: 'sistema', estado: 'viva', medido_em: '2026-07-29', dono: 'README.md' },
  donoExiste: sempre, registro: REGISTRO_COM_VIVA,
}).some((q) => q.includes('sucede')));

console.log('\n── NÃO MORDE (controles negativos) ──');
t('vista conforme = zero queixa', conferirPadrao({
  bloco: { tema: 'novo', estado: 'viva', medido_em: '2026-07-29', dono: 'README.md' },
  donoExiste: sempre, registro: REGISTRO_COM_VIVA,
}).length === 0);
t('histórica não exige sucede', conferirPadrao({
  bloco: { tema: 'sistema', estado: 'histórica', medido_em: '2026-07-29', dono: 'README.md' },
  donoExiste: sempre, registro: REGISTRO_COM_VIVA,
}).length === 0);
t('viva COM sucede passa', conferirPadrao({
  bloco: { tema: 'sistema', estado: 'viva', medido_em: '2026-07-29', dono: 'README.md', sucede: 'https://x' },
  donoExiste: sempre, registro: REGISTRO_COM_VIVA,
}).length === 0);

console.log('\n── E2E pelo stdin ──');
const ruim = rodar({ tool_name: 'Artifact', tool_input: { file_path: vista('ruim.html', '<h1>sem bloco</h1>') } });
t('E2E ruim: avisa no stderr', /VISTA FORA DO PADRÃO/.test(ruim.err));
t('E2E ruim: NÃO bloqueia (advisory, exit 0)', ruim.code === 0);

const bom = rodar({ tool_name: 'Artifact', tool_input: { file_path: vista('bom.html', BLOCO_BOM) } });
t('E2E bom: silencioso', bom.err.trim() === '' && bom.code === 0);

const outra = rodar({ tool_name: 'Write', tool_input: { file_path: vista('w.html', '<h1>x</h1>') } });
t('E2E tool alheia: silencioso', outra.err.trim() === '' && outra.code === 0);

const lista = rodar({ tool_name: 'Artifact', tool_input: { action: 'list' } });
t('E2E action=list: silencioso', lista.err.trim() === '' && lista.code === 0);

const inexistente = rodar({ tool_name: 'Artifact', tool_input: { file_path: join(TMP, 'nao-existe.html') } });
t('E2E arquivo inexistente: silencioso', inexistente.err.trim() === '' && inexistente.code === 0);

const lixo = spawnSync('node', [HOOK], { input: 'isto não é json', encoding: 'utf8' });
t('fail-open: stdin inválido não trava nem avisa', lixo.status === 0 && (lixo.stderr || '').trim() === '');

console.log(`\n${fail === 0 ? '✅' : '❌'} ${ok} passaram, ${fail} falharam\n`);
process.exit(fail === 0 ? 0 : 1);
