# IS-Press

IS-Press, WordPress yönetim alanında iş planları ve görev hatırlatıcıları
yönetmenizi sağlayan bir eklentidir. Planlar WordPress veritabanında özel
içerik türü olarak saklanır ve herkese açık sayfalarda yayınlanmaz.

## Özellikler

- İş planları oluşturma, açıklama ekleme ve düzenleme
- Son tarih ve görev durumu belirleme (planlandı, devam ediyor, tamamlandı)
- WordPress'in yerel saat dilimini kullanan e-posta hatırlatması ayarlama
- Yönetim listesinden son tarih, durum ve hatırlatma zamanını görme
- Hatırlatma zamanı geldiğinde görev yazarına bir kez e-posta gönderme

## Kurulum ve kullanım

1. `is-press` klasörünü `/wp-content/plugins/` içine yükleyin.
2. WordPress yönetim panelinde **Eklentiler > Yüklü eklentiler** sayfasını açıp
   **IS-Press** eklentisini etkinleştirin.
3. Yönetim menüsündeki **İş Planları > Yeni ekle** sayfasından plan oluşturun.
   Son tarih, görev durumu ve e-posta hatırlatma zamanını görev ayrıntıları
   alanından belirleyebilirsiniz.

Hatırlatmalar WordPress'in saatlik zamanlanmış göreviyle kontrol edilir.
Zamanlanmış görevlerin çalışması için WordPress cron'un etkin olması ve
sitenin düzenli olarak ziyaret edilmesi gerekir. E-postalar WordPress'in
`wp_mail()` işleviyle gönderilir; teslimat, sitenin e-posta yapılandırmasına
bağlıdır. Her görev hatırlatması başarılı gönderimden sonra bir kez gönderilir.

## Erişilebilirlik

Eklenti, iş planlarını WordPress'in yerel yönetim arayüzünde sunar. Özel
alanlarda görünür etiketler, açıklama ilişkileri ve yerel HTML form kontrolleri
kullanılır; yönetim listesi ve gezinme için WordPress'in erişilebilir
bileşenlerinden yararlanılır. Erişilebilirlik, WordPress sürümü, yönetici
teması ve kullanılan yardımcı teknolojilerle birlikte doğrulanmalıdır.
