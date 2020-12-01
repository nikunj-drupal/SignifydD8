<?php

namespace Drupal\wsf_signifyd;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\user\Entity\User;
use DateTime;
use Symfony\Component\HttpFoundation;
use Drupal\Core\Database\Database;

/**
 * Signifyd API Object.
 *
 * Provides methods to be used by Commerce sub modules.
 *
 * @see https://www.signifyd.com/docs/api
 */
class SignifydApiService {

  /**
   * Submit a new case to Signifyd.
   */
  public function submitCase($data, $orderNumber) {

    $api_key = \Drupal::state()->get('signifyd_api_key');
    $api_base = \Drupal::state()->get('signifyd_api_url').'/cases';

    $response = \Drupal::httpClient()->post($api_base, [
      'auth' => [$api_key, ''],
      'body' => json_encode($data),
    ])->getBody()->getContents();

    if ($response) {
      $result = json_decode($response);
      $message = t('Signifyd Data Result<br><pre><code>signifyd_data_result::' . print_r($result, TRUE) . '</code></pre>');
      \Drupal::logger('signifyd-data-result')->notice($message);
      if ($result->investigationId) {
        $conn = Database::getConnection();
        $conn->insert('wsf_signifyd')->fields(
          array(
            'orderID' => $orderNumber,
            'investigationId' => $result->investigationId,
          )
        )->execute();
      }
    }

    $message = t('Signifyd Data Order Number<br><pre><code>signifyd_data_orderNumber::' . print_r($orderNumber, TRUE) . '</code></pre>');
    \Drupal::logger('signifyd-data-order')->notice($message);

    return $response;
  }

  /**
   * Implements hook_parse_data().
   */
  public function parseData(Order $order, User $account) {
    $data = [];
    $phoneValue = '';
    $billing = $order->getBillingProfile()->get('address')->first();
    $shipments = $order->shipments->referencedEntities();
    $first_shipment = $shipments[0];
    $shipping = $first_shipment->getShippingProfile()->get('address')->first();
    $phone = $order->getBillingProfile()->get('field_customer_phone')->getValue();
    if ($phone) {
      $phoneValue = $phone[0]['value'];
    }
    $date = new DateTime('@' . $order->get('changed')->getString());
    $iso  = $date->format(DateTime::ISO8601);
    $ip_address = \Drupal::request()->getClientIp();
    $given_name = $shipping->get('given_name')->getString();
    $family_name = $shipping->get('family_name')->getString();
    $customerName = $given_name ." ". $family_name;
    
    $data = array(
      'purchase' => array(
        'browserIpAddress' => $ip_address,
        'orderId'          => $order->getOrderNumber(),
        'createdAt'        => $iso,
        'orderChannel'     => 'WEB',
        'products'         => $this->getProducts($order),
        'shipments'        => $this->getShipments($order),
      ),
      'recipient' => array(
        'fullName'          => $customerName,
        'confirmationEmail' => $order->getEmail(),
        'confirmationPhone' => $phoneValue,
        'organization'      => $shipping->get('organization')->getString(),
        'deliveryAddress' => array(
          'streetAddress' => $shipping->get('address_line1')->getString(),
          //'unit'          => $shipping_address['premise'],
          'city'          => $shipping->get('locality')->getString(),
          'provinceCode'  => $shipping->get('administrative_area')->getString(),
          'postalCode'    => $shipping->get('postal_code')->getString(),
          'countryCode'   => $shipping->get('country_code')->getString(),
        ),
      ),

      'card' => array(
        'cardHolderName' => $account->getUsername(),
        'billingAddress' => array(
          'streetAddress' => $billing->get('address_line1')->getString(),
          //'unit'          => $billing_address['premise'],
          'city'          => $billing->get('locality')->getString(),
          'provinceCode'  => $billing->get('administrative_area')->getString(),
          'postalCode'    => $billing->get('postal_code')->getString(),
          'countryCode'   => $billing->get('country_code')->getString(),
        ),
      ),
      'userAccount' => $this->getAccountData($account, $order, $customerName),
    );

    // Cost.
    $data['purchase']['totalPrice'] = $order->getTotalPrice()->getNumber();
    $data['purchase']['paymentGateway'] = $order->get('payment_gateway')->getString();
    $data['purchase']['currency'] = $order->getTotalPrice()->getCurrencyCode();

    return $data;
  }

  /**
   * Gets account specific data and returns the userAccount section data.
   */
  public function getAccountData($account, $order, $customerName) {
    $data = array();
    if ($account) {
      // createdDate.
      $created = new DateTime('@' . $account->get('created')->getString());
      $created = $created->format(DateTime::ISO8601);

      $database = \Drupal::database();
      $queryOrder = $database->query("SELECT count(*) FROM {commerce_order} WHERE (commerce_order.uid = :uid) AND (commerce_order.state IN ('validation', 'completed'))", [':uid' => $account->id()]);
      $totalOrders = $queryOrder->fetchField();

      $queryPaid = $database->query("SELECT SUM(total_paid__number) FROM `commerce_order` WHERE (commerce_order.uid = :uid) AND (commerce_order.state IN ('validation', 'completed'))", [':uid' => $account->id()]);
      $totalPaid = $queryPaid->fetchField();


      $data = array(
        'emailAddress'          => $order->getEmail(),
        'username'              => $customerName,
        'createdDate'           => date('Y-m-d\Th:i:sP', $account->getCreatedTime()),
        'accountNumber'         => $account->id(),
        'aggregateOrderCount'   => $totalOrders,
        'aggregateOrderDollars' => $totalPaid,
        'lastOrderId'           => $order->getOrderNumber(),
      );
    }

    return $data;
  }


  /**
   * Get Products.
   */
  public function getProducts(Order $order) {
    $products = array();

    foreach ($order->getItems() as $order_item) {
      $products[] = array(
        'itemId'       => $order_item->get('order_item_id')->getString(),
        'itemName'     => $order_item->getTitle(),
        'itemQuantity' => (int)$order_item->getQuantity(),
        'itemPrice'    => (double)$order_item->getTotalPrice()->getNumber(),
      );
    }

    return $products;
  }

  /**
   * Get Shipm.
   */
  public function getShipments(Order $order) {
    $shipments = array();
    $order_shipments = $order->shipments->referencedEntities();
    foreach ($order_shipments as $shipment) {
      $shipments[] = array(
        'shippingMethod' => $shipment->getTitle(),
        'shippingPrice'  =>(double) $shipment->getAmount()->getNumber(),
      );
    }

    return $shipments;
  }

}