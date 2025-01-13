<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\Services\ChatGPTService;
use Exception;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    protected $apiUrl;
    protected $model;
    protected $stream;
    protected $temperature;
    protected $token;
    protected $chatGPTService;

    public function __construct(ChatGPTService $chatGPTService)
    {
        $this->apiUrl = 'https://api.openai.com/v1/chat/completions';
        $this->model = 'gpt-4o-mini';
        $this->stream = true;
        $this->temperature = 0.7;
        $this->token = 2048;
        $this->chatGPTService = $chatGPTService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $conversations = Conversation::all();

        // Format the date as "today", "yesterday", or the specific date
        $groupedConversations = $conversations->groupBy(function ($conversation)
        {
            $createdAt = \Carbon\Carbon::parse($conversation->created_at);

            if($createdAt->isToday())
            {
                return 'Today';
            } 
            elseif($createdAt->isYesterday()) 
            {
                return 'Yesterday';
            }
            else 
            {
                return $createdAt->format('M d, Y');
            }
        });

        // Convert each conversation's response to HTML
        $groupedConversations = $groupedConversations->map(function ($conversations) 
        {
            return $conversations->transform(function ($conversation) 
            {
                $conversation->response = $this->chatGPTService->convertToHtml($conversation->response);
                return $conversation;
            });
        });

        return view('chat.index', compact('groupedConversations'));
    }

    /**
     * Handle the chat request
     */
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required',
        ]);

        $userMessage = $request->input('message');

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
                'messages' => [['role' => 'user', 'content' => $userMessage]],
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

            // Save to the database
            Conversation::create([
                'conversation_id' => uniqid(),
                'message' => $userMessage,
                'response' => $chatGPTResponse,
            ]);

            return $this->chatGPTService->convertToHtml($chatGPTResponse);
        }
        catch (Exception $e) 
        {
            // Log exception error
            Log::error('ChatGPT Exception:', ['exception' => $e->getMessage()]);
            
            // Handle exception error
            return "Chat GPT Limit Reached. ". $e->getMessage();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy()
    {
        try 
        {
            Conversation::truncate();
            return response()->json(['message' => 'All conversations deleted successfully.', 'statusCode' => 200], 200);
        }
        catch (\Exception $e) 
        {
            return response()->json(['message' => 'Failed to delete conversations.', 'statusCode' => 500], 500);
        }
    }
}
