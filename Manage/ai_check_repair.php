<?php
/**
 * AI Repair Spam Checker — JSON endpoint
 * Called via AJAX from Tenant/repair.php for real-time validation.
 * Also used server-side before inserting a repair record.
 *
 * GET/POST param: text (repair description to evaluate)
 * Response: {"score":int,"label":"ok"|"suspect"|"spam","message":string}
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache');
header('X-Content-Type-Options: nosniff');

// Rate limit: very simple check — only accept reasonable-length text
$text = trim((string)($_REQUEST['text'] ?? ''));

if (mb_strlen($text, 'UTF-8') > 1000) {
    echo json_encode(['score' => 0, 'label' => 'spam', 'message' => 'ข้อความยาวเกินไป']);
    exit;
}

require_once __DIR__ . '/../includes/repair_spam_check.php';

echo json_encode(scoreRepairText($text), JSON_UNESCAPED_UNICODE);
