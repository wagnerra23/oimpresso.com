---
id: resources-js-pages-ponto-bancohoras-index-casos
casos: Banco de horas · /ponto/banco-horas
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o comportamento é durável — o escopo e as regras de domínio não mudam quando a tela ganhar coluna nova.
owner: wagner
last_run: "—"
last_run_ci: "nenhum — tela F1, testes a escrever no F3"
---

# Casos de Uso & Aceite — Banco de horas (`/ponto/banco-horas`)

> **Âncora:** os UC derivam do charter irmão e das regras já existentes no módulo
> (`Modules/Ponto/Config/config.php`, enums de `lang/pt/ponto.php`, estados de `ApuracaoDia`,
> `MarcacaoService` append-only) — **nunca do .tsx**: teste derivado do código é tautológico.
>
> **Status:** ⬜ não verificado · 🧪 teste escrito, veredito pendente · ✅ passa · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Teste | Status |
|----|-------------|------|-------|--------|
| UC-BANC-01 | Ajuste é lançamento, não edição | must `[T0]` | — | ⬜ |
| UC-BANC-02 | Ajuste sem observação é recusado | must | — | ⬜ |
| UC-BANC-03 | Saldo é a soma do extrato | must | — | ⬜ |
| UC-BANC-04 | Teto e piso do acordo aparecem | should | — | ⬜ |

---

## UC-BANC-01 · Ajuste é lançamento, não edição · `must `[T0]``

- **Aceite:** Dado saldo com 5 movimentos · Quando um ajuste de −120 é registrado · Então há 6 movimentos e nenhum dos 5 mudou.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-BANC-02 · Ajuste sem observação é recusado · `must`

- **Aceite:** Dado minutos preenchidos e observação vazia · Quando registra · Então recusa e explica que a observação é obrigatória.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-BANC-03 · Saldo é a soma do extrato · `must`

- **Aceite:** Dado o extrato do colaborador · Quando a tela mostra o saldo · Então saldo = soma dos minutos dos movimentos.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-BANC-04 · Teto e piso do acordo aparecem · `should`

- **Aceite:** Dado config com 200h/−40h · Quando o detalhe abre · Então os limites estão visíveis junto do saldo.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.
