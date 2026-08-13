#!/usr/bin/env node
/**
 * requisitos-status.mjs — a CADEIA DE RASTREABILIDADE de um módulo, derivada e com STATUS.
 *
 * O QUE RESPONDE (nenhuma porta atual responde isto):
 *
 *     US (SPEC)  →  CU (SDD §6)  →  UC (casos.md)  →  teste  →  veredito
 *
 * O `casos-coverage-guard` (required) vê **UC↔teste**. O `anchor-lint` (required) vê
 * **US↔código**. Ninguém percorre a CADEIA INTEIRA nem diz, por requisito, ONDE ela quebra.
 * Régua da indústria (Jama Live Traceability · PTC · trace.space, 2026): *"coverage é a % de
 * requisitos com cadeia completa da ORIGEM à EVIDÊNCIA — é isso que separa rastreabilidade
 * auditável de incompleta"*. Este script é essa medida, para dentro do vocabulário do projeto.
 *
 * FRONTEIRA (não duplica régua — proibicoes §5 2026-07-09):
 *   · casos-coverage-guard = o UC tem teste que o cita? (elo UC→teste, e é REQUIRED)
 *   · anchor-lint          = a US aponta código vivo?   (elo US→código, e é REQUIRED)
 *   · ESTE                 = a cadeia US→CU→UC→teste FECHA? onde quebra? o que falta escrever?
 *   Nenhum status aqui re-julga o que aqueles dois já julgam — este COMPÕE e aponta o BURACO.
 *
 * O SISTEMA CRESCE POR AQUI ([W] 2026-07-26: "mantenha o sistema crescente indicando mais
 * requerimentos e proibições e status dos requisitos"). Cada elo que falta É o próximo
 * requisito a escrever — a lista de LACUNAS é a fila de trabalho, derivada, não inventada.
 *
 * STATUS É DERIVADO, NUNCA ESCRITO À MÃO (ADR 0256 · proibicoes §5 "campo auto-declarado"):
 *   ⬜ orfao      — US sem nenhum CU/UC que a cite  → escrever o caso
 *   📝 sem_teste  — UC existe, nenhum teste o cita  → escrever o teste (ou virar [BACKLOG])
 *   🧪 sem_prova  — teste existe mas não executa (test.fixme) ou nunca rodou
 *   ✅ provado    — teste real cita o UC e a lane publicou verde
 *   ❌ refutado   — teste cita o UC e FALHOU: é ACHADO com recibo, não pendência
 *
 * Uso:
 *   node scripts/governance/requisitos-status.mjs <Modulo>            # relatório
 *   node scripts/governance/requisitos-status.mjs <Modulo> --write    # grava _STATUS-GENERATED.md
 *   node scripts/governance/requisitos-status.mjs <Modulo> --check    # gerado × commitado
 *   node scripts/governance/requisitos-status.mjs --selftest          # bite-test
 */

import { readFileSync, existsSync, readdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
// Fonte ÚNICA de "o que é tela" (reconciliação #4836/#4840) — o mesmo módulo que o
// casos-coverage-guard e o screen-coverage-map consomem. Ver telasDoModulo().
import { isPageScreenPath, raizesDePages } from '../qa/page-path.mjs';
/**
 * Fonte ÚNICA do regex de UC-id (ADR 0264 · `scripts/lib/uc-regex.mjs`).
 *
 * ⚠️ Este arquivo tinha DOIS regex próprios (`UC-[A-Z0-9]{2,10}-\d{2,3}`) que exigiam **dois
 * hífens** e por isso não enxergavam UC de prefixo curto — `UC-F01`, `UC-S11`, `UC-P02`:
 * **12 ids reais** no repo (Financeiro/Unificado, Sells/Index, Ponto). Efeito: US COM contrato
 * era acusada de *"entregue sem contrato"* (falso-positivo medido em `US-FIN-031`/`US-FIN-038`).
 *
 * A lib existe **exatamente** pra matar "regex que deviam ser iguais e drifam" (o cabeçalho
 * dela documenta 4 drifts anteriores) — e este arquivo era mais um que drifava, sem importá-la.
 * Achado pelo chip Financeiro; a correção mora aqui porque `scripts/` é área proibida ao chip.
 */
import { ucScanRe } from '../lib/uc-regex.mjs';

/**
 * Namespace Inertia !== nome do modulo. FONTE UNICA: module-surface.mjs::PAGES_NS.
 *
 * Achado do chip TeamMcp (2026-07-28): esta porta resolvia `Pages/${mod}` cru. Pra
 * TeamMcp a pasta e `team-mcp`, entao ela imprimia "0 telas / nenhuma lacuna" sobre
 * 5 telas e 14 UC orfaos — METADE do debito de orfaos do repo. Medido: 6 modulos tem
 * o nome divergente (ADS/Governance/KB/NFSe/Superadmin/TeamMcp), e o mapa ja existia
 * no module-surface; faltava esta porta consumi-lo. Duplicar o mapa aqui reintroduziria
 * a doenca que a lib de UC ja documenta: "regex que deviam ser iguais e drifam".
 */
import { PAGES_NS } from './module-surface.mjs';
const pagesNsDe = (mod) => { const v = PAGES_NS[mod] ?? mod; return Array.isArray(v) ? v : [v]; };
/**
 * Bases de Pages REAIS do módulo — o produto (raiz × namespace) que existe em disco.
 *
 * Duas mudanças do PR #5686 quebram a forma antiga (`resources/js/Pages/${PAGES_NS[mod]}`),
 * e as duas falham em SILÊNCIO aqui (o `readdirSync` mora dentro de um `try/catch` que
 * devolve lista vazia — logo o módulo aparece com 0 telas em vez de dar erro):
 *   1. as Pages passaram a morar TAMBÉM em `Modules/<X>/Resources/js/Pages` — raiz fixa é cegueira;
 *   2. `PAGES_NS` virou 1:N, então o valor pode ser array e o template produzia `Forja,team-mcp`.
 * `raizesDePages` é o dono da lista de raízes — não reimplementar a segunda aqui.
 */
const basesDePages = (mod) => {
  const nss = pagesNsDe(mod);
  const out = [];
  for (const raizAbs of raizesDePages(ROOT)) {
    const raizRel = raizAbs.slice(ROOT.length + 1).replace(/\\/g, '/');
    for (const ns of nss) {
      const rel = `${raizRel}/${ns}`;
      if (existsSync(join(ROOT, rel))) out.push(rel);
    }
  }
  return out;
};

// Guard IS_MAIN (padrao do doc-freshness-score): os extratores sao EXPORTADOS pra teste;
// sem isto, um `import` do modulo dispara o CLI e polui a saida de quem so queria a funcao.
const IS_MAIN = process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1];

const ROOT = process.cwd();
const args = process.argv.slice(2);
const ler = (p) => { try { return readFileSync(join(ROOT, p), 'utf8'); } catch { return ''; } };

// ── extratores (puros — o selftest exercita cada um) ──────────────────────────
/**
 * US com o `status:` da linha de metadata logo abaixo do heading.
 *
 * POR QUE O STATUS IMPORTA AQUI (falso-positivo corrigido 2026-07-26): a 1ª versão listava
 * TODA US sem caso como lacuna. Rodado no Produto, 6 das 10 "lacunas" eram US `status: todo`
 * — feature que **ainda não existe**. Escrever UC para código inexistente cria UC órfão, que
 * o casos-gate G-2 pune e que BLOQUEIA o merge de quem for implementar (lápide §5 2026-07-16:
 * UC não é canal de pedido). Lacuna de verdade é **US entregue SEM contrato** (`done`/`doing`
 * sem caso). US `todo` sem caso é o estado NORMAL do backlog, não dívida.
 */
export function extrairUS(specSrc) {
  const linhas = specSrc.split(/\r?\n/);
  const out = [];
  linhas.forEach((ln, i) => {
    const m = ln.match(/^###\s+(US-[A-Z]{2,8}-\d{3,4})\s*·?\s*(.*)$/);
    if (!m) return;
    /**
     * O status vive no BLOCO da US (até o próximo heading), não nas 3 primeiras linhas.
     *
     * ⚠️ A 1ª versão lia `linhas.slice(i+1, i+4)` e casava só `status:` cru. Medido 2026-07-27
     * (achado do chip Cliente, que bateu no caso e corrigiu o DADO; o defeito da PORTA ficou):
     *   · 283 US — status na janela de 3 linhas ....... lidas ✅
     *   · **228 US — status FORA da janela** .......... liam `desconhecido` ❌
     *   · 353 US — sem status nenhum .................. `desconhecido` é correto (ausência de dado)
     * Os 228 estão em 10 módulos: Whatsapp 57 · Infra 44 · **Sells 42** · Jana 38 · Pcp 20 ·
     * **Ponto 10** · PaymentGateway 7 · ProjectMgmt 6 · Fiscal 3 · NFSe 1.
     *
     * Efeito: `desconhecido` não entra em `US_ENTREGUE`, logo a US **nunca** era acusada de
     * "entregue sem contrato" — falso-VERDE de módulo inteiro. No Cliente o painel imprimia
     * literalmente *"Nenhuma lacuna"* com 12 US entregues sem contrato.
     *
     * Aceita `status:` e `**Status:**` (negrito markdown — a forma que 228 US usam). Pega a
     * PRIMEIRA ocorrência do bloco: é a linha de metadata, e não a palavra "status" que possa
     * aparecer adiante em prosa/tabela sobre status de pedido.
     */
    let fim = i + 1;
    while (fim < linhas.length && !/^#{1,3}\s/.test(linhas[fim])) fim++;
    const meta = linhas.slice(i + 1, fim).join('\n');
    const st = meta.match(/(?:^|\s|\*)status:\s*\*{0,2}\s*([a-z-]+)/i);
    out.push({ id: m[1], titulo: m[2].trim(), status: st ? st[1].toLowerCase() : 'desconhecido' });
  });
  return out;
}
/** Entregue = tem que ter contrato. `todo`/`backlog` = ainda não, e tudo bem. */
export const US_ENTREGUE = new Set(['done', 'doing', 'review', 'in-progress']);
export function extrairCU(sddSrc) {
  return [...sddSrc.matchAll(/^####\s+(CU-[A-Z]{2,8}-\d{2,4})\s*—?\s*([^`\n]*)/gm)]
    .map((m) => ({ id: m[1], titulo: m[2].trim() }));
}
export function extrairUC(casosSrc) {
  const ids = new Set();
  for (const m of casosSrc.matchAll(ucScanRe())) ids.add(m[0].toUpperCase());
  return [...ids];
}
/** Um UC é "citado por teste" se aparece em qualquer arquivo de teste do repo. */
export function ucCitadoPorTeste(uc, corpusTestes) {
  return corpusTestes.some((t) => t.src.includes(uc));
}
/** `test.fixme`/`it.skip` na mesma linha do UC = existe mas não executa. */
export function ucSoStub(uc, corpusTestes) {
  const linhas = corpusTestes.flatMap((t) => t.src.split(/\r?\n/).filter((l) => l.includes(uc)));
  if (!linhas.length) return false;
  return linhas.every((l) => /\b(test\.fixme|it\.skip|xdescribe|markTestSkipped)\b/.test(l));
}

function listarTestes() {
  const out = [];
  const walk = (rel) => {
    let ents; try { ents = readdirSync(join(ROOT, rel), { withFileTypes: true }); } catch { return; }
    for (const e of ents) {
      const p = `${rel}/${e.name}`;
      if (e.isDirectory()) { if (!['node_modules', 'vendor', '.git'].includes(e.name)) walk(p); }
      else if (/\.(php|ts|tsx|js|mjs)$/.test(e.name)) out.push({ path: p, src: ler(p) });
    }
  };
  walk('tests'); walk('e2e');
  /**
   * `Modules/<X>/Tests` — a casa dos testes dos módulos nWidart (achado do chip S1, 2026-07-27).
   *
   * O `casos-coverage-guard` (REQUIRED) já varre `Modules`; esta porta não varria. Contado no
   * dia: **Compras 10 · Fiscal 20 · Ponto 32 · NfeBrasil 47 · Financeiro 80** arquivos de teste
   * invisíveis. Efeito: todo módulo nWidart aparecia com "UC com teste: 0" — falso-VERMELHO —
   * e a lacuna "US entregue sem contrato" nunca fechava, por mais teste que se escrevesse.
   * Pior no contexto do passo 5, cujo próprio plano manda o chip escrever em
   * `Modules/<Mod>/Tests/Feature/` (é onde a lane do módulo procura).
   *
   * Varre o subdir `Tests` de cada módulo, NUNCA o módulo inteiro: UC citado em comentário de
   * código de produção não é prova de teste — seria o `includes` cru que este arquivo já
   * rejeitou pro CU (âncora ≠ prosa).
   */
  let mods; try { mods = readdirSync(join(ROOT, 'Modules'), { withFileTypes: true }); } catch { mods = []; }
  for (const m of mods) if (m.isDirectory()) walk(`Modules/${m.name}/Tests`);
  return out;
}

/**
 * Casos do módulo — DUAS casas, porque o contrato não é só do React ([W] 2026-07-26:
 * *"1 e 2 são requeridos sim. tem que ter tudo do blade"*).
 *
 *   1. `resources/js/Pages/<Mod>/<Tela>.casos.md`     — tela React (governado pelo
 *      `casos-coverage-guard`, required: G-1 trio + G-2 UC↔teste)
 *   2. `memory/requisitos/<Mod>/_telas/<fluxo>.casos.md` — fluxo **sem tela React**:
 *      Blade puro, rota chamada de outro módulo, ou CU que o React ainda não cobre
 *
 * POR QUE A 2ª CASA (medido): o `casos-coverage-guard` varre APENAS `Pages/**`
 * (`listCasosFiles`). Fluxo Blade não tinha onde ancorar contrato — e foi exatamente
 * o que travou 4 lacunas do Produto: `CU-PROD-04` (estoque inicial, o React não faz),
 * `CU-PROD-05` (BOM, sem tela), `CU-PROD-08` (quick-add, chamado de Sells/Purchase) e
 * `US-PROD-028` (Blade via ReportController).
 *
 * POR QUE NÃO ESTENDER O GATE REQUIRED: o G-1 exige trio (charter+casos) de TODA página
 * roteada. Apontá-lo pra `resources/views/**` faria ~600 Blades nascerem em violação de
 * uma vez — big-bang de legado, a lápide de 2026-07-12. A fronteira fica: o `casos-gate`
 * governa o React (required); este painel percorre a cadeia inteira, React **e** Blade
 * (advisory). Nenhum re-julga o que o outro julga.
 *
 * `_telas/` já é a casa canônica dos artefatos por-tela do módulo (RUNBOOK-*, *-visual-
 * comparison) — não é diretório novo.
 */
function casosDoModulo(mod) {
  const out = [];
  // RECURSIVO no lado React pelo mesmo motivo de telasDoModulo(): `Unificado/Index.casos.md`
  // ficava invisível e a tela irmã aparecia como "sem caso" (ou nem aparecia). O nome da tela
  // é o caminho relativo ao módulo sem `.casos.md` — casa 1:1 com telasDoModulo().
  const coletar = (dir, sufixoTela, base = dir, recursivo = false) => {
    let ents; try { ents = readdirSync(join(ROOT, dir), { withFileTypes: true }); } catch { return; }
    for (const e of ents) {
      const p = `${dir}/${e.name}`;
      if (e.isDirectory()) { if (recursivo) coletar(p, sufixoTela, base, true); continue; }
      if (!e.name.endsWith('.casos.md')) continue;
      out.push({
        tela: p.slice(base.length + 1).replace(/\.casos\.md$/, '') + sufixoTela,
        path: p,
        src: ler(p),
      });
    }
  };
  for (const pagesDir of basesDePages(mod)) coletar(pagesDir, '', pagesDir, true);
  coletar(`memory/requisitos/${mod}/_telas`, ' (blade)');
  return out;
}
/**
 * Telas do módulo — RECURSIVO, pela fonte única `page-path.mjs`.
 *
 * ⚠️ A 1ª versão fazia `readdirSync` + `isFile()` (sem recursão) e por isso **não enxergava
 * tela em subpasta**. Medido 2026-07-27: subcontava **20 de 40 módulos** — `ads` 19→0,
 * `Financeiro` 21→2, `Ponto` 20→1, `Essentials` 13→0. Pior que o número: o painel imprimia
 * **"Nenhuma lacuna"** sobre telas que nem via, e o próprio piloto do Produto fechou como
 * "7/7" sendo **7 de 8** (`Produto/Unificado/Index.tsx` nunca entrou na conta).
 *
 * O escopo de "o que é tela" tem UM dono desde a reconciliação #4836/#4840 —
 * [`scripts/qa/page-path.mjs`](../qa/page-path.mjs) — que o `casos-coverage-guard` e o
 * `screen-coverage-map` já consomem. Esta porta ficou de fora; agora consome também.
 * Um conceito, um número, três lados.
 *
 * O nome da tela passa a ser o caminho relativo ao módulo sem extensão (`Unificado/Index`),
 * pra casar 1:1 com `Unificado/Index.casos.md` — mesma convenção em `casosDoModulo`.
 */
function telasDoModulo(mod) {
  const out = [];
  for (const base of basesDePages(mod)) {
    const walk = (rel) => {
      let ents; try { ents = readdirSync(join(ROOT, rel), { withFileTypes: true }); } catch { return; }
      for (const e of ents) {
        const p = `${rel}/${e.name}`;
        if (e.isDirectory()) { walk(p); continue; }
        if (!e.name.endsWith('.tsx') || !isPageScreenPath(p)) continue;
        out.push(p.slice(base.length + 1).replace(/\.tsx$/, ''));
      }
    };
    walk(base);
  }
  return out;
}
function sddDoModulo(mod) {
  const dir = `memory/requisitos/${mod}`;
  let ents; try { ents = readdirSync(join(ROOT, dir), { withFileTypes: true }); } catch { return null; }
  const f = ents.find((e) => e.isFile() && /^SDD.*\.md$/.test(e.name));
  return f ? `${dir}/${f.name}` : null;
}

// ── SELFTEST ──────────────────────────────────────────────────────────────────
if (IS_MAIN && args.includes('--selftest')) {
  let f = 0;
  const ok = (nome, cond) => { console.log(`${cond ? '  ok  ' : ' FALHA'} ${nome}`); if (!cond) f++; };

  ok('extrai US do SPEC', extrairUS('### US-PROD-020 · Governança\ntexto\n### US-PROD-021 · Outra').length === 2);
  ok('não confunde US com prosa', extrairUS('falamos de US-PROD-020 no meio do texto').length === 0);

  // O status separa LACUNA (entregue sem contrato) de BACKLOG (não entregue) — sem isto o
  // relatório empurra o autor a escrever UC órfão pra feature que não existe.
  const comStatus = extrairUS('### US-AB-001 · X\n> owner: w · status: done · type: story\n\n### US-AB-002 · Y\n> owner: w · status: todo');
  ok('lê status: done', comStatus[0].status === 'done');
  ok('lê status: todo', comStatus[1].status === 'todo');
  ok('done conta como entregue', US_ENTREGUE.has('done') === true);
  ok('todo NÃO conta como entregue', US_ENTREGUE.has('todo') === false);
  ok('sem linha de status → desconhecido', extrairUS('### US-AB-003 · Z\n\ntexto')[0].status === 'desconhecido');
  ok('extrai CU do SDD', extrairCU('#### CU-PROD-01 — Cadastrar `[must]`\n#### CU-PROD-02 — Variável').length === 2);
  ok('extrai UC do casos.md (dedup)', extrairUC('| UC-PSHOW-01 | x |\n UC-PSHOW-01 de novo\n UC-PSHOW-02').length === 2);

  const corpus = [{ path: 't.php', src: "it('UC-PSHOW-01 · faz algo', function () {});\ntest.fixme('UC-PSHOW-04 · stub');" }];
  ok('UC citado por teste → true', ucCitadoPorTeste('UC-PSHOW-01', corpus) === true);
  ok('UC não citado → false', ucCitadoPorTeste('UC-PSHOW-99', corpus) === false);
  ok('UC só em test.fixme → stub', ucSoStub('UC-PSHOW-04', corpus) === true);
  ok('UC em teste real → NÃO stub', ucSoStub('UC-PSHOW-01', corpus) === false);

  // ANTI-GAMING — achado do agent na corrida do BulkEdit, que testou e reportou honestamente:
  // com `includes` cru, citar o id em QUALQUER parágrafo fechava a lacuna sem contrato nenhum.
  // Presence-gate clássico (L-24: presença ≠ correção). Cobertura agora exige ÂNCORA estrutural.
  ok('NÃO cobre: id em prosa solta',
    citadoComoAncora('Falamos sobre CU-PROD-06 aqui no meio do texto.', 'CU-PROD-06') === false);
  ok('cobre: linha de tabela de rastreabilidade',
    citadoComoAncora('| UC-PBULK-01 | Editar em lote | must | CU-PROD-06 | teste |', 'CU-PROD-06') === true);
  ok('cobre: frontmatter related_us',
    citadoComoAncora('related_us: [US-PROD-023, US-PROD-020]', 'US-PROD-023') === true);
  ok('cobre: bullet de Âncora',
    citadoComoAncora('- **Âncora:** CU-PROD-06 do SDD §6.1', 'CU-PROD-06') === true);
  // A forma REAL do corpus — blockquote + backticks. A 1ª versão do regex não a aceitava e
  // acusou 3 CU legítimos (08/14/15). Este caso é o controle que faltava.
  ok('cobre: blockquote + backticks (forma real dos casos.md)',
    citadoComoAncora('> **Âncora:** `CU-PROD-14` (ficha/consulta) e `CU-PROD-10` `[T0]` do', 'CU-PROD-14') === true);
  ok('NÃO cobre: id parecido (não casa prefixo)',
    citadoComoAncora('| UC-X-01 | y | CU-PROD-061 | z |', 'CU-PROD-06') === false);
  // 2ª rodada de anti-gaming — o agent dos fluxos Blade citou uma US numa tabela de
  // CONTEXTO e ela saiu do backlog sem contrato. Só linha que COMEÇA com id conta.
  ok('NÃO cobre: id em tabela de contexto (1º campo não é id)',
    citadoComoAncora('| Fluxo | Canon | Blade | Delphi | US-PROD-025 |', 'US-PROD-025') === false);
  ok('cobre: CU em coluna de linha que começa com UC (rastreabilidade real)',
    citadoComoAncora('| UC-PBULK-01 | Editar em lote | must | CU-PROD-06 |', 'CU-PROD-06') === true);
  ok('cobre: 1º campo com backticks',
    citadoComoAncora('| `UC-PFIX-01` | Ajuste no relatório | must | US-PROD-028 |', 'US-PROD-028') === true);

  // ── BITE-TEST das 2 correções de 2026-07-27 (escopo de tela + casos vazio) ──────────
  // Regra do projeto: correção de máquina só vale com fixture RUIM que morde e BOA que passa.
  // Sem controle negativo, "consertei" é afirmação — e afirmação é o que estamos matando aqui.

  // (1) escopo de tela: subpasta CONTA, auxiliar NÃO. Antes do fix, `Unificado/Index` era
  // invisível e o painel do Produto fechava "7/7" sendo 7 de 8.
  const telasProduto = telasDoModulo('Produto');
  ok('MORDE: tela em subpasta entra na conta (Unificado/Index)',
    telasProduto.includes('Unificado/Index'));
  ok('controle-negativo: _components NÃO vira tela',
    !telasProduto.some((t) => t.startsWith('_')));
  ok('escopo bate com a fonte única (Produto = 8 telas, igual screen-coverage)',
    telasProduto.length === 8);

  // (2) casos.md sem UC: arquivo presente ≠ tela coberta. Fixture ruim = o stub REAL do
  // Fiscal (37-41 linhas, 0 UC); fixture boa = um casos.md com UC declarado.
  ok('MORDE: casos.md sem nenhum UC não cobre',
    extrairUC('---\nowner: W\n---\n# Casos — Nfe\n\n> stub: contrato a escrever.\n').length === 0);
  ok('controle-negativo: casos.md com UC declarado cobre',
    extrairUC('| UC-FISC-01 | Emitir NFe | must |').length === 1);

  // (3) corpus de testes: `Modules/<X>/Tests` conta (achado do chip S1). Sem isto, módulo
  // nWidart aparecia com "UC com teste: 0" mesmo com 80 arquivos de teste no disco.
  const corpusSelftest = listarTestes();
  ok('MORDE: Modules/<X>/Tests entra no corpus de testes',
    corpusSelftest.some((t) => t.path.startsWith('Modules/')));
  ok('controle-negativo: só o subdir Tests — código de produção do módulo fica fora',
    corpusSelftest.filter((t) => t.path.startsWith('Modules/')).every((t) => t.path.includes('/Tests/')));

  // (4) UC-id vem da fonte única (achado do chip Financeiro). O regex próprio exigia 2 hífens
  // e cegava 12 ids reais do repo — US COM contrato saía como "entregue sem contrato".
  ok('MORDE: UC de prefixo curto conta (UC-F01, UC-S11)',
    extrairUC('| UC-F01 | Baixa | must |\n| UC-S11 | Menu de ações | must |').length === 2);
  ok('cobre: as duas formas convivem (UC-F01 + UC-PROD-020)',
    extrairUC('UC-F01 e UC-PROD-020').length === 2);
  ok('controle-negativo: "UC-" solto em prosa não vira id',
    extrairUC('o UC- do caso ainda não foi numerado').length === 0);

  // (5) status da US: bloco inteiro, não 3 linhas (achado do chip Cliente). 228 US liam
  // `desconhecido` e por isso NUNCA eram acusadas de "entregue sem contrato" — falso-verde.
  ok('MORDE: status fora da janela de 3 linhas é lido',
    extrairUS('### US-CRM-001 · Ficha\ntexto\ntexto\ntexto\ntexto\n**Status:** done\n')[0].status === 'done');
  ok('cobre: forma clássica na metadata (não regrediu)',
    extrairUS('### US-PROD-020 · G\n\n> owner: wagner · status: todo · type: epic')[0].status === 'todo');
  ok('controle-negativo: US sem status nenhum segue desconhecido',
    extrairUS('### US-ARQ-001 · Scaffold\n**Implementado em:** `x.php`\n')[0].status === 'desconhecido');
  ok('controle-negativo: status da US seguinte não vaza pra anterior',
    extrairUS('### US-AA-001 · Um\ntexto\n### US-AA-002 · Dois\n> status: done')[0].status === 'desconhecido');

  const TOTAL = 36;
  console.log(f === 0 ? `\n✅ selftest ${TOTAL}/${TOTAL} — extratores, classificador e anti-gaming provados` : `\n❌ ${f} falha(s)`);
  process.exit(f === 0 ? 0 : 1);
}

// ── relatório ─────────────────────────────────────────────────────────────────
const mod = IS_MAIN ? args.find((a) => !a.startsWith('--')) : null;
if (IS_MAIN && !mod) { console.log('uso: requisitos-status.mjs <Modulo> [--write|--check]'); process.exit(0); }

const specPath = `memory/requisitos/${mod}/SPEC.md`;
const sddPath = sddDoModulo(mod);
const us = extrairUS(ler(specPath));
const cu = sddPath ? extrairCU(ler(sddPath)) : [];
const casos = casosDoModulo(mod);
const telas = telasDoModulo(mod);
const testes = listarTestes();

// DEDUPE por id: um UC é citado em mais de um casos.md (referência cruzada entre telas
// irmãs). A tela DONA é a que o declara na tabela de rastreabilidade (linha `| UC-… |`);
// citação em prosa de outra tela não cria um segundo requisito.
const donoDe = new Map();
for (const c of casos) {
  // 1º campo da linha de tabela = tela DONA do UC. Mesmo core da fonte única (ver import).
  for (const m of c.src.matchAll(new RegExp(`^\\|\\s*(${ucScanRe().source})\\s*\\|`, 'gm'))) {
    if (!donoDe.has(m[1])) donoDe.set(m[1], c.tela);
  }
}
for (const c of casos) for (const id of extrairUC(c.src)) if (!donoDe.has(id)) donoDe.set(id, c.tela);

const ucs = [...donoDe.entries()].map(([id, tela]) => ({ id, tela }));
const statusUC = ucs.map((u) => {
  if (!ucCitadoPorTeste(u.id, testes)) return { ...u, status: '📝 sem_teste' };
  if (ucSoStub(u.id, testes)) return { ...u, status: '🧪 stub (não executa)' };
  // Tem teste REAL que o cita. O veredito (✅/❌) é da lane — este gerador nunca o afirma.
  return { ...u, status: '🧪 aguarda veredito da lane' };
});

// LACUNAS = a fila de crescimento (derivada, não inventada)
const telasSemCasos = telas.filter((t) => !casos.some((c) => c.tela === t));
/**
 * `casos.md` PRESENTE mas SEM NENHUM UC = lacuna, não cobertura (medido 2026-07-27).
 *
 * A 1ª versão só perguntava "existe arquivo `<Tela>.casos.md`?". Rodado no Fiscal, o painel
 * imprimiu **"Nenhuma lacuna: toda tela tem caso"** — e os 7 arquivos eram **stubs de 37-41
 * linhas com ZERO UC**. Presença de arquivo tratada como contrato é exatamente a classe
 * **LC-11** (presence-gate) que este projeto persegue, aqui cometida DENTRO da máquina que
 * cobra. O irmão disso já tinha sido corrigido acima pro CU (âncora ≠ prosa); faltava o caso
 * mais grosso: arquivo vazio de UC.
 *
 * Não é gate novo nem régua nova — é a MESMA lacuna medindo conteúdo em vez de existência.
 */
const casosSemUC = casos.filter((c) => extrairUC(c.src).length === 0);
/**
 * COBERTO = citado como ÂNCORA, não mencionado em prosa (correção 2026-07-26).
 *
 * A 1ª versão usava `src.includes(id)`. O agent da corrida do BulkEdit testou o gaming e
 * reportou honestamente: *"bastaria citar o id na prosa pra lacuna sumir do painel — fiz,
 * vi fechar, desfiz"*. Um `includes` cru transforma o painel em **presence-gate**: escrever
 * o id num parágrafo qualquer "fecha" a lacuna sem contrato nenhum. É a família L-24
 * (presença ≠ correção), a mesma que este projeto mata desde 2026-07-01.
 *
 * Âncora estrutural aceita — as formas MEDIDAS no corpus, não supostas:
 *   · linha de TABELA        → `| UC-X-01 | … | CU-PROD-06 | …`  (rastreabilidade)
 *   · campo de frontmatter   → `related_us: [US-PROD-023]` / `us:` / `âncora:`
 *   · declaração de Âncora   → `> **Âncora:** \`CU-PROD-14\` …`  ← forma REAL dos casos.md
 *                              (blockquote, bullet ou linha nua; id entre backticks ou não)
 * Menção solta em parágrafo NÃO cobre — e é isso que impede o painel de mentir.
 *
 * ⚠️ A 1ª versão desta regra só aceitava BULLET (`- **Âncora:**`) e deu falso-positivo em 3 CU
 * (`CU-PROD-08/14/15`): o corpus usa BLOCKQUOTE (`> **Âncora:**`). Medido contra os arquivos
 * reais antes de fechar — o padrão vem do que o projeto escreve, não do que eu imaginei.
 */
export function citadoComoAncora(src, id) {
  const esc = id.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  return new RegExp(
    // Linha de tabela: só conta se a linha for de RASTREABILIDADE — 1º campo é um id
    // (UC-/US-/CU-). Sem isso, o id citado em QUALQUER coluna de QUALQUER tabela vira
    // âncora: o agent da corrida dos fluxos Blade gamificou sem querer (citou US-PROD-025
    // numa tabela de contexto e a US saiu do backlog sem contrato), pegou comparando o
    // backlog antes/depois, e reportou. A linha `| UC-PBULK-01 | … | CU-PROD-06 |` segue
    // valendo pro CU — porque ELA começa com id, então é rastreabilidade de verdade.
    `(^\\|\\s*\`?(UC|US|CU)-[A-Z0-9]{2,10}-\\d{2,4}\`?\\s*\\|[^\\n]*\\b${esc}\\b)`
    + `|(^\\s*(related_us|us|ancora|âncora|covers|cobre)\\s*:[^\\n]*\\b${esc}\\b)` // frontmatter
    + `|(^\\s*[>\\-*\\s]*\\*\\*(Âncora|Ancora|Cobre|Covers)[^\\n]*\\b${esc}\\b)`,  // declaração
    'im',
  ).test(src);
}
const cobreAncorado = (id) => casos.some((k) => citadoComoAncora(k.src, id));

const cuSemUC = cu.filter((c) => !cobreAncorado(c.id));
// Só é LACUNA a US já entregue e sem contrato. US `todo` sem caso é backlog normal —
// listá-la como dívida empurra o autor a escrever UC órfão (ver extrairUS).
const usSemContrato = us.filter((u) => US_ENTREGUE.has(u.status) && !cobreAncorado(u.id));
const usBacklog = us.filter((u) => !US_ENTREGUE.has(u.status) && !cobreAncorado(u.id));

const linhas = [];
const P = (s = '') => linhas.push(s);
// `authority: generated` NÃO é decoração — é o que faz o `distiller_freshness`
// (sdd-scorecard, gate REQUIRED GT-G3) IGNORAR este arquivo ao procurar "o doc mais
// novo do módulo". Sem ele, regenerar este índice marca a BRIEFING do módulo como
// stale por FALSO — conhecimento novo nenhum apareceu, só uma máquina rodou.
// Medido 2026-08-05: regenerar Financeiro/KB/Ponto levou distiller_freshness 0 → 1
// e avermelhou um gate required; com o campo, volta a 0. Mesmo defeito que o
// #5298 consertou pro Jana/ARCHITECTURE.md — lá a regra era por NOME e deixava
// passar o irmão; hoje é por `authority`, e este gerador não emitia o campo.
// §5 2026-07-12 / 2026-07-17: conserta o MEDIDOR (aqui, a fonte dele), não o baseline.
P('---');
P('authority: generated');
P('---');
P('');
P(`<!-- GERADO por scripts/governance/requisitos-status.mjs — NÃO editar à mão.`);
P(`     Status é DERIVADO da cadeia US→CU→UC→teste. Editar aqui não muda nada:`);
P(`     mude o SPEC/SDD/casos/teste e re-rode. (ADR 0256: derivado sobrevive.) -->`);
P('');
P(`# Requisitos — ${mod} · status derivado`);
P('');
P(`> **Cadeia medida:** \`US (SPEC) → CU (SDD §6) → UC (casos.md) → teste → veredito\`.`);
P(`> O veredito final (✅/❌) vem da **lane de CI**, nunca deste gerador — status aqui`);
P(`> nunca afirma verde sem execução (G-7 · [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)).`);
P('');
P('## Placar da cadeia');
P('');
P('| Elo | Quantidade |');
P('|---|---:|');
P(`| US no SPEC | ${us.length} |`);
P(`| CU no SDD | ${cu.length} |`);
P(`| Telas (.tsx) | ${telas.length} |`);
P(`| Telas com \`casos.md\` | ${casos.length} |`);
P(`| UC declarados | ${ucs.length} |`);
P(`| UC com teste que os cita | ${statusUC.filter((u) => u.status !== '📝 sem_teste').length} |`);
P('');
P('## Onde a cadeia QUEBRA — esta é a fila de crescimento');
P('');
if (!telasSemCasos.length && !casosSemUC.length && !cuSemUC.length && !usSemContrato.length) {
  P('_Nenhuma lacuna: toda tela tem caso **com UC**, todo CU é citado, e toda US **entregue** tem contrato._');
} else {
  P('| Lacuna | O que falta escrever |');
  P('|---|---|');
  for (const t of telasSemCasos) P(`| Tela \`${t}\` sem \`casos.md\` | o contrato da tela (trio incompleto) |`);
  for (const c of casosSemUC) P(`| \`${c.path.split('/').pop()}\` existe mas **não declara nenhum UC** | o contrato de verdade — arquivo presente ≠ tela coberta (LC-11) |`);
  for (const c of cuSemUC) P(`| \`${c.id}\` sem UC | caso de uso que o exercite — ${c.titulo.slice(0, 70)} |`);
  for (const u of usSemContrato) P(`| \`${u.id}\` **entregue sem contrato** (\`status: ${u.status}\`) | UC que prove o que foi entregue — ${u.titulo.slice(0, 60)} |`);
}
P('');
P('### Backlog — NÃO é lacuna');
P('');
P('> US ainda não entregue (`todo`/`backlog`) **não deve** ganhar UC agora: caso sem código vira');
P('> **UC órfão**, que o `casos-gate` G-2 pune e que bloqueia o merge de quem for implementar');
P('> ([proibicoes §5](../../proibicoes.md) 2026-07-16 — UC não é canal de pedido). O contrato');
P('> nasce **junto** com a implementação, não antes.');
P('');
if (!usBacklog.length) P('_Nenhuma._');
else {
  P('| US | status | Título |');
  P('|---|---|---|');
  for (const u of usBacklog) P(`| ${u.id} | \`${u.status}\` | ${u.titulo.slice(0, 80)} |`);
}
P('');
P('## UC por status');
P('');
P('| UC | Tela | Status |');
P('|---|---|---|');
for (const u of statusUC.sort((a, b) => a.id.localeCompare(b.id))) P(`| ${u.id} | ${u.tela} | ${u.status} |`);
P('');
P('---');
P('');
P('**Como este arquivo cresce:** cada linha da tabela "onde a cadeia quebra" é o **próximo');
P('requisito a escrever**. Fechou? Re-rode e ela some. Descobriu que NÃO se deve fazer?');
P('Então não é lacuna — é **Non-Goal no charter** (só [W] preenche) ou entrada no **§5 de');
P('`proibicoes.md`** se for padrão a nunca repetir. As duas saídas são legítimas; deixar');
P('a lacuna aberta sem decisão é a única que não é.');

const saida = linhas.join('\n') + '\n';
const destino = `memory/requisitos/${mod}/_STATUS-GENERATED.md`;

if (args.includes('--write')) {
  writeFileSync(join(ROOT, destino), saida, 'utf8');
  console.log(`  ✓ ${destino} gravado (${us.length} US · ${cu.length} CU · ${ucs.length} UC)`);
  process.exit(0);
}
if (args.includes('--check')) {
  const atual = existsSync(join(ROOT, destino)) ? ler(destino) : null;
  if (atual === null) { console.log(`  ✗ ${destino} não existe — rode com --write`); process.exit(1); }
  if (atual.replace(/\r\n/g, '\n') !== saida) {
    console.log(`  ✗ ${destino} está DRIFADO vs a árvore. Rode: node scripts/governance/requisitos-status.mjs ${mod} --write`);
    process.exit(1);
  }
  console.log(`  ✓ ${destino} em dia.`);
  process.exit(0);
}
console.log(saida);
