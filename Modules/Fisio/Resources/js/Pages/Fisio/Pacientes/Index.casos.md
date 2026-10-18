---
id: modules-fisio-pages-fisio-pacientes-index-casos
casos: Fisio · Pacientes · /fisio/pacientes
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do fisioterapeuta + critério de aceite verificável (Dado/Quando/Então)
por_que: paciente é um contact — duplicar cadastro parte o histórico em dois; meta semanal inválida quebra a adesão; paciente de outra clínica na lista é vazamento Tier 0.
owner: wagner
last_run: "2026-10-17"
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

---

## Backlog (prosa honesta — vira UC quando ganhar teste que o cite)

- `[BACKLOG]` Atalhos J/K e N (novo) — sem teste de navegador.
- `[BACKLOG]` Filtro "bloqueados" para consultar prontuário guardado — a regra de guarda está em UC-FPS-07; o filtro da lista ainda sem teste.

## Trilha do tempo
- 2026-10-17 · suíte TDD da F1 — casos nascem antes da tela e do código.
