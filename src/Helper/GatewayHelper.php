<?php declare(strict_types=1);

namespace Buckaroo\Shopware6\Helper;

use Buckaroo\Shopware6\PaymentMethods\Visa;
use Buckaroo\Shopware6\PaymentMethods\Ideal;
use Buckaroo\Shopware6\PaymentMethods\IdealProcessing;
use Buckaroo\Shopware6\PaymentMethods\Bancontact;

class GatewayHelper
{
    public const GATEWAYS = [
        Visa::class,
        Ideal::class,
        IdealProcessing::class,
        Bancontact::class
    ];
}
