---
id: resources-js-pages-ponto-relatorios-index-casos
casos: Relatórios · /ponto/relatorios
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o comportamento é durável — o escopo e as regras de domínio não mudam quando a tela ganhar coluna nova.
owner: wagner
last_run: "—"
last_run_ci: "nenhum — tela F1, testes a escrever no F3"
---

# Casos de Uso & Aceite — Relatórios (`/ponto/relatorios`)

> **Âncora:** os UC derivam do charter irmão e das regras já existentes no módulo
> (`Modules/Ponto/Config/config.php`, enums de `lang/pt/ponto.php`, estados de `ApuracaoDia`,
> `MarcacaoService` append-only) — **nunca do .tsx**: teste derivado do código é tautológico.
>
> **Status:** ⬜ não verificado · 🧪 teste escrito, veredito pendente · ✅ passa · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Teste | Status |
|----|-------------|------|-------|--------|
| UC-RELA-01 | Relatório não implementado se declara | must | — | ⬜ |
| UC-RELA-02 | Legal é TXT no encoding do config | must | — | ⬜ |
| UC-RELA-03 | Espelho gera de verdade | must | — | ⬜ |

---

## UC-RELA-01 · Relatório não implementado se declara · `must`

- **Aceite:** Dado AFD (disponivel=false) · Quando o pedido é feito · Então a tela registra NAO_IMPLEMENTADO e não simula download.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-RELA-02 · Legal é TXT no encoding do config · `must`

- **Aceite:** Dado AFD/AFDT/AEJ · Quando o wizard abre · Então o formato é TXT posicional, sem opção de PDF, citando o encoding.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-RELA-03 · Espelho gera de verdade · `must`

- **Aceite:** Dado o Espelho (disponivel=true) e uma competência · Quando gera · Então sai o PDF mensal do colaborador (ou de todos).
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.
