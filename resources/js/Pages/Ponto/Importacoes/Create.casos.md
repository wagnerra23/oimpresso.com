---
id: resources-js-pages-ponto-importacoes-create-casos
casos: Enviar arquivo AFD/AFDT do relógio · /ponto/importacoes/novo
irmaos: Create.charter.md (lei) · SDD-espelho-e-jornada-v1.0.md §5.3 F7 + §6.4 (contrato)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é o único caminho de entrada de marcação vinda de REP-A — e reimportar o mesmo arquivo duas vezes duplicaria jornada.
owner: wagner
last_run: "2026-08-02"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Ponto · MySQL)"
---

# Casos de Uso & Aceite — Enviar arquivo AFD/AFDT

> **Âncora:** `CU-PONTO-10` (§6.4) do
> [SDD](../../../../memory/requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md) + **US-PONTO-002**
> (*"importação idempotente"*) · **Portaria MTP 671/2021 Anexo I** (rastreabilidade) ·
> [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) (storage
> segregado por tenant). Fonte 4 (Delphi) **ausente** — SDD §0.1.
>
> 🔗 **Não duplica as telas irmãs.** `Importacoes/Show.casos.md` cobre a **não-duplicação de
> marcação** (`UC-IMPSHOW-01`) e a **dedup escopada** (`UC-IMPSHOW-02`); `Importacoes/Index.casos.md`
> cobre a **lista**. Aqui entra o que é do **envio**: o que o operador vê ao reenviar, e onde o
> arquivo é guardado.
>
> ⚖️ **Força do veredito:** lane `PHP / Pest (Ponto · MySQL)` — **advisory**: fica vermelha
> visível, não bloqueia merge (SDD §8.1).
>
> **Status:** ✅ verde na lane · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ vermelho.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-IMPCRE-01 | Reenviar arquivo já importado é recusado identificando o original | must | `CU-PONTO-10` + US-PONTO-002 | `ImportacaoCreateContratoTest` | 🧪 sem veredito |
| UC-IMPCRE-02 | O arquivo enviado fica guardado em área do meu empregador | must `[T0]` | Portaria 671 Anexo I + ADR 0093 | `ImportacaoCreateContratoTest` | 🧪 sem veredito |

**[BACKLOG]:**

- `[BACKLOG]` Recusa de arquivo com extensão/tamanho inválidos (`ImportacaoAfdRequest`:
  `mimes:txt,csv`, `max` de `pontowr2.afd.max_filesize_mb`). É contrato de validação de formulário
  — vira UC quando [W] confirmar que a lista de formatos aceitos é parte do contrato de negócio e
  não configuração ajustável.
- `[BACKLOG]` O processamento em si (`ProcessarImportacaoAfdJob`) — o job recebe `$businessId` no
  construtor conforme ADR 0093 (fila não tem `session()`). Testar o job é caso de **serviço**, não
  de tela; pertence a um `*ServiceTest`, não a este trio.

---

## UC-IMPCRE-01 · Reenviar arquivo já importado é recusado identificando o original · `must`

- **Persona:** RH que não lembra se já subiu o arquivo do relógio deste mês. Reenviar por engano
  é o erro mais provável desta tela — e duplicar marcação corrompe a apuração de todo o período.
- **Aceite:** Dado um arquivo já importado no meu business · Quando envio o **mesmo** arquivo de
  novo · Então o envio é **recusado** e a mensagem identifica a importação original.
- **Teste:** `Modules/Ponto/Tests/Feature/ImportacaoCreateContratoTest.php` — `UC-IMPCRE-01`.
- **Contrato:** `CU-PONTO-10` (SDD §6.4, *"importar o mesmo arquivo 2× não duplica marcação"*) ·
  US-PONTO-002 (*"importação idempotente"*) · F7 (§5.3, dedup por `sha256` **antes** de gravar).
- **Complementa, não duplica, o `UC-IMPSHOW-01`:** aquele prova o **efeito** (não duplicou
  marcação); este prova o **feedback ao operador** (foi recusado, e ele consegue achar a
  importação original). São coisas diferentes: uma dedup silenciosa que aceita o upload e não faz
  nada passaria no primeiro e falharia neste — e é justamente o comportamento que deixa o RH sem
  saber se entrou.
- **Regressão que defende:** a dedup roda **antes** do `store()` do arquivo. Inverter a ordem
  (gravar primeiro, deduplicar depois) deixa lixo no disco a cada reenvio, e o operador continua
  sem resposta clara.
- **Nota de escrita:** o assert é *"não é sucesso + a contagem não subiu"*, não um status HTTP
  cravado — trocar o `back()->withErrors` por outro mecanismo de recusa é correção legítima.
- **Status: 🧪 sem veredito.**

---

## UC-IMPCRE-02 · O arquivo enviado fica guardado em área do meu empregador · `must` `[T0]`

- **Persona:** auditor do MTE pedindo o arquivo original que originou as marcações. A Portaria
  exige poder apresentá-lo — e apresentá-lo **sem** entregar junto o de outro empregador.
- **Aceite:** Dado que envio um arquivo válido · Quando a importação é registrada · Então o
  caminho gravado aponta para a área do **meu** business.
- **Teste:** `ImportacaoCreateContratoTest.php` — `UC-IMPCRE-02`.
- **Contrato:** F7 (§5.3, *"`store()` em `local` sob `ponto/importacoes/{businessId}` — storage
  segregado por tenant"*) · Portaria MTP 671/2021 Anexo I (rastreabilidade) ·
  [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** o isolamento de **arquivo** é a única camada Tier 0 do módulo que
  **não** é defendida por global scope — é uma string de path montada à mão no controller. Trocar
  `"ponto/importacoes/{$businessId}"` por um diretório único (uma "simplificação" plausível num
  refactor) põe arquivos de todos os empregadores na mesma pasta, e a rota
  `GET /ponto/importacoes/{id}/original` passa a servir de lá. Nenhum scope Eloquent pega isso.
- **Nota de escrita:** o assert verifica que o caminho **contém o id do meu business**, não a
  string literal inteira — reorganizar a árvore de storage é legítimo; misturar tenants não é.
- **Status: 🧪 sem veredito.**
