Migration Generator
===================
Migration Generator for Yii2

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist sankam-nikolya/yii2-gii-migartion "*"
```

or add

```
"sankam-nikolya/yii2-gii-migartion": "*"
```

to the require section of your `composer.json` file.


Usage
-----

Once the extension is installed, simply use it in your code by  :


```php
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        ...
        'generators' => [
            'migration' => [
                'class' => '\sankam\gii\migration\generator',
                'templates' => [
                    'default' => '@vendor/sankam-nikolya/yii2-gii-migartion/default',
                    'sql' => '@vendor/sankam-nikolya/yii2-gii-migartion/sql',
                ],
            ],
        ],
        ...
    ]
```