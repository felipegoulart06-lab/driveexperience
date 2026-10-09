# Implantação

## Runtime

A Vercel não executa PHP nativamente. Este projeto usa o runtime comunitário `vercel-php@0.8.0`, que fornece PHP 8.4. A entrada da aplicação é `api/index.php`, e `vercel.json` encaminha os arquivos estáticos de `public/assets` e o restante das rotas para essa entrada.

A função está configurada com 1024 MB e 60 segundos. A geração de PDF usa Dompdf com cache de fonte em `/tmp`, porque o diretório do projeto fica somente leitura.

## Banco e arquivos

Use MySQL/MariaDB externo **ou** PostgreSQL (Supabase). Com a variável `VERCEL` presente, o sistema recusa SQLite.

Para o projeto Supabase já conectado (`lxqlsdynwtjcovjktxho`), use:

```text
DB_DRIVER=pgsql
DB_HOST=db.lxqlsdynwtjcovjktxho.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=<senha do painel Database Settings>
DB_SSLMODE=require
```

A senha fica em Project Settings → Database no [dashboard do Supabase](https://supabase.com/dashboard/project/lxqlsdynwtjcovjktxho/settings/database). Em produção, a connection string com a role `postgres` (ou service role de conexão direta) é a adequada para o PHP.

Sessões ficam na tabela `sessions`. PDFs ficam na coluna `documents.content` quando `STORAGE_DRIVER=database`, ou em um bucket compatível com S3 quando `STORAGE_DRIVER=s3`. Não há pasta local de documentos na produção.

## Variáveis na Vercel

No projeto da Vercel, em Settings → Environment Variables, preencha no mínimo:

- `APP_ENV=production`
- `APP_DEBUG=0`
- `APP_KEY` com pelo menos 32 caracteres aleatórios
- `APP_URL` com o endereço publicado (ex.: `https://seu-projeto.vercel.app`)
- `DB_DRIVER=pgsql` e os dados do Supabase acima
- `INSTALL_TOKEN` com um valor longo e aleatório
- `CRON_SECRET` com outro valor longo e aleatório
- `SEED_DEMO=0`
- `STORAGE_DRIVER=database`

Não defina `ADMIN_PASSWORD` permanente no painel da Vercel depois da instalação. A senha inicial é informada na tela `/instalar` e não volta a ser exibida.

## Publicação

1. Conecte o repositório ao projeto na Vercel (Root Directory = pasta do sistema, onde está `vercel.json`).
2. Confirme que `vercel.json` e `api/index.php` estão na raiz publicada.
3. Defina as variáveis de ambiente.
4. Faça o deploy.

## Primeiro acesso

1. Publique o projeto.
2. Abra `/instalar`.
3. Informe `INSTALL_TOKEN`, o e-mail e uma senha de 12 caracteres com letras e números.
4. Guarde a senha mostrada na tela seguinte.
5. Remova `INSTALL_TOKEN` das variáveis e publique de novo.

A redefinição emergencial usa `POST /emergencia/senha` com o token `EMERGENCY_RESET_TOKEN`, ou `php bin/reset-password.php` em um ambiente que alcance o mesmo banco.

## Tarefa agendada

O plano Hobby da Vercel executa cron uma vez por dia. A rota `/api/cron/alertas` está marcada para 12:00 UTC e exige o cabeçalho `Authorization: Bearer CRON_SECRET`. Ela envia alertas que já estejam na fila, remove sessões vencidas e limpa tentativas de login antigas. Os indicadores do painel não dependem dessa tarefa: eles são calculados na consulta.

## Limitações

- O sistema de arquivos da função é somente leitura, exceto `/tmp`.
- Não use sessão em arquivo nem SQLite.
- A execução máxima configurada é de 60 segundos. Relatórios muito grandes devem ser filtrados por período.
- Não há envio de e-mail ou WhatsApp sem as credenciais correspondentes.
- O cron diário não substitui um alerta em tempo real. A tela inicial mostra as reservas próximas e os saldos pendentes a cada acesso.
