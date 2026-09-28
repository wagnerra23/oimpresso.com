---
id: requisitos-ponto-escalas-index-gap
tela: Ponto/Escalas/Index (/ponto/escalas)
prototipo: prototipo-ui/cowork/Wagner/ponto-telas.jsx
tela_viva: resources/js/Pages/Ponto/Escalas/Index.tsx
gerado_em: 2026-09-28
---

# GAP-SPEC — Ponto/Escalas/Index

> **Origem:** thread `22-gap-escalas.md` do playbook do Ponto. Decisões citadas só existem em
> `ATA-DECISOES-2026-09-14.md`: **D-ESC-DESTROY** (remover ENTRA, indisponível com colaborador vinculado,
> motivo escrito, diálogo do DS em vez de `window.confirm`) e **D-PONTO-DETALHE** (o form é rota própria).
> **Protótipo medido nesta sha:** `ponto-telas.jsx` @ `2e3f8adb4e`, símbolo `Escalas` (`:442-515`).
> **Vivo medido nesta sha:** `resources/js/Pages/Ponto/Escalas/Index.tsx` @ `e4289e688` (181 linhas) e
> `EscalaController.php`. Toda linha abaixo saiu de `grep -n`.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Barra e ação primária | **Diverge no destino.** Vivo: `PageHeaderPrimary label="Nova escala"` navegando para `/ponto/escalas/create` (`Index.tsx:65`). Protótipo: `Nova escala` abrindo o form na mesma tela, com a nota de carga "480 = 8h, 2.640 = 44h" (`ponto-telas.jsx:460-464`, form em `:456`). | Protótipo corrige (D-PONTO-DETALHE, R2): navega para a rota. A nota de carga é da passada de FORMA. |
| Lista de escalas | **Diverge em ordem e sub-linha.** Vivo: Nome, Código, Tipo, Carga/dia, Carga/semana, BH, Turnos, ação (`Index.tsx:87-96`), a ordem do charter (`Index.charter.md:31`). Protótipo: Código primeiro e o 1º turno na sub-linha do Nome (`ponto-telas.jsx:466`, `:471`). | Protótipo corrige a ordem (o charter manda). A sub-linha do turno é protótipo à frente — forma, sem id na ata. A thread 17 grava `data-contract="escalas-escalas-cadastradas"`. |
| Remover escala | **Paridade na trava, vivo atrás no diálogo.** Vivo: com vínculo, texto "Em uso por N colaborador(es)" no lugar do botão (`Index.tsx:130-141`); sem vínculo, `Remover` com `window.confirm` (`:50`). A trava real está no servidor (`EscalaController.php:130-145`). Protótipo: o mesmo texto no lugar do botão e `Modal` do DS com Cancelar + Remover escala (`ponto-telas.jsx:480-489`, `:498-512`). | **Gap real no vivo (D-ESC-DESTROY):** trocar o `window.confirm` pelo diálogo do DS. A regra de vínculo já está nos dois lados e no servidor. |
| Editar | **Diverge no destino.** Vivo: link para `/ponto/escalas/{id}/edit` (`Index.tsx:119-121`). Protótipo: abre o form na mesma tela (`ponto-telas.jsx:479`). | Protótipo corrige (D-PONTO-DETALHE, R2). |
| Paginação | **Paridade.** Vivo: 20 por página (`EscalaController.php:20`), partial reload `only: ['escalas']` (`Index.tsx:150-164`). Protótipo: `usePagina(rows.length, 20)` (`ponto-telas.jsx:448`). | Nada. |
| Estado vazio | **Vivo à frente.** Vivo: `EmptyState` com CTA "Criar escala" (`Index.tsx:72-83`), Goal do charter (`Index.charter.md:34`). Protótipo: `Vazio` sem ação (`ponto-telas.jsx:467`). | Protótipo corrige: ganha o CTA. |
| Nota de turnos read-only | **Protótipo à frente, com decisão que a derruba.** Protótipo: `Nota` "turnos são leitura aqui, edição em fase posterior" (`ponto-telas.jsx:497`). Vivo: não tem a nota. A D-ESC-TURNOS decidiu que turnos **viram editáveis**. | A nota sai do protótipo quando o CRUD de turnos existir; até lá ela descreve o vivo corretamente. Ver `escalas-form-gap.md`. |
