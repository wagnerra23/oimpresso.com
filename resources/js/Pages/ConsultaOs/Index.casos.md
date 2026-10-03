---
id: resources-js-pages-consulta-os-index-casos
casos: Consulta pública de OS · /consulta-os
irmaos: Index.charter.md (lei) · Index.tsx (código) · tests/Feature/ConsultaOs/ConsultaOsIndexContratoTest.php (defesa)
tecnica: Caso de uso = o que o cliente final faz + critério de aceite verificável (Dado/Quando/Então)
por_que: é a única tela do ERP que fala com o cliente final sem login — o que ela expõe e o que ela recusa não pode mudar sem alguém ver.
owner: wagner
related_us: [US-CONSULTA-001]
last_run: "2026-10-02"
last_run_ci: "0 UC executado — UC-COS-08..11 nascem e UC-COS-01..07 mudam neste PR (fonte real do Repair); veredito pendente da lane PHP / Pest (Unit) (ci-sqlite-pest.list)"
---

# Casos de Uso & Aceite — Consulta pública de OS (`/consulta-os`)

> **Âncora:** os UC derivam do [charter](Index.charter.md) — Goals, Non-Goals e Anti-hooks — e do
> aceite da US-CONSULTA-001 no [SPEC](../../../../memory/requisitos/ConsultaOs/SPEC.md),
> **nunca do `Index.tsx`** ([proibicoes §5](../../../../memory/proibicoes.md) 2026-06-05).
>
> **Estado do módulo (2026-10-02):** a busca lê as folhas de OS reais do `Modules/Repair`
> (`RepairConsultaOsRepository`) — decisão [W] "Ligar o ConsultaOs ao Repair". O mock de 4 OS
> fixas saiu. O antigo `/repair-status` redireciona pra cá.
>
> **Multi-tenant:** a rota é pública e o `ScopeByBusiness` não age sem login; o único filtro é o
> critério de busca. O portal **não sabe a empresa do cliente** — um nº válido é procurado em todas
> as empresas, como no `/repair-status`. O UC-COS-09 documenta isso; quando [W] decidir como
> identificar a empresa, o assert dele inverte.

**Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC, veredito pendente da lane ·
⬜ não verificado · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Status |
|----|-------------|------|--------|--------|
| UC-COS-01 | Portal abre sem login | must | Non-Goals "NÃO exige login" · Mission | 🧪 |
| UC-COS-02 | Busca pelo nº da OS mostra o status daquela OS | must | Goals "busca" + "exibe" | 🧪 |
| UC-COS-03 | OS não encontrada: resposta clara, sem pista | must | Goals "não encontrada claro" · UX "sem jargão" | 🧪 |
| UC-COS-04 | Payload é a whitelist — nada interno, financeiro ou do cliente | must `[T0]` | Non-Goals "NÃO expõe dados internos/financeiros" + "NÃO expõe dados do cliente" | 🧪 |
| UC-COS-05 | Sem critério válido não há busca nem lista | must `[T0]` | Non-Goals "NÃO lista todas as OS" · aceite US-CONSULTA-001 | 🧪 |
| UC-COS-06 | Só leitura: nada grava, nada edita | must | Non-Goals "NÃO permite editar/cancelar" · Anti-hooks "NÃO grava nada em GET" | 🧪 |
| UC-COS-07 | Não deixa enumerar OS | must `[T0]` | Anti-hooks "NÃO indexa/expõe OS por enumeração" | 🧪 |
| UC-COS-08 | Busca por nº da venda e por celular; série só estreita | must | Goals "busca por nº da OS, nº da venda ou celular" | 🧪 |
| UC-COS-09 | Busca cruza empresas — comportamento atual documentado | must `[T0]` | Pendências do charter "identificar a empresa" | 🧪 |
| UC-COS-10 | Celular desligado na config é recusado | must | Goals "celular só se a config ligar" | 🧪 |
| UC-COS-11 | `/repair-status` leva ao `/consulta-os` | must | charter §2026-10-02 (D-PORTAL) | 🧪 |

Teste de todos: [`ConsultaOsIndexContratoTest`](../../../../tests/Feature/ConsultaOs/ConsultaOsIndexContratoTest.php),
lane `PHP / Pest (Unit)` do `ci.yml` (via `.github/ci-sqlite-pest.list`), schema mínimo em SQLite
in-memory com tenants fictícios 98 e 99. Nenhum status aqui é afirmação de verde: este PR não
rodou teste local ([ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).

---

## UC-COS-01 · Portal abre sem login · `must`

- **Persona:** cliente final com o link `/consulta-os` (ou o antigo `/repair-status`) que a loja mandou.
- **Aceite:** Dado um visitante sem sessão · Quando abre `/consulta-os` como visita Inertia (com
  `X-Inertia` e `X-Requested-With`) · Então recebe 200 e a tela `ConsultaOs/Index`, cuja única prop
  de página é `buscaPorCelular` (booleana) — nenhum dado de OS no render.
- **Fora do teste:** "sem AppShellV2/Sidebar" é propriedade do render no browser.
- **Status:** 🧪

## UC-COS-02 · Busca pelo nº da OS mostra o status daquela OS · `must`

- **Aceite:** Dado uma folha de OS com status, marca, modelo, aparelho, série e previsão · Quando o
  cliente busca o nº dela · Então recebe `found: true` e uma OS com esses valores · E uma outra OS
  devolve outro status (caso discriminante: um status fixo passaria com uma OS só).
- **Status:** 🧪

## UC-COS-03 · OS não encontrada: resposta clara, sem pista · `must`

- **Aceite:** Dado um número que não existe · Quando o cliente busca · Então recebe 404 com corpo
  exatamente `{"found": false}`. A frase amigável ("OS não encontrada") é da tela, não da API.
- **Status:** 🧪

## UC-COS-04 · Payload é a whitelist — nada interno, financeiro ou do cliente · `must` `[T0]`

- **Aceite:** Dado uma OS com custo estimado, senha do aparelho, defeitos, cliente com nome e
  documento, e um log automático de atividade com `defects` · Quando a busca devolve o payload ·
  Então as chaves de cada OS são exatamente `numero, marca, aparelho, modelo, serie, status,
  previsao_entrega, atividades`; as de cada atividade são `data, acao, por, nota, conclusao_de,
  conclusao_para` · E nenhum dos valores internos aparece em lugar nenhum do corpo.
- **Controle positivo:** o teste prova antes que o custo secreto está no banco.
- **Lista:** é a paridade com o `/repair-status` (D-PORTAL, 2026-10-02). Acrescentar campo é
  decisão [W].
- **Status:** 🧪

## UC-COS-05 · Sem critério válido não há busca nem lista · `must` `[T0]`

- **Aceite:** Dado um visitante · Quando busca sem tipo, sem número, com número vazio, com tipo fora
  da lista ou só com o nº de série · Então recebe 422 · E o repositório, chamado direto com tipo
  inválido ou número em branco, devolve `[]` sem consultar (defesa em profundidade — o portal não
  pode aceitar menos que o `/post-repair-status` endurecido no #8527).
- **Controle:** com critério válido o mesmo repositório acha a OS.
- **Status:** 🧪

## UC-COS-06 · Só leitura: nada grava, nada edita · `must`

- **Aceite:** Dado as rotas públicas do portal (`consulta-os.index`, `consulta-os.buscar`) · Então
  as duas existem e aceitam só `GET`/`HEAD`.
- **Status:** 🧪

## UC-COS-07 · Não deixa enumerar OS · `must` `[T0]`

- **Aceite:** Dado `consulta-os.index`, `consulta-os.buscar`, `repair-status` e `post-repair-status`
  · Então as quatro têm `throttle:` · E um número com aspas, `%` ou mais de 20 caracteres é recusado
  com 422 · E, como controle, o formato real do nº de OS do Repair (`JS2026/0001`, com barra) é aceito.
- **Status:** 🧪

## UC-COS-08 · Busca por nº da venda e por celular; série só estreita · `must`

- **Aceite:** Dado a busca por celular ligada · Quando o cliente busca pelo nº da venda ou pelo
  celular do cadastro · Então recebe a OS daquela venda/daquele celular · E o nº de série, quando
  informado, só reduz o resultado (série errada → 404).
- **Status:** 🧪

## UC-COS-09 · Busca cruza empresas — comportamento atual documentado · `must` `[T0]`

- **Aceite:** Dado o mesmo nº de OS em duas empresas (98 e 99) e uma terceira OS com outro nº ·
  Quando o cliente busca esse nº · Então a OS de outro nº **não** aparece · E as duas OS com o mesmo
  nº aparecem (é o que o `/repair-status` faz hoje).
- **Pendente [W]:** fechar exige identificar a empresa (subdomínio, slug ou parâmetro na URL). O
  ConsultaOs não tem esse mecanismo; criar rota nova ficou fora desta thread. Quando [W] decidir, o
  segundo assert inverte.
- **Status:** 🧪

## UC-COS-10 · Celular desligado na config é recusado · `must`

- **Aceite:** Dado `repair.enable_repair_check_using_mobile_num = false` · Quando o cliente busca por
  celular · Então recebe 422 e o repositório devolve `[]` · E os outros tipos seguem valendo.
- **Status:** 🧪

## UC-COS-11 · `/repair-status` leva ao `/consulta-os` · `must`

- **Aceite:** Dado um visitante sem sessão · Quando abre o antigo `/repair-status` · Então é
  redirecionado para `/consulta-os` · E a rota `post-repair-status` continua registrada.
- **Status:** 🧪

---

## Fora deste arquivo (sem contrato verificável ainda)

- [BACKLOG] Copy da tela em PT-BR e sem jargão técnico — o charter pede, mas não fixa texto literal
  e não há spec Playwright desta tela. Sem texto contratado, um teste leria o `.tsx`.
- [BACKLOG] "Sem CTA loud" (pedido da ficha do playbook) — sem fonte no charter nem em PT.
- [BACKLOG] Captcha (resto da US-CONSULTA-001).
