<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiSupportService
{
    public function reply(ChatConversation $conversation): ChatMessage
    {
        $history = $conversation->messages()
            ->latest('id')
            ->limit(12)
            ->get()
            ->reverse()
            ->values();

        $contents = $this->buildContents($history);
        $knowledgeDocuments = $this->loadKnowledgeDocuments();
        $combinedKnowledge = $this->buildCombinedKnowledge($knowledgeDocuments);

        $replyText = '';
        $usedLocalFallback = false;

        try {
            $response = Http::timeout(30)
                ->acceptJson()
                ->withHeaders([
                    'x-goog-api-key' => config('services.gemini.key'),
                    'Content-Type' => 'application/json',
                ])
                ->post(
                    'https://generativelanguage.googleapis.com/v1beta/models/' .
                    config('services.gemini.model', 'gemini-2.5-flash') .
                    ':generateContent',
                    [
                        'contents' => $contents,
                        'system_instruction' => [
                            'parts' => [
                                [
                                    'text' => $this->buildSystemPrompt($combinedKnowledge),
                                ],
                            ],
                        ],
                        'generationConfig' => [
                            'temperature' => 0.5,
                            'maxOutputTokens' => 500,
                        ],
                    ]
                )
                ->throw()
                ->json();

            $replyText = $this->extractText($response);
        } catch (\Throwable $e) {
            Log::error('Gemini reply failed', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversation->id,
            ]);

            $replyText = $this->buildLocalFallbackReply($conversation, $knowledgeDocuments);
            $usedLocalFallback = true;
        }

        $replyText = $this->postProcessReply($replyText, $conversation, $knowledgeDocuments);

        if (!$replyText) {
            $replyText = $this->buildHumanHandoffReply(
                $this->getLatestCustomerMessage($conversation),
                false
            );
        }

        return $conversation->messages()->create([
            'sender_type' => 'bot',
            'sender_id' => null,
            'message' => $replyText,
            'meta' => [
                'provider' => 'gemini',
                'model' => config('services.gemini.model', 'gemini-2.5-flash'),
                'used_local_fallback' => $usedLocalFallback,
            ],
        ]);
    }

    protected function buildContents($history): array
    {
        $contents = [];

        foreach ($history as $item) {
            $role = in_array($item->sender_type, ['agent', 'bot'], true)
                ? 'model'
                : 'user';

            $contents[] = [
                'role' => $role,
                'parts' => [
                    [
                        'text' => $item->message,
                    ],
                ],
            ];
        }

        if (empty($contents)) {
            $contents[] = [
                'role' => 'user',
                'parts' => [
                    [
                        'text' => 'Hello',
                    ],
                ],
            ];
        }

        return $contents;
    }

    protected function loadKnowledgeDocuments(): array
    {
        $directories = [
            base_path('app/ai'),
            storage_path('app/ai'),
        ];

        $documents = [];

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $files = glob($directory . DIRECTORY_SEPARATOR . '*.md');

            if (!$files) {
                continue;
            }

            foreach ($files as $file) {
                $content = @file_get_contents($file);

                if ($content === false || trim($content) === '') {
                    continue;
                }

                $documents[] = [
                    'name' => basename($file),
                    'title' => $this->makeTitleFromFilename(basename($file)),
                    'content' => trim($content),
                    'path' => $file,
                ];
            }
        }

        usort($documents, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $documents;
    }

    protected function makeTitleFromFilename(string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $name = str_replace(['_', '-'], ' ', $name);

        return ucwords($name);
    }

    protected function buildCombinedKnowledge(array $documents): string
    {
        if (empty($documents)) {
            return 'No local business knowledge files were found.';
        }

        $parts = [];

        foreach ($documents as $document) {
            $parts[] = "# " . $document['title'];
            $parts[] = $document['content'];
        }

        return implode("\n\n", $parts);
    }

    protected function buildSystemPrompt(string $combinedKnowledge): string
    {
        return implode("\n\n", [
            'You are a friendly website customer support assistant for AsyncAfrica.',
            'Use the official business knowledge below for all company-specific information.',
            'For general questions that are not asking for official company facts, you may answer helpfully using broad general knowledge.',
            'Never present general knowledge as an official company promise, policy, or confirmed fact.',
            'Never say "I do not know", "I don\'t know", or "I am not sure" as a dead-end reply.',
            'If company-specific information is missing, unclear, sensitive, or needs confirmation, politely recommend a human support team member instead of giving a dead-end answer.',
            'If only part of the answer is available, share the confirmed part first, then recommend a human for the remaining details.',
            'Do not invent company prices, contacts, policies, deadlines, payment instructions, schedules, certificates, locations, or services.',
            'Always escalate to a human for payment disputes, refund requests, registration issues, legal concerns, complaints, account-specific requests, personal status checks, or any request that needs staff confirmation.',
            'Keep answers short, clear, warm, and practical. Usually 2 to 5 sentences.',
            'If the message is only a greeting, reply briefly and invite the person to ask about training, internship, software development, networking, registration, or payment.',
            'OFFICIAL BUSINESS KNOWLEDGE:',
            $combinedKnowledge,
        ]);
    }

    protected function buildLocalFallbackReply(ChatConversation $conversation, array $documents): string
    {
        $latestCustomerMessage = $this->getLatestCustomerMessage($conversation);

        if ($this->isGreetingMessage($latestCustomerMessage)) {
            return 'Hello! Welcome to AsyncAfrica. You can ask about our training, internship, software development, networking, registration, or payment.';
        }

        if (empty($documents)) {
            return $this->buildHumanHandoffReply($latestCustomerMessage, true);
        }

        $bestMatch = $this->findBestMatchingDocumentSection($latestCustomerMessage, $documents);

        if ($bestMatch && $bestMatch['score'] > 0) {
            $intro = "Here is what I found";

            if (!empty($bestMatch['section_title']) && $bestMatch['section_title'] !== $bestMatch['document_title']) {
                $intro .= " about {$bestMatch['section_title']}";
            }

            return $intro . ":\n\n" .
                $bestMatch['excerpt'] .
                "\n\nFor anything more specific, our human support team can assist you directly.";
        }

        if ($this->looksLikeBusinessQuestion($latestCustomerMessage, $documents)) {
            return $this->buildHumanHandoffReply($latestCustomerMessage, true);
        }

        return 'Thanks for your question. I can usually help with general guidance, but the AI assistant is temporarily unavailable right now. Please try again shortly, or our human support team can assist you directly.';
    }

    protected function findBestMatchingDocumentSection(string $message, array $documents): ?array
    {
        $keywords = $this->extractKeywords($message);

        if (empty($documents) || empty($keywords)) {
            return null;
        }

        $bestMatch = null;
        $bestScore = 0;

        foreach ($documents as $document) {
            $sections = $this->parseMarkdownSections($document['content']);

            if (empty($sections)) {
                $sections = [
                    [
                        'title' => $document['title'],
                        'content' => $document['content'],
                    ],
                ];
            }

            foreach ($sections as $section) {
                $haystack = mb_strtolower(
                    $document['title'] . ' ' .
                    $section['title'] . ' ' .
                    $section['content']
                );

                $score = 0;

                foreach ($keywords as $keyword) {
                    if (str_contains($haystack, $keyword)) {
                        $score++;
                    }
                }

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestMatch = [
                        'document_title' => $document['title'],
                        'section_title' => $section['title'],
                        'excerpt' => $this->makeExcerpt($section['content'], 500),
                        'score' => $score,
                    ];
                }
            }
        }

        return $bestScore > 0 ? $bestMatch : null;
    }

    protected function parseMarkdownSections(string $markdown): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $markdown);
        $sections = [];
        $currentTitle = 'General Information';
        $currentContent = [];

        foreach ($lines as $line) {
            if (preg_match('/^\s{0,3}#{1,6}\s+(.*)$/', $line, $matches)) {
                if (!empty($currentContent)) {
                    $sections[] = [
                        'title' => trim($currentTitle),
                        'content' => trim(implode("\n", $currentContent)),
                    ];
                }

                $currentTitle = trim($matches[1]);
                $currentContent = [];
            } else {
                $currentContent[] = $line;
            }
        }

        if (!empty($currentContent)) {
            $sections[] = [
                'title' => trim($currentTitle),
                'content' => trim(implode("\n", $currentContent)),
            ];
        }

        return array_values(array_filter($sections, function ($section) {
            return trim($section['content']) !== '';
        }));
    }

    protected function extractKeywords(string $text): array
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $parts = preg_split('/\s+/', trim((string) $text));

        $stopWords = [
            'the', 'and', 'for', 'with', 'that', 'this', 'from', 'have', 'your',
            'about', 'what', 'when', 'where', 'which', 'will', 'would', 'there',
            'their', 'them', 'then', 'than', 'into', 'want', 'need', 'please',
            'help', 'hello', 'hi', 'can', 'you', 'are', 'our', 'how', 'why',
            'who', 'was', 'were', 'has', 'had', 'get', 'got', 'may', 'able',
            'is', 'to', 'of', 'in', 'on', 'at', 'a', 'an', 'it', 'we', 'i',
            'me', 'my', 'do', 'does', 'did', 'am', 'be', 'or', 'if', 'by',
        ];

        $keywords = [];

        foreach ($parts as $part) {
            if (mb_strlen($part) < 3) {
                continue;
            }

            if (in_array($part, $stopWords, true)) {
                continue;
            }

            $keywords[] = $part;
        }

        return array_values(array_unique($keywords));
    }

    protected function makeExcerpt(string $text, int $limit = 500): string
    {
        $text = trim(preg_replace('/\n{3,}/', "\n\n", $text));

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $limit - 3)) . '...';
    }

    protected function extractText(array $response): string
    {
        $parts = data_get($response, 'candidates.0.content.parts', []);

        if (!is_array($parts)) {
            return '';
        }

        $texts = [];

        foreach ($parts as $part) {
            if (!empty($part['text']) && is_string($part['text'])) {
                $texts[] = trim($part['text']);
            }
        }

        return trim(implode("\n", array_filter($texts)));
    }

    protected function getLatestCustomerMessage(ChatConversation $conversation): string
    {
        return (string) optional(
            $conversation->messages()
                ->where('sender_type', 'customer')
                ->latest('id')
                ->first()
        )->message;
    }

    protected function postProcessReply(string $replyText, ChatConversation $conversation, array $documents): string
    {
        $replyText = trim($replyText);

        if ($replyText === '') {
            return '';
        }

        $replyText = preg_replace('/\n{3,}/', "\n\n", $replyText);

        $latestCustomerMessage = $this->getLatestCustomerMessage($conversation);

        $forbiddenPhrases = [
            "i don't know",
            'i do not know',
            "i am not sure",
            "i'm not sure",
            'no information available',
            'i have no information',
        ];

        foreach ($forbiddenPhrases as $phrase) {
            if (stripos($replyText, $phrase) !== false) {
                if ($this->looksLikeBusinessQuestion($latestCustomerMessage, $documents)) {
                    return $this->buildHumanHandoffReply($latestCustomerMessage, false);
                }

                return 'Thanks for your question. Please share a little more detail, and I will try to help clearly. If you need official business confirmation, our human support team can assist you directly.';
            }
        }

        return $replyText;
    }

    protected function isGreetingMessage(string $message): bool
    {
        $message = trim(mb_strtolower($message));

        if ($message === '') {
            return false;
        }

        $message = preg_replace('/[^\p{L}\p{N}\s]/u', '', $message);

        $greetings = [
            'hello',
            'hi',
            'hey',
            'good morning',
            'good afternoon',
            'good evening',
            'morning',
            'afternoon',
            'evening',
        ];

        return in_array($message, $greetings, true);
    }

    protected function looksLikeBusinessQuestion(string $message, array $documents = []): bool
    {
        $message = mb_strtolower($message);

        $businessTerms = [
            'asyncafrica',
            'training',
            'trainings',
            'internship',
            'internaship',
            'software development',
            'network',
            'networking',
            'internet technology',
            'register',
            'registration',
            'apply',
            'application',
            'certificate',
            'certificates',
            'fee',
            'fees',
            'price',
            'cost',
            'payment',
            'pay',
            'mtn',
            'mobile money',
            'course',
            'courses',
            'program',
            'programs',
            'services',
            'service',
            'robotics',
            'ai',
            'iot',
            'consulting',
            'support',
            'deadline',
        ];

        foreach ($businessTerms as $term) {
            if (str_contains($message, $term)) {
                return true;
            }
        }

        if (preg_match('/\b(your|you offer|do you|can i register|how much|where can i pay)\b/u', $message)) {
            return true;
        }

        $keywords = $this->extractKeywords($message);

        if (empty($keywords) || empty($documents)) {
            return false;
        }

        $matches = 0;

        foreach ($documents as $document) {
            $haystack = mb_strtolower($document['title'] . ' ' . $document['content']);

            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    $matches++;
                }
            }
        }

        return $matches >= 2;
    }

    protected function buildHumanHandoffReply(string $message, bool $temporaryAiIssue = false): string
    {
        $message = mb_strtolower($message);

        if (
            str_contains($message, 'payment') ||
            str_contains($message, 'pay') ||
            str_contains($message, 'mtn') ||
            str_contains($message, 'mobile money') ||
            str_contains($message, 'register') ||
            str_contains($message, 'registration')
        ) {
            return $temporaryAiIssue
                ? 'Thanks for your message. For payment or registration help, our human support team can assist you directly right now. Please share your full name, program of interest, and payment details for assistance.'
                : 'Thanks for your message. For payment or registration confirmation, our human support team can assist you directly. Please share your full name, program of interest, and payment details for assistance.';
        }

        if (
            str_contains($message, 'refund') ||
            str_contains($message, 'complaint') ||
            str_contains($message, 'problem') ||
            str_contains($message, 'issue') ||
            str_contains($message, 'account') ||
            str_contains($message, 'legal')
        ) {
            return $temporaryAiIssue
                ? 'Thanks for your message. This request needs human assistance right now. Please share the full details with our support team so they can help you directly.'
                : 'Thanks for your message. This request needs confirmation from our human support team. Please share the full details so they can assist you directly.';
        }

        return $temporaryAiIssue
            ? 'Thanks for your question. Our human support team can assist you directly with this request right now.'
            : 'Thanks for your question. A human support team member can assist you directly with the exact details for this request.';
    }
}