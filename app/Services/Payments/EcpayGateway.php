<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

/** 綠界 ECPay 全方位金流（信用卡、ATM、超商代碼／條碼、網路 ATM，依 ChoosePayment=ALL 由會員選擇）。 */
class EcpayGateway
{
    public function checkout(Order $order): View
    {
        $fields = [
            'MerchantID' => (string) config('payment.ecpay.merchant_id'),
            // Unique per attempt, so a member can retry; the order is identified by CustomField1.
            'MerchantTradeNo' => strtoupper(Str::random(20)),
            'MerchantTradeDate' => now('Asia/Taipei')->format('Y/m/d H:i:s'),
            'PaymentType' => 'aio',
            'TotalAmount' => (string) $order->total,
            'TradeDesc' => (string) config('app.name'),
            'ItemName' => config('app.name').' #'.$order->number,
            'ReturnURL' => route('payments.ecpay.notify'),
            'ClientBackURL' => route('orders.show', $order),
            'ChoosePayment' => 'ALL',
            'EncryptType' => '1',
            'CustomField1' => (string) $order->id,
        ];
        $fields['CheckMacValue'] = $this->checkMacValue($fields);

        return view('payments.redirect', ['action' => config('payment.ecpay.endpoint'), 'fields' => $fields]);
    }

    /**
     * Verifies a ReturnURL notification and returns the paid order id, ECPay trade number and amount,
     * or null when the notification does not confirm a payment (for example ATM account issued, or a simulated payment in production).
     *
     * @param  array<string, mixed>  $data
     * @return array{order_id: int, reference: string, amount: int|null}|null
     */
    public function paidNotification(array $data): ?array
    {
        $mac = (string) ($data['CheckMacValue'] ?? '');

        if ($mac === '' || ! hash_equals($this->checkMacValue($data), strtoupper($mac))
            || (string) ($data['MerchantID'] ?? '') !== (string) config('payment.ecpay.merchant_id')) {
            throw new InvalidPaymentNotification('Invalid ECPay notification.');
        }

        $simulated = (string) ($data['SimulatePaid'] ?? '0') === '1';

        if ((string) ($data['RtnCode'] ?? '') !== '1' || ! ctype_digit((string) ($data['CustomField1'] ?? ''))
            || ($simulated && config('payment.ecpay.mode') === 'production')) {
            return null;
        }

        return [
            'order_id' => (int) $data['CustomField1'],
            'reference' => (string) ($data['TradeNo'] ?? $data['MerchantTradeNo'] ?? ''),
            'amount' => ctype_digit((string) ($data['TradeAmt'] ?? '')) ? (int) $data['TradeAmt'] : null,
        ];
    }

    /** @param array<string, mixed> $fields */
    public function checkMacValue(array $fields): string
    {
        unset($fields['CheckMacValue']);
        uksort($fields, 'strcasecmp');

        $raw = 'HashKey='.config('payment.ecpay.hash_key');
        foreach ($fields as $key => $value) {
            $raw .= '&'.$key.'='.$value;
        }
        $raw .= '&HashIV='.config('payment.ecpay.hash_iv');

        // ECPay follows .NET URL encoding, which leaves these characters unescaped.
        $encoded = str_replace(
            ['%2d', '%5f', '%2e', '%21', '%2a', '%28', '%29'],
            ['-', '_', '.', '!', '*', '(', ')'],
            strtolower(urlencode($raw)),
        );

        return strtoupper(hash('sha256', $encoded));
    }
}
