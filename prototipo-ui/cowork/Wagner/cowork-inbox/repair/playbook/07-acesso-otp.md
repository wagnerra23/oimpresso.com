---
sessao: "07"
titulo: Receber acesso ao portal pelo celular da OS
dono: "[CL]"
base: 93ff8a960b74
---
# 07 · Receber acesso pelo celular da OS

Só com a D-ACESSO respondida. Caso: UC-COS-23 em `cowork-inbox/repair/ConsultaOs-link.casos.md`. Forma: bloco "Acompanhar todas as suas OS num lugar só" de `repair-portal.jsx` (liga/desliga na config da loja).

- Código de 6 dígitos para o celular **da OS** (mascarado), 10 minutos, 3 tentativas; nunca para outro número.
- Contato real sem login → cria o login do portal pelo mesmo caminho do `ContactLoginController` e leva à aba Assistência (Crm 25). **Cliente balcão (walk-in) nunca ganha acesso.**
- Canal (SMS ou WhatsApp) = a D-ACESSO.
