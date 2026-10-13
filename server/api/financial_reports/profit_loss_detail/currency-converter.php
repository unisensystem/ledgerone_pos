<?php
class CurrencyConverter {
    private $pdo;
    private $tenant_id;
    private $exchange_rates = [];
    
    public function __construct($pdo, $tenant_id) {
        $this->pdo = $pdo;
        $this->tenant_id = $tenant_id;
    }
    
    public function convert($amount, $from_currency_id, $to_currency_id) {
        if ($from_currency_id == $to_currency_id) {
            return $amount;
        }
        
        $rate = $this->getExchangeRate($from_currency_id, $to_currency_id);
        return $amount * $rate;
    }
    
    private function getExchangeRate($from_currency_id, $to_currency_id) {
        $key = "{$from_currency_id}_{$to_currency_id}";
        
        if (isset($this->exchange_rates[$key])) {
            return $this->exchange_rates[$key];
        }
        
        $stmt = $this->pdo->prepare("SELECT code FROM ledgerone_public.currencies WHERE id = ?");
        $stmt->execute([$from_currency_id]);
        $from_currency = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt->execute([$to_currency_id]);
        $to_currency = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$from_currency || !$to_currency) {
            return 1;
        }
        
        $api_url = "https://v6.exchangerate-api.com/v6/09d1eae8bf8f00d9f9fd4f61/latest/{$from_currency['code']}";
        $response = @file_get_contents($api_url);
        
        if ($response === false) {
            return 1;
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['conversion_rates'][$to_currency['code']])) {
            $rate = $data['conversion_rates'][$to_currency['code']];
            $this->exchange_rates[$key] = $rate;
            return $rate;
        }
        
        return 1;
    }
}
