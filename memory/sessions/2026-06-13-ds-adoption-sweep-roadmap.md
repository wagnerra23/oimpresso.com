# Varredura paralela de adoção do DS — mapa consolidado (2026-06-13)

> 35 threads (1/módulo) auditaram `resources/js/Pages/**` via origin/main. Classificaram cada cor crua em ESTADO (→token), CATEGORIA (→mantém) ou INCERTO (→olho do Wagner). Read-only; este doc é o roadmap.

## Totais

| | |
|---|---|
| Módulos | 35 |
| Arquivos com paleta crua | 201 |
| Ocorrências | 2241 |
| **Edits semânticos seguros** | **360** |
| Categoria (mantém) | 190 |
| Incertos (olho) | 169 |

## Por módulo (ordenado por edits seguros)

| Módulo | arquivos | hits | ✅ seguro | 🎨 categoria | 👁️ incerto |
|---|---:|---:|---:|---:|---:|
| Admin | 19 | 120 | 43 | 4 | 7 |
| Cliente | 10 | 62 | 39 | 4 | 5 |
| Financeiro | 27 | 574 | 31 | 41 | 18 |
| ads | 15 | 79 | 25 | 14 | 9 |
| OficinaAuto | 6 | 64 | 22 | 3 | 4 |
| Ponto | 19 | 71 | 20 | 6 | 16 |
| kb | 8 | 24 | 19 | 2 | 5 |
| Sells | 9 | 62 | 17 | 6 | 5 |
| Settings | 6 | 193 | 17 | 11 | 9 |
| RecurringBilling | 9 | 347 | 16 | 8 | 13 |
| ProjectMgmt | 9 | 39 | 12 | 13 | 5 |
| Atendimento | 9 | 30 | 10 | 7 | 11 |
| StockAdjustment | 2 | 42 | 9 | 0 | 2 |
| Purchase | 4 | 29 | 8 | 3 | 8 |
| Repair | 5 | 62 | 7 | 4 | 5 |
| ConsultaOs | 2 | 9 | 7 | 2 | 0 |
| Whatsapp | 9 | 96 | 7 | 16 | 13 |
| Modules | 1 | 9 | 7 | 0 | 1 |
| Produto | 1 | 6 | 6 | 0 | 0 |
| StockTransfer | 2 | 47 | 5 | 2 | 2 |
| Essentials | 2 | 16 | 4 | 6 | 4 |
| TransactionPayment | 2 | 6 | 4 | 0 | 0 |
| Manufacturing | 1 | 4 | 4 | 0 | 0 |
| MemCofre | 2 | 12 | 4 | 4 | 3 |
| team-mcp | 3 | 42 | 3 | 7 | 4 |
| Vestuario | 1 | 3 | 3 | 0 | 0 |
| Jana | 3 | 14 | 2 | 6 | 2 |
| NfeBrasil | 5 | 22 | 2 | 6 | 7 |
| Site | 1 | 2 | 2 | 0 | 0 |
| Nfse | 1 | 4 | 2 | 0 | 2 |
| Compras | 3 | 35 | 1 | 0 | 2 |
| _Showcase | 1 | 1 | 1 | 0 | 0 |
| ComunicacaoVisual | 1 | 1 | 1 | 0 | 0 |
| governance | 2 | 94 | 0 | 8 | 6 |
| Home | 1 | 20 | 0 | 7 | 1 |

## Incertos (precisam do olho do Wagner na app rodando) — 169

### Repair
- `Index.tsx`: Slate = neutro mapearia conceitualmente pra muted/border/muted-foreground/foreground, MAS esta tela é port fiel de prototipo-ui (visual-source F1.html) com paleta neutra coesa em ~10 shades que os 4 tokens neutros não cobrem 1:1 (ex slate-300, slate-800, bg-slate-900 active, ring-slate-900). Tokenizar peça-a-peça quebra a coesão e arrisca regressão visual num gate Cowork. Vale tokenizar a pele inteira ou manter cru por ser skin de protótipo?
- `Index.tsx`: Amber aqui = 'aguardando aprovação' (atenção/pendente → warning), MAS amber também é um dos 5 tons de categoria (TONE_*) e está entrelaçado na pele do protótipo. Estado-warning ou parte do sistema de tom/skin? Na dúvida não troco.
- `Index.tsx`: Mesma ambiguidade — banner de aprovação pendente lê como warning, mas reusa a pele amber do protótipo + tom de categoria. Tokenizar pode divergir do visual-source aprovado. Marcado uncertain.
- `Index.tsx`: Emerald = aprovado/concluído lê como success, MAS é também um dos 5 tons de categoria (coluna Pronto) e dot de timeline derivado de prop 'dot'. Estado-success ou cor de etapa/skin do protótipo? Conservador: uncertain.
- `Index.tsx`: Blue aqui é badge de slot/box e tom de coluna (accent de categoria), não banner-info nem chip-info de estado. Poderia ser info? Provável accent/categoria, mas blue ambíguo → uncertain em vez de mapear pra info.

### Jana
- `Index.tsx`: Asterisco vermelho marca coluna 'critical' (gate bloqueante ADR 0049). Pode ser estado-erro (destructive) OU apenas enfase de marcador de categoria 'critico'. Nao e um estado de erro propriamente, e um indicador de criticidade — na duvida nao tokenizo. Tambem aparece de novo na legenda (linha 313, mesmo text-red-500) pareando com este marcador.
- `Index.tsx`: Legenda do asterisco critico. Mesma duvida do marcador acima: enfase de criticidade vs estado destructive. Par do marcador da linha 275; se um for tokenizado o outro deve acompanhar pra manter consistencia visual.

### RecurringBilling
- `Index.tsx`: Skin neutra stone coesa em toda a tela (slate-skin de prototipo-ui). Converter em massa pra muted/border/foreground e fora de escopo conservador — risco de regressao visual.
- `Index.tsx`: Chaves ok/warn/bad sao semanticas (success/warning/destructive), mas e mapa pareado com 'neutral' stone + variante solida hero text-white; converter so 3 de 4 entradas mistura token+cru no mesmo objeto. Estado ou tom de KPI?
- `Index.tsx`: Accent de foco rose tematizado pra acao destrutiva — tint de focus-ring, nao estado de cor nucleo; mapeamento -400/-100 pra token destructive e impreciso.
- `Index.tsx`: Skin neutra stone coesa — fora de escopo conservador pra conversao em massa.
- `Index.tsx`: Chaves semanticas (ok/warn/bad) mas mapa pareado com neutral stone + variante escura -300/-400 pra hero dark; conversao parcial mistura token+cru.
- `Index.tsx`: Verde positivo de tendencia, mas em card hero stone-900 escuro com shade -400; mapeamento pra text-success (light+dark) pode escurecer demais no contexto dark.
- `Create.tsx`: Skin neutra stone coesa do form — fora de escopo conservador.
- `Edit.tsx`: Amber sinaliza 'slug alterado' (atencao) — candidato a warning, mas e combo border+focus-ring condicional; focus:border-warning/focus:ring-warning pro shade -400/-500 e mapeamento impreciso.
- `Edit.tsx`: Skin neutra stone coesa do form — fora de escopo conservador.
- `CheatSheet.tsx`: Skin neutra stone coesa — modal usa stone como cinza neutro; fora de escopo conservador pra conversao em massa pra muted/border/foreground.
- `CmdPalette.tsx`: Skin neutra stone coesa — fora de escopo conservador.
- `PresentationMode.tsx`: Slide de apresentacao dark coesa: emerald=MRR positivo, amber=churn, mas com tints /10 /30 e shades -300/-400 sobre fundo escuro; tokens light+dark podem nao espelhar a intencao do slide. Estado ou accent de KPI em contexto dark?
- `TroubleshooterOverlay.tsx`: Skin neutra stone coesa — fora de escopo conservador.

### kb
- `NodeReader.tsx`: blue e o passo intermediario do gradiente sequencial fresh->aging->stale->expired (nao info-state). Trocar por info quebraria a progressao visual de frescor. Manter cru ou usar token info?
- `NodeReader.tsx`: hover positivo do botao 'Util' (voto util). emerald=positivo->success parece ok, mas e accent de feedback positivo, nao estado de erro/sucesso de sistema. Tokenizar pra success/5 ou manter?
- `NodeList.tsx`: blue como accent de link/contagem de OS vinculadas — ambiguo entre info-state e accent de link. Pode ser info-state ou apenas destaque visual de contagem.
- `TroubleshooterDialog.tsx`: botao solido Sim com text-white. bg-success usa text-success-foreground; trocar so o bg deixaria text-white desalinhado com o par do token. Tokenizar bg+text junto ou manter?
- `TroubleshooterDialog.tsx`: botao solido Nao com text-white. mesmo caso do Sim — par solido do token warning e text-warning-foreground, nao text-white. Tokenizar par completo ou manter?

### Sells
- `SaleSheet.tsx`: partial=blue convive com paid=emerald/due=amber no mesmo map de status de pagamento; blue aqui e info-state ou apenas a terceira cor da escala? Mantive cru pra nao quebrar o tri-estado.
- `SaleSheet.tsx`: paid=success e due=warning sao inequivocos, mas estao num map keyado junto com partial=blue; tokenizar so 2 das 3 entradas deixaria o map inconsistente. Decisao do dono: tokenizar o map inteiro tratando partial como info, ou deixar tudo cru?
- `SaleTimeline.tsx`: As chaves green/amber/red sozinhas seriam success/warning/destructive, mas vivem no mesmo Record<UnifiedTone> com blue e neutral; tokenizar parcial quebra o tipo. Tokenizar o map todo (blue->info, neutral->muted) e decisao do dono.
- `CobrancaDrawer.tsx`: Esta tela e uma pele 'stone' coesa de prototipo-ui (slate-like skin), nao tokens muted padrao. Migrar stone->muted/foreground/border tokeniza a tela inteira mas e mudanca de pele coesa — fazer em PR proprio de re-skin, nao no audit de estados?
- `CobrancaDrawer.tsx`: pending='aguardando pagamento' em blue — info-state (info) ou apenas o terceiro tom do tri-estado paid/pending/overdue? Mantive cru por ambiguidade; se for info, vira text-info/bg-info.

### ads
- `Conflicts.tsx`: blue e info-state ou apenas a 3a cor do set de tipos-de-conflito? ambiguo entre info e categoria
- `Conflicts.tsx`: par contrastante humano-aprovou (success) vs IA-nota-baixa; semantic mas usado como contraste lado-a-lado — tokenizar quebraria o pareamento visual?
- `DecisaoShow.tsx`: azul e accent decorativo da secao 'cadeia de raciocinio/Brain B' usado coeso, nao info-state alert — manter como accent de marca?
- `Decisoes.tsx`: callout do Brain B — info-state ou accent de marca Brain B (mesmo azul de DecisaoShow)? ambiguo
- `MetaSkills.tsx`: azul como contador-info / accent de form — info-state real ou apenas accent neutro de marca?
- `Policy.tsx`: alinham semanticamente (warning/success) mas pertencem a um mapa de 4 categorias junto com blue (Brain B) — tokenizar so 2 entradas deixaria o mapa inconsistente?
- `TeamScopes.tsx`: card explicativo colorido de ambar — warning-state real ou apenas callout soft informativo? texto e didatico, nao alerta
- `TeamScopes.tsx`: highlight sutil de linha 'tem acesso' — success-state ou apenas realce visual de tabela?
- `Test.tsx`: superficie de code-block dark deliberada dentro de prose — tokenizar pra muted quebraria o look de codigo; manter cru

### ProjectMgmt
- `DetailSheet.tsx`: Verde realca o NOVO valor numa transicao (from neutro -> to verde). E par visual de mudanca, nao estado de sucesso puro. Tokenizar como success pode descaracterizar o par from/to.
- `Index.tsx`: Tags de 'precisa atencao' (sem dono/sem prio) — defensavel como warning, mas vivem num set de chips Triage ao lado de slate 'backlog'. Estado-de-atencao ou rotulo-de-categoria?
- `Index.tsx`: Borda amber sinaliza campo pendente (warning-ish), mas em contexto de input/select; tokenizar pra border-warning/? incerto sem ver token de borda equivalente.
- `Index.tsx`: Realca o NOVO status numa transicao (from_value neutro bg-muted -> to_value verde). Par visual de mudanca de estado, nao success puro; tokenizar quebraria o contraste from/to.
- `Index.tsx`: done=concluido e success inequivoco, mas vive no mesmo rainbow map de 4 status; tokenizar so esta entrada cria inconsistencia interna no map. Manter map inteiro cru ou tokenizar a entrada?

### Purchase
- `Create.tsx`: Stone e a pele neutra coesa do prototipo-ui (foreground/muted/border). Mapear token-a-token (stone-500->muted-foreground, stone-900->foreground, border-stone-200->border) e mudanca ampla de skin; conservador -> uncertain.
- `Edit.tsx`: Mesma skin neutra coesa do prototipo-ui de Create; mudanca de skin ampla -> uncertain.
- `Index.tsx`: Rose como marcador de 'devolucao existe' — sinal negativo/alerta, mas e badge-marcador (categoria) e nao erro inequivoco. Conservador -> uncertain entre destructive e category.
- `Index.tsx`: Entradas emerald/amber/rose dos pill-maps sao estados inequivocos (poderiam ir pra success-soft/warning-soft/destructive-soft). Mantive como CATEGORY pq o mapa mistura ordered=blue e due=stone (nao-semanticos); tokenizar o mapa inteiro e decisao de design (info-soft/muted), nao edit cego.
- `Index.tsx`: Pele neutra coesa do prototipo-ui (cabecalho de tabela, linhas, hover). Mudanca de skin ampla -> uncertain.
- `Show.tsx`: Rose em 'A pagar' quando due>0 e estado negativo (mapeavel a destructive-fg), mas o old exato repete em duas spans irmas com ternario identico; precisa edit com contexto unico por linha. Conservador -> uncertain pra evitar substring colidindo com pill overdue.
- `Show.tsx`: Delete = acao destrutiva -> text-destructive seria coerente; deixei uncertain so pq o markup do botao difere dos outros (outline vs ghost icon) e quero confirmar tom de icone/texto.
- `Show.tsx`: Pele neutra coesa do prototipo-ui em todo o detalhe (labels, valores, tabelas). Mudanca de skin ampla -> uncertain.

### governance
- `Index.tsx`: zinc=neutro->muted/border/foreground em tese, mas sao chrome estrutural tecido em classNames compostas longas (muitas nao-unicas e com logica de estado ativo/hover). Tokenizar exige pass dedicado com old unico por ocorrencia — fora do escopo seguro deste lote.
- `Index.tsx`: Card informativo do gate poderia virar info-token, mas sky tambem e o degrau 'Bom' da rampa neste mesmo arquivo; trocar parte do sky por info e manter parte como rank criaria inconsistencia. Incerto entre info-state e accent coeso.
- `Index.tsx`: ring sky e accent de foco (nao info-state inequivoco); amber-100 do badge de label e destaque de codigo, nao necessariamente warning-state. Conservador: manter.
- `Show.tsx`: emerald=delta positivo / red=delta negativo poderia ser success/destructive (estado de variacao). Mas neste arquivo emerald/red tambem sao os extremos da rampa ordinal de score/dim — trocar so o delta por token e manter o resto como rank cru gera dois sistemas de cor pro mesmo emerald/red. Incerto: estado vs rampa coesa.
- `Show.tsx`: emerald de botao 'copiar/aprovar' e accent de acao, nao necessariamente success-state; emerald do 'N/A justificado' e marca de OK porem dentro do mesmo vocabulario emerald da rampa de qualidade. Conservador: nao tokenizar isoladamente.
- `Show.tsx`: Mesmas ambiguidades do Index: zinc=chrome em classNames compostas (pass dedicado), sky=info-card vs rank, red do '-lost' = perda (destructive?) vs extremo da rampa. Incerto.

### Cliente
- `ActiveChip.tsx`: variant default 'azul match Cowork' para chip de filtro ativo — accent neutro/marca ou info-state? Ambiguo (azul nao representa estado informativo, e cor de affordance do chip). Manter cru ou info?
- `ClassificacaoTab.tsx`: Dot usa comparacao com valores PT-BR ('ativo'/'inativo') mas STATUS_OPTIONS define EN ('active'/'inactive'). Possivel bug pre-existente (dot sempre cai no else=blocked). Tokenizar a cor e seguro, mas a logica de match parece quebrada — fora do escopo de cor, sinalizo.
- `IATab.tsx`: Icone decorativo do card 'Proxima acao' (Target). Amber aqui e decoracao do card, nao estado de atencao. Tokenizar para text-warning mudaria a semantica para 'alerta' que nao existe. Manter cru (decorativo) ou warning?
- `IATab.tsx`: Icone decorativo do card 'Score de relacionamento' (Sparkles). Emerald e decoracao/marca positiva do card, nao validacao de sucesso. Tokenizar para success e plausivel mas e icone ornamental — confirmar intencao.
- `SalesTab.tsx`: Estado 'partial' (pago parcial) num status-map de 4 estados. Azul = info-state plausivel (paid=success, due=warning, overdue=destructive, partial=info). Mas e estado de pagamento, nao 'informacao' — info pode ser ok, ou manter cru pra distincao de status. Tokenizar para info?

### Settings
- `Index.tsx`: Tokenizar a rampa stone inteira pra muted/border/foreground ou preservar como skin do prototipo? muted nao tem rampa 50-900 equivalente.
- `CheatSheetSettings.tsx`: Mesmo skin stone do modulo — tokenizar pra muted/border ou preservar como pele do prototipo?
- `DrawerGateway.tsx`: Info-state (sky) porem com estados hover (sky-100/300) sem token equivalente; swap parcial deixaria hover cru. Tokenizar so base ou manter?
- `DrawerGateway.tsx`: Sao erros (destructive) mas as strings variam por sufixos de layout (flex/px/py); confirmar se cada bloco deve virar destructive-soft individualmente ou ha um wrapper compartilhavel.
- `DrawerGateway.tsx`: created->success e deleted->destructive ok, mas o leg neutro stone-500 e skin; tokenizar 2 de 3 ou preservar o mapa inteiro?
- `SheetNovoGateway.tsx`: Mesmo caso do Drawer: info com hover sky-100/300 sem token; tokenizar base e deixar hover cru ou manter?
- `SheetNovoGateway.tsx`: Trocar os 3 legs por destructive/warning/success na mesma expressao? Confirmar mapeamento rose=erro, amber=aviso, emerald=ok.
- `atoms-settings.tsx`: On vira success; o off fica stone-300 (skin) ou vira muted/border pra consistencia do par?
- `atoms-settings.tsx`: Badge 'deprecated' e marcacao de catalogo (categoria) ou estado warning? Mesmo amber-100/800 aparece em outros arquivos como tag.

### Admin
- `WidgetHealth.tsx`: fallback neutro -> bg-muted-foreground? Ja coberto se mapa for tokenizado; trecho 'bg-gray-400' tambem aparece na string do mapa (unknown), edit do mapa cobre ambos.
- `WidgetInfraStatus.tsx`: fallback neutro cru bg-gray-400 -> bg-muted-foreground? unico no arquivo; manter uncertain por ser branch de fallback.
- `WidgetMcpServer.tsx`: varios neutros cinza (text-gray-500, text-gray-400) sao texto secundario/terciario -> text-muted-foreground. Tokenizar todos como muted ou preservar hierarquia 400 vs 500? Deixei uncertain por distincao de nivel.
- `WidgetAdrTier0.tsx`: neutros cinza de texto/codigo secundario -> text-muted-foreground / bg-muted. Tokenizar em lote ou caso a caso? Deixei uncertain pela quantidade e variacao de nivel.
- `WidgetMutations.tsx`: amber aqui e callout de ATENCAO (token plaintext, salve agora) -> warning, OU destaque de seguranca proposital? Inclino-me a warning (bg-warning/10 text-warning) mas e leve incerteza de intencao.
- `WidgetCurador.tsx`: neutros cinza de label/codigo -> text-muted-foreground / bg-muted. Lote, mas deixei uncertain pela mistura com chips coloridos categoricos no mesmo arquivo.
- `WidgetCycles.tsx`: green=done poderia ser success (text-success-fg), mas faz par com blue=doing (azul) na mesma tabela; tokenizar so o verde desbalancearia o par. Manter par cru ou tokenizar ambos (green->success, blue->info)?

### Compras
- `AcoesDropdown.tsx`: Skin neutro stone coeso (border/surface/texto/divisor). Mapear pra muted/border/muted-foreground colapsaria nuances e o token nao cobre bem hover:bg-stone-50. Tratar junto com decisao global de neutro do modulo?
- `GradeMatrixInput.tsx`: Skin neutro stone com escala de papeis (heading-900 vs body-700 vs muted-500 vs disabled-300 vs surfaces 50/100). Achatar tudo em muted perderia o gradiente habilitado/desabilitado e os fundos brancos. Manter cru ate decisao global de neutro?

### OficinaAuto
- `ServiceOrderItemFormSheet.tsx`: emerald destaca o total monetario calculado (positivo). Mapear pra success ou e so realce neutro de total? text-emerald-800 nao tem shade direto no token (success-fg). Conservador: uncertain.
- `Index.tsx`: Mapa de tones do card de KPI (Disponiveis=emerald, Locadas=blue, Manutencao=amber, Atrasada=rose). emerald/amber/rose teriam token de estado, mas blue=Locadas e um accent de categoria (nao info-state). Mapa coeso de 4 tones — tokenizar so 3 quebraria o conjunto. Conservador: uncertain o map inteiro.
- `Index.tsx`: Confirmar se algum text-amber-700/rose-700 da tabela colide como substring com o stat-card map antes de aplicar edits (os edits acima usam contexto unico de className pra evitar).
- `VehicleStatusBadge.tsx`: blue=locada/'Em servico' e estado ativo, nao info-state inequivoco nem erro/sucesso. Ambiguo entre info e accent de categoria do badge. Conservador: uncertain (nao tokenizar pra info sem certeza).

### team-mcp
- `Index.tsx`: active=verde sugere success-soft, mas faz parte de um trio de status (active/closed/archived) que poderia ser tratado como mapa de categoria; trocar so o active deixa o conjunto inconsistente.
- `Index.tsx`: done=emerald e claramente sucesso; review=amber claramente atencao; blocked=red claramente erro. Poderiam virar success/warning/destructive, mas fazem parte de mapas de lifecycle coesos — na duvida mantive crus.
- `Index.tsx`: Verde aqui e mais decorativo (pill do contador de tokens ativos) do que estado de sucesso; pode ser acento, nao success-state. Inclui hover:bg-green-200 que nao tem equivalente direto em token.
- `Index.tsx`: Os extremos da rampa (green=ok, red=excedido) sao estados inequivocos e poderiam virar success/destructive; mantive crus para preservar a rampa graduada inteira coesa.

### NfeBrasil
- `ImportCsv.tsx`: Trocar text-emerald-700 por text-success-fg (válidas é claramente sucesso). Mantido cru só porque o par text-destructive (erros) ao lado já está tokenizado e a troca isolada deixaria escala visualmente assimétrica — confirmar se OK tokenizar este isolado.
- `Index.tsx`: Botão 'Confirmar selecionadas' emerald sólido = ação de sucesso → bg-success text-success-foreground. Incerto: hover:bg-emerald-700 não tem token de hover limpo equivalente; tokenizar só o base deixaria hover órfão.
- `Index.tsx`: KPI card 'Vencendo em 7 dias' usa amber (warning) com vários tons (amber-200/50/700/900/100 + dark:). Tokenizar exige mapear bg+border+texto+dark coerentemente; risco de inconsistência parcial — revisar como conjunto warning-soft.
- `Index.tsx`: KPI 'Confirmadas no mês' em emerald (sucesso). text-success-fg cabe, mas é número de destaque (4xl) — confirmar se accent verde forte deve virar token success ou se é ênfase visual de métrica.
- `Index.tsx`: Highlight de linha selecionada em emerald — é afordância de seleção (não estado de sucesso). Pode ser bg-accent-soft/border-accent em vez de success; incerto entre seleção (accent) e eco do verde de confirmar.
- `Index.tsx`: LinkedHistorico.tsx: dot binário autorizado(emerald)/pendente(amber) = success/warning. Tokenizável (bg-success / bg-warning), mas é par de 2 estados num dot — confirmar se vira tokens semânticos ou fica como status-dot category.
- `Index.tsx`: PrazoBadge tier ≤30d em amber = warning. Faz parte da escala de urgência com os 2 blocos red; tokenizar só o amber pra warning sem resolver os red quebraria a gradação visual da escala.

### StockTransfer
- `Index.tsx`: Pill 'pending' usa rose, mas pendente nao e erro/negativo — e estado de espera. rose aqui pode ser cor de categoria do status-map (distingue pending/in_transit/completed) e nao destructive. Mapear pra destructive marcaria pendente como erro; alternativa seria muted ou info. Manter cru ate Wagner decidir a semantica de 'pending'.
- `Create.tsx`: Badge 'PARA' (filial destino) usa emerald em par visual com badge 'DE' (bg-stone-200). E accent categorico distinguindo origem x destino, nao estado de sucesso. Tokenizar pra success marcaria 'destino' como sucesso, o que e errado. Manter cru ou tratar como categoria.

### Essentials
- `Index.tsx`: amber em 'new' poderia virar warning, mas 'new' e estado inicial neutro, nao alerta. Estado vs categoria?
- `Index.tsx`: blue/sky ambiguo: info-state vs accent de status dentro do rainbow.
- `Show.tsx`: amber 'new' = warning ou estado inicial neutro?
- `Show.tsx`: sky ambiguo: info-state vs accent no rainbow de status.

### Whatsapp
- `CaptureFeedbackSheet.tsx`: Banner sky de resultado de dev-task: tratar como info-state (→info-soft/fg) ou manter como 3a cor distintiva da triade de banners?
- `ConversationThread.tsx`: Indicador de conexao Centrifugo: live(verde)=conectado e par com 'conectando'(muted). Estado-sucesso (→success) ou accent de status de conexao? Marquei uncertain por ser indicador de conectividade, nao operacao concluida.
- `ConversationThread.tsx`: Par binario janela-24h aberta(bom)/fechada(atencao). Aberta→success e fechada→warning sao defensaveis, mas sao classNames compound (border+text+bg+dark) sem token pill 1:1; tokenizar so o texto deixaria o bg cru. Edit limpo ou manter?
- `ConversationThread.tsx`: Bloqueio=estado negativo (→destructive). Mas sao classNames compound border+text+bg+dark; o token destructive-soft/fg cobriria, porem o risco de colisao de substring com outros 'bg-red-50' no arquivo e alto. Edit em massa ou pontual?
- `ConversationThread.tsx`: Warning real (AlertTriangle + janela fechada). Mas a string 'text-amber-800 ... bg-amber-50 ... border-amber-200' pode colidir com strings de nota amber; precisa edit com contexto unico maior pra evitar tocar o post-it.
- `ConversationThread.tsx`: Realce de termo pesquisado (highlight <mark>) — yellow=convencao de marca-texto, nao warning. Provavel category; registrado uncertain por ser yellow ambiguo.
- `ConversationList.tsx`: Indicador de direcao outbound (msg enviada) — emerald e accent de direcao/categoria (enviada vs recebida), nao estado-sucesso. Provavel category; uncertain por emerald ambiguo.
- `ConversationList.tsx`: Pill 'janela 24h fechada' com AlertTriangle = warning-state. ClassName compound (text+bg+border+dark) sem token pill 1:1 limpo; tokenizar so texto deixa bg cru. Edit ou manter?
- `ConversationSidebar.tsx`: Botoes cuja cor codifica o estado-alvo (resolvida=success, aguardar=warning, bloquear=destructive). Mas sao classNames compound com border+text+hover:bg+dark — sem token 1:1 pra hover:bg-*-50; tokenizar parcialmente deixaria hover/dark cru. Edit limpo possivel?
- `ConversationSidebar.tsx`: Texto de status janela-24h: aberta(positivo)/fechada(atencao). emerald→success-fg e amber→warning-fg sao limpos (so texto), mas emerald aqui e mais 'permitido/info' que 'sucesso'. Confirmar role antes de editar.
- `CustomerMemoryBlock.tsx`: Icone orange isolado sobre contador de reclamacoes — accent de atencao. orange nao tem token (warning e amber); manter ou aproximar de warning? Marquei uncertain.
- `MicRecorder.tsx`: Par de acao cancelar(vermelho)/enviar(verde) na gravacao. Compound text+hover:bg+dark sem token 1:1 pra hover:bg-*-100; e o vermelho aqui e 'descartar/cancelar' (acao destrutiva) ou so par visual? Manter por seguranca.
- `Index.tsx`: Badge 'pronto'(success), banner 'Rejeitado'(destructive), avisos amber 'sem contraparte Meta'(warning) sao estados reais. Mas convivem lado-a-lado com os mapas R-DS-002 emerald/red/amber identicos — tokenizar so os avulsos criaria divergencia visual com os badges de mapa. Confirmar se tokeniza avulsos + mapas juntos ou nenhum.

### StockAdjustment
- `Index.tsx`: Pele neutra 'stone' coesa cobrindo 9 shades (300-900 + bg-50). O token muted so oferece bg-muted/text-muted-foreground/border-border (1 valor cada) — swap em massa achataria a hierarquia tipografica (titulo stone-900 vs label stone-500 vs placeholder stone-400). Tokenizar so com aval de design ou mapeamento shade-a-shade. Mantido cru.
- `Create.tsx`: Mesma pele neutra 'stone' coesa do Index (multi-shade) usada como skin de form. Token muted nao mapeia 1:1 os varios shades; swap em massa achataria contraste. Mantido cru ate aval de design / mapeamento shade-a-shade.

### Nfse
- `Emitir.tsx`: Amber no valor do ISS pode ser warning OU pareamento de categoria (imposto-saida vs liquido). Nao e estado de atencao inequivoco — e cifra estatica pareada visualmente com o emerald do liquido. Manter cru ate confirmar intencao.
- `Emitir.tsx`: Green no valor liquido pode ser success (valor positivo) OU so categoria pareada com o amber do ISS acima. E figura financeira estatica, nao evento/estado de sucesso. Pareado com o item #308 — se um for category, ambos sao.

### Modules
- `Index.tsx`: Avatar decorativo (aria-hidden) da linha da tabela usa gradiente stone neutro. Nao e estado semantico — e skin decorativo. Nao ha token de gradiente; trocar por bg-muted apagaria o efeito from->to. Manter cru ou tokenizar so o texto?

### Home
- `Index.tsx`: stone=neutro mapearia p/ bg-muted/text-muted-foreground/border-border, mas aqui forma a pele coesa light-only do prototipo E e tambem uma linha do mapa accent (KPI 'Despesas', linha 61). Tokenizar piecemeal arrisca regressao visual e quebra a simetria do mapa rainbow. Confirmar se troca neutra global e desejada antes de editar?

### MemCofre
- `Dashboard.tsx`: Nas 3 evidencias (Pendentes/Triadas/Aplicadas) o tone le como ramp de workflow (warning/info/success), mas o MESMO prop tone serve categoria em 187-188 (Formato pasta/plano). Unificar pra token quebraria o uso categorico — manter cru ate decidir separar os dois usos do prop.
- `Memoria.tsx`: sky-500 aqui e parte da legenda categorica (project), mas blue/sky as vezes vira info-state. Como integra um rainbow de 3 cores, mantido cru com as outras pra preservar a paridade da legenda.
- `Memoria.tsx`: emerald/amber poderiam ler como success/warning isolados, mas no rootIcon sao itens de uma legenda categorica (claude/primer). Tokenizar so esses quebraria a coerencia rainbow do mapa.

### Atendimento
- `ComposerV4.tsx`: Amber marca o 'modo interno' (anotacao) do composer, nao um aviso. E accent de modo (skin coesa) ou warning-state? Conservador: nao tokenizar — troca distorceria o pareamento de modo.
- `ComposerV4.tsx`: Botao solido do 'modo interno' (Anotar) — par amber-400/amber-950 e skin de modo, nao estado. warning solido teria contraste diferente; manter ate decisao de design.
- `Index.tsx`: Icone de 'canal pareado com sucesso' — estado de sucesso, mas e par visual do bloco emerald-700 ja tokenizado; usar text-success (tint) ou manter cru pro icone? Incerto sobre o token exato de icone.
- `Index.tsx`: Icone 'LGPD aceito' — sucesso fraco/confirmacao; ambiguo entre success-tint e accent confirmatorio. Conservador: deixar pra decisao.
- `Index.tsx`: last_health_message com AlertTriangle — parece warning de saude, mas dentro do mesmo contexto do status-map multi-cor; tokenizar pra warning ou manter coerencia com o map? Incerto.
- `Index.tsx`: Aviso 'chip dedicado / risco ban Meta' (2 ocorrencias Baileys+Whatsmeow) com AlertTriangle — warning-state claro, mas repete em strings identicas; confirmar se old substring unica antes de tokenizar pra warning.
- `Show.tsx`: DetailRow 'LGPD aceito: Sim' com CheckCircle2 — confirmacao verde; ambiguo entre success-fg (texto+icone) e accent; conservador manter.
- `Show.tsx`: Texto inline '✓ conectado!' — estado de sucesso, mas frase curta de status dentro de paragrafo; success-fg provavel, confirmar.
- `Show.tsx`: Badge 'ativo' vs 'revogado' (muted) — par de estado; poderia ser success-soft/fg pill, mas e par binario de status; incerto se vira pill semantico ou mantem accent.
- `Index.tsx`: Barra de distribuicao CSAT por nota (4-5 verde / 3 amber / 1-2 destructive). O ramo 1-2 ja usa bg-destructive (tokenizado); aplicar success+warning aos outros mantem consistencia — proposto como semantic, mas e quase um escala/gradiente, confirmar se vira rainbow.
- `Variants.tsx`: Texto '(sem variantes ativas — apply usa body padrao)' — aviso informativo leve; ambiguo entre warning-fg e accent contextual; conservador manter.

### Financeiro
- `Index.tsx`: blue=estado 'Aberto'. Mapa de status (cancelado ja e muted). Estado neutro/inicial mais que info-state — info ou muted? deixo cru.
- `Index.tsx`: blue=estado 'Aberto' (mesma duvida do ContasReceber). estado inicial neutro vs info. deixo cru.
- `Index.tsx`: emerald='Ativa' — poderia ser success (estado ativo), mas vive ao lado do mapa receita=emerald; tokenizar so o badge criaria 2 verdes diferentes na mesma tela. deixo cru por coesao.
- `Index.tsx`: par credito(emerald)/debito(destructive). debito ja e token; credito poderia virar success. mas e direcao contabil, nao estado sucesso/erro. uncertain.
- `Index.tsx`: mapa de status do caixa: emerald=aberto, stone=fechado. emerald='aberto' e estado ativo (poderia ser success) mas o par usa stone neutro pro outro estado; ambiguo entre success e rainbow de status. uncertain.
- `Index.tsx`: emerald em link de acao, nao estado. provavelmente accent, deixo cru.
- `Index.tsx`: green sozinho; se for delta positivo/variacao seria success, se for tag 'novo' e categoria. sem contexto do label fica ambiguo. uncertain.
- `Index.tsx`: amber no Card pode ser alerta (warning) OU destaque de secao. assumi alerta -> warning, mas se for so realce visual reverter. revisar.
- `Index.tsx`: atrasado(rose)/proximo(amber) = severidade de vencimento. forte candidato a destructive/warning, mas e gradiente de urgencia inline, nao badge de estado. tokenizar par? deixei cru por conservadorismo.
- `Index.tsx`: varios verdes: '✓ conferido' parece success-state; mas totalIn e direcao(entrada) e 'Novo recebimento' e accent de acao. mistura de papeis no mesmo arquivo; tokenizar so o '✓ conferido' criaria 2 verdes. uncertain.
- `Index.tsx`: amber=sugerido(warning-ish) e emerald=conciliado(success) sao tokenizaveis, mas estao num mapa cujos outros 2 estados (pendente/ignorado) usam stone da skin; tokenizar so 2 de 4 quebra a simetria do Record. tokenizar o mapa todo (incl pendente/ignorado->muted)? decisao de design.
- `DrawerCobranca.tsx`: emerald 'mandato ativo' = estado ativo (success?) ou apenas accent de status-ok junto da feature violet. ambiguo. uncertain.
- `AiResumoMes.tsx`: rose num bloco de 'Atencao' de resumo executivo: warning (atencao) ou destructive (erro)? o icone e ⚠ (warning) mas a cor rose sugere erro. ambiguo. uncertain.
- `FunnelStrip.tsx`: rose=etapa em alerta no funil. destructive plausivel, mas e estado de etapa (alert) num gradiente com active(primary)/neutro(stone). tokenizar rose->destructive isolado? uncertain.
- `SheetNovaCobranca.tsx`: emerald=passo ja concluido no wizard. success plausivel, mas faz parte da escala de progresso do stepper (com stone pros outros estados). tokenizar isolado desbalanceia. uncertain.
- `BalancoView.tsx`: equacao contabil OK(emerald)/quebrada(rose) = forte candidato a success/destructive de validacao. mas o mesmo par cromatico serve +/- de valores no arquivo; tokenizar so a equacao criaria 2 verdes/vermelhos. uncertain.
- `Index.tsx`: amber sinaliza saldo abaixo do limite = semanticamente warning, porem renderizado como cor de barra/linha dentro do grafico (serie). tokenizar cor de grafico e desencorajado. uncertain.
- `FinBaixaSheet.tsx`: amber 'baixa parcial' poderia ser warning (atencao: resta saldo). texto inline, nao pill. tokenizar text-amber-700->text-warning-fg? leve, mas conservador deixei. uncertain.

### Ponto
- `Index.tsx`: stone-400 no separador do subtítulo do PageHeader: neutro->muted-foreground, mas é padrão repetido em todo o módulo (decisão de header canon) — tokenizar tudo de uma vez ou deixar pro lote de header?
- `Index.tsx`: stone-400 separador subtítulo: neutro->muted, mas padrão de header repetido no módulo
- `Show.tsx`: stone-400 separador subtítulo: neutro->muted, padrão header
- `Edit.tsx`: stone-400 separador subtítulo: neutro->muted, padrão header repetido no módulo
- `Index.tsx`: stone-400 separador subtítulo: neutro->muted, padrão header
- `Index.tsx`: stone-400 separador subtítulo: neutro->muted, padrão header
- `Reps.tsx`: stone-400 separador subtítulo: neutro->muted, padrão header
- `Index.tsx`: stone-400 separador subtítulo: neutro->muted, padrão header. Já existe text-muted-foreground em outras partes da mesma tela.
- `Form.tsx`: stone-400 separador subtítulo: neutro->muted, padrão header
- `Index.tsx`: stone-400 separador subtítulo: neutro->muted, padrão header
- `Index.tsx`: stone-400 separador subtítulo: neutro->muted, padrão header
- `Show.tsx`: stone-400 separador subtítulo: neutro->muted, padrão header
- `Create.tsx`: stone-400 separador subtítulo: neutro->muted, padrão header
- `Show.tsx`: stone-400 separador subtítulo: neutro->muted, padrão header
- `PresenceStrip.tsx`: Mapa de status de presença com variantes ring/dot derivadas (ring-emerald-500/40, dot bg-emerald-500). presente parece sucesso e atrasado warning, mas é um mapa de status com 3 derivações de tom coerentes (bg+ring+dot) e 'saiu'/'ausente' já em muted — tokenizar bg-success/ring-success/40 mantém? Conservador: incerto entre estado e mapa-de-status com tons derivados.
- `Create.tsx`: stone-400 separador subtítulo: neutro->muted, padrão header (Importacoes/Index.tsx e Show.tsx têm a mesma linha)

