# Segurança local — passos manuais

Estes passos são da responsabilidade do administrador do posto. A aplicação usa `vacinas_app`; a alteração abaixo afeta apenas o acesso administrativo ao MariaDB e ao phpMyAdmin. Não guardar palavras-passe neste documento nem no relatório.

## 1. Cópia de segurança

Antes de alterar o acesso do `root`, exportar a base:

```powershell
& 'C:\xampp\mysql\bin\mysqldump.exe' -u root --default-character-set=utf8mb4 --single-transaction --routines --triggers --result-file='C:\xampp\htdocs\vacinacao-infantil-fase1a\vacinas\database\backup_seguranca_local.sql' vacinas_db
```

## 2. Definir palavra-passe do root do MariaDB

Abrir o cliente MySQL como administrador local:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -u root
```

Executar no cliente, substituindo o marcador por uma palavra-passe forte:

```sql
ALTER USER 'root'@'localhost' IDENTIFIED BY 'SUBSTITUA_POR_UMA_PALAVRA_PASSE_FORTE';
FLUSH PRIVILEGES;
```

Fechar o cliente com `EXIT;`. A partir desse momento, os comandos administrativos passam a pedir a palavra-passe:

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' -u root -p
```

Não colocar a palavra-passe diretamente na linha de comandos. A aplicação PHP não deve ser alterada para usar `root`; deve continuar a usar `vacinas_app`.

## 3. Ativar autenticação por cookie no phpMyAdmin

Editar `C:\xampp\phpMyAdmin\config.inc.php` e, no bloco do servidor local, definir:

```php
$cfg['Servers'][$i]['auth_type'] = 'cookie';
$cfg['Servers'][$i]['AllowNoPassword'] = false;
```

Se `AllowNoPassword` ainda não existir nesse bloco, acrescentar a linha. Guardar o ficheiro e abrir novamente:

```text
http://localhost/phpmyadmin/
```

O phpMyAdmin deve apresentar o ecrã de login e não entrar automaticamente como `root`.

## 4. Verificações finais

- Abrir `public/login.php` com uma conta da aplicação e confirmar que o login continua a funcionar.
- Abrir o phpMyAdmin sem sessão e confirmar que pede utilizador e palavra-passe.
- Tentar aceder a `root` sem palavra-passe e confirmar que o acesso é recusado.
- Guardar a palavra-passe do `root` num gestor de palavras-passe ou local físico seguro.
- Manter cópias de segurança fora da pasta pública e testar o restauro.

## 5. Produção

Além destes passos, a área `/gestao/` deve ser protegida por restrição de IP do posto e/ou uma segunda palavra-passe no servidor. Esconder o endereço não substitui essas medidas.
