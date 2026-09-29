# Handoff: Oimpresso ERP — App Mobile

## Visão geral

App mobile (iOS + Android) do **Oimpresso ERP** — sistema de gestão para gráficas / comunicação visual. É a versão de campo do ERP web (Laravel + Inertia + React, backend tRPC + Drizzle), pensada para o vendedor/operador resolver coisas no celular: ver o dia, despachar tarefas, consultar e criar pedidos, acompanhar produção, cadastrar pessoas e produtos, e fazer venda rápida com leitor de código de barras.

A navegação é por **tab bar inferior de 5 abas** + pilhas de navegação por aba (push/pop com botão voltar). O 5º item ("Mais") é um hub que abre os módulos secundários, evitando inflar a barra.

> **Idioma:** todo label/copy em **PT-BR**. Código (nomes de classe, métodos) em inglês é ok.

---

## Sobre os arquivos de design

Os arquivos em `design/` são **referências de design feitas em HTML/React-via-Babel** — protótipos que mostram aparência e comportamento pretendidos, **não** código de produção para copiar direto. A tarefa é **recriar estes designs no ambiente do app real**.

O ERP real é **React 19 + TypeScript + Tailwind 4 + Inertia v3** no web; para o app mobile o alvo natural é **React Native (Expo)** — o pacote `.rar` original da arquitetura usa Expo Router, tRPC, Drizzle. Use os padrões e a biblioteca de componentes já estabelecidos no projeto mobile. Se ainda não existir base, **Expo + Expo Router + NativeWind (Tailwind RN)** é a escolha recomendada, porque preserva os mesmos tokens.

O protótipo roda no navegador (web components + React via Babel) só para demonstração. Mapeie:
- `<div>`/CSS → `View` + estilos (NativeWind ou StyleSheet)
- `.oi-scroll` → `ScrollView` / `FlatList`
- molduras `IOSDevice`/`AndroidDevice` → **descartar** (são bezels de protótipo; o app real roda no device)
- `oi-frame`, `design-canvas`, `tweaks-panel` → **descartar** (andaimes de apresentação)

---

## Fidelidade

**Alta fidelidade (hi-fi).** Cores, tipografia, espaçamento, raios e interações são finais. Recriar a UI fielmente usando a stack do app. Os valores exatos estão em **Design Tokens** abaixo e em `design/oimpresso-tokens.css` (fonte de verdade).

---

## Identidade visual / marca

Marca **Oimpresso** (ex-"Office Impresso"). Cor institucional: **magenta/roxo**. Logo: cubo CMYK (`design/assets/oimpresso-logo.png`).

- **Accent do app = magenta da marca** (não azul). Recolore botões primários, abas ativas, FAB, barras de progresso, foco e chips selecionados.
- Neutros (fundos/superfícies/bordas) têm **leve tinta roxa** (hue ~322, croma muito baixo) para puxar à marca sem virar um banho de cor.
- Cores institucionais expostas como tokens: `--brand-magenta`, `--brand-purple`, `--brand-deep`, `--brand-cyan`, `--brand-yellow` (CMYK do cubo).
- Login usa gradiente `--brand-deep → --brand-purple → --brand-magenta` com a logo.

---

## Temas (claro/escuro)

Dois temas completos, alternados por atributo `data-theme="light|dark"` no container raiz. No app real, mapear para o theme provider (ex.: `useColorScheme` + tokens). Ambos calibrados para **baixa agressividade**: claro = off-white levemente lilás; escuro = carvão levemente arroxeado (não preto puro). Densidade ajustável (`data-density="compact|normal|comfy"`) altera a altura das linhas de lista.

---

## Telas / Views

> Estrutura comum: cada tela é um header (título grande OU "DetailHeader" com voltar) + corpo rolável + opcional barra de ação fixa no rodapé. Hit targets mínimos 44px.

### 1. Home / Início (aba)
- **Propósito:** painel do dia, adaptável ao papel do usuário.
- **Layout:** header com saudação ("Bom dia, Wagner") + eyebrow data; à direita sino de notificações (com badge) e avatar.
- **Componentes:**
  - Pílula de **empresa ativa** (avatar colorido + nome + chevron) → abre seletor de tenant.
  - Card **"Faturado hoje"** (fundo accent-soft): valor grande em fonte mono, barra de progresso vs meta, % e variação vs ontem.
  - **3 KPIs** em grid: OS Hoje / Em aberto / Estoque baixo (este em variante "warn").
  - **Atalhos** (grid 4 col): Novo pedido, Venda rápida, Cobrar PIX, Conciliar.
  - **Próximas tarefas** (até 3 cards) com badge de origem + flame se urgente.
  - **Financeiro**: 2 cards A receber / A pagar.
- **Estados:** `taskState` (vazio/cheio/urgente) muda contagem de urgentes e a lista; vazio mostra empty-state "Tudo em dia".

### 2. Tarefas (aba) — inbox unificada
- **Propósito:** uma caixa só agregando pendências de todos os módulos (padrão TaskProvider do ERP).
- **Layout:** header + busca + chips de filtro por origem (Todas/OS/Financeiro/Clientes/Produção/Ponto) com contadores; corpo agrupado por **Hoje / Amanhã / Esta semana**; FAB (+).
- **Card de tarefa:** badge de origem (OS/CRM/FIN/PNT/MFG), flame se urgente, prazo (mono, vermelho se urgente), título 13.5px/600, subtítulo. Urgente = borda esquerda 3px danger.
- **Empty-state:** "Inbox zero".

### 3. Tarefa — detalhe (push)
- DetailHeader com id + origem. Card de cabeçalho (origem grande, urgente, prazo, título, sub). **Viewer específico por origem** (ver "Task Viewers"). Histórico (timeline). Barra inferior: **Adiar** + **Concluir**.

### 4. Pedidos (aba)
- **Layout:** header + busca (com ícone scan) + chips (Ativos/Urgentes/Concluídos/Todos); lista densa; FAB → novo-pedido.
- **Linha:** id mono, status pill por etapa, valor à direita; cliente; produto; barra de progresso + prazo. Urgente = borda esquerda danger.

### 5. Pedido — detalhe (push)
- Card hero (status, urgente, prazo, produto, valor grande).
- **Stepper de andamento** (Orçamento → Aprovação → Produção → Entrega → Concluído): bolinhas com check nas concluídas, linha de progresso accent; header mostra "próxima: X". Linha de controles: **Voltar etapa · Pular · Cancelar OS**.
- Detalhes (def list), Arte (quando aplicável), Contato.
- **Barra inferior contextual** muda conforme etapa: "Aprovar arte" / "Liberar produção" / "Saiu para entrega" / "Confirmar entrega" / "Reabrir pedido"; + atalhos WhatsApp/Imprimir.

### 6. Produção (aba) — fila por estação
- **Carga das estações** (Impressora 4 cores, Plotters, Acabamento, Expedição): barras com cor por carga (warn >70%, danger >85%); tap filtra a fila.
- Chips de estação. **Jobs** (cards) com prioridade (faixa danger/warn), status pill, progresso quando rodando.
- **Job detalhe (push):** hero + stepper de etapas (Em fila → Imprimindo → Acabamento → Pronto) + barra contextual ("Iniciar" / "Concluir etapa" / "Enviar para expedição").

### 7. Mais (aba) — hub
- Card **destaque "Venda rápida"** (gradiente accent).
- Grid **Módulos:** Produtos, Pessoas, Finanças, Relatórios, Equipe, Fornecedores.
- Lista **Ferramentas:** Cobrar PIX, Imprimir etiqueta, Calculadora gráfica.
- Lista **Conta:** Perfil, Empresa ativa, Notificações.
- **Lockup da marca** no rodapé (logo + wordmark + versão).

### 8. Venda rápida (push) — PDV móvel
- **Scanner:** viewfinder com cantos animados + linha de scan (animação CSS `oi-scan`). No app real: `expo-camera` + `expo-barcode-scanner`. Régua inferior de produtos simula "bipe".
- **Carrinho:** linhas com stepper +/−, subtotal, desconto, total.
- **Checkout:** bottom-sheet de pagamento (PIX/Crédito/Débito/Dinheiro/Boleto, radio visual) → tela de sucesso (recibo, Imprimir/Enviar, Nova venda).

### 9. Pessoas (push, via Mais) — cadastro unificado
- **Conceito-chave:** não é "clientes" — é **pessoas com papéis múltiplos**. Uma pessoa pode ser **cliente E fornecedor E funcionário** simultaneamente.
- **Lista:** busca + **filtros por papel** (Todos/Clientes/Fornecedores/Funcionários/Transportadoras/Em débito) com contadores; agrupada por inicial; cada linha mostra **badges de papel** (CLI/FORN/FUNC/TRANSP, cor por papel) + PF/PJ + saldo/OS; INATIVO badge; FAB → novo-cliente.
- **Detalhe (push):** hero (avatar, doc, **badges de papel**, ações WhatsApp/fone/email), 3 KPIs (Pedidos/Ticket/Saldo), linha **"Dados cadastrais"** → ficha completa, Contato, Pedidos recentes, CTA "Novo pedido para X". Lápis no header → editar.
- **Ficha cadastral (push, read-only):** seções Identificação/Contato/Endereço fiscal/Comercial/LGPD, cada uma com link **Editar** que abre o wizard naquela etapa.

### 10. Novo/Editar Pessoa (push) — wizard
- **Padrão wizard:** chips de etapa no topo (clicáveis + check nas concluídas) + barra inferior **Voltar / Avançar → Salvar**.
- Etapas: **Dados** (Classificação multi-seleção de papéis; tipo PF/PJ com campos condicionais; nome/razão, fantasia, CPF/CNPJ com **Buscar** Receita; contribuinte; IE; atalhos Escanear doc/Voz) · **Contato** · **Endereço** (CEP com **Buscar** auto-preenche) · **Comercial** · **LGPD** (toggles de consentimento). Tela de sucesso encadeia "Criar primeiro pedido".
- **Edição:** carrega dados existentes pré-preenchidos, título "Editar cliente", botão "Salvar alterações".

### 11. Novo/Editar Produto (push) — wizard (mesmo padrão)
- Etapas: **Dados** (tipo Produto/Serviço/Insumo; nome; SKU com **Gerar**; código de barras com **Bipar**; categoria; unidade) · **Preços** (venda, custo, **margem calculada ao vivo** com alerta <20%, promocional) · **Estoque** (toggle controla estoque; atual/mínimo; localização; fornecedor — puxa só pessoas com papel Fornecedor) · **Fiscal** (NCM, CFOP, Origem, CEST) · **Ficha técnica** (foto, gramatura, acabamento, descrição).

### 12. Produtos (catálogo, via Mais)
- Header + busca (scan) + chips de categoria; **toggle lista/grid**; indicador de estoque baixo. Detalhe com imagem, preço, estoque vs mínimo (barra), especificações, movimentações; lápis → editar-produto.

### 13. Finanças / Transações (via Mais)
- Saldo hero (entradas/saídas), cards de contas, chips Todas/Entradas/Saídas, lista agrupada por dia. Detalhe da transação com comprovante.

### 14. Notificações / Perfil / Empresa ativa (push, via Mais ou header)
- Notificações: lista com badge de origem + não-lida. Perfil: avatar, role, empresa ativa, preferências (tema, densidade, push). Empresa ativa: seletor de tenant (atualiza todas as telas).

### 15. Login
- Header gradiente da marca + logo cubo CMYK + wordmark "Oimpresso" + subtítulo. Botões: "Continuar com Manus" (OAuth) e "Login OAuth corporativo". O ERP real usa OAuth (ver `lib/_core/auth`, `constants/oauth`).

---

## Task Viewers (por origem)

A tela de Tarefa-detalhe renderiza um componente específico conforme `task.viewer`. Implementados:
- **OsAprovarArte** — preview da arte + dados da OS + decisão (Aprovar/Pedir ajuste/Reprovar).
- **FinBoleto** — valor, linha digitável, ações (WhatsApp, PIX, prorrogar).
- **CrmContato** — contato + histórico + WhatsApp/Ligar.
- **MfgLiberar** — resumo OS + carga da fila de produção.
- **OsEntrega** — rota/motoboy + endereço + abrir no mapa.
- **PntJustificar** — marcações do dia + justificativa (chips + textarea).
- **CrmOrcamento** — pedido do cliente + modelos rápidos.
- **FinConciliar** — PIX sem vínculo + sugestões de vínculo.

No backend real, cada módulo registra um `TaskProvider` (ver CLAUDE.md do ERP) com `origin()`, `color()`, `for(User)`, `viewerComponent()`; `TaskRegistry` agrega em `/api/tasks/inbox`.

---

## Interações & comportamento

- **Navegação:** tab bar (5) troca de aba mantendo a pilha de cada uma; dentro da aba, push/pop. **Toda tela empilhada tem botão voltar** (regra firme — nunca deixar o usuário preso). O header raiz de aba não mostra voltar; telas empilhadas mostram (condicional `canPop()`).
- **Wizards (Pessoa/Produto):** chips navegáveis livremente + Voltar/Avançar; última etapa = Salvar; tela de sucesso.
- **Buscas simuladas** (CNPJ/CEP/código de barras): spinner ~900ms e auto-preenchimento. No real: APIs Receita/ViaCEP/leitor de câmera.
- **Margem (produto):** recalculada ao vivo a partir de preço/custo; cor muda <20%.
- **Atalhos do ERP (herdar no real):** J/K navegar, E concluir, A adiar, N novo, / buscar, ⌘K global (no mobile, mapear para gestos/botões equivalentes).
- **Persistência:** o ERP usa `localStorage` com prefixo `oimpresso.` para empresa ativa, aba, filtros, painéis. No app, usar storage equivalente (AsyncStorage/MMKV) com mesmo prefixo.
- **Animações:** scanline do viewfinder (keyframes `oi-scan`), transições de toggle 0.15s, barras de progresso.

---

## Gerenciamento de estado

- **Global:** empresa ativa (tenant), tema, densidade, usuário/sessão.
- **Por tela:** filtros de chip, query de busca, etapa do wizard, formulário do wizard, estado do carrinho (venda rápida), tarefa/pedido/produto selecionado.
- **Dados:** no protótipo vêm de `mock-data.jsx`. No real, tRPC (`server/routers/pedidos|produtos|transacoes|notifications|ops`) + cache offline Drizzle. Helpers `fullCliente(c)` e `fullProduto(p)` mostram a forma completa esperada de cada entidade.

---

## Design Tokens

> Fonte de verdade: `design/oimpresso-tokens.css`. Valores em **oklch**. Abaixo os principais (tema claro / tema escuro).

### Marca
| Token | Claro | Escuro |
|---|---|---|
| `--accent` (magenta) | `oklch(0.50 0.155 330)` | `oklch(0.66 0.155 332)` |
| `--accent-2` | `oklch(0.57 0.150 330)` | `oklch(0.72 0.150 332)` |
| `--accent-soft` | `oklch(0.95 0.035 330)` | `oklch(0.36 0.080 332)` |
| `--brand-magenta` | `oklch(0.55 0.205 350)` | `oklch(0.64 0.205 350)` |
| `--brand-purple` | `oklch(0.42 0.150 330)` | `oklch(0.52 0.160 330)` |
| `--brand-deep` | `oklch(0.30 0.120 328)` | `oklch(0.34 0.130 328)` |
| `--brand-cyan` | `oklch(0.82 0.130 200)` | — |
| `--brand-yellow` | `oklch(0.92 0.150 100)` | — |

### Superfícies / texto (leve tinta roxa, hue 322)
| Token | Claro | Escuro |
|---|---|---|
| `--bg` | `oklch(0.975 0.004 322)` | `oklch(0.215 0.008 322)` |
| `--bg-2` | `oklch(0.955 0.006 322)` | `oklch(0.195 0.008 322)` |
| `--surface` | `oklch(0.993 0.003 322)` | `oklch(0.245 0.009 322)` |
| `--surface-2` | `oklch(0.965 0.005 322)` | `oklch(0.280 0.010 322)` |
| `--border` | `oklch(0.905 0.007 322)` | `oklch(0.310 0.011 322)` |
| `--border-2` | `oklch(0.935 0.006 322)` | `oklch(0.278 0.010 322)` |
| `--text` | `oklch(0.30 0.014 322)` | `oklch(0.91 0.008 322)` |
| `--text-dim` | `oklch(0.52 0.014 322)` | `oklch(0.69 0.008 322)` |
| `--text-mute` | `oklch(0.66 0.012 322)` | `oklch(0.56 0.008 322)` |

### Status
| Token | Claro | Escuro |
|---|---|---|
| `--danger` | `oklch(0.60 0.135 25)` | `oklch(0.68 0.130 25)` |
| `--warn` | `oklch(0.70 0.110 78)` | `oklch(0.76 0.105 78)` |
| `--ok` | `oklch(0.62 0.105 150)` | `oklch(0.72 0.100 150)` |

### Origens (badges de módulo — semânticas, mantidas)
`OS` (âmbar/70), `CRM` (azul/235), `FIN` (verde/150), `PNT` (roxo/322), `MFG` (laranja/40). Cada uma com `--origin-<X>-bg` e `--origin-<X>-fg` por tema (ver CSS).

### Raios / sombras / linhas
- `--radius: 10px`, `--radius-sm: 7px`, `--radius-lg: 14px`, `--radius-pill: 999px`
- `--shadow-pop: 0 8px 24px -12px rgba(40,45,60,.16), 0 2px 5px -3px rgba(40,45,60,.08)` (escuro: alfa maior)
- `--shadow-soft: 0 1px 2px rgba(40,45,60,.04)`
- Altura de linha: `--row-h` 56px (compact 46 / comfy 62)

### Tipografia
- **Sans:** `IBM Plex Sans` (400/500/600/700) — base 14.5px
- **Mono:** `IBM Plex Mono` (400/500/600) — usada para números, valores monetários (`font-variant-numeric: tabular-nums`), documentos, ids, SKUs, prazos
- Títulos de tela: 22px/600, letter-spacing -0.02em. Section headers: 10.5px/700 uppercase, letter-spacing .08em, cor text-mute.

---

## Assets

- `design/assets/oimpresso-logo.png` — logo cubo CMYK Oimpresso (84×70, transparente). Usada no Login e no lockup do hub Mais. Substituir por SVG vetorial se houver no projeto.
- **Ícones:** conjunto de traço próprio em `icons.jsx` (stroke 1.6, viewBox 24). No app real, usar a biblioteca de ícones do projeto (ex.: `lucide-react-native`) — os nomes mapeiam para os equivalentes Lucide (home, inbox, box, tag, etc.).
- **Fontes:** IBM Plex Sans/Mono via Google Fonts (no Expo, usar `@expo-google-fonts/ibm-plex-sans` e `-mono`).

---

## Arquivos (em `design/`)

| Arquivo | Conteúdo |
|---|---|
| `Oimpresso Mobile.html` | Entry point — monta os 3 frames (iOS/Android/Login) + Tweaks. Andaime de apresentação. |
| `oimpresso-tokens.css` | **Tokens + todos os estilos de componente** (`.oi-*`). Fonte de verdade visual. |
| `mock-data.jsx` | Dados de exemplo + `PAPEIS`, `CATEGORIAS`, `UNIDADES` + helpers `fullCliente`/`fullProduto`. Forma das entidades. |
| `mobile-app.jsx` | Shell: tab bar, mapa de rotas, pilha de navegação por aba. |
| `screens-home-tasks.jsx` | Home + Tarefas + primitivos (ScreenHeader, DetailHeader, OriginBadge, StageStatus). |
| `screens-modules.jsx` | Pedidos, Produtos, Finanças, Notificações, Perfil, Empresa, Login. |
| `screens-clientes-producao.jsx` | Mais (hub), Pessoas (lista+detalhe), Produção (fila+job), Venda rápida, `PapelBadges`. |
| `screens-novo-cliente.jsx` | Wizard de pessoa (novo/editar) + ficha cadastral read-only. |
| `screens-novo-produto.jsx` | Wizard de produto (novo/editar). |
| `task-viewers.jsx` | Os 8 viewers de tarefa por origem. |
| `icons.jsx` | Ícones de traço (mapear para lib do projeto). |
| `ios-frame.jsx`, `android-frame.jsx`, `design-canvas`, `tweaks-panel.jsx` | **Andaimes de protótipo — descartar.** |

---

## Como rodar o protótipo (referência)
Abrir `design/Oimpresso Mobile.html` em um servidor estático (os `.jsx` são carregados via `<script type="text/babel">`). Não é build de produção — só para inspeção visual e de comportamento.
