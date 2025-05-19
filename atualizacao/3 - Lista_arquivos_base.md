# Lista de Arquivos Base do Projeto Laravel

Este documento lista os arquivos primários/base do Laravel, os arquivos da pasta `layouts` (incluindo a subpasta `partials`), e o arquivo relacionado ao layout `app\Http\AdminlteCustomPresenter.php`.

## Arquivos Primários/Base do Laravel


| Arquivo/Diretório | Localização                             | Função                                                                       |
| ------------------ | ----------------------------------------- | ------------------------------------------------------------------------------ |
| web.php            | `routes/web.php`                          | Define as rotas da aplicação, essencial para o roteamento HTTP.              |
| app.blade.php      | `resources/views/layouts/app.blade.php`   | Layout principal que serve como base para outras views.                        |
| welcome.blade.php  | `resources/views/welcome.blade.php`       | View padrão do Laravel para a página inicial (`/`).                          |
| HomeController.php | `app/Http/Controllers/HomeController.php` | Controlador para a página inicial autenticada.                                |
| Auth Views         | `resources/views/auth/`                   | Contém views de autenticação (ex.:`login.blade.php`, `register.blade.php`). |

## Arquivos da Pasta `layouts`

### Diretamente em `layouts`


| Arquivo/Diretório   | Localização                                  | Função                                                |
| -------------------- | ---------------------------------------------- | ------------------------------------------------------- |
| app.blade.php        | `resources/views/layouts/app.blade.php`        | Layout principal que serve como base para outras views. |
| auth.blade.php       | `resources/views/layouts/auth.blade.php`       | Layout para páginas de autenticação.                 |
| auth2.blade.php      | `resources/views/layouts/auth2.blade.php`      | Layout alternativo para autenticação.                 |
| guest.blade.php      | `resources/views/layouts/guest.blade.php`      | Layout para usuários não autenticados (convidados).   |
| home.blade.php       | `resources/views/layouts/home.blade.php`       | Layout para a página inicial.                          |
| install.blade.php    | `resources/views/layouts/install.blade.php`    | Layout para o processo de instalação.                 |
| restaurant.blade.php | `resources/views/layouts/restaurant.blade.php` | Layout específico para o módulo de restaurante.       |

### Subpasta `partials`


| Arquivo/Diretório             | Localização                                                     | Função                                       |
| ------------------------------ | ----------------------------------------------------------------- | ---------------------------------------------- |
| calculator.blade.php           | `resources/views/layouts/partials/calculator.blade.php`           | Componente de calculadora reutilizável.       |
| css.blade.php                  | `resources/views/layouts/partials/css.blade.php`                  | Inclui estilos CSS específicos.               |
| error.blade.php                | `resources/views/layouts/partials/error.blade.php`                | Template para exibição de erros.             |
| extracss.blade.php             | `resources/views/layouts/partials/extracss.blade.php`             | Inclui CSS adicional.                          |
| extracss_auth.blade.php        | `resources/views/layouts/partials/extracss_auth.blade.php`        | CSS adicional para páginas de autenticação. |
| footer.blade.php               | `resources/views/layouts/partials/footer.blade.php`               | Rodapé padrão da aplicação.                |
| footer-pos.blade.php           | `resources/views/layouts/partials/footer-pos.blade.php`           | Rodapé para o módulo POS (Point of Sale).    |
| footer-restaurant.blade.php    | `resources/views/layouts/partials/footer-restaurant.blade.php`    | Rodapé para o módulo de restaurante.         |
| header.blade.php               | `resources/views/layouts/partials/header.blade.php`               | Cabeçalho padrão da aplicação.             |
| header_auth.blade.php          | `resources/views/layouts/partials/header_auth.blade.php`          | Cabeçalho para páginas de autenticação.    |
| header_notifications.blade.php | `resources/views/layouts/partials/header_notifications.blade.php` | Exibe notificações no cabeçalho.            |
| header-pos.blade.php           | `resources/views/layouts/partials/header-pos.blade.php`           | Cabeçalho para o módulo POS.                 |
| header-restaurant.blade.php    | `resources/views/layouts/partials/header-restaurant.blade.php`    | Cabeçalho para o módulo de restaurante.      |
| home_header.blade.php          | `resources/views/layouts/partials/home_header.blade.php`          | Cabeçalho para a página inicial.             |
| javascripts.blade.php          | `resources/views/layouts/partials/javascripts.blade.php`          | Inclui scripts JavaScript.                     |
| language_btn.blade.php         | `resources/views/layouts/partials/language_btn.blade.php`         | Botão de seleção de idioma.                 |
| logo.blade.php                 | `resources/views/layouts/partials/logo.blade.php`                 | Componente de logo da aplicação.             |
| module_form_part.blade.php     | `resources/views/layouts/partials/module_form_part.blade.php`     | Parte de formulário para módulos.            |
| notification_list.blade.php    | `resources/views/layouts/partials/notification_list.blade.php`    | Lista de notificações.                       |
| search_settings.blade.php      | `resources/views/layouts/partials/search_settings.blade.php`      | Configurações de busca.                      |
| sidebar.blade.php              | `resources/views/layouts/partials/sidebar.blade.php`              | Barra lateral da aplicação.                  |

## Arquivo Relacionado ao Layout


| Arquivo/Diretório          | Localização                          | Função                                                                                         |
| --------------------------- | -------------------------------------- | ------------------------------------------------------------------------------------------------ |
| AdminlteCustomPresenter.php | `app/Http/AdminlteCustomPresenter.php` | Classe personalizada para customizar a apresentação do AdminLTE (ex.: menus, barras laterais). |

# Customizações do layout principal do sistema

Este documento lista as ações necessárias para atualizar os arquivos do projeto UltimatePOS, incluindo a remoção do Bootstrap, adição de DevExtreme, Vite, Tailwind 4 e DaisyUI 5. A tabela abaixo detalha os arquivos/diretórios, ações, resumo das atualizações e arquivos com instruções detalhadas.

tem que remover o bootstrap e instalar a devexpress.
com a remoção do vendor e do init.js tirou o bootstrap e tem que gerar o arquivo mimificado novamente.

deve existir um arquivo chamado layout.md, que vai com ter todas alterações do arquivos, informado:

- Resumo com lista dos arquivos, e abaixo :
- nome do arquivo com a
- linha alterada( codigo anteriro -> codigo novo -> motivo da alteração)


### Estratégia para Envio de Arquivos

Para lidar com a limitação de 10 arquivos por vez e processar todos os arquivos base listados (36 no total, conforme 3 - Lista\_arquivos\_base.md), aqui está um plano eficiente:

1. **Priorize os arquivos mais relevantes**:
   * Comece com os **arquivos primários/base** (ex.: web.php, app.blade.php, welcome.blade.php, HomeController.php) e os **layouts principais** (ex.: app.blade.php, auth.blade.php, guest.blade.php), pois eles definem a estrutura do projeto.
   * Em seguida, envie os arquivos da subpasta partials que afetam diretamente o layout e a estilização (ex.: css.blade.php, javascripts.blade.php, extracss.blade.php, extracss\_auth.blade.php).
   * Por último, envie arquivos específicos (ex.: calculator.blade.php, sidebar.blade.php) ou o arquivo relacionado ao layout (AdminlteCustomPresenter.php).
2. **Organize os envios em lotes de 10**:
   * **Lote 1**: Envie os 7 arquivos primários/base (ou quantos estiverem disponíveis) + 3 arquivos de layouts (ex.: app.blade.php, auth.blade.php, guest.blade.php). Isso cobre a base do projeto.
   * **Lote 2**: Envie 10 arquivos da subpasta partials com maior impacto visual (ex.: css.blade.php, javascripts.blade.php, header.blade.php, footer.blade.php, extracss.blade.php, extracss\_auth.blade.php, error.blade.php, calculator.blade.php, sidebar.blade.php, language\_btn.blade.php).
   * **Lote 3**: Envie os 10 próximos arquivos de partials (ex.: footer-pos.blade.php, footer-restaurant.blade.php, header\_auth.blade.php, header\_notifications.blade.php, header-pos.blade.php, header-restaurant.blade.php, home\_header.blade.php, logo.blade.php, module\_form\_part.blade.php, notification\_list.blade.php).
   * **Lote 4**: Envie os arquivos restantes de partials (ex.: search\_settings.blade.php) + AdminlteCustomPresenter.php + qualquer arquivo adicional (ex.: vite.config.js ou theme.js criados).
3. **Forneça versões antiga e nova (quando disponível)**:
   * Para cada arquivo, envie a versão **antiga** (atual no seu projeto) e, se possível, a versão **nova** (atualizada, como no caso de calculator.novo.php).
   * Se a versão nova não existir, indique quais alterações deseja (ex.: remover Bootstrap, adicionar Tailwind/DaisyUI, configurar Vite, incluir DevExtreme). Posso inferir as mudanças com base nos documentos anteriores (ex.: 3 - Lista\_arquivos\_base.md e respostas anteriores).
4. **Use um formato claro no envio**:
   * Para cada lote, liste os arquivos no início da mensagem, com nomes e, se possível, uma breve descrição (ex.: “app.blade.php - layout principal”).
   * Envie cada arquivo como um trecho de código (usando \`\`\` ou <document>) com o nome do arquivo no início (ex.: <code>&#x3C;DOCUMENT filename="app.blade.php"></code>).</document>
   * Se o arquivo for grande, considere dividi-lo ou enviar apenas trechos relevantes (ex.: linhas com Bootstrap ou CSS hardcoded). Indique se quer que eu processe o arquivo completo.
5. **Envie em múltiplas mensagens, se necessário**:
   * Como você mencionou a limitação de 10 arquivos, envie cada lote em uma mensagem separada.
   * Para evitar confusão, numere os lotes (ex.: “Lote 1/4: Arquivos primários e layouts”) e liste os arquivos incluídos.
   * Se precisar de mais de 4 lotes (ex.: para arquivos adicionais não listados em 3 - Lista\_arquivos\_base.md), continue enviando em mensagens subsequentes.
6. **Confirme o progresso**:
   * Após cada lote, processarei os arquivos, gerarei um layout.md parcial com as instruções de alterações (como fiz para calculator.blade.php), e confirmarei quais arquivos ainda faltam.
   * Você pode revisar o layout.md parcial e ajustar as prioridades para os próximos lotes.

### Estrutura das Instruções

Para cada arquivo, o layout.md seguirá o formato estabelecido nas respostas anteriores (ex.: ID da mensagem: 6c5c8c93-7f7b-4e1f-9d62-7ebfa628aead):

* **Resumo**: Uma tabela listando todos os arquivos, com a ação (modificado, mantido, criado) e um resumo da atualização.
* **Detalhamento por arquivo**:
  * **Nome do arquivo**.
  * **Linha alterada**: Especificar a linha ou intervalo (ex.: “Linhas 1-5”).
  * **Código anterior**: Trecho da versão antiga.
  * **Código novo**: Trecho da versão nova (ou inferido, se não fornecido).
  * **Motivo da alteração**: Explicação clara (ex.: “Removeu Bootstrap para usar DaisyUI”).
* **Notas adicionais**: Informações sobre minificação, dependências (ex.: DevExtreme via CDN), ou sugestões para futuras melhorias (ex.: suporte RTL).

### Exemplo de Envio (Lote 1)

Aqui está como você pode estruturar a primeira mensagem com 10 arquivos:

`<span>Lote 1/4: Arquivos primários e layouts principais

</span>
Arquivos incluídos:

1. web.php - Rotas HTTP
2. app.blade.php - Layout principal
3. welcome.blade.php - Página inicial
4. HomeController.php - Controlador da página inicial
5. login.blade.php - View de login (auth)
6. register.blade.php - View de registro (auth)
7. auth.blade.php - Layout de autenticação
8. guest.blade.php - Layout para convidados
9. css.blade.php - Estilos CSS
10. javascripts.blade.php - Scripts JS

<DOCUMENT filename="web.php">
// Conteúdo do web.php
</DOCUMENT>

<DOCUMENT filename="app.blade.php">
<!-- Conteúdo do app.blade.php -->
</DOCUMENT>

... (e assim por diante para os 10 arquivos)`

### Arquivos Já Fornecidos

Você já enviou 9 arquivos antigos:

* calculator.blade.php (com versão nova: calculator.novo.php)
* css.blade.php
* extracss\_auth.blade.php
* error.blade.php
* extracss.blade.php
* header.blade.php
* footer.blade.php
* footer\_pos.blade.php
* footer-restaurant.blade.php

**Status**: Já processei o calculator.blade.php (com versão nova) e sugeri alterações para os outros 8 arquivos com base nas diretrizes (ex.: remover Bootstrap, usar Tailwind/DaisyUI). No entanto, para os 8 arquivos, as versões novas foram inferidas, pois não foram fornecidas. Se você tiver as versões novas desses arquivos, envie-as no próximo lote para uma comparação precisa.
