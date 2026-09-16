# ATA-DECISOES-2026-09-14.md — as 19 respostas de [W], e o que cada uma manda fazer

> **Estatuto:** recibo de decisão. O que está aqui **não se re-pergunta** — nem na próxima sessão, nem no próximo pacote. Quem reabrir sem emenda de [W] está gastando o ciclo de novo.
> **Emitido por:** [CC], 2026-09-14, a partir da resposta integral de [W] neste ciclo.
> **As 3 regras que [W] pôs acima de todas:**
> **R1** — Non-Goal ratificado vira **Pest GUARD no charter** *e* **sai do protótipo no mesmo pacote**. Protótipo é soberano em forma (UI-0029): se ele continuar mostrando o botão, a próxima sessão restaura o botão e o guard vira briga permanente.
> **R2** — Onde [W] decidiu **contra** o protótipo, **quem muda é o protótipo**. A tela sozinha não resolve.
> **R3** — Onde a resposta era técnica, [W] disse **"faça"**, não "escolha". Marcado item por item.

---

## BLOCO 1 · travam tudo

| id | decisão | o que muda, e onde |
|---|---|---|
| **D-ALVO-1280** | **FAÇA** (R3) — não era decisão dele. 1280 é o alvo canon; browser liberado se preciso. *"Não espere autorização pra medir."* | passada de largura no dispatch local ⇒ o 3º eixo do vetor. **Sem isso não há thread de layout legítima** |
| **D-PONTO-DETALHE** | **ROTA PRÓPRIA.** E o número dele é maior que o meu: **9 páginas**, não 6 — `BancoHoras/Show` · `Colaboradores/Edit` · `Escalas/Form` · `Espelho/Show` · `Importacoes/Create` · `Importacoes/Show` · `Intercorrencias/{Create,Edit,Show}`. *"In-page não é alternativa, é 2ª pele sobre 9 páginas que já existem, com charter e casos ao lado."* | **o protótipo se ajusta (R2)**: os 6 master-detail in-page (`if (sel)` / `if (form)` / `if (edit)` / `nova`) viram **rota**, com entrada no `app.jsx` e no host |

## BLOCO 2 · Non-Goals ratificados (R1: guard no charter **+** sai do protótipo)

| id | decisão | estado no build |
|---|---|---|
| **D-INTERC-ACOES** | **RATIFICADO.** *"Submeter sem abrir o detalhe é decisão cega, e o Show já existe pra isso."* | ✅ **FEITO neste turno** — `Editar` e `Submeter` saíram da linha; sobra `Ver`. Coluna Ação 170px → 88px |
| **D-ESC-DESTROY** | **ENTRA, com trava dura:** *indisponível* (não "confirmação") quando houver colaborador vinculado, com o motivo escrito. *"Perder referência de escala é perder histórico de jornada, e isso a CLT cobra."* `window.confirm` **não era pergunta** — usa o dialog do DS (R3) | ✅ **FEITO — e as duas metades estavam QUEBRADAS na 1ª tentativa** (pego pelo verificador, não por mim). **(a)** eu chamei o `Modal` do DS com `tone`/`confirmLabel`/`onConfirm`, props que **não existem** — a API é `{open,onClose,title,children,footer,width}` ⇒ o modal abriria **sem botão de ação** e `confirmar()` era código inalcançável. **(b)** o "motivo escrito" ia no `title` do botão, mas o `PtBtn` embrulha no `Tooltip` do DS, e **botão `disabled` não emite hover nem recebe foco** ⇒ 4 botões mortos com `title=""`. **Causa comum: nenhuma das duas rodou** — as 4 escalas do mock têm vínculo, então o caminho nunca executou. Corrigido: rodapé em `footer`, motivo como **texto na célula**, e uma escala **sem vínculo** no mock (`EST-30`). **Provado ponta a ponta:** 5 linhas · 4 `disabled` com motivo visível e plural correto · o habilitado abre Modal com **Cancelar + Remover escala** · confirmar → **5 → 4 linhas** |
| **D-REP-ATIVO** | **ENTRA.** *"Inativar preserva o histórico; deletar, não. **Nunca delete REP**"* — sem poder inativar fica dúvida sobre qual relógio valia numa data | ⏳ pede campo `ativo` no mock (`data.jsx`), coluna na lista e ação de inativar. **Nunca** delete |
| **D-REL-FILA** | **MANTÉM O CLIQUE** e registra. *"É ela que me diz qual dos 8 relatórios construir primeiro."* Duas condições: o registro carrega **`business_id`** (Tier 0) e a tela diz **"pedido registrado"**, não um 501 mudo | ⏳ copy da fila + declarar o `business_id` no contrato (no protótipo é mock; no vivo é obrigatório) |

## BLOCO 3 · conformidade

| id | decisão | estado |
|---|---|---|
| **D-COLAB-CPF** | **MASCARA na listagem**, inteiro só no form. *"Lista é tela de varredura, vista por quem passa atrás da mesa; minimização de dado é o default."* Vale **também pro PIS**. *"Não é gosto meu, é LGPD."* | ✅ **FEITO** — 3 últimos dígitos visíveis, CPF **e** PIS, só na lista |
| **D-PRINT-TINTA** | **EXCEÇÃO DECLARADA** de tinta de papel, escrita no charter, **sem token novo**. *"Cor de impressão não é cor de tela e não deve poluir o DS."* Onde declarar e como isolar é meu (R3) | ⏳ declarar no charter do Espelho + manter as 8 cruas isoladas no bloco `@media print` (já estão) |

## BLOCO 4 · protótipo à frente — INCORPORA (onda única de emenda charter+protótipo)

| id | decisão | ressalva de [W] |
|---|---|---|
| **D-COLAB-COLUNAS** | **INCORPORA tudo.** O filtro **"Sem PIS cadastrado"** é *"o item de maior valor do lote inteiro: sem PIS o AFD rejeita, e hoje a pessoa só descobre isso na Importação, depois do erro"* | emenda o charter |
| **D-IMP-EXTRAS** | **INCORPORA.** *"Sem isso a tela de importação só sabe dizer que falhou."* | — |
| **D-CFG-IA** | **INCORPORA o 5º bloco**, com **uma condição**: liga/desliga por business passa pela **UI canônica de pacote/permissão, nunca por `if` no código** | ⚠️ **eu não sei se as flags têm esse caminho hoje** — [W] pediu que isso vire **item próprio** se não tiver. **Vira `D-CFG-IA-CAMINHO`** (medir antes de construir) |
| **D-BH-KPI** | **EMENDA O CHARTER**, ficam os meus 4. *"O charter é o que está atrasado."* | — |
| **D-INTERC-ANEXO** | **INCORPORA.** *"Atestado sem anexo é intercorrência sem prova, e é ela que sustenta a justificativa numa fiscalização."* **Ressalva séria:** atestado é **dado de saúde, sensível** — arquivo não público, fora de log, acesso por permissão. *"Trata como PII desde o primeiro commit, não como 'depois a gente protege'"* | [W] ofereceu chamar **[E] Eliana** antes de virar código e **dispensou por agora** — registrado: revisão de [E] **não** é pré-requisito desta onda |
| **D-REL-FLUXO** | **Filtros globais no topo** (vai com a minha recomendação) — *"um passo a menos"*. Mas por **R2** o **wizard SAI do protótipo no mesmo pacote**: *"você está pedindo pra decidir contra o seu próprio desenho — ok, aceito, desde que o desenho mude junto"* | ⏳ arrasta o tipo do campo de período **e o campo Formato que hoje mente** — consertar os dois |

## BLOCO 5 · escopo de produto

| id | decisão |
|---|---|
| **D-ESC-TURNOS** | **SIM, editável.** *"Um redirect que manda 'vá configurar os turnos' pra uma tela que só lê é um beco. Ou a tela edita, ou o redirect some. Prefiro que edite."* |
| **D-CFG-POR-BUSINESS** | ⛔ **ADIADO** — decisão final de [W] neste ciclo. O mérito é "sim, e não vejo como poderia ser outra coisa" (*"parâmetro de CLT compartilhado entre tenants é um tenant mexendo na jornada do outro"*), **mas a frente não abre agora**: é tela nova, provavelmente com migration. **A resposta ao Design é "adiado", não "sim"** — para eu não planejar contando com uma tela que não vai existir. **Fora da fila até [W] reabrir** |
| **D-IMP-FILTRO** | **ENTRA.** *"Barato, e a lista cresce todo dia."* |
| **D-PONTO-ATOMO-BOOLEANO** | **FAÇA** (R3): `Switch` para flag persistida, `Checkbox` só para seleção em lista. **Aplica nos 8 sítios** |

## BLOCO 6 · fora de mim — **corrigido pela medição de [CL] em 14/09**

| # | item | estado real | o que eu errei |
|---|---|---|---|
| ① | `paths:` do gate | ✅ **JÁ FEITO** (branch `claude/ponto-ancoras-resposta-cowork`, commit `31733b7deb`) | **eu disse "3 linhas"; são 8 arquivos nomeados.** A minha conta deixaria **5 fora do gatilho**: `PROTOCOL`, `REGISTRY_DS_COMPONENTES`, `ARQUITETURA`, `LICOES_CC` e `FRESCOR`. E o glob da pasta (83 arquivos) foi **recusado de propósito** — seria "cobertura de mentira". Duas lições: eu contei linhas em vez de contar **arquivos que precisam do gatilho**, e o glob que eu teria pedido é o anti-padrão |
| ④ | A1/A2 (leitura de âncora) | ✅ **JÁ FEITO**, mesmo commit. **A1:** 21 charters · 20 com âncora · 1 `n/a` · 3 destinos. **A2:** 92 charters resolvíveis · 87 ok · 20 do Ponto · **0 podre, provado por mutação** | meus números batem com os dele (21/3 destinos). A pergunta do §0 **está respondida**: o Code lê igual — e provou por **mutação**, não por ausência na lista de erro, que é a régua mais dura que a minha |
| ② | manifesto commitado | ⏳ **PR #7117 aberto** (765 UCs · 737 pass) — *espera merge, não autor* | eu listei como "a fazer"; é "a mergear" |
| ③ | `_incoming/` × R1 | ⛔ **órfão, e a culpa é da minha citação** | ver abaixo |

### ③ · O R1 que eu quis dizer — endereço exato (e o palpite dele está errado)

[CL] não pôde agir porque **"R1" colide em 15+ lugares do repo, um por PEDIDO, incompatíveis entre si**. Ele tem razão, e o defeito é meu: eu citei um rótulo, não um endereço.

**O R1 é código, não documento:**
```
arquivo: scripts/governance/cowork-ssot-guard.mjs   (5.403 B, lido inteiro por [CC] em 2026-09-14)
âncora:  // R1 — a raiz do protótipo contém somente as duas áreas autorizadas.
check:   if (!e.isDirectory() || !['cowork', 'design-system'].includes(e.name))
erro:    `R1 item proibido na raiz de prototipo-ui/: prototipo-ui/${e.name}`
```
**O palpite dele (`PEDIDO-CL-reexport-build-cowork-2026-08-27`, "só build") NÃO é este R1.**

**O conflito, com os dois lados citados:**
- `.gitignore` reserva **`/prototipo-ui/_incoming/`** — *"Estação de ingestão de design (plano vectorized-badger PR-2): **staging volátil do unzip**"*;
- o guard varre o **disco** (`readdirSync` da raiz de `prototipo-ui/`), **não o índice do git** ⇒ a pasta existindo **já é violação de R1**; e os bytes dentro dela caem no **R4**, que hasheia tudo *"inclusive caches ignorados"*.
⇒ **a pasta que o `.gitignore` oferece para unzip é a pasta que o guard proíbe.** Uma das duas leis cede — e a alternativa já existe e é ignorada: **`/oimpresso-erp-conunica-o-visual/`**, na raiz do repo, fora de `prototipo-ui/`.

**Lição adotada (vale para mim em todo pacote daqui pra frente):** citar regra como **`arquivo#Rn`** com a linha do check, nunca "R1" solto. Rótulo sem endereço é exatamente o que travou este item por um ciclo.

### ⑤ · `sync/` — os dois retratos estão certos, sobre coisas diferentes

Medido por [CC] **agora** (árvore `32af4af112a4`, 18:27Z): filtro `^sync/|bundle\.manifest|payload\.part` → **0 de 16.920**. E `sync/` **não está no `.gitignore`** (lido inteiro).
⇒ **O pacote de 278 arquivos existe no DISCO dele** (foi o zip que habilitou o gerador, que só roda de onde os arquivos estão) **e não existe no git**. Não é "um dos dois com retrato velho": é **um pacote sem recibo versionado** — que é o próprio PR #7117.
⚠️ **E ele já nasceu defasado:** desde o zip, **6 arquivos do build mudaram neste ciclo** (`sidebar.jsx` · `app.jsx` · `ponto-telas.jsx` · `ponto-page.jsx`, mais os 2 de a11y). O manifesto de 14/09 cobre o estado do zip, **não o de agora** — confere por sha do seu lado antes de considerar fechado.
**Retratado de vez:** eu não digo mais "defasado desde 24/ago" — esse número era herdado e nunca foi medido por mim.

---

## BLOCO 0 · a decisão mais VELHA, e ela vem antes do bloco 5

**`D-PONTO-RETIDOS`** — proposal `ponto-contratos-retidos`, `status: open` **desde 2026-08-21** (`memory/decisions/proposals/2026-08-21-ponto-contratos-retidos-decisao-w.md`, lida inteira por [CC] em 14/09). [W] pediu para ser lembrado dela: **entra na fila dele antes do bloco 5**.

**O que ela destrava:** `Fechamento` · `Conformidade` · `REP-P` — exatamente as 3 superfícies que eu vinha classificando como "sem receptor" (o meu F4), e os 9 `data-contract` órfãos de `ponto-fechamento.jsx` + `ponto-mobile.jsx`.

**Por que os 2 contratos foram RETIDOS (e por que a minha thread 18 estava certa em não pedi-los):** não existe estado "em espera" no `contract.schema.json`; o job varre `git ls-files '*.contract.json'` e **todo contrato não-`EXEMPLO` é ativo**. Contrato com `alvo` inexistente nasce **vermelho permanente** e, como o job dispara em qualquer `.tsx` tocado, **pinta todo PR de UI do projeto**. *"Contrato vermelho permanente treina o time a ignorar gate — custo maior que a ausência do contrato."*

**As 4 decisões, enumeradas na proposal (o commit da opção B falava em "decisões 1-4" sem enumerá-las):**
1. **Quem pode fechar uma competência, e o fechamento é reversível?** O protótipo oferece **"Reabrir"** depois de fechado — *"reabrir competência fechada tem consequência em fiscalização"*.
2. **O que é "exceção assinada"?** O protótipo consolida "com exceções". *Assinada por quem, com que prova, guardada onde?* Sem isso *"o botão promete um ato jurídico que o sistema não pratica"*.
3. **Como "recusar" uma marcação sem violar append-only?** Marcação nova com `ORIGEM_ANULACAO` ou entidade separada de validação — **modelos de dado diferentes**. (`ponto_marcacoes` é append-only por força de lei.)
4. **AFD/AEJ entram neste escopo ou são outro passo?** `AEJ` aparece em 4 arquivos PHP; `fechar_competencia`, em **nenhum**.

**A assimetria que explica tudo:** o Espelho foi construível porque tinha **paridade** — a copy legal (`Portaria MTP 671/2021 Art. 85`) foi **transcrita** de um Blade que já roda. Estas 4 telas **não têm Blade, rota, controller nem backend**: construí-las é **redigir do zero um fluxo com efeito jurídico**.

✅ **RESOLVIDA EM 2026-09-14 — [W] ratificou as 5** (*"sim aceito as 5"*, depois de *"vai"* nos defaults propostos na thread 29). A proposal `ponto-contratos-retidos`, aberta em **21/08**, fechou **24 dias** depois:
- **D0** rota `/ponto/conformidade` read-only · **D1** `ponto.fechar` próprio, **sem "Reabrir"** · **D2** a palavra **"assinada" sai**, o ato continua com nome e data · **D3** recusar = **`ORIGEM_ANULACAO`** (precedente, não escolha) · **D4** **AFD/AEJ fora** do fechamento.
- **R2 cumprida no mesmo turno:** 4 das 5 mudaram o protótipo e estão **medidas no render** (`/Reabrir/` ausente · `/assinad/i` **zero** na tela · `ORIGEM_ANULACAO` presente · passo 4 = *"AFD / AEJ — em Relatórios"*).
- **Pedido executável:** thread **30**, 5 PRs em ordem de risco crescente; os 2 contratos retidos entram **junto das telas**.
- **O que sobra para o [CL] em canon:** virar a proposal para `accepted` (bloco de frontmatter na thread 30) e estender o guard da ADR 0383 ao `prototipo-ui/cowork/**`.

**O que eu não fiz, e segue valendo:** não escrevi os 2 contratos antes da tela (vermelho permanente) e não tratei "sem receptor" como lacuna de engenharia — era bloqueio de produto, com dono e data.

### ⚠️ ACHADO NA CONFERÊNCIA — a tela do REP-P violava a ADR 0383, e eu corrigi antes de [W] aprovar

[W] pediu a lista das telas *"para conferir antes de aprovar"*. Ao listar, medi o fonte e achei isto:

**`ponto-mobile.jsx` exigia SELFIE OBRIGATÓRIA** — estado `selfie`, botão *"Tirar selfie"*, bloqueio *"Tire a selfie para registrar"* (o `Bater ponto` **não habilitava** sem ela), `selfie_min_kb: 100` em `LIMITES`, coluna **"Selfie (hash)"** na fila do gestor e a copy *"guardamos só o hash"*.

**Li a ADR inteira antes de agir** (`memory/decisions/0383-ponto-interno-nao-coleta-biometria.md`): `status: aceito` · `authority: canonical` · `lifecycle: ativo` · decidida por [W] em **2026-08-27**, ratificada em **28/08** (*"aceito"*), executada no **PR #6393** (−111/+51). Texto da decisão: *"o sistema interno de ponto **não coleta, não trafega e não deriva** dado biométrico"* — e o **`SELFIE_MIN_BYTES` que eu tinha como `selfie_min_kb` é literalmente um dos termos que a ADR manda deletar**. A seção *"Como se reconhece violação"* lista exatamente a reintrodução desses termos **sem ADR nova**.

**Por que a máquina não pegou:** o `Wave28MobileMarcacaoTest` carrega um GUARD que lê o fonte **do Service e do Controller PHP** — ele *"cobre o ponto interno"*, não o protótipo do Cowork. Então o meu build escapou do guard e ficou **3 semanas** oferecendo captura facial numa tela que [W] ia aprovar.

**Corrigido no build neste turno** (lei ratificada não é matéria de gosto — §5-bis: o que falha no alvo conserta-se aqui): estado, botão, bloqueio, limite e coluna removidos; o header do arquivo passa a citar a ADR 0383 e a base legal **correta** (LGPD **Art. 5º II + Art. 11** — a ADR registra que a citação do *Art. 9º* que circulava no projeto **está errada**); e a coluna virou **"Hash da marcação"**, porque o hash encadeado do NSR (Portaria 671/2021 **Art. 85**) é legítimo — o que a ADR proíbe é hash **derivado de biometria**.
**Medido depois** (rota `pt-mobile`, T1 estável): **0 menções de "selfie"** na tela · 8 cabeçalhos coerentes · `Bater ponto` **habilitado** (antes travava esperando a foto) · os 4 contratos presentes · console limpo.

**Lição para o protocolo:** guard que lê fonte de produção **não cobre o protótipo**. Toda ADR com cláusula *"como se reconhece violação"* precisa ser varrida **no build do Cowork também** — hoje ninguém faz isso, e é a segunda vez que dívida minha passou por "defeito sem dono".

---

## Ordem de execução — determinada por [W]

```
1º  BLOCO 1            (ALVO + rota própria)            ← sem isso nada de pixel
2º  BLOCO 2 + BLOCO 3  (travam merge)
3º  BLOCO 4            (UMA onda: emenda de charter + protótipo juntos)
4º  BLOCO 5            (por último)
—   D-CFG-POR-BUSINESS ADIADO, separado de todo o resto
```

## Placar do que já saiu neste turno

✅ **3 de 19 aplicadas no build** (`D-INTERC-ACOES` · `D-ESC-DESTROY` · `D-COLAB-CPF`), com console limpo.

### ⚠️ A lição mais cara deste ciclo: **eu apliquei uma decisão de [W] sem nunca executar o caminho dela**

O `D-ESC-DESTROY` saiu "✅ feito" do meu lado e estava **quebrado nas duas metades** — API de componente que eu supus em vez de ler, e tooltip em controle `disabled`, que é inalcançável por construção. **O que deixou passar não foi descuido de código, foi ausência de caso:** as 4 escalas do mock têm vínculo, então o ramo habilitado **nunca rodou** — e "renderizou sem erro no console" virou prova de que funcionava.

**Regra nova, para todo pacote:** decisão aplicada **exige o caso que a exercita**. Se o mock não tem o estado onde a regra vale (aqui: escala **sem** vínculo), **o mock ganha o estado** — senão a verificação mede o ramo errado. E **prop de componente do DS se LÊ na assinatura**, nunca se supõe: `tone`/`confirmLabel`/`onConfirm` foram ignorados em silêncio, que é o pior modo de falha possível.
✅ **+ o BLOCO 1 aberto e provado:** o **mecanismo da rota própria está feito** (`pt-` no `app.jsx` + `daRota` no `ponto-page.jsx`), e medido — `pt-aprovacoes` abre a aba, **`pt-espelho-1` abre o detalhe por deep link** (h2 "Wagner Ramos", 4 contratos), `ponto` volta ao painel. **1 das 9 páginas fechada** (`Espelho/Show`) e as 13 abas com rota. As **8 restantes** estão na thread **28**, com tabela de rota, regex única, ordem justificada e 1 PR cada.
✅ **As 8 emendas de charter + os 2 guards** escritos como proposta na thread **27** (com os 3 UC novos) — charter é canon, eu proponho e o [CL] aplica.
⏳ **9 aguardam onda** · ⛔ **1 adiada** (`D-CFG-POR-BUSINESS`) · **3 são de outros** (Code ×4, DS ×2, pacote).
🆕 **1 decisão nova nasceu da ressalva de [W]:** `D-CFG-IA-CAMINHO` — as flags de IA têm caminho de pacote/permissão hoje? **Medir antes de construir**, não supor.
