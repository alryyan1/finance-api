<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;

class WhatsAppController extends Controller
{
    /** GET /api/whatsapp/phone-number — the configured Business sending number, for display only. */
    public function phoneNumber(WhatsAppService $whatsapp): JsonResponse
    {
        return response()->json($whatsapp->getBusinessPhoneNumber());
    }
}
