---
id: requisitos-ponto-colaboradores-edit-gap
tela: Ponto/Colaboradores/Edit (/ponto/colaboradores/{id}/editar)
prototipo: prototipo-ui/cowork/Wagner/ponto-telas.jsx
tela_viva: resources/js/Pages/Ponto/Colaboradores/Edit.tsx
gerado_em: 2026-09-28
charter: resources/js/Pages/Ponto/Colaboradores/Edit.charter.md
thread: prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/23-gap-colaboradores.md
---

# GAP-SPEC — Ponto/Colaboradores/Edit

> **Fonte do contrato:** charter `Colaboradores/Edit.charter.md` + protótipo `ponto-telas.jsx`,
> símbolo `ColaboradorForm` (`:661-713`). Lado vivo medido em `Colaboradores/Edit.tsx` e
> `ColaboradorController.php` (`origin/main` e4289e688). O `.tsx` vivo tem **0** `data-contract`.
> CPF/PIS aparecem **inteiros** só aqui, por decisão (`D-COLAB-CPF`); este gap não cita nenhum valor.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Configuração de ponto — campos | **Paridade.** Vivo `Edit.tsx:93-131`: Matrícula (30) · CPF (14) · PIS (14) · Admissão * · Desligamento · Escala (select). Protótipo `:675-686`: os mesmos 6 campos, com help no PIS ("sem PIS, a marcação do AFD é rejeitada") em `:677`. | Catch-up de copy: o help do PIS (`:677`) não existe no vivo e liga esta tela à de Importações. Barato, sem decisão. |
| Datas de admissão e desligamento | **Vivo à frente.** Vivo usa `type="date"` (`Edit.tsx:111` e `:116`) e valida a ordem no servidor: regra `after:admissao` no `desligamento` em `ColaboradorController.php:102`. Protótipo usa texto livre `dd/mm/aaaa` sem validar a ordem (`:684-685`) — defeito que a própria thread 23 declarou. | Nada — vivo à frente. Quem muda é o protótipo (R2): trocar para o `DatePicker` PT-BR do DS. |
| Flags controla ponto e banco de horas | **Vivo à frente.** Vivo usa `Switch` do DS (`Edit.tsx:133-148`, `Switch` em `:139` e `:146`) — é o que o charter pede ("Switches"). Protótipo usa `PtCheck` (checkbox) em `:688-689`. | Nada — vivo à frente. `D-PONTO-ATOMO-BOOLEANO` (FAÇA: `Switch` para flag persistida) é **catch-up do protótipo**, nos 8 sítios dele. |
| Dados do HRM (read-only) | **Ausente como card.** Vivo mostra só o e-mail no header (`Edit.tsx:75`) e a frase "Nome e email vêm do HRM" na descrição do card (`:89`). Protótipo tem card próprio com Nome · E-mail · Cargo · **ID HRM** + nota "para alterar, vá em Funcionários" (`:697-709`). `grep -c` de `cargo` e de `user_id` em `Edit.tsx` = 0 e 0 e o payload do `edit` (`ColaboradorController.php:64-88`) não envia cargo nem id do HRM. | **Incorporar** o card — materializa o Non-Goal "não edita nome/e-mail". Exige acrescentar cargo e id do usuário HRM ao payload do `edit`. |
| Ações Cancelar e Salvar | **Paridade.** Vivo `Edit.tsx:152-160` (Cancelar volta à lista; Salvar com estado "Salvando…"). Protótipo `:691-694`. | Nada — paridade. |
