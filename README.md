# ShegerPay PHP SDK

Official PHP SDK for ShegerPay — verify Ethiopian bank payments (CBE, Telebirr, BOA, Awash).

[![Version](https://img.shields.io/badge/version-2.2.0-blue)](https://packagist.org/packages/shegerpay/sdk)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

## Install

```bash
composer require shegerpay/sdk
```

## Quick Start

```php
require_once 'ShegerPay.php';
use ShegerPay\ShegerPay;

$client = new ShegerPay('sk_live_YOUR_API_KEY');

// Verify payment
$result = $client->verify('FT26062K7WMY', 1000, 'cbe');
echo $result->verified ? 'Payment verified!' : 'Not verified';

// Verify from screenshot
$image = base64_encode(file_get_contents('receipt.png'));
$result = $client->verifyImage(['image' => $image, 'provider' => 'cbe']);

// Create payment link
$link = $client->createPaymentLink(['title' => 'Order #1234', 'amount' => 1500]);
echo $link['url'];
```

## Supported Providers

`cbe` · `telebirr` · `boa` · `awash` · `ebirr_kaafi` · `ebirr_coop`

## Docs

https://shegerpay.com/docs | support@shegerpay.com
