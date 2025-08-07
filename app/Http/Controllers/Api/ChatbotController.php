<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use Illuminate\Http\Request;
use App\Models\ChatSession;
use App\Models\Question;
use App\Models\ServiceProvider;
use App\Services\OpenAIService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Nnjeim\World\Models\City;
use Nnjeim\World\Models\Country;

class ChatbotController extends Controller
{
    public function startSession(Request $request)
    {
        $session = ChatSession::create([
            'uuid' => Str::uuid(),
            'user_id' => null, // set later if linked
        ]);

        return response()->json([
            'status' => 'success',
            'session_id' => $session->uuid,
            'message' => 'New session started.'
        ]);
    }
    public function getNextQuestion(Request $request)
    {
        $session = ChatSession::where('uuid', $request->session_id)->firstOrFail();

        $answeredQuestionIds = ChatMessage::where('session_id', $session->id)
            ->pluck('question_id')
            ->toArray();

        $nextQuestion = Question::whereNotIn('id', $answeredQuestionIds)->first();
        if (!$nextQuestion) {
            return response()->json([
                'status' => 'complete',
                'message' => 'All questions answered.'
            ]);
        }

        return response()->json([
            'status' => 'success',
            'question_id' => $nextQuestion->id,
            'question' => $nextQuestion->title
        ]);
    }
    public function submitAnswer(Request $request)
    {
        $request->validate([
            'session_id' => 'required|uuid',
            'question_id' => 'required|integer',
            'answer' => 'required|string',
        ]);

        $session = ChatSession::where('uuid', $request->session_id)->firstOrFail();
        ChatMessage::updateOrCreate(
            [
                'session_id' => $session->id,
                'question_id' => $request->question_id
            ],
            [
                'role' => 'user',
                'message' => $request->answer
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Answer saved.'
        ]);
    }
    public function getSessionSummary(Request $request, OpenAIService $openAI)
    {
        $session = $this->getSessionByUuid($request->session_id);
        $answers = $this->getAnswersWithQuestions($session);

        if ($answers->isEmpty()) {
            return $this->errorResponse('No answers found in this session.');
        }

        $prompt = $this->buildPromptFromAnswers($answers);
        $reply = $openAI->ask($prompt);

        if (empty($reply)) {
            return $this->errorResponse('No suitable service found.');
        }
        $city = City::whereRaw('LOWER(name) = ?', [strtolower($reply['city'])])
            ->orWhereRaw('LOWER(name) LIKE ?', ['%' . strtolower($reply['city']) . '%'])
            ->first();

        $country = Country::whereRaw('LOWER(iso3) = ?', [strtolower($reply['country_code'])])
            ->orWhereRaw('LOWER(name) = ?', [strtolower($reply['country'])])
            ->orWhereRaw('LOWER(name) LIKE ?', ['%' . strtolower($reply['country']) . '%'])
            ->first();

        $services = $reply['service'];
        Log::info('Session Summary', [
            'city' => $city,
            'country' => $country,
            'services' => $services,
            'reply' => $reply
        ]);
        if (!$city || !$country) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to identify valid city or country from user input.',
                'city_found' => $city ? true : false,
                'country_found' => $country ? true : false,
            ]);
        }
        $providers = $this->getMatchingServiceProviders($services, $city, $country);
        $formated_providers = $this->formatServiceProviders($providers);
        $message = $this->generateServiceProviderMessage($formated_providers, $city, $country, $services);

        return response()->json([
            'status' => 'success',
            'gpt_response' => $reply,
            'service_prodivders' => $formated_providers,
            'message' => $message,
        ]);
    }
    protected function getSessionByUuid($uuid)
    {
        return ChatSession::where('uuid', $uuid)->firstOrFail();
    }

    protected function getAnswersWithQuestions($session)
    {
        return $session->messages()->with('question')->get();
    }

    protected function buildPromptFromAnswers($answers)
    {
        $prompt = "Here is the user information:\n";
        foreach ($answers as $answer) {
            $prompt .= "{$answer->question->title}: {$answer->message}\n";
        }
        return $prompt;
    }

    protected function errorResponse($message)
    {
        return response()->json([
            'status' => 'error',
            'message' => $message
        ]);
    }

    protected function getMatchingServiceProviders($services, $city, $country)
    {
        return ServiceProvider::with([
            'user',
            'service',
            'service.city',
            'service.country'
        ])
            ->whereHas('service', function ($q) use ($services, $city, $country) {
                $q->where(function ($q2) use ($services) {
                    foreach ($services as $s) {
                        $q2->orWhereRaw('LOWER(name) LIKE ?', ['%' . strtolower($s) . '%']);
                    }
                })
                    ->where('city_id', $city->id)
                    ->where('country_id', $country->id);
            })
            ->get();
    }

    protected function formatServiceProviders($providers)
    {
        return $providers->map(function ($provider) {
            return [
                'provider_id' => $provider->id,
                'user_name' => $provider->user->name ?? null,
                'service' => $provider->service->name ?? null,
                'city' => $provider->service->city->name ?? null,
                'country' => $provider->service->country->name ?? null,
            ];
        });
    }
    protected function generateServiceProviderMessage($formattedProviders, $city = null, $country = null, $services = [])
    {
        if ($formattedProviders->isEmpty()) {
            $cityName = $city?->name ?? 'the specified city';
            $countryName = $country?->name ?? 'the specified country';

            return "Sorry, we couldn't find any service providers matching your requirements in {$cityName}, {$countryName}. Please try again with different options or check back later.";
        }

        $serviceNames = collect($services)->filter()->map(fn($s) => ucfirst($s))->unique()->values();
        $serviceList = $serviceNames->join(', ', ', and ');
        $location = collect([$city?->name, $country?->name])->filter()->join(', ');

        $intro = "Here are the service providers in {$location} for the following service(s): {$serviceList}:\n\n";

        $list = $formattedProviders->values()->map(function ($provider, $index) {
            $num = $index + 1;
            $user = $provider['user_name'] ?? 'N/A';
            $service = $provider['service'] ?? 'N/A';
            $city = $provider['city'] ?? 'N/A';
            $country = $provider['country'] ?? 'N/A';

            return "{$num}. {$user} - {$service} ({$city}, {$country})";
        })->implode("\n");

        return $intro . $list;
    }
    public function restartSession(Request $request)
    {
        $session = ChatSession::where('uuid', $request->session_id)->firstOrFail();

        $session->messages()->delete();
        $session->ended_at = now();
        $session->save();

        $session = ChatSession::create([
            'uuid' => Str::uuid(),
        ]);
        return response()->json([
            'status' => 'success',
            'session_id' => $session->uuid,
            'message' => 'Session restarted.',
        ]);
    }
    public function getSessionDetail(Request $request)
    {
        $sessionId = $request->get('session_id');

        $session = ChatSession::with('messages', 'messages.question')
            ->where('uuid', $sessionId)
            ->first();

        return response()->json([
            'status' => 'success',
            'data' => $session
        ]);
    }
}
