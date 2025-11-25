<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemantauan Kualitas Udara</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}">
</head>

<body>
    <header class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8 hero-content">
                    <p class="mb-2 fs-5">
                        <img src="{{ asset('images/logo-64.png') }}" alt="Logo" class="me-2"
                            style="width:24px; height:24px;">
                        Pemantauan Kualitas Udara
                    </p>
                    <h1 class="display-4 fw-bold">PEMANTAUAN KUALITAS UDARA</h1>
                    <p class="lead">Data kualitas udara real-time &copy; BMKG & World Air Quality Index Project.
                        Prediksi kualitas udara 1 hari ke depan dibuat oleh Maulana Haekal Noval Akbar, Universitas
                        Islam Negeri
                        Malang, menggunakan Support Vector Regression (SVR) untuk tujuan akademik/non-profit.</p>

                    <a href="#data-section" class="btn btn-explore-more mt-3">
                        Jelajahi Lebih Lanjut <i class="bi bi-arrow-down"></i>
                    </a>
                </div>
                <div class="col-lg-4">
                </div>
            </div>
        </div>
    </header>

    <main>
        <div class="main-content-wrapper">
            <div class="main-flex-section">

                <div class="map-container">
                    <div id="indonesiaMap"></div>
                </div>

                <div class="data-and-table-wrapper">

                    <div id="data-header-container">
                        <h2 id="data-section" class="text-start">Data Saat Ini dan AQI</h2>
                    </div>

                    <div class="air-quality-table table-responsive">
                        <table class="table align-middle">
                            <thead class="table-header-custom">
                                <tr>
                                    <th>Wilayah</th>
                                    <th>Tanggal</th>
                                    <th>PM 2.5</th>
                                    <th>Indeks Kualitas Udara (ISPU)</th>
                                    <th>Prediksi PM2.5 Besok</th>
                                    <th>Prediksi Indeks Kualitas Udara (ISPU) Besok</th>
                                    <th>Detail</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    // Separate regions with and without data
                                    $availableData = [];
                                    $noData = [];

                                    foreach ($iaqiData as $index => $iaqi) {
                                        $predictedRegions = collect($predictedRegions);

                                        // Now you can safely use firstWhere() on the Collection
                                        $predictedRegion = $predictedRegions->firstWhere(
                                            'region_id',
                                            $iaqi['region']['id'],
                                        );

                                        if ($predictedRegion) {
                                            // Push to available data
                                            $availableData[] = ['iaqi' => $iaqi, 'predictedRegion' => $predictedRegion];
                                        } else {
                                            // Push to no data
                                            $noData[] = ['iaqi' => $iaqi, 'predictedRegion' => null];
                                        }
                                    }

                                    // Combine available data first, then no data at the end
                                    $allData = array_merge($availableData, $noData);
                                @endphp

                                @foreach ($allData as $data)
                                    <tr>
                                        <td>
                                            <span class="d-inline-flex align-items-center">
                                                <img src="{{ asset('images/regions/' . $data['iaqi']['region']['name'] . '.png') }}"
                                                    alt="{{ $data['iaqi']['region']['name'] }} Logo" class="city-logo">
                                                @if ($data['iaqi']['region']['city'])
                                                    {{ $data['iaqi']['region']['city'] }}
                                                @else
                                                    {{ $data['iaqi']['region']['name'] }}
                                                @endif
                                            </span>
                                        </td>
                                        <td>{{ \Carbon\Carbon::parse($data['iaqi']['region']['iaqi']['observed_at'])->locale('id')->translatedFormat('j F Y') }}
                                        </td>
                                        <td>{{ $data['iaqi']['region']['iaqi']['pm25'] }}</td>
                                        <td>{{ number_format($data['iaqi']['region']['iaqi']['aqi_ispu'], 2) }} -
                                            {{ $data['iaqi']['region']['iaqi']['category_ispu'] }}</td>
                                        <td>
                                            @if ($data['predictedRegion'])
                                                {{ $data['predictedRegion']['pm25'] }}
                                            @else
                                                Data tidak tersedia
                                            @endif
                                        </td>
                                        <td>
                                            @if ($data['predictedRegion'])
                                                {{ $data['predictedRegion']['ispu'] }} -
                                                {{ $data['predictedRegion']['cat_ispu'] }}
                                            @else
                                                Data tidak tersedia
                                            @endif
                                        </td>
                                        <td>
                                            @if ($data['predictedRegion'])
                                                <a href="{{ route('region.show', ['region_id' => $data['iaqi']['region']['id']]) }}"
                                                    class="btn btn-sm btn-detail">Lihat Detail</a>
                                            @else
                                                <a href="{{ route('region.show', ['region_id' => $data['iaqi']['region']['id']]) }}"
                                                    class="btn btn-sm btn-secondary-1 disabled-link"
                                                    onclick="event.preventDefault(); return false;">Lihat Detail</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer-custom text-center py-3">
        <p class="mb-0">&copy; 2025 Pemantauan Kualitas Udara. Semua hak dilindungi.</p>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous">
    </script>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        function getAQIColor(aqi) {
            if (aqi == "Baik") return 'green';
            if (aqi == "Sedang") return 'blue';
            if (aqi == "Tidak Sehat") return 'yellow';
            if (aqi == "Sangat Tidak Sehat") return 'red';
            return 'black'; // 201+
        }

        // Inisialisasi Peta Leaflet
        const map = L.map('indonesiaMap', {
            center: [-2.5, 118],
            zoom: 5,
            minZoom: 5,
            dragging: true,
            scrollWheelZoom: false,
            doubleClickZoom: false,
            boxZoom: false,
            keyboard: false,
            touchZoom: true,
            zoomControl: false,
            attributionControl: true
        });

        // Menggunakan tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: 'Peta oleh OpenStreetMap',
            maxZoom: 18
        }).addTo(map);

        const datas = @json($iaqiData);

        datas.forEach((data) => {
            const lat = parseFloat(data.region.latitude);
            const lng = parseFloat(data.region.longitude);
            const latestNum = parseFloat(data.region.iaqi.aqi_ispu);
            const latest = isNaN(latestNum) ? null : latestNum.toFixed(2);

            if (latest && !isNaN(lat) && !isNaN(lng)) {
                const popupContent = `
                    <b>${data.region.name}${data.region.city ? ', ' + data.region.city : ''}</b><br>
                    Indeks Kualitas Udara (ISPU): ${String(latest)} - ${data.region.iaqi.category_ispu}
                `;

                L.circleMarker([lat, lng], {
                        radius: 8,
                        fillColor: getAQIColor(data.region.iaqi.category_ispu),
                        color: '#000',
                        weight: 1,
                        opacity: 1,
                        fillOpacity: 0.8
                    })
                    .addTo(map)
                    .bindPopup(popupContent);
            }
        });

        // Menetapkan batas pandang (bounds)
        const southWest = L.latLng(-11, 90),
            northEast = L.latLng(6, 142);
        const bounds = L.latLngBounds(southWest, northEast);
        map.setMaxBounds(bounds);
        map.fitBounds(bounds);

        // Solusi Mobile: Memicu refresh peta setelah pemuatan atau perubahan ukuran
        function fixMapBounds() {
            if (window.innerWidth < 768) {
                map.setZoom(5.5);
                map.setMinZoom(5.5);
            } else {
                map.setZoom(5);
                map.setMinZoom(5);
            }
            map.fitBounds(bounds);
        }

        map.on('load', fixMapBounds);
        window.addEventListener('resize', fixMapBounds);
        setTimeout(fixMapBounds, 100);

        document.querySelector('.btn-explore-more').addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            const targetElement = document.getElementById(targetId);
            if (targetElement) {
                window.scrollTo({
                    top: targetElement.offsetTop - 20,
                    behavior: 'smooth'
                });
            }
        });
    </script>
</body>

</html>
