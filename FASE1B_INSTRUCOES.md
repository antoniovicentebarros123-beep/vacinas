# Fase 1b - Login Real com password_hash()

## Arquivos criados/alterados

### Novos arquivos:
- `config/database.php` - Configuração de conexão PDO com MySQL/MariaDB
- `app/core/conexao.php` - Classe singleton para conexão PDO
- `bin/criar_admin.php` - Script de linha de comandos para criar conta admin
- `public/logout.php` - Página de logout com CSRF protection
- `database/update_schema_fase1b.sql` - Script de atualização de schema

### Arquivos alterados:
- `config/config.local.php` - Atualizado para modo de produção (desativa pré-visualização)
- `app/core/helpers.php` - Adicionado funções de autenticação e corrigido is_preview_mode()
- `public/login.php` - Implementado login real com password_verify() e bloqueio
- `public/painel.php` - Removido pré-visualização, adicionado autenticação real
- `database/schema.sql` - Adicionado campos de segurança à tabela utilizadores
- `README.md` - Atualizado status para Fase 1b e renumerado fases

## Passos para implementação

### 1. Preparar banco de dados (caso ainda não exista)

**Opção A: Banco novo (recomendado)**
```bash
# Importar schema completo
mysql -u root < database/schema.sql
```

**Opção B: Banco existente**
```bash
# Executar atualização de schema
mysql -u root vacinas_db < database/update_schema_fase1b.sql
```

### 2. Criar conta administradora

```bash
# Executar script de criação de admin
cd bin
php criar_admin.php
```

O script pedirá:
- Nome de usuário (ex: admin)
- Senha (mínimo 6 caracteres)
- Confirmação da senha

### 3. Configurar XAMPP

Garanta que:
- Apache e MySQL estão iniciados
- Base de dados `vacinas_db` existe
- Permissões de escrita na pasta `config/` e `bin/`

### 4. Testar login real

Acesse `http://localhost/vacinacao-infantil-fase1a/vacinas/login.php` e:
1. Tente login com usuário incorreto → deve mostrar erro genérico
2. Tente senha incorreta 5 vezes → conta deve ser bloqueada por 15 min
3. Login correto → deve redirecionar para painel com dados reais

## Testes manuais necessários

1. **Login correto**: 
   - Usuário: admin (criado pelo script)
   - Senha: senha correta
   - Resultado: Redireciona para painel com nome e perfil reais

2. **Palavra-passe errada**: 
   - Usuário correto, senha incorreta
   - Resultado: Mensagem genérica "Utilizador ou palavra-passe incorretos"

3. **Bloqueio após 5 falhas**: 
   - Mesmo usuário com 5 senhas erradas
   - Resultado: Mensagem "Conta temporariamente bloqueada"

4. **Painel.php sem login**: 
   - Acessar painel.php diretamente sem estar logado
   - Resultado: Redireciona para login.php

5. **Sair + Voltar**: 
   - Fazer logout e tentar voltar com botão Voltar do navegador
   - Resultado: Deve continuar na página de login

## Sistema de segurança implementado

- ✅ password_hash() + password_verify() (PBKDF2 interno)
- ✅ Sessões HTTP-only + SameSite=Strict
- ✅ Tokens CSRF em formulários
- ✅ Bloqueio temporário após 5 tentativas (15 minutos)
- ✅ Registo de tentativas para auditoria
- ✅ session_regenerate_id(true) em login
- ✅ Mensagens de erro genéricas
- ❌ Headers CSP (não implementado nesta fase)
- ❌ Proteção contra brute-force avançada
- ❌ Two-factor authentication

## Restrições observadas

- ✅ Sem alterar layout, cores nem ícones
- ✅ Sem CDN (usando fontes locais)
- ✅ Mantido .htaccess e url_base()
- ✅ Todas as consultas usam prepared statements

## Próximos passos

Após confirmar todos os testes manuais:
- Fase 2: Implementar gestão de crianças
- Fase 3: Implementar registo de vacinações
- Fase 4: Implementar contactos e stock
- Fase 6: Implementar relatórios
- Fase 7: Implementar administração de utilizadores