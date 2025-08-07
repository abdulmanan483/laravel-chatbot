<!-- resources/views/chatbot.blade.php -->
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbot</title>
    <script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="bg-gray-100 min-h-screen flex flex-col">
    <div class="container mx-auto p-4 max-w-2xl flex-1">
        <div class="bg-white shadow-lg rounded-lg p-6">
            <h2 class="text-2xl font-bold mb-4">🤖 Chatbot Assistant</h2>

            <div id="chatbox" class="space-y-4 max-h-[60vh] overflow-y-auto mb-4 border p-4 rounded-md bg-gray-50">
                <!-- Messages will appear here -->
            </div>

            <form id="chat-form" class="flex gap-2">
                <input type="text" id="user-input"
                    class="flex-1 border border-gray-300 rounded px-4 py-2 focus:outline-none"
                    placeholder="Type your answer..." />
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Send</button>
            </form>

            <div class="mt-6 flex justify-between">
                <button id="restart-session" class="text-sm text-red-500 hover:underline">🔄 Restart Session</button>
                {{-- <button id="get-summary" class="text-sm text-green-600 hover:underline">📋 Get Summary</button> --}}
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script>
        let sessionId = null;
        let currentQuestionId = null;

        const addMessage = (message, type = 'bot') => {
            const bubble = `<div class="${type === 'bot' ? 'text-left' : 'text-right'}">
                <div class="inline-block px-4 py-2 rounded-lg ${type === 'bot' ? 'bg-gray-300 text-black' : 'bg-blue-600 text-white'}">
                    ${message}
                </div>
            </div>`;
            $('#chatbox').append(bubble).scrollTop($('#chatbox')[0].scrollHeight);
        }

        const getNextQuestion = () => {
            $.get(`/api/v1/chatbot/next-question?session_id=${sessionId}`, function(response) {
                if (response.status === 'success') {
                    currentQuestionId = response.question_id; // store the ID
                    addMessage(response.question, 'bot');
                } else if (response.status === 'complete') {
                    getSummary();

                } else {}
            });
        }

        const startSession = () => {
            $.post('/api/v1/chatbot/start-session', function(response) {
                if (response.status === 'success') {
                    sessionId = response.session_id;
                    localStorage.setItem('chatbot_session_id', sessionId);
                    getNextQuestion();
                }
            });
        }

        const submitAnswer = (message) => {
            $.post('/api/v1/chatbot/answer', {
                session_id: sessionId,
                question_id: currentQuestionId,
                answer: message
            }, function(response) {
                if (response.status === 'success') {
                    getNextQuestion();
                } else if (response.status === 'complete') {
                    getSummary();
                } else {
                    addMessage("Error submitting answer.", 'bot');
                }
            });
        }

        const getSummary = () => {
            $.get(`/api/v1/chatbot/session-summary?session_id=${sessionId}`, function(response) {
                if (response.status === 'success') {
                    const summary = response.gpt_response.summary || JSON.stringify(response.gpt_response);
                    const message = response.message;
                    localStorage.removeItem('chatbot_session_id'); // Clear session ID
                    sessionId = null; // Reset session ID
                    // addMessage("Summary: " + summary, 'bot');
                    addMessage(message, 'bot');
                } else {
                    addMessage(response.message, 'bot');
                    // if(response.status === 'error') {
                    //     restartSession();
                    // }
                }
                $('#chat-form').hide();
            });
        }

        const restartSession = () => {
            $.post('/api/v1/chatbot/restart-session', {
                session_id: sessionId
            }, function(response) {
                if (response.status === 'success') {
                    $('#chatbox').html('');
                    sessionId = response.session_id;
                    localStorage.setItem('chatbot_session_id', sessionId);
                    getNextQuestion();
                    $('#chat-form').show();
                }
            });
        }
        const fetchMessages = (sessionId) => {
            $.get('/api/v1/chatbot/session-detail', {
                session_id: sessionId
            }, function(response) {
                if (response.status === 'success' && response.data && response.data.messages) {
                    $('#chat-body').empty(); // Clear old messages

                    response.data.messages.forEach(msg => {
                        const roleClass = msg.role === 'user' ? 'bg-blue-100 text-right' :
                            'bg-gray-100 text-left';
                        const messageHtml = `
                        <div class="p-2 ${roleClass} rounded my-1 max-w-[75%] mx-auto">
                            <p class="text-sm">${msg.message}</p>
                            <span class="text-xs text-gray-400">${msg.created_at}</span>
                        </div>
                    `;
                        if (msg.question)
                            addMessage(msg.question.title, 'bot');
                        addMessage(msg.message, msg.role);
                        // $('#chat-body').append(messageHtml);
                    });

                    // Scroll to bottom
                    $('#chat-body').scrollTop($('#chat-body')[0].scrollHeight);
                } else {
                    localStorage.removeItem('chatbot_session_id'); // Clear session ID if no messages
                    sessionId = null; // Reset session ID
                    startSession();
                }
            }).fail(() => {
                alert('Error fetching messages.');
            });
        };

        // Init
        $(document).ready(() => {
            sessionId = localStorage.getItem('chatbot_session_id');
            console.log('Session ID:', sessionId);
            if (sessionId == 'undefined' || sessionId == null) {
                startSession();
            } else {
                fetchMessages(sessionId);
                getNextQuestion();
            }

            $('#chat-form').submit(function(e) {
                e.preventDefault();
                const message = $('#user-input').val();
                if (!message.trim()) return;
                addMessage(message, 'user');
                $('#user-input').val('');
                submitAnswer(message);
            });

            $('#restart-session').click(restartSession);
            $('#get-summary').click(getSummary);
        });
    </script>
</body>

</html>
