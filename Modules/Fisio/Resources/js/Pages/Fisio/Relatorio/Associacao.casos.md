---
id: modules-fisio-pages-fisio-relatorio-associacao-casos
casos: Fisio · Relatório da associação · /fisio/relatorio-associacao
irmaos: Associacao.charter.md (lei) · Associacao.tsx (tela)
tecnica: Caso de uso = narrativa da associação + critério de aceite verificável (Dado/Quando/Então)
por_que: o relatório sai da clínica para editais e convênios; número errado engana quem financia, e número pequeno demais identifica a pessoa (LGPD art. 11).
owner: wagner
last_run: "2026-11-11"
last_run_ci: "_pendente_ — o veredito entra quando a lane verticais-pest rodar FisioRelatorioAssociacaoTest."
related_us: [US-FISIO-062]
---

# Casos de Uso & Aceite — Fisio · Relatório da associação (`/fisio/relatorio-associacao`)

> **Âncora:** pedido [W] 2026-11-11 (relatório agregado sem identificação) · ADR 0437 §7 · SPEC US-FISIO-062. Os UCs não derivam do `.tsx`.
>
> **Status:** ✅ passa (manifesto) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou. **O veredito é da lane** (G-7).

## Rastreabilidade

| UC | O que defende | Peso | Teste |
|---|---|---|---|
| UC-FRA-01 | atendidos contados uma vez; presença; perfil; evolução média 1ª → última | must | `FisioRelatorioAssociacaoTest` |
| UC-FRA-02 | grupo < 5 sai "<5" e sem média; período com < 5 atendidos só mostra o total | must | `FisioRelatorioAssociacaoTest` |
| UC-FRA-03 | exige `fisio.relatorio`; a outra empresa não entra na conta | must | `FisioRelatorioAssociacaoTest` |
| UC-FRA-04 | nenhum dado de paciente no relatório | must | `FisioRelatorioAssociacaoTest` |
| UC-FRA-05 | PDF do período com rastro sem paciente | should | `FisioRelatorioAssociacaoTest` |

---

## UC-FRA-01 · Quem foi atendido, quanto e se melhorou · `must`
- **Persona:** coordenação da associação preparando a prestação de contas de um edital.
- **Aceite:** Dado um período · Então "pessoas atendidas" conta cada paciente UMA vez, mesmo com atendimento da agenda, check-in e questionário · a presença é presentes ÷ (presentes + faltas) dos atendimentos não cancelados · o perfil vem do contato (nascimento → faixa etária, município) e dos dados sociais · para FIQR, EVA, PGIC e sentar e levantar, a média da primeira e da última aplicação até o fim do período, só de quem respondeu duas vezes, com a diferença e o sentido da melhora.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioRelatorioAssociacaoTest.php`
- **Status: 🧪**

## UC-FRA-02 · Ninguém identificável `[T0]` · `must`
- **Aceite:** Toda contagem de 1 a 4 sai "<5"; média de instrumento com menos de 5 pessoas não aparece; município com menos de 5 entra em "Outros municípios", sem o nome; período com menos de 5 atendidos mostra só o total ("<5" ou 0) e nada de perfil, presença ou resultado.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioRelatorioAssociacaoTest.php`
- **Status: 🧪**

## UC-FRA-03 · Quem vê e de quem `[T0]` · `must`
- **Aceite:** Sem `fisio.relatorio` → 403 na tela e no PDF. Pacientes de outra empresa não entram em nenhuma contagem.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioRelatorioAssociacaoTest.php`
- **Status: 🧪**

## UC-FRA-04 · Só número `[T0]` · `must`
- **Aceite:** O relatório não traz nome, cartão SUS nem id de paciente — só rótulos de grupo, contagens e médias.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioRelatorioAssociacaoTest.php`
- **Status: 🧪**

## UC-FRA-05 · PDF para anexar ao edital · `should`
- **Aceite:** "Baixar PDF" gera o mesmo período em PDF; cada geração grava uma linha no `activity_log` com a empresa e o período, sem sujeito de paciente. Período com fim antes do início é recusado.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioRelatorioAssociacaoTest.php`
- **Status: 🧪**

---

## Backlog (prosa honesta — vira UC quando ganhar teste que o cite)

- `[BACKLOG]` Supressão secundária (impedir deduzir um "<5" subtraindo do total) — a regra pedida é a do "<5" por grupo.
- `[BACKLOG]` Adotar a base visual comum dos impressos (#10782) no PDF quando ela estiver no `main`.

## Trilha do tempo
- 2026-11-11 · US-FISIO-062 — tela, PDF e UC-FRA-01..05 nascem com o teste.
