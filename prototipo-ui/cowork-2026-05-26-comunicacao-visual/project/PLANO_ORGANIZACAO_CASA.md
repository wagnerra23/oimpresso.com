# PLANO_ORGANIZACAO_CASA.md
# 2026-05-18 · Reset estrutural Cowork ↔ Repo
# Objetivo: terminar a guerra de 10h. CSS escopado, nomes idênticos, lixo fora.

## Diagnóstico

Você está sofrendo 3 problemas SIMULTANEAMENTE:

1. **CSS conflitando** — `styles.css` cresceu pra ~8000 linhas e tem regras que
   atropelam `appshell.tsx`/sidebar reais do Laravel/Inertia
2. **Nomes diferentes Cowork↔Repo** — `Oimpresso ERP - Chat.html` é só do Cowork,
   no Laravel a entrada é `resources/views/.../AppShellV2.tsx`. Code se perde.
3. **Lixo acumulado** — 28 HTMLs experimentais no root, 4 versões de Vendas
   (`vendas-aplus`, `vendas-create-completo`, `vendas-page`, `Venda Simples F1`),
   2 produtos (`produto-app`/`produto-data`/`produto-icons` + `prod-*`),
   2 chats (`chat-v1-legacy.jsx`), múltiplos benchs.

Cada sync mistura essas 3 coisas e o Code não consegue distinguir o que aplicar.

---

## Plano — 4 fases (3-4 horas total)

### Fase 1 · Limpar o Cowork (45 min)

**Apagar HTMLs experimentais** (ficam no histórico Git se precisar):

```
Auditoria Final.html
Auditoria UI.html
Bench KB v2.html
Bench KB.html
Bench Mecânica.html
Boleto e Contas Inter.html      ← migrado pra boleto-contas-app.jsx
Compras.html                     ← migrado pra compras-page.jsx
Estado da Arte.html
Financeiro Unificado.html        ← migrado pra financeiro-app.jsx
Identidade Visual.html
Inventario - Migracao Blade React.html
Método KB-9.75.html              ← KEEP (referência do método)
Plano por Tela.html
Product Cadastro.html
Product Picker Mecanica.html
Product Picker.html
Produto Unificado.html
Produtos Cockpit.html
Produção Oficina - Tela.html     ← migrado pra oficina-page.jsx
Progresso de Notas.html
Telas Faltantes Onda 2.html
Telas Faltantes.html
Venda Simples F1.html
Venda por Estagio FSM v1.html
Venda por Estagio FSM.html
Vendas A+.html                   ← migrado pra vendas-page.jsx
```

**KEEP:**
- `Oimpresso ERP - Chat.html` (entrada do shell)
- `Método KB-9.75.html` (playbook)
- `Diagnóstico Vendas KB-9.75.html` (referência do bench)

**Apagar JSXs duplicados:**
```
chat-v1-legacy.jsx          ← sobrou da migração chat
prod-page-v1-cv.jsx         ← v1 antiga
produto-app.jsx             ← duplicado de prod-page.jsx
produto-data.jsx            ← idem
produto-icons.jsx           ← idem
vendas-aplus.jsx            ← protótipo · foi consolidado em vendas-page.jsx
vendas-create-completo.jsx  ← idem · embedded em vendas-page.jsx
```

### Fase 2 · CSS escopado por módulo (1h)

**Quebrar `styles.css` (8000 linhas) em:**

```
styles.css            → SÓ tokens + shell (sidebar, header, drawer base)
                        ~1500 linhas. Reset, vars CSS, .os-* utilitários.

vendas.css            → tudo .vd-*, .vendas-aplus, FSM stepper de vendas
                        ~1200 linhas. Extraído de styles.css.

financeiro.css        → tudo .fin-*, mescla fin-boletos.css
                        ~1500 linhas. Single source of truth Eliana.

(módulos existentes mantém)
compras-page.css
crm-page.css
inbox-page.css
kb-page.css
oficina-page.css
equipe-page.css
mockup-pages.css
chat-jana.css
prod-mec.css + prod-page-extras.css → prod.css (merge)
```

**Total: 12 → 11 arquivos, cada um <2k linhas, escopado por domínio.**

Carregamento condicional no `Oimpresso ERP - Chat.html`:
```html
<link rel="stylesheet" href="styles.css"/>              <!-- sempre -->
<link rel="stylesheet" href="vendas.css"/>              <!-- sempre · pequeno -->
<link rel="stylesheet" href="financeiro.css"/>          <!-- sempre · pequeno -->
<link rel="stylesheet" href="kb-page.css"/>             <!-- sempre -->
```

### Fase 3 · Nomes batem entre Cowork e Repo (30 min)

**Regra:** mesmo nome do arquivo nos dois lados. Code abre, encontra, sobrescreve.

```
Cowork (raiz)              ↔  Repo (prototipo-ui/)
─────────────────────────     ─────────────────────────
Oimpresso ERP - Chat.html  ↔  Oimpresso ERP - Chat.html   (já bate)
styles.css                 ↔  styles.css                  (já bate)
vendas.css                 ↔  vendas.css                  (NOVO)
financeiro.css             ↔  financeiro.css              (NOVO)
vendas-page.jsx            ↔  vendas-page.jsx             (já bate)
financeiro-app.jsx         ↔  financeiro-app.jsx          (já bate)
... (todos os .jsx do refino batem)
fsm-stepper.jsx            ↔  fsm-stepper.jsx             (NOVO no repo)
```

**Nenhum nome diferente. Código grita "este arquivo".**

### Fase 4 · Doc de mapeamento ÚNICO (30 min)

**Criar `ARQUITETURA.md` no Cowork e no repo · idêntico nos 2 lados:**

```
# ARQUITETURA.md
# Mapa de todos os arquivos do protótipo
# Cowork e prototipo-ui/ TÊM OS MESMOS ARQUIVOS

## CSS (11 arquivos · cada um escopado por domínio)
- styles.css        — tokens + shell + sidebar base
- vendas.css        — tudo .vd-*, .vendas-aplus
- financeiro.css    — tudo .fin-*
- ...

## JSX por módulo
### Vendas
- vendas-page.jsx       — Lista + Drawer
- vendas-extras.jsx     — Sub-rotas (Caixa, Devoluções, etc)
- vendas-shortcuts.jsx  — Cheat-sheet J/K
- vendas-ai.jsx         — IA copiloto
- vendas-curation.jsx   — Comentários + audit + troubleshooter
- vendas-output.jsx     — Transcript + apresentação
- vendas-tweaks.jsx     — TweaksPanel

### Financeiro
- financeiro-app.jsx
- financeiro-data.jsx
- financeiro-icons.jsx
- financeiro-telas-extras.jsx
- financeiro-curation.jsx
- financeiro-ai.jsx
- financeiro-output.jsx

### Componentes compartilhados (cross-module)
- fsm-stepper.jsx       — FSM canônica (vendas, OS, financeiro, boleto)
- tweaks-panel.jsx      — TweaksPanel base
- icons.jsx             — SVG icons
- sidebar.jsx           — Sidebar shell
```

---

## Como executar

**Opção rápida (eu faço todas as 4 fases agora):**
1. Eu apago os 25 HTMLs experimentais + 7 JSXs duplicados no Cowork
2. Quebro o styles.css em 3 arquivos escopados (vendas.css, financeiro.css, styles.css enxuto)
3. Atualizo o Oimpresso ERP - Chat.html com a nova lista
4. Crio o ARQUITETURA.md (idêntico nos 2 lados)
5. Regenero o pacote v3 atômico pro Code com a estrutura limpa
6. Code aplica UMA vez, vai bater 100%

**Opção lenta (você revê cada passo):**
Mostro fase a fase pra você confirmar antes de apagar coisa.

---

## Resposta à pergunta

> "É viável minha solicitação?"

**Sim.** A casa precisa dessa organização pra deixar de sangrar nos próximos refinos. Mas só faz sentido se for AGORA — antes de aplicar próximos refinos em CRM/Compras. Senão acumula mais lixo.

> "Deveria os arquivos ter os mesmos nomes?"

**SIM, obrigatório.** Esse é 80% do problema com o Code. Quando os nomes batem, ele entende "este aqui sobrescreve aquele". Quando os nomes diferem, ele tenta inferir e erra.

> "Alguma sugestão?"

**Faça opção rápida.** Confia em mim, eu apago, organizo, regenero o pacote v3, e você cola UMA vez no Code. Em 2-3h você termina a guerra de 10h.

**Confirma que eu execute a opção rápida?**
