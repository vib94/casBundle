# casBundle
Symfony cas bundle

Installation
============

### Step 1: Download the Bundle

add this to your composer.json file : 
```
"repositories": [
{
    "type": "vcs",
    "url": "https://github.com/vib94/casBundle.git"
}],
```

Open a command console, enter your project directory and execute:

```console
$ composer require vib94/cas-bundle
```
### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `app/AppKernel.php` file of your project:

```php
<?php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            // ...
            new <vendor>\<bundle-name>\<bundle-long-name>(),
        );

        // ...
    }

    // ...
}
```




cas:
    resource: "@CasBundle/Resources/config/routing.yml"
    prefix:   /


            new Cas\CasBundle\CasBundle(),
