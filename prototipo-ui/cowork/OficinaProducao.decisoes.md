---
register: Produção / Kanban da Oficina · window.OficinaPage
irmao_charter: OficinaProducao.charter.md
tecnica: Decision Register (anéis estilo Technology Radar — Avaliar/Testar/Adotar/Descartar)
owner: wagner
last_update: 2026-06-02
---

# Decision Register — Produção / Kanban da Oficina

> **O chão de debate da tela.** Aqui vivem as opções que ainda estão sendo discutidas, testadas e formando, com o tempo, como o sistema deve se comportar. O **charter** guarda só o que já fechou; este arquivo guarda o que está **em movimento**.
>
> **Ciclo de vida (anéis):**
> - 🔍 **AVALIAR** — ideia levantada, ainda não testada. Só discussão.
> - 🧪 **TESTAR** — [CC] protótipou; [W] está experimentando/decidindo.
> - ✅ **ADOTAR** — [W] aprovou → **grada pro charter** como ✅ e sai daqui.
> - ⛔ **DESCARTAR** — reprovado → vira anti-pattern no charter (memória de "não repetir").
>
> **Como [W] usa:** mexe no campo `estado:` de cada item, ou escreve em `nota [W]:`. [CC] lê isto no início de todo chat de Oficina e age conforme o anel.

---

## D-01 · Arrastar para avançar, com o gate como guarda
- **estado:** 🧪 TESTAR (protótipo entregue 2026-06-02 · aguarda veredito [W])
- **prioridade:** alta (é a "ideia melhor de interação" 2026-06-02)
- **contexto:** hoje avançar etapa = clicar card → abrir drawer → usar StageGate. Lento pro caminho feliz.
- **opção proposta:** arrastar o card pra próxima coluna = intenção de avançar; no *drop*, o StageGate valida. Gate ok → avança sem abrir nada. Gate falha → card volta e o drawer abre já no checklist do que falta.
- **build:** `oficina-page.jsx` (drag state + `dnd` + `gateOf`/`ctxFor` + toast) · `oficina-page.css` (`.ofc-drop-ok/no/over`, `.ofc-dragging`, `.ofc-toast`). Só ativo no **foco=Etapa**. **Drawer travado intacto** — só é aberto quando o gate barra.
- **TESTE FUNCIONAL (painel do usuário, 2026-06-02):**
  - ✅ render: 5 colunas, **12 cards arrastáveis** (`draggable=true`), `evalGate` ligado.
  - ✅ roteamento: cada etapa → `gate.next` correto (recepção→diagnóstico→peças→execução→pronto).
  - ✅ **caminho bloqueado** (default): OS 8804 com gate 3/4 → soltar bloqueia + abre drawer no checklist.
  - ✅ **caminho feliz**: completei o check manual → gate 4/4 `ready:true` → soltar avança peças→execução. As duas ramificações batem com `onColDrop` (`done===total`).
  - ✅ sem erro de console; drawer travado não foi tocado.
- **nota da 1ª passada:** **8/10** (funciona nas duas ramificações, render limpo, zero regressão) — segura os 2 pontos pelos achados abaixo.
- **REFINO 2ª passada (2026-06-02):**
  - **Uma máquina, duas portas:** extraí `tryAdvance(os)` — arrasto E os botões do card ("Triagem →", "Iniciar →", "Entregar →", que antes não faziam nada) chamam o MESMO avanço gate-guardado. Resolve o achado #2 (touch) e funde o **D-02**.
  - **Feedback preditivo:** ao arrastar, a coluna-alvo já mostra o desfecho — verde "solte p/ avançar" se o gate está pronto, âmbar "faltam N · abre checklist" se não. Acaba o bounce-surpresa (toque Linear/Stripe).
  - achado #1 (etapa terminal) mantido como decisão: "Entregar" é botão, não coluna.
  - **nota 2ª passada: 9/10** — falta só o veredito visual do [W] (meu iframe não roda o host; validei via DOM+lógica no painel do [W]).
- **achados (viram refino antes de ✅ ADOTAR):**
  1. **Etapa terminal:** "Pronto" tem `gate.next="entregue"`, mas **não existe coluna "Entregue"** → cards de Pronto não têm pra onde arrastar. O botão "Entregar →" no card já cobre isso — decisão: terminal é botão, não arrasto. Documentar (não é bug).
  2. **Touch (mecânico no tablet):** HTML5 drag é ruim em touch. **D-02 deixa de ser opcional** → o mesmo avanço gate-guardado precisa de um botão no card. Os botões "Triagem →/Iniciar →" já existem no card — devem **compartilhar a função de avanço** do D-01.
  3. **Verificação:** meu iframe não roda o host gigante (Babel trava); testei via eval+screenshot no painel do [W]. Não é defeito do D-01, é limite de ambiente.
- **nota [W]:** _(vazio — seu veredito: ✅ adotar / continuar 🧪 / ⛔)_

## D-02 · Botão "→ próxima etapa" no próprio card
- **estado:** 🧪 TESTAR — **implementado dentro do D-01** (2ª passada, 2026-06-02): os botões do card chamam `tryAdvance`, a mesma porta do arrasto. Gradua junto com o D-01.
- **contexto:** obrigatório pra touch (mecânico no tablet, onde arrasto falha).
- **nota [W]:** _(vazio — veredito junto com o D-01)_

## D-03 · Capacidade visível em todas as colunas
- **estado:** 🔍 AVALIAR
- **contexto:** hoje só "Em execução" mostra X/5 boxes. Estender a todas (ex.: diagnóstico = X/2 elevadores)?
- **dúvida:** Recepção e Pronto não têm capacidade física — faz sentido só onde há recurso (box/elevador)?
- **nota [W]:** _(vazio)_

## D-04 · Borda do card por SLA (verde/âmbar/vermelho)
- **estado:** 🔍 AVALIAR
- **contexto:** hoje urgência é booleano (tira vermelha). Trocar por gradiente de prazo?
- **opção:** borda/realce muda conforme proximidade do prazo, não só on/off.
- **risco:** ruído visual; pode brigar com a calma "Shopmonkey". Testar no modo Pressão.
- **nota [W]:** _(vazio)_

## D-05 · KPI clicável filtra o quadro
- **estado:** 🔍 AVALIAR
- **contexto:** os 6 KPIs são só leitura. Clicar "Urgentes" filtraria o kanban?
- **opção:** KPI vira filtro de 1 clique (toggle), com o card destacando que está filtrado.
- **nota [W]:** _(vazio)_

## D-06 · Persistir visão/foco escolhido
- **estado:** 🔍 AVALIAR
- **contexto:** ao voltar pra tela, volta no default (Etapa/Kanban). Lembrar a última escolha?
- **onde:** localStorage no protótipo; preferência de usuário no real.
- **nota [W]:** _(vazio)_

## D-07 · Atalhos de teclado (N / barra / setas)
- **estado:** 🔍 AVALIAR
- **contexto:** Larissa é teclado-first. `N` nova OS · `/` foca busca · setas navegam cards · Enter abre.
- **nota [W]:** _(vazio)_

## D-08 · Foto real de entrada no card
- **estado:** 🔍 AVALIAR
- **contexto:** card mostra tag textual ("frente", "OBD"). Trocar por thumbnail real da foto de check-in?
- **risco:** densidade/peso a 1280px; talvez só no modo "Detalhe".
- **nota [W]:** _(vazio)_

---

## D-09 · Runner in-app de casos de uso (QA sob demanda)
- **estado:** 🧪 TESTAR (protótipo 2026-06-02)
- **pedido [W]:** "quero que o teste seja executado na tela quando eu pedir."
- **solução viável:** separar spec (`casos.md`, humano) do **registry executável** (`oficina-casos.jsx`, `window.OFICINA_CASOS`) com o MESMO ID → fonte única, 2 consumidores (runner in-app agora · teste CI depois). Painel QA gated (botão "▶ Casos" na toolbar) roda os checks live na tela **já montada** (sem corrida com build) → confiável.
- **build:** `oficina-casos.jsx` (registry 7 UCs + `OficinaCasosRunner`) · toggle em `OficinaPage` · CSS `.ofc-casos-*` · script em `oimpresso.com.html`. Não toca drawer/kanban.
- **escopo honesto:** front-only (render/clique/estado); fiscal/backend = CI.
- **nota [W]:** _(vazio — testar e dar veredito)_
- **TESTE LIVE 2026-06-02:** painel "▶ Casos" abre na tela, **7/7 verde**, checks reais (UC-01 5 colunas · UC-06 12 arrastáveis·gate→diagnostico · etc.). DS-GUARD limpo. Roda pós-montagem → **confiável** (≠ eval externo). **Nota 1ª passada: 9/10.**
- **REGRESSÃO 2026-06-02 (escape a [W]):** ao generalizar o runner (CasosRunner com prop `casos`), o render da Oficina ficou sem passar `casos` → painel vazio. Pego por [W], não por mecanismo → conta no benchmark (escape +1). Corrigido. **Lição L-24 candidata:** generalizar componente com nova prop exige varrer TODOS os call-sites; o runner precisa de **self-guard** ("montou com N>0 casos? senão alerta") pra essa classe não passar silenciosa. Mostra o limite do tipo de defesa atual.
- **GATE QA 2026-06-02:** o launcher "▶ Casos" agora é **gated** (`localStorage oimpresso.qa=1` · `?qa=1` · atalho Ctrl/Cmd+Shift+Q) — **invisível pro cliente final** por padrão. Vale Oficina + Vendas (CasosLauncher + botão Oficina). Era exigência ("nunca visível pro cliente").
- **APOSENTADO 2026-06-02 ([W]):** runner DOM-grep arquivado em `_arquivo/runner-casos-domgrep-2026-06-02/` (lápide) + wiring removido do host. Cumpriu o papel (provou a ergonomia + carregou casos). Destino = **`_PROPOSTA-0244`** (Playwright/Storybook). FICAM vivos: `casos.md` (spec) + 0244 (decisão). D-09 → estado **⛔ descartado como impl** (conceito graduou pra 0244).

## Lacunas do dossiê (fonte: `Oficina-casos-dossie.md` · 2026-06-02)
- **estado:** 🔍 AVALIAR (backlog priorizado pelo dossiê de casos de uso; [W] confirma a ordem antes de aplicar à tela). Escopo: Oficina vertical (Kanban/Produção **+** OS/Create).
- **P1 (alto impacto, médio esforço):** abertura de OS + check-in (UC-01: form, busca placa, vistoria entrada, fotos, combustível, autorização) · fluxo de entrega + faturamento (UC-09: checklist saída, pagamento, NF-e+NFS-e, garantia 90d) · estados Orçamento/Aprovação + registro de recusados (UC-04/05).
- **P2:** ciclo de peças + estoque cotada→pedida→recebida + reserva/baixa (UC-06) · DVI real com foto/vídeo + achado→item (UC-03) · apontamento de tempo + checklist de roteiro + pausa c/ motivo (UC-07).
- **P3:** etapa de Qualidade (UC-08) · campos de frota/caminhão + histórico do veículo (§7, UC-10) · visão de pátio (ocupação) + KPIs (ARO, aprovação, MTTR, %DVI).
- **P4:** papéis e permissões · pós-venda (NPS, lembretes, retorno de garantia).
- **regra:** cada item escolhido vira D-NN próprio (Avaliar→Testar), passa pelo gate anti-regressão (METODO) e não pode quebrar UC ✅ existente. Detalhe e "a tela precisa" de cada um no dossiê.
- **nota [W]:** _(priorize/edite a ordem)_

## Lacunas do dossiê (fonte: `Oficina-casos-dossie.md` · 2026-06-02)
- **estado:** 🔍 AVALIAR (backlog priorizado pelo dossiê; [W] confirma a ordem). Escopo: Oficina (Kanban + OS/Create).
- **P1:** abertura de OS + check-in (UC-01) · entrega + faturamento NF-e/NFS-e + garantia (UC-09) · estados Orçamento/Aprovação + recusados (UC-04/05).
- **P2:** ciclo de peças cotada→pedida→recebida + estoque (UC-06) · DVI real foto/vídeo + achado→item (UC-03) · apontamento de tempo + checklist + pausa (UC-07).
- **P3:** etapa Qualidade (UC-08) · frota/caminhão + histórico do veículo (§7/UC-10) · visão de pátio + KPIs (ARO, aprovação, MTTR, %DVI).
- **P4:** papéis/permissões · pós-venda (NPS, lembretes, retorno de garantia).
- **regra:** cada item escolhido vira D-NN, passa pelo gate anti-regressão (METODO), não quebra UC ✅. Detalhe no dossiê.
- **nota [W]:** _(priorize/edite)_

## D-10 · Convergência caçamba→reparo no git (camada visível) — handoff entregue
- **estado:** 🧪 TESTAR (handoff [CL] gerado 2026-06-08 · aguarda [W] transportar + PR de convergência)
- **contexto:** `OficinaAuto/ProducaoOficina/Index.tsx@main` ainda espelha o caçamba (colunas `disponivel/locada/...`, "Mecânica Pesada", filtro Capacidade m³); a verdade de design é o modelo (A) reparo (`oficina-page.jsx`, nota 9.5). É a **dívida F3** que a própria `Index.charter.md@main` declara.
- **entrega [CC]:** `prototipo-ui-patch/PROMPT_PARA_CODE_OFICINA-CONVERGE-CACAMBA-REPARO.md` — converge só a **apresentação** (colunas/labels/vocabulário/filtro/KPIs/ação "Nova OS") + recompõe header/KPIs/meta-linha nos **primitivos de layout** (ADR 0253).
- **trava dura:** keys FSM `disponivel/locada/...` rodam LIVE no Martinho (biz=164, ADR 0194). Migrar DB/seeder/keys = **Tier 0 [W]**, fora do PR de design. Default = mapear apresentação, manter key.
- **nota [W]:** _(vazio — transportar o handoff / ajustar escopo)_

## Correção de registro (2026-06-08, REGRA 6)
- **D-01/D-02 já estão nos DOIS lados.** Cowork: `oficina-page.jsx` desde 2026-06-02 (este Register, nota 9/10). Git: PR #2228 (2026-06-04, `KanbanDndProvider`/`DragConfirmDialog`/`evaluateDrop`/`handleCardAdvance`). [CC] havia afirmado "protótipo não tem drag-drop" lendo *Refs* stale do charter do git, não o código — corrigido. **D-01/D-02 prontos p/ graduar pro charter como ✅ assim que [W] der o veredito visual.**

## Graduados (saíram daqui → viraram ✅ no charter)
- **2026-06-08 ([W] "veredito ✅"):** **D-04** (borda SLA) · **D-05** (KPI filtra) · **D-06** (persistir foco/view) · **D-07** (atalhos) → ✅ no charter. **D-08 parcial** (foto real no drawer; no card pendente — fica ⬜). **D-01/D-02** prontos p/ graduar quando [W] confirmar o visual.

## Descartados (viraram anti-pattern no charter)
- _(nenhum ainda)_

## Trilha do tempo
- 2026-06-02 · [CC] criou o Register (técnica Decision Register / anéis Radar). Semeado com D-01…D-08 a partir do inventário ⬜/💡 do charter. Drawer e itens ✅ ficam no charter.
- 2026-06-08 · [CC] **fechou a camada-protótipo** ([W] "pode fazer todos"): **D-04** borda SLA (verde/âmbar/vermelho por prazo, escopada por mood: calmo só vermelho, pressão mais forte) · **D-05** KPI clicável filtra o quadro + chip "limpar filtro" · **D-06** persistir foco/densidade/pressão/view em localStorage · **D-07** atalhos N// setas/Enter/Esc → todos **🧪 TESTAR**, aguardam veredito [W]. Também: bug do botão "Nova OS" cortando (white-space) · botões mortos ganharam feedback real (toast/print/conversa) · alert()→toast · **D-08 parcial** (foto real via file picker no drawer, thumb). Drawer travado intacto (verificado render). Tudo em `oficina-page.{jsx,css}`, host `?v=ofx9/tok3`.
- 2026-06-08 · **GOTCHA TÉCNICO (custou ~6 iterações de debug):** `kpiFilter` (state novo) foi declarado DEPOIS do `const filtered` que o consome. **Babel-standalone transpila `const`→`var` sem TDZ**, então no cálculo de `filtered` o `kpiFilter` lia `undefined` (filtro pulado → quadro não filtrava), enquanto o JSX (após a atribuição) lia o valor certo. Sintoma traiçoeiro: `filtered.length=12` e o MESMO cálculo inline=2 na mesma render, sem erro de console. **Regra:** no host Babel, declarar todo state/var ANTES de qualquer `const x = (()=>{… usa o state …})()` no corpo do componente — não confiar no TDZ pra pegar use-before-declaration.
