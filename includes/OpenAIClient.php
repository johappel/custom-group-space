<?php

class OpenAIClient {
    private $apiKey;
    private $model;
    private $maxTokens;
    private $temperature;
    private $systemMessage;
    private $apiUrl = 'https://api.openai.com/v1/chat/completions';

    public function __construct($model = 'gpt-4o-mini-2024-07-18', $maxTokens = 4096, $temperature = 0.2) {
        $this->apiKey = get_option('options_openai_api_key');
        $this->model = $model;
        $this->maxTokens = $maxTokens;
        $this->temperature = $temperature;
        $this->systemMessage = '';
    }

    public function setSystemMessage($message) {
        $this->systemMessage = $message;
    }

    public function generateText($userMessage) {
        return $this->chat($userMessage);
    }

    public function generateJson($userMessage) {
        return $this->json($userMessage);
    }

    private function makeRequest($userMessage, $responseFormat = ['type' => 'text']) {
        $messages = [];

        if (!empty($this->systemMessage)) {
            $messages[] = ['role' => 'system', 'content' => $this->systemMessage];
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $data = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
            'top_p' => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0,
            'response_format' => $responseFormat
        ];

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($data),
            'method' => 'POST',
            'data_format' => 'body',
            'timeout' => 60,
        ];

        $response = wp_remote_post($this->apiUrl, $args);

        if (is_wp_error($response)) {
            return ['error' => 'WordPress Fehler: ' . $response->get_error_message()];
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded_response = json_decode($body, true);

        if ($http_code !== 200) {
            return [
                'error' => 'HTTP Fehler ' . $http_code,
                'body' => $decoded_response ?: $body
            ];
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'JSON Dekodierungsfehler: ' . json_last_error_msg(), 'raw_body' => $body];
        }

        return $decoded_response;
    }

    public function chat($userMessage) {
        $response = $this->makeRequest($userMessage);

        if (isset($response['error'])) {
            return 'API-Fehler: ' . print_r($response, true);
        }

        if (isset($response['choices'][0]['message']['content'])) {
            return $response['choices'][0]['message']['content'];
        }

        return 'Unerwartete API-Antwort: ' . print_r($response, true);
    }

    public function json($userMessage) {
        $response = $this->makeRequest($userMessage, ['type' => 'json_object']);

        if (isset($response['error'])) {
            return ['error' => 'API-Fehler: ' . print_r($response, true)];
        }

        if (isset($response['choices'][0]['message']['content'])) {
            $json_content = json_decode($response['choices'][0]['message']['content'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['error' => 'JSON Dekodierungsfehler: ' . json_last_error_msg()];
            }
            return $json_content;
        }

        return ['error' => 'Unerwartete API-Antwort: ' . print_r($response, true)];
    }
}