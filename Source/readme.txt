=== ECPay Invoice for WooCommerce ===
Contributors: ecpaytechsupport
Tags: ecommerce, e-commerce, store, sales, sell, shop, cart, checkout, invoice, ecpay
Requires at least: 4.5
Tested up to: 5.4
Requires PHP: 5.6 or later
Stable tag: 1.1.2007070
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

綠界科技電子發票外掛套件

== Description ==

綠界科技電子發票外掛套件，提供合作特店以及個人會員使用開放原始碼商店系統時，無須自行處理複雜的檢核，直接透過安裝設定外掛套件，便可以較快速的方式介接綠界科技的電子發票系統。

= 電子發票 =
綠界科技提供合作特店透過介接方式整合電子發票作業功能，且支援48小時內將發票上傳至財政部。消費者若持有對應的「載具」（如自然人憑證），便不需列印紙本發票可直接將發票儲存於載具中。同時也提供發票查詢功能，可協助合作特店將其功能整合至網站（以下簡稱為合作特店系統），讓消費者能在線上查詢發票。合作特店也可透過電子郵件或簡訊的方式將發票通知寄出。


= 綠界科技電子發票服務 =
- 凡具備綠界科技會員資格即可申請。
- 24H隨時查詢電子發票明細
- 提供電子發票管理及明細下載

= 更安全的付款方式 =
以簡單、安全且保障隱私的方式付款，符合國際PCI DSS 認證，保護交易安全，執行嚴謹的網路軟體硬體防護措施，加倍安心。

= 功能清單 =
- 開立發票。
- 開立折讓。
- 作廢發票。
- 作廢折讓。
- 查詢發票明細。
- 查詢折讓明細。
- 查詢作廢發票明細。
- 查詢作廢折讓明細。
- 發送發票通知。
- 手機條碼驗證。
- 愛心碼驗證。

= 注意事項 =
- 若須同時使用綠界科技WooCommerce金流模組，除了更新綠界科技WooCommerce電子發票模組外，綠界科技WooCommerce金流模組也請同步更新才能正常使用。


= 聯絡我們 =
  綠界技術服務工程師信箱: techsupport@ecpay.com.tw


== Installation ==

= 系統需求 =

- PHP version 5.6.11 or greater
- MySQL version 5.5 or greater

= 自動安裝 =
1. 登入至您的 WordPress dashboard，拜訪 "Plugins menu" 並點擊 "Add"。
2. 在"search field"中輸入"ECPay Invoice"，然後點擊搜尋。
3. 點擊 "安裝" 即可進行安裝。

= 手動安裝 =
詳細說明請參閱 [綠界科技金流外掛套件安裝導引文件](https://github.com/ECPay/WooCommerce_Invoice)。

== Frequently Asked Questions ==

== Changelog ==

v1.1.2007070
修正相容性至 WordPress Version 5.4.2
修正相容性至 WooCommerce Version 4.2.2

v1.1.2003020
調整 cURL timeout

v1.1.2002130
更新電子發票SDK
修正買受人名稱與買受人地址內容

v1.1.1910210
優化程式架構

v1.1.190926
優化程式架構

v1.1.190816
優化程式架構

v1.1.190422
新增電子發票載具編號API驗證

v1.1.181023
修正無法正常開立發票問題(CustomerID、Donation )

v1.1.180606
新增綠界載具

v1.1.1023
更新安裝文件適用版本資訊

v1.1.0911
電子發票開立備註欄增加信用卡卡號後4碼,需搭配https://github.com/ECPay/WooCommerce_Payment 綠界科技金流模組v1.1.0911 以上使用

v1.1.0801
Official release