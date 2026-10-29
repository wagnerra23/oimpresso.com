#!/usr/bin/env node
// Prova de contrato de .github/workflows/deploy.yml.
// Verifica a topologia que impede publicar bundle incompleto ou declarar sucesso sem smoke.
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const workflow = readFileSync(resolve('.github/workflows/deploy.yml'), 'utf8');

export function lacunas(texto) {
  const checks = [
    ['push em main', /push:\s*[\s\S]{0,120}?branches:\s*\[main\]/],
    ['execução manual explícita', /workflow_dispatch:/],
    ['deploys serializados sem cancelar o anterior', /group:\s*deploy-production[\s\S]{0,80}?cancel-in-progress:\s*false/],
    ['build produz artefato e falha quando ele falta', /uses:\s*actions\/upload-artifact@v4[\s\S]{0,800}?name:\s*vite-build[\s\S]{0,800}?if-no-files-found:\s*error/],
    ['deploy depende do build', /\n  deploy:\s*[\s\S]{0,180}?needs:\s*build/],
    ['deploy baixa exatamente o artefato do build', /uses:\s*actions\/download-artifact@v4[\s\S]{0,160}?name:\s*vite-build/],
    ['publicação fixa o SHA do run', /git reset --hard \$\{\{ github\.sha \}\}/],
    ['maintenance tem saída normal', /name:\s*Maintenance mode OFF[\s\S]{0,180}?php artisan up/],
    ['failsafe executa mesmo após falha', /name:\s*Failsafe[^\n]*[\s\S]{0,2200}?if:\s*always\(\)/],
    ['smoke consulta produção por HTTP', /name:\s*Smoke test[^\n]*[\s\S]{0,500}?https:\/\/oimpresso\.com\/login/],
    ['smoke vermelho encerra com falha', /name:\s*Falha smoke[^\n]*[\s\S]{0,300}?exit 1/],
  ];
  return checks.filter(([, rx]) => !rx.test(texto)).map(([nome]) => nome);
}

let fails = 0;
function check(nome, ok) {
  console.log(`${ok ? '✓' : '✗'} ${nome}`);
  if (!ok) fails++;
}

const lacunasReais = lacunas(workflow);
check(`RELEASE: workflow real preserva build → artefato → deploy → smoke${lacunasReais.length ? ` [faltam: ${lacunasReais.join('; ')}]` : ''}`, lacunasReais.length === 0);

const semArtefatoObrigatorio = workflow.replace('if-no-files-found: error', 'if-no-files-found: warn');
check('BITE: artefato ausente não pode virar warning', lacunas(semArtefatoObrigatorio).includes('build produz artefato e falha quando ele falta'));

const semDependencia = workflow.replace(/(\n  deploy:\s*[\s\S]{0,120}?)needs:\s*build/, '$1');
check('BITE: deploy solto do build é detectado', lacunas(semDependencia).includes('deploy depende do build'));

const smokeMudo = workflow.replace(/(name:\s*Falha smoke[^\n]*[\s\S]{0,300}?)exit 1/, '$1echo ignorado');
check('BITE: smoke que registra erro mas retorna verde é detectado', lacunas(smokeMudo).includes('smoke vermelho encerra com falha'));

const shaMovel = workflow.replaceAll('git reset --hard ${{ github.sha }}', 'git reset --hard origin/main');
check('BITE: publicar tip móvel em vez do SHA medido é detectado', lacunas(shaMovel).includes('publicação fixa o SHA do run'));

// ── Quais pushes publicam ────────────────────────────────────────────────────
// Filtro de paths do GitHub: o ÚLTIMO padrão que casa decide; `!` exclui. `**` casa qualquer
// coisa (inclusive `/`), `*` não passa de `/`. Lê os padrões do próprio workflow.
export function padroesDoPush(texto) {
  const bloco = texto.match(/\n  push:\n[\s\S]*?\n    paths:\n((?:\s*#.*\n|      - .*\n)+)/);
  if (!bloco) return null;
  return [...bloco[1].matchAll(/^      - '([^']+)'/gm)].map(m => m[1]);
}
const paraRegex = glob => new RegExp('^' + glob.replace(/[.+^${}()|[\]\\]/g, '\\$&')
  .replace(/\*\*/g, '\u0000').replace(/\*/g, '[^/]*').replace(/\u0000/g, '.*') + '$');
export function publica(padroes, arquivo) {
  let sim = false;
  for (const p of padroes) {
    const neg = p.startsWith('!');
    if (paraRegex(neg ? p.slice(1) : p).test(arquivo)) sim = !neg;
  }
  return sim;
}
const ESPERADO = [
  ['resources/js/Pages/Fiscal/Nfse.casos.md', true],          // a página lê do disco
  ['memory/requisitos/Produto/_telas/quick-add.casos.md', true],// também lida pela página
  ['prototipo-ui/cowork/Wagner/venda.casos.md', false],        // espelho: a página ignora
  ['app/Http/Controllers/CasosPorTelaController.php', true],
  ['resources/js/Pages/Fiscal/Nfse.charter.md', false],
  ['memory/decisions/0001-x.md', false],
  ['memory/requisitos/Fiscal/BRIEFING.md', false],
  ['README.md', false],
  ['prototipo-ui/cowork/Wagner/venda.jsx', false],
];
const padroes = padroesDoPush(workflow);
check('RELEASE: o workflow declara os paths do push', Array.isArray(padroes) && padroes.length > 0);
const erros = (padroes || []).length ? ESPERADO.filter(([f, esp]) => publica(padroes, f) !== esp).map(([f]) => f) : ['sem padrões'];
check(`RELEASE: casos.md publica; docs, memória e protótipo não${erros.length ? ` [errado: ${erros.join(', ')}]` : ''}`, erros.length === 0);
const semExcecao = (padroes || []).filter(p => p !== '**.casos.md');
check('BITE: sem a exceção, um merge só de casos.md volta a não publicar', !publica(semExcecao, 'resources/js/Pages/Fiscal/Nfse.casos.md'));
const ordemTrocada = ['**', '**.casos.md', ...(padroes || []).filter(p => p !== '**' && p !== '**.casos.md')];
check('BITE: com a exceção antes do !**.md, ela não vale', !publica(ordemTrocada, 'resources/js/Pages/Fiscal/Nfse.casos.md'));

console.log(fails ? `\n${fails} falha(s)` : '\nOK — contrato do deploy tem RELEASE e seis mutações que mordem.');
process.exit(fails ? 1 : 0);
