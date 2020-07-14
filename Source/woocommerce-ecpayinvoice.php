<?php
/**
 * @copyright Copyright (c) 2016 Green World FinTech Service Co., Ltd. (https://www.ecpay.com.tw)
 * @version 1.1.2007070
 *
 * Plugin Name: ECPay Invoice for WooCommerce
 * Plugin URI: https://www.ecpay.com.tw
 * Description: ECPay Invoice For WooCommerce
 * Version: 1.1.2007070
 * Author: ECPay Green World FinTech Service Co., Ltd.
 * Author URI: https://www.ecpay.com.tw
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * WC requires at least: 3
 * WC tested up to: 4.2.2
 */

defined( 'ABSPATH' ) or exit;
define( 'ECPAY_INVOICE_PLUGIN_VERSION', '1.1.2007070' );

// include Invoice SDK
require_once( 'Ecpay_Invoice_Shell.php' );

/**
 * # WooCommerce ECPayinvoice Main Plugin Class
 */
class WC_ECPayInvoice
{
    /** 功能開關 */
    public $my_custom_features_switch;

    /**
     * Initializes the plugin
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        // 管理介面
        if( is_admin() && ! defined( 'DOING_AJAX' ) ) {

            // 載入設定頁面
            add_filter( 'woocommerce_get_settings_pages', array( $this, 'add_settings_page' ) );

            // 後臺手動開立按鈕
            add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'action_woocommerce_admin_generate_invoice_manual' ));
        }

        // 前台統一編號 載具資訊填寫
        add_filter( 'woocommerce_checkout_fields', array(&$this, 'ecpay_invoice_info_fields' ),10 ,1);

        // 發票自動開立程序(需綁ECPAY金流)
        add_action('ecpay_auto_invoice', array(&$this, 'ecpay_auto_invoice' ),10 ,3);

        add_action('woocommerce_checkout_process', array(&$this,'my_custom_checkout_field_process' ));

        // 功能開關設定
        $this->my_custom_features_switch = array(
            'billing_love_code_api_check' => true,
            'billing_carruer_num_api_check' => true
        );
    }


    /*
    |--------------------------------------------------------------------------
    | 前端
    |--------------------------------------------------------------------------
    |
    */

    /**
    * 自動開立
    */
    public function ecpay_auto_invoice($orderId, $SimulatePaid = 0)
    {
        global $woocommerce, $post;

        // 判斷是否啟動自動開立
        $configInvoice = get_option('wc_ecpayinvoice_active_model') ;

        // 啟動則自動開立
        if($configInvoice['wc_ecpay_invoice_auto'] == 'auto') {

            // 判斷是否為模擬觸發
            if($SimulatePaid == 0) {
                // 非模擬觸發
                $this->gen_invoice($orderId, 'auto');
            } else {
                // 模擬觸發
                // 判斷是否在發票測試環境
                if($configInvoice['wc_ecpay_invoice_testmode'] == 'enable_testmode') {
                    $this->gen_invoice($orderId, 'auto');
                }
            }
        }
    }

    /**
     * 統一編號 捐贈捐贈碼 填寫
     */
    public function ecpay_invoice_info_fields($fields)
    {
        // 載入相關JS
        wp_register_script('plugin_ecpay_invoice_frontend_script', plugins_url('/js/ecpay_invoice_frontend.js', __FILE__), array('jquery'),ECPAY_INVOICE_PLUGIN_VERSION, true);
        wp_enqueue_script('plugin_ecpay_invoice_frontend_script');

        //

        // 載具資訊
        $fields['billing']['billing_carruer_type'] = [
            'type'      => 'select',
            'label'         => '載具類別',
            'required'      => false,
            'priority'      => 200,
            'options'   => [
                '0' => '索取紙本',
                '1' => '雲端發票(中獎寄送紙本)',
                '2' => '自然人憑證',
                '3' => '手機條碼'
            ]

        ];

        $fields['billing']['billing_invoice_type'] = [
            'type'          => 'select',
            'label'         => '發票開立',
            'required'      => false,
            'priority'      => 210,
            'options'   => [
                'p' => '個人',
                'c' => '公司',
                'd' => '捐贈'
            ]
        ];

        $fields['billing']['billing_customer_identifier'] = [
            'type'          => 'text',
            'label'         => '統一編號',
            'required'      => false,
            'priority'      => 220,
        ];



        $fields['billing']['billing_love_code'] = [
            'type'          => 'text',
            'label'         => '捐贈碼',
            'desc_tip'      => true,
            'required'      => false,
            'priority'      => 230,
        ];



        $fields['billing']['billing_carruer_num'] = [
            'type'          => 'text',
            'label'         => '載具編號',
            'required'      => false,
            'priority'      => 240,
        ];

        return $fields;
    }

    /**
     * 結帳過程欄位檢查
     */
    public function my_custom_checkout_field_process()
    {
        if( isset($_POST['billing_invoice_type']) && sanitize_text_field($_POST['billing_invoice_type']) == 'c' && sanitize_text_field($_POST['billing_customer_identifier']) == '' ) {
            wc_add_notice( __( '請輸入統一編號' ), 'error' );
        }

        if( isset($_POST['billing_invoice_type']) && sanitize_text_field($_POST['billing_invoice_type']) == 'd' && sanitize_text_field($_POST['billing_love_code']) == '' ) {
            wc_add_notice( __( '請輸入捐贈碼' ), 'error' );
        }

        if( isset($_POST['billing_carruer_type']) && sanitize_text_field($_POST['billing_carruer_type']) == '2' && sanitize_text_field($_POST['billing_carruer_num']) == '' ) {
            wc_add_notice( __( '請輸入自然人憑證載具編號' ), 'error' );
        }

        if( isset($_POST['billing_carruer_type']) && sanitize_text_field($_POST['billing_carruer_type']) == '3' && sanitize_text_field($_POST['billing_carruer_num']) == '' ) {
            wc_add_notice( __( '請輸入手機條碼載具編號' ), 'error' );
        }

        // 統一編號格式判斷
        if( isset($_POST['billing_invoice_type']) && sanitize_text_field($_POST['billing_invoice_type']) == 'c' && sanitize_text_field($_POST['billing_customer_identifier']) != '' ) {

            if( !preg_match('/^[0-9]{8}$/', sanitize_text_field($_POST['billing_customer_identifier'])) ) {
                wc_add_notice( __( '統一編號格式錯誤' ), 'error' );
            }
        }

        // 捐贈碼格式判斷
        if( isset($_POST['billing_invoice_type']) && sanitize_text_field($_POST['billing_invoice_type']) == 'd' && sanitize_text_field($_POST['billing_love_code']) != '' ) {

            if( !preg_match('/^([xX]{1}[0-9]{2,6}|[0-9]{3,7})$/', sanitize_text_field($_POST['billing_love_code'])) ) {
                wc_add_notice( __( '捐贈碼格式錯誤' ), 'error' );

            } else {

                // 呼叫SDK 捐贈碼驗證
                if($this->my_custom_features_switch['billing_love_code_api_check']) {

                    try {

                        // 1.載入SDK程式
                        $ecpayInvoice       = new ECPay_Woo_EcpayInvoice ;

                        // 2.介接參數設定
                        $configInvoice  = get_option('wc_ecpayinvoice_active_model') ;
                        $invoiceUrl         = ($configInvoice['wc_ecpay_invoice_testmode'] == 'enable_testmode') ? 'https://einvoice-stage.ecpay.com.tw/Query/CheckLoveCode'  : 'https://einvoice.ecpay.com.tw/Query/CheckLoveCode' ;
                        $MerchantID         = $configInvoice['wc_ecpay_invoice_merchantid'] ;
                        $HashKey            = $configInvoice['wc_ecpay_invoice_hashkey'] ;
                        $HashIV             = $configInvoice['wc_ecpay_invoice_hashiv'] ;
                        $loveCode           = sanitize_text_field($_POST['billing_love_code']);

                        // 3.寫入基本介接參數
                        $ecpayInvoice->Invoice_Method   = 'CHECK_LOVE_CODE' ;
                        $ecpayInvoice->Invoice_Url      = $invoiceUrl ;
                        $ecpayInvoice->MerchantID       = $MerchantID ;
                        $ecpayInvoice->HashKey          = $HashKey ;
                        $ecpayInvoice->HashIV           = $HashIV ;

                        // 4.寫入發票傳送資訊
                        $ecpayInvoice->Send['LoveCode'] = $loveCode;

                        // 5.送出
                        $returnInfo = $ecpayInvoice->Check_Out();

                        // 6.錯誤訊息
                        if(!isset($returnInfo['RtnCode']) || $returnInfo['RtnCode'] != 1 || $returnInfo['IsExist'] == 'N') {
                            wc_add_notice( __( '請確認輸入的捐贈碼是否正確，或選擇其他發票開立方式('.$returnInfo['RtnCode'].')' ), 'error' );
                        }

                    } catch (Exception $e) {

                        // 例外錯誤處理。
                        $msg = $e->getMessage();
                    }
                }
            }
        }

        // 自然人憑證格式判斷
        if( isset($_POST['billing_carruer_type']) && sanitize_text_field($_POST['billing_carruer_type']) == '2' && sanitize_text_field($_POST['billing_carruer_num']) != '' ) {

            if( !preg_match('/^[a-zA-Z]{2}\d{14}$/', sanitize_text_field($_POST['billing_carruer_num'])) ) {
                wc_add_notice( __( '自然人憑證格式錯誤' ), 'error' );
            }
        }

        // 手機載具格式判斷
        if( isset($_POST['billing_carruer_type']) && sanitize_text_field($_POST['billing_carruer_type']) == '3' && sanitize_text_field($_POST['billing_carruer_num']) != '' ) {

            if( !preg_match('/^\/{1}[0-9a-zA-Z+-.]{7}$/', sanitize_text_field($_POST['billing_carruer_num'])) ) {
                wc_add_notice( __( '手機條碼載具格式錯誤' ), 'error' );

            } else {

                // 呼叫SDK 手機條碼驗證
                if($this->my_custom_features_switch['billing_carruer_num_api_check']) {

                    try {

                        // 1.載入SDK程式
                        $ecpayInvoice = new ECPay_Woo_EcpayInvoice ;

                        // 2.介接參數設定
                        $configInvoice  = get_option('wc_ecpayinvoice_active_model') ;
                        $invoiceUrl         = ($configInvoice['wc_ecpay_invoice_testmode'] == 'enable_testmode') ? 'https://einvoice-stage.ecpay.com.tw/Query/CheckMobileBarCode'  : 'https://einvoice.ecpay.com.tw/Query/CheckMobileBarCode' ;
                        $MerchantID         = $configInvoice['wc_ecpay_invoice_merchantid'] ;
                        $HashKey            = $configInvoice['wc_ecpay_invoice_hashkey'] ;
                        $HashIV             = $configInvoice['wc_ecpay_invoice_hashiv'] ;
                        $barCode            = sanitize_text_field($_POST['billing_carruer_num']);

                        // 3.寫入基本介接參數
                        $ecpayInvoice->Invoice_Method   = 'CHECK_MOBILE_BARCODE' ;
                        $ecpayInvoice->Invoice_Url      = $invoiceUrl ;
                        $ecpayInvoice->MerchantID       = $MerchantID ;
                        $ecpayInvoice->HashKey          = $HashKey ;
                        $ecpayInvoice->HashIV           = $HashIV ;

                        // 4.寫入發票傳送資訊
                        $ecpayInvoice->Send['BarCode']  = $barCode;

                        // 5.送出
                        $returnInfo = $ecpayInvoice->Check_Out();

                        // 6.錯誤訊息
                        if(!isset($returnInfo['RtnCode']) || $returnInfo['RtnCode'] != 1 || $returnInfo['IsExist'] == 'N') {
                            wc_add_notice( __( '請確認輸入的手機條碼載具是否正確，或選擇其他載具類別('.$returnInfo['RtnCode'].')' ), 'error' );
                        }

                    } catch (Exception $e) {

                        // 例外錯誤處理。
                        $msg = $e->getMessage();
                    }
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 後端
    |--------------------------------------------------------------------------
    |
    */

    /**
    * AJAX後端接收
    */
    function add_ajax_actions()
    {
        // 作廢發票
        add_action( 'wp_ajax_my_action2', array($this, 'orderid_return_issue_invalid' ) );

        // 開立發票
        add_action( 'wp_ajax_my_action', array($this, 'orderid_return' ) );
    }

    /**
    * 參數設定頁面
    */
    public function add_settings_page()
    {
        $settings[] = require_once( 'class-wc-ecpayinvoice-settings.php' );

        return $settings;
    }

    /**
    * 後端接收手動開立發票請求
    */
    public function orderid_return()
    {
        global $woocommerce, $post, $wpdb;
        $returnMsg = '';

        $orderId = intval( sanitize_text_field($_POST['oid']) );

        if(!empty($orderId)) {
            $returnMsg = WC_ECPayInvoice::gen_invoice($orderId);
            echo $returnMsg ;

        } else {
            echo '無法開立發票，參數傳遞錯誤。' ;
        }

        wp_die();
    }

    /**
    * 後端接收作廢發票請求
    */
    public function orderid_return_issue_invalid()
    {
        global $woocommerce, $post, $wpdb;
        $returnMsg = '';

        $orderId = intval( sanitize_text_field($_POST['oid']) );

        if(!empty($orderId)) {
            $returnMsg = $this->issue_invalid_invoice($orderId);
            echo $returnMsg ;

        } else {
            echo '無法開立發票，參數傳遞錯誤。' ;
        }

        wp_die();
    }

    /**
    * 開立發票
    */
    public function gen_invoice($orderId, $mode = 'manual')
    {

        global $woocommerce, $post;

        $orderObj = new WC_Order($orderId);
        $orderStatus = $orderObj->get_status($orderId);

        $orderInfo  = get_post_meta($orderId);

        // 付款成功次數 第一次付款或沒有此欄位則設定為空值
        $totalSuccessTimes = ( isset($orderInfo['_total_success_times'][0]) && $orderInfo['_total_success_times'][0] == '' ) ? '' :  $orderInfo['_total_success_times'][0] ;

        $invoiceEnable = false ;
        $invoiceRemark = '' ;

        if($mode == 'manual') {

            if( ( !isset($orderInfo['_payment_method'][0]) || $orderInfo['_payment_method'][0] == 'allpay_dca' ) || $orderInfo['_payment_method'][0] == 'ecpay_dca' ) {

                $_ecpay_invoice_status = '_ecpay_invoice_status'.$totalSuccessTimes ;

                if( ( !isset($orderInfo[$_ecpay_invoice_status][0]) || $orderInfo[$_ecpay_invoice_status][0] == 0 ) && $orderStatus == 'processing' ) {
                        $invoiceEnable = true ;
                }

            } else {

                // 判斷超商取貨付款
                if( $orderInfo['_payment_method'][0] == 'ecpay_shipping_pay' ) {

                    if( ( !isset($orderInfo['_ecpay_invoice_status'][0]) || $orderInfo['_ecpay_invoice_status'][0] == 0 ) && ( $orderStatus == 'ecpay' || $orderStatus == 'on-hold' ) ) {
                        $invoiceEnable = true ;
                    }

                } else {

                    if( ( !isset($orderInfo['_ecpay_invoice_status'][0]) || $orderInfo['_ecpay_invoice_status'][0] == 0 ) && $orderStatus == 'processing' ) {
                        $invoiceEnable = true ;
                    }
                }
            }

        } elseif($mode == 'auto') {

            if( ( !isset($orderInfo['_payment_method'][0]) || $orderInfo['_payment_method'][0] == 'allpay_dca' ) || $orderInfo['_payment_method'][0] == 'ecpay_dca' ) {

                $_ecpay_invoice_status = '_ecpay_invoice_status'.$totalSuccessTimes ;

                if( ( !isset($orderInfo[$_ecpay_invoice_status][0]) || $orderInfo[$_ecpay_invoice_status][0] == 0 )) {
                    $invoiceEnable = true ;
                }

            } else {

                if( ( !isset($orderInfo['_ecpay_invoice_status'][0]) || $orderInfo['_ecpay_invoice_status'][0] == 0 )) {
                    $invoiceEnable = true ;
                }
            }
        }

        // 尚未開立發票且訂單狀態為處理中
        if($invoiceEnable) {

            // 取得發票介接參數設定
            $configInvoice  = get_option('wc_ecpayinvoice_active_model') ;
            $MerchantID     = $configInvoice['wc_ecpay_invoice_merchantid'] ;
            $HashKey        = $configInvoice['wc_ecpay_invoice_hashkey'] ;
            $HashIV         = $configInvoice['wc_ecpay_invoice_hashiv'] ;

            $invoiceUrl     = '' ;

            $orderAmountTotal   = $orderObj->get_total();                       // 訂單總金額
            $orderInfo          = $orderObj->get_address();                     // 訂單地址與電話

            $orderAddress       = $orderInfo['country'] . $orderInfo['state'] . $orderInfo['city'] . $orderInfo['address_1']. $orderInfo['address_2'] ; //  地址
            $orderUserName      = $orderInfo['last_name'] . $orderInfo['first_name'] ;  // 購買人
            $orderEmail         = $orderInfo['email'] ;                     // EMAIL
            $orderPhone         = $orderInfo['phone'] ;                     // Phone
            $customerName       = $orderUserName ;

            $customerIdentifier = get_post_meta($orderId, '_billing_customer_identifier', true) ; // 統一編號
            $invoiceType        = get_post_meta($orderId, '_billing_invoice_type', true) ;

            $donation           = ( $invoiceType == 'd' ) ? 1 : 0 ; // 捐贈
            $donation           = (empty($customerIdentifier)) ? $donation : 0 ; // 如果有寫統一發票號碼則無法捐贈
            $print              = 0 ;

            // 有打統一編號 強制列印
            if( !empty($customerIdentifier) ) {

                $print = 1 ;

                // 有統一編號 則取得公司名稱
                $sCompany_Name  = get_post_meta($orderId, '_billing_company', true);        // 公司名稱
                $customerName = (!empty($sCompany_Name)) ? $sCompany_Name : $customerName ;
            }

            $loveCode           = get_post_meta($orderId, '_billing_love_code', true);      // 捐贈碼
            $carruerType        = get_post_meta($orderId, '_billing_carruer_type', true);       // 載具
            $carruerType        = ($carruerType == 0) ? '' : $carruerType ;

            // 無載具 強制列印
            if(empty($carruerType) ) {

                $print = 1 ;
            }

            // 有捐贈項目 不允許列印
            if($donation == 1 ) {

                $print = 0 ;
            }

            $carruerNum = get_post_meta($orderId, '_billing_carruer_num', true) ;       // 載具編號

            if($configInvoice['wc_ecpay_invoice_testmode'] == 'enable_testmode') {
                $invoiceUrl = 'https://einvoice-stage.ecpay.com.tw/Invoice/Issue' ;
            } else {
                $invoiceUrl = 'https://einvoice.ecpay.com.tw/Invoice/Issue' ;
            }

            // 寫入發票資訊到備註中
            $invoiceInfo = '' ;
            $invoiceTypeTmp = ($invoiceType == 'p') ? '個人' : ( ( $invoiceType == 'd' ) ? '捐贈' : '公司') ;

            $invoiceInfo .= ' 發票開立 : ' . $invoiceTypeTmp . '<br />';

            if($invoiceType == 'c') {
                $invoiceInfo .= ' 統一編號 : ' . $customerIdentifier . '<br />';
            }

            if($invoiceType == 'd') {
                $invoiceInfo .= ' 捐贈碼 : ' . $loveCode . '<br />';
            }

            if($carruerType != '') {
                $carruerTypeTmp = ($carruerType == 1 ) ? '合作店家' : (($carruerType == 2 ) ? '自然人憑證號碼' : '手機條碼' )  ;
                $invoiceInfo .= ' 發票載具 : ' . $carruerTypeTmp . '<br />';
                $invoiceInfo .= ' 載具編號 : ' . $carruerNum . '<br />';
            }

            $invoiceInfo .= '開立金額：' . $orderAmountTotal . ' 元' ;

            // 寫入開立資訊
            if(!empty($invoiceInfo)) {
                $orderObj->add_order_note($invoiceInfo);
            }

            // 呼叫SDK 開立發票
            try {

                $msg = '' ;

                $ecpayInvoice = new ECPay_Woo_EcpayInvoice ;

                // 2.寫入基本介接參數
                $ecpayInvoice->Invoice_Method   = 'INVOICE' ;
                $ecpayInvoice->Invoice_Url      = $invoiceUrl ;
                $ecpayInvoice->MerchantID       = $MerchantID ;
                $ecpayInvoice->HashKey          = $HashKey ;
                $ecpayInvoice->HashIV           = $HashIV ;

                // 3.寫入發票相關資訊

                // 取得商品資訊
                $itemsTmp   = array();
                $items      = array();

                $itemsTmp = $orderObj->get_items();

                global $woocommerce;
                if( version_compare( $woocommerce->version, '3.0', ">=" ) ) {
                    foreach($itemsTmp as $key1 => $value1) {
                        $items[$key1]['ItemName'] = $value1['name']; // 商品名稱 ItemName
                        $items[$key1]['ItemCount'] = $value1['quantity']; // 數量 ItemCount
                        $items[$key1]['ItemAmount'] = round($value1['total'] + $value1['total_tax']); // 小計 ItemAmount
                        $items[$key1]['ItemPrice'] = $items[$key1]['ItemAmount'] / $items[$key1]['ItemCount'] ; // 單價 ItemPrice
                    }
                } else {
                    foreach($itemsTmp as $key1 => $value1) {
                        $items[$key1]['ItemName'] = $value1['name']; // 商品名稱 ItemName
                        $items[$key1]['ItemCount'] = isset($value1['item_meta']['_quantity'][0]) ? $value1['item_meta']['_quantity'][0] : '' ; // 數量 ItemCount

                        if(empty($items[$key1]['ItemCount'])) {

                            $items[$key1]['ItemCount'] = isset($value1['item_meta']['_qty'][0]) ? $value1['item_meta']['_qty'][0] : '' ; // 數量 ItemCount
                        }
                        $items[$key1]['ItemAmount'] = round($value1['item_meta']['_line_total'][0] + $value1['item_meta']['_line_tax'][0]); // 小計 ItemAmount
                        $items[$key1]['ItemPrice'] = $items[$key1]['ItemAmount'] / $items[$key1]['ItemCount'] ; // 單價 ItemPrice
                    }
                }

                foreach($items as $key2 => $value2) {

                    // 商品資訊
                    array_push($ecpayInvoice->Send['Items'], array('ItemName' => $value2['ItemName'], 'ItemCount' => $value2['ItemCount'], 'ItemWord' => '批', 'ItemPrice' => $value2['ItemPrice'], 'ItemTaxType' => 1, 'ItemAmount' => $value2['ItemAmount']  )) ;
                }

                // 運費
                $shippingTotal = $orderObj->get_total_shipping();

                if($shippingTotal != 0) {

                    array_push($ecpayInvoice->Send['Items'], array('ItemName' => '運費', 'ItemCount' => 1, 'ItemWord' => '式', 'ItemPrice' => $shippingTotal, 'ItemTaxType' => 1, 'ItemAmount' => $shippingTotal )) ;
                }

                // 判斷測試模式
                if($configInvoice['wc_ecpay_invoice_testmode'] == 'enable_testmode') {
                    $relateNumber = date('YmdHis') . $orderId . $totalSuccessTimes;
                } else {
                    $relateNumber = $orderId . $totalSuccessTimes ;
                }

                // 判斷是否信用卡後四碼欄位有值，如果有值則寫入備註中
                $card4no = get_post_meta($orderId, 'card4no', true);    // 信用卡後四碼

                if(!empty($card4no)) {
                    $invoiceRemark .= $card4no ;
                }

                $ecpayInvoice->Send['RelateNumber']         = $relateNumber ;
                $ecpayInvoice->Send['CustomerID']           = '' ;
                $ecpayInvoice->Send['CustomerIdentifier']   = $customerIdentifier ;
                $ecpayInvoice->Send['CustomerName']         = $customerName ;
                $ecpayInvoice->Send['CustomerAddr']         = $orderAddress ;
                $ecpayInvoice->Send['CustomerPhone']        = $orderPhone ;
                $ecpayInvoice->Send['CustomerEmail']        = $orderEmail ;
                $ecpayInvoice->Send['ClearanceMark']        = '' ;
                $ecpayInvoice->Send['Print']                = $print ;
                $ecpayInvoice->Send['Donation']             = $donation ;
                $ecpayInvoice->Send['LoveCode']             = $loveCode ;
                $ecpayInvoice->Send['CarruerType']          = $carruerType ;
                $ecpayInvoice->Send['CarruerNum']           = $carruerNum ;
                $ecpayInvoice->Send['TaxType']              = 1 ;
                $ecpayInvoice->Send['SalesAmount']          = $orderAmountTotal ;
                $ecpayInvoice->Send['InvoiceRemark']        = $invoiceRemark ;
                $ecpayInvoice->Send['InvType']              = '07';
                $ecpayInvoice->Send['vat']                  = '' ;

                // 4.送出
                $returnInfo = $ecpayInvoice->Check_Out();

            } catch (Exception $e) {

                // 例外錯誤處理。
                $msg = $e->getMessage();
            }

            // 寫入發票回傳資訊
            $orderObj->add_order_note(print_r($returnInfo, true));

            if(!empty($msg)) {
                $orderObj->add_order_note($msg);
            }

            if(isset($returnInfo['RtnCode']) && $returnInfo['RtnCode'] == 1) {
                $orderInvoiceStatus = 1 ;  // 發票已經開立

                if(empty($totalSuccessTimes)) {
                    $orderInvoiceFieldName  = '_ecpay_invoice_status' ;     // 欄位名稱 記錄狀態
                    $orderInvoiceNumFieldName   = '_ecpay_invoice_number' ;     // 欄位名稱 記錄發票號碼
                } else {
                    $orderInvoiceFieldName  = '_ecpay_invoice_status'.$totalSuccessTimes ;  // 欄位 記錄狀態
                    $orderInvoiceNumFieldName = '_ecpay_invoice_number'.$totalSuccessTimes ;    // 欄位名稱 記錄發票號碼
                }

                // 異動已經開立發票的狀態 1.已經開立 0.尚未開立
                update_post_meta($orderId, $orderInvoiceFieldName, $orderInvoiceStatus );

                // 寫入發票號碼
                update_post_meta($orderId, $orderInvoiceNumFieldName, $returnInfo['InvoiceNumber'] );
            }

            if($mode == 'manual') {
                return 'RelateNumber=>' . $relateNumber . print_r($returnInfo, true) ;
            }

        } else {

            if($orderStatus != 'processing' ) {

                if($mode == 'manual') {
                    return '僅允許狀態為處理中的訂單開立發票' ;
                } else {
                    $orderObj->add_order_note('僅允許狀態為處理中的訂單開立發票');
                }
            }
        }
    }

    /**
    * 作廢發票
    */
    public function issue_invalid_invoice($orderId)
    {

        global $woocommerce, $post;

        $orderObj       = new WC_Order($orderId);
        $orderStatus    = $orderObj->get_status($orderId);
        $orderInfo      = get_post_meta($orderId);

        // 付款成功最後的一次 第一次付款或沒有此欄位則設定為空值
        $totalSuccessTimes = ( isset($orderInfo['_total_success_times'][0]) && $orderInfo['_total_success_times'][0] == '' ) ? '' :  $orderInfo['_total_success_times'][0] ;

        // 已經開立發票才允許(找出最後一次)
        $_ecpay_invoice_status = '_ecpay_invoice_status'.$totalSuccessTimes ;

        if( isset($orderInfo[$_ecpay_invoice_status][0]) && $orderInfo[$_ecpay_invoice_status][0] == 1 ) {

            // 發票號碼
            $_ecpay_invoice_number = '_ecpay_invoice_number'.$totalSuccessTimes ;
            $invoiceNumber  = get_post_meta($orderId, $_ecpay_invoice_number, true) ;

            // 取得發票介接參數設定
            $configInvoice  = get_option('wc_ecpayinvoice_active_model') ;
            $MerchantID     = $configInvoice['wc_ecpay_invoice_merchantid'] ;
            $HashKey        = $configInvoice['wc_ecpay_invoice_hashkey'] ;
            $HashIV         = $configInvoice['wc_ecpay_invoice_hashiv'] ;
            $invoiceUrl     = '' ;

            if($configInvoice['wc_ecpay_invoice_testmode'] == 'enable_testmode') {
                $invoiceUrl = 'https://einvoice-stage.ecpay.com.tw/Invoice/IssueInvalid' ;
            } else {
                $invoiceUrl = 'https://einvoice.ecpay.com.tw/Invoice/IssueInvalid' ;
            }

            // 寫入發票資訊到備註中
            $invoiceInfo = '' ;
            $invoiceInfo .= ' 發票作廢 : ' . $invoiceNumber . '<br />';

            // 寫入備註資訊
            if(!empty($invoiceInfo)) {
                $orderObj->add_order_note(esc_html($invoiceInfo));
            }

            // 呼叫SDK 作廢發票
            try {

                $msg = '' ;

                $ecpayInvoice = new ECPay_Woo_EcpayInvoice ;

                // 2.寫入基本介接參數
                $ecpayInvoice->Invoice_Method   = 'INVOICE_VOID' ;
                $ecpayInvoice->Invoice_Url      = $invoiceUrl ;
                $ecpayInvoice->MerchantID       = $MerchantID ;
                $ecpayInvoice->HashKey          = $HashKey ;
                $ecpayInvoice->HashIV           = $HashIV ;

                // 3.寫入發票相關資訊
                $ecpayInvoice->Send['InvoiceNumber'] = $invoiceNumber;
                $ecpayInvoice->Send['Reason'] = '發票作廢';

                // 4.送出
                $returnInfo = $ecpayInvoice->Check_Out();

            } catch (Exception $e) {

                // 例外錯誤處理。
                $msg = $e->getMessage();
            }

            // 寫入發票回傳資訊
            $orderObj->add_order_note(print_r($returnInfo, true));

            if(!empty($msg)) {
                $orderObj->add_order_note(esc_html($msg));
            }

            if(isset($returnInfo['RtnCode']) && $returnInfo['RtnCode'] == 1) {
                $orderInvoiceStatus = 0 ; // 發票作廢

                if(empty($totalSuccessTimes)) {
                    $orderInvoiceFieldName  = '_ecpay_invoice_status' ;     // 欄位名稱 記錄狀態
                    $orderInvoiceNumFieldName   = '_ecpay_invoice_number' ;     // 欄位名稱 記錄發票號碼
                } else {
                    $orderInvoiceFieldName  = '_ecpay_invoice_status'.$totalSuccessTimes ;  // 欄位 記錄狀態
                    $orderInvoiceNumFieldName   = '_ecpay_invoice_number'.$totalSuccessTimes ;  // 欄位名稱 記錄發票號碼
                }

                // 異動已經開立發票的狀態 1.已經開立 0.尚未開立
                update_post_meta($orderId, $orderInvoiceFieldName, $orderInvoiceStatus );

                // 清除發票號碼
                update_post_meta($orderId, $orderInvoiceNumFieldName, '');
            }

            return 'RelateNumber=>' . $relateNumber . print_r($returnInfo, true) ;

        }  else {
            return '發票已經完成作廢，請重新整理畫面' ;
        }
    }

    // 後臺手動開立發票按鈕
    public function action_woocommerce_admin_generate_invoice_manual()
    {
        wp_register_script('plugin_ecpay_invoice_admin_script', plugins_url('/js/ecpay_invoice_admin.js', __FILE__), array('jquery'),'1.1', true);
        wp_enqueue_script('plugin_ecpay_invoice_admin_script');

        global $woocommerce, $post;

        // 判斷是否已經開過發票
        $orderObj       = new WC_Order($post->ID);
        $orderStatus    = $orderObj->get_status($post->ID);
        $orderInfo      = get_post_meta($post->ID);

        $genInvoice     = false ;

        // 付款成功次數 第一次付款或沒有此欄位則設定為空值
        if(!isset($orderInfo['_total_success_times'][0])) {
            $totalSuccessTimes = '' ;
        } else {
            $totalSuccessTimes = $orderInfo['_total_success_times'][0] ;
        }

        $configInvoice = get_option('wc_ecpayinvoice_active_model') ;

        // 判斷是否啟動模組
        if($configInvoice['wc_ecpay_invoice_enabled'] == 'enable') {
            $_ecpay_invoice_status = '_ecpay_invoice_status'.$totalSuccessTimes ;

            if($configInvoice['wc_ecpay_invoice_auto'] == 'manual') {

                // 尚未開立發票
                if( !isset($orderInfo[$_ecpay_invoice_status][0]) || $orderInfo[$_ecpay_invoice_status][0] == 0 ) {

                    // 判斷付款方式
                    if($orderInfo['_payment_method'][0] == 'ecpay_shipping_pay') {

                        // 貨到付款 訂單產生就出現按鈕
                        if( $orderStatus == 'ecpay' || $orderStatus == 'on-hold') {
                            $genInvoice = true ;
                        }

                    } else {

                        if( $orderStatus == 'processing') {
                            $genInvoice = true ;
                        }
                    }
                }
            }

            // 產生按鈕
            if($genInvoice) {
                echo "
                <p class=\"form-field form-field-wide\">
                <input class='button' type='button' id='invoice_button' onclick='send_orderid_to_gen_invoice(".$post->ID.");' value='開立發票' />
                </p>
                ";
            }

            if( isset($orderInfo[$_ecpay_invoice_status][0]) && $orderInfo[$_ecpay_invoice_status][0] == 1 ) {
                echo "
                <p class=\"form-field form-field-wide\"><input class='button' type='button' id='invoice_button_issue_invalid' onclick='send_orderid_to_issue_invalid(".$post->ID.");' value='作廢發票' />
                </p>
                ";
            }
        }
    }
}

$ecpi = new WC_ECPayInvoice();

$ecpi->add_ajax_actions();