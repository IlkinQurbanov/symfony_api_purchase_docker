<?php


namespace App\Service;

use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use Symfony\Component\Dotenv\Dotenv;

class PayPalService
{
    private $client;

    public function __construct()
    {
        // Load environment variables
        (new Dotenv())->load(__DIR__.'/../../.env');

        $clientId = $_ENV['PAYPAL_CLIENT_ID'];
        $clientSecret = $_ENV['PAYPAL_CLIENT_SECRET'];

        $environment = new SandboxEnvironment($clientId, $clientSecret);
        $this->client = new PayPalHttpClient($environment);
    }

    public function createOrder($totalPrice, $returnUrl, $cancelUrl)
    {
        $request = new OrdersCreateRequest();
        $request->prefer('return=representation');
        $request->body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => $totalPrice
                ]
            ]],
            'application_context' => [
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl
            ]
        ];

        try {
            $response = $this->client->execute($request);
            return $response->result;
        } catch (\Exception $e) {
            throw new \RuntimeException('Error creating PayPal order: ' . $e->getMessage());
        }
    }

    public function captureOrder($orderId)
    {
        $request = new OrdersCaptureRequest($orderId);

        try {
            $response = $this->client->execute($request);
            return $response->result;
        } catch (\Exception $e) {
            throw new \RuntimeException('Error capturing PayPal order: ' . $e->getMessage());
        }
    }
}
