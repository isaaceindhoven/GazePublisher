# GazePHP

> This library is used for emitting events from a backend to the [GazeHub](https://gitlab.isaac.nl/study/php-chapter/real-time-ui-updates/gazehub)

## ⚙️ Installation

```bash
composer install @study/gazephp
```

### Creating a Public and Private keypair

GazePHP uses JWT token which are encryped using a public and private keypair.<br/>
To create this pair run the following command in your terminal:

```shell
# Generate a private key with the name 'private.key'
> openssl genrsa -out private.key 4096

# Extract a public key with the name 'public.key'
> openssl rsa -in private.key -outform PEM -pubout -out public.key
```

### Symfony

```php
    // add to service container
```

### Laravel

```php
    // add to service container
```

## 🧑🏻‍💻 Usage

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