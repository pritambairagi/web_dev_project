<?php
function authPageStart(string $title, string $message = '', string $error = ''): void {
    $pageTitle = $title . ' | SalesPro';
    require __DIR__ . '/header.php';
    echo '<main id="main"><section class="section"><div class="wrap"><div class="panel" style="max-width:480px;margin:0 auto"><h1 style="margin-bottom:20px">' . e($title) . '</h1>';
    if ($message !== '') echo '<div class="notice" role="status" style="display:block;margin-bottom:18px">' . e($message) . '</div>';
    if ($error !== '') echo '<div role="alert" style="background:#fde8e8;color:#9b1c1c;padding:14px;border-radius:var(--r);margin-bottom:18px">' . e($error) . '</div>';
}
function authPageEnd(): void {
    echo '</div></div></section></main>';
    require __DIR__ . '/footer.php';
}
function authField(string $name, string $label, string $type, string $autocomplete, string $value = '', bool $required = true, string $extra = ''): void {
    echo '<div class="field"><label for="' . e($name) . '">' . e($label) . ($required ? ' *' : ' (optional)') . '</label><input id="' . e($name) . '" name="' . e($name) . '" type="' . e($type) . '" autocomplete="' . e($autocomplete) . '" value="' . e($value) . '" ' . ($required ? 'required ' : '') . $extra . '></div>';
}
function authFormStart(): void { echo '<form class="form" method="post"><input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">'; }
