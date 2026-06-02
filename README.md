# 🌐 TLDix — Domain & Hosting Takip Platformu — v2.2

**TLDix**, alan adı (domain) ve web hosting takibi, WHOIS sorgulama, SSL kontrolü, affiliate yönlendirme ve ödeme sistemi içeren kapsamlı bir web platformudur. PHP + SQLite/MySQL ile çalışır; cPanel ortamına kolayca deploy edilebilir.

---

## 📋 İçindekiler

1. [Sistem Gereksinimleri](#sistem-gereksinimleri)
2. [Klasör Yapısı](#klasör-yapısı)
3. [Kurulum — Yerel Geliştirme](#kurulum--yerel-geliştirme)
4. [Kurulum — cPanel / Sunucu](#kurulum--cpanel--sunucu)
5. [Veritabanı](#veritabanı)
6. [Yapılandırma (config.php)](#yapılandırma-configphp)
7. [Admin Paneli](#admin-paneli)
8. [SMTP E-posta Ayarları](#smtp-e-posta-ayarları)
9. [Cron Görevleri](#cron-görevleri)
10. [Affiliate Sistemi & Tıklama Takibi](#affiliate-sistemi--tıklama-takibi)
11. [Ödeme Sistemi](#ödeme-sistemi)
12. [Kullanıcı Paketleri (Pricing)](#kullanıcı-paketleri-pricing)
13. [Blog Sistemi](#blog-sistemi)
14. [Çok Dil Desteği](#çok-dil-desteği)
15. [Tema Sistemi](#tema-sistemi)
16. [API Dokümantasyonu](#api-dokümantasyonu)
17. [Güvenlik](#güvenlik)
18. [Yayına Almadan Önce Yapılması Gerekenler](#yayına-almadan-önce-yapılması-gerekenler)
19. [API & Affiliate Hesapları — Kurulum Rehberi](#api--affiliate-hesapları--kurulum-rehberi)
20. [Son Değişiklikler](#son-değişiklikler)

---

## ⚙️ Sistem Gereksinimleri

| Gereksinim | Minimum Versiyon |
|------------|-----------------|
| PHP | 7.4+ (8.1+ önerilir) |
| PHP Uzantıları | `pdo_sqlite` veya `pdo_mysql`, `curl`, `openssl`, `mbstring`, `intl` |
| Veritabanı | SQLite 3 (yerel) **veya** MySQL 5.7 / MariaDB 10.3+ |
| Web Sunucusu | Apache (`.htaccess` desteğiyle) veya Nginx |
| cPanel | 84+ |

---

## 📁 Klasör Yapısı

```
TLDix/
├── index.php                   # Ana router (tüm URL'ler buradan yönetilir)
├── config.php                  # Uygulama yapılandırması (DB, SMTP, affiliate vb.)
├── api.php                     # Public JSON API endpoint
├── .htaccess                   # Apache mod_rewrite kuralları
├── database.sqlite             # SQLite veritabanı (SQLite modunda)
│
├── assets/
│   ├── css/
│   │   └── style.css           # Ana stil dosyası (dark/light, glassmorphism, light-mode fixes)
│   ├── js/
│   │   └── app.js              # Frontend JS (tema, dil, etkileşimler)
│   └── images/                 # Statik görseller ve blog resimleri
│
├── includes/
│   ├── db.php                  # PDO bağlantısı + initializeDatabase() çağrısı
│   ├── db_init.php             # Tablo oluşturma SQL sorguları (SQLite + MySQL)
│   ├── auth.php                # Kimlik doğrulama fonksiyonları
│   ├── functions.php           # Yardımcı fonksiyonlar (esc, __, url, formatDate vb.)
│   ├── whois.php               # WHOIS sorgulama motoru
│   └── cron_handler.php        # Cron görevleri: domain yenileme, bildirim e-postaları
│
├── templates/
│   ├── header.php              # HTML <head>, navbar, analitik entegrasyonları
│   ├── footer.php              # Footer: blog linkleri, sosyal medya, linkler
│   ├── home.php                # Anasayfa: domain arama, trend, SSL, blog slider
│   ├── login.php               # Giriş formu
│   ├── dashboard.php           # Kullanıcı paneli (domain & hosting özet)
│   ├── domains_list.php        # Kullanıcının izlediği domainler listesi
│   ├── expiring.php            # Süre dolmak üzere olan domainler
│   ├── domain.php              # Domain detay sayfası (WHOIS, SSL, fiyat karşılaştırma)
│   ├── hosting.php             # Hosting takip paneli
│   ├── integrations.php        # API entegrasyonları + Fiyatlandırma planları
│   ├── admin.php               # Admin paneli — Sidebar navigasyonlu sekmeli yapı
│   ├── domains_for_sale.php    # Domain satış platformları + SSL affiliate kartları
│   ├── trending.php            # Trend domainler
│   ├── blog_list.php           # Tüm blog yazıları listesi
│   ├── blog_detail.php         # Tekil blog yazısı
│   ├── checkout.php            # Ödeme ekranı (Whop + Havale + Kart/PayPal yakında)
│   ├── docs.php                # API dokümantasyonu
│   ├── legal.php               # KVKK / Gizlilik Politikası / Kullanım Koşulları
│   └── modal_add_domain.php    # Domain ekleme modal formu
│
└── languages/
    ├── en.php                  # İngilizce çeviriler (varsayılan)
    ├── tr.php                  # Türkçe çeviriler
    ├── es.php                  # İspanyolca çeviriler
    └── de.php                  # Almanca çeviriler
```

---

## 🚀 Kurulum — Yerel Geliştirme

```bash
cd TLDix
php -S 127.0.0.1:8000
```

İlk sayfa yüklemesinde tüm tablolar otomatik oluşturulur.

**Admin paneli:**
```
http://127.0.0.1:8000/manage-secure-panel
```

---

## 🌍 Kurulum — cPanel / Sunucu

1. Tüm dosyaları `public_html/` içine yükleyin
2. `.htaccess` aktif olmalı (`AllowOverride All`)
3. `database.sqlite` için yazma izni: `chmod 666`

---

## 🗄️ Veritabanı

### Tablolar

| Tablo | Açıklama |
|-------|----------|
| `settings` | Anahtar-değer ayarlar |
| `domains` | İzlenen domainler |
| `users` | Kayıtlı kullanıcılar |
| `user_domains` | Kullanıcı ↔ domain ilişkisi |
| `followers` | Takip abonelikleri |
| `user_hostings` | Hosting kayıtları |
| `blog_posts` | Blog yazıları |
| `affiliate_clicks` | Affiliate tıklama logları (UTM, referrer dahil) |
| `api_connections` | Kullanıcı API entegrasyonları |
| `notifications` | Kullanıcı bildirimleri |
| `payments` | Ödeme kayıtları (Whop + havale) |

### `payments` Tablosu

```sql
CREATE TABLE payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    plan TEXT NOT NULL,          -- bronze / silver / gold
    amount REAL NOT NULL,
    currency TEXT DEFAULT 'USD',
    method TEXT NOT NULL,        -- wire / whop / card / paypal
    status TEXT DEFAULT 'pending', -- pending / confirmed / rejected
    reference TEXT,              -- Dekont no veya Whop order ID
    notes TEXT,
    whop_order_id TEXT,
    created_at TEXT,
    confirmed_at TEXT
);
```

---

## 🔧 Yapılandırma (config.php)

```php
return [
    // Site
    'site_title'       => 'TLDix',
    'site_description' => 'Alan adı & hosting takip platformu',
    
    // Veritabanı
    'db_type'          => 'sqlite',  // 'sqlite' veya 'mysql'
    
    // Admin
    'admin_email'      => 'emre.ce@gmail.com',
    'admin_password'   => '...hash...',  // password_hash() ile
    
    // Affiliate — her biri /go?to=KEY üzerinden
    'affiliate_namecheap'  => 'https://...',
    'affiliate_godaddy'    => 'https://...',
    // ... (diğerleri admin panelinden yönetilir)
    
    // SMTP
    'smtp_host'        => '',
    'smtp_port'        => '587',
    
    // Whop
    'whop_api_key'         => '',
    'whop_link_bronze'     => 'https://whop.com/checkout/...',
    
    // Banka
    'bank_name'            => '',
    'bank_iban'            => '',
];
```

---

## 🛡️ Admin Paneli

**URL:** `https://yourdomain.com/manage-secure-panel`

### Sekmeler (Sol Sidebar Navigasyon)

| Sekme | İçerik |
|-------|--------|
| 📊 **Dashboard** | Genel istatistikler, bekleyen ödemeler, hızlı erişim |
| ⚙️ **Genel & SEO** | Site başlığı, açıklama, SEO meta, OG Image |
| 📧 **E-posta & SMTP** | SMTP yapılandırması ve test e-postası |
| 📢 **Reklam & Entegrasyon** | Google Search Console, GA4, GTM, AdSense, Bing, Cloudflare Analytics, özel head kodu |
| 🔗 **Affiliate URL'leri** | Tüm sağlayıcı affiliate linkleri |
| 💳 **Ödemeler** | Banka bilgileri, Whop.com yapılandırması, ödeme kayıtları tablosu |
| 📈 **Affiliate İstatistik** | Sağlayıcı bazlı özet + son 50 tıklama detayı |
| 👥 **Üyeler** | Kullanıcı listesi, plan değiştirme, kullanıcı silme |
| 🌐 **Domainler** | Trend domainleri yönetme (ekle / sil) |
| 🔌 **Entegrasyon Kodları** | Cloudflare API, WhoisXML API, Webhook |

---

## 📧 SMTP E-posta Ayarları

Gmail SMTP örneği:
```
Host: smtp.gmail.com
Port: 587
User: hesabiniz@gmail.com
Pass: Google App Password (Uygulama Şifresi)
```

---

## ⏰ Cron Görevleri

```bash
# cPanel'de her gün 02:00'de:
0 2 * * * curl -s "https://yourdomain.com/cron" >> /dev/null 2>&1
```

---

## 🔗 Affiliate Sistemi & Tıklama Takibi

Tüm linkler `/go?to=PROVIDER` üzerinden yönlendirilir. Desteklenen kodlar:

**Domain:** `namecheap`, `godaddy`, `namesilo`, `porkbun`, `dynadot`, `spaceship`, `domainnameapi`  
**Hosting:** `hostinger`, `bluehost`, `siteground`, `kinsta`, `wpengine`, `interserver`  
**SSL:** `namecheap_ssl`, `ssls`, `ssldragon`  
**Satış:** `afternic`, `sedo`, `dan`, `atom`, `dynadot_mkt`

UTM parametresi: `/go?to=namecheap&utm_source=renewal_cta`

---

## 💰 Ödeme Sistemi

### Nasıl Çalışır

1. Kullanıcı `/checkout?plan=bronze` sayfasına gider
2. **Whop.com** linkine tıklar veya **Havale/EFT** bilgilerini görerek para gönderir
3. Havale için dekont numarasını sisteme girer → `payments` tablosuna `pending` kaydı oluşur
4. Admin `/manage-secure-panel?tab=payments` sayfasından ödemeyi görür ve **Onayla** butonuna tıklar
5. Plan otomatik aktif edilir

### Whop.com Kurulumu

1. **whop.com**'da ücretsiz hesap açın
2. "New Product" → 3 ayrı ürün oluşturun (Bronze $9, Silver $29, Gold $99)
3. Her ürün için **recurring subscription** seçin
4. Her ürünün checkout linkini kopyalayın
5. Admin → Ödemeler sekmesine yapıştırın
6. API Keys bölümünden API key alın → admin panele yapıştırın
7. Webhook URL'i Whop'a tanımlayın: `https://yourdomain.com/webhook/whop`

> **Not:** Whop Webhook otomasyonu henüz aktif değil. Ödeme onayları şu an manuel yapılmaktadır.

### Premium Kısıtlama

- **FREE plan** kullanıcıları: Maksimum 5 domain, CSV export yok, webhook yok
- **Kullanıcılar kendi planlarını değiştiremez** — sadece admin değiştirebilir veya ödeme onaylandığında otomatik aktif olur
- `panel_integrations_upgrade` rotası kilitli: Her istek `/checkout` yönlendirmesi yapar

---

## 💎 Kullanıcı Paketleri

| Plan | Fiyat | Domain | Özellikler |
|------|-------|--------|------------|
| **FREE** | Ücretsiz | 5 | Temel sorgular, WHOIS, SSL, dolum takibi |
| **BRONZE** | $9/ay | 50 | + CSV export, 5 webhook, 30 gün geçmiş |
| **SILVER** | $29/ay | 500 | + 50 webhook, 1 yıl geçmiş, öncelikli kuyruk |
| **GOLD** | $99/ay | Sınırsız | + Sınırsız her şey, SLA & premium destek |

---

## 📝 Blog Sistemi

100 adet SEO odaklı içerik. `/blog` — tüm yazılar, `/blog/slug` — tekil.

---

## 🌍 Çok Dil Desteği

`en`, `tr`, `es`, `de` — header'dan küre ikonu ile değiştirilir.

---

## 🎨 Tema Sistemi

**Dark / Light / System** — `localStorage`'da saklanır. Light mode'da tüm başlıklar, kart metinleri ve alan adları otomatik koyu renkte görünür (`html[data-theme="light"]` kapsamlı CSS kuralları).

Hero animasyonu: `.hero-content` sayfa açılışında 32px alttan yukarı slide-up (0.7s).

---

## 📡 API Dokümantasyonu

```bash
curl "https://yourdomain.com/api.php?domain=example.com&key=YOUR_API_KEY"
```

Tam dokümantasyon: `/docs`

---

## 🔐 Güvenlik

- SQL Injection: PDO prepared statements
- XSS: `esc()` ile tüm çıktılar sanitize
- Admin URL gizli: `/manage-secure-panel`
- Şifreler: `password_hash()` (bcrypt)
- Kullanıcılar kendi planlarını değiştiremez
- Template dosyaları doğrudan erişime karşı korumalı

---

## ✅ Yayına Almadan Önce Yapılması Gerekenler

### Zorunlu
- [ ] `config.php` → `admin_password` için gerçek hash oluşturun (`php -r "echo password_hash('SIFRENIZ', PASSWORD_BCRYPT);"`)
- [ ] SMTP ayarlarını yapılandırın ve test e-postası gönderin
- [ ] SSL sertifikası kurun (HTTPS zorunlu)
- [ ] `database.sqlite` dosyasını `public_html` dışına taşıyın ve `config.php`'de yolu güncelleyin
- [ ] `.htaccess` — `database.sqlite` ve `config.php`'ye web erişimini engelleyin
- [ ] `display_errors = Off` — `php.ini` veya `.htaccess` ile

### Önerilen
- [ ] Google Search Console doğrulama kodu ekleyin (admin → Reklam & Entegrasyon)
- [ ] Google Analytics 4 Measurement ID ekleyin
- [ ] Whop.com üzerinden en az 1 plan oluşturun ve linkini admin panele ekleyin
- [ ] En az 1 affiliate link ekleyin (Namecheap ile başlayabilirsiniz)
- [ ] Banka bilgilerini admin → Ödemeler sekmesine girin
- [ ] Cron görevi kurun
- [ ] Blog içeriklerinin görsellerini kontrol edin
- [ ] robots.txt ve sitemap oluşturun

---

## 🔑 API & Affiliate Hesapları — Kurulum Rehberi

### Affiliate Hesapları (Gelir için)

| Platform | Ne Yapacaksınız | Nereye Ekleyeceksiniz |
|----------|----------------|----------------------|
| **Namecheap** | namecheap.com/affiliates/ → kayıt | Admin → Affiliate → Namecheap URL |
| **GoDaddy** | godaddy.com/affiliate-programs | Admin → Affiliate → GoDaddy URL |
| **Hostinger** | hostinger.com/affiliate | Admin → Affiliate → Hostinger URL |
| **SiteGround** | siteground.com/affiliates.htm | Admin → Affiliate → SiteGround URL |
| **Kinsta** | kinsta.com/affiliates | Admin → Affiliate → Kinsta URL |
| **Afternic** | afternic.com (GoDaddy hesabıyla) | Admin → Affiliate → Afternic URL |
| **Sedo** | sedo.com/us/affiliates/ | Admin → Affiliate → Sedo URL |
| **SSLs.com** | ssls.com/affiliates | Admin → Affiliate → SSLs URL |

### API Hesapları (Sistem fonksiyonları için)

| Platform | Ne İçin | Ücretsiz mi | Nereye Ekleyeceksiniz |
|----------|---------|------------|----------------------|
| **WhoisXML API** | WHOIS sorguları | 500 sorgu/ay ücretsiz | Admin → Entegrasyon Kodları → WhoisXML API Key |
| **Google Analytics** | Ziyaretçi takibi | Ücretsiz | Admin → Reklam & Entegrasyon → GA4 ID |
| **Google Search Console** | SEO takibi | Ücretsiz | Admin → Reklam & Entegrasyon → Search Console kodu |
| **Whop.com** | Ödeme almak | Komisyon (%3) | Admin → Ödemeler → Whop API Key + Plan Linkleri |

### Önemli Not: Cloudflare Registrar

Cloudflare Registrar affiliate programı **yoktur** (at-cost satıyor). Ekrana sürüklemeyin.

---

## 🚀 Son Değişiklikler

### Mayıs 2026 — v2.2

**Admin Panel Yenilendi:**
- Sol sidebar navigasyonlu 9 sekmeli yapı (Dashboard, Genel/SEO, E-posta, Reklam/Entegrasyon, Affiliate, Ödemeler, Affiliate İstatistik, Üyeler, Domainler, Entegrasyon Kodları)
- Light mode tam uyumlu admin arayüzü

**Google Servisleri Entegrasyonu:**
- Google Search Console doğrulama kodu
- Google Analytics 4 (GA4)
- Google Tag Manager
- Google AdSense Publisher ID
- Bing Webmaster doğrulaması
- Cloudflare Web Analytics
- Özel `<head>` kodu alanı
- OG Image URL desteği

**Ödeme Sistemi:**
- `payments` tablosu eklendi
- Whop.com entegrasyonu (plan bazlı linkler)
- Havale/EFT banka bilgileri admin panelinden yönetilebilir
- Ödeme bildirimi formu (kullanıcı dekont no giriyor)
- Admin ödeme onaylama / reddetme arayüzü
- Ödeme onaylandığında plan otomatik aktif

**Güvenlik:**
- `panel_integrations_upgrade` rotası kapatıldı — kullanıcılar kendi planlarını değiştiremez
- Sadece admin veya ödeme onayı ile plan değişir

**Kullanıcı Yönetimi:**
- Tüm eski kullanıcılar silindi
- Yeni admin kullanıcısı: `emre@kominikee.com` / `161224.Dora` — GOLD plan, tam yetkili
- Admin panelinden üye silme ve plan değiştirme arayüzü

**Light Mode:**
- Admin paneli tam light mode uyumlu CSS değişkenleri
- Checkout sayfası light mode form fix

---

*Son güncelleme: Mayıs 2026 (v2.2)*
