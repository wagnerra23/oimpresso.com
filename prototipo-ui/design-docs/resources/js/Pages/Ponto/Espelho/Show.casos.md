---
id: resources-js-pages-ponto-espelho-show-casos
casos: Espelho individual · /ponto/espelho/{colaborador}
irmaos: Show.charter.md (lei) · prototipo-ui/contrato/ponto-espelho.contract.json (copy)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o comportamento é durável — o escopo e as regras de domínio não mudam quando a tela ganhar coluna nova.
owner: wagner
last_run: "—"
last_run_ci: "nenhum — tela F1, testes a escrever no F3"
---

# Casos de Uso & Aceite — Espelho individual (`/ponto/espelho/{colaborador}`)

> **Âncora:** os UC derivam do charter irmão e das regras já existentes no módulo
> (`Modules/Ponto/Config/config.php`, enums de `lang/pt/ponto.php`, estados de `ApuracaoDia`,
> `MarcacaoService` append-only) — **nunca do .tsx**: teste derivado do código é tautológico.
>
> **Status:** ⬜ não verificado · 🧪 teste escrito, veredito pendente · ✅ passa · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Teste | Status |
|----|-------------|------|-------|--------|
| UC-ESPE-01 | Anular não altera a original | must `[T0]` | — | ⬜ |
| UC-ESPE-02 | Competência fechada não anula pela tela | must `[T0]` | — | ⬜ |
| UC-ESPE-03 | Marcação ímpar aparece como divergência | must | — | ⬜ |
| UC-ESPE-04 | Folha impressa mantém o dado do mês | must | — | ⬜ |

---

## UC-ESPE-01 · Anular não altera a original · `must `[T0]``

- **Aceite:** Dado uma marcação com NSR X · Quando é anulada · Então X continua no extrato marcada como anulada e entra um registro de anulação com NSR próprio e hash.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-ESPE-02 · Competência fechada não anula pela tela · `must `[T0]``

- **Aceite:** Dado competência fechada · Quando o drawer do dia abre · Então Anular está desabilitado com o motivo.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-ESPE-03 · Marcação ímpar aparece como divergência · `must`

- **Aceite:** Dado dia com 3 marcações · Quando o espelho carrega · Então o dia é DIVERGENCIA e a célula informa "ímpar".
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-ESPE-04 · Folha impressa mantém o dado do mês · `must`

- **Aceite:** Dado mês com 1 falta e 2 divergências · Quando imprime · Então a folha traz as 15 colunas, os totais, o DSR e a divergência destacada.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.
