---
id: modules-fisio-pages-fisio-pacientes-index-casos
casos: Fisio · Pacientes · /fisio/pacientes
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do fisioterapeuta + critério de aceite verificável (Dado/Quando/Então)
por_que: paciente é um contact — duplicar cadastro parte o histórico em dois; meta semanal inválida quebra a adesão; paciente de outra clínica na lista é vazamento Tier 0.
owner: wagner
last_run: "2026-10-18"
last_run_ci: "_pendente_ — o trio nasce com a suíte TDD da F1 (antes do código). O veredito entra quando a lane MySQL rodar FisioPacienteCrudTest, FisioMultiTenantTest e FisioModuloVisibilidadeTest."
related_us: []
---

# Casos de Uso & Aceite — Fisio · Pacientes (`/fisio/pacientes`)

> **Âncora:** ADR 0437 §4 (paciente é contact) e handoff F1 (T-02, T-05). Padrão de tela PT-01 Lista. Os UCs não derivam do `.tsx`.
>
> **Status:** ✅ passa (manifesto) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou. **O veredito é da lane** (G-7).

## Rastreabilidade

| UC | O que defende | Peso | Teste |
|---|---|---|---|
| UC-FPI-01 | criar paciente a partir de um contact; busca pelo nome | must | `FisioPacienteCrudTest` |
| UC-FPI-02 | lista não traz paciente de outra clínica | must | `FisioMultiTenantTest` |
| UC-FPI-03 | um contact, um paciente; meta 1–7; marcadores fechados | must | `FisioPacienteCrudTest` |
| UC-FPI-04 | sem pacote ou sem permissão, 403 | must | `FisioModuloVisibilidadeTest` |
| UC-FPI-05 | contato apagado não vira paciente | must | `FisioPacienteContatoTest` |
| UC-FPI-06 | busca de contato no servidor, só da empresa e sem ficha | must | `FisioPacienteContatoTest` |
| UC-FPI-07 | criar o contato na hora, sem duplicar celular | must | `FisioPacienteContatoTest` |
| UC-FPI-20 | coluna e filtro por fisioterapeuta responsável ("meus pacientes") | should | `FisioPacienteResponsavelTest` |

---

## UC-FPI-01 · Transformar um contato em paciente · `must`
- **Persona:** fisioterapeuta na primeira consulta.
- **Aceite:** Dado um contact do business · Quando o fisioterapeuta cria o paciente com queixa, objetivos, sessões por semana e marcadores de condição · Então nasce o perfil clínico 1:1 ligado ao contact, com `business_id` da sessão (nunca do corpo); nome e telefone continuam no contact e a ficha mostra o nome atualizado. A busca da lista encontra pelo nome do contact.
- **Regressão que defende:** cadastro paralelo de nome/telefone que diverge do contact.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPacienteCrudTest.php`
- **Status: 🧪**

## UC-FPI-02 · Lista só da minha clínica `[T0]` · `must`
- **Persona:** invariante multi-tenant (ADR 0093).
- **Aceite:** Dado pacientes nas empresas 98 e 99 · Quando a 99 abre a lista · Então só os dela aparecem — no Eloquent e na prop da tela.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioMultiTenantTest.php`
- **Status: 🧪**

## UC-FPI-03 · Cadastro que não quebra a conta · `must`
- **Persona:** fisioterapeuta.
- **Aceite:** O mesmo contact não vira dois pacientes no mesmo business · `sessions_per_week` fora de 1 a 7 (0, negativo, 8, texto, vazio) é recusado na criação e na edição · marcadores de condição só da lista fechada (prótese de quadril, pós-op de manguito, LCA, gestante, osteoporose, risco de queda, hipertensão não controlada…).
- **Regressão que defende:** adesão com divisão por zero; marcador digitado livre que o alerta de contraindicação nunca casa.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPacienteCrudTest.php`
- **Status: 🧪**

## UC-FPI-04 · Sem módulo ou sem permissão, sem lista · `must`
- **Aceite:** Sem o pacote, ou sem `fisio.access`, `/fisio/pacientes` responde 403; desligar o pacote vale no request seguinte.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioModuloVisibilidadeTest.php`
- **Status: 🧪**

## UC-FPI-05 · Contato apagado não vira paciente `[T0]` · `must`
- **Persona:** fisioterapeuta cadastrando paciente.
- **Aceite:** Dado um contact da empresa apagado (soft-delete, `deleted_at` preenchido) · Quando o cadastro manda o `contact_id` dele · Então o servidor recusa com erro em `contact_id` e nenhuma ficha nasce. Contact de outra empresa também é recusado.
- **Regressão que defende:** `Rule::exists('contacts','id')` sem `whereNull('deleted_at')` aceitava o id de um contato que não aparece em mais lugar nenhum do sistema.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPacienteContatoTest.php`
- **Status: 🧪**

## UC-FPI-06 · Buscar o contato no servidor `[T0]` · `must`
- **Persona:** fisioterapeuta numa clínica com milhares de contatos.
- **Aceite:** `GET /fisio/pacientes/contatos?q=` procura no nome ou no celular e devolve no máximo 20 contatos · só da empresa da sessão · só `customer`/`both` · só quem ainda não tem ficha Fisio · nunca contato apagado · exige `fisio.editar`. A tela não carrega mais a lista inteira de contatos.
- **Regressão que defende:** select com os primeiros 300 contatos — o contato 301 nunca podia virar paciente; contato de outra clínica na busca é vazamento Tier 0.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPacienteContatoTest.php`
- **Status: 🧪**

## UC-FPI-07 · Criar o contato na hora · `must`
- **Persona:** fisioterapeuta recebendo paciente que ainda não é contato da empresa.
- **Aceite:** Dado nome e celular em "Novo contato" · Quando cadastra o paciente · Então nasce um contact `customer` da empresa da sessão pelo `ContactUtil::createNewContact` (o mesmo caminho do cadastro de contatos) e a ficha ligada a ele, na mesma transação — se a ficha falhar, o contato não fica. Se já existe contato com o mesmo celular na empresa (comparando só os dígitos), o servidor recusa sugerindo usar o existente e não cria nada. Celular igual em outra empresa não bloqueia.
- **Regressão que defende:** contato duplicado por celular parte o histórico do paciente em dois.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPacienteContatoTest.php`
- **Status: 🧪**

## UC-FPI-20 · Ver e filtrar pelo fisioterapeuta responsável `[T0]` · `should`
- **Persona:** clínica com mais de um fisioterapeuta.
- **Aceite:** Cada linha da lista mostra o responsável (ou "sem responsável" nos pacientes cadastrados antes da coluna existir) · O filtro "Meus pacientes" traz só os do usuário logado e o filtro por profissional traz só os daquele profissional · A lista de profissionais do filtro tem só usuários ativos da mesma empresa com acesso ao Fisio — usuário de outra empresa nunca aparece.
- **Referência de mercado:** a Physitrack deixa compartilhar e transferir clientes entre profissionais. Aqui entra só a parte de responsável e filtro; notificações ficam fora.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPacienteResponsavelTest.php`
- **Status: 🧪**

---

## Backlog (prosa honesta — vira UC quando ganhar teste que o cite)

- `[BACKLOG]` Atalhos J/K e N (novo) — sem teste de navegador.
- `[BACKLOG]` Filtro "bloqueados" para consultar prontuário guardado — a regra de guarda está em UC-FPS-07; o filtro da lista ainda sem teste.

## Trilha do tempo
- 2026-10-18 · responsável pelo paciente — coluna e filtro por responsável (UC-FPI-20).
- 2026-10-17 · suíte TDD da F1 — casos nascem antes da tela e do código.
- 2026-10-18 · melhoria "novo contato na hora" — UC-FPI-05 (contato apagado recusado), UC-FPI-06 (busca de contato no servidor) e UC-FPI-07 (contato criado no cadastro). Veredito: lane Verticais.
- 2026-10-18 · US-FISIO-005 — a tela `.tsx` nasce (#9546) para os casos UC-FPI-01/03; os UCs não mudaram e seguem derivados do HANDOFF F1, não do `.tsx`. Veredito: lane Verticais.
