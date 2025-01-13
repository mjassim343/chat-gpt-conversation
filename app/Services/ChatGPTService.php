<?php

namespace App\Services;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Exception;

class ChatGPTService
{
    protected $apiUrl;
    protected $model;
    protected $stream;
    protected $temperature;
    protected $token;

    public function __construct()
    {
        $this->apiUrl = 'https://api.openai.com/v1/chat/completions';
        $this->model = 'gpt-4o-mini';
        $this->stream = true;
        $this->temperature = 0.7;
        $this->token = 2048;
    }

    public function sendMessage(string $message)
    {
        try 
        {
            $client = new Client();

            $headers = 
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . env('CHATGPT_API_KEY'),
            ];
            
            $body = 
            [
                'model' => $this->model,
                'messages' => [['role' => 'user', 'content' => $message]],
                "temperature" => $this->temperature,
                "max_tokens" => $this->token
            ];

            $response = $client->post($this->apiUrl, 
            [
                'headers' => $headers, 
                'json' => $body,
                "stream" => $this->stream,
            ]);

            $body = $response->getBody();

            $chunk ='';
            while (!$body->eof()) 
            {
                $chunk .= $body->read(1024); // Reading the stream in chunks
            }

            // Decode the chunk to check for content
            $result = json_decode($chunk, true);

            // Check for errors in the API response
            if (isset($result['error'])) 
            {
                return "ChatGPT API Error: " . $result['error']['message'];
            }

            // Check for the expected response
            if (isset($result['choices'][0]['message']['content'])) 
            {
                $chatGPTResponse = $result['choices'][0]['message']['content'];
            }

            return $chatGPTResponse;
        }
        catch (Exception $e) 
        {
            // Log exception error
            Log::error('ChatGPT Exception:', ['exception' => $e->getMessage()]);
            
            // Handle exception error
            return "Chat GPT Limit Reached. ". $e->getMessage();
        }
    }
}