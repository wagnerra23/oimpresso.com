<!-- SESSÃO FRIA · abra esta thread sozinha. Read-order mínimo e prompt de abertura: `_SESSAO-FRIA.md` (linha "defaults das retidas (RESPONDIDA)").
     Os ids de decisão (D-*) só existem em `ATA-DECISOES-2026-09-14.md` — leia a ata antes, ou as siglas ficam órfãs.
     Não leia as outras threads: cada uma é 1 PR e o contexto delas não é pré-requisito desta. -->

# 29 · As 4 decisões retidas com DEFAULT proposto — para [W] dizer "vai" ou "muda"

> **Pedido de [W] 2026-09-14:** *"não sei responder, pode sugerir e fazer o que falta"*.
> **O que eu posso e o que eu não posso:** eu **não invento semântica jurídica**. O que eu faço é **derivar cada resposta de um precedente que já existe no sistema** e, onde não houver precedente, propor o **default conservador** (o que remove risco) dizendo qual é a consequência de cada lado. **Você só precisa dizer "vai" ou "muda".**
> **Base lida neste ciclo:** a proposal `ponto-contratos-retidos` (inteira) · `ADR 0383` (inteira) · `ponto-fechamento.jsx` e `ponto-mobile.jsx` (fonte) · charters do Ponto.

---

## ⚠️ Correção minha, antes das 4

Eu disse no chat que **a Conformidade podia descer sozinha** porque não muta nada. **Errado, e o eixo que eu ignorei é o receptor:** a proposal mede que as 4 telas não têm **Blade, rota, controller nem backend**. Conformidade não muta — mas **também não tem rota**. Então ela não precisa das 4 decisões; precisa de **uma**, muito menor, que abro como item **0** abaixo.

---

## 0 · Conformidade — a decisão pequena (1 linha, sem efeito jurídico)

**Pergunta:** cria a rota `/ponto/conformidade` (read-only)?
**Por que é diferente das outras 3:** ela **só lê**. Roda a apuração das violações CLT do mês (interjornada Art. 66 · intrajornada Art. 71 · limite de HE Art. 59 · tolerância Art. 58 §1º · sequência de NSR) e lista achados. Não fecha competência, não escreve marcação, não promete ato jurídico. **Nenhuma das decisões 1–4 a afeta.**
**DEFAULT que eu proponho: SIM, cria a rota.** É a única das 4 telas que entrega valor sem abrir risco — e quem vai fechar a competência precisa dela **antes** para saber o que está bloqueado.
**Se você disser "vai":** o pedido é rota + controller read-only + Page, sem contrato de tela nesta onda (contrato depois, quando o alvo existir — contrato com `alvo` inexistente nasce vermelho permanente e pinta todo PR de UI, que é o motivo original da retenção).

---

## 1 · Quem fecha a competência, e reabrir é permitido?

**Precedente no sistema:** não há. `fechar_competencia` aparece em **0 arquivos** PHP.
**Onde está o risco:** o protótipo oferece **"Reabrir"** depois de fechado, e a proposal registra: *"reabrir competência fechada tem consequência em fiscalização"*.

**DEFAULT conservador que eu proponho:**
- **Quem fecha:** permissão **própria** (`ponto.fechar`), **não** o `ponto.access` que abre o módulo. Quem consulta ponto ≠ quem fecha o mês.
- **Reabrir: NÃO EXISTE na v1.** O botão sai. Correção depois do fechamento acontece **só** por anulação com trilha (o caminho que o módulo já tem e que o próprio protótipo anuncia: *"depois disso a marcação só muda por anulação com trilha de auditoria"*).

**Por que esse default:** "Reabrir" é o único dos dois que **não tem volta barata**. Se você fechar o mês e não puder reabrir, o pior caso é uma anulação registrada — que é auditável e legal. Se puder reabrir, o pior caso é um mês fechado, exportado em AFD e depois alterado: aí a pergunta na fiscalização deixa de ser sobre o dado e passa a ser sobre o **processo**.
**Custo de mudar depois:** baixo. Ligar "Reabrir" numa v2 é acrescentar; desligar depois de alguém ter usado é migração + explicação.

**Se você disser "vai":** eu removo o botão "Reabrir" do protótipo no mesmo pacote (sua **R2**), e o pedido nasce com `ponto.fechar` declarado.

---

## 2 · O que é "exceção assinada"?

**Precedente no sistema:** nenhum. Não existe assinatura de ato no módulo (o `certificado_icp_path` do config é para **assinar marcação**, não para assinar exceção — e o próprio painel mostra que ele pode estar vazio).
**Onde está o risco:** o botão do protótipo diz *"Consolidar com exceções"* e a descrição diz *"registra os bloqueios como exceção assinada"*. A proposal é direta: *"sem isso, o botão promete um ato jurídico que o sistema não pratica"*.

**DEFAULT conservador que eu proponho: a palavra "assinada" SAI. O ato continua.**
- O que o sistema **pratica hoje**: registrar quem consolidou, quando, e **quais bloqueios foram aceitos** — nominalmente, com timestamp e usuário, na trilha de auditoria.
- Então o botão passa a ser **"Consolidar aceitando os bloqueios"**, e a nota diz o que de fato acontece: *"os N bloqueios ficam registrados com o seu nome e a data"*.
- **Assinatura digital (ICP-Brasil) fica fora** — se um dia precisar, é ADR nova, com a hipótese e o certificado declarados.

**Por que esse default:** é o único que **não mente**. Chamar de "assinada" uma consolidação sem assinatura é dívida de copy com consequência legal — a mesma família do meu `AAAAMMDDHHMMSSNNN` que eu inventei no identificador do REP e que você viu neste ciclo.
**Se você disser "vai":** copy corrigida no protótipo no mesmo pacote, e o charter recebe o Non-Goal *"não assina digitalmente a exceção"*.

---

## 3 · Como "recusar" uma marcação sem violar append-only?

**⚠️ Esta não é escolha de gosto — o repo já respondeu.**
**Precedente medido:** `ponto_marcacoes` é append-only **por força de lei** (Portaria 671/2021), e o módulo **já tem** o padrão para desfazer: `Marcacao::anular()` + a origem `ORIGEM_ANULACAO`. A própria ADR 0383 cita o `MarcacaoService` canônico como o dono do NSR sequencial + hash encadeado.

**DEFAULT (na prática, o único caminho compatível): "Recusar" grava marcação NOVA de anulação**, com `ORIGEM_ANULACAO`, apontando a marcação original, com autor e motivo. **Nada é `UPDATE`. Nada é `DELETE`.**
**A alternativa (entidade separada de validação) é pior aqui:** cria um segundo lugar onde "o que valeu" mora, e aí o espelho passa a depender de um join para responder *"esta marcação conta?"* — é a porta para dois números diferentes do mesmo mês.

**Se você disser "vai":** o pedido já nasce com o modelo de dado fechado (sem decisão pendente), e a tela `ValidacaoMobile` só precisa de copy honesta: **"Recusar" não apaga — registra a anulação.**

---

## 4 · AFD/AEJ entram no escopo do fechamento?

**Precedente medido:** `AEJ` aparece em **4 arquivos** PHP; `fechar_competencia`, em **nenhum**.
**DEFAULT que eu proponho: FORA. É passo separado.**
- O fechamento (passos 1–3) é **estado interno**: pré-checagem, consolidação, fecho.
- Gerar AFD/AEJ é **artefato fiscal com layout posicional** (Portaria 671/2021 Anexo I) — o mesmo tipo de coisa que hoje vive em Relatórios, onde **só o Espelho tem geração implementada** e os outros retornam 501.

**Por que:** amarrar a tela nova de fechamento a um gerador fiscal que não existe faz o **passo 4 nascer quebrado**, e o passo 4 quebrado transforma os passos 1–3 (que funcionam) num fluxo que "não termina". Separado, o fechamento fecha e o AFD sai pela tela de Relatórios, que é o lugar onde o usuário já vai buscar arquivo legal.
**Se você disser "vai":** o 4º passo do protótipo vira **ponteiro** para Relatórios (não botão de geração), e o charter recebe o Non-Goal *"não gera arquivo fiscal — isso é Relatórios"*.

---

## Placar do que cada resposta destrava

| você diz | destrava |
|---|---|
| **0 · rota da Conformidade** | 1 tela inteira, sem risco jurídico, e ela é **pré-requisito prático** do fechamento (mostra o que está bloqueado) |
| **1 · `ponto.fechar` + sem "Reabrir"** | os 3 primeiros passos do Fechamento saem do bloqueio |
| **2 · a palavra "assinada" sai** | o botão "Consolidar com exceções" deixa de prometer ato que não existe |
| **3 · `ORIGEM_ANULACAO`** | `ValidacaoMobile` sai do bloqueio com modelo de dado fechado |
| **4 · AFD/AEJ fora** | o Fechamento deixa de depender de um gerador fiscal inexistente |

**Se você disser "vai" nas 5**, a proposal de 21/08 fecha, os **2 contratos retidos** passam a ter alvo (e param de ser vermelho permanente), e as 4 telas saem do limbo — na ordem: Conformidade → Fechamento (1–3) → ValidacaoMobile → AFD/AEJ como item próprio em Relatórios.

## O que eu NÃO fiz, de propósito

**Não mexi no protótipo por conta destes 5.** Por **R2**, quem muda é o protótipo — **depois** da sua palavra, no mesmo pacote. Mudar antes seria eu decidindo por você e depois te apresentando como fato, que é exatamente o vício que este ciclo inteiro esteve corrigindo.

**Não escrevi os 2 contratos.** Contrato com `alvo` inexistente nasce vermelho permanente e **pinta todo PR de UI do projeto** (medido na proposal). Eles nascem no mesmo PR da tela, não antes.
