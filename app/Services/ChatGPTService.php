<?php

namespace App\Services;

class ChatGPTService
{
    public function convertToHtml($text)
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
                return "<p></p><strong>$number. $title:</strong> $description</p>";
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
}