// documentacao-page.jsx — tela Documentação (rota `documentacao`).
// ÂNCORA: domínio (entidade) → fluxo → tela. Módulo é faceta, não pasta.
// Conteúdo espelha `memory/GUIA-DO-SISTEMA.md` + corpus mcp_memory_documents
// (types adr/reference/spec/runbook). O doc do git é o DONO — aqui é leitura.
(() => {
const { useState, useMemo, useEffect, useRef } = React;
const DS = window.OfficeImpressoPontoWR2DesignSystem_019dd0 || {};
const BLOB = "https://github.com/wagnerra23/oimpresso.com/blob/main/";

// ── DOCS ─────────────────────────────────────────────────────────
const D = [
{ id:"guia", grp:"start", n:"00", nav:"O mapa em uma página", title:"O oimpresso em uma página",
  sub:"ERP brasileiro multi-tenant, modular especializado por vertical: núcleo comum + módulos onde há cliente real. Construído sobre UltimatePOS v6.",
  type:"reference", auth:"canonical", upd:"2026-08-02", git:"memory/GUIA-DO-SISTEMA.md",
  rel:["ADR 0121 — modular especializado por vertical","ADR 0094 — Constituição v2","ADR 0062 — separação de runtime"],
  blocks:[
  {k:"alert",tone:"info",title:"Este doc é mapa, não cópia",body:"Ele aponta pras fontes vivas. Se um número aqui divergir da fonte linkada, <b>a fonte manda</b>. Estado vivo (cycle, tasks, brief) nunca vem daqui — vem das tools MCP."},
  {k:"h2",t:"As quatro camadas"},
  {k:"p",t:"Camada de cima <b>herda</b> da de baixo e <b>nunca contradiz</b>. É o modelo mental que resolve 80% das dúvidas de \"onde isso mora\"."},
  {k:"table",head:["Camada","O que é","Exemplos"],rows:[
    ["<b>Governança</b>","as leis","Constituição v2 · ADRs · Skills · Trust Tiers"],
    ["<b>Verticais</b>","produto vendável por setor","Vestuário ✅ · ComunicaçãoVisual 🟡 · OficinaAuto 🟡"],
    ["<b>Núcleo</b>","comum a todos","Jana IA · Financeiro · NF-e/NFS-e · Repair (OS) · FSM Pipeline"],
    ["<b>Kernel</b>","base multi-tenant","UltimatePOS + <code>business_id</code>"]]},
  {k:"h2",t:"Stack canônica"},
  {k:"ul",items:["Laravel 13.6 + PHP 8.4 · MySQL · Inertia v3 + React 19 + Tailwind 4 · Pest v4",
    "<code>nWidart Modules</code> em <code>Modules/&lt;Nome&gt;/</code> — lista viva no PAINEL-SISTEMA (derivada da árvore, não escrita à mão)",
    "IA: <code>laravel/ai</code> (camada A) + Agents Jana (B) + memória Meilisearch/Ollama (C)"]},
  {k:"h2",t:"Onde roda (Tier 0 irrevogável)"},
  {k:"table",head:["Ambiente","O que roda","Nunca"],rows:[
    ["<b>Hostinger</b> (shared)","ERP web + <code>git pull</code> de deploy","daemons, octane, laravel/mcp, teste pesado"],
    ["<b>CT 100</b> Proxmox","FrankenPHP · Centrifugo · Meilisearch · MCP server · Ollama · testes/PHPStan","—"],
    ["<b>GitHub main</b>","fonte de verdade do código + <code>memory/</code>","—"]]},
  {k:"h2",t:"Verticais — estado"},
  {k:"table",head:["Vertical","CNAE","Status","Piloto"],rows:[
    ["<b>Vestuário</b>","4781-4/00","em produção","ROTA LIVRE (Larissa, biz=4, 99% do volume)"],
    ["<b>ComunicaçãoVisual</b>","1813-0/01","em construção","6 candidatos OfficeImpresso"],
    ["<b>OficinaAuto</b>","4520-0/01","piloto LIVE (biz=164)","Martinho — reparo/mecânica, nunca locação"]]},
  {k:"p",t:"Teste automatizado usa o tenant fictício <code>biz=98</code> — nunca o biz=4 do cliente, nunca mais o biz=1 (a WR2, empresa real)."}]},

{ id:"estagio", grp:"dominio", n:"01", nav:"Estágio (FSM)", title:"Estágio — o portão único",
  sub:"Nenhuma entidade muda de estado por atribuição. Toda transição é uma ação nomeada que atravessa o mesmo portão — e é isso que torna a operação auditável.",
  type:"spec", auth:"canonical", upd:"2026-05-12", git:"memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md",
  rel:["ADR 0143 — FSM Pipeline live em prod","ADR 0093 — isolamento multi-tenant Tier 0"],
  blocks:[
  {k:"h2",t:"Contrato"},
  {k:"kv",rows:[["escrita permitida por","<code>ExecuteStageActionService</code> — e mais nada"],
    ["bloqueado","<code>UPDATE</code> direto em <code>current_stage_id</code>"],
    ["autorização","papel por empresa (<code>business_id</code>), checado na ação"],
    ["efeitos","isolados e com nome: <code>ReservarEstoque</code> · <code>ConsumirEstoque</code> · <code>LiberarReserva</code>"],
    ["histórico","append-only — transição não se reescreve"]]},
  {k:"alert",tone:"warn",title:"A regra que mais dói quando ignorada",body:"Mover o cartão no Kanban <b>é</b> executar a ação. Não existe \"só arrastar pra organizar\": se o estágio mudou, os efeitos rodaram."},
  {k:"h2",t:"Invariantes"},
  {k:"ul",items:["Uma ação por passo — nunca dois estágios adiante.","Emissão fiscal é amarrada ao estágio, não a um botão avulso.","Sem papel autorizado, a ação não aparece na UI <b>e</b> é negada no serviço.","Efeito que falha não deixa estágio meio-mudado: ou tudo, ou nada."]}]},

{ id:"venda", grp:"dominio", n:"02", nav:"Venda", title:"Venda — do rascunho à conclusão",
  sub:"Nasce rascunho de orçamento e caminha até concluída. Cada passo é uma ação nomeada com papel autorizado por empresa.",
  type:"spec", auth:"canonical", upd:"2026-07-18", git:"memory/reference/SPEC-VENDAS.md",
  rel:["ADR 0143 — FSM Pipeline","ADR 0121 — verticais"],
  blocks:[
  {k:"fsm",label:"Pipeline da venda",hue:295,steps:[["Rascunho","done"],["Orçamento","done"],["Aprovada","current"],["Produção","todo"],["Faturada","todo"],["Concluída","todo"]]},
  {k:"h2",t:"O que cada estágio libera"},
  {k:"table",head:["Estágio","Efeito","Fiscal"],rows:[
    ["Rascunho","nenhum","—"],
    ["Orçamento","cálculo por m² congela o preço","—"],
    ["Aprovada","<code>ReservarEstoque</code>","—"],
    ["Produção","gera OP; consumo por apontamento","—"],
    ["Faturada","<code>ConsumirEstoque</code>","NF-e emitida <b>pelo estágio</b>"],
    ["Concluída","baixa reserva residual","—"]]},
  {k:"h2",t:"Quem escreve"},
  {k:"kv",rows:[["balcão (Larissa)","rascunho → orçamento → aprovada"],["produção","produção → pronto"],["financeiro (Eliana)","faturada · recebimento"],["Jana","propõe, nunca executa transição"]]}]},

{ id:"os", grp:"dominio", n:"03", nav:"Ordem de serviço", title:"Ordem de serviço — mesma mecânica, vocabulário de oficina",
  sub:"Recebido para diagnóstico → orçamento → aprovação → execução → entrega. O Kanban é a projeção visual desses estágios.",
  type:"spec", auth:"canonical", upd:"2026-07-30", git:"memory/reference/SPEC-REPAIR-OS.md",
  rel:["ADR 0265 — Oficina é reparo, erradica locação","ADR 0143 — FSM Pipeline"],
  blocks:[
  {k:"fsm",label:"Pipeline da OS",hue:295,steps:[["Recepção","done"],["Diagnóstico","done"],["Orçamento","done"],["Aprovação","current"],["Execução","todo"],["Entrega","todo"]]},
  {k:"h2",t:"Contrato"},
  {k:"kv",rows:[["identidade no balcão","placa Mercosul + veículo, não número interno"],["diagnóstico","Vistoria Digital anexa antes do orçamento"],["aprovação","registro de quem aprovou e quando — exigência de litígio"],["execução","apontamento por box/elevador"],["entrega","checklist assinado libera a baixa"]]},
  {k:"alert",tone:"danger",title:"Nunca locação",body:"OficinaAuto é <b>reparo e mecânica</b>. Vocabulário de locação foi erradicado por decisão — se aparecer na UI, é bug de cópia velha."}]},

{ id:"nota", grp:"dominio", n:"04", nav:"Nota fiscal", title:"Nota fiscal — o número é da lei, não do sistema",
  sub:"Cancelar não é DELETE: cancela na SEFAZ preservando o número sequencial, porque a lei o considera usado.",
  type:"spec", auth:"canonical", upd:"2026-06-11", git:"memory/reference/SPEC-FISCAL-NFE.md",
  rel:["Art. 66 CLT — n/a","Portaria SEFAZ · manual NF-e 6.0","LGPD Art. 7º — aviso ao cliente"],
  blocks:[
  {k:"h2",t:"Invariantes"},
  {k:"ul",items:["Número sequencial <b>nunca</b> é reaproveitado, mesmo cancelado.","Emissão pertence ao estágio da venda — não há botão \"emitir\" solto.","Rejeição da SEFAZ é estado documentado, não erro silencioso.","Smoke fiscal manual roda em homologação com certificado do próprio [W]."]},
  {k:"h2",t:"Cancelamento — a cascata"},
  {k:"table",head:["Passo","Sistema","Se falhar"],rows:[
    ["cancela na SEFAZ","fiscal","para tudo; nada mais é executado"],
    ["estorna no gateway","PaymentGateway","fica pendente com alerta, não some"],
    ["devolve estoque","<code>LiberarReserva</code>","reconciliação manual registrada"],
    ["avisa o cliente","Inbox","<b>só se ele consentiu</b> (LGPD)"]]}]},

{ id:"titulo", grp:"dominio", n:"05", nav:"Título financeiro", title:"Título — a entrada e a saída de dinheiro",
  sub:"Entrada ou saída, com vencimento, status de pagamento e valor sinalizado. É o que o fechamento do mês soma.",
  type:"spec", auth:"canonical", upd:"2026-07-02", git:"memory/reference/SPEC-FINANCEIRO.md",
  rel:["ADR 0144 — PaymentGateway","Cobrança recorrente — RecurringBilling"],
  blocks:[
  {k:"h2",t:"Campos que importam"},
  {k:"kv",rows:[["tipo","entrada · saída (nunca \"crédito/débito\" na UI cliente)"],["vencimento","<code>dd/mm/aaaa</code>, tabular"],["status","aberto · parcial · pago · vencido"],["origem","venda · OS · assinatura · lançamento manual"],["valor","<code>R$ 12.480</code> — sinal pelo tipo, nunca por cor sozinha"]]},
  {k:"alert",tone:"warn",title:"Vencido é derivado",body:"Nunca existe um status \"vencido\" gravado: é <code>aberto</code> + data passada. Gravar apodrece na virada da meia-noite."}]},

{ id:"fl-venda", grp:"fluxo", n:"06", nav:"Uma venda", title:"Fluxo: uma venda, de ponta a ponta",
  sub:"A travessia completa — quem toca, em qual tela, com qual efeito. Leia isto no primeiro dia.",
  type:"runbook", auth:"canonical", upd:"2026-07-18", git:"memory/how-trabalhar.md",
  rel:["Venda (domínio)","Estágio (FSM)","Título financeiro"],
  blocks:[
  {k:"h2",t:"A travessia"},
  {k:"table",head:["#","Quem / onde","Ação","Vira"],rows:[
    ["1","Larissa · Vendas → novo","monta itens, cálculo por m²","rascunho"],
    ["2","Larissa · Orçamento","envia ao cliente","orçamento"],
    ["3","cliente · Inbox","aprova (registro datado)","aprovada + reserva"],
    ["4","produção · Board","OP entra na fila","em produção"],
    ["5","Eliana · Financeiro","fatura","NF-e + título"],
    ["6","balcão · Entrega","baixa e entrega","concluída"]]},
  {k:"p",t:"<b>O fio que une:</b> portão único, efeitos com nome próprio, histórico que não se reescreve, permissão por empresa. Quando algo dá errado, <i>\"o que aconteceu aqui?\"</i> tem resposta."}]},

{ id:"fl-cancel", grp:"fluxo", n:"07", nav:"Um cancelamento", title:"Fluxo: um cancelamento",
  sub:"O caso difícil. Quatro sistemas, uma ordem obrigatória, e uma regra de consentimento.",
  type:"runbook", auth:"canonical", upd:"2026-06-11", git:"memory/how-trabalhar.md",
  rel:["Nota fiscal","Título financeiro","LGPD Art. 7º"],
  blocks:[
  {k:"fsm",label:"Ordem obrigatória",hue:25,steps:[["SEFAZ","done"],["Gateway","current"],["Estoque","todo"],["Aviso","todo"]]},
  {k:"p",t:"A ordem não é preferência: se o cancelamento fiscal falha, <b>nada</b> depois roda — estornar dinheiro de uma nota válida é pior que não estornar."},
  {k:"ul",items:["Número sequencial preservado (a lei o considera usado).","Estorno no gateway com id de correlação — nunca \"estorno manual\" sem rastro.","Estoque volta por <code>LiberarReserva</code>, não por ajuste de inventário.","Aviso ao cliente <b>só com consentimento</b> registrado."]}]},

{ id:"fl-deploy", grp:"fluxo", n:"08", nav:"Um deploy", title:"Fluxo: um deploy",
  sub:"Merge em main que toque código dispara build, manutenção, migrations, reset de opcode e smoke em /login.",
  type:"runbook", auth:"canonical", upd:"2026-07-25", git:"memory/reference/INFRA-ACESSO-CANON.md",
  rel:["ADR 0062 — separação de runtime","Health-check diário"],
  blocks:[
  {k:"fsm",label:"Pipeline de deploy",hue:220,steps:[["Merge","done"],["Build (runner)","done"],["Manutenção","done"],["Migrations","current"],["Opcode + smoke","todo"],["Live","todo"]]},
  {k:"alert",tone:"info",title:"Doc-only não faz deploy",body:"Mudança que só toca <code>memory/</code> ou markdown <b>não</b> dispara pipeline. Se o boot falhar, um failsafe segura um 503 gracioso em vez de servir página quebrada."}]},

{ id:"tec-arq", grp:"tec", nav:"Arquitetura & módulos", title:"Arquitetura — camadas, módulos, limites",
  sub:"Laravel 13.6 + PHP 8.4 sobre UltimatePOS v6, modularizado com nWidart. Camada de cima só invoca primitivo da de baixo — nunca o contrário.",
  type:"reference", auth:"canonical", upd:"2026-07-28", git:"memory/governance/ARCHITECTURE.md",
  rel:["ADR 0001 — estender UltimatePOS (opção C)","ADR 0002 — nWidart Modules","ADR 0094 — Constituição v2"],
  blocks:[
  {k:"h2",t:"Anatomia de um módulo"},
  {k:"p",t:"Todo módulo vive em <code>Modules/&lt;Nome&gt;/</code> e é auto-contido: rotas, migrations, Entities, serviços e views próprias. Nada de código de vertical vazando pro núcleo."},
  {k:"kv",rows:[["Config/","config do módulo, registrado no <code>module.json</code>"],["Database/Migrations/","schema próprio; nunca altera tabela de outro módulo"],["Entities/","Models Eloquent com <code>business_id</code> no global scope"],["Http/Controllers/","finos — regra vive em serviço"],["Services/","onde mora a regra de negócio (ex: <code>ExecuteStageActionService</code>)"],["Resources/views/","Blade legado + páginas Inertia em <code>resources/js/Pages/</code>"],["Tests/","Pest v4, tenant fictício <code>biz=98</code>"]]},
  {k:"h2",t:"Limites que o CI enforça"},
  {k:"ul",items:["Módulo <b>não</b> importa Entity de outro módulo direto — passa por contrato/serviço.","Kernel (UltimatePOS) não conhece vertical: dependência é sempre de cima pra baixo.","<code>UPDATE</code> em <code>current_stage_id</code> é bloqueado — só o serviço de FSM escreve estágio.","~6.400 usos de <code>Form::</code> em Blade legado seguem vivos atrás de um shim; não migre em massa sem ADR."]},
  {k:"alert",tone:"info",title:"Lista de módulos é derivada",body:"Nunca escreva a lista à mão: <code>git ls-tree -d --name-only HEAD Modules/</code> é a fonte, e o PAINEL-SISTEMA é o retrato gerado por <code>system-map.mjs</code>."}]},

{ id:"tec-dados", grp:"tec", nav:"Dados & multi-tenant", title:"Dados — multi-tenant é Tier 0",
  sub:"business_id como global scope obrigatório. Vazar dado entre tenants é o pior bug possível do sistema — pior que perder dado.",
  type:"spec", auth:"canonical", upd:"2026-06-30", git:"memory/decisions/0093-multi-tenant-isolation-tier-0.md",
  rel:["ADR 0093 — isolamento Tier 0","ADR 0358 — doutrina de teste tenant 98","ADR 0141 — tool-use pattern"],
  blocks:[
  {k:"h2",t:"Regras de escrita"},
  {k:"ul",items:["Todo Model tenant-scoped declara o global scope — sem exceção \"só nesse relatório\".","Query crua (<code>DB::</code>) exige <code>where business_id</code> explícito e revisão.","O <code>business_id</code> de uma tool de IA vem do <b>construtor</b>, nunca do modelo: se o LLM injetar outro, a tool ignora.","Migration nova nasce com <code>business_id</code> + índice composto; sem isso o CI reprova."]},
  {k:"h2",t:"Tenants de referência"},
  {k:"table",head:["biz","Quem","Uso"],rows:[
    ["<code>4</code>","ROTA LIVRE (Larissa)","produção — 99% do volume; <b>nunca</b> alvo de teste"],
    ["<code>164</code>","Martinho (Oficina)","piloto LIVE de OficinaAuto"],
    ["<code>1</code>","WR2","empresa real; só smoke fiscal manual em homologação"],
    ["<code>98</code>","fictício","<b>o único</b> alvo de teste automatizado"]]},
  {k:"alert",tone:"danger",title:"Nunca teste em tenant real",body:"Rodar seed ou factory em biz=4 contamina o cliente que paga. A doutrina de teste é tenant 98 — e ela supersedeu a regra anterior justamente porque biz=1 é empresa real."}]},

{ id:"tec-front", grp:"tec", nav:"Front-end & tokens", title:"Front-end — Inertia, React 19 e os tokens",
  sub:"Inertia v3 + React 19 + TypeScript + Tailwind 4. Cor, tipo e espaçamento nunca são literais: vêm de tokens DTCG compilados por Style Dictionary.",
  type:"reference", auth:"canonical", upd:"2026-07-10", git:"memory/decisions/0239-ds-git-ssot.md",
  rel:["ADR 0239 — DS git SSOT","ADR 0190/0235 — primary roxo hue 295","ADR 0300 — errata de tokens"],
  blocks:[
  {k:"h2",t:"Cadeia dos tokens"},
  {k:"fsm",label:"De onde a cor vem",hue:295,steps:[["tokens.json (DTCG)","done"],["Style Dictionary","done"],["CSS vars","current"],["Tela","todo"]]},
  {k:"ul",items:["<code>npm run tokens:build</code> roda antes de <code>dev:inertia</code> e <code>build:inertia</code> — o CSS de token é <b>gerado</b>, não editado.","App operacional herda a paleta com <code>&lt;html class=\"cockpit\"&gt;</code>: <code>--bg</code>, <code>--surface</code>, <code>--border</code>, <code>--text</code>, <code>--accent</code>.","Primary roxo <code>oklch(0.55 0.15 295)</code>. Cor crua em componente é erro de lint, não questão de gosto.","IBM Plex Sans/Mono self-hosted — sem CDN, sem FOUT, imprime igual."]},
  {k:"h2",t:"Proibições de UI"},
  {k:"ul",items:["Sem modal full-screen pra detalhe — detalhe é <b>drawer lateral</b> (PT-02).","Sem inglês em UI cliente-facing; sem emoji no app.","Sem <code>rounded-xl</code> pra cima em card de lista; sem paleta inventada.","Densidade de ERP: tabela apertada, sidebar <code>py-2</code>, número tabular."]}]},

{ id:"tec-tela", grp:"tec", nav:"Contrato de tela", title:"Contrato de tela — o trio que trava o comportamento",
  sub:"Uma tela só está pronta pra aplicação quando existe o trio: componente + charter + casos. O contrato declara seções, copy literal e estados — e o CI cobra.",
  type:"spec", auth:"canonical", upd:"2026-07-22", git:"prototipo-ui/PRE-FLIGHT-TELA.md",
  rel:["ADR 0286 — Contrato de Tela","ADR 0114 — loop Cowork formalizado"],
  blocks:[
  {k:"h2",t:"O trio"},
  {k:"kv",rows:[["<code>&lt;Tela&gt;.tsx</code>","a implementação — Inertia page em <code>resources/js/Pages/&lt;Mod&gt;/</code>"],["<code>&lt;Tela&gt;.charter.md</code>","o que a tela é: propósito, persona, densidade, o que NÃO faz"],["<code>&lt;Tela&gt;.casos.md</code>","casos de uso numerados (UC) — a régua de aceite"],["<code>*.contract.json</code>","seções + copy literal + estados; quebra o CI se a tela divergir"]]},
  {k:"h2",t:"Pré-flight (antes de mexer)"},
  {k:"ul",items:["Ler <code>SPEC.md</code> + <code>RUNBOOK*.md</code> do módulo e o charter da tela.","Checar frescor: 🟠 desenvolver · 🔵 puxar o vivo (não refazer) · ⚪ fundação (espera [W]).","Não inventar token, Model ou componente que já existe — estender, nunca recriar.","Prontidão é <b>máquina</b>: <code>scripts/qa/prototipo-readiness.mjs</code>, não fila manual."]},
  {k:"alert",tone:"warn",title:"Variação é tweak, não arquivo",body:"Explorar duas versões de uma tela = <code>useTweaks</code> no mesmo componente. Arquivo novo por variação vira cópia que apodrece — o guard reprova."}]},

{ id:"tec-qa", grp:"tec", nav:"Qualidade & CI", title:"Qualidade — o que o CI cobra de verdade",
  sub:"Pest v4 nos testes, baselines pra dívida existente, guards pra doutrina. A régua não é opinião: é script com nome.",
  type:"runbook", auth:"canonical", upd:"2026-07-28", git:"package.json",
  rel:["ADR 0256 — derivado e enforçado sobrevive","ADR 0358 — tenant 98"],
  blocks:[
  {k:"h2",t:"Comandos que importam"},
  {k:"table",head:["Comando","O que garante"],rows:[
    ["<code>npm run typecheck</code>","TS sem erro (<code>tsc --noEmit</code>)"],
    ["<code>lint:baseline:check</code>","ESLint não <b>piora</b> — dívida antiga congelada, nova barrada"],
    ["<code>stylelint:baseline:check</code>","CSS idem, com baseline própria"],
    ["<code>css:size:check</code>","orçamento de bytes do CSS não estoura"],
    ["<code>pageheader:guard</code>","migração de PageHeader não regride"],
    ["<code>ds:report</code>","conformância com o DS (cor crua, radius, token)"],
    ["<code>docs:loop</code>","snapshot do loop de documentação — doc órfão aparece"],
    ["<code>handoff:check</code>","integridade do handoff append-only"]]},
  {k:"h2",t:"Doutrina de teste"},
  {k:"ul",items:["Pest v4; teste roda no <b>CT 100</b>, nunca no shared da Hostinger.","Tenant de teste é <code>98</code> — factory jamais aponta pra cliente.","Eval de IA roda o pipeline de verdade; a versão tautológica (gabarito contra si mesmo) foi morta.","Baseline não é permissão pra sujar: é catraca — só desce."]}]},

{ id:"tec-mcp", grp:"tec", nav:"MCP & agentes", title:"MCP & agentes — o que um agente pode fazer",
  sub:"O servidor MCP expõe o conhecimento canônico do memory/ como tools. Cada ator tem manifesto com trust level L0–L4: sem manifesto, sem ação.",
  type:"reference", auth:"canonical", upd:"2026-08-02", git:"memory/decisions/0053-mcp-server-governanca-como-produto.md",
  rel:["ADR 0053 — MCP server como produto","ADR 0081 — Identity Mesh","ADR 0114 · 0282 — contrato de agente"],
  blocks:[
  {k:"h2",t:"Tools de bolso"},
  {k:"kv",rows:[["<code>brief-fetch</code>","1ª coisa da sessão: estado consolidado (~3k tokens)"],["<code>my-work</code> / <code>my-inbox</code>","tasks e notificações do ator"],["<code>decisions-search</code>","busca no corpus de ADRs"],["<code>cycles-active</code>","ciclo em andamento — estado vivo, nunca markdown"]]},
  {k:"h2",t:"O que é negado no token"},
  {k:"ul",items:["<code>git.merge</code> — agente propõe, [W] merga. O merge <b>é</b> a ratificação.","<code>constituicao.edit</code> — lei não se edita por agente.","Ação sem manifesto: default-deny, sem exceção de conveniência.","Toda ação vai pra audit log imutável — inclusive a negada."]},
  {k:"alert",tone:"info",title:"Estado vivo nunca vem de markdown",body:"Cycle, tasks e brief mudam por hora. Ler isso de um <code>.md</code> é como ler o saldo bancário num extrato impresso na semana passada."}]},

{ id:"gov-adr", grp:"gov", nav:"Como decisão vira lei", title:"Como uma decisão vira lei",
  sub:"Propor é permitido a todos. Ratificar é do [W] — e o merge é o ato.",
  type:"reference", auth:"canonical", upd:"2026-08-02", git:"memory/decisions/0258-processo-adr-estado-arte-indice-gerado-supersede-atomico.md",
  rel:["ADR 0258 — processo ADR","ADR 0094 — Constituição v2","ADR 0114 — loop Cowork"],
  blocks:[
  {k:"fsm",label:"Ciclo da decisão",hue:295,steps:[["Proposta","done"],["ADR numerada","done"],["Ratificação [W]","current"],["Append-only","todo"]]},
  {k:"ul",items:["Proposta em <code>decisions/proposals/</code>, formato Nygard.","Vira ADR numerada com status <code>proposto</code>, no índice <b>gerado</b> (derivado do disco).","<b>[W] ratifica e o merge é o ato</b> — um PR que vira só a linha de status. Não há assinatura separada.","ADR aceita não se edita: gate de CI bloqueia. Mudou de ideia? Escreve outra com <code>supersedes</code>."]},
  {k:"p",t:"O efeito colateral é o mais valioso do projeto: existe um <b>registro datado do raciocínio</b>. \"Por que decidimos assim?\" não depende de lembrança."},
  {k:"alert",tone:"danger",title:"Linha vermelha do contrato de agente",body:"Agentes têm <b>ler</b> e <b>propor</b>. <code>git.merge</code> e <code>constituicao.edit</code> são negados no token. Propor é permitido; decidir o merge não é."}]},

{ id:"gov-conhec", grp:"gov", n:"10", nav:"Como o conhecimento é indexado", title:"Como o conhecimento é indexado",
  sub:"O repositório é a fonte; o índice é cache governado. Nunca o contrário.",
  type:"reference", auth:"canonical", upd:"2026-08-02", git:"memory/decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md",
  rel:["ADR 0061 — git+MCP, zero automem","ADR 0256 — meia-vida do conhecimento","ADR 0318 — eval real"],
  blocks:[
  {k:"table",head:["#","Etapa","Por quê"],rows:[
    ["1","Nasce em <code>memory/</code>, no git","versionado, revisável, com histórico. Zero memória privada do agente."],
    ["2","Webhook empurra pro MCP","<code>mcp_memory_documents</code>, PII redigida no caminho."],
    ["3","Dois índices convivem","FULLTEXT pro casamento exato; Meilisearch+embeddings pro sentido."],
    ["4","Recall reordenado","reranking + decaimento no tempo — senão a verdade de 6 meses atrás vem com a confiança da de ontem."],
    ["5","Qualidade contra gabarito real","a eval que comparava o gabarito consigo mesmo passava sempre, e foi morta por isso."]]},
  {k:"alert",tone:"info",title:"Doutrina",body:"<b>Derivado e enforçado sobrevive; escrito e lembrado apodrece.</b> É por isso que esta tela renderiza o documento dono em runtime, em vez de servir HTML commitado."}]},

{ id:"gov-obs", grp:"gov", n:"11", nav:"O que é observado", title:"O que é observado",
  sub:"Quatro instrumentos, cada um respondendo uma pergunta diferente. Saber qual abrir é metade do diagnóstico.",
  type:"reference", auth:"canonical", upd:"2026-07-20", git:"memory/GUIA-DO-SISTEMA.md",
  rel:["ADR 0132 — Langfuse","ADR 0162 — OTel collector","ADR 0333 — eixo rodar-e-observar sub-medido"],
  blocks:[
  {k:"table",head:["Pergunta","Instrumento","Onde"],rows:[
    ["A IA está cara, lenta ou alucinando?","<b>Langfuse</b> — trace por empresa","CT 100"],
    ["Onde o request gastou o tempo?","<b>Jaeger + OTel</b>","CT 100"],
    ["O sistema está saudável hoje?","<code>jana:health-check</code> — SQL diário","agendado"],
    ["Os módulos estão apodrecendo?","<b>vital-signs</b> — regerado à noite","governança"]]},
  {k:"alert",tone:"warn",title:"Ponto cego declarado",body:"A régua do projeto media bem <b>construir-e-governar</b> e mal <b>operar</b>. Está registrado, não esquecido."}]},

{ id:"corpus", grp:"corpus", n:"12", nav:"Buscar no corpus", title:"Corpus — ADR · reference · spec · runbook",
  sub:"Busca full-text no que o webhook do git sincronizou. Diário de bordo (session, handoff) fica fora de propósito: procurar “financeiro” não pode trazer 40 handoffs antes do briefing do módulo.",
  type:"reference", auth:"derivado", upd:"agora", git:"app/Http/Controllers/DocumentacaoController.php", corpus:true, rel:[]}
];

const GRPS=[["start","Comece aqui"],["dominio","Domínio — as entidades"],["fluxo","Fluxos de operação"],["tec","Técnico — construir"],["gov","Governança"],["corpus","Corpus"]];
// Ordem de leitura = ordem do array; a numeração é derivada (nunca escrita à mão).
const ORDER=["start","dominio","fluxo","tec","gov","corpus"];
D.sort((a,b)=>ORDER.indexOf(a.grp)-ORDER.indexOf(b.grp));
D.forEach((d,i)=>{d.n=String(i).padStart(2,"0")});

// Corpus fake-mas-real: docs citados no guia, com tipo/módulo pra faceta.
const CORPUS=[
 {t:"adr",m:"core",n:"ADR 0143",title:"FSM Pipeline live em prod",ex:"Toda mudança de estado de Venda/OS passa por ExecuteStageActionService; UPDATE direto em current_stage_id é bloqueado."},
 {t:"adr",m:"core",n:"ADR 0093",title:"Isolamento multi-tenant Tier 0",ex:"business_id como global scope obrigatório; vazar dado entre tenants é o pior bug possível."},
 {t:"adr",m:"oficina",n:"ADR 0265",title:"Oficina é reparo — erradica locação",ex:"OficinaAuto atende mecânica e reparo. Vocabulário de locação removido do domínio e da UI."},
 {t:"adr",m:"jana",n:"ADR 0035",title:"Stack de IA canônica",ex:"Três camadas: wrapper laravel/ai, agents próprios Jana, memória Meilisearch com embeddings Ollama."},
 {t:"adr",m:"infra",n:"ADR 0062",title:"Separação de runtime Hostinger / CT 100",ex:"Tier 0 irrevogável: daemons, octane e testes pesados nunca no shared."},
 {t:"adr",m:"core",n:"ADR 0256",title:"Sobrevivência do conhecimento",ex:"Derivado e enforçado sobrevive; escrito e lembrado apodrece. Meia-vida, catraca e sentinela."},
 {t:"adr",m:"fiscal",n:"ADR 0300",title:"Errata de tokens do DS",ex:"Primary roxo oklch(0.55 0.15 295) é canon; blue do shadcn foi superseded."},
 {t:"spec",m:"vendas",n:"SPEC-VENDAS",title:"Especificação de Vendas / PDV",ex:"Estágios, efeitos de estoque, amarração fiscal e papéis autorizados por empresa."},
 {t:"spec",m:"fiscal",n:"SPEC-FISCAL-NFE",title:"NF-e — emissão e cancelamento",ex:"Cancelamento preserva o número sequencial; rejeição da SEFAZ é estado documentado."},
 {t:"spec",m:"financeiro",n:"SPEC-FINANCEIRO",title:"Títulos, baixa e fechamento",ex:"Vencido é derivado de aberto + data passada, nunca gravado."},
 {t:"runbook",m:"infra",n:"RUNBOOK-DEPLOY",title:"Deploy e failsafe",ex:"Build no runner, manutenção, migrations, reset de opcode e smoke em /login; 503 gracioso se o boot falhar."},
 {t:"runbook",m:"ponto",n:"RUNBOOK-PONTO",title:"Fechamento de ponto (Portaria 671/2021)",ex:"Marcação, intercorrência e banco de horas; espelho de ponto por colaborador."},
 {t:"reference",m:"core",n:"PAINEL-SISTEMA",title:"Painel do sistema (gerado)",ex:"Retrato vivo de módulos, gates required, workflows e ADRs — derivado por system-map.mjs."},
 {t:"reference",m:"infra",n:"INFRA-ACESSO-CANON",title:"Acesso e deploy — canon",ex:"Tailscale, CT 100, credenciais em Vaultwarden, caminhos de deploy no Hostinger."},
 {t:"reference",m:"core",n:"ARCHITECTURE",title:"Arquitetura arc42 + C4",ex:"Mapa técnico: 30+ módulos, trust level por ator, runtime e limites de camada."}
];
const TIPOS=[["all","tudo"],["adr","adr"],["spec","spec"],["runbook","runbook"],["reference","reference"]];

// LENTES — uma documentação, duas audiências. A espinha (domínio) aparece nas duas:
// separar em dois sites duplicaria as invariantes — e cópia apodrece (ADR 0256).
const LENTES=[["operar","Operar",["start","dominio","fluxo","corpus"]],
 ["construir","Construir",["start","dominio","tec","gov","corpus"]],
 ["tudo","Tudo",ORDER]];

function esc(s){return s.replace(/[&<>]/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;"}[c]))}
function hi(txt,q){if(!q)return esc(txt);const i=txt.toLowerCase().indexOf(q.toLowerCase());if(i<0)return esc(txt);
 return esc(txt.slice(0,i))+"<mark>"+esc(txt.slice(i,i+q.length))+"</mark>"+esc(txt.slice(i+q.length))}
const slug=s=>s.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g,"").replace(/[^a-z0-9]+/g,"-").replace(/^-|-$/g,"");

function Blocks({ doc }){
 // FsmStepper full precisa de ~380px; abaixo disso o inline é a variante honesta.
 const [wide,setWide]=useState(()=>window.matchMedia("(min-width:1280px)").matches);
 useEffect(()=>{const mq=window.matchMedia("(min-width:1280px)");const h=e=>setWide(e.matches);
  mq.addEventListener("change",h);return()=>mq.removeEventListener("change",h)},[]);
 return doc.blocks.map((b,i)=>{
  if(b.k==="h2")return <h2 key={i} id={slug(b.t)}>{b.t}</h2>;
  if(b.k==="p")return <p key={i} dangerouslySetInnerHTML={{__html:b.t}} />;
  if(b.k==="ul")return <ul key={i}>{b.items.map((it,j)=><li key={j} dangerouslySetInnerHTML={{__html:it}} />)}</ul>;
  if(b.k==="table")return (
   <table className="doc-t" key={i}><thead><tr>{b.head.map((h,j)=><th key={j}>{h}</th>)}</tr></thead>
   <tbody>{b.rows.map((r,j)=><tr key={j}>{r.map((c,k)=><td key={k} dangerouslySetInnerHTML={{__html:c}} />)}</tr>)}</tbody></table>);
  if(b.k==="kv")return <div className="doc-kv" key={i}>{b.rows.map((r,j)=>[<div key={"k"+j}>{r[0]}</div>,<div key={"v"+j} dangerouslySetInnerHTML={{__html:r[1]}} />])}</div>;
  if(b.k==="fsm")return (
   <div className="doc-fsm" key={i}><div className="doc-fsm-h">{b.label}</div>
    {DS.FsmStepper
      ? <DS.FsmStepper variant={wide?"full":"inline"} hue={b.hue} steps={b.steps.map(s=>({label:s[0],state:s[1]}))} />
      : <div style={{fontFamily:"var(--font-mono)",fontSize:12}}>{b.steps.map(s=>s[0]).join(" → ")}</div>}
   </div>);
  if(b.k==="alert")return (
   <div className="doc-note" key={i}>{DS.Alert
     ? <DS.Alert tone={b.tone} title={b.title}><span dangerouslySetInnerHTML={{__html:b.body}} /></DS.Alert>
     : <p><b>{b.title}</b> <span dangerouslySetInnerHTML={{__html:b.body}} /></p>}</div>);
  return null;
 });
}

function Corpus(){
 const [q,setQ]=useState("");const [tipo,setTipo]=useState("all");const [mod,setMod]=useState(null);
 const mods=useMemo(()=>[...new Set(CORPUS.map(c=>c.m))].sort(),[]);
 const hits=CORPUS.filter(c=>(tipo==="all"||c.t===tipo)&&(!mod||c.m===mod)&&
  (q.length<2||(c.title+" "+c.ex+" "+c.n).toLowerCase().includes(q.toLowerCase())));
 return (<>
  <div className="doc-corpus-tools">
   <input value={q} onChange={e=>setQ(e.target.value)} placeholder="Buscar no corpus — termo, ADR, módulo" aria-label="Buscar no corpus" />
   {TIPOS.map(([id,l])=><button key={id} className={"doc-chip"+(tipo===id?" on":"")} onClick={()=>setTipo(id)}>{l}</button>)}
  </div>
  <div className="doc-corpus-tools" style={{marginBottom:20}}>
   <span style={{fontFamily:"var(--font-mono)",fontSize:10.5,letterSpacing:".08em",textTransform:"uppercase",color:"var(--text-mute)"}}>faceta módulo</span>
   {mods.map(m=><button key={m} className={"doc-chip"+(mod===m?" on":"")} onClick={()=>setMod(mod===m?null:m)}>{m}</button>)}
  </div>
  {hits.length===0
   ? (DS.EmptyState
      ? <DS.EmptyState variant="no-results" title="Nada no corpus com esses filtros" description="A busca cobre adr, spec, runbook e reference. Diário de bordo (session, handoff) está fora de propósito — por decisão." action={<button className="os-btn ghost" onClick={()=>{setQ("");setTipo("all");setMod(null)}}>Limpar filtros</button>} />
      : <p>Nada encontrado.</p>)
   : hits.map(c=>(
     <div className="doc-hit" key={c.n} tabIndex={0} role="button">
      <div className="doc-hit-t"><b dangerouslySetInnerHTML={{__html:hi(c.title,q.length>1?q:"")}} /><em>{c.n}</em>
       <span className="m doc-m-type">{c.t}</span><em>· {c.m}</em></div>
      <p dangerouslySetInnerHTML={{__html:hi(c.ex,q.length>1?q:"")}} />
     </div>))}
 </>);
}

function DocumentacaoPage(){
 const [id,setId]=useState(()=>{try{return localStorage.getItem("oimpresso.doc.id")||"guia"}catch(e){return "guia"}});
 // Default = Tudo: esconder metade da doc por padrão foi o que gerou "cadê o construir?".
 // A escolha só é lembrada depois de uma troca consciente de lente.
 const [lente,setLenteS]=useState(()=>{try{return localStorage.getItem("oimpresso.doc.lente")||"tudo"}catch(e){return "tudo"}});
 const setLente=k=>{setLenteS(k);try{localStorage.setItem("oimpresso.doc.lente",k)}catch(e){}};
 const [palette,setPalette]=useState(false);
 const [active,setActive]=useState(null);
 const mainRef=useRef(null);
 const grpsVis=(LENTES.find(l=>l[0]===lente)||LENTES[2])[2];
 // ordinal DENTRO da lente — sem buraco de páginas escondidas
 const vis=D.filter(d=>grpsVis.includes(d.grp));
 const hidden=GRPS.filter(([g])=>!grpsVis.includes(g));
 const num=(did)=>String(vis.findIndex(d=>d.id===did)).padStart(2,"0");
 const doc=D.find(d=>d.id===id)||D[0];
 const idx=D.indexOf(doc);
 const toc=(doc.blocks||[]).filter(b=>b.k==="h2").map(b=>b.t);

 useEffect(()=>{
  if(!grpsVis.includes(doc.grp)){const first=D.find(d=>grpsVis.includes(d.grp));if(first)setId(first.id)}},[lente]);

 useEffect(()=>{try{localStorage.setItem("oimpresso.doc.id",id)}catch(e){}
  if(mainRef.current)mainRef.current.parentElement.scrollTop=0;setActive(toc[0]||null)},[id]);

 useEffect(()=>{
  const hs=mainRef.current?mainRef.current.querySelectorAll("h2[id]"):[];
  if(!hs.length)return;
  const ob=new IntersectionObserver(es=>{es.forEach(e=>{if(e.isIntersecting)setActive(e.target.textContent)})},{rootMargin:"-10% 0px -75% 0px"});
  hs.forEach(h=>ob.observe(h));return()=>ob.disconnect();
 },[id]);

 useEffect(()=>{
  const h=e=>{if((e.metaKey||e.ctrlKey)&&e.key.toLowerCase()==="k"){e.preventDefault();setPalette(true)}};
  window.addEventListener("keydown",h);return()=>window.removeEventListener("keydown",h);
 },[]);

 const groups=[{label:"Documentação",items:D.map(d=>({id:d.id,label:d.title,hint:(GRPS.find(g=>g[0]===d.grp)||[])[1],icon:<span style={{fontFamily:"var(--font-mono)",fontSize:10.5}}>{d.n}</span>,onSelect:()=>{if(!grpsVis.includes(d.grp))setLente("tudo");setId(d.id);setPalette(false)}}))},
  {label:"Corpus",items:CORPUS.slice(0,8).map(c=>({id:c.n,label:c.title,hint:c.n+" · "+c.m,icon:DS.RegistrationMark?<DS.RegistrationMark size={14} color="var(--text-mute)" />:<span style={{fontFamily:"var(--font-mono)",fontSize:10.5}}>{c.t[0]}</span>,onSelect:()=>{setId("corpus");setPalette(false)}}))}];

 return (
 <div className="os-page doc-page" data-screen-label="01 Documentação">
  <header className="os-page-h">
   <div className="os-page-h-l">
    <h1>Documentação</h1>
    <p>Uma documentação, duas lentes — a espinha do domínio serve as duas. {D.length - 1} páginas · {CORPUS.length} documentos no corpus · última sync do git 02/08/2026</p>
   </div>
   <div className="os-page-h-r">
    <button className="os-btn ghost" onClick={()=>setPalette(true)}>⌘K Buscar</button>
    <a className="os-btn ghost" href={BLOB+doc.git} target="_blank" rel="noopener">Ver fonte no git</a>
   </div>
  </header>

  {DS.TabBar
   ? <div className="doc-lentebar"><DS.TabBar tabs={LENTES.map(([k,l,gs])=>({key:k,label:l,count:D.filter(d=>gs.includes(d.grp)).length}))} active={lente} onChange={setLente} /></div>
   : <div className="doc-lentebar fallback" role="group" aria-label="Lente da documentação">
      {LENTES.map(([k,l,gs])=><button key={k} className={lente===k?"on":""} onClick={()=>setLente(k)}>{l}<i>{D.filter(d=>gs.includes(d.grp)).length}</i></button>)}</div>}

  <div className="doc-wrap">
   <aside className="doc-rail">
    <button className="doc-rail-search" onClick={()=>setPalette(true)}>Buscar na documentação <kbd>⌘K</kbd></button>
    {GRPS.filter(([g])=>grpsVis.includes(g)).map(([g,label])=>{
     const items=D.filter(d=>d.grp===g);
     return (<div className="doc-grp" key={g}>
      <div className="doc-grp-h">{label}<span>{items.length}</span></div>
      <div className="doc-nav">{items.map(d=>
       <button key={d.id} className={d.id===id?"on":""} onClick={()=>setId(d.id)}><i>{num(d.id)}</i>{d.nav}</button>)}</div>
     </div>);})}

    {grpsVis.includes("tec")&&
     <div className="doc-grp">
      <div className="doc-grp-h">Programa<span>1</span></div>
      <div className="doc-nav">
       <button onClick={()=>window.__go&&window.__go("programa-doc")}><i>D</i>Trilha D — documentação técnica e operacional</button>
      </div>
     </div>}

    {hidden.length>0&&
     <button className="doc-ghost" onClick={()=>setLente("tudo")}>
      <em>oculto nesta lente</em>
      <span>{hidden.map(([g,l])=>l+" ("+D.filter(d=>d.grp===g).length+")").join(" · ")}</span>
      <b>ver tudo</b>
     </button>}
   </aside>

   <main className="doc-main" ref={mainRef}>
    <div className="doc-crumb">Documentação<span>/</span><b>{GRPS.find(g=>g[0]===doc.grp)[1]}</b><span>/</span><b>{doc.nav}</b></div>
    <h1 className="doc-title">{doc.title}</h1>
    <p className="doc-sub">{doc.sub}</p>
    <div className="doc-meta">
     <span className="m doc-m-type">{doc.type}</span>
     <span className="m">{doc.auth}</span>
     <span className="m">atualizado {doc.upd}</span>
     <span className="m">{doc.git}</span>
    </div>
    <div className="doc-body">{doc.corpus ? <Corpus /> : <Blocks doc={doc} />}</div>

    <div className="doc-src">
     Fonte dona deste texto: <a href={BLOB+doc.git} target="_blank" rel="noopener">{doc.git}</a>
     <span>·</span> a página é o documento renderizado, não uma cópia commitada
    </div>

    <div className="doc-pager">
     <button disabled={idx===0} onClick={()=>idx>0&&setId(D[idx-1].id)}>
      <em>anterior</em><span>{idx>0?D[idx-1].nav:"—"}</span></button>
     <button disabled={idx===D.length-1} onClick={()=>idx<D.length-1&&setId(D[idx+1].id)}>
      <em>próximo</em><span>{idx<D.length-1?D[idx+1].nav:"—"}</span></button>
    </div>
   </main>

   <aside className="doc-aside">
    {toc.length>0&&<>
     <div className="doc-aside-h">Nesta página</div>
     <nav className="doc-toc">{toc.map(t=>
      <a key={t} href={"#"+slug(t)} className={active===t?"on":""}>{t}</a>)}</nav></>}
    <div className="doc-aside-card"><div className="k">tipo</div><div className="v">{doc.type} · {doc.auth}</div></div>
    <div className="doc-aside-card"><div className="k">git</div><div className="v">{doc.git}</div></div>
    {doc.rel&&doc.rel.length>0&&
     <div className="doc-aside-card"><div className="k">relacionados</div>
      {doc.rel.map(r=><div className="v" key={r} style={{marginTop:6}}>{r}</div>)}</div>}
   </aside>
  </div>

  {DS.Command && <DS.Command open={palette} onClose={()=>setPalette(false)} groups={groups} />}
 </div>);
}

window.DocumentacaoPage=DocumentacaoPage;
})();
