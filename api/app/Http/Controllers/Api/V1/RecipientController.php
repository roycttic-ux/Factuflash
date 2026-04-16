<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Recipient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecipientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $recipients = Recipient::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['recipients' => $recipients]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:20',
            'document_id' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
        ]);

        $validated['user_id'] = $request->user()->id;

        $recipient = Recipient::create($validated);

        return response()->json(['recipient' => $recipient], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $recipient = Recipient::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$recipient) {
            return response()->json(['error' => 'Cliente no encontrado'], 404);
        }

        return response()->json(['recipient' => $recipient]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $recipient = Recipient::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$recipient) {
            return response()->json(['error' => 'Cliente no encontrado'], 404);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:150',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:20',
            'document_id' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
        ]);

        $recipient->update($validated);

        return response()->json(['recipient' => $recipient]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $recipient = Recipient::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$recipient) {
            return response()->json(['error' => 'Cliente no encontrado'], 404);
        }

        $recipient->delete();

        return response()->json(['message' => 'Cliente eliminado']);
    }
}
