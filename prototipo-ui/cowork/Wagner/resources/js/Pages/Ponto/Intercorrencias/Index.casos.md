---
id: resources-js-pages-ponto-intercorrencias-index-casos
casos: Intercorrências · /ponto/intercorrencias
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o comportamento é durável — o escopo e as regras de domínio não mudam quando a tela ganhar coluna nova.
owner: wagner
last_run: "—"
last_run_ci: "nenhum — tela F1, testes a escrever no F3"
---

# Casos de Uso & Aceite — Intercorrências (`/ponto/intercorrencias`)

> **Âncora:** os UC derivam do charter irmão e das regras já existentes no módulo
> (`Modules/Ponto/Config/config.php`, enums de `lang/pt/ponto.php`, estados de `ApuracaoDia`,
> `MarcacaoService` append-only) — **nunca do .tsx**: teste derivado do código é tautológico.
>
> **Status:** ⬜ não verificado · 🧪 teste escrito, veredito pendente · ✅ passa · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Teste | Status |
|----|-------------|------|-------|--------|
| UC-INTE-01 | Sem dia-todo, janela é obrigatória | must | — | ⬜ |
| UC-INTE-02 | Só rascunho edita e submete | must | — | ⬜ |
| UC-INTE-03 | Justificativa tem mínimo de 10 caracteres | must | — | ⬜ |
| UC-INTE-04 | Nada imprime null | should | — | ⬜ |

---

## UC-INTE-01 · Sem dia-todo, janela é obrigatória · `must`

- **Aceite:** Dado formulário sem "dia todo" · Quando início ou fim ficam vazios · Então o envio é bloqueado com o motivo.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-INTE-02 · Só rascunho edita e submete · `must`

- **Aceite:** Dado intercorrência APROVADA · Quando a lista renderiza · Então não há Editar nem Submeter para ela.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-INTE-03 · Justificativa tem mínimo de 10 caracteres · `must`

- **Aceite:** Dado justificativa "ok" · Quando salva · Então recusa com a mensagem do domínio.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-INTE-04 · Nada imprime null · `should`

- **Aceite:** Dado registro dia-todo (sem janela) · Quando aparece em lista, ficha e Painel · Então mostra "Dia todo", nunca "null – null".
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.
