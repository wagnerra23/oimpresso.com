---
# ADR 0014 — Integração PontoWR2 × Essentials (HRM)

**Data:** 2026-04-21
**Status:** Aceita
**Autora:** Eliana (WR2 Sistemas) — levantamento sessão 10

---

## Contexto

O módulo **Essentials** é o HRM nativo do UltimatePOS. O **PontoWR2** é o módulo de ponto eletrônico da WR2. Ambos lidam com jornada de trabalho, mas têm escopos complementares. Este ADR documenta a relação entre eles e as regras de não-duplicação de entidades.

---

## Entidades do Essentials (relevantes para PontoWR2)

| Entidade Essentials | Propósito | Relação com PontoWR2 |
|---------------------|-----------|----------------------|
| `Shift` | Define turno de trabalho (horário início/fim, dias da semana) | **PontoWR2 lê** Shift para calcular tolerâncias CLT |
| `EssentialsUserShift` | Associa usuário a um Shift | PontoWR2 usa para identificar escala do colaborador |
| `EssentialsAttendance` | Marcação de presença simples (check-in/out manual) | PontoWR2 **não duplica** — usa suas próprias `Marcacao` (REP-P) |
| `EssentialsLeave` | Afastamentos aprovados (férias, licença) | PontoWR2 respeita afastamentos como `Intercorrencia` tipo ausência |
| `EssentialsHoliday` | Feriados cadastrados | PontoWR2 usa para cálculo de HE em feriado (Art. 73 CLT) |
| `PayrollGroup` / `PayrollGroupTransaction` | Folha de pagamento | PontoWR2 alimenta com horas apuradas |

---

## Decisão

### 1. PontoWR2 NÃO duplica entidades do Essentials
- **Shift** do Essentials é a fonte de verdade para horário contratual
- PontoWR2 usa `escala_atual_id` no `Colaborador` para referenciar `Shift`
- PontoWR2 cria sua própria tabela `ponto_escalas` apenas para configurações específicas de ponto (tolerâncias, intervalos mínimos CLT) — campos que Essentials não tem

### 2. Separação de responsabilidades
| Responsabilidade | Módulo |
|------------------|--------|
| Definir horário contratual | Essentials (Shift) |
| Registrar batidas de REP-P/AFD | PontoWR2 (Marcacao — append-only) |
| Calcular HE, atrasos, banco de horas | PontoWR2 (ApuracaoService) |
| Aprovar/rejeitar afastamentos | Essentials (EssentialsLeave) |
| Gerar folha de pagamento | Essentials (Payroll) — alimentado por PontoWR2 |
| Feriados | Essentials (EssentialsHoliday) — lido pelo PontoWR2 |

### 3. Fluxo de integração
```
Essentials.Shift
    └─→ PontoWr2.Colaborador.escala_atual_id (FK para Shift)
    └─→ PontoWr2.ApuracaoService.calcular() usa horário do Shift

PontoWr2.ApuracaoService (resultado mensal)
    └─→ horas_extras → Essentials.PayrollGroupTransaction (lançamento)
    └─→ banco_horas_saldo → PontoWr2.BancoHoras (ledger próprio)

Essentials.EssentialsHoliday
    └─→ PontoWr2.ApuracaoService.isFeriado() para cálculo HE 100%

Essentials.EssentialsLeave (aprovado)
    └─→ PontoWr2.Intercorrencia (criada automaticamente via observer)
```

---

## Consequências

- **Positivo:** Evita dados duplicados. Essentials continua sendo a fonte de RH; PontoWR2 é especialista em conformidade legal de ponto.
- **Positivo:** Usuários do Essentials que não precisam de ponto REP-P continuam funcionando sem o PontoWR2.
- **Risco:** Se Essentials.Shift mudar estrutura em versão futura do UltimatePOS, PontoWR2 pode quebrar. Mitigação: observer no Shift que invalida cache de escalas.
- **Pendente:** Implementar observer EssentialsLeave → Intercorrencia (sessão futura).

---

> **Referências:** ADR 0004 (bridge colaborador), ADR 0007 (banco horas ledger), Portaria MTP 671/2021
