#!/usr/bin/env node
// gerar.mjs — monta a página "Casos de uso por tela" a partir do git.
//
// Fontes (nenhuma escrita à mão nesta página):
//   - os pedidos e as decisões dos *.casos.md (leitor.mjs);
//   - as regras de Fluxos do catálogo do código (app/Domain/Fsm/Modelos/CasosDoPrototipo.php).
// A página publicada é derivada: para mudar um caso, edite o casos.md da tela e gere de novo.
//
// Uso:
//   node scripts/casos-backlog/gerar.mjs --out <arquivo.html>   gera a página
//   node scripts/casos-backlog/gerar.mjs --resumo               só imprime as contagens
//   node scripts/casos-backlog/gerar.mjs --json                 resumo + dados, para o teste de paridade
// Sai 1 se um pedido cita regra que não existe ou se uma decisão destrava pedido inexistente.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { lerRepositorio, lerContratos } from './leitor.mjs';

const AQUI = path.dirname(fileURLToPath(import.meta.url));
const RAIZ = path.resolve(AQUI, '..', '..');
const REPO_URL = 'https://github.com/wagnerra23/oimpresso.com/blob/main/';

// Nome e área de cada tela, e o último número de pedido reservado pelo documento de origem
// (Maiara 23/10, Felipe 27/10: P-001 a P-114; Felipe e Maiara 28/10: até P-152, que o documento já
// numerou e ainda não estão todos no git; o próximo livre nunca é menor que P-153). Moram em
// telas.json porque a página dentro do sistema (app/Services/Documentacao/CasosPorTela.php) lê o
// mesmo arquivo.
const MAPA = JSON.parse(fs.readFileSync(path.join(AQUI, 'telas.json'), 'utf8'));
const AREAS = MAPA.areas;
const TELAS = MAPA.telas;
const ULTIMO_RESERVADO = MAPA.ultimoReservado;
const chaveDaTela = arquivo => arquivo.replace(/^.*\/Pages\//, '').replace(/\.casos\.md$/, '');

export function lerRegras(raiz = RAIZ) {
  const php = fs.readFileSync(path.join(raiz, 'app/Domain/Fsm/Modelos/CasosDoPrototipo.php'), 'utf8');
  return [...php.matchAll(/\['(UC-FLX-\d+)', '([^']*)', '([a-z_]+)', '([^']*)', '([^']*)', \[[^\]]*\], '([a-z]+)', '([a-z]+)', '([^']*)'\]/g)]
    .map(m => ({ id: m[1], titulo: m[2], fase: m[3], etapa: m[4], efeito: m[5], porte: m[6], status: m[7], nota: m[8] }));
}

export function montarDados(raiz = RAIZ) {
  const { pedidos, decisoes } = lerRepositorio(raiz);
  const regras = lerRegras(raiz);
  const ids = new Set(regras.map(r => r.id));
  const problemas = [];
  const vazio = { tipo: '', texto: '', nota: '' };
  const casos = pedidos.map(p => {
    const regrasDoCaso = [...new Set(((p.ancora || '') + ' ' + (p.titulo || '')).match(/UC-FLX-\d+/g) || [])];
    regrasDoCaso.filter(r => !ids.has(r)).forEach(r => problemas.push(`${p.id} cita ${r}, que não existe no catálogo`));
    return {
      id: p.id, n: Number(p.id.slice(2)), titulo: p.titulo, marcas: p.marcas || [], texto: p.texto || '', aceite: p.aceite || '',
      fazer: p.fazer || '', ancora: p.ancora || '', hoje: p.hoje || vazio, cv: p.cv || vazio,
      clientes: p.clientes || [], tags: p.tags || [], estagio: p.estagio, uc: p.uc || '',
      tela: chaveDaTela(p.arquivo), arquivo: p.arquivo, linha: p.linha, regras: regrasDoCaso,
    };
  });
  const porId = new Set(casos.map(c => c.id));
  const decs = decisoes.map(d => {
    (d.destrava || []).filter(x => !porId.has(x)).forEach(x => problemas.push(`${d.id} destrava ${x}, que não está no git`));
    return { id: d.id, pergunta: d.pergunta, estado: d.estado || 'Em aberto', texto: d.texto || '', opcoes: d.opcoes || [],
      casos: (d.destrava || []).map(x => Number(x.slice(2))), arquivo: d.arquivo, linha: d.linha };
  });
  const chaves = [...new Set(casos.map(c => c.tela))];
  const telas = chaves.map(k => {
    const [nome, area] = TELAS[k] || [k, 'outras'];
    const arquivo = casos.find(c => c.tela === k).arquivo;
    return { id: k, nome, area, arquivo, url: REPO_URL + arquivo };
  });
  const areas = AREAS.map(([id, nome]) => ({ id, nome, telas: telas.filter(t => t.area === id).map(t => t.id) })).filter(a => a.telas.length);
  // O contrato de todas as telas: os casos de uso com id, agrupados pelo módulo do caminho.
  const moduloDe = arquivo => (arquivo.match(/^Modules\/([^/]+)/) || arquivo.match(/\/Pages\/([^/]+)/) || [, 'Outros'])[1];
  const contratos = lerContratos(raiz).map(t => {
    const id = chaveDaTela(t.arquivo);
    return { id, nome: (TELAS[id] || [id])[0], modulo: moduloDe(t.arquivo), arquivo: t.arquivo, url: REPO_URL + t.arquivo,
      ucs: t.ucs, pedidos: casos.filter(c => c.tela === id).length };
  });
  const proximoPedido = 'P-' + String(Math.max(ULTIMO_RESERVADO, ...casos.map(c => c.n)) + 1).padStart(3, '0');
  return { dados: { geradoEm: new Date().toISOString().slice(0, 10), areas, telas, casos, regras, decisoes: decs, contratos, proximoPedido }, problemas };
}

export function renderizar(dados) {
  const tpl = fs.readFileSync(path.join(AQUI, 'template.html'), 'utf8');
  const MARCA = '/*__DADOS__*/null';
  if (!tpl.includes(MARCA)) throw new Error('template.html sem o marcador de dados');
  // '<' vira o escape u003c do JSON: nenhum texto de caso fecha o <script> que carrega os dados.
  const json = JSON.stringify(dados).split('<').join(String.fromCharCode(92) + 'u003c');
  return tpl.replace(MARCA, () => json);
}

const principal = fileURLToPath(import.meta.url) === path.resolve(process.argv[1] || '');
if (principal) {
  const args = process.argv.slice(2);
  const { dados, problemas } = montarDados();
  const resumo = {
    pedidos: dados.casos.length,
    noBacklog: dados.casos.filter(c => c.estagio === 'pedido').length,
    viraramUC: dados.casos.filter(c => c.estagio !== 'pedido').length,
    testados: dados.casos.filter(c => c.estagio === 'testado').length,
    telas: dados.telas.length, regras: dados.regras.length,
    regrasCitadas: new Set(dados.casos.flatMap(c => c.regras)).size, decisoes: dados.decisoes.length,
    telasComContrato: dados.contratos.length, casosDeUso: dados.contratos.reduce((s, t) => s + t.ucs.length, 0),
    casosDeUsoVerdes: dados.contratos.reduce((s, t) => s + t.ucs.filter(u => u.veredito === 'pass').length, 0),
  };
  if (!args.includes('--json')) {
    console.log(JSON.stringify(resumo));
    for (const p of problemas) console.error('PROBLEMA: ' + p);
  }
  const i = args.indexOf('--out');
  if (args.includes('--json')) {
    // Os dados inteiros, sem a data: é o que o teste de paridade compara com a leitura do PHP.
    const { geradoEm, ...resto } = dados;
    console.log(JSON.stringify({ resumo, ...resto, problemas }));
    process.exit(0);
  }
  if (i >= 0) {
    if (!args[i + 1]) { console.error('--out precisa do caminho do arquivo'); process.exit(2); }
    fs.writeFileSync(args[i + 1], renderizar(dados));
    console.log('página gerada em ' + args[i + 1]);
  } else if (!args.includes('--resumo')) {
    console.error('uso: gerar.mjs --out <arquivo.html> | --resumo');
    process.exit(2);
  }
  process.exit(problemas.length ? 1 : 0);
}
