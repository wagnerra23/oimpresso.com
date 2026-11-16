# Handoff 2026-11-16 09:30 BRT — Teste no Chrome do cadastro de produto

**Para:** a sessão local com o Chrome ligado (`claude --chrome` na pasta do projeto).
**De:** a sessão na nuvem que subiu os recursos (PRs #10937, #10939, #10943, #10944, #10947).
**Pedido do [F]:** testar sozinho, sem depender dela, e corrigir o que falhar.

## O que subiu em 16/11 e precisa de teste em produção

Deploy do commit `3015c90aa1` concluído com sucesso (run 43798263466). Os textos novos já estão
no código publicado. Ninguém clicou nas telas logado ainda: o smoke automático pós-deploy não
conseguiu entrar (a conta de teste do smoke falha no login, ver handoff 2026-11-15 21:30).

## Antes de começar

- Confira a empresa logada. Pode testar na **WR2 (biz=1)**. Se for a **ROTA LIVRE (biz=4)**,
  pare: é cliente real e não entra em teste.
- O aviso ao sair abre uma janela do navegador e pode travar a extensão. Se travar, peça ao
  usuário para fechar a janela.

## Parte A — sem gravar nada (`/products/create`)

| # | Recurso | Como testar | Esperado | Contrato |
|---|---|---|---|---|
| 1 | Bipar | Clicar "Bipar" ao lado do SKU | Cursor vai para o SKU, com aviso para bipar; o código digitado fica no SKU | UC-PCAD-28 |
| 2 | Subcategoria | Escolher categoria que tenha filhas; depois trocar a categoria | Aparece "Subcategoria" só com as filhas; ao trocar, a subcategoria escolhida é limpa | UC-PCAD-25 |
| 3 | Sub-unidades | Escolher unidade com derivadas (ex.: caixa) | Aparece "Sub-unidades relacionadas" com caixas de marcar. Se a empresa não usa sub-unidades, o bloco some (também certo: anotar qual) | UC-PCAD-26 |
| 4 | IMEI / nº de série | Olhar o bloco Configurações | Existe "Habilitar IMEI / nº de série" | UC-PCAD-27 |
| 5 | Campos personalizados | Olhar os campos personalizados | Aparecem com o nome que a empresa deu; campo sem nome não aparece | UC-PCAD-24 |
| 6 | "+" de unidade e marca | Clicar no "+" ao lado de Unidade e de Marca | Abre o cadastro rápido (Nome; Abreviação na unidade). **Não salvar** na parte A | UC-PCAD-29 |
| 7 | Rascunho | Digitar um nome, esperar ~1s, recarregar (F5), depois "Descartar rascunho" | "Rascunho salvo automaticamente às HH:MM"; após F5 volta com "Rascunho recuperado"; descartar limpa | UC-PCAD-30 |
| 8 | Aviso ao sair | Com algo digitado, clicar num item do menu e escolher ficar | Pergunta se quer sair sem salvar | UC-PCAD-30 |

Repetir 1–5 e 8 na tela Editar de um produto existente (UC-PEDIT-24…30). Não salvar.

## Parte B — grava dados (só com liberação do [F])

9. Criar pelo "+" a marca "TESTE CLAUDE" e conferir que ela já vem selecionada.
10. Salvar o produto "TESTE CLAUDE – apagar". Em Editar, enviar uma imagem e um PDF em
    "Imagem e folheto": devem aparecer na hora, sem clicar em Salvar (POST `/products/{id}/midia`).
    No fim, inativar o produto.

## Como devolver

Para cada item: **OK / FALHOU / NÃO APARECEU**, com uma frase do que viu e print dos que
falharam. O que falhar se corrige nesta mesma sessão local, em PR pequeno, com o caso de teste
(`resources/js/Pages/Produto/Create.casos.md` / `Edit.casos.md`) atualizado.

## Pendências fora deste teste

- C2 (compra seguir a política de preço da tela de Formação de preço): mexe em preço; só com
  antes→depois aprovado pelo [W] (REGRA MESTRE).
- Editores de variação e composição: decisão do [F] em 2026-11-17 (SDD §10.6).
