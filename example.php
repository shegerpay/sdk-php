<?php
/**
 * === ShegerPay PHP SDK Examples ===
 * Verify Ethiopian bank payments with just a few lines of code
 */

require_once '../php/ShegerPay.php';
use ShegerPay\ShegerPay;

$client = new ShegerPay('sk_live_YOUR_API_KEY');

// =====================================================
// Example 1: Basic verify — amount is now optional
// =====================================================

$result = $client->verify(['transaction_id' => 'FT26062K7WMY']);
echo $result->valid ? 'Verified!' : 'Not verified: ' . $result->reason;

// =====================================================
// Example 2: With amount for stricter verification
// =====================================================

$result = $client->verify([
    'transaction_id' => 'FT26062K7WMY',
    'amount'         => 1000.00,
    'provider'       => 'cbe',
]);
echo "\nStrict verify: " . ($result->valid ? 'OK' : $result->reason);

// =====================================================
// Example 3: Image verification (receipt screenshot)
// =====================================================

$imageBase64 = base64_encode(file_get_contents('receipt.jpg'));
$imageResult = $client->verifyImage([
    'image'    => $imageBase64,
    'provider' => 'cbe',
]);
echo "\nImage verify: " . ($imageResult->valid ? 'OK' : $imageResult->reason);

// =====================================================
// Example 4: Create payment link
// =====================================================

$link = $client->createPaymentLink([
    'title'           => 'Order #1234',
    'amount'          => 1500.00,
    'currency'        => 'ETB',
    'enable_cbe'      => true,
    'enable_telebirr' => true,
]);
echo "\nPayment link: " . $link['url'];

// =====================================================
// Example 5: List & delete payment links
// =====================================================

$links = $client->listPaymentLinks(['limit' => 10]);
foreach ($links as $l) {
    echo "\n" . $l['id'] . ' — ' . $l['url'];
}
$client->deletePaymentLink($link['id']);

// =====================================================
// Example 6: Webhook management
// =====================================================

$webhook = $client->createWebhook([
    'url'    => 'https://your-site.com/webhooks/shegerpay',
    'events' => ['payment.verified', 'payment.failed'],
]);

$webhooks = $client->listWebhooks();
$client->testWebhook($webhook['id']);
$client->deleteWebhook($webhook['id']);

// =====================================================
// Example 7: Webhook handler (in your webhook endpoint)
// =====================================================

$payload   = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_SHEGERPAY_SIGNATURE'] ?? '';

$isValid = ShegerPay::verifyWebhookSignature($payload, $signature, 'YOUR_WEBHOOK_SECRET');
if (!$isValid) {
    http_response_code(401);
    exit('Invalid signature');
}

$event = json_decode($payload, true);
echo 'Event type: ' . $event['type'];

// =====================================================
// Example 8: List supported providers
// =====================================================

$providers = $client->getProviders();
foreach ($providers as $p) {
    echo "\n" . $p['name'] . ': ' . $p['status'];
}

// =====================================================
// Example 9: Transaction history
// =====================================================

$history = $client->getHistory(['limit' => 10]);
foreach ($history as $tx) {
    echo "\n" . $tx['transaction_id'] . ' — ' . $tx['amount'] . ' ETB (' . $tx['status'] . ')';
}
