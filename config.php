<?php
   const DB_HOST = 'sql202.infinityfree.com';
   const DB_NAME = 'if0_43423405_inventory';
   const DB_USER = 'if0_43423405';
   const DB_PASS = 'joshuagwapo1204';
const CURRENCY = '₱';
const BUSINESS_NAME = 'Small Business Inventory';

session_start();

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            die('Database connection failed. Check config.php and make sure MySQL is running and database.sql was imported.');
        }
    }
    return $pdo;
}

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n): string { return CURRENCY . number_format((float)$n, 2); }
function redirect(string $url): void { header('Location: ' . $url); exit; }
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        die('Invalid or expired form token. Go back, refresh the page and try again.');
    }
}

function is_logged_in(): bool { return !empty($_SESSION['user']); }
function is_admin(): bool { return is_logged_in() && $_SESSION['user']['role'] === 'admin'; }
function require_login(): void { if (!is_logged_in()) redirect('login.php'); }
function require_admin(): void {
    require_login();
    if (!is_admin()) { http_response_code(403); die('Access denied. Administrators only.'); }
}

function stock_badge(int $qty, int $min): string {
    if ($qty <= 0) return '<span class="badge text-bg-danger">Out of stock</span>';
    if ($qty <= $min) return '<span class="badge text-bg-warning">Low stock</span>';
    return '<span class="badge text-bg-success">In stock</span>';
}

/** Rebuild the current query string with overrides (used for sort links and pagination). */
function qs(array $over = []): string {
    $q = array_merge($_GET, $over);
    foreach ($q as $k => $v) if ($v === null || $v === '') unset($q[$k]);
    return '?' . http_build_query($q);
}

function pager(int $total, int $per, int $page): string {
    $pages = max(1, (int)ceil($total / $per));
    if ($pages <= 1) return '';
    $h = '<nav><ul class="pagination pagination-sm justify-content-end mb-0">';
    $h .= '<li class="page-item' . ($page <= 1 ? ' disabled' : '') . '"><a class="page-link" href="' . e(qs(['page' => $page - 1])) . '">&laquo;</a></li>';
    for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++) {
        $h .= '<li class="page-item' . ($i === $page ? ' active' : '') . '"><a class="page-link" href="' . e(qs(['page' => $i])) . '">' . $i . '</a></li>';
    }
    $h .= '<li class="page-item' . ($page >= $pages ? ' disabled' : '') . '"><a class="page-link" href="' . e(qs(['page' => $page + 1])) . '">&raquo;</a></li>';
    return $h . '</ul></nav>';
}

function sort_link(string $col, string $label, string $cur, string $dir): string {
    $nd = ($cur === $col && $dir === 'ASC') ? 'desc' : 'asc';
    $icon = $cur === $col ? ($dir === 'ASC' ? ' <i class="bi bi-caret-up-fill"></i>' : ' <i class="bi bi-caret-down-fill"></i>') : '';
    return '<a class="text-decoration-none text-reset" href="' . e(qs(['sort' => $col, 'dir' => $nd, 'page' => 1])) . '">' . e($label) . $icon . '</a>';
}

function apply_stock(int $productId, string $type, int $qty, string $remarks, ?int $userId): array {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT quantity FROM products WHERE id = ? FOR UPDATE');
        $st->execute([$productId]);
        $before = $st->fetchColumn();
        if ($before === false) throw new RuntimeException('Product not found.');
        $before = (int)$before;

        if ($type === 'in') {
            if ($qty < 1) throw new RuntimeException('Quantity must be at least 1.');
            $after = $before + $qty; $moved = $qty;
        } elseif ($type === 'out') {
            if ($qty < 1) throw new RuntimeException('Quantity must be at least 1.');
            if ($qty > $before) throw new RuntimeException("Cannot remove $qty. Only $before in stock.");
            $after = $before - $qty; $moved = $qty;
        } else { // adjustment: $qty is the new counted quantity
            if ($qty < 0) throw new RuntimeException('Counted quantity cannot be negative.');
            $after = $qty; $moved = $after - $before;
        }
        $pdo->prepare('UPDATE products SET quantity = ? WHERE id = ?')->execute([$after, $productId]);
        $pdo->prepare('INSERT INTO stock_movements (product_id, user_id, type, quantity, qty_before, qty_after, remarks) VALUES (?,?,?,?,?,?,?)')
            ->execute([$productId, $userId, $type, $moved, $before, $after, $remarks !== '' ? $remarks : null]);
        $pdo->commit();
        return [true, "Stock updated: $before → $after."];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return [false, $ex->getMessage()];
    }
}
