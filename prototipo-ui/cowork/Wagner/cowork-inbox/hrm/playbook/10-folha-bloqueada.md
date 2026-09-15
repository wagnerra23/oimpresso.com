---
sessao: "10"
titulo: Folha de pagamento — BLOQUEADA (D2: folha completa com encargos = projeto com ADR própria)
dono: "[W]"
base: 159e572dd448
prefixo: nenhum neste playbook. A ADR nova e o projeto vivem fora daqui.
nao_toca: PayrollController (60 KB, NÃO lido) · EssentialsAllowanceAndDeductionController · payroll/*.blade.php · Transaction type=payroll
depende: D2 (respondida 2026-09-05: folha COMPLETA, com INSS/IRRF/FGTS/13º/férias)
---
# 10 · Folha — bloqueada, de propósito

## Por que não há thread de Page aqui
[W] respondeu D2 como **folha completa com encargos**. Isso reabre o que `Folha.charter.md` e o PEDIDO-CL declaravam fora de escopo e muda a natureza do trabalho: tabela legal que muda por ato do governo (manutenção perpétua), eSocial, e a regra mestre de `memory/proibicoes.md` — mexer em **VALOR** exige dupla prova por caminhos independentes e impacto antes→depois aprovado. **Isto é projeto com ADR própria**, não uma onda de tela. As ondas 7 e 8 do EXPORT-HRM-2026-09-04 (Folha — lotes · lote/contracheque) **não se executam** até a ADR existir.

## O que continua verdadeiro (do charter, para quem abrir a ADR)
F1 um contracheque por colaborador por competência · F2 lote `draft` → `final`, só `draft` exclui · F3 fechar com notificar dispara `PayrollNotification` · F4 situação de pagamento derivada dos itens · F5 comissão de venda e de meta calculadas na geração · F6 ganhos/deduções recorrentes fixo ou % · F7 prefixo `payroll_ref_no_prefix` · F8 nada recalcula após gerar. E, por D1: **a hora vem do Ponto**, não de `essentials_attendances`.

## O que esta thread pede
```
[W]  1) abrir (ou mandar abrir) a ADR "folha com encargos" — escopo, tabelas legais, fonte da hora (Ponto), gate de VALOR
     2) decidir o rótulo transitório da folha ATUAL: "folha gerencial" (como o DRE já faz) até a ADR aterrissar
[CL] 3) nada de Page até a ADR. Se alguém pedir "só a lista de lotes em React": é onda morta — apontar esta thread
PARAR SE : qualquer PR tocar cálculo de valor da folha sem a ADR → parar (proibicoes.md VALOR)
```

## Prova
- ADR nova existe em `memory/decisions/` e cita a 0014 · `_saida-10.md` com o link
- Enquanto não existir: placar mostra "10 · bloqueada por D2 (ADR ausente)" — **não** conta como ausente por omissão
