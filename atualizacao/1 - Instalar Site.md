# Guia Completo de Configuração, Instalação e Atualização do UltimatePOS

Este guia detalha o processo para configurar, instalar e atualizar o sistema UltimatePOS em um ambiente Laravel 9.52 com Vite, migrando de Bootstrap para Tailwind CSS/DaisyUI 5, integrando DevExtreme, e atualizando layouts e um Model completo. O tutorial é dividido em cinco etapas principais: configuração do subdomínio na KingHost, configuração do proxy reverso no aaPanel, instalação do sistema, atualização de layouts e integração de bibliotecas, e modificação de um Model. Siga cada passo cuidadosamente para garantir uma configuração bem-sucedida.

## Pré-requisitos

- **Acesso à KingHost**: Painel de controle para criar subdomínios.
- **Credenciais do aaPanel**:
  - **URL**: https://painel.wr2.com.br:7800/1f7980e1
  - **Usuário**: `36p1tgxv`
  - **Senha**: `76a497c3`
- **Servidor**: Acesso SSH, com Git, PHP, Composer, Node.js, npm instalados.
- **Subdomínio**: Ex.: `oi.wr2.com.br`.
- **Porta disponível**: Ex.: `8010`.
- **Dependências**: Laravel 9.52, Vite, Tailwind CSS, DaisyUI 5, jQuery, Axios, DevExtreme, jQuery, Toastr, SweetAlert, TinyMCE, DateRangePicker, DateTimePicker, Moment.js, jKanban.

## Etapa 1: Configurar o Subdomínio na KingHost

### Passos

1. Acesse o painel de controle da KingHost.
2. Navegue até **Domínios** ou **Subdomínios**.
3. Crie um subdomínio (ex.: `oi.wr2.com.br`).
4. Configure o registro DNS (ex.: registro A apontando para o IP do servidor, como `192.168.0.2`).
5. Aguarde a propagação do DNS (até 24 horas).

### Solução de Problemas

- **Subdomínio não resolve**:
  - Verifique os registros DNS no painel da KingHost.
  - Use `dig` ou `nslookup` para confirmar propagação.
  - Contate o suporte da KingHost.

## Etapa 2: Configurar o Proxy Reverso no aaPanel

### Passos

#### 2.1 Configurar `dynamic_conf.yml`

1. Acesse o aaPanel com as credenciais fornecidas.
2. Vá para **Arquivos** e localize `/www/wwwroot/dynamic_conf.yml`.
3. Adicione as configurações do subdomínio e proxy reverso:

   ```yaml
   http:
     routers:
       router1:
         rule: "Host(`oi.wr2.com.br`)"
         service: "service10"
         entryPoints:
           - "websecure"
         tls:
           certResolver: "letsencryptresolver"
     services:
       service10:
         loadBalancer:
           servers:
             - url: "http://192.168.0.2:8010"
   ```

   - Substitua `oi.wr2.com.br` pelo subdomínio criado.
   - Verifique se a porta `8010` está livre com `netstat -tuln`. Se ocupada, use outra (ex.: `8011`).
4. Salve as alterações.

#### 2.2 Adicionar o Site

1. No aaPanel, vá para **Sites**.
2. Clique em **Adicionar Site** e insira o subdomínio (ex.: `oi.wr2.com.br`).
3. Salve as configurações.

#### 2.3 Configurar o Proxy Reverso

1. Vá para **Proxy Reverso** ou **Configurações de Rede**.
2. Crie uma regra apontando para `http://192.168.0.2:8010`.
3. Associe ao subdomínio.

#### 2.4 Testar

1. Acesse `https://oi.wr2.com.br` no navegador.
2. Verifique logs em `/var/log` para erros (ex.: porta ocupada, TLS inválido).

### Solução de Problemas

- **Site não carrega**: Confirme sintaxe do `dynamic_conf.yml` e associação do subdomínio.
- **Porta ocupada**: Escolha outra porta e atualize `dynamic_conf.yml` e proxy.
- **TLS inválido**: Verifique `letsencryptresolver` no aaPanel.

## Etapa 3: Baixar e Instalar o Sistema

### Passos

#### 3.1 Acessar o Servidor

1. Conecte-se via SSH:
   ```bash
   ssh user@192.168.0.2
   ```

#### 3.2 Baixar o Sistema

1. Navegue até `/www/wwwroot/oi.wr2.com.br`:

   ```bash
   cd /www/wwwroot/oi.wr2.com.br
   ```
2. Baixe do site e siga as instruções:

   ```bash
   https://doniaweb.com/topic/14131-ultimate-pos-v67-best-erp-stock-management-point-of-sale-invoicing-application-addons-nulled/
   ```

   Ou transfira via SCP:

   ```bash
   scp sistema.zip user@192.168.0.2:/www/wwwroot/oi.wr2.com.br
   unzip sistema.zip
   ```

#### 3.3 Instalar Dependências

1. Instale dependências PHP:
   ```bash
   composer install
   ```
2. Instale dependências Node.js:
   ```bash
   npm install
   ```
3. Compile assets:
   ```bash
   npm run build
   ```

#### 3.4 Configurar o Ambiente

1. Copie o arquivo `.env`:
   ```bash
   cp .env.example .env
   ```
2. Edite `.env` com:
   - Banco de dados.
   - `APP_URL=https://oi.wr2.com.br`.
3. Gere a chave:
   ```bash
   php artisan key:generate
   ```

#### 3.5 Configurar o Banco de Dados

1. Crie um banco no aaPanel (**Banco de Dados**).
2. Execute migrações:
   ```bash
   php artisan migrate
   ```
3. (Opcional) Popule com dados:
   ```bash
   php artisan db:seed
   ```

#### 3.6 Ajustar Permissões

1. Configure permissões:
   ```bash
   chown -R www:www /www/wwwroot/oi.wr2.com.br
   chmod -R 755 /www/wwwroot/oi.wr2.com.br
   chmod -R 775 /www/wwwroot/oi.wr2.com.br/storage
   ```

#### 3.7 Testar

1. Acesse `https://oi.wr2.com.br`.
2. Verifique logs em `storage/logs/laravel.log`.

### Solução de Problemas

- **Erro 500**: Verifique `.env` e permissões.
- **Dependências ausentes**: Reexecute `composer install` ou `npm install`.
- **Banco não conecta**: Confirme credenciais em `.env`.

## Remover Bootstrap e Adicionar DependênciasIntegrar Bibliotecas

Remover Bootstrap e Adicionar Dependências

1. Atualize `package.json`, removendo:
   ```json
   "bootstrap": "^5.x.x",
   "popper.js": "^2.x.x"
   ```
2. Adicione DevExtreme, Tailwind, DaisyUI:
   ```bash
   npm install devextreme devextreme-theme-material tailwindcss @tailwindcss/forms daisyui@5 --save
   npm install jquery datatables.net datatables.net-dt select2 pace-js icheck bootstrap-datepicker sweetalert2 dropzone bootstrap-fileinput moment axios --save
   ```
3. Execute:
   ```bash
   npm install
   1. ```
   ```

#### 4.6 Testar

1. Execute:
   ```bash
   npm run dev
   ```
2. Acesse telas (ex.: `/brands`, `/note-documents`).
3. Verifique:
   - Estilos de tabelas, botões, inputs, modais.
   - Funcionalidade de formulários e eventos jQuery.
   - Console para erros (ex.: `DataTable is not a function`).

### Solução de Problemas

- **DataTables sem estilo**: Ajuste `_datatables.css`.
- **Modal não funciona**: Verifique importação de `axios` e URLs em `data-href`.
- **Conflitos de estilo**: Use `!important` em `_datatables.css`.

## Conclusão

Este guia cobre a configuração do subdomínio, proxy reverso, instalação do UltimatePOS, migração para DaisyUI 5, integração do DevExtreme, atualização de layouts, e modificação do Model Brand. Teste todas as funcionalidades em `/brands` e outras telas para garantir estabilidade. Para suporte, contate a equipe de desenvolvimento.
