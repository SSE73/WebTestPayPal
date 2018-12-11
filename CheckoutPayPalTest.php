<?php

namespace XLiteWeb\tests;

use Facebook\WebDriver\WebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Remote\RemoteWebDriver;

use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\Remote\RemoteWebElement;
use Facebook\WebDriver\Remote\RemoteTargetLocator;
use Facebook\WebDriver\WebDriverElement;
use Facebook\WebDriver\WebDriverWait;
use Facebook\WebDriver\WebDriverSelect;
use Facebook\WebDriver\Exception\WebDriverException;


/**
 * @author cerber
 */
class testCheckout extends \XLiteWeb\AXLiteWeb {

    /**
     *
     * @var  RemoteWebDriver
     */
    protected $driver;

    /**
     * @dataProvider provider
     */
    public function testGuestCheckout($dataSet)
    {
        $testData = $dataSet['testData'];
        $results = $dataSet['results'];

        if ($testData['methodPayPal'] === 'Standart' or $testData['methodPayPal'] === 'Advanced' ) {

            $adminPayPal = $this->AdminPayPal;
            $adminPayPal->load(true);
            $this->assertTrue($adminPayPal->validate(), 'Error validating PayPal payment settings page.');

            if ($testData['methodPayPal'] === 'Standart') {
                $adminPayPal->enableStandart();
            }
            if ($testData['methodPayPal'] === 'Advanced' ) {
                $adminPayPal->enableAdvanced();
            }
        }

        $this->clearSession($this->getStorefrontDriver());

        $storeFront = $this->CustomerIndex;
        $storeFront->load();

        if ($testData['methodPayPal'] === 'ExpressMiniCart' and $testData['guest'] === false) {
            $storeFront->LogIn();
        }

        $this->assertTrue($storeFront->validate(), 'Storefront is inaccessible.');

        $categoryLink = $storeFront->categoriesBox_getLink($testData['category']);
        $categoryLink->click();

        $category = $this->CustomerCategory;
        $this->assertTrue($category->componentItemList->isProductExist($testData['productId']), 'Product not accessible in store front.');
        $category->componentItemList->
        productName($testData['productId'])->click();
        $product = $this->CustomerProduct;

        $this->assertTrue($product->validate(), 'Opened page not the product page.');

        $product->addToCart();

        //чтобы нам не мешал попап просто переходим на хомпейдж.
        $storeFront->load();

        $countItems = $product->componentMiniCart->get_textItemCount->getText();
        $this->assertTrue($countItems == '1', 'Wrong item count in the cart.');
        $product->componentMiniCart->click();

        if ($testData['methodPayPal'] === 'ExpressMiniCart') {
            $product->componentMiniCart->clickPaypalButton();
            $product->componentPayPal->clickPaypalLoginMiniCart();
        }else {
            $product->componentMiniCart->get_buttonCheckout->click();
        }

        $checkout = $this->CustomerCheckout;
        $this->assertTrue($checkout->validate(), 'This is not checkout page.');

        if ($testData['guest'] === true) {

            $checkout->takeTestScreenshot('customer_anonymous_checkout_page.png');

            $checkout->goToCheckoutAsAnonymous($testData['address']['email']);

            $checkout->fillForm($testData['address']);
        } else {

            $checkout->get_inputLoginEmail->sendKeys($testData['address']['email']);
            $checkout->get_inputLoginPassword->sendKeys($testData['password']);
            $checkout->get_buttonSignIn->click();
        }

        sleep(2);
        $checkout->waitForAjax(10);

        $subtotalCheckout = $checkout->getSubtotal();
        $shippingCheckout = $checkout->getShipping();
        $totalCheckout = $checkout->getTotal();

//Проверка найти соответствующий на данный момент проверки из @dataprovider-а Метод оплаты (radioButton)

        if ($testData['methodPayPal'] === 'Express') {
            $checkout->waitForFindPayPalExpressCheckout();
        }

        if ($testData['methodPayPal'] === 'Standart') {
            $checkout->waitForFindPayPalPaymentsStandard();
        }

        if ($testData['methodPayPal'] === 'Hosted') {
            $checkout->waitForFindPayPalPartnerHosted();
        }

//нажать на кнопку и запустить соответствующий click()

        if ($testData['methodPayPal'] === 'Express') {
            $checkout->clickPaypalButton();
            $isPaypalWasSuccess = $product ->componentPayPal->clickPaypalLogin();

            if (!$isPaypalWasSuccess) {
                $this->markTestSkipped(
                    'PayPal Things don\'t appear to be working at the moment. Please try again later.'
                );
            }
        }

        if ($testData['methodPayPal'] === 'Standart') {
              $checkout->waitForPlaceOrderButton()->click();
              $product ->componentPayPal->clickPaypalLoginStandart();
        }

        if ($testData['methodPayPal'] === 'Advanced') {
            $checkout->waitForPlaceOrderButton()->click();
            $product ->componentPayPal->clickPaypalAdvanced();
            $checkout->waitForAjax(60);
        }

        if ($testData['methodPayPal'] === 'Hosted') {
            $product ->componentPayPal->clickPaypalHosted();
            $checkout->waitForPlaceOrderButton()->click();
            $checkout->waitForAjax(90);
        }

            $invoice = $this->CustomerInvoice;
            $this->assertTrue($invoice->validate(), 'This is not invoice page.');

            $subtotalInvoice = substr($invoice->getSubtotal(),1);
            $shippingInvoice = substr($invoice->getShipping(),1);
            $totalInvoice    = substr($invoice->getTotal(),1);

            $this->assertEquals($subtotalCheckout, $subtotalInvoice, 'Invalid price');
            $this->assertEquals($shippingCheckout, $shippingInvoice, 'Invalid Shipping');
            $this->assertEquals($totalCheckout, $totalInvoice, 'Invalid Total Invoice');

    }
    
    public function provider()
    {
        $email = 'bit-bucket+customer@example.com';

        $address = array(
            'shippingaddress-firstname' => 'User',
            'shippingaddress-lastname'  => 'Userovich',
            'shippingaddress-street'   => 'Address',
            'shippingaddress-city'      => 'Moody',
            'shippingaddress-country-code'   => 'US',
            'shippingaddress-state-id'     => '558',
            'shippingaddress-zipcode'   => '35004',
            'shippingaddress-phone'     => '88885555555',
            'email'     => $email,
        );
        $addressInInvoice = array(
            'firstname' => 'User',
            'lastname'  => 'Userovich',
            'street'   => 'Address',
            'city'      => 'Moody',
            'country_code'   => 'United States',
            'state_id'     => 'Alabama',
            'zipcode'   => '35004',
            'phone'     => '88885555555',
        );

        $datasets = array();

        $datasets['expressMiniCart'] = array(
            array(
                'config'=>array(),
                'testData'=>array(
                    'guest' => true,
                    'createAccount' => true,
                    'category'     => 'Apparel',
                    'productId'    => '1',
                    'address'     => $address,
                    'password' => '123',
                    'typeCustom' => 'guest',
                    'methodPayPal' => 'ExpressMiniCart',
                ),
                'results'=>array(
                    'CanPlaceOrder'=>true,
                    'addressInInvoice'=>$addressInInvoice,
                )
            ));

        $datasets['express'] = array(
            array(
                'config'=>array(),
                'testData'=>array(
                    'guest' => true,
                    'createAccount' => true,
                    'category'     => 'Apparel',
                    'productId'    => '1',
                    'address'     => $address,
                    'password' => '123',
                    'typeCustom' => 'guest',
                    'methodPayPal' => 'Express',
                ),
                'results'=>array(
                    'CanPlaceOrder'=>true,
                    'addressInInvoice'=>$addressInInvoice,
                )
            ));

        $datasets['standart'] = array(
            array(
            'config'=>array(),
            'testData'=>array(
                'guest' => true,
                'createAccount' => true,
                'category'     => 'Apparel',
                'productId'    => '1',
                'address'     => $address,
                'password' => '123',
                'typeCustom' => 'guest',
                'methodPayPal' => 'Standart',
            ),
            'results'=>array(
                'CanPlaceOrder'=>true,
                'addressInInvoice'=>$addressInInvoice,
            )
        ));

        $datasets['hosted'] = array(
            array(
                'config'=>array(),
                'testData'=>array(
                    'guest' => true,
                    'createAccount' => true,
                    'category'     => 'Apparel',
                    'productId'    => '1',
                    'address'     => $address,
                    'password' => '123',
                    'typeCustom' => 'guest',
                    'methodPayPal' => 'Hosted',
                ),
                'results'=>array(
                    'CanPlaceOrder'=>true,
                    'addressInInvoice'=>$addressInInvoice,
                )
            ));

        $datasets['advanced'] = array(
            array(
                'config'=>array(),
                'testData'=>array(
                    'guest' => true,
                    'createAccount' => true,
                    'category'     => 'Apparel',
                    'productId'    => '1',
                    'address'     => $address,
                    'password' => '123',
                    'typeCustom' => 'guest',
                    'methodPayPal' => 'Advanced',
                ),
                'results'=>array(
                    'CanPlaceOrder'=>true,
                    'addressInInvoice'=>$addressInInvoice,
                )
            ));

//Registred

        $datasets['expressMiniCartRegistred'] = array(
            array(
                'config'=>array(),
                'testData'=>array(
                    'guest' => false,
                    'createAccount' => true,
                    'category'     => 'Apparel',
                    'productId'    => '1',
                    'address'     => $address,
                    'password' => 'guest',
                    'typeCustom' => 'registred',
                    'methodPayPal' => 'ExpressMiniCart',
                ),
                'results'=>array(
                    'CanPlaceOrder'=>true,
                    'addressInInvoice'=>$addressInInvoice,
                )
            ));

        $datasets['expressRegistred'] = array(
            array(
                'config'=>array(),
                'testData'=>array(
                    'guest' => false,
                    'createAccount' => true,
                    'category'     => 'Apparel',
                    'productId'    => '1',
                    'address'     => $address,
                    'password' => 'guest',
                    'typeCustom' => 'guest',
                    'methodPayPal' => 'Express',
                ),
                'results'=>array(
                    'CanPlaceOrder'=>true,
                    'addressInInvoice'=>$addressInInvoice,
                )
            ));

        $datasets['standartRegistred'] = array(
            array(
                'config'=>array(),
                'testData'=>array(
                    'guest' => false,
                    'createAccount' => true,
                    'category'     => 'Apparel',
                    'productId'    => '1',
                    'address'     => $address,
                    'password' => 'guest',
                    'typeCustom' => 'registred',
                    'methodPayPal' => 'Standart',
                ),
                'results'=>array(
                    'CanPlaceOrder'=>true,
                    'addressInInvoice'=>$addressInInvoice,
                )
            ));

        $datasets['hostedRegistred'] = array(
            array(
                'config'=>array(),
                'testData'=>array(
                    'guest' => false,
                    'createAccount' => true,
                    'category'     => 'Apparel',
                    'productId'    => '1',
                    'address'     => $address,
                    'password' => 'guest',
                    'typeCustom' => 'registred',
                    'methodPayPal' => 'Hosted',
                ),
                'results'=>array(
                    'CanPlaceOrder'=>true,
                    'addressInInvoice'=>$addressInInvoice,
                )
            ));

        $datasets['advancedRegistred'] = array(
            array(
                'config'=>array(),
                'testData'=>array(
                    'guest' => false,
                    'createAccount' => true,
                    'category'     => 'Apparel',
                    'productId'    => '1',
                    'address'     => $address,
                    'password' => 'guest',
                    'typeCustom' => 'registred',
                    'methodPayPal' => 'Advanced',
                ),
                'results'=>array(
                    'CanPlaceOrder'=>true,
                    'addressInInvoice'=>$addressInInvoice,
                )
            ));

        return $datasets;
    }

}
