@php
    $BULAN = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $HARI = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];

    $tglPanjang = function ($t) use ($BULAN, $HARI) {
        if (! $t) return '-';
        $c = \Carbon\Carbon::parse($t);
        return $HARI[$c->format('l')].', '.$c->day.' '.$BULAN[(int) $c->month].' '.$c->year;
    };

    $now = \Carbon\Carbon::now();
    $cetakTeks = $now->day.' '.$BULAN[(int) $now->month].' '.$now->year.', '.$now->format('H:i');

    $malam = $reservasi->jenis === 'menginap' ? $reservasi->jumlahMalam() : 0;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Tiket {{ $reservasi->kode_booking }}</title>
    @include('dokumen.gaya')
    <style>
        .kode-kotak {
            border: 2px solid #1E6091;
            background: #f2f6fa;
            padding: 10px 14px;
            margin-bottom: 12px;
        }
        .kode-kotak .label { font-size: 8.5px; color: #5b6b7a; text-transform: uppercase; letter-spacing: .6px; }
        .kode-kotak .kode {
            font-size: 26px;
            font-weight: bold;
            color: #14456b;
            letter-spacing: 3px;
            margin-top: 2px;
        }
        .kode-kotak .ket { font-size: 9px; color: #5b6b7a; margin-top: 3px; }
        .sobek { border-top: 2px dashed #b9c7d4; margin: 14px 0 12px 0; }
    </style>
</head>
<body>

@include('dokumen.kop', [
    'jenisDokumen' => 'TIKET KUNJUNGAN',
    'nomorDokumen' => '',
    'tanggalDokumen' => 'Terbit '.$cetakTeks.' WIB',
])

<div class="kode-kotak">
    <div class="label">Kode Pemesanan</div>
    <div class="kode">{{ $reservasi->kode_booking ?: 'BELUM TERSEDIA' }}</div>
    <div class="ket">Tunjukkan kode ini kepada Pengantar Pulau sebelum naik kapal.</div>
</div>

<table class="blok">
    <tr>
        <td>
            <div class="label">Tujuan</div>
            <div class="isi">
                <b>{{ $reservasi->pulau->nama }}</b><br>
                {{ $reservasi->jenis === 'menginap' ? 'Menginap'.($malam ? ' — '.$malam.' malam' : '') : 'One Day Trip' }}<br>
                @if ($reservasi->bawa_tenda_sendiri)
                    Membawa tenda sendiri
                @elseif ($reservasi->akomodasi)
                    {{ $reservasi->akomodasi->nama }} ({{ $reservasi->jumlah_unit_dipesan }} unit)
                @else
                    Tanpa akomodasi
                @endif
            </div>
        </td>
        <td>
            <div class="label">Tanggal keberangkatan</div>
            <div class="isi">
                <b>{{ $tglPanjang($reservasi->tanggal_kunjungan) }}</b><br>
                @if ($reservasi->jenis === 'menginap' && $reservasi->tanggal_selesai)
                    Kembali: {{ $tglPanjang($reservasi->tanggal_selesai) }}<br>
                @endif
                Jumlah: <b>{{ $reservasi->jumlah_orang }} orang</b>
            </div>
        </td>
    </tr>
</table>

<table class="blok">
    <tr>
        <td>
            <div class="label">Pemesan</div>
            <div class="isi">
                <b>{{ $reservasi->wisatawan->name }}</b><br>
                {{ $reservasi->wisatawan->no_hp ?: '-' }}
            </div>
        </td>
        <td>
            <div class="label">Titik kumpul &amp; Pengantar Pulau</div>
            <div class="isi">
                @if ($pengantar)
                    <b>{{ $pengantar->name }}</b><br>
                    {{ $pengantar->no_hp ?: '-' }}<br>
                    Lokasi titik kumpul dapat dilihat pada Peta Interaktif.
                @else
                    Hubungi Pengantar Pulau untuk titik kumpul.
                @endif
            </div>
        </td>
    </tr>
</table>

<h2 class="bagian">Daftar Peserta ({{ $reservasi->peserta->count() }} orang)</h2>
@if ($reservasi->peserta->isEmpty())
    <p class="catatan">
        Reservasi ini dibuat sebelum pencatatan identitas peserta diberlakukan, sehingga
        daftar pesertanya tidak tersedia. Sampaikan identitas rombongan kepada Pengantar
        Pulau sebelum keberangkatan.
    </p>
@else
<table class="data">
    <tr>
        <th class="tengah" style="width:6%">No</th>
        <th>Nama Lengkap</th>
        <th style="width:22%">No. Identitas</th>
        <th class="tengah" style="width:10%">L/P</th>
        <th class="tengah" style="width:10%">Usia</th>
        <th style="width:18%">No. HP</th>
    </tr>
    @foreach ($reservasi->peserta as $i => $orang)
    <tr>
        <td class="tengah">{{ $i + 1 }}</td>
        <td>{{ $orang->nama }}</td>
        <td>{{ $orang->no_identitas ?: '-' }}</td>
        <td class="tengah">{{ $orang->jenis_kelamin }}</td>
        <td class="tengah">{{ $orang->usia }}</td>
        <td>{{ $orang->no_hp ?: '-' }}</td>
    </tr>
    @endforeach
</table>
@endif

<div class="sobek"></div>

<div class="catatan">
    <b>Yang perlu diperhatikan.</b>
    Tiket ini hanya berlaku untuk tanggal dan nama yang tercantum di atas, dan hanya sah bila
    pembayarannya sudah diverifikasi oleh Pengantar Pulau.
    Peserta wajib hadir di titik kumpul sebelum jadwal keberangkatan.
    Setiap peserta berusia 17 tahun ke atas membawa kartu identitas asli yang sesuai dengan
    daftar di atas untuk dicocokkan sebelum naik kapal.
    Daftar peserta ini juga menjadi dasar manifest penumpang, sehingga peserta yang tidak
    tercantum tidak dapat diberangkatkan.
    Ikuti seluruh arahan keselamatan dari Pengantar Pulau selama penyeberangan.
</div>

<div class="kaki">Diterbitkan otomatis oleh Sistem Informasi Reservasi Wisata Sungai Pisang &mdash; sah tanpa tanda tangan</div>

</body>
</html>
