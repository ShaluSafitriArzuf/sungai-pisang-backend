@php
    $BULAN = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $tgl = fn ($t) => $t ? \Carbon\Carbon::parse($t)->day.' '.$BULAN[(int) \Carbon\Carbon::parse($t)->month].' '.\Carbon\Carbon::parse($t)->year : '-';
    $rp = fn ($n) => 'Rp'.number_format((float) $n, 0, ',', '.');

    $now = \Carbon\Carbon::now();
    $cetakTeks = $now->day.' '.$BULAN[(int) $now->month].' '.$now->year.', '.$now->format('H:i');

    $statusTeks = [
        'menunggu_verifikasi' => 'MENUNGGU VERIFIKASI',
        'valid' => 'LUNAS - TERVERIFIKASI',
        'selesai' => 'LUNAS - KUNJUNGAN SELESAI',
        'ditolak' => 'DITOLAK',
        'pengajuan_batal' => 'PENGAJUAN PEMBATALAN',
        'dibatalkan' => 'DIBATALKAN',
    ][$reservasi->status] ?? strtoupper($reservasi->status);

    $malam = $reservasi->jenis === 'menginap' ? $reservasi->jumlahMalam() : 0;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $reservasi->nomorInvoice() }}</title>
    @include('dokumen.gaya')
</head>
<body>

@include('dokumen.kop', [
    'jenisDokumen' => 'INVOICE',
    'nomorDokumen' => $reservasi->nomorInvoice(),
    'tanggalDokumen' => $tgl($reservasi->created_at),
])

<table class="blok">
    <tr>
        <td>
            <div class="label">Ditagihkan kepada</div>
            <div class="isi">
                <b>{{ $reservasi->wisatawan->name }}</b><br>
                {{ $reservasi->wisatawan->email }}<br>
                {{ $reservasi->wisatawan->no_hp ?: '-' }}
            </div>
        </td>
        <td>
            <div class="label">Status pembayaran</div>
            <div class="isi">
                <b>{{ $statusTeks }}</b><br>
                Metode: Transfer bank, dibayar penuh di awal<br>
                Kode pemesanan: <b>{{ $reservasi->kode_booking ?: '-' }}</b>
            </div>
        </td>
    </tr>
</table>

<h2 class="bagian">Rincian Kunjungan</h2>
<table class="data">
    <tr>
        <th style="width:32%">Keterangan</th>
        <th>Isi</th>
    </tr>
    <tr><td>Tujuan</td><td>{{ $reservasi->pulau->nama }}</td></tr>
    <tr><td>Jenis kunjungan</td><td>{{ $reservasi->jenis === 'menginap' ? 'Menginap' : 'One Day Trip' }}</td></tr>
    <tr>
        <td>Tanggal</td>
        <td>
            {{ $tgl($reservasi->tanggal_kunjungan) }}
            @if ($reservasi->jenis === 'menginap' && $reservasi->tanggal_selesai)
                &mdash; {{ $tgl($reservasi->tanggal_selesai) }} ({{ $malam }} malam)
            @endif
        </td>
    </tr>
    <tr><td>Jumlah wisatawan</td><td>{{ $reservasi->jumlah_orang }} orang</td></tr>
    <tr>
        <td>Akomodasi</td>
        <td>
            @if ($reservasi->bawa_tenda_sendiri)
                Membawa tenda sendiri
            @elseif ($reservasi->akomodasi)
                {{ $reservasi->akomodasi->nama }} &mdash; {{ $reservasi->jumlah_unit_dipesan }} unit
            @else
                Tidak memakai akomodasi
            @endif
        </td>
    </tr>
</table>

<h2 class="bagian">Rincian Biaya</h2>
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
        <td>Tiket masuk pulau</td>
        <td class="kanan">{{ $rp($reservasi->biaya_tiket_masuk) }}</td>
    </tr>
    <tr>
        <td>
            Akomodasi
            @if ($reservasi->akomodasi && $malam)
                ({{ $reservasi->jumlah_unit_dipesan }} unit &times; {{ $malam }} malam)
            @endif
        </td>
        <td class="kanan">{{ $rp($reservasi->biaya_akomodasi) }}</td>
    </tr>
    <tr>
        <td style="background:#1E6091;color:#fff"><b>TOTAL DIBAYAR</b></td>
        <td class="kanan" style="background:#1E6091;color:#fff"><b>{{ $rp($reservasi->total_bayar) }}</b></td>
    </tr>
</table>

<h2 class="bagian">Daftar Peserta ({{ $reservasi->peserta->count() }} orang)</h2>
@if ($reservasi->peserta->isEmpty())
    <p class="catatan">
        Reservasi ini dibuat sebelum pencatatan identitas peserta diberlakukan, sehingga
        daftar pesertanya tidak tersedia.
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

<div class="catatan">
    <b>Ketentuan.</b> Pembayaran dilakukan penuh di awal melalui transfer bank dan berlaku sah
    setelah bukti transfer diverifikasi oleh Pengantar Pulau. Pengajuan pembatalan hanya dapat
    diajukan paling lambat dua hari sebelum tanggal kunjungan (H-2), dan keputusan pengembalian
    dana ditetapkan oleh Pengantar Pulau. Invoice ini diterbitkan otomatis oleh sistem sehingga
    sah tanpa tanda tangan basah.
</div>

<div class="kaki">Dicetak dari Sistem Informasi Reservasi Wisata Sungai Pisang pada {{ $cetakTeks }} WIB</div>

</body>
</html>
