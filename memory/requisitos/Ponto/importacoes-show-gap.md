---
id: requisitos-ponto-importacoes-show-gap
tela: Ponto/Importacoes/Show (/ponto/importacoes/{id})
prototipo: prototipo-ui/cowork/Wagner/ponto-telas.jsx
tela_viva: resources/js/Pages/Ponto/Importacoes/Show.tsx
gerado_em: 2026-09-28
charter: resources/js/Pages/Ponto/Importacoes/Show.charter.md
thread: prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/24-gap-importacoes.md
---

# GAP-SPEC — Ponto/Importacoes/Show

> **Fonte do contrato:** charter `Importacoes/Show.charter.md` + protótipo `ponto-telas.jsx`,
> ramo `if (sel)` do símbolo `Importacoes` (`:726-789`). Lado vivo medido em `Importacoes/Show.tsx`
> e `ImportacaoController@show` (`ImportacaoController.php:97-126`, `origin/main` e4289e688).
> O `.tsx` vivo tem **0** `data-contract`.
> **Troca simétrica, confirmada pela medição:** o vivo tem o tempo real (polling + alerta) que o
> protótipo não tem; o protótipo tem o diagnóstico (amostra de erros) que o vivo não tem.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho e baixar original | **Paridade.** Vivo: "Importação #id", badge de estado, nome do arquivo, Voltar e **Baixar original** para `/ponto/importacoes/{id}/original` (`Show.tsx:59-80`, link em `:74-78`). Protótipo `:731-736` (o botão só avisa, é mock). | Nada — paridade. |
| Dados do arquivo | **Protótipo à frente em 4 campos.** Vivo, card "Arquivo" (`Show.tsx:91-107`): Nome · Tipo · Tamanho · Hash SHA-256 (`:101-103`) · Enviado por · Criado em. Protótipo `:739-759`: + **ID** · **Estado** · **Iniciado em** · **Concluído em**, e o hash em bloco próprio. O payload do `show` não tem iniciado/concluído (`ImportacaoController.php:102-124`). | **Decidir** se iniciado/concluído entram — dependem de coluna que o payload não expõe hoje. Não há `D-*` específico; o charter pede 6 campos e o vivo cumpre os 6. |
| Resumo do processamento | **Parcial.** Vivo, card "Processamento" (`Show.tsx:109-127`): Estado · processadas · criadas · ignoradas · última atualização, em linhas. Protótipo `:770-785`: 4 KPIs (totais · processadas · criadas · erros) + **barra de % processado** + nota de encoding/limite/chunk. O vivo não tem total de linhas nem % (o payload não envia total). | **Decidir** a forma (KPI × linhas é FORMA, thread de forma). A barra de % exige o total de linhas no payload. |
| Tempo real enquanto processa | **Vivo à frente.** Vivo faz polling a cada 3s com partial reload `only: ['importacao']` enquanto pendente/processando (`Show.tsx:46-52`) e mostra "auto-refresh 3s…" (`:118-120`). Protótipo não tem. | Nada — vivo à frente. O "PARAR SE" da thread 24 se cumpriu: é catch-up do protótipo. |
| Alerta de erro | **Vivo à frente, com ressalva.** Vivo mostra `Alert` destrutivo "Erro no processamento" com `erro_mensagem` (`Show.tsx:82-88`). ⚠️ `erro_mensagem` é o campo `log` da importação (`ImportacaoController.php:120`), não uma mensagem de falha: se o job gravar log numa importação bem-sucedida, o alerta vermelho aparece mesmo assim. **Não medido em runtime** — é leitura do código. | **Decidir** junto do diagnóstico: separar "log de processamento" (neutro) de "falhou" (alerta, só quando o estado é de falha). |
| Diagnóstico do processamento | **Ausente como região própria.** O log só aparece dentro do alerta de erro (linha acima). Protótipo tem card "Diagnóstico do processamento" com o log em `<pre>` (`:760`). | **Incorporar** — `D-IMP-EXTRAS` = INCORPORA ("sem isso a tela de importação só sabe dizer que falhou"). |
| Amostra de erros | **Ausente.** O payload do `show` não traz amostra de linhas com erro (`ImportacaoController.php:102-124`); `grep -ci` de `nsr` e de `amostra` em `Show.tsx` = 0 e 0. Protótipo: tabela Linha · NSR · Tipo · Mensagem dos primeiros erros (`:761-768`). | **Nada — feito 2026-09-29 (#8124, UC-IMPSH-06).** Era: incorporar — `D-IMP-EXTRAS`. Exige o job persistir a amostra e o `show` expô-la. Mensagens de erro de linha podem carregar PIS do AFD: tratar como PII na persistência e na tela. |
