# Aplicativo do cliente

Esta pasta é o aplicativo do cliente da Drive Xperience. O painel administrativo continua na raiz do repositório. Os dois compartilham o banco configurado no `.env` da raiz.

## Subir localmente

Na pasta `cliente`:

```
php -S localhost:8081 router.php
```

O painel segue em `localhost:8080`. As sessões são separadas.

## Ambiente

O aplicativo lê `APP_KEY` e o banco do `.env` da raiz. Para a equipe validar visita presencial, defina `CLIENT_STAFF_TOKEN` no ambiente do servidor. Sem ele, nenhum ponto de visita é creditado.

Pagamento, e-mail e WhatsApp não são simulados. Sem credenciais, o pedido permanece aguardando pagamento.
