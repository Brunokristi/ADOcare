<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\GeocodeService;

class GeocodeController extends Controller
{
    public function __construct(protected GeocodeService $service)
    {

    }

    /**
     * Autocomplete place predictions for an input text.
     *
     * @group Geocoding
     * @queryParam text string Partial address to complete. Example: "Hlavná 1, Bratislava"
     * @response 200 {
     *  "predictions": [
     *      {"description":"Hlavná 1, Bratislava, Slovakia","place_id":"ChIJ..."}
     *  ]
     * }
     *
     * Returns the JSON from the Google Places Autocomplete API.
     */
    public function autocomplete(Request $request)
    {

        $text = trim((string) $request->query('text'));
        $res = $this->service->autocomplete($text);
        return $this->success($res['body'], 'Autocomplete predictions retrieved', $res['status']);
    }
    /**
     * Fetch place details for a given place_id.
     *
     * @group Geocoding
     * @queryParam place_id string required Google place_id to fetch details for. Example: "ChIJ..."
     * @response 200 {
     *  "result": {"formatted_address":"Hlavná 1, Bratislava","geometry":{...}}
     * }
     * @response 400 {"error":"Missing place_id"}
     *
     * Returns the JSON from the Google Place Details API.
     */
    public function details(Request $request)
    {

        $placeId = trim((string) $request->query('place_id'));
        $res = $this->service->details($placeId);
        return $this->success($res['body'], 'Place details retrieved', $res['status']);
    }

    /**
     * Reverse-geocode a latitude/longitude pair to an address.
     *
     * @group Geocoding
     * @queryParam lat number required Latitude. Example: 48.1486
     * @queryParam lon number required Longitude. Example: 17.1077
     * @response 200 {"address":"Hlavná 1, Bratislava","city":"Bratislava","street":"Hlavná 1","zip":"81101","place_id":"ChIJ...","components":{}}
     * @response 400 {"error":"Missing lat or lon"}
     *
     * Returns an object with address, city, street, zip, place_id and raw components.
     */
    public function reverse(Request $request)
    {

        $lat = $request->query('lat');
        $lon = $request->query('lon');
        $res = $this->service->reverse((float) $lat, (float) $lon);
        return $this->success($res['body'], 'Reverse geocoding successful', $res['status']);
    }
}
