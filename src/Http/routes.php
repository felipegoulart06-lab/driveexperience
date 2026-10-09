<?php

declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\AppPromoController;
use App\Controllers\AgendaController;
use App\Controllers\AuthController;
use App\Controllers\ClientController;
use App\Controllers\CronController;
use App\Controllers\DashboardController;
use App\Controllers\DocumentController;
use App\Controllers\FinanceController;
use App\Controllers\HealthController;
use App\Controllers\InstallController;
use App\Controllers\IntegrationController;
use App\Controllers\PhotoController;
use App\Controllers\ProductController;
use App\Controllers\QuoteController;
use App\Controllers\ReportController;
use App\Controllers\ReservationController;
use App\Controllers\SettingsController;
use App\Controllers\TrackingController;
use App\Controllers\UserController;
use App\Controllers\VehicleController;
use App\Controllers\WebhookController;
use App\Http\Router;

function register_routes(Router $router): void
{
    $router->get('/login', [AuthController::class, 'form'], public: true);
    $router->post('/login', [AuthController::class, 'login'], public: true);
    $router->post('/sair', [AuthController::class, 'logout']);
    $router->get('/instalar', [InstallController::class, 'form'], public: true);
    $router->post('/instalar', [InstallController::class, 'store'], public: true, csrf: false);
    $router->post('/emergencia/senha', [InstallController::class, 'emergency'], public: true, csrf: false);
    $router->get('/health', [HealthController::class, 'show'], public: true);
    $router->post('/api/webhooks/entrada/{token}', [WebhookController::class, 'inbound'], public: true, csrf: false);
    $router->post('/api/webhooks/{key}', [WebhookController::class, 'receive'], public: true, csrf: false);
    $router->post('/api/rastreio', [TrackingController::class, 'ingest'], public: true, csrf: false);
    $router->get('/api/cron/alertas', [CronController::class, 'alerts'], public: true);

    $router->get('/', [DashboardController::class, 'index'], ['dashboard.view']);
    $router->get('/conta', [AccountController::class, 'form']);
    $router->post('/conta/senha', [AccountController::class, 'password']);

    $router->get('/clientes', [ClientController::class, 'index'], ['clients.view']);
    $router->get('/clientes/novo', [ClientController::class, 'create'], ['clients.manage']);
    $router->post('/clientes', [ClientController::class, 'store'], ['clients.manage']);
    $router->get('/clientes/busca', [ClientController::class, 'search'], ['clients.view']);
    $router->get('/clientes/{id}', [ClientController::class, 'show'], ['clients.view']);
    $router->post('/clientes/{id}', [ClientController::class, 'update'], ['clients.manage']);
    $router->post('/clientes/{id}/arquivar', [ClientController::class, 'archive'], ['clients.manage']);
    $router->post('/clientes/{id}/contatos', [ClientController::class, 'contact'], ['clients.manage']);
    $router->get('/clientes/{id}/exportar', [ClientController::class, 'export'], ['clients.sensitive']);
    $router->post('/clientes/{id}/anonimizar', [ClientController::class, 'anonymize'], ['clients.anonymize']);

    $router->get('/reservas', [ReservationController::class, 'index'], ['reservations.view']);
    $router->get('/reservas/nova', [ReservationController::class, 'create'], ['reservations.manage']);
    $router->post('/reservas', [ReservationController::class, 'store'], ['reservations.manage']);
    $router->get('/reservas/{id}/csv', [ReservationController::class, 'csv'], ['reservations.view']);
    $router->get('/reservas/{id}', [ReservationController::class, 'show'], ['reservations.view']);
    $router->post('/reservas/{id}/whatsapp', [ReservationController::class, 'whatsapp']);
    $router->post('/reservas/{id}', [ReservationController::class, 'update'], ['reservations.manage']);
    $router->post('/reservas/{id}/status', [ReservationController::class, 'status'], ['reservations.manage']);
    $router->post('/reservas/{id}/cancelar', [ReservationController::class, 'cancel'], ['reservations.cancel']);
    $router->post('/reservas/{id}/pagamentos', [ReservationController::class, 'pay']);
    $router->post('/reservas/{id}/documentos/{key}', [ReservationController::class, 'document'], ['documents.issue']);

    $router->get('/agenda', [AgendaController::class, 'index'], ['agenda.view']);
    $router->post('/agenda/bloqueios', [AgendaController::class, 'block'], ['agenda.manage']);
    $router->post('/agenda/bloqueios/{id}/remover', [AgendaController::class, 'unblock'], ['agenda.manage']);
    $router->get('/agenda/disponibilidade', [AgendaController::class, 'availability'], ['agenda.view']);

    $router->get('/veiculos', [VehicleController::class, 'index'], ['vehicles.view']);
    $router->get('/veiculos/novo', [VehicleController::class, 'create'], ['vehicles.manage']);
    $router->post('/veiculos', [VehicleController::class, 'store'], ['vehicles.manage']);
    $router->get('/veiculos/{id}/fotos/{foto}', [VehicleController::class, 'photo'], ['vehicles.view']);
    $router->post('/veiculos/{id}/fotos/{foto}/mover', [VehicleController::class, 'movePhoto'], ['vehicles.manage']);
    $router->post('/veiculos/{id}/fotos/{foto}/excluir', [VehicleController::class, 'deletePhoto'], ['vehicles.manage']);
    $router->get('/veiculos/{id}', [VehicleController::class, 'show'], ['vehicles.view']);
    $router->post('/veiculos/{id}', [VehicleController::class, 'update'], ['vehicles.manage']);
    $router->post('/veiculos/{id}/excluir', [VehicleController::class, 'destroy'], ['vehicles.manage']);
    $router->post('/veiculos/{id}/manutencao', [VehicleController::class, 'maintenance'], ['vehicles.manage']);

    $router->get('/rastreio', [TrackingController::class, 'index'], ['tracking.view']);
    $router->get('/rastreio/posicoes', [TrackingController::class, 'feed'], ['tracking.view']);
    $router->post('/rastreio', [TrackingController::class, 'store'], ['tracking.manage']);
    $router->post('/rastreio/chave', [TrackingController::class, 'rotate'], ['tracking.manage']);
    $router->post('/rastreio/{id}/remover', [TrackingController::class, 'destroy'], ['tracking.manage']);

    $router->get('/promocoes-app', [AppPromoController::class, 'index'], ['products.view']);
    $router->post('/promocoes-app', [AppPromoController::class, 'store'], ['products.manage']);
    $router->get('/promocoes-app/{id}/imagem', [AppPromoController::class, 'image'], ['products.view']);
    $router->post('/promocoes-app/{id}/excluir', [AppPromoController::class, 'destroy'], ['products.manage']);
    $router->post('/promocoes-app/{id}', [AppPromoController::class, 'update'], ['products.manage']);

    $router->get('/produtos', [ProductController::class, 'index'], ['products.view']);
    $router->post('/produtos', [ProductController::class, 'store'], ['products.manage']);
    $router->post('/produtos/{id}', [ProductController::class, 'update'], ['products.manage']);

    $router->get('/fotos', [PhotoController::class, 'index'], ['photos.view']);
    $router->post('/fotos', [PhotoController::class, 'store'], ['photos.manage']);
    $router->get('/fotos/{id}', [PhotoController::class, 'show'], ['photos.view']);
    $router->post('/fotos/{id}', [PhotoController::class, 'update'], ['photos.manage']);
    $router->post('/fotos/{id}/documentos/{key}', [PhotoController::class, 'document'], ['documents.issue']);

    $router->get('/financeiro', [FinanceController::class, 'index'], ['finance.view']);
    $router->post('/financeiro/despesas', [FinanceController::class, 'expense'], ['finance.manage']);
    $router->post('/financeiro/despesas/{id}/excluir', [FinanceController::class, 'destroyExpense'], ['finance.manage']);
    $router->post('/financeiro/pagamentos/{id}/estornar', [FinanceController::class, 'refund'], ['finance.manage']);
    $router->post('/financeiro/reservas/{id}/taxa', [FinanceController::class, 'fee'], ['finance.manage']);

    $router->get('/documentos', [DocumentController::class, 'index'], ['documents.view']);
    $router->get('/documentos/{key}', [DocumentController::class, 'download'], ['documents.view']);

    $router->get('/relatorios', [ReportController::class, 'index'], ['reports.view']);
    $router->get('/relatorios/csv', [ReportController::class, 'csv'], ['reports.view']);
    $router->post('/relatorios/pdf', [ReportController::class, 'pdf'], ['reports.view']);

    $router->get('/orcamentos', [QuoteController::class, 'index'], ['quotes.view']);
    $router->post('/orcamentos', [QuoteController::class, 'store'], ['quotes.manage']);
    $router->get('/orcamentos/{id}', [QuoteController::class, 'show'], ['quotes.view']);
    $router->post('/orcamentos/{id}/pdf', [QuoteController::class, 'pdf'], ['documents.issue']);
    $router->post('/orcamentos/{id}/converter', [QuoteController::class, 'convert'], ['reservations.manage']);

    $router->get('/integracoes', [IntegrationController::class, 'index'], ['integrations.manage']);
    $router->post('/integracoes/webhooks', [IntegrationController::class, 'createWebhook'], ['integrations.manage']);
    $router->get('/integracoes/webhooks/{id}', [IntegrationController::class, 'webhook'], ['integrations.manage']);
    $router->get('/integracoes/webhooks/{id}/testes', [IntegrationController::class, 'webhookTests'], ['integrations.manage']);
    $router->post('/integracoes/webhooks/{id}', [IntegrationController::class, 'updateWebhook'], ['integrations.manage']);
    $router->post('/integracoes/webhooks/{id}/remover', [IntegrationController::class, 'deleteWebhook'], ['integrations.manage']);
    $router->post('/integracoes/plataformas', [IntegrationController::class, 'savePlatform'], ['integrations.manage']);
    $router->post('/integracoes/plataformas/{id}', [IntegrationController::class, 'updatePlatform'], ['integrations.manage']);
    $router->post('/integracoes/importar', [IntegrationController::class, 'upload'], ['integrations.manage']);
    $router->get('/integracoes/importar/{id}', [IntegrationController::class, 'map'], ['integrations.manage']);
    $router->post('/integracoes/importar/{id}/previa', [IntegrationController::class, 'preview'], ['integrations.manage']);
    $router->post('/integracoes/importar/{id}/confirmar', [IntegrationController::class, 'confirm'], ['integrations.manage']);
    $router->post('/integracoes/sincronizacoes/{id}/reprocessar', [IntegrationController::class, 'reprocess'], ['integrations.manage']);

    $router->get('/usuarios', [UserController::class, 'index'], ['users.manage']);
    $router->post('/usuarios', [UserController::class, 'store'], ['users.manage']);
    $router->post('/usuarios/{id}/senha', [UserController::class, 'password'], ['users.manage']);
    $router->post('/usuarios/{id}', [UserController::class, 'update'], ['users.manage']);
    $router->post('/usuarios/perfis/{id}', [UserController::class, 'permissions'], ['users.manage']);

    $router->get('/configuracoes', [SettingsController::class, 'index'], ['settings.manage']);
    $router->post('/configuracoes', [SettingsController::class, 'save'], ['settings.manage']);
    $router->post('/configuracoes/modelos', [SettingsController::class, 'template'], ['settings.manage']);
}
