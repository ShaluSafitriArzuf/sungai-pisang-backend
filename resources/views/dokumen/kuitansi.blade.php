@php
    $BULAN = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $tgl = fn ($t) => $t ? \Carbon\Carbon::parse($t)->day.' '.$BULAN[(int) \Carbon\Carbon::parse($t)->month].' '.\Carbon\Carbon::parse($t)->year : '-';
    $rp = fn ($n) => 'Rp'.number_format((float) $n, 0, ',', '.');

    $now = \Carbon\Carbon::now();
    $cetakTeks = $now->day.' '.$BULAN[(int) $now->month].' '.$now->year.', '.$now->format('H:i');

    // Jumlah uang wajib ditulis dalam huruf pada kuitansi. Fungsi ini menerjemahkan angka
    // menjadi kata bahasa Indonesia dengan cara rekursif per kelipatan ribuan.
    $terbilang = function ($angka) use (&$terbilang) {
        $angka = (int) $angka;
        $satuan = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

        if ($angka < 12) {
            return $satuan[$angka];
        }
        if ($angka < 20) {
            return $terbilang($angka - 10).' belas';
        }
        if ($angka < 100) {
            return $terbilang(intdiv($angka, 10)).' puluh'.($angka % 10 ? ' '.$terbilang($angka % 10) : '');
        }
        if ($angka < 200) {
            return 'seratus'.($angka % 100 ? ' '.$terbilang($angka % 100) : '');
        }
        if ($angka < 1000) {
            return $terbilang(intdiv($angka, 100)).' ratus'.($angka % 100 ? ' '.$terbilang($angka % 100) : '');
        }
        if ($angka < 2000) {
            return 'seribu'.($angka % 1000 ? ' '.$terbilang($angka % 1000) : '');
        }
        if ($angka < 1000000) {
            return $terbilang(intdiv($angka, 1000)).' ribu'.($angka % 1000 ? ' '.$terbilang($angka % 1000) : '');
        }
        if ($angka < 1000000000) {
            return $terbilang(intdiv($angka, 1000000)).' juta'.($angka % 1000000 ? ' '.$terbilang($angka % 1000000) : '');
        }

        return $terbilang(intdiv($angka, 1000000000)).' miliar'.($angka % 1000000000 ? ' '.$terbilang($angka % 1000000000) : '');
    };

    $hurufJumlah = trim($terbilang($reservasi->total_bayar));
    $hurufJumlah = $hurufJumlah === '' ? 'nol' : $hurufJumlah;
    $hurufJumlah = mb_strtoupper(mb_substr($hurufJumlah, 0, 1)).mb_substr($hurufJumlah, 1);

    $tanggalTerima = $reservasi->verified_at ?: $reservasi->updated_at;
    $malam = $reservasi->jenis === 'menginap' ? $reservasi->jumlahMalam() : 0;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kuitansi {{ $reservasi->nomorKuitansi() }}</title>
    @include('dokumen.gaya')
    <style>
        .jumlahbesar {
            background: #f2f6fa;
            border: 1px solid #d7e3ee;
            padding: 12px 14px;
            margin: 12px 0;
        }
        .jumlahbesar .angka { font-size: 22px; font-weight: bold; color: #14456b; }
        .jumlahbesar .huruf { font-size: 10.5px; font-style: italic; color: #1f2933; margin-top: 5px; line-height: 1.5; }
        .ttd { width: 100%; margin-top: 26px; border-collapse: collapse; }
        .ttd td { width: 50%; vertical-align: top; font-size: 10px; }
        .ttd .ruang { height: 52px; }
        .ttd .nama { border-top: 1px solid #5b6b7a; padding-top: 4px; font-weight: bold; color: #14456b; }
        .lunas {
            display: inline-block;
            border: 2px solid #1F6B4A;
            color: #1F6B4A;
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 2px;
            padding: 5px 16px;
            transform: rotate(-4deg);
        }
    </style>
</head>
<body>

@include('dokumen.kop', [
    'jenisDokumen' => 'KUITANSI',
    'nomorDokumen' => $reservasi->nomorKuitansi(),
    'tanggalDokumen' => $tgl($tanggalTerima),
])

<table class="blok">
    <tr>
        <td>
            <div class="label">Telah diterima dari</div>
            <div class="isi">
                <b>{{ $reservasi->wisatawan->name }}</b><br>
                {{ $reservasi->wisatawan->email }}<br>
                {{ $reservasi->wisatawan->no_hp ?: '-' }}
            </div>
        </td>
        <td>
            <div class="label">Keterangan pembayaran</div>
            <div class="isi">
                Metode: Transfer bank, dibayar penuh di awal<br>
                Kode pemesanan: <b>{{ $reservasi->kode_booking ?: '-' }}</b><br>
                Nomor tagihan: {{ $reservasi->nomorInvoice() }}
            </div>
        </td>
    </tr>
</table>

<div class="jumlahbesar">
    <div class="label" style="font-size:8.5px;color:#5b6b7a;text-transform:uppercase;letter-spacing:.4px;">Uang sejumlah</div>
    <div class="angka">{{ $rp($reservasi->total_bayar) }}</div>
    <div class="huruf">Terbilang: {{ $hurufJumlah }} rupiah</div>
</div>

<h2 class="bagian">Untuk Pembayaran</h2>
<table class="data">
    <tr>
        <th style="width:32%">Keterangan</th>
        <th>Isi</th>
    </tr>
    <tr><td>Kunjungan wisata ke</td><td>{{ $reservasi->pulau->nama }}</td></tr>
    <tr><td>Jenis kunjungan</td><td>{{ $reservasi->jenis === 'menginap' ? 'Menginap' : 'One Day Trip' }}</td></tr>
    <tr>
        <td>Tanggal keberangkatan</td>
        <td>
            {{ $tgl($reservasi->tanggal_kunjungan) }}
            @if ($reservasi->jenis === 'menginap' && $reservasi->tanggal_selesai)
                &mdash; {{ $tgl($reservasi->tanggal_selesai) }} ({{ $malam }} malam)
            @endif
        </td>
    </tr>
    <tr><td>Jumlah wisatawan</td><td>{{ $reservasi->jumlah_orang }} orang</td></tr>
</table>

<h2 class="bagian">Rincian Uang yang Diterima</h2>
<table class="data">
    <tr>
        <th>Komponen biaya</th>
        <th class="kanan" style="width:24%">Jumlah</th>
    </tr>
    <tr>
        <td>Ongkos kapal penyeberangan ({{ $reservasi->jumlah_orang }} orang)</td>
        <td class="kanan">{{ $rp($reservasi->biaya_penyeberangan) }}</td>
    </tr>
    <tr>
        {{-- Jumlah orang sengaja tidak dicantumkan di sini. Untuk akomodasi bertanda
             "tiket termasuk", yang ditagih hanya orang yang melebihi kapasitas unit,
             sehingga menuliskan seluruh rombongan justru keliru. --}}
        <td>Tiket masuk pulau</td>
        <td class="kanan">{{ $rp($reservasi->biaya_tiket_masuk) }}</td>
    </tr>
    <tr>
        <td>
            Akomodasi
            @if ($reservasi->akomodasi && $malam)
                ({{ $reservasi->jumlah_unit_dipesan }} unit &times; {{ $malam }} malam)
            @elseif ($reservasi->bawa_tenda_sendiri)
                (membawa tenda sendiri)
            @endif
        </td>
        <td class="kanan">{{ $rp($reservasi->biaya_akomodasi) }}</td>
    </tr>
    <tr>
        <td style="background:#1E6091;color:#fff"><b>JUMLAH DITERIMA</b></td>
        <td class="kanan" style="background:#1E6091;color:#fff"><b>{{ $rp($reservasi->total_bayar) }}</b></td>
    </tr>
</table>

<table class="ttd">
    <tr>
        <td>
            <div class="lunas">LUNAS</div>
        </td>
        <td>
            Sungai Pisang, {{ $tgl($tanggalTerima) }}<br>
            Diterima dan diverifikasi oleh
            <div class="ruang"></div>
            <div class="nama">{{ $reservasi->verifikator->name ?? 'Pengantar Pulau' }}</div>
            <div>Pengantar Pulau</div>
        </td>
    </tr>
</table>

<div class="catatan">
    <b>Ketentuan.</b> Kuitansi ini diterbitkan setelah bukti transfer diperiksa dan dinyatakan
    sah oleh Pengantar Pulau, sehingga menyatakan bahwa uang sejumlah di atas benar-benar
    telah diterima. Kuitansi berbeda kedudukannya dengan invoice: invoice adalah tagihan yang
    terbit saat reservasi dibuat, sedangkan kuitansi adalah bukti penerimaan uang. Dokumen ini
    diterbitkan otomatis oleh sistem sehingga sah tanpa tanda tangan basah.
</div>

<div class="kaki">Dicetak dari Sistem Informasi Reservasi Wisata Sungai Pisang pada {{ $cetakTeks }} WIB</div>

</body>
</html>
