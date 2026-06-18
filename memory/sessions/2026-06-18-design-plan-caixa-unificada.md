---
date: 2026-06-18
topic: "Plano de design da Caixa Unificada (Atendimento) — como a tela foi planejada (ingestão do bundle Cowork → memory/ · vínculo MCP/RAGAS via charter)"
tela: Atendimento/CaixaUnificada
charter: resources/js/Pages/Atendimento/CaixaUnificada/Index.charter.md
contract: prototipo-ui/contrato/caixa-unificada.contract.json
fonte: "prototipo-ui (Cowork 'comunicacao-visual', export 2026-06-18 — 'Caixa Unificada - Plano de Transformacao em DS.html' + 'Provar Antes de Padronizar (piloto).html')"
related_adrs: [0114, 0135, 0264]
pii: false
---

# Como a Caixa Unificada foi planejada (plano de design ingerido)

> **Por que este doc existe (esteira → armazém):** o plano de design vivia só no bundle Cowork (esteira, transitória). Aqui ele vira **conhecimento durável** no `memory/` do projeto (armazém, sincronizado pro MCP). **Vínculo com a tela** = a cadeia de proveniência: este doc → [`Index.charter.md`](../../resources/js/Pages/Atendimento/CaixaUnificada/Index.charter.md) (§Decisões) → [`caixa-unificada.contract.json`](../../prototipo-ui/contrato/caixa-unificada.contract.json) (`fonte` → protótipo). Por isso a Jana (MCP/RAGAS), perguntada sobre a Caixa, recupera **por que** ela é como é — não só **o que** ela faz.

## A decisão-mãe: a Caixa é o OURO

O design (e o [W]) decidiram: **a Caixa Unificada está do jeito que se quer.** O plano "Caixa Unificada → DS" é explícito:

- **"Como esta tela vira DS sem mudar um pixel dela."** Nenhuma cor muda.
- **Princípio: extrair, não repintar.** A Caixa é o molde; o Design System **aprende dela** — ela não se ajusta ao DS.
- **"Aplicar o design" = extrair o DS da Caixa e propagar pras OUTRAS telas**, NÃO mexer na Caixa.
- **Prova de "não mudou":** diff de computed-style = **0** (mesmo pixel antes/depois; se mudar, não passa).

→ Operacionalizado como **LEI** no charter (§Decisões: "A Caixa é o OURO — não repintar").

## As regras derivadas

| Regra | Detalhe |
|---|---|
| **O verde do WhatsApp fica** | vira token de canal governado (`--ch-wa`) com o mesmo valor; nunca trocado por roxo. |
| **`workspace-3` NÃO é padrão universal** | só primitivo opcional pras telas mestre→corpo→aside (CRM, OS, atendimento). Forçar em cadastro/dashboard = anti-padrão (intuição do [W], confirmada pelo design). |
| **Método de propagação: Piloto → Prova → Propaga** | o primitivo nasce com a 1ª tela real que precisa dele (nunca no vácuo). **3 portões:** a tela-piloto ficou igual-ou-melhor · a Caixa não mudou (diff=0) · o probe/guard passa. Aí sim propaga, 1 onda por tela, com antes/depois pra [W] aprovar. |

## O que foi PORTADO da Caixa (vínculo com os PRs)

- **Guia** (troubleshooters + trilhas) — `inbox-cur.jsx` → PR #2971.
- **Notas por-mensagem** — `MsgCommentWrap` → PR #2972.
- **Reconectar canal via QR in-place** ("resposta ao [W]: clicar Reconectar mostra o QR") — `inbox-page.jsx` "Modal Reconectar" → PR #2974 (1º piloto da catraca Contrato de Tela).

## Esteira → armazém: seguro apagar do bundle

Com este plano ingerido, é **seguro apagar do bundle/Cowork** (são resíduo OU já-conhecimento-no-projeto): os `.html` de plano da Caixa ("Plano de Transformacao em DS", "Provar Antes de Padronizar"), os `Adversário*`/`Auditoria*`/`Avaliac*`, `_arquivo/`, `benchmark/`, `GAPS_v*`, `FORCE_*`. O `bundle-lint` (régua 6, `prototipo-ui/PROTOCOL.md`) flagra o resíduo que voltar.

## Refs
- Charter: `resources/js/Pages/Atendimento/CaixaUnificada/Index.charter.md` (§Decisões & reclamações · §Contrato visual)
- Contrato: `prototipo-ui/contrato/caixa-unificada.contract.json`
- Método: `memory/requisitos/_DesignSystem/RUNBOOK-contrato-de-tela.md` + `memory/sessions/2026-06-18-plano-memoria-proveniencia.md`
- [ADR 0114](../decisions/0114-prototipo-ui-cowork-loop-formalizado.md) · [ADR 0135](../decisions/0135-omnichannel-inbox-arquitetura.md) · [ADR 0264](../decisions/0264-governanca-executavel-trio-de-tela.md)
