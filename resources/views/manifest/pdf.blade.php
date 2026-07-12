<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Manifest {{ $tanggal }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        h2 { text-align: center; margin-bottom: 4px; }
        p.sub { text-align: center; margin-top: 0; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #333; padding: 6px 8px; text-align: left; }
        th { background: #1E6091; color: #fff; }
    </style>
</head>
<body>
    <h2>Manifest Kunjungan Wisata Bahari Sungai Pisang</h2>
    <p class="sub">Tanggal: {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('d F Y') }}</p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Wisatawan</th>
                <th>Pulau Tujuan</th>
                <th>Jenis</th>
                <th>Akomodasi</th>
                <th>Jumlah Orang</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reservasi as $i => $r)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $r->wisatawan->name }}</td>
                    <td>{{ $r->pulau->nama }}</td>
                    <td>{{ $r->jenis === 'one_day_trip' ? 'One Day Trip' : 'Menginap' }}</td>
                    <td>{{ $r->akomodasi->nama ?? '-' }}</td>
                    <td>{{ $r->jumlah_orang }}</td>
                    <td>{{ ucfirst($r->status) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align:center;">Tidak ada kunjungan pada tanggal ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p style="margin-top: 20px;">Total wisatawan: <strong>{{ $reservasi->sum('jumlah_orang') }}</strong> orang</p>
</body>
</html>
