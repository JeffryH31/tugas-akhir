# BAB V PENGUJIAN SISTEM

## 5.1 Unit Test

### 5.1.1 Tujuan Pengujian

Pengujian unit bertujuan untuk memastikan bahwa setiap bagian logika aplikasi menghasilkan hasil yang benar secara mandiri, terlepas dari bagian sistem lainnya. Fokus pengujian ini mencakup kebenaran perhitungan algoritma *Critical Path Method* (CPM) yang digunakan untuk mengidentifikasi jalur kritis proyek, logika pengelolaan subtask seperti validasi kedalaman hierarki, duplikasi, dan pencatatan riwayat perubahan, serta operasi pengelolaan folder seperti pembuatan, pemindahan, dan pengurutan.

### 5.1.2 Cara Pengujian

Pengujian unit dilaksanakan menggunakan *framework* Pest PHP dengan pendekatan *test isolation*, di mana setiap kasus uji berjalan secara mandiri tanpa bergantung pada basis data maupun lapisan HTTP. Data masukan disiapkan langsung di dalam kode pengujian menggunakan objek *plain* atau *mock*, kemudian fungsi yang diuji dipanggil secara langsung dan keluarannya diverifikasi menggunakan *assertion*. Untuk fungsi-fungsi internal yang tidak dapat diakses dari luar kelas, pengujian menggunakan `ReflectionMethod` PHP agar metode tersebut dapat dipanggil tanpa mengubah visibilitas kode produksi.

Modul yang dicakup dalam pengujian ini meliputi: algoritma *Critical Path Method* (CPM) yang menguji pembangunan graf dependensi, deteksi siklus, pengurutan topologis, kalkulasi *forward pass*, *backward pass*, dan identifikasi jalur kritis; logika pengelolaan subtask yang menguji pembuatan hierarki, validasi kedalaman *nesting*, duplikasi, normalisasi tanggal, serta pencatatan riwayat perubahan (*activity log*); pengelolaan folder yang menguji operasi buat, ubah, pindah, dan urutkan; serta logika kalkulasi kemajuan terjadwal (*scheduled progress ratio*) yang menjadi dasar perhitungan metrik *Earned Value Management* (EVM) seperti *Planned Value* (PV) dan *Schedule Performance Index* (SPI).

---

## 5.2 Integration Test

### 5.2.1 Tujuan Pengujian

Pengujian integrasi bertujuan untuk memastikan bahwa seluruh alur kerja aplikasi berjalan dengan benar dari awal hingga akhir — mulai dari pengguna melakukan suatu aksi di aplikasi, diproses oleh sistem, hingga data tersimpan atau diperbarui di basis data. Pengujian ini memverifikasi bahwa fitur-fitur utama seperti pengelolaan task, subtask, komentar, sprint, pencatatan waktu, dan analitik workspace bekerja sebagaimana mestinya ketika semua bagian sistem bekerja bersama.

### 5.2.2 Cara Pengujian

Pengujian integrasi dilaksanakan menggunakan fitur *HTTP testing* bawaan Laravel yang dijalankan melalui Pest PHP. Setiap kasus uji mensimulasikan *HTTP request* nyata ke *endpoint* aplikasi — mulai dari autentikasi pengguna, pengiriman data, hingga penerimaan respons — menggunakan basis data *in-memory* yang di-*refresh* setiap kali satu kasus uji selesai dijalankan, sehingga setiap pengujian selalu dimulai dari kondisi bersih. Sebelum setiap skenario dijalankan, data prasyarat berupa hierarki lengkap (workspace → space → list → task) dibuat secara otomatis menggunakan *helper* yang tersedia, agar kondisi pengujian mencerminkan situasi penggunaan nyata.

Cakupan pengujian meliputi seluruh alur kerja utama aplikasi: pembuatan, pembaruan, dan penghapusan task serta subtask; penambahan dan pengeditan komentar; pembuatan dan pengelolaan sprint; pencatatan dan pembaruan entri waktu; kalkulasi CPM pada task; pengelolaan space dan workspace termasuk manajemen anggota; serta penghasilan data analitik workspace. Setiap kasus uji tidak hanya memverifikasi kode respons HTTP yang dikembalikan, tetapi juga memastikan bahwa perubahan data tersimpan dengan benar di basis data dan bahwa setiap operasi yang relevan menghasilkan catatan riwayat aktivitas (*activity log*) yang sesuai.

---

## 5.3 Security Test

### 5.3.1 Tujuan Pengujian

Pengujian keamanan bertujuan untuk memastikan bahwa sistem menerapkan pembatasan akses secara konsisten, sehingga setiap pengguna hanya dapat mengakses dan mengubah data yang menjadi haknya. Pengujian ini mencakup tiga skenario ancaman utama: percobaan mengakses atau mengubah data milik pengguna lain (*Insecure Direct Object Reference*/IDOR), percobaan melakukan operasi yang tidak sesuai dengan peran pengguna (misalnya anggota biasa mencoba menghapus workspace), serta percobaan menggunakan data dari workspace atau space lain dalam satu operasi (misalnya melampirkan label dari workspace berbeda ke sebuah task).

### 5.3.2 Cara Pengujian

Pengujian keamanan dilaksanakan sebagai *feature test* menggunakan *HTTP testing* Laravel, di mana setiap skenario mensimulasikan percobaan akses tidak sah oleh pengguna yang sudah terautentikasi. Setiap kasus uji menyiapkan dua atau lebih pengguna dengan peran dan kepemilikan data yang berbeda, kemudian salah satu pengguna mencoba melakukan operasi yang seharusnya tidak diizinkan melalui *HTTP request* langsung ke *endpoint* yang bersangkutan. Sistem diharapkan menolak setiap percobaan tersebut dengan respons yang tepat — baik berupa HTTP 403 *Forbidden*, HTTP 404 *Not Found*, maupun *validation error* — dan pengujian memverifikasi bahwa data di basis data tidak mengalami perubahan akibat percobaan akses tidak sah tersebut.

Pengujian IDOR memverifikasi bahwa pengguna tidak dapat memanipulasi komentar, entri waktu, atau task milik pengguna lain meskipun mengetahui ID datanya, termasuk skenario di mana pengguna mencoba menyusupkan ID milik workspace lain ke dalam URL miliknya sendiri. Pengujian otorisasi berbasis peran memverifikasi bahwa operasi sensitif seperti menghapus workspace, mengelola anggota space, dan menambahkan status hanya dapat dilakukan oleh peran yang berwenang (owner, admin workspace, atau admin space sesuai konteksnya). Pengujian validasi lintas-workspace memverifikasi bahwa sistem menolak data yang berasal dari workspace atau space berbeda, misalnya melampirkan label dari workspace lain ke sebuah task, atau menggunakan status dari space yang berbeda.

---

## 5.4 Pengujian dengan Survei Kuesioner *System Usability Scale*

### 5.4.1 Tujuan Pengujian

Pengujian usabilitas dengan metode *System Usability Scale* (SUS) bertujuan untuk mengukur seberapa mudah aplikasi ini digunakan dari sudut pandang pengguna akhir secara kuantitatif. SUS dipilih karena merupakan instrumen yang telah tervalidasi secara ilmiah, ringkas (hanya 10 pernyataan), dan menghasilkan satu angka skor dalam rentang 0–100 yang dapat langsung dibandingkan dengan tolok ukur industri untuk menentukan apakah tingkat kemudahan penggunaan aplikasi sudah memadai.

### 5.4.2 Cara Pengujian

Pengujian dilaksanakan dengan meminta responden untuk mencoba menggunakan aplikasi secara langsung, kemudian mengisi kuesioner SUS. Selama sesi pengujian, responden diminta menyelesaikan serangkaian skenario yang mencerminkan penggunaan nyata aplikasi, meliputi: membuat dan mengelola workspace serta space, membuat dan memperbarui task beserta subtask-nya, menggunakan tampilan sprint dan kalender, mencatat waktu pengerjaan, serta melihat laporan analitik workspace. Setelah menyelesaikan skenario tersebut, responden mengisi 10 pernyataan SUS dengan skala Likert 1–5, di mana pernyataan bernomor ganjil bersifat positif dan pernyataan bernomor genap bersifat negatif.

Skor akhir dihitung menggunakan formula standar SUS: nilai setiap pernyataan ganjil dikurangi 1, nilai setiap pernyataan genap dikurangi dari 5, kemudian seluruh nilai dijumlahkan dan dikalikan 2,5 sehingga menghasilkan skor dalam rentang 0–100. Skor tersebut kemudian diinterpretasikan berdasarkan skala penilaian yang telah ditetapkan, di mana skor di atas 68 dikategorikan di atas rata-rata, skor di atas 80,3 dikategorikan *excellent*, dan skor di atas 90 dikategorikan *best imaginable*.

### 5.4.3 Hasil Pengujian

**Tabel 5.1 Kuesioner *System Usability Scale***

| No | Pernyataan | SS (5) | S (4) | N (3) | TS (2) | STS (1) |
|---|---|---|---|---|---|---|
| 1 | Saya pikir saya akan sering menggunakan sistem ini | | | | | |
| 2 | Saya merasa sistem ini terlalu kompleks | | | | | |
| 3 | Saya merasa sistem ini mudah digunakan | | | | | |
| 4 | Saya membutuhkan bantuan teknis untuk menggunakan sistem ini | | | | | |
| 5 | Saya merasa berbagai fungsi dalam sistem ini terintegrasi dengan baik | | | | | |
| 6 | Saya merasa terlalu banyak ketidakkonsistenan dalam sistem ini | | | | | |
| 7 | Saya merasa kebanyakan orang akan belajar menggunakan sistem ini dengan sangat cepat | | | | | |
| 8 | Saya merasa sistem ini sangat rumit untuk digunakan | | | | | |
| 9 | Saya merasa sangat percaya diri menggunakan sistem ini | | | | | |
| 10 | Saya perlu belajar banyak hal sebelum bisa menggunakan sistem ini | | | | | |

**Tabel 5.2 Rekap Skor SUS per Responden**

| Responden | Jabatan / Peran | Skor SUS | Kategori |
|---|---|---|---|
| R-01 | | | |
| R-02 | | | |
| R-03 | | | |
| R-04 | | | |
| R-05 | | | |
| **Rata-rata** | | | |

> *Catatan: Tabel 5.1 dan 5.2 diisi setelah pelaksanaan survei.*

**Tabel 5.3 Skala Penilaian SUS**

| Rentang Skor | Grade | Kategori |
|---|---|---|
| > 90 | A+ | *Best Imaginable* |
| 85 – 90 | A | *Excellent* |
| 80 – 85 | B | *Excellent* |
| 70 – 80 | C | *Good* |
| 60 – 70 | D | *OK* |
| < 60 | F | *Poor* |
