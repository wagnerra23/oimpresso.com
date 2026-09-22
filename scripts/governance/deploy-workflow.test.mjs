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

console.log(fails ? `\n${fails} falha(s)` : '\nOK — contrato do deploy tem RELEASE e quatro mutações que mordem.');
process.exit(fails ? 1 : 0);
