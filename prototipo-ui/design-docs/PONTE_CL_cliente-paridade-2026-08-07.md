# PONTE [CL] — Cliente (Index + Drawer 760) · sessão 2026-08-07

> [CC] propõe · [CL] confere no `@main` antes de aplicar. Nada aqui está commitado.
> Fonte lida nesta sessão: **espelho local anexado** (`resources/js/Pages/Cliente/*`), **não** o `main`. Reconferir antes de mexer.
> Build no Cowork: `clientes-page.jsx` · `clientes-page.css` · `cliente-drawer760.jsx` · `cliente-drawer760.css`.

## 1 · Correções de paridade (o protótipo estava divergente do que já existe em produção)

Nenhuma decisão nova — só alinhamento. Se produção já está certa, é só o protótipo que estava errado; conferir e fechar.

| Onde | Estava no protótipo | Canon de produção |
| --- | --- | --- |
| Classificação · papéis | 4 chips | **5** — entrou `is_other` "Outros" (ADR 0246) |
| Classificação · status | Ativo/Inativo | **3** — `active/inactive/blocked` (`contact_status`) |
| Classificação · tags | 5 valores | **9** — `TAG_OPTIONS` |
| Classificação · segmento | 7, com `parceiro` inventado | **6**, rótulos explicativos de `SEGMENTO_OPTIONS` |
| Comercial · pgto | 6, com "Faturado 30d" | **5** — pix/boleto/cartao/dinheiro/transferencia |
| Comercial · campos | 2 caixas de observação | **`obs_comercial`** (uma) + **`mensagem_venda`** (faltava — alerta ao vendedor no PDV) |
| Comercial · tabela preço | lista fixa | FK `customer_groups` + estado "nenhuma tabela cadastrada" |
| Abas cadastrais | 1 frase dizendo que salva | **status por campo** (Salvando/Salvo/erro 422·403·404) + **rollback** com baseline (`previousValuesRef`) |
| Header do drawer | badge fixo "Ativo" | lê o status do cadastro (inclui Bloqueado) |

**Divergência que o protótipo criou e produção não tem** (decidir: adotar ou remover) — aba Contato ganhou **WhatsApp**, **e-mail comercial** e **e-mail do contador**. Hoje não existem no `ContactController`.

## 2 · Index — comportamento que o charter v10 pede e não estava feito

- `KpiStripClickable`: os 5 cards viraram filtro (clique aplica substitutivo, 2º clique desliga, `aria-pressed`).
- 6 dropdowns completos: status 3 valores · tags 9 **multi** · sem-compra **15/30/90/180/365** · **27 UFs** · tipo · saldo.
- Botão **Exportar** no header (CSV com BOM, respeita filtro e ordem da tela).
- Atalhos KB-9.75 Slice A: `J/K` cursor · `↵` abre drawer · `?` cheatsheet (o `/` e o `⌘K` já existiam).
- Skeleton de carregamento (proxy do `Inertia::defer`) + empty-state que separa "sem cadastro" de "sem resultado".
- **Ativar/Desativar cadastro** no ⋮, com aviso e Desfazer. No protótipo persiste em `localStorage`; em produção é `PATCH /cliente/{id}/classificacao { contact_status }`.

## 3 · DECISÃO NOVA — precisa de ADR, não é delta de charter

**Crédito do cliente (saldo a favor).** Definido por [W] 2026-08-07. Substitui o "Add Discount" do legado, que era outra coisa (desconto abate numa venda; crédito é saldo que fica no cadastro).

- **Onde nasce:** nos dois — lança direto do cadastro do cliente **e** o registro cai no Financeiro. Um lançamento só, duas portas de entrada.
- **Consumo:** automático na venda seguinte, **com recusa possível pelo vendedor antes de fechar**.
- **Origens:** entrada / sinal pago · devolução de mercadoria · acordo comercial · bonificação.
- **Validade:** não expira (não há campo de expiração).

Impacto que [CL] precisa avaliar antes de qualquer código:
- modelo: onde vive o crédito (tabela própria de lançamentos vs `opening_balance`/ledger do UPOS) e como se relaciona com `business_id` (Tier 0);
- Sells/PDV: o gancho de "oferecer e permitir recusa" no fechamento;
- Financeiro: o lançamento tem que aparecer lá — **hoje a UI do cliente promete isso e a tela do Financeiro não recebe** (dívida conhecida, item 1 do que falta);
- extrato: o bloco de créditos lista a parcela herdada + os lançamentos + **linha de total** que bate com o cabeçalho (regra de fechamento de conta).

## 4 · O que ainda falta (não foi feito nesta sessão)

1. Lado Financeiro do crédito — a promessa da UI sem o outro lado.
2. Telas irmãs sem equivalente no protótipo: `Import`, `Create`/`Edit` full-page (+ `DadosFiscaisBRSection`), `Ledger` como página (formatos 1/2/3 + PDF/e-mail), `Map`, `VehiclesTab` como aba (hoje é chip com 2 mocks), `RiscoClienteCard`.
3. Ações do `_show/ActionsMenu` ainda ausentes: Pagar, Excluir.
4. Os outros 4 papéis (Fornecedores, Funcionários, Representantes, Todos) não receberam KPI-filtro, atalhos, skeleton, Exportar nem ativar/desativar.
5. Blade legado `resources/views/contact/*` e `ContactController` **não conferidos** — a comparação acima é contra o Cliente Inertia, não contra o legado.

## 5 · Régua de gate

- Nada aqui foi lido do `main` nesta sessão. Antes de aplicar: `github_compare` / leitura direta e conferir cada linha da tabela §1.
- §3 não entra como código antes de ADR ratificada por [W].
- Export Cowork segue a regra: só build em `prototipo-ui/cowork/`; este arquivo é documento de ponte, não vai pra lá.
