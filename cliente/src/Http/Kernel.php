<?php

declare(strict_types=1);

namespace Cliente\Http;

use App\Database\Connection;
use App\Support\ConflictException;
use App\Support\ValidationException;
use Cliente\Auth\Gate;
use Cliente\Services\AccountService;
use Cliente\Services\Catalog;
use Cliente\Services\Commerce;
use Cliente\Services\Loyalty;

final class Kernel
{
    public function run(): void
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        try {
            if ($method === 'POST' && !str_starts_with($path, '/api/')) {
                Gate::checkCsrf();
            }
            $this->dispatch($method, $path);
        } catch (ValidationException $exception) {
            if (str_starts_with($path, '/api/')) {
                $this->json(['error' => implode(' ', $exception->errors)], 422);
            }
            Gate::flash('erro', implode(' ', $exception->errors));
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
        } catch (ConflictException $exception) {
            Gate::flash('erro', $exception->getMessage());
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/experiencias'));
        }
    }

    private function dispatch(string $method, string $path): void
    {
        $catalog = new Catalog();
        if ($method === 'GET' && $path === '/') {
            $user = Gate::user();
            $points = $user ? (new Loyalty())->balance((int) $user['client_id']) : null;
            $goal = (int) (Connection::value('SELECT MIN(cost_points) FROM rewards WHERE active = 1 AND cost_points > 0') ?: 500);
            $reached = false;
            if ($points) {
                $higher = Connection::value('SELECT MIN(cost_points) FROM rewards WHERE active = 1 AND cost_points > ?', [(int) $points['available']]);
                if ($higher) {
                    $goal = (int) $higher;
                } else {
                    $reached = true;
                }
            }
            $this->page('home', 'Drive Xperience', [
                'vehicles' => array_slice($catalog->vehicles(), 0, 6),
                'products' => array_slice($catalog->products(), 0, 4),
                'campaigns' => Connection::all('SELECT * FROM campaigns WHERE active = 1 AND is_public = 1 ORDER BY id DESC LIMIT 3'),
                'monthPromos' => Connection::all('SELECT * FROM app_promos WHERE active = 1 AND starts_on <= ? AND ends_on >= ? ORDER BY sort_order, id LIMIT 3', [today(), today()]),
                'activity' => $user ? Connection::all('SELECT * FROM client_notifications WHERE client_id = ? ORDER BY id DESC LIMIT 5', [(int) $user['client_id']]) : [],
                'points' => $points,
                'pointsGoal' => $goal,
                'pointsReached' => $reached,
                'referralPoints' => (int) (Connection::value('SELECT setting_value FROM client_settings WHERE setting_key = ?', ['referral_points']) ?: 200),
            ]);
        }
        if ($method === 'GET' && $path === '/design') {
            $this->page('design', 'Design');
        }
        if ($method === 'GET' && $path === '/experiencias') {
            $this->page('experiences', 'Experiências', [
                'vehicles' => $catalog->vehicles(),
                'photos' => $catalog->photos(),
            ]);
        }
        if ($method === 'GET' && $path === '/experiencias/veiculos') {
            $this->page('fleet', 'Veículos', ['vehicles' => $catalog->vehicles()]);
        }
        if ($method === 'GET' && $path === '/experiencias/fotos') {
            $this->page('photos', 'Fotos', ['photos' => $catalog->photos()]);
        }
        if ($method === 'GET' && $path === '/experiencias/casamentos') {
            $this->page('occasion', 'Casamentos', ['topic' => 'casamentos']);
        }
        if ($method === 'GET' && $path === '/experiencias/eventos') {
            $this->page('occasion', 'Eventos', ['topic' => 'eventos']);
        }
        if ($method === 'GET' && preg_match('#^/experiencias/([a-z0-9-]+)$#', $path, $match)) {
            $vehicle = $catalog->vehicle($match[1]);
            if (!$vehicle) {
                $this->missing();
            }
            $this->page('experience', $vehicle['brand'] . ' ' . $vehicle['model'], ['vehicle' => $vehicle]);
        }
        if ($method === 'POST' && preg_match('#^/experiencias/([a-z0-9-]+)$#', $path, $match)) {
            $vehicle = $catalog->vehicle($match[1]);
            if (!$vehicle) {
                $this->missing();
            }
            $line = (new Commerce())->experienceLine(
                (int) $vehicle['id'],
                (int) ($_POST['modality_id'] ?? 0),
                (string) ($_POST['date'] ?? ''),
                (string) ($_POST['time'] ?? '')
            );
            $cart = $_SESSION['cart'] ?? [];
            $key = 'e-' . $line['vehicle_id'] . '-' . $line['modality_id'] . '-' . $line['date'] . '-' . $line['time'];
            $cart[$key] = $line;
            $_SESSION['cart'] = $cart;
            Gate::flash('ok', 'A experiência entrou na mesma compra do carrinho.');
            header('Location: /carrinho');
            return;
        }
        if ($method === 'GET' && $path === '/loja') {
            $this->page('shop', 'Loja', ['products' => $catalog->products()]);
        }
        if ($method === 'GET' && preg_match('#^/loja/([a-z0-9-]+)$#', $path, $match)) {
            $product = $catalog->product($match[1]);
            if (!$product) {
                $this->missing();
            }
            $this->page('product', (string) $product['name'], ['product' => $product]);
        }
        if ($method === 'POST' && $path === '/carrinho') {
            $cart = $_SESSION['cart'] ?? [];
            $kind = (string) ($_POST['kind'] ?? 'produto');
            if ($kind === 'foto') {
                $line = (new Commerce())->photoLine((int) ($_POST['modality_id'] ?? 0));
                $key = 'f-' . $line['modality_id'];
                $line['quantity'] = (int) ($cart[$key]['quantity'] ?? 0) + 1;
                $cart[$key] = $line;
                Gate::flash('ok', 'O pacote de fotos entrou no carrinho.');
            } else {
                $id = (int) ($_POST['product_id'] ?? 0);
                $qty = max(1, (int) ($_POST['quantity'] ?? 1));
                $variant = (int) ($_POST['variant_id'] ?? 0);
                $key = $id . '-' . $variant;
                $cart[$key] = ['kind' => 'produto', 'product_id' => $id, 'variant_id' => $variant, 'quantity' => ($cart[$key]['quantity'] ?? 0) + $qty];
            }
            $_SESSION['cart'] = $cart;
            header('Location: /carrinho');
            return;
        }
        if ($method === 'POST' && $path === '/carrinho/remover') {
            $key = (string) ($_POST['key'] ?? '');
            $cart = $_SESSION['cart'] ?? [];
            unset($cart[$key]);
            $_SESSION['cart'] = $cart;
            header('Location: /carrinho');
            return;
        }
        if ($method === 'GET' && $path === '/carrinho') {
            $this->page('cart', 'Carrinho', ['lines' => $this->cartLines()]);
        }
        if ($method === 'POST' && $path === '/checkout') {
            $user = Gate::require();
            $code = (new Commerce())->checkoutCart((int) $user['client_id'], array_values($_SESSION['cart'] ?? []), (string) ($_POST['coupon'] ?? ''), (int) $user['is_demo'] === 1);
            $_SESSION['cart'] = [];
            Gate::flash('ok', 'Pedido ' . $code . ' aguarda pagamento. Nada foi marcado como pago.');
            header('Location: /pedidos');
            return;
        }
        if ($method === 'GET' && $path === '/vales') {
            $this->page('gifts', 'Vales-presente', ['vehicles' => $catalog->vehicles()]);
        }
        if ($method === 'POST' && $path === '/vales') {
            $user = Gate::require();
            $code = (new Commerce())->createGift(
                (int) $user['client_id'],
                (int) ($_POST['vehicle_id'] ?? 0),
                (int) ($_POST['modality_id'] ?? 0),
                (string) ($_POST['recipient'] ?? ''),
                (string) ($_POST['giver'] ?? $user['full_name']),
                (string) ($_POST['message'] ?? ''),
                (int) $user['is_demo'] === 1
            );
            header('Location: /vouchers/' . rawurlencode($code));
            return;
        }
        if ($method === 'GET' && $path === '/fidelidade') {
            $user = Gate::require();
            $loyalty = new Loyalty();
            $this->page('loyalty', 'Drive Points', [
                'balance' => $loyalty->balance((int) $user['client_id']),
                'entries' => $loyalty->entries((int) $user['client_id']),
                'rewards' => Connection::all('SELECT * FROM rewards WHERE active = 1 ORDER BY cost_points'),
                'visit' => $loyalty->visitToken((int) $user['client_id']),
            ]);
        }
        if ($method === 'POST' && $path === '/fidelidade/resgatar') {
            $user = Gate::require();
            (new Loyalty())->redeem((int) $user['client_id'], (int) ($_POST['reward_id'] ?? 0));
            Gate::flash('ok', 'Recompensa resgatada.');
            header('Location: /fidelidade');
            return;
        }
        if ($method === 'GET' && $path === '/indique') {
            $user = Gate::require();
            $code = (new Loyalty())->code((int) $user['client_id'], (string) $user['full_name']);
            $events = Connection::all('SELECT * FROM referral_events WHERE referrer_client_id = ? ORDER BY id DESC', [(int) $user['client_id']]);
            $this->page('referral', 'Indique e ganhe', ['code' => $code, 'events' => $events]);
        }
        if ($method === 'GET' && $path === '/promocoes') {
            $this->page('promotions', 'Promoções', [
                'campaigns' => Connection::all('SELECT * FROM campaigns WHERE active = 1 ORDER BY id DESC'),
                'coupons' => Connection::all('SELECT code, description, expires_on, is_demo FROM coupons WHERE active = 1'),
            ]);
        }
        if ($method === 'GET' && $path === '/notificacoes') {
            $user = Gate::require();
            $this->page('notifications', 'Avisos', [
                'items' => Connection::all('SELECT * FROM client_notifications WHERE client_id = ? ORDER BY id DESC', [(int) $user['client_id']]),
            ]);
        }
        if ($method === 'GET' && $path === '/meu-drive') {
            $this->page('drive', 'Meu Drive');
        }
        if ($method === 'GET' && $path === '/pedidos') {
            $user = Gate::require();
            $this->page('orders', 'Meus pedidos', [
                'orders' => Connection::all('SELECT * FROM client_orders WHERE client_id = ? ORDER BY id DESC', [(int) $user['client_id']]),
                'reservations' => Connection::all('SELECT code, scheduled_date, start_time, operational_status, financial_status, final_cents FROM reservations WHERE client_id = ? AND deleted_at IS NULL ORDER BY id DESC', [(int) $user['client_id']]),
            ]);
        }
        if ($method === 'GET' && $path === '/vouchers') {
            $user = Gate::require();
            $this->page('vouchers', 'Meus vouchers', [
                'vouchers' => Connection::all('SELECT * FROM gift_vouchers WHERE buyer_client_id = ? OR recipient_client_id = ? ORDER BY id DESC', [(int) $user['client_id'], (int) $user['client_id']]),
            ]);
        }
        if ($method === 'GET' && preg_match('#^/vouchers/([A-Z0-9-]+)$#i', $path, $match)) {
            $user = Gate::require();
            $voucher = Connection::one('SELECT g.*, v.brand, v.model, m.name AS modality FROM gift_vouchers g LEFT JOIN vehicles v ON v.id = g.vehicle_id LEFT JOIN modalities m ON m.id = g.modality_id WHERE g.code = ? AND (g.buyer_client_id = ? OR g.recipient_client_id = ?)', [strtoupper($match[1]), (int) $user['client_id'], (int) $user['client_id']]);
            if (!$voucher) {
                $this->missing();
            }
            $this->page('voucher', (string) $voucher['code'], ['voucher' => $voucher]);
        }
        if ($method === 'GET' && $path === '/entrar') {
            $this->page('login', 'Entrar', ['mode' => 'entrar']);
        }
        if ($method === 'POST' && $path === '/entrar') {
            (new AccountService())->login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
            header('Location: /meu-drive');
            return;
        }
        if ($method === 'GET' && $path === '/cadastrar') {
            $this->page('login', 'Criar conta', ['mode' => 'cadastrar']);
        }
        if ($method === 'POST' && $path === '/cadastrar') {
            (new AccountService())->register($_POST);
            Gate::flash('ok', 'Conta criada e vinculada ao cadastro de clientes.');
            header('Location: /meu-drive');
            return;
        }
        if ($method === 'POST' && $path === '/sair') {
            (new AccountService())->logout();
            header('Location: /');
            return;
        }
        if ($method === 'POST' && $path === '/conta/excluir') {
            $user = Gate::require();
            (new AccountService())->requestDeletion((int) $user['client_id']);
            Gate::flash('ok', 'Pedido de exclusão registrado. Reservas já feitas permanecem no cadastro central até a equipe concluir.');
            header('Location: /meu-drive');
            return;
        }
        if ($method === 'GET' && preg_match('#^/midia/(\d+)$#', $path, $match)) {
            $this->media((int) $match[1]);
        }
        if ($method === 'POST' && $path === '/api/visitas') {
            $name = (new Loyalty())->creditVisit((string) ($_POST['token'] ?? ''));
            $this->json(['ok' => true, 'client' => $name]);
        }
        if ($method === 'POST' && $path === '/api/pagamentos/retorno') {
            $this->json(['error' => 'Provedor de pagamento não configurado. O retorno do navegador não confirma compra.'], 503);
        }
        $this->missing();
    }

    private function cartLines(): array
    {
        $catalog = new Catalog();
        $lines = [];
        foreach ($_SESSION['cart'] ?? [] as $key => $line) {
            $kind = (string) ($line['kind'] ?? 'produto');
            if ($kind === 'foto') {
                $photo = $catalog->photo((int) ($line['modality_id'] ?? 0));
                if (!$photo) {
                    continue;
                }
                $qty = max(1, (int) ($line['quantity'] ?? 1));
                $lines[] = [
                    'key' => (string) $key,
                    'title' => (string) $photo['name'],
                    'meta' => 'Pacote de fotos · ' . $qty,
                    'total_cents' => (int) $photo['base_price_cents'] * $qty,
                ];
                continue;
            }
            if ($kind === 'experiencia') {
                $offer = $catalog->price((int) ($line['vehicle_id'] ?? 0), (int) ($line['modality_id'] ?? 0));
                if (!$offer) {
                    continue;
                }
                $when = ($line['date'] ?? '') !== '' ? date_br((string) $line['date']) . ' ' . (string) $line['time'] : 'Agendar depois';
                $lines[] = [
                    'key' => (string) $key,
                    'title' => $offer['name'] . ' · ' . $offer['brand'] . ' ' . $offer['model'],
                    'meta' => 'Experiência · ' . $when,
                    'total_cents' => (int) $offer['price_cents'],
                ];
                continue;
            }
            $product = Connection::one('SELECT * FROM shop_products WHERE id = ?', [(int) ($line['product_id'] ?? 0)]);
            if (!$product) {
                continue;
            }
            $variant = !empty($line['variant_id']) ? Connection::one('SELECT * FROM shop_variants WHERE id = ?', [(int) $line['variant_id']]) : null;
            $qty = max(1, (int) $line['quantity']);
            $unit = (int) ($product['promo_cents'] ?: $product['price_cents']);
            $detail = $variant ? trim($variant['size'] . ' ' . $variant['color']) : '';
            $lines[] = [
                'key' => (string) $key,
                'title' => (string) $product['name'],
                'meta' => trim($detail . ' · ' . $qty, ' ·'),
                'total_cents' => $unit * $qty,
            ];
        }
        return $lines;
    }

    private function page(string $template, string $title, array $data = []): never
    {
        $user = Gate::user();
        $flash = Gate::pullFlash();
        $csrf = Gate::csrf();
        extract($data, EXTR_SKIP);
        ob_start();
        require CLIENT_PATH . '/views/' . $template . '.php';
        $content = (string) ob_get_clean();
        require CLIENT_PATH . '/views/layout.php';
        exit;
    }

    private function media(int $id): never
    {
        $allowed = Connection::value(
            'SELECT id FROM stored_files WHERE id = ? AND (
                EXISTS (SELECT 1 FROM vehicles v WHERE v.image_file_id = stored_files.id AND v.deleted_at IS NULL)
                OR EXISTS (SELECT 1 FROM vehicle_images i JOIN vehicles v ON v.id = i.vehicle_id WHERE i.file_id = stored_files.id AND v.deleted_at IS NULL)
                OR EXISTS (SELECT 1 FROM app_promos p WHERE p.image_file_id = stored_files.id AND p.active = 1)
            )',
            [$id]
        );
        if (!$allowed) {
            http_response_code(404);
            exit;
        }
        $file = Connection::one('SELECT mime, content FROM stored_files WHERE id = ?', [$id]);
        header('Content-Type: ' . ($file['mime'] ?: 'application/octet-stream'));
        header('Cache-Control: public, max-age=86400');
        echo $file['content'];
        exit;
    }

    private function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function missing(): never
    {
        http_response_code(404);
        $this->page('missing', 'Página não encontrada');
    }
}
