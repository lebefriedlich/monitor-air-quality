<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AirSense - Monitor Air Quality</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <link rel="stylesheet" href="styles.css">
</head>

<body>

    <header class="hero-section-detail">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8 hero-content">
                    <p class="mb-2 fs-5">
                        <img src="{{ asset('assets/logo-64.png') }}" alt="Logo" class="me-2"
                            style="width:24px; height:24px;">
                        Pemantauan Kualitas Udara
                    </p>
                    <h1 class="display-4 fw-bold">PEMANTAUAN KUALITAS UDARA<br>NAMA KOTA</h1>
                    <p class="lead">Data kualitas udara real-time &copy; BMKG & World Air Quality Index Project.
                        Prediksi kualitas udara 1 hari ke depan dibuat oleh Maulana Haekal Noval Akbar, Universitas
                        Islam Negeri
                        Malang, menggunakan Support Vector Regression (SVR) untuk tujuan akademik/non-profit.</p>

                    <a href="" class="btn btn-back-to-home mt-3 me-2">
                        <i class="bi bi-arrow-left"></i> Kembali ke Halaman Utama
                    </a>
                    <a href="#data-section" class="btn btn-explore-more mt-3">
                        Jelajahi Lebih Lanjut <i class="bi bi-arrow-down"></i>
                    </a>
                </div>
                <div class="col-lg-4">
                    <img src="Rectangle 2.png" alt="Monitor Air Quality" class="img-fluid">
                </div>
            </div>
        </div>
    </header>

    <main>
        <div class="detail-wrapper">

            <!-- Kiri: Column berisi 2 card -->
            <div class="detail-left-column">
                <!-- Card 1 -->
                <div class="detail-left-header card-block">
                    <table class="table detail-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Wilayah</th>
                                <th>PM 2.5</th>
                                <th>Indeks Kualitas Udara (ISPU)</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <span class="detail-area">
                                        <img src="{{ asset('images/' . $iaqi->region->name . '.png') }}"
                                            alt="{{ $iaqi->region->name }} Logo" class="city-logo">
                                        {{ $iaqi->region->name }}
                                    </span>
                                </td>
                                <td>{{ $iaqi->pm25 }} µg/m³</td>
                                <td><strong>{{ $iaqi->aqi_ispu }}</strong></td>
                                <td>{{ $iaqi->observed_at->toDateString() }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Card 2 -->
                <div class="detail-left-body card-block">
                    <div class="detail-body-content">
                        <p><strong>Wilayah:</strong> {{ $data['region_name'] }}</p>
                        <p><strong>Tanggal Prediksi:</strong> {{ $data['date'] }}</p>
                        <p><strong>PM2.5:</strong> {{ $data['pm25'] }} µg/m³</p>
                        <p><strong>Indeks Kualitas Udara (US EPA):</strong> {{ $data['aqi_us_epa'] }} —
                            {{ $data['cat_us_epa'] }}</p>
                        <p><strong>ISPU (RI):</strong> {{ $data['ispu'] }} — {{ $data['cat_ispu'] }}</p>
                        <p><strong>Model:</strong> {{ $data['model_info']['model_type'] }}</p>
                        <p><strong>CV Metrics (SVR):</strong></p>
                        <ul class="mb-0">
                            <li>R² = {{ number_format($data['cv_metrics_svr']['r2_mean'], 2) }}</li>
                            <li>MAE = {{ number_format($data['cv_metrics_svr']['mae_mean'], 2) }}</li>
                            <li>RMSE = {{ number_format($data['cv_metrics_svr']['rmse_mean'], 2) }}</li>
                        </ul>
                        <p><strong>CV Metrics (Baseline):</strong></p>
                        <ul class="mb-0">
                            <li>R² = {{ number_format($data['cv_metrics_baseline']['r2_mean'], 2) }}</li>
                            <li>MAE = {{ number_format($data['cv_metrics_baseline']['mae_mean'], 2) }}</li>
                            <li>RMSE = {{ number_format($data['cv_metrics_baseline']['rmse_mean'], 2) }}</li>
                        </ul>
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
