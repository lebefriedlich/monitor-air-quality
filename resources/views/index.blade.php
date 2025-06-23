<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Monitor Kualitas Udara Indonesia</title>

    {{-- Bootstrap CSS CDN --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    {{-- Leaflet --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

    <style>
        #map {
            height: 500px;
            margin-bottom: 30px;
            border-radius: 10px;
            overflow: hidden;
        }
    </style>
</head>

<body class="bg-light">
    <div class="container my-4">
        <h2 class="text-center mb-4">Peta Kualitas Udara Indonesia</h2>

        {{-- Leaflet Map --}}
        <div id="map" class="mb-5"></div>

        {{-- AQI Cards --}}
        <h3 class="text-center mb-3">Data data dan AQI Saat Ini</h3>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            @foreach ($datas as $data)
                <div class="col">
                    @php
                        $dominantPol = $data->latestIaqi->dominent_pol ?? null;
                        $aqiValue = $dominantPol ? $data->latestIaqi->{$dominantPol} ?? null : null;
                        $color = match (true) {
                            $aqiValue <= 50 => 'green',
                            $aqiValue <= 100 => 'blue',
                            $aqiValue <= 150 => 'yellow',
                            $aqiValue <= 200 => 'red',
                            default => 'black',
                        };
                    @endphp
                    <div class="card shadow-sm" style="border-left: 8px solid {{ $color }}">
                        <div class="card-body">
                            <h5 class="card-title">{{ $data->name }}</h5>
                            @php
                                $dominantPol = $data->latestIaqi->dominent_pol ?? null;
                                $aqiValue = $dominantPol ? $data->latestIaqi->{$dominantPol} ?? 'N/A' : 'N/A';
                            @endphp
                            <p class="card-text mb-1">Dominan: <strong>{{ strtoupper($dominantPol ?? 'N/A') }}</strong>
                            </p>
                            <p class="card-text fs-4">AQI: <span class="text-primary">{{ $aqiValue }}</span></p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Bootstrap JS (opsional, untuk komponen interaktif) --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    {{-- Leaflet JS --}}
    <script>
        function getAQIColor(aqi) {
            if (aqi <= 50) return 'green';
            if (aqi <= 100) return 'blue';
            if (aqi <= 150) return 'yellow';
            if (aqi <= 200) return 'red';
            return 'black'; // 201+
        }

        const datas = @json($datas);

        const map = L.map('map', {
            center: [-2.5, 118],
            zoom: 5,
            dragging: false,
            zoomControl: false,
            scrollWheelZoom: false,
            doubleClickZoom: false,
            boxZoom: false,
            keyboard: false,
            tap: false,
            touchZoom: false
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: 'Peta oleh OpenStreetMap',
            maxZoom: 18
        }).addTo(map);

        datas.forEach((data) => {
            const lat = parseFloat(data.latitude);
            const lng = parseFloat(data.longitude);
            const latest = data.latest_iaqi;

            if (latest && !isNaN(lat) && !isNaN(lng)) {
                const pol = latest.dominent_pol;
                const value = latest[pol] ?? 'N/A';

                const popupContent = `
                    <b>${data.name}</b><br>
                    Dominan: ${pol?.toUpperCase()}<br>
                    AQI: ${value}<br>
                    Lat: ${lat}, Lng: ${lng}
                `;

                L.circleMarker([lat, lng], {
                        radius: 8,
                        fillColor: getAQIColor(value),
                        color: '#000',
                        weight: 1,
                        opacity: 1,
                        fillOpacity: 0.8
                    })
                    .addTo(map)
                    .bindPopup(popupContent);
            }
        });
    </script>
</body>

</html>
