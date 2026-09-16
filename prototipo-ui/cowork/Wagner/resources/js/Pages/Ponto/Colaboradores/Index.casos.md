---
id: resources-js-pages-ponto-colaboradores-index-casos
casos: Colaboradores do ponto · /ponto/colaboradores
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o comportamento é durável — o escopo e as regras de domínio não mudam quando a tela ganhar coluna nova.
owner: wagner
last_run: "—"
last_run_ci: "nenhum — tela F1, testes a escrever no F3"
---

# Casos de Uso & Aceite — Colaboradores do ponto (`/ponto/colaboradores`)

> **Âncora:** os UC derivam do charter irmão e das regras já existentes no módulo
> (`Modules/Ponto/Config/config.php`, enums de `lang/pt/ponto.php`, estados de `ApuracaoDia`,
> `MarcacaoService` append-only) — **nunca do .tsx**: teste derivado do código é tautológico.
>
> **Status:** ⬜ não verificado · 🧪 teste escrito, veredito pendente · ✅ passa · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Teste | Status |
|----|-------------|------|-------|--------|
| UC-COLA-01 | PIS ausente é visível e filtrável | must | — | ⬜ |
| UC-COLA-02 | Nome e e-mail são somente-leitura | must | — | ⬜ |
| UC-COLA-03 | Trocar escala não reescreve o passado | must `[T0]` | — | ⬜ |

---

## UC-COLA-01 · PIS ausente é visível e filtrável · `must`

- **Aceite:** Dado colaborador ativo sem PIS · Quando o filtro "sem PIS" é usado · Então ele aparece, e a linha sinaliza o impacto no AFD.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-COLA-02 · Nome e e-mail são somente-leitura · `must`

- **Aceite:** Dado a tela de configuração · Quando renderiza · Então nome/e-mail aparecem sem campo editável, com a origem (HRM) declarada.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-COLA-03 · Trocar escala não reescreve o passado · `must `[T0]``

- **Aceite:** Dado apuração já processada com a escala antiga · Quando a escala muda · Então os dias já apurados permanecem como estavam.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.
