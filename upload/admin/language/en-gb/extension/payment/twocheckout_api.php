<?php
// Heading
$_['heading_title'] = '2Checkout API';

// Text
$_['text_extension'] = 'Extensions';
$_['text_success'] = 'Success: You have modified 2Checkout account details!';
$_['text_edit'] = 'Edit 2Checkout API';
$_['text_refund'] = 'Refund';
$_['text_total_amount_refund'] = 'Total amount that can be refunded: ';
$_['text_refund_final'] = 'Refunded';
$_['text_twocheckout_api'] = '<a href="https://www.2checkout.com" target="_blank">
<img src="view/image/payment/2checkout_verifone.png" alt="2Checkout API logo" 
title="2Checkout API" style="border: 1px solid #EEEEEE;" /></a>';

// Entry
$_['entry_account'] = '2Checkout Account ID';
$_['entry_secret_key'] = 'Secret Key';
$_['entry_test'] = 'Test Mode';
$_['entry_total'] = 'Total';
$_['entry_order_status'] = 'Order Status';
$_['entry_geo_zone'] = 'Geo Zone';
$_['entry_status'] = 'Status';
$_['entry_sort_order'] = 'Sort Order';
$_['entry_use_default'] = 'Use default style';
$_['entry_custom_style'] = 'Edit your credit card form style';
$_['entry_ipn'] = 'IPN url';

// Help
$_['help_ipn'] = 'Add this url to your 2Checkout admin area (under IPN section) in order to get the order status updated each time';
$_['help_secret_key'] = 'The secret key (from your 2Checkout account) is required  to authenticate into 2Checkout API.';
$_['help_total'] = 'The checkout total the order must reach before this payment method becomes active.';
$_['help_custom_style'] = '<i style="color: #e35d5d"><b>IMPORTANT! </b><br /> This is the styling object that styles your form.
                     Do not remove or add new classes. You can modify the existing ones. Use
                      double quotes for all keys and values!  <br /> VALID JSON FORMAT REQUIRED (validate 
                      json before save here: <a href="https://jsonlint.com/" target="_blank">https://jsonlint.com/</a>) </i>. <br >
                      Also you can find more about styling your form <a href="https://knowledgecenter.2checkout.com/API-Integration/2Pay.js-payments-solution/2Pay.js-Payments-Solution-Integration-Guide/How_to_customize_and_style_the_2Pay.js_payment_form"
                       target="_blank">here</a>!';

// Error
$_['error_permission'] = 'Warning: You do not have permission to modify payment 2Checkout!';
$_['error_account'] = 'Account No. Required!';
$_['error_secret_key'] = 'Secret Key Required!';
