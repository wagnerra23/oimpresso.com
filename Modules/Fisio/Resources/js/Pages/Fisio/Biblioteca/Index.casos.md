---
id: modules-fisio-pages-fisio-biblioteca-index-casos
casos: Fisio · Biblioteca de exercícios · /fisio/biblioteca
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do fisioterapeuta + critério de aceite verificável (Dado/Quando/Então)
por_que: a biblioteca global é a mesma para todos e não pode ser alterada por um cliente; o exercício próprio de uma clínica é dado dela. Escopo errado ou esconde os globais de todo mundo ou vaza o próprio.
owner: wagner
last_run: "2026-10-17"
last_run_ci: "_pendente_ — o trio nasce com a suíte TDD da F1 (antes do código). O veredito entra quando a lane MySQL rodar FisioBibliotecaTest."
related_us: []
---

# Casos de Uso & Aceite — Fisio · Biblioteca (`/fisio/biblioteca`)

> **Âncora:** handoff F1 (T-04, T-06). Os UCs não derivam do `.tsx`.
>
> **Status:** ✅ passa (manifesto) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou. **O veredito é da lane** (G-7).

## Rastreabilidade

| UC | O que defende | Peso | Teste |
|---|---|---|---|
| UC-FBI-01 | global para todos; próprio só para o dono | must | `FisioBibliotecaTest` |
| UC-FBI-02 | criar exercício próprio | must | `FisioBibliotecaTest` |
| UC-FBI-03 | global não se edita; próprio alheio é 404 | must | `FisioBibliotecaTest` |
| UC-FBI-04 | filtro por região e busca | should | `FisioBibliotecaTest` |
| UC-FBI-05 | foto ou vídeo no exercício próprio, só a dona vê | must | `FisioBibliotecaMidiaTest` |
| UC-FBI-06 | tipo, tamanho e declaração validados no servidor | must | `FisioBibliotecaMidiaTest` |
| UC-FBI-07 | remover mídia mexe só na da clínica dona | must | `FisioBibliotecaMidiaTest` |

---

## UC-FBI-01 · O que cada clínica vê `[T0]` · `must`
- **Aceite:** Dado um exercício global (`business_id` nulo), um da 98 e um da 99 · Quando cada clínica abre a biblioteca · Então ambas veem o global e só o próprio — na tela e no Eloquent.
- **Regressão que defende:** escopo por business cru escondendo os globais; exercício próprio vazando.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioBibliotecaTest.php`
- **Status: 🧪**

## UC-FBI-02 · Exercício da clínica · `must`
- **Aceite:** Quando o fisioterapeuta cria um exercício · Então ele grava com o business da sessão e `is_custom`, e a outra clínica não o vê. Sem nome ou sem passos é recusado.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioBibliotecaTest.php`
- **Status: 🧪**

## UC-FBI-03 · Global é de todos, ninguém altera · `must`
- **Aceite:** Editar um global → 403; editar o próprio de outra clínica → 404; editar o próprio → ok. Os dois primeiros não mudam nada.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioBibliotecaTest.php`
- **Status: 🧪**

## UC-FBI-04 · Achar o exercício · `should`
- **Aceite:** Filtrar por região devolve só aquela região; buscar por parte do nome encontra.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioBibliotecaTest.php`
- **Status: 🧪**

## UC-FBI-05 · Foto ou vídeo do exercício da clínica `[T0]` · `must`
- **Aceite:** Dado um exercício próprio da 98, um da 99 e um global · Quando a 98 anexa uma foto (jpg/png/webp) ou um vídeo mp4 ao próprio · Então o arquivo fica no backbone `Modules/Arquivos` sob `biz-98/`, ligado ao exercício, aparece na grade e no formulário da 98, e a rota da mídia responde 200 para a 98 e 404 para a 99. Anexar ao global → 403; ao próprio da 99 → 404; nada é gravado. Anexar de novo troca a mídia (a anterior sai).
- **Regressão que defende:** mídia de uma clínica legível por outra; clínica alterando a biblioteca global.
- **LGPD (ADR 0437, Res. COFFITO 424/2013):** a mídia é do EXERCÍCIO, nunca do paciente — não há vínculo com paciente e o envio exige a declaração de que a mídia não mostra paciente.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioBibliotecaMidiaTest.php`
- **Status: 🧪**

## UC-FBI-06 · Limites validados no servidor · `must`
- **Aceite:** Arquivo de outro tipo (ex.: gif, pdf), foto acima do limite de foto, vídeo acima do limite de vídeo ou envio sem a declaração → 422 com mensagem em português; nada é gravado. Sem `fisio.editar` → 403.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioBibliotecaMidiaTest.php`
- **Status: 🧪**

## UC-FBI-07 · Remover a mídia · `must`
- **Aceite:** A 98 remove a mídia do próprio exercício → o vínculo sai (exclusão reversível do backbone, a purga é da retenção) e a rota da mídia passa a 404. A 99 tentando remover a mídia da 98 → 404 e nada muda.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioBibliotecaMidiaTest.php`
- **Status: 🧪**

---

## Backlog (prosa honesta — vira UC quando ganhar teste que o cite)

- `[BACKLOG]` Seed dos 38 exercícios com `times_per_day`, avisos de respiração/apoio e `contraindication_flags` — revisão clínica anotada no PR (T-04), sem teste de conteúdo.
- `[BACKLOG]` Protocolos-modelo na grade.
- `[BACKLOG]` Duração máxima do vídeo: hoje o limite é de tamanho; medir duração exigiria ferramenta nova (ffprobe), que pede ADR.
- `[BACKLOG]` Mídia do exercício no portal do paciente (US-FISIO-007).

## Trilha do tempo
- 2026-10-17 · suíte TDD da F1 — casos nascem antes da tela e do código.
- 2026-10-18 · US-FISIO-004 — `BibliotecaController`, tela e lane Verticais com `FisioBibliotecaTest`. Busca por texto na F1 aceita por [W] em 2026-10-18 (ADR 0437 §14 nº 3). Status segue 🧪 até o veredito da lane.
- 2026-10-18 · foto e vídeo no exercício próprio (comparativo Physitrack) — UC-FBI-05..07, `FisioBibliotecaMidiaTest`. Usa o backbone `Modules/Arquivos` (ADR 0123/0214).
