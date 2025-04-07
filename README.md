# FishPay WHMCS Module

## How to install

Fistly, download the `fishpay.php` file
After you've downloaded it, you have to put it into
```
/var/www/whmcs/modules/gateways/
```
Secondly, download `fishpay-checker.php`, and put it in the root directory of WHMCS (`/var/www/whmcs/`)
Then, run `crontab -e` and add this:
```
*/2 * * * * php /var/www/html/fishpay-checker.php >/dev/null 2>&1
```

After, save it.

(If you reboot the VDS, you would need to setup the crontab again.)

## How to activate

Then, go to `System Settings` > `Payment Gateways` > `Visit Apps and Integrations` > `Browse` > `Search Apps` > `FishPay` > `Activate`

After, type in your Merchant ID and your API key.

Now you can accept payments!
