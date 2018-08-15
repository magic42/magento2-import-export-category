# Magento 2 Import Export Category

The Magento 2 Import Export Category extension helps you to Import/Export Category.

# Installation Instruction

## Install With Composer
```BASH
# Add Composer Repository
composer config repositories.navin git git@github.com:mobilefunuk/magento2-import-export-category.git

# Install Module with Composer
composer require navin/importexportcategory:"*"

# Enable Module
php bin/magento module:enable Navin_Importexportcategory

# Upgrade Magento Database
php bin/magento setup:upgrade

# Redeploy Static Content
php bin/magento setup:static-content:deploy

# Flush Magento Cache
php bin/magento cache:flush
```

## Manual Install

- Create a new directory at `<magento_root>/app/code/Navin/Importexportcategory`
- Copy or clone this repository into this directory
- `ssh` to your magento instance
- `cd` to your Magento Root directory
- Run command `php bin/magento module:enable Navin_Importexportcategory`
- Run command `php bin/magento setup:upgrade`
- Run command `php bin/magento setup:static-content:deploy`
- And finally Flush the Magento Cache with `php bin/magento cache:flush`

### Menu
![Left Menu](https://raw.githubusercontent.com/navinbhudiya/all-module-screenshots/master/Importexportcategory/menu.png)

### Import Category
![Import Category](https://raw.githubusercontent.com/navinbhudiya/all-module-screenshots/master/Importexportcategory/import.png)

### Export Category & Sample File
![Export Category & Sample File](https://raw.githubusercontent.com/navinbhudiya/all-module-screenshots/master/Importexportcategory/import.png)
