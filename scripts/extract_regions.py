#!/usr/bin/env python3
"""Ekstrak referensi wilayah Jawa Barat dari Kepmendagri 300.2.2-2138/2025.

Sumber utama (kode + nama resmi):
  Keputusan Menteri Dalam Negeri Nomor 300.2.2-2138 Tahun 2025 tentang
  Pemberian dan Pemutakhiran Kode, Data Wilayah Administrasi Pemerintahan,
  dan Pulau.

PDF resmi yang dipakai adalah salinan pada Wikimedia Commons:
  https://upload.wikimedia.org/wikipedia/commons/5/51/Keputusan_Menteri_Dalam_Negeri_Nomor_300.2.2-2138_Tahun_2025.pdf

PDF tersebut adalah hasil pindai dengan lapisan teks OCR. Skrip ini:
  1. membaca halaman rincian desa/kelurahan Provinsi Jawa Barat,
  2. mengambil kode dan nama dari baris resmi,
  3. membersihkan catatan kolom keterangan yang menempel pada nama,
  4. memperbaiki nama yang terpotong akibat lebar kolom (daftar koreksi
     eksplisit di CORRECTIONS, hanya untuk nama yang terbukti terpotong),
  5. memvalidasi hierarki dan jumlah baris,
  6. menulis CSV yang dapat diimpor ulang secara idempoten.

Pemakaian:
  python scripts/extract_regions.py \
      --pdf /path/kepmendagri_2138_2025.pdf \
      --output database/data/regions/jawa_barat_2025.csv \
      --report database/data/regions/jawa_barat_2025.report.json

Dependensi (di luar dependensi proyek):
  python -m pip install pypdf

Skrip tidak menulis ke database dan tidak memerlukan kredensial.
"""

from __future__ import annotations

import argparse
import csv
import hashlib
import json
import re
import sys
import unicodedata
from collections import Counter, defaultdict
from pathlib import Path

# Rentang halaman rincian desa/kelurahan Jawa Barat pada PDF 4.428 halaman.
# Nilai ini adalah lokasi fisik PDF (0-based), bukan nomor halaman cetak.
DEFAULT_START_PAGE = 1369
DEFAULT_END_PAGE = 1604  # inklusif

PROVINCE_CODE = "32"
PROVINCE_NAME = "Jawa Barat"

# Nama yang terpotong karena lebar kolom pada PDF. Nilai pengganti diambil
# dari kolom MFD/desa yang sama pada publikasi BPS Jawa Barat 2025 Semester 2
# (lihat docs/REGION_DATA_SOURCES.md). Jangan menambah koreksi tanpa bukti.
CORRECTIONS = {
    "32.09.29.2003": "Prajawinangun Wetan",
    "32.12.28.2005": "Kedokanbunder Wetan",
    "32.15.06.2001": "Rengasdengklok Selatan",
    "32.15.06.2002": "Rengasdengklok Utara",
}

# Kata kunci awal kolom keterangan yang kadang menempel pada nama.
NOTE_KEYWORDS = (
    "Semula",
    "semula",
    "Perbaikan",
    "perbaikan",
    "Koreksi",
    "koreksi",
    "Perubahan",
    "perubahan",
    "Pemekaran",
    "pemekaran",
    "Pe,mekaran",
    "Perda",
    "Undang",
    "Surat",
    "Srt",
    "sesuai",
    "Catatan",
)

VILLAGE_RE = re.compile(r"^(\d{2}\.\d{2}\.\d{2}\.\d{4})\s+(\d+)\s+(.+)$")
DISTRICT_RE = re.compile(r"^(\d{2}\.\d{2}\.\d{2})\s+(\d+)\s+(.+)$")
REGENCY_CODE_RE = re.compile(r"^(32\.\d{2})$")
SECTION_RE = re.compile(
    r"^C\.[A-Za-z0-9.]+\)\s+(Kabupaten|Kota)\s+(.+?)\s+Provinsi\s+Jawa\s+Barat",
    re.IGNORECASE,
)
REGENCY_LINE_RE = re.compile(r"^(Kabupaten|Kota)\s+(.+?)\s{2,}\d+", re.IGNORECASE)


def clean_name(value: str) -> str:
    """Buang kolom keterangan dan spasi berlebih dari nama wilayah."""
    name = re.split(r"\s{2,}", value.strip())[0].strip()

    # Catatan kadang dipisahkan hanya satu spasi dari nama.
    for keyword in NOTE_KEYWORDS:
        marker = f" {keyword}"
        if marker in name:
            name = name.split(marker, 1)[0].strip()

    name = re.sub(r"\s+", " ", name)
    return name.strip(" ;,.")


def normalize(value: str) -> str:
    """Normalisasi untuk pemeriksaan duplikat/hierarki (bukan untuk disimpan)."""
    decomposed = unicodedata.normalize("NFKD", value)
    ascii_value = decomposed.encode("ascii", "ignore").decode("ascii")
    return re.sub(r"[^a-z0-9]", "", ascii_value.lower())


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def parse_pdf(pdf_path: Path, start_page: int, end_page: int) -> dict:
    try:
        from pypdf import PdfReader
    except ImportError as exc:  # pragma: no cover - pesan untuk operator
        raise SystemExit(
            "pypdf belum terpasang. Jalankan: python -m pip install pypdf"
        ) from exc

    reader = PdfReader(str(pdf_path))
    if end_page >= len(reader.pages):
        raise SystemExit(
            f"Rentang halaman tidak valid: PDF hanya {len(reader.pages)} halaman."
        )

    regencies: dict[str, dict] = {}
    districts: dict[str, dict] = {}
    villages: dict[str, dict] = {}

    current_regtype: str | None = None
    current_regname: str | None = None
    current_regcode: str | None = None

    for page_index in range(start_page, end_page + 1):
        text = reader.pages[page_index].extract_text() or ""

        for raw_line in text.splitlines():
            line = raw_line.strip()
            if not line:
                continue

            section = SECTION_RE.match(line)
            if section:
                current_regtype = section.group(1).title()
                current_regname = clean_name(section.group(2))
                continue

            regency_line = REGENCY_LINE_RE.match(line)
            if regency_line and current_regname is None:
                current_regtype = regency_line.group(1).title()
                current_regname = clean_name(regency_line.group(2))
                continue

            regency_code = REGENCY_CODE_RE.match(line)
            if regency_code:
                current_regcode = regency_code.group(1)
                if current_regtype is None or current_regname is None:
                    raise SystemExit(
                        f"Kode kabupaten/kota {current_regcode} ditemukan tanpa nama "
                        f"(halaman fisik {page_index})."
                    )
                regencies[current_regcode] = {
                    "code": current_regcode,
                    "name": current_regname,
                    "type": current_regtype.lower(),
                    "page": page_index,
                }
                continue

            village = VILLAGE_RE.match(line)
            if village:
                code = village.group(1)
                name = CORRECTIONS.get(code, clean_name(village.group(3)))
                if not name:
                    raise SystemExit(
                        f"Nama desa/kelurahan kosong untuk {code} (halaman {page_index})."
                    )
                if code in villages:
                    raise SystemExit(
                        f"Kode desa/kelurahan duplikat: {code} "
                        f"(halaman {villages[code]['page']} dan {page_index})."
                    )
                villages[code] = {
                    "code": code,
                    "name": name,
                    "parent_code": code[:8],
                    "page": page_index,
                }
                continue

            district = DISTRICT_RE.match(line)
            if district:
                code = district.group(1)
                name = clean_name(district.group(3))
                if not name:
                    raise SystemExit(
                        f"Nama kecamatan kosong untuk {code} (halaman {page_index})."
                    )
                if code in districts:
                    raise SystemExit(
                        f"Kode kecamatan duplikat: {code} "
                        f"(halaman {districts[code]['page']} dan {page_index})."
                    )
                districts[code] = {
                    "code": code,
                    "name": name,
                    "parent_code": code[:5],
                    "page": page_index,
                }

    return {
        "regencies": regencies,
        "districts": districts,
        "villages": villages,
    }


def validate(parsed: dict) -> dict:
    regencies = parsed["regencies"]
    districts = parsed["districts"]
    villages = parsed["villages"]

    errors: list[str] = []

    if len(regencies) != 27:
        errors.append(f"Jumlah kabupaten/kota {len(regencies)}, seharusnya 27.")
    if len(districts) != 627:
        errors.append(f"Jumlah kecamatan {len(districts)}, seharusnya 627.")
    if len(villages) != 5957:
        errors.append(f"Jumlah desa/kelurahan {len(villages)}, seharusnya 5.957.")

    for code, regency in regencies.items():
        if not code.startswith(PROVINCE_CODE + "."):
            errors.append(f"Kode kabupaten/kota di luar Provinsi 32: {code}")
        if regency["type"] not in {"kabupaten", "kota"}:
            errors.append(f"Tipe kabupaten/kota tidak dikenal: {code} {regency['type']}")

    for code, district in districts.items():
        if district["parent_code"] not in regencies:
            errors.append(
                f"Kecamatan {code} menunjuk kabupaten/kota yang tidak ada: "
                f"{district['parent_code']}"
            )

    for code, village in villages.items():
        if village["parent_code"] not in districts:
            errors.append(
                f"Desa/kelurahan {code} menunjuk kecamatan yang tidak ada: "
                f"{village['parent_code']}"
            )

    # Duplikat nama dalam satu kecamatan biasanya berarti nama terpotong.
    seen: dict[tuple[str, str], list[str]] = defaultdict(list)
    for code, village in villages.items():
        seen[(village["parent_code"], normalize(village["name"]))].append(code)
    for (district_code, name), codes in seen.items():
        if len(codes) > 1:
            errors.append(
                f"Nama duplikat dalam kecamatan {district_code}: {name} -> {codes}"
            )

    # Catatan: nama yang menjadi awalan nama lain TIDAK otomatis invalid.
    # Contoh resmi: "Nanggewer" dan "Nanggewer Mekar" adalah dua desa berbeda.
    # Pemotongan kolom hanya dikoreksi lewat CORRECTIONS yang sudah diverifikasi.

    if errors:
        raise SystemExit("Validasi gagal:\n- " + "\n- ".join(errors))

    return {
        "counts": {
            "province": 1,
            "regency": sum(1 for r in regencies.values() if r["type"] == "kabupaten"),
            "city": sum(1 for r in regencies.values() if r["type"] == "kota"),
            "district": len(districts),
            "village": len(villages),
            "total": 1 + len(regencies) + len(districts) + len(villages),
        },
        "duplicate_names_in_district": 0,
    }


def write_csv(output: Path, parsed: dict) -> None:
    output.parent.mkdir(parents=True, exist_ok=True)

    rows: list[dict[str, str]] = [
        {
            "level": "province",
            "code": PROVINCE_CODE,
            "name": PROVINCE_NAME,
            "parent_code": "",
            "type": "province",
        }
    ]

    for code in sorted(parsed["regencies"]):
        regency = parsed["regencies"][code]
        rows.append(
            {
                "level": "regency",
                "code": code,
                "name": regency["name"],
                "parent_code": PROVINCE_CODE,
                "type": regency["type"],
            }
        )

    for code in sorted(parsed["districts"]):
        district = parsed["districts"][code]
        rows.append(
            {
                "level": "district",
                "code": code,
                "name": district["name"],
                "parent_code": district["parent_code"],
                "type": "district",
            }
        )

    for code in sorted(parsed["villages"]):
        village = parsed["villages"][code]
        rows.append(
            {
                "level": "village",
                "code": code,
                "name": village["name"],
                "parent_code": village["parent_code"],
                "type": "village",
            }
        )

    with output.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(
            handle,
            fieldnames=["level", "code", "name", "parent_code", "type"],
            lineterminator="\n",
        )
        writer.writeheader()
        writer.writerows(rows)


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--pdf", required=True, type=Path)
    parser.add_argument(
        "--output",
        type=Path,
        default=Path("database/data/regions/jawa_barat_2025.csv"),
    )
    parser.add_argument("--report", type=Path, default=None)
    parser.add_argument("--start-page", type=int, default=DEFAULT_START_PAGE)
    parser.add_argument("--end-page", type=int, default=DEFAULT_END_PAGE)
    args = parser.parse_args()

    if not args.pdf.is_file():
        raise SystemExit(f"PDF tidak ditemukan: {args.pdf}")

    parsed = parse_pdf(args.pdf, args.start_page, args.end_page)
    validation = validate(parsed)
    write_csv(args.output, parsed)

    report = {
        "source": {
            "title": (
                "Keputusan Menteri Dalam Negeri Nomor 300.2.2-2138 Tahun 2025 "
                "tentang Pemberian dan Pemutakhiran Kode, Data Wilayah "
                "Administrasi Pemerintahan, dan Pulau"
            ),
            "publisher": "Kementerian Dalam Negeri Republik Indonesia",
            "file": args.pdf.name,
            "sha256": sha256(args.pdf),
            "pdf_pages": [args.start_page, args.end_page],
        },
        "output": {
            "file": args.output.name,
            "sha256": sha256(args.output),
            "rows": validation["counts"]["total"],
        },
        "counts": validation["counts"],
        "corrections": CORRECTIONS,
        "note": (
            "Nama diambil dari lapisan teks OCR PDF resmi. Nama yang terpotong "
            "karena lebar kolom dikoreksi hanya bila terbukti dari kolom desa "
            "yang sama pada publikasi BPS Jawa Barat 2025 Semester 2."
        ),
    }

    if args.report:
        args.report.parent.mkdir(parents=True, exist_ok=True)
        args.report.write_text(
            json.dumps(report, ensure_ascii=False, indent=2) + "\n",
            encoding="utf-8",
        )

    print(json.dumps(report["counts"], ensure_ascii=False))
    print(f"CSV: {args.output} ({validation['counts']['total']} baris)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
