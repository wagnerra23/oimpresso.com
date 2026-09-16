---
id: resources-js-pages-ponto-escalas-index-casos
casos: Escalas · /ponto/escalas
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o comportamento é durável — o escopo e as regras de domínio não mudam quando a tela ganhar coluna nova.
owner: wagner
last_run: "—"
last_run_ci: "nenhum — tela F1, testes a escrever no F3"
---

# Casos de Uso & Aceite — Escalas (`/ponto/escalas`)

> **Âncora:** os UC derivam do charter irmão e das regras já existentes no módulo
> (`Modules/Ponto/Config/config.php`, enums de `lang/pt/ponto.php`, estados de `ApuracaoDia`,
> `MarcacaoService` append-only) — **nunca do .tsx**: teste derivado do código é tautológico.
>
> **Status:** ⬜ não verificado · 🧪 teste escrito, veredito pendente · ✅ passa · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Teste | Status |
|----|-------------|------|-------|--------|
| UC-ESCA-01 | Carga diária respeita a faixa | must | — | ⬜ |
| UC-ESCA-02 | Remoção avisa o impacto | must | — | ⬜ |
| UC-ESCA-03 | Tipo da escala é do enum | must | — | ⬜ |

---

## UC-ESCA-01 · Carga diária respeita a faixa · `must`

- **Aceite:** Dado 900 minutos · Quando salva · Então recusa (limite 60–600) com a mensagem.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-ESCA-02 · Remoção avisa o impacto · `must`

- **Aceite:** Dado escala com 3 colaboradores · Quando remove · Então confirma nomeando que eles perdem a referência.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-ESCA-03 · Tipo da escala é do enum · `must`

- **Aceite:** Dado tipo inválido no payload · Quando salva · Então 422 do FormRequest.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.
