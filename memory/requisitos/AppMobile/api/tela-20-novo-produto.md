# App — Novo produto (tela 20) — escrita, SEM preço

> Parte do contrato da API do app ([API-CONTRATO-v1.md](../API-CONTRATO-v1.md): regras gerais §0, Início §6).

Decisão [W] 2026-10-02: o app cria produto **sem preço**, como a tela React de criar produto hoje
(`Produto/Create.casos.md`, pendência de contrato com o [F]). A variação nasce com preço zerado e o preço
se acerta na web. Não grava valor; fora da regra mestre.

- `GET /api/app/produtos/opcoes` → `{ categorias:[{id, nome}], unidades:[{id, nome, curta}] }` (do business).
- `POST /api/app/produtos` com
  `{ nome*, codigo|null (vazio = o ERP gera), categoria_id|null, unidade_id*, estoque:{controla, minimo|null},
  prateleira:{rack, fileira, posicao}|null, fiscal:{ncm (8 díg.)|null, cest (7)|null, cfop_interno (4)|null, cfop_externo (4)|null} }`
  → `201 { id, codigo }` · `422 { erro:"validacao", campos }` (chaves aninhadas: `estoque.minimo`, `fiscal.ncm`…) ·
  `403 { erro:"sem_permissao" }` (sem `product.create`).
- Só tipo simples. Categoria e unidade precisam ser do business (a trava UC-PCAD-05 da web).
- O produto fica disponível nas lojas que o usuário pode ver; a prateleira vai para a primeira delas.
- Números com ponto decimal, sem `num_uf` (o parser do incidente de 2026-06-05). `origem` não existe em `products`.
