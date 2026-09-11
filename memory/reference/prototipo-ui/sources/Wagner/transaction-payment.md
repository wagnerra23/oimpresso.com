# Protótipo — Pagamento: Editar + Detalhe (`/payments/v2`)

> **Fonte de design GERADA pelo designer-agente** ([ADR 0282](../../../../../memory/decisions/0282-protocolo-v2-colapso-ratificacao.md) §0.1 — *"o agente gera, ancorado no DS canon"*), não importada do Cowork. Cobre `TransactionPayment/Edit` (PT-02) e `TransactionPayment/Show` (PT-03), duas das 7 telas que tocam valor e não tinham fonte visual.

## 1 · Status — PROPOSTA de forma, não lei

⚠️ As duas telas **já existem em produção** (282 + 214 linhas de `.tsx`) e declaram `related_prototype: n/a (herda PT-02 …)` / `n/a (herda PT-03 …)`. Este protótipo nasceu **depois**.

- **NÃO promover a `related_prototype`** sem decisão [W] — pela cadeia FORMA da [UI-0029](../../../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md) isso tornaria um desenho novo soberano sobre telas vivas que editam **valor de pagamento**.
- O `n/a` dos charters **permanece**; é declaração consciente que a máquina reconhece (`ehDeclaracaoNa`), **não um defeito** ([§5 2026-08-28](../../../../../memory/proibicoes.md)). Nenhum charter foi tocado.
- Enquanto não houver decisão [W], isto é **material de comparação** (um lado do `design-diff`), não alvo de `visual-regression`.

## 2 · Ausência confirmada — os dois donos, medido 2026-09-09

| dono | método | resultado |
|---|---|---|
| repo + espelho | `rg -l --hidden 'TransactionPayment' prototipo-ui/` | **10 arquivos, todos `.md`/docs** — zero em arquivo de design (`.jsx`/`.css`/`.html`) |
| Cowork vivo | `DesignSync.list_files` no projeto de telas **por ID** (`019dcfd3…`; `list_projects` não enxerga projeto regular — §5 2026-08-11) | nenhuma tela de linha de pagamento |

**O drawer mais próximo não serve:** `financeiro-legado.jsx:369` abre o painel sob `sel.k === "titulo"` — ele detalha **título** (`fin_titulos`), objeto diferente de linha de pagamento (`transaction_payments`). Um título agrega; uma linha de pagamento é um evento único com método, data e comprovante.

## 3 · O que foi desenhado

| tela | padrão | conteúdo |
|---|---|---|
| **Editar** | PT-02 Form | forma de pagamento · data · valor · conta · observação · **bloco condicional por método** · trilho com a situação da transação |
| **Detalhe** | PT-03 Detalhe | valor grande · método e data · campos condicionais · observação · comprovante anexo · trilho com contato e saldo |

Estados desenhados: **valor inválido** (≤ 0), **data ausente**, **método sem campos extras**, **sem comprovante anexado**, e o **recibo para impressão** (`.no-print` some com navegação e botões).

## 4 · Âncora do domínio — nada inventado

Nenhum campo veio do `.tsx` vivo (porte reverso é proibido — [§5 2026-06-05](../../../../../memory/proibicoes.md) e 2026-08-28):

| o que | de onde |
|---|---|
| rotas `/payments/v2/{id}/edit` e `/payments/v2/{id}` | `routes/web.php:947-949` |
| props do Editar (`payment_line` · `transaction` · `payment_types` · `accounts`) | `TransactionPaymentController::editInertia` (`:850`) |
| props do Detalhe (`single_payment_line` · `transaction` · `payment_types`) | `TransactionPaymentController::showInertia` (`:886`) |
| métodos `cash · card · cheque · bank_transfer · other` + `custom_pay_N` | `app/Utils/Util.php::payment_types` (`:196`) |
| campos por método (cartão 3 · cheque 1 · transferência 1 · custom `transaction_no`) | `Edit.charter.md` §Campos condicionais |
| validação `amount > 0` · `method` · `paid_on` | `Edit.charter.md` §Validação cliente |
| 3 cards do Detalhe (contato · pagamento · documento) + Imprimir/Voltar + `.no-print` | `Show.charter.md` §UX e §Tier 0 |

⚠️ **Dois desalinhamentos entre charter e código, encontrados ao ancorar** — registrados, não "corrigidos" no desenho:

1. O `Edit.charter.md` cita `custom_pay_1/2/3`; o `Util::payment_types` gera **até `custom_pay_7`**. Desenhei o mecanismo (qualquer `custom_pay_N` pede `transaction_no`) sem fixar a quantidade, que é dado de configuração do business.
2. O Editar exclui `method = 'advance'` (`editInertia` filtra `where('method','!=','advance')`), o que o charter não menciona. Não desenhei "adiantamento" como opção — seria desenhar algo que a rota recusa.

## 5 · Conformidade de token

Zero cor crua (`conformance-gate` = LEI). **13 tokens** do DS, prefixo `.tp-`. Medidos no browser com as 3 folhas do shell:

- **0 dos 13 não resolvem.** Sonda validada por controle **positivo** (`--fg` → `oklch(0.137 0.036 258.5)`) **e negativo** (`--nao-existe` → vazio, logo discrimina).
- Não copiei a paleta do `prototipos/perfil/` — o `SOURCE.md` do `nfe-tributacao` mediu que **8 dos 14 tokens de lá não existem**.

## 5-bis · Validação de runtime — medida, não screenshot

Host estático com as 3 folhas do shell, React 18.3.1 + Babel standalone, viewport **1280**:

| medida | resultado |
|---|---|
| console | **0 erro** |
| tokens não resolvidos | **0** de 13 |
| `scrollWidth > clientWidth` @1280 | **false** (1280 / 1280), nas duas telas |
| condicional · cartão | 3 campos — Nome no cartão · Últimos 4 dígitos · Código da transação |
| condicional · cheque | 1 — Número do cheque |
| condicional · transferência | 1 — Conta de origem |
| condicional · dinheiro | **0 extras** (o bloco some) |
| validação · valor `0,00` | *"O valor precisa ser maior que zero."* · **Salvar desabilitado** |
| validação · valor `1.284,50` | sem erro · Salvar habilitado |
| valor no Detalhe | `R$ 1.284,50` — PT-BR, tabular |
| datas | `08/09/2026` — PT-BR |

⚠️ **O que NÃO foi medido, e por quê.** A regra `@media print { .no-print { display:none !important } }` está **no CSSOM parseado** (condição `print`, 3 regras no bloco, 3 elementos marcados: `NAV.tp-nav`, `DIV.tp-head-r`, `BUTTON.tp-btn`), mas **emulação de impressão não existe neste harness** — então o *efeito* está declarado, não provado. É exatamente a distinção da lápide [§5 2026-08-28](../../../../../memory/proibicoes.md) (declaração passa no CI e é inerte no runtime), e fica dita em vez de escondida.

## 6 · Aviso Tier 0 para quem for aplicar isto no `.tsx`

A tela **Editar grava `amount`**. O campo desenhado usa vírgula decimal PT-BR (`1.284,50`), que é a forma correta na tela — **mas o submit é onde mora o incidente**: em 2026-06-05 o `Util::num_uf` interpretou o ponto de um float locale-ambíguo (`204.99605`) como separador de milhar e gravou uma venda inflada ~×100k em prod (biz=4). A regra-mestre de [proibicoes.md](../../../../../memory/proibicoes.md) exige, para qualquer alteração que toque valor: **prova por dois caminhos independentes + tabela antes→depois + aprovação [W]**.

Este protótipo **não muda cálculo nenhum** — desenha a tela. Mas quem levar o desenho ao `.tsx` está mexendo em VALOR e cai na regra inteira.

## 7 · O que este protótipo NÃO decide

- **Se vira âncora** — decisão [W] (§1).
- **A forma da produção.** Onde diverge do `.tsx` vivo, é *dado para comparar*, não veredito — `design-diff --probe` nos dois lados, nunca leitura no olho (LC-06).
- **O trail de auditoria** que o `Show.charter.md` marca como *"placeholder pro `LogsActivity` — não bloqueante v2"*: não desenhei, porque o charter não decidiu a forma.

## Refs
- [ADR 0282](../../../../../memory/decisions/0282-protocolo-v2-colapso-ratificacao.md) §0.1 — o Code é o designer-agente e **gera**
- [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — o 404 cross-tenant que as duas rotas aplicam
- [ADR UI-0013](../../../../../memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) · [UI-0029](../../../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)
- [INVENTÁRIO 2026-09-09](../../../../../memory/requisitos/_DesignSystem/INVENTARIO-ANCORAS-2026-09-09.md) §5.3 e §5.7
- precedente de forma: [`prototipos/nfe-tributacao/SOURCE.md`](nfe-tributacao.md) ([#7145](https://github.com/wagnerra23/oimpresso.com/pull/7145)) · irmão: [`prototipos/payment-gateway-cnab/`](payment-gateway-cnab.md)
