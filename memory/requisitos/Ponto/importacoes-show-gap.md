---
id: requisitos-ponto-importacoes-show-gap
tela: Ponto/Importacoes/Show (/ponto/importacoes/{id})
prototipo: prototipo-ui/cowork/Wagner/ponto-telas.jsx
tela_viva: resources/js/Pages/Ponto/Importacoes/Show.tsx
gerado_em: 2026-09-29
charter: resources/js/Pages/Ponto/Importacoes/Show.charter.md
thread: prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/24-gap-importacoes.md
---

# GAP-SPEC — Ponto/Importacoes/Show

> **Fonte do contrato:** charter `Importacoes/Show.charter.md` + protótipo `ponto-telas.jsx`,
> ramo `if (sel)` do símbolo `Importacoes` (`:758-821`). Lado vivo medido em `Importacoes/Show.tsx`
> e `ImportacaoController@show` (`ImportacaoController.php:97-144`), em `origin/main` e4289e688
> (2026-09-28); citações de linha re-medidas em 2026-09-29. Em 2026-09-28 o `.tsx` vivo tinha
> **0** `data-contract`; em 2026-09-29 tem 3 (`Show.tsx:100`, `:118`, `:142`).
> **Troca simétrica, confirmada pela medição de 2026-09-28:** o vivo tinha o tempo real (polling +
> alerta) que o protótipo não tem; o protótipo tinha o diagnóstico (log + amostra de erros) que o vivo
> não tinha. 2026-09-29: a amostra de erros entrou no vivo (#8124); o card de diagnóstico segue só no
> protótipo.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho e baixar original | **Paridade.** Vivo: "Importação #id", badge de estado, nome do arquivo, Voltar e **Baixar original** para `/ponto/importacoes/{id}/original` (`Show.tsx:69-89`, link em `:83-87`). Protótipo `ponto-telas.jsx:763-768` (o botão só avisa, é mock). | Nada — paridade. |
| Dados do arquivo | **Protótipo à frente em 4 campos.** Vivo, card "Arquivo" (`Show.tsx:100-116`): Nome · Tipo · Tamanho · Hash SHA-256 (`:110-112`) · Enviado por · Criado em. Protótipo `ponto-telas.jsx:771-791`: + **ID** · **Estado** · **Iniciado em** · **Concluído em**, e o hash em bloco próprio. O payload do `show` não tem iniciado/concluído (`ImportacaoController.php:102-142`). | **Decidir** se iniciado/concluído entram — dependem de coluna que o payload não expõe hoje. Não há `D-*` específico; o charter pede 6 campos e o vivo cumpre os 6. |
| Resumo do processamento | **Parcial.** Vivo, card "Processamento" (`Show.tsx:118-136`): Estado · processadas · criadas · ignoradas · última atualização, em linhas. Protótipo `ponto-telas.jsx:802-817`: 4 KPIs (totais · processadas · criadas · erros) + **barra de % processado** + nota de encoding/limite/chunk. O vivo não tem total de linhas nem % (o payload não envia total). | **Decidir** a forma (KPI × linhas é FORMA, thread de forma). A barra de % exige o total de linhas no payload. |
| Tempo real enquanto processa | **Vivo à frente.** Vivo faz polling a cada 3s com partial reload `only: ['importacao']` enquanto pendente/processando (`Show.tsx:55-61`) e mostra "auto-refresh 3s…" (`:127-129`). Protótipo não tem. | Nada — vivo à frente. O "PARAR SE" da thread 24 se cumpriu: é catch-up do protótipo. |
| Alerta de erro | **Vivo à frente, com ressalva.** Vivo mostra `Alert` destrutivo "Erro no processamento" com `erro_mensagem` (`Show.tsx:91-97`). ⚠️ `erro_mensagem` é o campo `log` da importação (`ImportacaoController.php:120`), não uma mensagem de falha: se o job gravar log numa importação bem-sucedida, o alerta vermelho aparece mesmo assim. **Não medido em runtime** — é leitura do código. | **Decidir** junto do diagnóstico: separar "log de processamento" (neutro) de "falhou" (alerta, só quando o estado é de falha). |
| Diagnóstico do processamento | **Ausente como região própria.** O log só aparece dentro do alerta de erro (linha acima). Protótipo tem card "Diagnóstico do processamento" com o log em `<pre>` (`ponto-telas.jsx:792`). | **Incorporar** — `D-IMP-EXTRAS` = INCORPORA ("sem isso a tela de importação só sabe dizer que falhou"). |
| Amostra de erros | **Paridade desde 2026-09-29 ([#8124](https://github.com/wagnerra23/oimpresso.com/pull/8124), `UC-IMPSH-06`).** Vivo: card "Amostra de erros" com Linha · NSR · Tipo · Mensagem e "(N primeiros)" (`Show.tsx:141-175`), alimentado pela chave `erros_amostra` do `show` — até 20 itens, só as 4 chaves do contrato (`ImportacaoController.php:128-138`); a mensagem chega mascarada pelo `PiiRedactor` no produtor (`AfdParserService.php:168`). Protótipo: a mesma tabela (`ponto-telas.jsx:793-800`). Fato datado: quando este gap foi medido, em 2026-09-28, o payload não trazia amostra e `Show.tsx` não tinha a região. | Nada — incorporado pelo #8124 (`D-IMP-EXTRAS`). |
