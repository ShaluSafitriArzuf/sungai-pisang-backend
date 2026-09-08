@php
    // Logo dibaca dari berkas lalu ditanam sebagai data URI. Ditanam, bukan dirujuk lewat
    // alamat, karena DomPDF tidak selalu bisa mengambil berkas lewat http saat berjalan di
    // hosting berbagi. Kalau berkasnya tidak ada, kop tetap tampil tanpa logo — bukan galat.
    $logoBerkas = public_path('logo/jelajah-bahari.png');
    $logo = is_file($logoBerkas)
        ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoBerkas))
        : null;
@endphp
<div class="kop">
    <table>
        <tr>
            @if ($logo)
            <td style="width:52px; vertical-align:middle;">
                <img src="{{ $logo }}" alt="Jelajah Bahari" style="width:44px; height:44px;">
            </td>
            @endif
            <td style="vertical-align:middle;">
                <p class="judul">Jelajah Bahari</p>
                <p class="sub">Wisata Bahari Sungai Pisang &mdash; Kecamatan Bungus Teluk Kabung, Kota Padang</p>
            </td>
            <td class="kanan">
                <div class="jenis">{{ $jenisDokumen }}</div>
                @if (!empty($nomorDokumen))
                    <div class="nomor">{{ $nomorDokumen }}</div>
                @endif
                <div class="nomor">{{ $tanggalDokumen }}</div>
            </td>
        </tr>
    </table>
</div>
