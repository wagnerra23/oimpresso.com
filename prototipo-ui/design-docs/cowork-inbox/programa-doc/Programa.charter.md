---
id: documentacao-programa-charter
page: /documentacao/programa
component: resources/js/Pages/Documentacao/Programa.tsx (proposto — ver PEDIDO §placement)
related_prototype: prototipo-ui/cowork/programa-doc-page.jsx
owner: wagner
status: draft
last_validated: "2026-08-06"
parent_module: Governanca
related_adrs: [0320, 0294, 0070, 0286, 0239]
tier: C
charter_version: 1
---

# Page Charter — /documentacao/programa (DRAFT)

> **Status:** draft de [CC] (F1, 2026-08-06). [W] aprova **Non-Goals + Anti-hooks** antes de virar `status: live`.
>
> Dono do texto: `memory/requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md` § Trilha D.
> Task de execução: `US-INFRA-048`, `parent_plan=programa-ondas`.

---

## Mission

Dar rosto humano à Trilha D — o programa que mantém a documentação técnica e operacional viva.
Mostra o **ciclo de 11 estações**, as **ondas D0–D10**, o **caminho canônico por tipo de artefato**
e a **definição de pronto + batimento**, sempre apontando pro dono no git. É tela de LEITURA de
plano: quem quer saber "onde estamos" clica na task MCP, não nesta página.

Persona: [W] (revisão do programa) e quem executa uma unidade de trabalho da trilha (ZELADOR).
Densidade de ERP, 1440px, dentro do shell autenticado.

---

## Goals — Features (faz)

- `PageHeader` canônico + `TabBar` (underline-active) com quatro vistas: **Ciclo · Ondas · Caminhos · Pronto & batimento**.
- Faixa de 4 KPIs: onda atual, ondas (n/11), estações do ciclo, task MCP do programa.
- **Ciclo**: 11 estações clicáveis (fase por cor), painel de detalhe com `entrada · máquina que já existe · regra`, e a volta explícita 11→02.
- **Ondas**: tabela D0–D10 com escopo, saída no dono existente, gate de saída e estado.
- **Caminhos**: cinco tipos (máquinas · hooks · MCP · módulos · fluxos) com pipeline e campos obrigatórios; mais a tabela das seis camadas do escopo.
- **Pronto & batimento**: checklist da DoD (aberto/parcial/feito) e tabela do batimento advisory.
- Link permanente "Ver plano no git" pro arquivo dono + rodapé de proveniência.
- Volta pra `/documentacao` (a tela é uma página da documentação, não um módulo).

---

## Non-Goals — Features (NÃO faz)

- ❌ **NÃO grava nem edita status de onda, task ou DoD.** Read-only, sem exceção "só pra marcar um item".
- ❌ **NÃO é fonte de estado vivo.** `todo/doing/done` vem das tasks MCP (`parent_plan=programa-ondas`, ADR 0070); duplicar aqui é exatamente o erro que a trilha existe pra não repetir (ADR 0294 — 1 plano = 1 registro).
- ❌ **NÃO mantém cópia do texto do plano.** O `.tsx` não guarda parágrafo do PLANO-MESTRE como literal de código quando a rota já sabe renderizar o markdown dono.
- ❌ NÃO cria índice, roadmap, agente ou gate novo — a Trilha D é explícita nisso.
- ❌ NÃO mostra segredo, credencial ou host: máquinas aparecem por nome e ponteiro pro Vaultwarden.
- ❌ NÃO é tela de produto por tela nem inventário — inventário é derivado (`PAINEL-SISTEMA`, `MAQUINAS-INVENTARIO`).
- ❌ NÃO cruza dado entre businesses: conteúdo é global de governança, sem query tenant-scoped.

---

## UX targets

p95 < 800ms · cabe em 1280px · shell autenticado (AppShellV2) · densidade alta ·
tabs underline-active em accent (nunca pill) · zero cor crua (tokens `.cockpit`).

---

## Automation hooks (faz)

- Vista corrente refletida na URL (`?vista=ciclo|ondas|caminhos|pronto`) — a página é linkável num handoff.
- Contagem de ondas e de itens da DoD derivada da estrutura renderizada, nunca escrita à mão na copy.

---

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO abre, fecha nem cria task no MCP a partir da tela.
- ❌ NÃO dispara `documentation-loop`, `system-map` ou qualquer detector — batimento é advisory e roda no seu momento.
- ❌ NÃO edita o markdown dono a partir da UI (o caminho de mudança é PR + merge de [W]).
- ❌ NÃO persiste preferência antes de escolha consciente (vista default = Ciclo).

---

## Pendências antes de `status: live`

- [ ] [W] aprova Non-Goals + Anti-hooks
- [ ] Decidir placement: página Inertia própria **ou** documento navegável em `/documentacao` (PEDIDO §1)
- [ ] Se Inertia: origem do estado de onda = tool MCP de tasks (definir contrato de leitura)
- [ ] Smoke visual 1280/1440
