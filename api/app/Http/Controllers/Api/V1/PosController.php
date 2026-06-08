<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PosDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $devices = PosDevice::where('user_id', $request->user()->system_owner_id)->get();
        return response()->json(['devices' => $devices]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:mercadopago_point,traditional_pos',
            'external_id' => 'required|string|unique:pos_devices,external_id',
        ]);

        $device = PosDevice::create([
            'user_id' => $request->user()->system_owner_id,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'external_id' => $validated['external_id'],
        ]);

        return response()->json(['device' => $device], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $device = PosDevice::where('id', $id)
            ->where('user_id', $request->user()->system_owner_id)
            ->first();

        if (!$device) {
            return response()->json(['error' => 'Dispositivo no encontrado'], 404);
        }

        $device->delete();
        return response()->json(['message' => 'Dispositivo eliminado']);
    }

    public function confirmPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'external_id' => 'required|string',
            'amount' => 'required|numeric',
            'transaction_id' => 'nullable|string',
        ]);

        $device = PosDevice::where('external_id', $validated['external_id'])->first();
        if (!$device) {
            return response()->json(['error' => 'Dispositivo no reconocido'], 404);
        }

        // Buscamos la última factura pendiente de pago para este comercio que coincida con el monto (aprox)
        // O mejor: el Bridge podría enviarnos el invoice_id si lo capturó.
        
        // Guardamos la confirmación en caché o DB para que el frontend la vea (Polling)
        \Illuminate\Support\Facades\Cache::put("pos_confirm_{$device->external_id}", [
            'status' => 'approved',
            'amount' => $validated['amount'],
            'transaction_id' => $validated['transaction_id'] ?? 'TRX-' . time(),
            'timestamp' => now()
        ], 60); // Expira en 1 minuto

        return response()->json(['message' => 'Pago confirmado y registrado en el sistema']);
    }

    public function checkStatus(Request $request, string $externalId): JsonResponse
    {
        $status = \Illuminate\Support\Facades\Cache::get("pos_confirm_{$externalId}");
        if ($status) {
            // No borramos de inmediato por si el frontend falla en la primera lectura, 
            // pero el cache tiene poco tiempo de vida.
            return response()->json($status);
        }
        return response()->json(['status' => 'pending']);
    }

    public function downloadAgent(Request $request, string $platform)
    {
        $apiToken = $request->query('token');
        if (!$apiToken) {
            return response()->json(['error' => 'Se requiere el token de vinculación para descargar.'], 401);
        }

        $owner = \App\Models\User::where('api_token', $apiToken)->first();
        if (!$owner) {
            return response()->json(['error' => 'Token de vinculación inválido.'], 401);
        }

        if ($platform !== 'macos' && $platform !== 'windows') {
            return response()->json(['error' => 'Plataforma no válida.'], 400);
        }

        // Crear la URL base de la API dinámicamente según cómo estén accediendo
        $apiUrl = url('/api/v1');

        // Contenido de la configuración pre-vinculada
        $configContent = json_encode([
            'api_url' => $apiUrl,
            'api_token' => $apiToken,
            'notify_afip' => true,
            'listen_port' => 9100,
            'log_file' => 'factuflash_agent.log'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // Rutas de archivos fuente
        $agentPath = base_path('../factuflash_agent/factuflash_agent.py');
        $readmePath = base_path('../factuflash_agent/LEEME_macOS.txt');
        $launcherPath = base_path('../factuflash_agent/FactuFlash_Agent_Mac.command');

        // Si ZipArchive no está habilitado en este servidor PHP, hacemos un fallback directo
        if (!class_exists('ZipArchive')) {
            if (file_exists($agentPath)) {
                return response()->download($agentPath, 'factuflash_agent.py', [
                    'Content-Type' => 'text/x-python',
                ]);
            }
            return response()->json(['error' => 'ZipArchive no está habilitado y el script original no se encontró.'], 500);
        }

        // Si es macOS, creamos un ZIP interactivo
        if ($platform === 'macos') {
            $tempZip = tempnam(sys_get_temp_dir(), 'agent_') . '.zip';
            $zip = new \ZipArchive();
            if ($zip->open($tempZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                // Agregar archivos
                if (file_exists($agentPath)) {
                    $zip->addFile($agentPath, 'factuflash_agent.py');
                }
                if (file_exists($readmePath)) {
                    $zip->addFile($readmePath, 'LEEME_macOS.txt');
                }
                if (file_exists($launcherPath)) {
                    $zip->addFile($launcherPath, 'FactuFlash_Agent_Mac.command');
                }
                // Agregar config.json personalizado
                $zip->addFromString('config.json', $configContent);
                $zip->close();
            }

            return response()->download($tempZip, 'Agente_FactuFlash_Mac.zip')->deleteFileAfterSend(true);
        }

        // Si es Windows, podemos enviarle un ZIP con el ejecutable o el script configurado
        $tempZip = tempnam(sys_get_temp_dir(), 'agent_') . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($tempZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            if (file_exists($agentPath)) {
                $zip->addFile($agentPath, 'factuflash_agent.py');
            }
            $zip->addFromString('config.json', $configContent);
            
            // Launcher batch para Windows
            $batContent = "@echo off\r\n";
            $batContent .= "title FactuFlash POS Bridge\r\n";
            $batContent .= "echo Iniciando FactuFlash POS Bridge...\r\n";
            $batContent .= "python --version >nul 2>&1\r\n";
            $batContent .= "if %errorlevel% neq 0 (\r\n";
            $batContent .= "    echo ERROR: Python no esta instalado. Descargalo de python.org\r\n";
            $batContent .= "    pause\r\n";
            $batContent .= "    exit /b\r\n";
            $batContent .= ")\r\n";
            $batContent .= "pip install requests pystray pillow --quiet\r\n";
            $batContent .= "python factuflash_agent.py\r\n";
            $batContent .= "pause\r\n";
            
            $zip->addFromString('FactuFlash_Agent_Windows.bat', $batContent);
            
            $readmeWin = "==============================================================\r\n";
            $readmeWin .= "         FactuFlash POS Bridge - Guia para Windows\r\n";
            $readmeWin .= "==============================================================\r\n\r\n";
            $readmeWin .= "El agente ya viene pre-configurado con tu token y tu servidor!\r\n";
            $readmeWin .= "Solo tenes que:\r\n";
            $readmeWin .= "1. Descomprimir el ZIP.\r\n";
            $readmeWin .= "2. Hacer doble clic en 'FactuFlash_Agent_Windows.bat'.\r\n";
            $zip->addFromString('LEEME_Windows.txt', $readmeWin);
            
            $zip->close();
        }

        return response()->download($tempZip, 'Agente_FactuFlash_Windows.zip')->deleteFileAfterSend(true);
    }
}
