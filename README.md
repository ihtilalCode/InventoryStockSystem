# Inventory & Stock Management System

Web tabanlı bir envanter ve stok yönetim sistemi. Orta ölçekli işletmelerin iç operasyonlarını düzenlemek amacıyla geliştirilmiştir.

## Özellikler
- Rol tabanlı yetkilendirme (Super Admin, Admin, Satıcı, Depo Elemanı)
- Firma ve kullanıcı onay süreçleri
- Ürün ve stok takibi
- Satış ve iade işlemleri
- Stok transferi ve stok hareket kayıtları
- Güvenli giriş ve kayıt mekanizmaları

## Kullanılan Teknolojiler
- PHP
- MySQL
- PDO
- HTML / CSS
- Session tabanlı kimlik doğrulama

## Kurulum
1. Proje dosyalarını web sunucu dizinine kopyalayın.
2. MySQL üzerinde bir veritabanı oluşturun.
3. `database/inventory_stock_db.sql` dosyasını içeri aktarın.
4. `.env.example` dosyasını `.env` olarak kopyalayın ve veritabanı bilgilerini girin.
5. Tarayıcıdan projeyi çalıştırın.

## Roller
- **Super Admin:** Firma ve sistem onaylarını yönetir.
- **Admin:** Firma içi kullanıcı ve ürün yönetimini sağlar.
- **Satıcı:** Satış işlemlerini gerçekleştirir.
- **Depo Elemanı:** Stok transfer ve hareketlerini yönetir.


Final projesi
