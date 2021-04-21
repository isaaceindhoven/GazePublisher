# GazePublisher

This library is used for emitting events from a backend to the [GazeHub](https://gitlab.isaac.nl/study/php-chapter/real-time-ui-updates/gazehub)

## ⚙️ Installation

### Add Gitlab repo link to your composer.json
```js
'repositories': [
    {
        'type': 'vcs',
        'url': 'ssh://git@gitlab.isaac.local/study/php-chapter/real-time-ui-updates/gazepublisher.git'
    }
]
```

### Install the composer package
```shell
composer require isaac/gazepublisher
```

### Creating a Public and Private keypair

> GazePublisher uses JWT token which are encryped using a public and private keypair.<br/>
To create this pair run the following command in your terminal:

```shell
# Generate a private key with the name 'private.key'
> openssl genrsa -out private.key 4096

# Extract a public key with the name 'public.key'
> openssl rsa -in private.key -outform PEM -pubout -out public.key
```

## Symfony

### Adding .env variables
```dotenv
# url where the hub is hosted at
GAZEHUB_URL='http://localhost:8000'

# the path location of the primairy key file
PRIVATE_KEY_PATH='./private.key'
```

### Add Gaze as a service in config/services.yaml

```yaml
# file: config/services.yaml

services:
    ISAAC\GazePublisher\GazePublisher:
        factory: ['App\Factory\GazePublisherFactory', 'create']
```

### Create the factory
```php
<?php

// file: src/Factory/GazePublisherFactory.php

namespace App\Factory;

use ISAAC\GazePublisher\GazePublisher;
use Symfony\Component\HttpKernel\KernelInterface;

class GazePublisherFactory
{​​​​​
    public static function create(KernelInterface $kernel)
    {​​​​​
        $hubUrl = $_ENV['GAZEHUB_URL'];
        $privateKeyPath = realpath($kernel->getProjectDir(). '/' . $_ENV['PRIVATE_KEY_PATH']);
        $privateKeyContents = file_get_contents($privateKeyPath);
        return new GazePublisher($hubUrl, $privateKeyContents);
    }​​​​​
}​​​​​
```

### Add **/token** endpoint

> When a client connects to GazeHub it needs to authenticate itself. GazeHub has no knowledge about your users or auth-method in your backend (Symfony, Laravel or Magento). The `/token` endpoint is defined in your backend to generate a JWT token for the client to use when communicating with the GazeHub.

```php
/**
 * @Route('/token', name='token')
 */
public function index(GazePublisher $gaze): Response
{
    return new JsonResponse([
        'token' => $gaze->generateClientToken($this->getUser()->getRoles())
    ]);
}
```

## Laravel

### Adding .env variables
```dotenv
# url where the hub is hosted at
GAZEHUB_URL='http://localhost:8000'

# the path location of the primairy key file
PRIVATE_KEY_PATH='./private.key'
```

### Add config file
Add a config file in /config/gaze.php and add the following content:
```php
<?php
return [
    'hubUrl' => env('GAZEHUB_URL'),
    'tokenUrl' => env('GAZEHUB_TOKEN_URL'),
    'privateKeyPath' => __DIR__ . '/../' . env('PRIVATE_KEY_PATH')
];
```

### Create Gaze provider
```shell script
php artisan make:provider GazePublisherProvider
```

And add the following in the register method:
```php
$this->app->singleton(GazePublisher::class, function() {
    $privateKeyPath = base_path(config('gaze.privateKeyPath'))
    $privateKeyContents = file_get_contents($privateKeyPath);
    return new GazePublisher(config('gaze.hubUrl'), $privateKeyContents);
});
```

### Add **/token** endpoint
```php
public function index(GazePublisher $gaze) {
    return response()->json([
        'token' => $gaze->generateClientToken(['user'])
    ]);
}
```

And register this route in /routes/web.php
```php
Route::get('/token', [\App\Http\Controllers\TokenController::class, 'token']);
```

## ⚡️ Usage

```php
/**
 * Example: 1
 * This will emit an new event to the hub with the name 'ProductUpdated/1'
 * The payload that will be send is $product
 * The only listerners that will recieve the event are users with the role 'admin'
 */
$gaze->emit('ProductUpdated/1', $product, 'admin');


/**
 * Example: 2
 * This will emit an new event to the hub with the name 'ProductCreated'
 * The payload that will be send is $newProduct
 * The role is not specified, thus it will be recieved by everyone
 */
$gaze->emit('ProductCreated', $newProduct);
```
