#!/usr/bin/env node
// placar-indice.test.mjs — bite-test do PR-A8. Exercita o CLI de FORA (subprocesso), nunca só
// helpers puros: assert sobre satélite exportado não prova contrato de pipeline (§5 2026-07-30).
//
// O QUE CADA CLASSE DE CASO PROVA, e por que ela existe:
//   CONTROLE+   uma thread CHEGA a `feito` num corpus sintético. Sem este caso, o "0 de 61" que
//               o corpus real devolve hoje seria indistinguível de medidor cego — é a lição do
//               drift-sentinel (§5 2026-07-17): distribuição de um valor só acusa o MEDIDOR.
//   T5          o aceite do plano: apagar UMA prova do repo derruba X→X−1 NOMEANDO a thread.
//   LEI 2       `_saida-NN.md` ausente reprova a thread mesmo com todas as provas verdes —
//               "PR mergeado" não é estado; estado é derivado (Lei 2 por construção).
//   NÃO MEDI    prova de recibo sai `não medida`: não vira `feito` (fail-closed) e NÃO morde o
//               `--check` (acusar por falta de instrumento é LC-33, §5 2026-09-03). Os dois
//               lados são assertados, porque só um deles deixaria o outro livre pra regredir.
//   EXIT 2      índice ilegível/sem bloco json/sem threads → 2, nunca "0 de 0 = 100%".
//   INTEGRIDADE id duplicado · dependência fantasma · ciclo · decisão fantasma → 2.
//   MODOS       `--check` morde, default só reporta; `--md`/`--json` carregam o PAR — validar
//               um modo do job e chamar de verde é §5 2026-07-28.

import { execFileSync } from 'node:child_process';
import { writeFileSync, mkdirSync, rmSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const HERE = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(HERE, 'placar.mjs');
const TMP = join(tmpdir(), `placar-indice-test-${process.pid}`);
const INBOX = ['prototipo-ui', 'cowork', 'Wagner', 'cowork-inbox'];

let fails = 0;
const ok = (c, m) => { if (c) console.log(`  ✓ ${m}`); else { console.error(`  ✗ ${m}`); fails++; } };

const cli = (args) => {
  try { return { rc: 0, out: execFileSync('node', [SCRIPT, ...args], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }), err: '' }; }
  catch (e) { return { rc: e.status ?? 1, out: e.stdout || '', err: e.stderr || '' }; }
};

/** Monta um root com um playbook. `saidas` = ids com `_saida-NN.md`; `arquivos` = extras. */
let n = 0;
function root({ indice, saidas = [], arquivos = {}, mod = 'm' }) {
  const r = join(TMP, `r${++n}`);
  const dir = join(r, ...INBOX, mod, 'playbook');
  mkdirSync(dir, { recursive: true });
  const corpo = typeof indice === 'string' ? indice : '# Índice\n\n```json\n' + JSON.stringify(indice, null, 1) + '\n```\n';
  writeFileSync(join(dir, '00-INDICE.md'), corpo);
  for (const id of saidas) writeFileSync(join(dir, `_saida-${id}.md`), '# saida\n');
  for (const [p, c] of Object.entries(arquivos)) {
    const abs = join(r, ...p.split('/'));
    mkdirSync(dirname(abs), { recursive: true });
    writeFileSync(abs, c);
  }
  return r;
}
const rel = (r, mod = 'm') => [...INBOX, mod, 'playbook', '00-INDICE.md'].join('/');

/** Índice de 1 thread com N provas de `arquivo`, todas satisfeitas por `arquivos`. */
const IDX = (provas, extra = {}) => ({
  modulo: 'M', threads: [{ id: '01', titulo: 'T1', dono: 'CL', provas, ...extra }],
});

mkdirSync(TMP, { recursive: true });
console.log('placar-indice.test — bite/release do PR-A8\n');

/* ── 1. CONTROLE POSITIVO: uma thread CHEGA a feito ──────────────────────────────────── */
const provas2 = [{ tipo: 'arquivo', path: 'a.txt' }, { tipo: 'contem', path: 'b.txt', padrao: 'ok' }];
const completo = root({ indice: IDX(provas2), saidas: ['01'], arquivos: { 'a.txt': 'x', 'b.txt': 'ok' } });
let r = cli(['--root', completo, '--todos', '--check']);
ok(r.rc === 0, 'CONTROLE+: provas verdes + _saida → exit 0');
ok(/entregue 1 de 1/.test(r.out), 'CONTROLE+: a thread CHEGA a `feito` ("entregue 1 de 1") — o medidor discrimina');
ok(/\[feito/.test(r.out), 'CONTROLE+: o estado impresso é `feito`, derivado (ninguém escreveu)');

/* ── 2. T5 (o aceite do plano): apagar UMA prova derruba X→X−1 nomeando a thread ─────── */
const t5 = root({ indice: IDX(provas2), saidas: ['01'], arquivos: { 'b.txt': 'ok' } }); // a.txt AUSENTE
r = cli(['--root', t5, '--todos', '--check']);
ok(/entregue 0 de 1/.test(r.out), 'T5: prova removida do repo → o placar CAI (1 de 1 → 0 de 1)');
ok(/\b01\b/.test(r.out) && /a\.txt/.test(r.out), 'T5: a saída NOMEIA a thread (01) e o arquivo que falta (a.txt)');
ok(r.rc === 1, 'T5: com --check, o placar que não fecha MORDE (exit 1)');

/* ── 3. LEI 2: sem `_saida-NN.md` não há `feito`, mesmo com tudo verde ───────────────── */
const semSaida = root({ indice: IDX(provas2), saidas: [], arquivos: { 'a.txt': 'x', 'b.txt': 'ok' } });
r = cli(['--root', semSaida, '--todos', '--check']);
ok(r.rc === 1 && /entregue 0 de 1/.test(r.out), 'LEI 2: todas as provas verdes mas sem _saida → NÃO é feito');
ok(/sem _saida/.test(r.out), 'LEI 2: a saída diz que falta o _saida (não é reprovação muda)');

/* ── 4. NÃO MEDI (prova de recibo): não vira feito E não morde ───────────────────────── */
const recibo = root({ indice: IDX([{ tipo: 'execucao', path: 'r.json', testes: ['t'] }]), saidas: ['01'] });
r = cli(['--root', recibo, '--todos', '--check']);
ok(r.rc === 0, 'NÃO MEDI: prova de recibo NÃO morde o --check (acusar por falta de instrumento é LC-33)');
ok(/entregue 0 de 1/.test(r.out), 'NÃO MEDI: fail-closed — a thread também NÃO conta como entregue');
ok(/n[ãa]o medidas?: 1/i.test(r.out), 'NÃO MEDI: o total de não-medidas é DITO, não escondido');
ok(/recibo/.test(r.out), 'NÃO MEDI: a saída nomeia o motivo (avaliador de recibo ausente)');

/* ── 5. EXIT 2 — não medi estrutural, nunca "0 de 0 = 100%" ──────────────────────────── */
ok(cli(['--root', root({ indice: '# sem bloco json aqui\n' }), '--todos', '--check']).rc === 2, 'EXIT 2: 00-INDICE.md sem bloco json → 2');
ok(cli(['--root', root({ indice: '```json\n{ nao é json\n```\n' }), '--todos', '--check']).rc === 2, 'EXIT 2: bloco json inválido → 2');
ok(cli(['--root', root({ indice: { modulo: 'M', threads: [] } }), '--todos', '--check']).rc === 2, 'EXIT 2: índice sem threads → 2 ("0 de 0" não é placar)');
const vazio = join(TMP, 'vazio'); mkdirSync(vazio, { recursive: true });
ok(cli(['--root', vazio, '--todos', '--check']).rc === 2, 'EXIT 2: root sem nenhum playbook → 2');

/* ── 6. INTEGRIDADE do índice (o placar não mede um grafo quebrado) ──────────────────── */
const dup = { modulo: 'M', threads: [{ id: '01', provas: [] }, { id: '01', provas: [] }] };
ok(cli(['--root', root({ indice: dup }), '--todos']).rc === 2, 'INTEGRIDADE: thread duplicada → 2');
const fantasma = { modulo: 'M', threads: [{ id: '01', provas: [], depende_threads: ['99'] }] };
ok(cli(['--root', root({ indice: fantasma }), '--todos']).rc === 2, 'INTEGRIDADE: dependência inexistente → 2');
const ciclo = { modulo: 'M', threads: [{ id: '01', provas: [], depende_threads: ['02'] }, { id: '02', provas: [], depende_threads: ['01'] }] };
ok(cli(['--root', root({ indice: ciclo }), '--todos']).rc === 2, 'INTEGRIDADE: dependência circular → 2 (não trava o processo)');
const decFantasma = { modulo: 'M', threads: [{ id: '01', provas: [], depende_decisoes: ['D-X'] }] };
ok(cli(['--root', root({ indice: decFantasma }), '--todos']).rc === 2, 'INTEGRIDADE: decisão inexistente → 2');
const varFora = { modulo: 'M', variaveis: { UI: '../fora' }, threads: [{ id: '01', provas: [] }] };
ok(cli(['--root', root({ indice: varFora }), '--todos']).rc === 2, 'INTEGRIDADE: variável apontando pra fora do repo → 2');

/* ── 7. DEPENDÊNCIA e DECISÃO travam o `PRÓXIMO:` ────────────────────────────────────── */
const cadeia = {
  modulo: 'M',
  threads: [
    { id: '01', titulo: 'A', dono: 'CL', provas: [{ tipo: 'arquivo', path: 'a.txt' }] },
    { id: '02', titulo: 'B', dono: 'CL', provas: [{ tipo: 'arquivo', path: 'b.txt' }], depende_threads: ['01'] },
  ],
};
r = cli(['--root', root({ indice: cadeia, arquivos: { 'a.txt': 'x', 'b.txt': 'x' } }), '--todos', '--proximo']);
ok(/PRÓXIMO:.*\b01\b/.test(r.out) && !/PRÓXIMO:.*\b02\b/.test(r.out), 'DEPS: só a thread sem dependência pendente entra em PRÓXIMO:');
const comDecisao = {
  modulo: 'M', decisoes: [{ id: 'D-1', pergunta: 'q', respondida: false }],
  threads: [{ id: '01', titulo: 'A', dono: 'CL', provas: [{ tipo: 'arquivo', path: 'a.txt' }], depende_decisoes: ['D-1'] }],
};
r = cli(['--root', root({ indice: comDecisao, saidas: ['01'], arquivos: { 'a.txt': 'x' } }), '--todos', '--proximo']);
ok(/decis[ãa]o pendente/.test(r.out), 'DECISÃO [W]: pendente aparece como motivo');
ok(/PRÓXIMO: nenhum/.test(r.out), 'DECISÃO [W]: pendente TIRA a thread do PRÓXIMO: (mesmo com provas verdes)');
const respondida = JSON.parse(JSON.stringify(comDecisao)); respondida.decisoes[0].respondida = true;
r = cli(['--root', root({ indice: respondida, saidas: ['01'], arquivos: { 'a.txt': 'x' } }), '--todos', '--check']);
ok(r.rc === 0 && /entregue 1 de 1/.test(r.out), 'CONTROLE+: respondida a decisão, a MESMA thread vira feito (o número acompanha)');

/* ── 8. `${VAR}` não decidida: não vira path chutado nem thread executável ───────────── */
const varNula = { modulo: 'M', variaveis: { PAGES: null }, threads: [{ id: '01', titulo: 'A', dono: 'CL', provas: [{ tipo: 'arquivo', path: '${PAGES}/x.tsx' }] }] };
r = cli(['--root', root({ indice: varNula }), '--todos', '--proximo']);
ok(/vari[áa]vel n[ãa]o decidida/.test(r.out), 'VARIÁVEL: `${PAGES}` nula é reportada como não decidida, não chutada');
ok(/PRÓXIMO: nenhum/.test(r.out), 'VARIÁVEL: thread com variável nula NÃO é executável');

/* ── 9. Tipos estruturais: cada um morde e libera ────────────────────────────────────── */
const tipo = (provas, arquivos) => cli(['--root', root({ indice: IDX(provas), saidas: ['01'], arquivos }), '--todos', '--check']).rc;
ok(tipo([{ tipo: 'ausente', path: 'z.txt' }], {}) === 0, 'TIPO ausente: arquivo inexistente → passa');
ok(tipo([{ tipo: 'ausente', path: 'z.txt' }], { 'z.txt': 'x' }) === 1, 'TIPO ausente: arquivo AINDA existe → morde');
ok(tipo([{ tipo: 'nao_contem', path: 'z.txt', padrao: 'TODO' }], { 'z.txt': 'limpo' }) === 0, 'TIPO nao_contem: padrão ausente → passa');
ok(tipo([{ tipo: 'nao_contem', path: 'z.txt', padrao: 'TODO' }], { 'z.txt': 'tem TODO' }) === 1, 'TIPO nao_contem: padrão presente → morde');
ok(tipo([{ tipo: 'json_com_chaves', path: 'z.json', chaves: ['a'] }], { 'z.json': '{"a":1}' }) === 0, 'TIPO json_com_chaves: chave presente → passa');
ok(tipo([{ tipo: 'json_com_chaves', path: 'z.json', chaves: ['a'] }], { 'z.json': '{"b":1}' }) === 1, 'TIPO json_com_chaves: chave faltando → morde');
ok(tipo([{ tipo: 'json_com_chaves', path: 'z.json', chaves: ['a'] }], { 'z.json': '[1]' }) === 1, 'TIPO json_com_chaves: array não é objeto → morde');
ok(tipo([{ tipo: 'um_de', paths: ['x/a.tsx', 'x/b.tsx'] }], { 'x/b.tsx': 'x' }) === 0, 'TIPO um_de: basta UMA alternativa existir → passa');
ok(tipo([{ tipo: 'um_de', paths: ['x/a.tsx', 'x/b.tsx'] }], {}) === 1, 'TIPO um_de: nenhuma existe → morde');

/* ── 10. `bloqueio` é decisão declarada, não dívida do Code ──────────────────────────── */
const bloq = { modulo: 'M', threads: [{ id: '01', titulo: 'A', dono: 'CL', bloqueio: 'aguarda [W]', provas: [{ tipo: 'arquivo', path: 'nao-existe.txt' }] }] };
r = cli(['--root', root({ indice: bloq }), '--todos', '--check']);
ok(r.rc === 0, 'BLOQUEIO: thread bloqueada não faz o placar reprovar (bloqueio é decisão, não dívida)');
ok(/\[bloqueada/.test(r.out), 'BLOQUEIO: o estado `bloqueada` aparece na saída');

/* ── 11. MODOS: os dois do job, e o número MUDA com o corpus ─────────────────────────── */
ok(cli(['--root', t5, '--todos']).rc === 0, 'MODO: sem --check o script só REPORTA (exit 0) — quem morde é o --check');
const md = cli(['--root', t5, '--todos', '--md']);
ok(/entregue 0 de 1/.test(md.out), 'MODO --md: o comentário carrega o PAR entregue/alvo');
ok(/PRÓXIMO:/.test(md.out), 'MODO --md: o comentário traz o PRÓXIMO:');
ok(/entregue 1 de 1/.test(cli(['--root', completo, '--todos', '--md']).out), 'CONTROLE+ --md: o número MUDA com o corpus (o md não é carimbo)');
const j = JSON.parse(cli(['--root', completo, '--todos', '--json']).out);
ok(j.somaFeito === 1 && j.somaTotal === 1, 'MODO --json: expõe o par pra máquina');
ok(j.modulos[0].linhas[0].estado === 'feito', 'MODO --json: o estado por thread é exposto');

/* ── 12. `--indice <arquivo>` mede UM playbook (o outro eixo do plano) ───────────────── */
r = cli(['--root', completo, '--indice', rel(completo), '--check']);
ok(r.rc === 0 && /entregue 1 de 1/.test(r.out), '--indice <arquivo>: mede um playbook só');
ok(cli(['--root', completo, '--indice', 'nao/existe/00-INDICE.md']).rc === 2, '--indice inexistente → 2 (não medi), nunca verde');

/* ── 13. `--todos` SOMA os módulos (o unificador do plano) ───────────────────────────── */
const multi = root({ indice: IDX(provas2), saidas: ['01'], arquivos: { 'a.txt': 'x', 'b.txt': 'ok' }, mod: 'alpha' });
mkdirSync(join(multi, ...INBOX, 'beta', 'playbook'), { recursive: true });
writeFileSync(join(multi, ...INBOX, 'beta', 'playbook', '00-INDICE.md'),
  '```json\n' + JSON.stringify({ modulo: 'BETA', threads: [{ id: '01', titulo: 'B', dono: 'CL', provas: [{ tipo: 'arquivo', path: 'nao-existe.txt' }] }] }) + '\n```\n');
r = cli(['--root', multi, '--todos']);
ok(/entregue 1 de 1/.test(r.out) && /BETA/.test(r.out), '--todos: os DOIS módulos aparecem');
ok(/cobertura cumulativa: 1 de 2/.test(r.out), '--todos: a cobertura SOMA os módulos (1 de 2)');

/* ── 14. `--thread NN` (A8 × A7): recorta o RELATO, sem falsear o estado ─────────────── */
// A cadeia 01→02 é o caso que separa "recortar o relato" de "recortar a avaliação": se o
// filtro fosse aplicado ANTES, a 02 perderia de vista que a 01 não está feita e poderia
// aparecer como executável. O teste pina os dois lados.
const cad = root({ indice: cadeia, saidas: ['01'], arquivos: { 'a.txt': 'x', 'b.txt': 'x' } });
r = cli(['--root', cad, '--indice', rel(cad), '--thread', '01', '--check']);
ok(/entregue 1 de 1/.test(r.out) && !/\b02\b/.test(r.out), '--thread 01: recorta pra UMA thread (a 02 some do relato)');
r = cli(['--root', cad, '--indice', rel(cad), '--thread', '02', '--proximo']);
ok(/entregue 0 de 1/.test(r.out), '--thread 02: a thread ainda não entregue NÃO aparece como entregue');
// A 01 está `feito` (prova + _saida), então a 02 É executável — e o recorte só sabe disso
// porque a avaliação rodou na cadeia INTEIRA antes de filtrar. É o par que prova o desenho.
ok(/PRÓXIMO:.*\b02\b/.test(r.out), '--thread 02: com a 01 feita, a 02 É o próximo (o recorte NÃO perdeu a cadeia)');
// O outro lado do par: sem o `_saida-01.md` a 01 não fecha, e aí a 02 não pode ser próximo.
const cadSemSaida = root({ indice: cadeia, arquivos: { 'a.txt': 'x', 'b.txt': 'x' } });
r = cli(['--root', cadSemSaida, '--indice', rel(cadSemSaida), '--thread', '02', '--proximo']);
ok(/PRÓXIMO: nenhum/.test(r.out), '--thread 02: com a 01 NÃO feita, a 02 deixa de ser próximo (a dependência atravessa o recorte)');
ok(cli(['--root', cad, '--indice', rel(cad), '--thread', '99']).rc === 2, '--thread inexistente → 2 (NÃO MEDI), nunca "0 de 0"');

/* ── 15. ENDEREÇO APOSENTADO pela ADR 0397 — medir no novo, sem acusar o inocente ────── */
// O corpus real (12 índices) veio do import da conta Cowork carregando `prova.path` do mundo
// pré-0397. Antes disto o placar respondia "arquivo ausente" — que o leitor entende como "a
// thread não entregou" — para arquivo que EXISTE no endereço novo. Era LC-33 na direção
// acusação, e fabricava um placar que não podia sair de zero (gate-de-teatro invertido).
// Cada caso abaixo vem com o controle NEGATIVO ao lado: sem ele, "migrou" ficaria livre pra
// virar carimbo que aprova qualquer coisa.

// CONTROLE+ : prova cita `prototipo-ui/contrato/…` (D3) e o arquivo vive em
//             `governance/design/contracts/…`. A thread tem de FECHAR.
const apos = root({
  indice: IDX([{ tipo: 'arquivo', path: 'prototipo-ui/contrato/x.contract.json' }]),
  saidas: ['01'],
  arquivos: { 'governance/design/contracts/x.contract.json': '{}' },
});
r = cli(['--root', apos, '--indice', rel(apos), '--check']);
ok(r.rc === 0 && /entregue 1 de 1/.test(r.out), 'endereço pré-0397 (D3 contratos): mede no endereço NOVO e a thread FECHA');
ok(/fonte desatualizada: 1 prova/.test(r.out), 'a migração é DITA no relato — a dívida da fonte não fica muda');

// CONTROLE− : mesmo endereço aposentado, mas o arquivo NÃO existe no destino novo.
//             Tem de continuar reprovando: migrar endereço não é perdoar ausência.
const aposVazio = root({ indice: IDX([{ tipo: 'arquivo', path: 'prototipo-ui/contrato/x.contract.json' }]), saidas: ['01'] });
r = cli(['--root', aposVazio, '--indice', rel(aposVazio), '--check']);
ok(r.rc === 1 && /entregue 0 de 1/.test(r.out), 'CONTROLE−: endereço migrado mas arquivo ausente no destino SEGUE reprovando');
ok(/governance\/design\/contracts\/x\.contract\.json/.test(r.out), 'CONTROLE−: o motivo cita o endereço NOVO (não manda procurar no lugar velho)');

// D2 (dono no endereço) e D5 (cowork-inbox) — as outras duas regras estruturais.
const aposDono = root({
  indice: IDX([{ tipo: 'arquivo', path: 'prototipo-ui/cowork/sidebar.jsx' }]),
  saidas: ['01'], arquivos: { 'prototipo-ui/cowork/Wagner/sidebar.jsx': 'x' },
});
ok(/entregue 1 de 1/.test(cli(['--root', aposDono, '--indice', rel(aposDono)]).out), 'D2: `cowork/<solto>` resolve sob o dono Wagner');
const aposInbox = root({
  indice: IDX([{ tipo: 'arquivo', path: 'prototipo-ui/design-docs/cowork-inbox/m/playbook/_saida-03.md' }]),
  saidas: ['01'], arquivos: { 'prototipo-ui/cowork/Wagner/cowork-inbox/m/playbook/_saida-03.md': 'x' },
});
ok(/entregue 1 de 1/.test(cli(['--root', aposInbox, '--indice', rel(aposInbox)]).out), 'D5: `design-docs/cowork-inbox/` resolve na árvore viva (o caso Fiscal/03)');

// SEM SUCESSOR: `design-docs/` fora do `cowork-inbox/` não tem destino fixado pela ADR.
// Não se inventa (D6 proíbe basename) — sai NÃO MEDIDA: não fecha e NÃO morde o --check.
const semSuc = root({
  indice: IDX([{ tipo: 'arquivo', path: 'prototipo-ui/design-docs/contrato-cowork/g.contract.json' }]),
  saidas: ['01'],
});
r = cli(['--root', semSuc, '--indice', rel(semSuc), '--check']);
ok(r.rc === 0, 'sem sucessor: NÃO morde o --check (acusar por falta de endereço é LC-33)');
ok(/entregue 0 de 1/.test(r.out) && /não medida|nao medidas/i.test(r.out), 'sem sucessor: também NÃO fecha (fail-closed nos dois lados)');

// CONTROLE− : path que a ADR 0397 não aposentou passa INTACTO. Sem este caso, a migração
//             poderia estar reescrevendo endereço legítimo e ninguém veria.
const intacto = root({ indice: IDX([{ tipo: 'arquivo', path: 'resources/js/Pages/X.tsx' }]), saidas: ['01'], arquivos: { 'resources/js/Pages/X.tsx': 'x' } });
r = cli(['--root', intacto, '--indice', rel(intacto)]);
ok(/entregue 1 de 1/.test(r.out) && !/fonte desatualizada/.test(r.out), 'CONTROLE−: endereço vivo passa intacto e NÃO é reportado como migrado');

/* ── 16. O eixo A6 (tela) não regrediu — o A8 pluga, não substitui ───────────────────── */
ok(!existsSync(join(TMP, 'x')) && cli(['--dir', join(TMP, 'nao-existe')]).rc === 2, 'A6 intacto: o eixo de tela segue saindo 2 em diretório inexistente');

rmSync(TMP, { recursive: true, force: true });
console.log(`\n${fails ? `${fails} FALHA(S)` : 'todos os casos passaram'}`);
process.exit(fails ? 1 : 0);
