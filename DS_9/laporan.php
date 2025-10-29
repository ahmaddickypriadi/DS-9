<?php
require_once 'config/database.php';
require_once 'config/session.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$tanggal_awal = $_GET['tanggal_awal'] ?? date('Y-m-01');
$tanggal_akhir = $_GET['tanggal_akhir'] ?? date('Y-m-d');

// Build query with filters
$where_conditions = ["DATE(t.tanggal_transaksi) BETWEEN ? AND ?"];
$params = [$tanggal_awal, $tanggal_akhir];

// Get laporan data
$query = "SELECT t.*, u.nama_lengkap FROM transaksi t 
          JOIN users u ON t.user_id = u.id 
          WHERE " . implode(' AND ', $where_conditions) . "
          ORDER BY t.tanggal_transaksi DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$laporan_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate summary
$total_transaksi = count($laporan_data);
$total_penjualan = array_sum(array_column($laporan_data, 'total_harga'));

// Get daily sales data for chart
$query = "SELECT DATE(tanggal_transaksi) as tanggal, 
                 COUNT(*) as jumlah_transaksi,
                 COALESCE(SUM(total_harga), 0) as total_penjualan
          FROM transaksi 
          WHERE DATE(tanggal_transaksi) BETWEEN ? AND ?
          GROUP BY DATE(tanggal_transaksi)
          ORDER BY tanggal ASC";
$stmt = $db->prepare($query);
$stmt->execute([$tanggal_awal, $tanggal_akhir]);
$chart_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get top selling products
$query = "SELECT b.nama_barang, b.kode_barang, 
                 SUM(dt.jumlah) as total_terjual,
                 SUM(dt.subtotal) as total_penjualan
          FROM detail_transaksi dt
          JOIN barang b ON dt.barang_id = b.id
          JOIN transaksi t ON dt.transaksi_id = t.id
          WHERE DATE(t.tanggal_transaksi) BETWEEN ? AND ?
          GROUP BY b.id, b.nama_barang, b.kode_barang
          ORDER BY total_terjual DESC
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute([$tanggal_awal, $tanggal_akhir]);
$top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan - Sistem CRUD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            .container { max-width: none !important; }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4 no-print">
                    <h2>
                        <i class="bi bi-graph-up me-2"></i>
                        Laporan Penjualan
                    </h2>
                    <div>
                        <button onclick="window.print()" class="btn btn-outline-primary me-2">
                            <i class="bi bi-printer me-1"></i>
                            Cetak
                        </button>
                        <button onclick="exportToExcel()" class="btn btn-outline-success">
                            <i class="bi bi-file-earmark-excel me-1"></i>
                            Export Excel
                        </button>
                    </div>
                </div>
                
                <!-- Filter Form -->
                <div class="card mb-4 no-print">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="tanggal_awal" class="form-label">Tanggal Awal</label>
                                <input type="date" class="form-control" id="tanggal_awal" name="tanggal_awal" 
                                       value="<?php echo $tanggal_awal; ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="tanggal_akhir" class="form-label">Tanggal Akhir</label>
                                <input type="date" class="form-control" id="tanggal_akhir" name="tanggal_akhir" 
                                       value="<?php echo $tanggal_akhir; ?>" required>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="bi bi-search me-1"></i>
                                    Filter
                                </button>
                                <a href="laporan.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise me-1"></i>
                                    Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Total Transaksi</h6>
                                        <h3><?php echo $total_transaksi; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-receipt" style="font-size: 2rem;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Total Penjualan</h6>
                                        <h3><?php echo formatRupiah($total_penjualan); ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-currency-dollar" style="font-size: 2rem;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title">Rata-rata per Transaksi</h6>
                                        <h3><?php echo $total_transaksi > 0 ? formatRupiah($total_penjualan / $total_transaksi) : 'Rp 0'; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-graph-up" style="font-size: 2rem;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Chart -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Grafik Penjualan Harian</h5>
                            </div>
                            <div class="card-body">
                                <div style="position: relative; height: 400px;">
                                    <canvas id="salesChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Top Products -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Produk Terlaris</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Produk</th>
                                                <th>Terjual</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($top_products as $product): ?>
                                                <tr>
                                                    <td>
                                                        <small class="text-muted"><?php echo htmlspecialchars($product['kode_barang']); ?></small><br>
                                                        <?php echo htmlspecialchars($product['nama_barang']); ?>
                                                    </td>
                                                    <td><?php echo $product['total_terjual']; ?></td>
                                                    <td><?php echo formatRupiah($product['total_penjualan']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Detail Transaksi</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Kode</th>
                                                <th>Tanggal</th>
                                                <th>Total</th>
                                                <th>Kasir</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($laporan_data as $transaksi): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($transaksi['kode_transaksi']); ?></td>
                                                    <td><?php echo date('d/m/Y', strtotime($transaksi['tanggal_transaksi'])); ?></td>
                                                    <td><?php echo formatRupiah($transaksi['total_harga']); ?></td>
                                                    <td><?php echo htmlspecialchars($transaksi['nama_lengkap']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Chart data
        const chartData = <?php echo json_encode($chart_data); ?>;
        
        // Initialize chart when page loads
        window.addEventListener('load', function() {
            if (typeof Chart === 'undefined') {
                console.error('Chart.js is not loaded');
                return;
            }
            
            const canvas = document.getElementById('salesChart');
            if (!canvas) {
                console.error('Canvas element not found');
                return;
            }
            
            const ctx = canvas.getContext('2d');
            
            // Check if chart data exists and is not empty
            if (chartData && chartData.length > 0) {
                // Sort chart data by date to ensure proper order
                chartData.sort((a, b) => new Date(a.tanggal) - new Date(b.tanggal));
                
                // Prepare chart data
                const labels = chartData.map(item => {
                    const date = new Date(item.tanggal);
                    return date.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit' });
                });
                
                const salesData = chartData.map(item => {
                    const value = parseFloat(item.total_penjualan);
                    return isNaN(value) ? 0 : value;
                });
                
                const transactionData = chartData.map(item => {
                    const value = parseInt(item.jumlah_transaksi);
                    return isNaN(value) ? 0 : value;
                });
                
                // Create chart
                try {
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Penjualan (Rp)',
                                data: salesData,
                                borderColor: 'rgb(75, 192, 192)',
                                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                tension: 0.1
                            }, {
                                label: 'Jumlah Transaksi',
                                data: transactionData,
                                borderColor: 'rgb(255, 99, 132)',
                                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                                tension: 0.1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                mode: 'index',
                                intersect: false,
                            },
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top'
                                }
                            },
                            scales: {
                                x: {
                                    display: true,
                                    title: {
                                        display: true,
                                        text: 'Tanggal'
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    display: true,
                                    title: {
                                        display: true,
                                        text: 'Penjualan (Rp)'
                                    },
                                    ticks: {
                                        callback: function(value) {
                                            return 'Rp ' + value.toLocaleString('id-ID');
                                        }
                                    }
                                }
                            }
                        }
                    });
                } catch (error) {
                    console.error('Error creating chart:', error);
                    ctx.font = '16px Arial';
                    ctx.fillStyle = '#ff0000';
                    ctx.textAlign = 'center';
                    ctx.fillText('Error loading chart', canvas.width / 2, canvas.height / 2);
                }
            } else {
                // Show message when no data
                ctx.font = '16px Arial';
                ctx.fillStyle = '#666';
                ctx.textAlign = 'center';
                ctx.fillText('Tidak ada data untuk ditampilkan', canvas.width / 2, canvas.height / 2);
            }
        });
        
        // Export to Excel function
        function exportToExcel() {
            const table = document.createElement('table');
            const thead = document.createElement('thead');
            const tbody = document.createElement('tbody');
            
            // Add headers
            const headerRow = document.createElement('tr');
            ['Kode Transaksi', 'Tanggal', 'Total Harga', 'Jumlah Bayar', 'Kembalian', 'Kasir'].forEach(text => {
                const th = document.createElement('th');
                th.textContent = text;
                headerRow.appendChild(th);
            });
            thead.appendChild(headerRow);
            table.appendChild(thead);
            
            // Add data rows
            const transaksiData = <?php echo json_encode($laporan_data); ?>;
            transaksiData.forEach(function(transaksi) {
                const dataRow = document.createElement('tr');
                [transaksi.kode_transaksi, 
                 new Date(transaksi.tanggal_transaksi).toLocaleDateString('id-ID'), 
                 transaksi.total_harga, 
                 transaksi.jumlah_bayar, 
                 transaksi.kembalian, 
                 transaksi.nama_lengkap].forEach(text => {
                    const td = document.createElement('td');
                    td.textContent = text;
                    dataRow.appendChild(td);
                });
                tbody.appendChild(dataRow);
            });
            
            table.appendChild(tbody);
            
            // Convert to Excel
            const wb = XLSX.utils.table_to_book(table);
            XLSX.writeFile(wb, 'laporan_penjualan_<?php echo $tanggal_awal; ?>_<?php echo $tanggal_akhir; ?>.xlsx');
        }
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
</body>
</html>
