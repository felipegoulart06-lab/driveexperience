# Integrações

## Cadastro manual

Em Reservas, a origem “Plataforma externa” pede a plataforma, o identificador da venda e o código da reserva original. O valor informado pela plataforma fica registrado à parte. Ele só vira pagamento recebido se a equipe marcar essa opção, ou se a plataforma estiver configurada para confiar no pagamento.

## Importação

A tela Integrações aceita CSV e XLSX. O XLSX precisa da extensão `zip`. O arquivo de exemplo está em `exemplos/vendas-plataforma.csv`.

O fluxo é enviar o arquivo, relacionar as colunas, revisar a prévia e confirmar. Uma venda com o mesmo identificador externo e a mesma plataforma não é criada de novo. Sem identificador, o sistema calcula uma chave estável da linha para reconhecer a repetição.

## Webhook genérico

Plataformas sem documentação permanecem com o conector `pendente` e respondem `503` sem gravar a venda.

Quando o conector `generico` está com status `configurado`, o endereço é:

```text
POST /api/webhooks/{identificador-da-plataforma}
X-DX-Signature: sha256=<hmac sha256 do corpo bruto, usando o segredo da plataforma>
Content-Type: application/json
```

Corpo esperado:

```json
{
  "external_id": "VENDA-123",
  "external_reservation_code": "RES-123",
  "customer": {
    "name": "Nome do cliente",
    "email": "cliente@example.com",
    "phone": "47999999999",
    "document": "",
    "city": "Balneário Camboriú",
    "state": "SC"
  },
  "product_name": "Drive Xperience",
  "vehicle_name": "Porsche 718 Boxster S",
  "scheduled_date": "2026-10-20",
  "start_time": "14:00",
  "participants": 1,
  "gross_amount": "590,00",
  "fee_amount": "0,00",
  "net_amount": "590,00",
  "paid_amount": "590,00"
}
```

`paid_amount` é guardado como valor informado pela plataforma. Ele não entra no financeiro, a menos que a plataforma tenha a opção de confiar no pagamento. Cliente compatível é vinculado; cliente ambíguo ou com CPF divergente não é criado automaticamente. Cada chamada fica em sincronizações e pode ser reprocessada se falhar.

Não existe conector de uma plataforma comercial específica. Ele só deve ser adicionado quando houver documentação e credenciais reais.

## Mensagens

E-mail usa SMTP com `AUTH LOGIN` e STARTTLS quando `SMTP_*` está preenchido. WhatsApp usa `WHATSAPP_API_URL` (endereço base da instância) e `WHATSAPP_TOKEN`, definidos no ambiente do servidor. Sem essas credenciais, a fila registra `nao_configurado` e nenhuma mensagem é simulada.
