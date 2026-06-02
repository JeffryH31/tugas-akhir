# BAB IV IMPLEMENTASI SISTEM

## 4.1 Teknologi yang Digunakan

Bagian ini menjelaskan teknologi yang digunakan dalam implementasi solusi sistem manajemen proyek berbasis web. Teknologi dipilih berdasarkan kesesuaian dengan kebutuhan fungsional, ekosistem yang matang, serta kemudahan integrasi antar komponen.

---

### 4.1.1 *Technology Stack Diagram*

Diagram berikut menggambarkan lapisan teknologi yang menyusun sistem, mulai dari lapisan antarmuka pengguna hingga basis data dan pengujian. File diagram tersedia di `docs/tech-stack-diagram.drawio`.

**Deskripsi diagram:** Sistem dibangun di atas arsitektur *full-stack* berlapis yang terdiri dari tujuh lapisan utama: lapisan *Client* (Web Browser dan Electron untuk akses *desktop*), lapisan *Frontend* berbasis Vue.js yang berkomunikasi dengan server melalui Inertia.js, lapisan *Build Tools* dengan Vite sebagai *bundler* utama, lapisan *Backend* yang ditenagai Laravel dan PHP, lapisan *Database* menggunakan MySQL dengan akses melalui Eloquent ORM, lapisan *Testing* menggunakan Pest PHP, serta lapisan *Infrastructure/Deployment* berbasis Docker yang mengorkestrasi container Nginx, PHP-FPM, dan MySQL; seluruh komponen dipilih karena memiliki ekosistem yang matang, dokumentasi lengkap, dan kompatibilitas tinggi satu sama lain.

---

### 4.1.2 Bahasa Pemrograman dan *Runtime*

**Tabel 4.1 Bahasa Pemrograman dan *Runtime***

| Komponen | Teknologi | Versi |
|---|---|---|
| Bahasa sisi server | PHP | 8.3.26 |
| *Runtime* JavaScript (*build*) | Node.js | 22.14.0 |
| Manajer paket PHP | Composer | 2.6.5 |
| Manajer paket JS | npm | 10.2.5 |

---

### 4.1.3 *Framework* dan *Library* (Backend)

**Tabel 4.2 *Framework* dan *Library* Backend**

| Perangkat Lunak | Fungsi | Versi |
|---|---|---|
| Laravel | *Framework* aplikasi web utama (*routing*, ORM, validasi, dan sebagainya) | 12.46.0 |
| Laravel Jetstream | *Scaffolding* autentikasi dan manajemen tim | 5.4.0 |
| Laravel Sanctum | Autentikasi berbasis token untuk SPA dan API | 4.2.2 |
| Inertia.js (Laravel adapter) | Jembatan komunikasi antara backend Laravel dan frontend Vue.js tanpa memerlukan API terpisah | 2.0.18 |
| Ziggy | Meneruskan *named routes* Laravel ke sisi JavaScript | 2.6.0 |
| PHPSpreadsheet | Membaca dan menulis berkas *spreadsheet* Excel (impor/ekspor data) | 5.7.0 |
| Laravel Tinker | REPL interaktif untuk eksplorasi dan eksperimen aplikasi | 2.11.0 |

---

### 4.1.4 *Build* dan *Asset* (Frontend)

**Tabel 4.3 *Framework* dan *Library* Frontend**

| Perangkat Lunak | Fungsi | Versi |
|---|---|---|
| Vue.js | *Framework* JavaScript reaktif berbasis komponen untuk antarmuka pengguna | 3.5.26 |
| @inertiajs/vue3 | Adaptor Inertia.js sisi klien untuk Vue 3 | 2.3.8 |
| Vuetify | *Component library* Material Design untuk Vue 3 | 3.11.6 |
| Tailwind CSS | *Framework* CSS *utility-first* | 3.4.19 |
| @tailwindcss/forms | Plugin Tailwind untuk normalisasi *style* elemen form | 0.5.11 |
| @tailwindcss/typography | Plugin Tailwind untuk tipografi konten prosa | 0.5.19 |
| vuedraggable | Komponen *drag-and-drop* berbasis SortableJS untuk Vue 3 | 4.1.0 |
| Axios | *HTTP client* berbasis *promise* untuk request API | 1.13.2 |
| @mdi/font | Paket ikon Material Design Icons | 7.4.47 |
| Sass | *CSS preprocessor* untuk penulisan *style* yang lebih terstruktur | 1.97.2 |
| Vite | *Build tool* dan *bundler* aset frontend | 7.3.1 |
| laravel-vite-plugin | Integrasi Vite dengan Laravel | 2.0.1 |
| @vitejs/plugin-vue | Plugin Vite untuk mendukung *Single File Component* Vue | 5.2.4 |
| @tailwindcss/vite | Plugin Vite untuk integrasi Tailwind CSS v4 | 4.1.18 |
| PostCSS | Pemrosesan CSS pasca-kompilasi (digunakan oleh Tailwind) | 8.5.6 |
| vite-plugin-vuetify | Plugin Vite untuk *tree-shaking* komponen Vuetify | 2.1.3 |

---

### 4.1.5 Pengujian (*Testing*)

**Tabel 4.4 Perangkat Lunak Pengujian**

| Perangkat Lunak | Fungsi | Versi |
|---|---|---|
| Pest PHP | *Framework* pengujian PHP modern dengan sintaks ekspresif | 4.3.1 |
| pestphp/pest-plugin-laravel | Integrasi Pest dengan fitur pengujian Laravel | 4.0.0 |
| Mockery | *Library* untuk pembuatan *mock* dan *stub* objek pada pengujian unit | 1.6.x |

---

### 4.1.6 Paket Pengembangan (*Development*)

**Tabel 4.5 Paket Pengembangan**

| Perangkat Lunak | Fungsi | Versi |
|---|---|---|
| Electron | *Runtime* untuk menjalankan aplikasi sebagai aplikasi *desktop* lintas platform | 35.7.5 |
| Laravel Sail | Lingkungan pengembangan berbasis Docker untuk Laravel | 1.41.x |
| Laravel Pint | *Code style fixer* PHP berdasarkan PSR-12 | 1.24.x |
| Laravel Pail | Penampil log real-time di terminal untuk Laravel | 1.2.x |
| concurrently | Menjalankan beberapa perintah sekaligus di terminal (*dev server*) | 9.2.1 |
| Faker PHP | Pembangkit data palsu untuk keperluan *database seeding* | 1.23.x |

---

### 4.1.7 Infrastruktur dan *Deployment*

Sistem di-*deploy* menggunakan Docker dengan pendekatan *multi-container* yang dikelola oleh Docker Compose. Arsitektur terdiri dari tiga container yang saling terhubung melalui jaringan internal: container **Nginx** sebagai *reverse proxy* yang menerima seluruh *request* dari luar dan meneruskan *request* PHP ke container **PHP-FPM**, serta container **MySQL** sebagai basis data dengan *volume* persisten. Aset statis hasil *build* Vite di-*serve* langsung oleh Nginx tanpa melewati PHP untuk efisiensi maksimal. `Dockerfile` menggunakan strategi *multi-stage build* — *stage* pertama menggunakan image Node.js untuk mengompilasi aset *frontend*, dan *stage* kedua menggunakan image PHP-FPM Alpine yang ringan untuk menjalankan aplikasi Laravel di *production*.

**Tabel 4.6 Infrastruktur dan *Deployment***

| Perangkat Lunak | Fungsi | Versi |
|---|---|---|
| Docker Engine | *Container runtime* untuk menjalankan dan mengisolasi setiap layanan aplikasi | 27.x (latest) |
| Docker Compose | Orkestrasi *multi-container* (Nginx + PHP-FPM + MySQL) menggunakan satu file konfigurasi | 2.x (latest) |
| Nginx | *Reverse proxy* dan *static file server* di depan PHP-FPM | 1.27-alpine |
| PHP-FPM | *Process manager* PHP yang menangani *request* dari Nginx via FastCGI | 8.3-alpine |
| MySQL | Basis data relasional yang berjalan di container terpisah dengan *volume* persisten | 8.0 |

**Struktur file Docker yang dibuat:**

```
tugas-akhir/
├── Dockerfile              # Multi-stage build (Node builder + PHP-FPM)
├── docker-compose.yml      # Orkestrasi 3 container (nginx, php, mysql)
├── .env.docker             # Environment variables khusus production Docker
└── docker/
    └── nginx/
        └── default.conf    # Konfigurasi Nginx untuk Laravel
```

**Cara menjalankan:**

```bash
# 1. Generate APP_KEY terlebih dahulu
docker compose run --rm php php artisan key:generate --show
# Salin output ke APP_KEY di .env.docker

# 2. Build dan jalankan semua container
docker compose up -d --build

# 3. Jalankan migrasi database
docker compose exec php php artisan migrate --force

# 4. Buat symlink storage
docker compose exec php php artisan storage:link
```
