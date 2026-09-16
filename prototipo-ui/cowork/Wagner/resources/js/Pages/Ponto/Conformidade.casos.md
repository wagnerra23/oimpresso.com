---
id: resources-js-pages-ponto-conformidade-casos
casos: Conformidade CLT · /ponto/conformidade
irmaos: Conformidade.charter.md (lei) · prototipo-ui/contrato/ponto-fechamento.contract.json (copy)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o comportamento é durável — o escopo e as regras de domínio não mudam quando a tela ganhar coluna nova.
owner: wagner
last_run: "—"
last_run_ci: "nenhum — tela F1, testes a escrever no F3"
---

# Casos de Uso & Aceite — Conformidade CLT (`/ponto/conformidade`)

> **Âncora:** os UC derivam do charter irmão e das regras já existentes no módulo
> (`Modules/Ponto/Config/config.php`, enums de `lang/pt/ponto.php`, estados de `ApuracaoDia`,
> `MarcacaoService` append-only) — **nunca do .tsx**: teste derivado do código é tautológico.
>
> **Status:** ⬜ não verificado · 🧪 teste escrito, veredito pendente · ✅ passa · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Teste | Status |
|----|-------------|------|-------|--------|
| UC-CONF-01 | Cada apontamento cita a lei e os números | must | — | ⬜ |
| UC-CONF-02 | Limite vem do config | must | — | ⬜ |
| UC-CONF-03 | Contagem casa com o Fechamento | must | — | ⬜ |

---

## UC-CONF-01 · Cada apontamento cita a lei e os números · `must`

- **Aceite:** Dado intrajornada de 35 min · Quando o painel abre · Então mostra Art. 71, apurado 00:35, limite 60 min, colaborador, dia e atalho.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-CONF-02 · Limite vem do config · `must`

- **Aceite:** Dado `intrajornada_minima_minutos` alterado para 30 · Quando reapura · Então dias de 35 min saem da lista.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-CONF-03 · Contagem casa com o Fechamento · `must`

- **Aceite:** Dado N violações duras · Quando a pré-checagem do Fechamento conta · Então usa o mesmo N.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.
