---
sessao: "12"
titulo: Dono por papel — emenda 0014 (D4 escala → Ponto · D5 feriado no HRM, lido pelo Ponto)
dono: "[CL] executa · [W] já decidiu (2026-09-29)"
base: 38921d4f1027 (lida 2026-09-29 16:52 UTC; feriados conferidos em 46f1d4b3976d)
prefixo: memory/decisions/0014-essentials-pontowr2-integracao.md (emenda datada) · Modules/Essentials/Routes/web.php (`/shift` resource + `shift/assign-users` → 301 `/ponto/escalas`)
nao_toca: Modules/Ponto/** (dono do Ponto — sai como pedido) · ShiftController.php e Entities/Shift.php (o código não se apaga aqui, como na 09) · essentials_shifts (dado — outro PR, dupla prova)
depende: thread 09 (feita, _saida-09) · D4 · D5 (respondidas por [W] 2026-09-29)
---
# 12 · Dono por papel

## O que [W] decidiu em 2026-09-29
Regra: **o Ponto é dono da jornada; o HRM é dono do cadastro de pessoas e da aprovação** (extensão da D1).

| tema | dono | quem consome | estado medido no `main` |
|---|---|---|---|
| Presença / marcação | Ponto | — | feito (09) |
| **Escala / horário contratual (D4)** | **Ponto** (`ponto_escalas`) | — | o código já faz: `escala_atual_id` → `ponto_escalas` (migration `2026_04_18_000003:42`, `Colaborador.php:102`); o Ponto **não lê** `Shift` |
| **Feriados (D5)** | **HRM** (cadastro, `Holidays/Index.tsx`) | Ponto (apuração, Art. 73 CLT) | o Ponto **não lê** `EssentialsHoliday` (0 ocorrências em `Modules/Ponto`, @46f1d4b3976d) |
| Licença (aprovação) | HRM | Ponto abona o dia | feito: `ApuracaoService.php:534` + `LicencaAbonaDiaContratoTest` |
| Licença bloqueia marcação (D3) | Ponto | — | pedido aberto desde a 09 |
| Departamentos / Cargos | HRM | — | abas no build (RESÍDUO-2, `_saida-01`) |
| Folha | projeto Folha | lê horas do Ponto | bloqueada (10) |

## Execução
```
1) [CL] PR ≤300 ln:
   a) emenda datada 2026-09-29 na 0014 (nunca ADR nova — LC-19):
      - D4: a linha "Shift = fonte do horário contratual" da tabela de 2026-04-21 não vale mais; a fonte é ponto_escalas.
        Registrar que o código já era assim (migration :42) — a ADR é que estava errada.
      - D5: feriado = cadastro do HRM, lido pelo Ponto. Hoje não é lido (medido) → pedido abaixo.
      - Tabela "tema → dono → consome" acima.
   b) Routes/web.php: `/shift` (resource) e `shift/assign-users` → 301 `/ponto/escalas` (mesmo padrão das 11 de attendance)
      + tirar o link "Turnos" do nav_hrm/sidebar_hrm se existir (senão o action() quebra, como na 09 item 5)
2) [CL] 2 pedidos ao dono do Ponto (fora deste prefixo — task/Issue, citar no _saida):
   P1  ApuracaoService lê EssentialsHoliday (por business_id + localidade) para HE 100% em feriado (Art. 73 CLT) e não conta falta.
   P2  guard D3 na criação da marcação (herdado da 09 — ainda sem task aberta).
3) [CC] build — já feito em 2026-09-29: aba Turnos saiu (`hrm-page.jsx?v=hrm12dono`, `data.jsx?v=sb23`); `hrm-turnos` → aviso com botão para `pt-escalas`.
PARAR SE : (a) alguém propor apagar essentials_shifts ou migrar dado → outro PR, dupla prova (proibicoes.md VALOR)
           (b) algum turno em produção sem escala equivalente no Ponto → medir antes do 301 (quantos business têm Shift e não têm ponto_escalas)
```

## Prova
- `0014-*.md` contém a emenda `2026-09-29` com D4 e D5 · `Routes/web.php` contém `'/ponto/escalas'`
- `_saida-12.md` com os 2 pedidos ao Ponto (link da task) e a contagem do PARAR SE (b)
- Não verificável daqui: P1 e P2 (prefixo de outro dono) · T7
