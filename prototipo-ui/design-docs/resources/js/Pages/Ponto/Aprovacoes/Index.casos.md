---
id: resources-js-pages-ponto-aprovacoes-index-casos
casos: Fila de aprovações · /ponto/aprovacoes
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o comportamento é durável — o escopo e as regras de domínio não mudam quando a tela ganhar coluna nova.
owner: wagner
last_run: "—"
last_run_ci: "nenhum — tela F1, testes a escrever no F3"
---

# Casos de Uso & Aceite — Fila de aprovações (`/ponto/aprovacoes`)

> **Âncora:** os UC derivam do charter irmão e das regras já existentes no módulo
> (`Modules/Ponto/Config/config.php`, enums de `lang/pt/ponto.php`, estados de `ApuracaoDia`,
> `MarcacaoService` append-only) — **nunca do .tsx**: teste derivado do código é tautológico.
>
> **Status:** ⬜ não verificado · 🧪 teste escrito, veredito pendente · ✅ passa · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Teste | Status |
|----|-------------|------|-------|--------|
| UC-APRO-01 | Rejeição exige motivo | must | — | ⬜ |
| UC-APRO-02 | Lote só pega PENDENTE | must | — | ⬜ |
| UC-APRO-03 | Decisão reapura o dia | must | — | ⬜ |
| UC-APRO-04 | Só quem pode aprovar vê a ação | must | — | ⬜ |

---

## UC-APRO-01 · Rejeição exige motivo · `must`

- **Aceite:** Dado seleção de pendentes · Quando rejeita sem motivo · Então nada muda e a tela diz o que falta.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-APRO-02 · Lote só pega PENDENTE · `must`

- **Aceite:** Dado linhas aprovada/rejeitada na página · Quando "selecionar pendentes" é usado · Então só as pendentes entram no lote.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-APRO-03 · Decisão reapura o dia · `must`

- **Aceite:** Dado intercorrência que impacta apuração · Quando aprovada · Então o dia entra na fila de reapuração (job), não é recalculado na request.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-APRO-04 · Só quem pode aprovar vê a ação · `must`

- **Aceite:** Dado usuário sem `ponto.aprovacoes.manage` · Quando abre a fila · Então vê a lista somente-leitura, sem botões de decisão.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.
