---
sessao: "09"
titulo: Presença SAI do HRM — dono da jornada é o Ponto (D1)
dono: "[W] decide · [CL] executa · [CC] só ajusta o build (thread 01)"
base: 159e572dd448
prefixo: memory/decisions/0014-essentials-pontowr2-integracao.md (emendar ou superseder — NUNCA ADR paralela) · Modules/Essentials/Routes/web.php (as 11 rotas de attendance cedem, com 301/redirect pro Ponto)
nao_toca: Modules/Ponto/** (é pedido pro dono do Ponto, não edição desta thread) · ponto_marcacoes (append-only, Portaria MTP 671/2021) · AttendanceController::importAttendance (#6798 — o dado migra, o código não se apaga aqui)
depende: D1 (respondida 2026-09-05) · D3 (respondida: licença aprovada BLOQUEIA a marcação — o guard nasce no Ponto)
---
# 09 · Presença sai do HRM

## O que [W] decidiu (PEDIDO-CL-hrm §Emenda 2026-09-05)
- **D1** A presença web cede lugar ao Ponto. O `Modules/Ponto` é dono único da jornada.
- **D3** Licença aprovada bloqueia a marcação e sai da conta de ausência. Como a marcação é do Ponto, o guard nasce **no Ponto** — e "bloquear" é impedir a **criação**, nunca apagar depois (`ponto_marcacoes` é append-only).
- Direção: *"deixar o ponto decidir, vincular com outros módulos"* — integrar, não manter silos.

## O que isso mata neste playbook (e por isso esta thread existe)
- Ondas 4 e 5 do EXPORT-HRM-2026-09-04 (Presença · Espelho do mês) **não se executam**. Quem as encontrar no export deve tratá-las como revogadas.
- 11 rotas de `Routes/web.php` (`/attendance` resource + `import-attendance` · `clock-in-clock-out` · `validate-clock-in-clock-out` · `get-attendance-by-shift` · `get-attendance-by-date` · `get-attendance-row/{user_id}` · `user-attendance-summary`) e 14 métodos públicos do `AttendanceController` **cedem**.
- A folha (thread 10) passa a ler o Ponto (`ponto_apuracao_dia`, `ponto_banco_horas`), não `essentials_attendances`.

## O dono do tema já existe e está no papel
ADR 0014 `essentials-pontowr2-integracao` (2026-04-21, `lifecycle: arquivado`): Shift = horário contratual · Ponto dono das batidas · Payroll alimentado pelo Ponto · `EssentialsHoliday` lido pelo Ponto · `EssentialsLeave` respeitado como Intercorrência. Medido em 05/09 pelo [CL]: **nunca saiu do papel** (`escala_atual_id` só em 2 migrations + 1 seeder; a folha não lê o Ponto; nenhuma ADR a cita). **A saída é emendar/superseder a 0014** — abrir ADR nova é a LC-19 que custou 3 colisões em 04/09.

## Execução (o que cabe a esta thread)
```
1) [W]  ratifica a emenda da 0014 (texto: D1 + D3 + "folha lê o Ponto") — 1 decisão, já tomada em prosa; falta o registro
2) [CL] PR ≤300 ln: emenda na 0014 + tabela "rota do HRM → destino no Ponto" (11 linhas) + redirect 301 das rotas
        (rota que some sem 301 é link morto no sidebar legado e no nav_hrm)
3) [CL] pedido pro dono do Ponto (fora deste playbook): guard D3 na criação da marcação + excluir licença/feriado
        da conta de ausência — cita EssentialsLeave/EssentialsHoliday como fonte (é o que a 0014 já desenha)
4) [CC] thread 01: aba Presença sai do protótipo; Painel aponta "Ver no Ponto"
PARAR SE : (a) alguém propor ADR nova em vez de emendar a 0014 → parar e apontar a LC-19
           (b) migração de dado de essentials_attendances → é outro PR, com dupla prova (proibicoes.md VALOR)
```

## Prova
- `memory/decisions/0014-essentials-pontowr2-integracao.md` com `lifecycle` ≠ arquivado e a emenda datada
- `Routes/web.php` sem as 11 rotas ativas (ou com redirect) · `_saida-09.md`
- Não verificável daqui: o guard no Ponto (prefixo de outro dono)
