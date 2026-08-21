---
id: resources-js-pages-ponto-importacoes-index-casos
casos: Importações AFD/AFDT · /ponto/importacoes
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o comportamento é durável — o escopo e as regras de domínio não mudam quando a tela ganhar coluna nova.
owner: wagner
last_run: "—"
last_run_ci: "nenhum — tela F1, testes a escrever no F3"
---

# Casos de Uso & Aceite — Importações AFD/AFDT (`/ponto/importacoes`)

> **Âncora:** os UC derivam do charter irmão e das regras já existentes no módulo
> (`Modules/Ponto/Config/config.php`, enums de `lang/pt/ponto.php`, estados de `ApuracaoDia`,
> `MarcacaoService` append-only) — **nunca do .tsx**: teste derivado do código é tautológico.
>
> **Status:** ⬜ não verificado · 🧪 teste escrito, veredito pendente · ✅ passa · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Teste | Status |
|----|-------------|------|-------|--------|
| UC-IMPO-01 | Duplicado por hash é recusado | must | — | ⬜ |
| UC-IMPO-02 | Erro por linha é rastreável | must | — | ⬜ |
| UC-IMPO-03 | Processamento é assíncrono | must | — | ⬜ |
| UC-IMPO-04 | Importação é escopada por business | must `[T0]` | — | ⬜ |

---

## UC-IMPO-01 · Duplicado por hash é recusado · `must`

- **Aceite:** Dado arquivo já importado · Quando é enviado de novo · Então recusa citando o hash, sem criar marcação.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-IMPO-02 · Erro por linha é rastreável · `must`

- **Aceite:** Dado AFD com PIS não cadastrado · Quando processa · Então estado CONCLUIDA_COM_ERROS e a amostra mostra linha, NSR e motivo.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-IMPO-03 · Processamento é assíncrono · `must`

- **Aceite:** Dado upload aceito · Quando a request retorna · Então o estado é PENDENTE/PROCESSANDO e o job está enfileirado.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-IMPO-04 · Importação é escopada por business · `must `[T0]``

- **Aceite:** Dado dois businesses · Quando A importa · Então nenhuma marcação cai em B.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.
