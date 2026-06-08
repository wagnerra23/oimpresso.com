---
page: /crm · window.CrmPage
component: crm-page.jsx (+ crm-page.css)
repo_alvo: Inertia CRM/Index — ⚠️ HOJE É BLADE LEGADO (UltimatePOS, sem Inertia page · CODE_NOTES 2026-06-02 / L-26). Migração Blade→Inertia pendente.
status: proposta (Cowork) — trio criado pra DESTRAVAR a migração do legado. [W] confirma aprovações/reprovações.
owner: wagner
last_validated: 2026-06-02
validated_against: crm-page.jsx @ cowork-2026-06-02
persona: vendedor/comercial (funil) · Wagner (pipeline/conversão)
identidade: azul 220 — accent ESCOPADO `.crm-scope{ --accent: oklch(0.45 0.11 220) }` (ADR 0200 D-02, proposta). Estágios têm hues SEMÂNTICOS próprios (lead 250 · qual 270 · prop 70 · negoc 30 · ganho 145) — não confundir com o accent da tela. Roxo canon (ADR 0235) intacto fora do escopo.
nota_atual: 8.6
irmao: CRM.casos.md (6 UCs grounded no código)
tier: B
charter_version: 1
---

# Page Charter — /crm (Funil comercial / kanban)

> **Status:** memória-por-tela (L-14), grounded em `crm-page.jsx`. Captura o estado vivo pra a migração Blade→Inertia ser fiel. [W] confirma aprovações/reprovações.
> **Padrão visual:** Cockpit V2 (ADR 0110) · kanban + drawer lateral.
> **⚠️ Contexto (L-26):** o CRM do repo ainda é **Blade legado UltimatePOS** (provável sobre `resources/views/contact` / customer_group). Esta charter + os casos são o alvo da migração — não há Inertia page ainda.

---

## Mission

O funil comercial da gráfica: ver todas as oportunidades num kanban por estágio, arrastar pra avançar, e abrir o detalhe pra ligar/cobrar/orçar. A pergunta que responde primeiro é **"o que está quente e o que vai fechar?"** — pipeline e conversão num relance, com as oportunidades em risco saltando aos olhos.

---

## Goals — Features (PRECISA TER)

**Vivo hoje no protótipo (aprovado / manter):**
- **Kanban 5 estágios:** Lead → Qualificado → Proposta → Negociação → Ganho, com **total por coluna** + contador (`STAGES`/`byCol`) — _UC-C01_
- **Drag-drop pra avançar:** arrasta o card pra outra coluna → muda `stage` + **toast** de confirmação (`moveDeal`/`crm-toast`) — _UC-C02_
- **KPI strip:** Pipeline ativo · Ganhas no mês (+ticket) · Taxa de conversão · Ciclo médio (`stats`) — _UC-C03_
- **Filtro "Só quentes":** mostra só os deals `warn`/`bad` (em risco) — _UC-C04_
- **Drawer do deal:** valor, **flow de estágio clicável** (move pelo drawer), ações (Ligar · WhatsApp · Orçamento · Próxima ação), histórico — _UC-C05_
- **Novo lead** (drawer de criação) — _UC-C06_
- **Badge semântico por card:** novo/indicação/orçamento ok/aguarda decisão/Nd sem resp./fechando/contrato/NFe (cores ok/warn/bad/win/info)

---

## Non-Goals — Features (NÃO faz)

- ❌ Cadastro completo de cliente (isso é o módulo Clientes/Contatos · drawer 760 PT-03)
- ❌ Emissão de orçamento/venda na tela (dispara pro módulo Vendas/Orçamento)
- ❌ Atendimento/chat omnichannel (isso é Caixa Unificada / Inbox)
- ❌ Inglês em UI cliente-facing

---

## UX Targets

- Cabe em 1280px; kanban com scroll horizontal só se necessário (5 colunas folgadas)
- Oportunidade em risco (warn/bad) salta aos olhos no card + filtro "só quentes" em 1 clique
- Mover deal = arrastar (drag-drop) OU pelo flow no drawer — sempre com toast
- escala warm semântica nos badges; estágios com hue próprio consistente
- 0 erros JS console

---

## UX Anti-patterns (REPROVADO — não repetir)

- ❌ **Emoji em ações** (`📞 Ligar`, `💬 WhatsApp`, `📄 Orçamento`) — proibição DS "só lucide-react, sem emoji". **Na migração F3: trocar por ícones lucide** (Phone/MessageCircle/FileText).
- ❌ **Cor crua / paleta inventada** fora dos tokens — identidade só por `.crm-scope{--accent}`; os hues de estágio devem virar tokens semânticos, não oklch solto espalhado.
- ❌ Modal full-screen pra detalhe (usar drawer lateral — já é o padrão aqui)
- ❌ `rounded-xl+` · `font-bold` em h1

> **Nota sobre WhatsApp:** o botão "WhatsApp" no drawer é **ação interna do operador** (abrir conversa com o lead), NÃO um CTA "Fale no WhatsApp" em landing pública — então **não** fere a proibição charter (que é sobre landing cliente-facing). Manter.

---

## Identidade & DS

- **Accent escopado azul 220** (`.crm-scope{ --accent: oklch(0.45 0.11 220) }`) herdando componentes do `ds-v5`. Roxo canon intacto fora do escopo.
- **Hues de estágio** (lead/qual/prop/negoc/ganho) = semânticos do funil; na migração, tokenizar (`--stage-lead` etc.) em vez de oklch inline por card.
- Reusa shell: `.os-page`/`.os-stats`/`.os-btn` + `.crm-*` + `.fin-stat-hero` (compartilhado).

---

## Refs

- CRM.casos.md — 6 UCs grounded em `crm-page.jsx`
- ADR 0110 — Cockpit V2 · ADR 0235 — roxo canon · ADR 0200 — DS é piso / accent escopado
- L-26 — repo é UltimatePOS híbrido; CRM = legado Blade a migrar

## Trilha do tempo
- 2026-06-02 · [CC] criou o trio (charter+casos) grounded no `crm-page.jsx` (8.6), pra destravar a migração Blade→Inertia que [CL] flagou pendente (CODE_NOTES 17:16). Travado: emoji→lucide na F3, WhatsApp é ação interna (mantém), accent escopado azul 220. Aguarda confirmação de [W] + decisão de prioridade da migração.
