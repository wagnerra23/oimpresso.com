#!/usr/bin/env node
// @ts-check
/**
 * importar-bundle.test.mjs — MORDE/SOLTA do import do bundle Cowork, exercitando o CLI DE FORA.
 *
 * Por que existe: o `--selftest` do `importar-bundle.mjs` asserta SÓ `decidirSwap` — 4 casos
 * sobre uma função pura. Assert sobre helper puro exportado não prova contrato de pipeline
 * (§5 2026-07-30, LC-15): a guarda `if (!veredito.ok) return 1` podia sumir de `importar()` e
 * os 4 asserts seguiriam verdes. A divisão de trabalho fica assim, e é deliberada pra não
 * duplicar régua consolidada (§5 2026-07-09):
 *   · `--selftest` → `decidirSwap` (a função que DECIDE a troca);
 *   · este arquivo → `acharBundleRoot` + `classificarParaSync` + o contrato de PROCESSO (quem
 *     chama obedece?).
 *
 * A parte que DESTRÓI (2026-09-13): o `sincronizarCowork()` roda `robocopy /PURGE` e depois APAGA
 * arquivos do SSOT em `prototipo-ui/cowork/Wagner/` — o sweep de junk (allowlist de extensão) e o
 * de resíduo (regex de processo). Ela não tinha cobertura nenhuma, e não tinha COMO ter: a regra
 * vivia dentro de uma string heredoc PowerShell. Medido: `git grep -lF robocopy origin/main --
 * scripts .github` → 1 arquivo (o próprio script); `git grep -lE "runs-on:.*windows" origin/main
 * -- .github/workflows` → 0 runners. `classificarParaSync` tira a DECISÃO de lá (a execução segue
 * no PowerShell) e é o que os casos C1-C11 exercitam — mesmo movimento que tornou `decidirSwap` e
 * `acharBundleRoot` testáveis.
 *
 * Hermético e cross-platform: nenhum caso extrai zip de verdade, então não depende do
 * PowerShell — o script é Windows-only na extração e o CI roda `ubuntu-latest`. Os casos de CLI
 * asseguram o EFEITO observável (exit code + destino intacto), NUNCA a mensagem, que difere por
 * plataforma: no Windows a extração morre em `ZipFile::OpenRead`, no Linux o `powershell` nem
 * existe (ENOENT). Mesma conclusão por caminhos distintos — a lição de §5 2026-08-07 é
 * exatamente essa, não afirmar de uma plataforma o que se mediu na outra.
 *
 * Defeitos que ficam travados aqui:
 *   · `acharBundleRoot` voltar a assumir `<destino>/project`: o zip Cowork abre em
 *     `<slug>/project/`, e assumir o topo ANINHA o slug inteiro (bug 2026-07-01, descrito no
 *     cabeçalho do próprio script e sem um único teste até hoje);
 *   · ambiguidade (2+ raízes com host+app) resolvida em silêncio, em vez de avisar;
 *   · marca fraca ignorada, caindo pro destino cru e aninhando;
 *   · `importar()` tocar o destino ANTES de a integridade fechar — o "delete-before-verify" que
 *     é a razão de existir do script. O `--selftest` não prova isso: prova que a função que
 *     decide sabe decidir, não que quem a chama respeita o veredito.
 *
 * Segurança do próprio teste (não é zelo, é pré-requisito): todo caso de CLI passa
 * `--dir <tmp>` — jamais o staging real `~/Downloads/_cowork-handoff-staging` — e
 * `--no-sync-cowork`, porque o sync escreve com /PURGE em `prototipo-ui/cowork/Wagner/`, que é
 * o SSOT do repo. Um teste que chegasse lá por acidente apagaria a fonte de design.
 */
import { execFileSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, existsSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { acharBundleRoot, classificarParaSync, BUILD_EXTS, NOISE_DIRS, RESIDUO_PATTERN } from './importar-bundle.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(HERE, 'importar-bundle.mjs');

let falhas = 0;
const ok = (cond, nome) => {
  console.log(`  ${cond ? 'ok  ' : 'FALHOU'} ${nome}`);
  if (!cond) falhas++;
};

const novoTmp = (p) => mkdtempSync(join(tmpdir(), p));

/** Marca FORTE = host + app juntos (o que `acharBundleRoot` exige pra cravar a raiz). */
function marcaForte(dir) {
  mkdirSync(dir, { recursive: true });
  writeFileSync(join(dir, 'oimpresso.com.html'), '<html></html>');
  writeFileSync(join(dir, 'app.jsx'), '// app');
  return dir;
}
/** Marca FRACA = só um dos dois. */
function marcaFraca(dir) {
  mkdirSync(dir, { recursive: true });
  writeFileSync(join(dir, 'app.jsx'), '// app');
  return dir;
}

/** Roda `acharBundleRoot` capturando o stderr dele (o aviso é parte do contrato). */
function acharCapturando(destino) {
  const avisos = [];
  const orig = console.error;
  console.error = (...a) => avisos.push(a.map(String).join(' '));
  try { return { raiz: acharBundleRoot(destino), avisos }; } finally { console.error = orig; }
}

/** Roda o CLI DE FORA (subprocesso) — é o ponto do exercício. */
function rodarCli(args) {
  try {
    const out = execFileSync(process.execPath, [SCRIPT, ...args], {
      encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'],
    });
    return { code: 0, out, err: '' };
  } catch (e) {
    return { code: typeof e.status === 'number' ? e.status : -1, out: e.stdout || '', err: e.stderr || '' };
  }
}

/** Staging de mentira com uma sentinela — se ela sumir, o destino foi tocado. */
function stagingComSentinela(prefixo) {
  const dir = novoTmp(prefixo);
  const sentinela = join(dir, 'NAO-APAGUE.txt');
  writeFileSync(sentinela, 'staging antigo');
  return { dir, sentinela };
}
const sentinelaIntacta = (s) => existsSync(s) && readFileSync(s, 'utf8') === 'staging antigo';

console.log('\nimportar-bundle — acharBundleRoot (o bug 2026-07-01 que nunca teve teste)\n');

// A1 — o formato REAL do zip Cowork. Assumir `<destino>/project` aninharia o slug inteiro.
{
  const d = novoTmp('ib-a1-');
  const alvo = marcaForte(join(d, 'Oimpresso ERP', 'project'));
  ok(acharBundleRoot(d) === alvo, 'MORDE: raiz real em <slug>/project — assumir <destino>/project aninha o slug');
}

// A2/A3 — as outras duas topologias aceitas, pra provar que A1 não passou por acidente.
// A2 — `project/` no topo é o caso MAIS SIMPLES, e era onde a lista de candidatos duplicava:
// `join(destino,'project')` e o `project` vindo de `subs.map(...)` são o MESMO path, então
// `fortes` vinha com 2 entradas iguais e o script gritava "raiz ambígua" sem ambiguidade
// nenhuma. O retorno saía certo por sorte; o diagnóstico é que mentia — e aviso que grita
// falso treina o operador a ignorar aviso verdadeiro. Achado por este teste em 2026-09-13.
{
  const d = novoTmp('ib-a2-');
  const alvo = marcaForte(join(d, 'project'));
  const { raiz, avisos } = acharCapturando(d);
  ok(raiz === alvo, 'SOLTA: project/ direto no topo');
  ok(!avisos.some((m) => m.includes('ambígua')), 'MORDE: topo simples NÃO é ambiguidade (candidato duplicado deduplicado)');
}
{
  const d = novoTmp('ib-a3-');
  const alvo = marcaForte(join(d, 'bundle-x'));
  ok(acharBundleRoot(d) === alvo, 'SOLTA: <slug>/ sem project/ dentro');
}

// A4 — ambiguidade. O aviso é determinístico; a ESCOLHA (1º ordenado) só difere do first-wins
// do readdir quando o FS devolve fora de ordem, então é o aviso que carrega a mordida aqui.
{
  const d = novoTmp('ib-a4-');
  marcaForte(join(d, 'zeta', 'project'));
  const esperado = marcaForte(join(d, 'alfa', 'project'));
  const { raiz, avisos } = acharCapturando(d);
  ok(avisos.some((m) => m.includes('ambígua')), 'MORDE: 2 raízes fortes AVISAM em vez de escolher caladas');
  ok(raiz === esperado, 'SOLTA: e a escolhida é a 1ª ORDENADA (determinística, não a que o FS listou antes)');
}

// A5 — marca fraca: cai pro fraco COM aviso, nunca pro destino cru em silêncio.
{
  const d = novoTmp('ib-a5-');
  const alvo = marcaFraca(join(d, 'so-app'));
  const { raiz, avisos } = acharCapturando(d);
  ok(raiz === alvo, 'SOLTA: sem raiz forte, usa a marca fraca (não o destino cru)');
  ok(avisos.some((m) => m.includes('nenhuma raiz FORTE')), 'MORDE: a queda pra marca fraca é declarada');
}

// A6 — nada reconhecível: devolve o destino cru, mas avisa que pode aninhar.
{
  const d = novoTmp('ib-a6-');
  mkdirSync(join(d, 'sem-marca'));
  const { raiz, avisos } = acharCapturando(d);
  ok(raiz === d, 'SOLTA: sem marker nenhum, devolve o destino cru');
  ok(avisos.some((m) => m.includes('nenhum host-marker')), 'MORDE: o destino cru é declarado, não silencioso');
}

console.log('\nimportar-bundle — contrato de PROCESSO (CLI de fora, o que o --selftest não alcança)\n');

// B1 — uso. Exit 2 tem que ser DISTINTO do 1 de integridade, senão o CLI não discrimina (LC-13).
const semArg = rodarCli([]);
ok(semArg.code === 2, 'MORDE: sem zip -> exit 2 (uso)');

// B2 — zip inexistente: falha ANTES de qualquer extração, e o destino não é tocado.
{
  const { dir, sentinela } = stagingComSentinela('ib-b2-');
  const r = rodarCli([join(dir, 'nao-existe.zip'), '--dir', dir, '--no-detect', '--no-sync-cowork']);
  ok(r.code === 1, 'MORDE: zip inexistente -> exit 1');
  ok(sentinelaIntacta(sentinela), 'MORDE: staging antigo PRESERVADO (nada foi trocado)');
  ok(semArg.code !== r.code, 'MORDE: uso(2) e integridade(1) são exits DISTINTOS — o CLI discrimina');
}

// B3 — o caso que o --selftest NÃO cobre: a extração falha e o destino segue intacto.
// Windows: `ZipFile::OpenRead` lança num arquivo que não é zip. Linux: `powershell` é ENOENT.
// Caminhos diferentes, mesmo efeito observável — por isso a asserção é no exit + sentinela.
{
  const { dir, sentinela } = stagingComSentinela('ib-b3-');
  const falsoZip = join(novoTmp('ib-b3z-'), 'bundle.zip');
  writeFileSync(falsoZip, 'isto nao e um zip');
  const r = rodarCli([falsoZip, '--dir', dir, '--no-detect', '--no-sync-cowork']);
  ok(r.code === 1, 'MORDE: extração falha -> exit 1');
  ok(sentinelaIntacta(sentinela), 'MORDE: delete-before-verify FECHADO — destino intacto após falha de extração');
}

console.log('\nimportar-bundle — classificarParaSync (a parte que APAGA o SSOT, antes sem um teste)\n');

// A decisão do sync vivia DENTRO da string heredoc do PowerShell — intestável por construção, e
// nem havia onde rodá-la (`runs-on:.*windows` → 0 runners). Medido em 2026-09-13:
// `git grep -lF robocopy origin/main -- scripts .github` devolvia 1 arquivo, o próprio script.
// Estes casos são PUROS (string → veredito): nenhum toca disco, nenhum chama PowerShell, então
// rodam no ubuntu do CI. A paridade com a PS REAL foi medida à parte, no Windows, rodando os 3
// sweeps de verdade num mkdtemp e comparando sobrevivente × veredito: 35/35 (recibo no PR).
const cls = (p) => classificarParaSync(p).acao;

// C1 — BUILD-ONLY. As 9 extensões sobrevivem; qualquer outra é junk. Varre a lista inteira em vez
// de amostrar uma: allowlist testada por amostra é allowlist meio testada.
{
  const vivas = BUILD_EXTS.filter((e) => cls(`tela/Comp${e}`) === 'copia');
  ok(vivas.length === BUILD_EXTS.length, `SOLTA: as ${BUILD_EXTS.length} extensões de build sobrevivem (${vivas.length}/${BUILD_EXTS.length})`);
}
// Extensão é comparada em minúsculas — a PS faz `.Extension.ToLower()`, então `Comp.JSX` sobrevive.
// Sem isso o sync apagaria arquivo de design legítimo só por causa do caixa do nome.
ok(cls('tela/Comp.JSX') === 'copia', 'MORDE: extensão MAIÚSCULA sobrevive — paridade com .Extension.ToLower() da PS');

// C2 — `.md` é junk, e isso É LEI: o `cowork-ssot-guard.mjs` reprova documentação em cowork/ fora
// do canal de handoff, e o filtro de landing exclui `.md` (ADR-proposta 2026-06-23 §77).
ok(cls('README.md') === 'junk', 'MORDE: .md é junk — cowork/ é BUILD-ONLY (guard reprova doc em cowork/)');
ok(cls('tela/notas.md') === 'junk', 'MORDE: .md aninhado também é junk (o sweep é recursivo)');

// C3 — `.gitignore` sobrevive. É o ÚNICO não-build poupado, e a guarda é pelo NOME porque
// `[IO.Path]::GetExtension('.gitignore')` devolve `.gitignore`, não `''` (medido 2026-09-13) —
// uma guarda por extensão o mataria. Se alguém "simplificar" jogando-o em BUILD_EXTS, o robocopy
// passaria a COPIÁ-LO do bundle, o que é outro comportamento.
ok(cls('.gitignore') === 'copia', 'MORDE: .gitignore sobrevive ao sweep (guarda pelo NOME, não por extensão)');
ok(cls('sub/dir/.gitignore') === 'copia', 'SOLTA: .gitignore aninhado também sobrevive');
ok(!BUILD_EXTS.includes('.gitignore'), 'MORDE: .gitignore NÃO está em BUILD_EXTS (não vem do bundle; é só poupado)');

// C4 — dupe `?v=hash`. A extração sanitiza `?` → `_`, então `app.jsx?v=abc` chega como
// `app.jsx_v=abc` e a extensão fica QUEBRADA (`.jsx_v=abc`), caindo no junk. É o mecanismo que
// tira o canonical-shadow: sem ele o SSOT ganha duas cópias da mesma tela.
ok(cls('app.jsx_v=abc123') === 'junk', 'MORDE: dupe ?v=hash (extensão quebrada) é junk — mata o canonical-shadow');
ok(cls('estilo.css_v=9f1') === 'junk', 'SOLTA: o dupe de .css também');
ok(cls('LICENSE') === 'junk', 'SOLTA: arquivo sem extensão nenhuma é junk');
// Nome com DOIS pontos: a extensão sai do ÚLTIMO, como `[IO.Path]::GetExtension` (medido: `a.b.css`
// → `.css`). Este caso existe porque o harness de mutação flagrou que, sem ele, trocar
// `lastIndexOf` por `indexOf` SOBREVIVIA — e aí `tela.mobile.css` (design legítimo) viraria junk.
ok(cls('tela.mobile.css') === 'copia', 'MORDE: extensão vem do ÚLTIMO ponto — nome com 2 pontos é design, não junk');

// C5 — ruído de DIRETÓRIO. robocopy os exclui (/XD) e o loop seguinte apaga os que sobraram de
// runs anteriores. Vale em qualquer profundidade, porque /XD com nome nu casa em qualquer nível.
for (const d of ['_arquivo', '_ds', 'uploads']) {
  ok(cls(`${d}/qualquer.jsx`) === 'ruido-dir', `MORDE: ${d}/ é ruído de diretório — nem .jsx sobrevive lá`);
}
ok(cls('nested/deep/uploads/x.jsx') === 'ruido-dir', 'MORDE: ruído em QUALQUER profundidade (/XD com nome nu)');
ok(cls('UPLOADS/x.jsx') === 'ruido-dir', 'MORDE: ruído é case-insensitive — paridade com robocopy e com o FS do Windows');
{
  const pegos = NOISE_DIRS.filter((d) => cls(`${d}/a.jsx`) === 'ruido-dir');
  ok(pegos.length === NOISE_DIRS.length, `SOLTA: os ${NOISE_DIRS.length} dirs de ruído são pegos (${pegos.length}/${NOISE_DIRS.length})`);
}

// C6 — resíduo de PROCESSO: `.html` de esteira (audit/tribunal/adversário/FORCE), que passa o
// filtro de extensão e morre no sweep seguinte.
ok(cls('FORCE_reset.html') === 'residuo', 'MORDE: FORCE_*.html é resíduo de processo (passa o build-ext, morre no sweep)');
ok(cls('Tribunal/veredito.html') === 'residuo', 'SOLTA: Tribunal/ também');
ok(cls('Adversario.html') === 'residuo', 'SOLTA: e Adversario (o padrão usa . coringa no acento)');
// O padrão roda case-INSENSITIVE, e isso não é escolha: `-match` da PS é case-insensitive por
// default (medido 2026-09-13 — `'x/tribunal/a.jsx' -match` → True, `-cmatch` → False). Sem a flag
// `i` no JS, `tribunal/` minúsculo escaparia aqui e morreria lá: a função deixaria de reproduzir
// a PS exatamente no eixo que ninguém confere no olho.
ok(cls('tribunal/veredito.html') === 'residuo', 'MORDE: resíduo é case-insensitive (flag i) — paridade com o -match da PS');

// C6-bis — ⚠ DEFEITO REPORTADO, comportamento PINADO. `GAPS_v2.html` NÃO é varrido: o literal
// `GAPS_v\d` vivia num TEMPLATE LITERAL, então a barra COLAPSOU e a PS recebia `GAPS_vd` — letra
// `d` literal (LC-26 · medido em 2026-09-13 reproduzindo a linha de origin/main, e confirmado
// rodando a PS real: GAPS_v2.html SOBREVIVE, GAPS_vd.html morre). Este PR é refactor: preserva o
// comportamento e REPORTA. O assert abaixo é o TRIPWIRE — no dia em que o `\d` for restaurado,
// ele fica vermelho e o conserto se anuncia em vez de passar calado.
ok(cls('GAPS_v2.html') === 'copia', 'PINA (defeito reportado): GAPS_v2.html NÃO é resíduo — o \\d colapsou pra "d" no template literal');
ok(cls('GAPS_vd.html') === 'residuo', 'PINA: o que o padrão efetivo pega é GAPS_vd (letra d), que ninguém nomeia assim');
ok(!RESIDUO_PATTERN.includes('\\'), 'MORDE: RESIDUO_PATTERN é backslash-free — a ausência de barra É a evidência do colapso');

// C7 — ⚠ DEFEITO REPORTADO nº 2. O guard PERMITE `.md` em `cowork/<dono>/handoffs/` (regra R3 tem
// essa exceção), mas o sweep de junk NÃO poupa: os 3 `.md` reais em `cowork/Wagner/handoffs/` de
// origin/main seriam apagados se o sync rodasse. Pinado, não consertado — poupá-los muda o que o
// sync apaga, e mexer nisso sem teste antes é o risco que este PR existe pra reduzir.
ok(cls('handoffs/erros-dedup.md') === 'junk', 'PINA (defeito reportado): handoffs/*.md é apagado, embora o guard R3 o PERMITA');

// C8 — CONTROLE NEGATIVO. Sem ele, uma função que devolvesse 'junk' pra tudo passaria em C2/C4.
ok(cls('tela/Index.jsx') === 'copia', 'CONTROLE NEGATIVO: .jsx comum de design NÃO é tocado');
ok(cls('vendas/_components/Grade.tsx') === 'copia', 'CONTROLE NEGATIVO: .tsx em subpasta comum NÃO é tocado');
ok(cls('tokens/cores.css') === 'copia', 'CONTROLE NEGATIVO: .css de token NÃO é tocado');
ok(classificarParaSync('tela/Index.jsx').motivo.includes('.jsx'), 'SOLTA: o motivo do sobrevivente nomeia a extensão (diagnóstico, não só veredito)');

// C9 — PRECEDÊNCIA = ordem de EXECUÇÃO da PS. Quem morre num sweep nunca chega no próximo, e
// trocar a ordem troca o DIAGNÓSTICO de quem apagou o arquivo.
ok(cls('uploads/README.md') === 'ruido-dir', 'MORDE: ruído-dir vence junk (o dir morre antes do sweep de extensão)');
ok(cls('uploads/FORCE_x.html') === 'ruido-dir', 'MORDE: ruído-dir vence resíduo');
ok(cls('Tribunal/notas.md') === 'junk', 'MORDE: junk vence resíduo (o sweep de extensão roda primeiro)');

// C10 — entrada inválida não vira veredito de arquivo. A PS itera arquivos reais e nunca passa
// por aqui; o guard existe pra não devolver 'copia' silencioso pra lixo de chamada.
for (const ruim of ['', '   ', null, undefined, 42, {}]) {
  ok(classificarParaSync(ruim).acao === 'invalido', `MORDE: entrada inválida (${JSON.stringify(ruim)}) → 'invalido', nunca 'copia'`);
}

// C11 — FONTE ÚNICA. O heredoc PowerShell interpola daqui; se alguém voltar a hardcodar `$keep`
// ou `$resPat` lá dentro, a regra passa a ter duas verdades e drifa. Não dá pra assertar o
// heredoc (é local à função), mas dá pra provar que o fonte NÃO tem mais as listas literais.
{
  const src = readFileSync(join(HERE, 'importar-bundle.mjs'), 'utf8');
  ok(!/\$keep=@\('\.jsx'/.test(src), 'MORDE: $keep não é mais hardcodado no heredoc — vem de BUILD_EXTS');
  ok(!/\$noise = @\('_arquivo'/.test(src), 'MORDE: $noise não é mais hardcodado no heredoc — vem de NOISE_DIRS');
  ok(/\$resPat='\$\{RESIDUO_PATTERN\}'/.test(src), 'MORDE: $resPat vem de RESIDUO_PATTERN (uma verdade só, JS e PS)');
}

console.log(`\n  ${falhas === 0 ? 'OK' : 'FALHAS: ' + falhas}\n`);
if (falhas) process.exit(1);
