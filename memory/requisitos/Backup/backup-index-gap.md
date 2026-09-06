---
id: requisitos-backup-backup-index-gap
tela: Backup/Index (/backup)
prototipo: prototipo-ui/cowork/backup-page.jsx
tela_viva: resources/js/Pages/Backup/Index.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — Backup/Index

> **Âncora estável:** as duas pontas já carregam os MESMOS cinco `data-contract`
> (`cabecalho` · `kpis` · `alerta-destino` · `lista` · `cron`) — protótipo em
> `backup-page.jsx:180`, `:186`, `:208`, `:246`, `:254`; vivo em `Index.tsx:158`, `:174`,
> `:195`, `:228`, `:254`. O `map.json` declara essas âncoras, então refactor que as remova
> vira DRIFT no `design-code-map-check`, e não silêncio.
>
> **Frescor da fonte:** `backup-page.jsx` foi verificado contra o Cowork vivo em 2026-08-27 e
> **não** entrou na rodada de 2026-09-06 (que mediu 7 de 258). A comparação abaixo fala do
> espelho; não prova o Cowork de hoje.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho | `Index.tsx:158-172` usa `PageHeader` do DS com o mesmo título e o mesmo subtítulo derivado do protótipo (compare `Index.tsx:150-152` com `backup-page.jsx:174-176` — a frase e a ordem dos fatos são as mesmas). A única ação é "Gerar backup agora"; o protótipo põe duas (`backup-page.jsx:168-173`), acrescentando um botão "Auditoria". | **Decidir.** Só o botão "Auditoria". Região do mockup: `backup-page.jsx:170`, que no protótipo chama o roteador do shell (`window.__selectRoute`). No vivo entraria em `Index.tsx:162-170`, apontando para `/auditoria` — rota que existe. É atalho cross-tela, não capacidade desta tela. Construir ou rejeitar por escrito. |
| Cartões de indicador | `Index.tsx:174-193` traz os quatro cartões do protótipo, com os mesmos rótulos e a mesma ordem (Último backup · Backups guardados · Espaço ocupado · Agendamento diário). O protótipo (`backup-page.jsx:187-196`) enriquece três deles: `hero` no primeiro, `spark` com o histórico de tamanho, `progress` nos dois do meio, e descreve o espaço como percentual de um limite. | **Decidir.** Região do mockup: `backup-page.jsx:187-196`; ponto no vivo: `Index.tsx:175-192`. **Pré-condição medida:** o `KpiCard` do repo não tem essas props — `resources/js/Components/shared/KpiCard.tsx:77-86` expõe `icon`, `description`, `delta`, `action`, `tone`, `size`, `selected`, e nada de `hero`/`spark`/`progress`. Adotar exige estender um componente do Design System, que é decisão [W] (soberania Tier 0), não ajuste de tela. Construir ou rejeitar por escrito. |
| Alertas de destino e de escopo | `Index.tsx:195-226` tem três alertas, um deles **ausente do protótipo**: "O arquivo contém os dados de todos os negócios" (`Index.tsx:197-203`, UC-BKP-08). Os outros dois (destino local e agendado parado) batem com `backup-page.jsx:213-224`. O protótipo tem mais três notas que o vivo não tem: destino remoto configurado (`:209-212`), falha da última geração (`:225-230`) e ambiente de demonstração (`:231-236`). | **Decidir.** Duas direções na mesma região (`backup-page.jsx:208-236` × `Index.tsx:195-226`). O alerta de multi-tenant é do vivo e **não** deve ser removido para "convergir" — é o UC-BKP-08. Das três notas do protótipo, a de destino remoto e a de demo dependem de props que o controller não envia hoje (`Index.tsx:36-43` tem `destino.remoto`, mas não estado de erro nem `APP_ENV`), e a de falha da última geração exige persistir o resultado do job assíncrono. Construir ou rejeitar por escrito, nota a nota. |
| Progresso da geração | Não existe no vivo, e é deliberado: o cabeçalho do arquivo (`Index.tsx:24-25`) registra que a geração é assíncrona desde a Onda 2 (job na fila `backups`) e que o retorno diz "pode fechar a tela". O protótipo desenha uma barra de progresso em quatro passos (`backup-page.jsx:238-245`) e afirma no texto que o backup "roda dentro desta requisição" (`:270`). | Nada — vivo à frente. O bloco do protótipo descreve o modelo **síncrono** que o vivo já substituiu; adotá-lo seria regredir a arquitetura para poder desenhar a barra. Fica registrado como trecho STALE do espelho, não como gap. |
| Lista de backups | `Index.tsx:228-251` usa `DataTable` com paginação de servidor (`pagination`, `endpoint`) dentro de `Deferred` com `Skeleton`, e `EmptyState` com a copy do protótipo. As seis colunas (Arquivo · Origem · Tamanho · Data · Idade · Ações) são as mesmas de `backup-page.jsx:133-140`. O protótipo enfeita três células (`:141-165`): ícone de zip com selo "mais recente" na primeira linha, `StatusBadge` na Origem e `StatusBadge kind="frescor"` na Idade. | **Decidir.** Só o enfeite das células. Região do mockup: `backup-page.jsx:141-165`; ponto no vivo: o array de colunas em `Index.tsx:87-148` (as três células em disputa ficam em `:87-117`). O `Deferred` e a paginação são do vivo e **não** entram na conversa — nisso o vivo está à frente do protótipo, que lista um array em memória. Construir ou rejeitar por escrito. |
| Confirmação de gerar e de excluir | `Index.tsx:76-79` e `:81-85` confirmam com `window.confirm` nativo. O protótipo usa o `Confirm` do Design System, com título, rótulo de ação e corpo em dois parágrafos (`backup-page.jsx:269-281`). | **Decidir.** Região do mockup: `backup-page.jsx:269-281`; ponto no vivo: `Index.tsx:76-85`. Vale a **forma** (diálogo do DS em vez do nativo), não o texto: o corpo do protótipo afirma que a geração roda dentro da requisição e leva de um a três minutos, o que é o mesmo trecho STALE da linha anterior — o vivo já reescreveu a copy para o modelo assíncrono. Construir ou rejeitar por escrito. |
| Bloco do cron | `Index.tsx:253-280` mostra o mesmo bloco do protótipo (`backup-page.jsx:254-265`): título, a mesma frase sobre o crontab, a linha copiável com botão, e os três fatos (pasta, retenção, permissão). A diferença é que no vivo os valores vêm de props e no protótipo são constantes. | Nada — paridade. |
