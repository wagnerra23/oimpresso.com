<!-- SESSÃO FRIA · abra esta thread sozinha. Read-order mínimo e prompt de abertura: `_SESSAO-FRIA.md` (linha "proposal retida — 5 PRs").
     Os ids de decisão (D-*) só existem em `ATA-DECISOES-2026-09-14.md` — leia a ata antes, ou as siglas ficam órfãs.
     Não leia as outras threads: cada uma é 1 PR e o contexto delas não é pré-requisito desta. -->

# 30 · PEDIDO — a proposal de 21/08 fechou: as 5 respostas de [W] e o que desce

> ✅ **RATIFICADO por [W] em 2026-09-14:** *"sim aceito as 5"*.
> **[W] disse "vai" nas 5** (2026-09-14). A proposal `ponto-contratos-retidos` (`status: open` desde 2026-08-21) **pode ser fechada como `accepted`** com as decisões abaixo. Este é o pedido executável.
> **R2 cumprida no mesmo turno:** onde a decisão foi contra o protótipo, **o protótipo já mudou** — medido no render, console limpo. Nada aqui espera o design.

---

## As 5 decisões, como devem entrar na ADR

| # | decisão de [W] | base |
|---|---|---|
| **D0** | **Cria a rota `/ponto/conformidade`** (read-only). Não muta nada; é pré-requisito prático do fechamento (mostra o que está bloqueado) | nova |
| **D1** | **Fechar exige permissão própria `ponto.fechar`** (não o `ponto.access` que abre o módulo). **"Reabrir" NÃO existe na v1** — depois de consolidada, correção entra por **anulação com trilha** | conservador: reabrir mês exportado em AFD desloca a fiscalização do dado para o **processo** |
| **D2** | **A palavra "assinada" sai; o ato continua.** O sistema registra **quem consolidou, quando e quais bloqueios aceitou**. Assinatura digital (ICP) **fora** — se precisar, ADR nova | não existe assinatura de ato no módulo; o `certificado_icp_path` assina **marcação**, e pode estar vazio |
| **D3** | **"Recusar" grava marcação NOVA de anulação** (`ORIGEM_ANULACAO`) apontando a original, com autor e motivo. **Nada de `UPDATE`, nada de `DELETE`** | **precedente, não escolha:** o módulo já tem `Marcacao::anular()` + `ORIGEM_ANULACAO`; `ponto_marcacoes` é append-only por força de lei |
| **D4** | **AFD/AEJ FORA do escopo do fechamento** — passo separado, na tela de Relatórios | `AEJ` existe em 4 arquivos PHP; `fechar_competencia`, em **nenhum**. Amarrar a tela nova ao gerador faz o passo 4 nascer quebrado |

---

## Já aplicado no protótipo (R2) — medido, não prometido

| decisão | o que mudou em `prototipo-ui/cowork/Wagner/` | prova no render |
|---|---|---|
| D1 | botão **"Reabrir" removido** de `ponto-fechamento.jsx`, com o motivo no comentário | `/Reabrir/` → **ausente** na tela |
| D2 | `title`, `confirm`, toast, rótulo do botão e a nota pós-consolidação reescritos: **"Consolidar aceitando os bloqueios"** · *"registrados com nome e data — **sem assinatura digital**"* | `/assinad/i` → **ausente** na tela inteira |
| D3 | `ponto-mobile.jsx`: a nota do REP-P declara *"recusar grava um lançamento novo de **anulação** (`ORIGEM_ANULACAO`) apontando a original"*, e o botão `Recusar` leva a regra no `title` | `ORIGEM_ANULACAO` **presente** |
| D4 | 4º passo passou de **"Gerar AFD / AEJ"** para **"AFD / AEJ — em Relatórios"**; o botão virou **"Ir para Relatórios"** | passo 4 = `"AFD / AEJ — em Relatórios"` |
| — | (do mesmo ciclo) **biometria removida** do REP-P por **ADR 0383** | `/selfie/i` → **ausente** |

---

## O que o [CL] faz — 5 PRs, nesta ordem

**PR 1 · Conformidade (D0)** — a única sem risco jurídico.
Rota `ponto.conformidade` + controller **read-only** + Page. Fonte da apuração: as 6 verificações que o protótipo já implementa em `ponto-fechamento.jsx :14-60` (`achados()`): interjornada **Art. 66** · intrajornada **Art. 71** · limite de HE **Art. 59** · tolerância **Art. 58 §1º** · **NSR fora de sequência** (Portaria 671/2021 Anexo I) · **colaborador sem PIS** (bloqueia AFD e eSocial S-2230).
**Sem contrato de tela neste PR** — contrato nasce com o alvo existindo, no PR seguinte (contrato com `alvo` inexistente é vermelho permanente e pinta todo PR de UI: é o motivo original da retenção).

**PR 2 · Fechamento, passos 1–3 (D1 + D2)**
Permissão `ponto.fechar` própria · sem rota de reabertura · consolidação com bloqueios aceitos registra **autor + timestamp + lista** na trilha · copy **literal do protótipo** (já corrigida). Non-Goals novos no charter: *"não reabre competência"* e *"não assina digitalmente a exceção"*.

**PR 3 · ValidacaoMobile (D3)**
`Validar`/`Recusar` sobre marcação mobile. **Recusar = `Marcacao::anular()`** (lançamento novo com `ORIGEM_ANULACAO`, ref. à original, autor, motivo). Guard de teste: *"recusar não faz UPDATE nem DELETE em `ponto_marcacoes`"*.

**PR 4 · REP-P — bater ponto**
GPS ≤ 500m · clock-skew ≤ 30s · geofence **sinaliza** (não bloqueia) · NSR + hash encadeado. **Sem biometria** (ADR 0383) — o guard do `Wave28MobileMarcacaoTest` já cobre o PHP.

**PR 5 · AFD/AEJ (D4)** — item próprio em **Relatórios**, não no fechamento.

**Os 2 contratos retidos** (`ponto-fechamento`, `ponto-rep-p`) entram **junto das telas** (PR 2 e PR 4), nunca antes.

---

## Guard que falta, e é a lição deste ciclo

O guard da **ADR 0383** lê o fonte **do Service e do Controller PHP**. Ele **não lê o protótipo do Cowork** — e foi por isso que a captura facial sobreviveu **3 semanas** numa tela que [W] ia aprovar.
**Pedido:** estender a varredura dos termos proibidos (`selfie_base64`, `SELFIE_MIN_BYTES`, `verificarBiometria`, sufixo derivado no `dispositivo_id`) a **`prototipo-ui/cowork/**`**. Dono candidato: o `ds-guard`/`integrity-check`, que já varrem o espelho — **estender o que existe, não criar script novo** (LC-19).
**Generalizando:** toda ADR com cláusula *"como se reconhece violação"* precisa de varredura **nos dois lados**. Hoje o protótipo é ponto cego, e ele é o alvo que a produção copia.

---

## PARAR SE

- o `fechar_competencia` aparecer em algum arquivo que eu não vi ⇒ **parar**: minha medição diz 0 e ela é de leitura, não de execução;
- a rota da Conformidade exigir escrita para funcionar ⇒ **parar**: D0 foi aprovada como **read-only**, e o "não muta nada" é a razão pela qual ela passou sem as outras decisões;
- qualquer PR tentar descer contrato sem a tela ⇒ **parar**: é o vermelho permanente que a proposal documentou.


---

## Fechar a proposal — bloco de frontmatter para o [CL] aplicar

Arquivo: `memory/decisions/proposals/2026-08-21-ponto-contratos-retidos-decisao-w.md`

```yaml
status: accepted          # era: open
decided_at: '2026-09-14'
decided_by:
  - W
```

E, no fim do corpo, a linha de ratificação (o padrão que a ADR 0383 usa):

```md
**Ratificação:** [W] em 2026-09-14 — "sim aceito as 5", após os defaults propostos pelo Cowork
(`cowork-inbox/ponto/playbook/29-decisoes-retidas-default.md`). As 4 decisões do §5 ficam:
1. `ponto.fechar` próprio; **reabrir não existe na v1** (correção por anulação com trilha).
2. "Exceção assinada" **deixa de existir como conceito**: registra-se autor, data e bloqueios aceitos.
   Assinatura digital exige ADR nova.
3. Recusar marcação = lançamento novo com `ORIGEM_ANULACAO` (`Marcacao::anular()`), nunca UPDATE/DELETE.
4. AFD/AEJ **fora** do escopo do fechamento — geração vive em Relatórios.
Acrescentada a decisão **D0** (não estava nas 4): criar `/ponto/conformidade` read-only, que não
depende de nenhuma das outras e é pré-requisito prático do fechamento.
Execução: `cowork-inbox/ponto/playbook/30-pedido-retidos-aprovado.md` (5 PRs).
```

> **Por que eu não commito isto:** `memory/decisions/**` é canon do repo — eu proponho o bloco, o [CL] aplica. Proposal que eu virasse para `accepted` do meu lado seria decisão minha com a assinatura de [W].
