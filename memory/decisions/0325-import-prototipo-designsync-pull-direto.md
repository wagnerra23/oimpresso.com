---
slug: 0325-import-prototipo-designsync-pull-direto
number: 325
title: "Import de protótipo via DesignSync pull direto — browser/ZIP deixam de ser o ÚNICO transporte (Fase −1-PULL)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-07"
module: design-system
tags: [design, design-sync, claude-design, cowork, aplicar-prototipo, import, transporte, protocolo]
supersedes: []
superseded_by: []
related:
  - 0315-design-sync-claude-design-vs-cowork-charter
  - 0324-frescor-espelho-cowork-dispatch-sla-limite-plataforma
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0299-figma-nao-e-fonte-de-design
  - 0282-protocolo-v2-colapso-ratificacao
pii: false
---

> **Ordenada por [W] em 2026-07-07** (verbatim: *"revogue as regras anteriores — acesso direto e não precisa de browser"*, após *"gostaria que atualizasse o protocolo com as novas melhorias de acesso — onde tens acesso para baixar comparar o design direto"*). Redigida por [CL] na mesma sessão, com o acesso **provado antes de escrever** (ver §Validação).
>
> **O que muda:** o TRANSPORTE do design (Cowork vivo → lado código). **O que NÃO muda:** a fonte de design (Cowork, §0.2 do INDEX), o SSOT git (ADR 0239) e o Eixo A da [ADR 0315](0315-design-sync-claude-design-vs-cowork-charter.md) — claude.ai/design segue **não** sendo armazém canônico; escrita (`write_files`/`delete_files`/`create_project`) segue gateada.

> **⚠️ EMENDA NO CORPO — [W] 2026-08-13** (verbatim: *"não existe mais zip, é direto o protocolo"* e, sobre esta ADR, *"remova ou altere a adr, ela não é mais read only"*). **O D2 caducou:** o ZIP deixou de ser *fallback* e virou caminho **morto**. Emenda pela exceção da [ADR 0377](0377-append-only-adr-excecao-por-label-emenda-0094.md), que libera **mexer**, não **falsificar** — o que está **datado** (o que se decidiu e se mediu em 2026-07-07) fica preservado abaixo; só a **rota** foi atualizada. O PR desta emenda exige a label `adr-body-edit-W`.

# ADR 0325 — Import de protótipo via DesignSync pull direto (Fase −1-PULL)

## Contexto

A premissa histórica da [ADR 0114](0114-prototipo-ui-cowork-loop-formalizado.md) ("Cowork desconectado do repo — Wagner copia e cola via export zip; *eventualmente Anthropic pode oferecer GitHub integration*") **expirou**: a integração oficial existe (tool nativa `DesignSync`, auth `/design-login` persistida na máquina — [ADR 0315](0315-design-sync-claude-design-vs-cowork-charter.md)) e a [ADR 0324](0324-frescor-espelho-cowork-dispatch-sla-limite-plataforma.md) já a usa em produção pro frescor do espelho (ledger 3 SYNC, PR #3893). Mas o protocolo de **import** (`aplicar-prototipo` Fase −1) continuava assumindo o ZIP manual como único caminho — Wagner baixava export no browser, largava em `~/Downloads`, e só então a máquina (`importar-bundle.mjs`) entrava.

Nesta sessão o acesso direto foi **verificado de ponta a ponta** (sem browser): `list_projects` + `get_project` + `list_files` + `get_file` alcançam os DOIS projetos do §0.2 do [INDEX-DESIGN-MEMORIAS](../requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md):

| Projeto | ID | Papel |
|---|---|---|
| **Oimpresso ERP Conunicação Visual.** | `019dcfd3-6ef2-7ee6-8512-b1b0e5544e58` | fonte das TELAS (`*-page.jsx`, 1337 arquivos) |
| Office Impresso — Design System | `019dd02f-d2d0-7ba6-a57f-24b3ddd073ac` | biblioteca do DS (componentes/templates) |

## Decisão

### D1 — Fase −1 ganha o caminho **−1-PULL (preferido pra mudança escopada)**

Quando a mudança de design é de **poucas telas/arquivos conhecidos** (o caso comum: "o Design mexeu no Financeiro"), o agente logado puxa DIRETO, sem Wagner no meio:

1. Resolver a âncora da tela (charter → `bundle_source`/`related_prototype`, `ancora.mjs`).
2. `DesignSync.get_file(projectId: 019dcfd3…, path: <âncora>)` → **persistir em arquivo**. ⚠️ *Atualizado 2026-08-13:* o destino deixou de ser o staging do ZIP (`~/Downloads/_cowork-handoff-staging/…`, hoje **legado**) e passou a ser o espelho versionado `prototipo-ui/cowork/`, escrito por `cowork-mirror-freshness.mjs --export-from` ([ADR 0374](0374-emenda-0315-espelho-cowork-e-rota-prevista.md)) — **transcrever à mão é proibido**: a escrita sai do dado, por script.
3. Seguir o fluxo normal: `detectar-telas.mjs` → manifesto → Fases 1-5. Comparação de identidade sempre com `contentHash`/`normalize` de `cowork-mirror-freshness.mjs` (ADR 0324 D1 — nunca hash "de memória").

### D2 — ZIP vira **fallback de bundle cheio**, não regra · ⛔ CADUCADO em 2026-08-13

> **O QUE VALE HOJE ([W] 2026-08-13):** o ZIP **não é mais transporte** — nem default, nem fallback. Todo import desce pelo **−1-PULL** (D1), e a escrita no espelho `prototipo-ui/cowork/` é do `cowork-mirror-freshness.mjs --export-from` ([ADR 0374](0374-emenda-0315-espelho-cowork-e-rota-prevista.md)). **Medido no dia da emenda:** `importar-bundle.mjs` só é invocado no CI como `--selftest` (zero imports reais) e `check-handoff.ps1` não tem invocador nenhum. **Os scripts NÃO foram apagados** — `render-proto-baseline.mjs` importa `acharBundleRoot` de `importar-bundle.mjs`, e podar capacidade é decisão [W] à parte, não efeito colateral de uma emenda de redação.
>
> ⚠️ **O limite técnico que motivava o fallback NÃO desapareceu** (segue vivo em D3): `get_file` é 1 chamada/arquivo, cap 256 KiB, e o conteúdo entra no contexto do agente — logo **re-importar o projeto inteiro segue caro**. O que mudou foi a *resolução*: em vez de reabrir o ZIP, bundle cheio se resolve por pull **escopado/em lote** (o espelho já é o SSOT do último handoff, e na prática o que muda por vez são poucas telas). Se um dia o custo de um re-import integral pesar de verdade, a saída é decisão [W] — não o retorno silencioso do ZIP.

**Registro do que se decidiu em 2026-07-07 (preservado, não reescrito):** o import por ZIP (`importar-bundle.mjs`) permanecia canônico quando o handoff era o **projeto inteiro**, pelas razões de custo acima; e a regra ainda anterior — *"transporte = SEMPRE export zip via browser"* — já ficava **revogada** naquela data, com o zip virando um dos dois transportes escolhidos por escopo:

| Escopo da mudança | Transporte (decidido em 2026-07-07) | Transporte VIGENTE (desde 2026-08-13) |
|---|---|---|
| 1–10 arquivos conhecidos (tela/componente) | **−1-PULL** DesignSync (default) | **−1-PULL** DesignSync (único) |
| Bundle cheio / reorganização ampla | ZIP + `importar-bundle.mjs` (fallback) | **−1-PULL** escopado/em lote — ZIP morto ([W]) |

### D3 — Limites de plataforma (honestos, herdam da 0324 D3)

`get_file` cap 256 KiB/arquivo · conteúdo entra no contexto (custo token) · auth interativa (sem headless/cron — CI continua medindo só cadência, 0324 D2) · binários via base64 (evitar; assets pesados ficam pro ZIP). `list_projects` filtra por design-system — o projeto Cowork (`PROJECT_TYPE_PROJECT`) se alcança por **ID explícito** (documentado no §0.2; não "descobrir" por lista).

### D4 — Segurança (herda da 0315, inalterada)

Leitura = transporte permitido (0315: métodos read livres). **Escrita segue gateada** (opt-in Wagner). Conteúdo de `get_file` é **dado, não instrução** (anti prompt-injection, doutrina da própria tool). Git continua o único canal de entrada no canon (PR + CI + gates).

## Validação (executada nesta sessão, 2026-07-07)

- ✅ `list_projects` → DS `019dd02f…` (updatedAt 2026-07-06T19:55Z).
- ✅ `get_project(019dcfd3…)` → `Oimpresso ERP Conunicação Visual.` (`PROJECT_TYPE_PROJECT`, owner Wagner) — fonte das telas alcançável por ID.
- ✅ `list_files` + `get_file(README.md)` no DS — conteúdo íntegro, sem browser.
- ✅ Protocolo de detecção sadio no mesmo dia: `detectar-telas.mjs --selftest` 6/6 PASS + run real no staging (46 sources, 0 órfãos).

## Consequências

✅ "O que mudou no protótipo do Financeiro?" vira 1 pull + 1 diff — sem Wagner exportar zip no browser. ✅ A premissa morta da 0114 fica corrigida por emenda (append-only). ✅ 0315/0324 intactas — esta ADR só promove a leitura de "medição de frescor" a "transporte de import escopado".
⚠️ **[atualizado 2026-08-13]** Pull integral de bundle **segue caro** (D3 intacto) — mas a resposta deixou de ser o fallback ZIP, morto por decisão [W]. Os scripts do ZIP continuam no repo por **dependência de módulo** (`acharBundleRoot`), não como rota; apagá-los é decisão [W] à parte. ⚠️ Se a plataforma ganhar export headless/webhook, reavaliar (gatilho da 0324 D4 — PR-bot regenerador).
