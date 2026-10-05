<?php
/** JSON: an invoice's lines for the purchase form (what can be bought, and what already was). */
defined('APP_DIR') || exit;

header('Content-Type: application/json; charset=utf-8');
$doc = doc_get((int) query('doc'));
if (!$doc || $doc['type'] !== 'inv') {
    echo json_encode(['ok' => false, 'lines' => []]);
    exit;
}
echo json_encode(['ok' => true, 'lines' => invoice_buy_lines((int) $doc['id'], (int) query('except'))], JSON_UNESCAPED_UNICODE);
exit;
