# Implementation Plan: Sistem Estimasi Biaya & Purchasing

Dokumen ini merinci rencana arsitektur dan fase implementasi untuk pengembangan **Sistem Estimasi Biaya & Purchasing** berbasis **Laravel 13 & Filament PHP v3**.

---

## 1. Arsitektur Sistem & Spesifikasi Teknis

- **Framework**: Laravel 13.x (PHP 8.3)
- **UI / Admin Panel**: Filament PHP v3
- **Database**: MySQL (`db_pud` pada port 3306)
- **Autentikasi & Otorisasi**: Role-Based Access Control (Admin, Estimator, Approver)
- **Mesin Kalkulasi**: Reactive Filament Form State + Service Class (`CostCalculationService`) untuk konsistensi perhitungan di form dan backend.

---

## 2. Struktur Data & Skema Database (ERD Mapping)

### A. `users`
- `id` (bigint, PK)
- `name` (string)
- `email` (string, unique)
- `password` (string)
- `role` (enum: `admin`, `estimator`, `approver`, default: `estimator`)
- `timestamps`

### B. `global_variables`
- `id` (bigint, PK)
- `key` (string, unique, contoh: `exchange_rate`, `fluktuasi_jfe`)
- `name` (string, label deskriptif)
- `value` (decimal: 16, 4)
- `updated_by` (foreignId -> users.id, nullable)
- `timestamps`

### C. `materials`
- `id` (bigint, PK)
- `spec` (string, contoh: `SPH270-OD`, `SWMB`)
- `category` (enum: `Fastener`, `Presspart`)
- `basic_rate` (decimal: 16, 4)
- `is_active` (boolean, default: true)
- `timestamps`

### D. `processes`
- `id` (bigint, PK)
- `name` (string, contoh: `Heading`, `Forging`, `Heat treatment`, `Inspection`, `Bending`)
- `default_cost` (decimal: 16, 4)
- `is_active` (boolean, default: true)
- `timestamps`

### E. `quotations`
- `id` (bigint, PK)
- `document_number` (string, unique, format: `QUO-YYYYMM-XXXX`)
- `user_id` (foreignId -> users.id, Estimator)
- `material_id` (foreignId -> materials.id)
- `diameter` (string, nullable)
- `thickness` (string, nullable)
- `size_material` (string, nullable)
- `weight` (decimal: 12, 4)
- `snapshot_exchange_rate` (decimal: 16, 4)
- `snapshot_fluktuasi` (decimal: 16, 4)
- `price_idr` (decimal: 16, 2)
- `total_process_cost` (decimal: 16, 2)
- `profit` (decimal: 16, 2)
- `transport` (decimal: 16, 2, default: 0)
- `packing` (decimal: 16, 2, default: 0)
- `total_cost` (decimal: 16, 2)
- `status` (enum: `draft`, `submitted`, `approved`, `rejected`, default: `draft`)
- `approved_by` (foreignId -> users.id, nullable)
- `approved_at` (timestamp, nullable)
- `notes` (text, nullable)
- `timestamps`

### F. `quotation_processes`
- `id` (bigint, PK)
- `quotation_id` (foreignId -> quotations.id, onDelete cascade)
- `process_id` (foreignId -> processes.id)
- `applied_cost` (decimal: 16, 2)
- `timestamps`

---

## 3. Logika Perhitungan Bisnis (Berdasarkan Book5.xlsx)

1. **Harga Material (`price_idr`)**:
   - Untuk **Fastener**:
     $$\text{Price IDR} = \text{Weight} \times \text{Basic Rate} \times \text{Exchange Rate}$$
     *(Fluktuasi JFE tidak berlaku untuk Fastener / bernilai 0).*
   - Untuk **Presspart**:
     $$\text{Effective Rate} = \text{Basic Rate} - \text{Fluktuasi JFE}$$
     $$\text{Price IDR} = \text{Weight} \times \text{Effective Rate} \times \text{Exchange Rate}$$
     *(Contoh dari data: Basic Rate 1.99, Fluktuasi JFE -0.02 $\rightarrow$ Effective Rate = $1.99 - (-0.02) = 2.01$. Weight 0.15, Rate 20.000 $\rightarrow \text{Price IDR} = 6.030$).*

2. **Total Biaya Proses (`total_process_cost`)**:
   $$\text{Total Process Cost} = \sum (\text{Biaya Proses yang Dipilih})$$
   - Contoh Fastener: Heading (100) + Forging (100) + Heat treatment (200) + Inspection (150) = **550**
   - Contoh Presspart: Progressive (100) + Bending 1 (100) + Bending 2 (100) + Forming (100) + Ristrake (100) + Painting (100) + Final Check (100) = **700**

3. **Margin Profit (`profit`)**:
   $$\text{Profit} = 10\% \times (\text{Price IDR} + \text{Total Process Cost})$$
   - Fastener: $10\% \times (2.135 + 550) = \mathbf{268.5}$
   - Presspart: $10\% \times (6.030 + 700) = \mathbf{673}$

4. **Biaya Logistik**:
   - `Transport`: Default 100 (dapat disesuaikan)
   - `Packing`: Default 50 (dapat disesuaikan)

5. **Total Keseluruhan (`total_cost`)**:
   $$\text{Total Cost} = \text{Price IDR} + \text{Total Process Cost} + \text{Profit} + \text{Transport} + \text{Packing}$$
   - Fastener: $2.135 + 550 + 268.5 + 100 + 50 = \mathbf{3.103,5}$
   - Presspart: $6.030 + 700 + 673 + 100 + 50 = \mathbf{7.553}$

---

## 4. Pembagian Fase Kerja (Phase Breakdown) - STATUS: SELESAI (COMPLETED)

### Phase 1: Setup & Migrations [SELESAI]
- [x] Instalasi **Filament PHP v3** dan inisialisasi panel admin (`AdminPanelProvider`).
- [x] Pembuatan database migrations lengkap dengan indexing dan foreign keys:
  - Modifikasi tabel `users` (penambahan field `role`).
  - Migration `global_variables`.
  - Migration `materials`.
  - Migration `processes`.
  - Migration `quotations`.
  - Migration `quotation_processes`.
- [x] Pembuatan Eloquent Models & Relasi (`User`, `GlobalVariable`, `Material`, `Process`, `Quotation`, `QuotationProcess`).
- [x] Pembuatan Database Seeders untuk data awal:
  - User admin, estimator, approver.
  - Global variable awal (`Exchange Rate` 20.000, `Fluktuasi JFE` -0.02).
  - Sample Master Material (`SWMB`, `SPH270-OD`, `SPHC-P`, `S10C`).
  - Sample Master Process (`Heading`, `Forging`, `Progressive`, `Painting`, dll.).
  - Sample Quotations dari Book5.xlsx (`QUO-202610-0001` & `QUO-202610-0002`).

### Phase 2: Master Data Management (Filament Resources) [SELESAI]
- [x] **GlobalVariableResource**: Form & Table untuk kelola Kurs dan Fluktuasi JFE dengan audit trail `updated_by`.
- [x] **MaterialResource**: CRUD Material dengan kategori (`Fastener` vs `Presspart`) dan `basic_rate`.
- [x] **ProcessResource**: CRUD Proses manufaktur dengan kategori dan `default_cost`.
- [x] **UserResource / Role Policies**: Hak akses terisolasi per peran (Admin, Estimator, Approver).

### Phase 3: Kalkulator Quotation Dinamis (Core Feature) [SELESAI]
- [x] **QuotationResource & Calculation Engine**:
  - Live reactive form: Otomatis memuat snapshot Basic Rate, Kurs, dan Fluktuasi JFE saat material dipilih.
  - Form adaptif: Field Diameter untuk Fastener, Thickness & Size Material untuk Presspart.
  - Repeater proses manufaktur dengan pengisian otomatis default cost dan live recalculation.
  - Auto-generate Nomor Dokumen Unik (`QUO-YYYYMM-XXXX`).
- [x] **Quotation Infolist / View Page**: Tampilan dokumen detail visual dan profesional tanpa mode edit.

### Phase 4: Pelaporan, Ekspor Dokumen & Workflow Approval [SELESAI]
- [x] **Workflow Persetujuan**: Aksi `Ajukan` (Estimator), `Approve`, dan `Tolak` (dengan catatan).
- [x] **Ekspor PDF Resmi**: Template dokumen estimasi biaya A4 lengkap dengan rincian biaya dan tanda tangan.
- [x] **Ekspor Excel / CSV**: Tombol unduh rekapitulasi data purchasing dalam format CSV/Excel dengan UTF-8 BOM.

### Phase 5: Audit Trail, Validasi & Pengujian Menyeluruh [SELESAI]
- [x] Unit testing `CostCalculationTest` membuktikan 100% presisi dengan sampel data Excel Book5.xlsx.
- [x] Widget Dashboard: `StatsOverview` (ringkasan status dokumen & kurs) dan `QuotationChart` (distribusi kategori).
- [x] Helper script `serve.bat` untuk kemudahan menjalankan server di lingkungan Windows/Laragon.

