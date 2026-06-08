# 00 — Perfil do Usuário / Cliente

## Identificação

- **Empresa:** WR2 Sistemas
- **Contato principal:** Eliana
- **E-mail:** eliana@wr2.com.br
- **Domínio da empresa:** desenvolvimento e comercialização de sistemas de gestão (integradora/vendor UltimatePOS no Brasil)
- **Produto-alvo:** ERP UltimatePOS v6 + Essentials & HRM já instalado em clientes da WR2

## Papel presumido

A Eliana (ou a WR2) atua como **fornecedor de software** que precisa estender o UltimatePOS com um módulo de **ponto eletrônico em conformidade com a legislação brasileira** para oferecer aos seus próprios clientes. Não é o usuário final do ponto — é quem especifica, constrói e vende.

## O que o cliente valoriza

Capturado da conversa ao longo da concepção do projeto:

- **Economia de crédito/custo de IA.** Pede explicação antes de trabalho grande, prefere iteração incremental, checa uma tela antes de pedir as 9. → Efeito prático: **confirmar escopo com perguntas curtas antes de produção massiva**, entregar em incrementos testáveis, evitar boilerplate desnecessário.
- **Integração não-invasiva com o UltimatePOS.** Não quer romper o que já funciona. → Escolhemos a **Opção C**: produto WR2 que estende o UltimatePOS como módulo `Modules/PontoWr2/`, sem alterar core.
- **UI coerente com o que já existe.** UltimatePOS tem sidebar própria com vários módulos. O Ponto WR2 deve ocupar **apenas 1 item** nessa sidebar e ter navegação interna em **abas horizontais** dentro do módulo.
- **Conformidade legal como requisito, não diferencial.** Portaria 671/2021, CLT, LGPD, eSocial — são obrigatórios, não opcionais.
- **Pragmatismo.** Aceita protótipos em HTML+Tailwind CDN para validar visual antes de investir em React/shadcn oficial. Evita gold-plating.

## O que o cliente NÃO valoriza

- Jargão técnico sem justificativa de negócio
- Documentação inflada que não ajuda a executar
- Decisões arquiteturais sem comparação com alternativas
- Entregas monolíticas — prefere passo a passo revisável

## Idioma e tom

- **Idioma de comunicação:** PT-BR, informal mas profissional
- **Ortografia:** o usuário escreve rápido, comete typos ("ultmatepos", "meu" por "menu", "contineu"). Não corrija — entenda e continue.
- **Respostas longas:** só quando pedido. Para perguntas simples, resposta curta com link direto.

## Sinal amarelo

- Se o cliente disser "não deu certo" ou "não funciona", **investigue causa raiz antes de tentar de novo**. Ele deu feedback de que React+Babel via CDN falhou → migramos para HTML puro, e funcionou.

---

**Última atualização:** 2026-04-18
