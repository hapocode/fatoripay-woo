<?php
/**
 * Not supported event exception
 *
 * @package FatoriPayWoo
 */

namespace WC_FatoriPay\Webhook;

/**
 * This exception must be thrown when a webhook endpoint receveid an event not
 * supported by the plugin.
 */
class Event_Exception extends \Exception {
}
