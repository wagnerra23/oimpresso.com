---
id: requisitos-ponto-escalas-index-gap
tela: Ponto/Escalas/Index (/ponto/escalas)
prototipo: prototipo-ui/cowork/Wagner/ponto-telas.jsx
tela_viva: resources/js/Pages/Ponto/Escalas/Index.tsx
gerado_em: 2026-09-29
---

# GAP-SPEC — Ponto/Escalas/Index

> **Origem:** thread `22-gap-escalas.md` do playbook do Ponto. Decisões citadas só existem em
> `ATA-DECISOES-2026-09-14.md`: **D-ESC-DESTROY** (remover ENTRA, indisponível com colaborador vinculado,
> motivo escrito, diálogo do DS em vez de `window.confirm`) e **D-PONTO-DETALHE** (o form é rota própria).
> **Protótipo medido nesta sha:** `ponto-telas.jsx` @ `2e3f8adb4e`; linhas e estado re-medidos em 2026-09-29 no working tree, símbolo `Escalas` (`:464-540`).
> **Vivo medido nesta sha:** `resources/js/Pages/Ponto/Escalas/Index.tsx` @ `e4289e688` (181 linhas; 301 em 2026-09-29) e
> `EscalaController.php` (re-medidos em 2026-09-29). Toda linha abaixo saiu de `grep -n`.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Barra e ação primária | **Paridade desde 2026-09-29.** Vivo: barra com a nota de carga "480 = 8h, 2.640 = 44h" e `Nova escala` navegando para `/ponto/escalas/create` (`Index.tsx:115-131`, pelo #8115). Protótipo: a mesma barra (`ponto-telas.jsx:485-489`), e `Nova escala` agora vai para a rota própria `pt-escalas-create` (thread 28 onda 3, comentário em `:463`). Registro de 2026-09-28: o protótipo abria o form na mesma tela e o vivo não tinha a nota de carga. | Nada — paridade (D-PONTO-DETALHE feita nos dois lados). |
| Lista de escalas | **Paridade desde o #8115 (2026-09-29).** Vivo: Código primeiro, depois Nome, Tipo, Carga diária, Carga semanal, Turnos, Banco de horas, Ação (`Index.tsx:164-171`), com o 1º turno na sub-linha do Nome (`:180-182`) e `data-contract="escalas-escalas-cadastradas"` (`:134`). Protótipo: a mesma ordem e sub-linha (`ponto-telas.jsx:491`, `:496`). O charter foi emendado para esta ordem (`Index.charter.md:36-38`). Registro de 2026-09-28: o vivo tinha Nome primeiro e sem sub-linha. | Nada — paridade. |
| Remover escala | **Paridade.** Vivo: com vínculo, texto "Em uso por N colaborador(es)" no lugar do botão (`Index.tsx:208-212`); sem vínculo, `Remover` (`:214-221`) abre o `AlertDialog` do DS com Cancelar + Remover escala (`:276-292`), desde o #8079 (2026-09-28). A trava real está no servidor (`EscalaController.php:136-152`). Protótipo: o mesmo texto no lugar do botão e `Modal` do DS com Cancelar + Remover escala (`ponto-telas.jsx:511-513`, `:523-537`). Registro de 2026-09-28: o vivo usava `window.confirm`. | Nada — D-ESC-DESTROY construída nos dois lados. |
| Editar | **Paridade.** Vivo: link para `/ponto/escalas/{id}/edit` (`Index.tsx:200-202`). Protótipo: `Editar` (`ponto-telas.jsx:504`) vai para a rota própria `pt-escalas-<id>-edit` (thread 28 onda 3, `:463`). Registro de 2026-09-28: o protótipo abria o form na mesma tela. | Nada — paridade (D-PONTO-DETALHE feita nos dois lados). |
| Paginação | **Paridade.** Vivo: 20 por página (`EscalaController.php:23`), partial reload `only: ['escalas']` (`Index.tsx:248`, no rodapé `:233-256`). Protótipo: `usePagina(rows.length, 20)` (`ponto-telas.jsx:472`) e `Pager` (`:520`). | Nada. |
| Estado vazio | **Vivo à frente.** Vivo: `EmptyState` com CTA "Criar escala" (`Index.tsx:146-158`), Goal do charter (`Index.charter.md:42`). Protótipo: `Vazio` sem ação (`ponto-telas.jsx:492`). | Protótipo corrige: ganha o CTA. |
| Nota de turnos read-only | **Paridade desde o #8115 (2026-09-29).** Protótipo: `Nota` "turnos são leitura aqui, edição em fase posterior" (`ponto-telas.jsx:522`). Vivo: a mesma nota (`Index.tsx:263-271`). A D-ESC-TURNOS decidiu que turnos **viram editáveis**. Registro de 2026-09-28: o vivo não tinha a nota. | Nada agora — paridade. Quando o CRUD de turnos existir (D-ESC-TURNOS), a nota sai dos dois lados; ver `escalas-form-gap.md`. |
