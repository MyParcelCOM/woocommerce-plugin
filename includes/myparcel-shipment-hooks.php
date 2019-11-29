<?php declare(strict_types=1);

add_action('admin_enqueue_scripts', 'adminLoadJsCss', 999);

/**
 *
 * @return void
 */
function adminLoadJsCss(): void
{
    ?>
  <script type="text/javascript">
    var ajaxUrl = "<?php echo admin_url('admin-ajax.php') ?>"
  </script>
    <?php
    $screen = get_current_screen();
    if ('shop_order' === $screen->id) {
        enqueueJsAndCssFile();
    }
    if ('edit-shop_order' === $screen->id) {
        enqueueJsAndCssFile();
        wp_enqueue_style('bootstrap-cdn', plugins_url('', __FILE__).'/../assets/admin/css/bootstrap.min.css');
        wp_enqueue_style('bootstrap-cdn-min', plugins_url('', __FILE__).'/../assets/admin/css/bootstrap-theme.min.css');
        wp_enqueue_script('bootstrap-cdn-jquey', plugins_url('', __FILE__).'/../assets/admin/js/bootstrap.min.js');        
        if (!session_id()) {
            session_start();
        }
        if (!empty($_SESSION['errormessage'])) {
            $errMessage = $_SESSION['errormessage'];
            ?>
          <div id="export-wrong-cred" class="notice-error notice is-dismissible">
            <p><?php _e($errMessage, 'woocommerce'); ?></p>
          </div>
            <?php
        }
        unset($_SESSION['errormessage']);
    }
}

add_action('woocommerce_admin_order_item_headers', 'orderItemHeaders', 10, 1);

/**
 * @param object $order
 *
 * @return void
 */
function orderItemHeaders($order): void
{
    $orderId = $order->get_id();
    if (isMyParcelOrder($orderId)) {
        echo '<th class="partial_item_head">'.__('Shipped Qty', 'partial-shipment').'</th>';
        echo '<th class="partial_item_head">Shipping Status</th>';
        echo '<th class="partial_item_head">Remaining Quantity</th>';
    }
}

add_action('woocommerce_admin_order_item_values', 'orderItemValues', 10, 3);

/**
 * @param object  $product
 * @param object  $item
 * @param integer $itemId
 *
 * @return void
 */
function orderItemValues($product, $item, $itemId): void
{
    if (isMyParcelOrder($item->get_order_id())) {
        if ($product) {
            $itemQuantity = $item->get_quantity();
            $orderId      = $item->get_order_id();
            $itemId       = $item->get_id();
            $shipped      = get_post_meta($orderId, GET_META_MYPARCEL_ORDER_SHIPMENT_TEXT, true);
            $shipped      = (!empty($shipped)) ? json_decode($shipped, true) : '';

            $myParcelShipmentNormalOrder = get_post_meta($orderId, '_my_parcel_shipment_for_normal_order', true);

            $tdHtml     = '<a href="javascript:void(0);" class="partial-anchor-top partial-anchor-top-'.$itemId.'" title="Not Shipped">';
            $tdHtml     .= '<span class="not-shipped-color ship-status" title="Not Shipped"> Not Shipped - '.$itemQuantity.'</span>';
            $tdHtml     .= '</a>';
            $qtyHtml    = '<input type="text" name="ship_qty" class="ship_qty ship_qty_'.$itemId.'" value="'.$itemQuantity.'" data-qty="'.$itemQuantity.'" data-old-qty="0" data-flag-id="0" data-rqty="'.$itemQuantity.'" data-item-id="'.$itemId.'" data-order-id="'.$orderId.'" style="width: 43px;"/>';
            $remainHtml = '<a href="javascript:void(0);" class="partial-anchor-remain-'.$itemId.'"><span class="remain-qty">'.$itemQuantity.'</span></a>';
            if ($myParcelShipmentNormalOrder) {
                $tdHtml     = '<a href="javascript:void(0);" class="partial-anchor-top partial-anchor-top-'.$itemId.'" title="Not Shipped">';
                $tdHtml     .= '<span class="shipped-color ship-status" title="Not Shipped"> Shipped </span>';
                $tdHtml     .= '</a>';
                $qtyHtml    = '<input type="text" name="ship_qty" class="ship_qty ship_qty_'.$itemId.'" value="'.$itemQuantity.'" data-qty="'.$itemQuantity.'" data-old-qty="0" data-flag-id="0" data-rqty="'.$itemQuantity.'" data-item-id="'.$itemId.'" data-order-id="'.$orderId.'" style="width: 43px;"/>';
                $remainHtml = '<a href="javascript:void(0);" class="partial-anchor-remain-'.'0'.'"><span class="remain-qty">'.$itemQuantity.'</span></a>';
                echo '<td class="partital-td-item"><span class="text-span">'.$qtyHtml.' <i class="fa fa-truck fa-sm" aria-hidden="true"></i></span> <input type="button" class="btn btn-success btn-quanity-update" id="update-quantity-'.$itemId.'" value="Update Quantity"></td>';
                echo '<td class="partial-status-td" width="1%">'.$tdHtml.'</td>';
                echo '<td class="remain-status-td" width="1%">'.'0'.'</td>';

            } else {
                if ($orderId) {
                    if (!empty($shipped)) {
                        $key = array_search($itemId, array_column($shipped, 'item_id'));
                        prepareHtmlForUpdateQuantity($shipped, $key, $itemQuantity, $orderId, $itemId, $qtyHtml, $tdHtml, $remainHtml);
                    }
                    echo '<td class="partital-td-item"><span class="text-span">'.$qtyHtml.' <i class="fa fa-truck fa-sm" aria-hidden="true"></i></span> <input type="button" class="btn btn-success btn-quanity-update" id="update-quantity-'.$itemId.'" value="Update Quantity"></td>';
                    echo '<td class="partial-status-td" width="1%">'.$tdHtml.'</td>';
                    echo '<td class="remain-status-td" width="1%">'.$remainHtml.'</td>';
                }
            }
        } else {
            echo '<td></td>';
            echo '<td></td>';
            echo '<td></td>';
        }
    }
}

/**
 *
 * @return object
 */
function orderSetShipped(): object
{
    $orderId        = isset($_POST['order_id']) ? $_POST['order_id'] : 0;
    $itemId         = $_POST['item_id'];
    $qty            = $_POST['qty'];
    $shipQty        = $_POST['ship_quantity'];
    $productId      = $_POST['productId'];
    $flagStatus     = $_POST['flagStatus'];
    $order          = new WC_Order($orderId);
    $items          = $order->get_items();
    $weight         = getWeightByProductId($productId);
    $shipmentArrs   = get_post_meta($orderId, GET_META_MYPARCEL_ORDER_SHIPMENT_TEXT, true);
    $shipmentArrs   = (!empty($shipmentArrs)) ? json_decode($shipmentArrs, true) : [];
    $itemIdArr      = (!empty($shipmentArrs)) ? array_column($shipmentArrs, 'item_id') : [];
    $shipmentNewArr = [];
    $shipmentNewAr  = [];
    $totalShipQty   = 0;
    if (!empty($shipmentArrs)) {
        if (!empty($itemIdArr) && !in_array($itemId, $itemIdArr)) {
            $totalShipQty   = $shipQty;
            $remainQty      = $qty - $totalShipQty;
            $shipmentNewAr  = setOrderShipment(
                $orderId,
                $itemId,
                $shipQty,
                $totalShipQty,
                $qty,
                $weight,
                $remainQty,
                $flagStatus,
                "shipped"
            );
            $shipmentArrs[] = $shipmentNewAr;
        } else {
            foreach ($shipmentArrs as $key => $shipmentArr) {
                if ($itemId == $shipmentArr['item_id']) {
                    $totalShipQty       = (int)$shipQty + (int)$shipmentArr['total_shipped'];
                    $remainQty          = $qty - $totalShipQty;
                    $shipmentNewAr      = setOrderShipment(
                        $orderId,
                        $itemId,
                        $shipQty,
                        $totalShipQty,
                        $qty,
                        $weight,
                        $remainQty,
                        $flagStatus,
                        "shipped"
                    );
                    $shipmentArrs[$key] = $shipmentNewAr;
                }
            }
        }
        update_post_meta($orderId, GET_META_MYPARCEL_ORDER_SHIPMENT_TEXT, json_encode($shipmentArrs));
    } else {
        $totalShipQty     = $shipQty;
        $remainQty        = $qty - $totalShipQty;
        $shipmentNewAr    = setOrderShipment($orderId, $itemId, $shipQty, $totalShipQty, $qty, $weight, $remainQty, $flagStatus, "shipped");
        $shipmentNewArr[] = $shipmentNewAr;
        update_post_meta($orderId, GET_META_MYPARCEL_ORDER_SHIPMENT_TEXT, json_encode($shipmentNewArr));
    }
    echo json_encode(
        [
            'order_id'   => $orderId,
            'item_id'    => $itemId,
            'shipped'    => $totalShipQty,
            'qty'        => $qty,
            'weight'     => $weight,
            'remain_qty' => $remainQty,
            'flagStatus' => $flagStatus,
        ]
    );
    exit;
}

add_action('wp_ajax_order_set_shipped', 'orderSetShipped');


