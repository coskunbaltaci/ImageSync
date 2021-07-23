* Diğer dillerde okumak için: [English](README.md)

# Image Sync

Image Sync modülü Magento 2 için geliştirilmiştir. Herhangi bir FTP adresi aracılığıyla toplu şekilde ürünlere birden fazla görsel eklemeyi sağlamaktadır.

# Nasıl Çalışır?

Ftp'ye atılan işlenecek olan ürün listesi ve eşleştirilen görsel isimleri okunarak db'de bir liste olarak tutulur. Daha sonra db'deki bu liste manuel tetikleme ile veya cron ile işlenmeye başlanır. 

İşlem log'ları "***var/log/cb_image_sync.lo**g*" dosyasında tutulmaktadır. İşlem bittiğinde ftp adresinde de belirlenen dosya içerisine eklenmektedir. Ayrıca işlem sonucunda oluşan log dosyası belirlenen mail adresine mail atılmaktadır. 

Başarılı olan kayıtlar cron ile silinmektedir.

# Kurulum

- "***App/Code***" altında "***Cb***" klasörü oluşturup içerisine "ImageSync" modulü atılır.
- "`bin/php magento setup:upgrade`" çalıştırılır.
- "`bin/php magento cache:clean`" çalıştırılır.
- Modül kurulduktan sonra admin panelinde "***Stores/Configuration***" altında gelecek olan ayarlardan modül aktifleştirilip ayarlar tamamlanmalıdır.

# Ayarlar

"***Store/Configuration***" altında "***CB Settings***" menüsü altından modül ayarları yapılabilir.

## "General"
----------------------------
**Enable** : Modülü admin panelinden açıp kapatılabilir.

**Use Custom Format** : Ftp'den yüklenecek görsellerin ve ürün listesinin csv dosyasından mı yoksa görsel adından mı oluşacağının seçebiliriz.
 - **"yes"** : Yes opsiyonu seçilirse açıklama metninde yer alan "sample.csv" dosyası aynı formatta doldurulup ftp'ye atılır.
 - **"No"** : No opsyionu seçilirse görsel ismine göre liste oluşur. Görsel isimleri aşağıdaki formattaki gibi olmalıdır.  
 Örnek: productsku-order.jpg -> exampleSku-1.jpg

**Use Product Name as Alt tag** : Ürün ismi görselin alt bilgisinede kullanılıp kullanılmamasını seçebiliyoruz.

**Image Import Frequency** : Görsellerin import edilmesi için oluşturulmuş "Cron" çalışma sıklığının belirtilmesi. 
Örnek kullanım: */5 * * * *

**Import row count** : Bir işlem çalıştığında işlenecek olan satır sayısı

**Delete Done Rows Frequency** : Başarılı satırların silinmesi için oluşturulmuş "Cron" çalışma sıklığının belirtilmesi. Örnek kullanım: */5 * * * *

**Log Emails** : İşlem sonunda log dosyasının gönderilmesi istenen mail adresleri. Birden çok adresi virgül kullanarak ayrılabilir.

## "FTP"
**Host** : Ftp adresi

**Username** : Ftp kullanıcı adı

**Password** : Ftp kullanıcı şifresi

**Path** : Yüklenecek olan csv ve image'ların atılacağı klasörün adı. FTP kullanıcısına ait full path yazılmalıdır. Örnek: /ftp/username/folder-name/uploads/

**Success Path** : Başarıyla yüklenen dosyaların taşınacağı klasörün adı.  FTP kullanıcısına ait full path yazılmalıdır. Örnek: /ftp/username/folder-name/success-foldder/

**Logs Path** : Ftp tarafında tutulacak log dosyasının adı.  FTP kullanıcısına ait full path yazılmalıdır. Örnek: /ftp/username/folder-name/logs/

# Nasıl görsel yüklerim?

Admin panelinden ayarlar yapıldıktan sonra FTP'de belirlenen "uploads" klasörüne "csv" dosyası ile birlikte görselleri veya örnek formattaki görseller yüklenir. (exampleSku-1.jpg)

"Cb Modules" menüsü altında yer alan "ImageSync Index" sayfasına gidilir. Öncelikle "Get Import List" butonuna tıklanarak Csv dosyası veya örnek formattaki görsellerin isimlerinden yükleme listesi oluşturulur.

Listenin çalıştırılması için aşağıdaki üç yöntem kullanılabilir:

 1.  Ayarlarda tanımlanmış olan Cron sıklığına göre listenin çalışması
    beklenebilir.
 2. Cronu beklemeden "Start Image Import" butonuna basılarak manuel
    olarak listenin çalışması tetiklenebilir.
 3. Sunucu üzerinden aşağıdaki komut kullanılarak listenin çalışması
    tetiklenebilir.
    
`bin/magento cb:image-sync:start`

# Ek Bilgiler

Aşağıdaki komutlar kullanılarak sunucu üzerinden tüm işlemler yapılabilir.

| Komut | Açıklama |
|--|--|
| cb:image-sync:create-folder     | Sunucuda klasörleri oluşturur |
| cb:image-sync:delete-done-rows  | Başarılı satırları siler |
| cb:image-sync:save-import-list  | Import listesini db'ye kaydeder |
| cb:image-sync:start             | Import listesini işlemeye başlar |

# Lisans

MIT lisansı koşulları altında dağıtılan ücretsiz bir yazılımdır.

# İletişim

Herhangi bir sorun olduğunda task açabilir, mesaj atabilirsiniz.