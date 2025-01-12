<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ChatGPT Conversation</title>
    <link rel="icon" href="assets/images/favicon.png"/>
    <link href="assets/css/style.css" rel="stylesheet" type="text/css"/>
</head>
<body>
    <div id="app">
        <div class="header">
          <div class="header-content">
            <img src="assets/images/logo.png"/>
            <span class="header-text">ChatGPT Conversation</span>
          </div>
        </div>
        <div class="chat-list" id="chat-list" ref="list">
          <div class="chat-item">
            Start chatting with ChatGPT AI
          </div>
          @foreach ($groupedConversations as $group => $conversations)
            <div class="chat-item date-group">
              <span class="group-title">{{ $group }}</span>
            </div>
            
            @foreach ($conversations as $conversation)
              <div class="chat-item bubble user">
                <p>{{ $conversation->message }}</p>
                <span class="timestamp-right">{{ $conversation->created_at->format('g:i A') }}</span>
              </div>
              <div class="chat-item bubble">
                <p>{!! $conversation->response !!}</p>
                <span class="timestamp-left">{{ $conversation->created_at->format('g:i A') }}</span>
              </div>
            @endforeach
          @endforeach
        </div>
        <form id="chat-form">
          <div class="form">
            <div class="form-clean" id="chat-clear">
              <button type="button" class="btn">
                <svg class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg"><path d="M796.8 828.8a48.96 48.96 0 1 0 48.96 48.96 48.96 48.96 0 0 0-48.96-48.96z m146.56-113.92A48.96 48.96 0 1 0 992 763.52a48.96 48.96 0 0 0-48.96-48.64zM928 861.44a48.96 48.96 0 1 0 48.96 48.96A48.96 48.96 0 0 0 928 861.44z m-5.44-585.6L992 205.12 850.88 64l-70.72 70.72a66.56 66.56 0 0 0-94.08 0l235.2 235.2a66.56 66.56 0 0 0 0-94.08z m-853.12 128a32 32 0 0 0-32 50.24 1291.2 1291.2 0 0 0 75.2 112L288 551.68c19.84 0 24.64 21.44 8 36.8l-93.44 85.76a1281.6 1281.6 0 0 0 120 114.24l100.48-32c18.88-5.76 27.52 15.04 14.4 33.6l-39.68 55.36c25.92 18.56 53.44 36.16 82.24 53.12a89.28 89.28 0 0 0 114.56-20.48 1391.04 1391.04 0 0 0 256-485.44l-187.84-187.52s-305.6 224-594.56 198.4z"></path></svg>
              </button>
            </div>
            <div class="form-text">
              <input type="text" name="message" class="text" id="message" placeholder="Type your message">
              <button type="submit" class="btn btn-primary" id="form_submit">
                <svg class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg"><path d="M760.768 576L384 931.84 469.76 1024 1024 515.392 474.048 0 384 98.88 753.6 448H0v128h760.768z"></path></svg>
              </button>
            </div>
          </div>
        </form>
      </div>
      <!-- JavaScript -->
      <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>
      <script>
        $(document).ready(function()
        {
          // Scroll to bottom of the chatbox
          $('#chat-list').scrollTop($('#chat-list')[0].scrollHeight);

          // Focus message inout
          $("form #message").focus();
        });

        // Form submit for store conversation
        $('form').on('submit', function (event) 
        {
          event.preventDefault();

          const message = $("form #message").val();

          // Format the current timestamp (e.g: 02:15 PM)
          const formattedTime = new Date().toLocaleTimeString([], 
          {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
          });

          // Stop empty messages
          if (message.trim() === '') 
          {
            alert('Type your message');
            return;
          }

          // Cleanup
          $("form #message").val('');

          // Disable form
          $("form #message").prop('disabled', true);
          $("form #form_submit").prop('disabled', true);

          // Sending message
          $(".chat-list > .chat-item").last().after('<div class="chat-item bubble user">' +
          '<p>' + message + '</p>' +
          '<span class="timestamp-right">' + formattedTime + '</span>' +
          '</div>');

          // Loading response
          $(".chat-list > .chat-item").last().after('<div class="chat-item bubble">' +
          '<p>Loading...</p>' +
          '</div>');

          // Scroll to bottom of the chatbox
          $('#chat-list').scrollTop($('#chat-list')[0].scrollHeight);

          // Send AJAX POST request
          $.ajax({
            url: "/chat",
            method: 'POST',
            headers: 
            {
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: 
            {
              "message": message
            },
            success : function (response)
            {
              // Receiving message
              $(".chat-list > .chat-item").last().remove();
              $(".chat-list > .chat-item").last().after('<div class="chat-item bubble">' +
              '<p>' + response + '</p>' +
              '<span class="timestamp-left">' + formattedTime + '</span>' +
              '</div>');

              // Enable form
              $("form #message").prop('disabled', false);
              $("form #form_submit").prop('disabled', false);

              // Focus message input
              $("form #message").focus();

              // Get the last request element (your sent message)
              let $lastRequest = $('#chat-list').children('.chat-item.bubble.user').last();

              // Calculate the position of the last request relative to the chat list
              let lastRequestTop = $lastRequest.position().top;

              // Set the scroll position so that the last request is at the top
              $('#chat-list').scrollTop($('#chat-list').scrollTop() + lastRequestTop);
            },
            error: function(xhr, error) 
            {
              // Log the error message correctly
              console.log('Error:', error);
              console.log('Response Text:', xhr.responseText);

              // Remove Loading
              $(".chat-list > .chat-item").last().remove();

              // Enable form
              $("form #message").prop('disabled', false);
              $("form #form_submit").prop('disabled', false);
            }
          });
        });

        // For delete all stored conversation
        $('#chat-clear').on('click', function () 
        {
          // Check if there are any conversations to delete
          if ($(".chat-list .bubble").length === 0) 
          {
            // alert('No conversations available to delete.');
            return;
          }

          // Confirm deletion action
          if (confirm('Are you sure you want to delete all conversations? This action cannot be undone.')) 
          {
            // Send AJAX DELETE request
            $.ajax({
              url: '/conversation',
              type: 'DELETE',
              headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') // Include CSRF token
              },
              success: function (data) 
              {
                if(data.statusCode === 200)
                {
                  $(".chat-list .bubble").remove();
                }
              },
              error: function (xhr, error) 
              {
                  // Log the error message correctly
                  console.log('Error:', error);
                  console.log('Response Text:', xhr.responseText);
              }
            });
          }
        });
      </script>
</body>
</html>