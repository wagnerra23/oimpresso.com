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
charter_version: 2
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

## Non-Goals — respondidos 2026-07-28

> A skill `charter-write` proíbe o agente de INFERIR Non-Goals (cada um vira Pest GUARD;
> anti-padrão inventado parece canon — `proibicoes` §5 2026-07-16). Então a procedência de
> cada item fica explícita: **[W]** = decisão do dono · **[W→CC]** = [W] delegou dizendo
> *"não sei dizer, quero usabilidade"*, e o agente decidiu com fundamento citado.

- ❌ **Editar a venda dentro do drawer** — `Editar` NAVEGA pra `/sells/{id}/edit`. _[W→CC]_
  Fundamento: a irmã full-page **já** carrega esse Non-Goal (`Show.charter.md` §Non-Goals:
  "❌ Edição inline (vai pra /sells/{id}/edit)"). Drawer editável + tela cheia não-editável =
  dois modelos mentais pra mesma venda. Coerência entre as duas > conveniência de um clique.
- ❌ **Ação de dinheiro fora da aba Pagamento** — `Emitir cobrança` e `+ Adicionar` moram
  JUNTOS. _[W→CC]_ Fundamento: respondem a mesma pergunta do operador ("como esse dinheiro
  entra?"); separá-las É o defeito D-3.
- ❌ **Botão solto pra cada formato de documento** — `Transcript`/`Apresentar`/`Imprimir`
  viram um menu único `Documento ▾`. _[W→CC]_ Fundamento: 3 formas de gerar a mesma coisa
  ocupando 3 slots que competem com ações de dinheiro (D-5).
- ❌ **Esconder a mensagem ao cliente atrás de scroll ou aba** — vai pro header, sempre
  visível. _[W→CC + W]_ [W] decidiu que a Mensagem FICA no drawer; o agente decidiu o LUGAR:
  mandar mensagem é ação frequente, não leitura — pôr numa aba só mudaria o D-2 de lugar.
- ✅ **Aba `✦ IA` entra nesta onda** (não é Non-Goal — é escopo confirmado). _[W]_

### Ainda aberto — [W] marcou *"não tenho certeza ainda"*

Nenhum destes vira GUARD até haver decisão; ficam como pergunta honesta:

- ❓ `Devolução` fica na aba **Itens** (proposta do agente: devolve-se um item) ou vira ação
  de header? [W] confirmou que FICA no drawer; falta o lugar.
- ❓ O drawer deve mostrar cobranças pagas que hoje só existem em `fin_titulos` (D-4)?
  Depende da Onda 0 — ver §Risco.

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

## O alvo — como o drawer fica (decidido 2026-07-28)

```
┌──────────────────────────────────────────────────────────────┐
│ #V-9284   R$ 500,00   [A receber]                            │  ← identidade + dinheiro
│                    [Mensagem] [Documento ▾] [Editar →]  [×]  │  ← 3 ações, não 7
├──────────────────────────────────────────────────────────────┤
│  Itens (1) │ Fiscal │ Pagamento │ Timeline │ ✦ IA            │  ← 5 abas do protótipo
├──────────────────────────────────────────────────────────────┤
│  (conteúdo da aba ativa — cada ação junto do seu assunto)    │
└──────────────────────────────────────────────────────────────┘
```

| Aba | Conteúdo | Ação que vive nela |
|---|---|---|
| **Itens** | linhas da venda + resumo de valores | `Devolução` (lugar ainda ❓) |
| **Fiscal** | NF-e / NFS-e + status | emitir/reenviar documento fiscal |
| **Pagamento** | saldo devedor + recebimentos | `Emitir cobrança` **e** `+ Adicionar` — juntos |
| **Timeline** | histórico + pipeline FSM | ações FSM ([ADR 0143](../../../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)) |
| **✦ IA** | `SaleAiPanel` (`/sells/{id}/ai-ask`) | perguntar sobre a venda |

`Mensagem` (header) abre os 3 templates + `Copiar` / `Abrir no WhatsApp`.

**O que isso mata:** D-1 (7 seções → 5 abas) · D-2 (mensagem sempre visível no header) ·
D-3 (dinheiro reunido em Pagamento) · D-5 (7 ações → 3 no header + contextuais nas abas).
**D-4 não é resolvido por UI** — é a Onda 0 (ver §Risco).

---

## UX Anti-patterns

> Procedência explícita, como nos Non-Goals. Derivam das divergências MEDIDAS, não de gosto.

- ❌ **Ação de um assunto fora do bloco do assunto** _[W→CC]_ — mata D-3. Ex: cobrança no
  rodapé enquanto o pagamento está numa seção.
- ❌ **Ação primária abaixo da dobra** _[W→CC]_ — mata D-2. Se a ação principal de um bloco
  exige rolar pra aparecer, o bloco está errado.
- ❌ **Empilhar seção nova sem aba** _[W→CC]_ — mata D-1/D-6. Assunto novo entra numa das 5
  abas ou vira aba; nunca vira 8ª seção na coluna.
- ❌ **Mais de 1 ação primária visível ao mesmo tempo** _[W→CC]_ — mata D-5.
- ❌ Cor crua (`bg-blue-500` etc.) — token do DS sempre ([UI-0013](../../../../memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md))

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

- 2026-07-28 (v2) · [CC] Non-Goals e Anti-patterns FECHADOS. [W] decidiu: aba ✦ IA entra;
  Devolução e Mensagem ficam no drawer. Onde disse *"não sei dizer, quero usabilidade"*, o
  agente decidiu com fundamento citado e marcou `[W→CC]` — a procedência de cada `❌` fica
  auditável (a skill `charter-write` proíbe INFERIR em silêncio, não proíbe decidir quando o
  dono delega o critério). §O alvo desenha o resultado. 2 pontos seguem ❓ por [W] ter dito
  "não tenho certeza": o lugar da Devolução e o D-4. `.tsx` continua intocado.
- 2026-07-28 (v1) · [CC] criado a pedido de [W] (*"até a interface está errada. confusa."*). Descreve
  o ALVO (protótipo Cowork) e mede 6 divergências vs produção. Non-Goals e Anti-patterns ficam
  como PERGUNTAS — a skill `charter-write` proíbe o agente de inferi-los. Nenhum `.tsx` tocado.
