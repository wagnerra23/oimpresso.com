---
title: "Runbook · NFSe — operação da emissão no Sistema Nacional"
module: NFSe
owner: W
status: ativo
last_validated: "2026-11-17"
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0062-separacao-runtime-hostinger-ct100
---

# Runbook · NFSe — operação

> **O que este runbook cobre:** a Sefin fora do ar, o certificado vencido, a rejeição, o
> cancelamento, a nota presa em `processando` e o passo de ligar produção para uma empresa.
> Substitui as seções operacionais do [RUNBOOK.md](RUNBOOK.md), que é de 2026-06 e fala de
> provedor terceiro (está marcado como história desde 2026-08-16).
>
> **Como foi validado (2026-11-17):** cada passo foi conferido contra o código de `origin/main`
> depois dos lotes 0 e 2 da auditoria e contra uma leitura de produção. **Nenhum passo foi
> executado contra a Sefin**: até esta data nenhuma NFS-e real foi emitida. O fluxo completo está
> no [SDD](SDD-emissao-nfse-v1.0.md).
>
> **Regra para todo o runbook:** nada de `UPDATE`/`INSERT` à mão em produção (tinker, SQL,
> phpMyAdmin). Onde não existe comando, o passo diz isso e para ([proibicoes](../../proibicoes.md),
> "Mexeu, registra").

---

## 0. Antes de qualquer coisa: onde olhar

| O quê | Como |
|---|---|
| Saúde do módulo | `php artisan nfse:health` (`--business=<id>`, `--json`, `--alert` sai 2 em FAIL e 1 em WARN) |
| Log da emissão | `storage/logs/nfse-AAAA-MM-DD.log` (canal `nfse`, 90 dias) |
| Notas da empresa | tela `/nfse`; nota em `erro` mostra o motivo no detalhe |
| Fila | tabela `jobs` com `queue = 'nfse'`; falhos em `failed_jobs` |

**Estado em 2026-11-17 (leitura de produção):** 1 config (empresa 1, homologação, certificado
vinculado), `nfse_emissoes` vazia, `nfse:health` com **FAIL: certificado vencido (business 1)**,
fila `nfse` com 0 jobs. É fato datado: rode de novo antes de usar.

---

## 1. Antes da primeira emissão real: a fila `nfse` não tem consumidor

A emissão é assíncrona: `EmitirNfseJob` vai para a fila `nfse`. Em produção a fila é `database`
(`QUEUE_CONNECTION=database`) e o Laravel só processa jobs por `queue:work` agendado no
`schedule`. Em 2026-11-17, `php artisan schedule:list` em produção tem 7 entradas de
`queue:work` e **nenhuma lê `nfse`** (a do backlog lê `nfe,default,...`).

**Efeito:** a primeira emissão ficaria na tabela `jobs`, e a nota nem chegaria a ser criada (a
linha nasce dentro do job). A tela teria dito "enviada para processamento".

**O que fazer:** antes de ligar a primeira empresa em produção, pôr um `queue:work` que leia
`nfse` no `schedule` (mudança de código em `app/Console/Kernel.php`, por PR — não é passo de
operação). Confira depois:

```bash
php artisan schedule:list | grep "queue:work" | grep nfse
```

Vazio = ainda sem consumidor.

---

## 2. Sefin fora do ar ou lenta

**Sintoma:** a nota fica em `processando` com a mensagem de timeout; o log traz
`NFSe sem resposta da Sefin`.

**O que o sistema já faz sozinho:**
1. Falha de **conexão** (inclui timeout de 30s): 3 tentativas dentro do serviço, com espera de
   1s, 2s e 4s.
2. Depois disso a linha fica `processando` e o job tenta de novo até 3 vezes, com 60s entre as
   tentativas, **com o mesmo nº de DPS**. Se a Sefin já tinha recebido, ela devolve "duplicada"
   e o sistema consulta a nota em vez de criar outra.
3. Esgotado o job, a linha **continua `processando`** — de propósito: a nota pode ter sido aceita.

**Atenção:** se a Sefin responder com HTTP 5xx **com corpo de erro**, o sistema trata como
rejeição: a nota vai para `erro` e o job não retenta. Ver §4.

**O que fazer:**
1. Confira se o problema é da Sefin: status do Emissor Nacional em gov.br/nfse.
2. Não reenvie pela tela enquanto a nota estiver `processando`: o reenvio devolve a mesma linha
   sem chamar a Sefin.
3. Se a nota ficou `processando` depois da Sefin voltar, siga §6.

---

## 3. Certificado A1 vencido, ausente ou com senha errada

**Sintoma:** `nfse:health` com `cert_vencimento_alarme` em FAIL ("certificado vencido") ou WARN
("vencendo em < 30d", "sem certificado"); notas em `erro` com "Certificado expirado em …",
"Nenhum certificado A1 configurado", "Nenhum certificado A1 no payload para assinar a DPS"
(arquivo ou senha que não abrem) ou "Não foi possível assinar a DPS". Nada foi à Sefin.

**Renovar:**

```bash
php artisan nfse:importar-cert --pfx=/caminho/absoluto/novo.pfx --senha='***' --business=<id>
```

- **Sempre passe `--business`.** O padrão do comando é `1`: sem a opção, o certificado vai para
  a empresa 1.
- O comando valida o arquivo e grava cifrado em `nfe_certificados` (pelo `CertificadoService` do
  NfeBrasil) como o certificado **ativo** da empresa, desativando o anterior.
- A senha vai na linha de comando: não deixe no histórico do shell e apague o `.pfx` do disco
  depois de conferir.
- Enviar o certificado pela tela `/fiscal/config?tab=cert` faz o mesmo e é o caminho normal:
  NF-e e NFS-e usam o mesmo certificado ativo. _Até 2026-11-18 a NFS-e lia
  `nfse_provider_configs.cert_id`, que só o comando atualizava._

**Confira:**

```bash
php artisan nfse:health --business=<id>
```

`cert_vencimento_alarme` deve dar OK. Notas que ficaram em `erro` por certificado: reenvie pela
tela com os mesmos dados; o sistema reaproveita a mesma linha e o mesmo nº de DPS.

_O FAIL "certificado de outra empresa" deixou de existir em 2026-11-18: o certificado é buscado
pelo `business_id` da própria empresa, nunca por ponteiro._

---

## 4. Rejeição da Sefin

**Sintoma:** nota em `erro` com a mensagem da Sefin (já redigida de CPF/CNPJ). O job não
retenta.

| Mensagem / código | Provável causa | O que corrigir |
|---|---|---|
| código `E501…` | ISS inválido | alíquota ou ISS retido do formulário |
| código `E17…` | regra da Sefin (mensagem segue o código) | ler a mensagem; corrigir o dado |
| menciona CNPJ/CPF | documento do tomador | cadastro do cliente |
| menciona LC116/serviço, ou desdobro nacional | código de serviço / cTribNac | escolher o desdobro no formulário |
| código `L1…` | prestador não autorizado | habilitação da empresa no Sistema Nacional |
| "Regime tributário da empresa não configurado" | `nfe_business_configs.regime` vazio | Fiscal › Configuração |
| "CNPJ do prestador não configurado" | `prestador_cnpj` vazio na config de NFS-e | ver §7 |
| "Configuração NFSe não encontrada" | empresa sem config de NFS-e | ver §7 |

**Reenvio:** com os **mesmos** dados (tomador, valor, descrição, competência), o reenvio
reaproveita a linha em `erro`. Com dados **corrigidos**, nasce uma linha nova e a antiga fica em
`erro` como registro.

---

## 5. Cancelamento

**Pela tela:** detalhe da nota → Cancelar → motivo de 15 a 255 caracteres. Exige permissão
`nfse.cancel`. Só nota `emitida` com número cancela.

**O que acontece:**
- O sistema usa o ambiente e o certificado **da empresa da nota**.
- Só grava `cancelada` se a Sefin confirmar. Sem confirmação, a tela mostra "O provedor não
  confirmou o cancelamento. A nota continua emitida." e nada muda.
- Ao virar `cancelada`, a retenção da nota é estornada no Financeiro.
- A nota cancelada continua no banco e na lista.

**Como cancela (desde 2026-11-18):** pelo **evento de cancelamento assinado** (e101101) do padrão
nacional — `POST /nfse/{chaveAcesso}/eventos` com o pedido assinado pelo certificado da empresa.
Precisa da **chave de acesso** da nota (gravada na emissão) e do **CNPJ do prestador** na config
NFSe. Se a Sefin recusar, a tela mostra "A Sefin recusou o cancelamento: <motivo dela>" e a nota
fica `emitida`. Até 2026-11-18 o sistema mandava `DELETE /nfse/{numero}`, que não está no manual.

**Limite conhecido:** o envio real do evento à Sefin ainda não foi exercitado (só testes com a
resposta simulada). Se a Sefin recusar o evento, o cancelamento precisa ser feito no Emissor
Nacional (gov.br/nfse) e **a nota fica `emitida` no oimpresso**: não existe comando para marcar
como cancelada, e não se faz isso no banco.

---

## 6. Nota presa em `processando`

**Quando acontece:** timeout esgotado (§2) ou DPS duplicada sem chave de acesso.

**O que o sistema não faz:** não consulta a nota pelo nº da DPS (o endpoint está no manual da
Sefin, que não está no repo) e não tem comando para reprocessar a linha.

**O que fazer:**
1. O nº da DPS é o id da nota no oimpresso: o número na URL `/nfse/<id>` (e no título
   "NFSe #<id>" do detalhe enquanto a nota não tem número).
2. Procure a nota no Emissor Nacional (gov.br/nfse) pelo nº da DPS e o CNPJ do prestador.
3. Se a nota existe lá: hoje não há caminho no sistema para gravar o número; registre o caso e
   abra tarefa (é a falta da consulta por DPS).
4. Se não existe: também não há caminho para devolver a linha ao fluxo. Mesma tarefa.

Não altere o status no banco.

---

## 7. Ligar uma empresa (homologação → produção)

O ambiente é **por empresa** (`nfse_provider_configs.ambiente`): ligar produção numa empresa
não muda as outras. Sem config, o sistema emite em homologação.

**Checklist antes:**
- [ ] §1 resolvido (a fila `nfse` tem consumidor).
- [ ] Certificado vigente e ligado na config (§3); `nfse:health --business=<id>` sem FAIL.
- [ ] `nfe_business_configs.regime` da empresa preenchido.
- [ ] Config com CNPJ, inscrição municipal, município IBGE, série numérica, LC 116 e alíquota padrão.
- [ ] Uma nota emitida e cancelada em homologação para a empresa.
- [ ] Decisão sobre o DANFSe: hoje nenhuma nota tem PDF (US-NFSE-016).

**Como trocar o ambiente ou criar a config:** não existe tela nem comando. O seeder do módulo só
cobre a empresa 1. Criar config e trocar ambiente exige o comando ou a tela da US-NFSE-014 —
não faça por tinker/SQL.

---

## 8. Referências

- [SDD-emissao-nfse-v1.0.md](SDD-emissao-nfse-v1.0.md) — fluxo, estados e riscos
- [RUNBOOK-emitir.md](RUNBOOK-emitir.md) · [RUNBOOK-index.md](RUNBOOK-index.md) · [RUNBOOK-show.md](RUNBOOK-show.md) — as telas
- [SPEC.md](SPEC.md) — US-NFSE-007, 013, 014, 015, 016, 017, 020

---

- **2026-11-17** — Primeira versão. Auditoria NFSe, lote 5.3.
