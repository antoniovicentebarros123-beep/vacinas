# Restrições adicionais da gestão em produção

A área `/public/gestao/` já possui sessão própria, cookie `VACINAS_GESTAO`, autenticação Argon2id, CSRF, bloqueio por tentativas e verificação de administrador ativo em cada pedido. Estas medidas não substituem os controlos de rede do alojamento.

## Restrição por rede

Antes de publicar, restrinja `/gestao/` a uma rede privada ou a uma lista de IP autorizados no servidor web, na firewall ou no painel do alojamento. Não exponha a área diretamente na Internet sem essa restrição. Se a equipa de suporte precisar de acesso remoto, use uma VPN aprovada.

Exemplo conceptual para Apache, apenas depois de confirmar o caminho real e os endereços autorizados:

```apache
<Directory "/caminho/do/site/public/gestao">
    Require ip 127.0.0.1 ::1
    # Acrescentar apenas os IP fixos e aprovados da equipa.
</Directory>
```

Não copiar este exemplo sem substituir o caminho e definir os IP reais. Um IP dinâmico ou a mudança de provedor pode exigir atualização da lista.

## Segunda palavra-passe ou autenticação forte

Para produção, coloque `/gestao/` atrás de um segundo factor ou de uma segunda credencial gerida pelo servidor web/proxy. A senha temporária de funcionário não deve ser usada como segundo fator. Prefira TOTP, chave de segurança ou VPN com MFA, conforme a infraestrutura aprovada.

A segunda credencial deve ser individual, revogável e registada em auditoria fora da aplicação. Não guardar códigos de recuperação nem palavras-passe no repositório, no relatório ou em mensagens de log.

## Operação

- Rever periodicamente os funcionários ativos e os perfis atribuídos.
- Desativar contas de saída imediatamente; não apagar linhas nem auditoria.
- Repor palavras-passe apenas por canal seguro e nunca as registrar no `docs/Report.md`.
- Manter o certificado TLS válido para que o cookie possa usar `Secure`.
- Registar acessos administrativos e conservar os logs conforme a política institucional.
- Validar a legislação angolana de proteção de dados e o período de conservação antes da produção.

Estas restrições são documentação de implementação manual; não foram ativadas automaticamente neste ambiente local.
