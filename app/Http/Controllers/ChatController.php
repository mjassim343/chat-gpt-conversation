<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;
use App\Services\ChatGPTService;

class ChatController extends Controller
{
    protected $chatGPTService;

    public function __construct(ChatGPTService $chatGPTService)
    {
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
                $conversation->response = $this->convertToHtml($conversation->response);
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

        // Fetch previous messages from the database
        $previousConversations = Conversation::orderBy('created_at', 'asc')->get();

        // Format the conversation in array
        $messages = $previousConversations->map(function ($conversation) 
        {
            return [
                ['role' => 'user', 'content' => $conversation->message],
                ['role' => 'assistant', 'content' => $conversation->response]
            ];
        })->flatten(1)->toArray();

        // Add new user message to the conversation
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        // Get the response from chatGPT
        $chatGPTResponse = $this->chatGPTService->sendMessage($messages);

         // Save to the database
         Conversation::create([
            'conversation_id' => uniqid(),
            'message' => $userMessage,
            'response' => $chatGPTResponse,
        ]);

        return $this->convertToHtml($chatGPTResponse);
    }

    /**
     * Convert response text into HTML
     */
    private function convertToHtml($text)
    {
        // Escape special HTML characters to prevent XSS attacks
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        // Match and format numbered items into an HTML list
        $text = preg_replace_callback(
            '/(\d+)\.\s(.*?):(.*?)(\n|$)/',
            function ($matches) {
                $number = $matches[1];   
                $title = $matches[2];
                $description = trim($matches[3]);
                return "<p><strong>$number. $title:</strong> $description</p>";
            },
            $text
        );

        // Convert bold text (Markdown style: **text**)
        $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);

        // Convert headers (Markdown style: ### text)
        $text = preg_replace('/^###\s(.*)$/m', '<h3>$1</h3>', $text);

        // Preserve numbered sections without wrapping in list tags (e.g., 1. Item)
        $text = preg_replace('/(\d+\.)\s(.*?)(?=\n\d+\.\s|$)/', '<p>$1 $2</p>', $text);

        // Convert unordered list items (e.g., - Item)
        $text = preg_replace_callback('/(?:^|\n)-\s(.*?)(?=\n-|$)/s', function ($matches) {
            return "<li>{$matches[1]}</li>";
        }, $text);

        // Wrap unordered list items in <ul> tags
        $text = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $text);

        // Remove extra newlines
        $text = preg_replace('/\n+/', '', $text);

        return $text;
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
