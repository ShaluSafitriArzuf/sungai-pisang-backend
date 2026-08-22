@php
    // Nama bulan ditulis manual, tidak memakai translatedFormat(), karena hasilnya bergantung
    // pada locale aplikasi -- sebelumnya tanggal tercetak "13 August 2026" (Inggris) walaupun
    // seluruh isi dokumen berbahasa Indonesia. Cara ini hasilnya selalu pasti.
    $BULAN = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $tgl = \Carbon\Carbon::parse($tanggal);
    $tanggalTeks = $tgl->day.' '.$BULAN[(int) $tgl->month].' '.$tgl->year;

    $now = \Carbon\Carbon::now();
    $cetakTeks = $now->day.' '.$BULAN[(int) $now->month].' '.$now->year.', '.$now->format('H:i');

    $totalOrang = $reservasi->sum('jumlah_orang');
    $totalRombongan = $reservasi->count();
    $totalUnit = $reservasi->whereNotNull('akomodasi_id')->sum('jumlah_unit_dipesan');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Manifest Kunjungan {{ $tanggalTeks }}</title>
    <style>
        @page { margin: 28px 32px 46px 32px; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            color: #1f2933;
            margin: 0;
        }

        /* ---------- Kop ---------- */
        .kop { border-bottom: 2px solid #1E6091; padding-bottom: 10px; margin-bottom: 14px; }
        .kop h1 {
            font-size: 15px;
            margin: 0 0 3px 0;
            text-align: center;
            color: #14456b;
            letter-spacing: .2px;
        }
        .kop .sub { font-size: 10px; text-align: center; margin: 0; color: #5b6b7a; }

        /* ---------- Ringkasan ---------- */
        .ringkasan { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin-bottom: 14px; }
        .ringkasan td {
            width: 33.33%;
            background: #f2f6fa;
            border: 1px solid #d7e3ee;
            padding: 7px 10px;
        }
        .ringkasan .label { font-size: 9px; color: #5b6b7a; text-transform: uppercase; letter-spacing: .4px; }
        .ringkasan .angka { font-size: 14px; font-weight: bold; color: #14456b; }
        .ringkasan .satuan { font-size: 9.5px; font-weight: normal; color: #5b6b7a; }

        /* ---------- Tabel utama ---------- */
        table.data { width: 100%; border-collapse: collapse; }
        table.data thead { display: table-header-group; }
        table.data th {
            background: #1E6091;
            color: #fff;
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: .3px;
            padding: 7px 6px;
            border: 1px solid #1a5480;
            text-align: left;
        }
        table.data td {
            padding: 6px;
            border: 1px solid #d7dee5;
            vertical-align: top;
        }
        table.data tr.genap td { background: #f7fafc; }

        .c { text-align: center; }
        .nama { font-weight: bold; }
        .kecil { font-size: 9px; color: #6b7a89; }
        .kosong { text-align: center; padding: 22px 6px; color: #6b7a89; font-style: italic; }

        .status-valid { color: #1e7a34; font-weight: bold; }
        .status-selesai { color: #1E6091; font-weight: bold; }

        /* ---------- Kaki halaman ---------- */
        .kaki {
            position: fixed;
            bottom: -30px; left: 0; right: 0;
            font-size: 8.5px;
            color: #8a97a3;
            border-top: 1px solid #e2e8ee;
            padding-top: 5px;
        }
        /* Memakai tabel, bukan float, karena dukungan float pada DomPDF terbatas. */
        .kaki table { width: 100%; border-collapse: collapse; }
        .kaki td { border: none; padding: 0; }
        .kaki .kanan { text-align: right; }
        .kaki .kanan:after { content: counter(page); }
    </style>
</head>
<body>

    <div class="kop">
        <h1>MANIFEST KUNJUNGAN WISATA BAHARI SUNGAI PISANG</h1>
        <p class="sub">Tanggal Keberangkatan: <strong>{{ $tanggalTeks }}</strong></p>
    </div>

    <table class="ringkasan">
        <tr>
            <td>
                <div class="label">Total Wisatawan</div>
                <div class="angka">{{ $totalOrang }} <span class="satuan">orang</span></div>
            </td>
            <td>
                <div class="label">Total Rombongan</div>
                <div class="angka">{{ $totalRombongan }} <span class="satuan">reservasi</span></div>
            </td>
            <td>
                <div class="label">Unit Akomodasi Disiapkan</div>
                <div class="angka">{{ $totalUnit }} <span class="satuan">unit</span></div>
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th style="width: 4%;"  class="c">No</th>
                <th style="width: 21%;">Nama Wisatawan</th>
                <th style="width: 14%;">No. HP</th>
                <th style="width: 15%;">Pulau Tujuan</th>
                <th style="width: 11%;">Jenis</th>
                <th style="width: 20%;">Akomodasi</th>
                <th style="width: 7%;"  class="c">Jml</th>
                <th style="width: 8%;"  class="c">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reservasi as $i => $r)
                <tr class="{{ $i % 2 === 1 ? 'genap' : '' }}">
                    <td class="c">{{ $i + 1 }}</td>
                    <td class="nama">{{ $r->wisatawan->name }}</td>
                    <td>{{ $r->wisatawan->no_hp ?: '-' }}</td>
                    <td>{{ $r->pulau->nama }}</td>
                    <td>{{ $r->jenis === 'one_day_trip' ? 'One Day Trip' : 'Menginap' }}</td>
                    <td>
                        @if ($r->akomodasi)
                            {{ $r->akomodasi->nama }}
                            @if ($r->jumlah_unit_dipesan > 1)
                                <span class="kecil">({{ $r->jumlah_unit_dipesan }} unit)</span>
                            @endif
                        @elseif ($r->bawa_tenda_sendiri)
                            <span class="kecil">Bawa tenda sendiri</span>
                        @else
                            <span class="kecil">&mdash;</span>
                        @endif
                    </td>
                    <td class="c">{{ $r->jumlah_orang }}</td>
                    <td class="c status-{{ $r->status }}">{{ ucfirst($r->status) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="kosong">Tidak ada kunjungan terjadwal pada tanggal ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="kaki">
        <table>
            <tr>
                <td>Dicetak dari Sistem Informasi Reservasi Wisata Bahari Sungai Pisang &middot; {{ $cetakTeks }} WIB</td>
                <td class="kanan">Halaman </td>
            </tr>
        </table>
    </div>

</body>
</html>
