<?php

namespace Database\Seeders;

use App\Models\Question;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class QuestionsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $questions = [
            'What is your full name?',
            'What is your email address?',
            'What is your phone number?',
            'Which country are you from?',
            'Which city are you from?',
            'What type of service are you looking for?',
        ];

        foreach ($questions as $title) {
            Question::create(['title' => $title]);
        }
    }
}
