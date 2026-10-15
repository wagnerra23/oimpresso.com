# Tela 04 · Orçamento rápido (P4) ✅ — regra mestre

> ✅ **Rota existe no ERP** (PR 16 do playbook `propostas-app`). Parte do contrato da API do app ([API-CONTRATO-v1.md](../API-CONTRATO-v1.md): regras gerais §0, Início §6). Origem: handoff `propostas-app` do Design, conferido em 2026-10-05.
>
> ⚠️ **Regra mestre:** o PR que criou estas rotas só entra com a dupla prova, a tabela antes → depois e o ok do [W]. O ajuste `app_orcamento_rapido` nasce `false`.

## As mesmas funções da web
- Web (sessão): `POST /comunicacao-visual/api/calcular` e `POST /comunicacao-visual/api/orcamentos`, no `OrcamentoController`.
- App (token): as rotas abaixo, no `Api\App\OrcamentosController`.
- Os dois usam o **mesmo** cálculo (`OrcamentoCalculator`) e a **mesma** gravação (`OrcamentoGravador`). O preço vem de `comvis_materiais.preco_venda_m2`.
- Fórmula: `area_m2 = largura × altura × quantidade` (3 casas) · `total do item = area_m2 × preço/m²` (2 casas) · total = soma dos itens.

## Rotas
Arquivo `routes/api/app/orcamentos.php` (grupo `auth:api`, prefixo `app`).

### `GET /api/app/orcamentos/materiais`
→ `200 { "itens": [{ "id": 12, "nome": "Lona 440g", "categoria": "lona", "unidade": "m2", "preco_m2": 65.0 }] }`

Só os materiais **ativos** do business do token, por categoria e nome.

### `POST /api/app/orcamentos/calcular` (throttle 60/min)
```json
{ "material_id": 12, "largura_m": "3.00", "altura_m": "1.20", "quantidade": "1" }
```
→ `200 { "area_m2": 3.6, "preco_m2": 65.0, "total": 234.0 }`. Não grava nada.

### `POST /api/app/orcamentos` (throttle 20/min, header `Idempotency-Key` obrigatório)
```json
{ "cliente_id": 1, "itens": [ { "material_id": 12, "largura_m": "3.00", "altura_m": "1.20", "quantidade": "1", "total_previsto": "234.00" } ] }
```
→ `201 { "id": 87, "numero": "ORC-2026-00042", "status": "rascunho", "total": 234.0 }`

- `cliente_id` é opcional (`null` = sem cliente). Precisa ser cliente (`customer` ou `both`) do business do token.
- De 1 a 50 itens. Grava como `rascunho`, com o usuário do token como vendedor e a data de hoje. Enviar ao cliente é outra tarefa (P5).
- O número segue a **mesma** sequência por business da web: `ORC-<ano>-<5 dígitos>`.

## Números como texto
Nada passa por `Util::num_uf` (incidente de 2026-06-05). Número enviado como JSON number é recusado (422).

| Campo | Formato |
|---|---|
| `largura_m`, `altura_m` | `"N.NNN"`: ponto, até 3 casas, maior que zero |
| `quantidade` | `"N"`: inteiro, pelo menos 1 |
| `total_previsto` | `"N.NN"`: ponto, até 2 casas |

## Dupla prova
O ERP recalcula cada item. Se `total_previsto` divergir do calculado (comparação em centavos), responde
`422 { "erro": "validacao", "campos": { "itens.0.total_previsto": "O total mudou: o ERP calculou 234.00. Confira e envie de novo." } }`
e **nada é gravado**, nem a reserva da `Idempotency-Key`. A chave do campo é literal, com os pontos.

## Idempotência
Tabela `app_idempotencia`, rota `orcamentos`, reservada na mesma transação da gravação (como a venda, §2.2).
- Mesma chave e mesmo corpo → `200` com a mesma resposta do `201`, sem gravar de novo.
- Mesma chave e outro corpo → `422 idempotencia_conflito`.
- Gravação ainda em andamento → `409 em_andamento`.

## Erros
| Código | Quando |
|---|---|
| `403 sem_permissao` | sem o módulo Comunicação Visual no pacote; sem `comvis.orcamento.view` nem `create` (materiais e calcular); sem `comvis.orcamento.create` (gravar); ajuste `app_orcamento_rapido` desligado (gravar) |
| `422 validacao` | número fora do formato, medida ≤ 0, material ou cliente de outro business, material sem preço, total divergente |
| `422 idempotencia_conflito` | chave já usada com outro corpo |
| `409 em_andamento` | a mesma chave ainda está gravando |
| `503 sem_configuracao` | business sem material ativo |
| `429` | throttle |

## Tier 0
`business_id` sai do usuário do token e entra explícito em toda consulta. Os global scopes de `Material` e `Orcamento` leem a sessão e não filtram nada sem ela; por isso o preço do material é resolvido no controller, com `business_id`, e entra no cálculo como preço do item. Material ou cliente de outra empresa responde 422 igual a "não encontrado", sem dizer que existe.

## App
O app **nunca** soma sozinho. Mostra "calculando no ERP…" até chegar a resposta de `/calcular`, com debounce de 500 ms, e envia em `total_previsto` o total que mostrou.

## Ajuste da empresa
`app_orcamento_rapido` (padrão `false`, lido por `AjustesDaEmpresa::valor`). Liga o "+ Novo" no app. Com `false`, `POST /orcamentos` → `403 sem_permissao`; materiais e calcular continuam respondendo (não gravam). Só pode ir para `true` depois do ok do [W] na regra mestre.

## Teste
`tests/Feature/Sells/AppOrcamentoRapidoContratoTest.php` (UC-APPOR-01..10), lane `PHP / Pest (Sells · MySQL)`.
