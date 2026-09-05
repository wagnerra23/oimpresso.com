---
id: resources-js-pages-ponto-welcome-casos
casos: Hub de boas-vindas do módulo de ponto · /ponto/react
irmaos: Welcome.charter.md (lei) · Dashboard/Index.casos.md (a home real, UC-PAINEL-01..08)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a única tela do módulo servida por closure de rota, sem controller — e a tela mais fácil de alguém "enriquecer" com um KPI, transformando uma página de navegação em superfície de dado de jornada sem passar por controller nenhum.
owner: wagner
last_run: "2026-09-04"
last_run_ci: "1 UC rodado por mim no CT 100 (container oimpresso-staging, MySQL real), NAO em CI. A rota e uma closure em Modules/Ponto/Http/routes.php, identica ao main no container (medido). Medicao que originou o caso: GET /ponto/react devolveu 200, component `Ponto/Welcome`, e as props do payload eram SO as compartilhadas do HandleInertiaRequests (errors, auth, business, ai, flash, shell, sells, locale, csrf_token, publicRoutes, consent, clarity) — nenhuma prop de dominio do Ponto. CT100 != CI: verde la e CANDIDATURA, nao veredito."
---

# Casos de Uso & Aceite — Hub de boas-vindas do ponto

> **Âncora:** `Welcome.charter.md` §Non-Goals/§Anti-hooks + `CU-PONTO-12` do
> [SDD §6.5](../../../../../memory/requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md) + LGPD Art. 7º.
> Os UC derivam do **contrato**, nunca do `Welcome.tsx`.
>
> **Status:** ✅ verde na lane · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ vermelho.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-PWEL-01 | A porta de entrada não carrega dado de ponto — é navegação, não painel | must | charter §Non-Goals + §Anti-hooks | `WelcomeContratoTest` | 🧪 verde no CT 100, sem veredito de lane |

**[BACKLOG]** (pergunta aberta ao [W], ou fora do alcance de teste de contrato):

- `[BACKLOG]` O charter pergunta em §Pendências se `/ponto/react` **permanece** ou é substituído pelo
  Dashboard real (`/ponto` → `DashboardController`). Esta tela nasceu como piloto do pipeline
  React/Inertia e hoje convive com a home de verdade, que tem contrato próprio
  (`Dashboard/Index.casos.md`, `UC-PAINEL-01..08`). Enquanto a resposta não vier, o contrato aqui é
  deliberadamente **mínimo**: garante o que a tela promete *não* fazer, sem fixar em teste um
  desenho que pode ser aposentado.
- `[BACKLOG]` Os quatro atalhos (Aprovações, Banco de horas, Espelho, Importações) não têm caso que
  prove que apontam para rota **viva** — a família do `CU-PONTO-14` (*"o catálogo não promete o que
  não entrega"*). Não virou UC porque derivar os destinos do `.tsx` seria tautológico (§5 2026-06-05),
  e o charter descreve "quatro cards" sem fixar quais são as quatro seções — o conjunto é escolha de
  produto, não contrato. Vira UC no dia em que [W] fixar a lista.
- `[BACKLOG]` Ninguém verifica quem **pode** abrir a tela. A rota está no grupo com middleware do
  módulo, mas o contrato de permissão (`ponto.access`) é do grupo inteiro e não desta tela — cobri-lo
  aqui duplicaria o `SpatiePermissionsTest`, que já é o dono desse eixo na lane.

---

## UC-PWEL-01 · A porta de entrada não carrega dado de ponto · `must`

- **Persona:** qualquer usuário que abre o módulo. Esta tela é um hub de navegação — quatro cartões
  com links. Jornada é dado sensível (LGPD Art. 7º + sigilo trabalhista), e uma página de boas-vindas
  não é lugar para exibi-la.
- **Aceite:** Dado que acesso `/ponto/react` · Então a página abre (200) renderizando o hub · **e** o
  que é entregue à tela **não** contém nenhuma prop de domínio do ponto — nem marcação, nem
  colaborador, nem apuração, nem espelho, nem indicador.
- **Teste:** `Modules/Ponto/Tests/Feature/WelcomeContratoTest.php` — `UC-PWEL-01`.
- **Contrato:** charter §Non-Goals (*"Não mostra KPIs, dados de ponto nem marcações — é só navegação
  (sem props do backend)"* · *"Não executa nenhuma ação de negócio — só links"*) · §Anti-hooks
  (*"Não faz polling nem fetch"*) · `CU-PONTO-12` (nenhuma tela do módulo expõe dado de outro
  empregador — e a forma mais barata de cumprir isso é não carregar dado nenhum).
- **Regressão que defende:** esta é a única tela do módulo servida por **closure de rota**, sem
  controller. Isso a torna o lugar mais fácil de alguém "enriquecer" — basta passar um array no
  `Inertia::render` dentro do `routes.php`, sem tocar controller, sem revisão de query, sem
  `business_id` escopado à vista. O caso trava a tela como navegação: no dia em que ela precisar de
  dado, a mudança passa por aqui e alguém decide conscientemente (inclusive sobre o isolamento).
- **Como o assert é escrito, e por quê:** ele afirma a **ausência de props de domínio** por nome
  (`marcacoes`, `colaboradores`, `apuracoes`, `espelho`, `escalas`, `reps`, `intercorrencias`,
  `kpis`, `config`), e **não** compara a lista completa de props com uma lista esperada. As props
  compartilhadas do `HandleInertiaRequests` (`auth`, `business`, `shell`, `locale`, …) mudam por
  motivos que nada têm a ver com esta tela, e fixá-las aqui faria o caso quebrar em PR alheio —
  gate frágil, que se aprende a ignorar.
- **Status: 🧪 verde no CT 100, sem veredito de lane.**
