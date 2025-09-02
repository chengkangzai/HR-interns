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
    }

    public function chat(array $messages, array $options = [])
    {
        // curl -X POST "https://api.groq.com/openai/v1/chat/completions" \
        //     -H "Authorization: Bearer $GROQ_API_KEY" \
        //     -H "Content-Type: application/json" \
        //     -d '{"messages": [{"role": "user", "content": "Explain the importance of fast language models"}], "model": "llama-3.3-70b-versatile"}'
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl, array_merge([
            'model' => 'llama-3.3-70b-versatile',
            'messages' => $messages,
            'temperature' => 0.1,
        ], $options));

        if (! $response->successful()) {
            throw new \Exception('Groq API error: '.$response->body());
        }

        return $response->json();
    }
}
