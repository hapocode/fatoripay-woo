<?php

/**
 * Shorthand to retrieving variables from $_GET, $_POST and $_REQUEST;
 *
 * @since 2.0.0
 *
 * @param string  $key Key to retrieve.
 * @param boolean $default Default value, when the variable is not available.
 * @return mixed
 */
function wcbc_request($key, $default = false) {

	return isset($_REQUEST[$key]) ? esc_html($_REQUEST[$key]) : esc_html($default);

} // end wu_request;

/**
 * Checks if an array key value is set and returns it.
 *
 * @since 2.0.0
 *
 * @param array  $array Array to check key.
 * @param string $key Key to check.
 * @param mixed  $default Default value, if the key is not set.
 * @return mixed
 */
function wcbc_get_isset($array, $key, $default = false) {

	return isset($array[$key]) ? esc_html($array[$key]) : esc_html($default);

} // end wcbc_get_isset;

/**
 * Clean input values.
 *
 * @param  string $value.
 * @return string Return claned values
 */
function wcbc_clean_input_values($value) {

  $value = str_replace('.', '', $value);

  $value = str_replace('-', '', $value);

  $value = str_replace(' ', '', $value);

  $value = str_replace(',', '', $value);

	$value = str_replace('(', '', $value);

	$value = str_replace(')', '', $value);

  return esc_html(trim($value));

} // end wcbc_clean_input_values;

function wcbc_get_order_by_transaction_id($transaction_id) {

} // end wcbc_get_order_by_transaction_id;
