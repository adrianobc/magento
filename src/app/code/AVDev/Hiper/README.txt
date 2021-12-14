CONFIGURAÇÃO

- Admin pode configurar a chave de segurança de sua conta Hiper;

PERMISSÕES ADMIN

- O Admin pode liberar/bloquear o acesso de usuários à sessão AV Dev - Hiper

INSTALAÇÃO

php bin/magento module:status AVDev_Hiper

- se o módulo não estiver habilitado, execute:

php bin/magento module:enable AVDev_Hiper

php bin/magento setup:upgrade

php bin/magento setup:static-content:deploy -f

php bin/magento c:c

para configurar o cron

php bin/magento cron:run