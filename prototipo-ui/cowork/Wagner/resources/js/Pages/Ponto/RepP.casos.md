---
id: resources-js-pages-ponto-repp-casos
casos: REP-P (celular) e validação · /ponto/rep-p
irmaos: RepP.charter.md (lei) · prototipo-ui/contrato/ponto-rep-p.contract.json (copy)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o comportamento é durável — o escopo e as regras de domínio não mudam quando a tela ganhar coluna nova.
owner: wagner
last_run: "—"
last_run_ci: "nenhum — tela F1, testes a escrever no F3"
---

# Casos de Uso & Aceite — REP-P (celular) e validação (`/ponto/rep-p`)

> **Âncora:** os UC derivam do charter irmão e das regras já existentes no módulo
> (`Modules/Ponto/Config/config.php`, enums de `lang/pt/ponto.php`, estados de `ApuracaoDia`,
> `MarcacaoService` append-only) — **nunca do .tsx**: teste derivado do código é tautológico.
>
> **Status:** ⬜ não verificado · 🧪 teste escrito, veredito pendente · ✅ passa · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Teste | Status |
|----|-------------|------|-------|--------|
| UC-REPP-01 | Anti-cheat bloqueia com motivo | must | — | ⬜ |
| UC-REPP-02 | Fora do geofence entra e sinaliza | must | — | ⬜ |
| UC-REPP-03 | NSR é sequencial | must `[T0]` | — | ⬜ |
| UC-REPP-04 | Selfie não vira PII persistida | must `[T0]` | — | ⬜ |
| UC-REPP-05 | Justificar cria pedido real | must | — | ⬜ |

---

## UC-REPP-01 · Anti-cheat bloqueia com motivo · `must`

- **Aceite:** Dado accuracy 780m · Quando tenta bater · Então bloqueia citando o limite de 500m; idem drift acima de 30s e selfie abaixo de 100KB.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-REPP-02 · Fora do geofence entra e sinaliza · `must`

- **Aceite:** Dado marcação em obra · Quando registra · Então a marcação existe e aparece na fila de validação do gestor.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-REPP-03 · NSR é sequencial · `must `[T0]``

- **Aceite:** Dado duas marcações no dia · Quando a segunda é registrada · Então o NSR é o anterior + 1, sem buraco.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-REPP-04 · Selfie não vira PII persistida · `must `[T0]``

- **Aceite:** Dado envio com selfie · Quando processa · Então o banco guarda hash e URI, e o log não contém base64.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-REPP-05 · Justificar cria pedido real · `must`

- **Aceite:** Dado justificativa enviada do celular · Quando o gestor abre Aprovações · Então o pedido está lá como PENDENTE.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.
