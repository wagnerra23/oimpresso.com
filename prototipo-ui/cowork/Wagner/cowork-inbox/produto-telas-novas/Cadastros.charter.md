# Product/Cadastros — charter (F1 [CC], 2026-08-21)

**Norte.** São 6 tabelas pequenas que ninguém abre por prazer: você vem aqui porque o cadastro de produto pediu uma unidade que não existe. Então a tela tem que ser **um lugar** (não 6 itens de menu), resolver em segundos e nunca deixar você quebrar o catálogo por engano.

**Origem.** `variation/*`, `selling_price_group/*`, `unit/*`, `taxonomy/*` (type=product), `brand/*`, `warranties/*`. Rota: `prod-cadastros`.

## Regras
- **R1** Uma tela, 6 abas, contador por aba vindo do **estado vivo** — excluir um registro baixa o número na hora.
- **R2** Toda aba tem a mesma gramática: busca (`/` foca) · botão Novo · tabela · modal de criar/editar (↵ salva, esc fecha) · confirmação de exclusão que diz a consequência.
- **R3** Coluna de uso ("Em uso"/"Produtos") é **clicável**: abre o índice de produtos filtrado por aquele valor. Número sem produto não é clicável.
- **R4** Registro em uso **não é excluído**: a confirmação mostra quantos produtos usam e não oferece o botão destrutivo — a recusa é do servidor, dita antes da tentativa.
- **R5** Grupo de preço tem **Desativar** antes de Excluir; desativar preserva os preços digitados e só esconde a coluna.
- **R6** Unidade decimal e múltiplo de base são domínio, não enfeite: `1 cx = 1.000 Un` aparece escrito na linha.
- **R7** Categoria mostra hierarquia na própria linha (`↳ Lonas · em Comunicação visual`); excluir pai avisa que as filhas vão junto.
- **R8** Três estados sempre com motivo: primeira-vez (explica pra que serve o cadastro), busca sem resultado (com limpar busca) e erro (nada foi alterado).
- **R9** Marca com "usar na Oficina" é a mesma marca do catálogo — um cadastro, dois usos, sem tabela paralela.

## Non-goals
- Não é cadastro de imposto nem de local do negócio (Configurações do negócio).
- Não é a taxonomia de despesa nem a de Oficina — está fixa em `type=product` até [W] decidir.
- Não faz merge de duplicata (existe em Análises do catálogo → Duplicatas).

## Achados do legado
- **A1** Os 6 blades usam **modal** com DataTable server-side e cada um tem seu botão Add — o menu Produtos fica com 6 entradas pra 6 tabelas de 4 a 9 linhas. Aqui viram abas.
- **A2** ✅ **resolvido no F1**: as permissões reais do `main` (tree `4235f8df6838`) estão aplicadas na tela com os nomes literais — `unit.{view,create,update,delete}`, `brand.{…}`, `category.{…}` (esta só com `category_type == 'product'`) e `barcode_settings.access`. Papel simulado no Tweak: Administrador · Gerente de catálogo (sem delete) · Balcão (só view) · Sem acesso. Sem `view` a aba mostra `EmptyState variant="no-perm"`; sem `create` o botão Novo é desabilitado com o motivo; sem `delete` o Excluir não existe.
- **A2b** 🔴 **A-P1 — 6 rotas sem permissão nenhuma** no legado: `VariationTemplateController`, `SellingPriceGroupController` (inclui atualizar preço em lote), `WarrantyController`, `LabelsController`, `ImportProductsController` e `ImportOpeningStockController` — só middleware de autenticação (`routes/web.php` 613-615, 711-712, 729-730, 748-753, 838, 443). A tela **não inventa permissão**: mostra um aviso nomeando o controller. Fechar = PR-0 + PR-1 do `PEDIDO-CL-produto-telas-novas.md`.
- **A3** `taxonomy/index` é genérica (`module_category_data`): o mesmo blade serve categoria de produto, de despesa e de módulo. Decisão pendente.
- **A4** `brand/create` tem `use_for_repair` condicionado a `$is_repair_installed` — mantive o campo visível porque a Oficina está instalada no piloto.
