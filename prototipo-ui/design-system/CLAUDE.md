# Regras deste projeto (valem em toda conversa)

## Vocabulário das cópias — usar sempre estes nomes, nunca "o repo"

| nome | onde | o que é |
| --- | --- | --- |
| **vivo** | `resources/js/Components/**` no git | o código que roda pro cliente. Autoridade. |
| **espelho** | este projeto | vitrine derivada. NÃO é fonte (ADR 0239/0315/0299). |
| **carga publicada** | `prototipo-ui/design-system/` no git | cópia do espelho commitada no repo, só pra leitura. |

Dizer "o repo tem 11 domínios" é ambíguo e já embaralhou uma explicação: o vivo tinha 15 e a carga publicada 11. Toda afirmação nomeia a cópia.

## Antes de afirmar (as 6 regras que nasceram de erro medido)

1. **"0 alterados" ≠ "está tudo em dia".** A baseline (`sync-baseline-components.json`) é detector de movimento no **vivo**. Ela não vê o espelho nem a carga publicada. Igualdade entre cópias só se afirma lendo os dois lados.
2. **`[added]` no `github_compare` não prova ausência no base** quando as histórias divergiram — a ferramenta avisa e o aviso vale. Confirmar por `github_get_tree`. (Erro de 09/09, PR 7096.)
3. **Busca limitada não prova ausência.** Quando o resultado vem com nota de scan truncado, "No matches" é "não achei", não "não existe". Estreitar e repetir.
4. **Toda sonda roda um caso de sanidade antes do veredito** — inclusive busca de código. (Erro real: busquei `from "@/Components/…"` com aspas duplas num repo que usa simples; "não achei" virou o fato falso "o Ponto não usa o DS", errado em 21 arquivos.)
5. **"X não existe / está atrasado" exige ler 1 arquivo real e citar arquivo + linha.** Ausência em recibo/handoff não é medição.
6. **"A produção está atrás do protótipo" é hipótese, não default.** Testada contra leitura, falhou 2 de 2 (Fiscal 03/09, Ponto 09/09).

## Ao sincronizar

- Perguntar/receber **número de PR aberto**: sync que lê só o `main` é incompleto enquanto houver PR de design aberto.
- Separar no relato: **medido** (com sha/arquivo/linha) × **interpretado** × **decisão [W]**. Decisão de design nunca é aplicada por conta própria.
- Reescrever a baseline e o `## Last sync` do `github.md` no mesmo turno.

## Direção do fluxo (não inverter)

Decisão de design [W] → git (vivo) → **eu puxo pro espelho** → o espelho é o que se usa pra desenhar aqui.
A volta (espelho → carga publicada, via `ds-push.mjs --write`) é outro caminho, roda fora daqui, e **não bloqueia** o trabalho de design neste projeto.
