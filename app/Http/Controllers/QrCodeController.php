<?php

namespace App\Http\Controllers;

use App\Chime;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QrCodeController extends Controller
{
    /**
     * Generate and return a QR code for a chime's join URL
     */
    public function show(Request $request, Chime $chime)
    {
        // Validate user has access to this chime
        $user = Auth::user();
        if (!$user->isPresenter($chime->id) && !$user->global_admin) {
            // Allow participants to generate QR codes too
            if (!$user->chimes()->where('chime_id', $chime->id)->exists() && !$user->guest_user) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        // Get size from query parameter (default 300)
        $size = (int) $request->query('size', 300);
        $size = max(100, min(500, $size)); // Clamp between 100-500

        // Get format from query parameter (default png)
        $format = $request->query('format', 'png');
        $format = in_array($format, ['png', 'svg']) ? $format : 'png';

        // Generate the join URL
        $joinUrl = sprintf(
            '%s/join/%s',
            config('app.url'),
            $chime->access_code
        );

        try {
            if ($format === 'svg') {
                $qrCode = $this->generateSvgQrCode($joinUrl, $size);
                return response($qrCode, 200)
                    ->header('Content-Type', 'image/svg+xml')
                    ->header('Cache-Control', 'public, max-age=3600');
            } else {
                $qrCode = $this->generatePngQrCode($joinUrl, $size);
                return response($qrCode, 200)
                    ->header('Content-Type', 'image/png')
                    ->header('Cache-Control', 'public, max-age=3600');
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate QR code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate PNG QR code
     */
    private function generatePngQrCode(string $data, int $size): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 0),
            new \BaconQrCode\Renderer\Image\ImagickImageBackEnd()
        );

        $writer = new Writer($renderer);
        $qrCode = $writer->writeString($data);

        return $qrCode;
    }

    /**
     * Generate SVG QR code
     */
    private function generateSvgQrCode(string $data, int $size): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 0),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);
        $qrCode = $writer->writeString($data);

        return $qrCode;
    }
}
