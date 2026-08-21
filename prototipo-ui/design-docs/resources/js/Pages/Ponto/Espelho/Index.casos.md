---
id: resources-js-pages-ponto-espelho-index-casos
casos: Espelho — lista de colaboradores · /ponto/espelho
irmaos: Index.charter.md (lei) · prototipo-ui/contrato/ponto-espelho.contract.json (copy)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o comportamento é durável — o escopo e as regras de domínio não mudam quando a tela ganhar coluna nova.
owner: wagner
last_run: "—"
last_run_ci: "nenhum — tela F1, testes a escrever no F3"
---

# Casos de Uso & Aceite — Espelho — lista de colaboradores (`/ponto/espelho`)

> **Âncora:** os UC derivam do charter irmão e das regras já existentes no módulo
> (`Modules/Ponto/Config/config.php`, enums de `lang/pt/ponto.php`, estados de `ApuracaoDia`,
> `MarcacaoService` append-only) — **nunca do .tsx**: teste derivado do código é tautológico.
>
> **Status:** ⬜ não verificado · 🧪 teste escrito, veredito pendente · ✅ passa · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Teste | Status |
|----|-------------|------|-------|--------|
| UC-ESPE-01 | Quem não controla ponto não exibe apuração | must | — | ⬜ |
| UC-ESPE-02 | "Só com divergência" filtra pela competência escolhida | must | — | ⬜ |
| UC-ESPE-03 | Filtro sobrevive ao link | should | — | ⬜ |
| UC-ESPE-04 | Lista é escopada por business | must `[T0]` | — | ⬜ |

---

## UC-ESPE-01 · Quem não controla ponto não exibe apuração · `must`

- **Aceite:** Dado colaborador com `controla_ponto=false` · Quando a lista carrega · Então Trabalhado, HE, Saldo BH e divergências saem como —.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-ESPE-02 · "Só com divergência" filtra pela competência escolhida · `must`

- **Aceite:** Dado julho fechado sem divergência e agosto com 9 · Quando o filtro é ligado com julho selecionado · Então a lista vem vazia com empty state.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-ESPE-03 · Filtro sobrevive ao link · `should`

- **Aceite:** Dado filtros aplicados · Quando a URL é copiada e aberta noutra aba · Então os mesmos filtros valem (query string).
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-ESPE-04 · Lista é escopada por business · `must `[T0]``

- **Aceite:** Dado colaboradores em dois businesses · Quando o usuário de A lista · Então nenhum colaborador de B aparece.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.
