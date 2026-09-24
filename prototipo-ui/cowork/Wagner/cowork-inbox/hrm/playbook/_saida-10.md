---
sessao: "_saida-10"
thread: "10 · Folha — BLOQUEADA (D2 → projeto com ADR própria)"
dono: "[W]"
data: 2026-09-24
prefixo_tocado: nenhum arquivo de produto. Escritos só este recibo e a mudança do `status` da ADR-mãe (PR #7920)
base_lida: wagnerra23/oimpresso.com@main 4807395dc (2026-09-24)
---
# _saida-10

## Decisão [W], 2026-09-24
**(a) Ratificar a ADR-mãe da folha como está.** O arquivo é
`memory/decisions/proposals/2026-09-05-folha-com-encargos-modelo-e-fronteira.md`. A ratificação é
a mudança do `status` de `proposto` para `aceito` no
[PR #7920](https://github.com/wagnerra23/oimpresso.com/pull/7920), com a label
`adr-metadata-normalization`. **O merge desse PR pelo [W] é o ato de ratificação.**

As outras opções apresentadas foram (b), voltar à "folha gerencial" e reverter a D2, e (c), manter
a thread bloqueada sem ratificar. As duas foram descartadas.

## Premissa corrigida: a ADR não precisava ser escrita
A thread pedia *"abrir a ADR folha com encargos"*, e o índice (§1) afirma que os planos da emenda
[W] "não existem". **As duas afirmações estão velhas.** Medido em 4807395:
- A ADR-mãe existe desde 2026-09-05. O [W] mergeou o #6881 em 2026-09-05 22:50 UTC. O texto dela diz
  que o merge é a ratificação, mas a mudança formal do `status` (receita do
  `memory/decisions/README.md`) nunca foi feita, e por isso ela ficou `proposto`.
- O estudo `memory/sessions/2026-09-05-arte-folha-encargos-br.md` está no main desde o #6877.
- A ADR cita a 0014 (`related`, §1.3). A prova da thread no §7 está satisfeita no conteúdo.

Escrever outra ADR seria duplicar um tema que já tem dono (LC-19).

## O que a ratificação fixa (resumo; vale o texto da ADR)
- **Modelo de verba:** rubrica × natureza × 3 flags de incidência independentes (INSS, IRRF e FGTS)
  × vigência `AAAA-MM`. As bases são derivadas, e cada item carrega `competencia_pagamento` e
  `competencia_apuracao`.
- **Fronteira:** a folha **calcula e imprime, mas não declara**. eSocial, guias, DCTFWeb e RAIS
  ficam fora. Onde o cálculo divergir do contador, vale o contador, e a folha concilia por rubrica.
- **Caminho:** cálculo feito por nós; o transporte fiscal é terceirizado só se a declaração entrar
  no escopo (mesmo desenho do NfeBrasil).
- **Ordem (§9):** recálculo server-side → casos-verdade externos → contrato do modelo de verba →
  tabela legal por competência → motor mensalista → gate de gravação com antes→depois → conciliação
  com o contador, no biz=1.

## Estado dos passos da §9, medido
1. **Recálculo server-side do total: feito**, no
   [#6880](https://github.com/wagnerra23/oimpresso.com/pull/6880) (`PayrollTotalCalculator` +
   `PayrollTotalDivergenteException` + `tests/Feature/Calculo/CalculoValorPayrollTest.php`).
2. a 7. **Não iniciados.** É o próximo trabalho do projeto Folha, fora deste playbook.

## A thread segue sem Page
A ratificação **não destrava nenhuma tela**. A ADR não decide UI (§8). As ondas 7 e 8 do
EXPORT-HRM (lotes e contracheque) continuam mortas até existirem o modelo de verba e o motor.
Também há um bloqueio de sequência que a ADR já registra (§6): o Ponto tinha **0 marcações em
produção** (medição de 2026-08-03 no `Ponto/SPEC.md`), e os geradores do `ReportService` são stubs.
O motor não tem insumo até a integração Ponto × Essentials avançar.

## Não decidido
- **Rótulo transitório "folha gerencial"** na tela atual `/hrm/payroll`, pedido no item 2 desta
  thread. Foi apresentado com recomendação de "sim", e o [W] respondeu só a pergunta da ADR. Fica
  pendente. Se for aprovado, é uma mudança de copy de 1 arquivo em PR próprio.

## RESIDUO-6, registrada junto porque o índice ainda a mostra aberta
As 5 chaves (`grace_*` ×4 + `is_location_required`) já estão **resolvidas e executadas**:
- Em 2026-09-23 o [W] respondeu "migram para o Ponto" (`_saida-07a.md`).
- Na execução da thread 09 (2026-09-24), a resposta virou **aposentar, sem migrar** (`_saida-09.md`
  item 6). No Ponto, a lei fixa as duas regras: a tolerância é do Art. 58 §1º da CLT e a
  geolocalização é obrigatória no REP-P (Portaria 671).
- O registro no código está em `EssentialsSettingsController.php:25` ("APOSENTADAS em 2026-09-24
  ([W]; ADR 0014 emenda)"), e o teste é o UC-HRM-PRES-05 do `HrmPresencaCedeAoPontoTest.php`.

O índice do Cowork recebe a marcação `respondida: true`.

## Pedido ao índice do Cowork (Lei 4: esta thread não escreve nele)
- `D2`: acrescentar a ratificação da ADR-mãe (PR #7920).
- `RESIDUO-6`: `respondida: true`, com a resposta acima.
- Thread `10`: trocar `bloqueio` por "ADR ratificada; sem Page até o modelo de verba e o motor" e
  pôr a prova `{tipo: contem, path: memory/decisions/proposals/2026-09-05-folha-com-encargos-modelo-e-fronteira.md, padrao: "status: aceito"}`.
- §1: remover a afirmação de que os planos da emenda não existem.

## Pedido literal
*"Thread bloqueada do playbook HRM: 10 (Folha — BLOQUEADA). Também está aberta a RESIDUO-6. Apresente,
recomende, registre."* A resposta do [W] foi `a`.
