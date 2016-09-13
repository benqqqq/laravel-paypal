<?php namespace Srmklive\PayPal\Services;

use Srmklive\PayPal\Traits\PayPalRequest As PayPalAPIRequest;

class AdaptivePayments
{
    use PayPalAPIRequest;

    /**
     * PayPal Processor Constructor
     */
    public function __construct()
    {
        // Setting PayPal API Credentials
        $this->setConfig();
    }

    /**
     * Set Adaptive Payments API request headers
     *
     * @return array
     */
    private function setHeaders()
    {
        $headers = [
            'X-PAYPAL-SECURITY-USERID' => $this->config['username'],
            'X-PAYPAL-SECURITY-PASSWORD' => $this->config['password'],
            'X-PAYPAL-SECURITY-SIGNATURE' => $this->config['secret'],
            'X-PAYPAL-REQUEST-DATA-FORMAT' => 'JSON',
            'X-PAYPAL-RESPONSE-DATA-FORMAT' => 'JSON',
            'X-PAYPAL-APPLICATION-ID' => $this->config['app_id'],
        ];

        return $headers;
    }

    /**
     * Set Adaptive Payments API request envelope
     *
     * @return array
     */
    private function setEnvelope()
    {
        $envelope = [
            'errorLanguage' => 'en_US',
            'detailLevel' => 'ReturnAll',
        ];

        return $envelope;
    }

    /**
     * Function to perform Adaptive Payments API's PAY operation
     *
     * @param  array  $data
     * @return array
     * @throws \Exception
     */
    public function createPayRequest($data)
    {
        $post = [
            'actionType' => 'PAY',
            'currencyCode' => $this->currency,
            'receiverList' => [
                'receiver' => $data['receivers']
            ]
        ];

        if (! empty($data['feesPayer'])) {
            $post['feesPayer'] = $data['feesPayer'];
        }

        if (! empty($data['return_url']) && ! empty($data['cancel_url'])) {
            $post['returnUrl'] = $data['return_url'];
            $post['cancelUrl'] = $data['cancel_url'];
        } else {
            throw new \Exception('Return & Cancel URL should be specified');
        }

        $post['requestEnvelope'] = $this->setEnvelope();

        $response = $this->doPayPalRequest('Pay', $post);

        return $response;
    }

    /**
     * Function to perform Adaptive Payments API's PaymentDetails operation
     *
     * @param $data
     */
    public function createDetailRequest($data)
    {
        $post = $data;
        $post['requestEnvelope'] = $this->setEnvelope();

        $response = $this->doPayPalRequest('PaymentDetails', $post);

        return $response;
    }

    /**
     * Function to perform Adaptive Payments API's SetPaymentOptions operation.
     *
     * @param  string  $payKey
     * @param  array  $receivers
     * @return array
     */
    public function setPaymentOptions($payKey, $receivers)
    {
        $post = [
            'requestEnvelope' => $this->setEnvelope(),
            'payKey' => $payKey,
        ];

        $receiverOptions = [];
        foreach ($receivers as $receiver) {
            $tmp = [];

            $tmp['receiver'] = [
                'email' => $receiver['email'],
            ];

            $tmp['invoiceData'] = [];
            foreach ($receiver['invoice_data'] as $invoice) {
                $tmp['invoiceData']['item'][] = $invoice;
            }

            $receiverOptions[] = $tmp;
            unset($tmp);
        }

        $post['receiverOptions'] = $receiverOptions;

        $response = $this->doPayPalRequest('SetPaymentOptions', $post);

        return $response;
    }

    /**
     * Function to perform Adaptive Payments API's GetPaymentOptions operation.
     *
     * @param  string  $payKey
     * @return array
     */
    public function getPaymentOptions($payKey)
    {
        $post = [
            'requestEnvelope' => $this->setEnvelope(),
            'payKey' => $payKey,
        ];

        $response = $this->doPayPalRequest('GetPaymentOptions', $post);

        return $response;
    }

    /**
     * Get PayPal redirect url for processing payment.
     *
     * @param  string  $option
     * @param  string  $payKey
     * @return string
     */
    public function getRedirectUrl($option, $payKey)
    {
        $url = $this->config['gateway_url'] . '?cmd=';

        if ($option == 'approved')
            $url .= '_ap-payment&paykey=' . $payKey;
        elseif ($option == 'pre-approved')
            $url .= '_ap-preapproval&preapprovalkey=' . $payKey;

        return $url;
    }

    /**
     * Function To Perform PayPal API Request
     *
     * @param  string  $method
     * @param  array  $params
     * @return array|mixed|\Psr\Http\Message\StreamInterface
     * @throws \Exception
     */
    private function doPayPalRequest($method, $params)
    {
        if (empty($this->config))
            self::setConfig();

        $post_url = $this->config['api_url'] . '/' . $method;

        foreach ($params as $key => $value) {
            $post[$key] = $value;
        }

        try {
            $request = $this->client->post($post_url, [
                'json' => $post,
                'headers' => $this->setHeaders(),
            ]);

            $response = $request->getBody(true);
            $response = \GuzzleHttp\json_decode($response, true);

            return $response;

        } catch (ClientException $e) {
            throw new \Exception($e->getRequest() . " " . $e->getResponse());
        } catch (ServerException $e) {
            throw new \Exception($e->getRequest() . " " . $e->getResponse());
        } catch (BadResponseException $e) {
            throw new \Exception($e->getRequest() . " " . $e->getResponse());
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }

        return [
            'type'      => 'error',
            'message'   => $message
        ];
    }
}
