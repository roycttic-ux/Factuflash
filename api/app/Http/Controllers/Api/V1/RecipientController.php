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
        $recipients = Recipient::where('user_id', $request->user()->system_owner_id)
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
            'iva_condition_id' => 'nullable|integer|in:1,4,5,6',
            'address' => 'nullable|string|max:255',
            'iibb_alicuota' => 'nullable|numeric|min:0|max:100',
            'iibb_exempt' => 'nullable|boolean',
        ]);

        $this->validateFiscalData($validated);

        $validated['user_id'] = $request->user()->system_owner_id;

        $recipient = Recipient::create($validated);

        return response()->json(['recipient' => $recipient], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $recipient = Recipient::where('id', $id)
            ->where('user_id', $request->user()->system_owner_id)
            ->first();

        if (!$recipient) {
            return response()->json(['error' => 'Cliente no encontrado'], 404);
        }

        return response()->json(['recipient' => $recipient]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $recipient = Recipient::where('id', $id)
            ->where('user_id', $request->user()->system_owner_id)
            ->first();

        if (!$recipient) {
            return response()->json(['error' => 'Cliente no encontrado'], 404);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:150',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:20',
            'document_id' => 'nullable|string|max:50',
            'iva_condition_id' => 'nullable|integer|in:1,4,5,6',
            'address' => 'nullable|string|max:255',
            'iibb_alicuota' => 'nullable|numeric|min:0|max:100',
            'iibb_exempt' => 'nullable|boolean',
        ]);

        $merged = array_merge($recipient->toArray(), $validated);
        $this->validateFiscalData($merged);
        if (isset($merged['document_id'])) {
            $validated['document_id'] = $merged['document_id'];
        }

        $recipient->update($validated);

        return response()->json(['recipient' => $recipient]);
    }

    private function validateFiscalData(array &$data): void
    {
        $condition = (int) ($data['iva_condition_id'] ?? 5);
        $rawDoc = $data['document_id'] ?? '';
        $cleanDoc = preg_replace('/\D/', '', (string) $rawDoc);

        if (in_array($condition, [1, 4, 6], true)) {
            if ($cleanDoc === '') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'document_id' => ['El CUIT es obligatorio para Responsables Inscriptos, Monotributistas y Exentos.'],
                ]);
            }
            if (strlen($cleanDoc) !== 11 || !\App\Services\CuitValidator::isValid($cleanDoc)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'document_id' => ['El CUIT ingresado no es válido (verificá los 11 dígitos y el dígito verificador).'],
                ]);
            }
            $data['document_id'] = \App\Services\CuitValidator::format($cleanDoc);
        } elseif ($cleanDoc !== '') {
            if (strlen($cleanDoc) === 11) {
                if (!\App\Services\CuitValidator::isValid($cleanDoc)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'document_id' => ['El CUIT ingresado no es válido (verificá los 11 dígitos y el dígito verificador).'],
                    ]);
                }
                $data['document_id'] = \App\Services\CuitValidator::format($cleanDoc);
            } elseif (!in_array(strlen($cleanDoc), [7, 8], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'document_id' => ['El documento debe ser un DNI de 7-8 dígitos o un CUIT de 11 dígitos.'],
                ]);
            } else {
                $data['document_id'] = $cleanDoc;
            }
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $recipient = Recipient::where('id', $id)
            ->where('user_id', $request->user()->system_owner_id)
            ->first();

        if (!$recipient) {
            return response()->json(['error' => 'Cliente no encontrado'], 404);
        }

        $recipient->delete();

        return response()->json(['message' => 'Cliente eliminado']);
    }
}
