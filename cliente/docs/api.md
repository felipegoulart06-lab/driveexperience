# Contrato com o sistema administrativo

O aplicativo em `cliente/` usa o mesmo banco do painel. Clientes, veículos, modalidades, preços e reservas são as tabelas já existentes. Esta pasta não cria um CRM separado.

## O que já grava no painel

- Conta nova cria ou vincula um registro em `clients` pelo e-mail. Telefone ou CPF sozinhos não vinculam ninguém.
- Experiência com data e horário chama a mesma checagem de agenda do painel e grava `reservations` com situação operacional `aguardando_confirmacao` e financeira `aguardando_pagamento`. O valor pago fica zero.
- O preço sai de `vehicle_prices`. Se não houver preço, a compra não segue.

## O que o painel ainda não opera

Estas tabelas foram criadas no mesmo banco e precisam de tela no sistema administrativo:

- `shop_products`, `shop_variants`: loja física. Os itens atuais estão marcados como demonstração.
- `client_orders`: pedidos do aplicativo.
- `gift_vouchers`: vales. Nascem como `aguardando_pagamento` e não podem ser usados nesse estado.
- `point_entries`, `rewards`: Drive Points. O cliente não credita a própria visita.
- `referrals`, `referral_events`: indicações ficam pendentes até uma regra de campanha aprová-las.
- `campaigns`, `coupons`: campanhas e cupons.
- `client_settings`: `points_per_visit`, `visit_interval_hours`, `referral_points`.

## Visita presencial

`POST /api/visitas` com o campo `token` (o código que o cliente vê) e o cabeçalho `X-DX-Staff` igual a `CLIENT_STAFF_TOKEN` no ambiente do servidor. Sem esse token, a visita não gera pontos.

## Pagamento

`POST /api/pagamentos/retorno` responde 503. Voltar do navegador para uma página de sucesso não marca pedido como pago. Mercado Pago, ou outro provedor, só entra quando houver credencial e validação de assinatura.
