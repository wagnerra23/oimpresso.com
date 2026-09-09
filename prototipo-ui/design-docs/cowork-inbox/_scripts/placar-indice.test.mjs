import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, mkdtempSync, writeFileSync, unlinkSync, rmdirSync } from 'node:fs';
import { compararNoRepo } from './placar-comparacao.mjs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';
import { avaliar, proximas, descobrirIndices, coberturaModulos } from './placar-indice.mjs';
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

function playwrightFixture() {
 const f=fixture();f.indice.threads[0].provas[1].formato='playwright-json';
 f.indice.threads[0].provas[1].raiz_testes='.';
 f.files['summary.json']=JSON.stringify({errors:[],stats:{expected:1,unexpected:0,flaky:0,skipped:0},
   suites:[{specs:[{file:'test.php',ok:true,tests:[{expectedStatus:'passed',status:'expected',results:[{status:'passed',retry:0,errors:[]}]}]}]}]});
 f.receipt();return f;
}
test('Playwright: aceita o formato nativo sem inventar assertions',()=>assert.equal(playwrightFixture().run().feito,1));
for(const erro of ['skip','flaky','zero','arquivo','retry','error','failed','sem-results']) test('Playwright recusa '+erro,()=>{
 const f=playwrightFixture(),s=JSON.parse(f.files['summary.json']);const t=s.suites[0].specs[0].tests[0];
 if(erro==='skip')s.stats.skipped=1;
 if(erro==='flaky')t.status='flaky';
 if(erro==='zero')s.stats.expected=0;
 if(erro==='arquivo')s.suites[0].specs[0].file='outro.php';
 if(erro==='retry')t.results[0].retry=1;
 if(erro==='error')s.errors.push({message:'falhou'});
 if(erro==='failed')t.results[0].status='failed';
 if(erro==='sem-results')t.results=[];
 f.files['summary.json']=JSON.stringify(s);f.receipt();assert.equal(f.run().feito,0);
});
function revisaoFixture() {
 const f=fixture();f.indice.threads[0].prefixo=['parecer.md'];
 f.indice.threads[0].provas=[{tipo:'revisao',path:'revisao.json',fontes:['parecer.md'],criterios:['rotas','limites']}];
 f.files['parecer.md']='levantamento';
 f.files['revisao.json']=JSON.stringify({thread:'01',revisor:'revisor-teste',resultado:'aprovado',
  criterios:['rotas','limites'].map(id=>({id,resultado:'aprovado',justificativa:'Conferido no levantamento'})),
  arquivos:Object.fromEntries(['parecer.md','pb/_saida-01.md'].map(p=>[p,hash(f.files[p])]))});
 return f;
}
test('revisão documental conclui e libera dependência',()=>{
 const f=revisaoFixture();f.indice.threads.push({id:'02',titulo:'Seguinte',dono:'CL',arquivo:'02.md',prefixo:[],provas:[],depende_threads:['01']});
 assert.equal(f.run().feito,1);assert.equal(f.run().linhas[1].executavel,true);
});
for(const erro of ['codigo','fonte-stale','criterio','reprovado','outra-thread']) test('revisão recusa '+erro,()=>{
 const f=revisaoFixture(),r=JSON.parse(f.files['revisao.json']);
 if(erro==='codigo')f.indice.threads[0].prefixo=['controller.php'];
 if(erro==='fonte-stale')f.files['parecer.md']+='modificado';
 if(erro==='criterio')r.criterios.pop();
 if(erro==='reprovado')r.resultado='reprovado';
 if(erro==='outra-thread')r.thread='02';
 f.files['revisao.json']=JSON.stringify(r);assert.equal(f.run().feito,0);
});
test('comparação exige snapshots, contrato, fontes atuais e comparador verde',()=>{
 const f=fixture();f.indice.threads[0].provas=[{tipo:'comparacao',path:'visual.json',fontes:['service.php'],contrato:'tela.contract.json',dimensoes:['D2']}];
 for(const p of ['prod.json','proto.json','tela.contract.json'])f.files[p]='{}';
 f.files['visual.json']=JSON.stringify({thread:'01',producao:'prod.json',prototipo:'proto.json',arquivos:Object.fromEntries(Object.entries(f.files).map(([p,v])=>[p,hash(v)]))});
 assert.equal(f.run().feito,0);
 f.ctx.comparar=()=>true;assert.equal(f.run().feito,1);
 f.ctx.comparar=()=>false;assert.equal(f.run().feito,0);
 f.ctx.comparar=()=>true;f.files['proto.json']='alterado';assert.equal(f.run().feito,0);
});
test('inventário inclui módulos sem playbook sem afirmar conclusão',()=>{
 const r=coberturaModulos(fileURLToPath(new URL('../../../../',import.meta.url)));
 assert.ok(r.length>descobrirIndices(fileURLToPath(new URL('../../../../',import.meta.url))).length);
 assert.ok(r.some(m=>m.modulo==='AssetManagement'&&m.playbook));
 assert.ok(r.some(m=>m.modulo==='Financeiro'&&!m.playbook));
 assert.ok(r.every(m=>m.status==='não avaliado'));
});

test('comparador canônico real: igualdade passa; tema, identidade e bug recusam',()=>{
 const root=fileURLToPath(new URL('../../../../',import.meta.url));
 const dir=mkdtempSync(join(root,'.placar-visual-'));
 const prefix=dir.slice(root.length).replaceAll('\\','/');
 const paths=['prod.json','proto.json','tela.contract.json'];
 const receipt={producao:prefix+'/prod.json',prototipo:prefix+'/proto.json'};
 const prova={contrato:prefix+'/tela.contract.json',dimensoes:['D2','SHELL']};
 const prod={url:'https://oimpresso.com/asset/dashboard',theme:'dark',assinatura:'Titulo da prova',roles:{
  filterRows:1,kpi:{tag:'DIV',count:1,overflowX:false,items:[{textAlign:'left',smallAlign:'left',valueFontPx:20}]},
  shell:{papeis:{menu:{n:1,css:{fontSize:'13px'},caixa:{w:200,h:40},icone:null}},atalhos:[]}}};
 const design=structuredClone(prod);design.url='https://claude.ai/design/fixture';
 try {
  writeFileSync(join(dir,paths[0]),JSON.stringify(prod));
  writeFileSync(join(dir,paths[2]),JSON.stringify({secoes:[{id:'titulo',copy:['Titulo da prova']}]}));
  writeFileSync(join(dir,paths[1]),JSON.stringify(design));assert.equal(compararNoRepo(root,receipt,prova),true);
  design.theme='light';writeFileSync(join(dir,paths[1]),JSON.stringify(design));assert.equal(compararNoRepo(root,receipt,prova),false);
  design.theme='dark';design.assinatura='Outra tela';writeFileSync(join(dir,paths[1]),JSON.stringify(design));assert.equal(compararNoRepo(root,receipt,prova),false);
  design.assinatura='Titulo da prova';design.roles.kpi.count=2;writeFileSync(join(dir,paths[1]),JSON.stringify(design));assert.equal(compararNoRepo(root,receipt,prova),false);
 } finally {for(const p of paths)unlinkSync(join(dir,p));rmdirSync(dir);}
});
