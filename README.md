### Integrate OpenCart with 2Checkout
----------------------------------------

### OpenCart Settings

1. Get the latest release artifact from https://github.com/2Checkout/opencart-2checkout/releases
2. Sign in to your OpenCart admin.
3. Under **Extensions** -> **Installer** click **Upload** and choose the release that you downloaded.
4. Under **Extensions** click **Extensions** and select **Payments** under **Choose the extension type**.
5. Click **Install** on the payment option you want to use (**2Checkout API**, **2Checkout Convert Plus**, **2Checkout Inline**) and then click **Edit**.
6. Enter your **2Checkout Account ID** _(Merchant Code, Found in your 2Checkout Control Panel)_
7. Enter your **Secret Key** _(Found in your 2Checkout Control Panel)_
8. Enter your **Secret Word** _(Found in your 2Checkout Control Panel)_
9. Under **Test Mode** select **No** for live sales or **Yes** for test sales.
10. Select **Complete** under **Order Status**.
11. Select **Enabled** under **Status**.
12. Save your changes.

### 2Checkout Settings

1. Sign in to your 2Checkout account. 
2. Navigate to Dashboard → Integrations → Webhooks & API Section
3. Make sure to enable the IPN webhook notification in your Merchant Control Panel.
	- Log in to the 2Checkout Merchant Control Panel and navigate to Integrations → Webhooks & API
	- Scroll down to the Notifications section and enable the IPN webhook
	- For the Payment notification type field, select IPN or Email Text & IPN, and then click on the Configure IPN button.
	- On the IPN settings page, click on the Add IPN URL button and input the IPN URL available in the configuration page in OpenCart.
	- Enable all triggers and response tags

Please feel free to contact 2Checkout directly with any integration questions via supportplus@2checkout.com.
