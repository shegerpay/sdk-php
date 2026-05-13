
    // ============================================
    // MULTI-CURRENCY WALLET METHODS
    // ============================================

    /**
     * Get multi-currency wallet balances
     * @return array
     */
    public function getWalletBalance() {
        return $this->request('GET', '/api/v1/paypal/wallet/balance');
    }

    /**
     * Convert currency within wallet
     * @param string $fromCurrency Source currency code
     * @param string $toCurrency Target currency code
     * @param float $amount Amount to convert
     * @return array
     */
    public function convertCurrency($fromCurrency, $toCurrency, $amount) {
        throw new \Exception('Currency conversion is private/assisted and is not exposed in the public SDK.');
    }

    /**
     * Get wallet transaction history
     * @param string|null $currency
     * @param int $limit
     * @return array
     */
    public function getWalletHistory($currency = null, $limit = 20) {
        $params = ['limit' => $limit];
        if ($currency) $params['currency'] = $currency;
        return $this->request('GET', '/api/v1/wallets/transactions?' . http_build_query($params));
    }

    // ============================================
    // REFUND METHODS
    // ============================================

    /**
     * Request a refund
     * @param string $transactionId
     * @param float|null $amount
     * @param string|null $reason
     * @return array
     */
    public function createRefund($transactionId, $amount = null, $reason = null) {
        $data = ['transaction_id' => $transactionId];
        if ($amount) $data['amount'] = $amount;
        if ($reason) $data['reason'] = $reason;
        return $this->request('POST', '/api/v1/refunds/request', $data, true);
    }

    /**
     * Get refund details
     * @param string $refundId
     * @return array
     */
    public function getRefund($refundId) {
        return $this->request('GET', "/api/v1/refunds/$refundId");
    }

    /**
     * Approve a pending refund
     * @param string $refundId
     * @return array
     */
    public function approveRefund($refundId) {
        return $this->request('POST', "/api/v1/refunds/$refundId/approve", [], true);
    }

    /**
     * Reject a pending refund
     * @param string $refundId
     * @param string $reason
     * @return array
     */
    public function rejectRefund($refundId, $reason) {
        return $this->request('POST', "/api/v1/refunds/$refundId/reject", ['reason' => $reason], true);
    }

    // ============================================
    // DISPUTE METHODS
    // ============================================

    /**
     * List disputes
     * @param string|null $status
     * @param int $limit
     * @return array
     */
    public function listDisputes($status = null, $limit = 20) {
        $params = ['limit' => $limit];
        if ($status) $params['status'] = $status;
        return $this->request('GET', '/api/v1/disputes?' . http_build_query($params));
    }

    /**
     * Get dispute details
     * @param string $disputeId
     * @return array
     */
    public function getDispute($disputeId) {
        return $this->request('GET', "/api/v1/disputes/$disputeId");
    }

    /**
     * Respond to a dispute
     * @param string $disputeId
     * @param string $message
     * @param array $evidence URLs of evidence
     * @return array
     */
    public function respondToDispute($disputeId, $message, $evidence = []) {
        return $this->request('POST', "/api/v1/disputes/$disputeId/respond", [
            'message' => $message,
            'evidence_urls' => $evidence
        ], true);
    }

    // ============================================
    // ANALYTICS METHODS
    // ============================================

    public function getApiUsage() {
        return $this->request('GET', '/api/v1/analytics/api-usage');
    }

    public function getWebhookLogs($limit = 20) {
        return $this->request('GET', "/api/v1/analytics/webhook-logs?limit=$limit");
    }
}
