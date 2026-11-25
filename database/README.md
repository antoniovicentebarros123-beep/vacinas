# Base de Dados - Sistema de Controlo de Vacinação Infantil

## Estrutura
- `schema.sql` - Esquema completo do banco de dados (16 tabelas + 2 vistas)
- `seed.sql` - Dados iniciais estáticos com IDs fixos
- `seeds/` - Pasta para seeds adicionais (não usado neste momento)

## Importação
1. Criar base de dados vazia no phpMyAdmin com nome `vacinas_db`
2. Importar `schema.sql` pelo separador "Importar" do phpMyAdmin
3. Importar `seed.sql` pelo separador "Importar" do phpMyAdmin

## Tabelas Geridas Manualmente (phpMyAdmin)
- `configuracoes` - Configurações do sistema
- `bairros` - Bairros do município
- `vacinas` - Catálogo de vacinas
- `calendario_vacinal` - Calendário vacinal

## Tabelas Geridas pela Aplicação
- `utilizadores` - Utilizadores do sistema
- `login_tentativas` - Tentativas de login
- `encarregados` - Encarregados de educação
- `criancas` - Registo de crianças
- `stock_lotes` - Lotes de stock
- `atendimentos` - Atendimentos no posto
- `atendimento_vacinas` - Vacinas pretendidas nos atendimentos
- `vacinacoes` - Vacinações realizadas
- `vacinacao_anulacoes` - Anulações de vacinação
- `stock_movimentos` - Movimentos de stock
- `contactos_faltosos` - Contactos a crianças sem vacina
- `auditoria` - Registo de auditoria

## Vistas
- `stock_saldo_lotes` - Saldo atual dos lotes
- `vacinas_disponiveis` - Disponibilidade de vacinas

## Utilizador da Aplicação
Criar utilizador `vacinas_app` com permissões:
```sql
CREATE USER 'vacinas_app'@'localhost' IDENTIFIED BY 'senha_temporaria';
GRANT SELECT, INSERT, UPDATE ON vacinas_db.* TO 'vacinas_app'@'localhost';
GRANT DELETE ON vacinas_db.login_tentativas TO 'vacinas_app'@'localhost';
FLUSH PRIVILEGES;
```

## Configuração
- Editar `config/config.local.php` fora do Git com as credenciais e os parâmetros locais.
- O nome da instituição é lido exclusivamente de `configuracoes.nome_posto`. Para o nome atual, executar `database/update_nome_hospital.sql` com `--default-character-set=utf8mb4`.
- Os tempos de sessão são configuráveis por `configuracoes.session_lifetime` (30 minutos por defeito) e `configuracoes.session_remember_lifetime` (7 dias por defeito). Em bases existentes, aplicar `database/update_sessao.sql`.
- `modo_previa` = true apenas para desenvolvimento.
- `prefixo_processo` = "RG".

## Notas
- Não remover `CREATE DATABASE` do schema.sql para importação pelo phpMyAdmin
- Todas as chaves estrangeiras usam `ON DELETE RESTRICT`
- Campos `eliminado_em` só para soft deletes
- Campos `criado_em` e `atualizado_em` automáticos