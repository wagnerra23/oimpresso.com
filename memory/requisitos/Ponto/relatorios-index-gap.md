---
id: requisitos-ponto-relatorios-index-gap
tela: Ponto/Relatorios/Index (/ponto/relatorios)
prototipo: prototipo-ui/cowork/Wagner/ponto-telas.jsx
tela_viva: resources/js/Pages/Ponto/Relatorios/Index.tsx
gerado_em: 2026-09-28
charter: resources/js/Pages/Ponto/Relatorios/Index.charter.md
thread: prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/25-gap-relatorios.md
---

# GAP-SPEC — Ponto/Relatorios/Index

> **Fonte do contrato:** charter `Relatorios/Index.charter.md` + protótipo `ponto-telas.jsx`,
> símbolo `Relatorios` (`:849-931`). Lado vivo medido em `Relatorios/Index.tsx` e
> `RelatorioController.php` (`origin/main` e4289e688). O `.tsx` vivo tem **0** `data-contract`.
> **As duas decisões desta tela já foram tomadas** (`D-REL-FLUXO`, `D-REL-FILA`) e puxam em
> sentidos opostos: a primeira dá razão ao vivo, a segunda dá razão ao protótipo.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Nota de topo com a lei | **Ausente no vivo.** O header diz só "Defina período e colaborador, depois clique em Gerar" (`Index.tsx:139`); `grep -c` de `671` e de `Anexo` em `Relatorios/Index.tsx` = 0 e 0. Protótipo: nota citando **Portaria MTP 671/2021 Anexo I** para AFD/AFDT/AEJ e PDF/CSV para espelho e gerenciais (`:870-872`). | **Incorporar** a nota, com o artigo literal. Copy de conformidade, sem decisão pendente. |
| Filtros de período e colaborador | **Vivo segue a decisão.** Vivo: filtros **globais no topo** — período `<input type="month">` (`Index.tsx:151-156`) e colaborador (`:159-176`), aplicados a todos os cards. Protótipo: wizard "Gerar: <relatório>" aberto por card, com competência em select extenso (`:874-895`). | Nada no vivo. `D-REL-FLUXO` = **filtros globais no topo**, e por R2 **o wizard sai do protótipo** no mesmo pacote — o campo Formato do wizard, que mostra sempre "TXT" mesmo para PDF (`:885`), sai junto. |
| Incluir marcações anuladas | **Ausente no vivo.** `grep -n "anulad" Relatorios/Index.tsx` = 0. Protótipo: flag "Incluir marcações anuladas (exigido no AFD — a anulação também é registro)" (`:887`). | **Decidir** onde a flag mora depois que o wizard sai (`D-REL-FLUXO`): filtro global ou parâmetro só dos relatórios legais. Ela chega ao gerador de AFD, que ainda é 501 (thread 12). |
| Grade de relatórios por categoria | **Vivo à frente.** Vivo agrupa os cards por categoria com default "Geral" (`Index.tsx:110-120` e `:180-248`), como o charter pede. Protótipo tem grade plana (`:897-911`). | Nada — vivo à frente; o agrupamento é catch-up do protótipo (o "PARAR SE" da thread 25 se cumpriu). |
| Relatório que sai por colaborador | **Vivo à frente.** Vivo trava o Gerar de relatório com `requer_colaborador` enquanto nenhum colaborador está escolhido, com a razão escrita (`Index.tsx:191-202` e `:237-241`). A regra vem do backend (`:55-60`). Protótipo não tem essa trava. | Nada — vivo à frente. |
| Gerar relatório Em breve | **Diverge da decisão.** Vivo desabilita o botão de relatório indisponível, com rótulo "Em desenvolvimento" (`Index.tsx:198-199` e `:224-236`) — é o Non-Goal atual do charter. Protótipo aceita o clique e registra o pedido (`:857-866`). | **Incorporar** — `D-REL-FILA` = **mantém o clique e registra**, com duas condições da ata: o registro carrega `business_id` (Tier 0) e a tela diz "pedido registrado", não um 501 mudo. Exige persistência no backend (hoje `RelatorioController.php:102` é `abort(501)`) e emenda do Non-Goal do charter (thread 27). |
| Pedidos desta sessão | **Ausente no vivo.** Protótipo: tabela de pedidos (Relatório · Competência · Escopo · Formato · Estado) em `:913-926`, em memória. | **Incorporar** junto com `D-REL-FILA`: é a superfície da fila. No vivo tem de vir do registro persistido, não de estado local. |
| Rodapé legal | **Ausente no vivo.** Protótipo: "Hoje só o Espelho está implementado em ReportService" (`:928`). No vivo a mesma verdade está só no código (`RelatorioController.php:90-102`). | **Incorporar** — mesma copy do charter. |
