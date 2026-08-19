---
page: /officeimpresso/licenca_log
component: Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Logs/Index.tsx
status: draft
owner: wagner
parent_module: Officeimpresso
last_validated: '2026-08-19'
related_prototype: prototipo-ui/cowork/officeimpresso-page.jsx
related_runbook: memory/requisitos/Officeimpresso/RUNBOOK-logs.md
related_us:
  - US-OI-004
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
  - 0189-pageheader-canon-v3-1-cadastro-roxo
charter_version: 1
---

# Charter — Máquinas Cadastradas (`/officeimpresso/licenca_log`)

**Missão.** Dar ao suporte, numa tela só, a resposta de *"a máquina do cliente está liberada,
quando ela falou com o servidor pela última vez, e como eu libero/travo agora"* — sem sair do
cockpit e sem abrir o Delphi.

**Padrão de tela:** [PT-01 Lista](../../../../../../memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md).
**Plano:** [RUNBOOK-logs.md](../../../../../../memory/requisitos/Officeimpresso/RUNBOOK-logs.md) ·
**Paridade:** [logs-parity.md](../../../../../../memory/requisitos/Officeimpresso/logs-parity.md).

## Sobre a âncora de design — leia antes de usar

`related_prototype` aponta pro `officeimpresso-page.jsx` do Cowork, que é a fonte de design do
módulo inteiro. **A view dele que corresponde a ESTA tela é a `oi-licencas`** (lista de máquinas) —
**não** a `oi-log`, apesar do nome da rota.

Isso não é descuido, é um conflito medido em 2026-08-19 entre três artefatos:

| Artefato | Diz que `/officeimpresso/licenca_log` é... |
|---|---|
| Blade em produção | lista de **máquinas** (`licenca_computador` + último acesso) |
| Protótipo Cowork (`oi-log`) | **log de eventos** (KPIs por tipo, filtros por origem) |
| RUNBOOK do módulo (2026-05-10) | *"timeline de máquinas"* — concorda com o Blade |

**Decisão [W] 2026-08-19: paridade agora, realinhar depois.** Esta onda migra o que a tela faz
hoje, campo a campo; a reorganização para a estrutura desenhada (`empresas` / `licencas` / `log`)
é Onda 2, com o protótipo como fonte. Quem for mexer aqui **não** deve "consertar" o descompasso
por conta própria.

## Goals

- Preservar **100% dos 54 itens** mapeados no `logs-parity.md` — nenhum campo some na travessia.
- Sair do AdminLTE/DataTables/jQuery para o shell único (AppShellV2 + DS).
- Manter a operação idêntica: mesmos filtros, mesma ordenação, mesmas duas ações de bloqueio.

## Non-Goals

- ❌ **Não** muda o que a tela mostra. Migração preserva função; redesenho é Onda 2.
- ❌ **Não** renomeia a rota nem o componente para resolver o descompasso nome↔conteúdo.
- ❌ **Não** transforma os KPIs em filtro clicável — eles são globais e **não** seguem o filtro
  aplicado, igual ao Blade. KPI que reage a filtro é outra tela.
- ❌ **Não** adiciona coluna, exportação, seleção em massa ou bulk action que o Blade não tem.
- ❌ **Não** deixa o CSS `oi-*` do módulo atravessar — o módulo não tem Design System próprio.

## Automation Anti-hooks

- ❌ **Nunca renderizar as ações de bloqueio como `<Link>`/`<a href>`.** As duas rotas do legado
  são `Route::get` que **mudam estado**; um href é seguível por prefetch, crawler e "abrir em nova
  aba". Elas são `<Button onClick>` + diálogo, e só um clique deliberado dispara.
- ❌ **Nunca usar `value=""` em `<SelectItem>`.** O Radix lança e derruba a árvore React inteira
  (tela branca em produção). O item "Todos" usa o sentinela `__all__`; se as opções virarem
  data-driven, trocar por `<SafeSelectItem>`.
- ❌ **Nunca tratar `was_blocked_last === null` como `false`.** É tri-estado: `null` significa
  *nunca houve log*, e vira travessão — não "Liberada".
- ❌ **Nunca omitir o rótulo `(cadastro)`** quando a data vem de `dt_ultimo_acesso` em vez do log.
  Sem ele a coluna afirma um acesso que nunca foi registrado.
- ❌ **Nunca eager-load `maquinas`/`kpis`.** São `Inertia::defer` — a lista faz JOIN com
  enriquecimento por log e os KPIs são 4 `count()`.

## UX targets

- **1280px com a sidebar aberta sem scroll horizontal na página** (o monitor do cliente piloto é
  1280). São 10 colunas; se estourar, o scroll é interno ao container da tabela.
- Busca reage em ≤300ms de debounce, com partial reload que **não** repaga os KPIs.

## Estados

| Estado | O que aparece |
|---|---|
| Carregando | skeleton dos KPIs e da tabela, dentro do `<Deferred>` |
| Vazio sem filtro | explica que a rotina `/connector/api/processa-dados-cliente` popula quando o Delphi envia CNPJ + HD |
| Vazio com filtro | "Nenhuma máquina encontrada com os filtros aplicados." + ação **Limpar filtros** |
| Sem permissão | 403 antes do render — a guarda roda no controller, não na tela |

## Permissões

`superadmin` **ou** `officeimpresso.access` para ver; `officeimpresso.licencas.gerenciar` (ou
`superadmin`) para as ações de bloqueio, que somem quando o usuário não pode.

**A visão cross-empresa é por design** ([ADR 0093](../../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
§exceções): a WR2 é a fornecedora do desktop e quem dá assistência precisa ver a máquina do
cliente. Quem **não** tem a permissão continua preso ao próprio `business_id`.

## Casos

[Index.casos.md](Index.casos.md)
