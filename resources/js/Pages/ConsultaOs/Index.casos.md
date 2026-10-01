---
id: resources-js-pages-consulta-os-index-casos
casos: Consulta pública de OS · /consulta-os
irmaos: Index.charter.md (lei) · Index.tsx (código) · tests/Feature/ConsultaOs/ConsultaOsIndexContratoTest.php (defesa)
tecnica: Caso de uso = o que o cliente final faz + critério de aceite verificável (Dado/Quando/Então)
por_que: é a única tela do ERP que fala com o cliente final sem login — o que ela expõe e o que ela recusa não pode mudar sem alguém ver.
owner: wagner
related_us: [US-CONSULTA-001]
last_run: "2026-09-30"
last_run_ci: "0 UC executado — casos nascem neste PR; veredito pendente da lane PHP / Pest (Unit) (ci-sqlite-pest.list)"
---

# Casos de Uso & Aceite — Consulta pública de OS (`/consulta-os`)

> **Âncora:** os UC derivam do [charter](Index.charter.md) — Goals, Non-Goals e Anti-hooks —,
> **nunca do `Index.tsx`** ([proibicoes §5](../../../../memory/proibicoes.md) 2026-06-05). O
> [SPEC](../../../../memory/requisitos/ConsultaOs/SPEC.md) só traz roadmap (US-CONSULTA-001..003,
> todas `pendente`); não há SDD do módulo.
>
> **Estado do módulo:** mock-only. A busca lê `MockConsultaOsRepository` (4 OS estáticas) até a
> US-CONSULTA-001 trocar a fonte por `Modules/Repair`. Estes contratos valem para a fonte real
> também: trocar o repositório não pode mudar o que o público vê.
>
> **Multi-tenant:** rota pública, sem sessão e sem `business_id` — hoje não há dado de negócio
> para isolar, então não há teste cross-tenant. Quando a US-CONSULTA-001 entrar, o lookup por
> protocolo resolve o `business_id` e este arquivo ganha o UC de isolamento.

**Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC, veredito pendente da lane ·
⬜ não verificado · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora (charter) | Status |
|----|-------------|------|------------------|--------|
| UC-COS-01 | Portal abre sem login | must | Non-Goals "NÃO exige login" · Mission | 🧪 |
| UC-COS-02 | Busca por número mostra o status daquela OS | must | Goals "campo de busca" + "exibe o status" | 🧪 |
| UC-COS-03 | OS não encontrada: resposta clara, sem pista | must | Goals "não encontrada claro" · UX "sem jargão" | 🧪 |
| UC-COS-04 | Não expõe dado interno nem financeiro | must `[T0]` | Non-Goals "NÃO expõe dados internos/financeiros" | 🧪 |
| UC-COS-05 | Não lista OS — só a consultada | must | Non-Goals "NÃO lista todas as OS" | 🧪 |
| UC-COS-06 | Só leitura: nada grava, nada edita | must | Non-Goals "NÃO permite editar/cancelar" · Anti-hooks "NÃO grava nada em GET" | 🧪 |
| UC-COS-07 | Não deixa enumerar OS | must `[T0]` | Anti-hooks "NÃO indexa/expõe OS por enumeração" | 🧪 |

Teste de todos: [`ConsultaOsIndexContratoTest`](../../../../tests/Feature/ConsultaOs/ConsultaOsIndexContratoTest.php),
lane `PHP / Pest (Unit)` do `ci.yml` (via `.github/ci-sqlite-pest.list`). Nenhum status aqui é
afirmação de verde: este PR não rodou teste local ([ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).

---

## UC-COS-01 · Portal abre sem login · `must`

- **Persona:** cliente final com o link `/consulta-os` que o vendedor mandou.
- **Aceite:** Dado um visitante sem sessão · Quando abre `/consulta-os` · Então recebe 200 e a
  tela `ConsultaOs/Index`, sem ser mandado ao login.
- **Fora do teste:** "sem AppShellV2/Sidebar" é propriedade do render no browser; o Pest prova
  só o lado do servidor.
- **Status:** 🧪

## UC-COS-02 · Busca por número mostra o status daquela OS · `must`

- **Aceite:** Dado duas OS existentes em estágios diferentes · Quando o cliente busca cada número ·
  Então cada resposta traz `found: true`, o próprio número em `os.id` e um `os.stage` não vazio — e
  os dois estágios diferem (caso discriminante: um status fixo passaria num teste de uma OS só).
- **Status:** 🧪

## UC-COS-03 · OS não encontrada: resposta clara, sem pista · `must`

- **Aceite:** Dado um número que não existe · Quando o cliente busca · Então recebe 404 com corpo
  exatamente `{"found": false}` — nenhuma sugestão de número válido, nenhuma mensagem técnica de
  servidor. A frase amigável ("OS não encontrada") é da tela, não da API.
- **Status:** 🧪

## UC-COS-04 · Não expõe dado interno nem financeiro · `must` `[T0]`

- **Aceite:** Dado qualquer OS encontrada · Quando a busca devolve o payload · Então nenhuma chave,
  em qualquer nível (inclusive dentro de `items`), é `business_id`, preço/valor/total, custo,
  lucro/margem ou CPF/CNPJ.
- **Controle positivo:** o teste prova antes que a varredura acha uma chave proibida aninhada —
  sem isso, "nenhuma chave achada" seria vácuo.
- **Pendente do charter:** a lista do que o público **pode** ver não está decidida (charter
  §Pendências: "confirmar o que exatamente é exposto"). Hoje o mock devolve também nome de
  contato, vendedor e designer. Este UC trava o que o charter já proíbe; a lista positiva é
  decisão [W].
- **Status:** 🧪

## UC-COS-05 · Não lista OS — só a consultada · `must`

- **Aceite:** Dado um visitante · Quando chama a busca sem número (ou com número vazio) · Então
  recebe 422, não uma lista. Com um número válido, a resposta é **uma** OS (um mapa com `id`), não
  uma coleção.
- **Status:** 🧪

## UC-COS-06 · Só leitura: nada grava, nada edita · `must`

- **Aceite:** Dado as rotas públicas do portal (`consulta-os.index`, `consulta-os.buscar`) ·
  Então as duas existem e aceitam só `GET`/`HEAD` — não há verbo de escrita exposto ao público.
- **Status:** 🧪

## UC-COS-07 · Não deixa enumerar OS · `must` `[T0]`

- **Aceite:** Dado as duas rotas públicas · Então ambas têm `throttle:` · E um número fora do
  formato (com símbolos, ou com mais de 20 caracteres) é recusado com 422 · E, como controle, um
  número válido no mesmo endpoint é aceito.
- **Status:** 🧪

---

## Fora deste arquivo (sem contrato verificável ainda)

- [BACKLOG] Copy da tela em PT-BR e sem jargão técnico — o charter pede ("mensagem sem jargão
  técnico"), mas não fixa texto literal e não há spec Playwright desta tela. Sem texto contratado,
  um teste leria o `.tsx`, o que é tautológico.
- [BACKLOG] "Sem CTA loud" (pedido da ficha do playbook) — sem fonte no charter nem em PT. Fica
  para [W] decidir se vira Non-Goal.
