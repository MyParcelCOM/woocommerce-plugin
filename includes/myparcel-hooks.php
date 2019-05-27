<?php declare(strict_types=1);

use MyParcelCom\ApiSdk\Resources\Address;
use MyParcelCom\ApiSdk\Shipments\PriceCalculator;
use MyParcelCom\ApiSdk\Resources\Shipment;
use MyParcelCom\ApiSdk\Resources\Shop;
use MyParcelCom\ApiSdk\Resources\Carrier;
use MyParcelCom\ApiSdk\Resources\ShipmentStatus;
use MyParcelCom\ApiSdk\Resources\CarrierStatus;
use MyParcelCom\ApiSdk\Resources\Interfaces\PhysicalPropertiesInterface;
use MyParcelCom\ApiSdk\Resources\ShipmentStatusProxy;
use MyParcelCom\ApiSdk\Resources\ShipmentStatusProxyTest;
use MyParcelCom\ApiSdk\Resources\ShipmentStatusTest;
use MyParcelCom\ApiSdk\Resources\Interfaces\ServiceOptionInterface;
use MyParcelCom\ApiSdk\Resources\Interfaces\CarrierInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

add_filter('woocommerce_shipping_methods', 'addMyparcelShippingMethod');

/** 
 * @param array $methods
 *
 * @return array
 */
function addMyparcelShippingMethod($methods): array
{
    $methods[] = 'MyParcel_Shipping_Method';

    return $methods;
}

add_action('woocommerce_review_order_before_cart_contents', 'myparcelValidateOrder', 10);
add_action('woocommerce_after_checkout_validation', 'myparcelValidateOrder', 10);

/**
 *
 * @return void
 */
function myparcelValidateOrder(): void
{
    $packages = WC()->shipping->get_packages();
    $chosenMethods = WC()->session->get('chosen_shipping_methods');
    if (is_array($chosenMethods) && in_array('myparcel', $chosenMethods)) {
        foreach ($packages as $i => $package) {
            if ($chosenMethods[$i] != "myparcel") {
                continue;
            }
            $myparcelShippingMethod = new MyParcel_Shipping_Method();
            $weightLimit = (int) $myparcelShippingMethod->settings['weight'];
            $weight = 0;

            foreach ($package['contents'] as $item_id => $values) {
                $product = $values['data']; 
                $weight = $weight + $product->get_weight() * $values['quantity'];
            }
            $weight = wc_get_weight($weight, 'kg');

            if ($weight > $weightLimit) {
                $message = sprintf(__('Sorry, %d kg exceeds the maximum weight of %d kg for %s', 'myparcel'), $weight, $weightLimit, $myparcelShippingMethod->title);
                $messageType = "error";
                if (!wc_has_notice($message, $messageType)) {
                    wc_add_notice($message, $messageType);
                }
            }
        }
    }
}

add_action('woocommerce_after_shipping_rate', 'shippingText', 10);

/**
 * @param object $method
 *
 * @return void
 */
function shippingText($method): void
{
    if ('myparcel' === $method->get_method_id()) {
        echo "<p>".$method->get_meta_data()['delivery_method']. '/ '. $method->get_meta_data()['carrier_name'].'/ '. $method->get_meta_data()['transit_time']."</p>";
        echo "<p>" . $method->get_meta_data()['line_2'] . "</p>";
    }
}

add_filter( 'woocommerce_cart_ready_to_calc_shipping', 'disableShippingCalcCartPage', 99 );

/**
 *
 * @param $show_shipping object
 *
 * @return void
 */
function disableShippingCalcCartPage($show_shipping)
{
    if( is_cart() ) {
        return false;
    }

    return $show_shipping;
}


//add_action( 'woocommerce_after_shipping_rate', 'checkoutShippingAdditionalField', 20, 2 );

// /**
//  *
//  * @param $methods object, $index
//  *
//  * @return void
//  */
// function checkoutShippingAdditionalField( $method, $index )
// {
//    if( $method->get_id() != 'flat_rate:1' ){
//         echo '<p><a href="javascript:void(0);" class="different_delivery">Select different delivery location</a></p>';
//     }
// }

add_action( 'woocommerce_review_order_before_payment', 'checkoutShippingAdditionalOption', 20, 2 );

/**
 *
 * @return void
 */
function checkoutShippingAdditionalOption(): void
{

    echo '<p><a href="javascript:void(0);" class="different_delivery">Select different delivery location</a></p>
    <div class="myparcelcom-pudo-location myparcelcom-active">
        <div class="myparcelcom-pudo-location-info">
            <h2 class="myparcelcom-pudo-location-company">
              OFF LICENCE INTERNET CAFE
              <span class="myparcelcom-distance"></span>
            </h2>

            <p class="myparcelcom-pudo-location-details">
              31a Goodge Street,
            </p>
            <p class="myparcelcom-pudo-location-details">
              Camden Town
            </p>
        </div>
    </div>

    <div class="myparcelcom-pudo-location">
        <div class="myparcelcom-pudo-location-info">
            <h2 class="myparcelcom-pudo-location-company">
              Test Pushpendra
              <span class="myparcelcom-distance"></span>
            </h2>

            <p class="myparcelcom-pudo-location-details">
              Test
            </p>
            <p class="myparcelcom-pudo-location-details">
              Test
            </p>
        </div>
    </div>';

}

add_action( 'wp_enqueue_scripts', 'addFrontEndJs' );

/**
 *
 * @return void
 */
function addFrontEndJs(): void
{   
    
    wp_enqueue_style('view_order_style',plugins_url('',__FILE__).'/../assets/front-end/css/frontend-myparcel.css');
    if (is_page('checkout')) {
        wp_register_script('checkout-page-script', plugins_url('woocommerce-connect-myparcel/assets/front-end/js/address-checkout-page.js'  , _FILE_ ),'','1.0',true);
        wp_enqueue_script('checkout-page-script');
    }
}

add_filter( 'manage_edit-shop_order_columns', 'customShopOrderColumn',11);

/**
 * @param array $columns
 *
 * @return array
 */
function customShopOrderColumn($columns): array
{
    $newColumn = array();
    $i = 0;
    foreach ($columns as $key => $value) {
       if(5 == $i) {
            $newColumn['order_type'] = __( 'Order Type','order_type');
            $newColumn['shipped_status'] = __( 'Shipped Status','shipped_status');
            $newColumn['partial_shipment_status'] = __( 'Ship Status','partial_shipment_status');
       }
       $newColumn[$key] = $value;
       $i++;
    }

    return $newColumn;
}


add_action( 'manage_shop_order_posts_custom_column' , 'customOrdersListColumnContent', 10, 2 );

/**
 * @param string $column
 *
 * @return void
 */
function customOrdersListColumnContent( $column ): void
{
    global $post, $woocommerce, $the_order;
    $orderId = $the_order->id;
    switch ($column) {

        case 'order_type' :
            foreach ($the_order->get_items( 'shipping' ) as $itemId => $shippingItemObj) {
                $orderItemName = $shippingItemObj->get_method_id();
                if('myparcel' == $orderItemName) {
                    echo "<span style='color:green;'>My Parcel Order <input type='hidden' class='myparcel' value='".$orderId."'/></span>";
                    break;
                }
            }
            break;

        case 'shipped_status' :
            echo (!empty(get_post_meta($orderId,'myparcel_shipment_key',true))) ? '<mark class="order-status status-completed"><span>Shipment Created</span></mark>': '';
            break;          

    }
}

add_filter( 'bulk_actions-edit-shop_order', 'bulkActionsEditProduct', 20, 1 );

/**
 * @param array $actions
 *
 * @return array
 */
function bulkActionsEditProduct($actions): array
{
    $actions['print_myparcel_label'] = __( 'Print MyParcel.com labels', 'print_myparcel_label' );
    $actions['export_myparcel_order'] = __( 'Export orders to MyParcel.com', 'export_myparcel_order' );
    
    return $actions;
}

add_filter( 'handle_bulk_actions-edit-shop_order', 'exportPrintLabelBulkActionHandler', 10, 3 );

/**
 * @param string $redirectTo
 * @param string $action
 * @param array $postIds
 *
 * @return string
 */
function exportPrintLabelBulkActionHandler($redirectTo, $action, $postIds): string
{
    $queryParam = array('_customer_user','m','export_shipment_action','label_generate_action','export_shipment_action_n','check_action');
    $redirectTo = remove_query_arg($queryParam, $redirectTo);
    if ('export_myparcel_order' == $action  || 'print_myparcel_label' == $action) {
        if ('export_myparcel_order' == $action) {
            $isAllMyParcelOrder = true;
            foreach ($postIds as $postId) {
                if (!isMyParcelOrder($postId)) {
                    $isAllMyParcelOrder = false;
                    break;
                }
            }
            if ($isAllMyParcelOrder) {
                $orderShippedCount = 0;
                foreach ($postIds as $postId) {
                    //Check if order belongs to partial shipment or normal one
                    // $checkRecords   = checkForPartialOrNormalOrder($postId);
                    $ifShipmentTrue =  get_option('ship_exists');  
                    $shipKey        =  get_post_meta($postId,'myparcel_shipment_key',true); 

                    //Get tracking key
                    $shipTrackKey        =  get_post_meta($postId,'shipment_track_key',true); 

                    $shippedData    =   get_post_meta($postId, '_my_parcel_order_shipment', true);
                    $shippedItems   =   (!empty($shippedData)) ? json_decode($shippedData,true) : '';
                    if($shippedItems) {                        
                        $totalWeight = 0;  
                        $itemIdArr = [];
                        $shippedItemsNewArr = [];
                        foreach ($shippedItems as $key => $shippedItem) {
                            $type            = $shippedItem['type'];           
                            $shippedQtyNew   = $shippedItem['shipped'];
                            $totalShippedQtyNew   = $shippedItem['total_shipped'];                                       
                            $totalQtyNew     = $shippedItem['qty'];           
                            $remainQtyNew    = $shippedItem['remain_qty'];
                            $weightNew       = $shippedItem['weight'];       
                            //Check shipped status for an item 
                            $flagStatus      = $shippedItem['flagStatus'];       
                            $item_id         = $shippedItem['item_id'];       
                            // if()
                                if ( 1 == $ifShipmentTrue)  {
                                    //logic for weight > 0 
                                    if($remainQtyNew == 0 ) {
                                        $totalWeight += $weightNew * $totalQtyNew ;                                     
                                    }else {
                                        // $totalWeight += $weightNew * $shippedQtyNew;                                    
                                        $totalWeight += $weightNew * $totalShippedQtyNew;  // All shipped quantity                                   
                                    }
                                    $shippedItem["flagStatus"] = 1;                                    
                                } else {                                
                                    if($shipTrackKey && $remainQtyNew == 0 ) {
                                        return $redirectTo = add_query_arg( array('check_action' => 'shipped_already_created'), $redirectTo );                                     
                                    }else {
                                        if( 0 == $flagStatus) {
                                            $totalWeight += $weightNew * $shippedQtyNew;                                            
                                            $shippedItem["flagStatus"] = 1;                                            
                                        }                                        
                                    }                                    
                                 } 
                            array_push($shippedItemsNewArr, $shippedItem);
                        }                        
                    $orderShippedCount++;                                                    
                    $shippedItemsNewArr = json_encode($shippedItemsNewArr);
                    update_post_meta($postId, '_my_parcel_order_shipment', $shippedItemsNewArr);
                    $packages           = WC()->shipping->get_packages();                                
                    $shipmentTrackKey   = createPartialOrderShipment($postId, $totalWeight);
                    add_post_meta($postId, 'shipment_track_key', $shipmentTrackKey); //Update the shipment key on database 
                    $redirectTo = ($orderShippedCount > 0) ? add_query_arg( array('export_shipment_action' => $orderShippedCount,'check_action' => 'export_order'), $redirectTo ) : $redirectTo;
                    }
                    else{
                        return $redirectTo = add_query_arg( array('check_action' => 'select_shipped_order_first'), $redirectTo );
                    }
                }
            } else {
                $redirectTo = add_query_arg( 'export_shipment_action_n', 1, $redirectTo );
            }
        } elseif ('print_myparcel_label' == $action) {
            $isAllMyParcelOrder = true;
            foreach ($postIds as $postId) {
                if (!isMyParcelOrder($postId)) {
                    $isAllMyParcelOrder = false;
                    break;
                }
            }
            if ($isAllMyParcelOrder) {
                $orderLabelCount = 0;
                foreach ($postIds as $postId) {
                    $myParcelShipmentKey = get_post_meta($postId,'myparcel_shipment_key',true);
                    if (!empty($myParcelShipmentKey)) { //API PART REMAIN HERE       
                        $orderLabelCount++; 
                    }  
                }
                $redirectTo = add_query_arg( array('label_generate_action' => $orderLabelCount,'check_action' => 'label_print'));
            }else{
                $redirectTo = add_query_arg( 'export_shipment_action_n', 1, $redirectTo );
            }
       }
    }

    return $redirectTo;
}

add_action( 'admin_notices', 'exportPrintBulkActionAdminNotice' );
set_transient( "shipment-plugin-notice", "alive", 3 );

/**
 *
 * @return void
 */
function exportPrintBulkActionAdminNotice(): void
{
    if("alive" == get_transient( "shipment-plugin-notice" ) ){
        if (!empty($_REQUEST['export_shipment_action']) && 'export_order' == $_REQUEST['check_action']) {
            $orderShippedCount = intval($_REQUEST['export_shipment_action']);
            printf('<div id="message" class="updated notice notice-success is-dismissible" style="color:green;">' ._n( '%s Success: Orders shipment created successfully.', '%s Orders shipment created successfully.', $orderShippedCount). '</div>',$orderShippedCount);    
        } elseif (0 == $_REQUEST['label_generate_action'] && 'label_print' == $_REQUEST['check_action']) {
            $msgDiv = '<div id="message" class="updated notice notice-success is-dismissible" style="color:red;">Error: Please choose only shipped created order.</div>';
            printf($msgDiv);
        } elseif (1 <= $_REQUEST['label_generate_action'] && 'label_print' == $_REQUEST['check_action']) {
            $msgDiv = '<div id="message" class="updated notice notice-success is-dismissible" style="color:green;">Success: Order label generated successfully.</div>';
            printf($msgDiv);
        } elseif (!empty($_REQUEST['export_shipment_action_n'])) {
            $msgDiv = '<div id="message" class="updated notice notice-success is-dismissible" style="color:red;">ERROR: Please choose only Myparcel order.</div>';
            printf($msgDiv);
        } elseif('already_export_order' == $_REQUEST['check_action']) {
            $msgDiv = '<div id="message" class="updated notice notice-success is-dismissible" style="color:red;">ERROR: Order is already shipped!.</div>';
            printf($msgDiv);
        }
        elseif('select_shipped_order_first' == $_REQUEST['check_action']) {
            $msgDiv = '<div id="message" class="updated notice notice-success is-dismissible" style="color:red;">ERROR: Please update  shipping quantity first!.</div>';
            printf($msgDiv);
        }
        elseif('shipped_already_created' == $_REQUEST['check_action']) {
            $msgDiv = '<div id="message" class="updated notice notice-success is-dismissible" style="color:red;">ERROR: Order already exported to Myparcel.com!.</div>';
            printf($msgDiv);
        }

        delete_transient( "shipment-plugin-notice" );
    }        

}

/**
 * @param integer $orderId
 *
 * @return bool
 */
function isMyParcelOrder($orderId): bool
{
    $theOrder = wc_get_order( $orderId );
    foreach ($theOrder->get_items( 'shipping' ) as $itemId => $shippingItemObj) {
        $orderItemName = $shippingItemObj->get_method_id();
        if('myparcel' == $orderItemName) {
            return true;
        }
    }

    return false;
}

/** 
 * @param array $orderId
 *
 * @return void
 **/   
function createOrderShipment($orderId, $orderPartialOrNot = NULL) 
{
    $getAuth    = new MyParcel_API();    
    $api        = $getAuth->apiAuthentication();
    $mpCarrier  = new Carrier();
    $mpShop     = new Shop();

    $order          = wc_get_order( $orderId );
    $order_data     = $order->get_data();
    $items          = $order->get_items();
    $total_weight = 0 ; 
    foreach ( $items as $item ) { 
        $product = wc_get_product( $item['product_id'] );     
        // Now you have access to (see above)...
        $quantity       = $item->get_quantity(); // get quantity
        $product        = $item->get_product(); // get the WC_Product object
        $product_weight = $product->get_weight(); // get the product weight        
        $total_weight += floatval( $product_weight * $quantity );
        $order_shipping_weight = $product->get_weight();
    }    
    // SHIPPING INFORMATION:
    $order_shipping_first_name  = $order_data['shipping']['first_name'];
    $order_shipping_last_name   = $order_data['shipping']['last_name'];
    $order_shipping_company     = $order_data['shipping']['company'];
    $order_shipping_address_1   = $order_data['shipping']['address_1'];
    $order_shipping_address_2   = $order_data['shipping']['address_2'];
    $order_shipping_city        = $order_data['shipping']['city'];
    $order_shipping_state       = $order_data['shipping']['state'];
    $order_shipping_formated_state       = $order_data['shipping']['formated_state'];    
    $order_shipping_postcode    = $order_data['shipping']['postcode'];
    $order_shipping_country     = $order_data['shipping']['country'];    
    $order_shipping_formated_country     = $order_data['shipping']['formated_country'];    
    $order_billing_email        = $order_data['billing']['email'];

    $recipient = new Address();    
    if('GB' == $order_shipping_country){
        $recipient
            ->setStreet1($order_shipping_address_1)
            ->setStreetNumber(221)
            ->setCity($order_shipping_city)
            ->setPostalCode($order_shipping_postcode)
            ->setFirstName($order_shipping_first_name)
            ->setLastName($order_shipping_last_name)
            ->setCountryCode($order_shipping_country)
            ->setRegionCode('ENG')
            ->setEmail($order_billing_email);

    }else{
        $recipient
        ->setStreet1($order_shipping_address_1)
        ->setStreetNumber(221)
        ->setCity($order_shipping_city)
        ->setPostalCode($order_shipping_postcode)
        ->setFirstName($order_shipping_first_name)
        ->setLastName($order_shipping_last_name)
        ->setCountryCode($order_shipping_country) 
        ->setEmail($order_billing_email);    
    }

    // Create the shipment and set required parameters.
    $shipment = new Shipment();    
    $shipment
        ->setRecipientAddress($recipient)
        ->setWeight($total_weight, PhysicalPropertiesInterface::WEIGHT_GRAM);


    $shops = $api->getShops();    

    // Have the SDK determine the cheapest service and post the shipment to the MyParcel.com API.
    $createdShipment    = $api->createShipment($shipment);
    $shipmentId         = $createdShipment->getId();    

    // Get the updated shipment from the API based on its id.
    $updatedShipment = $api->getShipment($shipmentId);

    // Get the current status of the shipment.
    $status = $updatedShipment->getShipmentStatus();    
    // Get the files associated with the shipment, eg label.
    // $files  = $updatedShipment->getFiles();


    $shipmentResourceId = '' ; 
    // if($shipmentId){
    //     $trackKey = update_post_meta($orderId, 'shipment_track_key', $shipmentId);         
    //     return $shipmentId; 
    // }
    return $shipmentId; 
}

add_action('wp_head', 'codecanal_ajaxurl');

function codecanal_ajaxurl() {
    echo '<script type="text/javascript">
           var ajaxurl = "' . admin_url('admin-ajax.php') . '";
         </script>';
}

/**
  * @param array $orderId
  *  
  **/
function getPartialShippingQuantity($orderId) : array
{
    $orderId    = isset($orderId) ? $orderId : 0 ; 
    $getRecords = get_post_meta($orderId, '_my_parcel_order_shipment', true);
    $records    = json_decode($getRecords, true);
    return $records;
}

/**
  * @param 
  *   @return void
  **/
function printShipmentLabel()
{    
    $getAuth    = new MyParcel_API();    
    $api        = $getAuth->apiAuthentication();
    // Retrieve the shipment id from where you stored it.
    $id = "53e69a26-99a5-4755-bb0c-da09e5d7ceca";

    // Get the shipment from the api.
    $shipment = $api->getShipment($id);
    // Get the shipment status.
    $shipmentStatus = $shipment->getShipmentStatus();
    // This can hold extra data given by the carrier, like the carrier's status code
// description.
    $shipmentStatus->setCarrierStatusCode();
    $shipmentStatus->getCarrierStatusDescription();
    // But most importantly it holds the normalized status.
    $status = $shipmentStatus->getStatus();
    
    // $files    = array();    
    // $order_statuses = array('wc-on-hold', 'wc-processing', 'wc-completed');
    // $filters = array(
    //     'post_status' => 'any',
    //     'post_type' => 'shop_order',
    //     'posts_per_page' => 200,
    //     'paged' => 1,
    //     'orderby' => $order_statuses,
    //     'order' => 'ASC',
    //     'status' => 'on-hold',
    // );

    // $loop = new WP_Query($filters);
    // $count = 1 ;
    // while ($loop->have_posts()) {
    //     $loop->the_post();
    //     $order = new WC_Order($loop->post->ID);

    //     foreach ($order->get_items() as $key => $lineItem) {
    //         // echo $count; 
    //         echo $key.' - '.$lineItem.'<br>'; 
    //         //uncomment the following to see the full data
    //         //        echo '<pre>';
    //         //        print_r($lineItem);
    //         //        echo '</pre>';
    //         /*echo '<br>' . 'Product Name : ' . $lineItem['name'] . '<br>';
    //         echo 'Product ID : ' . $lineItem['product_id'] . '<br>';
    //         if ($lineItem['variation_id']) {
    //             echo 'Product Type : Variable Product' . '<br>';
    //         } else {
    //             echo 'Product Type : Simple Product' . '<br>';
    //         }*/
    //     }
    //     $count++; 
    // }

    die;

}

/**
  * @param int $orderId
  *  
  * @return object
  **/
function getPartialShippingTotal($orderId) : float
{
    $shipped            = get_post_meta($orderId, '_my_parcel_order_shipment', true);
    $shippedItems       = (!empty($shipped)) ? json_decode($shipped,true) : '';
    //Get total quantity
    function getColumnQty($shippedItems){ 
        $rec = array_column($shippedItems, 'qty'); 
        return $rec; 
    } 
    //Get shipped quantity                        
    function getColumnShippedQty($shippedItems){ 
        $rec = array_column($shippedItems, 'shipped'); 
        return $rec; 
    }
    //Get shipped item weight                        
    function getColumnShippedWeight($shippedItems){ 
        $rec = array_column($shippedItems, 'weight'); 
        return $rec; 
    }
    $getTotalQty        = array_sum(getColumnQty($shippedItems));
    $getShippedQty      = array_sum(getColumnShippedQty($shippedItems));
    $getShippedWeight   = array_sum(getColumnShippedWeight($shippedItems));
    
    $newRemainQty   = $getTotalQty - $getShippedQty; 
    $newArrs = []; $newRemainQty = 0;$partialDatastr = array();
    foreach ($shippedItems as $key => $shippedItem) {
        $shippedQty     = $shippedItem['shipped'];
        $totalQty       = $shippedItem['qty'];            
        $weight         = $shippedItem['weight'];        

        if($totalQty == $shippedQty) {
            $finalWeight    = floatval($weight * $shippedQty) ;     
        }else {
            $newRemainQty   = $totalQty - $shippedQty; 
            $finalWeight    = floatval($weight * $shippedQty) ; 
        }
        $newArrs[]      = $finalWeight;        
    }    
    return array_sum($newArrs); 
    // return array_sum($newArrs); 
    // echo "<pre>";
    // print_r($newArrs); die;
    
    // $totalFnlWeight =  array_sum($newArrs);
    
    // echo "<br>";
    // echo $finalWeight    = floatval($getShippedWeight * $newRemainQty) ; 
    


    // if($shippedItems){
    //    $newRemainQty = 0;
    //    $newArrs = [];  $partialDatastr = array();
    //    foreach ($shippedItems as $key => $shippedItem) {
    //         $shippedQty     = $shippedItem['shipped'];
    //         $totalQty       = $shippedItem['qty'];            
    //         $weight         = $shippedItem['weight'];            
            
    //         // if($shippedQty > 0){
    //         //     $newRemainQty   = $totalQty - $shippedQty; 
    //         //     $finalWeight    = floatval($weight * $newRemainQty) ; 
    //         //     $newArrs[]      =  $finalWeight;                
    //         //     $partialDatastr[]  = array('myremainqty' => $newRemainQty, 'finalWeight' => $finalWeight); 
    //         // }
    //     }
    //     return $partialDatastr; 
    // }
     
}

function checkForPartialOrNormalOrder($orderId){
    $shipped            = get_post_meta($orderId, '_my_parcel_order_shipment', true);
    $shippedItems       = (!empty($shipped)) ? json_decode($shipped,true) : '';
    if($shippedItems){
        foreach ($shippedItems as $key => $shippedItem) {
            $type         = $shippedItem['type'];            
        }
        return $type; 
    }
}
// Logic for exporing order to Myparcel.com 
function createPartialOrderShipment($orderId, $totalWeight){
    // $getWeightAndQuantities     = getPartialShippingTotal($orderId);
    $countAllWeight = ($totalWeight) ? $totalWeight : 500; 

    $order          = wc_get_order( $orderId );
    $order_data     = $order->get_data();
    $items          = $order->get_items();
    foreach ( $items as $item ) { 
        $product = wc_get_product( $item['product_id'] );     
        // Now you have access to (see above)...
        $quantity       = $item->get_quantity(); // get quantity
        $product        = $item->get_product(); // get the WC_Product object
        $product_weight = $product->get_weight(); // get the product weight                
        $order_shipping_weight = $product->get_weight();
    }  
    // $totalWeight = $total_weight-$totalWeight;
    
     // SHIPPING INFORMATION:
    $order_shipping_first_name  = $order_data['shipping']['first_name'];
    $order_shipping_last_name   = $order_data['shipping']['last_name'];
    $order_shipping_company     = $order_data['shipping']['company'];
    $order_shipping_address_1   = $order_data['shipping']['address_1'];
    $order_shipping_address_2   = $order_data['shipping']['address_2'];
    $order_shipping_city        = $order_data['shipping']['city'];
    $order_shipping_state       = $order_data['shipping']['state'];
    $order_shipping_formated_state       = $order_data['shipping']['formated_state'];    
    $order_shipping_postcode    = $order_data['shipping']['postcode'];
    $order_shipping_country     = $order_data['shipping']['country'];    
    $order_shipping_formated_country     = $order_data['shipping']['formated_country'];    
    $order_billing_email        = $order_data['billing']['email'];

    $recipient = new Address();    
    
    if('GB' == $order_shipping_country){
        $recipient
            ->setStreet1($order_shipping_address_1)
            ->setStreetNumber(221)
            ->setCity($order_shipping_city)
            ->setPostalCode($order_shipping_postcode)
            ->setFirstName($order_shipping_first_name)
            ->setLastName($order_shipping_last_name)
            ->setCountryCode($order_shipping_country)
            ->setRegionCode('ENG')
            ->setEmail($order_billing_email);

    }else{
        $recipient
        ->setStreet1($order_shipping_address_1)
        ->setStreetNumber(221)
        ->setCity($order_shipping_city)
        ->setPostalCode($order_shipping_postcode)
        ->setFirstName($order_shipping_first_name)
        ->setLastName($order_shipping_last_name)
        ->setCountryCode($order_shipping_country) 
        ->setEmail($order_billing_email);    
    }

    // Create the shipment and set required parameters.
    $shipment = new Shipment();    
    $shipment
        ->setRecipientAddress($recipient)
        ->setWeight($countAllWeight, PhysicalPropertiesInterface::WEIGHT_GRAM);

    $getAuth    = new MyParcel_API();    
    $api        = $getAuth->apiAuthentication();



    $shops      = $api->getShops();    
    // Have the SDK determine the cheapest service and post the shipment to the MyParcel.com API.
    $createdShipment    = $api->createShipment($shipment);
    $shipmentId         = $createdShipment->getId();    
    $trackingCode       = $shipment->getTrackingCode();
    $trackingUrl        = $shipment->getTrackingUrl();

    $shipmentResourceId = '' ;     
    return $shipmentId; 
    // }   
    
}

/**
 * @param Shipment $shipment
 * @param string $when
 * @return mixed
 **/
function setRegisterAt($shipment, $when = 'now')
{
    $api = MyParcelComApi::getSingleton();
    $shipment->setRegisterAt($when);
    return $api->updateShipment($shipment);
}