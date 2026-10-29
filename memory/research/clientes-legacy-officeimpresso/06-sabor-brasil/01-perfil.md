---
slug: 06-sabor-brasil
hash_id: Cliente_E360F9
status: em-migracao
date_first_analysis: 2026-10-29
date_last_update: 2026-10-29
controlador: Wagner
vertical_real: industria-temperos-atacado-supermercado
size: pequeno
tipo_relacao: cliente-pagante
banco_firebird: D:\DadosClientes\Sabor Brasil\Dados\BANCO.FDB
banco_alias_firebird: servidor-crm:D:\DadosClientes\Sabor Brasil\Dados\BANCO.FDB
alias_hkcu: Camargo
business_id_oimpresso: 199
---

# Perfil — `Cliente_E360F9` (Sabor Brasil · temperos para supermercado)

> **LGPD:** versão commitável. Sem CNPJ, sem contato, sem valores em R$ ([proibicoes.md](../../../proibicoes.md) — só contagens e estrutura). Ver [_LGPD.md](../_LGPD.md).
>
> **Fonte de cada número:** leitura **somente-leitura** do Firebird pelo apelido `Camargo` (`scripts/legacy-migration/lib/firebird_reader.py`, só `SELECT`), em 2026-10-29. Fato datado — o banco segue vivo, re-meça antes de citar.
>
> **Correção de canon:** a [matriz de clientes legacy](../../../reference/matriz-conhecimento-clientes-legacy.md) chuta *"restaurante (Sabor Brasil)?"*. É **venda de tempero para supermercado** ([W] 2026-10-29).

## 1. Identificação

| Campo | Valor |
|---|---|
| Hash ID | `Cliente_E360F9` (`sha1(razão social)[:6]`, regra do [README](../README.md)) |
| Apelido no Delphi (HKCU) | `Camargo` — o nome do registry não bate com a pasta |
| Banco | Firebird 3 no `servidor-crm`, **`VERSAO_BANCO = 1514`**, 399 tabelas |
| Empresa no oimpresso | `business_id = 199`, criada em 2026-03-23 — **vazia**: 1 loja, 1 usuário, 1 cliente, 0 produto, 0 venda (medido em produção, só leitura, 2026-10-29) |
| Uso | 4 usuários no Delphi; última venda em 2026-09-17 → cliente **ativo** |

⚠️ **Versão 1514 está fora do intervalo conhecido do adapter** (1404 → 1474, agente [`migracao-firebird-versoes`](../../../../.claude/agents/migracao-firebird-versoes.md)). O próprio agente manda escalar `VERSAO_BANCO ≥ 1500`: o schema pode ter colunas que nenhum importer conhece. É o primeiro bloqueio técnico (Etapa 0 do plano).

## 2. Tipo de negócio real

Indústria/atacado de temperos que vende **NF-e modelo 55 para supermercado** (pessoa jurídica, quase tudo em SP), com **representantes comissionados**, **tabela de preço por cliente** e **boleto com desconto por pontualidade**. Não é varejo de balcão: não há NFC-e, e o mix é pequeno (141 produtos).

## 3. O que ele usa (medido)

| Área | Prova no banco | O que significa |
|---|---|---|
| **Vendas** | `VENDA` 4.089 no total · 550 desde 2024 (≈ 200/ano) · tipos: venda, venda sem ST, venda simples, bonificação, orçamento, devolução, troca | Volume baixo, operação B2B. Tela mais usada nos últimos 12 meses: `TFrmVenda_Base` (6.854 eventos no log `HISTORICO`) |
| **Duas empresas, dois regimes** | Desde 2024: empresa 4 (**Regime Normal**) 372 vendas · empresa 5 (**Simples Nacional**) 174 vendas | Emite por **dois CNPJs com regimes diferentes**. CST na empresa normal (10/20/60), CSOSN na do Simples (500/400/101) |
| **Tributação** | 22 regras em `REGRA_TRIBUTARIA` (por regime, UF destino, CFOP, CST/CSOSN) · itens desde 2024: CST 10/CFOP 5405 (1.754), CSOSN 500/5405 (1.771), CST 20/5102 (775) · ICMS-ST calculado em 215 itens com **MVA 62,3% e alíquota 18%** · CFOP 5910 (bonificação) | ICMS-ST com MVA, redução de base (CST 20), ST já retido (500/60). Sem IPI, sem DIFAL/FCP (cliente é quase todo intraestadual) |
| **Tabela de preço por cliente** | 74 tabelas ativas · 707 preços (706 com valor fixo, 453 com % desconto, 213 com % acréscimo) · **192 clientes** ligados a **61 tabelas** | Cada rede de supermercado tem a sua tabela |
| **Comissão** | 1.643 lançamentos em `COMISSAO_FINANCEIRO` (por **parcela**: aberta / recebida / vencida) · % por cliente em `PESSOAS.COMISSAO_REPRESENTANTE` (2% a 15%, maioria 10%) · 3 representantes ativos desde 2024 · fechamento em `COMISSAO` (último em 2022-09) | Comissão é **por cliente** e paga **conforme o cliente paga a parcela** |
| **Boleto** | 1.650 boletos · banco por ano: Caixa (104) 2017–18, Santander (033) 2019–23, **Inter (077) desde 2023**, **Banco do Brasil (001) em 2026** · desconto em 606 boletos · % padrão por cliente (3%, 5%, 7% — 52 clientes) · juros e multa por boleto | Hoje imprime boleto **Inter e BB**. Desconto vale **até o vencimento** (`DIADESCONTO = 0`) |
| **Financeiro** | `FINANCEIRO` 3.226 · 12 meses: 188 a receber por boleto, 18 recebidas · a pagar pouco usado (121 abertas no total) · nada conciliado · plano de contas presente (`CODPLANOCONTAS`) | Uso concentrado em **contas a receber por boleto**. A diferença 188 × 18 precisa de explicação (baixa fora do sistema?) — pergunta para a Maiara |
| **Importação fiscal** | `NF_ENTRADA_MANIFESTO` 453 notas (2019–2023), com ciência e confirmação · 20 XMLs guardados (2023) · `NF_ENTRADA` só 23 | Usa **manifesto do destinatário** (baixa as NF-e emitidas contra ele na SEFAZ). Lança pouca entrada no sistema. Uso parou em 2023 — confirmar se ainda importa |
| **Duplicar venda** | 106 edições com "duplic" no log de `TFrmVenda_Base`, a mais recente em 2026-03-06 | Usa a cópia de venda (recompra do mesmo pedido) |
| **Cadastro** | `PESSOAS` 434 (265 PJ, 163 tipo "O", 6 PF) · `PRODUTO` 141, sem grade | Base pequena, migração rápida |

## 4. Sinal qualificado ([ADR 0105](../../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md))

- [x] Pedido do [W]: *"vamos colocar em prática a migração do cliente Ricardo (Sabor Brasil)"* (2026-10-29) — pedido do dono é decisão ([ADR 0382](../../../decisions/0382-remove-trava-de-sinal-para-trabalho-dirigido-por-w.md))
- [x] Empresa 199 já criada em produção (2026-03-23)

## 5. Decisor

- **Ricardo** — dono/operador. Contato fora do git.
- Levantamento dos casos de uso: **Maiara [M]**.

## 6. Plano

Etapas do início à conclusão em [02-plano-migracao.md](02-plano-migracao.md).
