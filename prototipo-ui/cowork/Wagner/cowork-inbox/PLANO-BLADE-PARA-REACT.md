# PLANO — descrever as telas Blade (eventos · funções · casos · testes) e reconstruir em React

> **Status:** proposta do Cowork [CC] para [W] ratificar. Fonte lida no `main` neste turno: `prototipo-ui/COWORK-ESTRUTURA-E-TELAS.md`, `FRESCOR-PRODUCAO-vs-PROTOTIPO.md`, `PRE-FLIGHT-TELA.md` + árvore real de `resources/views/**` e `Modules/*/Resources/views/**`.
> **Limite:** eu não escrevo no git. Este doc nasce aqui no Cowork; virar canon = você colar 1× ou Issue/PR (`cowork-inbox`). Nada aqui está commitado.

---

## 0. Ratificado por [W] (2026-08-18)

- **Granularidade:** 1 Blade = 1 tela React (index/create/edit/show ficam separados).
- **Quem descreve:** **eu leio o Blade e proponho a Ficha; você corrige.** Isso muda a E2 — deixa de ser "você escreve" e passa a ser "eu proponho, você valida". A Ficha só vira contrato (E3) depois do seu OK.
- **Testes na Ficha:** Dado/Quando/Então em texto **+** casos de borda e validação por campo **+** permissões por papel. Performance e impressão/PDF ficam de fora do padrão (entram por exceção, ex: recibo, DANFE, espelho).
- **Onde a Ficha mora:** só aqui no Cowork por enquanto — nada de Issue/PR ainda. Quando um lote fechar, a gente decide o que sobe pro `main`.
- **Escopo:** **tudo entra** — Superadmin, Woocommerce/Connector, Cms, Spreadsheet, Officeimpresso (licenças/catálogo), Essentials (todo/memos/KB/mensagens) e os relatórios GST. Fora ficam apenas `install/`, `vendor/`, `emails/`, `myfatoorah/`, `_smoke-probe` e `*_old.blade.php` (código morto).
- **Lote inicial:** não marcado → decidi **L1 Vendas/PDV** (dinheiro entra ali; persona Larissa). Inventário em `INVENTARIO-L1-VENDAS-PDV.md`.

---

## 1. O que estamos fazendo (uma frase)

Congelar o **inventário de telas Blade legadas** (UltimatePOS v6 + Modules), fazer você **descrever cada tela** num formato fixo (eventos, funções, casos de uso, testes), destilar isso em **charter + casos + contrato de tela**, e só então **reconstruir em React** dentro do app único `oimpresso.com.html` — com o [CL] traduzindo pra Inertia real depois.

Regra que não muda: **a descrição é a fonte**. Se a Ficha não diz, o protótipo não inventa (PRE-FLIGHT §Princípio).

---

## 2. Estrutura do plano — 6 etapas, sempre na mesma ordem

| # | Etapa | Quem | Entrega | Gate de saída |
|---|---|---|---|---|
| **E0** | **Pré-flight** da tela | [CC] | pacote de pré-requisitos (arquétipo PT-0X, persona, tokens, componentes DS existentes, erros catalogados) | pacote resolvido — sem pacote, não trabalha |
| **E1** | **Inventário congelado** | [CC] | lista de telas com **ID estável** (`BL-<mod>-<tela>-<ação>`), rota, blade path, controller, lote | você aprova a lista + a ordem dos lotes |
| **E2** | **Ficha de Tela Blade** | [CC] lê o Blade e propõe · **[W] corrige** | 1 `.md` por tela no formato §4 | seu OK explícito · zero `TBD` nos campos obrigatórios |
| **E3** | **Destilação em contrato** | [CC] | `<Tela>.charter.md` + `<Tela>.casos.md` (UC-xx) + `<tela>.contract.json` (seções + copy literal + estados) | charter cobre 100% dos eventos/funções da Ficha |
| **E4** | **Protótipo React** | [CC] | rota nova no `oimpresso.com.html` (`<modulo>-page.jsx` + `app.jsx` + `data.jsx`); variações = **Tweaks**, nunca arquivo novo | render sem erro · zero cor crua · só componentes do DS |
| **E5** | **Crítica + grade** | [CD] / [CA] / [CC] | grade 16-dim, a11y, readiness (trio .tsx+charter+casos) | ✅ pronta pra aplicação |
| **E6** | **Handoff produção** | [CL] | Inertia/React real + testes; frescor volta 🔵 | [W2] aprova screenshot/merge |

**Cadência proposta:** 1 lote = 3–6 telas de um mesmo módulo. E2→E4 do lote inteiro antes de abrir o próximo (fundação compartilhada — sidebar, PageHeader — é PR sequencial isolado, nunca em paralelo).

---

## 3. Ordem dos lotes (por Peso Real, não por ordem alfabética)

| Lote | Módulo / domínio | Por quê primeiro | Telas Blade |
|---|---|---|---|
| **L1** | **Vendas / PDV** (`sell/`, `sale_pos/`, `sales_order/`, `sell_return/`) | dinheiro entra aqui; persona Larissa (balcão, 1280px, atalhos) | ~24 |
| **L2** | **Clientes / Contatos** (`contact/`, `customer_group/`, `Crm/lead`, `Crm/schedule`) | já tem vivo 🔵 à frente — descrever pra fechar paridade, não repintar | ~30 |
| **L3** | **Produtos / Estoque** (`product/`, `variation/`, `unit/`, `stock_adjustment/`, `stock_transfer/`, `opening_stock/`, `import_products/`) | base de tudo; grade tam×cor (gap 🟠 real) | ~28 |
| **L4** | **Compras** (`purchase/`, `purchase_order/`, `purchase_requisition/`, `purchase_return/`) | único gap 🟠 confirmado no FRESCOR (grade matrix órfã) | ~19 |
| **L5** | **Financeiro** (`account/`, `expense/`, `transaction_payment/`, `account_reports/`, `Financeiro/`) | Eliana, tabelas densas; tem histórico de rejeição F3 → ler `LICOES_F3_FINANCEIRO_REJEITADO.md` antes | ~35 |
| **L6** | **Produção / Oficina** (`Manufacturing/production`, `Manufacturing/recipe`, `Repair/job_sheet`, `Repair/repair`, `Repair/status`) | OP + OS; persona Técnico (touch ≥44px) | ~30 |
| **L7** | **Fiscal / Documentos** (`invoice_layout/`, `invoice_scheme/`, `tax_rate/`, `tax_group/`, `NfeBrasil/`, `labels/`, `barcode/`) | trava faturamento; copy legal literal | ~20 |
| **L8** | **RH / Ponto** (`Ponto/*`, `Essentials/attendance`, `payroll`, `leave`, `holiday`) | módulo com vocabulário próprio (marcação/intercorrência/banco de horas) | ~45 |
| **L9** | **Relatórios / BI** (`report/` 27 telas + `Crm/reports` + `Ponto/relatorios`) | arquétipo único repetido 30× → 1 golden resolve o lote | ~32 |
| **L10** | **Admin / Config** (`business/settings`, `role/`, `manage_user/`, `printer/`, `location_settings/`, `Superadmin/*`, `Connector/`, `Woocommerce/`) | baixa frequência, alta contagem — último | ~55 |
| **L11** | **Integrações & extras** (`Superadmin/*`, `Woocommerce/`, `Connector/`, `Cms/`, `Spreadsheet/`, `Officeimpresso/`, `Essentials/todo·memos·KB·mensagens`, relatórios GST) | entram no escopo por decisão [W] — cauda longa, último lote | ~70 |
| **—** | **Fora** (código morto/infra) | `install/`, `vendor/`, `emails/`, `myfatoorah/`, `_smoke-probe`, `*_old.blade.php` | ~25 |

> Total bruto na árvore: **~660 arquivos** sob `resources/views/` (inclui `vendor/` e partials) + **289** telas de nível 2 em `Modules/*`. Depois de tirar partials, receipts, modais-fragmento e o fora-de-escopo, o alvo real fica em **~190 telas**.

---

## 4. A Ficha de Tela Blade — o formato que você preenche (E2)

Um arquivo por tela. Campos marcados **(obrig.)** travam a etapa; se ficarem `TBD` eu paro e pergunto em vez de inventar.

```md
# BL-<mod>-<tela>-<ação>   ex: BL-sell-index-list
- **Rota / URL** (obrig.):            /sells
- **Blade** (obrig.):                 resources/views/sell/index.blade.php
- **Controller@método** (obrig.):     SellController@index
- **Arquétipo** (obrig.):             lista | form | dashboard | kanban | detalhe | relatório | drawer  → PT-0X
- **Persona dona** (obrig.):          Larissa · Wagner · Técnico · Eliana · Iniciante
- **Permissão / gate**:               sell.view, direct_sell.access

## Dados que a tela recebe (obrig.)
| campo | origem (Model/query) | formato | obrigatório |

## Eventos (obrig.) — o que o usuário dispara
| # | gatilho (clique/teclado/change) | o que acontece | endpoint/ajax | resposta na UI |

## Funções (obrig.) — a lógica por trás
| nome | entrada → saída | regra de negócio | onde vive hoje (js/blade/controller) |

## Estados da tela (obrig.)
vazio · carregando · erro · sem permissão · filtrado sem resultado · sucesso

## Casos de uso (obrig.) — UC-01…UC-nn
UC-01 — <ator> quer <objetivo> → passos → resultado esperado

## Testes (obrig.) — Dado / Quando / Então
T-01 — Dado ... Quando ... Então ...

## Validação por campo (obrig.)
| campo | regra | mensagem de erro | caso de borda |

## Permissões por papel (obrig.)
| papel | vê | edita | não pode |

## Saídas
impressão · PDF · export · notificação · integração

## Anti-padrões desta tela (o que NÃO repetir)
```

**Como você me entrega:** cola a Ficha no chat (uma tela por mensagem, ou o lote inteiro). Se você preferir falar solto, eu transcrevo pro formato e devolvo pra você confirmar antes de virar contrato.

---

## 5. Regras de execução (o que me impede de errar)

1. **Toda tela nova = rota no `oimpresso.com.html`**, nunca `.html` novo. Variação/exploração = Tweak no mesmo componente.
2. **Só componentes do DS vivo** (`window.OfficeImpressoPontoWR2DesignSystem_019dd0`) e tokens `var(--*)`. Primary roxo `oklch(0.55 0.15 295)`. Zero hex cru, zero paleta inventada.
3. **Telas 🔵 (produção à frente)** — Atendimento/CaixaUnificada, Cliente/Crm, PageHeader: a Ficha serve pra **documentar**, não pra repintar. A Caixa Unificada é referência de DS, é LEI.
4. **Tela sem charter** → gero draft na E3; não desenvolvo direto do Blade.
5. **Sem memória local.** Cada lote começa lendo o `main` — Ficha e charter moram no repo, não aqui.
6. **Copy PT-BR, sentence case, sem emoji no app**, vocabulário do domínio (marcação, intercorrência, OP, OS, PDV, cálculo por m²).

---

## 6. Como saber que está funcionando (métrica do plano)

- **Cobertura:** telas com Ficha completa / telas no inventário (meta L1–L4 = 100% antes de abrir L5).
- **Fidelidade:** nº de eventos/funções da Ficha ausentes no protótipo = **0**.
- **Retrabalho:** nº de telas que voltam do [CD]/[CA] com nota < corte — se > 1 por lote, o formato da Ficha está frouxo e a gente ajusta o template, não a tela.
- **Prontidão:** `scripts/qa/prototipo-readiness.mjs` ✅ (trio + scorecard) — máquina, não fila manual.

---

## 7. Próximo passo concreto

1. ✅ Inventário L1 publicado — `INVENTARIO-L1-VENDAS-PDV.md` (24 telas + 6 anexos, com rota/blade/controller lidos do `main`).
2. Eu escrevo a **Ficha BL-pos-create** (PDV) lendo `sale_pos/create.blade.php` + `product_row.blade.php` — você corrige.
3. Ficha aprovada → charter + casos + contrato → rota React no `oimpresso.com.html`.
