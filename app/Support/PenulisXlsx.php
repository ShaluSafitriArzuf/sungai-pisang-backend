<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Penulis berkas Excel (.xlsx) sederhana, tanpa pustaka tambahan.
 *
 * Alasan tidak memakai PhpSpreadsheet atau Laravel Excel: keduanya harus dipasang lewat
 * composer, dan satu-satunya yang dibutuhkan laporan ini cuma tabel biasa berisi teks, angka,
 * dan rupiah. Memasang pustaka besar untuk kebutuhan sekecil itu menambah puluhan megabita ke
 * folder vendor dan menambah satu langkah pemasangan yang bisa gagal di komputer lain.
 *
 * Sebuah berkas .xlsx sebenarnya hanyalah berkas ZIP berisi beberapa berkas XML. Kelas ini
 * menyusun XML seperlunya lalu memampatkannya memakai ZipArchive bawaan PHP.
 *
 * Angka ditulis apa adanya sebagai angka (bukan teks), supaya di Excel tetap bisa dijumlah,
 * diurutkan, dan dipakai rumus — ini bedanya dengan ekspor CSV yang semuanya jadi teks.
 */
class PenulisXlsx
{
    /** @var array<int, array{nama: string, baris: array, lebar: array}> */
    private array $lembar = [];

    /**
     * Menambahkan satu lembar kerja.
     *
     * @param  string  $nama   Nama tab di Excel (maksimal 31 karakter, aturan Excel).
     * @param  array   $baris  Daftar baris. Tiap baris adalah daftar sel. Tiap sel boleh berupa
     *                         nilai polos, atau [nilai, gaya] untuk menentukan tampilannya.
     * @param  array   $lebar  Lebar tiap kolom dalam satuan karakter.
     */
    public function tambahLembar(string $nama, array $baris, array $lebar = []): static
    {
        $this->lembar[] = [
            'nama' => mb_substr($nama, 0, 31),
            'baris' => $baris,
            'lebar' => $lebar,
        ];

        return $this;
    }

    /** Menyusun seluruh berkas dan mengembalikan isinya sebagai teks biner. */
    public function jadikanBerkas(): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP "zip" belum aktif, berkas Excel tidak dapat dibuat.');
        }

        $sementara = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();

        if ($zip->open($sementara, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Gagal menyiapkan berkas Excel sementara.');
        }

        $zip->addFromString('[Content_Types].xml', $this->jenisIsi());
        $zip->addFromString('_rels/.rels', $this->relasiUtama());
        $zip->addFromString('xl/workbook.xml', $this->bukuKerja());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->relasiBukuKerja());
        $zip->addFromString('xl/styles.xml', $this->gaya());

        foreach ($this->lembar as $i => $lembar) {
            $zip->addFromString('xl/worksheets/sheet'.($i + 1).'.xml', $this->lembarKerja($lembar));
        }

        $zip->close();

        $isi = file_get_contents($sementara);
        @unlink($sementara);

        return $isi;
    }

    // ── Bagian-bagian XML ────────────────────────────────────────────────────────────

    private function jenisIsi(): string
    {
        $tambahan = '';
        foreach ($this->lembar as $i => $tidakDipakai) {
            $tambahan .= '<Override PartName="/xl/worksheets/sheet'.($i + 1).'.xml"'
                .' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$tambahan
            .'</Types>';
    }

    private function relasiUtama(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function bukuKerja(): string
    {
        $daftar = '';
        foreach ($this->lembar as $i => $lembar) {
            $daftar .= '<sheet name="'.$this->aman($lembar['nama']).'" sheetId="'.($i + 1).'" r:id="rId'.($i + 1).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$daftar.'</sheets>'
            .'</workbook>';
    }

    private function relasiBukuKerja(): string
    {
        $daftar = '';
        foreach ($this->lembar as $i => $tidakDipakai) {
            $daftar .= '<Relationship Id="rId'.($i + 1).'"'
                .' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
                .' Target="worksheets/sheet'.($i + 1).'.xml"/>';
        }

        $idGaya = 'rId'.(count($this->lembar) + 1);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$daftar
            .'<Relationship Id="'.$idGaya.'"'
            .' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"'
            .' Target="styles.xml"/>'
            .'</Relationships>';
    }

    /**
     * Daftar gaya. Urutan cellXfs di bawah ini yang menentukan nomor gaya tiap sel,
     * dan nomor itulah yang dipakai method nomorGaya().
     */
    private function gaya(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            // 164 = format rupiah tanpa desimal, dengan pemisah ribuan
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="&quot;Rp&quot;\ #,##0"/></numFmts>'
            .'<fonts count="4">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="14"/><color rgb="FF004873"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="4">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF004873"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFFDEBD8"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border>'
            .'<left style="thin"><color rgb="FFD9D9D9"/></left>'
            .'<right style="thin"><color rgb="FFD9D9D9"/></right>'
            .'<top style="thin"><color rgb="FFD9D9D9"/></top>'
            .'<bottom style="thin"><color rgb="FFD9D9D9"/></bottom>'
            .'<diagonal/></border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="9">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">'
            .'<alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1">'
            .'<alignment horizontal="center"/></xf>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"/>'
            .'<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            .'<xf numFmtId="164" fontId="1" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"/>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    private function nomorGaya(?string $gaya): int
    {
        return match ($gaya) {
            'tebal' => 1,
            'judul' => 2,
            'kepala' => 3,
            'teks' => 4,
            'angka' => 5,
            'rupiah' => 6,
            'total' => 7,
            'totalRupiah' => 8,
            default => 0,
        };
    }

    private function lembarKerja(array $lembar): string
    {
        $kolom = '';
        if ($lembar['lebar']) {
            $kolom = '<cols>';
            foreach ($lembar['lebar'] as $i => $lebar) {
                $kolom .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$lebar.'" customWidth="1"/>';
            }
            $kolom .= '</cols>';
        }

        $isi = '';
        foreach ($lembar['baris'] as $nomorBaris => $baris) {
            $r = $nomorBaris + 1;
            $sel = '';

            foreach ($baris as $nomorKolom => $isiSel) {
                [$nilai, $gaya] = is_array($isiSel) ? [$isiSel[0], $isiSel[1] ?? null] : [$isiSel, null];

                if ($nilai === null || $nilai === '') {
                    // Sel kosong tetap ditulis kalau punya gaya, supaya garis tabelnya tidak putus.
                    if ($gaya === null) {
                        continue;
                    }
                    $sel .= '<c r="'.$this->hurufKolom($nomorKolom).$r.'" s="'.$this->nomorGaya($gaya).'"/>';

                    continue;
                }

                $alamat = $this->hurufKolom($nomorKolom).$r;
                $s = ' s="'.$this->nomorGaya($gaya).'"';

                if (is_int($nilai) || is_float($nilai)) {
                    $sel .= '<c r="'.$alamat.'"'.$s.'><v>'.$nilai.'</v></c>';
                } else {
                    $sel .= '<c r="'.$alamat.'"'.$s.' t="inlineStr"><is><t xml:space="preserve">'
                        .$this->aman((string) $nilai).'</t></is></c>';
                }
            }

            $isi .= '<row r="'.$r.'">'.$sel.'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$kolom
            .'<sheetData>'.$isi.'</sheetData>'
            .'</worksheet>';
    }

    /** 0 -> A, 25 -> Z, 26 -> AA */
    private function hurufKolom(int $nomor): string
    {
        $huruf = '';
        $nomor++;

        while ($nomor > 0) {
            $sisa = ($nomor - 1) % 26;
            $huruf = chr(65 + $sisa).$huruf;
            $nomor = (int) (($nomor - $sisa - 1) / 26);
        }

        return $huruf;
    }

    private function aman(string $teks): string
    {
        // Karakter kendali (selain tab/enter) tidak sah di XML dan membuat Excel menolak berkas.
        $teks = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $teks);

        return htmlspecialchars($teks, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
