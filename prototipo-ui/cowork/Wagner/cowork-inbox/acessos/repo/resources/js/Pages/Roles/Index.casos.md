# Funções e permissões — casos de uso

> Tela: `resources/js/Pages/Roles/{Index,Form}.tsx` (destino da tradução do `/roles` Blade).
> Origem: `cowork-inbox/ACESSOS-F1-2026-08-19.md` \4 · F1 em `prototipo-ui/cowork/acessos/funcoes-page.jsx`.
> Testes: `tests/Feature/Roles/RoleControllerTest.php`.

## Rastreabilidade

| UC | Regra | Teste |
|---|---|---|
| UC-FNC-01 | R1, R2 | caso 1, 2, 3 |
| UC-FNC-02 | autorização | caso 1 |
| UC-FNC-03 | R1 | caso 12 |
| UC-FNC-04 | R3 | caso 4 |
| UC-FNC-05 | R3, R4 | caso 5 |
| UC-FNC-06..14 | A1–A6 | contrato de tela |
| UC-FNC-15 | R8 | caso 8 |
| UC-FNC-16 | R9 | caso 10 |
| UC-FNC-17 | D1 | caso 6 |
| UC-FNC-18 | R6, D2 | caso 7 |
| UC-FNC-19 | R5 | caso 9 |
| UC-FNC-20 | D4 | caso 11 |

## UC-FNC-01 · abrir a lista
**Dado** um usuário com `roles.view` no negócio 1
**Quando** abre a tela de funções
**Então** vê um card por função do negócio 1, com nome **sem** o sufixo `#1`, `Admin` e `Cashier` traduzidos,
e o rodapé de cada card informando quantos usuários e quantos controles ativos.

## UC-FNC-02 · sem permissão
**Dado** um usuário sem `roles.view`
**Quando** tenta abrir a tela
**Então** recebe 403 e nenhuma prop de papel chega ao cliente.

## UC-FNC-03 · função de outro negócio
**Dado** uma função do negócio 2
**Quando** o usuário do negócio 1 tenta abrir ou salvar essa função
**Então** nada é alterado e ela não aparece na lista.

## UC-FNC-04 · função padrão é somente leitura
**Dado** a função **Administrador** (`is_default`)
**Quando** o usuário abre o editor
**Então** nome e controles ficam desabilitados, o botão salvar não age, e a tela explica que acesso total
é por definição — para restringir alguém, crie uma função personalizada.

## UC-FNC-05 · a exceção Cashier
**Dado** a função `Cashier` (padrão, mas editável por regra do controller)
**Quando** o usuário abre o editor
**Então** os controles ficam editáveis **e** a tela avisa: ao salvar, ela deixa de ser a função padrão —
o `is_default` é zerado e não volta.

## UC-FNC-06 · navegar por domínio
**Dado** o editor aberto
**Quando** o usuário percorre o rail
**Então** vê 8 domínios agrupando os 53 grupos do `/roles`, cada grupo com `ativas/total` e um ponto
vermelho quando há permissão de risco ativa.

## UC-FNC-07 · buscar permissão
**Dado** o editor aberto
**Quando** digita no campo de busca
**Então** a busca casa rótulo, chave crua e nome do grupo; cada resultado mostra o grupo de origem e é
editável na própria linha de resultado.

## UC-FNC-08 · escopo em vez de par de checkbox
**Dado** a linha "Enxergar vendas"
**Quando** escolhe **Só os próprios**
**Então** grava `view_own_sell_only` e **remove** `view_all_sells`; escolher **Sem acesso** remove as duas.

## UC-FNC-09 · CRUD numa linha
**Dado** a linha "Cliente"
**Quando** marca **editar**
**Então** **ver** é marcado junto; desmarcar **ver** limpa a linha inteira.

## UC-FNC-10 · liberações do PDV
**Dado** o grupo PDV
**Quando** liga "Aplicar desconto"
**Então** o que é gravado é a **ausência** de `disable_discount`, e a linha declara "grava invertido".

## UC-FNC-11 · seletor exclusivo
**Dado** "Clientes sem venda visíveis" (5 opções no legado)
**Quando** escolhe uma
**Então** só uma permissão é gravada — as outras quatro nunca coexistem.

## UC-FNC-12 · grupo de preço é radio
**Dado** o grupo "Grupos de preço"
**Quando** escolhe ATACADO
**Então** o padrão é **substituído**, nunca somado, e nenhuma permissão de grupo entra duplicada.

## UC-FNC-13 · tudo / nada no grupo
**Dado** um grupo aberto
**Quando** usa "Tudo" ou "Nada"
**Então** a ação vale só para o grupo aberto, e contador do rail e rodapé atualizam na hora.

## UC-FNC-14 · barra de diferenças
**Dado** o editor com alterações
**Quando** o usuário olha o rodapé
**Então** lê `+N −M` desde o padrão, quantas permissões de risco estão ativas e quantos usuários são
afetados; sem alterações, lê "Igual ao padrão da função".

## UC-FNC-15 · salvar
**Dado** o editor alterado
**Quando** salva
**Então** um único POST leva o array de permissões, o que saiu é revogado (`syncPermissions`) e o toast
confirma com a mensagem do domínio.

## UC-FNC-16 · nome duplicado
**Dado** um nome que já existe no negócio
**Quando** salva
**Então** recebe o erro de nome existente, nada é gravado e o editor mantém o que foi digitado.

## UC-FNC-17 · permissão fora do catálogo
**Dado** um POST forjado com `permissions[]` inventado
**Quando** o servidor processa
**Então** responde 422 e **nenhuma** linha nova aparece em `permissions` (hoje ela seria criada — D1).

## UC-FNC-18 · chaves sem tradução
**Dado** os grupos `Fiscal`, `PaymentGateway` e `RecurringBilling`
**Quando** o usuário abre um deles
**Então** vê as chaves cruas em mono com aviso de que falta a lang string — a tela não inventa rótulo.

## UC-FNC-19 · responsável pela venda
**Dado** a linha "Ser responsável pela venda"
**Quando** liga
**Então** grava `roles.is_service_staff` (coluna), não uma permissão.

## UC-FNC-20 · excluir função em uso
**Dado** uma função com usuários
**Quando** tenta excluir
**Então** é bloqueado com a contagem de usuários e a alternativa de reatribuir.
