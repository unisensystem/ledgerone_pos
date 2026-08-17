<?php
class CurrencyConverter {
    private $pdo;
    private $tenant_id;
    private $api_key = '09d1eae8bf8f00d9f9fd4f61';
    private $cache = [];
    
    public function __construct($pdo, $tenant_id) {
        $this->pdo = $pdo;
        $this->tenant_id = $tenant_id;
    }
    
    /**
     * Get currency code by ID
     */
    private function getCurrencyCode($currency_id) {
        if (isset($this->cache['code_' . $currency_id])) {
            return $this->cache['code_' . $currency_id];
        }
        
        $stmt = $this->pdo->prepare("SELECT code FROM ledgerone_public.currencies WHERE id = ?");
        $stmt->execute([$currency_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $this->cache['code_' . $currency_id] = $result['code'];
            return $result['code'];
        }
        
        return 'PKR';
    }
    
    /**
     * Get exchange rate from API
     */
    private function getExchangeRate($fromCode, $toCode) {
        $cacheKey = $fromCode . '_' . $toCode;
        
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        try {
            $url = "https://v6.exchangerate-api.com/v6/{$this->api_key}/latest/{$fromCode}";
            $response = @file_get_contents($url);
            
            if ($response === false) {
                return 1;
            }
            
            $data = json_decode($response, true);
            
            if (isset($data['conversion_rates'][$toCode])) {
                $rate = $data['conversion_rates'][$toCode];
                $this->cache[$cacheKey] = $rate;
                return $rate;
            }
        } catch (Exception $e) {
            return 1;
        }
        
        return 1;
    }
    
    /**
     * Convert amount from one currency to another
     */
    public function convert($amount, $fromCurrencyId, $toCurrencyId) {
        if (!$amount || $amount == 0) {
            return 0;
        }
        
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
}
