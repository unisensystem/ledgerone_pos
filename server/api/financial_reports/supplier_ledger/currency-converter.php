<?php
class CurrencyConverter {
    private $apiKey = '09d1eae8bf8f00d9f9fd4f61';
    private $apiUrl = 'https://v6.exchangerate-api.com/v6/';
    private $pdo;
    private $ratesCache = [];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get exchange rate from API with caching
     */
    private function getExchangeRate($fromCurrencyCode, $toCurrencyCode) {
        if ($fromCurrencyCode === $toCurrencyCode) {
            return 1;
        }
        
        $cacheKey = $fromCurrencyCode . '_' . $toCurrencyCode;
        if (isset($this->ratesCache[$cacheKey])) {
            return $this->ratesCache[$cacheKey];
        }
        
        $url = $this->apiUrl . $this->apiKey . '/latest/' . $fromCurrencyCode;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$response) {
            throw new Exception('Failed to fetch exchange rate');
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['result']) || $data['result'] !== 'success') {
            throw new Exception('Exchange rate API error');
        }
        
        if (!isset($data['conversion_rates'][$toCurrencyCode])) {
            throw new Exception('Currency not found in exchange rates');
        }
        
        $rate = $data['conversion_rates'][$toCurrencyCode];
        $this->ratesCache[$cacheKey] = $rate;
        
        return $rate;
    }
    
    /**
     * Get currency code by ID
     */
    private function getCurrencyCode($currencyId) {
        $stmt = $this->pdo->prepare("SELECT code FROM ledgerone_public.currencies WHERE id = ?");
        $stmt->execute([$currencyId]);
        $currency = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currency) {
            throw new Exception('Invalid currency ID: ' . $currencyId);
        }
        
        return $currency['code'];
    }
    
    /**
     * Convert amount from one currency to another
     */
    public function convert($amount, $fromCurrencyId, $toCurrencyId) {
        if (!$amount || $amount == 0) {
            return 0;
        }
        
        // If no from currency, assume it's already in target currency
        if (!$fromCurrencyId) {
            return $amount;
        }
        
        if ($fromCurrencyId == $toCurrencyId) {
            return $amount;
        }
        
        $fromCode = $this->getCurrencyCode($fromCurrencyId);
        $toCode = $this->getCurrencyCode($toCurrencyId);
        
        $rate = $this->getExchangeRate($fromCode, $toCode);
        
        return $amount * $rate;
    }
    
    /**
     * Convert amount with currency codes directly
     */
    public function convertByCodes($amount, $fromCode, $toCode) {
        if (!$amount || $amount == 0) {
            return 0;
        }
        
        if ($fromCode === $toCode) {
            return $amount;
        }
        
        $rate = $this->getExchangeRate($fromCode, $toCode);
        return $amount * $rate;
    }
}
