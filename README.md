# Pay Green Climate Kit

Ce module permet à vos clients de participer financièrement à des projets de compensation du coût carbone de leurs achats sur votre boutique
grâce au [ClimateKIT de PayGreen](https://www.paygreen.io/climatekit/)

À tout moment, votre client peut connaitre l'empreinte carbone générée par l'utilisation de votre boutique. Avant de procéder au paiement,
il pourra ajouter à son panier une contribution à un projet environnemental qui permettra de neutraliser son empreinte carbone.

<img src="https://raw.githubusercontent.com/PayGreen/carbon-bot-doc/main/doc/presentation.jpg">

Pour commencer, vous devez créer un compte PayGreen Climate ici : https://climatekit.paygreen.fr, et renseigner les informations nécessaires.

### Installation

#### Avec Composer

Ajoutez le module au composer.json de votre installation

```
composer require thelia/paygreen-climate-kit-module
```

#### Manuellement

Vous pouvez aussi installer le module manuellement, mais vous devrez ajouter ses dépendances à votre `composer.`json` :

    "php-http/curl-client": "^2.2",
    "paygreen/paygreen-php": "^1.2",
    "mobiledetect/mobiledetectlib": "^2.8"

### Configuration

Une fois le module installé, vous devez le configurer, pour indiquer les identifiants PayGreen Climate, choisir le mode d'opération,
le thème couleur du Bot... Une fois les identifiants entrés vous devrez envoyer votre catalogue de produits vers PayGreen, soit
automatiquement, soit manuellement.

Une fois le module installé et configuré, les informations sur l'empreinte carbone sont affichées sur chaque page du site si vous le souhaitez.

Le module associé à chaque commande un Footprint ID, qui permet de faire le lien entre PayGreen et la commande. Vous retrouverez cet ID dans
votre back-office et dans le tableau de bord PayGreen Climate.

### Loop

```
{loop type="paygreen_order_footprint" ...}
```

Permet d'obtenir le footprint ID d'une commande

|Argument |Description |
|---      |---         |
|**order_id** | L'ID d'une commande, exemple: order_id=12 |

## Documentations techniques

Climate Kit API : https://api-climatekit.paygreen.fr/documentation/climate

PayGreen SDK : https://github.com/PayGreen/paygreen-php

PayGreen Carbon Bot documentation : https://github.com/PayGreen/carbon-bot-doc

---

This module allows your customers to compensate the carbon cost of their purchases on your store by contributing to carbon offset projects
thanks to the [ClimateKIT from PayGreen](https://www.paygreen.io/climatekit/)

At any time, your customer can check the carbon footprint generated by the use of your store. Before making payment,
he can add to his basket a contribution to an environmental project that will neutralize his carbon footprint.

<img src="https://raw.githubusercontent.com/PayGreen/carbon-bot-doc/main/doc/presentation.jpg">

To start, you must create a PayGreen Climate account here: https://climatekit.paygreen.fr, and provide the required information.

### Installation

#### With Composer

Add the module to composer.json of your Thelia installation

```
composer require thelia/paygreen-climate-kit-module
```

#### Manually

You can also install the module manually, but you will need to add its dependencies to your `composer.`json`:

    "php-http/curl-client": "^2.2",
    "paygreen/paygreen-php": "^1.2",
    "mobiledetect/mobiledetectlib": "^2.8"

### Setup

Once the module is installed, you must configure it, to provide the PayGreen Climate identifiers, choose the operation mode,
the color theme of the widgets... Once the identifiers have been entered, you will have to send your product catalog to PayGreen,
either automatically or manually.

Once the module is installed and configured, the carbon footprint information is displayed on every page of the site.

The module assciates a Footprint ID to every order, which creates a link between PayGreen and the order. You will find this Footprint ID in
your back office and in the PayGreen Climate dashboard.

### Loop

```
{loop type="paygreen_order_footprint" ...}
```

Allows you to get the footprint ID of an order

|Argument |Description |
|--- |--- |
|**order_id** | The ID of an order, example: order_id="12" |

## Technical Documentations

Climate Kit API : https://api-climatekit.paygreen.fr/documentation/climate

PayGreen SDK : https://github.com/PayGreen/paygreen-php

PayGreen Carbon Bot documentation : https://github.com/PayGreen/carbon-bot-doc
