<?php

namespace App\Http\Controllers;

use App\Services\LiveMapData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class MapLiveController extends Controller
{
    public function __construct(private LiveMapData $live) {}

    /**
     * Espacios abiertos reales (OpenStreetMap) para orientar la evacuación.
     */
    public function openSpaces(): JsonResponse
    {
        // La primera consulta a OpenStreetMap puede tardar más de 30 s; luego queda en caché.
        set_time_limit(120);

        return response()->json(['data' => $this->live->openSpaces()])
            ->header('Cache-Control', 'private, max-age=3600');
    }

    /**
     * Calles y pasajes coloreados según la aglomeración estimada a su alrededor.
     */
    public function crowdStreets(): JsonResponse
    {
        // La primera descarga de calles de una zona puede tardar más de 30 s; luego queda en caché.
        set_time_limit(150);

        return response()->json(['data' => $this->live->crowdStreets()]);
    }

    /**
     * Mosaico de tráfico en tiempo real. La clave del proveedor nunca llega
     * al navegador: el servidor descarga el mosaico y lo reenvía.
     */
    public function traffic(int $zoom, int $x, int $y): Response
    {
        $limit = 2 ** $zoom;
        abort_if($zoom < 3 || $zoom > 18 || $x < 0 || $y < 0 || $x >= $limit || $y >= $limit, 404);

        $tile = $this->live->trafficTile($zoom, $x, $y);
        abort_if($tile === null, 404);

        return response($tile, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=90',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
