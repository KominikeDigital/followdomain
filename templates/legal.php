<?php
// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit('Direct access not allowed.');
}

global $lang;

$isPrivacy = ($route === 'privacy');
$title = $isPrivacy ? __('legal_privacy_title') : __('legal_terms_title');
$lastUpdated = __('legal_last_updated');
?>

<div class="legal-page-container" style="max-width: 800px; margin: 3rem auto; padding: 0 1rem;">
    <div class="glass-panel" style="padding: 2.5rem; border-radius: 12px; line-height: 1.65; color: var(--color-text-secondary);">
        
        <div style="text-align: center; margin-bottom: 2rem; border-bottom: 1px solid var(--color-border); padding-bottom: 1.5rem;">
            <h1 style="font-family: var(--font-display); color: #ffffff; font-size: 2rem; margin-bottom: 0.5rem;"><?php echo esc($title); ?></h1>
            <p class="text-muted" style="font-size: 0.85rem; font-style: italic;"><?php echo esc($lastUpdated); ?></p>
        </div>

        <div class="legal-content" style="font-size: 0.95rem;">
            <?php if ($lang === 'tr'): ?>
                <?php if ($isPrivacy): ?>
                    <!-- Turkish Privacy Policy -->
                    <h3 style="color: #ffffff; margin-top: 1.5rem;">1. Toplanan Veriler</h3>
                    <p>TLDix hizmetlerini kullanırken, hesabınızı yönetmek ve hizmet kalitesini sürdürmek amacıyla aşağıdaki bilgileri topluyoruz:</p>
                    <ul>
                        <li>Kayıt bilgileri (Kullanıcı adı, e-posta adresi ve şifre karması).</li>
                        <li>Takip ettiğiniz alan adları ve hosting sunucu detayları.</li>
                        <li>API entegrasyonu için webhook adresiniz ve istek sayıları.</li>
                        <li>İşlem ve ödeme kayıtları (Kredi kartı bilgileri sistemimizde depolanmaz).</li>
                    </ul>

                    <h3 style="color: #ffffff; margin-top: 1.5rem;">2. Verilerin Kullanımı</h3>
                    <p>Toplanan bilgiler, size alan adı ve hosting süre aşımı bildirimleri göndermek, API limitlerinizi denetlemek ve müşteri destek taleplerinizi yönetmek için kullanılır. E-posta adresiniz asla üçüncü şahıslarla paylaşılmaz veya satılmaz.</p>

                    <h3 style="color: #ffffff; margin-top: 1.5rem;">3. Veri Güvenliği</h3>
                    <p>Sistemimizdeki tüm veriler, yetkisiz erişim, değiştirme veya ifşa edilmeye karşı endüstri standardı güvenlik protokolleri (SSL/TLS, şifreli şifre hash'leri ve düzenli veri tabanı yedeklemeleri) kullanılarak korunmaktadır.</p>

                    <h3 style="color: #ffffff; margin-top: 1.5rem;">4. Çerezler (Cookies)</h3>
                    <p>Oturum yönetimi ve kullanıcı tercihlerini (dil ve koyu/açık tema) hatırlamak için tarayıcınızda temel çerezler depolanmaktadır.</p>
                <?php else: ?>
                    <!-- Turkish Terms of Service -->
                    <h3 style="color: #ffffff; margin-top: 1.5rem;">1. Hizmet Tanımı</h3>
                    <p>TLDix, kullanıcılarına alan adı (domain) ve hosting sürelerini izleme, WHOIS sorgulama ve REST API aracılığıyla geliştirici araçları sağlama amaçlı bir SaaS platformudur.</p>

                    <h3 style="color: #ffffff; margin-top: 1.5rem;">2. Kullanım Koşulları ve Sorumluluk</h3>
                    <p>Sistemimiz, süre dolumlarını e-posta ve webhook bildirimleri aracılığıyla takip etmenizi kolaylaştırır. Ancak, e-posta servislerindeki gecikmeler, spam filtreleri veya üçüncü taraf sunuculardaki aksaklıklar sebebiyle kaçırılan alan adı yenilemelerinden TLDix hiçbir şekilde sorumlu tutulamaz. Kritik alan adlarınızı manuel olarak da kontrol etmeniz önerilir.</p>

                    <h3 style="color: #ffffff; margin-top: 1.5rem;">3. Hesap Paketleri ve API Limitleri</h3>
                    <p>Her üyelik paketi (Free, Bronze, Silver, Gold) belirli alan adı takip limitleri ve sorgu limitleri içerir. Kayıt olurken veya panelinizden seçeceğiniz paketlere uygulanan limitlerin aşılması durumunda hizmet geçici olarak durdurulabilir veya ek paket satın almanız istenebilir.</p>

                    <h3 style="color: #ffffff; margin-top: 1.5rem;">4. Kötüye Kullanım</h3>
                    <p>Sistem kaynaklarını (API servisleri, WHOIS sorgulayıcıları) botlarla sabote etmek, aşırı yüklemek veya güvenlik açıklarını taramak hesabınızın kalıcı olarak askıya alınmasına sebep olur.</p>
                <?php endif; ?>

            <?php elseif ($lang === 'es'): ?>
                <?php if ($isPrivacy): ?>
                    <!-- Spanish Privacy Policy -->
                    <h3 style="color: #ffffff; margin-top: 1.5rem;">1. Datos Recopilados</h3>
                    <p>Al utilizar los servicios de TLDix, recopilamos la siguiente información para gestionar su cuenta y mantener la calidad del servicio:</p>
                    <ul>
                        <li>Información de registro (nombre de usuario, dirección de correo electrónico y hash de contraseña).</li>
                        <li>Nombres de dominio y detalles de servidores de alojamiento que usted sigue.</li>
                        <li>Su dirección de webhook para la integración de la API y estadísticas de solicitudes.</li>
                        <li>Registros de transacciones y pagos (los datos de su tarjeta de crédito no se almacenan en nuestros sistemas).</li>
                    </ul>

                    <h3 style="color: #ffffff; margin-top: 1.5rem;">2. Uso de Datos</h3>
                    <p>La información recopilada se utiliza para enviarle notificaciones de vencimiento de dominios y hosting, monitorear los límites de su API y gestionar solicitudes de soporte. Su dirección de correo electrónico nunca se comparte ni se vende a terceros.</p>

                    <h3 style="color: #ffffff; margin-top: 1.5rem;">3. Seguridad de los Datos</h3>
                    <p>Todos los datos en nuestro sistema están protegidos utilizando protocolos de seguridad estándar de la industria (SSL/TLS, hashes de contraseñas encriptados y copias de seguridad de bases de datos periódicas) contra el acceso no autorizado, alteración o divulgación.</p>
                <?php else: ?>
                    <!-- Spanish Terms of Service -->
                    <h3 style="color: #ffffff; margin-top: 1.5rem;">1. Descripción del Servicio</h3>
                    <p>TLDix es una plataforma SaaS diseñada para permitir a los usuarios rastrear el vencimiento de dominios y hosting, realizar consultas WHOIS y utilizar herramientas de desarrollo a través de una API REST.</p>

                    <h3 style="color: #ffffff; margin-top: 1.5rem;">2. Condiciones de Uso y Responsabilidad</h3>
                    <p>Nuestro sistema le ayuda a realizar el seguimiento a través de correo electrónico y notificaciones de webhook. Sin embargo, TLDix no se hace responsable de las renovaciones de dominio fallidas debido a retrasos en el correo, filtros de spam o problemas con servidores de terceros. Se recomienda verificar manualmente sus dominios críticos.</p>

                    <h3 style="color: #ffffff; margin-top: 1.5rem;">3. Planes de Cuenta y Límites de API</h3>
                    <p>Cada plan (Free, Bronze, Silver, Gold) incluye ciertos límites de seguimiento y límites de API. Si supera estos límites, su acceso a la API puede suspenderse temporalmente hasta la actualización de su plan.</p>
                <?php endif; ?>

            <?php elseif ($lang === 'de'): ?>
                <?php if ($isPrivacy): ?>
                    <!-- German Privacy Policy -->
                    <h3 style="color: #ffffff; margin-top: 1.5rem;">1. Erhobene Daten</h3>
                    <p>Bei der Nutzung von TLDix-Diensten erheben wir folgende Daten, um Ihr Konto zu verwalten und die Dienstqualität sicherzustellen:</p>
                    <ul>
                        <li>Registrierungsdaten (Benutzername, E-Mail-Adresse und Passwort-Hash).</li>
                        <li>Domainnamen und Hosting-Details, die Sie überwachen.</li>
                        <li>Ihre Webhook-URL für die API-Integration und Nutzungsstatistiken.</li>
                        <li>Transaktions- und Zahlungsprotokolle (Kreditkartendaten werden nicht auf unseren Systemen gespeichert).</li>
                    </ul>

                    <h3 style="color: #ffffff; margin-top: 1.5rem;">2. Datennutzung</h3>
                    <p>Die erhobenen Daten werden verwendet, um Benachrichtigungen über den Ablauf von Domains und Hosting zu senden, API-Limits zu überwachen und Support-Anfragen zu bearbeiten. Ihre E-Mail-Adresse wird niemals an Dritte verkauft oder weitergegeben.</p>

                    <h3 style="color: #ffffff; margin-top: 1.5rem;">3. Datensicherheit</h3>
                    <p>Alle Daten in unserem System sind durch Verschlüsselungsverfahren nach Industriestandard (SSL/TLS, verschlüsselte Passwörter und regelmäßige Datenbank-Backups) gegen unbefugten Zugriff geschützt.</p>
                <?php else: ?>
                    <!-- German Terms of Service -->
                    <h3 style="color: #ffffff; margin-top: 1.5rem;">1. Leistungsbeschreibung</h3>
                    <p>TLDix ist eine SaaS-Plattform zur Überwachung von Domains und Webhosting-Verlängerungen sowie zur Bereitstellung von WHOIS- und API-Tools für Entwickler.</p>

                    <h3 style="color: #ffffff; margin-top: 1.5rem;">2. Nutzungsbedingungen und Haftung</h3>
                    <p>Obwohl wir automatisierte Benachrichtigungen senden, haftet TLDix nicht für versäumte Domainverlängerungen infolge von E-Mail-Verzögerungen, Spam-Filtern oder Serverausfällen. Wir empfehlen, kritische Domains zusätzlich manuell zu kontrollieren.</p>

                    <h3 style="color: #ffffff; margin-top: 1.5rem;">3. API-Limits und Tarife</h3>
                    <p>Jeder Tarif (Free, Bronze, Silver, Gold) unterliegt Limits für die Domain-Überwachung und die Anzahl der täglichen API-Abfragen. Die Überschreitung dieser Limits führt zur vorübergehenden Sperrung der API-Funktionen.</p>
                <?php endif; ?>

            <?php else: ?>
                <!-- English Default -->
                <?php if ($isPrivacy): ?>
                    <!-- English Privacy Policy -->
                    <h3 style="color: #ffffff; margin-top: 1.5rem;">1. Data We Collect</h3>
                    <p>When you use TLDix services, we collect the following information to manage your account and maintain service quality:</p>
                    <ul>
                        <li>Registration details (Username, email address, and encrypted password hash).</li>
                        <li>Domain names and hosting server details that you track.</li>
                        <li>Your webhook destination URL for API integration and traffic analytics.</li>
                        <li>Transaction and invoice logs (Credit card details are processed securely and never stored on our systems).</li>
                    </ul>

                    <h3 style="color: #ffffff; margin-top: 1.5rem;">2. How We Use Your Data</h3>
                    <p>The collected information is used to send expiration alerts, verify API limit counters, and handle support requests. Your email address is never shared or sold to third parties.</p>

                    <h3 style="color: #ffffff; margin-top: 1.5rem;">3. Data Security</h3>
                    <p>We implement industry-standard security protocols (SSL/TLS encryption, salted BCRYPT password hashing, and regular database backups) to ensure that your tracked domains and settings remain completely secure.</p>
                <?php else: ?>
                    <!-- English Terms of Service -->
                    <h3 style="color: #ffffff; margin-top: 1.5rem;">1. Service Overview</h3>
                    <p>TLDix is a SaaS utility designed to allow webmasters to track domain names, monitor hosting expiration schedules, perform WHOIS lookups, and execute REST API queries.</p>

                    <h3 style="color: #ffffff; margin-top: 1.5rem;">2. Term Policies & Liability</h3>
                    <p>While our alerts help you track renewals, TLDix is not responsible for failed domain registrations or missed renewals caused by SMTP delays, network down times, or spam filters. Critical domains should be checked manually.</p>

                    <h3 style="color: #ffffff; margin-top: 1.5rem;">3. Subscription Plans and API Limits</h3>
                    <p>Each tier (Free, Bronze, Silver, Gold) imposes strict domain-tracking limits and API query quotas. Quota overages may lead to temporary API suspension until the account plan is upgraded or renewed.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div style="margin-top: 3rem; text-align: center;">
            <a href="<?php echo url(''); ?>" class="btn btn-secondary btn-sm" style="font-size: 0.85rem; padding: 0.5rem 1rem;">
                &larr; <?php echo __('blog_back_to_list', 'Back'); ?>
            </a>
        </div>

    </div>
</div>
