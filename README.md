# Controlo de Vacinação Infantil (PHP + XAMPP)

Sistema de gestão de vacinação para postos médicos em Angola, desenvolvido com PHP 8.0, MariaDB e frontend em HTML/CSS/JS puros.

**Estado: Fase 1b** (login real com password_hash/password_verify, sessão segura, bloqueio após 5 tentativas).

## Requisitos
- XAMPP com PHP 8.0+
- MariaDB/MySQL
- Navegador moderno com suporte a CSS Grid e Flexbox

## Ambiente de desenvolvimento local (XAMPP)

### Configuração do Apache
Para desenvolvimento local com XAMPP:
- Acesso principal: `http://localhost/vacinacao-infantil-fase1a/vacinas/public/login.php`
- Apache permite Override: `AllowOverride All` em `C:/xampp/apache/conf/httpd.conf`
- Sistema de segurança por .htaccess: recusa acesso à raiz, permite só `public/`

### Arquivos de segurança (Apache 2.4)
- `.htaccess` (raiz): `Require all denied` - nega acesso a tudo
- `public/.htaccess`: `Require all granted` - permite acesso só à interface pública

### Pastas protegidas (403 Forbidden)
- `database/` - arquivos SQL da estrutura
- `config/` - configurações da aplicação
- `app/` - código backend PHP
- `arquivo_cloudflare/` - arquivos do ambiente Cloudflare (versão original)
- `.git/` - repositório Git
- `bin/`, `docs/` - pastas de documentação

### Aviso importante
Este sistema de .htaccess é **exclusivo para desenvolvimento local**. Em produção, a raiz do site será diretamente a pasta `public/` e as outras pastas ficarão fora do alcance do navegador.

## Arquitetura do sistema

### Backend PHP
- `app/core/helpers.php` - Funções auxiliares (configuração, formatação de datas, URL base)
- `app/views/partials/` - Templates reutilizáveis (head.php, foot.php)
- `config/config.local.php` - Configuração do nome do posto e modo de pré-visualização

### Frontend
- `public/assets/css/` - Estilos em módulos separados
  - `variaveis.css` - Variáveis de design e paleta de cores
  - `base.css` - Estilos base e tipografia com fontes Inter
  - `icones.css` - Sistema de ícones com mask-image (Lucide)
  - `componentes.css` - Componentes reutilizáveis (botões, formulários)
  - `layout.css` - Layout responsivo e grade
  - `paginas/` - Estilos específicos por página (login.css, painel.css)
  - `previa.css` - Estilos para modo de pré-visualização
  - `impressao.css` - Estilos para impressão
- `public/assets/js/` - JavaScript modular
  - `ui/dom.js` - Criação de elementos segura contra XSS
  - `ui/login.js` e `ui/painel.js` - Interface original do sistema
  - `app.js` - Arranque da aplicação (adaptado para pré-visualização)
- `public/shared/` - Regras compartilhadas (validações, perfis)

### Banco de dados
- `database/schema.sql` - Estrutura das tabelas: crianças, utilizadores e segurança
- `database/seeds/` - Dados de exemplo e calendário vacinal
- `bin/criar_admin.php` - Script para criar conta administradora inicial

### Recursos estáticos
- `public/assets/icons/` - Ícones SVG em formato Lucide
- `public/assets/fonts/` - Fontes Inter (woff2)

## Funcionalidades implementadas

### Interface web
- ✅ Página de login com campos de usuário e senha
- ✅ Painel principal com navegação responsiva
- ✅ Sistema de ícones dinâmicos (herdam cor do texto)
- ✅ Formatação de datas em português
- ✅ Saudação personalizada (Bom dia/Boa tarde/Boa noite)
- ✅ Layout responsivo com barra lateral e grade de cartões

### Modo pré-visualização
> ⚠️ **Removido na Fase 1b:** O sistema agora requer autenticação real para acessar o painel.

### Sistema de segurança
- Hash de senhas com password_hash() + password_verify()
- Sessões HTTP-only e SameSite Strict
- Tokens CSRF para formulários
- Controle de acesso a recursos estáticos via .htaccess
- Bloqueio temporário após 5 tentativas de login falhadas
- Registo de tentativas de login para auditoria

## Próximas fases
- **Fase 2**: Crianças - Cadastro e gestão de crianças
- **Fase 3**: Vacinação - Registro de vacinações
- **Fase 4**: Faltosos e Stock - Contactos e gestão de lotes
- **Fase 6**: Relatórios - Dashboard e exportação de dados
- **Fase 7**: Administração - Utilizadores e configurações

## Limites conhecidos desta fase
- Interface baseada em placeholders (dados fictícios)
- Sistema de persistência ainda não implementado
- Falta implementação das funcionalidades de gestão de crianças, vacinações, etc.

## Estrutura de arquivos
```
vacinas/
├── public/                 # Interface acessível via web
│   ├── assets/             # Recursos estáticos
│   │   ├── css/           # Estilos organizados por módulo
│   │   ├── js/            # JavaScript modular
│   │   ├── icons/        # Ícones SVG
│   │   └── fonts/        # Fontes Inter
│   ├── login.php          # Página de login
│   └── painel.php         # Painel principal
├── app/                   # Código backend PHP
│   └── core/             # Lógica central
├── database/             # Esquema e dados de exemplo
├── config/               # Configurações locais
├── tests/               # Scripts de verificação
└── arquivo_cloudflare/   # Versão original em Cloudflare
```