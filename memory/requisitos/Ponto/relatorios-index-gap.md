---
id: requisitos-ponto-relatorios-index-gap
tela: Ponto/Relatorios/Index (/ponto/relatorios)
prototipo: prototipo-ui/cowork/Wagner/ponto-telas.jsx
tela_viva: resources/js/Pages/Ponto/Relatorios/Index.tsx
gerado_em: 2026-09-29
charter: resources/js/Pages/Ponto/Relatorios/Index.charter.md
thread: prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/25-gap-relatorios.md
---

# GAP-SPEC — Ponto/Relatorios/Index

> **Fonte do contrato:** charter `Relatorios/Index.charter.md` + protótipo `ponto-telas.jsx`,
> símbolo `Relatorios` (`:889-971`, re-medido 2026-09-29). Lado vivo medido em `Relatorios/Index.tsx` e
> `RelatorioController.php` (`origin/main` e4289e688; linhas re-medidas em 2026-09-29). O `.tsx` vivo tem **0** `data-contract`.
> **As duas decisões desta tela já foram tomadas** (`D-REL-FLUXO`, `D-REL-FILA`) e puxam em
> sentidos opostos: a primeira dá razão ao vivo, a segunda dá razão ao protótipo.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Nota de topo com a lei | **Ausente no vivo.** Até 2026-09-28 o header dizia só "Defina período e colaborador, depois clique em Gerar"; desde 2026-09-29 (W9, #8118) a tela não tem mais header próprio — só o `PontoAreaHeader` de módulo (`Index.tsx:136`). `grep -c` de `671` e de `Anexo` em `Relatorios/Index.tsx` = 0 e 0 (2026-09-29). Protótipo: nota citando **Portaria MTP 671/2021 Anexo I** para AFD/AFDT/AEJ e PDF/CSV para espelho e gerenciais (`ponto-telas.jsx:910-912`). | **Incorporar** a nota, com o artigo literal. Copy de conformidade, sem decisão pendente. |
| Filtros de período e colaborador | **Vivo segue a decisão.** Vivo: filtros **globais no topo** (`Index.tsx:139-170`) — período `<input type="month">` (`:143-148`) e colaborador (`:151-168`), aplicados a todos os cards. Protótipo: wizard "Gerar: <relatório>" aberto por card (`ponto-telas.jsx:914-935`), com competência em select extenso (`:918-920`). | Nada no vivo. `D-REL-FLUXO` = **filtros globais no topo**, e por R2 **o wizard sai do protótipo** no mesmo pacote — o campo Formato do wizard, que mostra sempre "TXT" mesmo para PDF (`ponto-telas.jsx:925`), sai junto. |
| Incluir marcações anuladas | **Ausente no vivo.** `grep -n "anulad" Relatorios/Index.tsx` = 0 (2026-09-29). Protótipo: flag "Incluir marcações anuladas (exigido no AFD — a anulação também é registro)" (`ponto-telas.jsx:927`). | **Decidir** onde a flag mora depois que o wizard sai (`D-REL-FLUXO`): filtro global ou parâmetro só dos relatórios legais. Ela chega ao gerador de AFD, que ainda é 501 (thread 12). |
| Grade de relatórios por categoria | **Vivo à frente.** Vivo agrupa os cards por categoria com default "Geral" (`Index.tsx:110-120` e `:172-240`), como o charter pede. Protótipo tem grade plana (`ponto-telas.jsx:937-951`). | Nada — vivo à frente; o agrupamento é catch-up do protótipo (o "PARAR SE" da thread 25 se cumpriu). |
| Relatório que sai por colaborador | **Vivo à frente.** Vivo trava o Gerar de relatório com `requer_colaborador` enquanto nenhum colaborador está escolhido, com a razão escrita (`Index.tsx:183-194` e `:229-233`). A regra vem do backend (`:55-60`). Protótipo não tem essa trava. | Nada — vivo à frente. |
| Gerar relatório Em breve | **Diverge da decisão.** Vivo desabilita o botão de relatório indisponível, com rótulo "Em desenvolvimento" (`Index.tsx:190-191` e `:215-228`) — é o Non-Goal atual do charter. Protótipo aceita o clique e registra o pedido (`ponto-telas.jsx:897-906`). | **Incorporar** — `D-REL-FILA` = **mantém o clique e registra**, com duas condições da ata: o registro carrega `business_id` (Tier 0) e a tela diz "pedido registrado", não um 501 mudo. Exige persistência no backend (hoje `RelatorioController.php:102` é `abort(501)`) e emenda do Non-Goal do charter (thread 27). |
| Pedidos desta sessão | **Ausente no vivo.** Protótipo: tabela de pedidos (Relatório · Competência · Escopo · Formato · Estado) em `ponto-telas.jsx:953-966`, em memória. | **Incorporar** junto com `D-REL-FILA`: é a superfície da fila. No vivo tem de vir do registro persistido, não de estado local. |
| Rodapé legal | **Ausente no vivo.** Protótipo: "Hoje só o Espelho está implementado em ReportService" (`ponto-telas.jsx:968`). No vivo a mesma verdade está só no código (`RelatorioController.php:90-102`). | **Incorporar** — a copy vem do protótipo (`ponto-telas.jsx:968`); o charter não traz essa frase. |
