CONFIGURAÇÃO

- Admin pode configurar a chave de segurança de sua conta Sankhya;

PERMISSÕES ADMIN

- O Admin pode liberar/bloquear o acesso de usuários à sessão TA Dev - Sankhya

INSTALAÇÃO

php bin/magento module:status TADev_Sankhya

- se o módulo não estiver habilitado, execute:

php bin/magento module:enable TADev_Sankhya

php bin/magento setup:upgrade

php bin/magento setup:static-content:deploy -f

php bin/magento c:c

para configurar o cron

php bin/magento cron:run