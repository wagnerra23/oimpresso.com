---
id: requisitos-ponto-escalas-form-gap
tela: Ponto/Escalas/Form (/ponto/escalas/create · /ponto/escalas/{id}/edit)
prototipo: prototipo-ui/cowork/Wagner/ponto-telas.jsx
tela_viva: resources/js/Pages/Ponto/Escalas/Form.tsx
gerado_em: 2026-09-29
---

# GAP-SPEC — Ponto/Escalas/Form

> **Origem:** thread `22-gap-escalas.md` do playbook do Ponto. Decisões citadas só existem em
> `ATA-DECISOES-2026-09-14.md`: **D-ESC-TURNOS** (SIM, turnos editáveis) e **D-PONTO-DETALHE** (rota própria).
> **Protótipo medido nesta sha:** `ponto-telas.jsx` @ `2e3f8adb4e`; linhas re-medidas em 2026-09-29 no working tree, símbolo `EscalaForm` (`:542-586`).
> **Vivo medido nesta sha:** `resources/js/Pages/Ponto/Escalas/Form.tsx` @ `e4289e688` (212 linhas) e
> `EscalaController.php` (re-medidos em 2026-09-29). Toda linha abaixo saiu de `grep -n`.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho com título dual | **Paridade.** Vivo: "Editar escala" × "Nova escala" + `Voltar` (`Form.tsx:78-83`). Protótipo: o mesmo par + `Voltar às escalas` (`ponto-telas.jsx:552-553`). | Nada. |
| Nome, código e tipo | **Paridade.** Vivo: Nome obrigatório com erro inline, Código, Tipo (`Form.tsx:99-119`). Protótipo: Nome 120, Código 30, Tipo (`ponto-telas.jsx:557-561`). | Nada. |
| Cargas e banco de horas | **Paridade de regra, átomo pendente.** Vivo: carga diária 60–600 e semanal 0–3600 como `min`/`max` (`Form.tsx:130-146`) e "Banco de Horas" como `input type="checkbox"` (`:121-128`). Protótipo: os mesmos limites (`ponto-telas.jsx:548-549`, `:564-565`) e `PtCheck` (`:566`). Charter: 60–600 e 0–3600 (`Form.charter.md:31`). | Os limites batem nos três. A flag é dado persistido: pela D-PONTO-ATOMO-BOOLEANO (R3) vira `Switch` nos dois lados — dono é a onda de átomos. |
| Turnos configurados | **Read-only nos dois lados, contra a decisão.** Vivo: tabela read-only de turnos por dia, só no edit (`Form.tsx:151-187`), com a frase "CRUD de turnos em iteração futura" (`:156`). Protótipo: tabela read-only (`ponto-telas.jsx:568-576`). O charter ainda diz read-only (`Form.charter.md:38`). | **D-ESC-TURNOS decidida: SIM, editável.** Os dois lados estão atrás. Pendente: o escopo do CRUD (colunas, validação de interjornada) não está na ata — emenda de charter primeiro (thread 27), código depois. Não inventado aqui. |
| Nota de interjornada e intrajornada | **Ausente no vivo.** Protótipo: `Nota` citando Art. 66 e Art. 71 da CLT, validadas na apuração (`ponto-telas.jsx:577`). Vivo: `grep -n "Art\."` em `Form.tsx` = 0 (re-contado em 2026-09-29: 0). | Protótipo à frente; entra com a passada de FORMA. |
| Rodapé e destino pós-salvar | **Protótipo corrige.** Vivo: `Cancelar` + `Criar escala`/`Salvar alterações` com erros por campo (`Form.tsx:190-198`); o servidor redireciona o `store` para o edit (`EscalaController.php:65-67`), como o charter (`Form.charter.md:51`). Protótipo: botão desabilitado com erro no `title` (`ponto-telas.jsx:578-581`) e volta para a lista com o toast "vincule colaboradores" (`:476`). | Protótipo corrige nos dois pontos: erro inline por campo (o `title` em botão desabilitado é inalcançável) e destino pós-criar no edit. Com a D-ESC-TURNOS, o redirect para o edit deixa de ser beco. |

## Região do form: `escalaform-dados-da-escala` (2026-09-29, thread 17)

O protótipo embrulha nome, código, tipo, cargas e banco de horas num `Card` cujo título é o nome da escala na edição e "Dados da escala" na criação, e por isso a derivação gerou o id `escalaform-card` (em 2026-09-29 o card está em `ponto-telas.jsx:554`, já com o id novo). No vivo a mesma região é o card de título fixo "Dados da escala" (`Form.tsx:89`). O nome da região passa a ser **`escalaform-dados-da-escala`**, gravado nos dois lados no mesmo PR. O card de turnos (`Form.tsx:151`) é outra região e segue sem id.
