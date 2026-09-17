#!/usr/bin/env node
// placar-indice.mjs — PR-A8: o PLACAR DA LISTA. "O Code terminou a lista inteira?"
//
// Doc: prototipo-ui/cowork/Wagner/COLAR-NO-CODE-AUTOMACAO-DO-PROTOCOLO.md (PR-A8)
// Entrada: scripts/qa/placar.mjs --indice (a flag mora lá, como o A8 pede; a lógica aqui,
//          que é o destino que a própria ponte original sugeria em 2026-09-06)
// Bite-test: scripts/qa/placar-indice.test.mjs (exercita o CLI de FORA — §5 2026-07-30)
//
// O A6 mede UMA tela (seções entregues ÷ alvo). Este eixo mede UM MÓDULO: as threads de um
// playbook. O estado de cada thread é DERIVADO do repo — ninguém escreve estado (Lei 2).
//
// ── DE ONDE VEIO, E POR QUE NÃO FOI COLADO DE CACHE ────────────────────────────────────
// O plano manda ler a ponte `cowork-inbox/_scripts/placar-indice.mjs` no `main`. Medido em
// 2026-09-17: ela NÃO está no main. A ADR 0397 (#7224, 2026-09-11) removeu a árvore
// `prototipo-ui/design-docs/**` inteira — a D5 nomeia `design-docs/` entre os cemitérios
// inválidos, a D3 tira máquina de dentro de `prototipo-ui/`, a D1 proíbe pasta fora de
// `cowork/` + `design-system/`. A lógica foi lida do GIT (`4f51a9ec781^`), que é o cemitério
// que a própria D5 declara VÁLIDO, e reescrita aqui — o endereço que a D3 designa.
// Recibo: memory/reference/prototipo-ui/CODE_NOTES.errata-pr-a8-fonte-removida-pela-0397-2026-09-17.md
//
// ── A FONTE É O BLOCO json DENTRO DO 00-INDICE.md, NÃO UM playbook.json ───────────────
// O plano descreve `<mod>/playbook/playbook.json`. Esse formato é ANTIGO — a própria ponte já
// o havia superado, e o motivo está no comentário dela: "só .md roteia pelo DesignSync, então
// .json solto não chega". Medido 2026-09-17: ZERO `playbook.json` no repo, 12 `00-INDICE.md`
// vivos. Um `.json` paralelo seria o segundo dono do estado que o §0 do índice proíbe.
//
// ── O QUE ESTE EIXO MEDE, E O QUE ELE **NÃO** MEDE (pra verde não mentir) ─────────────
// Provas ESTRUTURAIS (existe · não existe · contém · não contém · chaves de JSON · um de N)
// são avaliadas aqui: 85 das 114 provas dos 12 índices vivos (74,6%, medido 2026-09-17 com
// `git grep -hoE '"tipo"...' -- '<inbox>/*/playbook/00-INDICE.md' | sort | uniq -c`).
// Provas de RECIBO (`execucao` · `comparacao` · `revisao` · `runtime` · `medicao`, 29) exigem
// o avaliador de recibo: Ajv + o schema + hash SHA-256 + `junit-summary` + o comparador — 4
// arquivos removidos junto, e o comparador ainda aponta pra um path que a 0397 moveu. Elas
// saem **NÃO MEDIDA**, nunca "falhou" e nunca "ok": colapsar não-medição num estado do objeto
// é LC-33 nas duas direções (§5 2026-07-29 fail-open · §5 2026-09-03 acusação).
// Duas consequências deliberadas, e as duas são ditas na saída, não escondidas:
//   fail-closed  thread com prova não medida NÃO pode ficar `feito`;
//   sem acusar   `--check` NÃO morde por ela — reprovar por não-medição é acusar o inocente.

import { readFileSync, readdirSync, existsSync } from 'node:fs';
import { join } from 'node:path';

/** Erro de NÃO-MEDIÇÃO. O campo `naoMedi` é o contrato com o placar.mjs, que mapeia pra exit 2
 *  — "não medi" nunca pode se passar por "está são" nem por "falhou" (§5 2026-07-29). */
export class NaoMedi extends Error {
  constructor(msg) { super(msg); this.naoMedi = true; }
}

/** Tipos avaliáveis sem recibo. Fora desta lista = NÃO MEDIDA, jamais "falhou". */
export const TIPOS_ESTRUTURAIS = ['arquivo', 'ausente', 'contem', 'nao_contem', 'json_com_chaves', 'um_de'];
export const ESTADOS = ['feito', 'em curso', 'proximo', 'pendente', 'bloqueada'];
export const BASE_INBOX = 'prototipo-ui/cowork/Wagner/cowork-inbox';

/** Path de dentro do repo: sem raiz absoluta, sem drive do Windows, sem subir com `..`. */
export const pathSeguro = (p) => typeof p === 'string' && p.length > 0
  && !/^[\\/]/.test(p) && !/^[A-Za-z]:/.test(p) && !p.split('/').includes('..');

// ── ENDEREÇOS QUE A ADR 0397 APOSENTOU (#7224, 2026-09-11) ─────────────────────────────
// Os playbooks chegaram ao repo em 14-16/09 pelo import da árvore da conta (#7256, #7422,
// #7445) carregando `prova.path` do mundo PRÉ-0397. Medido 2026-09-17 nos 12 índices vivos:
// 13 paths distintos apontam pra endereços que aquela ADR removeu — e o placar respondia
// "arquivo ausente" para todos. Isso NÃO é "a thread não entregou": é o instrumento
// perguntando no endereço errado e colapsando não-medição em ACUSAÇÃO (LC-33, a direção
// que este mesmo docblock diz combater). O caso puro é `Fiscal/03`, cujo `_saida-03.md`
// EXISTE — só que em `cowork/Wagner/cowork-inbox/`, não no `design-docs/` que a prova cita.
//
// Cada regra abaixo é ESTRUTURAL — sai de um item da decisão, não de palpite. A D6 proíbe
// "heurística de basename para trocar dono, subdiretório ou âncora", e nenhuma delas o faz:
// são prefixos que a D1 declara inadmissíveis, com sucessor único fixado pela própria ADR.
// O que NÃO tem sucessor estrutural não é adivinhado — vira NÃO MEDIDA (abaixo).
export const ENDERECOS_APOSENTADOS = [
  // D3 — máquinas de inspeção/importação/comparação vivem em `scripts/design/`.
  { re: /^prototipo-ui\/([^/]+\.mjs)$/, para: 'scripts/design/$1', regra: 'ADR 0397 D3 (máquinas)' },
  // D3 — contratos, alvos e mapas máquina-legíveis vivem em `governance/design/`.
  { re: /^prototipo-ui\/contrato\//, para: 'governance/design/contracts/', regra: 'ADR 0397 D3 (contratos)' },
  // D2 — a procedência é parte do endereço: material Wagner vive sob `cowork/Wagner/`.
  //      D1 não admite arquivo solto em `cowork/`, então sobrar ali é sempre pré-0397.
  { re: /^prototipo-ui\/cowork\/(?!Wagner\/|Felipe\/)/, para: 'prototipo-ui/cowork/Wagner/', regra: 'ADR 0397 D2 (dono no endereço)' },
  // D5 — `design-docs/` não é cemitério válido; a árvore `cowork-inbox/` migrou inteira.
  { re: /^prototipo-ui\/design-docs\/cowork-inbox\//, para: 'prototipo-ui/cowork/Wagner/cowork-inbox/', regra: 'ADR 0397 D5 (cowork-inbox)' },
];

// D5 aposentou `design-docs/` INTEIRO, mas só o `cowork-inbox/` tem sucessor fixado pela ADR.
// Para o resto não se inventa destino: medido 2026-09-17, `design-docs/contrato-cowork/
// governance.contract.json` (blob 144d03c5) NÃO é o `cowork-inbox/governance/
// governance.contract.json` de hoje (blob 156ff30a) — bytes diferentes. Casá-los pelo
// basename seria exatamente a heurística que a D6 proíbe. Sem sucessor ⇒ NÃO MEDIDA.
export const APOSENTADO_SEM_SUCESSOR = /^prototipo-ui\/(design-docs|_arquivo)\//;

/** Aplica os endereços da ADR 0397. Devolve `{ path, migrado?, semSucessor? }`. */
export function migrarEndereco(p) {
  for (const r of ENDERECOS_APOSENTADOS) {
    if (r.re.test(p)) return { path: p.replace(r.re, r.para), migrado: { de: p, regra: r.regra } };
  }
  if (APOSENTADO_SEM_SUCESSOR.test(p)) return { path: p, semSucessor: true };
  return { path: p };
}

export function descobrirIndices(root) {
  const base = join(root, ...BASE_INBOX.split('/'));
  if (!existsSync(base)) return [];
  return readdirSync(base, { withFileTypes: true })
    .filter((d) => d.isDirectory())
    .map((d) => `${BASE_INBOX}/${d.name}/playbook/00-INDICE.md`)
    .filter((p) => existsSync(join(root, p)))
    .sort();
}

/** `${VAR}` que [W] não decidiu vira `indefinida` — nunca path chutado. */
export function resolverPath(p, variaveis = {}) {
  let indefinida = false;
  const out = String(p ?? '').replace(/\$\{([A-Z0-9_]+)\}/g, (_, k) => {
    const v = variaveis[k];
    if (v === null || v === undefined) { indefinida = true; return '${' + k + '}'; }
    return String(v).replace(/\/$/, '');
  });
  if (!indefinida && !pathSeguro(out)) throw new NaoMedi(`path resolvido fora do repositório: ${out}`);
  if (indefinida) return { path: out, indefinida };
  // Endereço pré-0397 é medido no endereço NOVO — e o relato DIZ que migrou, senão a dívida
  // de dado da fonte (que é do Cowork, não nossa) some de vista e nunca é corrigida.
  return { ...migrarEndereco(out), indefinida: false };
}

export function avaliarProva(prova, ctx) {
  const tipo = prova && prova.tipo;
  if (!TIPOS_ESTRUTURAIS.includes(tipo)) {
    return { ok: false, naoMedida: true, path: (prova && prova.path) || '', motivo: `prova "${tipo}" precisa do avaliador de recibo (não portado — ver docblock)` };
  }
  if (tipo === 'um_de') {
    const alts = (prova.paths || []).map((p) => resolverPath(p, ctx.variaveis));
    if (!alts.length) return { ok: false, path: '', motivo: 'um_de sem "paths"' };
    if (alts.some((a) => a.indefinida)) return { ok: false, indefinida: true, path: alts[0].path, motivo: 'variável não decidida' };
    const migs = alts.filter((a) => a.migrado).map((a) => a.migrado);
    const achado = alts.find((a) => ctx.existe(a.path));
    return { ok: !!achado, path: achado ? achado.path : alts[0].path, migrado: migs[0] || undefined,
      motivo: achado ? '' : `nenhum de ${alts.map((a) => a.path).join(' | ')}` };
  }
  const { path, indefinida, migrado, semSucessor } = resolverPath(prova.path ?? '', ctx.variaveis);
  if (indefinida) return { ok: false, indefinida: true, path, motivo: 'variável não decidida' };
  // Endereço que a ADR 0397 aposentou SEM sucessor fixado por ela: não dá pra medir e não se
  // inventa destino. Sai NÃO MEDIDA — nunca "ausente", que acusaria a thread pelo dado podre.
  if (semSucessor) {
    return { ok: false, naoMedida: true, path, migrado,
      motivo: 'endereço aposentado pela ADR 0397 D5 e sem sucessor fixado — corrigir a prova na fonte (Cowork)' };
  }
  const existe = ctx.existe(path);
  // `migrado` acompanha o veredito de todos os ramos: a prova é medida no endereço NOVO, e
  // quem lê o relato precisa saber que a FONTE ainda cita o velho (senão a dívida fica muda).
  return { ...avaliarNoEndereco(tipo, prova, ctx, path, existe), migrado };
}

function avaliarNoEndereco(tipo, prova, ctx, path, existe) {
  switch (tipo) {
    case 'arquivo': return { ok: existe, path, motivo: existe ? '' : 'arquivo ausente' };
    case 'ausente': return { ok: !existe, path, motivo: existe ? 'arquivo ainda existe' : '' };
    case 'contem': case 'nao_contem': {
      if (!existe) return { ok: false, path, motivo: 'arquivo ausente' };
      const tem = ctx.ler(path).includes(prova.padrao);
      const ok = tipo === 'contem' ? tem : !tem;
      return { ok, path, motivo: ok ? '' : (tipo === 'contem' ? `não contém "${prova.padrao}"` : `ainda contém "${prova.padrao}"`) };
    }
    case 'json_com_chaves': {
      if (!existe) return { ok: false, path, motivo: 'arquivo ausente' };
      let j;
      try { j = JSON.parse(ctx.ler(path)); } catch { return { ok: false, path, motivo: 'JSON inválido' }; }
      if (!j || typeof j !== 'object' || Array.isArray(j)) return { ok: false, path, motivo: 'JSON precisa ser objeto' };
      const faltam = (prova.chaves || []).filter((k) => !(k in j));
      return { ok: !faltam.length, path, motivo: faltam.length ? `faltam chaves ${faltam.join(', ')}` : '' };
    }
    default: return { ok: false, path, motivo: `tipo desconhecido ${tipo}` };
  }
}

/** Integridade do índice. Sem Ajv de propósito: o schema saiu do repo com a ADR 0397, e validar
 *  contra um schema inexistente seria pior que não validar. Aqui fica o que o placar precisa
 *  pra não mentir — id duplicado, dependência fantasma, ciclo, variável fora do repo. */
export function validarIndice(indice) {
  if (!indice || typeof indice !== 'object') throw new NaoMedi('índice não é objeto');
  if (!Array.isArray(indice.threads) || !indice.threads.length) throw new NaoMedi('índice sem threads — "0 de 0" não é placar');
  const ids = new Set(), decisoes = new Set();
  for (const d of indice.decisoes || []) {
    if (decisoes.has(d.id)) throw new NaoMedi(`decisão duplicada: ${d.id}`);
    decisoes.add(d.id);
  }
  for (const [k, v] of Object.entries(indice.variaveis || {})) {
    if (v !== null && v !== undefined && !pathSeguro(v)) throw new NaoMedi(`variável ${k} fora do repositório: ${v}`);
  }
  for (const t of indice.threads) {
    if (!t || !t.id) throw new NaoMedi('thread sem id');
    if (ids.has(t.id)) throw new NaoMedi(`thread duplicada: ${t.id}`);
    ids.add(t.id);
    if (!Array.isArray(t.provas)) throw new NaoMedi(`thread ${t.id} sem "provas"`);
  }
  const visitados = new Set(), pilha = new Set();
  const visitar = (id) => {
    if (!ids.has(id)) throw new NaoMedi(`dependência inexistente: ${id}`);
    if (pilha.has(id)) throw new NaoMedi(`dependência circular: ${id}`);
    if (visitados.has(id)) return;
    pilha.add(id);
    const t = indice.threads.find((x) => x.id === id);
    for (const d of t.depende_decisoes || []) if (!decisoes.has(d)) throw new NaoMedi(`decisão inexistente: ${d}`);
    for (const dep of t.depende_threads || []) visitar(dep);
    pilha.delete(id);
    visitados.add(id);
  };
  for (const id of ids) visitar(id);
}

/** Estado DERIVADO. `_saida-NN.md` é a prova implícita da Lei 2: sem ela, nada é `feito`. */
export function avaliarIndice(indice, ctx) {
  validarIndice(indice);
  const dir = ctx.dirPlaybook;
  const variaveis = indice.variaveis || {};
  const decis = Object.fromEntries((indice.decisoes || []).map((d) => [d.id, d]));
  const byId = {};

  const linhas = indice.threads.map((t) => {
    const provas = t.provas.map((p) => ({ tipo: p.tipo, ...avaliarProva(p, { ...ctx, variaveis }) }));
    const saida = ctx.existe(`${dir}/_saida-${t.id}.md`);
    const naoMedidas = provas.filter((p) => p.naoMedida);
    const falhas = provas.filter((p) => !p.ok && !p.naoMedida);
    const decisPend = (t.depende_decisoes || []).filter((id) => !(decis[id] && decis[id].respondida));
    const l = {
      id: t.id, titulo: t.titulo || '', dono: t.dono || '?', saida,
      estado: t.bloqueio ? 'bloqueada' : (saida ? 'em curso' : 'pendente'),
      provas, decisPend, depende_threads: t.depende_threads || [],
      naoMedidas: naoMedidas.length, executavel: false,
      migrados: provas.filter((p) => p.migrado).length,
      // fail-closed: prova não medida impede `feito` — e o motivo aparece em `ausentes`.
      pronto: saida && !falhas.length && !naoMedidas.length,
      ausentes: [...falhas, ...naoMedidas].map((p) => `${p.path} (${p.motivo})`),
    };
    byId[t.id] = l;
    return l;
  });

  // Ordem topológica: `feito` também depende das predecessoras estarem `feito`.
  const resolvidas = new Set();
  const resolver = (l) => {
    if (resolvidas.has(l.id)) return;
    resolvidas.add(l.id);                       // marca ANTES: validarIndice já barrou ciclo
    for (const id of l.depende_threads) resolver(byId[id]);
    if (l.estado !== 'bloqueada' && l.pronto && !l.decisPend.length
      && l.depende_threads.every((id) => byId[id].estado === 'feito')) l.estado = 'feito';
  };
  for (const l of linhas) resolver(l);

  for (const l of linhas) {
    if (l.estado === 'bloqueada' || l.estado === 'feito') continue;
    l.executavel = l.depende_threads.every((id) => byId[id].estado === 'feito')
      && !l.decisPend.length && !l.provas.some((p) => p.indefinida);
    if (l.executavel && l.estado === 'pendente') l.estado = 'proximo';
    // INDECIDÍVEL: tudo que dá pra medir está verde e só a prova de recibo impede o `feito`.
    // Ela fica fora do `fecha` — reprovar aqui seria acusar por falta de instrumento (LC-33),
    // e dar como entregue seria fail-open. Nem uma coisa nem outra: fica DITO e à parte.
    l.indecidivel = l.naoMedidas > 0 && l.saida && !l.decisPend.length
      && !l.provas.some((p) => !p.ok && !p.naoMedida)
      && l.depende_threads.every((id) => byId[id].estado === 'feito' || byId[id].indecidivel);
  }

  const cont = Object.fromEntries(ESTADOS.map((e) => [e, linhas.filter((l) => l.estado === e).length]));
  const motivo = (l) => `${l.saida ? '' : 'sem _saida; '}${l.decisPend.length ? 'decisão pendente ' + l.decisPend.join(',') + '; ' : ''}${l.ausentes.join('; ')}`.replace(/; $/, '');
  return {
    modulo: indice.modulo || '(sem módulo)', sha: indice.sha || null,
    total: linhas.length, feito: cont.feito, cont, linhas, motivo,
    naoMedidas: linhas.reduce((n, l) => n + l.naoMedidas, 0),
    migrados: linhas.reduce((n, l) => n + l.migrados, 0),
    indecidiveis: linhas.filter((l) => l.indecidivel).length,
    // `fecha` do módulo: entregue, bloqueada (decisão declarada) ou indecidível (sem
    // instrumento) — o resto é dívida medida, e é só por ela que o --check morde.
    fecha: linhas.every((l) => l.estado === 'feito' || l.estado === 'bloqueada' || l.indecidivel),
    falhas: linhas.filter((l) => l.estado !== 'feito' && l.estado !== 'bloqueada')
      .map((l) => `${l.id} ${l.titulo} — ${motivo(l)}`),
  };
}

/** Lê o PRIMEIRO bloco json embutido no 00-INDICE.md (a fonte — ver docblock). */
export function lerIndice(root, rel) {
  let bruto;
  try { bruto = readFileSync(join(root, rel), 'utf8'); }
  catch (e) { throw new NaoMedi(`${rel}: ${e.message.slice(0, 120)}`); }
  const m = bruto.match(/```json\s*\r?\n([\s\S]*?)\r?\n```/);
  if (!m) throw new NaoMedi(`${rel}: sem bloco json embutido (a fonte é o bloco do §0, não um playbook.json)`);
  try { return JSON.parse(m[1]); }
  catch (e) { throw new NaoMedi(`${rel}: bloco json inválido — ${e.message.slice(0, 100)}`); }
}

export function agregarIndices(root, alvos, { thread = null } = {}) {
  // Corpus vazio NÃO é 100%: "entregue 0 de 0" é o verde vazio clássico (§5 2026-08-04).
  if (!alvos.length) throw new NaoMedi('nenhum índice selecionado — use --indice <00-INDICE.md> ou --todos');
  const modulos = alvos.map((rel) => {
    const r = avaliarIndice(lerIndice(root, rel), {
      dirPlaybook: rel.split('/').slice(0, -1).join('/'),
      existe: (p) => existsSync(join(root, p)),
      ler: (p) => readFileSync(join(root, p), 'utf8'),
    });
    r.playbook = rel;
    return r;
  });
  // `--thread NN` (A8 × A7): recorta UMA thread pra sessão limpa do `/onda` nascer lida. O
  // recorte é do RELATO, nunca da avaliação — o estado de uma thread depende das dependências
  // dela, então avaliar o índice inteiro e só então filtrar é o que mantém `feito` honesto.
  if (thread) {
    for (const m of modulos) {
      m.linhas = m.linhas.filter((l) => l.id === thread);
      m.total = m.linhas.length;
      m.feito = m.linhas.filter((l) => l.estado === 'feito').length;
      m.naoMedidas = m.linhas.reduce((n, l) => n + l.naoMedidas, 0);
      m.migrados = m.linhas.reduce((n, l) => n + l.migrados, 0);
      m.indecidiveis = m.linhas.filter((l) => l.indecidivel).length;
      m.fecha = m.linhas.every((l) => l.estado === 'feito' || l.estado === 'bloqueada' || l.indecidivel);
      m.falhas = m.linhas.filter((l) => l.estado !== 'feito' && l.estado !== 'bloqueada').map((l) => `${l.id} ${l.titulo} — ${m.motivo(l)}`);
      m.cont = Object.fromEntries(ESTADOS.map((e) => [e, m.linhas.filter((l) => l.estado === e).length]));
    }
    if (!modulos.some((m) => m.linhas.length)) throw new NaoMedi(`thread "${thread}" não existe em ${alvos.join(', ')}`);
  }
  const somaFeito = modulos.reduce((n, m) => n + m.feito, 0);
  const somaTotal = modulos.reduce((n, m) => n + m.total, 0);
  return {
    modulos, somaFeito, somaTotal,
    cobertura: somaTotal ? Math.round((somaFeito / somaTotal) * 1000) / 10 : 0,
    naoMedidas: modulos.reduce((n, m) => n + m.naoMedidas, 0),
    migrados: modulos.reduce((n, m) => n + m.migrados, 0),
    proximos: modulos.flatMap((m) => m.linhas.filter((l) => l.executavel).map((l) => ({ modulo: m.modulo, ...l }))),
    indecidiveis: modulos.reduce((n, m) => n + m.indecidiveis, 0),
    // Cada módulo já decide o próprio `fecha` (entregue · bloqueada · indecidível).
    fecha: modulos.every((m) => m.fecha),
  };
}

/* ── saídas (mesmo formato do A6: `entregue X de Y · ausentes <n> por <motivo>`) ───────── */

/** Corta a lista e DIZ quantas ficaram de fora — truncar em silêncio faria o comentário
 *  mentir por omissão, que é o mesmo defeito do relato que some com a seção não entregue. */
const cortar = (itens, n, rotulo) => itens.length <= n
  ? itens.join(' · ')
  : `${itens.slice(0, n).join(' · ')} · _+${itens.length - n} ${rotulo}_`;

export function emitirMdIndice(r) {
  const L = ['<!-- placar-de-indice -->', '## Placar da lista — PR-A8', ''];
  for (const m of r.modulos) {
    // Uma linha por módulo + as ausentes em sub-lista. Antes isto concatenava tudo numa linha
    // só: 2.759 chars, ilegível — e comentário que ninguém lê equivale a não comentar.
    L.push(`- **${m.modulo}** · entregue ${m.feito} de ${m.total}${m.indecidiveis ? ` · ${m.indecidiveis} indecidível(is)` : ''}`);
    for (const f of m.falhas.slice(0, 5)) {
      const [cabeca, ...resto] = f.split(' — ');
      L.push(`  - \`${cabeca}\` — ${(resto.join(' — ') || 'pendente').slice(0, 180)}`);
    }
    if (m.falhas.length > 5) L.push(`  - _+${m.falhas.length - 5} thread(s) — veja \`npm run placar:lista\`_`);
  }
  L.push('', `**Cobertura cumulativa:** ${r.somaFeito} de ${r.somaTotal} (${r.cobertura}%)`);
  L.push('', `**PRÓXIMO:** ${r.proximos.length ? cortar(r.proximos.map((p) => `\`${p.modulo}/${p.id}\` ${p.titulo} [${p.dono}]`), 8, 'executáveis') : '— nenhuma executável (ver dependências e decisões acima)'}`);
  if (r.naoMedidas) {
    L.push('', `**⚠️ Não medidas:** ${r.naoMedidas} prova(s) de recibo — o avaliador saiu do repo com a ADR 0397. Thread com prova não medida NÃO conta como entregue, e o \`--check\` não morde por ela.`);
  }
  if (r.migrados) {
    L.push('', `**📍 Fonte desatualizada:** ${r.migrados} prova(s) citam endereços que a ADR 0397 (#7224) aposentou — foram medidas no endereço NOVO, e por isso o veredito acima é honesto. A correção pertence à FONTE (playbook do Cowork): \`prototipo-ui/cowork/Wagner/**\` é espelho de leitura (ADR 0374) e o import sincroniza com \`/PURGE\`, então editar aqui seria desfeito no próximo pacote.`);
  }
  L.push('', '<sub>Estado é DERIVADO das provas + `_saida-NN.md` (Lei 2) — ninguém escreve estado. Detalhe completo: `npm run placar:lista`.</sub>');
  return L.join('\n');
}

export function emitirTextoIndice(r, { proximo = false } = {}) {
  for (const m of r.modulos) {
    console.log(`${m.modulo}: entregue ${m.feito} de ${m.total} · próximo ${m.cont.proximo} · em curso ${m.cont['em curso']} · pendente ${m.cont.pendente} · bloqueada ${m.cont.bloqueada}`);
    for (const l of m.linhas) {
      // O motivo é o MESMO texto do `falhas`/`--md`: decisão pendente + _saida + provas.
      // Antes esta linha só mostrava `ausentes[0]`, e decisão pendente saía muda — reprovação
      // sem motivo legível é meio caminho pro gate que ninguém entende e todos ignoram.
      const cauda = (l.estado === 'feito' || l.estado === 'bloqueada') ? '' : ' — ' + (m.motivo(l) || 'sem pendência legível');
      console.log(`  ${l.id} [${l.estado.padEnd(9)}]${l.indecidivel ? ' (indecidível)' : ''} ${l.titulo}${cauda}`);
    }
  }
  console.log(`cobertura cumulativa: ${r.somaFeito} de ${r.somaTotal} (${r.cobertura}%)`);
  if (r.naoMedidas) console.log(`nao medidas: ${r.naoMedidas} prova(s) de recibo (avaliador fora do repo — ADR 0397); nao mordem o --check`);
  if (r.migrados) console.log(`fonte desatualizada: ${r.migrados} prova(s) citam endereco pre-ADR 0397; medidas no endereco NOVO — corrigir na fonte (Cowork), nao no espelho`);
  if (proximo) {
    console.log(r.proximos.length
      ? `PRÓXIMO: ${r.proximos.map((p) => `${p.modulo}/${p.id} ${p.titulo} [${p.dono}]`).join(' · ')}`
      : 'PRÓXIMO: nenhum executável — consulte dependências e decisões acima');
  }
}
