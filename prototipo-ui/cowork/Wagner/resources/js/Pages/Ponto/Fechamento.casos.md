---
id: resources-js-pages-ponto-fechamento-casos
casos: Fechamento da competência · /ponto/fechamento
irmaos: Fechamento.charter.md (lei) · prototipo-ui/contrato/ponto-fechamento.contract.json (copy)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o comportamento é durável — a ordem "checar → consolidar → fechar" e a trava de edição não mudam quando a tela ganhar coluna nova.
owner: wagner
last_run: "—"
last_run_ci: "nenhum — tela F1, testes a escrever no F3"
---

# Casos de Uso & Aceite — Fechamento da competência (`/ponto/fechamento`)

> **Âncora:** os UC derivam do charter irmão e das regras já existentes no módulo
> (`Modules/Ponto/Config/config.php`, estados de `ApuracaoDia`, `MarcacaoService` append-only) —
> **nunca do .tsx**: teste derivado do código é tautológico.
>
> **Status:** ⬜ não verificado (nenhum teste existe ainda) · 🧪 teste escrito, veredito pendente ·
> ✅ passa · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Regra de origem | Teste | Status |
|----|-------------|------|-----------------|-------|--------|
| UC-PTF-01 | Consolidar é impossível com bloqueio grave aberto | must | charter §Goals | — | ⬜ |
| UC-PTF-02 | Consolidar com exceções exige confirmação e registra autor | must | charter §Goals | — | ⬜ |
| UC-PTF-03 | Competência fechada recusa qualquer edição de marcação | must `[T0]` | Portaria 671/2021 Art. 85 · `marcacao.forcar_append_only` | — | ⬜ |
| UC-PTF-04 | Pré-checagem só conta o que é da competência selecionada | must | charter §Non-Goals | — | ⬜ |
| UC-PTF-05 | Consolidar não recalcula apuração | must | charter §Non-Goals | — | ⬜ |
| UC-PTF-06 | Contadores batem com as abas de origem | should | charter §Anti-hooks | — | ⬜ |
| UC-PTF-07 | Violação dura de CLT aparece com o artigo e o caso | must | Art. 66/71/59 CLT | — | ⬜ |
| UC-PTF-08 | Fechamento é escopado por business | must `[T0]` | ADR 0093 | — | ⬜ |

---

## UC-PTF-01 · Consolidar é impossível com bloqueio grave aberto · `must`

- **Persona:** Eliana, fechando agosto na sexta à tarde.
- **Aceite:** Dado uma competência com ≥1 dia em `DIVERGENCIA` **ou** ≥1 intercorrência
  `PENDENTE`/`RASCUNHO` da competência · Quando ela abre o fechamento · Então o botão
  `Consolidar apuração` está desabilitado, o motivo aparece no título do botão, e cada bloqueio
  lista grau `bloqueia` com atalho para a tela que resolve.
- **Regressão que defende:** consolidar com divergência carimba jornada errada no espelho, e o AFD
  gerado depois sai fiel ao erro — a fiscalização vê o dado consolidado, não a intenção.

## UC-PTF-02 · Consolidar com exceções exige confirmação e registra autor · `must`

- **Aceite:** Dado bloqueio grave aberto · Quando o usuário aciona `Consolidar com exceções` e
  confirma · Então a competência vai para `consolidado`, o número de exceções fica visível na tela
  com autor e data, e os bloqueios **continuam listados** (não são apagados pela exceção).
- **E:** cancelar a confirmação **não** muda o estado.

## UC-PTF-03 · Competência fechada recusa qualquer edição de marcação · `must` `[T0]`

- **Aceite:** Dado competência `fechado` · Quando qualquer caminho tenta alterar marcação do mês ·
  Então a ação é recusada e a única correção oferecida é **anulação + nova marcação**, cada uma com
  NSR próprio e hash `sha256`.
- **Contrato:** `marcacao.forcar_append_only = true` · Portaria MTP 671/2021 Art. 85.
- **Na tela:** o botão `Anular` do drawer do dia fica desabilitado com o motivo "Competência fechada".

## UC-PTF-04 · Pré-checagem só conta o que é da competência selecionada · `must`

- **Aceite:** Dado intercorrências de agosto e uma importação `PROCESSANDO` de 20/08 · Quando a
  competência selecionada é **julho** · Então nenhuma delas aparece como bloqueio de julho.
- **Regressão que defende:** foi o defeito real da primeira versão desta tela (contagem global).

## UC-PTF-05 · Consolidar não recalcula apuração · `must`

- **Aceite:** Dado uma apuração com valores X · Quando a competência é consolidada · Então nenhum
  minuto de trabalhado/HE/BH muda; só o estado dos dias avança. Reapuração é `ReapurarDiaJob`,
  disparado por ação explícita.

## UC-PTF-06 · Contadores batem com as abas de origem · `should`

- **Aceite:** Dado N intercorrências pendentes · Quando o operador aprova M delas em Aprovações ·
  Então o bloqueio do fechamento, o badge da aba e o KPI do Painel mostram N−M **na mesma sessão**,
  sem recarregar a página.
- **Regressão que defende:** estado duplicado por aba (ocorreu duas vezes no F1).

## UC-PTF-07 · Violação dura de CLT aparece com o artigo e o caso · `must`

- **Aceite:** Dado um dia com intrajornada de 35 min · Quando o painel de Conformidade é aberto ·
  Então a verificação "Intrajornada abaixo do mínimo" mostra `Art. 71 CLT — 60 min acima de 6h`,
  o apurado (`00:35`), o limite (`60 min`), o colaborador, o dia e o atalho para o espelho.
- **E:** o mesmo vale para interjornada < 11h (Art. 66), HE > 2h/dia (Art. 59) e NSR fora de ordem
  (Portaria 671/2021 Anexo I).

## UC-PTF-08 · Fechamento é escopado por business · `must` `[T0]`

- **Aceite:** Dado competências de dois businesses · Quando o usuário do business A fecha agosto ·
  Então nada do business B muda de estado, e a pré-checagem de A nunca conta pendência de B.
- **Contrato:** ADR 0093 (multi-tenant Tier 0).
