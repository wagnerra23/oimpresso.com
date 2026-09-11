#!/usr/bin/env node
// cowork-pele-paralela.mjs — guard de PELE PARALELA no build do Cowork.
//
// DESTINO NO REPO: scripts/qa/cowork-pele-paralela.mjs
// Rodar: node scripts/qa/cowork-pele-paralela.mjs [--dir prototipo-ui/cowork] [--json] [--aviso-so]
// No CI: junto do cowork-ssot-guard (mesmo job), exit != 0 quebra o check.
//
// ── O PROBLEMA QUE ELE RESOLVE ────────────────────────────────────────────────────
// Uma auditoria manual encurta a lista de duplicações; a tela seguinte a alonga de
// novo. Em 2026-08-31 a medição no main achou: 14 dialetos de segmented, 20+ arquivos
// com a pele de aba própria, 4 mini-DS paralelas (AcessosDS/PBUI/ModuloPadrao/HrmUI) e
// uma colisão de nome com o DS (window.FsmStepper). Nada disso foi decidido — foi
// acumulado, um arquivo por vez, porque nada reclamava na hora do commit.
// Este script reclama na hora do commit. Ele não julga desenho: só cobra DONO ÚNICO.
//
// ── AS 5 REGRAS ───────────────────────────────────────────────────────────────────
// R1  classe de aba (.cli-moduletopnav*) fora do dono declarado
// R2  segmented novo: classe *-seg com botões irmãos fora do dono declarado
// R3  mini-DS nova: window.<Algo>UI / window.<Algo>DS publicado fora da allowlist
// R4  nome publicado em window por DOIS arquivos diferentes
// R5  nome publicado em window que JÁ EXISTE como componente do Design System
// R6  TabBar/Segmented do DS usado direto, fora do dono (perde ariaLabel, ganha wrapper)
// R7  componente local com o nome de uma peça que o DS publica (6 Kebab, 5 Toolbar…)
//
// Allowlist = o que já existe e está catalogado. Encolher a allowlist é progresso;
// crescer exige ADR. É isso que impede o guard de virar carimbo.

import { readdirSync, readFileSync, statSync } from "node:fs";
import { join, extname, basename } from "node:path";

const args = process.argv.slice(2);
const opt = (n, d) => { const i = args.indexOf(n); return i === -1 ? d : args[i + 1]; };
const DIR = opt("--dir", "prototipo-ui/cowork");
const JSON_OUT = args.includes("--json");
const AVISO_SO = args.includes("--aviso-so");

// Donos declarados: o arquivo que TEM direito de pintar cada peça.
const DONOS = {
  abas: ["cli-tabs.jsx"],
  segmented: ["cli-seg.js", "tweaks-panel.jsx"], // tweaks-panel é chrome do host, não UI de cliente
  pagehead: ["cli-pagehead.jsx"],
};
// WAIVERS — exceção com MOTIVO e validade. Sem motivo escrito, não entra.
// A lista existe pra o guard ser sinal, não ruído; encolher é progresso.
const WAIVERS = [
  { arquivo: "produto-blade.jsx", regras: ["R1", "R2"], motivo: "rota Produtos trava com o TabBar do DS via wrapper (não isolado, 2026-08-31); espera TabBar aceitar props no <nav>" },
  { arquivo: "produto-analises.jsx", regras: ["R1"], motivo: "idem produto-blade" },
  { arquivo: "produto-cadastros.jsx", regras: ["R1"], motivo: "idem produto-blade" },
  { arquivo: "inbox-page.jsx", regras: ["R2"], motivo: "cada botão carrega data-testid usado em teste de contrato; CliSeg não repassa atributos por item" },
  { arquivo: "essenciais-page.jsx", regras: ["R2"], motivo: "não é escolha única: é paginador de mês (Anterior · mês · Próximo)" },
  // ── Fileiras de aba medidas em 2026-09-01, ainda não migradas ─────────────────────
  // Não são exceção de mérito: é lote grande, cada uma precisa de olho na tela. O waiver
  // existe pra o guard entrar VERDE no CI hoje e a dívida ficar contada, com nome e motivo.
  // Ao migrar cada uma, APAGUE a linha — a lista encolhendo é a métrica.
  // ── Fileiras que NÃO viram TabBar, por mérito (medido 2026-09-01) ─────────────────
  // 17 waivers de "não migrada" viraram 3 de mérito: as 14 restantes foram migradas.
  { arquivo: "app.jsx", regras: ["R1"], motivo: "topbar-tabs e ph-nav são chrome do SHELL (troca de módulo/empresa), não sub-nav de módulo — vocabulário do AppSidebar/PageHeader, não do TabBar" },
  { arquivo: "vendas-extras.jsx", regras: ["R1"], motivo: "vd-modnav não é fileira de aba: mistura salto para módulos irmãos (CRM, Oficina), divisor e botão de PDV em tela cheia — é barra de navegação, não escolha de aba" },
  { arquivo: "superadmin-page.jsx", regras: ["R1"], motivo: "sa-cfg-nav é rail VERTICAL de duas linhas por item (<b>título</b> + <small>N ajustes</small>); TabBar é horizontal de uma linha" },
  // R6 — TabBar direto que NÃO vai passar pelo dono agora, com motivo:
  { arquivo: "venda-v3.jsx", regras: ["R6"], motivo: "Venda vem de outra conta de design ([W] 2026-08-13); mudança aqui é fora do meu escopo" },
  { arquivo: "sells-app.jsx", regras: ["R6"], motivo: "idem venda-v3 (outra conta de design)" },
  { arquivo: "sells-telas.jsx", regras: ["R6"], motivo: "idem venda-v3 (outra conta de design)" },
  { arquivo: "sells-item-detail.jsx", regras: ["R6"], motivo: "idem venda-v3 (outra conta de design)" },
  { arquivo: "dash-legacy-page.jsx", regras: ["R6"], motivo: "tela legada em substituição; não investir" },
  { arquivo: "fiscal-page.jsx", regras: ["R2"], motivo: "fx-chips de visões salvas é vocabulário de CHIP (quantidade variável, data-tone, filtro manual entra e sai) — FilterChip/TagChip do DS, não Segmented de 2-5 opções fixas" },
  { arquivo: "fiscal-subpages.jsx", regras: ["R2"], motivo: "fx-chips de tipo de evento: mesma razão (lista de tipos, não escolha fixa)" },
  // ── Achados da 4ª geração do detector (2026-09-01) que NÃO são segmented ─────────
  // Regra que usei pra decidir: segmented é ESCOLHA EXCLUSIVA de 2–5 opções FIXAS.
  // Clicar no ativo e desmarcar → é filtro (chip). Conjunto vindo de dados → é chip.
  // Múltipla seleção, árvore ou dropdown → outro componente. Os 5 que ERAM segmented
  // foram migrados nesta rodada; estes ficam com o motivo, não com "não migrada".
  { arquivo: "inbox-page.jsx", regras: ["R2"], motivo: "om-flt-pills: chip de filtro por canal/conta/fila/tag — conjunto vem de dados, tem estado 'muted' (em breve) e data-testid por botão usado em teste de contrato" },
  { arquivo: "cms-page.jsx", regras: ["R2"], motivo: "cms-chips mistura dois grupos de filtro + separador + botão 'Limpar' heterogêneo; é barra de chips, não segmented" },
  { arquivo: "forja-tarefas.jsx", regras: ["R2"], motivo: "fj-groupby: clicar no ativo DESMARCA (toggle), e o conjunto sai de TK_TASKS — filtro, não escolha exclusiva" },
  { arquivo: "essenciais-extras.jsx", regras: ["R2"], motivo: "ess-kb-cat é árvore de navegação aninhada (categoria → seção → artigo), não fileira" },
  { arquivo: "financeiro-page.jsx", regras: ["R2"], motivo: "fin-contas-filter é dropdown de seleção MÚLTIPLA com checkbox" },
  { arquivo: "venda-index.jsx", regras: ["R2"], motivo: "vi-salvas são dois toggles independentes + dropdown de origem, não um grupo exclusivo" },
  { arquivo: "forja-page.jsx", regras: ["R2"], motivo: "os-page-h-r é a barra do header (campainha + ⌘K + nav agrupada); heterogênea por natureza" },
  // ── R7: peças do DS reimplementadas, ainda não migradas (medido 2026-09-01) ───────
  // Os 6 Kebab foram migrados nesta rodada. Estes ficam contados, com nome e motivo:
  { arquivo: "produto-blade.jsx", regras: ["R7"], motivo: "Kebab com posicionamento FIXED calculado: dentro de célula de tabela com overflow, qualquer menu ancorado (inclusive o DropdownMenu do DS, que não usa portal) é recortado ~2px. Só migra quando o DS portalizar o menu" },
  { arquivo: "estoque-page.jsx", regras: ["R7"], motivo: "Toolbar local orquestra colunas+export+densidade+período+FilterChip; o Toolbar do DS é de 3 zonas — precisa de mapeamento tela por tela, não é troca mecânica" },
  { arquivo: "crm-blade.jsx", regras: ["R7"], motivo: "Toolbar .pb-toolbar; mesma razão do estoque" },
  { arquivo: "venda-blade.jsx", regras: ["R7"], motivo: "Toolbar .pb-toolbar; mesma razão" },
  { arquivo: "catchup-shared.jsx", regras: ["R7"], motivo: "Toolbar .pb-toolbar; mesma razão" },
  { arquivo: "clientes-page.jsx", regras: ["R7"], motivo: "Toolbar .cli-toolbar-v2 com contagem de resultado e limpar-filtros embutidos" },
  { arquivo: "pg-cobranca-page.jsx", regras: ["R7"], motivo: "Timeline de cobrança: vem da família payment-gateway (outra conta de design)" },
];
const temWaiver = (nome, regra) => WAIVERS.some((w) => w.arquivo === nome && w.regras.includes(regra));
// Mini-DS existentes (dívida catalogada em 2026-08-31). Não crescer sem ADR.
// Medido 2026-08-31: SEIS, não quatro. CatchupUI e PontoUI só apareceram quando o guard rodou.
const MINI_DS_OK = new Set(["AcessosDS", "PBUI", "ModuloPadrao", "HrmUI", "CatchupUI", "PontoUI", "CBUI", "PBD", "CBD"]);
// Componentes publicados pelo Design System — nome curto é dele, não nosso.
const DS_COMPONENTES = new Set([
  "Button", "Input", "Select", "Textarea", "Switch", "Checkbox", "RadioGroup", "Drawer",
  "DrawerSection", "Modal", "StatusBadge", "Avatar", "TagChip", "KpiCard", "KpiFilterCard",
  "PageHeader", "FsmStepper", "DataTable", "DataTablePro", "Toast", "Skeleton", "EmptyState",
  "BulkBar", "FilterChip", "TaskCard", "BoardColumn", "TabBar", "Breadcrumb", "Pagination",
  "AppSidebar", "Command", "Chart", "Tooltip", "DropdownMenu", "Alert", "Progress",
  "PeriodBar", "DatePicker", "ProofFrame", "Dimension", "ProofStrip", "RegistrationMark",
  "PlacaVeiculo", "Logo",
  // Publicados no bundle de 2026-09-01 (eram as "7 lacunas verdadeiras"):
  "Segmented", "Widget", "Toolbar", "ToolbarButton", "ToolbarSearch", "ToolbarDivider",
  "ToolbarSpacer", "Kebab", "Timeline", "DataGrid", "PresenterMode",
]);
// Pastas que não são build do app único (já são achado de outro guard; aqui só ignora).
const IGNORAR_DIR = new Set(["prototipo-ui-patch", "node_modules", "ds-v6", ".git"]);

const arquivos = [];
(function anda(d) {
  let entradas;
  try { entradas = readdirSync(d); } catch { return; }
  for (const e of entradas) {
    const p = join(d, e);
    if (statSync(p).isDirectory()) { if (!IGNORAR_DIR.has(e)) anda(p); continue; }
    if ([".jsx", ".js", ".css", ".html"].includes(extname(e))) arquivos.push(p);
  }
})(DIR);

const achados = [];
const reporta = (regra, nivel, arquivo, linha, msg) => achados.push({ regra, nivel, arquivo, linha, msg });
const linhaDe = (txt, i) => txt.slice(0, i).split("\n").length;

// window.X = …  (publicação de nome global)
const publicados = new Map(); // nome -> [arquivo]

for (const p of arquivos) {
  const nome = basename(p);
  const txt = readFileSync(p, "utf8");
  const ext = extname(p);
  const eDono = (lista) => lista.includes(nome);

  // R1/R2 — fileira de aba e segmented fora do dono, por FORMA e SEM depender de tag.
  //
  // Duas gerações erradas antes desta, e as duas erraram do mesmo jeito — dependendo de
  // um nome, só cada vez mais acima:
  //   1ª  casava a string "cli-moduletopnav"  → não via .pd-abas, .hrm-tabs, .gov-tabs…
  //   2ª  casava "<nav"                       → não via .os-tabs, montada em <div>
  //   2ª  casava sufixo "-seg"                → não via .rel-dens, .fin-density, .vd-vista
//   3ª  casava palavra de estado (on|active) → não via o segmented de Boletos, cujo
//                                              ativo é "bg-white shadow-sm" em utilitária
  // Nas duas vezes a instância que escapou estava VIVA no app e foi achada varrendo o
  // DOM, não o código. A lição não é "melhorar o regex": é que qualquer casamento por
  // nome só encontra o que já foi catalogado. Esta versão casa a FORMA:
  //
  //   um elemento (qualquer tag) com 2+ <button> irmãos + alternância de estado ativo
  //   (classe on|active|act|selected  ou  aria-selected|aria-pressed|aria-current)
  //
  // Aba vs segmented, sem adivinhar: quem tem CONTADOR por item, ou 6+ itens, é fileira
  // de aba (recorte de lista); 2–5 itens sem contador é segmented (visão/densidade/período).
  // Errar entre os dois é barato — os dois têm dono e a mensagem cita os dois.
  if (ext === ".jsx" && !eDono(DONOS.abas) && !eDono(DONOS.segmented)) {
    // ESTADO ATIVO — 4ª e última geração deste detector. As três anteriores dependiam
    // de um vocabulário de nomes, só cada vez mais acima: a string da classe → a tag
    // <nav> → o sufixo -seg → e agora se descobriu que também as PALAVRAS de estado
    // (on|active|selected). Controle estilizado por utilitárias Tailwind não usa nenhuma:
    // o ativo é `bg-white shadow-sm text-stone-900` e o inativo `text-stone-600` —
    // classes que não têm palavra de estado nenhuma. Foi assim que o segmented de
    // Boletos (5 opções, paleta stone crua, zero a11y) passou por todas as versões.
    // Família inteira no projeto: boletos-page, pg-vendas-integration,
    // pg-sells-cobranca-preview, prototipos/payment-gateway-ui/cobranca-page.
    //
    // O sinal que NÃO depende de vocabulário: a className DIVERGE entre irmãos por um
    // ternário. Se dois botões da mesma série são estilizados de forma diferente segundo
    // uma condição, existe estado selecionado — não importa como as classes se chamam.
    const ATIVO = /class[nN]ame=\{?["'`][^"'`]*\b(on|active|act|selected)\b|aria-(selected|pressed|current)=/;
    // Ternário em className é comum demais pra servir de sinal sozinho (39 achados, a
    // maioria `cn(..., cond ? "a" : "b")` de qualquer coisa). O sinal específico é o
    // ternário cuja condição compara ESTADO × ITEM DO LOOP — `tab === t.id` — porque é
    // isso que significa "este irmão está selecionado". Medido: 39 → 26 achados reais.
    const TERNARIO = /class[nN]ame=\{[^}]{0,400}?\b(\w+)\s*===?\s*(\w+\.\w+|\w+)\s*\?/;
    const temEstado = (b) => {
      if (ATIVO.test(b)) return true;
      const m = b.match(TERNARIO);
      return !!m && m[1] !== m[2];   // identificador × identificador, não literal
    };
    // SÉRIE — o discriminador que separa "escolha entre irmãos" de "linha de ação".
    // Sem ele o guard deu 26 achados com ~20 falsos positivos (Cancelar/Salvar em rodapé
    // de drawer, ações de linha, bulkbar). Guard que grita demais é guard ignorado, então
    // isto foi MEDIDO, não intuído: 26 → 8 achados, e os 8 eram todos reais ou
    // classificáveis. Uma escolha é sempre uma série: ou um .map() sobre a lista de
    // opções, ou a mesma variável de estado comparada 2+ vezes.
    const serie = (b) => {
      if (/\.map\(/.test(b)) return true;
      const cont = {};
      for (const x of b.matchAll(/(\w+)\s*===?\s*["'][\w-]+["']/g)) cont[x[1]] = (cont[x[1]] || 0) + 1;
      return Object.values(cont).some((n) => n >= 2);
    };
    const ABRE = /<(\w[\w.]*)([^>]*)>/g;
    let m;
    while ((m = ABRE.exec(txt))) {
      const tag = m[1];
      if (!/^(div|span|nav|ul|section|header|footer)$/i.test(tag)) continue;
      const fecha = txt.indexOf("</" + tag + ">", m.index);
      if (fecha === -1) continue;
      const bloco = txt.slice(m.index, fecha);
      // Só o nível imediato: um <div> de página contém botões demais para significar algo.
      if (bloco.length > 1400) continue;
      const botoes = (bloco.match(/<button/g) || []).length;
      if (botoes < 2) continue;
      if (!temEstado(bloco)) continue;                     // sem estado ativo não é escolha
      if (!serie(bloco)) continue;                         // linha de ação, não escolha
      if (/CliTabs|CliSeg|<TabBar|<Segmented/.test(bloco)) continue; // já é do dono
      // Dropdown/listbox tem estado "selecionado" mas não é aba nem segmented.
      if (/role="(listbox|option|menu|menuitem)"|aria-haspopup/.test(bloco)) continue;
      const attrs = m[2] || "";
      const cls = ((attrs.match(/class[nN]ame=\{?["'`]([^"'`]*)/) || [])[1] || "").trim();
      // Vocabulários que NÃO são aba/segmented (medido no build, 2026-09-01): passo de
      // fluxo, trilha de navegação, rail vertical, linha de ação, rodapé de drawer.
      // Ampliado com o que a varredura de 2026-09-01 mostrou não ser escolha entre
      // irmãos: paginação (mfg-pag), navegação de apresentação (…-pres-bot, prev/next),
      // barra de ação em lote, toolbar e linha de filtros com botões heterogêneos.
      if (/(stepper|bcrumb|breadcrumb|rail|sb-menu|row-actions|actions|acts|foot|bulkbar|fdrop|table-wrap|bulk|toolbar|filters|kpirow|chipsrow|flt-group|-pag\b|pres-bot|os-head|wrap$)/i.test(cls)) continue;
      // O ROLE decide quando existe. Sem isto o nome enganava nos dois sentidos:
      // .fx-chips (fiscal-subpages) era role="tablist" com 2 abas de verdade, e
      // .om-mobile-tabs tinha "tabs" no nome sendo... também tablist. Nome é pista fraca.
      const temContador = /-n"|-n }|-count|-ct"|count[:=]|\bn:/.test(bloco);
      const ehAba = /role="tablist"/.test(bloco) ? true : (temContador || botoes >= 6);
      const regra = ehAba ? "R1" : "R2";
      if (temWaiver(nome, regra)) continue;
      reporta(regra, "erro", p, linhaDe(txt, m.index),
        "monta " + (ehAba ? "uma fileira de aba" : "um segmented") + " própria em <" + tag + ">" +
        (cls ? " (." + cls + ")" : "") + " — " + botoes + " botões com estado ativo. Dono: window." +
        (ehAba ? "CliTabs" : "CliSeg") + ".");
    }
  }

  // R6 — TabBar/Segmented do DS usado DIRETO, sem passar pelo dono.
  //
  // Terceira categoria, achada em 2026-09-01: não é pele paralela (o componente é o
  // certo), mas passa por fora do adaptador e por isso perde tudo que o adaptador
  // garante. Sintomas medidos em 14 call sites: NENHUM passava `ariaLabel`, então toda
  // fileira do app se anunciava como "Sub-navegação" pro leitor de tela; e vários
  // embrulhavam a barra num <div> só pra dar padding — o anti-padrão que o DS acabou de
  // eliminar com `inset` (o wrapper come a border-bottom e entra na chain de overflow).
  //
  // Também pega um caso que me passou batido: arquivos-page tinha DOIS caminhos no mesmo
  // ponto (TabBar direto no ramo vivo, <nav> bespoke no fallback morto). Eu migrei o
  // morto e deixei o vivo. Um dono por peça vale por ponto de render, não por arquivo.
  if (ext === ".jsx" && !eDono(DONOS.abas) && !eDono(DONOS.segmented)) {
    for (const peca of ["TabBar", "Segmented"]) {
      const re = new RegExp("<" + peca + "\\b", "g");
      let m;
      while ((m = re.exec(txt))) {
        const props = txt.slice(m.index, m.index + 400);
        if (temWaiver(nome, "R6")) continue;
        reporta("R6", "erro", p, linhaDe(txt, m.index),
          "usa <" + peca + "> do DS direto, fora do dono" +
          (/ariaLabel/.test(props) ? "" : " (e sem ariaLabel: a barra se anuncia como \"Sub-navegação\")") +
          ". Passe por window." + (peca === "TabBar" ? "CliTabs" : "CliSeg") + ".");
      }
    }
  }

  // R7 — componente LOCAL com o nome de uma peça que o DS publica.
  //
  // A auditoria de 2026-09-01 achou SEIS `function Kebab({items})` praticamente
  // idênticas (modulos, officeimpresso, superadmin, comissionados, usuarios, connector):
  // mesmo estado, mesmo listener de mousedown, mesmo SVG de três pontos, mesma pele.
  // Nenhuma navegava por teclado; o `Kebab` do DS navega. R4/R5 não pegavam isso porque
  // essas cópias NÃO publicam em window — são funções locais de módulo.
  //
  // O sinal: uma `function <Nome>` cujo nome bate com um componente do DS e cujo corpo
  // NÃO delega (não menciona o namespace do DS nem um adaptador Cli*). Delegar é o
  // padrão correto e fica isento.
  if (ext === ".jsx") {
    const re = /function\s+([A-Z][A-Za-z0-9_]*)\s*\(/g;
    let m;
    while ((m = re.exec(txt))) {
      const n = m[1];
      if (!DS_COMPONENTES.has(n)) continue;
      const corpo = txt.slice(m.index, m.index + 900);
      if (/OfficeImpressoPontoWR2DesignSystem_019dd0|window\.Cli[A-Z]|DS\(\)/.test(corpo)) continue;
      if (temWaiver(nome, "R7")) continue;
      reporta("R7", "erro", p, linhaDe(txt, m.index),
        "define um `" + n + "` local, e o Design System publica `" + n + "`. " +
        "Delegue (adaptador Cli" + n + ") ou use o do DS — cópia local de peça do DS " +
        "envelhece sozinha e perde o que o DS ganha (teclado, a11y, tokens).");
    }
  }

  // R3/R4/R5 — publicação de nomes em window
  if (ext === ".jsx" || ext === ".js") {
    const re = /window\.([A-Za-z_][A-Za-z0-9_]*)\s*=(?!=)/g;
    let m;
    while ((m = re.exec(txt))) {
      const n = m[1];
      if (!publicados.has(n)) publicados.set(n, []);
      if (!publicados.get(n).includes(p)) publicados.get(n).push(p);

      if (/(UI|DS)$/.test(n) && !MINI_DS_OK.has(n)) {
        reporta("R3", "erro", p, linhaDe(txt, m.index),
          "publica uma biblioteca nova (window." + n + "). Mini-DS paralela não cresce: estenda o Design System ou um dono existente.");
      }
      if (DS_COMPONENTES.has(n)) {
        reporta("R5", "erro", p, linhaDe(txt, m.index),
          "publica window." + n + ", que É um componente do Design System. Prefixe (window.Oi" + n + ") ou use o do DS — dois donos do mesmo nome quebram por ordem de carga.");
      }
    }
  }
}

for (const [n, onde] of publicados) {
  if (n.startsWith("__")) continue; // handoff interno (ex: __PBFiltro), não peça publicada
  if (onde.length > 1) {
    reporta("R4", "erro", onde[1], 0,
      "window." + n + " é publicado por " + onde.length + " arquivos: " + onde.join(", ") + ". Um nome, um dono.");
  }
}

const erros = achados.filter((a) => a.nivel === "erro");
if (JSON_OUT) {
  console.log(JSON.stringify({ dir: DIR, arquivos: arquivos.length, achados }, null, 2));
} else {
  console.log("cowork-pele-paralela · " + arquivos.length + " arquivos em " + DIR);
  if (!achados.length) console.log("  nenhuma pele paralela nova. ✔");
  for (const a of achados) console.log("  [" + a.regra + "] " + a.arquivo + (a.linha ? ":" + a.linha : "") + " — " + a.msg);
  if (erros.length) {
    console.log("\n" + erros.length + " problema(s). Cada um tem saída conhecida:");
    console.log("  R1 → <window.CliTabs …>   R2 → <window.CliSeg …>   R3 → estender o DS");
    console.log("  R4 → apagar a segunda publicação   R5 → prefixar com Oi");
  }
}
process.exit(!AVISO_SO && erros.length ? 1 : 0);
