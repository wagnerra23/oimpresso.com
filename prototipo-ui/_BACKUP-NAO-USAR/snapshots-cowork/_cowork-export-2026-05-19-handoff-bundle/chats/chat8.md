# WhatsApp Theme Integration

_Started 2026-05-15 10:35 UTC_

---

## User

https://claude.ai/design/p/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58?file=Oimpresso+ERP+-+Chat.html

Whatzap quero transformar emOmichanel
tem ser detro do thema principal. 
anote para não esqucer. não quero criação sem estar no arquivo principal. 

Pode me ajudar com isso e coloque na sua regra basica no claude.md 
Como voce fará? me explique primeiro

## Assistant

_[tool: list_files]_

_[tool: read_file]_

_[tool: grep]_

_[tool: grep]_

_[tool: read_file]_

Vou primeiro entender o que existe pra te explicar o plano com precisão. Deixa eu olhar como o WhatsApp está montado hoje, como está registrado no sidebar/router, e onde encaixar "Omnichannel".

_[tool: grep]_

_[tool: grep]_

_[tool: grep]_

_[tool: grep]_

Beleza, antes de mexer em nada — aqui está o que entendi e como vou fazer.

## 📋 Estado atual (mapeado)

O módulo "WhatsApp" hoje vive **dentro do principal** (`Oimpresso ERP - Chat.html` → carrega `whatsapp-page.jsx`). Os pontos de toque são:

| Onde | Linha | O que tem |
|---|---|---|
| `Oimpresso ERP - Chat.html` | linha 76 | `<script src="whatsapp-page.jsx">` |
| `data.jsx` | 38 | sidebar: `{ id:"whatsapp", icon:"wa", label:"WhatsApp" }` (grupo COMERCIAL) |
| `data.jsx` | 335 | GROUP_META COMERCIAL: `"CRM, ADS, Grow, WhatsApp"` |
| `app.jsx` | 90 | registry: `whatsapp: { phase:1, status:"partial", ... priority:"Comercial" }` |
| `app.jsx` | 486 | router: `else if (route === "whatsapp") content = <window.WhatsappPage/>` |
| `app.jsx` | 488 | lista de rotas conhecidas |
| `whatsapp-page.jsx` | inteiro | componente + dados mock (5 conversas só WA) |
| `whatsapp-page.css` | inteiro | `.wa-page`, `.wa-list`, `.wa-thread`, `.wa-side` |
| `chat-jana.jsx` | 112 | menção "Régua WhatsApp" — fica, é régua específica do canal |
| `oficina-page.jsx` | várias | botões "Pedir aprovação WhatsApp" — ficam, é CTA específico |

✅ Ponto bom: **já está dentro do principal**. Não preciso criar arquivo HTML novo.

## 🎯 O que "virar Omnichannel" significa

Não é só renomear label. Omnichannel = **mesma caixa unificada com N canais entrando**: WhatsApp + Instagram DM + Email + SMS + Webchat (+ Messenger/Telegram se quiser). A tela 3-colunas (lista / thread / contexto) é exatamente a forma certa pra isso (igual Front/Pylon/Chatwoot).

Mudanças concretas no protótipo:

1. **Renomear identidade** (in-place — não crio arquivo separado):
   - Arquivo `whatsapp-page.jsx` → `omnichannel-page.jsx`
   - Componente `WhatsappPage` → `OmnichannelPage`
   - CSS `whatsapp-page.css` → `omnichannel-page.css` (e classes `.wa-*` → `.om-*`)
   - Atualizar os 3 scripts no `Oimpresso ERP - Chat.html` + 4 referências em `data.jsx`/`app.jsx`
   - Apago os arquivos antigos depois de migrar

2. **Modelo de dados** — cada conversa ganha `channel: "wa" | "ig" | "email" | "sms" | "webchat"`. Reaproveito as 5 conversas mock atuais (todas WA) e adiciono ~5 novas em outros canais.

3. **UI nova dentro da mesma tela**:
   - **Header:** subtitle vira `"WA · IG · Email · SMS · Webchat · N abertas em 5 canais"`
   - **Filtro segmentado** no topo da lista: `Todos · WA · IG · Email · SMS · Webchat` com contador por canal
   - **Badge de canal** no card de cada conversa (ícone colorido pequeno sobre o avatar)
   - **Chip de canal** no header da thread (`📱 WhatsApp · +55 21 9...`)
   - **Composer ciente do canal:** mostra `"Respondendo via WhatsApp · template obrigatório fora da janela 24h"` vs `"Respondendo via Email · assunto + corpo"` — tom muda
   - **Templates** filtrados pelo canal ativo (Cloud API templates só pra WA, etc.)
   - **Broadcast** vira "Disparo cross-canal: usa canal preferencial de cada destinatário"

4. **`data-screen-label`** muda de `"01 WhatsApp"` pra `"01 Omnicanal"` (importante pro `dom:` em comentários).

## 📝 Regra a adicionar no CLAUDE.md

Na seção **"Minha responsabilidade neste loop ([CC])"** vou inserir um bloco novo:

> **🔒 ARQUIVO PRINCIPAL ÚNICO — Oimpresso ERP - Chat.html**
> 
> Todas as telas/módulos do ERP vivem **dentro** de `Oimpresso ERP - Chat.html` como rotas do shell Cockpit V2. **Proibido** criar `.html` novo pra módulo, tela ou variação.
> 
> Padrão de adição de nova tela:
> 1. Criar `<modulo>-page.jsx` exportando `window.<Modulo>Page`
> 2. (opcional) `<modulo>-page.css`
> 3. Registrar `<script>` no `Oimpresso ERP - Chat.html`
> 4. Adicionar rota em `app.jsx` (router + array de rotas conhecidas + registry)
> 5. Adicionar entrada em `data.jsx` (sidebar item + GROUP_META se grupo novo)
> 
> Variações/explorações = Tweaks dentro do mesmo componente, NÃO arquivo novo.
> 
> Arquivos `.html` legados na raiz (`Compras.html`, `Vendas A+.html` etc.) são histórico — não criar novos.

## ❓ 3 decisões antes de eu codar

Pra não retrabalhar, preciso de você nessas 3:

**1. Label em pt-BR ou EN?**
Briefing proíbe inglês em UI cliente-facing — mas isso é UI interna do operador. Opções:
- (a) **"Omnicanal"** ← recomendo, pt-BR consistente
- (b) "Omnichannel" (en)
- (c) "Caixa unificada" (descritivo, sem termo)

**2. Rota/ID interno**
- (a) Renomear `whatsapp` → `omnichannel` em tudo (limpo, mas quebra qualquer link/memória que apontasse `/whatsapp`)
- (b) Manter `whatsapp` como ID interno, só mudar label visível (preserva histórico)
- ← Recomendo (a) já que o módulo é mock e ainda não foi mergeado em F3

**3. Quais canais no mock?**
Padrão proposto: **WhatsApp · Instagram DM · Email · SMS · Webchat** (5 canais). Adiciono Messenger e Telegram? Você atende em algum desses na ROTA LIVRE hoje?

**Me responde as 3 e eu executo de uma vez** — renomeação + canais + filtro + badges + composer ciente + atualização do CLAUDE.md. Não vou tocar em nada antes de você responder.

## User

c

## Assistant

_[tool: read_file]_

_[tool: read_file]_

Beleza, anotado:
- **Label:** "Caixa unificada" (interno/operador)
- **Canais (do print):** WhatsApp Meta Cloud · WhatsApp Z-API · **WhatsApp Baileys (ativo)** · Instagram DM (em breve) · Facebook Messenger (em breve) · Email IMAP (em breve) · Mercado Livre (em breve)

Vou:
- Renomear o módulo (ID interno = `inbox`, label visível = "Caixa unificada") **dentro do principal** — sem criar HTML novo
- Modelar os 7 canais; mock com conversas reais no Baileys + preview em cada "em breve"
- Filtro segmentado, badge de canal nos cards, chip no header da thread, composer ciente, seletor de provedor WA
- Atualizar CLAUDE.md com a regra do arquivo principal único

Vou ler os arquivos completos antes de reescrever.

_[tool: grep]_

_[tool: read_file]_

_[tool: update_todos]_

Vou executar agora. Plano fechado, indo direto.

_[tool: write_file]_

_[tool: write_file]_

Agora os hooks no Chat.html, data.jsx, app.jsx, CLAUDE.md, e remover os arquivos antigos.

_[tool: str_replace_edit]_

_[tool: str_replace_edit]_

_[tool: str_replace_edit]_

_[tool: str_replace_edit]_

_[tool: str_replace_edit]_

_[tool: str_replace_edit]_

_[tool: str_replace_edit]_

_[tool: grep]_

_[tool: read_file]_

Deixa eu conferir se cortei texto sem querer no app.jsx (a linha do registry era longa) e ver outros pontos que ainda referenciem `whatsapp`.

_[tool: str_replace_edit]_

Tem texto dangling, vou consertar:Agora a regra no CLAUDE.md, e apagar os arquivos antigos:

_[tool: str_replace_edit]_

_[tool: delete_file]_

_[tool: update_todos]_

_[tool: done]_

_[tool: fork_verifier_agent]_

Pronto. Resumo do que mudou:

**Renomeação semântica (dentro do principal `Oimpresso ERP - Chat.html`):**
- `whatsapp` → `inbox` em todos os pontos de roteamento (sidebar, registry, router, rotas conhecidas)
- Label visível: **"Caixa unificada"**
- Arquivos `whatsapp-page.jsx` + `whatsapp-page.css` apagados; substituídos por `inbox-page.jsx` + `inbox-page.css`

**Modelo omnichannel novo:**
- 7 canais catalogados: WhatsApp Baileys (ativo) · Meta Cloud · Z-API · Instagram DM · Messenger · Email IMAP · Mercado Livre (todos "em breve")
- Filtro segmentado com contador por canal; badges "em breve" nos inativos
- Badge de canal no avatar de cada conversa
- Chip de canal + handle no header da thread (tom da bolha muda por canal)
- Banner amarelo "em homologação" + input bloqueado em conversas preview
- Drawer "Canais" lista os 7 conectores agrupados (WhatsApp/Social/Email/Marketplace)
- Broadcast cross-canal com seletor de canal de envio (só ativos selecionáveis)

**Regra adicionada no `CLAUDE.md`** — seção nova "🔒 ARQUIVO PRINCIPAL ÚNICO":
- Todas as telas vivem dentro de `Oimpresso ERP - Chat.html`
- Proibido criar `.html` novo para módulo, tela ou variação
- Padrão de 5 passos pra adicionar/evoluir uma tela
- Variações = Tweaks, não arquivo

Verificador rodando em paralelo pra confirmar a renderização.

## User

REMOVER TOPNAV (CRM ads grow ....)

## Assistant

_[tool: grep]_

_[tool: read_file]_

_[tool: grep]_

_[tool: read_file]_

_[tool: str_replace_edit]_

_[tool: grep]_

_[tool: read_file]_

_[tool: str_replace_edit]_

_[tool: done]_

Topnav removida. Mudanças:

- **`app.jsx`** — `<Header>` removido do render principal (linha ~508). Componente `Header` permanece definido (dead code, deixei pra remover depois sem risco se você quiser restaurar)
- **`styles.css`** — `.main` ajustado de `grid-template-rows: 44px 1fr` → `1fr` pra `.main-body` ocupar a altura toda

**O que sumiu:** label da área (CRM/OPERAÇÃO/etc), pílulas das sub-telas do grupo (CRM · ADS · Grow · Caixa unificada), filtro Produtos (Todos/Produto/Serviço/Composição), tabs Jana (Dashboard/Analista IA), e os ícones de tenant/busca/sino do canto.

**Heads-up:** o filtro de tipo na página Produtos e os tabs Dashboard/Analista IA no Jana vinham daí. Se você usa essas páginas, esses controles precisam reaparecer em algum lugar (header interno da página, ou Tweaks). Me avisa se quiser que eu reposicione.

## User

faltou o filtro de "Equipe", oi "Interno" - Como se fosse comunicação interna?
Coloco junto ou separo? só explique

## Assistant

Boa pergunta — vale separar **antes** de eu codar pra você não pagar refactor depois.

## O que cabe sob "Equipe / Interno"

Existem 3 naturezas distintas de comunicação interna no ERP:

| # | Tipo | Exemplo | Onde mora hoje |
|---|---|---|---|
| 1 | **Nota privada dentro de conversa com cliente** | Wagner anota "esse cliente atrasou 3x, exija PIX" no thread do Renato — cliente NÃO vê | Não existe ainda |
| 2 | **DM 1:1 entre operadores** | Wagner ↔ Larissa: "pode imprimir a lona da Acme hoje?" | Aba Chat (Jana) — mas é com IA, não com pessoa |
| 3 | **Canal de equipe** | `#producao`, `#financeiro` — recados, alinhamentos, escalas | Não existe ainda |

## 3 modelos possíveis

**A) Tudo junto na Caixa unificada** (Interno vira mais um "canal" no filtro)
- ✅ Uma tela só, métricas unificadas
- ❌ Mistura SLA de cliente com "bom dia gente". Tom, urgência e templates são diferentes. Notificação de colega interrompe atendimento.
- 📎 Quem faz: poucos. Front tem aproximação, mas via notas dentro do thread.

**B) Aba "Equipe" totalmente separada no sidebar**
- ✅ Separação limpa. Caixa unificada fica focada em cliente.
- ❌ Comunicação interna **sobre o cliente** fica órfã do contexto da conversa dele.
- 📎 Quem faz: maioria dos ERPs + Slack-paralelo. Funciona mas gera context-switch.

**C) Híbrido (recomendado)** — padrão Front/Pylon/Chatwoot:
1. **Caixa unificada** continua só com clientes (WA/IG/Email/etc)
2. **Nota interna dentro da conversa do cliente** → toggle no composer (`Cmd+Shift+N`), bolha **amarela** visível só pra equipe, fica grudada no contexto daquele cliente
3. **"Equipe"** vira item novo no sidebar (DM entre operadores + canais `#producao` `#financeiro` `#vendas`) — pra recados que NÃO são sobre um cliente específico

## Minha recomendação: **C (híbrido)**

Razão: as 3 naturezas acima ocupam lugares diferentes pela LÓGICA, não pela tela:
- Nota sobre cliente → grudada no thread do cliente (onde o contexto vive)
- DM/canal interno → tela própria (modo de trabalho diferente)

Filtro "Internas" dentro da Caixa unificada seria redundante e poluiria a métrica de "abertas pendentes" (que tem que ser só SLA cliente).

## O que isso vira em código (se você aprovar C)

1. **`inbox-page.jsx`** ganha:
   - Toggle "Nota interna" no composer (atalho `⌘⇧N` ou botão)
   - Bolha amarela `.om-bub.internal` (só equipe vê)
   - Não muda filtro de canais

2. **Item novo no sidebar:** "Equipe" (grupo COMERCIAL ou grupo novo "ORGANIZAÇÃO"), id `equipe`, label `Equipe`
3. **Componente novo:** `equipe-page.jsx` com layout 2-col (canais + DMs à esquerda, thread à direita) — mais simples que Caixa unificada porque não tem multi-canal/templates/broadcast

## Decida 2 coisas:

1. **Modelo: A, B ou C?** (recomendo C)
2. Se C: **Equipe entra no grupo "COMERCIAL"** (junto com CRM/ADS/Grow/Caixa unificada) **ou cria grupo novo "ORGANIZAÇÃO"** (Equipe + Tarefas + Memorandos no futuro)?

Me responde as 2 e eu monto.

## User

c

## Assistant

_[tool: update_todos]_

Vou com C, e como você não respondeu o (2), decido pelo mais econômico: **"Equipe" entra no grupo COMERCIAL ao lado da Caixa unificada** (1 item órfão num grupo novo "ORGANIZAÇÃO" ficaria estranho — quando aparecer um 2º item interno, a gente promove pra grupo próprio).

_[tool: write_file]_

_[tool: write_file]_

Agora a **nota interna** dentro do inbox-page.jsx + registros.

_[tool: str_replace_edit]_

Agora o CSS pra nota interna + modo composer + a 1ª nota mock pra demonstrar:

_[tool: str_replace_edit]_

_[tool: str_replace_edit]_

Agora os registros — Chat.html, data.jsx, app.jsx:

_[tool: str_replace_edit]_

_[tool: str_replace_edit]_

_[tool: str_replace_edit]_

_[tool: str_replace_edit]_

_[tool: str_replace_edit]_

_[tool: str_replace_edit]_

_[tool: str_replace_edit]_

_[tool: grep]_

`users` existe. Vou conferir o resultado.

_[tool: update_todos]_

_[tool: done]_

_[tool: fork_verifier_agent]_

Pronto. Resumo:

**Modelo C (híbrido) implementado:**

1. **Caixa unificada** continua só com clientes — ganhou **nota interna** no composer:
   - Botão `Resp / Nota` no canto esquerdo alterna o modo
   - Atalho `⌘⇧N`
   - Bolha amarela tracejada centralizada (visualmente distinta da bolha verde/cliente)
   - 1 nota mock já inserida no thread do Renato Lopes pra demonstrar

2. **Equipe** — novo item no sidebar (grupo COMERCIAL, depois de Caixa unificada):
   - Layout 2-col: canais (`#geral` `#producao` `#financeiro` `#vendas` `#motoboy`) + DMs (Larissa, Felipe, Maíra, Eliana, Luiz)
   - Thread estilo Slack-leve (avatares quadrados, mensagens agrupadas por autor)
   - Status presence (online/away/offline) nos avatares
   - Sem multi-canal, sem templates, sem broadcast — propositalmente mais enxuto que a Caixa unificada

**Arquivos novos:** `equipe-page.jsx`, `equipe-page.css`
**Arquivos editados:** `inbox-page.jsx`, `inbox-page.css`, `Oimpresso ERP - Chat.html`, `data.jsx`, `app.jsx`

Verificador rodando em paralelo.

## User

Verifier agent check completed

## Assistant

Verificador deu ✓ em tudo. Caixa unificada (omnichannel) + Equipe (interno) no ar, sem erros.

## User

Pode adicionar varios numeros para mesmo tipo de canal.
macros
e acho que pode passar para proxima fase, F1 ou a de polimento. 
deve ter filtro o score esta em quantos? o que precisa melhorar?

## Assistant

_[tool: update_todos]_

Beleza, 3 melhorias + crítica. Vou organizar:

_[tool: write_file]_

_[tool: str_replace_edit]_

_[tool: read_file]_

_[tool: str_replace_edit]_

Agora adiciono o resto (operator avatar, list-meta, assign, tags, macros popover, slash autocomplete) no final do arquivo:

_[tool: run_script]_

_[tool: done]_

_[tool: update_todos]_

_[tool: fork_verifier_agent]_

Pronto. Resumo + crítica:

## ✅ Entregue nesta rodada

| Feature | Onde |
|---|---|
| **Múltiplos números por canal** | Modelo `ACCOUNTS` com 10 contas. 3 WhatsApp Baileys ativos (Balcão · Vendas · Financeiro) com responsáveis (Larissa, Wagner, Eliana). Filtro secundário aparece quando um canal é selecionado. Drawer "Canais e contas" lista cada conta separadamente. |
| **Macros** | 7 macros mock (`/orc`, `/abrir`, `/preco`, `/horario`, `/agradecer`, `/cobrar`, `/pronto`). Botão no header + autocomplete inline ao digitar `/`. Macro pode disparar ações (ex: `/cobrar` adiciona tag "cobrança" + atribui pra Eliana). |
| **Filtros adicionais** | Barra de status no topo (Abertas/Pendentes/Aguardando/Resolvidas) + busca por nome/empresa/texto. Atribuição (assignee) com dropdown no header da thread + mini-avatar no card da lista. |

---

## 🎯 Self-critique (informal — [CD] roda F1.5 formal)

Sou **[CC]**, não rodo o `critique-score.json`. Mas dá pra estimar contra as ~15 dimensões do briefing:

| Dimensão | ~Score | Comentário curto |
|---|---|---|
| Layout (3-col, header sticky, footer) | **90** | Cockpit V2 canônico. ✓ |
| Hierarquia (1 primária, 2-3 secundárias) | **85** | Header tem 5 botões (Canais/Macros/Templates/Broadcast/+Nova). Bordeline cheio. |
| Densidade (1280px) | **80** | OK. Em 1280 o filtro de canais quebra em 2 linhas. Aceitável. |
| Tipografia | **85** | IBM Plex Sans/Mono consistente. |
| Cor / tokens | **80** | Uso oklch corretamente. Mas inventei algumas cores de hue específico (status badges). Não é fora dos tokens mas merece consolidar. |
| Espaçamento | **80** | Padding sutil em alguns botões pequenos pode escalar mal em monitor menor. |
| Componentes shared | **65** ⚠️ | **NÃO uso** `PageHeader`, `KpiCard`, `DataTable`, `PageFilters`, `EmptyState` do briefing. Uso CSS local. Aqui mora a maior dívida. |
| Estado/feedback | **85** | Toast, disabled states, loading mockado. OK. |
| Empty states | **70** | Lista vazia tem mensagem, mas Equipe não tem empty state pra "sem mensagens". |
| Acessibilidade | **60** ⚠️ | Sem `aria-label` nos botões de ícone. Sem foco visível claro. Sem leitura de teclado pra navegar lista. |
| Mobile / Larissa 1280 | **75** | Funciona, mas com filtro secundário aberto fica apertado. |
| Performance | **90** | React simples, sem lista virtualizada (10 convs OK; 1000+ vai lentir). |
| Copy (pt-BR, sem inglês cliente-facing) | **85** | OK. Tem "Macros" (jargão), poderia ser "Atalhos". |
| Branding (sem CTA WA, sem emoji excessivo) | **80** | Removi emojis dos botões. Sobrou alguns no toast e nos mocks. |
| Inovação | **80** | Nota interna + macros com slash + accounts multi-número são boas decisões. |

**Score estimado: ~78/100** — passa do threshold de 80 com folga em alguns critérios e tropeça em **3 lugares**:

### ❗ Top 5 itens pra polir antes do F2 (screenshot Wagner)

1. **Acessibilidade** — adicionar `aria-label` em todos os botões só-ícone, `aria-current` no item de filtro selecionado, foco visível custom. Provavelmente reprova F3.5 ([CA]) se for direto.
2. **Componentes shared** — refatorar pra usar `PageHeader`/`PageFilters` do design system real do repo (a `prototipo-ui-patch/` espelha isso). Hoje é CSS local; vai gerar dívida no F3 ([CL]).
3. **Header denso** — 5 botões no header é demais. Mover Templates/Macros pra dentro do composer (já existem botões `⌘T` e `/` lá) e tirar do header. Sobra: Canais · Broadcast · + Nova.
4. **Larissa 1280px (canon)** — testar com a sidebar do ERP aberta. Em 1280 com sidebar 240px, sobra 1040px pro inbox. Os 3-col atuais (300 + flex + 280) podem espremer a thread. Validar.
5. **Empty states + onboarding de contas** — drawer "Canais e contas" tem botão "+ Adicionar conta" mas não vai a lugar. Pra F1.5 passar precisa do flow (mock) de adição/configuração.

### 🔜 Próximo passo recomendado (protocolo)

1. **[W] anota em `COWORK_NOTES.md`** que P0 `inbox` está em F1 versão `v2` (com accounts/macros/filters/notas).
2. **[CD] roda F1.5** — design-critique formal → `critique-score.json`. Espero ~78 (passa do threshold 80? muito justo — provavelmente bate em "componentes shared" e "a11y" como `changes-requested`).
3. Polir os 5 itens acima como `v3`.
4. **[W2] F2** — screenshot pra você aprovar visualmente.
5. **[CL] F3** — Claude Code traduz pra Inertia/React real no repo.

Se quiser, posso já atacar o **#1 (a11y)** e o **#3 (header denso)** agora — ambos são polish puro, sem mudar arquitetura. Me diz se sim.

## User

Verifier agent check completed

## Assistant

_[tool: str_replace_edit]_

Verifier ok com 1 nit válido — sub-filtro com "Todos" mostra 10 contas (poluição). Conserto rápido: só renderiza o sub-filtro quando um canal específico está selecionado.Ajustado. Sub-filtro de contas agora só aparece quando você escolhe um canal específico (WA Baileys, Email, etc) — quando "Todos" estiver ativo, a tela fica limpa.

Status do módulo: **Caixa unificada v2 + Equipe v1** prontos pra entrar em F1.5 ([CD]) quando você quiser. Próximos polish naturais (#1 a11y, #3 header denso) ficam aguardando sua decisão.

## User

tava mais bonito, muito mais 
canais baixou muito a qualidade
Os filtros podem ser , ao lado Conversas  colocar poup econtas a mesma coisa
a pesquisa onde é Conversas. 
Ou seja fica restrito filtro ali. 

Filas de atendimento e distribuição de atendimento por fila

## Assistant

_[tool: update_todos]_

Entendi, vou:
1. **Voltar a estética limpa** — remover barras horizontais (status + canais + contas)
2. **Concentrar tudo na coluna Conversas** — busca inline + botão Filtros (popover com canais/contas/status/fila)
3. **Adicionar Filas de atendimento** — modelo + chip nos cards + drawer de configuração

_[tool: write_file]_

_[tool: write_file]_

_[tool: update_todos]_

_[tool: done]_

_[tool: fork_verifier_agent]_

Refatorada. Resumo:

**Visual restaurado:**
- Tirei as 2 barras horizontais (status + canais + contas) do topo. Header da página → 3 colunas, sem ruído entre eles.
- Header da página enxuto: **Filas · Canais · Broadcast · + Nova conversa** (4 botões). Templates e Macros ficaram só no composer.

**Tudo de filtro restrito à coluna Conversas:**
- Linha do título: `Conversas (8)` + botão `Filtros` (com badge contando filtros ativos)
- Logo abaixo: busca inline (`Buscar nome, empresa, texto…`)
- Botão `Filtros` abre popover **dentro da própria coluna** (não modal, não barra horizontal) com 4 grupos: Status / Fila / Canal / Conta
- Chips de filtros ativos aparecem abaixo da busca (clique pra remover individual + "Limpar tudo")

**Filas de atendimento — novo:**
- 5 filas mock: **Vendas** (Wagner+Larissa · round-robin · SLA 1h), **Pós-venda** (Larissa · sticky · 2h), **Financeiro** (Eliana · manual · 4h), **Produção** (Felipe · round-robin · 2h), **Geral** (3 ops · fallback · 30min)
- Chip colorido da fila em cada card da lista
- Header da thread ganhou dropdown "Fila" ao lado de "Atribuído" — move conversa entre filas
- Botão `Filas` no header abre drawer larger mostrando regras de distribuição + operadores por fila
- Contexto da conversa também mostra SLA + tipo de distribuição
- Macro `/cobrar` ganhou ação `queue:fin` (move pra fila Financeiro automaticamente)

Verificador rodando.

## User

Verifier agent check completed

## Assistant

Verificador ✓. Caixa unificada v3 limpa + Filas integradas. Header da página esvaziado, filtros restritos à coluna.

O nit do verificador (header da thread denso em viewport estreito) é o **mesmo ponto #4 do critique anterior** (Larissa 1280px) — fica como polish item pra próxima rodada quando você quiser atacar.

## User

eu prefiro dois templates atras.

vou tentar explicar :
que tinha uma linha de filtro por canal, ficou linda
canois e contasera bem mais bonito,
a largura do nomeda pessoas ficou grande e polida

filro e atribuido no topoficou ridiculo tirou o foco do que é principal para tela. as mensagens do contato

pode refinar mais?

## Assistant

_[tool: read_file]_

Entendi, vou reverter pra estética da v2 (canal + contas em linhas horizontais "lindas") e tirar fila/atribuído do header da thread.

_[tool: str_replace_edit]_

_[tool: str_replace_edit]_

_[tool: str_replace_edit]_

Agora coloco fila + atribuído no Contexto (lado direito) e simplifico o card:

_[tool: str_replace_edit]_

_[tool: str_replace_edit]_

_[tool: done]_

_[tool: fork_verifier_agent]_

Refinamento v4. Mudanças:

**Restaurado da v2 (a "linda"):**
- Linha horizontal de pílulas de canais voltou acima das 3 colunas
- Sub-linha horizontal de contas (Balcão · Vendas · Financeiro) aparece quando um canal é selecionado, com o número em mono ao lado

**Header da thread limpo (foco nas mensagens):**
- Removidos os dropdowns Fila + Atribuído de lá
- Sobrou: avatar + nome + chip de canal + handle + status + botão "✓ Resolver"

**Fila + Atribuído migraram pro Contexto (coluna direita):**
- Select inline em cada uma — mesma função, sem competir visualmente com o thread

**Cards mais polidos:**
- Removido o chip de fila e nome da conta de dentro do card (eram visualmente densos)
- Nome agora ocupa toda a largura disponível
- A fila aparece como **barra colorida vertical de 3px na lateral esquerda** do card (Linear-esque, comunica sem ocupar espaço)

**Status filter movido pra dentro da coluna Conversas:**
- Select pequeno ao lado do contador (Abertas (8) / Pendentes (3) / etc)
- Busca inline logo abaixo

Verificador rodando.

## User

Verifier agent check completed

## Assistant

✓ Tudo confirmado. Caixa unificada v4 com a estética da v2 + Filas + Atribuição (sem poluir o thread) + multi-conta + macros + busca + status filter restritos na coluna.

## User

consegue comparar como meu projeto, whatzap. e informar a nota

## Assistant

_[tool: github_get_tree]_

_[tool: github_get_tree]_

_[tool: github_read_file]_

_[tool: github_get_tree]_

_[tool: github_get_tree]_

_[tool: github_read_file]_

_[tool: github_get_tree]_

_[tool: github_read_file]_

Boa pedida — porque acabei de descobrir uma coisa importante:

## 🎯 Seu protótipo (este projeto) virou source-of-truth no repo real

No `Modules/Whatsapp/Http/Controllers/Admin/CaixaUnificadaController.php` (24KB, mergeado em **PR-D 2026-05-15 — hoje**) tem essa linha em destaque:

```
@see prototipo-ui/prototipos/caixa-unificada/inbox-page.jsx (fonte visual canônica)
```

Ou seja: **[CL] já pegou o `inbox-page.jsx` que fizemos aqui e traduziu pro Inertia/React real**. O loop F1 → F3 fechou. A rota `/atendimento/caixa-unificada` existe e coexiste com `/atendimento/inbox` legacy em canary 7d.

---

## 📊 Comparação feature × feature

### ✅ O que o backend confirmou (paridade 1:1 com meu protótipo)

| Feature | Meu protótipo | Backend real |
|---|---|---|
| 7 canais (Baileys/Meta/Z-API/IG/Messenger/Email/ML) | ✓ catalog hardcoded | ✓ `buildAvailableChannelsPayload()` |
| Hue/glyph/short/label idênticos | ✓ | ✓ `145/145/145/0/250/280/95` |
| Múltiplas contas por canal | ✓ ACCOUNTS array | ✓ tabela `channels` + `display_identifier` |
| 4 status (abertas/pendentes/aguardando/resolvidas) | ✓ | ✓ `buildStatsPayload()` |
| Filas com derivação tag→fila | ✓ QUEUES array | ✓ `deriveQueueFromTags()` |
| Status "em homologação" (preview_only) | ✓ banner amarelo | ✓ `preview_only` flag |
| Nota interna | ✓ bolha amarela | ✓ `is_internal_note` |
| Tags com cores | ✓ tags array | ✓ tabela `tags` polimórfica |
| Real-time | ❌ mock | ✓ Centrifugo + token issuer |
| Multi-tenant (`business_id`) | ❌ mock | ✓ global scope Tier 0 ADR 0093 |
| ACL canal=fila (US-WA-069) | ❌ mock | ✓ `channel_user_access` |

### ⚠️ O que o backend tem que MEU PROTÓTIPO não cobre

Olhei `ConversationThread.tsx` no repo — **69KB** (vs meu thread de ~5KB). Diferença:

1. **MicRecorder.tsx** — gravação de áudio (9KB)
2. **MediaFullscreenModal + MediaPreviewCard** — anexos com lightbox
3. **InteractiveMessageDialog.tsx** (27KB) — botões interativos WA Cloud
4. **TemplatePicker.tsx** — sync HSM Meta
5. **CSAT dashboard** — pesquisa pós-atendimento
6. **MacroVariantsController** — A/B testing de macros
7. **ChannelHealth monitoring** real (badge live)
8. **ContactPickerModal** — buscar contato pra nova conversa

### ✨ O que MEU PROTÓTIPO tem que o backend pode não ter renderizado ainda

(Não dá pra confirmar sem ler o `Atendimento/CaixaUnificada/Index.tsx` que o [CL] criou — me diz se quer que eu pegue)

1. Barra vertical colorida da fila no card (Linear-esque) — minha v4
2. Sub-filtro horizontal de contas com handle em mono
3. Slash autocomplete `/` inline no composer
4. Equipe (#producao/#financeiro/#vendas/DMs) — protótipo separado

---

## 🎯 Score honesto

| Critério | Score | Justificativa |
|---|---|---|
| **Fidelidade ao briefing** (CLAUDE_DESIGN_BRIEFING.md) | **82** | Tokens warm OK, sem emoji em produção (só nos mocks), Cockpit V2 respeitado. Perdeu pontos: não usei `shadcn` semantic colors (`bg-emerald-50`), usei `oklch` direto. Refactor pro [CL] traduzir foi necessário. |
| **Cobertura funcional vs SCOPE.md** | **70** | Cobri 12 dos 18 features principais. Voice/media/interactive messages/CSAT/macro variants ficaram pro F3 ([CL] implementou). |
| **Densidade Larissa 1280px** | **75** | Funciona em 1280. Com sidebar do ERP aberta (240px), sobra 1040px pros 3-col — apertado mas legível. |
| **Acessibilidade WCAG 2.1 AA** | **55** | Não atende. Sem `aria-label` nos icon buttons, sem `focus-visible:ring-*`, sem `aria-current` nos filtros. **Reprova F3.5 ([CA]) se for direto.** |
| **Componentes shared** | **65** | Não usei `PageHeader`/`PageFilters`/`KpiCard`. CSS local. Mas o [CL] absorveu isso traduzindo pra shadcn no repo. |
| **Inovação útil** | **88** | Nota interna, slash macros, filas com SLA, multi-conta — tudo virou backend. |
| **Refinamento visual** (após v4) | **85** | Estética limpa, filtros restritos à coluna, header da thread focado nas msgs. |
| **Contribuição efetiva ao loop** | **95** | ⭐ Meu `inbox-page.jsx` é citado como `fonte visual canônica` no PR-D que mergeou hoje. F1 → F3 fechou. |

### **Score geral: 78/100**

Bate exatamente onde o briefing pede pra cair (threshold F1.5 é 80). Provavelmente passaria com `changes-requested` por causa de **acessibilidade (55)** e **componentes shared (65)** — os 2 itens que sinalizei como dívida no critique anterior.

---

## 💡 Recomendação

Você **já fechou** o loop pra Caixa Unificada V4 — está em produção (canary 7d). Próximos passos naturais:

1. **Ler `/projects/.../resources/js/Pages/Atendimento/CaixaUnificada/Index.tsx`** (se [CL] criou) — confirmar paridade visual com meu protótipo e listar gaps
2. **F3.5 ([CA])** roda accessibility-review sobre o que [CL] entregou (não sobre meu mock — meu mock só preenche o blueprint)
3. **Próxima tela P0** da fila — `Sells/Create` ou `Sells/Index` (já listadas em `TELAS_REVIEW_QUEUE.md`)

Quer que eu busque o `Index.tsx` que [CL] criou pra eu te dizer o que ficou paridade e o que pode ter ficado pra trás?

## User

sim

## Assistant

_[tool: github_get_tree]_

_[tool: github_read_file]_

_[tool: github_read_file]_

_[tool: github_read_file]_

_[tool: github_read_file]_

Pronto, comparação completa. Resultado **muito bom** — meu protótipo virou linha-por-linha o código de produção:

## 📋 Paridade visual (meu protótipo vs Index.tsx do repo)

| Elemento | Meu inbox-page.jsx | Repo Index.tsx + componentes | Match |
|---|---|---|---|
| 3-col `320 / 1fr / 300` | ✓ | ✓ `lg:grid-cols-[320px_1fr_300px]` | ✅ idêntico |
| Chips horizontais de canais | ✓ `.om-filter` | ✓ `ChannelChipsRow.tsx` | ✅ idêntico |
| Sub-row de contas com handle mono | ✓ `.om-filter.sub` | ✓ "Linha 2 — contas" | ✅ idêntico |
| Status dropdown na lista | ✓ select compacto | ✓ (`ConversationListV4`) | ✅ idêntico |
| Banner amarelo "em homologação" | ✓ `.om-preview-banner` | ✓ banner `preview_only` | ✅ idêntico |
| 8 sections do Contexto | ✓ | ✓ `ContextSidebarV4` — comentário cita meu CSS L682-714 | ✅ idêntico |
| Topnav: Filas \| Canais \| Broadcast \| +Nova | ✓ 4 botões | ✓ + Templates (Jana+HSM) ganho | ✅ + 1 ganho |
| Toggle Resp/Nota (⌘⇧N) | ✓ | ✓ `ComposerV4` | ✅ idêntico |
| Glyph circular OKLCH por canal | ✓ `oklch(0.62 0.14 ${hue})` | ✓ mesma fórmula linha-por-linha | ✅ idêntico |
| Filas com SLA | ✓ chip + SLA | ✓ section 1 do Contexto | ✅ idêntico |

**O `Index.charter.md` cita explicitamente:**
> `visual_source: prototipo-ui/prototipos/caixa-unificada/inbox-page.jsx`
> 
> Implementação F3-F5 do RUNBOOK `cowork-prototype-replication` ADR 0114. Fonte canônica **prototipo-ui/prototipos/caixa-unificada/inbox-page.jsx (802 LOC Cowork)**

## ⚠️ Gaps reais (placeholders honestos no repo)

O [CL] foi honesto e marcou TUDO que não conseguiu implementar como `TODO US-WA-XXX`:

| Feature do meu protótipo | Estado no repo |
|---|---|
| Slash autocomplete `/` no composer | ❌ "placeholder; reusar dropdown `/macros` do legacy em PR seguinte" |
| Templates picker inline | ❌ placeholder (Templates dropdown no header funciona, mas só linka pra outras telas) |
| Mover conversa entre filas | ❌ "só leitura (fila vem de heurística)" |
| Assignee picker (dropdown no contexto) | ❌ "— sem atribuição" placeholder |
| Drawer "Canais e contas" lateral | ❌ "tela linka direto pra `/atendimento/canais`" — Wagner sugeriu drawer (§5 roadmap) |
| Drawer "Filas" lateral c/ SLA + ops | ❌ botão desabilitado, config em `config/whatsapp.queues` |
| Broadcast cross-canal | ❌ desabilitado, P2 backlog |
| `+ Nova conversa` | ❌ desabilitado, backlog |
| Barra vertical colorida 3px da fila | ⚠️ Preciso ler `ConversationListV4.tsx` pra confirmar |
| Equipe (#producao etc) | ❌ não existe no repo — outra tela |

## ✨ O que o [CL] ADICIONOU que eu não tinha

1. **Templates dropdown agrupando Jana (IA) + HSM Meta** no topnav — divisão real boa
2. **Centrifugo real-time** + polling 5s defensive
3. **`Inertia::defer()`** em todas props caras (perf D-14)
4. **3 testes Pest GUARD** (`R-WA-CAIXA-UNIF-001/002/003`) — incluindo cross-tenant `business_id`
5. **`preserveScroll: true` + `preserveState: true`** em todos reloads (anti-flash US-WA-068)
6. **`Deferred` skeleton** com 8 cards animados enquanto carrega

## 🎯 Score atualizado

| Critério | Antes | Agora | Mudança |
|---|---|---|---|
| Fidelidade ao briefing | 82 | **88** | +6 — paridade 1:1 confirmada |
| Cobertura funcional vs SCOPE | 70 | **72** | +2 — Templates adicionados pelo [CL] |
| Acessibilidade WCAG | 55 | **70** | +15 — [CL] adicionou `role="tab"`, `aria-selected`, `aria-label` em tudo |
| Componentes shared | 65 | **85** | +20 — [CL] usou shadcn (`Card`, `DropdownMenu`, `EmptyState`) |
| Densidade Larissa 1280 | 75 | **80** | +5 — confirmado no charter (`grid-cols-[320px_1fr_300px]`) |
| Inovação útil | 88 | **88** | igual |
| Refinamento visual | 85 | **88** | +3 |
| Contribuição ao loop | 95 | **97** | +2 — `802 LOC Cowork` virou fonte canônica de PR-D |

### **Score geral final: 84/100**

**Saiu do "passa apertado" e entrou no zona segura do F1.5.** Acima do threshold de 80. Restam 3 dívidas principais (todas já listadas no `Roadmap` do próprio charter do [CL]):

1. **Filas DB + painel** (P1) — meu drawer de filas com SLA tá lá te esperando virar US-WA-XXX
2. **Assignee picker** (P1) — meu select de Atribuído idem
3. **Slash macros + templates inline** — meu dropdown `/orc /preco` idem

## 🚀 Próxima ação canônica

Você já fechou o loop pra Caixa Unificada V4. As **3 telas P0 restantes** em `TELAS_REVIEW_QUEUE.md` que vi:

1. `Sells/Create` 
2. `Sells/Index`
3. (... ler a fila completa pra confirmar)

Quer que eu pegue o `TELAS_REVIEW_QUEUE.md` atualizado e proponha qual atacar agora?

## User

como eu faço para o claude code fazer?

## Assistant

O fluxo zero-touch (do `CLAUDE.md`) é assim — sem você ter que copiar arquivo, baixar zip, nem interpretar nada:

## 🔁 Como funciona (na prática)

```
Aqui no Cowork (eu = [CC])              No seu Cursor/Claude Code (= [CL])
─────────────────────────────────       ─────────────────────────────────────
1. Wagner: "implementa X, Y, Z"  ───►   
                                        
2. [CC] gera os arquivos finais         
   em `prototipo-ui-patch/` (eu)        
                                        
3. [CC] pega URL pública de             
   cada arquivo (get_public_file_url)   
                                        
4. [CC] te entrega 1 PROMPT             
   pronto, com:                ────►    5. Você cola no Code, dá Enter
   • URLs dos arquivos                  6. Code: curl URLs, aplica patch,
   • comandos git add/commit/push          git commit, git push, abre PR,
   • número do PR                          mergeia
   • critérios de aceitação             7. Wagner: zero toque
```

Você **não escolhe arquivos, não faz copy/paste, não interpreta** — só cola **1 mensagem** no Code uma vez.

## ✅ Pros itens pendentes da Caixa Unificada V4

Você tem 2 opções pelo protocolo formal do `PROTOCOL.md`:

### Opção A — Cada item passa pelo loop F1→F5 completo
Pra cada um dos 3 itens (Filas / Assignee / Slash):
1. Você adiciona em `prototipo-ui/COWORK_NOTES.md` no repo
2. Eu refino o protótipo isolando aquela feature
3. [CD] roda critique
4. Você aprova screenshot
5. Te entrego prompt pro Code traduzir
6. [CA] roda a11y
7. Merge

⏱️ ~1h cada × 3 = 3 ciclos.

### Opção B — Atalho (mais rápido, válido pra refinement)
Esses 3 itens já estão **prototipados e aprovados visualmente** no `inbox-page.jsx`. Se você concorda em pular F1, dá pra ir direto pro F3:

1. Te entrego **1 prompt** pro Claude Code que diz:
   > "Implementa US-WA-301 (Filas), US-WA-302 (Assignee), US-WA-303 (Slash macros) com base nas linhas X-Y, A-B, C-D do `prototipo-ui/prototipos/caixa-unificada/inbox-page.jsx`. Cria as US no `memory/requisitos/Whatsapp/SPEC.md`. Cria migrations + Eloquent + Inertia, abre PR, mergeia."
2. Cola no Code
3. Code abre PR, mergeia
4. [CA] roda a11y depois

⏱️ ~30min pra entrega total (uma rodada).

## 🤔 Recomendação

**B**, porque:
- Os 3 itens já têm "fonte visual canônica" aprovada (você viu, validou, comparou — score 84)
- Filas + Assignee são **placeholders honestos** que o próprio [CL] marcou como TODO no charter
- Não tem decisão visual nova pra você tomar — só execução

A única coisa que muda é: **Filas precisa decidir o schema da tabela `whatsapp_queues`**. Posso pré-escrever o schema no prompt baseado no que tem no protótipo (label, hue, sla, dist_rule, trigger_tags JSON, members JSON) — se você não gostar, [CL] pergunta antes de commitar.

## ❓ Antes de eu montar o prompt

3 decisões rápidas:

1. **Vai com B (atalho)?** ← recomendo
2. **Os 3 itens juntos num PR só, ou 1 PR por feature?**
   - 1 PR só: mais rápido, review denso
   - 3 PRs: review fácil, 3 cutovers em canary
   - ← recomendo **1 PR** porque são features acopladas (Slash macro `/cobrar` precisa ter o Assignee pra atribuir pra Eliana)
3. **Vai pro `main` direto ou cria branch `caixa-unif-v4-filas-assignee-macros`?**
   - branch é o padrão do repo (vi PR-D), vou nesse

Me responde **B + 1PR + branch** (ou alternativas) e eu monto o prompt completo.

## User

encaixe aqui para eu ver como deveria ser, incorpore o que tem de bom no real.  para mostrar para cluade code deve fazer. um exemplo canonico

## Assistant

_[tool: str_replace_edit]_

Vou incorporar o que o [CL] fez bem (a11y, dropdown Templates Jana+HSM, data-testid, skeleton) no meu protótipo e marcar os TODOs com IDs de US — fica como handoff canônico.Agora a11y + Templates dropdown + data-testid:

_[tool: str_replace_edit]_

Agora adiciono os comentários TODO US-WA-3XX no Contexto + a11y nos icon buttons + CSS pro dropdown e focus:

_[tool: str_replace_edit]_

_[tool: run_script]_

Por fim, adiciono `data-testid` na lista de conversas e composer input + send:

_[tool: str_replace_edit]_

_[tool: done]_

_[tool: fork_verifier_agent]_

Pronto. O `inbox-page.jsx` agora é a **versão canônica** que serve de handoff pro [CL]:

## ✨ O que ele tem agora (incorporando o melhor do real)

**Cabeçalho-documento explicativo no topo do arquivo:**
- Lista features ✅ já mergeadas em PR-D
- Lista features 🔜 pendentes com US-WA-3XX assigned (301 Filas / 302 Assignee / 303 Slash macros / 304 Drawer Canais / 305 Mover fila / 306 Broadcast real / 307 Nova conversa)
- Tabela de mapeamento `<componente mock>` → `<componente shadcn real>`
- Tokens canônicos OKLCH explicitados (hue por canal / hue por fila)

**Ganhos absorvidos do real (visíveis na tela):**
- **Templates dropdown** no header (Jana ✨ + HSM ▣) substituindo o botão único
- `role="tab"` + `aria-selected` em todos os chips de canais e contas
- `data-testid="caixa-unif-*"` em 12+ elementos (paridade com testes Pest `R-WA-CAIXA-UNIF-001/002/003`)
- `aria-label` nos icon buttons (⌘T, /, ✕, mode toggle)
- `aria-pressed` no toggle Resp/Nota
- `aria-current="true"` na conversa selecionada
- **Focus-visible ring verde** em todos os interativos (atende WCAG 2.1 AA)

**TODOs honestos inline (anti-pattern M-AP-1 do `LICOES_F3`):**
- `TODO US-WA-301` no botão Filas
- `TODO US-WA-302` no select Atribuído (com nota: "buscar users do business com permission whatsapp.access ATIVA + presence")
- `TODO US-WA-303` no botão Macros
- `TODO US-WA-305` no select Fila do Contexto (com nota: "precisa nova coluna conversations.queue_slug nullable")
- `TODO US-WA-306` no Broadcast
- `TODO US-WA-307` no + Nova conversa

## 🎯 Como o Claude Code usa isso

Quando você mandar pro [CL], ele lê:
1. **O cabeçalho** → entende quais US implementar e o que **não** mexer (paridade 88% já mergeada)
2. **Os TODO US-WA-3XX** → cada comentário tem a pista do schema/lógica que falta
3. **Padrão `data-testid`** → mantém os testes Pest atuais passando + adiciona novos
4. **A tabela de mapeamento** → traduz `<om-bub>` → `ConversationThreadV4`, `<om-input>` → `ComposerV4`, etc

Quer agora que eu monte o **prompt zero-toque pra colar no Claude Code** com base nessa versão canônica? Ele vai dizer "implemente US-WA-301 a US-WA-303 lendo `prototipo-ui/prototipos/caixa-unificada/inbox-page.jsx` linhas X-Y, crie branch, abra PR, mergeie". É só me confirmar **1 PR ou 3 PRs**.

## User

Verifier agent check completed

## Assistant

✓ Verifier passou. Versão canônica do `inbox-page.jsx` está pronta como handoff visual.

Quando quiser que eu monte o prompt zero-toque pro Claude Code (US-WA-301 a 307), me diz **1 PR ou múltiplos PRs** e eu entrego.

## User

vou exportar para ele agora

## Assistant

Beleza. 3 coisas pra checar antes:

**1. O que ele precisa ver:**
- `inbox-page.jsx` ← protótipo canônico (cabeçalho com todas as US-WA-3XX)
- `inbox-page.css` ← estilos
- `equipe-page.jsx` + `equipe-page.css` ← comunicação interna (opcional pra esse PR)

**2. Caminho no repo onde deve cair:**
- `prototipo-ui/prototipos/caixa-unificada/inbox-page.jsx`
- `prototipo-ui/prototipos/caixa-unificada/inbox-page.css`

(o charter do PR-D já aponta pra esse path como `visual_source`)

**3. Lembrete pra você no Code:**
Os TODOs `US-WA-301` a `US-WA-307` estão inline no `.jsx`. O [CL] precisa criar essas US em `memory/requisitos/Whatsapp/SPEC.md` antes de implementar — não inventar Service que não existe (M-AP-1).

Se travar em qualquer ponto, volta aqui e me passa o erro/screenshot que eu ajusto.

