# Pages


## Usage

Create your page, with his Thelia block and access it by his own url.

## Requirements

The `imagick` PHP extension is optional. When it is available, the module builds a preview image from the first page of any PDF document attached to a page. Without it, PDF documents are still listed, only without that preview thumbnail. Nothing else in the module needs it, so a standard PHP install runs fine.

## Installation
### Composer

Add it in your main thelia composer.json file

```
composer require thelia/page-module:~1.0.1
```
