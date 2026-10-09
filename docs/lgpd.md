# Privacidade e retenção

O sistema guarda os dados necessários para reserva, atendimento, pagamento e prestação de contas. CPF, placa e valores financeiros só aparecem para perfis com a permissão correspondente. Relatórios de clientes mascaram o CPF quando o usuário não tem essa permissão.

Cada cliente tem exportação dos dados pessoais para quem possui a permissão de dados sensíveis. A exclusão de dados pessoais substitui a identificação, apaga os contatos e remove o conteúdo dos PDFs daquele cliente. Reservas e lançamentos financeiros permanecem sem o nome e o documento, para não apagar o histórico contábil.

A senha é armazenada com `password_hash`. Tentativas de login são limitadas e o registro não guarda a senha digitada. Logs de auditoria não recebem senha, segredo de webhook nem conteúdo de PDF.

Documentos são baixados apenas por usuário autenticado e autorizado. O endereço usa um identificador aleatório. Uma nova emissão cria outra versão; a anterior continua disponível até ser removida por anonimização.

A configuração `document_retention_months` registra a política desejada de guarda. A remoção automática de documentos antigos não acontece sozinha: a equipe decide a exclusão ou a anonimização conforme a obrigação legal de cada documento. O termo de responsabilidade informa que o texto precisa de validação jurídica antes do uso definitivo. O recibo informa que não é comprovante bancário nem documento fiscal.

Faça backup do banco externo e, se usar S3, do bucket. O SQLite local não é cópia de produção.
