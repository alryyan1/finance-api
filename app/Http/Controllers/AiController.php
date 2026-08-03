<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AiController extends Controller
{
    private string $apiKey;

    private string $model = 'gpt-4o-mini';

    private string $baseUrl = 'https://api.openai.com/v1/chat/completions';

    private string $systemPrompt = 'أنت مساعد محاسبي متخصص مدمج في نظام محاسبة مالي. تحدث باللغة العربية دائماً. ساعد في الإجابة على الأسئلة المحاسبية وشرح القيود وتفسير التقارير المالية. كن دقيقاً ومختصراً ومهنياً.';

    public function __construct()
    {
        $this->apiKey = config('services.openai.key', '');
    }

    private function complete(array $messages, float $temperature, int $maxTokens): ?string
    {
        $response = Http::withToken($this->apiKey)->timeout(30)->post($this->baseUrl, [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ]);

        if ($response->failed()) {
            return null;
        }

        return trim($response->json('choices.0.message.content') ?? '');
    }

    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'messages' => ['required', 'array', 'min:1'],
            'messages.*.role' => ['required', 'in:user,model'],
            'messages.*.text' => ['required', 'string', 'max:4000'],
        ]);

        $messages = collect($request->messages)->map(fn ($m) => [
            'role' => $m['role'] === 'model' ? 'assistant' : 'user',
            'content' => $m['text'],
        ])->values()->toArray();

        array_unshift($messages, ['role' => 'system', 'content' => $this->systemPrompt]);

        $text = $this->complete($messages, 0.4, 1024);

        if ($text === null) {
            return response()->json(['error' => 'خدمة الذكاء الاصطناعي غير متاحة حالياً'], 503);
        }

        return response()->json(['reply' => $text]);
    }

    public function suggestDescription(Request $request): JsonResponse
    {
        $request->validate([
            'debit_account' => ['nullable', 'string', 'max:200'],
            'credit_account' => ['nullable', 'string', 'max:200'],
            'amount' => ['nullable', 'numeric'],
        ]);

        $debit = $request->debit_account ?? '—';
        $credit = $request->credit_account ?? '—';
        $amount = $request->amount ? number_format((float) $request->amount, 2) : '—';

        $prompt = "اقترح وصفاً محاسبياً مختصراً (جملة واحدة فقط بالعربية، بدون علامات اقتباس) لقيد يومية:\n- مدين: {$debit}\n- دائن: {$credit}\n- المبلغ: {$amount}";

        $text = $this->complete([['role' => 'user', 'content' => $prompt]], 0.3, 100);

        if ($text === null) {
            return response()->json(['error' => 'خدمة الذكاء الاصطناعي غير متاحة حالياً'], 503);
        }

        return response()->json(['suggestion' => $text]);
    }

    public function analyzeReport(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'string', 'max:100'],
            'data' => ['required', 'array'],
        ]);

        $dataJson = json_encode($request->data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $prompt = "أنت محلل مالي متخصص. حلل بيانات تقرير ({$request->type}) التالية وقدم:\n1. أبرز الملاحظات\n2. نقاط القوة والضعف\n3. توصيات قصيرة\n\nالبيانات:\n{$dataJson}";

        $text = $this->complete([
            ['role' => 'system', 'content' => $this->systemPrompt],
            ['role' => 'user', 'content' => $prompt],
        ], 0.4, 1500);

        if ($text === null) {
            return response()->json(['error' => 'خدمة الذكاء الاصطناعي غير متاحة حالياً'], 503);
        }

        return response()->json(['analysis' => $text]);
    }
}
