# Implantação

## Runtime

A Vercel não executa PHP nativamente. Este projeto usa o runtime comunitário `vercel-php@0.8.0`, que fornece PHP 8.4. A entrada da aplicação é `api/index.php`, e `vercel.json` encaminha os arquivos estáticos de `public/assets` e o restante das rotas para essa entrada.

A função está configurada com 1024 MB e 60 segundos. A geração de PDF usa Dompdf com cache de fonte em `/tmp`, porque o diretório do projeto fica somente leitura.

O painel responde na raiz do domínio. O aplicativo do cliente responde em `/app`, no mesmo projeto, e usa os arquivos de `cliente/public`. A raiz do projeto na Vercel precisa ser a pasta do repositório, onde está o `vercel.json`. Não use a pasta `cliente` como raiz: o aplicativo depende do `bootstrap` e do `vendor` da raiz.

## Banco e arquivos

Use MySQL/MariaDB externo **ou** PostgreSQL (Supabase). Com a variável `VERCEL` presente, o sistema recusa SQLite.

Para o projeto Supabase já conectado (`lxqlsdynwtjcovjktxho`), use:

```text
DB_DRIVER=pgsql
DB_HOST=aws-1-sa-east-1.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=dx_vercel.lxqlsdynwtjcovjktxho
DB_PASSWORD=<senha da role dx_vercel>
DB_SSLMODE=require
```

O endereço direto `db.lxqlsdynwtjcovjktxho.supabase.co` só responde em IPv6. A Vercel precisa do pooler acima, que é IPv4, na porta 5432 (modo sessão, para as consultas preparadas do PHP). A role `dx_vercel` ignora o RLS de propósito. A chave `anon` e o papel `authenticated` não têm acesso a nenhuma tabela: cada tabela tem RLS obrigatório e a política `deny_api`. Não reative esses privilégios no painel do Supabase.

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
- `MAPBOX_TOKEN` com o token público (`pk.*`) do Mapbox — mapa de Rastreio e trajeto nos PDFs da reserva

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

O plano Hobby da Vercel executa cron uma vez por dia. O agendamento chama `/api/cron.php` às 12:00 UTC. Essa função executa o mesmo tratamento de `/api/cron/alertas` e exige o cabeçalho `Authorization: Bearer CRON_SECRET`. Com a variável `CRON_SECRET` definida no projeto, a Vercel envia esse cabeçalho. A tarefa envia alertas que já estejam na fila, remove sessões vencidas e limpa tentativas de login antigas. Os indicadores do painel não dependem dessa tarefa: eles são calculados na consulta.

## Limitações

- O sistema de arquivos da função é somente leitura, exceto `/tmp`.
- Não use sessão em arquivo nem SQLite.
- A execução máxima configurada é de 60 segundos. Relatórios muito grandes devem ser filtrados por período.
- Não há envio de e-mail ou WhatsApp sem as credenciais correspondentes.
- O cron diário não substitui um alerta em tempo real. A tela inicial mostra as reservas próximas e os saldos pendentes a cada acesso.
