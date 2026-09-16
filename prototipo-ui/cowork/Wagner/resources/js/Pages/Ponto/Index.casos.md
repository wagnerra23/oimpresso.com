---
id: resources-js-pages-ponto-index-casos
casos: Painel do Ponto · /ponto
irmaos: Index.charter.md (lei) · prototipo-ui/contrato/ponto-painel.contract.json (copy)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o comportamento é durável — o escopo e as regras de domínio não mudam quando a tela ganhar coluna nova.
owner: wagner
last_run: "—"
last_run_ci: "nenhum — tela F1, testes a escrever no F3"
---

# Casos de Uso & Aceite — Painel do Ponto (`/ponto`)

> **Âncora:** os UC derivam do charter irmão e das regras já existentes no módulo
> (`Modules/Ponto/Config/config.php`, enums de `lang/pt/ponto.php`, estados de `ApuracaoDia`,
> `MarcacaoService` append-only) — **nunca do .tsx**: teste derivado do código é tautológico.
>
> **Status:** ⬜ não verificado · 🧪 teste escrito, veredito pendente · ✅ passa · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Teste | Status |
|----|-------------|------|-------|--------|
| UC-INDE-01 | Painel bate com as abas | must | — | ⬜ |
| UC-INDE-02 | Sem apuração, empty state — não zero | must | — | ⬜ |
| UC-INDE-03 | KPI é escopado por business | must `[T0]` | — | ⬜ |
| UC-INDE-04 | Sem `ponto.access` não entra | must | — | ⬜ |

---

## UC-INDE-01 · Painel bate com as abas · `must`

- **Aceite:** Dado N intercorrências pendentes · Quando M são aprovadas em Aprovações · Então KPI, nota e fila mostram N−M na mesma sessão, sem recarregar.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-INDE-02 · Sem apuração, empty state — não zero · `must`

- **Aceite:** Dado competência sem apuração processada · Quando o Painel carrega · Então os totais mostram estado vazio explicando por quê, e não 00:00.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-INDE-03 · KPI é escopado por business · `must `[T0]``

- **Aceite:** Dado dois businesses com marcações · Quando o usuário de A abre o Painel · Então nenhum número inclui B (ADR 0093).
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-INDE-04 · Sem `ponto.access` não entra · `must`

- **Aceite:** Dado usuário sem a permissão · Quando pede /ponto · Então 403, sem vazar contagem no HTML.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.
