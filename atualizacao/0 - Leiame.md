# Leiame - Guia de Instalação e Atualização do Projeto

Este documento serve como um guia inicial para a instalação, configuração e atualização do projeto. Siga os passos abaixo em ordem para garantir uma implementação bem-sucedida.

## 1. Instalação do Site

* **Ação:** Leia o arquivo detalhado sobre a instalação.
* **Referência:** Consulte o arquivo `1 - Instalar Site.md` para instruções completas.

## 2. Copiar os Arquivos

* **Ação:** Copie os arquivos para as pastas corretas e instale os novos componentes.
  * Certifique-se de que todos os arquivos sejam colocados nas respectivas pastas do projeto.
  * Instale os componentes adicionais (ex.: DevExtreme, Vite, Tailwind 4, DaisyUI 5) conforme necessário.

## 3. Lista de Arquivos Base

* **Localização:** Pasta `resources/views/layouts`
* **Descrição:** Esta pasta contém os arquivos principais do projeto.
  * **`app.blade.php`:** Arquivo inicial do projeto, servindo como layout principal.
  * **`javascripts.blade.php`:** Inclui os scripts JavaScript utilizados.
  * **Outros arquivos:** (A ser complementado conforme atualizações.)
* **Atualizações:**
  * Cada arquivo atualizado deve incluir a linha alterada e uma descrição do que foi modificado.
* **Testes:**
  * Teste a troca do tema (de Bootstrap para Tailwind/DaisyUI) e verifique os componentes visuais, como:
    * Menu
    * Botões
    * Títulos
    * Outros elementos da interface
  * Confirme se o tema está registrado na tabela do usuário (anteriormente registrado na tabela `business`).

## 4. Lista dos Modelos

* **Ação:** Modifique um modelo completo, incluindo:
  * Controller
  * Views (index, create, store, show, edit, update, destroy, partial)
* **Testes:**
  * Execute testes para garantir que todas as funcionalidades estejam presentes. O arquivo Markdown correspondente deve conter:
    * Lista de testes a serem realizados para cada modelo.
* **Documentação:**
  * Para cada arquivo modificado, inclua:
    * O que foi alterado.
    * A linha específica do arquivo afetada.

## 5. Módulos

* **Ação:** A revisão e modificação de cada módulo serão abordadas separadamente.
  * A análise será realizada quando cada módulo for abordado no processo de atualização.
