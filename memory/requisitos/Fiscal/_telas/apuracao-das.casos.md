---
id: requisitos-fiscal-telas-apuracao-das-casos
casos: Apurar o DAS do Simples pelo Anexo (fluxo SEM tela React nesta fatia) · ApuracaoDasService
irmaos: ../SPEC.md (US-FISCAL-030) · ../../../../Modules/Financeiro/Http/Controllers/ImpostosController.php (a estimativa ≈6% que esta apuração vai substituir)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-10-27"
last_run_ci: "pendente — CT 100 fora; veredito vem da lane Pest NfeBrasil do PR"
related_us: [US-FISCAL-030]
---

# Casos de Uso & Aceite — Apuração do DAS pelo Anexo

> **Fatia 1, só o cálculo.** Não grava, não lança título, não transmite PGDAS-D e não escolhe o
> anexo. Nenhum chamador ainda: a tela e o "lançar a pagar" chegam em fatia própria, atrás de flag,
> com protótipo publicado antes. Até lá o Financeiro segue mostrando a estimativa ≈6%.
>
> **Leiaute legal:** LC 123/2006, art. 18 §1º e §1º-A, Anexos I a V na redação da LC 155/2016
> (vigente desde 01/01/2018).
>
> **Âncora:** a US. Não há CU de tela no SDD do cockpit para isso, e não se inventa um.
>
> Os números abaixo são **vetores de teste** (empresa fictícia, tenant 98), não valores de cliente.

## Rastreabilidade

| UC | O que defende | Peso | Âncora (US) | Teste |
|---|---|---|---|---|
| UC-DAS-01 | DAS = receita do mês × (RBT12 × Aliq − PD) ÷ RBT12, sem arredondar a efetiva no meio | must | US-FISCAL-030 | `ApuracaoDasTest` |
| UC-DAS-02 | faixa "até" o limite; a tabela legal é contínua nas fronteiras 1 a 5 | must | US-FISCAL-030 | `ApuracaoDasTest` |
| UC-DAS-03 | acima do sublimite marca; acima do teto não calcula | must | US-FISCAL-030 | `ApuracaoDasTest` |
| UC-DAS-04 | sem RBT12 não inventa valor; anexo fora de I–V é recusado | must | US-FISCAL-030 | `ApuracaoDasTest` |
| UC-DAS-05 | RBT12 e receita do mês só do próprio tenant | must `[T0]` | US-FISCAL-030 | `ApuracaoDasTest` |

---

## UC-DAS-01 · Valor do DAS
- **Persona:** contador da empresa conferindo a guia do mês.
- **Aceite:** Dado Anexo I, RBT12 500.000 e receita do mês 50.000 · Então faixa 3, efetiva 6,728%
  e DAS 3.364,00. Dado Anexo III, RBT12 280.000 e mês 25.000 · Então DAS 1.964,29 (22.000 ÷ 280.000
  aplicado direto, não a efetiva já arredondada).

## UC-DAS-02 · Faixa e tabela
- **Aceite:** RBT12 exatamente 180.000 é faixa 1; 180.000,01 é faixa 2 e a efetiva continua 4,00%.
  Em todos os anexos, na fronteira das faixas 1 a 5, RBT12 × Aliq − PD dá o mesmo valor nas duas
  faixas vizinhas (20 fronteiras). É a segunda prova da tabela, independente das contas do UC-01.

## UC-DAS-03 · Sublimite e teto
- **Aceite:** RBT12 acima de 3,6 milhões devolve `acima_sublimite` (art. 13-A). A partilha de
  ICMS/ISS fora do DAS **não** é feita aqui (pergunta ao contador). Acima de 4,8 milhões o DAS sai
  vazio com o motivo (art. 3º, II).

## UC-DAS-04 · Sem dado não vira número
- **Aceite:** sem RBT12 (início de atividade, art. 18 §2º) o DAS sai vazio com o motivo; anexo "VI"
  lança erro.

## UC-DAS-05 · Tenant
- **Aceite:** Dado vendas do tenant 98 e do 99 · Quando apuro 2026-10 do 98 · Então RBT12 = 500.000
  (vendas finais de 01/10/2025 a 30/09/2026 menos devoluções) e mês = 50.000; nada do 99 nem de
  rascunho entra.

## Perguntas ao contador (não inventar)
1. A empresa apura por competência ou por caixa (Res. CGSN 140/2018 art. 18)?
2. Que receitas segregar no PGDAS-D: ST retida, monofásico, exportação, mais de uma atividade?
3. Início de atividade: confirmar o RBT12 proporcional antes de usar o valor.
4. Acima do sublimite: qual a partilha a tirar do DAS (ICMS e/ou ISS)?
