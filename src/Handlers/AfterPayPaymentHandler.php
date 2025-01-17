<?php declare (strict_types = 1);

namespace Buckaroo\Shopware6\Handlers;

use Psr\Log\LoggerInterface;
use Buckaroo\Shopware6\Helpers\Helper;
use Buckaroo\Shopware6\Handlers\AfterPayOld;
use Buckaroo\Shopware6\Helpers\CheckoutHelper;
use Buckaroo\Shopware6\PaymentMethods\AfterPay;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;

class AfterPayPaymentHandler extends AsyncPaymentHandler
{
    

    public const CUSTOMER_TYPE_B2C = 'b2c';
    public const CUSTOMER_TYPE_B2B = 'b2b';
    public const CUSTOMER_TYPE_BOTH = 'both';

    /**
     * @var \Buckaroo\Shopware6\Handlers\AfterPayOld
     */
    protected $afterPayOld;

    /**
     * Buckaroo constructor.
     * @param Helper $helper
     * @param CheckoutHelper $checkoutHelper
     */
    public function __construct(
        Helper $helper,
        CheckoutHelper $checkoutHelper,
        LoggerInterface $logger,
        AfterPayOld $afterPayOld
    ) {
        parent::__construct($helper, $checkoutHelper, $logger);
        $this->afterPayOld = $afterPayOld;
    }

    /**
     * @param AsyncPaymentTransactionStruct $transaction
     * @param RequestDataBag $dataBag
     * @param SalesChannelContext $salesChannelContext
     * @param string|null $buckarooKey
     * @param string $type
     * @param array $gatewayInfo
     * @return RedirectResponse
     * @throws \Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException
     */
    public function pay(
        AsyncPaymentTransactionStruct $transaction,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext,
        string $buckarooKey = null,
        string $type = null,
        string $version = null,
        array $gatewayInfo = []
    ): RedirectResponse {
        $dataBag = $this->getRequestBag($dataBag);

        $additional = [];
        $latestKey  = 1;
        $order      = $transaction->getOrder();

        $paymentMethod = new AfterPay();

        if ($this->checkoutHelper->getSettingsValue('afterpayEnabledold') === true) {
           $paymentMethod->setBuckarooKey('afterpaydigiaccept');
           $additional = $this->afterPayOld->buildPayParameters(
                $order, $salesChannelContext, $dataBag
           );
        } else {
            $additional = $this->getArticleData($order, $additional, $latestKey);
            $additional = $this->getBuckarooFee($order, $additional, $latestKey, $salesChannelContext->getSalesChannelId());
            $additional = $this->getAddressArray($order, $additional, $latestKey, $salesChannelContext, $dataBag);
        }
        
        $gatewayInfo   = [
            'additional' => $additional,
        ];

        return parent::pay(
            $transaction,
            $dataBag,
            $salesChannelContext,
            $paymentMethod->getBuckarooKey(),
            $paymentMethod->getType(),
            $paymentMethod->getVersion(),
            $gatewayInfo
        );
    }

    public function getBuckarooFee($order, $additional, &$latestKey, $salesChannelId)
    {
        $buckarooFee = $this->checkoutHelper->getBuckarooFee('afterpayFee', $salesChannelId);
        if (false !== $buckarooFee && (double)$buckarooFee > 0) {
            $additional[] = [
                [
                    '_'       => 'buckarooFee',
                    'Name'    => 'Description',
                    'GroupID' => $latestKey,
                    'Group'   => 'Article',
                ],
                [
                    '_'       => 'buckarooFee',
                    'Name'    => 'Identifier',
                    'Group'   => 'Article',
                    'GroupID' => $latestKey,
                ],
                [
                    '_'       => 1,
                    'Name'    => 'Quantity',
                    'GroupID' => $latestKey,
                    'Group'   => 'Article',
                ],
                [
                    '_'       => round($buckarooFee, 2),
                    'Name'    => 'GrossUnitPrice',
                    'GroupID' => $latestKey,
                    'Group'   => 'Article',
                ],
                [
                    '_'       => 0,
                    'Name'    => 'VatPercentage',
                    'GroupID' => $latestKey,
                    'Group'   => 'Article',
                ],
            ];
            $latestKey++;
        }
        return $additional;
    }
    
    public function getArticleData($order, $additional, &$latestKey)
    {
        $lines = $this->checkoutHelper->getOrderLinesArray($order);
        foreach ($lines as $key => $item) {
            $additional[] = [
                [
                    '_'       => $item['name'],
                    'Name'    => 'Description',
                    'GroupID' => $latestKey,
                    'Group'   => 'Article',
                ],
                [
                    '_'       => $item['sku'],
                    'Name'    => 'Identifier',
                    'Group'   => 'Article',
                    'GroupID' => $latestKey,
                ],
                [
                    '_'       => $item['quantity'],
                    'Name'    => 'Quantity',
                    'GroupID' => $latestKey,
                    'Group'   => 'Article',
                ],
                [
                    '_'       => $item['unitPrice']['value'],
                    'Name'    => 'GrossUnitPrice',
                    'GroupID' => $latestKey,
                    'Group'   => 'Article',
                ],
                [
                    '_'       => $item['vatRate'],
                    'Name'    => 'VatPercentage',
                    'GroupID' => $latestKey,
                    'Group'   => 'Article',
                ],
            ];
            $latestKey++;
        }

        return $additional;
    }

    public function getAddressArray($order, $additional, &$latestKey, $salesChannelContext, $dataBag)
    {
        $address  = $this->checkoutHelper->getBillingAddress($order, $salesChannelContext);
        $customer = $this->checkoutHelper->getOrderCustomer($order, $salesChannelContext);
        $shippingAddress  = $this->checkoutHelper->getShippingAddress($order, $salesChannelContext);

        if ($address === null) {
            return $additional;
        }

        $streetFormat  = $this->checkoutHelper->formatStreet($address->getStreet());
        $birthDayStamp = $dataBag->get('buckaroo_afterpay_DoB');
        $address->setPhoneNumber($dataBag->get('buckaroo_afterpay_phone'));
        
        $shippingAddress->setPhoneNumber($dataBag->get('buckaroo_afterpay_phone'));
        $shippingStreetFormat  = $this->checkoutHelper->formatStreet($shippingAddress->getStreet());
        

        $category    = 'Person';

        if (
            $this->isOnlyCustomerB2B($salesChannelContext->getSalesChannelId()) && 
            (
                $this->isCompanyEmpty($address->getCompany()) &&
                $this->isCompanyEmpty($shippingAddress->getCompany())
            )
        ) {
            throw new \Exception(
                'Company name is required for this payment method'
            );
        }

        if (
            $this->isCustomerB2B($salesChannelContext->getSalesChannelId()) &&
            $this->checkoutHelper->getCountryCode($address) === 'NL' &&
            !$this->isCompanyEmpty($address->getCompany())
        ) {
            $category = 'Company';
        }

        $billingData = [
            [
                '_'       => $category,
                'Name'    => 'Category',
                'Group'   => 'BillingCustomer',
                'GroupID' => '',
            ],
            [
                '_'       => $address->getFirstName(),
                'Name'    => 'FirstName',
                'Group'   => 'BillingCustomer',
                'GroupID' => '',
            ],
            [
                '_'       => $address->getLastName(),
                'Name'    => 'LastName',
                'Group'   => 'BillingCustomer',
                'GroupID' => '',
            ],
            [
                '_'       => (!empty($streetFormat['house_number']) ? $streetFormat['street'] : $address->getStreet()),
                'Name'    => 'Street',
                'Group'   => 'BillingCustomer',
                'GroupID' => '',
            ],
            [
                '_'       => $address->getZipCode(),
                'Name'    => 'PostalCode',
                'Group'   => 'BillingCustomer',
                'GroupID' => '',
            ],
            [
                '_'       => $address->getCity(),
                'Name'    => 'City',
                'Group'   => 'BillingCustomer',
                'GroupID' => '',
            ],
            [
                '_'       => $this->checkoutHelper->getCountryCode($address),
                'Name'    => 'Country',
                'Group'   => 'BillingCustomer',
                'GroupID' => '',
            ],
            [
                '_'       => $address->getPhoneNumber(),
                'Name'    => 'MobilePhone',
                'Group'   => 'BillingCustomer',
                'GroupID' => '',
            ],
            [
                '_'       => $address->getPhoneNumber(),
                'Name'    => 'Phone',
                'Group'   => 'BillingCustomer',
                'GroupID' => '',
            ],
            [
                '_'       => $customer->getEmail(),
                'Name'    => 'Email',
                'Group'   => 'BillingCustomer',
                'GroupID' => '',
            ],
        ];

        if (!empty($streetFormat['house_number'])) {
            $billingData[] = [
                '_'       => $streetFormat['house_number'],
                'Name'    => 'StreetNumber',
                'Group'   => 'BillingCustomer',
                'GroupID' => '',
            ];
        }elseif(!empty($address->getAdditionalAddressLine1())){
            $billingData[] = [
                '_'       => $address->getAdditionalAddressLine1(),
                'Name'    => 'StreetNumber',
                'Group'   => 'BillingCustomer',
                'GroupID' => '',
            ]; 
        }

        if (!empty($streetFormat['number_addition'])) {
            $billingData[] = [
                '_'       => $streetFormat['number_addition'],
                'Name'    => 'StreetNumberAdditional',
                'Group'   => 'BillingCustomer',
                'GroupID' => '',
            ];
        }elseif(!empty($address->getAdditionalAddressLine2())){
            $billingData[] = [
                '_'       => $address->getAdditionalAddressLine2(),
                'Name'    => 'StreetNumberAdditional',
                'Group'   => 'BillingCustomer',
                'GroupID' => '',
            ]; 
        }

        if (in_array($this->checkoutHelper->getCountryCode($address),['NL','BE'])) {

            $billingData[] = [
                '_'       => $birthDayStamp,
                'Name'    => 'BirthDate',
                'Group'   => 'BillingCustomer',
                'GroupID' => '',
            ];
        }

        if (
            $this->isCustomerB2B($salesChannelContext->getSalesChannelId()) &&
            $this->checkoutHelper->getCountryCode($address) === 'NL' &&
            !$this->isCompanyEmpty($address->getCompany())
        ) {
            $billingData = array_merge($billingData,[
                [
                    '_'    => $address->getCompany(),
                    'Name' => 'CompanyName',
                    'Group' => 'BillingCustomer',
                    'GroupID' => '',
                ],
                [
                    '_'    => $dataBag->get('buckaroo_afterpay_Coc'),
                    'Name' => 'IdentificationNumber',
                    'Group' => 'BillingCustomer',
                    'GroupID' => '',
                ]
            ]);
        }

        if (
            $this->isCustomerB2B($salesChannelContext->getSalesChannelId()) &&
            $this->checkoutHelper->getCountryCode($shippingAddress) === 'NL' &&
            !$this->isCompanyEmpty($shippingAddress->getCompany())
        ) {
            $category = 'Company';
        }

        $shippingData = [
            [
                '_'       => $category,
                'Name'    => 'Category',
                'Group'   => 'ShippingCustomer',
                'GroupID' => '',
            ],
            [
                '_'       => $shippingAddress->getFirstName(),
                'Name'    => 'FirstName',
                'Group'   => 'ShippingCustomer',
                'GroupID' => '',
            ],
            [
                '_'       => $shippingAddress->getLastName(),
                'Name'    => 'LastName',
                'Group'   => 'ShippingCustomer',
                'GroupID' => '',
            ],
            [
                '_'       => (!empty($shippingStreetFormat['house_number']) ? $shippingStreetFormat['street'] : $shippingAddress->getStreet()),
                'Name'    => 'Street',
                'Group'   => 'ShippingCustomer',
                'GroupID' => '',
            ],
            [
                '_'       => $shippingAddress->getZipCode(),
                'Name'    => 'PostalCode',
                'Group'   => 'ShippingCustomer',
                'GroupID' => '',
            ],
            [
                '_'       => $shippingAddress->getCity(),
                'Name'    => 'City',
                'Group'   => 'ShippingCustomer',
                'GroupID' => '',
            ],
            [
                '_'       => $this->checkoutHelper->getCountryCode($shippingAddress),
                'Name'    => 'Country',
                'Group'   => 'ShippingCustomer',
                'GroupID' => '',
            ],
            [
                '_'       => $shippingAddress->getPhoneNumber(),
                'Name'    => 'MobilePhone',
                'Group'   => 'ShippingCustomer',
                'GroupID' => '',
            ],
            [
                '_'       => $shippingAddress->getPhoneNumber(),
                'Name'    => 'Phone',
                'Group'   => 'ShippingCustomer',
                'GroupID' => '',
            ],
            [
                '_'       => $customer->getEmail(),
                'Name'    => 'Email',
                'Group'   => 'ShippingCustomer',
                'GroupID' => '',
            ],
        ];

        if (!empty($shippingStreetFormat['house_number'])) {
            $shippingData[] = [
                '_'       => $shippingStreetFormat['house_number'],
                'Name'    => 'StreetNumber',
                'Group'   => 'ShippingCustomer',
                'GroupID' => '',
            ];
        }elseif (!empty($shippingAddress->getAdditionalAddressLine1())) {
            $shippingData[] = [
                '_'       => $shippingAddress->getAdditionalAddressLine1(),
                'Name'    => 'StreetNumber',
                'Group'   => 'ShippingCustomer',
                'GroupID' => '',
            ];
        }

        if (!empty($shippingStreetFormat['number_addition'])) {
            $shippingData[] = [
                '_'       => $shippingStreetFormat['number_addition'],
                'Name'    => 'StreetNumberAdditional',
                'Group'   => 'ShippingCustomer',
                'GroupID' => '',
            ];
        }elseif(!empty($shippingAddress->getAdditionalAddressLine2())){
            $shippingData[] = [
                '_'       => $shippingAddress->getAdditionalAddressLine2(),
                'Name'    => 'StreetNumberAdditional',
                'Group'   => 'ShippingCustomer',
                'GroupID' => '',
            ];
        }

        if (in_array($this->checkoutHelper->getCountryCode($shippingAddress),['NL','BE'])) {

            $shippingData[] = [
                '_'       => $birthDayStamp,
                'Name'    => 'BirthDate',
                'Group'   => 'ShippingCustomer',
                'GroupID' => '',
            ];
        }

        if (
            $this->isCustomerB2B($salesChannelContext->getSalesChannelId()) &&
            $this->checkoutHelper->getCountryCode($shippingAddress) === 'NL' &&
            !$this->isCompanyEmpty($shippingAddress->getCompany())
        ) {
            $shippingData = array_merge($shippingData,[
                [
                    '_'    => $shippingAddress->getCompany(),
                    'Name' => 'CompanyName',
                    'Group' => 'ShippingCustomer',
                    'GroupID' => '',
                ],
                [
                    '_'    => $dataBag->get('buckaroo_afterpay_Coc'),
                    'Name' => 'IdentificationNumber',
                    'Group' => 'ShippingCustomer',
                    'GroupID' => '',
                ]
            ]);
        }

        $latestKey++;

        return array_merge($additional, [$billingData,$shippingData]);

    }
    public function isCustomerB2B($salesChannelId = null)
    {
        return $this->checkoutHelper->getSetting('afterpayCustomerType', $salesChannelId) !== self::CUSTOMER_TYPE_B2C;
    }
    public function isOnlyCustomerB2B($salesChannelId = null)
    {
        return $this->checkoutHelper->getSetting('afterpayCustomerType', $salesChannelId) === self::CUSTOMER_TYPE_B2B;
    }
    /**
     * Check if company is empty
     *
     * @param string $company
     *
     * @return boolean
     */
    public function isCompanyEmpty(string $company = null)
    {
        if (null === $company) {
            return true;
        }

        return strlen(trim($company)) === 0;
    }
}
