---
id: resources-js-pages-sells-components-salesheet-charter
page: /sells (drawer da linha — documento vivo da venda)
component: resources/js/Pages/Sells/_components/SaleSheet.tsx
related_prototype: prototipo-ui/cowork/vendas-page.jsx (VendaDetailDrawer, L1240-1400)
owner: wagner
status: draft
last_validated: "2026-07-28"
parent_module: Sells
related_adrs: [93, 104, 143, 149, 192]
related_us: [US-SELL-COWORK-R4-DISTRIBUICAO]
tier: A
charter_version: 1
---

# Page Charter — drawer da venda (`SaleSheet`)

> **Status:** draft — nasce em 2026-07-28 a pedido de [W] (*"até a interface está errada. confusa."*),
> DEPOIS de 880 linhas e ~5 ondas de features. É a lei que faltava: sem ela, cada onda
> empilhou mais uma seção e mais um botão, e ninguém tinha onde dizer não.
>
> ⚠️ **Este charter descreve o ALVO (o protótipo canônico), não o estado atual.** O §Divergência
> mede a distância. Nenhum `.tsx` muda antes de [W] ratificar esta lei.

---

## Mission

Ser o **documento vivo da venda** — abrir da linha da lista e responder, sem sair da lista:
o que foi vendido, quanto falta receber, qual a situação fiscal, o que já aconteceu.

---

## A fonte (ADR 0299 · ADR 0282 — protótipo Cowork, não Figma, não invenção)

`prototipo-ui/cowork/vendas-page.jsx` → `VendaDetailDrawer` (L1240+). Verificado no disco em
2026-07-28: 1.910 linhas, o drawer começa em L1240 e a barra de abas em L1314.

**O protótipo organiza o drawer em ABAS. A implementação atual empilha tudo numa coluna.**
Essa é a divergência-mãe, e é a causa medida do "confuso".

| Protótipo (`vd-drawer-tabs`, L1314-1330) | Conteúdo |
|---|---|
| **Itens** | linhas da venda (+ contador + badge de comentário inline) |
| **Fiscal** | NF-e / NFS-e (+ contador de documentos) |
| **Pagamento** | recebimentos e cobrança |
| **Timeline** | histórico |
| **✦ IA** | assistente |

No header do protótipo: `#id` · total · status · **Editar** · fechar. **Uma** ação, não sete.

---

## Goals — o que o drawer faz

- Abre da linha da lista sem navegar (a lista continua atrás)
- Header carrega a identidade e o dinheiro: `#invoice_no` · total · status de pagamento
- Conteúdo **agrupado por natureza**, na ordem do protótipo (Itens → Fiscal → Pagamento → Timeline → IA)
- Toda ação de um assunto vive **junto do assunto** — dinheiro no bloco de dinheiro, documento no bloco de documento
- `Ver tela →` leva ao `/sells/{id}` (full-page) quando o usuário quer profundidade
- Multi-tenant Tier 0: todo payload escopado por `business_id` ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))

---

## Non-Goals — ⚠️ PENDENTE [W]

> A skill `charter-write` **proíbe** o agente de inferir Non-Goals e Anti-hooks — cada item
> vira Pest GUARD no CI, e anti-padrão inventado é pior que ausente porque parece canon
> (`memory/proibicoes.md` §5 2026-07-16). Os itens abaixo são **perguntas**, não lei.
> Viram `❌` quando [W] responder.

- ❓ O drawer deve **editar** a venda, ou só exibir e mandar pro `/sells/{id}/edit`?
- ❓ "Emitir cobrança" pertence ao drawer, ou só à tela cheia?
- ❓ "Apresentar" (fullscreen) e "Transcript" (A4) são do drawer, ou de outro lugar?
- ❓ A aba `✦ IA` do protótipo entra nesta onda, ou fica pra depois?

---

## Divergência medida: protótipo × produção (2026-07-28)

| # | Achado | Evidência |
|---|---|---|
| D-1 | **7 seções empilhadas** numa coluna, sem abas | `grep -c '<Section title='` → 7: Cliente · Produtos · Resumo de valores · Mensagem WhatsApp · Observações · Pipeline FSM · Histórico |
| D-2 | **Ação principal do WhatsApp fora da dobra** | `SaleMessagePreview.tsx` põe `vd-msg-actions` (Copiar / Abrir no WhatsApp) DEPOIS do balão; o recorte de [W] corta na linha `variáveis:` |
| D-3 | **Dinheiro em dois lugares sem relação** | `+ Adicionar` (`POST /sells/{id}/quick-payment`) no bloco PAGAMENTOS × `Emitir cobrança` (`POST /sells/{id}/emitir-cobranca`) no rodapé |
| D-4 | **"PAGAMENTOS (0)" pode mentir** | cobrança paga → `OnCobrancaPagaCreateFinanceiroTitulo` cria `fin_titulos`+`fin_titulo_baixas` e **não** toca `transaction_payments`. Varredura: 0 arquivos de `Modules/PaymentGateway` referenciam `transaction_payments`. Elo em aberto = `US-PG-008` |
| D-5 | **7 ações no rodapé, 3 pesos visuais, sem agrupamento** | `variant="outline"` ×5 (Transcript · Apresentar · Imprimir · Ver tela · Devolução) + `Editar` primary + `Emitir cobrança` destacado. Mistura documento, navegação, dinheiro e estoque |
| D-6 | **Nunca teve charter** | este arquivo é o primeiro. Causa-raiz de D-1..D-5: não havia lei pra dizer não |

**Risco fora do visual (herdado de D-4):** `+ Adicionar` cria `TransactionPayment` → dispara
`TransactionPaymentObserver` → cria `TituloBaixa`. A cobrança paga cria a baixa por outro
caminho, com **chave de idempotência diferente** (`cobranca_id` × `TransactionPayment`). Registrar
à mão um boleto que o gateway já baixou lança o mesmo dinheiro **duas vezes** no Financeiro.
Não é bug de UI — mas é a UI que convida ao erro.

---

## UX Targets

- Abrir o drawer não recarrega a lista
- 1280px (Larissa): o drawer cabe sem scroll horizontal
- Cada assunto resolvido sem rolagem para achar sua ação
- Tipografia e cor pelos tokens do DS ([UI-0013](../../../../memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)) — sem cor crua

---

## UX Anti-patterns — ⚠️ PENDENTE [W]

> Mesma regra dos Non-Goals: só [W] preenche. Os candidatos vêm da divergência medida acima,
> **não** de opinião do agente:
>
> - ❓ "ação de um assunto fora do bloco do assunto" vira `❌` (mataria D-3)?
> - ❓ "ação primária abaixo da dobra" vira `❌` (mataria D-2)?
> - ❓ "contador que ignora fonte de dado paralela" vira `❌` (mataria D-4)?

---

## Endpoints alimentadores

| Método | Rota | Papel |
|---|---|---|
| GET | `/sells/{id}/sheet-data` | payload do drawer |
| POST | `/sells/{id}/quick-payment` | registra dinheiro **recebido** |
| POST | `/sells/{id}/emitir-cobranca` | **pede** o dinheiro (boleto/PIX via gateway) |
| GET | `/api/sells/{id}/fsm-actions` · POST `/sells/{id}/fsm-action` | pipeline ([ADR 0143](../../../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)) |
| GET | `/sells/{id}` | `Ver tela →` (full-page, [Show.charter.md](../Show.charter.md)) |

---

## Tests anti-regressão

- Nenhum hoje. `tests/Feature/Sells/SaleSheetComponentTest.php` existe e é **estrutural**
  (casa string no `.tsx`) — não prova comportamento.
- A tela cheia irmã já tem contrato: [Show.casos.md](../Show.casos.md) (`UC-VSHOW-01..07`).
- Quando esta lei for ratificada, o contrato do drawer nasce do mesmo jeito: UC derivado
  daqui + Pest citando o UC ([ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md) G-2).

---

## Refs

- Protótipo: `prototipo-ui/cowork/vendas-page.jsx::VendaDetailDrawer`
- Irmã full-page: [Show.charter.md](../Show.charter.md) · [Show.casos.md](../Show.casos.md)
- [UI-0013 Constituição UI v2](../../../../memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) · [ADR 0149](../../../../memory/decisions/0149-mwart-screen-pattern-reuse-cowork.md) · [ADR 0192](../../../../memory/decisions/0192-integracao-vendas-oficina.md)
- Elo em aberto do D-4: `US-PG-008` (Linkage `cobranca_id` no webhook genérico)

## Trilha do tempo

- 2026-07-28 · [CC] criado a pedido de [W] (*"até a interface está errada. confusa."*). Descreve
  o ALVO (protótipo Cowork) e mede 6 divergências vs produção. Non-Goals e Anti-patterns ficam
  como PERGUNTAS — a skill `charter-write` proíbe o agente de inferi-los. Nenhum `.tsx` tocado.
