# FORMA4 Imobiliária — Sistema de Formulários

Sistema completo de gerenciamento de formulários para imobiliárias, com geração de PDF, envio de e-mail e painel administrativo.

---

## Requisitos

| Componente | Versão mínima |
|------------|---------------|
| PHP | 7.4+ (recomendado 8.1) |
| MySQL | 5.7+ ou MariaDB 10.3+ |
| Composer | 2.x |
| Apache | `mod_rewrite` habilitado |
| Extensões PHP | `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd` |

---

## Instalação no cPanel / Hostinger

### 1. Criar o banco de dados

1. Acesse **cPanel → MySQL Databases**
2. Crie um novo banco de dados: ex. `forma4_db`
3. Crie um usuário: ex. `forma4_user` com senha forte
4. Adicione o usuário ao banco com **Todos os privilégios**
5. Anote: `host`, `banco`, `usuário` e `senha`

---

### 2. Fazer upload dos arquivos

**Opção A — Git (recomendado):**
```bash
cd public_html
git clone <url-do-repositorio> .
```

**Opção B — Upload manual:**
1. Compacte todos os arquivos em um `.zip`
2. Acesse **cPanel → Gerenciador de Arquivos**
3. Faça upload para `public_html/` e extraia

> ⚠️ Certifique-se de que os arquivos ficam na raiz de `public_html`, **não** em uma subpasta.

---

### 3. Instalar dependências (Composer)

**Via SSH (Terminal do cPanel):**
```bash
cd public_html
composer install --no-dev --optimize-autoloader
```

**Se não tiver SSH:**
1. Instale o Composer localmente em seu computador
2. Execute `composer install` no diretório do projeto
3. Faça upload da pasta `vendor/` gerada para o servidor

---

### 4. Configurar o sistema

Abra o arquivo `includes/config.php` e preencha:

```php
// Banco de dados
define('DB_HOST', 'localhost');        // Geralmente 'localhost'
define('DB_USER', 'forma4_user');      // Usuário do banco
define('DB_PASS', 'sua_senha_forte');  // Senha do banco
define('DB_NAME', 'forma4_db');        // Nome do banco

// URL da aplicação (sem barra final)
define('APP_URL', 'https://seudominio.com.br');
```

---

### 5. Criar as tabelas e configurações iniciais

1. Acesse no navegador: `https://seudominio.com.br/install.php`
2. O instalador irá:
   - Criar todas as tabelas do banco
   - Inserir configurações padrão
   - Criar o usuário administrador inicial
   - Cadastrar o formulário "Autorização de Venda com Exclusividade"
3. **Após a instalação, delete o arquivo `install.php` imediatamente!**

> ⚠️ Credenciais criadas pelo instalador:
> - **Email:** `admin@admin.com`
> - **Senha:** `admin123`
> - **Troque a senha no primeiro acesso!**

---

### 6. Permissões de diretório

Defina as permissões corretas via **cPanel → Gerenciador de Arquivos** ou SSH:

```bash
chmod 755 uploads/
chmod 755 uploads/pdfs/
chmod 755 uploads/logos/
find . -name "*.php" -exec chmod 644 {} \;
```

---

### 7. Configurar SMTP (E-mail)

Acesse o painel admin → **Configurações** → seção **E-mail SMTP**.

**Hostinger (recomendado):**
| Campo | Valor |
|-------|-------|
| Host | `smtp.hostinger.com` |
| Porta | `465` |
| Segurança | `SSL` |
| Usuário | Seu e-mail completo |
| Senha | Senha do e-mail |
| Nome remetente | Nome da imobiliária |
| E-mail remetente | Mesmo e-mail do usuário |

**Gmail (alternativa):**
| Campo | Valor |
|-------|-------|
| Host | `smtp.gmail.com` |
| Porta | `587` |
| Segurança | `TLS` |
| Usuário | seuemail@gmail.com |
| Senha | Senha de app (não a senha normal) |

> 📌 Para o Gmail, ative a [autenticação em dois fatores](https://myaccount.google.com/security) e gere uma [senha de app](https://myaccount.google.com/apppasswords).

---

## Estrutura de arquivos

```
/
├── index.php                   # Redireciona para admin ou login
├── login.php                   # Página de login
├── logout.php                  # Encerrar sessão
├── form.php                    # Formulário público (?slug=...)
├── submit.php                  # Handler de submissão de formulário
├── install.php                 # ⚠️ DELETAR APÓS INSTALAR
├── composer.json               # Dependências PHP
├── .htaccess                   # Reescrita de URL + segurança
│
├── includes/
│   ├── config.php              # ⚙️ CONFIGURAÇÕES DO SISTEMA
│   ├── db.php                  # Conexão PDO (Singleton)
│   ├── auth.php                # Autenticação e sessões
│   ├── functions.php           # Funções auxiliares
│   ├── pdf.php                 # Geração de PDF (DomPDF)
│   └── mailer.php              # Envio de e-mail (PHPMailer)
│
├── admin/
│   ├── index.php               # Dashboard
│   ├── forms.php               # Lista de formulários
│   ├── form-create.php         # Criar novo formulário
│   ├── form-edit.php           # Editar formulário
│   ├── form-delete.php         # Excluir formulário (POST)
│   ├── submissions.php         # Lista de envios
│   ├── submission-view.php     # Ver envio + PDF
│   ├── submission-delete.php   # Excluir envio (POST)
│   ├── settings.php            # Configurações do sistema
│   └── layout/
│       ├── header.php          # Cabeçalho + sidebar
│       └── footer.php          # Rodapé + scripts
│
├── assets/
│   ├── css/style.css           # Estilos globais
│   ├── js/app.js               # Scripts globais do admin
│   └── js/form-builder.js      # Builder visual de formulários
│
├── uploads/
│   ├── pdfs/                   # PDFs gerados (auto)
│   ├── logos/                  # Logo da imobiliária
│   └── .htaccess               # Protege execução de scripts
│
└── vendor/                     # Dependências Composer (não versionar)
```

---

## Formulário pré-cadastrado

O instalador cria automaticamente o formulário:

**"Autorização de Venda com Exclusividade"**
- **Slug:** `autorizacao-venda-exclusividade`
- **URL pública:** `https://seudominio.com.br/form.php?slug=autorizacao-venda-exclusividade`
- **Template PDF:** Contrato de autorização com cláusulas jurídicas, blocos de assinatura e marca d'água "CONFIDENCIAL"

Campos incluídos:
- Tipo de imóvel, endereço completo, bairro, cidade, CEP
- Área total, área construída, quartos, suítes, banheiros, garagem
- Descrição do imóvel
- Nome, CPF, RG, estado civil, telefone e e-mail do proprietário
- Valor mínimo de venda, comissão (%), prazo de exclusividade (dias)
- Formas de pagamento aceitas
- Data da autorização
- Observações adicionais

---

## Uso do painel administrativo

### Criar formulário
1. Admin → **Formulários** → **Novo Formulário**
2. Defina título e descrição
3. Adicione campos com o builder visual
4. Escolha o template PDF (`padrão` ou `autorização`)
5. Salve e copie o link público

### Ver envios
1. Admin → **Envios**
2. Clique em **Ver** para visualizar detalhes
3. Baixe o PDF gerado, reenvie por e-mail ou regenere o PDF

### Personalizar aparência
1. Admin → **Configurações**
2. Faça upload do logotipo da imobiliária
3. Defina a cor primária do sistema

---

## Segurança

O sistema implementa as seguintes proteções:

| Proteção | Implementação |
|----------|---------------|
| Injeção SQL | PDO com prepared statements em 100% das queries |
| XSS | Função `e()` com `htmlspecialchars` em todas as saídas |
| CSRF | Token em todos os formulários POST |
| Brute-force | Sessão protegida + hash bcrypt de senhas |
| Path traversal | `realpath()` na exclusão de arquivos |
| Dir listing | `Options -Indexes` em `.htaccess` |
| Exec de scripts | `.htaccess` na pasta `uploads/` |
| Headers | `X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection` |

---

## Solução de problemas

**PDF não é gerado**
- Verifique se o Composer foi executado: `ls vendor/`
- Confirme que a extensão `gd` está habilitada no PHP
- Verifique permissão de escrita em `uploads/pdfs/` (755)
- No cPanel: **PHP Selector** → certifique que `gd` e `mbstring` estão marcados

**E-mail não é enviado**
- Teste as configurações SMTP em: Admin → Configurações
- Certifique se a porta 465 (SSL) ou 587 (TLS) está liberada pelo provedor
- Na Hostinger, use o e-mail criado no painel (não Gmail diretamente)

**Página em branco / Erro 500**
- Habilite exibição de erros temporariamente em `includes/config.php`:
  ```php
  ini_set('display_errors', 1);
  error_reporting(E_ALL);
  ```
- Verifique o log de erros: cPanel → **Error Logs**
- Certifique que `mod_rewrite` está ativo: cPanel → **Apache Handlers**

**Formulário público não abre (404)**
- Verifique se `APP_URL` em `config.php` está correto
- Certifique que `.htaccess` foi enviado (às vezes oculto no upload)
- Habilite `AllowOverride All` no vhost do Apache (suporte do provedor)

**Não consigo fazer login**
- Reacesse `install.php` (recria o usuário admin) — mas isso recria as tabelas!
- Ou atualize a senha manualmente no MySQL:
  ```sql
  UPDATE users SET password = '$2y$10$...' WHERE email = 'admin@admin.com';
  ```
  Use `password_hash('nova_senha', PASSWORD_DEFAULT)` no PHP para gerar o hash.

---

## Melhorias futuras sugeridas

| Funcionalidade | Descrição |
|----------------|-----------|
| **Assinatura digital** | Integrar [Autentique](https://autentique.com.br) ou [DocuSign](https://docusign.com) API para assinar o contrato digitalmente |
| **CRM integrado** | Webhook para enviar leads ao CRM da imobiliária (Revenda, Ville Imóveis, etc.) |
| **WhatsApp** | Envio automático do PDF via [API Z-API](https://z-api.io) ou [WPPConnect](https://github.com/wppconnect-team) |
| **API de leads** | Endpoint `/api/submissions` para integração com Google Sheets, HubSpot ou RD Station |
| **Multi-usuário** | Cadastro de corretores com acesso restrito aos próprios formulários |
| **Dashboard analytics** | Gráficos de envios por período, taxa de conversão por formulário |
| **Agendamento de visitas** | Campo de data/hora no formulário integrado ao Google Calendar |
| **Notificações push** | Web push para alertar novos envios em tempo real |

---

## Dependências utilizadas

| Biblioteca | Versão | Finalidade |
|------------|--------|------------|
| [dompdf/dompdf](https://github.com/dompdf/dompdf) | ^2.0 | Geração de PDF server-side |
| [phpmailer/phpmailer](https://github.com/PHPMailer/PHPMailer) | ^6.8 | Envio de e-mail via SMTP |

---

## Licença

Sistema desenvolvido para uso interno. Todos os direitos reservados.
