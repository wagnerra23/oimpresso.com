---
thread: "07 · Configurações — PUXAR"
dono: "[CC]"
estado: feito (medição) · 1 decisão para [W]
base_lida: wagnerra23/oimpresso.com@main 68e071305601 (2026-09-24)
prefixo_tocado: nenhum arquivo de build · 00-INDICE.md (decisão RESIDUO-6 acrescentada)
---
# _saida-07 · Configurações — protótipo × produção

Lido no turno: `resources/js/Pages/Essentials/Settings/Index.tsx` (8.866 B). Protótipo: `hrm-extras.jsx` `Config()` + `hrm-data.jsx` `CFG`.

## Premissa corrigida
A thread dizia **12 campos** no protótipo. Medido hoje: **10** — as mesmas 10 chaves da Page viva, 1:1. A 11ª ("permitir registrar a própria presença") **já não é campo**: é uma nota que aponta a permissão `allow_users_for_attendance_from_web`. Classe (i) já estava resolvida no build.

## As 10 chaves
| chave | protótipo (rótulo) | produção (rótulo) | classe | ação |
|---|---|---|---|---|
| `leave_ref_no_prefix` | Prefixo do número de referência | Prefixo de afastamentos | — | nada |
| `leave_instructions` | Instruções ao colaborador | Instruções para afastamentos (card) | — | nada |
| `payroll_ref_no_prefix` | Prefixo da folha | Prefixo de folha | — | nada |
| `essentials_todos_prefix` | Prefixo das tarefas | Prefixo de tarefas | — | nada |
| `grace_before_checkin` | Antes da entrada | Antes de entrar | **(ii) presença** | decisão [W] |
| `grace_after_checkin` | Depois da entrada | Depois de entrar | **(ii)** | decisão [W] |
| `grace_before_checkout` | Antes da saída | Antes de sair | **(ii)** | decisão [W] |
| `grace_after_checkout` | Depois da saída | Depois de sair | **(ii)** | decisão [W] |
| `is_location_required` | Exigir localização na marcação | Exigir localidade no ponto | **(ii)** — e **divergência de significado** ↓ | decisão [W] |
| `calculate_sales_target_commission_without_tax` | Apurar comissão de meta sem imposto | Meta de vendas sem impostos | — | nada |

Classe (iii) (chave real que a Page não expõe): **0**. Nenhum gap para o [CL].

## Divergência de significado (não é só rótulo)
`is_location_required` — protótipo: *"Sem permissão de local no navegador, a marcação é recusada"* (GPS). Produção: *"Colaborador deve selecionar **loja** ao bater ponto"* (business_location). São regras diferentes. Não mudei o protótipo: sem ler o `EssentialsAttendanceController`, não sei qual das duas o backend aplica. Fica anexada à RESIDUO-6.

## Decisão pedida — `RESIDUO-6` (acrescentada ao json do índice)
As 5 chaves de presença continuam em `/hrm/settings` ou migram para o Ponto (dono da jornada desde **D1**, 2026-09-05)? Sem resposta, nada se move — nem aqui, nem no `main`.
> Colisão de nome evitada: a thread chamava isto de "RESÍDUO 5", mas `RESIDUO-5` no índice já é a pergunta de Metas (trava a 04).

## Protótipo × produção que fica como está (layout/copy)
- Nota "Esta tela já é Inertia no main", botão **Descartar alterações** e aviso "alterações não salvas" — só protótipo, afordância de UX; não vira pedido.
- Produção agrupa em 4 cards (Prefixos · Afastamentos · Tolerâncias · Comportamentos); protótipo em 4 (Licenças · Folha e tarefas · Tolerância · Regras). Mesmo conteúdo.

---

## Code — medição da sessão Hrm 07 ([#7850](https://github.com/wagnerra23/oimpresso.com/pull/7850))

> Reconciliação de 2026-09-24: o Cowork e o Code mediram a mesma thread em paralelo e cada um escreveu este recibo. O texto acima é o do Cowork (`[CC]`); abaixo, íntegro, o do Code. Nenhum dos dois foi editado.
> Procedência do lado Code: dono "[C]" · data 2026-09-23 · base wagnerra23/oimpresso.com@main 1061dbf2e · Cowork vivo `hrm-extras.jsx` (projeto telas 019dcfd3…, get_file 2026-09-23, truncated:false)

### Lado do trabalho
Esta thread é de **medição**, não é trabalho do Cowork nem de código. O índice não declara prefixo
(`"prefixo": []`) e o próprio `07-*.md` diz *"Nada de código nesta fase"*. O `CC→CL` só passaria
ao código se houvesse gap da classe (iii), e **não há** (ver abaixo). Por isso nenhum arquivo de
produto nem do espelho foi tocado.

### Feito
1. **Os campos do protótipo foram contados em três fontes, e o número é 10, não 12.**
   - Espelho `prototipo-ui/cowork/Wagner/hrm-extras.jsx`, `function Config`: 10 chaves (8 `set("…")` + 2 `flag("…")`).
   - Cowork vivo, mesmo arquivo lido por `get_file`: a mesma estrutura, com as mesmas 10 chaves.
   - `hrm-data.jsx`, `const CFG`: as mesmas 10 chaves.
   - Histórico: o `hrm-extras.jsx` entrou no repo em `4f51a9ec7` (#7224, 2026-09-11) já com 10. A base
     citada pela thread (`159e572dd448`) **não tem** o arquivo, então o "12" vem de uma leitura do
     build servido em 04/09 que o repo não guarda. A causa provável: o campo "permitir marcação via
     web" (classe i) saiu depois e ficou só como nota de texto. **Não medi isso.**
2. **Os 10 campos foram cruzados com as 10 chaves da Page viva e com o `validate()` do controller.** A
   correspondência é 1 para 1, sem sobra dos dois lados:
   - Page: `resources/js/Pages/Essentials/Settings/Index.tsx`, com 10 inputs/switches, todos com `Label htmlFor`.
   - Controller: `EssentialsSettingsController@update`, com `$request->validate` de 10 chaves.

| # | campo no protótipo (card) | chave | Page viva (rótulo) | classe | ação |
|---|---|---|---|---|---|
| 1 | Prefixo do número de referência (Licenças) | `leave_ref_no_prefix` | "Prefixo de afastamentos" | — (existe nos dois) | nenhuma |
| 2 | Instruções ao colaborador (Licenças) | `leave_instructions` | card "Instruções para afastamentos" | — | nenhuma |
| 3 | Prefixo da folha (Folha e tarefas) | `payroll_ref_no_prefix` | "Prefixo de folha" | — | nenhuma |
| 4 | Prefixo das tarefas (Folha e tarefas) | `essentials_todos_prefix` | "Prefixo de tarefas" | — | nenhuma |
| 5 | Antes da entrada (Tolerância) | `grace_before_checkin` | "Antes de entrar" | **(ii) presença** | decisão [W] |
| 6 | Depois da entrada | `grace_after_checkin` | "Depois de entrar" | **(ii)** | decisão [W] |
| 7 | Antes da saída | `grace_before_checkout` | "Antes de sair" | **(ii)** | decisão [W] |
| 8 | Depois da saída | `grace_after_checkout` | "Depois de sair" | **(ii)** | decisão [W] |
| 9 | Exigir localização na marcação (Regras) | `is_location_required` | switch `sw-location` | **(ii)** | decisão [W] |
| 10 | Apurar comissão de meta sem imposto (Regras) | `calculate_sales_target_commission_without_tax` | switch `sw-sales` | — | nenhuma |
| — | "Permitir que o colaborador registre a própria presença" | (nenhuma, é só nota) | ausente | **(i) chave morta, já resolvida** | nada: o protótipo já a trata como nota que aponta para a permissão `allow_users_for_attendance_from_web` |

**Totais:** 10 de 10 chaves estão nos dois lados. Classe (i): 0 campos (o único candidato já é
nota). Classe (ii): 5. Classe (iii), gap da Page: **0**, então não há PR de código para o [CL].

### Não feito, e por quê
- **Nenhum movimento das 5 chaves de presença.** O `PARAR SE` da thread manda registrar e não mover
  (a D1 dá o Ponto como dono da jornada, e onde essas chaves moram é decisão [W]).
- **Nenhum ajuste de rótulo.** A produção está à frente (🔵, "não repintar") e a thread é de
  leitura. A divergência de texto fica registrada em Descobertas 2.
- **T7/design-diff não rodados.** A thread pede a classificação, não paridade visual.

### Decisão pedida a [W]
**As 5 chaves de tolerância e localização** (`grace_before_checkin` · `grace_after_checkin` ·
`grace_before_checkout` · `grace_after_checkout` · `is_location_required`). Com a D1 (presença cede
ao Ponto), elas continuam nas Configurações do HRM ou migram para as configurações do Ponto?
Enquanto a thread 09 não desagenda o cron `pos:autoClockOutUser`, quem ainda as lê é o clock-in web
do Essentials. Movê-las antes da 09 quebraria esse leitor.

**Respondida por [W] em 2026-09-23: "as 5 chaves de presença migram para o Ponto".**

O que isso implica, medido com `git grep` no `main` 1061dbf2e, sem código de teste:
- **Leitores vivos das 5 chaves fora das Configurações:**
  - `Modules/Essentials/Http/Controllers/AttendanceController.php`
  - `Modules/Connector/Http/Controllers/Api/AttendanceController.php` (**API**, fora do HRM)
  - `Modules/Essentials/Utils/EssentialsUtil.php` (8 usos)
  - `Modules/Essentials/Providers/EssentialsServiceProvider.php` (cron)
  - `clock_in_clock_out_modal.blade.php`
- **O destino não é vazio.** O Ponto já tem tolerância própria (`Modules/Ponto/Config/config.php`,
  `configuracoes/index.blade.php`, `ApuracaoService.php`). Migrar é **fundir** com o que o Ponto já
  tem, não criar 5 chaves novas lá. A decisão sobre o que fazer quando os dois valores divergirem por
  business fica para quem executar, e esse caso tem de ser **medido antes**.
- **Sequência:** a remoção das Configurações do HRM (Page + `validate()`) só entra **junto ou depois**
  da thread 09, que tira o clock-in web do Essentials. Antes disso, os leitores acima ficariam sem
  valor. O Connector API precisa de dono na 09, porque não está no prefixo dela hoje.
- **Execução fora desta thread:** a 07 não tem prefixo, e o `EssentialsSettingsController` está no
  `nao_toca` dela. O trabalho vai para a thread 09 (a ampliar no índice) ou para uma thread nova. Quem
  mantém o índice decide qual.

### Descobertas
1. **A premissa "12 campos" está velha.** Nem o espelho, nem o Cowork vivo, nem o histórico do repo
   têm 12. O título da thread no índice (`12 campos × 10 chaves`) deveria virar `10 × 10`. Não editei
   o índice, porque a Lei 4 proíbe a thread de escrever nele.
2. **O vocabulário diverge entre protótipo e produção:** o protótipo diz **licença** e a Page diz
   **afastamentos** (`leave_ref_no_prefix` e o card de instruções). Hoje a thread 02 chama a Page
   irmã de "Licenças" (`Essentials/Licencas`), então é a Page de Configurações que destoa do módulo.
   É um ajuste de copy de 2 rótulos na Page, candidato a PR de 1 arquivo se [W] quiser alinhar. Não é
   gap de capacidade.
3. **Colisão de nome:** o `07-*.md` manda registrar a classe (ii) *"como RESÍDUO 5"*, mas o
   `RESIDUO-5` do índice (§6/§7) já é a pergunta de Metas. Por isso registrei a decisão acima sem
   número. Numerar é de quem mantém o índice.
4. `gh pr list --state open`: nenhum PR aberto toca `hrm*`, `Essentials/Settings` ou
   `EssentialsSettingsController`. Sem colisão.

### Pedido literal
`/onda Hrm --thread 07`: *"Configurações — PUXAR (12 campos × 10 chaves)"*, dono CC→CL. A sessão
devia entender primeiro se o trabalho era do Cowork ou do código.
