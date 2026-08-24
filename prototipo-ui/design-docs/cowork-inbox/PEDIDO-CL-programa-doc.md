# Pedido ao [CL] — Trilha D: patch do plano + tela do programa

> [CC] F1. **Não commitado.** Cole pro Code ou abra Issue `cowork-intake`.
> Base: leitura do `main` em 2026-08-06 (`PLANO-MESTRE.md`, `contract.schema.json`, charters/casos de referência).
> Origem: [W] pediu o ciclo completo (não só o mapa de cobertura) e depois "quero ver a interface".

## 0. Os dois entregáveis são independentes

| # | O quê | Caminho | Depende de |
|---|---|---|---|
| A | Reescrita da § Trilha D do plano mestre | `cowork-inbox/PLANO-MESTRE-trilha-d-ciclo-completo.md` | nada — doc-only, não dispara deploy |
| B | Tela do programa (F1 → F3) | `cowork-inbox/programa-doc/` (trio) + protótipo `programa-doc-page.jsx` | placement decidido (§1) |

**A pode entrar hoje. B não deve entrar sem o §1 decidido.**

---

## A · Patch do plano  · doc-only, zero código

Substituir a seção `## Trilha D — documentação técnica e operacional` de
`memory/requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md` (de `## Trilha D` até imediatamente
antes de `## Índice das etapas (arquivos)`) pelo bloco do arquivo do patch. Também troca a célula da
Trilha D na tabela de etapas (o patch traz a linha pronta).

O que muda frente à redação de 2026-08-05:

- **D.2** vira o ciclo de **11 estações** (Mermaid), fechando em *aprender → medir de novo* — antes o texto parava em publicar/operar.
- **D.4** novo: caminho canônico por tipo (máquinas · hooks · MCP · módulos · fluxos), com o "não escreva uma segunda lista de hooks" explícito e `SUPERFICIE.md` nas portas modulares.
- **D.5** vai de 9 pra **11 ondas (D0–D10)** — plataforma separada de MCP, legado/rede local separado de verticais, D10 manutenção contínua.
- **D.1** ganha as camadas **Operação** e **Visão humana**.
- **D.8** ganha os dois critérios que faltavam: segredo só como ponteiro e "um incidente já girou o ciclo".

**Antes de commitar:** `node scripts/memory-schemas/validate.mjs memory/requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md` e `npm run docs:loop -- --snapshot` (pra o recibo antes→depois existir).
Ratificação = merge de [W] (ADR 0258).

---

## B · Tela do programa

### 1. Placement — decidir ANTES de escrever código

A rota `/documentacao` hoje é **Blade** (`DocumentacaoController` + `documentacao.doc`), renderizando
markdown do `memory/`. Então há dois caminhos, e eles não custam o mesmo:

| Opção | O que é | Custo | Quando escolher |
|---|---|---|---|
| **A · documento navegável** ✅ escrito | `memory/reference/GOV-PROGRAMA-DOCUMENTACAO.md` — pronto em `cowork-inbox/programa-doc/`, frontmatter completo (`nav_group: governanca`, `nav_order: 40`, `lente: [operar, construir]`), aparece no rail existente, zero código | ~0 | se o valor é **ler o plano** |
| **B · página própria** | `resources/js/Pages/Documentacao/Programa.tsx` (Inertia) com as 4 vistas do protótipo | médio | se o valor é **operar o programa** (ciclo clicável, ondas ligadas às tasks MCP) |

Recomendação [CC]: **A agora, B quando as tasks MCP forem a fonte do estado de onda** (é o que dá à
tela algo que o markdown não dá). Fazer B com estado estático seria criar a cópia que a própria
Trilha D proíbe — ver `UC-PROGD-01`.

### 2. O trio (para a opção B)

Em `cowork-inbox/programa-doc/`, prontos pra mover:

| Arquivo | Destino | Nota |
|---|---|---|
| `Programa.charter.md` | `resources/js/Pages/Documentacao/Programa.charter.md` | `status: draft` — [W] aprova Non-Goals + Anti-hooks antes de `live` |
| `Programa.casos.md` | `resources/js/Pages/Documentacao/Programa.casos.md` | 5 UC, todos **❌ não coberto** — nenhum teste escrito ainda |
| `programa-doc.contract.json` | `prototipo-ui/contrato/programa-doc.contract.json` | valida contra `contract.schema.json`; rodar **advisory** no `contrato-de-tela.yml` antes de promover |

O contrato exige âncora `data-contract="<id>"` em cada seção declarada — 13 seções, ordem
`page-header → tabbar-vistas → kpi-strip → fonte-dona`.

### 3. Invariantes que a tradução não pode perder

1. **Estado de onda vem do MCP** (`parent_plan=programa-ondas`), não de literal no `.tsx` nem do markdown — `UC-PROGD-01`, ADR 0294. O protótipo marca D0 "em execução" **como texto**; isso é F1, não canon.
2. **Ponteiro > cópia**: o texto renderiza do dono; parágrafo do plano não vira string de código — `UC-PROGD-02`, ADR 0239.
3. **Read-only**: nenhum controle que grave. Sem checkbox de DoD clicável — `UC-PROGD-03`.
4. **Tabs underline-active em accent**, nunca pill (guia do DS). Vista na URL (`?vista=…`) — `UC-PROGD-04`.
5. **Sem segredo, sem tenant**: máquinas por nome + ponteiro pro Vaultwarden — `UC-PROGD-05`, ADR 0093.

### 4. Referência visual (não é código pra portar)

Protótipo Cowork: rota `programa-doc` em `oimpresso.com.html` → `programa-doc-page.jsx` + `.css`.
Usa DS vivo (`Alert`, `StatusBadge`, `TabBar`) e só tokens `.cockpit`. Os três tons de fase do ciclo
(azul medir / roxo traduzir / verde publicar / âmbar operar) são `oklch` na família dos semânticos,
via `color-mix` — se virarem produção, promover a token nomeado antes.

---

## Fora de escopo

- Portar `programa-doc-page.jsx` como está: é protótipo de arquitetura de informação; o que atravessa é a estrutura e o contrato, não o código.
- Criar gate, agente, índice ou roadmap novo — a Trilha D é explícita: reusa as máquinas que já existem.
- Mexer nas outras ondas do `PLANO-MESTRE` (0a–6.4) — o patch toca só a § Trilha D.
