<?php declare(strict_types=1);
// htdocs/api/helpers/ai.php
// AI Integration for recommendations, analytics, chatbot

require_once __DIR__ . '/../core/repositories/AiRepository.php';

class AIHelper {
    private static ?PDO $pdo = null;
    private static string $openaiKey = '';
    
    public static function setPDO(PDO $pdo) {
        self::$pdo = $pdo;
    }
    
    public static function setOpenAIKey($key) {
        self::$openaiKey = $key;
    }
    
    // توصيات المنتجات بناءً على سلوك المستخدم
    public static function getProductRecommendations($userId, $limit = 5) {
        if (!self::$pdo) return [];
        
        $repo = new AiRepository(self::$pdo);
        return $repo->getTopViewedProducts($userId, $limit);
    }
    
    // تحليل المبيعات باستخدام AI (مثال بسيط)
    public static function analyzeSalesTrend() {
        if (!self::$pdo) return [];
        
        $repo = new AiRepository(self::$pdo);
        return $repo->getDailySalesLast30Days();
    }
    
    // Chatbot بسيط باستخدام OpenAI
    public static function chatbotResponse($userMessage) {
        if (empty(self::$openaiKey)) return "AI not configured";
        
        $url = 'https://api.openai.com/v1/chat/completions';
        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [['role' => 'user', 'content' => $userMessage]],
            'max_tokens' => 100
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . self::$openaiKey,
                'Content-Type: application/json'
            ]
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        return $result['choices'][0]['message']['content'] ?? "Error";
    }
}