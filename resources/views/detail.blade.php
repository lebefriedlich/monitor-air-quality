<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemantauan Kualitas Udara - {{ $iaqi->region->name }}</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}">
</head>

<body>

    <header class="hero-section-detail">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8 hero-content">
                    <p class="mb-2 fs-5">
                        <img src="{{ asset('images/logo-64.png') }}" alt="Logo" class="me-2"
                            style="width:24px; height:24px;">
                        Pemantauan Kualitas Udara
                    </p>
                    <h1 class="display-4 fw-bold">PEMANTAUAN KUALITAS UDARA
                        {{ mb_strtoupper($iaqi->region->name, 'UTF-8') }}</h1>
                    <p class="lead">Data kualitas udara &copy; BMKG & World Air Quality Index Project.
                        Peramalan kualitas udara 1 hari ke depan dibuat oleh Maulana Haekal Noval Akbar, Universitas
                        Islam Negeri
                        Malang, menggunakan Support Vector Regression (SVR) untuk tujuan akademik/non-profit.</p>

                    <a href="{{ route('index') }}" class="btn btn-back-to-home mt-3 me-2">
                        <i class="bi bi-arrow-left"></i> Kembali ke Halaman Utama
                    </a>
                    <a href="#data-section" class="btn btn-explore-more mt-3">
                        Jelajahi Lebih Lanjut <i class="bi bi-arrow-down"></i>
                    </a>
                </div>
                <div class="col-lg-4">
                    <img src="{{ asset('images/Rectangle 2.png') }}" alt="Monitor Air Quality" class="img-fluid">
                </div>
            </div>
        </div>
    </header>

    <main>
        <div class="detail-wrapper" id="data-section">

            <!-- Kiri: Column berisi card dengan observasi dan peramalan -->
            <div class="detail-left-column">
                <div class="card air-quality-card has-forecast">
                    <!-- Card Header -->
                    <div class="card-header bg-light d-flex align-items-center gap-2">
                        <img src="{{ asset('images/regions/' . $iaqi->region->name . '.png') }}"
                            alt="{{ $iaqi->region->name }} Logo" class="city-logo" style="width: 32px; height: 32px;">
                        <div>
                            <h6 class="mb-0">
                                @if ($iaqi->region->city)
                                    {{ $iaqi->region->city }}
                                @else
                                    {{ $iaqi->region->name }}
                                @endif
                            </h6>
                            <small
                                class="text-muted">{{ \Carbon\Carbon::parse($iaqi->observed_at)->locale('id')->translatedFormat('j F Y H:i') }}</small>
                        </div>
                    </div>

                    <!-- Card Body -->
                    <div class="card-body">
                        <!-- Data Observasi -->
                        <div class="mb-3">
                            <p class="mb-2 text-muted small">DATA OBSERVASI</p>
                            <div class="row">
                                <div class="col-6">
                                    <div>
                                        <small class="text-muted">PM 2.5</small>
                                        <p class="fs-5 fw-bold mb-0">{{ $iaqi->pm25 }} μg/m³</p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div>
                                        <small class="text-muted">ISPU</small>
                                        <p class="fs-5 fw-bold mb-0">{{ number_format($iaqi->aqi_ispu, 0) }}</p>
                                        <small>{{ $iaqi->category_ispu }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-2">

                        <!-- Data Peramalan -->
                        <div>
                            <p class="mb-2 text-muted small">
                                PERAMALAN
                                <span
                                    class="badge bg-info ms-2">{{ \Carbon\Carbon::parse($data['date'])->locale('id')->translatedFormat('j F Y') }}</span>
                            </p>
                            <div class="row">
                                <div class="col-6">
                                    <div>
                                        <small class="text-muted">PM 2.5</small>
                                        <p class="fs-5 fw-bold mb-0">{{ $data['forecast_pm25'] }} μg/m³</p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div>
                                        <small class="text-muted">ISPU</small>
                                        <p class="fs-5 fw-bold mb-0">{{ $data['forecast_ispu'] }}</p>
                                        <small>{{ $data['forecast_category_ispu'] }}</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Model Information -->
                            <div class="mt-3 pt-2 border-top">
                                <p class="mb-2 text-muted small">INFORMASI MODEL</p>
                                <p class="mb-1"><small><strong>Model:</strong>
                                        {{ $data['model_info']['model_type'] }}</small></p>
                                <p class="mb-2"><small><strong>US EPA AQI:</strong> {{ $data['forecast_aqi'] }} —
                                        {{ $data['forecast_category'] }}</small></p>

                                <div class="row mt-2">
                                    <div class="col-6">
                                        <p class="mb-2 text-muted small">CV METRICS (SVR)</p>
                                        <ul class="mb-0 ps-3">
                                            <li><small>R² = {{ number_format($data['cv_metrics_svr']['r2_mean'], 2) }}</small>
                                            </li>
                                            <li><small>MAE =
                                                    {{ number_format($data['cv_metrics_svr']['mae_mean'], 2) }}</small></li>
                                            <li><small>RMSE =
                                                    {{ number_format($data['cv_metrics_svr']['rmse_mean'], 2) }}</small></li>
                                        </ul>
                                    </div>
                                    <div class="col-6">
                                        <p class="mb-2 text-muted small">CV METRICS (BASELINE)</p>
                                        <ul class="mb-0 ps-3">
                                            <li><small>R² =
                                                    {{ number_format($data['cv_metrics_baseline']['r2_mean'], 2) }}</small>
                                            </li>
                                            <li><small>MAE =
                                                    {{ number_format($data['cv_metrics_baseline']['mae_mean'], 2) }}</small>
                                            </li>
                                            <li><small>RMSE =
                                                    {{ number_format($data['cv_metrics_baseline']['rmse_mean'], 2) }}</small>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kanan: Peta -->
            <div class="detail-map">
                <div id="detailMap"></div>
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
        const map = L.map('detailMap', {
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

        // Menggunakan tile layer gelap CartoDB
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 18
        }).addTo(map);

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

        const region = @json($region);
        const iaqi = @json($iaqi);

        const lat = parseFloat(region.latitude);
        const lng = parseFloat(region.longitude);
        const latestNum = parseFloat(iaqi.aqi_ispu);
        const latest = isNaN(latestNum) ? null : latestNum.toFixed(2);

        const popupContent = `
                    <b>${region.name}${region.city ? ', ' + region.city : ''}</b><br>
                    Indeks Kualitas Udara (ISPU): ${String(latest)} - ${iaqi.category_ispu}
                `;

        L.circleMarker([lat, lng], {
                radius: 8,
                fillColor: getAQIColor(iaqi.category_ispu),
                color: '#000',
                weight: 1,
                opacity: 1,
                fillOpacity: 0.8
            })
            .addTo(map)
            .bindPopup(popupContent);

        // Fokus ke koordinat region
        map.setView([lat, lng], 6);

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
