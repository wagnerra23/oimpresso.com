---
sessao: "08i"
titulo: Configuração da empresa em React — saída (F3-5a, E-mail e SMS)
playbook: sistema
thread: "08"
dono: "[CL]"
data: "2026-10-13"
tipo: recibo-parcial
base: wagnerra23/oimpresso.com@claude/sistema-08-f3-4-sistema (empilhado no #9210)
---
# _saida-08i · F3-5a · abas E-mail e SMS

Integrações foi dividida em três: **F3-5a** E-mail + SMS (este), **F3-5b** Pontos de fidelidade + Módulos,
**F3-5c** Rótulos personalizados.

## Entregue
- Aba **E-mail**: usar o servidor do sistema (quando o superadmin libera), driver, servidor, porta, criptografia,
  usuário, senha, remetente.
- Aba **SMS**: Nexmo, Twilio ou requisição própria (URL, método, parâmetros de destinatário e mensagem, 3 cabeçalhos,
  10 parâmetros), mostrando só os campos do serviço escolhido.
- Segredos **só de escrita** — `mail_password`, `nexmo_secret`, `twilio_token`, `header_val_*`, `param_val_*`: não vão
  para as props; a tela recebe em `segredos_gravados` quais existem; em branco mantém, digitar troca.
- `postBusinessSettings` mantém o segredo gravado quando o envio não traz a chave (mesma regra do #9198). A Blade sempre
  manda todos os campos, então para ela nada muda — inclusive apagar.
- `EmpresaContratoTest` UC-EMP-12; UC-EMP-01 reescrito: o corpo agora traz e-mail/SMS, sem os segredos.

## Decisão registrada
Valores de cabeçalho e parâmetro do SMS próprio são tratados como segredo porque é onde vai o token da API
(o protótipo mostra `Authorization: Bearer …`). Custo: a tela não mostra esses valores; em branco mantém.

## Fica na tela antiga
Testar envio de e-mail/SMS (o teste usa a senha do formulário) e apagar um segredo gravado.

## Falta
F3-5b Pontos de fidelidade + Módulos · F3-5c Rótulos · F4 smoke biz=1. Cutover é [W].
