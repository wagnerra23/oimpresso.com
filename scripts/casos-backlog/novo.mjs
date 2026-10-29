#!/usr/bin/env node
// novo.mjs — registra um pedido de cliente no casos.md da tela, já no formato que a página lê.
//
// Um pedido de cliente (ou do [W]) entra por aqui, não escrito à mão. O comando:
//   - reserva o próximo P-NNN livre (o maior entre telas.json `ultimoReservado` e os do git, + 1);
//   - escreve o bullet de backlog com os subitens Pedido, Âncora, Fazer, Hoje, Comunicação visual,
//     Clientes e Etiquetas, na seção "## Pedidos de clientes" do casos.md (cria a seção se faltar);
//   - acrescenta uma linha na "## Trilha do tempo", quando o arquivo tem trilha;
//   - relê o arquivo com o leitor da página (leitor.mjs) e só grava se o pedido voltar com os campos.
// A página "Casos de uso por tela" (gerar.mjs e app/Services/Documentacao/CasosPorTela.php) mostra o
// pedido no próximo acesso. Dívida técnica (caso sem teste, débito de schema) não entra por aqui:
// continua como bullet de backlog sem Pedido.
//
// Uso:
//   node scripts/casos-backlog/novo.mjs --caso <arquivo.json> [--dry-run]
//   node scripts/casos-backlog/novo.mjs --proximo        só imprime o próximo P-NNN livre
//
// O arquivo .json do caso:
//   { "tela": "Purchase/Create",            caminho em Pages/ ou o próprio .casos.md
//     "titulo": "Título curto do pedido",
//     "marcas": ["V0", "E0"],               opcional: V0 valor, E0 estoque, T0 multi-empresa
//     "texto": "Dado …, Quando …, Então …",
//     "decisao": "o que falta o [W] decidir", opcional
//     "ancora": "US-XXX-NNN · UC-FLX-NN",
//     "fazer": "o que precisa ser construído",
//     "hoje": "part | Em parte | o que existe hoje",        tipo: ok, part, miss ou gray
//     "cv": "ok | Sim | por quê",                           opcional, mesmo vocabulário
//     "clientes": ["Cliente A", "Cliente B"],
//     "etiquetas": ["valor", "part", "cv"],
//     "autor": "[F+C]",                                    opcional, vai na trilha
//     "data": "2026-10-29" }                               opcional, padrão: hoje
//
// O número vem do git que está no disco. Rode com o main atualizado: dois PRs abertos ao mesmo tempo
// podem pegar o mesmo número, e aí a página acusa o repetido (leitor.mjs recusa id em dois lugares).
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { lerArquivo, lerRepositorio } from './leitor.mjs';

const AQUI = path.dirname(fileURLToPath(import.meta.url));
const RAIZ = path.resolve(AQUI, '..', '..');
const SECAO = '## Pedidos de clientes';
const TRILHA = '## Trilha do tempo';
const TIPOS = ['ok', 'part', 'miss', 'gray'];
const MARCAS = ['V0', 'E0', 'T0'];
const LARGURA = 110;

/** O próximo P-NNN livre: nunca abaixo do que o documento de origem já reservou. */
export function proximoPedido(raiz = RAIZ) {
  const mapa = JSON.parse(fs.readFileSync(path.join(raiz, 'scripts/casos-backlog/telas.json'), 'utf8'));
  const { pedidos } = lerRepositorio(raiz);
  const maior = Math.max(mapa.ultimoReservado, ...pedidos.map(p => Number(p.id.slice(2))));
  return 'P-' + String(maior + 1).padStart(3, '0');
}

/** Caminho do casos.md a partir da chave da tela ("Purchase/Create") ou do próprio caminho. */
export function arquivoDaTela(tela, raiz = RAIZ) {
  const candidatos = tela.endsWith('.casos.md') ? [tela] : [`resources/js/Pages/${tela}.casos.md`];
  const achado = candidatos.find(c => fs.existsSync(path.join(raiz, c)));
  if (!achado) throw new Error(`não achei o casos.md da tela "${tela}" (procurei ${candidatos.join(', ')}). Tela nova nasce com o trio: node scripts/governance/criar-tela.mjs`);
  return achado;
}

/** Valida o caso e devolve a lista de erros (vazia = ok). */
export function validar(c) {
  const erros = [];
  for (const k of ['tela', 'titulo', 'texto', 'ancora', 'fazer', 'hoje']) if (!c[k] || !String(c[k]).trim()) erros.push(`falta "${k}"`);
  if (!Array.isArray(c.clientes) || !c.clientes.length) erros.push('falta "clientes": pedido de cliente sem cliente é dívida técnica, não entra por aqui');
  if (c.etiquetas !== undefined && !Array.isArray(c.etiquetas)) erros.push('"etiquetas" é uma lista');
  for (const m of c.marcas || []) if (!MARCAS.includes(m)) erros.push(`marca "${m}" não existe (use ${MARCAS.join(', ')})`);
  for (const k of ['hoje', 'cv']) {
    if (!c[k]) continue;
    const tipo = String(c[k]).split(' | ')[0].trim();
    if (!TIPOS.includes(tipo)) erros.push(`"${k}" começa pelo tipo (${TIPOS.join(', ')}), veio "${tipo}"`);
    if (String(c[k]).split(' | ').length < 2) erros.push(`"${k}" tem a forma "tipo | rótulo | nota"`);
  }
  if (c.data && !/^\d{4}-\d{2}-\d{2}$/.test(c.data)) erros.push('"data" é AAAA-MM-DD');
  // Valor de cliente não entra no git (proibicoes.md: nunca commitar valor em R$).
  for (const [k, v] of Object.entries(c)) if (/R\$\s?\d/.test(JSON.stringify(v))) erros.push(`"${k}" tem valor em R$; escreva sem o valor`);
  return erros;
}

/** Quebra um parágrafo em linhas de até LARGURA, a primeira com `primeira` e as outras com 2 espaços. */
function quebrar(texto, primeira) {
  const linhas = [];
  let atual = primeira;
  for (const palavra of texto.split(/\s+/).filter(Boolean)) {
    const vazio = atual === primeira || atual === '  ';
    const tentativa = vazio ? atual + palavra : atual + ' ' + palavra;
    if (tentativa.length > LARGURA && !vazio) { linhas.push(atual); atual = '  ' + palavra; } else atual = tentativa;
  }
  linhas.push(atual);
  return linhas;
}

/** O bullet de backlog com os subitens, no formato do leitor. */
export function montarBullet(c, id) {
  const marcas = (c.marcas || []).map(m => ` \`[${m}]\``).join('');
  const decisao = c.decisao ? ` Falta decisão [W]: ${c.decisao.trim().replace(/\.?$/, '.')}` : '';
  const regra = (c.marcas || []).some(m => m === 'V0' || m === 'E0') ? ' Mexe em valor ou estoque: Regra Mestre.' : '';
  const corpo = `${c.texto.trim()} Pedido de ${c.clientes.join(', ')}.${decisao}${regra}`;
  const linhas = quebrar(corpo, `- \`[BACKLOG]\` **${c.titulo.trim()}**${marcas} — `);
  linhas.push(`  - Pedido: ${id}`, `  - Âncora: ${c.ancora.trim()}`, `  - Fazer: ${c.fazer.trim()}`, `  - Hoje: ${c.hoje.trim()}`);
  if (c.cv) linhas.push(`  - Comunicação visual: ${c.cv.trim()}`);
  linhas.push(`  - Clientes: ${c.clientes.join(', ')}`);
  if (c.etiquetas?.length) linhas.push(`  - Etiquetas: ${c.etiquetas.join(', ')}`);
  return linhas.join('\n');
}

/** Fim da seção que começa na linha `i` (índice da próxima linha `## ` ou o fim do arquivo). */
function fimDaSecao(L, i) {
  let j = i + 1;
  while (j < L.length && !/^#{1,2} /.test(L[j])) j++;
  // Recua as linhas em branco do fim da seção, para inserir logo depois do último conteúdo.
  while (j - 1 > i && L[j - 1].trim() === '') j--;
  return j;
}

/** Insere o bullet na seção de pedidos (cria se faltar) e a linha na trilha (se o arquivo tem trilha). */
export function inserir(md, bullet, linhaTrilha) {
  const L = md.replace(/\n+$/, '').split('\n');
  const s = L.findIndex(l => l.trim() === SECAO);
  if (s < 0) {
    const bloco = [SECAO, '',
      '> Pedidos de clientes registrados com `node scripts/casos-backlog/novo.mjs`. Cada um aparece na',
      '> página "Casos de uso por tela"; quando ganhar teste, vira caso de uso com id e a linha `Pedido de origem`.',
      '', bullet, ''];
    const t = L.findIndex(l => l.trim() === TRILHA);
    if (t >= 0) L.splice(t, 0, ...bloco); else L.push('', ...bloco.slice(0, -1));
  } else {
    L.splice(fimDaSecao(L, s), 0, '', bullet);
  }
  const t = L.findIndex(l => l.trim() === TRILHA);
  if (t >= 0 && linhaTrilha) L.splice(fimDaSecao(L, t), 0, linhaTrilha);
  return L.join('\n') + '\n';
}

export function registrar(c, { raiz = RAIZ, gravar = true } = {}) {
  const erros = validar(c);
  if (erros.length) throw new Error('caso inválido:\n  - ' + erros.join('\n  - '));
  const rel = arquivoDaTela(c.tela, raiz);
  const id = proximoPedido(raiz);
  const data = c.data || new Date().toISOString().slice(0, 10);
  const trilha = `- ${data} · ${c.autor || '[C]'} Pedido ${id} "${c.titulo.trim()}" (${c.clientes.join(', ')}), registrado com casos-backlog/novo.mjs. Sem id de UC.`;
  const antes = fs.readFileSync(path.join(raiz, rel), 'utf8');
  const depois = inserir(antes, montarBullet(c, id), trilha);
  // Confere com o leitor da página antes de gravar: o pedido tem de voltar, com os campos.
  const lido = lerArquivo(depois, rel).pedidos.find(p => p.id === id);
  if (!lido) throw new Error(`o leitor da página não achou ${id} no texto gerado; nada foi gravado`);
  if (lido.titulo !== c.titulo.trim().replace(/\*\*/g, '')) throw new Error(`o leitor leu o título "${lido.titulo}"; nada foi gravado`);
  if (!lido.hoje || !lido.clientes?.length) throw new Error(`o leitor não leu Hoje/Clientes de ${id}; nada foi gravado`);
  const antesPedidos = lerArquivo(antes, rel).pedidos.map(p => p.id).join();
  const depoisPedidos = lerArquivo(depois, rel).pedidos.map(p => p.id).filter(x => x !== id).join();
  if (antesPedidos !== depoisPedidos) throw new Error('a inserção mudou os outros pedidos do arquivo; nada foi gravado');
  if (gravar) fs.writeFileSync(path.join(raiz, rel), depois);
  return { id, arquivo: rel, texto: depois };
}

const principal = fileURLToPath(import.meta.url) === path.resolve(process.argv[1] || '');
if (principal) {
  const args = process.argv.slice(2);
  try {
    if (args.includes('--proximo')) { console.log(proximoPedido()); process.exit(0); }
    const i = args.indexOf('--caso');
    if (i < 0 || !args[i + 1]) { console.error('uso: novo.mjs --caso <arquivo.json> [--dry-run] | --proximo'); process.exit(2); }
    const caso = JSON.parse(fs.readFileSync(args[i + 1], 'utf8'));
    const seco = args.includes('--dry-run');
    const r = registrar(caso, { gravar: !seco });
    if (seco) { console.log(r.texto); console.error(`(dry-run) ${r.id} iria para ${r.arquivo}`); }
    else console.log(`${r.id} registrado em ${r.arquivo}. Ele aparece na página "Casos de uso por tela" depois do merge.`);
  } catch (e) {
    console.error(e.message);
    process.exit(1);
  }
}
