<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AiController extends Controller
{
    private string $apiKey;
    private string $model   = 'gemini-2.0-flash';
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    private string $systemPrompt = 'أنت مساعد محاسبي متخصص مدمج في نظام محاسبة مالي. تحدث باللغة العربية دائماً. ساعد في الإجابة على الأسئلة المحاسبية وشرح القيود وتفسير التقارير المالية. كن دقيقاً ومختصراً ومهنياً.';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key', '');
    }

    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'messages'        => ['required', 'array', 'min:1'],
            'messages.*.role' => ['required', 'in:user,model'],
            'messages.*.text' => ['required', 'string', 'max:4000'],
        ]);

        $contents = collect($request->messages)->map(fn($m) => [
            'role'  => $m['role'],
            'parts' => [['text' => $m['text']]],
        ])->values()->toArray();

        $response = Http::timeout(30)->post(
            "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}",
            [
                'systemInstruction' => ['parts' => [['text' => $this->systemPrompt]]],
                'contents'          => $contents,
                'generationConfig'  => ['temperature' => 0.4, 'maxOutputTokens' => 1024],
            ]
        );

        if ($response->failed()) {
            return response()->json(['error' => 'خدمة الذكاء الاصطناعي غير متاحة حالياً'], 503);
        }

        $text = $response->json('candidates.0.content.parts.0.text') ?? '';

        return response()->json(['reply' => trim($text)]);
    }

    public function suggestDescription(Request $request): JsonResponse
    {
        $request->validate([
            'debit_account'  => ['nullable', 'string', 'max:200'],
            'credit_account' => ['nullable', 'string', 'max:200'],
            'amount'         => ['nullable', 'numeric'],
        ]);

        $debit  = $request->debit_account  ?? '—';
        $credit = $request->credit_account ?? '—';
        $amount = $request->amount ? number_format((float) $request->amount, 2) : '—';

        $prompt = "اقترح وصفاً محاسبياً مختصراً (جملة واحدة فقط بالعربية، بدون علامات اقتباس) لقيد يومية:\n- مدين: {$debit}\n- دائن: {$credit}\n- المبلغ: {$amount}";

        $response = Http::timeout(15)->post(
            "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}",
            [
                'contents'         => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 100],
            ]
        );

        if ($response->failed()) {
            return response()->json(['error' => 'خدمة الذكاء الاصطناعي غير متاحة حالياً'], 503);
        }

        $text = $response->json('candidates.0.content.parts.0.text') ?? '';

        return response()->json(['suggestion' => trim($text)]);
    }

    public function analyzeReport(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'string', 'max:100'],
            'data' => ['required', 'array'],
        ]);

        $dataJson = json_encode($request->data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $prompt = "أنت محلل مالي متخصص. حلل بيانات تقرير ({$request->type}) التالية وقدم:\n1. أبرز الملاحظات\n2. نقاط القوة والضعف\n3. توصيات قصيرة\n\nالبيانات:\n{$dataJson}";

        $response = Http::timeout(30)->post(
            "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}",
            [
                'systemInstruction' => ['parts' => [['text' => $this->systemPrompt]]],
                'contents'          => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                'generationConfig'  => ['temperature' => 0.4, 'maxOutputTokens' => 1500],
            ]
        );

        if ($response->failed()) {
            return response()->json(['error' => 'خدمة الذكاء الاصطناعي غير متاحة حالياً'], 503);
        }

        $text = $response->json('candidates.0.content.parts.0.text') ?? '';

        return response()->json(['analysis' => trim($text)]);
    }
}
