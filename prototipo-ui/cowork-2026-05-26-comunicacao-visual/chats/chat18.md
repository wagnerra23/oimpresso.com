# FSM de Vendas

_Started 2026-05-25 15:58 UTC_

---

## User

Vendas, edição conforme o git FSM. oque pode sugerir a edição precisa melhorar. qual a nota do metodo 9.75

## Assistant

_[tool: list_files]_

_[tool: read_file]_

_[tool: read_file]_

_[tool: read_file]_

_[tool: read_file]_

# Vendas — Score Método KB-9.75

Auditando `vendas-page.jsx` contra as **22 features-tipo × 5 categorias**:

## 📊 Score por categoria

| Cat. | Score | Status |
|---|---|---|
| **Navegação** | **9,0** | tri-pane ish + ⌘K rico + J/K + tree + cheat-sheet · falta só N5 (responsive 3-tabs <1100px) |
| **Curadoria** | **5,5** | só C3 (SLA pill) está A+. C1/C4 parciais. **C2 histórico edições** e **C5 comentários inline** zerados |
| **Inteligência** | **5,5** | I4 (empty-state→IA) ✓ · I1/I2 parciais (palette tem IA mas drawer não resume conversa nem faz RAG do cliente) · **I3 auto-sugest** zerado |
| **Guia** | **2,5** | só G4 cross-link parcial (#ID no palette). G1/G2/G3 zerados — **maior gap** |
| **Saída** | **4,0** | S1 favoritos A+. S3 imprimir parcial (sem brand fullbleed). **S2 modo apresentação** e **S4 lightbox de mídia** zerados |

## 🎯 Nota global pelo método

**~6,8 / 9,75** — vs 8,7 da auditoria visual antiga. O método KB-9.75 é mais duro porque exige IA inline + Guia (troubleshooter/trilhas/cross-link) + Saída completa, não só visual + densidade.

> A auditoria 8,7 só olha Estrutura/Visual/Domínio/Interação. O KB-9.75 olha **paridade arquitetural** com o módulo de referência.

---

## 🔧 4 refinos pra subir 6,8 → 9,5

### Refino #1 · Tapar Curadoria (6,8 → 7,8)
- **C2 Histórico de edições da venda** — drawer "Ver histórico" mostrando alteração de item/preço/status com autor + timestamp (crítico fiscal/auditoria SEFAZ)
- **C5 Comentários inline por item** — hover no item da venda → "+ comentar pra equipe" (ex: "cliente pediu desconto extra, ver com Wagner")
- **C4 Pílula "Atendimento ok"** — quando fechar venda, marca explícita no thread

### Refino #2 · IA dentro do drawer (7,8 → 8,7)
- **I1 Resumir venda** — botão no header do drawer: "Resumir histórico deste cliente" (últimas 5 vendas + ticket médio + comportamento de pagamento)
- **I2 Perguntar com RAG do cliente** — "Já compramos peça X pra ele antes?" / "Qual prazo médio dele?"
- **I3 Auto-sugest fiscal** — IA olha os itens e sugere NF-e vs NFS-e vs ambos + CFOP esperado (operador edita antes de emitir)

### Refino #3 · Guia (8,7 → 9,2)
- **G1 Troubleshooter "Qual nota emitir?"** — árvore: tem produto? tem serviço? cliente é PJ? CFOP X
- **G3 Trilha onboarding vendedor novo** — "Como bater meta no balcão" (reuso direto do KBTroubleEditor)
- **G4 Cross-link forte** — `#a9` em notas vira link pro artigo KB de fiscal; `#os1240` linka pra OS

### Refino #4 · Saída (9,2 → 9,5)
- **S2 Modo apresentação read-only** — pra Wagner mostrar pra contador sem expor IDs internos
- **S3 Recibo/DANFE com brand Oimpresso fullbleed** (reuso KBPrintSOP adaptado)
- **S4 Lightbox de mídia** — comprovante PIX, foto da peça, assinatura digital com download direto

---

## ⚠️ Edição mais urgente (1 só)

Se for **1 mudança** com maior ganho/esforço, é o **C2 — histórico de edições**. Hoje você não consegue auditar "quem mudou o preço da venda #1240 e quando". É o que mais machuca em compliance fiscal e o que mais sobe a nota (+0,2 sozinho, +0,4 quando casado com C5).

Quer que eu prepare o patch (`vendas-page.jsx` + `vendas.css`) pra esse refino #1 completo? Posso aplicar como entrega F1 e gerar o COMPARISON.md das 15 dimensões.

## User

ok como pode fazer

## Assistant

_[tool: read_file]_

_[tool: grep]_

_[tool: read_file]_

_[tool: grep]_

_[tool: grep]_

_[tool: grep]_

_[tool: grep]_

_[tool: grep]_

# Correção honesta do score — Vendas já está em ~9,0

Eu errei feio na primeira auditoria. Não tinha lido `vendas-curation.jsx`, `vendas-ai.jsx`, `vendas-output.jsx`, `vendas-extras.jsx`. Reauditando com tudo na mesa:

| Cat. | Score real | O que tem |
|---|---|---|
| **Navegação** | **9,0** | tri-pane · ⌘K rico · J/K · tree views · cheat-sheet · falta só **N5 responsive ≤1100px** |
| **Curadoria** | **9,5** | C1 edição inline qty/preço · **C2 audit trail completo** (timeline) · C3 SLA pill · C4 Confirmar/Faturar · **C5 comentários inline** com contador |
| **Inteligência** | **9,5** | **tab ✦ IA dedicada** · Resumir · RAG histórico cliente · sugerir próxima venda · empty-state palette |
| **Guia** | **7,0** | G1 Troubleshooter (3 fluxos: rejeição NFe · cliente sem CNPJ · desistência) · G4 Cross-link (#V-/#OS-/#CLI-) · **G2 e G3 zerados** |
| **Saída** | **9,5** | S1 favoritos · S2 modo apresentação · S3 Transcript PDF + Recibo A4 com brand · S4 VdItemArt (drag-drop de arte) |

## 📐 Nota real do Método KB-9.75: **~9,0 / 9,75**

Faltam **3 itens cirúrgicos** pra cravar 9,5+ — é isso que dá pra fazer agora:

---

## Plano executável (1 sessão de trabalho)

### Patch 1 · **N5 Responsive 3-tabs ≤1100px** (+0,2)
- Em `vendas.css`: media query 1100px → tabela vira 3 abas (Lista · Detalhe · Contexto)
- Persona Felipe (técnico oficina mecânica em tablet) ganha
- Reuso direto do padrão Inbox

### Patch 2 · **G3 Trilhas onboarding vendedor** (+0,2)
- Novo `vendas-trails.jsx` com 3 trilhas:
  - "Primeiro dia no balcão" (5 passos)
  - "Como bater meta mensal" (4 passos)
  - "Tratar reclamação sem perder cliente" (6 passos)
- Botão flutuante "🎓 Aprender" no header (só aparece pra usuário com <10 vendas registradas)
- Storage: `oimpresso.vendas.trails.<userId>.<trailId>.step`

### Patch 3 · **G2 Editor visual de troubleshooter** (+0,15)
- Reuso direto do `window.KBTroubleEditor` (já existe no KB)
- Botão "+ Novo fluxo" dentro do VdTroubleshooterDialog
- Wagner cria fluxo de objeção novo sem código

### Patch 4 · **Polimento cross-link** (+0,05)
- Hoje `VdLinkify` só faz console.log no onPick — wirear pra abrir drawer da venda alvo ou navegar pro módulo
- 1 hora de trabalho

**Total projetado:** 9,0 → **9,6** (cruza A+)

---

## Como entrego (modo zero-touch validado)

1. Aplico os 4 patches em `vendas.css` + `vendas-page.jsx` + crio `vendas-trails.jsx` + registro script no `Oimpresso ERP - Chat.html`
2. Espelho 1:1 em `prototipo-ui-patch/` (mesma estrutura do `prototipo-ui/` do repo)
3. Gero URLs públicas via tool dedicada
4. Entrego **UM prompt completo** pro Claude Code com URLs + comandos `git add/commit/push` + PR alvo
5. Você cola UMA VEZ no Code, ele resolve

**Quer que eu execute essa sequência agora?** Se sim, preciso só confirmar:
- (a) **um patch por vez** (mais seguro, 4 PRs separados) ou **bundle único** (1 PR só, mais rápido)?
- (b) algum dos 4 que você quer **pular**?

Sem resposta, executo bundle único com os 4 — é o caminho mais rápido pra 9,6.

## User

Incluir a integração com o módulo de Oficina Auto, acho que integrar a venda com esse módulo seria ótimo, como seria? as duas são vendas? caem no faturamento. tiram nota. Seria abas ?

## Assistant

# Vendas × Oficina Auto — sim, mas NÃO como abas

Pergunta certa. Resposta honesta primeiro, depois o desenho.

## ❓ As duas são vendas?

**Contabilmente: sim.** Ambas geram receita, ambas saem do estoque (peça) ou do tempo (mão-de-obra), ambas precisam tirar nota:

| Tipo | NF-e (55) | NFS-e (municipal) | DRE | Caixa | Comissão |
|---|---|---|---|---|---|
| Venda balcão (banner) | ✅ produto | ✅ instalação | linha receita | entra hoje | vendedor |
| OS Oficina (revisão) | ✅ peça | ✅ mão-de-obra | linha receita | entra na entrega | mecânico |

**Operacionalmente: NÃO.** Larissa não trabalha no box mecânico, Felipe não atende balcão. Forçar UI única quebra os dois fluxos.

## ❌ Por que abas dentro de Vendas é armadilha

- Larissa abre `/vendas` e vê 30 OS de mecânica que não são dela → ruído
- Felipe abre `/vendas` esperando OS dele e cai num drawer estranho de "venda CV"
- FSM diferente: venda tem 5 passos (Orç→Ped→Fat→Ent→Paga), OS tem 5 outros (Diag→Peça→Serv→Pronto→Ent)
- Persona diferente, atalhos diferentes, KPIs diferentes

## ✅ Caminho real — 5 pontos de costura, módulos separados

### 1. **OS Entregue vira automaticamente Venda Faturada**
Quando uma OS chega em "Pronto p/ retirar" + cliente confirma:
- Cria objeto venda com `origin: "oficina"` + `osRef: "OS-1240"`
- Herda itens (peças = produto NF-e · mão-de-obra = serviço NFS-e)
- FSM da venda já começa em **Faturada** (não Orçamento)
- Mecânico vira `mechanicId` no avatar, vendedor balcão é null
- Comissão divide: mecânico % do serviço · concessionária % da peça

### 2. **Coluna "Origem" na lista de Vendas**
Hoje a tabela tem coluna `Atendido por`. Vira:
```
Atendido por · Origem
[Larissa]    · Balcão
[Felipe — mec] · Oficina · #OS-1240 ↗
[Bruna]      · Balcão
```
Click no `#OS-1240` → abre drawer da OS no módulo Oficina (não fica trocando).

### 3. **Saved view "Por origem"**
No dropdown Visões ▾ que já existe, adicionar nodes:
```
▸ Por origem
   · Balcão (124)
   · Oficina (38)
   · Online (12)
```

### 4. **KPIs hero somam tudo (modo Faturamento)**
A vista `Foco = Faturamento` já existe. Hoje só conta vendas próprias.
Refator:
- "Faturado hoje" = ∑ vendas balcão + ∑ OS faturadas hoje
- Sparkline 30d puxa cross-módulo
- "Notas fiscais" hero conta NF-e/NFS-e de AMBOS

Persona Wagner (escritório) precisa disso pra fechar o dia.

### 5. **Caixa do dia (já é sub-rota de Vendas) consolida**
A sub-rota `/vendas/caixa` (já existe) puxa TUDO que entrou hoje:
- Balcão PIX/dinheiro/cartão
- Oficina (peças vendidas + serviços faturados)
- Devoluções (negativo)

É AQUI que a integração faz mais sentido — caixa é por natureza unificado.

---

## 🎨 Como aparece pro usuário

**Larissa (balcão):**
- Abre `/vendas` — vê só balcão por padrão (saved view "Balcão" pré-selecionada)
- Pode mudar pra "Todas origens" se quiser

**Felipe (mec):**
- Abre `/oficina` — trabalha lá normalmente
- Quando entrega OS, nada muda no fluxo dele — a venda nasce automática no caixa

**Wagner (escritório):**
- Abre `/vendas` com vista "Todas origens" + `Foco = Faturamento`
- Vê hero KPI somado · pode filtrar por origem na sidebar

**Eliana (financeiro):**
- Vai direto em `/vendas/caixa` (que já consolida)
- Filtro `Fiscal · Rejeitadas` cruza balcão + oficina

---

## 🔌 O que precisa mudar (técnico)

| Onde | O quê |
|---|---|
| `data-vendas.jsx` | aceitar `origin: "balcao" \| "oficina" \| "online"` + `osRef: string` |
| `data-os.jsx` | quando OS.status === "entregue" → emit evento `osEntregue(os)` |
| `app.jsx` | listener cria objeto venda derivado da OS |
| `vendas-page.jsx` | coluna Origem + sub-tree "Por origem" no saved views · KPI cross-source quando `vista=faturamento` |
| `oficina-page.jsx` | botão "Ver venda gerada ↗" no drawer da OS entregue |
| `vendas-extras.jsx` | sub-rota `/vendas/caixa` puxa cross-módulo |
| `fsm-stepper.jsx` | já está pronto (`venda_cv` + `venda_mec` coexistem) ✓ |

---

## 📐 Casa com o método KB-9.75

Isso é exatamente o pré-requisito **A1 — Integração cruzada** do método (KB-9.75 §1.4). Hoje listado como:
> ❌ **CRM → Inbox + KB:** falta
> Aqui adiciona: ❌ → ✅ **Oficina → Vendas (auto-faturar na entrega)**

Sobe nota: **Domínio +0,5** · **Saída +0,2** (KPI unificado conta como S1 cross-source)

---

## Próximo passo

Quer que eu **mocke isso visualmente** primeiro (antes de tocar nos arquivos)? Tenho 2 caminhos:

**(a) Storyboard rápido** — 1 HTML com 3 frames:
1. Lista de Vendas com coluna Origem nova + saved tree
2. Drawer de OS Entregue mostrando "→ gerou venda #V-1840"
3. Caixa do dia consolidado

**(b) Patch direto** — aplico nos arquivos reais e você vê funcionando no `Oimpresso ERP - Chat.html`

**(c) Os dois** — storyboard pra alinhar o conceito, depois patch.

Recomendo **(c)** porque a integração tem 5 pontos e quero ter certeza que você está de acordo com o modelo "OS entregue = venda nasce automática" antes de eu mexer em data-os/data-vendas/app — esse refator atravessa 4 arquivos. Storyboard custa 20min, patch custa 2h.

## User

ok pode fazer

## Assistant

_[tool: update_todos]_

Vou montar o storyboard primeiro. 3 frames empilhados num HTML único pra você revisar antes de eu tocar nos arquivos reais.

_[tool: write_file]_

_[tool: update_todos]_

_[tool: done]_

Storyboard pronto. Sem erros no console.

## 📋 O que tem dentro

- **Frame 1** — Vendas/Index com nova coluna **Origem** (Balcão · Oficina · Online) + saved tree "Por origem" no dropdown ▾ + hero KPI consolidado com breakdown
- **Frame 2** — Drawer da OS Entregue no módulo Oficina mostrando o card destacado **"Esta OS gerou a venda #V-1840"** com itens herdados, fiscal e atalho cross-módulo
- **Frame 3** — `/vendas/caixa` consolidado: KPIs por origem · timeline cronológica única · filtro inline · refs `↗ #OS-NNNN`
- **Diagrama de arquitetura** — fluxo `OS Entregue → evento osEntregue → cria Venda` com 4 regras (comissão · caixa · cross-link · desfazer)
- **4 decisões pendentes** que preciso confirmar antes do patch (auto vs click manual, split de comissão, OS sem nota, default Felipe)

## ⏭️ Próximo passo — depende de você

Quando você abrir e revisar, me responda **3 coisas**:

1. **Modelo geral está certo?** (módulos separados + 5 pontos de costura · OS entregue → venda automática)
2. **As 4 decisões pendentes** no quadro azul ao final — pelo menos as 2 primeiras (auto/manual + split comissão)
3. **Quer ajustar algo visual nos frames** antes de eu codar? (ex: coluna Origem em outra posição · KPI hero diferente · cores)

Com isso eu disparo o patch real (6 arquivos) e o prompt zero-touch pro Code.

_[tool: fork_verifier_agent]_

Storyboard entregue. Aguardo sua revisão + as 3 respostas pra disparar o patch.

## User

concordo, ficou bom eu acho que auto, split

## Assistant

_[tool: read_file]_

_[tool: grep]_

_[tool: grep]_

Boa. Vou com os defaults nas decisões 3 e 4:
- **(3) OS sem nota** vira venda mesmo assim (`fiscal:{}`, sem badge NF-e) — não bloqueia fluxo informal
- **(4) Felipe** vê tudo, com filtro `Por origem · Oficina` pré-aplicado por padrão (sem ACL hard)

Executando o patch agora. Vou ler primeiro os arquivos que vou tocar.

_[tool: str_replace_edit]_

Boa — `origin` antigo já existe (significa "canal: balcão/orçamento/frota"). Pra não quebrar, vou adicionar campo novo `source` (balcao/oficina/online) + `osRef`. Vou em 2 fases pra controlar contexto:

**Fase A (agora):** Frame 1 — data + Vendas list com coluna Origem + saved tree + KPI breakdown  
**Fase B (próxima sync):** Frames 2 e 3 — drawer Oficina + Caixa consolidado

Começando Fase A.

_[tool: read_file]_

_[tool: read_file]_

_[tool: str_replace_edit]_

_[tool: str_replace_edit]_

_[tool: grep]_

_[tool: str_replace_edit]_

_[tool: str_replace_edit]_

_[tool: str_replace_edit]_

_[tool: grep]_

_[tool: str_replace_edit]_

_[tool: grep]_

_[tool: read_file]_

_[tool: str_replace_edit]_

_[tool: done]_

Patches da Fase A aplicados. Verificando que carrega sem erros.

_[tool: save_screenshot]_

_[tool: view_image]_

_[tool: save_screenshot]_

_[tool: view_image]_

_[tool: save_screenshot]_

_[tool: view_image]_

_[tool: save_screenshot]_

_[tool: show_html]_

_[tool: fork_verifier_agent]_

✅ **Fase A entregue** — Frame 1 do storyboard está vivo no `Oimpresso ERP - Chat.html`:

- Hero KPI mostra **"todas origens"** + breakdown `● Balcão R$ [redacted Tier 0]k · ● Oficina R$ [redacted Tier 0]k` quando você está em `Foco = Faturamento` (já visível no screenshot acima)
- Coluna **Origem** entre "Atendido por" e "Pipeline" — pill Balcão (verde) / Oficina (azul, com link `↗ #OS-NNNN`) / Online (laranja)
- Saved tree **"Por origem ▾"** expansível no dropdown ▾ com filhos Balcão/Oficina/Online + contadores
- Linhas de Oficina ganham stripe azul sutil na borda esquerda

**Arquivos tocados:** `data-vendas.jsx` (campo `source` + `osRef` derivados) · `vendas-page.jsx` (VdSource + coluna + tree + KPI) · `vendas.css` (tokens + estilos)

## ⏭️ Próximo

**Fase B** (próxima sync) — frames 2 e 3:
- Card **"Esta OS gerou venda #V-NNNN"** no drawer do módulo Oficina  
- `/vendas/caixa` consolidado com filtro Todas/Balcão/Oficina/Devoluções

**Quer eu seguir agora pra Fase B ou prefere revisar a Fase A primeiro no Vendas?** (abre lá em `Foco = Faturamento` e expande o dropdown ▾ pra ver "Por origem")

Se aprovar, na próxima sync já espelho tudo em `prototipo-ui-patch/` e gero o prompt zero-touch pro Code com os 3 arquivos da Fase A.Fase A pronta. Aguardo seu OK pra disparar Fase B (drawer Oficina + caixa consolidado).

## User

Verifier agent check completed

## Assistant

Verifier confirmou tudo funcionando. Aguardo seu OK pra Fase B.

## User

sim e respeite a constitutição no git.

## Assistant

_[tool: grep]_

_[tool: grep]_

Fase B começando. Respeitando constituição (single file, tokens canônicos, sem emoji em UI, sem rounded-xl+, cross-link bidirecional).

_[tool: read_file]_

_[tool: read_file]_

_[tool: read_file]_

_[tool: str_replace_edit]_

_[tool: grep]_

_[tool: run_script]_

_[tool: str_replace_edit]_

_[tool: str_replace_edit]_

_[tool: str_replace_edit]_

_[tool: grep]_

_[tool: grep]_

_[tool: read_file]_

_[tool: str_replace_edit]_

_[tool: done]_

_[tool: save_screenshot]_

_[tool: view_image]_

_[tool: grep]_

_[tool: save_screenshot]_

_[tool: view_image]_

_[tool: save_screenshot]_

Frame 2 funcionando lindamente. Drawer da OS #8788 (Nissan Versa, Helena) mostra o card verde "Esta OS gerou a venda #V-7835 · Faturada" com breakdown peças/serviço + fiscal + 3 atalhos. Agora Frame 3.

_[tool: show_html]_

_[tool: save_screenshot]_

_[tool: show_html]_

_[tool: sleep]_

_[tool: save_screenshot]_

_[tool: snip]_

_[tool: update_todos]_

## ✅ Fase B entregue — Frames 2 e 3

**Frame 2 — Drawer Oficina** (confirmado visualmente):
- OS #8788 (Nissan Versa, Helena Bastos) mostra card verde flutuante "**Esta OS gerou a venda #V-7835 · Faturada**"
- Subtitle "auto-criada na transição Pronto p/ retirar"
- Grid 3 colunas: Total · Peças (NF-e) · Mão-de-obra (NFS-e)
- Fiscal badges + 3 atalhos: **Abrir # V-7835 ↗** (primário) · DANFE NF-e · Ver no Caixa do dia
- Botão "Abrir" dispara evento `oimpresso:open-venda` que abre o drawer da venda no módulo Vendas (cross-link bidirecional ✓)

**Frame 3 — Caixa do dia "Por origem"**:
- Nova seção ao lado de "Por forma de pagamento" mostrando breakdown por source
- Barra de progresso colorida por origem (verde balcão · azul oficina · âmbar online)
- % do faturamento + contagem
- Linha extra com `↗ #OS-NNNN` clicáveis nas vendas de oficina

## 📁 Arquivos tocados Fase B

| Arquivo | Mudança |
|---|---|
| `oficina-page.jsx` | Drawer · computa `vendaFromOs` por `osIds.includes(os.id)` + render card quando `stage==="pronto"` |
| `oficina-page.css` | +130 linhas · estilos do card (gradient verde, fiscal badges, CTAs) |
| `vendas-page.jsx` | Listener `oimpresso:open-venda` abre drawer ao receber evento cross-módulo |
| `vendas-extras.jsx` | `VendasCaixaPage` · computa `bySource` + nova seção "Por origem" |
| `styles.css` | Grid fix `:nth-child(3),(4)` full-width + estilos `.vc-src-*` |

## 🏛️ Constituição respeitada

- ✅ Single file (tudo dentro de `Oimpresso ERP - Chat.html`)
- ✅ Sem emoji em UI (só ✓⌛× símbolos canônicos e ↗ pra cross-link)
- ✅ Tokens canônicos (oklch da paleta, sem inventar cores fora do design system)
- ✅ Sem rounded-xl+ (radius 6-10px)
- ✅ Drawer lateral, não modal full-screen
- ✅ UI em português (Esta OS gerou venda · Por origem · Abrir)
- ✅ Cross-link bidirecional (Vendas tem `↗ #OS-` na coluna · Oficina tem "Abrir #V-" no card)
- ✅ Padrão Cockpit V2 mantido (sidebar + drawer)

## ⏭️ Próximo — entrega zero-touch pro Code

Resta apenas a tarefa 4 do todo: espelhar em `prototipo-ui-patch/` + gerar URLs públicas + prompt pronto pro Claude Code commitar no `wagnerra23/oimpresso.com`.

Quer que eu **execute essa parte agora** ou **revisa visualmente a Fase B primeiro** (abre Vendas → Visões ▾ → Caixa do dia pra ver o "Por origem" + clica em qualquer OS pronta no Oficina Auto pra ver o card "gerou venda")?

Se aprovar agora, no próximo turn eu espelho os 5 arquivos e te entrego o prompt zero-touch.

_[tool: fork_verifier_agent]_

Aguardo seu OK pra fechar a entrega zero-touch pro Code.

## User

Verifier agent check completed

## User

ok

## User

ok

## Assistant

_[tool: list_files]_

_[tool: copy_files]_

_[tool: write_file]_

_[tool: get_public_file_url]_

_[tool: get_public_file_url]_

_[tool: get_public_file_url]_

_[tool: get_public_file_url]_

_[tool: get_public_file_url]_

_[tool: get_public_file_url]_

_[tool: get_public_file_url]_

_[tool: get_public_file_url]_

_[tool: write_file]_

_[tool: update_todos]_

_[tool: show_to_user]_

## ✅ Entrega zero-touch pronta

Tudo espelhado em `prototipo-ui-patch/integracao-vendas-oficina/`:

| Arquivo | Função |
|---|---|
| `data-vendas.jsx` · `vendas-page.jsx` · `vendas.css` | Fase A — coluna Origem + tree + KPI |
| `vendas-extras.jsx` · `styles.css` | Fase B Frame 3 — Caixa "Por origem" |
| `oficina-page.jsx` · `oficina-page.css` | Fase B Frame 2 — Card "OS gerou venda" |
| `README.md` | Documentação no repo (vira `prototipo-ui/INTEGRACAO_VENDAS_OFICINA.md`) |
| `STORYBOARD.html` | Referência visual dos 3 frames |
| **`PROMPT_PARA_CODE.md`** ← **abre agora** | Prompt pronto pra colar no Code |

## Como usar

1. Abre o arquivo **`PROMPT_PARA_CODE.md`** (já abri na sua aba)
2. Copia o bloco entre os `---` (começa em "Tarefa: trazer...")
3. Cola **UMA vez** no Claude Code
4. Code executa os 9 passos sozinho: pull main → branch novo → 8 curls → diff → commit → push → PR → merge → confirmação
5. Me avisa aqui quando o Code reportar "✓ Integração mergeada na main" pra eu atualizar `SYNC_LOG`

## ⚠️ Lembretes

- **URLs duram ~1h.** Se você demorar pra abrir o Code, me chama que regenero.
- Eu não commito (constituição: tools GitHub aqui são read-only). O Code é quem faz git+gh.
- A pasta `prototipo-ui-patch/integracao-vendas-oficina/` fica no Cowork como audit trail mesmo depois do merge.

Tudo pronto. Avisa quando o Code resolver.

