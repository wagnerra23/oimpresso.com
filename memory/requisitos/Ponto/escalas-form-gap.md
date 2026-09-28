---
id: requisitos-ponto-escalas-form-gap
tela: Ponto/Escalas/Form (/ponto/escalas/create · /ponto/escalas/{id}/edit)
prototipo: prototipo-ui/cowork/Wagner/ponto-telas.jsx
tela_viva: resources/js/Pages/Ponto/Escalas/Form.tsx
gerado_em: 2026-09-28
---

# GAP-SPEC — Ponto/Escalas/Form

> **Origem:** thread `22-gap-escalas.md` do playbook do Ponto. Decisões citadas só existem em
> `ATA-DECISOES-2026-09-14.md`: **D-ESC-TURNOS** (SIM, turnos editáveis) e **D-PONTO-DETALHE** (rota própria).
> **Protótipo medido nesta sha:** `ponto-telas.jsx` @ `2e3f8adb4e`, símbolo `EscalaForm` (`:517-561`).
> **Vivo medido nesta sha:** `resources/js/Pages/Ponto/Escalas/Form.tsx` @ `e4289e688` (212 linhas) e
> `EscalaController.php`. Toda linha abaixo saiu de `grep -n`.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho com título dual | **Paridade.** Vivo: "Editar escala" × "Nova escala" + `Voltar` (`Form.tsx:78-83`). Protótipo: o mesmo par + `Voltar às escalas` (`ponto-telas.jsx:527-528`). | Nada. |
| Nome, código e tipo | **Paridade.** Vivo: Nome obrigatório com erro inline, Código, Tipo (`Form.tsx:99-119`). Protótipo: Nome 120, Código 30, Tipo (`ponto-telas.jsx:531-537`). | Nada. |
| Cargas e banco de horas | **Paridade de regra, átomo pendente.** Vivo: carga diária 60–600 e semanal 0–3600 como `min`/`max` (`Form.tsx:130-146`) e "Banco de Horas" como `input type="checkbox"` (`:121-128`). Protótipo: os mesmos limites (`ponto-telas.jsx:523-524`, `:539-540`) e `PtCheck` (`:541`). Charter: 60–600 e 0–3600 (`Form.charter.md:31`). | Os limites batem nos três. A flag é dado persistido: pela D-PONTO-ATOMO-BOOLEANO (R3) vira `Switch` nos dois lados — dono é a onda de átomos. |
| Turnos configurados | **Read-only nos dois lados, contra a decisão.** Vivo: tabela read-only de turnos por dia, só no edit (`Form.tsx:151-187`), com a frase "CRUD de turnos em iteração futura" (`:156`). Protótipo: tabela read-only (`ponto-telas.jsx:543-551`). O charter ainda diz read-only (`Form.charter.md:38`). | **D-ESC-TURNOS decidida: SIM, editável.** Os dois lados estão atrás. Pendente: o escopo do CRUD (colunas, validação de interjornada) não está na ata — emenda de charter primeiro (thread 27), código depois. Não inventado aqui. |
| Nota de interjornada e intrajornada | **Ausente no vivo.** Protótipo: `Nota` citando Art. 66 e Art. 71 da CLT, validadas na apuração (`ponto-telas.jsx:552`). Vivo: `grep -n "Art\."` em `Form.tsx` = 0. | Protótipo à frente; entra com a passada de FORMA. |
| Rodapé e destino pós-salvar | **Protótipo corrige.** Vivo: `Cancelar` + `Criar escala`/`Salvar alterações` com erros por campo (`Form.tsx:190-198`); o servidor redireciona o `store` para o edit (`EscalaController.php:59-60`), como o charter (`Form.charter.md:51`). Protótipo: botão desabilitado com erro no `title` (`ponto-telas.jsx:553-556`) e volta para a lista com o toast "vincule colaboradores" (`:452`). | Protótipo corrige nos dois pontos: erro inline por campo (o `title` em botão desabilitado é inalcançável) e destino pós-criar no edit. Com a D-ESC-TURNOS, o redirect para o edit deixa de ser beco. |
