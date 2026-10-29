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
2. **Dois CNPJs, dois regimes.** Empresa 4 é Regime Normal, empresa 5 é Simples Nacional, e as duas vendem. Decidir com [W]: (a) uma empresa 199 com dois locais emissores (`nfe_locais_emissores`) — só serve se o regime puder ser **por local**; ou (b) duas empresas no oimpresso. **Bloqueia as Etapas 3 e 4.** A conferir no código: `nfe_locais_emissores` herda o regime do business; regime por local não foi encontrado.
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
| Duas empresas, dois regimes | 4 = Normal, 5 = Simples | ❓ ver Etapa 0.2 |
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

- Loja(s) e locais emissores conforme a decisão da Etapa 0.2; certificado A1 e séries.
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

## Perguntas abertas

| # | Pergunta | Para quem | Bloqueia |
|---|---|---|---|
| 1 | Uma empresa com dois locais ou duas empresas no oimpresso? | [W] | Etapas 3 e 4 |
| 2 | Por que, em 12 meses, 188 boletos a receber e só 18 baixados no Delphi? A baixa acontece fora do sistema? | [M] / Ricardo | Etapa 4 (financeiro) |
| 3 | O manifesto de NF-e de entrada ainda é usado? O último é de 2023 | [M] / Ricardo | Etapa 1 |
| 4 | O BB em 2026 é a conta nova ou um teste? Inter continua? | Ricardo | Etapa 3 |
| 5 | Comissão: sempre pela parcela recebida, ou alguma pelo faturamento? | [M] / Ricardo | Etapa 2 |
