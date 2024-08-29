<?php

namespace App\Service;

use PayPal\Api\Amount;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;

class PaymentService
{
    private $apiContext;

    public function __construct(string $clientId, string $clientSecret)
    {
        $this->apiContext = new ApiContext(
            new OAuthTokenCredential($clientId, $clientSecret)
        );
        $this->apiContext->setConfig([
            'mode' => 'sandbox',  // Change to 'live' in production
        ]);
    }

    public function createPayment(float $amount, string $currency = 'USD', string $returnUrl, string $cancelUrl): string
    {
        $payer = new Payer();
        $payer->setPaymentMethod('paypal');

        $amountObj = new Amount();
        $amountObj->setCurrency($currency)
                  ->setTotal($amount);

        $transaction = new Transaction();
        $transaction->setAmount($amountObj)
                    ->setDescription('Payment description');

        $redirectUrls = new RedirectUrls();
        $redirectUrls->setReturnUrl($returnUrl)
                     ->setCancelUrl($cancelUrl);

        $payment = new Payment();
        $payment->setIntent('sale')
                ->setPayer($payer)
                ->setTransactions([$transaction])
                ->setRedirectUrls($redirectUrls);

        try {
            $payment->create($this->apiContext);
            return $payment->getApprovalLink();  // Redirect the user to this URL to approve the payment
        } catch (\Exception $e) {
            throw new \Exception('Unable to create PayPal payment: ' . $e->getMessage());
        }
    }

    public function executePayment(string $paymentId, string $payerId)
    {
        $payment = Payment::get($paymentId, $this->apiContext);

        $execution = new PaymentExecution();
        $execution->setPayerId($payerId);

        try {
            $result = $payment->execute($execution, $this->apiContext);
            return $result;
        } catch (\Exception $e) {
            throw new \Exception('Unable to execute PayPal payment: ' . $e->getMessage());
        }
    }
}
