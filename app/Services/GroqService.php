<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GroqService
{
    protected $apiKey;

    protected $baseUrl = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct()
    {
        $this->apiKey = config('services.groq.api_key');

        if (empty($this->apiKey)) {
            throw new \InvalidArgumentException('Groq API key is not configured. Please set GROQ_API_KEY in your environment.');
        }
    }

    public function chat(array $messages, array $options = [])
    {
        // Validate input messages
        if (empty($messages)) {
            throw new \InvalidArgumentException('Messages array cannot be empty.');
        }

        foreach ($messages as $message) {
            if (! isset($message['role']) || ! isset($message['content'])) {
                throw new \InvalidArgumentException('Each message must have "role" and "content" fields.');
            }

            if (! in_array($message['role'], ['system', 'user', 'assistant'])) {
                throw new \InvalidArgumentException('Message role must be one of: system, user, assistant.');
            }

            if (strlen($message['content']) > 100000) {
                throw new \InvalidArgumentException('Message content is too long (max 100,000 characters).');
            }
        }

        // Validate and sanitize options
        $allowedOptions = ['model', 'temperature', 'max_tokens', 'top_p', 'frequency_penalty', 'presence_penalty'];
        $options = array_intersect_key($options, array_flip($allowedOptions));

        if (isset($options['temperature']) && ($options['temperature'] < 0 || $options['temperature'] > 2)) {
            throw new \InvalidArgumentException('Temperature must be between 0 and 2.');
        }

        $response = Http::timeout(30)->withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl, array_merge([
            'model' => 'llama3-8b-8192',  // or 'llama2-70b-4096'
            'messages' => $messages,
            'temperature' => 0.1,
        ], $options));

        if (! $response->successful()) {
            throw new \Exception('Groq API error: '.$response->body());
        }

        return $response->json();
    }
}
