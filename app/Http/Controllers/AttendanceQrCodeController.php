<?php

namespace App\Http\Controllers;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\Response;

class AttendanceQrCodeController extends Controller
{
    public function show(): View
    {
        return view('attendance.qr');
    }

    public function image(): Response
    {
        return $this->svgResponse();
    }

    public function download(): Response
    {
        return $this->svgResponse([
            'Content-Disposition' => 'attachment; filename="qr-asistencia.svg"',
        ]);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function svgResponse(array $headers = []): Response
    {
        $renderer = new ImageRenderer(
            new RendererStyle(320),
            new SvgImageBackEnd
        );

        $svg = (new Writer($renderer))->writeString(route('attendance.scan'));

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ...$headers,
        ]);
    }
}
