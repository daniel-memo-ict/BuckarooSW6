<?php declare (strict_types = 1);

namespace Buckaroo\Shopware6\PaymentMethods;

use Buckaroo\Shopware6\Handlers\BillinkPaymentHandler;

class Billink extends AbstractPayment
{
    /*
     * @return string
     */
    public function getBuckarooKey(): string
    {
        return 'Billink';
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     */
    public function getName(): string
    {
        return 'Billink';
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Pay with Billink';
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     */
    public function getPaymentHandler(): string
    {
        return BillinkPaymentHandler::class;
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     */
    public function getMedia() : string
    {
        return __DIR__ . '/../Resources/views/storefront/buckaroo/logo/Billink.png';
    }

    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function getTranslations(): array
    {
        return [
            'de-DE' => [
                'name'        => $this->getName(),
                'description' => 'Bezahlen mit Billink',
            ],
            'en-GB' => [
                'name'        => $this->getName(),
                'description' => $this->getDescription(),
            ],
        ];
    }

    public function canCapture(): bool
    {
        return false;
    }

}