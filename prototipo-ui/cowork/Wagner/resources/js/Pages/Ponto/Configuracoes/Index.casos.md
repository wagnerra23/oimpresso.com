---
id: resources-js-pages-ponto-configuracoes-index-casos
casos: Configurações e REPs · /ponto/configuracoes
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o comportamento é durável — o escopo e as regras de domínio não mudam quando a tela ganhar coluna nova.
owner: wagner
last_run: "—"
last_run_ci: "nenhum — tela F1, testes a escrever no F3"
---

# Casos de Uso & Aceite — Configurações e REPs (`/ponto/configuracoes`)

> **Âncora:** os UC derivam do charter irmão e das regras já existentes no módulo
> (`Modules/Ponto/Config/config.php`, enums de `lang/pt/ponto.php`, estados de `ApuracaoDia`,
> `MarcacaoService` append-only) — **nunca do .tsx**: teste derivado do código é tautológico.
>
> **Status:** ⬜ não verificado · 🧪 teste escrito, veredito pendente · ✅ passa · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Teste | Status |
|----|-------------|------|-------|--------|
| UC-CONF-01 | Config é somente-leitura | must | — | ⬜ |
| UC-CONF-02 | Identificador de REP tem 17 caracteres | must | — | ⬜ |
| UC-CONF-03 | Certificado ausente é dito | must | — | ⬜ |

---

## UC-CONF-01 · Config é somente-leitura · `must`

- **Aceite:** Dado a tela · Quando renderiza · Então não há campo editável, e o caminho de alteração (arquivo + config:clear) está declarado.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-CONF-02 · Identificador de REP tem 17 caracteres · `must`

- **Aceite:** Dado 12 caracteres · Quando cadastra · Então recusa citando o formato do Anexo I.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.

## UC-CONF-03 · Certificado ausente é dito · `must`

- **Aceite:** Dado `certificado_icp_path` vazio · Quando a tela carrega · Então mostra "Não" com a variável de ambiente que resolve.
- **Teste:** a escrever no F3.
- **Status: ⬜** não verificado.
