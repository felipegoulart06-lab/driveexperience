# Drive Xperience — gestão

Sistema interno de clientes, reservas, agenda, frota, pacotes de fotos, financeiro e documentos da Drive Xperience.

A interface e os documentos estão em português. Preços, veículos, horários e textos dos PDFs ficam no banco e são alterados no painel.

## Requisitos locais

- PHP 8.2 ou superior, com as extensões `pdo`, `pdo_sqlite` ou `pdo_mysql`/`pdo_pgsql`, `mbstring`, `openssl`, `dom` e `fileinfo`.
- A importação de XLSX também usa a extensão `zip`.
- Composer, apenas para instalar as dependências.

O desenvolvimento local usa SQLite. Produção e a Vercel usam MySQL/MariaDB ou PostgreSQL (Supabase).

## Instalação local

```bash
composer install
php bin/install.php --demo
php -S localhost:8080 router.php
```

O comando de instalação cria o arquivo `.env`, gera `APP_KEY` e uma senha de administrador se elas ainda não existirem, e grava a senha uma vez em `storage/credenciais-locais.txt`. Esse arquivo não entra no repositório. Sem `--demo`, o banco fica só com a empresa, a frota, os produtos e o administrador.

Abra `http://localhost:8080` e entre com o e-mail e a senha exibidos no terminal.

Para MariaDB local:

```bash
docker compose up -d
```

Depois ajuste o `.env`:

```text
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=drivexperience
DB_USERNAME=dx
DB_PASSWORD=dxlocal
```

A senha `dxlocal` existe só no `docker-compose.yml` para o computador de desenvolvimento.

No Windows, se o PHP não carregar o MySQL, inicie o servidor com `php -d extension=pdo_mysql -S localhost:8080 router.php`.

## Testes

```bash
php tests/acceptance.php
```

O teste usa um SQLite temporário e cobre cliente, duplicidade, reserva, conflito de horário, pagamento parcial, PDF, cancelamento, venda externa, importação CSV, webhook assinado, permissão e anonimização.

## Publicação

O runtime previsto é o comunitário `vercel-php@0.8.0`, com PHP 8.4. A configuração está em `vercel.json` e `api/index.php`. Os limites e as variáveis estão em `docs/implantacao.md`.

## Integrações e privacidade

- Contrato do webhook, importação e o que permanece pendente: `docs/integracoes.md`.
- Acesso, correção, anonimização e retenção: `docs/lgpd.md`.

## O que depende de credencial externa

| Recurso | Variável | Sem credencial |
| --- | --- | --- |
| Banco na Vercel | `DB_*` com MySQL ou `pgsql` (Supabase) | A aplicação não inicia com SQLite |
| Instalação web | `INSTALL_TOKEN` | A rota responde 404 |
| Redefinição emergencial | `EMERGENCY_RESET_TOKEN` | O comando recusa a troca |
| Alertas agendados | `CRON_SECRET` | O endpoint recusa a chamada |
| PDFs fora do banco | `S3_*` | O padrão grava o PDF no banco |
| E-mail | `SMTP_*` | A notificação fica como não configurada |
| WhatsApp | `WHATSAPP_API_URL` e `WHATSAPP_TOKEN` | Nada é enviado |
| Webhook de plataforma | segredo salvo na plataforma | O conector permanece pendente |

Não há integração pronta com uma plataforma específica. O conector genérico só recebe dados depois que a plataforma e o segredo forem configurados.
