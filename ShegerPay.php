<?php
/**
 * ShegerPay PHP SDK v2.2.0
 * Official PHP SDK for ShegerPay Payment Verification Gateway
 *
 * @package ShegerPay
 * @version 2.2.0
 * @author ShegerPay <support@shegerpay.com>
 * @license MIT
 */

namespace ShegerPay;

class ShegerPayException extends \Exception {}
class AuthenticationException extends ShegerPayException {}
class ValidationException extends ShegerPayException {}

/**
 * Verification result object
 */
class VerificationResult {
    public bool $valid;
    public string $status;
    public ?string $provider;
    public ?string $transactionId;
    public ?float $amount;
    public ?string $reason;
    public ?string $mode;
    
    public function __construct(array $data) {
        $this->valid = $data['valid'] ?? false;
        $this->status = $data['status'] ?? 'unknown';
        $this->provider = $data['provider'] ?? null;
        $this->transactionId = $data['transaction_id'] ?? null;
        $this->amount = $data['amount'] ?? null;
        $this->reason = $data['reason'] ?? null;
        $this->mode = $data['mode'] ?? null;
    }
}

/**
 * ShegerPay Payment Verification Client
 */
class ShegerPay {
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;
    private string $mode;
    
    const DEFAULT_BASE_URL = 'https://api.shegerpay.com';
    
    public function __construct(string $apiKey, array $options = []) {
        if (empty($apiKey)) {
            throw new AuthenticationException('API key is required');
        }
        
        if (!str_starts_with($apiKey, 'sk_test_') && !str_starts_with($apiKey, 'sk_live_')) {
            throw new AuthenticationException('Invalid API key format');
        }
        
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($options['base_url'] ?? self::DEFAULT_BASE_URL, '/');
        $this->timeout = $options['timeout'] ?? 30;
        $this->mode = str_starts_with($apiKey, 'sk_test_') ? 'test' : 'live';
    }
    
    // ============================================
    // 🇪🇹 ETHIOPIAN VERIFICATION
    // ============================================
    
    public function verify(array $params): VerificationResult {
        $transactionId = $params['transaction_id'] ?? null;
        $amount = $params['amount'] ?? null;
        $provider = $params['provider'] ?? null;
        $merchantName = $params['merchant_name'] ?? 'ShegerPay Verification';
        $senderAccount = $params['sender_account'] ?? null;
        
        if (!$transactionId) throw new ValidationException('transaction_id is required');

        if (!$provider) {
            $provider = stripos($transactionId, 'cs.bankofabyssinia.com/slip/?trx=') !== false ? 'boa' : null;
        }
        if (!$provider) {
            throw new ValidationException('provider is required for ambiguous transaction references. Pass provider explicitly or use quickVerify().');
        }

        $data = [
            'provider' => $provider,
            'transaction_id' => $transactionId,
            'merchant_name' => $merchantName,
        ];
        if ($amount !== null) {
            $data['amount'] = $amount;
        }

        if (isset($params['sub_provider'])) {
            $data['sub_provider'] = $params['sub_provider'];
        }
        if ($senderAccount) {
            $data['sender_account'] = $senderAccount;
        }

        $response = $this->request('POST', '/api/v1/verify', $data);
        return new VerificationResult($response);
    }

    public function quickVerify(string $transactionId, ?float $amount = null, ?string $expectedProvider = null, ?string $senderAccount = null): VerificationResult {
        $payload = ['transaction_id' => $transactionId];
        if ($amount !== null) {
            $payload['amount'] = $amount;
        }
        if ($expectedProvider) {
            $payload['expected_provider'] = $expectedProvider;
        }
        if ($senderAccount) {
            $payload['sender_account'] = $senderAccount;
        }
        $response = $this->request('POST', '/api/v1/quick-verify', $payload);
        return new VerificationResult($response);
    }

    public function verifyImage(array $params): VerificationResult {
        $image = $params['image'] ?? null; // base64 or URL
        $provider = $params['provider'] ?? null;
        $amount = $params['amount'] ?? null;
        $merchantName = $params['merchant_name'] ?? 'ShegerPay Verification';

        if (!$image) throw new ValidationException('image is required (base64 or URL)');

        $payload = [
            'image' => $image,
            'provider' => $provider,
            'merchant_name' => $merchantName,
        ];
        if ($amount !== null) $payload['amount'] = $amount;

        $response = $this->request('POST', '/api/v1/verify/image', $payload, true);
        return new VerificationResult($response);
    }

    public function getHistory(int $limit = 50): array {
        return $this->request('GET', '/api/v1/history');
    }
    
    // ============================================
    // 🪙 CRYPTO
    // ============================================
    
    public function getCryptoPrices(?string $symbol = null): array {
        if ($symbol) {
            return $this->request('GET', '/api/v1/crypto/rate/' . strtoupper($symbol));
        }
        return $this->request('GET', '/api/v1/crypto/rates');
    }
    
    public function createCryptoPayment(array $params): array {
        return $this->requestJson('POST', '/api/v1/crypto/generate-intent', [
            'amount_usd' => $params['amount_usd'],
            'currency' => strtoupper($params['currency']),
            'wallet_address' => $params['wallet_address'],
            'chain' => $params['chain'] ?? 'TRON',
        ]);
    }
    
    public function verifyCryptoPayment(string $referenceId, ?string $txHash = null): array {
        return $this->requestJson('POST', '/api/v1/crypto/verify-reference', [
            'reference_id' => $referenceId,
            'transaction_hash' => $txHash,
        ]);
    }
    
    // ============================================
    // 💳 PAYPAL
    // ============================================
    
    public function paypalCreateOrder(array $params): array {
        return $this->requestJson('POST', '/api/v1/paypal/create-order', [
            'amount' => $params['amount'],
            'currency' => $params['currency'] ?? 'USD',
            'description' => $params['description'] ?? null,
        ]);
    }
    
    public function paypalCaptureOrder(string $orderId): array {
        return $this->requestJson('POST', '/api/v1/paypal/capture-order', ['order_id' => $orderId]);
    }
    
    public function paypalRefund(array $params): array {
        return $this->requestJson('POST', '/api/v1/paypal/refund', $params);
    }
    
    // ============================================
    // 🔗 PAYMENT LINKS
    // ============================================
    
    public function createPaymentLink(array $params): array {
        return $this->requestJson('POST', '/api/v1/payment-links/', [
            'title' => $params['title'],
            'amount' => $params['amount'],
            'currency' => $params['currency'] ?? 'ETB',
            'description' => $params['description'] ?? null,
            'enable_cbe' => $params['enable_cbe'] ?? true,
            'enable_telebirr' => $params['enable_telebirr'] ?? true,
            'enable_crypto' => $params['enable_crypto'] ?? false,
            'expires_in_hours' => $params['expires_in_hours'] ?? 24,
        ]);
    }
    
    public function listPaymentLinks(): array {
        return $this->request('GET', '/api/v1/payment-links/');
    }
    
    public function deletePaymentLink(string $linkId): array {
        return $this->request('DELETE', '/api/v1/payment-links/' . $linkId);
    }
    
    // ============================================
    // 🪝 WEBHOOKS
    // ============================================
    
    public function createWebhook(string $url, array $events): array {
        return $this->requestJson('POST', '/api/v1/webhooks/', ['url' => $url, 'events' => $events]);
    }
    
    public function listWebhooks(): array {
        return $this->request('GET', '/api/v1/webhooks/');
    }
    
    public function testWebhook(string $webhookId): array {
        return $this->request('POST', '/api/v1/webhooks/test?webhook_id=' . $webhookId);
    }
    
    public function deleteWebhook(string $webhookId): array {
        return $this->request('DELETE', '/api/v1/webhooks/' . $webhookId);
    }
    
    // ============================================
    // 💼 WALLETS
    // ============================================
    
    public function getWalletBalance(): array {
        return $this->request('GET', '/api/v1/paypal/wallet/balance');
    }
    
    public function convertCurrency(string $from, string $to, float $amount): array {
        throw new ShegerPayException('Currency conversion is private/assisted and is not exposed in the public SDK.');
    }
    
    // ============================================
    // 💸 REFUNDS
    // ============================================
    
    public function createRefund(string $transactionId, ?float $amount = null, ?string $reason = null): array {
        return $this->requestJson('POST', '/api/v1/refunds', [
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'reason' => $reason,
        ]);
    }
    
    public function listRefunds(?string $status = null): array {
        $path = '/api/v1/refunds';
        if ($status) $path .= '?status=' . $status;
        return $this->request('GET', $path);
    }
    
    // ============================================
    // ⚖️ DISPUTES
    // ============================================
    
    public function listDisputes(?string $status = null): array {
        $path = '/api/v1/disputes';
        if ($status) $path .= '?status=' . $status;
        return $this->request('GET', $path);
    }
    
    public function respondToDispute(string $disputeId, string $message, ?array $evidenceUrls = null): array {
        return $this->requestJson('POST', "/api/v1/disputes/{$disputeId}/respond", [
            'message' => $message,
            'evidence_urls' => $evidenceUrls,
        ]);
    }
    
    // ============================================
    // 💰 PAYOUTS
    // ============================================
    
    public function requestPayout(float $amount, string $currency, string $method = 'bank_transfer', ?string $destinationId = null): array {
        return $this->requestJson('POST', '/api/v1/paypal/payouts/request', [
            'amount' => $amount,
            'currency' => $currency,
            'recipient_email' => $destinationId,
        ]);
    }
    
    public function listPayouts(?string $status = null): array {
        return $this->request('GET', '/api/v1/paypal/payouts');
    }
    
    // ============================================
    // 🌍 INTERNATIONAL
    // ============================================
    
    public function addWiseAccount(string $email, ?string $label = null): array {
        throw new ShegerPayException('Wise account setup is private/assisted and is not exposed in the public SDK.');
    }
    
    public function addPayoneerAccount(string $email, ?string $label = null): array {
        throw new ShegerPayException('Payoneer account setup is private/assisted and is not exposed in the public SDK.');
    }
    
    public function getGmailStatus(): array {
        return $this->request('GET', '/api/v1/international/gmail/status');
    }
    
    // ============================================
    // 🔐 2FA
    // ============================================
    
    public function setup2FA(): array {
        return $this->requestJson('POST', '/api/v1/two-factor/setup', []);
    }
    
    public function verify2FA(string $code): array {
        return $this->requestJson('POST', '/api/v1/two-factor/verify', ['code' => $code]);
    }
    
    public function get2FAStatus(): array {
        return $this->request('GET', '/api/v1/two-factor/status');
    }
    
    public function disable2FA(string $code): array {
        return $this->requestJson('POST', '/api/v1/two-factor/disable', ['code' => $code]);
    }
    
    // ============================================
    // 🔑 PASSKEYS
    // ============================================
    
    public function listPasskeys(): array {
        return $this->request('GET', '/api/v1/passkeys');
    }
    
    public function deletePasskey(string $passkeyId): array {
        return $this->request('DELETE', '/api/v1/passkeys/' . $passkeyId);
    }
    
    // ============================================
    // 📊 TRANSACTIONS & SUBSCRIPTIONS
    // ============================================
    
    public function listTransactions(array $filters = []): array {
        $query = http_build_query($filters);
        return $this->request('GET', '/api/v1/transactions/history' . ($query ? '?' . $query : ''));
    }
    
    public function getSubscription(): array {
        return $this->request('GET', '/api/v1/subscriptions/status');
    }
    
    public function getUsage(): array {
        return $this->request('GET', '/api/v1/analytics/api-usage');
    }
    
    // ============================================
    // INTERNAL METHODS
    // ============================================
    
    private function request(string $method, string $path, array $data = [], bool $asJson = false): array {
        $url = $this->baseUrl . $path;

        $contentType = $asJson ? 'application/json' : 'application/x-www-form-urlencoded';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-Key: ' . $this->apiKey,
            'Content-Type: ' . $contentType,
            'User-Agent: ShegerPay-PHP-SDK/2.2',
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $asJson ? json_encode($data) : http_build_query($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode === 401) throw new AuthenticationException('Invalid API key');
        if ($statusCode === 400) {
            $error = json_decode($response, true);
            throw new ValidationException($error['detail'] ?? 'Validation error');
        }

        return json_decode($response, true) ?? [];
    }

    private function requestJson(string $method, string $path, array $data): array {
        $url = $this->baseUrl . $path;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-Key: ' . $this->apiKey,
            'Content-Type: application/json',
            'User-Agent: ShegerPay-PHP-SDK/2.1',
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($statusCode === 401) throw new AuthenticationException('Invalid API key');
        if ($statusCode === 400) {
            $error = json_decode($response, true);
            throw new ValidationException($error['detail'] ?? 'Validation error');
        }
        
        return json_decode($response, true) ?? [];
    }
    
    public static function verifyWebhookSignature(string $payload, string $signature, string $secret): bool {
        $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $signature);
    }
}


/**
 * Verification result object
 */
class VerificationResult {
    public bool $valid;
    public string $status;
    public ?string $provider;
    public ?string $transactionId;
    public ?float $amount;
    public ?string $reason;
    public ?string $mode;
    
    public function __construct(array $data) {
        $this->valid = $data['valid'] ?? false;
        $this->status = $data['status'] ?? 'unknown';
        $this->provider = $data['provider'] ?? null;
        $this->transactionId = $data['transaction_id'] ?? null;
        $this->amount = $data['amount'] ?? null;
        $this->reason = $data['reason'] ?? null;
        $this->mode = $data['mode'] ?? null;
    }
}

/**
 * ShegerPay Payment Verification Client
 */
class ShegerPay {
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;
    private string $mode;
    
    const DEFAULT_BASE_URL = 'https://api.shegerpay.com';
    
    /**
     * Create a new ShegerPay client
     * 
     * @param string $apiKey Your secret API key (sk_test_xxx or sk_live_xxx)
     * @param array $options Optional configuration
     */
    public function __construct(string $apiKey, array $options = []) {
        if (empty($apiKey)) {
            throw new AuthenticationException('API key is required');
        }
        
        if (!str_starts_with($apiKey, 'sk_test_') && !str_starts_with($apiKey, 'sk_live_')) {
            throw new AuthenticationException('Invalid API key format');
        }
        
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($options['base_url'] ?? self::DEFAULT_BASE_URL, '/');
        $this->timeout = $options['timeout'] ?? 30;
        $this->mode = str_starts_with($apiKey, 'sk_test_') ? 'test' : 'live';
    }
    
    /**
     * Verify a payment transaction
     * 
     * @param array $params Verification parameters
     * @return VerificationResult
     */
    public function verify(array $params): VerificationResult {
        $transactionId = $params['transaction_id'] ?? null;
        $amount = $params['amount'] ?? null;
        $provider = $params['provider'] ?? null;
        $merchantName = $params['merchant_name'] ?? 'ShegerPay Verification';
        $senderAccount = $params['sender_account'] ?? null;
        
        if (!$transactionId) {
            throw new ValidationException('transaction_id is required');
        }

        if (!$provider) {
            $provider = stripos($transactionId, 'cs.bankofabyssinia.com/slip/?trx=') !== false ? 'boa' : null;
        }
        if (!$provider) {
            throw new ValidationException('provider is required for ambiguous transaction references. Pass provider explicitly or use quickVerify().');
        }

        $data = [
            'provider' => $provider,
            'transaction_id' => $transactionId,
            'merchant_name' => $merchantName,
        ];
        if ($amount !== null) {
            $data['amount'] = $amount;
        }

        if (isset($params['sub_provider'])) {
            $data['sub_provider'] = $params['sub_provider'];
        }
        if ($senderAccount) {
            $data['sender_account'] = $senderAccount;
        }

        $response = $this->request('POST', '/api/v1/verify', $data);
        return new VerificationResult($response);
    }

    /**
     * Quick verification with auto-detected provider
     *
     * @param string $transactionId Bank transaction reference
     * @param float|null $amount Expected amount (optional for lookup-only)
     * @return VerificationResult
     */
    public function quickVerify(string $transactionId, ?float $amount = null, ?string $expectedProvider = null, ?string $senderAccount = null): VerificationResult {
        $payload = ['transaction_id' => $transactionId];
        if ($amount !== null) {
            $payload['amount'] = $amount;
        }
        if ($expectedProvider) {
            $payload['expected_provider'] = $expectedProvider;
        }
        if ($senderAccount) {
            $payload['sender_account'] = $senderAccount;
        }
        $response = $this->request('POST', '/api/v1/quick-verify', $payload);
        return new VerificationResult($response);
    }
    
    /**
     * Verify a payment from a screenshot image
     *
     * @param array $params image (base64 or URL), provider, amount, merchant_name
     * @return VerificationResult
     */
    public function verifyImage(array $params): VerificationResult {
        $image = $params['image'] ?? null; // base64 or URL
        $provider = $params['provider'] ?? null;
        $amount = $params['amount'] ?? null;
        $merchantName = $params['merchant_name'] ?? 'ShegerPay Verification';

        if (!$image) throw new ValidationException('image is required (base64 or URL)');

        $payload = [
            'image' => $image,
            'provider' => $provider,
            'merchant_name' => $merchantName,
        ];
        if ($amount !== null) $payload['amount'] = $amount;

        $response = $this->request('POST', '/api/v1/verify/image', $payload, true);
        return new VerificationResult($response);
    }

    /**
     * Get transaction history
     *
     * @param int $limit Maximum number of transactions
     * @return array
     */
    public function getHistory(int $limit = 50): array {
        return $this->request('GET', '/api/v1/history');
    }
    
    /**
     * Make API request
     */
    private function request(string $method, string $path, array $data = [], bool $asJson = false): array {
        $url = $this->baseUrl . $path;

        $contentType = $asJson ? 'application/json' : 'application/x-www-form-urlencoded';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-Key: ' . $this->apiKey,
            'Content-Type: ' . $contentType,
            'User-Agent: ShegerPay-PHP-SDK/2.2',
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $asJson ? json_encode($data) : http_build_query($data));
        }

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($statusCode === 401) {
            throw new AuthenticationException('Invalid API key');
        }

        if ($statusCode === 400) {
            $error = json_decode($response, true);
            throw new ValidationException($error['detail'] ?? 'Validation error');
        }

        return json_decode($response, true) ?? [];
    }
    
    /**
     * Verify webhook signature
     * 
     * @param string $payload Raw request body
     * @param string $signature X-ShegerPay-Signature header
     * @param string $secret Your webhook secret
     * @return bool
     */
    public static function verifyWebhookSignature(string $payload, string $signature, string $secret): bool {
        $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $signature);
    }
}
