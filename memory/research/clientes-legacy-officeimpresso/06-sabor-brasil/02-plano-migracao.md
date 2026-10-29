---
slug: 06-sabor-brasil-plano
cliente: Cliente_E360F9
business_id_oimpresso: 199
date: 2026-10-29
controlador: Wagner
---

# Plano de migração — Sabor Brasil (`Cliente_E360F9` → `business_id = 199`)

> Etapas do início à conclusão. Segue o [pattern canônico de migração](../../../reference/migracao-officeimpresso-pattern.md) (importers em `scripts/legacy-migration/`, idempotência por `legacy_id`, audit JSON, dry-run antes de aplicar). Este documento **não repete** o pattern: diz o que é próprio deste cliente e o que fecha cada etapa.
>
> **Estado vivo não mora aqui** (tasks não vão em markdown, [ADR 0070](../../../decisions/0070-jira-style-task-management-current-md-removed.md)). Cada etapa fecha com um **recibo** (PR, audit JSON, run de teste, screenshot) — é ele que prova a conclusão, não um ✅ escrito à mão.
>
> Perfil e números de origem: [01-perfil.md](01-perfil.md).

## Visão geral

| # | Etapa | Dono | Fecha quando |
|---|---|---|---|
| 0 | Preparação técnica | Claude + [W] | adapter lê a versão 1514 sem erro e a empresa 199 está configurada para dois CNPJs |
| 1 | Casos de uso | [M] lista · Claude confere | toda linha da lista tem veredito ✅/🟡/❌ com prova |
| 2 | Construir o que falta | Claude · merge [W] | todo caso ❌/🟡 que trava a virada tem PR mergeado e teste verde |
| 3 | Configurar a empresa 199 | Claude · [M] confere | loja(s), regras fiscais, contas bancárias e representantes conferidos na tela |
| 4 | Migrar os dados | Claude · aprovação [W] por fase | todas as fases aplicadas com audit JSON e contagem origem = destino |
| 5 | Conferência dos números | Claude · [W] | dupla prova de totais por ano (Delphi × oimpresso) apresentada e aceita |
| 6 | Homologação com o cliente | [M] + Ricardo | Ricardo emite NF-e e boleto de teste e opera uma semana em paralelo |
| 7 | Virada | [W] + [M] | Delphi para de emitir; delta final migrado; primeira nota real autorizada |
| 8 | Acompanhamento e encerramento | [M] | 30 dias sem incidente aberto; perfil vai para `status: migrado` |

## Etapa 0 — Preparação técnica

1. **Adapter da versão 1514.** O intervalo conhecido vai até 1474. Rodar a introspecção de schema (`RDB$RELATION_FIELDS`) em `VENDA`, `VENDA_PRODUTO`, `FINANCEIRO`, `PESSOAS`, `PRODUTO`, `BOLETOS`, `COMISSAO_FINANCEIRO` e comparar com o que os importers esperam. Saída: diferenças registradas em `03-schema-v1514.md` nesta pasta + ajustes no `lib/firebird_reader.py`. (O agente cita um `firebird-schema-drift-by-version.md` em `memory/reference/`, mas ele **não existe** no repo em 2026-10-29.)
2. **Dois CNPJs, dois regimes.** Empresa 4 é Regime Normal, empresa 5 é Simples Nacional, e as duas vendem. **Decisão [W] 2026-10-29: uma empresa (199) com dois locais emissores**, não duas empresas. Consequência medida no código: o local emissor (`nfe_locais_emissores`) já tem CNPJ, IE e série próprios, mas o regime é um só por empresa (`nfe_business_configs.regime`, lido em 7 arquivos do NfeBrasil). Falta o **regime por local** — caso P-155 em `Fiscal/Config.casos.md`, que entra na Etapa 2 como trava da virada. **Bloqueia as Etapas 3 e 4** até ele estar pronto.
3. **Segredos.** Certificado A1, senhas de SEFAZ e credenciais de banco (Inter, BB) **não saem do Delphi pelo importer** (`EMPRESA_SECRET_FIELDS`). Ricardo/[W] cadastram direto no oimpresso; cópia em Vaultwarden.
4. **Congelar a foto de origem.** Registrar as contagens do [perfil §3](01-perfil.md) com data — é a régua da Etapa 5.

## Etapa 1 — Casos de uso

- **Fonte:** a Maiara lista, um caso por linha: *quem faz · o que faz · como faz hoje no Delphi · o que tem de sair · se trava a virada*.
- **Conferência:** para cada caso, Claude acha a tela no oimpresso e testa — produção só leitura na empresa de teste (biz=1), fluxo que grava (venda, NF-e, boleto) no **staging**. Veredito ✅/🟡/❌ com screenshot.
- **Registro:** caso 🟡/❌ entra como pedido pelo `npm run casos:novo -- --caso <caso.json>`, com `Clientes: Sabor Brasil`, no `.casos.md` da tela certa. Assim aparece na página "Casos de uso por tela". Caso que já existe como `[BACKLOG]` (ex.: duplicar venda) **ganha o cliente**, não vira pedido repetido.
- **Ponto de partida** (o que o banco mostra que ele usa — a lista da Maiara completa e corrige):

| Área | Uso medido no Delphi | O oimpresso hoje (pelo código, **não testado em produção**) |
|---|---|---|
| ICMS-ST com MVA, CST 10/20/60 e CSOSN 500 | 215 itens com ST, MVA 62,3% | 🟡 cálculo no XML existe (`NfeIcmsStNoXmlTest`); ST no total da venda em andamento (US-SELL-078, `Sells/Create.casos.md`) |
| Regras tributárias por regime/UF/CFOP | 22 regras | 🟡 `nfe_fiscal_rules` existe; falta carregar as 22 e conferir CST 20 (redução de base) |
| Duas empresas, dois regimes | 4 = Normal, 5 = Simples | 🟡 decidido: uma empresa com dois locais; falta o regime por local (P-155) |
| Bonificação (CFOP 5910) e "venda sem ST" | 8 + 184 vendas desde 2024 | ❓ a conferir como natureza de operação |
| Tabela de preço por cliente | 74 tabelas, 192 clientes | ✅ grupo de preço por cliente (`selling_price_groups` → `customer_groups` → `contacts`); importer `import-tabelas-preco.py` existe. ⚠️ ele grava só o **valor**; % de desconto/acréscimo por item (453/213 linhas) não é importado |
| Comissão por cliente, paga por parcela recebida | 1.643 lançamentos, 2%–15% | ❌ o oimpresso tem % por **vendedor** (`users.cmmsn_percent`), não por cliente, e calcula pela venda, não pelo recebimento |
| Boleto Inter e BB | Inter desde 2023, BB em 2026 | 🟡 Inter por API (`InterDriver`); BB só por CNAB (`BBCnabDriver`), sem API |
| Desconto no boleto pago até o vencimento | 52 clientes com 3/5/7% | ❌ o campo existe no cliente (`contacts.boleto_desconto_pontualidade_pct`) mas **ninguém o lê**; `InterDriver` não manda desconto |
| Juros e multa no boleto | por boleto | a conferir no `InterDriver` |
| Manifesto do destinatário / importação de XML de entrada | 453 notas (até 2023) | ✅ `ManifestacaoController` + `PuxarDfesRecebidosCommand`; entrada por XML em `ImportarXmlEntradaService` |
| Duplicar venda | 106 usos, último 2026-03 | 🟡 rota antiga `/sells/duplicate/{id}` existe; tela nova não tem o botão (`[BACKLOG]` em `Sells/Index.casos.md`) |
| Contas a receber por boleto | 188 títulos em 12 meses | ✅ Financeiro; pergunta aberta: por que só 18 baixados no Delphi |

## Etapa 2 — Construir o que falta

- Só entra o que a Etapa 1 marcou como **trava a virada**. O resto fica como pedido registrado.
- Um PR por caso, ≤300 linhas, teste citando o UC, lane MySQL real.
- **Valor e estoque** (ICMS-ST, desconto de boleto, comissão, tabela de preço) seguem a regra mestre: dupla prova do cálculo + tabela antes→depois + aprovação [W] antes do merge ([proibicoes.md](../../../proibicoes.md) §Cálculo de valor).
- Fecha quando: todo caso que trava está mergeado, verde no CI e com smoke em produção.

## Etapa 3 — Configurar a empresa 199

- Duas lojas na empresa 199, cada uma local emissor com o próprio CNPJ, IE, série e regime (Etapa 0.2); certificado A1 de cada CNPJ.
- As 22 regras tributárias, conferidas contra os CST/CFOP mais usados (perfil §3).
- Contas bancárias Inter (API) e BB; carteira, juros, multa, desconto.
- Usuários (4 no Delphi) com permissões; representantes como vendedores com o % certo.
- Fecha quando: [M] confere cada item na tela, com screenshot.

## Etapa 4 — Migrar os dados

Ordem prática do pattern, sem a fase de veículos (não é oficina), com dry-run e aprovação [W] em cada fase antes de aplicar:

| Ordem | Fase | Origem (contagem 2026-10-29) | Importer | Observação deste cliente |
|---|---|---|---|---|
| 1 | Empresas | `EMPRESA` 6 | `import-empresas.py` | só as que emitem (4 e 5) viram entidade própria |
| 2 | Produtos | `PRODUTO` 141 | `import-produtos.py` | conferir NCM/CEST de cada item — base do ST |
| 3 | Clientes e fornecedores | `PESSOAS` 434 | `import-contacts-*.py` | levar `boleto_desconto_pontualidade_pct` e o % de comissão |
| 4 | Tabelas de preço | 74 tabelas · 707 preços · 192 vínculos | `import-tabelas-preco.py` | falta % desconto/acréscimo por item (Etapa 1) |
| 5 | Vendas | `VENDA` 4.089 · `VENDA_PRODUTO` 17.014 | `import-vendas.py` + `import-venda-itens.py` | cancelado/excluído (`INATIVO …`) vence pagamento; conferir que os itens vieram (o Martinho perdeu 92%) |
| 6 | NF-e emitidas | `NOTA_FISCAL` 2.745 | `import-notas-fiscais.py` | histórico, sem reenvio à SEFAZ |
| 7 | Financeiro | `FINANCEIRO` 3.226 | `import-financeiro.py` | cleanup-first: título vencido há mais de 365 dias, sem boleto e sem movimento fica fora — decisão [W] |
| 8 | Estoque e compras | `PRODUTO_MOVIMENTO` 16.467 · `NF_ENTRADA` 23 | `import-estoque.py` + `import-compras.py` | saldo final por produto é o que importa |

- Antes de cada fase: contagem do destino em produção (detecta importação anterior) — pattern §4.
- Fecha quando: as 8 fases têm audit JSON em `scripts/legacy-migration/output/` e contagem origem = destino (diferença explicada linha a linha).

## Etapa 5 — Conferência dos números

- Por ano, desde 2024: quantidade de vendas, total vendido, total de ST, a receber em aberto, comissão em aberto por representante — no Delphi e no oimpresso, por **dois caminhos** (consulta direta + relatório da tela).
- Saldo de estoque dos 141 produtos.
- Apresentar ao [W] a tabela lado a lado; diferença só fecha com explicação.

## Etapa 6 — Homologação com o cliente

- Ricardo emite em **homologação SEFAZ** uma venda de cada tipo usado (venda com ST, venda sem ST, bonificação, devolução) nos dois CNPJs.
- Gera boleto de teste no Inter e no BB com desconto até o vencimento e confere o PDF.
- Uma semana operando em paralelo (Delphi segue valendo).
- Fecha quando: Ricardo dá o ok por escrito para a [M].

## Etapa 7 — Virada

- Data combinada com aviso ao cliente. Na véspera: delta das vendas e títulos desde a migração da Etapa 4.
- Delphi para de emitir NF-e e boleto (fica só consulta).
- Primeira NF-e real autorizada e primeiro boleto real registrado no banco, com recibo.

## Etapa 8 — Acompanhamento e encerramento

- 7 dias de acompanhamento próximo + 30 dias sem incidente aberto.
- Retorno de boleto (baixa automática) conferido no primeiro mês.
- Fecha quando: perfil vai para `status: migrado`, com a data e o link do último recibo.

## Casos de uso: o que falta (medido em 2026-10-29)

Fonte: os pedidos com `Clientes: Sabor Brasil` nos `.casos.md` do `main` em `15432d80c0` e os PRs abertos. Um pedido só conta como provado com teste verde no CI. A lista da Maiara (Etapa 1) ainda não chegou e pode acrescentar linhas. A coluna "Trava a virada?" é uma proposta minha para ela confirmar.

**Entregues hoje, aguardando o CI do `main`:**

| Pedido | O quê | PR |
|---|---|---|
| P-153 | ICMS-ST dentro do estado para contribuinte | [#10162](https://github.com/wagnerra23/oimpresso.com/pull/10162) |
| P-155 | dois CNPJs, regime por local emissor | [#10161](https://github.com/wagnerra23/oimpresso.com/pull/10161) |
| P-157 | boleto BB por CNAB (remessa, PDF, retorno) | [#10168](https://github.com/wagnerra23/oimpresso.com/pull/10168) |
| P-086 | desconto de pontualidade no boleto e na baixa | [#10166](https://github.com/wagnerra23/oimpresso.com/pull/10166) |
| P-035 | duplicar guarda a venda de origem (UC-SDUP-01..06) | [#10164](https://github.com/wagnerra23/oimpresso.com/pull/10164), [#10175](https://github.com/wagnerra23/oimpresso.com/pull/10175) |

O campo "Hoje" de P-153 e P-086 nos `.casos.md` ainda diz "em parte" ou "não tem". Ele precisa ser atualizado junto com o teste verde.

**Em andamento (PR aberto, testes ainda não rodaram):**

| Pedido | Caso de uso | PR |
|---|---|---|
| P-154 bonificação, venda | UC-SBON-01 | [#10177](https://github.com/wagnerra23/oimpresso.com/pull/10177) |
| P-154 bonificação, Financeiro | UC-FBON-01..05 | [#10176](https://github.com/wagnerra23/oimpresso.com/pull/10176) |
| P-154 bonificação, NF-e | UC-FNFE-57, UC-FNFE-58 | [#10178](https://github.com/wagnerra23/oimpresso.com/pull/10178) |

**Faltam:**

| # | Pedido / área | O que falta | Trava a virada? | Depende de |
|---|---|---|---|---|
| 1 | P-156 comissão por cliente | % no cliente, liberada quando a parcela é paga (1.643 lançamentos no Delphi) | sim | decisão [W]: momento da comissão (pergunta 5) |
| 2 | Bonificação e venda "sem ST" | o Delphi tem os tipos "BONIFICAÇÃO SEM ST" e venda sem ST (184 vendas sem ST desde 2024); o P-154 não trata nenhum dos dois | sim | conferir com o contador como sai a nota |
| 3 | 22 regras tributárias | carregar as 22 e conferir CST 20 (redução de base) nos dois regimes | sim | Etapa 3 |
| 4 | Juros e multa no boleto | conferir no Inter (API) e no BB (CNAB) | sim | — |
| 5 | P-087 tabela de preço do cliente | hoje funciona pelo grupo do cliente; o cliente não tem tabela própria | não (o grupo cobre as 74 tabelas) | Maiara confirmar |
| 6 | P-026 condição do cliente preenche a venda | a venda usa a regra da empresa | não | Maiara confirmar |
| 7 | P-025 sinal e prazo por cliente | o sinal mínimo é um só para a empresa | não | Maiara confirmar |
| 8 | P-035 resto | o topo do rascunho mostrar a origem; decisão D-05 | não | decisão [W] D-05 |
| 9 | P-031 / P-037 orçamento duplicado | a lista de orçamentos mostrar de onde veio; a cópia do orçamento guardar a origem | não | — |
| 10 | P-032 / P-038 devolver tudo | a devolução parcial existe; falta "devolver tudo" | não | — |
| 11 | P-039 segunda devolução | medir se a segunda devolução desconta a primeira | não (medir antes) | — |
| 12 | Manifesto de NF-e de entrada | o último é de 2023 | a decidir | pergunta 3 |
| 13 | Contas a receber | 188 boletos e só 18 baixados no Delphi | não trava o código; trava a fase 7 da migração | pergunta 2 |

## Cronograma (proposta de 2026-10-29)

As datas valem se o CT 100 voltar ao ar e as dependências da tabela acima chegarem no prazo. Tarefas de código seguem o fator de [ADR 0106](../../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md). Homologação e acompanhamento seguem o relógio real.

| Quando | Etapa | O quê | Dono |
|---|---|---|---|
| 29–30/10 | 2 | P-154: CI verde, [W] vê o impacto antes→depois, merge dos 3 PRs | Claude · [W] |
| 30/10 | 0 | ~~adapter 1514~~ feito ([schema](03-schema-v1514.md)): os 10 importadores rodam em dry-run | — |
| até 03/11 | 1 | lista de casos da Maiara + respostas das perguntas 2, 3 e 5 | [M] · Ricardo · [W] |
| 03–05/11 | 2 | itens 1 a 4 da tabela (os que travam), um PR por item | Claude · merge [W] |
| 05–06/11 | 3 | empresa 199: dois locais emissores, 22 regras, contas Inter e BB, usuários | Claude · [M] confere |
| 06–11/11 | 4 | 8 fases de dados, dry-run e aprovação [W] em cada | Claude · [W] |
| 12/11 | 5 | conferência dos números por ano (dupla prova) | Claude · [W] |
| 13–20/11 | 6 | homologação: Ricardo emite e opera uma semana em paralelo | [M] · Ricardo |
| 23/11 | 7 | virada | [W] · [M] |
| até 23/12 | 8 | 30 dias sem incidente; perfil vira `status: migrado` | [M] |

Os itens 5 a 11 (não travam) entram depois da virada, na ordem que a Maiara pedir.

## Perguntas abertas

| # | Pergunta | Para quem | Bloqueia |
|---|---|---|---|
| 1 | ~~Uma empresa com dois locais ou duas empresas no oimpresso?~~ Respondida: uma empresa com dois locais ([W] 2026-10-29) | [W] | — |
| 2 | Por que, em 12 meses, 188 boletos a receber e só 18 baixados no Delphi? A baixa acontece fora do sistema? | [M] / Ricardo | Etapa 4 (financeiro) |
| 3 | O manifesto de NF-e de entrada ainda é usado? O último é de 2023 | [M] / Ricardo | Etapa 1 |
| 4 | O BB em 2026 é a conta nova ou um teste? Inter continua? | Ricardo | Etapa 3 |
| 5 | Comissão: sempre pela parcela recebida, ou alguma pelo faturamento? | [M] / Ricardo | Etapa 2 |
