<?php
// Turkish Beginners Guide Blog Posts (Dynadot-inspired)
return [
    'beginners-guide-to-domain-names' => [
        'slug' => 'beginners-guide-to-domain-names',
        'title' => 'Yeni Başlayanlar İçin Alan Adı Rehberi',
        'description' => 'Alan adı nedir, web hosting ile arasındaki farklar nelerdir ve neden dijital markanızın temelidir? Temel bilgileri öğrenin.',
        'category' => 'Domains',
        'image' => 'assets/images/blog/domain_tracking.png',
        'date' => '2026-05-30',
        'content' => '<h2>Alan Adlarının Temel Mantığı</h2>
<p>Bir web sitesi kurmayı, çevrimiçi bir mağaza açmayı veya bir blog başlatmayı planlıyorsanız, karşılaşacağınız ilk terim "alan adı" (domain) olacaktır. Peki bu tam olarak nedir? Basitçe söylemek gerekirse alan adı, insanların web sitenizi ziyaret etmek için tarayıcı adres çubuğuna yazdıkları adrestir. Örneğin, <code>domainawait.com</code> bir alan adıdır.</p>

<h3>Alan Adı ve Web Hosting Farkı: Ev Analojisi</h3>
<p>Yeni başlayanların en sık karıştırdığı konulardan biri alan adı ile web hosting (barındırma) arasındaki farktır. Bunu anlamak için web sitenizi fiziksel bir ev olarak düşünün:</p>
<ul>
    <li><strong>Alan Adı (Domain):</strong> Bu sizin ev adresinizdir. İnsanlara sizi bulmaları için nereye gitmeleri gerektiğini söyler.</li>
    <li><strong>Web Hosting:</strong> Bu fiziksel evin kendisidir. Web sitenizin tüm dosyalarının, resimlerinin ve veritabanlarının depolandığı sunucu alanıdır.</li>
    <li><strong>Site Dosyaları:</strong> Bunlar evin içindeki mobilyalar ve dekorasyonlardır.</li>
</ul>
<p>Bir web sitesinin çalışabilmesi için hem alan adına hem de web hosting hizmetine ihtiyacınız vardır. Bunlar ayrı hizmetlerdir ancak el ele çalışırlar.</p>

<h3>Doğru Alan Adı Nasıl Seçilir?</h3>
<p>Doğru alan adını seçmek, arama motoru optimizasyonu (SEO) ve marka bilinirliği için hayati önem taşır. İşte birkaç hızlı ipucu:</p>
<ol>
    <li>Kısa, akılda kalıcı ve yazımı kolay bir isim tercih edin.</li>
    <li>Mümkünse en çok tanınan uzantı olan <code>.com</code> uzantısını tercih edin.</li>
    <li>Tire işaretleri, çift harfler ve sayılar kullanmaktan kaçının.</li>
    <li>Başkalarının markasını ihlal etmediğinizden emin olmak için marka tescillerini kontrol edin.</li>
</ol>'
    ],
    'how-dns-works-beginners-guide' => [
        'slug' => 'how-dns-works-beginners-guide',
        'title' => 'Alan Adı Sistemi (DNS) Nasıl Çalışır?',
        'description' => 'DNS, Nameserver, A Kayıtları ve IP Yönlendirmesini Anlama. Tarayıcınızın alan adlarını sunucu konumlarına nasıl dönüştürdüğünü öğrenin.',
        'category' => 'Technology',
        'image' => 'assets/images/blog/dns_security.png',
        'date' => '2026-05-30',
        'content' => '<h2>DNS Altyapısını Anlamak</h2>
<p>Bilgisayarlar, IP adresleri adı verilen sayılarla (örneğin <code>142.250.190.46</code>) iletişim kurar. Ancak insanların bu uzun sayı listelerini hatırlaması zordur. Alan Adı Sistemi (DNS), alan adlarını (örneğin <code>google.com</code>) bu IP adresleriyle eşleştirerek bu sorunu çözer. DNS\'i internetin telefon rehberi olarak düşünebilirsiniz.</p>

<h3>Adım Adım DNS Çözümleme Süreci</h3>
<p>Tarayıcınıza bir alan adı yazdığınızda, arka planda milisaniyeler içinde karmaşık bir istek zinciri gerçekleşir:</p>
<ol>
    <li><strong>İstek:</strong> Tarayıcınız yönlendiricinize (router) sorar, o da internet servis sağlayıcınız veya Cloudflare\'in 1.1.1.1 gibi genel hizmetleri tarafından çalıştırılan bir DNS Çözümleyiciye (Resolver) danışır.</li>
    <li><strong>Kök Sunucular (Root Servers):</strong> Çözümleyici, ilgili uzantıyı (örneğin .com) kimin yönettiğini bulmak için Kök Ad Sunucusuna danışır.</li>
    <li><strong>TLD Sunucuları:</strong> Kök sunucu, o uzantı altındaki tüm alan adlarının verilerini tutan TLD Ad Sunucusunu işaret eder.</li>
    <li><strong>Yetkili Ad Sunucuları (Nameservers):</strong> TLD sunucusu, kayıt firmanızın veya hosting sağlayıcınızın Ad Sunucularını (örneğin <code>ns1.hostinger.com</code>) işaret eder.</li>
    <li><strong>IP Çözümleme:</strong> Ad sunucularınız aktif DNS bölge dosyasına (örneğin <strong>A Kaydı</strong>) bakar ve hedef sunucunun IP adresini tarayıcınıza döndürür.</li>
</ol>

<h3>Bilinmesi Gereken Temel DNS Kayıt Türleri</h3>
<ul>
    <li><strong>A Kaydı (A Record):</strong> Alan adınızı doğrudan bir IPv4 hosting sunucusuna yönlendirir.</li>
    <li><strong>AAAA Kaydı:</strong> Alan adınızı doğrudan bir IPv6 hosting sunucusuna yönlendirir.</li>
    <li><strong>CNAME (Canonical Name):</strong> Bir alt alan adını (örneğin <code>www.domaininiz.com</code>) başka bir alan adına yönlendirir.</li>
    <li><strong>MX Kaydı (MX Record):</strong> E-posta trafiğini doğru e-posta sunucusuna (örneğin Google Workspace) yönlendirir.</li>
    <li><strong>TXT Kaydı:</strong> Sahiplik doğrulaması ve e-posta güvenlik kayıtları (SPF, DKIM) için kullanılır.</li>
</ul>'
    ],
    'understanding-domain-extensions' => [
        'slug' => 'understanding-domain-extensions',
        'title' => 'Alan Adı Uzantılarını Anlamak: TLD, gTLD ve ccTLD Nedir?',
        'description' => '.com, ülke kodlu alan adları ve yeni jenerik uzantılar arasındaki farklar. Markanız için hangi uzantının en iyisi olduğunu öğrenin.',
        'category' => 'Domains',
        'image' => 'assets/images/blog/domain_tracking.png',
        'date' => '2026-05-30',
        'content' => '<h2>TLD Nedir?</h2>
<p>Üst Düzey Alan Adı (TLD), bir alan adının en sonunda, son noktadan sonra gelen uzantıdır. <code>domainawait.com</code> adresinde TLD uzantısı <code>.com</code>\'dur. İnternet artık kullanım amaçlarına ve bölgelere göre kategorize edilmiş binlerce farklı uzantıyı desteklemektedir.</p>

<h3>Ana Uzantı Kategorileri</h3>
<ol>
    <li><strong>gTLD (Jenerik Üst Düzey Alan Adları):</strong> Bunlar standart, genel uzantılardır. En bilinenleri <code>.com</code> (ticari), <code>.net</code> (ağ) ve <code>.org</code> (organizasyon) uzantılarıdır. Yıllar içinde ICANN, <code>.app</code>, <code>.dev</code>, <code>.shop</code> ve <code>.tech</code> gibi yüzlerce yeni gTLD yayınlamıştır.</li>
    <li><strong>ccTLD (Ülke Kodlu Üst Düzey Alan Adları):</strong> Belirli ülkelere veya bölgelere ayrılmış uzantılardır. Örnek olarak <code>.tr</code> (Türkiye), <code>.de</code> (Almanya) ve <code>.uk</code> (Birleşik Krallık) verilebilir. Bazı ülke uzantıları küresel olarak da popülerleşmiştir, örneğin Kolombiya\'nın <code>.co</code> uzantısı veya Tuvalu\'nun <code>.tv</code> uzantısı gibi.</li>
</ol>

<h3>Hangi Uzantıyı Seçmelisiniz?</h3>
<p>Uluslararası markalaşma ve genel güvenilirlik için klasik <code>.com</code> altın standarttır. Akılda kalıcılığı en yüksek uzantıdır. Ancak yerel bir işletme işletiyorsanız, ülkenizin ccTLD uzantısını kullanmak (örneğin <code>.com.tr</code> veya <code>.de</code>) yerel arama motoru sıralamalarını iyileştirebilir ve ziyaretçi güvenini artırabilir.</p>'
    ],
    'domain-security-best-practices' => [
        'slug' => 'domain-security-best-practices',
        'title' => 'Alan Adı Varlıklarınızı Koruma: Temel Güvenlik Önlemleri',
        'description' => 'Alan adlarınızı çalınma ve izinsiz transfer edilmeye karşı güvende tutun. Transfer kilitleri, WHOIS gizliliği ve 2FA hakkında bilgi edinin.',
        'category' => 'Security',
        'image' => 'assets/images/blog/dns_security.png',
        'date' => '2026-05-30',
        'content' => '<h2>Alan Adı Güvenliği Neden Önemlidir?</h2>
<p>Alan adınız, çevrimiçi işletmenizin en değerli dijital varlığıdır. Kötü niyetli bir kişi alan adı kayıt hesabınıza erişim sağlarsa web trafiğinizi yönlendirebilir, e-postalarınızı ele geçirebilir ve müşteri verilerinizi çalabilir. Yetkisiz transferleri önlemek için güçlü güvenlik protokolleri uygulamak şarttır.</p>

<h3>1. Transfer Kilidini (Registrar Lock) Etkinleştirin</h3>
<p>Bir transfer kilidi (WHOIS verilerinde <code>clientTransferProhibited</code> durumu olarak görünür), alan adınızın izniniz olmadan başka bir kayıt firmasına transfer edilmesini engeller. Bu kilidin kayıt kuruluşu ayarlarınızda her zaman açık olduğundan emin olun.</p>

<h3>2. WHOIS Gizlilik Korumasını Aktif Edin</h3>
<p>Bir alan adı tescil ettiğinizde, iletişim bilgileriniz (isim, adres, e-posta, telefon) herkese açık bir veritabanına kaydedilir. Spam göndericiler bu veritabanını düzenli olarak tarar. WHOIS Gizliliği, kişisel bilgilerinizi maskeleyerek sizi sosyal mühendislik saldırılarından korur.</p>

<h3>3. İki Faktörlü Kimlik Doğrulama (2FA) Kullanın</h3>
<p>Alan adı tescil hesabınızı (Namecheap, Dynadot vb.) Google Authenticator veya bir donanım anahtarı gibi uygulamalar üzerinden İki Faktörlü Kimlik Doğrulama ile güvence altına alın. Bu sayede şifreniz ele geçirilse bile hesabınız korunur.</p>'
    ],
    'buying-selling-domains-aftermarket' => [
        'slug' => 'buying-selling-domains-aftermarket',
        'title' => 'İkincil Alan Adı Piyasasından (Aftermarket) Domain Alım Satımı',
        'description' => 'Domain açık artırmaları, premium ikincil satış pazarları ve aracı ağları nasıl çalışır? Önceden tescil edilmiş alan adlarını güvenle nasıl alacağınızı öğrenin.',
        'category' => 'Domains',
        'image' => 'assets/images/blog/cloud_hosting.png',
        'date' => '2026-05-30',
        'content' => '<h2>Alan Adı İkincil Piyasası (Aftermarket) Nedir?</h2>
<p>Domain aftermarket, daha önceden tescil edilmiş alan adlarının alınıp satıldığı ikincil piyasadır. İstediğiniz alan adı başkası tarafından alınmışsa, kötü bir alternatif seçmek zorunda değilsiniz. Bunun yerine, aracı pazarlardan alan adını mevcut sahibinden satın almayı deneyebilirsiniz.</p>

<h3>Kullanabileceğiniz Başlıca Aftermarket Platformları</h3>
<ul>
    <li><strong>Namecheap Pazarı:</strong> Güvenli iç emanet sistemiyle uygun maliyetli alan adları alıp satmak için harika bir başlangıç noktasıdır.</li>
    <li><strong>Afternic:</strong> GoDaddy tarafından desteklenen bu ağ, alan adlarınızı onlarca kayıt kuruluşunda listeleyerek ilanlarınıza küresel görünürlük kazandırır.</li>
    <li><strong>Sedo:</strong> Domain açık artırmaları, park etme gelirleri ve profesyonel aracı pazarlıkları sunan premium bir küresel platformdur.</li>
    <li><strong>Dan.com:</strong> Alıcıların premium alan adlarını aylık taksitlerle ödemelerine olanak tanıyan şeffaf kiralama (lease-to-own) planlarıyla tanınır.</li>
    <li><strong>Atom (Eski adıyla Squadhelp):</strong> Özel logo paketleri ile birleştirilmiş, marka değeri yüksek premium alan adları sunan küratörlü bir pazardır.</li>
    <li><strong>Dynadot Pazarı:</strong> Süresi dolan alan adı açık artırmaları, indirimli satışlar ve kullanıcılar arası pazar işlemlerini içerir.</li>
</ul>

<h3>Domain İşlemlerinde Güvenliği Sağlamak</h3>
<p>İkincil piyasadan alan adı satın alırken, her zaman güvenli bir emanet (escrow) hizmeti kullanın (Sedo, Dan ve Atom bünyesinde yerleşik olarak bulunur). Emanet yetkilisi, satıcı alan adını başarıyla transfer edene kadar ödemenizi bloke eder, böylece dolandırıcılığı önler.</p>'
    ]
];
