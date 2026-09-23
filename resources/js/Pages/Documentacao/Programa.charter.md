---
id: documentacao-programa-charter
page: /documentacao/programa
component: resources/js/Pages/Documentacao/Programa.tsx
related_prototype: prototipo-ui/cowork/Wagner/programa-doc-page.jsx
owner: wagner
status: draft
last_validated: "2026-09-23"
parent_module: Governanca
related_adrs:
  - 0320-programa-ondas-regua-correcao
  - 0294-metodo-dual-track-shapeup-catraca
  - 0070-jira-style-task-management-current-md-removed
  - 0239-governanca-design-system-git-ssot-regressao-ia
related_us: [US-INFRA-048]
tier: C
charter_version: 1
---

# Page Charter — /documentacao/programa (DRAFT)

> **Status:** `draft`. Não vira `live` sem [W] preencher **Non-Goals + Anti-hooks** (seções abaixo, vazias de propósito).
>
> **Dono do texto:** [`PLANO-MESTRE.md` § Trilha D](../../../../memory/requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md) — D.2 estados · D.3 ondas · D.4 ciclo · D.5 caminhos · D.6 batimento · D.7 DoD.
> **Execução:** `US-INFRA-048`, `parent_plan=programa-ondas`.
>
> ⚠️ **O `.tsx` deste charter ainda não existe.** A rota `/documentacao/programa` já está no ar e é **Blade**:
> `routes/web.php` (`documentacao.programa`) → `DocumentacaoController::programa()` → `resources/views/documentacao/programa.blade.php`.
> O porte pra Inertia é a thread 02 do playbook `programa-doc`. Enquanto isso, o comportamento vivo está na Blade e é defendido por `tests/Feature/DocumentacaoRouteTest.php`.

---

## Mission

Mostrar a Trilha D pra quem precisa operar o programa de documentação: o ciclo de 11 estações (D.4), as ondas D0–D10 (D.3), o caminho canônico por tipo de artefato (D.5), o batimento (D.6) e a definição de pronto (D.7). Todo o conteúdo vem do plano no git, lido em runtime.

Persona: [W] revisando o programa, e quem executa uma unidade de trabalho da trilha. Tela autenticada, densidade de ERP, 1280–1440px.

---

## Goals — Features (faz)

> Derivados do que o plano declara (D.2–D.7) e do que a Blade viva já faz. Não do protótipo.

- `PageHeader` do shell + navegação entre quatro vistas: **Ciclo · Ondas · Caminhos · Pronto & batimento**.
- **Ciclo** — as estações de D.4, na ordem do plano, com a volta explícita da estação 11 para a 2.
- **Ondas** — a tabela de D.3 (escopo · saída no dono existente · gate de saída).
- **Caminhos** — a tabela de D.5 (cinco tipos).
- **Pronto & batimento** — a lista de D.7 e a tabela de D.6.
- Link pro arquivo dono no git + data de atualização do plano.
- Volta pra `/documentacao`.

---

## Non-Goals — Features (NÃO faz)

> _pendente [W]._ Só [W] preenche esta seção (skill `charter-write`: é proibido inferir).
> O [CC] deixou uma proposta em `prototipo-ui/cowork/Wagner/cowork-inbox/programa-doc/Programa.charter.md` — ela **não é lei** até [W] aprovar.

---

## UX targets

p95 < 800ms · cabe em 1280px · shell autenticado (AppShellV2 no porte Inertia) · tabs underline-active em accent, nunca pill · só tokens `.cockpit`.

---

## Automation hooks (faz)

- A vista corrente fica na URL (`?vista=ciclo|ondas|caminhos|pronto`), pra poder ser citada num handoff.
- Contagens (ondas, estações, itens da DoD) saem da estrutura lida do plano, nunca de número escrito na copy.

---

## Anti-hooks (NÃO faz automaticamente)

> _pendente [W]._ Mesma regra dos Non-Goals.

---

## Perguntas abertas (decisão [W])

1. **De onde vem o estado de execução da onda?** O plano diz duas coisas: D.2 põe *execução* nas tasks MCP ("`todo/doing/done` nunca duplicado aqui"); o `## Status vivo` do próprio plano carrega "🟡 D0 em execução". A Blade viva lê a **segunda** (`DocumentacaoController::execucaoDaTrilha`), e um teste verde trava isso (`DocumentacaoRouteTest`, "a linha da Trilha D no plano continua legível…"). O protótipo e a proposta do [CC] pedem a **primeira**. O porte (thread 02) precisa de uma escolha antes de nascer.
2. Placement do porte: página Inertia própria (esta) **ou** manter a Blade, que já lê o plano em runtime (`PEDIDO-CL-programa-doc.md` §1).

---

## Pendências antes de `status: live`

- [ ] [W] preenche Non-Goals + Anti-hooks
- [ ] [W] responde as duas perguntas abertas
- [ ] `Programa.tsx` existe (thread 02) e os casos do `.casos.md` viram UC com teste
- [ ] Smoke visual 1280/1440
