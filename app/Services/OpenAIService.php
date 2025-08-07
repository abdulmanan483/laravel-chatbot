<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    public function ask(string $prompt, array $context = []): array
    {
        $response = Http::withToken(config('services.openai.key'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.openai.model', 'gpt-4-0613'),
                'messages' => array_merge([
                    [
                        'role' => 'system',
                        // 'content' => "You are a service chatbot. Your job is to extract and normalize user data to match exact values from the database. Do fuzzy matching on city, country, and service names (e.g., fsd → Faisalabad, pak → Pakistan, `ac not cooling` → AC Repair). Use only database-approved values. If unsure, return null. Output ONLY using the extract_user_inputs function. Never explain.",
                        // 'content' => "You are a service chatbot. Your job is to extract and normalize user data to match exact values from the database. Do fuzzy matching on city, country, and service names (e.g., fsd → Faisalabad, pak → Pakistan, `ac not cooling` → AC Repair (user specified),Electronics Repairs (generic but strongly related to user input) - (first one is user specificed, second one is generic, for `service` return minimum two services separated by comma from which one should be generic as user input and the others should be user specified separated by comma)) and these inputs are just examples to understand so do not give the same outputs for given examples. Use only database-approved values. Output ONLY using the extract_user_inputs function. Never explain."
                        // 'content' => "You are a service chatbot. Your job is to extract and normalize user data to match exact values from the database. Do fuzzy matching on city and country names (e.g., fsd → Faisalabad, pak → Pakistan) and return their corresponding 3-letter codes (e.g., Faisalabad → FSD, Pakistan → PAK). For services, return a minimum of two entries: one should directly match user intent (user-specified), the other should be a broader or generic category. Use only database-approved values. Output ONLY using the extract_user_inputs function. Never explain."

                        'content' => "You are a service chatbot. Your job is to extract and normalize user data to match exact values from the database. Do fuzzy matching on city, country, and service names (e.g., fsd → Faisalabad, pak → Pakistan) and return their corresponding 3-letter codes too along with the exact names (e.g., Faisalabad → FSD, Pakistan → PAK) . Use only database-approved values.
                         If city or country are vague (e.g., `third largest city of my country`), match them to the closest real names from the list of valid countries and cities and Always translate user inputs and your response parameters and all required data like city, city_code, country_code,country etc into English
                        For the `service` field:
                                        - Always return at least two values in an array.
                                        - The first value should reflect the user's specific intent (e.g., 'bed repair').
                                        - The second should be a broader, related or parent category from the database (e.g., 'wood furniture repair' or 'furniture repair').
                                        - If no appropriate broader category is found, return only the specific service or return null.

                                        Do not repeat example inputs or outputs. Output ONLY using the extract_user_inputs function. Never explain."

                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ], $context),
                'tools' => [
                    [
                        'type' => 'function',
                        'function' => [
                            'name' => 'extract_user_inputs',
                            // 'description' => "You are a service chatbot. Your job is to extract and normalize user data to match exact values from the database. Do fuzzy matching on city, country, and service names (e.g., fsd → Faisalabad, pak → Pakistan, `ac not cooling` → AC Repair). Use only database-approved values. If unsure, return null. Output ONLY using the extract_user_inputs function. Never explain.",
                            // 'description' => 'Extract structured data from user input by matching city, country, and service to the closest valid entries according to natural language. Normalize fuzzy input (e.g., LHR → Lahore, گری → Gree) and return only exact natural language matching values. If no valid match exists, return null for that field.',
                            // 'description' => 'Extract structured data from user input.',
                            'parameters' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'city' => ['type' => 'string'],
                                    'city_code' => ['type' => 'string'],
                                    'country' => ['type' => 'string'],
                                    'country_code' => ['type' => 'string'],
                                    // 'service' => ['type' => 'string'],
                                    "service" => [
                                        "type" => "array",
                                        "items" => ["type" => "string"],
                                        "minItems" => 2
                                    ]
                                ],
                                'required' => ['name', 'city', 'city_code', 'country', 'country_code', 'service'],
                            ],
                        ]
                    ]
                ],
                'tool_choice' => ['type' => 'function', 'function' => ['name' => 'extract_user_inputs']],
                'temperature' => 0.1,
                'max_tokens' => 300,
            ]);

        if ($response->failed()) {
            return ['error' => 'Request failed or unauthorized'];
        }

        $data = $response->json();
        Log::info('OpenAI Response', $data);
        // Fix: Access the tool_call response properly
        $toolCalls = $data['choices'][0]['message']['tool_calls'] ?? [];

        if (!empty($toolCalls)) {
            $arguments = $toolCalls[0]['function']['arguments'] ?? null;
            if ($arguments) {
                return json_decode($arguments, true);
            }
        }

        // 🔁 Fallback: Try parsing content directly (if GPT didn't use tool_call)
        $rawContent = $data['choices'][0]['message']['content'] ?? null;
        if ($rawContent) {
            $parsed = json_decode($rawContent, true);
            if (is_array($parsed)) {
                return $parsed;
            }
        }

        return ['error' => 'No structured data returned'];
    }
}
