import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, mkdtempSync, writeFileSync, unlinkSync, rmdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';
import { avaliar, proximas, descobrirIndices } from './placar-indice.mjs';
import { hash, validarIndice } from './placar-evidencia.mjs';

function fixture() {
  const t = { id: '01', titulo: 'Trava', dono: 'CL', arquivo: '01.md', prefixo: ['service.php'],
    provas: [{ tipo: 'contem', path: 'service.php', padrao: 'saldo' }, { tipo: 'execucao', path: 'recibo.json', testes: ['test.php'] }] };
  const indice = { modulo: 'Teste', sha: 'abcdef0', gerado: '2026-09-08', threads: [t] };
  const files = { 'service.php': 'saldo', 'test.php': 'teste saldo', '_saida-01.md': 'entrega',
    'summary.json': JSON.stringify({ schema: 'junit-summary/v1', coherent: true, provou_algo: true,
      files: [{ file: 'test.php', tests: 1, passed: 1, assertions: 2, failed: 0, errors: 0, skipped: 0 }] }) };
  const receipt = () => { files['recibo.json'] = JSON.stringify({ thread: '01', exitCode: 0, runner: 'ct100', command: ['php', 'artisan', 'test'],
    summary: 'summary.json', testes: ['test.php'], arquivos: Object.fromEntries(Object.entries(files).filter(([p]) => p !== 'recibo.json').map(([p,v]) => [p, hash(v)])) }); };
  receipt();
  const ctx = { dirPlaybook: '.', existe: p => Object.hasOwn(files, p.replace(/^\.\//, '')), ler: p => files[p.replace(/^\.\//, '')] };
  // O recibo guarda o mesmo path usado pelo avaliador para a saída.
  ctx.dirPlaybook = 'pb'; files['pb/_saida-01.md'] = files['_saida-01.md']; delete files['_saida-01.md']; receipt();
  return { indice, files, ctx, receipt, run: () => avaliar(indice,ctx) };
}

test('controle positivo: teste executado e hashes atuais concluem', () => assert.equal(fixture().run().feito, 1));
test('recibo de outra suíte não satisfaz o teste declarado no plano', () => {
  const f=fixture(); f.indice.threads[0].provas[1].testes=['outro.php']; assert.equal(f.run().feito,0);
});
test('um_de exige somente a alternativa existente e suporta variáveis',()=>{
  const f=fixture(); f.indice.variaveis={DIR:'src'};
  f.files['src/service.php']=f.files['service.php'];delete f.files['service.php'];
  f.indice.threads[0].provas[0]={tipo:'um_de',paths:['${DIR}/service.php','src/ausente.php']};f.receipt();
  assert.equal(f.run().feito,1);
});
test('variável não pode escapar da raiz nem paths ocultar o path de uma prova',()=>{
  const f=fixture();f.indice.variaveis={DIR:'../fora'};assert.throws(()=>f.run());
  delete f.indice.variaveis; f.indice.threads[0].provas[0].paths=['seguro.php'];assert.throws(()=>f.run());
});
test('JSON null e primitivos são provas inválidas, sem derrubar o relatório',()=>{
  for(const raw of ['null','1','"texto"','[]']){
    const f=fixture();f.files['obj.json']=raw;f.indice.threads[0].provas.push({tipo:'json_com_chaves',path:'obj.json',chaves:['a']});
    assert.equal(f.run().feito,0);
  }
});
for (const alvo of ['service.php', 'test.php', 'summary.json', 'pb/_saida-01.md', 'recibo.json']) {
  test(`evidência ausente ou alterada recusa entrega: ${alvo}`, () => {
    const f=fixture(); delete f.files[alvo]; assert.equal(f.run().feito,0);
    const g=fixture(); g.files[alvo]+=' alterado'; assert.equal(g.run().feito,0);
  });
}
for (const campo of ['failed','errors','skipped','assertions','passed']) {
  test(`JUnit não integralmente verde: ${campo}`, () => {
    const f=fixture(), s=JSON.parse(f.files['summary.json']); s.files[0][campo]=['assertions','passed'].includes(campo)?0:1;
    f.files['summary.json']=JSON.stringify(s); f.receipt(); assert.equal(f.run().feito,0);
  });
}
test('arquivo de saída sozinho e prova pré-existente não concluem', () => {
  const f=fixture(); f.indice.threads[0].provas.pop(); assert.equal(f.run().feito,0);
  delete f.files['pb/_saida-01.md']; assert.equal(f.run().linhas[0].estado,'proximo');
});
test('retomada aparece no próximo passo', () => {
  const f=fixture(); delete f.files['recibo.json']; assert.equal(proximas(f.run())[0].estado,'em curso');
});
test('dependência incompleta impede executar ou concluir, mesmo fora de ordem', () => {
  const f=fixture(); f.indice.threads[0].depende_threads=['02'];
  f.indice.threads.push({id:'02',titulo:'Predecessora',dono:'CL',arquivo:'02.md',prefixo:[],provas:[]});
  const r=f.run(); assert.equal(r.feito,0); assert.equal(r.linhas[0].executavel,false);
});
for(const erro of ['typo','ciclo','ausente','duplicada','decisao','path']) test(`schema/grafo recusa ${erro}`,()=>{
  const f=fixture(),t=f.indice.threads[0];
  if(erro==='typo')t.depende_thread=['02'];
  if(erro==='ciclo')t.depende_threads=['01'];
  if(erro==='ausente')t.depende_threads=['02'];
  if(erro==='duplicada')f.indice.threads.push(t);
  if(erro==='decisao')t.depende_decisoes=['NAO-EXISTE'];
  if(erro==='path')t.provas[0].path='../segredo';
  assert.throws(()=>f.run());
});
test('decisão pendente e bloqueio vencem recibo verde',()=>{
  const f=fixture();f.indice.decisoes=[{id:'D',pergunta:'Destino?',respondida:false}];f.indice.threads[0].depende_decisoes=['D'];assert.equal(f.run().feito,0);
  f.indice.decisoes[0].respondida=true; assert.equal(f.run().feito,1);
  f.indice.threads[0].bloqueio='Descartada';assert.equal(f.run().linhas[0].executavel,false);assert.equal(f.run().feito,0);
});
test('playbooks reais continuam parseáveis e dependência/retencao estão reconciliadas',()=>{
  const root = fileURLToPath(new URL('../../../../', import.meta.url));
  const paths = descobrirIndices(root);
  assert.ok(paths.length > 0, 'inventário não pode ficar vazio');
  for(const p of paths) {
    const raw=readFileSync(new URL('../../../../'+p,import.meta.url),'utf8');
    const index=JSON.parse(raw.match(/```json\s*\n([\s\S]*?)\n```/)[1]); validarIndice(index);
    if(index.modulo==='Patrimonio'){assert.deepEqual(index.threads.find(t=>t.id==='02').depende_threads,['01']);assert.ok(index.threads.find(t=>t.id==='05').bloqueio);}
  }
});

test('Governança: sufixo 03a participa das dependências sem renumerar a ficha',()=>{
 const f=fixture(); f.indice.threads[0].depende_threads=['03a'];
 f.indice.threads.push({id:'03a',titulo:'Contrato',dono:'CL',arquivo:'03a-contrato.md',prefixo:[],provas:[]});
 assert.equal(f.run().linhas[0].executavel,false);
});
test('CLI sem índices falha; --todos descobre todos sem depender de glob nativo',()=>{
 const cli=fileURLToPath(new URL('./placar-indice.mjs',import.meta.url));
 const root=fileURLToPath(new URL('../../../../',import.meta.url));
 const vazio=spawnSync(process.execPath,[cli],{cwd:root,encoding:'utf8'});
 assert.equal(vazio.status,2);
 const todos=spawnSync(process.execPath,[cli,'--todos','--root',root],{cwd:root,encoding:'utf8'});
 assert.equal(todos.status,1,todos.stderr);
 assert.equal(todos.stdout.split('entregue').length-1,descobrirIndices(root).length);
});

for (const [nome, assertions, esperado] of [['PHPUnit', ' assertions="2"', 1], ['JUnit sem assertions', '', 0]]) {
 test('produtor real de resumo: '+nome,()=>{
  const dir=mkdtempSync(join(tmpdir(),'placar-junit-'));
  const xml=join(dir,'suite.xml');
  try {
   writeFileSync(xml,'<testsuites><testsuite tests="1"><testcase file="test.php" name="saldo"'+assertions+'/></testsuite></testsuites>');
   const root=fileURLToPath(new URL('../../../../',import.meta.url));
   const result=spawnSync(process.execPath,[join(root,'scripts/tests/junit-summary.mjs'),xml],{cwd:root,encoding:'utf8',env:{...process.env,GITHUB_STEP_SUMMARY:''}});
   assert.equal(result.status,0,result.stderr);
   const f=fixture();f.files['summary.json']=result.stdout;f.receipt();
   assert.equal(f.run().feito,esperado);
  } finally { unlinkSync(xml); rmdirSync(dir); }
 });
}
