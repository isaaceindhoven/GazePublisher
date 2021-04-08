# GazePublisher

This library is used for emitting events from a backend to the [GazeHub](https://gitlab.isaac.nl/study/php-chapter/real-time-ui-updates/gazehub)

## ⚙️ Installation

### Add Gitlab repo link to your composer.json
```js
"repositories": [
    {
        "type": "vcs",
        "url": "ssh://git@gitlab.isaac.local/study/php-chapter/real-time-ui-updates/gazepublisher.git"
    }
]

"require": {
    ...
    "isaac/gaze" : "dev-master",
    ...
}

```

### Install the composer package
```shell
composer update isaac/gaze
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
GAZEHUB_URL="http://localhost:8000"

# the path location of the primairy key file
PRIVATE_KEY_PATH="./private.key"
```

### Add Gaze as a service in config/services.yaml
```yaml
# file: config/services.yaml

services:
    # ...
    ISAAC\GazePublisher\Gaze:
        arguments:
            $hubUrl: '%env(GAZEHUB_URL)%'
            $privateKey: '../%env(PRIVATE_KEY_PATH)%'
            $maxTries: 3
            $ignoreErrors: false
```

### Add **/token** endpoint

```php
/**
 * @Route("/token", name="token")
 */
public function index(Gaze $gaze): Response
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
GAZEHUB_URL="http://localhost:8000"

# the path location of the primairy key file
PRIVATE_KEY_PATH="./private.key"
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
php artisan make:provider GazeProvider
```

And add the following in the register method:
```php
$this->app->singleton(Gaze::class, function() {
    return new Gaze(config('gaze.hubUrl'), config('gaze.privateKeyPath'));
});
```

### Add **/token** endpoint
```php
public function index(Gaze $gaze) {
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
$gaze = new Gaze(
    "http://localhost:3333",    // $hubUrl -> Url to the hub
    __DIR__ . "/private.key",   // $privateKey -> Path to your private.key file
    3,                          // $maxTries -> Max tries for a single emit
    false                       // $ignoreErrors -> If set to true it will not throw errors if emit fails
);


/**
 * Example: 1
 * This will emit an new event to the hub with the name "ProductUpdated/1"
 * The payload that will be send is $product
 * The only listerners that will recieve the event are users with the role "admin"
 */
$gaze->emit("ProductUpdated/1", $product, "admin");


/**
 * Example: 2
 * This will emit an new event to the hub with the name "ProductCreated"
 * The payload that will be send is $newProduct
 * The role is not specified, thus it will be recieved by everyone
 */
$gaze->emit("ProductCreated", $newProduct);
```