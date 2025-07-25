# 📦 INVs - Sistem Permintaan & Pengeluaran Part Antar Gedung

Sistem ini dibuat untuk mendigitalisasi alur logistik part antar gedung (contohnya dari Gedung A ke Gedung B), mulai dari **penerimaan**, **pelabelan**, **permintaan**, hingga **pengeluaran** part. Dirancang agar proses warehouse lebih cepat, akurat, dan terdokumentasi secara real-time.

## ✨ Fitur Utama
- ✅ **Receiving Part** — Pencatatan part masuk ke warehouse.
- ✅ **Input & Labelling** — Identifikasi part dan label barcode.
- ✅ **Request Part Antar Gedung** — Permintaan part berbasis sistem.
- ✅ **Voice Feedback** — Suara otomatis (Google TTS) saat request berhasil.
- ✅ **1 KBN = 1x Scan** — Pengeluaran part cukup satu kali scan.
- 🔜 **Issuance** — Pengeluaran part berdasarkan permintaan.
- 🔜 **WebPush Notification** — Notifikasi real-time saat ada request masuk.

## 🛠️ Teknologi
- PHP (Laravel)
- Google Speech Synthesis API
- WebPush API (planned)
- Barcode scan via kamera/reader

## 📌 Status
🚧 *Masih dalam tahap pengembangan. Fitur issuance dan notifikasi sedang disiapkan.*
